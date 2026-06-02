<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/revision/services/class-revision-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/revision/ajax/class-revision-ajax.php';

class MDCAT_Platform_Revision {

    /**
     * Bootstrap the revision learning-retention layer.
     */
    public static function init() {

        MDCAT_Platform_Revision_Ajax::init();
    }
}
