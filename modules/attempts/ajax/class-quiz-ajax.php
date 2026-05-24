<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Quiz_Ajax {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register authenticated AJAX actions for the quiz engine.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_start_quiz', [__CLASS__, 'start_quiz']);
        add_action('wp_ajax_mdcat_get_questions', [__CLASS__, 'get_questions']);
        add_action('wp_ajax_mdcat_save_answer', [__CLASS__, 'save_answer']);
        add_action('wp_ajax_mdcat_complete_quiz', [__CLASS__, 'complete_quiz']);
        add_action('wp_ajax_mdcat_get_result', [__CLASS__, 'get_result']);
        add_action('wp_ajax_mdcat_get_attempt_history', [__CLASS__, 'get_attempt_history']);
    }

    /**
     * Start a quiz attempt for the current user.
     */
    public static function start_quiz() {

        self::verify_request();

        $user_id       = get_current_user_id();
        $collection_id = self::get_post_absint('collection_id');

        if (!$collection_id) {
            self::send_error('invalid_collection', __('A valid collection is required.', 'mdcat-platform'), 400);
        }

        $attempt = MDCAT_Platform_Quiz_Engine::start_attempt($user_id, $collection_id);

        if (is_wp_error($attempt)) {
            self::send_wp_error($attempt);
        }

        wp_send_json_success(
            [
                'attempt_id'      => absint($attempt['attempt_id']),
                'total_questions' => absint($attempt['total_questions']),
                'total_time'      => absint($attempt['total_time_minutes']),
                'status'          => sanitize_key($attempt['status']),
                'started_at'      => $attempt['started_at'],
            ]
        );
    }

    /**
     * Return safe question data for an owned attempt.
     */
    public static function get_questions() {

        self::verify_request();

        $attempt_id = self::get_post_absint('attempt_id');

        if (!$attempt_id) {
            self::send_error('invalid_attempt', __('A valid attempt is required.', 'mdcat-platform'), 400);
        }

        self::verify_attempt_ownership($attempt_id);

        $attempt = MDCAT_Platform_Quiz_Engine::get_attempt_context($attempt_id);

        if (is_wp_error($attempt)) {
            self::send_wp_error($attempt);
        }

        $questions = MDCAT_Platform_Quiz_Engine::get_collection_questions($attempt['collection_id']);

        if (is_wp_error($questions)) {
            self::send_wp_error($questions);
        }

        wp_send_json_success(
            [
                'attempt_id'    => $attempt_id,
                'collection_id' => absint($attempt['collection_id']),
                'questions'     => $questions,
            ]
        );
    }

    /**
     * Save one answer for an owned attempt.
     */
    public static function save_answer() {

        self::verify_request();

        $attempt_id      = self::get_post_absint('attempt_id');
        $question_id     = self::get_post_absint('question_id');
        $selected_option = self::get_post_key('selected_option');
        $question_index  = self::get_post_absint('question_index');

        if (!$attempt_id || !$question_id) {
            self::send_error('invalid_answer_input', __('A valid attempt and question are required.', 'mdcat-platform'), 400);
        }

        self::verify_attempt_ownership($attempt_id);

        $question_validation = MDCAT_Platform_Quiz_Engine::validate_question_for_attempt($attempt_id, $question_id);

        if (is_wp_error($question_validation)) {
            self::send_wp_error($question_validation);
        }

        $answer = MDCAT_Platform_Quiz_Engine::save_answer($attempt_id, $question_id, $selected_option);

        if (is_wp_error($answer)) {
            self::send_wp_error($answer);
        }

        wp_send_json_success(
            [
                'attempt_id'          => absint($answer['attempt_id']),
                'question_id'         => absint($answer['question_id']),
                'selected_option'     => sanitize_key($answer['selected_option']),
                'is_correct'          => (bool) $answer['is_correct'],
                'answered_at'         => $answer['answered_at'],
                'next_question_index' => $question_index + 1,
            ]
        );
    }

    /**
     * Complete an owned quiz attempt.
     */
    public static function complete_quiz() {

        self::verify_request();

        $attempt_id = self::get_post_absint('attempt_id');

        if (!$attempt_id) {
            self::send_error('invalid_attempt', __('A valid attempt is required.', 'mdcat-platform'), 400);
        }

        self::verify_attempt_ownership($attempt_id);

        $result = MDCAT_Platform_Quiz_Engine::complete_attempt($attempt_id);

        if (is_wp_error($result)) {
            self::send_wp_error($result);
        }

        wp_send_json_success(self::format_result($result));
    }

    /**
     * Return safe result data for an owned attempt.
     */
    public static function get_result() {

        self::verify_request();

        $attempt_id = self::get_post_absint('attempt_id');

        if (!$attempt_id) {
            self::send_error('invalid_attempt', __('A valid attempt is required.', 'mdcat-platform'), 400);
        }

        self::verify_attempt_ownership($attempt_id);

        $result = MDCAT_Platform_Quiz_Engine::get_attempt_result($attempt_id);

        if (is_wp_error($result)) {
            self::send_wp_error($result);
        }

        wp_send_json_success(self::format_result($result));
    }

    /**
     * Return completed attempt history for the current user.
     */
    public static function get_attempt_history() {

        self::verify_request();

        $history = MDCAT_Platform_Attempt_History::get_user_attempt_history(
            get_current_user_id(),
            [
                'page'     => self::get_post_absint('page'),
                'per_page' => self::get_post_absint('per_page'),
            ]
        );

        if (is_wp_error($history)) {
            self::send_wp_error($history);
        }

        wp_send_json_success($history);
    }

    /**
     * Verify nonce and authentication for every quiz AJAX request.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!is_user_logged_in()) {
            self::send_error('not_logged_in', __('You must be logged in to access this quiz action.', 'mdcat-platform'), 401);
        }
    }

    /**
     * Validate that the current user owns an attempt.
     *
     * @param int $attempt_id Attempt ID.
     */
    private static function verify_attempt_ownership( $attempt_id ) {

        $attempt_id = absint($attempt_id);
        $user_id    = get_current_user_id();

        if (!MDCAT_Platform_Quiz_Engine::user_owns_attempt_id($attempt_id, $user_id)) {
            self::send_error('forbidden_attempt', __('You do not have access to this attempt.', 'mdcat-platform'), 403);
        }
    }

    /**
     * Read an integer from POST data.
     *
     * @param string $key Request key.
     * @return int
     */
    private static function get_post_absint( $key ) {

        if (!isset($_POST[$key])) {
            return 0;
        }

        return absint(wp_unslash($_POST[$key]));
    }

    /**
     * Read a sanitized key from POST data.
     *
     * @param string $key Request key.
     * @return string
     */
    private static function get_post_key( $key ) {

        if (!isset($_POST[$key])) {
            return '';
        }

        return sanitize_key(wp_unslash($_POST[$key]));
    }

    /**
     * Format score/result payloads consistently.
     *
     * @param array $result Result data.
     * @return array
     */
    private static function format_result( $result ) {

        return [
            'attempt_id'         => isset($result['attempt_id']) ? absint($result['attempt_id']) : 0,
            'collection_id'      => isset($result['collection_id']) ? absint($result['collection_id']) : 0,
            'total_questions'    => isset($result['total_questions']) ? absint($result['total_questions']) : 0,
            'answered_questions' => isset($result['answered_questions']) ? absint($result['answered_questions']) : 0,
            'correct_answers'    => isset($result['correct_answers']) ? absint($result['correct_answers']) : 0,
            'wrong_answers'      => isset($result['wrong_answers']) ? absint($result['wrong_answers']) : 0,
            'score'              => isset($result['score']) ? (float) $result['score'] : 0,
            'status'             => isset($result['status']) ? sanitize_key($result['status']) : '',
            'time_taken'         => isset($result['time_taken']) ? absint($result['time_taken']) : 0,
            'completed_at'       => isset($result['completed_at']) ? $result['completed_at'] : null,
        ];
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
