<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Gamification_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated gamification AJAX actions.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_get_streak_summary', [__CLASS__, 'get_streak_summary']);
        add_action('wp_ajax_mdcat_get_xp_summary', [__CLASS__, 'get_xp_summary']);
        add_action('wp_ajax_mdcat_get_user_badges', [__CLASS__, 'get_user_badges']);
        add_action('wp_ajax_mdcat_get_user_achievements', [__CLASS__, 'get_user_achievements']);
        add_action('wp_ajax_mdcat_get_leaderboard', [__CLASS__, 'get_leaderboard']);
    }

    /**
     * Return streak summary for the current student.
     */
    public static function get_streak_summary() {

        self::verify_request();

        $user_id = get_current_user_id();

        $summary = MDCAT_Platform_Streak_Service::get_streak_summary($user_id);

        if (is_wp_error($summary)) {
            self::send_wp_error($summary);
        }

        wp_send_json_success($summary);
    }

    /**
     * Return XP summary for the current student.
     *
     * Includes total XP, current level, level progress,
     * and recent XP transactions.
     */
    public static function get_xp_summary() {

        self::verify_request();

        $user_id = get_current_user_id();

        $summary = MDCAT_Platform_XP_Service::get_xp_summary($user_id);

        if (is_wp_error($summary)) {
            self::send_wp_error($summary);
        }

        wp_send_json_success($summary);
    }

    /**
     * Return all badge definitions with earned/locked status.
     *
     * Used by the badge showcase to display both earned and
     * locked badges in the student dashboard.
     */
    public static function get_user_badges() {

        self::verify_request();

        $user_id = get_current_user_id();
        $badges  = MDCAT_Platform_Badge_Service::get_badges_with_status($user_id);

        wp_send_json_success(['badges' => $badges]);
    }

    /**
     * Return all achievements earned by the current student.
     */
    public static function get_user_achievements() {

        self::verify_request();

        $user_id      = get_current_user_id();
        $achievements = MDCAT_Platform_Achievement_Service::get_user_achievements($user_id);

        wp_send_json_success(['achievements' => $achievements]);
    }

    /**
     * Return leaderboard data for the requested type.
     *
     * Accepts an optional 'type' parameter: 'all_time', 'weekly', 'monthly'.
     * Defaults to 'weekly' if not specified.
     */
    public static function get_leaderboard() {

        self::verify_request();

        $user_id = get_current_user_id();
        $type    = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'weekly';
        $limit   = isset($_POST['limit']) ? absint($_POST['limit']) : 0;

        $leaderboard = MDCAT_Platform_Leaderboard_Service::get_leaderboard_data($user_id, $type, $limit);

        if (is_wp_error($leaderboard)) {
            self::send_wp_error($leaderboard);
        }

        wp_send_json_success($leaderboard);
    }

    /**
     * Verify nonce and authentication for gamification requests.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to view your data.', 'mdcat-platform'), 401);
        }
    }

    /**
     * Send a normalized WP_Error response.
     *
     * @param WP_Error $error Error object.
     */
    private static function send_wp_error( $error ) {

        self::send_error($error->get_error_code(), $error->get_error_message(), 400);
    }

    /**
     * Send a normalized JSON error response.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status.
     */
    private static function send_error( $code, $message, $status = 400 ) {

        wp_send_json_error(
            [
                'code'    => sanitize_key($code),
                'message' => $message,
            ],
            absint($status)
        );
    }
}
