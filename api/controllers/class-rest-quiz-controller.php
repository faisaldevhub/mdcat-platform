<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller for the quiz engine.
 *
 * Exposes the full quiz lifecycle through 7 endpoints:
 *
 *   POST /quiz/start            — Start a new quiz attempt.
 *   GET  /quiz/{id}/questions   — Load questions for an attempt.
 *   POST /quiz/{id}/answer      — Save one answer.
 *   POST /quiz/{id}/complete    — Complete an attempt (triggers gamification).
 *   GET  /quiz/{id}/result      — View attempt result.
 *   GET  /quiz/{id}/review      — Full post-completion review.
 *   GET  /quiz/history          — Paginated attempt history.
 *
 * All endpoints delegate to existing services:
 *
 *   - Quiz_Engine     → start, questions, answer, complete, result
 *   - Review_Service  → post-completion review with explanations
 *   - Attempt_History → paginated completed attempt history
 *
 * This controller contains NO business logic, NO SQL, and NO
 * direct database access. It is a thin translation layer.
 *
 * Ownership validation for attempt-scoped endpoints is handled by
 * the check_attempt_owner permission callback in Base_Controller,
 * which runs before any callback executes.
 */
class MDCAT_Platform_REST_Quiz_Controller
    extends MDCAT_Platform_REST_Base_Controller {

    /**
     * Register all quiz routes.
     */
    public static function register_routes() {

        // Start a new quiz attempt.
        register_rest_route(self::$namespace, '/quiz/start', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'start_quiz'],
            'permission_callback' => [__CLASS__, 'check_quiz_access'],
        ]);

        // Load questions for an owned attempt.
        register_rest_route(self::$namespace, '/quiz/(?P<id>\d+)/questions', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_questions'],
            'permission_callback' => [__CLASS__, 'check_attempt_owner'],
        ]);

        // Save one answer for an owned attempt.
        register_rest_route(self::$namespace, '/quiz/(?P<id>\d+)/answer', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'save_answer'],
            'permission_callback' => [__CLASS__, 'check_attempt_owner'],
        ]);

        // Complete an owned attempt.
        register_rest_route(self::$namespace, '/quiz/(?P<id>\d+)/complete', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'complete_quiz'],
            'permission_callback' => [__CLASS__, 'check_attempt_owner'],
        ]);

        // View result of an owned attempt.
        register_rest_route(self::$namespace, '/quiz/(?P<id>\d+)/result', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_result'],
            'permission_callback' => [__CLASS__, 'check_attempt_owner'],
        ]);

        // Full review of a completed owned attempt.
        register_rest_route(self::$namespace, '/quiz/(?P<id>\d+)/review', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_review'],
            'permission_callback' => [__CLASS__, 'check_attempt_owner'],
        ]);

        // Paginated attempt history for the authenticated student.
        register_rest_route(self::$namespace, '/quiz/history', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_history'],
            'permission_callback' => [__CLASS__, 'check_dashboard_access'],
        ]);
    }

    // ------------------------------------------------------------------
    //  POST /quiz/start
    // ------------------------------------------------------------------

    /**
     * Start a new quiz attempt for the authenticated student.
     *
     * Delegates to Quiz_Engine::start_attempt() which validates the
     * collection, counts active questions, and inserts an attempt row.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function start_quiz( $request ) {

        $user_id       = self::get_current_user_id($request);
        $collection_id = absint($request->get_param('collection_id'));

        if (!$collection_id) {
            return self::error('missing_collection_id', __('Collection ID is required.', 'mdcat-platform'), 400);
        }

        $attempt = MDCAT_Platform_Quiz_Engine::start_attempt($user_id, $collection_id);

        if (is_wp_error($attempt)) {
            return self::wp_error($attempt);
        }

        return self::success(
            [
                'attempt_id'      => absint($attempt['attempt_id']),
                'total_questions' => absint($attempt['total_questions']),
                'total_time'      => absint($attempt['total_time_minutes']),
                'status'          => sanitize_key($attempt['status']),
                'started_at'      => $attempt['started_at'],
            ],
            'Quiz started.'
        );
    }

    // ------------------------------------------------------------------
    //  GET /quiz/{id}/questions
    // ------------------------------------------------------------------

    /**
     * Load questions for an owned attempt.
     *
     * Two service calls:
     * 1. get_attempt_context() — retrieves collection_id from attempt.
     * 2. get_collection_questions() — returns safe question data
     *    (no correct_option, no explanation).
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_questions( $request ) {

        $attempt_id = absint($request->get_param('id'));

        $attempt = MDCAT_Platform_Quiz_Engine::get_attempt_context($attempt_id);

        if (is_wp_error($attempt)) {
            return self::wp_error($attempt);
        }

        $questions = MDCAT_Platform_Quiz_Engine::get_collection_questions($attempt['collection_id']);

        if (is_wp_error($questions)) {
            return self::wp_error($questions);
        }

        return self::success(
            [
                'attempt_id'    => $attempt_id,
                'collection_id' => absint($attempt['collection_id']),
                'questions'     => $questions,
            ],
            'Questions loaded.'
        );
    }

    // ------------------------------------------------------------------
    //  POST /quiz/{id}/answer
    // ------------------------------------------------------------------

    /**
     * Save one answer for an owned in-progress attempt.
     *
     * Validates question-to-attempt association, option validity,
     * attempt status, and expiry before persisting the answer.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function save_answer( $request ) {

        $attempt_id      = absint($request->get_param('id'));
        $question_id     = absint($request->get_param('question_id'));
        $selected_option = sanitize_key($request->get_param('selected_option'));

        if (!$question_id) {
            return self::error('invalid_answer_input', __('A valid question is required.', 'mdcat-platform'), 400);
        }

        if (!$selected_option) {
            return self::error('invalid_selected_option', __('Selected option is required.', 'mdcat-platform'), 400);
        }

        $question_validation = MDCAT_Platform_Quiz_Engine::validate_question_for_attempt($attempt_id, $question_id);

        if (is_wp_error($question_validation)) {
            return self::wp_error($question_validation);
        }

        $answer = MDCAT_Platform_Quiz_Engine::save_answer($attempt_id, $question_id, $selected_option);

        if (is_wp_error($answer)) {
            return self::wp_error($answer);
        }

        return self::success(
            [
                'attempt_id'      => absint($answer['attempt_id']),
                'question_id'     => absint($answer['question_id']),
                'selected_option' => sanitize_key($answer['selected_option']),
                'is_correct'      => (bool) $answer['is_correct'],
                'answered_at'     => $answer['answered_at'],
            ],
            'Answer saved.'
        );
    }

    // ------------------------------------------------------------------
    //  POST /quiz/{id}/complete
    // ------------------------------------------------------------------

    /**
     * Complete an owned in-progress attempt.
     *
     * Triggers the mdcat_quiz_completed action (streak, XP, badges,
     * achievements) and applies the mdcat_quiz_completion_response
     * filter to append gamification feedback to the response.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function complete_quiz( $request ) {

        $attempt_id = absint($request->get_param('id'));

        $result = MDCAT_Platform_Quiz_Engine::complete_attempt($attempt_id);

        if (is_wp_error($result)) {
            return self::wp_error($result);
        }

        $formatted = self::format_result($result);

        /**
         * Filter the quiz completion response before sending.
         *
         * Mirrors the AJAX handler behavior. Gamification module
         * appends XP, badge, and achievement data via this filter.
         *
         * @param array $formatted  The formatted quiz result data.
         * @param int   $attempt_id The completed attempt ID.
         */
        $response = apply_filters('mdcat_quiz_completion_response', $formatted, $attempt_id);

        return self::success($response, 'Quiz completed.');
    }

    // ------------------------------------------------------------------
    //  GET /quiz/{id}/result
    // ------------------------------------------------------------------

    /**
     * View the result of an owned attempt.
     *
     * Unlike complete, this is a pure read. No hooks are fired.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_result( $request ) {

        $attempt_id = absint($request->get_param('id'));

        $result = MDCAT_Platform_Quiz_Engine::get_attempt_result($attempt_id);

        if (is_wp_error($result)) {
            return self::wp_error($result);
        }

        return self::success(self::format_result($result), 'Result loaded.');
    }

    // ------------------------------------------------------------------
    //  GET /quiz/{id}/review
    // ------------------------------------------------------------------

    /**
     * Full post-completion review of an owned attempt.
     *
     * Delegates to Review_Service which enforces its own ownership
     * and completion checks. Returns questions with correct answers
     * and explanations.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_review( $request ) {

        $attempt_id = absint($request->get_param('id'));
        $user_id    = self::get_current_user_id($request);

        $review = MDCAT_Platform_Review_Service::get_attempt_review($attempt_id, $user_id);

        if (is_wp_error($review)) {
            return self::wp_error($review);
        }

        return self::success($review, 'Review loaded.');
    }

    // ------------------------------------------------------------------
    //  GET /quiz/history
    // ------------------------------------------------------------------

    /**
     * Paginated completed attempt history for the authenticated student.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_history( $request ) {

        $user_id  = self::get_current_user_id($request);
        $page     = self::sanitize_page($request);
        $per_page = self::sanitize_per_page($request);

        $history = MDCAT_Platform_Attempt_History::get_user_attempt_history(
            $user_id,
            [
                'page'     => $page,
                'per_page' => $per_page,
            ]
        );

        if (is_wp_error($history)) {
            return self::wp_error($history);
        }

        return self::success($history, 'Quiz history loaded.');
    }

    // ------------------------------------------------------------------
    //  Response Formatting
    // ------------------------------------------------------------------

    /**
     * Format score/result payloads consistently.
     *
     * Mirrors the AJAX handler's format_result() method exactly
     * to ensure the apply_filters response filter receives the
     * same data structure it expects.
     *
     * @param array $result Result data from Quiz_Engine.
     * @return array Formatted result.
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
}
