<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Loader {

    public static function init() {

        /**
         * Load Admin Files
         */

        if (is_admin()) {

            require_once MDCAT_PLATFORM_PATH . 'admin/class-admin-menu.php';
            require_once MDCAT_PLATFORM_PATH . 'modules/subjects/class-subjects-handler.php';
            require_once MDCAT_PLATFORM_PATH . 'modules/subjects/class-subjects.php';
            require_once MDCAT_PLATFORM_PATH . 'modules/chapters/class-chapters-handler.php';
            require_once MDCAT_PLATFORM_PATH . 'modules/chapters/class-chapters.php';
            require_once MDCAT_PLATFORM_PATH . 'modules/collections/class-collections-handler.php';
            require_once MDCAT_PLATFORM_PATH . 'modules/collections/class-collections.php';
            require_once MDCAT_PLATFORM_PATH . 'modules/questions/class-questions-handler.php';
            require_once MDCAT_PLATFORM_PATH . 'modules/questions/class-questions.php';

            MDCAT_Platform_Admin_Menu::init();
            MDCAT_Platform_Subjects::init();
            MDCAT_Platform_Chapters::init();
            MDCAT_Platform_Collections::init();
            MDCAT_Platform_Questions::init();

            require_once MDCAT_PLATFORM_PATH . 'modules/admin-reports/class-admin-reports.php';
            MDCAT_Platform_Admin_Reports::init();

            require_once MDCAT_PLATFORM_PATH . 'modules/bulk-import/class-bulk-import.php';
            MDCAT_Platform_Bulk_Import::init();

            require_once MDCAT_PLATFORM_PATH . 'modules/student-management/class-student-management.php';
            MDCAT_Platform_Student_Management::init();
        }

        /**
         * Load Backend Modules
         */

        require_once MDCAT_PLATFORM_PATH . 'modules/access/class-access.php';

        MDCAT_Platform_Access::init();

        require_once MDCAT_PLATFORM_PATH . 'modules/attempts/class-attempts-handler.php';
        require_once MDCAT_PLATFORM_PATH . 'modules/attempts/class-attempts.php';
        require_once MDCAT_PLATFORM_PATH . 'modules/reviews/class-reviews.php';
        require_once MDCAT_PLATFORM_PATH . 'modules/analytics/class-analytics.php';
        require_once MDCAT_PLATFORM_PATH . 'modules/revision/class-revision.php';

        MDCAT_Platform_Attempts::init();
        MDCAT_Platform_Reviews::init();
        MDCAT_Platform_Analytics::init();
        MDCAT_Platform_Revision::init();

        require_once MDCAT_PLATFORM_PATH . 'modules/dashboard/class-dashboard.php';

        MDCAT_Platform_Dashboard::init();

        require_once MDCAT_PLATFORM_PATH . 'modules/gamification/class-gamification.php';

        MDCAT_Platform_Gamification::init();

        require_once MDCAT_PLATFORM_PATH . 'modules/progress/class-progress.php';

        MDCAT_Platform_Progress::init();

        require_once MDCAT_PLATFORM_PATH . 'modules/study-planner/class-study-planner.php';

        MDCAT_Platform_Study_Planner::init();

        require_once MDCAT_PLATFORM_PATH . 'modules/enrollment/class-enrollment.php';

        MDCAT_Platform_Enrollment::init();

        require_once MDCAT_PLATFORM_PATH . 'modules/notifications/class-notifications.php';

        MDCAT_Platform_Notifications::init();

        /**
         * Load Public Files
         */

        require_once MDCAT_PLATFORM_PATH . 'frontend/class-frontend.php';

        MDCAT_Platform_Frontend::init();
    }
}
