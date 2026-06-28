<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API authentication middleware for the MDCAT Platform.
 *
 * Extracts the JWT from the Authorization header, validates it,
 * establishes the WordPress user context, and stores the
 * authenticated user ID on the request object.
 *
 * This is the ONLY class that touches JWT tokens after login.
 * Controllers never decode, validate, or inspect tokens directly.
 * They retrieve the authenticated user via:
 *
 *     $user_id = $request->get_param('_authenticated_user_id');
 *
 * Or via the standard WordPress function (set by this middleware):
 *
 *     $user_id = get_current_user_id();
 *
 * Both methods return the same value after successful authentication.
 *
 * Request attributes set on success:
 *
 *     _authenticated_user_id  → int     WordPress user ID.
 *     _authenticated_email    → string  User email from database.
 *     _authenticated_user     → WP_User Complete WordPress user object.
 *
 * The WP_User object avoids repeated get_userdata() calls in
 * controllers. It is loaded once by the middleware and reused.
 */
class MDCAT_Platform_REST_Auth_Middleware {

    /**
     * Store the latest JWT decode error to preserve exact error contracts
     * (e.g. 'expired_token', 'invalid_token') since decoding happens during
     * determine_current_user before the REST request is fully dispatched.
     *
     * @var WP_Error|null
     */
    private static $jwt_error = null;

    /**
     * Hook into determine_current_user to authenticate JWTs early.
     *
     * This avoids calling wp_set_current_user() mid-flight during the REST
     * API request, which causes infinite recursion and fatal errors when
     * Gamification and Dashboard services later call get_current_user_id().
     *
     * @param int|bool $user_id The current determined user ID.
     * @return int|bool The determined user ID, or untouched if unchanged.
     */
    public static function determine_current_user( $user_id ) {
        
        // Reset static authentication state for this request lifecycle.
        self::$jwt_error = null;

        // If another authentication method (like cookies) succeeded, bail early.
        if ( ! empty( $user_id ) ) {
            return $user_id;
        }

        // Only execute JWT authentication for the mdcat/v1 REST namespace.
        // Tolerates subdirectories and reverse proxies by using strpos.
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($request_uri, '/mdcat/v1/') === false && strpos($request_uri, '/mdcat/v1') === false) {
            return $user_id;
        }

        // Only execute when a valid Authorization header exists.
        $token = self::get_token_from_server();
        if ( empty($token) ) {
            return $user_id;
        }

        // Decode exactly once per request.
        $decoded_user_id = MDCAT_Platform_JWT_Handler::validate_access_token($token);

        if (is_wp_error($decoded_user_id)) {
            // Preserve the exact error contract for authenticate().
            // Add HTTP status to the WP_Error data so WordPress REST API
            // returns the correct HTTP status code instead of defaulting to 500.
            $decoded_user_id->add_data(['status' => 401]);
            self::$jwt_error = $decoded_user_id;
            return $user_id; 
        }

        return absint($decoded_user_id);
    }

    /**
     * Authenticate an incoming REST request.
     *
     * Called by permission callbacks in the Base Controller. 
     * Verifies that determine_current_user succeeded and a legitimate
     * WordPress user exists.
     *
     * IMPORTANT: Every WP_Error returned from this method MUST include
     * a 'status' key in its error data. WordPress REST API uses this
     * to set the HTTP response status code. Without it, WordPress
     * defaults to HTTP 500, which prevents the frontend's silent
     * token refresh interceptor (which listens for 401) from firing.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return int|WP_Error User ID on success, WP_Error on failure.
     */
    public static function authenticate( $request ) {

        $token = self::get_token_from_header($request);

        if (null === $token) {
            return new WP_Error(
                'missing_token',
                __('Authorization header is required.', 'mdcat-platform'),
                ['status' => 401]
            );
        }

        // Return exact JWT errors (e.g. expired_token) captured early.
        if (is_wp_error(self::$jwt_error)) {
            return self::$jwt_error;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_Error(
                'invalid_token',
                __('Invalid or expired token.', 'mdcat-platform'),
                ['status' => 401]
            );
        }

        // Verify the user still exists in the database.
        $user = get_userdata($user_id);

        if ( ! $user || ! ( $user instanceof WP_User ) || ! $user->exists() ) {
            return new WP_Error(
                'user_not_found',
                __('User account no longer exists.', 'mdcat-platform'),
                ['status' => 401]
            );
        }

        // Store authenticated user data on the request object.
        // Controllers read these attributes instead of decoding JWTs.
        $request->set_param('_authenticated_user_id', $user->ID);
        $request->set_param('_authenticated_email', $user->user_email);
        $request->set_param('_authenticated_user', $user);

        return $user->ID;
    }

    /**
     * Extract the Bearer token from the $_SERVER global directly.
     * Used by determine_current_user where WP_REST_Request is unavailable.
     *
     * @return string|null
     */
    private static function get_token_from_server() {
        
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        
        // Handle CGI/FastCGI environments
        if (empty($auth_header) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (empty($auth_header)) {
            return null;
        }

        if (0 !== stripos($auth_header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($auth_header, 7));

        if (empty($token)) {
            return null;
        }

        return $token;
    }

    /**
     * Extract the Bearer token from the Authorization header.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return string|null The raw JWT string, or null if not found.
     */
    public static function get_token_from_header( $request ) {

        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header)) {
            return null;
        }

        // Check for "Bearer " prefix (case-insensitive).
        if (0 !== stripos($auth_header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($auth_header, 7));

        if (empty($token)) {
            return null;
        }

        return $token;
    }
}
