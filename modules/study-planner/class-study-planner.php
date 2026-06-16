<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/study-planner/services/class-recommendation-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/study-planner/ajax/class-study-planner-ajax.php';

class MDCAT_Platform_Study_Planner {

    /**
     * Bootstrap the study planner module.
     *
     * The study planner is a pure read-only aggregation layer. It reads
     * from existing platform services (analytics, progress, streak,
     * revision) and applies scoring logic to produce personalized
     * study recommendations. It has zero database tables of its own.
     */
    public static function init() {

        MDCAT_Platform_Study_Planner_Ajax::init();
    }
}
