<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Student_Management_Ajax {

    const NONCE_ACTION = 'mdcat_student_management_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register admin-only AJAX actions for student management.
     *
     * All actions use wp_ajax_ prefix only (no nopriv variants).
     * Every handler requires manage_options capability.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_admin_get_student_directory', [__CLASS__, 'get_directory']);
        add_action('wp_ajax_mdcat_admin_get_student_profile', [__CLASS__, 'get_profile']);
        add_action('wp_ajax_mdcat_admin_suspend_student', [__CLASS__, 'suspend_student']);
        add_action('wp_ajax_mdcat_admin_activate_student', [__CLASS__, 'activate_student']);
        add_action('wp_ajax_mdcat_admin_get_student_attempts', [__CLASS__, 'get_attempts']);
    }

    /**
     * Return paginated student directory with search and filter support.
     */
    public static function get_directory() {

        self::verify_request();

        $args = [
            'page'     => isset($_POST['page']) ? absint($_POST['page']) : 1,
            'per_page' => isset($_POST['per_page']) ? absint($_POST['per_page']) : 20,
            'search'   => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
            'status'   => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all',
            'orderby'  => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'registered',
            'order'    => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC',
        ];

        $result = MDCAT_Platform_Student_Directory_Service::get_students($args);

        wp_send_json_success($result);
    }

    /**
     * Return a complete student profile.
     *
     * Requires a student_id parameter.
     */
    public static function get_profile() {

        self::verify_request();

        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;

        if (!$student_id) {
            self::send_error('missing_student_id', __('Student ID is required.', 'mdcat-platform'));
        }

        $profile = MDCAT_Platform_Student_Profile_Service::get_student_profile($student_id);

        if (is_wp_error($profile)) {
            self::send_error($profile->get_error_code(), $profile->get_error_message());
        }

        wp_send_json_success($profile);
    }

    /**
     * Suspend a student account.
     */
    public static function suspend_student() {

        self::verify_request();

        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;

        if (!$student_id) {
            self::send_error('missing_student_id', __('Student ID is required.', 'mdcat-platform'));
        }

        $admin_id = get_current_user_id();

        $result = MDCAT_Platform_Student_Status_Service::suspend_student($student_id, $admin_id);

        if (is_wp_error($result)) {
            self::send_error($result->get_error_code(), $result->get_error_message());
        }

        wp_send_json_success([
            'message'    => __('Student has been suspended.', 'mdcat-platform'),
            'student_id' => $student_id,
            'status'     => 'suspended',
        ]);
    }

    /**
     * Activate a suspended student account.
     */
    public static function activate_student() {

        self::verify_request();

        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;

        if (!$student_id) {
            self::send_error('missing_student_id', __('Student ID is required.', 'mdcat-platform'));
        }

        $admin_id = get_current_user_id();

        $result = MDCAT_Platform_Student_Status_Service::activate_student($student_id, $admin_id);

        if (is_wp_error($result)) {
            self::send_error($result->get_error_code(), $result->get_error_message());
        }

        wp_send_json_success([
            'message'    => __('Student has been activated.', 'mdcat-platform'),
            'student_id' => $student_id,
            'status'     => 'active',
        ]);
    }

    /**
     * Return paginated attempt history for a student.
     */
    public static function get_attempts() {

        self::verify_request();

        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;

        if (!$student_id) {
            self::send_error('missing_student_id', __('Student ID is required.', 'mdcat-platform'));
        }

        $args = [
            'page'     => isset($_POST['page']) ? absint($_POST['page']) : 1,
            'per_page' => isset($_POST['per_page']) ? absint($_POST['per_page']) : 20,
        ];

        $result = MDCAT_Platform_Student_Profile_Service::get_attempt_history($student_id, $args);

        if (is_wp_error($result)) {
            self::send_error($result->get_error_code(), $result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Verify nonce and admin capability for student management requests.
     *
     * Mirrors the pattern from MDCAT_Platform_Admin_Reports_Ajax.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!current_user_can('manage_options')) {
            self::send_error('unauthorized', __('You do not have permission to manage students.', 'mdcat-platform'), 403);
        }
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
