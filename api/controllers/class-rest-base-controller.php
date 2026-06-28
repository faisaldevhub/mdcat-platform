<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base REST controller for the MDCAT Platform API.
 *
 * All REST controllers extend this class to inherit:
 *
 *   - Permission callbacks for route registration.
 *   - Response helpers that delegate to REST_Response.
 *   - Authenticated user accessors (read from middleware attributes).
 *   - Pagination validation and sanitization.
 *
 * This class contains NO business logic, NO database queries, and
 * NO endpoint definitions. It is purely shared controller infrastructure.
 *
 * Usage:
 *
 *     class MDCAT_Platform_REST_Dashboard_Controller
 *         extends MDCAT_Platform_REST_Base_Controller {
 *
 *         public static function register_routes() {
 *             register_rest_route(self::$namespace, '/dashboard', [
 *                 'methods'             => 'GET',
 *                 'callback'            => [__CLASS__, 'get_dashboard'],
 *                 'permission_callback' => [__CLASS__, 'check_dashboard_access'],
 *             ]);
 *         }
 *
 *         public static function get_dashboard( $request ) {
 *             $user_id = self::get_current_user_id($request);
 *             $data    = Dashboard_Service::get_stats($user_id);
 *             return self::success($data, 'Dashboard loaded.');
 *         }
 *     }
 */
class MDCAT_Platform_REST_Base_Controller {

    /**
     * REST API namespace shared by all controllers.
     *
     * Sourced from MDCAT_Platform_API_Loader::API_NAMESPACE to
     * maintain a single source of truth for the namespace string.
     *
     * @var string
     */
    protected static $namespace = '';

    /**
     * Set the namespace from the API Loader constant.
     *
     * Called once by the API Loader before routes are registered.
     * PHP does not allow class constant references in property
     * declarations, so this runtime initialization is required.
     */
    public static function init_namespace() {

        self::$namespace = MDCAT_Platform_API_Loader::API_NAMESPACE;
    }

    // ------------------------------------------------------------------
    //  Response Helpers
    //
    //  Thin wrappers around MDCAT_Platform_REST_Response so controllers
    //  can call self::success() instead of the full class name.
    // ------------------------------------------------------------------

    /**
     * Build a success response.
     *
     * @param mixed  $data    Response payload.
     * @param string $message Human-readable success message.
     * @param int    $status  HTTP status code. Default 200.
     * @return WP_REST_Response
     */
    protected static function success( $data = null, $message = '', $status = 200 ) {

        return MDCAT_Platform_REST_Response::success($data, $message, $status);
    }

    /**
     * Build an error response.
     *
     * @param string $code    Machine-readable error code.
     * @param string $message Human-readable error description.
     * @param int    $status  HTTP status code. Default 400.
     * @param array  $errors  Optional field-level validation errors.
     * @return WP_REST_Response
     */
    protected static function error( $code, $message, $status = 400, $errors = [] ) {

        return MDCAT_Platform_REST_Response::error($code, $message, $status, $errors);
    }

    /**
     * Convert a WP_Error into a standardized error response.
     *
     * @param WP_Error $wp_error       The WordPress error object.
     * @param int      $default_status Fallback HTTP status. Default 400.
     * @return WP_REST_Response
     */
    protected static function wp_error( $wp_error, $default_status = 400 ) {

        return MDCAT_Platform_REST_Response::from_wp_error($wp_error, $default_status);
    }

    /**
     * Build a paginated success response.
     *
     * @param array  $items    List of result items.
     * @param int    $page     Current page number.
     * @param int    $per_page Items per page.
     * @param int    $total    Total items across all pages.
     * @param string $message  Human-readable success message.
     * @return WP_REST_Response
     */
    protected static function paginated( $items, $page, $per_page, $total, $message = '' ) {

        return MDCAT_Platform_REST_Response::paginated($items, $page, $per_page, $total, $message);
    }

