<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Quiz_Engine {

    /**
     * Start a new quiz attempt for a collection.
     *
     * @param int $user_id       WordPress user ID.
     * @param int $collection_id Collection ID.
     * @return array|WP_Error
     */
    public static function start_attempt( $user_id, $collection_id ) {

        global $wpdb;

        $user_id       = absint($user_id);
        $collection_id = absint($collection_id);

        if (!$user_id || !$collection_id) {
            return new WP_Error('invalid_attempt_input', __('A valid user and collection are required.', 'mdcat-platform'));
        }

        $collection = self::get_active_collection($collection_id);

        if (!$collection) {
            return new WP_Error('invalid_collection', __('The selected collection is unavailable.', 'mdcat-platform'));
        }

        $total_questions = self::get_collection_question_count($collection_id);

        if (!$total_questions) {
            return new WP_Error('empty_collection', __('This collection does not contain active questions.', 'mdcat-platform'));
        }

        $started_at         = current_time('mysql');
        $total_time_minutes = self::calculate_total_time($total_questions);

        $inserted = $wpdb->insert(
            self::get_attempts_table_name(),
            [
                'user_id'         => $user_id,
                'collection_id'   => $collection_id,
                'score'           => 0,
                'total_questions' => $total_questions,
                'correct_answers' => 0,
                'wrong_answers'   => 0,
                'time_taken'      => 0,
                'status'          => 'in_progress',
                'started_at'      => $started_at,
            ],
            [
                '%d',
                '%d',
                '%f',
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );

        if (!$inserted) {
            return new WP_Error('attempt_create_failed', __('Unable to create quiz attempt.', 'mdcat-platform'));
        }

        $attempt_id = absint($wpdb->insert_id);

        return [
            'attempt_id'         => $attempt_id,
            'user_id'            => $user_id,
            'collection_id'      => $collection_id,
            'total_questions'    => $total_questions,
            'total_time_minutes' => $total_time_minutes,
            'status'             => 'in_progress',
            'started_at'         => $started_at,
        ];
    }

    /**
     * Fetch active collection questions in quiz order.
     *
     * This method intentionally excludes answer and explanation data. Frontend,
     * AJAX, and REST consumers should not receive answer material before an
     * answer is submitted or the attempt is completed.
     *
     * @param int $collection_id Collection ID.
     * @return array|WP_Error
     */
    public static function get_collection_questions( $collection_id ) {

        global $wpdb;

        $collection_id = absint($collection_id);

        if (!$collection_id) {
            return new WP_Error('invalid_collection', __('A valid collection is required.', 'mdcat-platform'));
        }

        if (!self::get_active_collection($collection_id)) {
            return new WP_Error('invalid_collection', __('The selected collection is unavailable.', 'mdcat-platform'));
        }

        $questions_table = self::get_questions_table_name();

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question, option_a, option_b, option_c, option_d, difficulty
                FROM {$questions_table}
                WHERE collection_id = %d
                    AND status = %s
                    AND correct_option IN ('a', 'b', 'c', 'd')
                    AND difficulty IN ('easy', 'medium', 'hard')
                ORDER BY sort_order ASC, id ASC",
                $collection_id,
                'active'
            )
        );

        $structured_questions = [];

        foreach ($questions as $question) {
            $structured_questions[] = [
                'id'             => absint($question->id),
                'question'       => $question->question,
                'options'        => [
                    'a' => $question->option_a,
                    'b' => $question->option_b,
                    'c' => $question->option_c,
                    'd' => $question->option_d,
                ],
                'difficulty'     => sanitize_key($question->difficulty),
            ];
        }

        return $structured_questions;
    }

    /**
     * Calculate quiz time in minutes.
     *
     * @param int $question_count Question count.
     * @return int
     */
    public static function calculate_total_time( $question_count ) {

        return absint($question_count);
    }

    /**
     * Save or update one answer for an attempt.
     *
     * @param int    $attempt_id      Attempt ID.
     * @param int    $question_id     Question ID.
     * @param string $selected_option Selected option.
     * @return array|WP_Error
     */
    public static function save_answer( $attempt_id, $question_id, $selected_option ) {

        global $wpdb;

        $attempt_id      = absint($attempt_id);
        $question_id     = absint($question_id);
        $selected_option = sanitize_key($selected_option);

        if (!$attempt_id || !$question_id) {
            return new WP_Error('invalid_answer_input', __('A valid attempt and question are required.', 'mdcat-platform'));
        }

        if (!self::is_valid_selected_option($selected_option)) {
            return new WP_Error('invalid_selected_option', __('Selected option must be a, b, c, or d.', 'mdcat-platform'));
        }

        $attempt = self::get_attempt($attempt_id);

        if (!$attempt) {
            return new WP_Error('invalid_attempt', __('The selected attempt does not exist.', 'mdcat-platform'));
        }

        if (!self::is_valid_attempt_status($attempt->status)) {
            return new WP_Error('invalid_attempt_status', __('This attempt has an invalid status.', 'mdcat-platform'));
        }

        if ('completed' === $attempt->status) {
            return new WP_Error('attempt_completed', __('Completed attempts cannot accept answers.', 'mdcat-platform'));
        }

        if (!self::is_attempt_in_progress($attempt)) {
            return new WP_Error('attempt_not_in_progress', __('Only in-progress attempts can accept answers.', 'mdcat-platform'));
        }

        if (self::is_attempt_expired($attempt)) {
            return new WP_Error('attempt_expired', __('This attempt has expired.', 'mdcat-platform'));
        }

        $question = self::get_question($question_id);

        if (!$question) {
            return new WP_Error('invalid_question', __('The selected question does not exist.', 'mdcat-platform'));
        }

        if (!self::is_valid_selected_option($question->correct_option)) {
            return new WP_Error('invalid_question_answer_key', __('This question has an invalid answer key.', 'mdcat-platform'));
        }

        if (!self::is_valid_difficulty($question->difficulty)) {
            return new WP_Error('invalid_question_difficulty', __('This question has an invalid difficulty value.', 'mdcat-platform'));
        }

        if (!self::question_belongs_to_attempt($question, $attempt)) {
            return new WP_Error('question_collection_mismatch', __('This question does not belong to the attempt collection.', 'mdcat-platform'));
        }

        $is_correct = $selected_option === sanitize_key($question->correct_option) ? 1 : 0;
        $answered_at = current_time('mysql');
        $answers_table = self::get_attempt_answers_table_name();

        $existing_answer_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$answers_table} WHERE attempt_id = %d AND question_id = %d",
                $attempt_id,
                $question_id
            )
        );

        if ($existing_answer_id) {
            $saved = $wpdb->update(
                $answers_table,
                [
                    'selected_option' => $selected_option,
                    'is_correct'      => $is_correct,
                    'answered_at'     => $answered_at,
                ],
                ['id' => absint($existing_answer_id)],
                [
                    '%s',
                    '%d',
                    '%s',
                ],
                ['%d']
            );

            if (false === $saved) {
                return new WP_Error('answer_update_failed', __('Unable to update attempt answer.', 'mdcat-platform'));
            }

            $answer_id = absint($existing_answer_id);
        } else {
            $saved = $wpdb->insert(
                $answers_table,
                [
                    'attempt_id'      => $attempt_id,
                    'question_id'     => $question_id,
                    'selected_option' => $selected_option,
                    'is_correct'      => $is_correct,
                    'answered_at'     => $answered_at,
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                ]
            );

            if (!$saved) {
                return new WP_Error('answer_create_failed', __('Unable to save attempt answer.', 'mdcat-platform'));
            }

            $answer_id = absint($wpdb->insert_id);
        }

        return [
            'answer_id'       => $answer_id,
            'attempt_id'      => $attempt_id,
            'question_id'     => $question_id,
            'selected_option' => $selected_option,
            'is_correct'      => (bool) $is_correct,
            'answered_at'     => $answered_at,
        ];
    }

    /**
     * Calculate attempt score from stored answers.
     *
     * @param int $attempt_id Attempt ID.
     * @return array|WP_Error
     */
    public static function calculate_score( $attempt_id ) {

        global $wpdb;

        $attempt_id = absint($attempt_id);

        if (!$attempt_id) {
            return new WP_Error('invalid_attempt', __('A valid attempt is required.', 'mdcat-platform'));
        }

        $attempt = self::get_attempt($attempt_id);

        if (!$attempt) {
            return new WP_Error('invalid_attempt', __('The selected attempt does not exist.', 'mdcat-platform'));
        }

        $answers_table = self::get_attempt_answers_table_name();

        $answer_totals = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(id) AS answered_questions, COALESCE(SUM(is_correct), 0) AS correct_answers
                FROM {$answers_table}
                WHERE attempt_id = %d",
                $attempt_id
            )
        );

        $total_questions    = self::get_collection_question_count(absint($attempt->collection_id));
        $answered_questions = isset($answer_totals->answered_questions) ? absint($answer_totals->answered_questions) : 0;
        $correct_answers    = isset($answer_totals->correct_answers) ? absint($answer_totals->correct_answers) : 0;
        $wrong_answers      = max(0, $answered_questions - $correct_answers);
        $score              = $correct_answers;

        return [
            'attempt_id'         => $attempt_id,
            'collection_id'      => absint($attempt->collection_id),
            'total_questions'    => $total_questions,
            'answered_questions' => $answered_questions,
            'correct_answers'    => $correct_answers,
            'wrong_answers'      => $wrong_answers,
            'score'              => $score,
        ];
    }

    /**
     * Complete an attempt and persist final scoring data.
     *
     * @param int $attempt_id Attempt ID.
     * @return array|WP_Error
     */
    public static function complete_attempt( $attempt_id ) {

        global $wpdb;

        $attempt_id = absint($attempt_id);

        if (!$attempt_id) {
            return new WP_Error('invalid_attempt', __('A valid attempt is required.', 'mdcat-platform'));
        }

        $attempt = self::get_attempt($attempt_id);

        if (!$attempt) {
            return new WP_Error('invalid_attempt', __('The selected attempt does not exist.', 'mdcat-platform'));
        }

        if (!self::is_valid_attempt_status($attempt->status)) {
            return new WP_Error('invalid_attempt_status', __('This attempt has an invalid status.', 'mdcat-platform'));
        }

        if ('completed' === $attempt->status) {
            return new WP_Error('attempt_completed', __('This attempt has already been completed.', 'mdcat-platform'));
        }

        if (!self::is_attempt_in_progress($attempt)) {
            return new WP_Error('attempt_not_in_progress', __('Only in-progress attempts can be completed.', 'mdcat-platform'));
        }

        if (!self::get_active_collection(absint($attempt->collection_id))) {
            return new WP_Error('invalid_collection', __('The attempt collection is unavailable.', 'mdcat-platform'));
        }

        $score_data = self::calculate_score($attempt_id);

        if (is_wp_error($score_data)) {
            return $score_data;
        }

        if (!$score_data['total_questions']) {
            return new WP_Error('empty_collection', __('This attempt cannot be completed because the collection has no active questions.', 'mdcat-platform'));
        }

        $completed_at = current_time('mysql');
        $time_taken   = self::calculate_time_taken($attempt->started_at, $completed_at);

        $updated = $wpdb->update(
            self::get_attempts_table_name(),
            [
                'score'           => $score_data['score'],
                'total_questions' => $score_data['total_questions'],
                'correct_answers' => $score_data['correct_answers'],
                'wrong_answers'   => $score_data['wrong_answers'],
                'time_taken'      => $time_taken,
                'status'          => 'completed',
                'completed_at'    => $completed_at,
            ],
            ['id' => $attempt_id],
            [
                '%f',
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
            ],
            ['%d']
        );

        if (false === $updated) {
            return new WP_Error('attempt_complete_failed', __('Unable to complete attempt.', 'mdcat-platform'));
        }

        return array_merge(
            $score_data,
            [
                'status'       => 'completed',
                'time_taken'   => $time_taken,
                'completed_at' => $completed_at,
            ]
        );
    }

    /**
     * Get the attempts table name.
     *
     * @return string
     */
    private static function get_attempts_table_name() {

        return MDCAT_Platform_Attempts_Handler::get_attempts_table_name();
    }

    /**
     * Get the attempt answers table name.
     *
     * @return string
     */
    private static function get_attempt_answers_table_name() {

        return MDCAT_Platform_Attempts_Handler::get_attempt_answers_table_name();
    }

    /**
     * Get the questions table name.
     *
     * @return string
     */
    private static function get_questions_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_questions';
    }

    /**
     * Get the collections table name.
     *
     * @return string
     */
    private static function get_collections_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_collections';
    }

    /**
     * Fetch an active collection row.
     *
     * @param int $collection_id Collection ID.
     * @return object|null
     */
    private static function get_active_collection( $collection_id ) {

        global $wpdb;

        $collection_id     = absint($collection_id);
        $collections_table = self::get_collections_table_name();

        if (!$collection_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, status
                FROM {$collections_table}
                WHERE id = %d
                    AND status = %s",
                $collection_id,
                'active'
            )
        );
    }

    /**
     * Count active questions for a collection.
     *
     * @param int $collection_id Collection ID.
     * @return int
     */
    private static function get_collection_question_count( $collection_id ) {

        global $wpdb;

        $collection_id   = absint($collection_id);
        $questions_table = self::get_questions_table_name();

        if (!$collection_id) {
            return 0;
        }

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(id)
                    FROM {$questions_table}
                    WHERE collection_id = %d
                        AND status = %s
                        AND correct_option IN ('a', 'b', 'c', 'd')
                        AND difficulty IN ('easy', 'medium', 'hard')",
                    $collection_id,
                    'active'
                )
            )
        );
    }

    /**
     * Fetch an attempt row.
     *
     * @param int $attempt_id Attempt ID.
     * @return object|null
     */
    private static function get_attempt( $attempt_id ) {

        global $wpdb;

        $attempt_id     = absint($attempt_id);
        $attempts_table = self::get_attempts_table_name();

        if (!$attempt_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, collection_id, score, total_questions, correct_answers, wrong_answers,
                    time_taken, status, started_at, completed_at
                FROM {$attempts_table}
                WHERE id = %d",
                $attempt_id
            )
        );
    }

    /**
     * Prepare ownership validation for future frontend, AJAX, and REST layers.
     *
     * @param object $attempt Attempt row.
     * @param int    $user_id WordPress user ID.
     * @return bool
     */
    public static function user_owns_attempt( $attempt, $user_id ) {

        $user_id = absint($user_id);

        if (!$attempt || !$user_id) {
            return false;
        }

        return absint($attempt->user_id) === $user_id;
    }

    /**
     * Validate attempt ownership by ID for future controllers.
     *
     * AJAX and REST layers can call this before exposing or mutating attempt
     * data without duplicating ownership logic outside the engine.
     *
     * @param int $attempt_id Attempt ID.
     * @param int $user_id    WordPress user ID.
     * @return bool
     */
    public static function user_owns_attempt_id( $attempt_id, $user_id ) {

        $attempt = self::get_attempt($attempt_id);

        return self::user_owns_attempt($attempt, $user_id);
    }

    /**
     * Validate attempt status for answer submission and completion.
     *
     * @param object $attempt Attempt row.
     * @return bool
     */
    private static function is_attempt_in_progress( $attempt ) {

        return $attempt && 'in_progress' === sanitize_key($attempt->status);
    }

    /**
     * Validate attempt lifecycle status values.
     *
     * @param string $status Attempt status.
     * @return bool
     */
    private static function is_valid_attempt_status( $status ) {

        return array_key_exists(sanitize_key($status), MDCAT_Platform_Attempts_Handler::get_allowed_statuses());
    }

    /**
     * Prepare timer enforcement for future quiz expiry rules.
     *
     * Current MVP does not auto-expire attempts. This helper centralizes the
     * decision so AJAX/REST/UI layers can rely on one service rule later.
     *
     * @param object $attempt Attempt row.
     * @return bool
     */
    private static function is_attempt_expired( $attempt ) {

        if (!$attempt || empty($attempt->started_at)) {
            return true;
        }

        return false;
    }

    /**
     * Fetch a question row needed for answer validation and marking.
     *
     * @param int $question_id Question ID.
     * @return object|null
     */
    private static function get_question( $question_id ) {

        global $wpdb;

        $question_id     = absint($question_id);
        $questions_table = self::get_questions_table_name();

        if (!$question_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, collection_id, correct_option, difficulty, status
                FROM {$questions_table}
                WHERE id = %d
                    AND status = %s",
                $question_id,
                'active'
            )
        );
    }

    /**
     * Validate question ownership against the attempt collection.
     *
     * @param object $question Question row.
     * @param object $attempt  Attempt row.
     * @return bool
     */
    private static function question_belongs_to_attempt( $question, $attempt ) {

        if (!$question || !$attempt) {
            return false;
        }

        return absint($question->collection_id) === absint($attempt->collection_id);
    }

    /**
     * Validate selected option values.
     *
     * @param string $selected_option Selected option.
     * @return bool
     */
    private static function is_valid_selected_option( $selected_option ) {

        return array_key_exists(sanitize_key($selected_option), MDCAT_Platform_Attempts_Handler::get_allowed_selected_options());
    }

    /**
     * Validate difficulty values from question records.
     *
     * @param string $difficulty Difficulty value.
     * @return bool
     */
    private static function is_valid_difficulty( $difficulty ) {

        return array_key_exists(
            sanitize_key($difficulty),
            [
                'easy'   => true,
                'medium' => true,
                'hard'   => true,
            ]
        );
    }

    /**
     * Calculate elapsed attempt time in seconds.
     *
     * @param string $started_at   Attempt start time.
     * @param string $completed_at Attempt completion time.
     * @return int
     */
    private static function calculate_time_taken( $started_at, $completed_at ) {

        $started_timestamp   = strtotime($started_at);
        $completed_timestamp = strtotime($completed_at);

        if (!$started_timestamp || !$completed_timestamp || $completed_timestamp < $started_timestamp) {
            return 0;
        }

        return absint($completed_timestamp - $started_timestamp);
    }
}
