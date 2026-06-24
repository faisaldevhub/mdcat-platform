<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller for student performance analytics.
 *
 * Exposes subject-level and chapter-level accuracy data through
 * a single endpoint that mirrors the existing AJAX analytics handler.
 *
 * Endpoint:
 *
 *   GET /analytics/performance — Subject + chapter accuracy breakdown.
 *
 * Delegates to:
 *
 *   - Performance_Analytics → subject and chapter aggregates
 *
 * This controller contains NO business logic, NO SQL, and NO
 * direct database access.
 */
class MDCAT_Platform_REST_Analytics_Controller
    extends MDCAT_Platform_REST_Base_Controller {

    /**
     * Register all analytics routes.
     */
    public static function register_routes() {

        register_rest_route(self::$namespace, '/analytics/performance', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_performance'],
            'permission_callback' => [__CLASS__, 'check_analytics_access'],
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /analytics/performance
    // ------------------------------------------------------------------

    /**
     * Return subject and chapter performance analytics.
     *
     * Two service calls:
     * 1. get_subject_performance() — accuracy by subject, ordered best-first.
     * 2. get_chapter_performance() — accuracy by chapter, ordered weakest-first.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_performance( $request ) {

        $user_id = self::get_current_user_id($request);

        $subject_performance = MDCAT_Platform_Performance_Analytics::get_subject_performance($user_id);

        if (is_wp_error($subject_performance)) {
            return self::wp_error($subject_performance);
        }

        $chapter_performance = MDCAT_Platform_Performance_Analytics::get_chapter_performance($user_id);

        if (is_wp_error($chapter_performance)) {
            return self::wp_error($chapter_performance);
        }

        return self::success(
            [
                'subject_performance' => $subject_performance,
                'chapter_performance' => $chapter_performance,
            ],
            'Performance analytics loaded.'
        );
    }
}
