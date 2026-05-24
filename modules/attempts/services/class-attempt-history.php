<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Attempt_History {

    /**
     * Fetch completed attempt history for a user.
     *
     * Pagination is intentionally part of the service contract now so future
     * dashboards, charts, and mobile clients can scale without changing callers.
     *
     * @param int   $user_id WordPress user ID.
     * @param array $args    Query arguments.
     * @return array|WP_Error
     */
    public static function get_user_attempt_history( $user_id, $args = [] ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $args = wp_parse_args(
            is_array($args) ? $args : [],
            [
                'page'     => 1,
                'per_page' => 20,
            ]
        );

        $page     = absint($args['page']);
        $per_page = absint($args['per_page']);
        $page     = $page ? $page : 1;
        $per_page = $per_page ? $per_page : 20;
        $per_page = min(100, max(1, $per_page));
        $offset   = ($page - 1) * $per_page;

        $attempts_table    = self::get_attempts_table_name();
        $collections_table = self::get_collections_table_name();
        $chapters_table    = self::get_chapters_table_name();
        $subjects_table    = self::get_subjects_table_name();

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT attempts.id AS attempt_id, attempts.collection_id, attempts.score,
                    attempts.total_questions, attempts.correct_answers, attempts.wrong_answers, attempts.completed_at,
                    collections.title AS collection_title, chapters.name AS chapter_title, subjects.name AS subject_title
                FROM {$attempts_table} AS attempts
                LEFT JOIN {$collections_table} AS collections ON attempts.collection_id = collections.id
                LEFT JOIN {$chapters_table} AS chapters ON collections.chapter_id = chapters.id
                LEFT JOIN {$subjects_table} AS subjects ON chapters.subject_id = subjects.id
                WHERE attempts.user_id = %d
                    AND attempts.status = %s
                ORDER BY attempts.completed_at DESC, attempts.id DESC
                LIMIT %d OFFSET %d",
                $user_id,
                'completed',
                $per_page,
                $offset
            )
        );

        $total_items = absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(id)
                    FROM {$attempts_table}
                    WHERE user_id = %d
                        AND status = %s",
                    $user_id,
                    'completed'
                )
            )
        );

        return [
            'items'      => self::format_items($items),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total_items' => $total_items,
                'total_pages' => $per_page ? (int) ceil($total_items / $per_page) : 0,
            ],
        ];
    }

    /**
     * Format raw database rows for frontend/API consumers.
     *
     * @param array $items Raw rows.
     * @return array
     */
    private static function format_items( $items ) {

        $history = [];

        foreach ((array) $items as $item) {
            $history[] = [
                'attempt_id'       => absint($item->attempt_id),
                'collection_id'    => absint($item->collection_id),
                'collection_title' => $item->collection_title ? $item->collection_title : __('Collection unavailable', 'mdcat-platform'),
                'chapter_title'    => $item->chapter_title ? $item->chapter_title : __('Chapter unavailable', 'mdcat-platform'),
                'subject_title'    => $item->subject_title ? $item->subject_title : __('Subject unavailable', 'mdcat-platform'),
                'score'            => (float) $item->score,
                'total_questions'  => absint($item->total_questions),
                'correct_answers'  => absint($item->correct_answers),
                'wrong_answers'    => absint($item->wrong_answers),
                'completed_at'     => $item->completed_at,
            ];
        }

        return $history;
    }

    /**
     * Get the attempts table name.
     *
     * @return string
     */
    private static function get_attempts_table_name() {

        return MDCAT_Platform_Attempts_Handler::get_attempts_table_name();
    }

    /**
     * Get the collections table name.
     *
     * @return string
     */
    private static function get_collections_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_collections';
    }

    /**
     * Get the chapters table name.
     *
     * @return string
     */
    private static function get_chapters_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_chapters';
    }

    /**
     * Get the subjects table name.
     *
     * @return string
     */
    private static function get_subjects_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_subjects';
    }
}
