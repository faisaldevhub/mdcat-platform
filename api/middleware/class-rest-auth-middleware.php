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
     * Authenticate an incoming REST request.
     *
     * Extracts the Bearer token from the Authorization header,
     * validates it as an access token, verifies the user still
     * exists in WordPress, sets the WordPress user context, and
     * stores the user ID on the request object.
     *
     * Called by permission callbacks in the Base Controller. The
     * permission callback checks the return value:
     *   - int      → authentication succeeded (user ID).
     *   - WP_Error → authentication failed (passed to REST_Response).
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return int|WP_Error User ID on success, WP_Error on failure.
     */
    public static function authenticate( $request ) {

        $token = self::get_token_from_header($request);

        if (null === $token) {
            return new WP_Error(
                'missing_token',
                __('Authorization header is required.', 'mdcat-platform')
            );
        }

        // Validate the token as an access token (not refresh).
        $user_id = MDCAT_Platform_JWT_Handler::validate_access_token($token);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Establish WordPress user context and verify user exists.
        $user = self::set_current_user($user_id);

        if (is_wp_error($user)) {
            return $user;
        }

        // Store authenticated user data on the request object.
        // Controllers read these attributes instead of decoding JWTs.
        $request->set_param('_authenticated_user_id', $user->ID);
        $request->set_param('_authenticated_email', $user->user_email);
        $request->set_param('_authenticated_user', $user);

        return $user->ID;
    }

    /**
     * Extract the Bearer token from the Authorization header.
     *
     * Supports the standard format: Authorization: Bearer <token>
     * The "Bearer" prefix check is case-insensitive per RFC 6750.
     *
     * Returns null (not WP_Error) when the header is missing or
     * malformed, allowing the caller to decide how to handle the
     * absence. The authenticate() method converts null to a
     * WP_Error('missing_token').
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

    /**
     * Set the WordPress current user from a validated user ID.
     *
     * Calls wp_set_current_user() to establish the user context.
     * After this call, all WordPress functions that depend on the
     * current user will work correctly:
     *
     *   - get_current_user_id() → returns $user_id.
     *   - is_user_logged_in()   → returns true.
     *   - current_user_can()    → checks capabilities for $user_id.
     *
     * Also verifies the user still exists in the database. A valid
     * JWT for a deleted user must be rejected.
     *
     * Returns the WP_User object on success so the caller can store
     * it on the request without a second get_userdata() call.
     *
     * @param int $user_id WordPress user ID from the JWT payload.
     * @return WP_User|WP_Error WP_User on success, WP_Error if user not found.
     */
    public static function set_current_user( $user_id ) {

        $user_id = absint($user_id);

        // Set WordPress user context.
        wp_set_current_user($user_id);

        // Verify the user still exists in the database.
        $user = get_userdata($user_id);

        if (false === $user) {
            return new WP_Error(
                'user_not_found',
                __('User account no longer exists.', 'mdcat-platform')
            );
        }

        return $user;
    }
}
