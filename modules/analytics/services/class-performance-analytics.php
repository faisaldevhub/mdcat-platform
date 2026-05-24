<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Performance_Analytics {

    const STRONG_THRESHOLD  = 80;
    const AVERAGE_THRESHOLD = 60;

    /**
     * Calculate subject-level performance for a student.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_subject_performance( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $tables = self::get_tables();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT subjects.id AS subject_id, subjects.name AS subject_title,
                    COUNT(answers.id) AS total_questions,
                    COALESCE(SUM(answers.is_correct), 0) AS correct_answers,
                    COUNT(answers.id) - COALESCE(SUM(answers.is_correct), 0) AS wrong_answers
                FROM {$tables['answers']} AS answers
                INNER JOIN {$tables['attempts']} AS attempts ON answers.attempt_id = attempts.id
                INNER JOIN {$tables['questions']} AS questions ON answers.question_id = questions.id
                INNER JOIN {$tables['collections']} AS collections ON questions.collection_id = collections.id
                INNER JOIN {$tables['chapters']} AS chapters ON collections.chapter_id = chapters.id
                INNER JOIN {$tables['subjects']} AS subjects ON chapters.subject_id = subjects.id
                WHERE attempts.user_id = %d
                    AND attempts.status = %s
                GROUP BY subjects.id, subjects.name
                ORDER BY (COALESCE(SUM(answers.is_correct), 0) / COUNT(answers.id)) DESC, subjects.name ASC",
                $user_id,
                'completed'
            )
        );

        return self::format_subject_rows($rows);
    }

    /**
     * Calculate chapter-level performance for a student.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_chapter_performance( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $tables = self::get_tables();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT chapters.id AS chapter_id, chapters.name AS chapter_title, subjects.name AS subject_title,
                    COUNT(answers.id) AS total_questions,
                    COALESCE(SUM(answers.is_correct), 0) AS correct_answers,
                    COUNT(answers.id) - COALESCE(SUM(answers.is_correct), 0) AS wrong_answers
                FROM {$tables['answers']} AS answers
                INNER JOIN {$tables['attempts']} AS attempts ON answers.attempt_id = attempts.id
                INNER JOIN {$tables['questions']} AS questions ON answers.question_id = questions.id
                INNER JOIN {$tables['collections']} AS collections ON questions.collection_id = collections.id
                INNER JOIN {$tables['chapters']} AS chapters ON collections.chapter_id = chapters.id
                INNER JOIN {$tables['subjects']} AS subjects ON chapters.subject_id = subjects.id
                WHERE attempts.user_id = %d
                    AND attempts.status = %s
                GROUP BY chapters.id, chapters.name, subjects.name
                ORDER BY (COALESCE(SUM(answers.is_correct), 0) / COUNT(answers.id)) ASC, subjects.name ASC, chapters.name ASC",
                $user_id,
                'completed'
            )
        );

        return self::format_chapter_rows($rows);
    }

    /**
     * Format subject aggregate rows.
     *
     * @param array $rows Raw database rows.
     * @return array
     */
    private static function format_subject_rows( $rows ) {

        $performance = [];

        foreach ((array) $rows as $row) {
            $total    = absint($row->total_questions);
            $correct  = absint($row->correct_answers);
            $accuracy = self::calculate_accuracy($correct, $total);

            $performance[] = [
                'subject_id'          => absint($row->subject_id),
                'subject_title'       => $row->subject_title,
                'total_questions'     => $total,
                'correct_answers'     => $correct,
                'wrong_answers'       => absint($row->wrong_answers),
                'accuracy_percentage' => $accuracy,
            ];
        }

        return $performance;
    }

    /**
     * Format chapter aggregate rows.
     *
     * @param array $rows Raw database rows.
     * @return array
     */
    private static function format_chapter_rows( $rows ) {

        $performance = [];

        foreach ((array) $rows as $row) {
            $total    = absint($row->total_questions);
            $correct  = absint($row->correct_answers);
            $accuracy = self::calculate_accuracy($correct, $total);

            $performance[] = [
                'chapter_id'          => absint($row->chapter_id),
                'chapter_title'       => $row->chapter_title,
                'subject_title'       => $row->subject_title,
                'total_questions'     => $total,
                'correct_answers'     => $correct,
                'wrong_answers'       => absint($row->wrong_answers),
                'accuracy_percentage' => $accuracy,
                'performance_label'   => self::get_performance_label($accuracy),
            ];
        }

        return $performance;
    }

    /**
     * Calculate accuracy percentage.
     *
     * @param int $correct Correct answers.
     * @param int $total   Total questions.
     * @return float
     */
    private static function calculate_accuracy( $correct, $total ) {

        $correct = absint($correct);
        $total   = absint($total);

        if (!$total) {
            return 0;
        }

        return round(($correct / $total) * 100, 2);
    }

    /**
     * Convert accuracy to a performance label.
     *
     * @param float $accuracy Accuracy percentage.
     * @return string
     */
    private static function get_performance_label( $accuracy ) {

        $accuracy = (float) $accuracy;

        if ($accuracy >= self::STRONG_THRESHOLD) {
            return 'Strong';
        }

        if ($accuracy >= self::AVERAGE_THRESHOLD) {
            return 'Average';
        }

        return 'Weak';
    }

    /**
     * Get table names used by analytics queries.
     *
     * @return array
     */
    private static function get_tables() {

        global $wpdb;

        return [
            'attempts'    => MDCAT_Platform_Attempts_Handler::get_attempts_table_name(),
            'answers'     => MDCAT_Platform_Attempts_Handler::get_attempt_answers_table_name(),
            'questions'   => $wpdb->prefix . 'mdcat_questions',
            'collections' => $wpdb->prefix . 'mdcat_collections',
            'chapters'    => $wpdb->prefix . 'mdcat_chapters',
            'subjects'    => $wpdb->prefix . 'mdcat_subjects',
        ];
    }
}
