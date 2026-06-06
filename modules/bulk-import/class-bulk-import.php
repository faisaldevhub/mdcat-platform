<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/bulk-import/services/class-csv-parser-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/bulk-import/services/class-import-validator-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/bulk-import/services/class-entity-resolver-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/bulk-import/services/class-import-processor-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/bulk-import/ajax/class-bulk-import-ajax.php';
require_once MDCAT_PLATFORM_PATH . 'modules/bulk-import/views/class-bulk-import-view.php';

class MDCAT_Platform_Bulk_Import {

    /**
     * Bootstrap the bulk import module.
     *
     * The import module is admin-only and provides CSV-based
     * question import with validation, entity resolution,
     * duplicate detection, and batch insertion.
     */
    public static function init() {

        MDCAT_Platform_Bulk_Import_Ajax::init();
    }
}
