<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_XP_Service {

    /**
     * Level thresholds — cumulative XP required for each level.
     *
     * Level is always calculated from total XP using this table.
     * It is never stored as a separate field to prevent drift.
     */
    const LEVEL_THRESHOLDS = [
        1  => 0,
        2  => 100,
        3  => 350,
        4  => 850,
        5  => 1600,
        6  => 2600,
        7  => 4100,
        8  => 6100,
        9  => 9100,
        10 => 14100,
    ];

    /**
     * Award XP for a completed quiz attempt.
     *
     * Applies the following rules:
     * - 10 XP base for every completed quiz
     * - +20 XP if this is the student's first ever completed quiz
     * - +5 XP if accuracy >= 80%
     * - +10 XP if accuracy = 100% (perfect score)
     *
     * Also evaluates streak milestones for streak-based XP.
     *
     * Idempotent: if this attempt already has a quiz_completion
     * transaction, the entire method is skipped. This prevents
     * duplicate XP if the hook fires more than once for the
     * same attempt (e.g., retry, double-click, re-complete).
     *
     * @param int $user_id    WordPress user ID.
     * @param int $attempt_id The completed attempt ID.
     */
    public static function award_quiz_completion_xp( $user_id, $attempt_id ) {

        $user_id    = absint($user_id);
        $attempt_id = absint($attempt_id);

        if (!$user_id || !$attempt_id) {
            return;
        }

        // Idempotency guard: skip if this attempt already earned quiz XP.
        if (self::has_milestone($user_id, 'quiz_completion', $attempt_id)) {
            return;
        }

        $attempt = self::get_attempt_data($attempt_id);

        if (!$attempt) {
            return;
        }

        // Base quiz completion XP.
        self::record_transaction($user_id, 10, 'quiz_completion', $attempt_id, 'Quiz completed');

        // First quiz bonus.
        $total_completed = self::get_completed_attempt_count($user_id);

        if ($total_completed === 1) {
            self::record_transaction($user_id, 20, 'first_quiz', $attempt_id, 'First quiz completed');
        }

        // Accuracy bonus.
        $total_questions = absint($attempt->total_questions);
        $correct_answers = absint($attempt->correct_answers);

        if ($total_questions > 0) {
            $accuracy = ($correct_answers / $total_questions) * 100;

            if ($accuracy >= 100) {
                self::record_transaction($user_id, 10, 'perfect_score', $attempt_id, 'Perfect score bonus');
                self::record_transaction($user_id, 5, 'accuracy_bonus', $attempt_id, 'Accuracy bonus (80%+)');
            } elseif ($accuracy >= 80) {
                self::record_transaction($user_id, 5, 'accuracy_bonus', $attempt_id, 'Accuracy bonus (80%+)');
            }
        }

        // Streak milestone XP.
        self::evaluate_streak_xp($user_id);

        // Progress milestone XP.
        self::evaluate_progress_xp($user_id);
    }

    /**
     * Evaluate and award streak milestone XP.
     *
     * Each milestone (3, 7, 14, 30 days) is awarded once only.
     * Duplicate prevention uses the source + description combination.
     *
     * @param int $user_id WordPress user ID.
     */
    private static function evaluate_streak_xp( $user_id ) {

        $current_streak = MDCAT_Platform_Streak_Service::get_current_streak($user_id);

        $milestones = [
            3  => 15,
            7  => 30,
            14 => 50,
            30 => 100,
        ];

        foreach ($milestones as $days => $xp) {
            if ($current_streak >= $days && !self::has_milestone($user_id, 'streak_milestone', $days)) {
                self::record_transaction($user_id, $xp, 'streak_milestone', $days, $days . '-day streak milestone');
            }
        }
    }

    /**
     * Evaluate and award progress milestone XP.
     *
     * Milestones at 25%, 50%, 75%, and 100% curriculum completion.
     * Each milestone is awarded once only.
     *
     * @param int $user_id WordPress user ID.
     */
    private static function evaluate_progress_xp( $user_id ) {

        $overall = MDCAT_Platform_Progress_Service::get_overall_completion($user_id);

        if (is_wp_error($overall)) {
            return;
        }

        $percentage = isset($overall['completion_percentage']) ? (float) $overall['completion_percentage'] : 0;

        $milestones = [
            25  => 50,
            50  => 100,
            75  => 150,
            100 => 300,
        ];

        foreach ($milestones as $threshold => $xp) {
            if ($percentage >= $threshold && !self::has_milestone($user_id, 'progress_milestone', $threshold)) {
                self::record_transaction($user_id, $xp, 'progress_milestone', $threshold, $threshold . '% curriculum completed');
            }
        }
    }

    /**
     * Award XP for earning a badge or achievement.
     *
     * Uses a deterministic hash of the reward slug as source_id
     * so each reward maps to a unique, traceable identifier.
     * This enables has_milestone() to prevent duplicate award XP
     * if the evaluation runs more than once.
     *
     * @param int    $user_id     WordPress user ID.
     * @param string $reward_slug The reward slug that was unlocked.
     */
    public static function award_reward_xp( $user_id, $reward_slug ) {

        $source_id = abs(crc32(sanitize_key($reward_slug)));

        // Idempotency guard: skip if this reward already earned XP.
        if (self::has_milestone(absint($user_id), 'reward_unlock', $source_id)) {
            return;
        }

        self::record_transaction($user_id, 25, 'reward_unlock', $source_id, 'Reward unlocked: ' . $reward_slug);
    }

    /**
     * Get total lifetime XP for a user.
     *
     * Always derived from SUM(amount) — never cached.
     *
     * @param int $user_id WordPress user ID.
     * @return int Total XP.
     */
    public static function get_total_xp( $user_id ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return 0;
        }

        $table = self::get_table_name();

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                FROM {$table}
                WHERE user_id = %d",
                $user_id
            )
        );

        return absint($total);
    }

    /**
     * Get XP earned within a date range.
     *
     * Used by leaderboard service for weekly/monthly rankings.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $since   MySQL datetime string for the start of the period.
     * @return int XP earned since the given date.
     */
    public static function get_xp_since( $user_id, $since ) {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return 0;
        }

        $table = self::get_table_name();

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                FROM {$table}
                WHERE user_id = %d
                    AND created_at >= %s",
                $user_id,
                $since
            )
        );

        return absint($total);
    }

    /**
     * Calculate the user's current level from total XP.
     *
     * Iterates through level thresholds in reverse to find the
     * highest level the user qualifies for.
     *
     * @param int $user_id WordPress user ID.
     * @return int Current level (1–10).
     */
    public static function get_level( $user_id ) {

        $total_xp = self::get_total_xp($user_id);

        return self::calculate_level($total_xp);
    }

    /**
     * Calculate level from a given XP total.
     *
     * @param int $total_xp Total XP amount.
     * @return int Level number.
     */
    public static function calculate_level( $total_xp ) {

        $level = 1;

        foreach (self::LEVEL_THRESHOLDS as $lv => $threshold) {
            if ($total_xp >= $threshold) {
                $level = $lv;
            }
        }

        return $level;
    }

    /**
     * Get level progress details.
     *
     * Returns XP within the current level, XP needed for the next
     * level, and a percentage value for progress bar rendering.
     *
     * @param int $user_id WordPress user ID.
     * @return array Level progress data.
     */
    public static function get_level_progress( $user_id ) {

        $total_xp      = self::get_total_xp($user_id);
        $current_level  = self::calculate_level($total_xp);
        $thresholds     = self::LEVEL_THRESHOLDS;
        $max_level      = max(array_keys($thresholds));

        $current_threshold = isset($thresholds[$current_level]) ? $thresholds[$current_level] : 0;

        if ($current_level >= $max_level) {
            return [
                'current_level'     => $current_level,
                'total_xp'          => $total_xp,
                'xp_in_level'       => $total_xp - $current_threshold,
                'xp_for_next_level' => 0,
                'progress_percentage' => 100,
                'is_max_level'      => true,
            ];
        }

        $next_threshold   = $thresholds[$current_level + 1];
        $xp_in_level      = $total_xp - $current_threshold;
        $xp_for_next_level = $next_threshold - $current_threshold;
        $progress         = $xp_for_next_level > 0 ? round(($xp_in_level / $xp_for_next_level) * 100, 1) : 0;

        return [
            'current_level'       => $current_level,
            'total_xp'            => $total_xp,
            'xp_in_level'         => $xp_in_level,
            'xp_for_next_level'   => $xp_for_next_level,
            'progress_percentage' => min(100, $progress),
            'is_max_level'        => false,
        ];
    }

    /**
     * Build a full XP summary for the frontend.
     *
     * Aggregates total XP, level, level progress, and recent
     * transactions into a single structured response.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error XP summary data.
     */
    public static function get_xp_summary( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $level_progress = self::get_level_progress($user_id);
        $recent         = self::get_recent_transactions($user_id, 5);

        return [
            'total_xp'            => $level_progress['total_xp'],
            'current_level'       => $level_progress['current_level'],
            'xp_in_level'         => $level_progress['xp_in_level'],
            'xp_for_next_level'   => $level_progress['xp_for_next_level'],
            'progress_percentage' => $level_progress['progress_percentage'],
            'is_max_level'        => $level_progress['is_max_level'],
            'recent_transactions' => $recent,
        ];
    }

    /**
     * Get recent XP transactions for a user.
     *
     * @param int $user_id WordPress user ID.
     * @param int $limit   Maximum number of transactions.
     * @return array Recent transactions.
     */
    public static function get_recent_transactions( $user_id, $limit = 10 ) {

        global $wpdb;

        $user_id = absint($user_id);
        $limit   = absint($limit);

        if (!$user_id) {
            return [];
        }

        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT amount, source, description, created_at
                FROM {$table}
                WHERE user_id = %d
                ORDER BY created_at DESC
                LIMIT %d",
                $user_id,
                $limit
            )
        );

        $transactions = [];

        foreach ((array) $rows as $row) {
            $transactions[] = [
                'amount'      => absint($row->amount),
                'source'      => sanitize_key($row->source),
                'description' => $row->description,
                'created_at'  => $row->created_at,
            ];
        }

        return $transactions;
    }

    /**
     * Record an XP transaction.
     *
     * @param int    $user_id     WordPress user ID.
     * @param int    $amount      XP amount to award.
     * @param string $source      Source category.
     * @param int    $source_id   Optional reference ID.
     * @param string $description Human-readable description.
     * @return bool True on success.
     */
    private static function record_transaction( $user_id, $amount, $source, $source_id, $description ) {

        global $wpdb;

        $table = self::get_table_name();

        $result = $wpdb->insert(
            $table,
            [
                'user_id'     => absint($user_id),
                'amount'      => absint($amount),
                'source'      => sanitize_key($source),
                'source_id'   => absint($source_id),
                'description' => sanitize_text_field($description),
                'created_at'  => current_time('mysql'),
            ],
            [
                '%d',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );

        return false !== $result;
    }

    /**
     * Check if a milestone has already been awarded.
     *
     * Prevents duplicate XP awards for streak and progress milestones.
     *
     * @param int    $user_id   WordPress user ID.
     * @param string $source    Source category.
     * @param int    $source_id The milestone identifier.
     * @return bool True if the milestone has already been awarded.
     */
    private static function has_milestone( $user_id, $source, $source_id ) {

        global $wpdb;

        $table = self::get_table_name();

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id)
                FROM {$table}
                WHERE user_id = %d
                    AND source = %s
                    AND source_id = %d",
                absint($user_id),
                sanitize_key($source),
                absint($source_id)
            )
        );

        return absint($exists) > 0;
    }

    /**
     * Get attempt data for XP calculation.
     *
     * @param int $attempt_id Attempt ID.
     * @return object|null Attempt row.
     */
    private static function get_attempt_data( $attempt_id ) {

        global $wpdb;

        $table = MDCAT_Platform_Attempts_Handler::get_attempts_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, total_questions, correct_answers, wrong_answers, status
                FROM {$table}
                WHERE id = %d
                    AND status = %s",
                absint($attempt_id),
                'completed'
            )
        );
    }

    /**
     * Count total completed attempts for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return int Total completed attempts.
     */
    private static function get_completed_attempt_count( $user_id ) {

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
     * Get the XP transactions table name.
     *
     * @return string Table name with WordPress prefix.
     */
    public static function get_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_xp_transactions';
    }
}
