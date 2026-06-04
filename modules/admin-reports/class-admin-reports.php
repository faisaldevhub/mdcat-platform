<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/admin-reports/services/class-admin-stats-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/admin-reports/services/class-admin-student-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/admin-reports/services/class-admin-performance-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/admin-reports/ajax/class-admin-reports-ajax.php';
require_once MDCAT_PLATFORM_PATH . 'modules/admin-reports/views/class-admin-reports-view.php';

class MDCAT_Platform_Admin_Reports {

    /**
     * Bootstrap the admin reporting and dashboard module.
     *
     * The admin reports module is a pure aggregation layer — it loads
     * its own services, AJAX handler, and view but never duplicates
     * logic from student-facing modules. All data is derived from
     * direct database queries against existing tables.
     */
    public static function init() {

        MDCAT_Platform_Admin_Reports_Ajax::init();
    }
}
