<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Badge_Service {

    /**
     * Get all badge definitions.
     *
     * Badges are code-based — stored as a PHP array, not in the
     * database. This keeps them version-controlled and avoids
     * admin complexity.
     *
     * @return array Badge definitions keyed by slug.
     */
    public static function get_badge_definitions() {

        return [

            // Quiz badges.
            'first_quiz' => [
                'name'        => 'First Steps',
                'description' => 'Complete your first quiz',
                'icon'        => '🎯',
                'category'    => 'quiz',
            ],
            'ten_quizzes' => [
                'name'        => 'Quiz Warrior',
                'description' => 'Complete 10 quizzes',
                'icon'        => '⚔️',
                'category'    => 'quiz',
            ],
            'fifty_quizzes' => [
                'name'        => 'Quiz Master',
                'description' => 'Complete 50 quizzes',
                'icon'        => '👑',
                'category'    => 'quiz',
            ],

            // Streak badges.
            'streak_3' => [
                'name'        => 'Getting Started',
                'description' => 'Reach a 3-day streak',
                'icon'        => '🔥',
                'category'    => 'streak',
            ],
            'streak_7' => [
                'name'        => 'Committed Learner',
                'description' => 'Reach a 7-day streak',
                'icon'        => '💪',
                'category'    => 'streak',
            ],
            'streak_14' => [
                'name'        => 'Consistency King',
                'description' => 'Reach a 14-day streak',
                'icon'        => '🏅',
                'category'    => 'streak',
            ],
            'streak_30' => [
                'name'        => 'Unstoppable',
                'description' => 'Reach a 30-day streak',
                'icon'        => '🚀',
                'category'    => 'streak',
            ],

            // Accuracy badges.
            'accuracy_80' => [
                'name'        => 'Sharp Shooter',
                'description' => 'Achieve 80%+ overall accuracy',
                'icon'        => '🎯',
                'category'    => 'accuracy',
            ],
            'accuracy_90' => [
                'name'        => 'Precision Master',
                'description' => 'Achieve 90%+ overall accuracy',
                'icon'        => '💎',
                'category'    => 'accuracy',
            ],

            // Completion badges.
            'completion_25' => [
                'name'        => 'Quarter Way',
                'description' => 'Complete 25% of the curriculum',
                'icon'        => '📘',
                'category'    => 'completion',
            ],
            'completion_50' => [
                'name'        => 'Halfway Hero',
                'description' => 'Complete 50% of the curriculum',
                'icon'        => '📗',
                'category'    => 'completion',
            ],
            'completion_100' => [
                'name'        => 'Curriculum Champion',
                'description' => 'Complete 100% of the curriculum',
                'icon'        => '🏆',
                'category'    => 'completion',
            ],
        ];
    }

    /**
     * Evaluate all badge criteria for a user and award unlocked ones.
     *
     * Called after every quiz completion. For each badge definition,
     * checks if the user already has it. If not, evaluates the criteria.
     * If met, awards the badge and bonus XP.
     *
     * @param int $user_id WordPress user ID.
     * @return array List of newly awarded badge slugs.
     */
    public static function evaluate_badges( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return [];
        }

        $definitions    = self::get_badge_definitions();
        $existing       = self::get_user_badge_slugs($user_id);
        $newly_awarded  = [];

        // Pre-fetch data needed by multiple criteria evaluations.
        $context = self::build_evaluation_context($user_id);

        foreach ($definitions as $slug => $definition) {

            if (in_array($slug, $existing, true)) {
                continue;
            }

            if (self::check_criteria($slug, $context)) {
                self::award_badge($user_id, $slug);
                MDCAT_Platform_XP_Service::award_reward_xp($user_id, $slug);
                $newly_awarded[] = $slug;
            }
        }

        return $newly_awarded;
    }

    /**
     * Build context data for badge criteria evaluation.
     *
     * Pre-fetches all data needed by badge criteria in a single pass
     * so individual criteria checks do not make redundant queries.
     *
     * @param int $user_id WordPress user ID.
     * @return array Evaluation context.
     */
    private static function build_evaluation_context( $user_id ) {

        $stats    = MDCAT_Platform_Dashboard_Service::get_dashboard_stats($user_id);
        $overall  = MDCAT_Platform_Progress_Service::get_overall_completion($user_id);
        $streak   = MDCAT_Platform_Streak_Service::get_current_streak($user_id);

        return [
            'total_attempts'        => is_wp_error($stats) ? 0 : absint($stats['total_attempts']),
            'overall_accuracy'      => is_wp_error($stats) ? 0 : (float) $stats['overall_accuracy'],
            'completion_percentage' => is_wp_error($overall) ? 0 : (float) $overall['completion_percentage'],
            'current_streak'        => absint($streak),
        ];
    }

    /**
     * Check if a specific badge's criteria is met.
     *
     * @param string $slug    Badge slug.
     * @param array  $context Pre-fetched evaluation context.
     * @return bool True if criteria is met.
     */
    private static function check_criteria( $slug, $context ) {

        switch ($slug) {

            // Quiz count badges.
            case 'first_quiz':
                return $context['total_attempts'] >= 1;

            case 'ten_quizzes':
                return $context['total_attempts'] >= 10;

            case 'fifty_quizzes':
                return $context['total_attempts'] >= 50;

            // Streak badges.
            case 'streak_3':
                return $context['current_streak'] >= 3;

            case 'streak_7':
                return $context['current_streak'] >= 7;

            case 'streak_14':
                return $context['current_streak'] >= 14;

            case 'streak_30':
                return $context['current_streak'] >= 30;

            // Accuracy badges.
            case 'accuracy_80':
                return $context['total_attempts'] >= 5 && $context['overall_accuracy'] >= 80;

            case 'accuracy_90':
                return $context['total_attempts'] >= 10 && $context['overall_accuracy'] >= 90;

            // Completion badges.
            case 'completion_25':
                return $context['completion_percentage'] >= 25;

            case 'completion_50':
                return $context['completion_percentage'] >= 50;

            case 'completion_100':
                return $context['completion_percentage'] >= 100;

            default:
                return false;
        }
    }

    /**
     * Award a badge to a user.
     *
     * Uses INSERT IGNORE to prevent duplicate awards. The UNIQUE KEY
     * on (user_id, reward_type, reward_slug) makes this idempotent.
     *
     * @param int    $user_id    WordPress user ID.
     * @param string $badge_slug Badge slug to award.
     * @return bool True on success.
     */
    public static function award_badge( $user_id, $badge_slug ) {

        global $wpdb;

        $table = self::get_table_name();

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (user_id, reward_type, reward_slug, earned_at)
                VALUES (%d, %s, %s, %s)",
                absint($user_id),
                'badge',
                sanitize_key($badge_slug),
                current_time('mysql')
            )
        );

        return true;
    }

    /**
     * Get all badges a user has earned.
     *
     * Returns badge data enriched with definition metadata.
     *
     * @param int $user_id WordPress user ID.
     * @return array Earned badges with metadata.
     */
    public static function get_user_badges( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return [];
        }

        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT reward_slug, earned_at
                FROM {$table}
                WHERE user_id = %d
                    AND reward_type = %s
                ORDER BY earned_at DESC",
                $user_id,
                'badge'
            )
        );

        $definitions = self::get_badge_definitions();
        $badges      = [];

        foreach ((array) $rows as $row) {
            $slug = sanitize_key($row->reward_slug);

            if (!isset($definitions[$slug])) {
                continue;
            }

            $badges[] = array_merge(
                $definitions[$slug],
                [
                    'slug'      => $slug,
                    'earned_at' => $row->earned_at,
                    'earned'    => true,
                ]
            );
        }

        return $badges;
    }

    /**
     * Get all badge definitions with earned/locked status for a user.
     *
     * Used by the badge showcase to display both earned and locked badges.
     *
     * @param int $user_id WordPress user ID.
     * @return array All badges with status.
     */
    public static function get_badges_with_status( $user_id ) {

        $definitions = self::get_badge_definitions();
        $earned_map  = self::get_user_badge_map($user_id);
        $badges      = [];

        foreach ($definitions as $slug => $definition) {

            $is_earned = isset($earned_map[$slug]);

            $badges[] = array_merge(
                $definition,
                [
                    'slug'      => $slug,
                    'earned'    => $is_earned,
                    'earned_at' => $is_earned ? $earned_map[$slug] : null,
                ]
            );
        }

        return $badges;
    }

    /**
     * Check if a user has a specific badge.
     *
     * @param int    $user_id    WordPress user ID.
     * @param string $badge_slug Badge slug.
     * @return bool True if the user has the badge.
     */
    public static function has_badge( $user_id, $badge_slug ) {

        global $wpdb;

        $table = self::get_table_name();

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id)
                FROM {$table}
                WHERE user_id = %d
                    AND reward_type = %s
                    AND reward_slug = %s",
                absint($user_id),
                'badge',
                sanitize_key($badge_slug)
            )
        );

        return absint($exists) > 0;
    }

    /**
     * Get just the badge slugs a user has earned.
     *
     * Used internally for fast duplicate checking during evaluation.
     *
     * @param int $user_id WordPress user ID.
     * @return array List of earned badge slugs.
     */
    private static function get_user_badge_slugs( $user_id ) {

        global $wpdb;

        $table = self::get_table_name();

        $slugs = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT reward_slug
                FROM {$table}
                WHERE user_id = %d
                    AND reward_type = %s",
                absint($user_id),
                'badge'
            )
        );

        return (array) $slugs;
    }

    /**
     * Get a user's earned badges as a slug => earned_at map.
     *
     * @param int $user_id WordPress user ID.
     * @return array Slug => earned_at map.
     */
    private static function get_user_badge_map( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return [];
        }

        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT reward_slug, earned_at
                FROM {$table}
                WHERE user_id = %d
                    AND reward_type = %s",
                $user_id,
                'badge'
            )
        );

        $map = [];

        foreach ((array) $rows as $row) {
            $map[sanitize_key($row->reward_slug)] = $row->earned_at;
        }

        return $map;
    }

    /**
     * Get the user rewards table name.
     *
     * @return string Table name with WordPress prefix.
     */
    public static function get_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_user_rewards';
    }
}
