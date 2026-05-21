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

            MDCAT_Platform_Admin_Menu::init();
        }

        /**
         * Load Public Files
         */

        // Future frontend files
    }
}