<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Dashboard_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated dashboard AJAX actions.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_get_student_dashboard', [__CLASS__, 'get_student_dashboard']);
    }

    /**
     * Return aggregated dashboard data for the current student.
     *
     * This handler is intentionally thin — all business logic lives in
     * Dashboard Service. The handler only verifies security, calls the
     * service, and formats the response.
     */
    public static function get_student_dashboard() {

        self::verify_request();

        $user_id = get_current_user_id();

        $stats = MDCAT_Platform_Dashboard_Service::get_dashboard_stats($user_id);

        if (is_wp_error($stats)) {
            self::send_wp_error($stats);
        }

        $recent_activity = MDCAT_Platform_Dashboard_Service::get_recent_activity($user_id);

        if (is_wp_error($recent_activity)) {
            self::send_wp_error($recent_activity);
        }

        $performance_snapshot = MDCAT_Platform_Dashboard_Service::get_performance_snapshot($user_id);

        if (is_wp_error($performance_snapshot)) {
            self::send_wp_error($performance_snapshot);
        }

        $streak = MDCAT_Platform_Dashboard_Service::get_streak_data($user_id);

        if (is_wp_error($streak)) {
            self::send_wp_error($streak);
        }

        wp_send_json_success(
            [
                'stats'                => $stats,
                'recent_activity'      => $recent_activity,
                'performance_snapshot' => $performance_snapshot,
                'streak'               => $streak,
            ]
        );
    }

    /**
     * Verify nonce and authentication for dashboard requests.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to view your dashboard.', 'mdcat-platform'), 401);
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
