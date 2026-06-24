<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller for gamification features.
 *
 * Exposes streak, XP, badges, achievements, and leaderboard data
 * through 5 read-only endpoints.
 *
 * Endpoints:
 *
 *   GET /gamification/streak       — Streak summary.
 *   GET /gamification/xp           — XP summary with level progress.
 *   GET /gamification/badges       — All badges with earned/locked status.
 *   GET /gamification/achievements — Earned achievements.
 *   GET /gamification/leaderboard  — Ranked students by XP.
 *
 * Delegates to:
 *
 *   - Streak_Service      → streak summary
 *   - XP_Service          → XP summary with recent transactions
 *   - Badge_Service       → all badge definitions with earned status
 *   - Achievement_Service → earned achievements
 *   - Leaderboard_Service → ranked student list with current user rank
 *
 * This controller contains NO business logic, NO SQL, and NO
 * direct database access.
 */
class MDCAT_Platform_REST_Gamification_Controller
    extends MDCAT_Platform_REST_Base_Controller {

    /**
     * Maximum leaderboard limit allowed via REST.
     *
     * The service does not cap the limit parameter. This controller
     * enforces a ceiling to prevent unbounded queries via the API.
     */
    const MAX_LEADERBOARD_LIMIT = 100;

    /**
     * Register all gamification routes.
     */
    public static function register_routes() {

        register_rest_route(self::$namespace, '/gamification/streak', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_streak'],
            'permission_callback' => [__CLASS__, 'check_gamification_access'],
        ]);

        register_rest_route(self::$namespace, '/gamification/xp', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_xp'],
            'permission_callback' => [__CLASS__, 'check_gamification_access'],
        ]);

        register_rest_route(self::$namespace, '/gamification/badges', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_badges'],
            'permission_callback' => [__CLASS__, 'check_gamification_access'],
        ]);

        register_rest_route(self::$namespace, '/gamification/achievements', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_achievements'],
            'permission_callback' => [__CLASS__, 'check_gamification_access'],
        ]);

        register_rest_route(self::$namespace, '/gamification/leaderboard', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_leaderboard'],
            'permission_callback' => [__CLASS__, 'check_gamification_access'],
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /gamification/streak
    // ------------------------------------------------------------------

    /**
     * Return streak summary for the authenticated student.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_streak( $request ) {

        $user_id = self::get_current_user_id($request);

        $summary = MDCAT_Platform_Streak_Service::get_streak_summary($user_id);

        if (is_wp_error($summary)) {
            return self::wp_error($summary);
        }

        return self::success($summary, 'Streak summary loaded.');
    }

    // ------------------------------------------------------------------
    //  GET /gamification/xp
    // ------------------------------------------------------------------

    /**
     * Return XP summary with level progress and recent transactions.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_xp( $request ) {

        $user_id = self::get_current_user_id($request);

        $summary = MDCAT_Platform_XP_Service::get_xp_summary($user_id);

        if (is_wp_error($summary)) {
            return self::wp_error($summary);
        }

        return self::success($summary, 'XP summary loaded.');
    }

    // ------------------------------------------------------------------
    //  GET /gamification/badges
    // ------------------------------------------------------------------

    /**
     * Return all badge definitions with earned/locked status.
     *
     * Unlike achievements, this returns ALL badges (earned and locked)
     * so the frontend can render a complete badge showcase.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_badges( $request ) {

        $user_id = self::get_current_user_id($request);

        $badges = MDCAT_Platform_Badge_Service::get_badges_with_status($user_id);

        return self::success(['badges' => $badges], 'Badges loaded.');
    }

    // ------------------------------------------------------------------
    //  GET /gamification/achievements
    // ------------------------------------------------------------------

    /**
     * Return earned achievements for the authenticated student.
     *
     * Unlike badges, this returns ONLY earned achievements.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_achievements( $request ) {

        $user_id = self::get_current_user_id($request);

        $achievements = MDCAT_Platform_Achievement_Service::get_user_achievements($user_id);

        return self::success(['achievements' => $achievements], 'Achievements loaded.');
    }

    // ------------------------------------------------------------------
    //  GET /gamification/leaderboard
    // ------------------------------------------------------------------

    /**
     * Return leaderboard data for the requested period.
     *
     * Accepts optional query parameters:
     *   - type:  'all_time', 'weekly', 'monthly' (default: 'weekly')
     *   - limit: max students to return (default: 20, max: 100)
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_leaderboard( $request ) {

        $user_id = self::get_current_user_id($request);
        $type    = sanitize_key($request->get_param('type'));
        $limit   = absint($request->get_param('limit'));

        // Default to 'weekly' if empty or missing.
        if (!$type) {
            $type = 'weekly';
        }

        // Cap the limit to prevent unbounded queries.
        if ($limit > self::MAX_LEADERBOARD_LIMIT) {
            $limit = self::MAX_LEADERBOARD_LIMIT;
        }

        $leaderboard = MDCAT_Platform_Leaderboard_Service::get_leaderboard_data($user_id, $type, $limit);

        if (is_wp_error($leaderboard)) {
            return self::wp_error($leaderboard);
        }

        return self::success($leaderboard, 'Leaderboard loaded.');
    }
}
