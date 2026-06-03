<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/progress/services/class-progress-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/progress/ajax/class-progress-ajax.php';

class MDCAT_Platform_Progress {

    /**
     * Bootstrap the progress tracking module.
     *
     * Progress tracking is separated from analytics intentionally:
     * - Analytics answers "how well did you perform?"
     * - Progress answers "how much have you covered?"
     *
     * Current MVP: subject-level completion tracking.
     * Future: chapter completion, overall completion, continue learning.
     */
    public static function init() {

        MDCAT_Platform_Progress_Ajax::init();
    }
}
