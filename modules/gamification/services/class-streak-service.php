<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Streak_Service {

    /**
     * Record a daily activity entry for a user.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for an atomic upsert:
     * - First activity of the day creates a new row with attempts_count = 1
     * - Subsequent activities on the same day increment attempts_count
     *
     * The UNIQUE KEY on (user_id, activity_date) guarantees exactly one
     * row per user per calendar date regardless of concurrency.
     *
     * @param int $user_id WordPress user ID.
     * @return bool True on success, false on failure.
     */
    public static function record_daily_activity( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return false;
        }

        $table         = self::get_table_name();
        $activity_date = current_time('Y-m-d');
        $now           = current_time('mysql');

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (user_id, activity_date, attempts_count, created_at, updated_at)
                VALUES (%d, %s, 1, %s, %s)
                ON DUPLICATE KEY UPDATE
                    attempts_count = attempts_count + 1,
                    updated_at = VALUES(updated_at)",
                $user_id,
                $activity_date,
                $now,
                $now
            )
        );

        return false !== $result;
    }

    /**
     * Calculate the current consecutive streak for a user.
     *
     * Uses a SQL subquery with LAG() window function to detect gaps between
     * consecutive activity dates. The streak counts backward from today
     * (or yesterday if no activity today) and stops at the first gap.
     *
     * Performance: single query, no PHP loops. Scales to thousands of rows
     * because the window function processes in a single pass over the index.
     *
     * @param int $user_id WordPress user ID.
     * @return int Current streak in days (0 if no activity or streak broken).
     */
    public static function get_current_streak( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return 0;
        }

        $table = self::get_table_name();
        $today = current_time('Y-m-d');

        /**
         * Strategy:
         * 1. Select all activity dates for this user, ordered DESC
         * 2. Use LAG() to get the previous date (next chronologically)
         * 3. Calculate the day gap between consecutive dates
         * 4. Also check if the most recent date is today or yesterday
         *    (if neither, streak is 0)
         * 5. Count rows from the top until the first non-consecutive gap
         */

        $most_recent = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT activity_date
                FROM {$table}
                WHERE user_id = %d
                ORDER BY activity_date DESC
                LIMIT 1",
                $user_id
            )
        );

        if (!$most_recent) {
            return 0;
        }

        $days_since_last = absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT DATEDIFF(%s, %s)",
                    $today,
                    $most_recent
                )
            )
        );

        if ($days_since_last > 1) {
            return 0;
        }

        $streak = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) AS streak_length
                FROM (
                    SELECT activity_date,
                        @streak_broken := IF(
                            @prev_date IS NOT NULL AND DATEDIFF(@prev_date, activity_date) > 1,
                            1,
                            @streak_broken
                        ) AS broken,
                        @prev_date := activity_date
                    FROM {$table},
                        (SELECT @prev_date := NULL, @streak_broken := 0) vars
                    WHERE user_id = %d
                    ORDER BY activity_date DESC
                ) computed
                WHERE broken = 0",
                $user_id
            )
        );

        return absint($streak);
    }

    /**
     * Calculate the longest consecutive streak ever achieved by a user.
     *
     * Uses a SQL approach with user variables to assign group IDs to
     * consecutive date sequences, then counts each group and returns
     * the maximum.
     *
     * Performance: single query, no PHP loops.
     *
     * @param int $user_id WordPress user ID.
     * @return int Longest streak in days.
     */
    public static function get_longest_streak( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return 0;
        }

        $table = self::get_table_name();

        $longest = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(streak_length) FROM (
                    SELECT group_id, COUNT(*) AS streak_length
                    FROM (
                        SELECT activity_date,
                            @group_id := IF(
                                @prev_date IS NOT NULL AND DATEDIFF(activity_date, @prev_date) > 1,
                                @group_id + 1,
                                @group_id
                            ) AS group_id,
                            @prev_date := activity_date
                        FROM {$table},
                            (SELECT @prev_date := NULL, @group_id := 0) vars
                        WHERE user_id = %d
                        ORDER BY activity_date ASC
                    ) grouped
                    GROUP BY group_id
                ) streaks",
                $user_id
            )
        );

        return absint($longest);
    }

    /**
     * Build a complete streak summary for the frontend.
     *
     * Aggregates current streak, longest streak, total active days,
     * and last active date into a single structured response.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_streak_summary( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $current_streak = self::get_current_streak($user_id);
        $longest_streak = self::get_longest_streak($user_id);
        $activity_stats = self::get_activity_stats($user_id);

        return [
            'current_streak'   => $current_streak,
            'longest_streak'   => $longest_streak,
            'total_active_days' => absint($activity_stats->total_active_days),
            'last_active_date' => $activity_stats->last_active_date ? $activity_stats->last_active_date : null,
        ];
    }

    /**
     * Get aggregate activity statistics for a user.
     *
     * Returns total active days and last active date in a single query.
     *
     * @param int $user_id WordPress user ID.
     * @return object
     */
    private static function get_activity_stats( $user_id ) {

        global $wpdb;

        $table = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(id) AS total_active_days,
                    MAX(activity_date) AS last_active_date
                FROM {$table}
                WHERE user_id = %d",
                $user_id
            )
        );

        if (!$row) {
            return (object) [
                'total_active_days' => 0,
                'last_active_date'  => null,
            ];
        }

        return $row;
    }

    /**
     * Get the daily activity table name with WordPress prefix.
     *
     * @return string
     */
    public static function get_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_daily_activity';
    }
}