    // ------------------------------------------------------------------
    //  Authenticated User Accessors
    //
    //  Read user data from request attributes set by Auth Middleware.
    //  Controllers call these instead of decoding JWTs or calling
    //  get_userdata() themselves.
    // ------------------------------------------------------------------

    /**
     * Get the authenticated user ID from the request or WP Core natively.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return int WordPress user ID, or 0 if not authenticated.
     */
    protected static function get_current_user_id( $request ) {

        $user_id = $request->get_param('_authenticated_user_id');

        if ($user_id) {
            return absint($user_id);
        }

        return get_current_user_id();
    }

    /**
     * Get the authenticated WP_User object from the request or WP Core.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_User|null WP_User object, or null if not authenticated.
     */
    protected static function get_current_user( $request ) {

        $user = $request->get_param('_authenticated_user');
        
        if ($user) {
            return $user;
        }

        $wp_user = wp_get_current_user();
        return $wp_user->exists() ? $wp_user : null;
    }

    /**
     * Get the authenticated user's email from the request.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return string User email, or empty string if not authenticated.
     */
    protected static function get_authenticated_email( $request ) {

        $email = $request->get_param('_authenticated_email');

        return $email ? $email : '';
    }

    // ------------------------------------------------------------------
    //  Pagination Helpers
    // ------------------------------------------------------------------

    /**
     * Sanitize the page number from a request.
     *
     * Clamps to a minimum of 1.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return int Sanitized page number.
     */
    protected static function sanitize_page( $request ) {

        return max(1, absint($request->get_param('page')));
    }

    /**
     * Sanitize the per_page value from a request.
     *
     * Clamps to the range [1, $max]. Defaults to $default when
     * the parameter is missing or invalid.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @param int             $default Default items per page. Default 20.
     * @param int             $max     Maximum allowed per page. Default 100.
     * @return int Sanitized per_page value.
     */
    protected static function sanitize_per_page( $request, $default = 20, $max = 100 ) {

        $per_page = absint($request->get_param('per_page'));

        if ($per_page < 1) {
            return $default;
        }

        return min($per_page, $max);
    }

    /**
     * Extract and validate pagination parameters from a request.
     *
     * Returns a clean array with 'page' and 'per_page' keys that
     * can be passed directly to service methods.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @param int             $default Default items per page. Default 20.
     * @param int             $max     Maximum allowed per page. Default 100.
     * @return array { page: int, per_page: int }
     */
    protected static function validate_pagination( $request, $default = 20, $max = 100 ) {

        return [
            'page'     => self::sanitize_page($request),
            'per_page' => self::sanitize_per_page($request, $default, $max),
        ];
    }

    // ------------------------------------------------------------------
    //  Permission Callbacks
    //
    //  Each method is a 'permission_callback' for register_rest_route().
    //  Returns true to allow the request, or WP_Error to deny it.
    //
    //  These are public and static so they can be referenced as:
    //      'permission_callback' => [__CLASS__, 'check_student_access']
    //  from child controllers.
    // ------------------------------------------------------------------

    /**
     * Permission: allow all requests (no authentication required).
     *
     * Used by public endpoints: subjects, chapters, collections,
     * enrollment form.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return true Always returns true.
     */
    public static function check_public_access( $request ) {

        return true;
    }

