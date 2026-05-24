<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/analytics/services/class-performance-analytics.php';
require_once MDCAT_PLATFORM_PATH . 'modules/analytics/ajax/class-analytics-ajax.php';

class MDCAT_Platform_Analytics {

    /**
     * Bootstrap the student analytics intelligence layer.
     */
    public static function init() {

        MDCAT_Platform_Analytics_Ajax::init();
    }
}
