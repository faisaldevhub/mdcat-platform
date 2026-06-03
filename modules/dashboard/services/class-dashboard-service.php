<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Dashboard_Service {

    /**
     * Strong/weak classification threshold.
     *
     * Matches the constant defined in Performance Analytics so dashboard
     * partitioning stays consistent with the analytics module.
     */
    const STRONG_THRESHOLD = 80;

    /**
     * Aggregate core dashboard statistics for a student.
     *
     * Uses a single optimized query for attempt-level aggregates (total attempts,
     * correct answers, wrong answers, accuracy) and delegates bookmark counting
     * to the Revision Service to avoid duplicating bookmark logic.
     *
     * Weak topics count is derived from the Performance Analytics subject data
     * so the dashboard never reimplements performance classification.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_dashboard_stats( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $aggregates = self::get_attempt_aggregates($user_id);

        $total_attempts     = absint($aggregates->total_attempts);
        $total_correct      = absint($aggregates->total_correct);
        $total_wrong        = absint($aggregates->total_wrong);
        $total_answered     = $total_correct + $total_wrong;
        $overall_accuracy   = $total_answered ? round(($total_correct / $total_answered) * 100, 2) : 0;

        $bookmarked_count = self::get_bookmarked_count($user_id);
        $weak_topics_count = self::get_weak_topics_count($user_id);

        return [
            'total_attempts'           => $total_attempts,
            'total_correct_answers'    => $total_correct,
            'total_wrong_answers'      => $total_wrong,
            'overall_accuracy'         => $overall_accuracy,
            'bookmarked_questions_count' => $bookmarked_count,
            'weak_topics_count'        => $weak_topics_count,
        ];
    }

    /**
     * Fetch recent completed activity for the dashboard.
     *
     * Delegates entirely to the Attempt History service with a 5-item limit.
     * No additional queries or transformations are performed here.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_recent_activity( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $history = MDCAT_Platform_Attempt_History::get_user_attempt_history(
            $user_id,
            [
                'page'     => 1,
                'per_page' => 5,
            ]
        );

        if (is_wp_error($history)) {
            return $history;
        }

        return isset($history['items']) ? $history['items'] : [];
    }

    /**
     * Build a performance snapshot partitioned into strong and weak subjects.
     *
     * Delegates to Performance Analytics for subject-level data, then
     * classifies each subject using the same threshold the analytics module
     * uses internally. The dashboard never recalculates accuracy — it only
     * reads the pre-computed accuracy_percentage from the analytics response.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_performance_snapshot( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $subject_performance = MDCAT_Platform_Performance_Analytics::get_subject_performance($user_id);

        if (is_wp_error($subject_performance)) {
            return $subject_performance;
        }

        $strong_subjects = [];
        $weak_subjects   = [];

        foreach ((array) $subject_performance as $subject) {
            $accuracy = isset($subject['accuracy_percentage']) ? (float) $subject['accuracy_percentage'] : 0;

            $entry = [
                'subject_id'          => isset($subject['subject_id']) ? absint($subject['subject_id']) : 0,
                'subject_title'       => isset($subject['subject_title']) ? $subject['subject_title'] : '',
                'accuracy_percentage' => $accuracy,
                'total_questions'     => isset($subject['total_questions']) ? absint($subject['total_questions']) : 0,
            ];

            if ($accuracy >= self::STRONG_THRESHOLD) {
                $strong_subjects[] = $entry;
            } else {
                $weak_subjects[] = $entry;
            }
        }

        return [
            'strong_subjects' => $strong_subjects,
            'weak_subjects'   => $weak_subjects,
        ];
    }

    /**
     * Run a single aggregate query for attempt-level statistics.
     *
     * This consolidates total attempts, correct answers, and wrong answers into
     * one database round-trip using SUM over the attempts table that already
     * stores pre-computed per-attempt tallies (set during quiz completion).
     *
     * @param int $user_id WordPress user ID.
     * @return object
     */
    private static function get_attempt_aggregates( $user_id ) {

        global $wpdb;

        $attempts_table = MDCAT_Platform_Attempts_Handler::get_attempts_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(id) AS total_attempts,
                    COALESCE(SUM(correct_answers), 0) AS total_correct,
                    COALESCE(SUM(wrong_answers), 0) AS total_wrong
                FROM {$attempts_table}
                WHERE user_id = %d
                    AND status = %s",
                $user_id,
                'completed'
            )
        );

        if (!$row) {
            return (object) [
                'total_attempts' => 0,
                'total_correct'  => 0,
                'total_wrong'    => 0,
            ];
        }

        return $row;
    }

    /**
     * Get the total number of bookmarked questions for a user.
     *
     * Delegates to the Revision Service and counts the returned items.
     * This avoids duplicating bookmark query logic or touching the bookmarks
     * table directly from the dashboard layer.
     *
     * @param int $user_id WordPress user ID.
     * @return int
     */
    private static function get_bookmarked_count( $user_id ) {

        $bookmarks = MDCAT_Platform_Revision_Service::get_bookmarked_questions($user_id);

        if (is_wp_error($bookmarks) || !is_array($bookmarks)) {
            return 0;
        }

        return count($bookmarks);
    }

    /**
     * Count weak topics from subject performance data.
     *
     * Reuses Performance Analytics to get subject accuracy, then counts
     * subjects below the strong threshold. This keeps classification logic
     * consistent with the analytics module.
     *
     * @param int $user_id WordPress user ID.
     * @return int
     */
    private static function get_weak_topics_count( $user_id ) {

        $subject_performance = MDCAT_Platform_Performance_Analytics::get_subject_performance($user_id);

        if (is_wp_error($subject_performance) || !is_array($subject_performance)) {
            return 0;
        }

        $weak_count = 0;

        foreach ($subject_performance as $subject) {
            $accuracy = isset($subject['accuracy_percentage']) ? (float) $subject['accuracy_percentage'] : 0;

            if ($accuracy < self::STRONG_THRESHOLD) {
                $weak_count++;
            }
        }

        return $weak_count;
    }

    /**
     * Fetch streak data for the dashboard display.
     *
     * Delegates entirely to the Streak Service to avoid duplicating
     * gamification logic inside the dashboard layer.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_streak_data( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        return MDCAT_Platform_Streak_Service::get_streak_summary($user_id);
    }

    /**
     * Fetch subject completion progress for the dashboard display.
     *
     * Delegates entirely to the Progress Service to avoid duplicating
     * progress logic inside the dashboard layer.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_subject_progress( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        return MDCAT_Platform_Progress_Service::get_subject_completion($user_id);
    }
}
