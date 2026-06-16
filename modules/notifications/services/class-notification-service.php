<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Notification_Service {

    /**
     * Maximum notifications returned in dashboard preview.
     */
    const DASHBOARD_PREVIEW_LIMIT = 5;

    /**
     * Default page size for paginated notification feed.
     */
    const DEFAULT_PER_PAGE = 15;

    /**
     * Notification retention period in days.
     */
    const RETENTION_DAYS = 90;

    /**
     * Get the notifications table name.
     *
     * @return string
     */
    private static function get_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_notifications';
    }

    /**
     * Create a new notification.
     *
     * Inserts a single notification row for the specified user.
     * Callers should use exists() first to prevent duplicates.
     *
     * @param int   $user_id Recipient user ID.
     * @param array $data    Notification data: type, title, message, icon, source_type, source_id.
     * @return int|WP_Error Notification ID on success, WP_Error on failure.
     */
    public static function create( $user_id, $data ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        // source_type and source_id are required for idempotency.
        // Without them, exists() cannot prevent duplicate notifications.
        $source_type = isset($data['source_type']) ? sanitize_key($data['source_type']) : '';
        $source_id   = isset($data['source_id']) ? absint($data['source_id']) : 0;

        if (empty($source_type) || !$source_id) {
            return new WP_Error(
                'missing_source',
                __('source_type and source_id are required for notification idempotency.', 'mdcat-platform')
            );
        }

        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            [
                'user_id'     => $user_id,
                'type'        => sanitize_key(isset($data['type']) ? $data['type'] : 'general'),
                'title'       => sanitize_text_field(isset($data['title']) ? $data['title'] : ''),
                'message'     => sanitize_textarea_field(isset($data['message']) ? $data['message'] : ''),
                'icon'        => sanitize_text_field(isset($data['icon']) ? $data['icon'] : '🔔'),
                'source_type' => $source_type,
                'source_id'   => $source_id,
                'is_read'     => 0,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );

        if (false === $inserted) {
            return new WP_Error('insert_failed', __('Failed to create notification.', 'mdcat-platform'));
        }

        return absint($wpdb->insert_id);
    }

    /**
     * Get paginated notifications for a user.
     *
     * Returns notifications sorted by recency (newest first).
     *
     * @param int $user_id  WordPress user ID.
     * @param int $per_page Number of notifications per page.
     * @param int $offset   Offset for pagination.
     * @return array Notification rows.
     */
    public static function get_notifications( $user_id, $per_page = 0, $offset = 0 ) {

        global $wpdb;

        $user_id  = absint($user_id);
        $per_page = $per_page > 0 ? absint($per_page) : self::DEFAULT_PER_PAGE;
        $offset   = absint($offset);

        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, type, title, message, icon, source_type, source_id, is_read, created_at
                FROM {$table}
                WHERE user_id = %d
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            )
        );

        return self::format_notifications($rows);
    }

    /**
     * Get the dashboard preview for a user.
     *
     * Returns unread count + the 5 most recent notifications.
     * This is the lightweight summary included in the dashboard response.
     *
     * @param int $user_id WordPress user ID.
     * @return array Preview data with unread_count and notifications.
     */
    public static function get_dashboard_preview( $user_id ) {

        $user_id = absint($user_id);

        return [
            'unread_count'  => self::get_unread_count($user_id),
            'notifications' => self::get_notifications($user_id, self::DASHBOARD_PREVIEW_LIMIT, 0),
        ];
    }

    /**
     * Get the count of unread notifications for a user.
     *
     * Uses the user_unread composite index for fast lookup.
     *
     * @param int $user_id WordPress user ID.
     * @return int Unread notification count.
     */
    public static function get_unread_count( $user_id ) {

        global $wpdb;

        $table = self::get_table_name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
                absint($user_id)
            )
        );

        return absint($count);
    }

    /**
     * Mark a single notification as read.
     *
     * Includes user_id in the WHERE clause to prevent users from
     * marking another user's notifications.
     *
     * @param int $notification_id Notification ID.
     * @param int $user_id         WordPress user ID (ownership check).
     * @return true|WP_Error
     */
    public static function mark_as_read( $notification_id, $user_id ) {

        global $wpdb;

        $table = self::get_table_name();

        $updated = $wpdb->update(
            $table,
            [
                'is_read' => 1,
                'read_at' => current_time('mysql'),
            ],
            [
                'id'      => absint($notification_id),
                'user_id' => absint($user_id),
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        if (false === $updated) {
            return new WP_Error('update_failed', __('Failed to mark notification as read.', 'mdcat-platform'));
        }

        return true;
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return true|WP_Error
     */
    public static function mark_all_as_read( $user_id ) {

        global $wpdb;

        $table = self::get_table_name();

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET is_read = 1, read_at = %s WHERE user_id = %d AND is_read = 0",
                current_time('mysql'),
                absint($user_id)
            )
        );

        return true;
    }

    /**
     * Check if a notification already exists for a user.
     *
     * Used for idempotency — prevents duplicate notifications from
     * retried or concurrent requests.
     *
     * @param int    $user_id     WordPress user ID.
     * @param string $source_type Source type identifier.
     * @param int    $source_id   Source object ID.
     * @return bool True if notification already exists.
     */
    public static function exists( $user_id, $source_type, $source_id ) {

        global $wpdb;

        $table = self::get_table_name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE user_id = %d AND source_type = %s AND source_id = %d",
                absint($user_id),
                sanitize_key($source_type),
                absint($source_id)
            )
        );

        return absint($count) > 0;
    }

    /**
     * Delete notifications older than the retention period.
     *
     * Called by the daily WP-Cron cleanup task. Uses the created_at
     * index for efficient deletion.
     *
     * @return int Number of deleted rows.
     */
    public static function cleanup_old_notifications() {

        global $wpdb;

        $table = self::get_table_name();

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)",
                current_time('mysql'),
                self::RETENTION_DAYS
            )
        );

        return absint($deleted);
    }

    /**
     * Format raw notification rows for API responses.
     *
     * @param array $rows Raw database rows.
     * @return array Formatted notifications.
     */
    private static function format_notifications( $rows ) {

        $notifications = [];

        foreach ((array) $rows as $row) {
            $notifications[] = [
                'id'          => absint($row->id),
                'type'        => $row->type,
                'title'       => $row->title,
                'message'     => $row->message,
                'icon'        => $row->icon ? $row->icon : '🔔',
                'source_type' => $row->source_type,
                'source_id'   => absint($row->source_id),
                'is_read'     => (bool) $row->is_read,
                'created_at'  => $row->created_at,
            ];
        }

        return $notifications;
    }
}
