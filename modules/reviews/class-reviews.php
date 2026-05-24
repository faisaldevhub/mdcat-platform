<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/reviews/services/class-review-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/reviews/ajax/class-review-ajax.php';

class MDCAT_Platform_Reviews {

    /**
     * Bootstrap the review and explanation backend module.
     */
    public static function init() {

        MDCAT_Platform_Review_Ajax::init();
    }
}
