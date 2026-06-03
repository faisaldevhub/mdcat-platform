<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Progress_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated progress AJAX actions.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_get_subject_progress', [__CLASS__, 'get_subject_progress']);
    }

    /**
     * Return subject completion data for the current student.
     *
     * Thin AJAX handler — verifies security, calls the service,
     * and formats the response.
     */
    public static function get_subject_progress() {

        self::verify_request();

        $user_id = get_current_user_id();

        $subjects = MDCAT_Platform_Progress_Service::get_subject_completion($user_id);

        if (is_wp_error($subjects)) {
            self::send_wp_error($subjects);
        }

        wp_send_json_success($subjects);
    }

    /**
     * Verify nonce and authentication for progress requests.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to view progress data.', 'mdcat-platform'), 401);
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
