<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Admin_Stats_Service {

    /**
     * Aggregate platform-wide overview statistics.
     *
     * Returns all eight overview cards in a single response.
     * Each metric is fetched via a dedicated private method so individual
     * queries stay simple, indexable, and independently testable.
     *
     * @return array Associative array of platform statistics.
     */
    public static function get_overview_stats() {

        $content_counts     = self::get_content_counts();
        $attempt_aggregates = self::get_attempt_aggregates();
        $student_count      = self::get_student_count();
        $active_streak      = self::get_active_streak_count();

        return [
            'total_students'      => $student_count,
            'total_subjects'      => absint($content_counts->total_subjects),
            'total_chapters'      => absint($content_counts->total_chapters),
            'total_collections'   => absint($content_counts->total_collections),
            'total_questions'     => absint($content_counts->total_questions),
            'total_attempts'      => absint($attempt_aggregates->total_attempts),
            'average_accuracy'    => self::calculate_accuracy(
                absint($attempt_aggregates->total_correct),
                absint($attempt_aggregates->total_answered)
            ),
            'active_streak_users' => $active_streak,
        ];
    }

    /**
     * Count content entities across the platform.
     *
     * Uses four separate COUNT queries rather than subqueries for clarity
     * and because each table is small enough that individual scans are fast.
     * Collections and questions are filtered to active status only.
     *
     * @return object
     */
    private static function get_content_counts() {

        global $wpdb;

        $tables = self::get_tables();

        $row = $wpdb->get_row(
            "SELECT
                (SELECT COUNT(id) FROM {$tables['subjects']}) AS total_subjects,
                (SELECT COUNT(id) FROM {$tables['chapters']}) AS total_chapters,
                (SELECT COUNT(id) FROM {$tables['collections']} WHERE status = 'active') AS total_collections,
                (SELECT COUNT(id) FROM {$tables['questions']} WHERE status = 'active') AS total_questions"
        );

        if (!$row) {
            return (object) [
                'total_subjects'    => 0,
                'total_chapters'    => 0,
                'total_collections' => 0,
                'total_questions'   => 0,
            ];
        }

        return $row;
    }

    /**
     * Aggregate attempt-level statistics across all students.
     *
     * Returns total completed attempts and the raw numerator/denominator
     * for accuracy calculation. Accuracy is computed by the caller so
     * this method stays a pure data-fetch.
     *
     * Uses the existing index on `status` in wp_mdcat_attempts.
     *
     * @return object
     */
    private static function get_attempt_aggregates() {

        global $wpdb;

        $attempts_table = MDCAT_Platform_Attempts_Handler::get_attempts_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(id) AS total_attempts,
                    COALESCE(SUM(correct_answers), 0) AS total_correct,
                    COALESCE(SUM(correct_answers) + SUM(wrong_answers), 0) AS total_answered
                FROM {$attempts_table}
                WHERE status = %s",
                'completed'
            )
        );

        if (!$row) {
            return (object) [
                'total_attempts' => 0,
                'total_correct'  => 0,
                'total_answered' => 0,
            ];
        }

        return $row;
    }

    /**
     * Count total registered students (subscriber role).
     *
     * Uses WordPress count_users() for role-based counting, which
     * queries the wp_usermeta table efficiently via WP internals.
     *
     * @return int
     */
    private static function get_student_count() {

        $user_counts = count_users();

        if (isset($user_counts['avail_roles']['subscriber'])) {
            return absint($user_counts['avail_roles']['subscriber']);
        }

        return 0;
    }

    /**
     * Count users who have completed at least one quiz today.
     *
     * Hits the activity_date index on wp_mdcat_daily_activity.
     *
     * @return int
     */
    private static function get_active_streak_count() {

        global $wpdb;

        $table = MDCAT_Platform_Streak_Service::get_table_name();
        $today = current_time('Y-m-d');

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id)
                FROM {$table}
                WHERE activity_date = %s",
                $today
            )
        );

        return absint($count);
    }

    /**
     * Calculate accuracy percentage safely.
     *
     * @param int $correct Correct answers count.
     * @param int $total   Total answered count.
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
     * Get table names used by admin stats queries.
     *
     * Reuses Attempts Handler for attempt table names to maintain
     * a single source of truth for table name resolution.
     *
     * @return array
     */
    private static function get_tables() {

        global $wpdb;

        return [
            'subjects'    => $wpdb->prefix . 'mdcat_subjects',
            'chapters'    => $wpdb->prefix . 'mdcat_chapters',
            'collections' => $wpdb->prefix . 'mdcat_collections',
            'questions'   => $wpdb->prefix . 'mdcat_questions',
        ];
    }
}
