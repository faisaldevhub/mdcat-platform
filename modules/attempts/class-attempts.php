<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/attempts/services/class-quiz-engine.php';

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
