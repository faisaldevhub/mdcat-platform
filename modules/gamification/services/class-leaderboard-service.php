<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Leaderboard_Service {

    /**
     * Default number of students to show on the leaderboard.
     */
    const DEFAULT_LIMIT = 20;

    /**
     * Get leaderboard data for a specific type.
     *
     * Returns the rankings list plus the current user's position.
     *
     * @param int    $user_id WordPress user ID of the current student.
     * @param string $type    Leaderboard type: 'all_time', 'weekly', 'monthly'.
     * @param int    $limit   Maximum students to show (default 20).
     * @return array|WP_Error Leaderboard data.
     */
    public static function get_leaderboard_data( $user_id, $type = 'all_time', $limit = 0 ) {

        $user_id = absint($user_id);
        $limit   = $limit > 0 ? absint($limit) : self::DEFAULT_LIMIT;

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $allowed_types = ['all_time', 'weekly', 'monthly'];

        if (!in_array($type, $allowed_types, true)) {
            $type = 'all_time';
        }

        $since = self::get_period_start($type);

        $rankings     = self::get_rankings($since, $limit);
        $current_user = self::get_user_rank($user_id, $since);

        return [
            'type'          => $type,
            'period_label'  => self::get_period_label($type),
            'rankings'      => $rankings,
            'current_user'  => $current_user,
        ];
    }

    /**
     * Get ranked list of top students by XP.
     *
     * For 'all_time', $since is null and no date filter is applied.
     * For 'weekly' and 'monthly', results are limited to XP earned
     * since the start of the period.
     *
     * Privacy: Only display_name is exposed — no emails or user IDs
     * are returned to the frontend.
     *
     * @param string|null $since MySQL datetime for period start, or null for all-time.
     * @param int         $limit Maximum students to return.
     * @return array Ranked student list.
     */
    private static function get_rankings( $since, $limit ) {

        global $wpdb;

        $xp_table = MDCAT_Platform_XP_Service::get_table_name();

        if ($since) {
            $query = $wpdb->prepare(
                "SELECT xp.user_id, SUM(xp.amount) AS total_xp, u.display_name
                FROM {$xp_table} AS xp
                INNER JOIN {$wpdb->users} AS u ON xp.user_id = u.ID
                WHERE xp.created_at >= %s
                GROUP BY xp.user_id, u.display_name
                HAVING total_xp > 0
                ORDER BY total_xp DESC, u.display_name ASC
                LIMIT %d",
                $since,
                $limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT xp.user_id, SUM(xp.amount) AS total_xp, u.display_name
                FROM {$xp_table} AS xp
                INNER JOIN {$wpdb->users} AS u ON xp.user_id = u.ID
                GROUP BY xp.user_id, u.display_name
                HAVING total_xp > 0
                ORDER BY total_xp DESC, u.display_name ASC
                LIMIT %d",
                $limit
            );
        }

        $rows = $wpdb->get_results($query);

        $rankings = [];
        $rank     = 1;

        foreach ((array) $rows as $row) {
            $rankings[] = [
                'rank'         => $rank,
                'display_name' => $row->display_name,
                'total_xp'     => absint($row->total_xp),
                'level'        => MDCAT_Platform_XP_Service::calculate_level(absint($row->total_xp)),
            ];
            $rank++;
        }

        return $rankings;
    }

    /**
     * Get the current user's rank and XP total.
     *
     * Uses a subquery to count how many users have more XP than
     * the current user, then adds 1 for the rank position.
     *
     * @param int         $user_id WordPress user ID.
     * @param string|null $since   MySQL datetime for period start, or null for all-time.
     * @return array User rank data.
     */
    private static function get_user_rank( $user_id, $since ) {

        global $wpdb;

        $xp_table = MDCAT_Platform_XP_Service::get_table_name();

        // Get user's total XP for the period.
        if ($since) {
            $user_xp = absint(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(amount), 0)
                        FROM {$xp_table}
                        WHERE user_id = %d
                            AND created_at >= %s",
                        $user_id,
                        $since
                    )
                )
            );
        } else {
            $user_xp = MDCAT_Platform_XP_Service::get_total_xp($user_id);
        }

        // Count users with more XP.
        if ($since) {
            $users_above = absint(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT user_id)
                        FROM (
                            SELECT user_id, SUM(amount) AS total_xp
                            FROM {$xp_table}
                            WHERE created_at >= %s
                            GROUP BY user_id
                            HAVING total_xp > %d
                        ) ranked",
                        $since,
                        $user_xp
                    )
                )
            );
        } else {
            $users_above = absint(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT user_id)
                        FROM (
                            SELECT user_id, SUM(amount) AS total_xp
                            FROM {$xp_table}
                            GROUP BY user_id
                            HAVING total_xp > %d
                        ) ranked",
                        $user_xp
                    )
                )
            );
        }

        $user = get_userdata($user_id);

        return [
            'rank'         => $users_above + 1,
            'display_name' => $user ? $user->display_name : '',
            'total_xp'     => $user_xp,
            'level'        => MDCAT_Platform_XP_Service::calculate_level($user_xp),
        ];
    }

    /**
     * Get the start datetime for a leaderboard period.
     *
     * Uses current_time() to match the WordPress timezone used
     * when recording XP transactions (created_at). Without this,
     * period boundaries would be in the server timezone while
     * transaction timestamps are in the WordPress timezone.
     *
     * @param string $type Period type: 'all_time', 'weekly', 'monthly'.
     * @return string|null MySQL datetime string, or null for all-time.
     */
    private static function get_period_start( $type ) {

        switch ($type) {
            case 'weekly':
                // Monday 00:00:00 of the current week in WordPress timezone.
                $wp_now = current_time('timestamp');
                $monday = strtotime('monday this week', $wp_now);
                return gmdate('Y-m-d 00:00:00', $monday);

            case 'monthly':
                // First day of the current month in WordPress timezone.
                return current_time('Y-m') . '-01 00:00:00';

            case 'all_time':
            default:
                return null;
        }
    }

    /**
     * Get a human-readable label for the leaderboard period.
     *
     * @param string $type Period type.
     * @return string Human-readable period label.
     */
    private static function get_period_label( $type ) {

        switch ($type) {
            case 'weekly':
                $wp_now = current_time('timestamp');
                $monday = strtotime('monday this week', $wp_now);
                $sunday = strtotime('sunday this week', $wp_now);
                return gmdate('M j', $monday) . ' – ' . gmdate('M j, Y', $sunday);

            case 'monthly':
                return current_time('F Y');

            case 'all_time':
            default:
                return 'All Time';
        }
    }
}
