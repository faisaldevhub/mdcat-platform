<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Activator {

    public static function activate() {

        require_once MDCAT_PLATFORM_PATH . 'database/class-database.php';

        MDCAT_Platform_Database::create_tables();

    }
}