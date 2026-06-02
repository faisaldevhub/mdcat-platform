<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once MDCAT_PLATFORM_PATH . 'modules/gamification/services/class-streak-service.php';
require_once MDCAT_PLATFORM_PATH . 'modules/gamification/ajax/class-gamification-ajax.php';

class MDCAT_Platform_Gamification {

    /**
     * Bootstrap the gamification behavioral layer.
     *
     * Gamification is its own isolated module — it listens to events from
     * other systems (via WordPress action hooks) but never reaches into
     * their internals. Other modules never call gamification directly.
     *
     * Current MVP: streak tracking triggered by quiz completion.
     * Future: XP, badges, achievements, leaderboards, challenges.
     */
    public static function init() {

        MDCAT_Platform_Gamification_Ajax::init();

        /**
         * Listen for completed quiz attempts to record daily activity.
         *
         * The quiz engine fires 'mdcat_quiz_completed' after a successful
         * attempt completion. This listener is the ONLY coupling point
         * between the quiz system and gamification.
         */
        add_action('mdcat_quiz_completed', [__CLASS__, 'on_quiz_completed'], 10, 2);
    }

    /**
     * Handle quiz completion event for daily activity tracking.
     *
     * @param int $user_id    WordPress user ID who completed the quiz.
     * @param int $attempt_id The completed attempt ID.
     */
    public static function on_quiz_completed( $user_id, $attempt_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return;
        }

        MDCAT_Platform_Streak_Service::record_daily_activity($user_id);
    }
}
