<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Review_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated review AJAX actions.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_get_attempt_review', [__CLASS__, 'get_attempt_review']);
    }

    /**
     * Return a completed attempt review for the current user.
     */
    public static function get_attempt_review() {

        self::verify_request();

        $attempt_id = self::get_post_absint('attempt_id');

        if (!$attempt_id) {
            self::send_error('invalid_attempt', __('A valid attempt is required.', 'mdcat-platform'), 400);
        }

        $review = MDCAT_Platform_Review_Service::get_attempt_review($attempt_id, get_current_user_id());

        if (is_wp_error($review)) {
            self::send_wp_error($review);
        }

        wp_send_json_success($review);
    }

    /**
     * Verify nonce and authentication for review requests.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to review this attempt.', 'mdcat-platform'), 401);
        }
    }

    /**
     * Read an integer from POST data.
     *
     * @param string $key Request key.
     * @return int
     */
    private static function get_post_absint( $key ) {

        if (!isset($_POST[$key])) {
            return 0;
        }

        return absint(wp_unslash($_POST[$key]));
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
