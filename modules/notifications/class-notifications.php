<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/notifications/services/class-notification-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/notifications/services/class-notification-email-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/notifications/ajax/class-notification-ajax.php';

class MDCAT_Platform_Notifications {

    /**
     * Bootstrap the notifications module.
     *
     * Registers AJAX endpoints and hooks into platform events
     * fired by other modules. All listeners run at priority 20
     * (after gamification at priority 10) to ensure all processing
     * is complete before notifications reference the results.
     */
    public static function init() {

        MDCAT_Platform_Notification_Ajax::init();

        // Event listeners — priority 20 (after gamification at 10).
        add_action('mdcat_badge_unlocked', [__CLASS__, 'on_badge_unlocked'], 20, 2);
        add_action('mdcat_achievement_unlocked', [__CLASS__, 'on_achievement_unlocked'], 20, 2);
        add_action('mdcat_enrollment_approved', [__CLASS__, 'on_enrollment_approved'], 20, 2);
        add_action('mdcat_enrollment_rejected', [__CLASS__, 'on_enrollment_rejected'], 20, 1);

        // Daily cleanup cron.
        add_action('mdcat_notification_cleanup', [__CLASS__, 'run_cleanup']);

        if (!wp_next_scheduled('mdcat_notification_cleanup')) {
            wp_schedule_event(time(), 'daily', 'mdcat_notification_cleanup');
        }
    }

    /**
     * Handle badge unlock event.
     *
     * Creates an in-app notification and sends an email when
     * a student earns a new badge.
     *
     * @param int    $user_id    WordPress user ID.
     * @param string $badge_slug Badge slug identifier.
     */
    public static function on_badge_unlocked( $user_id, $badge_slug ) {

        $user_id = absint($user_id);

        if (!$user_id || empty($badge_slug)) {
            return;
        }

        // Resolve badge slug to display data.
        $definitions = MDCAT_Platform_Badge_Service::get_badge_definitions();

        if (!isset($definitions[$badge_slug])) {
            return;
        }

        $badge = $definitions[$badge_slug];

        // Idempotency: check if notification already exists.
        if (MDCAT_Platform_Notification_Service::exists($user_id, 'badge', crc32($badge_slug))) {
            return;
        }

        // Create in-app notification.
        MDCAT_Platform_Notification_Service::create($user_id, [
            'type'        => 'badge_unlock',
            'title'       => __('Badge Unlocked!', 'mdcat-platform'),
            'message'     => sprintf(
                /* translators: %s: badge name */
                __('You earned the "%s" badge! %s', 'mdcat-platform'),
                $badge['name'],
                $badge['description']
            ),
            'icon'        => $badge['icon'],
            'source_type' => 'badge',
            'source_id'   => crc32($badge_slug),
        ]);

        // Send email notification.
        $user = get_userdata($user_id);

        if ($user) {
            MDCAT_Platform_Notification_Email_Service::send_badge_email(
                $user->user_email,
                $user->display_name,
                $badge
            );
        }
    }

    /**
     * Handle achievement unlock event.
     *
     * @param int    $user_id          WordPress user ID.
     * @param string $achievement_slug Achievement slug identifier.
     */
    public static function on_achievement_unlocked( $user_id, $achievement_slug ) {

        $user_id = absint($user_id);

        if (!$user_id || empty($achievement_slug)) {
            return;
        }

        $definitions = MDCAT_Platform_Achievement_Service::get_achievement_definitions();

        if (!isset($definitions[$achievement_slug])) {
            return;
        }

        $achievement = $definitions[$achievement_slug];

        if (MDCAT_Platform_Notification_Service::exists($user_id, 'achievement', crc32($achievement_slug))) {
            return;
        }

        MDCAT_Platform_Notification_Service::create($user_id, [
            'type'        => 'achievement_unlock',
            'title'       => __('Achievement Unlocked!', 'mdcat-platform'),
            'message'     => sprintf(
                /* translators: %s: achievement name */
                __('You unlocked "%s"! %s', 'mdcat-platform'),
                $achievement['name'],
                $achievement['description']
            ),
            'icon'        => $achievement['icon'],
            'source_type' => 'achievement',
            'source_id'   => crc32($achievement_slug),
        ]);

        $user = get_userdata($user_id);

        if ($user) {
            MDCAT_Platform_Notification_Email_Service::send_achievement_email(
                $user->user_email,
                $user->display_name,
                $achievement
            );
        }
    }

    /**
     * Handle enrollment approved event.
     *
     * @param int    $wp_user_id WordPress user ID of the new student.
     * @param object $request    Enrollment request object.
     */
    public static function on_enrollment_approved( $wp_user_id, $request ) {

        $wp_user_id = absint($wp_user_id);

        if (!$wp_user_id) {
            return;
        }

        $request_id = isset($request->id) ? absint($request->id) : 0;

        if (MDCAT_Platform_Notification_Service::exists($wp_user_id, 'enrollment_approved', $request_id)) {
            return;
        }

        MDCAT_Platform_Notification_Service::create($wp_user_id, [
            'type'        => 'enrollment_approved',
            'title'       => __('Welcome!', 'mdcat-platform'),
            'message'     => __('Your enrollment has been approved. Start your learning journey now!', 'mdcat-platform'),
            'icon'        => '✅',
            'source_type' => 'enrollment_approved',
            'source_id'   => $request_id,
        ]);
    }

    /**
     * Handle enrollment rejected event.
     *
     * @param object $request Enrollment request object.
     */
    public static function on_enrollment_rejected( $request ) {

        // Rejected students may not have a WP user account.
        // We store the notification for the email address if a user exists.
        $email = isset($request->email) ? $request->email : '';

        if (empty($email)) {
            return;
        }

        $user = get_user_by('email', $email);

        // If user doesn't exist (rejected before account creation), send email only.
        $full_name  = isset($request->full_name) ? $request->full_name : '';
        $reason     = isset($request->admin_notes) ? $request->admin_notes : '';
        $request_id = isset($request->id) ? absint($request->id) : 0;

        MDCAT_Platform_Notification_Email_Service::send_enrollment_rejected_email(
            $email,
            $full_name,
            $reason
        );

        // If user exists, also create an in-app notification.
        if ($user) {
            if (MDCAT_Platform_Notification_Service::exists($user->ID, 'enrollment_rejected', $request_id)) {
                return;
            }

            MDCAT_Platform_Notification_Service::create($user->ID, [
                'type'        => 'enrollment_rejected',
                'title'       => __('Enrollment Update', 'mdcat-platform'),
                'message'     => __('Your enrollment request was not approved. Please check your email for details.', 'mdcat-platform'),
                'icon'        => '📋',
                'source_type' => 'enrollment_rejected',
                'source_id'   => $request_id,
            ]);
        }
    }

    /**
     * Run daily notification cleanup.
     *
     * Deletes notifications older than the configured retention period.
     */
    public static function run_cleanup() {

        MDCAT_Platform_Notification_Service::cleanup_old_notifications();
    }
}
