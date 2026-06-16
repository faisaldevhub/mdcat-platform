<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Recommendation_Service {

    /**
     * Accuracy threshold — chapters below this are considered weak.
     * Matches the constant in Performance Analytics.
     */
    const WEAK_THRESHOLD    = 60;
    const AVERAGE_THRESHOLD = 80;

    /**
     * Maximum number of items per recommendation category.
     */
    const MAX_PRIORITY_TOPICS   = 5;
    const MAX_WEAK_SUBJECTS     = 3;
    const MAX_REVISION_CHAPTERS = 3;

    /**
     * Scoring weights for priority calculations.
     *
     * Accuracy is the strongest signal — low accuracy means the student
     * is struggling. Completion gaps indicate unexplored content.
     */
    const WEIGHT_ACCURACY   = 0.7;
    const WEIGHT_COMPLETION = 0.3;

    /**
     * Generate the complete study plan for a student.
     *
     * Aggregates all 5 recommendation types into a single response.
     * Each sub-method applies scoring logic to the provided data —
     * no database queries originate from this class.
     *
     * When called from the dashboard, pre-fetched data is passed via
     * $context to avoid duplicate service queries. When called from
     * the standalone AJAX endpoint, $context is empty and data is
     * fetched directly from the underlying services.
     *
     * @param int   $user_id WordPress user ID.
     * @param array $context Optional pre-fetched data from the dashboard.
     *                       Supported keys: chapter_performance, subject_performance,
     *                       subject_completion, chapter_completion, continue_learning,
     *                       streak_summary.
     * @return array|WP_Error Complete study plan data.
     */
    public static function get_study_plan( $user_id, $context = [] ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        // Use pre-fetched data from context when available, otherwise fetch from services.
        $chapter_performance = isset($context['chapter_performance'])
            ? $context['chapter_performance']
            : MDCAT_Platform_Performance_Analytics::get_chapter_performance($user_id);

        $subject_performance = isset($context['subject_performance'])
            ? $context['subject_performance']
            : MDCAT_Platform_Performance_Analytics::get_subject_performance($user_id);

        $subject_completion = isset($context['subject_completion'])
            ? $context['subject_completion']
            : MDCAT_Platform_Progress_Service::get_subject_completion($user_id);

        $chapter_completion = isset($context['chapter_completion'])
            ? $context['chapter_completion']
            : MDCAT_Platform_Progress_Service::get_chapter_completion($user_id);

        $continue_learning = isset($context['continue_learning'])
            ? $context['continue_learning']
            : MDCAT_Platform_Progress_Service::get_continue_learning($user_id);

        $streak_summary = isset($context['streak_summary'])
            ? $context['streak_summary']
            : MDCAT_Platform_Streak_Service::get_streak_summary($user_id);

        // Wrong questions are only used by the study planner, never pre-fetched.
        $wrong_questions = MDCAT_Platform_Revision_Service::get_wrong_questions($user_id);

        // Normalize all data to safe arrays.
        $chapter_performance = is_wp_error($chapter_performance) ? [] : $chapter_performance;
        $subject_performance = is_wp_error($subject_performance) ? [] : $subject_performance;
        $subject_completion  = is_wp_error($subject_completion)  ? [] : $subject_completion;
        $chapter_completion  = is_wp_error($chapter_completion)  ? [] : $chapter_completion;
        $continue_learning   = is_wp_error($continue_learning)   ? [] : $continue_learning;
        $streak_summary      = is_wp_error($streak_summary)      ? [] : $streak_summary;
        $wrong_questions     = is_wp_error($wrong_questions)      ? [] : $wrong_questions;

        $priority_topics          = self::build_priority_topics($chapter_performance, $chapter_completion);
        $weak_subjects            = self::build_weak_subjects($subject_performance, $subject_completion);
        $revision_recommendations = self::build_revision_recommendations($wrong_questions);
        $daily_plan               = self::build_daily_plan(
            $priority_topics,
            $continue_learning,
            $revision_recommendations,
            $streak_summary
        );

        return [
            'priority_topics'          => $priority_topics,
            'weak_subjects'            => $weak_subjects,
            'continue_learning'        => $continue_learning,
            'revision_recommendations' => $revision_recommendations,
            'daily_plan'               => $daily_plan,
        ];
    }

    /**
     * Build priority topics from chapter performance data.
     *
     * Identifies chapters with accuracy below AVERAGE_THRESHOLD,
     * scores them by a weighted combination of accuracy and completion,
     * and returns the top N weakest chapters.
     *
     * @param array $chapter_performance Chapter-level accuracy data.
     * @param array $chapter_completion  Chapter-level completion data.
     * @return array Prioritized chapter recommendations.
     */
    private static function build_priority_topics( $chapter_performance, $chapter_completion ) {

        if (empty($chapter_performance)) {
            return [];
        }

        // Index chapter completion by chapter_id for fast lookup.
        $completion_map = [];

        foreach ($chapter_completion as $chapter) {
            $chapter_id = isset($chapter['chapter_id']) ? absint($chapter['chapter_id']) : 0;

            if ($chapter_id) {
                $completion_map[$chapter_id] = isset($chapter['completion_percentage'])
                    ? (float) $chapter['completion_percentage']
                    : 0;
            }
        }

        $scored_chapters = [];

        foreach ($chapter_performance as $chapter) {
            $accuracy   = isset($chapter['accuracy_percentage']) ? (float) $chapter['accuracy_percentage'] : 0;
            $chapter_id = isset($chapter['chapter_id']) ? absint($chapter['chapter_id']) : 0;
            $completion = isset($completion_map[$chapter_id]) ? $completion_map[$chapter_id] : 0;

            // Only include chapters below the average threshold.
            if ($accuracy >= self::AVERAGE_THRESHOLD) {
                continue;
            }

            $priority_score = self::calculate_priority_score($accuracy, $completion);
            $label          = $accuracy < self::WEAK_THRESHOLD ? 'Weak' : 'Average';

            $scored_chapters[] = [
                'chapter_id'       => $chapter_id,
                'chapter_title'    => isset($chapter['chapter_title']) ? $chapter['chapter_title'] : '',
                'subject_title'    => isset($chapter['subject_title']) ? $chapter['subject_title'] : '',
                'accuracy'         => $accuracy,
                'completion'       => $completion,
                'priority_score'   => $priority_score,
                'performance_label'=> $label,
                'action'           => __('Revise this chapter', 'mdcat-platform'),
            ];
        }

        // Sort by priority_score DESC (highest priority first).
        usort($scored_chapters, function ($a, $b) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        return array_slice($scored_chapters, 0, self::MAX_PRIORITY_TOPICS);
    }

    /**
     * Build weak subject recommendations.
     *
     * Combines subject accuracy with subject completion to produce a
     * composite priority score. Subjects with low accuracy AND low
     * completion rank highest.
     *
     * @param array $subject_performance Subject-level accuracy data.
     * @param array $subject_completion  Subject-level completion data.
     * @return array Weak subjects ranked by composite score.
     */
    private static function build_weak_subjects( $subject_performance, $subject_completion ) {

        if (empty($subject_performance)) {
            return [];
        }

        // Index subject completion by subject_id.
        $completion_map = [];

        foreach ($subject_completion as $subject) {
            $subject_id = isset($subject['subject_id']) ? absint($subject['subject_id']) : 0;

            if ($subject_id) {
                $completion_map[$subject_id] = isset($subject['completion_percentage'])
                    ? (float) $subject['completion_percentage']
                    : 0;
            }
        }

        $scored_subjects = [];

        foreach ($subject_performance as $subject) {
            $accuracy   = isset($subject['accuracy_percentage']) ? (float) $subject['accuracy_percentage'] : 0;
            $subject_id = isset($subject['subject_id']) ? absint($subject['subject_id']) : 0;
            $completion = isset($completion_map[$subject_id]) ? $completion_map[$subject_id] : 0;

            // Only include subjects below the strong threshold.
            if ($accuracy >= self::AVERAGE_THRESHOLD) {
                continue;
            }

            $priority_score = self::calculate_priority_score($accuracy, $completion);
            $label          = $accuracy < self::WEAK_THRESHOLD ? 'Weak' : 'Needs Practice';

            $scored_subjects[] = [
                'subject_id'       => $subject_id,
                'subject_title'    => isset($subject['subject_title']) ? $subject['subject_title'] : '',
                'accuracy'         => $accuracy,
                'completion'       => $completion,
                'priority_score'   => $priority_score,
                'performance_label'=> $label,
                'action'           => __('Focus on this subject', 'mdcat-platform'),
            ];
        }

        usort($scored_subjects, function ($a, $b) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        return array_slice($scored_subjects, 0, self::MAX_WEAK_SUBJECTS);
    }

    /**
     * Build revision recommendations from wrong questions data.
     *
     * Groups wrong questions by chapter, counts total wrong per chapter,
     * and returns the chapters with the most wrong answers.
     *
     * @param array $wrong_questions Wrong question data from Revision Service.
     * @return array Chapter-level revision recommendations.
     */
    private static function build_revision_recommendations( $wrong_questions ) {

        if (empty($wrong_questions)) {
            return [
                'chapters'            => [],
                'total_wrong_count'   => 0,
                'total_bookmark_count'=> 0,
            ];
        }

        // Group wrong questions by chapter using stable chapter_id.
        $chapter_groups = [];

        foreach ($wrong_questions as $question) {
            $chapter_id    = isset($question['chapter_id']) ? absint($question['chapter_id']) : 0;
            $chapter_title = isset($question['chapter_title']) ? $question['chapter_title'] : __('Unknown Chapter', 'mdcat-platform');
            $subject_title = isset($question['subject_title']) ? $question['subject_title'] : __('Unknown Subject', 'mdcat-platform');
            $wrong_count   = isset($question['wrong_count']) ? absint($question['wrong_count']) : 1;

            // Use chapter_id as the grouping key. Fall back to title hash
            // only for orphaned questions where the chapter join returned NULL.
            $key = $chapter_id > 0 ? $chapter_id : md5($chapter_title . '|' . $subject_title);

            if (!isset($chapter_groups[$key])) {
                $chapter_groups[$key] = [
                    'chapter_id'      => $chapter_id,
                    'chapter_title'   => $chapter_title,
                    'subject_title'   => $subject_title,
                    'wrong_count'     => 0,
                    'question_count'  => 0,
                ];
            }

            $chapter_groups[$key]['wrong_count']    += $wrong_count;
            $chapter_groups[$key]['question_count'] += 1;
        }

        // Sort by wrong_count DESC.
        usort($chapter_groups, function ($a, $b) {
            return $b['wrong_count'] <=> $a['wrong_count'];
        });

        $top_chapters = array_slice($chapter_groups, 0, self::MAX_REVISION_CHAPTERS);

        // Add action labels.
        foreach ($top_chapters as &$chapter) {
            $chapter['action'] = sprintf(
                /* translators: %d: number of wrong answers */
                __('You have %d wrong answers — revise this chapter', 'mdcat-platform'),
                $chapter['wrong_count']
            );
        }

        return [
            'chapters'            => $top_chapters,
            'total_wrong_count'   => count($wrong_questions),
            'total_bookmark_count'=> 0,
        ];
    }

    /**
     * Build the daily study plan.
     *
     * Produces exactly 3 actionable items for today:
     * 1. Revision — weakest chapter that needs review
     * 2. New Learning — next uncompleted collection in curriculum order
     * 3. Practice — chapter with most wrong answers to re-practice
     *
     * Includes a streak-awareness message to encourage consistency.
     *
     * @param array $priority_topics          Scored priority chapters.
     * @param array $continue_learning        Continue learning data.
     * @param array $revision_recommendations Revision chapter data.
     * @param array $streak_summary           Current streak data.
     * @return array Structured daily plan with 3 items + streak context.
     */
    private static function build_daily_plan( $priority_topics, $continue_learning, $revision_recommendations, $streak_summary ) {

        $items = [];

        // Item 1: Revision — weakest chapter.
        if (!empty($priority_topics)) {
            $weakest = $priority_topics[0];
            $items[] = [
                'type'    => 'revision',
                'icon'    => '📖',
                'title'   => __('Revise', 'mdcat-platform'),
                'target'  => $weakest['chapter_title'],
                'context' => $weakest['subject_title'],
                'detail'  => sprintf(
                    /* translators: %s: accuracy percentage */
                    __('Accuracy: %s%% — %s', 'mdcat-platform'),
                    $weakest['accuracy'],
                    $weakest['performance_label']
                ),
            ];
        }

        // Item 2: New Learning — next uncompleted collection.
        if (!empty($continue_learning) && empty($continue_learning['curriculum_completed'])) {
            $items[] = [
                'type'    => 'learning',
                'icon'    => '📚',
                'title'   => __('Continue', 'mdcat-platform'),
                'target'  => isset($continue_learning['collection_title']) ? $continue_learning['collection_title'] : '',
                'context' => isset($continue_learning['subject_title']) ? $continue_learning['subject_title'] : '',
                'detail'  => __('Next quiz in your learning path', 'mdcat-platform'),
            ];
        }

        // Item 3: Practice — chapter with most wrong answers.
        $revision_chapters = isset($revision_recommendations['chapters']) ? $revision_recommendations['chapters'] : [];

        if (!empty($revision_chapters)) {
            $most_wrong = $revision_chapters[0];
            $items[] = [
                'type'    => 'practice',
                'icon'    => '🔄',
                'title'   => __('Practice', 'mdcat-platform'),
                'target'  => $most_wrong['chapter_title'],
                'context' => $most_wrong['subject_title'],
                'detail'  => sprintf(
                    /* translators: %d: number of wrong answers to review */
                    __('%d wrong answers to review', 'mdcat-platform'),
                    $most_wrong['wrong_count']
                ),
            ];
        }

        // Streak context message.
        $current_streak = isset($streak_summary['current_streak']) ? absint($streak_summary['current_streak']) : 0;

        if ($current_streak > 0) {
            $streak_message = sprintf(
                /* translators: %d: current streak day count */
                __('🔥 Keep your %d-day streak going!', 'mdcat-platform'),
                $current_streak
            );
        } else {
            $streak_message = __('🔥 Start a new streak today!', 'mdcat-platform');
        }

        return [
            'items'          => $items,
            'streak_message' => $streak_message,
            'current_streak' => $current_streak,
        ];
    }

    /**
     * Calculate a weighted priority score for a chapter or subject.
     *
     * Higher score = higher priority (needs more attention).
     *
     * @param float $accuracy   Accuracy percentage (0–100).
     * @param float $completion Completion percentage (0–100).
     * @return float Priority score (0–100).
     */
    private static function calculate_priority_score( $accuracy, $completion ) {

        $accuracy_deficit   = 100 - (float) $accuracy;
        $completion_deficit = 100 - (float) $completion;

        return round(
            ($accuracy_deficit * self::WEIGHT_ACCURACY) + ($completion_deficit * self::WEIGHT_COMPLETION),
            2
        );
    }
}
