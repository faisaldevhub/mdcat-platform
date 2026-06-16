<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Notification_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register notification AJAX endpoints.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_get_notifications', [__CLASS__, 'get_notifications']);
        add_action('wp_ajax_mdcat_mark_notification_read', [__CLASS__, 'mark_read']);
        add_action('wp_ajax_mdcat_mark_all_notifications_read', [__CLASS__, 'mark_all_read']);
    }

    /**
     * Get paginated notification feed for the current student.
     *
     * Accepts optional 'page' parameter for pagination.
     * This is the dedicated endpoint for full notification history.
     */
    public static function get_notifications() {

        self::verify_request();

        $user_id  = get_current_user_id();
        $page     = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
        $per_page = MDCAT_Platform_Notification_Service::DEFAULT_PER_PAGE;
        $offset   = ($page - 1) * $per_page;

        $notifications = MDCAT_Platform_Notification_Service::get_notifications($user_id, $per_page, $offset);
        $unread_count  = MDCAT_Platform_Notification_Service::get_unread_count($user_id);

        wp_send_json_success([
            'notifications' => $notifications,
            'unread_count'  => $unread_count,
            'page'          => $page,
            'per_page'      => $per_page,
        ]);
    }

    /**
     * Mark a single notification as read.
     *
     * Requires 'notification_id' parameter. Ownership is verified
     * inside the service method via user_id WHERE clause.
     */
    public static function mark_read() {

        self::verify_request();

        $user_id         = get_current_user_id();
        $notification_id = isset($_POST['notification_id']) ? absint($_POST['notification_id']) : 0;

        if (!$notification_id) {
            self::send_error('missing_id', __('Notification ID is required.', 'mdcat-platform'));
        }

        $result = MDCAT_Platform_Notification_Service::mark_as_read($notification_id, $user_id);

        if (is_wp_error($result)) {
            self::send_error($result->get_error_code(), $result->get_error_message());
        }

        wp_send_json_success([
            'unread_count' => MDCAT_Platform_Notification_Service::get_unread_count($user_id),
        ]);
    }

    /**
     * Mark all notifications as read for the current student.
     */
    public static function mark_all_read() {

        self::verify_request();

        $user_id = get_current_user_id();

        $result = MDCAT_Platform_Notification_Service::mark_all_as_read($user_id);

        if (is_wp_error($result)) {
            self::send_error($result->get_error_code(), $result->get_error_message());
        }

        wp_send_json_success([
            'unread_count' => 0,
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
            self::send_error('not_logged_in', __('You must be logged in.', 'mdcat-platform'), 401);
        }
    }

    /**
     * Send a normalized JSON error response.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
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
