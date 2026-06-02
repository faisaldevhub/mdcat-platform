<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Revision_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated revision AJAX actions.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_toggle_bookmark', [__CLASS__, 'toggle_bookmark']);
        add_action('wp_ajax_mdcat_get_bookmarks', [__CLASS__, 'get_bookmarks']);
        add_action('wp_ajax_mdcat_get_wrong_questions', [__CLASS__, 'get_wrong_questions']);
    }

    /**
     * Toggle bookmark state for the current user.
     */
    public static function toggle_bookmark() {

        self::verify_request();

        $question_id = self::get_post_absint('question_id');

        if (!$question_id) {
            self::send_error('invalid_question', __('A valid question is required.', 'mdcat-platform'), 400);
        }

        $result = MDCAT_Platform_Revision_Service::toggle_bookmark(get_current_user_id(), $question_id);

        if (is_wp_error($result)) {
            self::send_wp_error($result);
        }

        wp_send_json_success($result);
    }

    /**
     * Fetch bookmarked questions for the current user.
     */
    public static function get_bookmarks() {

        self::verify_request();

        $questions = MDCAT_Platform_Revision_Service::get_bookmarked_questions(get_current_user_id());

        if (is_wp_error($questions)) {
            self::send_wp_error($questions);
        }

        wp_send_json_success(['questions' => $questions]);
    }

    /**
     * Fetch dynamically tracked wrong questions for the current user.
     */
    public static function get_wrong_questions() {

        self::verify_request();

        $questions = MDCAT_Platform_Revision_Service::get_wrong_questions(get_current_user_id());

        if (is_wp_error($questions)) {
            self::send_wp_error($questions);
        }

        wp_send_json_success(['questions' => $questions]);
    }

    /**
     * Verify nonce and authentication for revision requests.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to use revision features.', 'mdcat-platform'), 401);
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
