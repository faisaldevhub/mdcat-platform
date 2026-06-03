<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Progress_Service {

    /**
     * Calculate subject-level completion for a student.
     *
     * Completion is defined as the ratio of collections attempted
     * (with at least one completed attempt) to total collections
     * within each subject. This gives a clear picture of curriculum
     * coverage per subject.
     *
     * Implementation details:
     * - Uses LEFT JOIN so subjects with zero completed attempts still appear at 0%.
     * - Uses COUNT(DISTINCT) so repeating a quiz doesn't inflate completion.
     * - Single query with GROUP BY — no PHP loops for counting.
     * - Only counts active collections to avoid inflating totals with drafts.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error Array of subject completion data, or WP_Error on failure.
     */
    public static function get_subject_completion( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $tables = self::get_tables();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    subjects.id AS subject_id,
                    subjects.name AS subject_name,
                    COUNT(DISTINCT collections.id) AS total_collections,
                    COUNT(DISTINCT CASE WHEN attempts.id IS NOT NULL THEN collections.id END) AS completed_collections
                FROM {$tables['subjects']} AS subjects
                INNER JOIN {$tables['chapters']} AS chapters
                    ON chapters.subject_id = subjects.id
                INNER JOIN {$tables['collections']} AS collections
                    ON collections.chapter_id = chapters.id
                    AND collections.status = 'active'
                LEFT JOIN {$tables['attempts']} AS attempts
                    ON attempts.collection_id = collections.id
                    AND attempts.user_id = %d
                    AND attempts.status = 'completed'
                GROUP BY subjects.id, subjects.name
                ORDER BY subjects.name ASC",
                $user_id
            )
        );

        return self::format_subject_rows($rows);
    }

    /**
     * Format raw database rows into structured subject completion data.
     *
     * @param array $rows Raw database result rows.
     * @return array Formatted subject completion array.
     */
    private static function format_subject_rows( $rows ) {

        $subjects = [];

        foreach ((array) $rows as $row) {

            $total     = absint($row->total_collections);
            $completed = absint($row->completed_collections);

            $subjects[] = [
                'subject_id'              => absint($row->subject_id),
                'subject_name'            => $row->subject_name,
                'total_collections'       => $total,
                'completed_collections'   => $completed,
                'completion_percentage'   => self::calculate_percentage($completed, $total),
            ];
        }

        return $subjects;
    }

    /**
     * Calculate a percentage safely.
     *
     * @param int $part  Numerator value.
     * @param int $whole Denominator value.
     * @return float Percentage rounded to 1 decimal place.
     */
    private static function calculate_percentage( $part, $whole ) {

        $part  = absint($part);
        $whole = absint($whole);

        if (!$whole) {
            return 0;
        }

        return round(($part / $whole) * 100, 1);
    }

    /**
     * Get table names used by progress queries.
     *
     * Reuses Attempts Handler for attempt table names to maintain
     * a single source of truth for table name resolution.
     *
     * @return array Associative array of table names.
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
