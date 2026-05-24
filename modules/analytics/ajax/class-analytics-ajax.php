<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Analytics_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated analytics AJAX actions.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_get_performance_analytics', [__CLASS__, 'get_performance_analytics']);
    }

    /**
     * Return performance analytics for the current student.
     */
    public static function get_performance_analytics() {

        self::verify_request();

        $user_id = get_current_user_id();

        $subject_performance = MDCAT_Platform_Performance_Analytics::get_subject_performance($user_id);

        if (is_wp_error($subject_performance)) {
            self::send_wp_error($subject_performance);
        }

        $chapter_performance = MDCAT_Platform_Performance_Analytics::get_chapter_performance($user_id);

        if (is_wp_error($chapter_performance)) {
            self::send_wp_error($chapter_performance);
        }

        wp_send_json_success(
            [
                'subject_performance' => $subject_performance,
                'chapter_performance' => $chapter_performance,
            ]
        );
    }

    /**
     * Verify nonce and authentication for analytics requests.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to view analytics.', 'mdcat-platform'), 401);
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
