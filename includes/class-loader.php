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
         * Load Public Files
         */

        // Future frontend files
    }
}
