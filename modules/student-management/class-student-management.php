<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/student-management/services/class-student-directory-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/student-management/services/class-student-profile-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/student-management/services/class-student-status-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/student-management/ajax/class-student-management-ajax.php';
require_once MDCAT_PLATFORM_PATH . 'modules/student-management/views/class-student-management-view.php';

class MDCAT_Platform_Student_Management {

    /**
     * Bootstrap the student management module.
     *
     * The student management module provides administrators with a
     * centralized interface for viewing, searching, monitoring, and
     * managing student accounts. It is a pure aggregation layer that
     * reuses existing analytics, progress, streak, enrollment, and
     * reporting services — no new database tables are created.
     *
     * Access control integration:
     * Registers filter hooks on existing access control filters to
     * deny platform access for suspended students. This uses the
     * filter-based extension points already built into the access
     * module — the access module itself is never modified.
     */
    public static function init() {

        MDCAT_Platform_Student_Management_Ajax::init();

        // Register access control filters for suspended students.
        add_filter('mdcat_can_access_quiz', ['MDCAT_Platform_Student_Status_Service', 'check_suspended'], 20, 3);
        add_filter('mdcat_can_access_dashboard', ['MDCAT_Platform_Student_Status_Service', 'check_suspended_dashboard'], 20, 2);
        add_filter('mdcat_can_access_analytics', ['MDCAT_Platform_Student_Status_Service', 'check_suspended_dashboard'], 20, 2);
        add_filter('mdcat_can_access_revision', ['MDCAT_Platform_Student_Status_Service', 'check_suspended_dashboard'], 20, 2);
        add_filter('mdcat_can_access_streak', ['MDCAT_Platform_Student_Status_Service', 'check_suspended_dashboard'], 20, 2);
    }
}
