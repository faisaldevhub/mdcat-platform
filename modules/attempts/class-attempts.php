<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Attempts {

    /**
     * Bootstrap the attempts backend module.
     *
     * No admin menu, AJAX endpoint, REST route, or frontend UI is registered here yet.
     */
    public static function init() {

        MDCAT_Platform_Attempts_Handler::init();
    }
}
