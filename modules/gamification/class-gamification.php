<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/gamification/services/class-streak-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/gamification/services/class-xp-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/gamification/services/class-badge-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/gamification/services/class-achievement-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/gamification/services/class-leaderboard-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/gamification/ajax/class-gamification-ajax.php';

class MDCAT_Platform_Gamification {

    /**
     * Stores gamification results from the most recent quiz completion.
     *
     * Populated by on_quiz_completed(), consumed by
     * append_gamification_to_response(). Cleared after each
     * response to prevent stale data leaking across requests.
     *
     * @var array|null
     */
    private static $last_results = null;

    /**
     * Bootstrap the gamification behavioral layer.
     *
     * Gamification is its own isolated module — it listens to events from
     * other systems (via WordPress action hooks) but never reaches into
     * their internals. Other modules never call gamification directly.
     *
     * Systems: streak tracking, XP, badges, achievements, leaderboards.
     */
    public static function init() {

        MDCAT_Platform_Gamification_Ajax::init();

        /**
         * Listen for completed quiz attempts to trigger all
         * gamification systems: streak, XP, badges, achievements.
         *
         * The quiz engine fires 'mdcat_quiz_completed' after a successful
         * attempt completion. This listener is the ONLY coupling point
         * between the quiz system and gamification.
         */
        add_action('mdcat_quiz_completed', [__CLASS__, 'on_quiz_completed'], 10, 2);

        /**
         * Filter the quiz completion AJAX response to append
         * gamification feedback (XP earned, new badges, etc).
         *
         * The quiz module applies this filter before sending the
         * response. Gamification data is available because the
         * do_action hook fires before the filter is applied.
         */
        add_filter('mdcat_quiz_completion_response', [__CLASS__, 'append_gamification_to_response'], 10, 2);
    }

    /**
     * Handle quiz completion event for all gamification systems.
     *
     * Order matters:
     * 1. Record daily activity (streak) — must happen first because
     *    badge and achievement criteria depend on updated streak data.
     * 2. Award XP — must happen before achievements because some
     *    achievement criteria depend on total XP.
     * 3. Evaluate badges — depends on streak + progress + analytics.
     * 4. Evaluate achievements — depends on XP + streak + attempts.
     *
     * Captures a snapshot of XP before and after all gamification
     * processing to calculate the exact XP earned from this quiz.
     *
     * @param int $user_id    WordPress user ID who completed the quiz.
     * @param int $attempt_id The completed attempt ID.
     */
    public static function on_quiz_completed( $user_id, $attempt_id ) {

        $user_id    = absint($user_id);
        $attempt_id = absint($attempt_id);

        if (!$user_id) {
            return;
        }

        // Snapshot XP before any gamification processing.
        $xp_before = MDCAT_Platform_XP_Service::get_total_xp($user_id);

        // 1. Streak tracking (existing).
        MDCAT_Platform_Streak_Service::record_daily_activity($user_id);

        // 2. XP awards (quiz completion + streak milestones + progress milestones).
        MDCAT_Platform_XP_Service::award_quiz_completion_xp($user_id, $attempt_id);

        // 3. Badge evaluation (quiz count, streak, accuracy, completion).
        $new_badges = MDCAT_Platform_Badge_Service::evaluate_badges($user_id);

        // 4. Achievement evaluation (XP milestones, activity milestones, exploration).
        $new_achievements = MDCAT_Platform_Achievement_Service::evaluate_achievements($user_id);

        // Snapshot XP after all processing (includes reward XP from badges/achievements).
        $xp_after = MDCAT_Platform_XP_Service::get_total_xp($user_id);

        // Store results for the response filter.
        self::$last_results = [
            'user_id'          => $user_id,
            'xp_earned'        => $xp_after - $xp_before,
            'new_badges'       => (array) $new_badges,
            'new_achievements' => (array) $new_achievements,
        ];
    }

    /**
     * Append gamification feedback to the quiz completion response.
     *
     * Called by the mdcat_quiz_completion_response filter. Enriches
     * the quiz result payload with XP earned, newly unlocked badges
     * and achievements, and current level progress.
     *
     * If no gamification data is available (e.g., on a get_result call
     * that does not fire the completion hook), returns the response
     * unchanged.
     *
     * @param array $response   The formatted quiz result data.
     * @param int   $attempt_id The completed attempt ID.
     * @return array Response with gamification data appended.
     */
    public static function append_gamification_to_response( $response, $attempt_id ) {

        if (self::$last_results === null) {
            return $response;
        }

        $data = self::$last_results;
        self::$last_results = null;

        // Get current level progress (reuses existing service method).
        $level_progress = MDCAT_Platform_XP_Service::get_level_progress($data['user_id']);

        // Resolve badge slugs to display data.
        $badge_definitions = MDCAT_Platform_Badge_Service::get_badge_definitions();
        $badges_detail     = [];

        foreach ($data['new_badges'] as $slug) {
            if (isset($badge_definitions[$slug])) {
                $badges_detail[] = [
                    'slug'        => $slug,
                    'name'        => $badge_definitions[$slug]['name'],
                    'icon'        => $badge_definitions[$slug]['icon'],
                    'description' => $badge_definitions[$slug]['description'],
                ];
            }
        }

        // Resolve achievement slugs to display data.
        $achievement_definitions = MDCAT_Platform_Achievement_Service::get_achievement_definitions();
        $achievements_detail     = [];

        foreach ($data['new_achievements'] as $slug) {
            if (isset($achievement_definitions[$slug])) {
                $achievements_detail[] = [
                    'slug'        => $slug,
                    'name'        => $achievement_definitions[$slug]['name'],
                    'icon'        => $achievement_definitions[$slug]['icon'],
                    'description' => $achievement_definitions[$slug]['description'],
                ];
            }
        }

        $response['gamification'] = [
            'xp_earned'           => absint($data['xp_earned']),
            'new_badges'          => $badges_detail,
            'new_achievements'    => $achievements_detail,
            'current_level'       => $level_progress['current_level'],
            'total_xp'            => $level_progress['total_xp'],
            'progress_percentage' => $level_progress['progress_percentage'],
            'is_max_level'        => $level_progress['is_max_level'],
        ];

        return $response;
    }
}
