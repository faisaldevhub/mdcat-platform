<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Student_Directory_Service {

    /**
     * Fetch a paginated, filterable student directory.
     *
     * Joins wp_users (subscriber role) with attempts and enrollment
     * tables to enrich each student row with activity and enrollment data.
     * Supports search, status filter, and sorting.
     *
     * @param array $args {
     *     @type int    $page     Page number (default 1).
     *     @type int    $per_page Results per page (default 20, max 100).
     *     @type string $search   Search by name or email.
     *     @type string $status   Filter by account status: 'active', 'suspended', 'all'.
     *     @type string $orderby  Sort column: 'name', 'email', 'registered', 'attempts', 'last_activity'.
     *     @type string $order    Sort direction: 'ASC' or 'DESC'.
     * }
     * @return array Paginated result with items and pagination meta.
     */
    public static function get_students( $args = [] ) {

        global $wpdb;

        $args = wp_parse_args(
            is_array($args) ? $args : [],
            [
                'page'     => 1,
                'per_page' => 20,
                'search'   => '',
                'status'   => 'all',
                'orderby'  => 'registered',
                'order'    => 'DESC',
            ]
        );

        $page     = absint($args['page']);
        $per_page = absint($args['per_page']);
        $page     = $page ? $page : 1;
        $per_page = $per_page ? $per_page : 20;
        $per_page = min(100, max(1, $per_page));
        $offset   = ($page - 1) * $per_page;

        $search  = sanitize_text_field($args['search']);
        $status  = sanitize_text_field($args['status']);
        $orderby = sanitize_text_field($args['orderby']);
        $order   = strtoupper(sanitize_text_field($args['order'])) === 'ASC' ? 'ASC' : 'DESC';

        $attempts_table    = MDCAT_Platform_Attempts_Handler::get_attempts_table_name();
        $activity_table    = MDCAT_Platform_Streak_Service::get_table_name();

        // Base query: subscribers only.
        $where_clauses = [];
        $where_values  = [];

        $where_clauses[] = "um_role.meta_key = %s";
        $where_values[]  = $wpdb->prefix . 'capabilities';

        $where_clauses[] = "um_role.meta_value LIKE %s";
        $where_values[]  = '%subscriber%';

        // Search filter.
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(users.display_name LIKE %s OR users.user_email LIKE %s)";
            $where_values[]  = $like;
            $where_values[]  = $like;
        }

        // Status filter.
        if ($status === 'active') {
            $where_clauses[] = "(um_status.meta_value IS NULL OR um_status.meta_value != %s)";
            $where_values[]  = 'suspended';
        } elseif ($status === 'suspended') {
            $where_clauses[] = "um_status.meta_value = %s";
            $where_values[]  = 'suspended';
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Order by mapping.
        $order_map = [
            'name'          => 'users.display_name',
            'email'         => 'users.user_email',
            'registered'    => 'users.user_registered',
            'attempts'      => 'attempt_count',
            'last_activity' => 'last_activity_date',
        ];

        $order_column = isset($order_map[$orderby]) ? $order_map[$orderby] : 'users.user_registered';

        // Count query.
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT users.ID)
            FROM {$wpdb->users} AS users
            INNER JOIN {$wpdb->usermeta} AS um_role
                ON um_role.user_id = users.ID
            LEFT JOIN {$wpdb->usermeta} AS um_status
                ON um_status.user_id = users.ID
                AND um_status.meta_key = 'mdcat_account_status'
            WHERE {$where_sql}",
            ...$where_values
        );

        $total_items = absint($wpdb->get_var($count_sql));

        // Main query.
        $main_sql = $wpdb->prepare(
            "SELECT
                users.ID AS user_id,
                users.display_name,
                users.user_email,
                users.user_registered,
                COALESCE(um_status.meta_value, 'active') AS account_status,
                COALESCE(attempt_data.attempt_count, 0) AS attempt_count,
                attempt_data.last_attempt_date,
                activity_data.last_activity_date
            FROM {$wpdb->users} AS users
            INNER JOIN {$wpdb->usermeta} AS um_role
                ON um_role.user_id = users.ID
            LEFT JOIN {$wpdb->usermeta} AS um_status
                ON um_status.user_id = users.ID
                AND um_status.meta_key = 'mdcat_account_status'
            LEFT JOIN (
                SELECT user_id,
                    COUNT(id) AS attempt_count,
                    MAX(completed_at) AS last_attempt_date
                FROM {$attempts_table}
                WHERE status = 'completed'
                GROUP BY user_id
            ) AS attempt_data ON attempt_data.user_id = users.ID
            LEFT JOIN (
                SELECT user_id,
                    MAX(activity_date) AS last_activity_date
                FROM {$activity_table}
                GROUP BY user_id
            ) AS activity_data ON activity_data.user_id = users.ID
            WHERE {$where_sql}
            ORDER BY {$order_column} {$order}
            LIMIT %d OFFSET %d",
            ...[...$where_values, $per_page, $offset]
        );

        $rows = $wpdb->get_results($main_sql);

        return [
            'items'      => self::format_students($rows),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total_items' => $total_items,
                'total_pages' => $per_page ? (int) ceil($total_items / $per_page) : 0,
            ],
        ];
    }

    /**
     * Format raw database rows into structured student directory data.
     *
     * @param array $rows Raw database rows.
     * @return array Formatted student array.
     */
    private static function format_students( $rows ) {

        $students = [];

        foreach ((array) $rows as $row) {
            $students[] = [
                'user_id'            => absint($row->user_id),
                'display_name'       => $row->display_name,
                'email'              => $row->user_email,
                'registered'         => $row->user_registered,
                'account_status'     => $row->account_status ? $row->account_status : 'active',
                'attempt_count'      => absint($row->attempt_count),
                'last_attempt_date'  => $row->last_attempt_date,
                'last_activity_date' => $row->last_activity_date,
            ];
        }

        return $students;
    }
}
