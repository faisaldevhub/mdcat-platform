<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Study_Planner_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated study planner AJAX actions.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_get_study_plan', [__CLASS__, 'get_study_plan']);
        add_action('wp_ajax_mdcat_get_daily_plan', [__CLASS__, 'get_daily_plan']);
    }

    /**
     * Return the complete study plan for the current student.
     *
     * Returns all 5 recommendation types: priority topics, weak subjects,
     * continue learning, revision recommendations, and daily plan.
     */
    public static function get_study_plan() {

        self::verify_request();

        $user_id = get_current_user_id();

        $study_plan = MDCAT_Platform_Recommendation_Service::get_study_plan($user_id);

        if (is_wp_error($study_plan)) {
            self::send_wp_error($study_plan);
        }

        wp_send_json_success($study_plan);
    }

    /**
     * Return only the daily plan for the current student.
     *
     * A lighter endpoint that returns just the 3 daily action items
     * and streak context. Useful for compact widget rendering.
     */
    public static function get_daily_plan() {

        self::verify_request();

        $user_id = get_current_user_id();

        $study_plan = MDCAT_Platform_Recommendation_Service::get_study_plan($user_id);

        if (is_wp_error($study_plan)) {
            self::send_wp_error($study_plan);
        }

        wp_send_json_success([
            'daily_plan' => isset($study_plan['daily_plan']) ? $study_plan['daily_plan'] : [],
        ]);
    }

    /**
     * Verify nonce and authentication.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to view your study plan.', 'mdcat-platform'), 401);
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
