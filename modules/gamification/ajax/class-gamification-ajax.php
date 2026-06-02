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
    }

    /**
     * Return streak summary for the current student.
     *
     * This handler is intentionally thin — all business logic lives in
     * Streak Service. The handler only verifies security, calls the
     * service, and formats the response.
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
     * Verify nonce and authentication for gamification requests.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to view your streak data.', 'mdcat-platform'), 401);
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
