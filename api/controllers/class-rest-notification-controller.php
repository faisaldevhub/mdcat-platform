<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller for student notifications.
 *
 * Exposes the notification feed and read-state management
 * through 3 endpoints that mirror the existing AJAX handlers.
 *
 * Endpoints:
 *
 *   GET  /notifications          — Paginated notification feed.
 *   POST /notifications/{id}/read — Mark a single notification as read.
 *   POST /notifications/read-all  — Mark all notifications as read.
 *
 * Delegates to:
 *
 *   - Notification_Service → feed, unread count, mark read
 *
 * Ownership enforcement on mark-read is handled inside the
 * service via the user_id WHERE clause. A request to mark
 * another user's notification silently affects 0 rows.
 *
 * This controller contains NO business logic, NO SQL, and NO
 * direct database access.
 */
class MDCAT_Platform_REST_Notification_Controller
    extends MDCAT_Platform_REST_Base_Controller {

    /**
     * Register all notification routes.
     *
     * Route order matters: /notifications/read-all MUST be registered
     * before /notifications/(?P<id>\d+)/read so that WordPress does
     * not match "read-all" as a numeric {id}.
     */
    public static function register_routes() {

        register_rest_route(self::$namespace, '/notifications', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_notifications'],
            'permission_callback' => [__CLASS__, 'check_student_access'],
        ]);

        register_rest_route(self::$namespace, '/notifications/read-all', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'mark_all_read'],
            'permission_callback' => [__CLASS__, 'check_student_access'],
        ]);

        register_rest_route(self::$namespace, '/notifications/(?P<id>\d+)/read', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'mark_read'],
            'permission_callback' => [__CLASS__, 'check_student_access'],
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /notifications
    // ------------------------------------------------------------------

    /**
     * Return paginated notification feed with unread count.
     *
     * Pagination uses a fixed per_page of 15 (DEFAULT_PER_PAGE from
     * the service). The frontend detects the last page when the
     * returned notifications array is empty.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_notifications( $request ) {

        $user_id  = self::get_current_user_id($request);
        $page     = max(1, absint($request->get_param('page')));
        $per_page = MDCAT_Platform_Notification_Service::DEFAULT_PER_PAGE;
        $offset   = ($page - 1) * $per_page;

        $notifications = MDCAT_Platform_Notification_Service::get_notifications($user_id, $per_page, $offset);
        $unread_count  = MDCAT_Platform_Notification_Service::get_unread_count($user_id);

        return self::success(
            [
                'notifications' => $notifications,
                'unread_count'  => $unread_count,
                'page'          => $page,
                'per_page'      => $per_page,
            ],
            'Notifications loaded.'
        );
    }

    // ------------------------------------------------------------------
    //  POST /notifications/{id}/read
    // ------------------------------------------------------------------

    /**
     * Mark a single notification as read.
     *
     * Ownership is enforced inside the service: the WHERE clause
     * includes user_id, so marking another user's notification
     * silently affects 0 rows. This matches existing AJAX behavior.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function mark_read( $request ) {

        $user_id         = self::get_current_user_id($request);
        $notification_id = absint($request->get_param('id'));

        if (!$notification_id) {
            return self::error('missing_id', __('Notification ID is required.', 'mdcat-platform'), 400);
        }

        $result = MDCAT_Platform_Notification_Service::mark_as_read($notification_id, $user_id);

        if (is_wp_error($result)) {
            return self::wp_error($result);
        }

        return self::success(
            [
                'unread_count' => MDCAT_Platform_Notification_Service::get_unread_count($user_id),
            ],
            'Notification marked as read.'
        );
    }

    // ------------------------------------------------------------------
    //  POST /notifications/read-all
    // ------------------------------------------------------------------

    /**
     * Mark all notifications as read for the authenticated student.
     *
     * Returns unread_count as 0 to match AJAX handler behavior.
     * The service scopes the UPDATE to the authenticated user_id.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function mark_all_read( $request ) {

        $user_id = self::get_current_user_id($request);

        $result = MDCAT_Platform_Notification_Service::mark_all_as_read($user_id);

        if (is_wp_error($result)) {
            return self::wp_error($result);
        }

        return self::success(
            [
                'unread_count' => 0,
            ],
            'All notifications marked as read.'
        );
    }
}
