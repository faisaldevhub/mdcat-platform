<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Deactivator {

    public static function deactivate() {

        // Unschedule the notification cleanup cron to prevent
        // orphaned scheduled tasks after plugin deactivation.
        wp_clear_scheduled_hook('mdcat_notification_cleanup');

    }
}