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

            MDCAT_Platform_Admin_Menu::init();
            MDCAT_Platform_Subjects::init();
        }

        /**
         * Load Public Files
         */

        // Future frontend files
    }
}
