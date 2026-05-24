<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/attempts/services/class-quiz-engine.php';
require_once MDCAT_PLATFORM_PATH . 'modules/attempts/ajax/class-quiz-ajax.php';

class MDCAT_Platform_Attempts {

    /**
     * Bootstrap the attempts backend module.
     *
     * No admin menu, REST route, or frontend UI is registered here yet.
     */
    public static function init() {

        MDCAT_Platform_Attempts_Handler::init();
        MDCAT_Platform_Quiz_Ajax::init();
    }
}
