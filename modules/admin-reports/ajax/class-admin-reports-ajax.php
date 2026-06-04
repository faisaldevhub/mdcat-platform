<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Admin_Reports_Ajax {

    const NONCE_ACTION = 'mdcat_admin_reports_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register admin-only AJAX actions for the reporting dashboard.
     *
     * All actions use wp_ajax_ prefix only (no nopriv variants).
     * Every handler requires manage_options capability.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_admin_get_overview', [__CLASS__, 'get_overview']);
        add_action('wp_ajax_mdcat_admin_get_students', [__CLASS__, 'get_students']);
        add_action('wp_ajax_mdcat_admin_get_performance', [__CLASS__, 'get_performance']);
        add_action('wp_ajax_mdcat_admin_get_activity', [__CLASS__, 'get_activity']);
    }

    /**
     * Return platform-wide overview statistics.
     *
     * Thin handler — verifies security, calls the service, sends response.
     */
    public static function get_overview() {

        self::verify_request();

        $stats = MDCAT_Platform_Admin_Stats_Service::get_overview_stats();

        wp_send_json_success($stats);
    }

    /**
     * Return student reporting data.
     *
     * Aggregates most-active students and top performers into
     * a single response to minimize frontend AJAX calls.
     */
    public static function get_students() {

        self::verify_request();

        $most_active   = MDCAT_Platform_Admin_Student_Service::get_most_active_students(10);
        $top_performers = MDCAT_Platform_Admin_Student_Service::get_student_performance_summary(10);

        wp_send_json_success([
            'most_active'    => $most_active,
            'top_performers' => $top_performers,
        ]);
    }

    /**
     * Return subject performance reporting data.
     *
     * Includes the full report plus pre-sorted strongest/weakest lists
     * so the frontend doesn't need to re-sort.
     */
    public static function get_performance() {

        self::verify_request();

        $report   = MDCAT_Platform_Admin_Performance_Service::get_subject_performance_report();
        $strongest = MDCAT_Platform_Admin_Performance_Service::get_strongest_subjects(5);
        $weakest   = MDCAT_Platform_Admin_Performance_Service::get_weakest_subjects(5);

        wp_send_json_success([
            'report'    => $report,
            'strongest' => $strongest,
            'weakest'   => $weakest,
        ]);
    }

    /**
     * Return the platform-wide recent activity feed.
     */
    public static function get_activity() {

        self::verify_request();

        $activity = MDCAT_Platform_Admin_Student_Service::get_recent_activity_feed(15);

        wp_send_json_success($activity);
    }

    /**
     * Verify nonce and admin capability for reporting requests.
     *
     * Unlike the student-facing dashboard which checks is_user_logged_in(),
     * admin reporting requires the manage_options capability to ensure
     * only administrators can access platform-wide data.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!current_user_can('manage_options')) {
            self::send_error('unauthorized', __('You do not have permission to access admin reports.', 'mdcat-platform'), 403);
        }
    }

    /**
     * Send a normalized JSON error response.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status.
     */
    private static function send_error( $code, $message, $status = 400 ) {

        wp_send_json_error(
            [
                'code'    => sanitize_key($code),
                'message' => $message,
            ],
            absint($status)
        );
    }
}
