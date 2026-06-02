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
        }

        /**
         * Load Backend Modules
         */

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

        /**
         * Load Public Files
         */

        require_once MDCAT_PLATFORM_PATH . 'frontend/class-frontend.php';

        MDCAT_Platform_Frontend::init();
    }
}
