<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller for student dashboard endpoints.
 *
 * Exposes granular dashboard data through 4 focused endpoints
 * instead of a single monolithic response. The frontend composes
 * the full dashboard by calling these endpoints in parallel.
 *
 * Endpoints:
 *
 *   GET /dashboard/stats             — Aggregate quiz statistics.
 *   GET /dashboard/progress          — Subject + chapter completion.
 *   GET /dashboard/continue-learning — Next recommended collection.
 *   GET /dashboard/study-plan        — AI-generated study recommendations.
 *
 * All endpoints delegate to existing services:
 *
 *   - Dashboard_Service   → stats, aggregates
 *   - Progress_Service    → completion tracking
 *   - Recommendation_Service → study plan generation
 *
 * This controller contains NO business logic, NO SQL, and NO
 * direct database access. It is a thin translation layer between
 * HTTP requests and service method calls.
 */
class MDCAT_Platform_REST_Dashboard_Controller
    extends MDCAT_Platform_REST_Base_Controller {

    /**
     * Register all dashboard routes.
     */
    public static function register_routes() {

        register_rest_route(self::$namespace, '/dashboard/stats', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_stats'],
            'permission_callback' => [__CLASS__, 'check_dashboard_access'],
        ]);

        register_rest_route(self::$namespace, '/dashboard/progress', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_progress'],
            'permission_callback' => [__CLASS__, 'check_dashboard_access'],
        ]);

        register_rest_route(self::$namespace, '/dashboard/continue-learning', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_continue_learning'],
            'permission_callback' => [__CLASS__, 'check_dashboard_access'],
        ]);

        register_rest_route(self::$namespace, '/dashboard/study-plan', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_study_plan'],
            'permission_callback' => [__CLASS__, 'check_dashboard_access'],
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /dashboard/stats
    // ------------------------------------------------------------------

    /**
     * Return aggregate quiz statistics for the authenticated student.
     *
     * Delegates to Dashboard_Service::get_dashboard_stats() which runs
     * a single optimized query for attempt aggregates and counts
     * bookmarks and weak topics via existing service calls.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_stats( $request ) {

        $user_id = self::get_current_user_id($request);

        $stats = MDCAT_Platform_Dashboard_Service::get_dashboard_stats($user_id);

        if (is_wp_error($stats)) {
            return self::wp_error($stats);
        }

        return self::success($stats, 'Dashboard stats loaded.');
    }

    // ------------------------------------------------------------------
    //  GET /dashboard/progress
    // ------------------------------------------------------------------

    /**
     * Return subject, chapter, and overall completion progress.
     *
     * Combines three Progress_Service calls into a single structured
     * response. Each call runs a single JOIN query — no N+1 problems.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_progress( $request ) {

        $user_id = self::get_current_user_id($request);

        $overall = MDCAT_Platform_Progress_Service::get_overall_completion($user_id);

        if (is_wp_error($overall)) {
            return self::wp_error($overall);
        }

        $subjects = MDCAT_Platform_Progress_Service::get_subject_completion($user_id);

        if (is_wp_error($subjects)) {
            return self::wp_error($subjects);
        }

        $chapters = MDCAT_Platform_Progress_Service::get_chapter_completion($user_id);

        if (is_wp_error($chapters)) {
            return self::wp_error($chapters);
        }

        return self::success(
            [
                'overall'  => $overall,
                'subjects' => $subjects,
                'chapters' => $chapters,
            ],
            'Progress loaded.'
        );
    }

    // ------------------------------------------------------------------
    //  GET /dashboard/continue-learning
    // ------------------------------------------------------------------

    /**
     * Return the next recommended collection for the student.
     *
     * Delegates to Progress_Service::get_continue_learning() which
     * finds the first uncompleted collection in curriculum order.
     *
     * Returns a curriculum_completed flag when all collections are done,
     * with nullable collection/chapter/subject fields set to null.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_continue_learning( $request ) {

        $user_id = self::get_current_user_id($request);

        $recommendation = MDCAT_Platform_Progress_Service::get_continue_learning($user_id);

        if (is_wp_error($recommendation)) {
            return self::wp_error($recommendation);
        }

        return self::success($recommendation, 'Continue learning loaded.');
    }

    // ------------------------------------------------------------------
    //  GET /dashboard/study-plan
    // ------------------------------------------------------------------

    /**
     * Return the complete study plan for the student.
     *
     * Delegates to Recommendation_Service::get_study_plan() in
     * standalone mode (no pre-fetched context). The service internally
     * fetches chapter performance, subject performance, completion data,
     * continue learning, streak, and wrong questions.
     *
     * This results in ~6 internal service calls. Acceptable trade-off
     * for a standalone endpoint that is called infrequently.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_study_plan( $request ) {

        $user_id = self::get_current_user_id($request);

        $study_plan = MDCAT_Platform_Recommendation_Service::get_study_plan($user_id);

        if (is_wp_error($study_plan)) {
            return self::wp_error($study_plan);
        }

        return self::success($study_plan, 'Study plan loaded.');
    }
}