    /**
     * Permission: require a valid JWT access token.
     *
     * Delegates to Auth Middleware which validates the token,
     * sets the WordPress user context, and stores user data
     * on the request object.
     *
     * Used by: dashboard, progress, analytics, gamification,
     * notifications, study planner, attempt history.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return true|WP_Error True if authenticated, WP_Error otherwise.
     */
    public static function check_student_access( $request ) {

        $result = MDCAT_Platform_REST_Auth_Middleware::authenticate($request);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Permission: require authentication + dashboard access.
     *
     * Checks JWT, then runs the dashboard access filter which
     * includes suspension checks via Student_Status_Service.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return true|WP_Error
     */
    public static function check_dashboard_access( $request ) {

        $auth = self::check_student_access($request);

        if (is_wp_error($auth)) {
            return $auth;
        }

        $user_id = self::get_current_user_id($request);
        $access  = MDCAT_Platform_Access_Control_Service::can_access_dashboard($user_id);

        if (is_wp_error($access)) {
            return $access;
        }

        return true;
    }

    /**
     * Permission: require authentication + quiz access for a collection.
     *
     * Reads collection_id from the request body (POST /quiz/start).
     * Checks JWT, then runs the quiz access filter which includes
     * suspension checks.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return true|WP_Error
     */
    public static function check_quiz_access( $request ) {

        $auth = self::check_student_access($request);

        if (is_wp_error($auth)) {
            return $auth;
        }

        $user_id       = self::get_current_user_id($request);
        $collection_id = absint($request->get_param('collection_id'));

        if (!$collection_id) {
            return new WP_Error(
                'missing_collection_id',
                __('Collection ID is required.', 'mdcat-platform')
            );
        }

        $access = MDCAT_Platform_Access_Control_Service::can_access_quiz($user_id, $collection_id);

        if (is_wp_error($access)) {
            return $access;
        }

        return true;
    }

    /**
     * Permission: require authentication + analytics access.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return true|WP_Error
     */
    public static function check_analytics_access( $request ) {

        $auth = self::check_student_access($request);

        if (is_wp_error($auth)) {
            return $auth;
        }

        $user_id = self::get_current_user_id($request);
        $access  = MDCAT_Platform_Access_Control_Service::can_access_analytics($user_id);

        if (is_wp_error($access)) {
            return $access;
        }

        return true;
    }

    /**
     * Permission: require authentication + revision access.
     *
     * Used by bookmarks and wrong questions endpoints.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return true|WP_Error
     */
    public static function check_revision_access( $request ) {

        $auth = self::check_student_access($request);

        if (is_wp_error($auth)) {
            return $auth;
        }

        $user_id = self::get_current_user_id($request);
        $access  = MDCAT_Platform_Access_Control_Service::can_access_revision($user_id);

        if (is_wp_error($access)) {
            return $access;
        }

        return true;
    }

    /**
     * Permission: require authentication + gamification access.
     *
     * Used by streak, XP, badges, achievements, leaderboard.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return true|WP_Error
     */
    public static function check_gamification_access( $request ) {

        $auth = self::check_student_access($request);

        if (is_wp_error($auth)) {
            return $auth;
        }

        $user_id = self::get_current_user_id($request);
        $access  = MDCAT_Platform_Access_Control_Service::can_access_streak($user_id);

        if (is_wp_error($access)) {
            return $access;
        }

        return true;
    }

    /**
     * Permission: require authentication + attempt ownership.
     *
     * Verifies the authenticated user owns the attempt specified
     * in the URL parameter 'id'. Prevents students from accessing
     * another student's quiz data.
     *
     * Used by: quiz questions, answer, complete, result, attempt review.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return true|WP_Error
     */
    public static function check_attempt_owner( $request ) {

        $auth = self::check_student_access($request);

        if (is_wp_error($auth)) {
            return $auth;
        }

        $user_id    = self::get_current_user_id($request);
        $attempt_id = absint($request->get_param('id'));

        if (!$attempt_id) {
            return new WP_Error(
                'missing_attempt_id',
                __('Attempt ID is required.', 'mdcat-platform')
            );
        }

        global $wpdb;

        $table  = $wpdb->prefix . 'mdcat_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, collection_id, status FROM {$table} WHERE id = %d",
                $attempt_id
            )
        );

        if (null === $attempt) {
            return new WP_Error(
                'not_found',
                __('Attempt not found.', 'mdcat-platform')
            );
        }

        if (absint($attempt->user_id) !== $user_id) {
            return new WP_Error(
                'forbidden',
                __('You do not have access to this attempt.', 'mdcat-platform')
            );
        }

        return true;
    }
}
