<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/enrollment/services/class-enrollment-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/enrollment/services/class-enrollment-user-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/enrollment/services/class-enrollment-email-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/enrollment/ajax/class-enrollment-ajax.php';
require_once MDCAT_PLATFORM_PATH . 'modules/enrollment/views/class-enrollment-form-view.php';
require_once MDCAT_PLATFORM_PATH . 'modules/enrollment/views/class-enrollment-admin-view.php';

class MDCAT_Platform_Enrollment {

    /**
     * Bootstrap the enrollment module.
     *
     * The enrollment module handles the complete lifecycle of student
     * enrollment requests: public form submission, admin review,
     * WordPress user creation, and credential email delivery.
     *
     * This module integrates with the existing Access Control module
     * by adding an "Enroll Now" link to the login-required gate card.
     * No access control logic is modified — the existing login check
     * is the sole gate. Having a WordPress account = having access.
     */
    public static function init() {

        MDCAT_Platform_Enrollment_Ajax::init();
    }
}
