<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/dashboard/services/class-dashboard-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/dashboard/ajax/class-dashboard-ajax.php';

class MDCAT_Platform_Dashboard {

    /**
     * Bootstrap the student dashboard orchestration module.
     *
     * The dashboard is a pure aggregation layer — it loads its own service
     * and AJAX handler but never duplicates logic from analytics, attempts,
     * or revision modules.
     */
    public static function init() {

        MDCAT_Platform_Dashboard_Ajax::init();
    }
}
