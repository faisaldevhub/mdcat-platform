<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Admin_Performance_Service {

    const STRONG_THRESHOLD  = 80;
    const AVERAGE_THRESHOLD = 60;

    /**
     * Generate a platform-wide subject performance report.
     *
     * Aggregates all students' answers per subject to produce overall
     * accuracy metrics. Uses the same JOIN chain as the student-facing
     * Performance Analytics but removes the user_id filter so it spans
     * the entire platform.
     *
     * @return array
     */
    public static function get_subject_performance_report() {

        global $wpdb;

        $tables = self::get_tables();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    subjects.id AS subject_id,
                    subjects.name AS subject_title,
                    COUNT(answers.id) AS total_questions,
                    COALESCE(SUM(answers.is_correct), 0) AS correct_answers,
                    COUNT(answers.id) - COALESCE(SUM(answers.is_correct), 0) AS wrong_answers
                FROM {$tables['answers']} AS answers
                INNER JOIN {$tables['attempts']} AS attempts
                    ON answers.attempt_id = attempts.id
                INNER JOIN {$tables['questions']} AS questions
                    ON answers.question_id = questions.id
                INNER JOIN {$tables['collections']} AS collections
                    ON questions.collection_id = collections.id
                INNER JOIN {$tables['chapters']} AS chapters
                    ON collections.chapter_id = chapters.id
                INNER JOIN {$tables['subjects']} AS subjects
                    ON chapters.subject_id = subjects.id
                WHERE attempts.status = %s
                GROUP BY subjects.id, subjects.name
                ORDER BY subjects.name ASC",
                'completed'
            )
        );

        return self::format_subject_rows($rows);
    }

    /**
     * Get the strongest subjects by platform-wide accuracy.
     *
     * @param int $limit Maximum subjects to return.
     * @return array
     */
    public static function get_strongest_subjects( $limit = 5 ) {

        $report = self::get_subject_performance_report();

        usort($report, function ( $a, $b ) {
            return $b['accuracy_percentage'] <=> $a['accuracy_percentage'];
        });

        return array_slice($report, 0, absint($limit));
    }

    /**
     * Get the weakest subjects by platform-wide accuracy.
     *
     * @param int $limit Maximum subjects to return.
     * @return array
     */
    public static function get_weakest_subjects( $limit = 5 ) {

        $report = self::get_subject_performance_report();

        usort($report, function ( $a, $b ) {
            return $a['accuracy_percentage'] <=> $b['accuracy_percentage'];
        });

        return array_slice($report, 0, absint($limit));
    }

    /**
     * Format subject aggregate rows into structured performance data.
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
                'performance_label'   => self::get_performance_label($accuracy),
            ];
        }

        return $performance;
    }

    /**
     * Calculate accuracy percentage safely.
     *
     * Mirrors the calculation in Performance Analytics to keep
     * classification consistent without coupling to that module.
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
     * Uses the same thresholds as Performance Analytics (80/60)
     * for consistent classification across admin and student views.
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
     * Get table names used by admin performance queries.
     *
     * Reuses Attempts Handler for attempt/answer table names.
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
