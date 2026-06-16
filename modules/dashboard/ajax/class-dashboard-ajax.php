<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Dashboard_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated dashboard AJAX actions.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_get_student_dashboard', [__CLASS__, 'get_student_dashboard']);
    }

    /**
     * Return aggregated dashboard data for the current student.
     *
     * This handler is intentionally thin — all business logic lives in
     * Dashboard Service. The handler only verifies security, calls the
     * service, and formats the response.
     */
    public static function get_student_dashboard() {

        self::verify_request();

        $user_id = get_current_user_id();

        $stats = MDCAT_Platform_Dashboard_Service::get_dashboard_stats($user_id);

        if (is_wp_error($stats)) {
            self::send_wp_error($stats);
        }

        $recent_activity = MDCAT_Platform_Dashboard_Service::get_recent_activity($user_id);

        if (is_wp_error($recent_activity)) {
            self::send_wp_error($recent_activity);
        }

        $performance_snapshot = MDCAT_Platform_Dashboard_Service::get_performance_snapshot($user_id);

        if (is_wp_error($performance_snapshot)) {
            self::send_wp_error($performance_snapshot);
        }

        $streak = MDCAT_Platform_Dashboard_Service::get_streak_data($user_id);

        if (is_wp_error($streak)) {
            self::send_wp_error($streak);
        }

        $subject_progress = MDCAT_Platform_Dashboard_Service::get_subject_progress($user_id);

        if (is_wp_error($subject_progress)) {
            self::send_wp_error($subject_progress);
        }

        $chapter_progress = MDCAT_Platform_Dashboard_Service::get_chapter_progress($user_id);

        if (is_wp_error($chapter_progress)) {
            self::send_wp_error($chapter_progress);
        }

        $overall_progress = MDCAT_Platform_Dashboard_Service::get_overall_progress($user_id);

        if (is_wp_error($overall_progress)) {
            self::send_wp_error($overall_progress);
        }

        $continue_learning = MDCAT_Platform_Dashboard_Service::get_continue_learning($user_id);

        if (is_wp_error($continue_learning)) {
            self::send_wp_error($continue_learning);
        }

        $engagement = MDCAT_Platform_Dashboard_Service::get_engagement_data($user_id);

        if (is_wp_error($engagement)) {
            $engagement = ['xp' => [], 'badges' => [], 'achievements' => []];
        }

        /**
         * Fetch chapter-level performance once for the study planner.
         *
         * The dashboard widgets above do not use raw chapter performance
         * directly, but the study planner needs it for priority topic
         * scoring. Fetching it here avoids a duplicate query inside
         * the recommendation service.
         */
        $chapter_performance = MDCAT_Platform_Performance_Analytics::get_chapter_performance($user_id);

        if (is_wp_error($chapter_performance)) {
            $chapter_performance = [];
        }

        /**
         * Build study plan context from already-fetched dashboard data.
         *
         * The recommendation service accepts pre-fetched data via the
         * $context parameter. This eliminates 6 duplicate service calls
         * that would otherwise re-query the same indexed tables.
         *
         * subject_progress and chapter_progress are the raw arrays from
         * Progress_Service, which the delegate methods return unchanged.
         */
        $study_plan_context = [
            'chapter_performance' => $chapter_performance,
            'subject_completion'  => is_wp_error($subject_progress) ? [] : $subject_progress,
            'chapter_completion'  => is_wp_error($chapter_progress) ? [] : $chapter_progress,
            'continue_learning'   => is_wp_error($continue_learning) ? [] : $continue_learning,
            'streak_summary'      => is_wp_error($streak) ? [] : $streak,
        ];

        $study_plan = MDCAT_Platform_Dashboard_Service::get_study_recommendations($user_id, $study_plan_context);

        if (is_wp_error($study_plan)) {
            $study_plan = [];
        }

        $notification_summary = MDCAT_Platform_Dashboard_Service::get_notification_summary($user_id);

        wp_send_json_success(
            [
                'stats'                  => $stats,
                'recent_activity'        => $recent_activity,
                'performance_snapshot'   => $performance_snapshot,
                'streak'                 => $streak,
                'subject_progress'       => $subject_progress,
                'chapter_progress'       => $chapter_progress,
                'overall_progress'       => $overall_progress,
                'continue_learning'      => $continue_learning,
                'engagement'             => $engagement,
                'study_plan'             => $study_plan,
                'notification_summary'   => $notification_summary,
            ]
        );
    }

    /**
     * Verify nonce and authentication for dashboard requests.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to view your dashboard.', 'mdcat-platform'), 401);
        }
    }

    /**
     * Send a normalized WP_Error response.
     *
     * @param WP_Error $error Error object.
     */
    private static function send_wp_error( $error ) {

        self::send_error($error->get_error_code(), $error->get_error_message(), 400);
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
