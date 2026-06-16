<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Achievement_Service {

    /**
     * Get all achievement definitions.
     *
     * Achievements represent major cumulative milestones
     * that span multiple activity types.
     *
     * @return array Achievement definitions keyed by slug.
     */
    public static function get_achievement_definitions() {

        return [

            // XP milestones.
            'xp_100' => [
                'name'        => 'XP Beginner',
                'description' => 'Earn 100 XP',
                'icon'        => '⭐',
                'category'    => 'xp',
            ],
            'xp_500' => [
                'name'        => 'XP Enthusiast',
                'description' => 'Earn 500 XP',
                'icon'        => '🌟',
                'category'    => 'xp',
            ],
            'xp_1000' => [
                'name'        => 'XP Champion',
                'description' => 'Earn 1,000 XP',
                'icon'        => '💫',
                'category'    => 'xp',
            ],
            'xp_5000' => [
                'name'        => 'XP Legend',
                'description' => 'Earn 5,000 XP',
                'icon'        => '🌠',
                'category'    => 'xp',
            ],

            // Activity milestones.
            'attempts_100' => [
                'name'        => 'Century',
                'description' => 'Complete 100 quizzes',
                'icon'        => '💯',
                'category'    => 'activity',
            ],
            'active_days_30' => [
                'name'        => 'Monthly Regular',
                'description' => '30 total active days',
                'icon'        => '📅',
                'category'    => 'activity',
            ],
            'active_days_100' => [
                'name'        => 'Dedicated Learner',
                'description' => '100 total active days',
                'icon'        => '🎓',
                'category'    => 'activity',
            ],

            // Exploration milestones.
            'all_subjects_attempted' => [
                'name'        => 'Subject Explorer',
                'description' => 'Attempt a quiz in every subject',
                'icon'        => '🗺️',
                'category'    => 'exploration',
            ],
        ];
    }

    /**
     * Evaluate all achievement criteria for a user.
     *
     * Called after every quiz completion. For each achievement,
     * checks if the user already has it. If not, evaluates criteria.
     * If met, records the achievement and awards bonus XP.
     *
     * @param int $user_id WordPress user ID.
     * @return array List of newly awarded achievement slugs.
     */
    public static function evaluate_achievements( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return [];
        }

        $definitions   = self::get_achievement_definitions();
        $existing      = self::get_user_achievement_slugs($user_id);
        $newly_awarded = [];

        // Pre-fetch data needed by criteria evaluations.
        $context = self::build_evaluation_context($user_id);

        foreach ($definitions as $slug => $definition) {

            if (in_array($slug, $existing, true)) {
                continue;
            }

            if (self::check_criteria($slug, $context)) {
                self::record_achievement($user_id, $slug);
                MDCAT_Platform_XP_Service::award_reward_xp($user_id, $slug);
                $newly_awarded[] = $slug;
            }
        }

        return $newly_awarded;
    }

    /**
     * Build context data for achievement criteria evaluation.
     *
     * @param int $user_id WordPress user ID.
     * @return array Evaluation context.
     */
    private static function build_evaluation_context( $user_id ) {

        $total_xp          = MDCAT_Platform_XP_Service::get_total_xp($user_id);
        $streak_summary    = MDCAT_Platform_Streak_Service::get_streak_summary($user_id);
        $total_active_days = is_wp_error($streak_summary) ? 0 : absint($streak_summary['total_active_days']);
        $total_attempts    = self::get_total_completed_attempts($user_id);
        $subjects_attempted = self::get_distinct_subjects_attempted($user_id);
        $total_subjects     = self::get_total_subjects();

        return [
            'total_xp'            => $total_xp,
            'total_attempts'      => $total_attempts,
            'total_active_days'   => $total_active_days,
            'subjects_attempted'  => $subjects_attempted,
            'total_subjects'      => $total_subjects,
        ];
    }

    /**
     * Check if a specific achievement's criteria is met.
     *
     * @param string $slug    Achievement slug.
     * @param array  $context Pre-fetched evaluation context.
     * @return bool True if criteria is met.
     */
    private static function check_criteria( $slug, $context ) {

        switch ($slug) {

            // XP milestones.
            case 'xp_100':
                return $context['total_xp'] >= 100;

            case 'xp_500':
                return $context['total_xp'] >= 500;

            case 'xp_1000':
                return $context['total_xp'] >= 1000;

            case 'xp_5000':
                return $context['total_xp'] >= 5000;

            // Activity milestones.
            case 'attempts_100':
                return $context['total_attempts'] >= 100;

            case 'active_days_30':
                return $context['total_active_days'] >= 30;

            case 'active_days_100':
                return $context['total_active_days'] >= 100;

            // Exploration milestones.
            case 'all_subjects_attempted':
                return $context['total_subjects'] > 0
                    && $context['subjects_attempted'] >= $context['total_subjects'];

            default:
                return false;
        }
    }

    /**
     * Record an achievement for a user.
     *
     * Uses INSERT IGNORE to prevent duplicates. The UNIQUE KEY
     * on (user_id, reward_type, reward_slug) makes this idempotent.
     *
     * @param int    $user_id          WordPress user ID.
     * @param string $achievement_slug Achievement slug.
     * @return bool True on success.
     */
    public static function record_achievement( $user_id, $achievement_slug ) {

        global $wpdb;

        $table = self::get_table_name();

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (user_id, reward_type, reward_slug, earned_at)
                VALUES (%d, %s, %s, %s)",
                absint($user_id),
                'achievement',
                sanitize_key($achievement_slug),
                current_time('mysql')
            )
        );

        return true;
    }

    /**
     * Get all achievements a user has earned.
     *
     * Returns achievement data enriched with definition metadata.
     *
     * @param int $user_id WordPress user ID.
     * @return array Earned achievements with metadata.
     */
    public static function get_user_achievements( $user_id ) {

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
                'achievement'
            )
        );

        $definitions  = self::get_achievement_definitions();
        $achievements = [];

        foreach ((array) $rows as $row) {
            $slug = sanitize_key($row->reward_slug);

            if (!isset($definitions[$slug])) {
                continue;
            }

            $achievements[] = array_merge(
                $definitions[$slug],
                [
                    'slug'      => $slug,
                    'earned_at' => $row->earned_at,
                    'earned'    => true,
                ]
            );
        }

        return $achievements;
    }

    /**
     * Get achievement slugs a user has earned.
     *
     * @param int $user_id WordPress user ID.
     * @return array List of earned achievement slugs.
     */
    private static function get_user_achievement_slugs( $user_id ) {

        global $wpdb;

        $table = self::get_table_name();

        $slugs = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT reward_slug
                FROM {$table}
                WHERE user_id = %d
                    AND reward_type = %s",
                absint($user_id),
                'achievement'
            )
        );

        return (array) $slugs;
    }

    /**
     * Count total completed attempts for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return int Total completed attempts.
     */
    private static function get_total_completed_attempts( $user_id ) {

        global $wpdb;

        $table = MDCAT_Platform_Attempts_Handler::get_attempts_table_name();

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(id)
                    FROM {$table}
                    WHERE user_id = %d
                        AND status = %s",
                    absint($user_id),
                    'completed'
                )
            )
        );
    }

    /**
     * Count distinct subjects a user has attempted quizzes in.
     *
     * @param int $user_id WordPress user ID.
     * @return int Number of distinct subjects attempted.
     */
    private static function get_distinct_subjects_attempted( $user_id ) {

        global $wpdb;

        $attempts_table    = MDCAT_Platform_Attempts_Handler::get_attempts_table_name();
        $collections_table = $wpdb->prefix . 'mdcat_collections';
        $chapters_table    = $wpdb->prefix . 'mdcat_chapters';

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT chapters.subject_id)
                    FROM {$attempts_table} AS attempts
                    INNER JOIN {$collections_table} AS collections
                        ON attempts.collection_id = collections.id
                    INNER JOIN {$chapters_table} AS chapters
                        ON collections.chapter_id = chapters.id
                    WHERE attempts.user_id = %d
                        AND attempts.status = %s",
                    absint($user_id),
                    'completed'
                )
            )
        );
    }

    /**
     * Count total active subjects in the curriculum.
     *
     * @return int Total subject count.
     */
    private static function get_total_subjects() {

        global $wpdb;

        $subjects_table = $wpdb->prefix . 'mdcat_subjects';

        return absint(
            $wpdb->get_var("SELECT COUNT(id) FROM {$subjects_table}")
        );
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
