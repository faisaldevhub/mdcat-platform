<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Admin_Student_Service {

    /**
     * Fetch the most active students ranked by attempt count.
     *
     * Joins wp_users with wp_mdcat_attempts to count completed attempts
     * per user. Uses the user_status(user_id, status) composite index
     * on the attempts table for efficient grouping.
     *
     * @param int $limit Maximum number of students to return.
     * @return array
     */
    public static function get_most_active_students( $limit = 10 ) {

        global $wpdb;

        $limit          = absint($limit) ? absint($limit) : 10;
        $attempts_table = MDCAT_Platform_Attempts_Handler::get_attempts_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    users.ID AS user_id,
                    users.display_name,
                    COUNT(attempts.id) AS attempt_count,
                    MAX(attempts.completed_at) AS last_attempt_date
                FROM {$wpdb->users} AS users
                INNER JOIN {$attempts_table} AS attempts
                    ON attempts.user_id = users.ID
                    AND attempts.status = %s
                GROUP BY users.ID, users.display_name
                ORDER BY attempt_count DESC, last_attempt_date DESC
                LIMIT %d",
                'completed',
                $limit
            )
        );

        return self::format_active_students($rows);
    }

    /**
     * Fetch top-performing students ranked by accuracy.
     *
     * Accuracy is calculated as SUM(correct_answers) / SUM(total_questions) * 100
     * across all completed attempts per user. Only students with at least one
     * completed attempt are included.
     *
     * @param int $limit Maximum number of students to return.
     * @return array
     */
    public static function get_student_performance_summary( $limit = 10 ) {

        global $wpdb;

        $limit          = absint($limit) ? absint($limit) : 10;
        $attempts_table = MDCAT_Platform_Attempts_Handler::get_attempts_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    users.ID AS user_id,
                    users.display_name,
                    COUNT(attempts.id) AS total_attempts,
                    COALESCE(SUM(attempts.correct_answers), 0) AS total_correct,
                    COALESCE(SUM(attempts.wrong_answers), 0) AS total_wrong,
                    COALESCE(SUM(attempts.total_questions), 0) AS total_questions
                FROM {$wpdb->users} AS users
                INNER JOIN {$attempts_table} AS attempts
                    ON attempts.user_id = users.ID
                    AND attempts.status = %s
                GROUP BY users.ID, users.display_name
                HAVING total_questions > 0
                ORDER BY (total_correct / total_questions) DESC, total_attempts DESC
                LIMIT %d",
                'completed',
                $limit
            )
        );

        return self::format_performance_summary($rows);
    }

    /**
     * Fetch the most recent platform-wide activity feed.
     *
     * Returns the latest completed quiz attempts across all students,
     * enriched with student name and content hierarchy (subject → chapter → collection).
     *
     * Uses the completed_at index on wp_mdcat_attempts for efficient ordering.
     *
     * @param int $limit Maximum number of activity items to return.
     * @return array
     */
    public static function get_recent_activity_feed( $limit = 15 ) {

        global $wpdb;

        $limit  = absint($limit) ? absint($limit) : 15;
        $tables = self::get_tables();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    attempts.id AS attempt_id,
                    users.display_name AS student_name,
                    subjects.name AS subject_title,
                    chapters.name AS chapter_title,
                    collections.title AS collection_title,
                    attempts.score,
                    attempts.total_questions,
                    attempts.correct_answers,
                    attempts.wrong_answers,
                    attempts.completed_at
                FROM {$tables['attempts']} AS attempts
                INNER JOIN {$wpdb->users} AS users
                    ON users.ID = attempts.user_id
                LEFT JOIN {$tables['collections']} AS collections
                    ON collections.id = attempts.collection_id
                LEFT JOIN {$tables['chapters']} AS chapters
                    ON chapters.id = collections.chapter_id
                LEFT JOIN {$tables['subjects']} AS subjects
                    ON subjects.id = chapters.subject_id
                WHERE attempts.status = %s
                ORDER BY attempts.completed_at DESC, attempts.id DESC
                LIMIT %d",
                'completed',
                $limit
            )
        );

        return self::format_activity_feed($rows);
    }

    /**
     * Format most-active student rows.
     *
     * @param array $rows Raw database rows.
     * @return array
     */
    private static function format_active_students( $rows ) {

        $students = [];

        foreach ((array) $rows as $row) {
            $students[] = [
                'user_id'           => absint($row->user_id),
                'display_name'      => $row->display_name,
                'attempt_count'     => absint($row->attempt_count),
                'last_attempt_date' => $row->last_attempt_date,
            ];
        }

        return $students;
    }

    /**
     * Format student performance summary rows.
     *
     * @param array $rows Raw database rows.
     * @return array
     */
    private static function format_performance_summary( $rows ) {

        $students = [];

        foreach ((array) $rows as $row) {
            $total_correct   = absint($row->total_correct);
            $total_questions = absint($row->total_questions);

            $students[] = [
                'user_id'         => absint($row->user_id),
                'display_name'    => $row->display_name,
                'total_attempts'  => absint($row->total_attempts),
                'total_correct'   => $total_correct,
                'total_wrong'     => absint($row->total_wrong),
                'total_questions' => $total_questions,
                'accuracy'        => $total_questions
                    ? round(($total_correct / $total_questions) * 100, 2)
                    : 0,
            ];
        }

        return $students;
    }

    /**
     * Format activity feed rows.
     *
     * @param array $rows Raw database rows.
     * @return array
     */
    private static function format_activity_feed( $rows ) {

        $activity = [];

        foreach ((array) $rows as $row) {
            $activity[] = [
                'attempt_id'       => absint($row->attempt_id),
                'student_name'     => $row->student_name,
                'subject_title'    => $row->subject_title ? $row->subject_title : __('Subject unavailable', 'mdcat-platform'),
                'chapter_title'    => $row->chapter_title ? $row->chapter_title : __('Chapter unavailable', 'mdcat-platform'),
                'collection_title' => $row->collection_title ? $row->collection_title : __('Collection unavailable', 'mdcat-platform'),
                'score'            => (float) $row->score,
                'total_questions'  => absint($row->total_questions),
                'correct_answers'  => absint($row->correct_answers),
                'wrong_answers'    => absint($row->wrong_answers),
                'completed_at'     => $row->completed_at,
            ];
        }

        return $activity;
    }

    /**
     * Get table names used by admin student queries.
     *
     * @return array
     */
    private static function get_tables() {

        global $wpdb;

        return [
            'attempts'    => MDCAT_Platform_Attempts_Handler::get_attempts_table_name(),
            'collections' => $wpdb->prefix . 'mdcat_collections',
            'chapters'    => $wpdb->prefix . 'mdcat_chapters',
            'subjects'    => $wpdb->prefix . 'mdcat_subjects',
        ];
    }
}
