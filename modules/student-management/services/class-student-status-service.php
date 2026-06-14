<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Student_Status_Service {

    const META_KEY = 'mdcat_account_status';

    const STATUS_ACTIVE    = 'active';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * Suspend a student account.
     *
     * Sets the mdcat_account_status user meta to 'suspended'.
     * Prevents admins from suspending themselves.
     *
     * @param int $user_id  WordPress user ID of the student.
     * @param int $admin_id WordPress user ID of the admin performing the action.
     * @return true|WP_Error
     */
    public static function suspend_student( $user_id, $admin_id ) {

        $user_id  = absint($user_id);
        $admin_id = absint($admin_id);

        $validation = self::validate_status_change($user_id, $admin_id);

        if (is_wp_error($validation)) {
            return $validation;
        }

        if (self::is_suspended($user_id)) {
            return new WP_Error(
                'already_suspended',
                __('This student is already suspended.', 'mdcat-platform')
            );
        }

        update_user_meta($user_id, self::META_KEY, self::STATUS_SUSPENDED);
        update_user_meta($user_id, 'mdcat_status_changed_by', $admin_id);
        update_user_meta($user_id, 'mdcat_status_changed_at', current_time('mysql'));

        return true;
    }

    /**
     * Activate a student account.
     *
     * Sets the mdcat_account_status user meta to 'active'.
     *
     * @param int $user_id  WordPress user ID of the student.
     * @param int $admin_id WordPress user ID of the admin performing the action.
     * @return true|WP_Error
     */
    public static function activate_student( $user_id, $admin_id ) {

        $user_id  = absint($user_id);
        $admin_id = absint($admin_id);

        $validation = self::validate_status_change($user_id, $admin_id);

        if (is_wp_error($validation)) {
            return $validation;
        }

        if (!self::is_suspended($user_id)) {
            return new WP_Error(
                'already_active',
                __('This student is already active.', 'mdcat-platform')
            );
        }

        update_user_meta($user_id, self::META_KEY, self::STATUS_ACTIVE);
        update_user_meta($user_id, 'mdcat_status_changed_by', $admin_id);
        update_user_meta($user_id, 'mdcat_status_changed_at', current_time('mysql'));

        return true;
    }

    /**
     * Get the current account status for a student.
     *
     * @param int $user_id WordPress user ID.
     * @return string 'active' or 'suspended'.
     */
    public static function get_student_status( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return self::STATUS_ACTIVE;
        }

        $status = get_user_meta($user_id, self::META_KEY, true);

        return $status === self::STATUS_SUSPENDED ? self::STATUS_SUSPENDED : self::STATUS_ACTIVE;
    }

    /**
     * Check if a student account is suspended.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function is_suspended( $user_id ) {

        return self::get_student_status($user_id) === self::STATUS_SUSPENDED;
    }

    /**
     * Access control filter callback for quizzes.
     *
     * Hooked onto mdcat_can_access_quiz to deny access for
     * suspended students. Uses the existing filter system in
     * MDCAT_Platform_Access_Control_Service.
     *
     * @param true|WP_Error $access        Current access decision.
     * @param int           $user_id       WordPress user ID.
     * @param int           $collection_id Quiz collection ID.
     * @return true|WP_Error
     */
    public static function check_suspended( $access, $user_id, $collection_id ) {

        if (is_wp_error($access)) {
            return $access;
        }

        if (self::is_suspended($user_id)) {
            return new WP_Error(
                'account_suspended',
                __('Your account has been suspended. Please contact the administrator.', 'mdcat-platform')
            );
        }

        return $access;
    }

    /**
     * Access control filter callback for dashboard.
     *
     * Hooked onto mdcat_can_access_dashboard to deny access for
     * suspended students.
     *
     * @param true|WP_Error $access  Current access decision.
     * @param int           $user_id WordPress user ID.
     * @return true|WP_Error
     */
    public static function check_suspended_dashboard( $access, $user_id ) {

        if (is_wp_error($access)) {
            return $access;
        }

        if (self::is_suspended($user_id)) {
            return new WP_Error(
                'account_suspended',
                __('Your account has been suspended. Please contact the administrator.', 'mdcat-platform')
            );
        }

        return $access;
    }

    /**
     * Validate common preconditions for status changes.
     *
     * @param int $user_id  Target student ID.
     * @param int $admin_id Admin performing the action.
     * @return true|WP_Error
     */
    private static function validate_status_change( $user_id, $admin_id ) {

        if (!$user_id) {
            return new WP_Error(
                'invalid_user',
                __('A valid student is required.', 'mdcat-platform')
            );
        }

        if (!$admin_id) {
            return new WP_Error(
                'invalid_admin',
                __('A valid administrator is required.', 'mdcat-platform')
            );
        }

        // Prevent admin from suspending themselves.
        if ($user_id === $admin_id) {
            return new WP_Error(
                'self_action',
                __('You cannot change your own account status.', 'mdcat-platform')
            );
        }

        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error(
                'user_not_found',
                __('Student not found.', 'mdcat-platform')
            );
        }

        // Prevent status changes on admin users.
        if (user_can($user_id, 'manage_options')) {
            return new WP_Error(
                'cannot_manage_admin',
                __('Cannot change the status of an administrator account.', 'mdcat-platform')
            );
        }

        return true;
    }
}
