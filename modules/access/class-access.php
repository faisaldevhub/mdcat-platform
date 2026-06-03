<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/access/services/class-access-control-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/access/middleware/class-access-middleware.php';

class MDCAT_Platform_Access {

    /**
     * Bootstrap the access control module.
     *
     * This module loads before all other modules so that access
     * checks are available for any module to use. No hooks are
     * registered here — the service and middleware are static
     * utility classes called on demand.
     *
     * Current MVP: login-based access control.
     * Future: enrollment, payment, subscription, institution, role gating.
     */
    public static function init() {

        /**
         * Fires after the access control module is initialized.
         *
         * External plugins can hook here to register their own
         * access filters on the mdcat_can_access_* hooks.
         */
        do_action('mdcat_access_init');
    }
}
