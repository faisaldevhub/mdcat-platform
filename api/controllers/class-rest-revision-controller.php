<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller for revision features: bookmarks and wrong questions.
 *
 * Endpoints:
 *
 *   GET  /revision/bookmarks        — List bookmarked questions.
 *   POST /revision/bookmarks/toggle — Toggle bookmark state.
 *   GET  /revision/wrong-questions  — List wrong questions.
 *
 * Delegates to:
 *
 *   - Revision_Service → bookmarks CRUD and wrong question retrieval
 *
 * Bookmarks and wrong questions share the same question response shape
 * via the service's format_questions() method. The only difference is
 * wrong_count: 0 for bookmarks, N for wrong questions.
 *
 * This controller contains NO business logic, NO SQL, and NO
 * direct database access.
 */
class MDCAT_Platform_REST_Revision_Controller
    extends MDCAT_Platform_REST_Base_Controller {

    /**
     * Register all revision routes.
     */
    public static function register_routes() {

        register_rest_route(self::$namespace, '/revision/bookmarks', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_bookmarks'],
            'permission_callback' => [__CLASS__, 'check_revision_access'],
        ]);

        register_rest_route(self::$namespace, '/revision/bookmarks/toggle', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'toggle_bookmark'],
            'permission_callback' => [__CLASS__, 'check_revision_access'],
        ]);

        register_rest_route(self::$namespace, '/revision/wrong-questions', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_wrong_questions'],
            'permission_callback' => [__CLASS__, 'check_revision_access'],
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /revision/bookmarks
    // ------------------------------------------------------------------

    /**
     * Return bookmarked questions for the authenticated student.
     *
     * Delegates to Revision_Service::get_bookmarked_questions() which
     * returns questions with full context (options, correct answer,
     * explanation, collection/chapter/subject).
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_bookmarks( $request ) {

        $user_id = self::get_current_user_id($request);

        $questions = MDCAT_Platform_Revision_Service::get_bookmarked_questions($user_id);

        if (is_wp_error($questions)) {
            return self::wp_error($questions);
        }

        return self::success(['questions' => $questions], 'Bookmarks loaded.');
    }

    // ------------------------------------------------------------------
    //  POST /revision/bookmarks/toggle
    // ------------------------------------------------------------------

    /**
     * Toggle bookmark state for a question.
     *
     * If the question is bookmarked, removes it. If not, adds it.
     * Validates question existence before insert.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function toggle_bookmark( $request ) {

        $user_id     = self::get_current_user_id($request);
        $question_id = absint($request->get_param('question_id'));

        if (!$question_id) {
            return self::error('invalid_question', __('A valid question is required.', 'mdcat-platform'), 400);
        }

        $result = MDCAT_Platform_Revision_Service::toggle_bookmark($user_id, $question_id);

        if (is_wp_error($result)) {
            return self::wp_error($result);
        }

        return self::success($result, 'Bookmark toggled.');
    }

    // ------------------------------------------------------------------
    //  GET /revision/wrong-questions
    // ------------------------------------------------------------------

    /**
     * Return wrong questions for the authenticated student.
     *
     * Delegates to Revision_Service::get_wrong_questions() which
     * dynamically identifies questions the student answered incorrectly
     * across completed attempts.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_wrong_questions( $request ) {

        $user_id = self::get_current_user_id($request);

        $questions = MDCAT_Platform_Revision_Service::get_wrong_questions($user_id);

        if (is_wp_error($questions)) {
            return self::wp_error($questions);
        }

        return self::success(['questions' => $questions], 'Wrong questions loaded.');
    }
}
