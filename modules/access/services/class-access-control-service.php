<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Access_Control_Service {

    /**
     * Check if the current user is logged in.
     *
     * This is the most fundamental access check. All other permission
     * methods call this first before evaluating feature-level access.
     *
     * @return true|WP_Error
     */
    public static function is_logged_in() {

        if (is_user_logged_in()) {
            return true;
        }

        return new WP_Error(
            'login_required',
            __('You must be logged in to access this content.', 'mdcat-platform')
        );
    }

    /**
     * Check if a user can access a quiz collection.
     *
     * MVP: all logged-in students can access all quizzes.
     *
     * Future expansion points:
     * - Check enrollment status for the collection
     * - Check payment/subscription status
     * - Check institution license for the collection's subject
     * - Check if collection is free or premium
     *
     * The apply_filters hook allows external plugins (payment gateways,
     * enrollment managers) to modify access decisions without editing
     * this class.
     *
     * @param int $user_id       WordPress user ID.
     * @param int $collection_id Quiz collection ID.
     * @return true|WP_Error
     */
    public static function can_access_quiz( $user_id, $collection_id ) {

        $login_check = self::is_logged_in();

        if (is_wp_error($login_check)) {
            return $login_check;
        }

        $user_id       = absint($user_id);
        $collection_id = absint($collection_id);

        if (!$user_id) {
            return new WP_Error(
                'invalid_user',
                __('A valid user is required.', 'mdcat-platform')
            );
        }

        /**
         * Filter whether a user can access a quiz collection.
         *
         * Returning a WP_Error from this filter denies access.
         * Returning true allows access.
         *
         * Future enrollment/payment modules should hook here:
         *
         *     add_filter('mdcat_can_access_quiz', function($access, $user_id, $collection_id) {
         *         if (!MyEnrollmentService::is_enrolled($user_id, $collection_id)) {
         *             return new WP_Error('not_enrolled', 'Please enroll to access this quiz.');
         *         }
         *         return $access;
         *     }, 10, 3);
         *
         * @param true     $access        Default access (allowed for all logged-in users).
         * @param int      $user_id       WordPress user ID.
         * @param int      $collection_id Quiz collection ID.
         */
        return apply_filters('mdcat_can_access_quiz', true, $user_id, $collection_id);
    }

    /**
     * Check if a user can access the student dashboard.
     *
     * MVP: all logged-in students can access the dashboard.
     *
     * Future: check account status, subscription tier, etc.
     *
     * @param int $user_id WordPress user ID.
     * @return true|WP_Error
     */
    public static function can_access_dashboard( $user_id ) {

        $login_check = self::is_logged_in();

        if (is_wp_error($login_check)) {
            return $login_check;
        }

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error(
                'invalid_user',
                __('A valid user is required.', 'mdcat-platform')
            );
        }

        /**
         * Filter whether a user can access the student dashboard.
         *
         * @param true $access  Default access.
         * @param int  $user_id WordPress user ID.
         */
        return apply_filters('mdcat_can_access_dashboard', true, $user_id);
    }

    /**
     * Check if a user can access performance analytics.
     *
     * MVP: all logged-in students can access analytics.
     *
     * Future: premium tier gating, institution-level analytics, etc.
     *
     * @param int $user_id WordPress user ID.
     * @return true|WP_Error
     */
    public static function can_access_analytics( $user_id ) {

        $login_check = self::is_logged_in();

        if (is_wp_error($login_check)) {
            return $login_check;
        }

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error(
                'invalid_user',
                __('A valid user is required.', 'mdcat-platform')
            );
        }

        /**
         * Filter whether a user can access performance analytics.
         *
         * @param true $access  Default access.
         * @param int  $user_id WordPress user ID.
         */
        return apply_filters('mdcat_can_access_analytics', true, $user_id);
    }

    /**
     * Check if a user can access revision features (bookmarks, wrong questions).
     *
     * MVP: all logged-in students can access revision.
     *
     * Future: premium revision tools, advanced revision modes, etc.
     *
     * @param int $user_id WordPress user ID.
     * @return true|WP_Error
     */
    public static function can_access_revision( $user_id ) {

        $login_check = self::is_logged_in();

        if (is_wp_error($login_check)) {
            return $login_check;
        }

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error(
                'invalid_user',
                __('A valid user is required.', 'mdcat-platform')
            );
        }

        /**
         * Filter whether a user can access revision features.
         *
         * @param true $access  Default access.
         * @param int  $user_id WordPress user ID.
         */
        return apply_filters('mdcat_can_access_revision', true, $user_id);
    }

    /**
     * Check if a user can access streak/gamification features.
     *
     * MVP: all logged-in students can access streak data.
     *
     * Future: gamification tiers, premium streak insights, etc.
     *
     * @param int $user_id WordPress user ID.
     * @return true|WP_Error
     */
    public static function can_access_streak( $user_id ) {

        $login_check = self::is_logged_in();

        if (is_wp_error($login_check)) {
            return $login_check;
        }

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error(
                'invalid_user',
                __('A valid user is required.', 'mdcat-platform')
            );
        }

        /**
         * Filter whether a user can access streak features.
         *
         * @param true $access  Default access.
         * @param int  $user_id WordPress user ID.
         */
        return apply_filters('mdcat_can_access_streak', true, $user_id);
    }

    /**
     * Check if a user has administrative privileges.
     *
     * Uses WordPress capability checks. This is the foundation for
     * future admin dashboard, content management, and user management
     * access control.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function is_admin_user( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return false;
        }

        return user_can($user_id, 'manage_options');
    }
}
