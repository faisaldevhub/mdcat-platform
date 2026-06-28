<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auth REST controller for the MDCAT Platform API.
 *
 * Handles student authentication via JWT tokens. Provides four
 * endpoints matching the AUTH_API_CONTRACT.md specification:
 *
 *   POST /auth/login   — Authenticate and receive JWT pair.
 *   POST /auth/refresh  — Exchange refresh token for new access token.
 *   POST /auth/logout   — Client-side logout acknowledgement.
 *   GET  /auth/me       — Get authenticated user profile.
 *
 * Rate limiting on login:
 *   - 5 failed attempts per email within 15 minutes → 429.
 *   - 20 total attempts per IP within 1 hour → 429.
 *   - Uses WordPress transients (same pattern as Enrollment_Ajax).
 */
class MDCAT_Platform_REST_Auth_Controller extends MDCAT_Platform_REST_Base_Controller {

    // ------------------------------------------------------------------
    //  Route Registration
    // ------------------------------------------------------------------

    /**
     * Register all authentication routes.
     *
     * Called by the API Loader during rest_api_init.
     */
    public static function register_routes() {

        // POST /auth/login — public, rate-limited.
        register_rest_route(self::$namespace, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'login'],
            'permission_callback' => [__CLASS__, 'check_public_access'],
            'args'                => [
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        // POST /auth/refresh — public.
        register_rest_route(self::$namespace, '/auth/refresh', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'refresh'],
            'permission_callback' => [__CLASS__, 'check_public_access'],
            'args'                => [
                'refresh_token' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // POST /auth/logout — requires valid access token.
        register_rest_route(self::$namespace, '/auth/logout', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'logout'],
            'permission_callback' => [__CLASS__, 'check_student_access'],
        ]);

        // GET /auth/me — requires valid access token.
        register_rest_route(self::$namespace, '/auth/me', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'me'],
            'permission_callback' => [__CLASS__, 'check_student_access'],
        ]);
    }

    // ------------------------------------------------------------------
    //  POST /auth/login
    // ------------------------------------------------------------------

    /**
     * Authenticate a student and return a JWT token pair.
     *
     * Flow:
     *   1. Check rate limits (IP + email).
     *   2. Validate credentials via wp_authenticate().
     *   3. Check suspension status.
     *   4. Generate access + refresh tokens.
     *   5. Return token pair + user profile.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_REST_Response
     */
    public static function login( $request ) {

        $email    = $request->get_param('email');
        $password = $request->get_param('password');

        // Validate required fields.
        if (empty($email) || empty($password)) {
            return self::error(
                'missing_credentials',
                __('Email and password are required.', 'mdcat-platform'),
                400
            );
        }

        // Check rate limits before attempting authentication.
        $rate_check = self::check_login_rate_limit($email);

        if (is_wp_error($rate_check)) {
            return self::wp_error($rate_check);
        }

        // Authenticate via WordPress.
        $user = wp_authenticate($email, $password);

        // Track the attempt for rate limiting.
        self::record_login_attempt($email, is_wp_error($user));

        if (is_wp_error($user)) {
            return self::wp_error($user, 401);
        }

        // Check if the account is suspended.
        if (MDCAT_Platform_Student_Status_Service::is_suspended($user->ID)) {
            return self::error(
                'account_suspended',
                __('Your account has been suspended. Please contact the administrator.', 'mdcat-platform'),
                403
            );
        }

        // Generate JWT tokens.
        $access_token = MDCAT_Platform_JWT_Handler::generate_access_token($user->ID, $user->user_email);

        if (is_wp_error($access_token)) {
            return self::wp_error($access_token, 500);
        }

        $refresh_token = MDCAT_Platform_JWT_Handler::generate_refresh_token($user->ID);

        if (is_wp_error($refresh_token)) {
            return self::wp_error($refresh_token, 500);
        }

        return self::success([
            'access_token'  => $access_token,
            'refresh_token' => $refresh_token,
            'token_type'    => 'Bearer',
            'expires_in'    => MDCAT_Platform_JWT_Config::get_access_token_expiry(),
            'user'          => self::format_user($user),
        ], __('Login successful.', 'mdcat-platform'));
    }

    // ------------------------------------------------------------------
    //  POST /auth/refresh
    // ------------------------------------------------------------------

    /**
     * Exchange a valid refresh token for a new access token.
     *
     * Does NOT rotate the refresh token — the original remains valid
     * until its own expiry. This simplifies client-side token storage.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_REST_Response
     */
    public static function refresh( $request ) {

        $refresh_token = $request->get_param('refresh_token');

        if (empty($refresh_token)) {
            return self::error(
                'missing_token',
                __('Refresh token is required.', 'mdcat-platform'),
                400
            );
        }

        // Validate the refresh token (checks type = 'refresh').
        $user_id = MDCAT_Platform_JWT_Handler::validate_refresh_token($refresh_token);

        if (is_wp_error($user_id)) {
            return self::wp_error($user_id, 401);
        }

        // Verify the user still exists.
        $user = get_userdata($user_id);

        if (false === $user) {
            return self::error(
                'user_not_found',
                __('User account no longer exists.', 'mdcat-platform'),
                401
            );
        }

        // Check suspension — prevent suspended users from refreshing.
        if (MDCAT_Platform_Student_Status_Service::is_suspended($user->ID)) {
            return self::error(
                'account_suspended',
                __('Your account has been suspended. Please contact the administrator.', 'mdcat-platform'),
                403
            );
        }

        // Generate a new access token.
        $access_token = MDCAT_Platform_JWT_Handler::generate_access_token($user->ID, $user->user_email);

        if (is_wp_error($access_token)) {
            return self::wp_error($access_token, 500);
        }

        return self::success([
            'access_token' => $access_token,
            'token_type'   => 'Bearer',
            'expires_in'   => MDCAT_Platform_JWT_Config::get_access_token_expiry(),
            'user_id'      => $user->ID,
        ], __('Token refreshed successfully.', 'mdcat-platform'));
    }

    // ------------------------------------------------------------------
    //  POST /auth/logout
    // ------------------------------------------------------------------

    /**
     * Acknowledge a logout request.
     *
     * JWT is stateless — the server does not maintain a token blocklist.
     * Logout is handled client-side by discarding stored tokens. This
     * endpoint exists for:
     *   - Consistent API contract for the frontend.
     *   - Future server-side token invalidation if needed.
     *   - Audit logging (can be added later).
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_REST_Response
     */
    public static function logout( $request ) {

        return self::success(null, __('Logged out successfully.', 'mdcat-platform'));
    }

    // ------------------------------------------------------------------
    //  GET /auth/me
    // ------------------------------------------------------------------

    /**
     * Return the authenticated user's profile.
     *
     * Reads the WP_User object stored on the request by the auth
     * middleware — no additional database query needed.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_REST_Response
     */
    public static function me( $request ) {

        $user = self::get_current_user($request);

        if (null === $user) {
            return self::error(
                'user_not_found',
                __('User not found.', 'mdcat-platform'),
                401
            );
        }

        $data = self::format_user($user);
        $data['registered_at'] = $user->user_registered;

        return self::success($data, __('User profile loaded.', 'mdcat-platform'));
    }

    // ------------------------------------------------------------------
    //  User Formatting
    // ------------------------------------------------------------------

    /**
     * Format a WP_User into the frontend-safe user object.
     *
     * Returns ONLY the fields defined in AUTH_API_CONTRACT.md.
     * WordPress internals (password hash, capabilities, activation
     * key, etc.) are never exposed.
     *
     * @param WP_User $user WordPress user object.
     * @return array Frontend-safe user data.
     */
    private static function format_user( $user ) {

        $roles = $user->roles;

        return [
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'email'        => $user->user_email,
            'role'         => !empty($roles) ? $roles[0] : 'subscriber',
            'avatar_url'   => get_avatar_url($user->ID, ['size' => 96]),
        ];
    }

    // ------------------------------------------------------------------
    //  Rate Limiting
    // ------------------------------------------------------------------

    /**
     * Check if the login attempt is rate-limited.
     *
     * Two independent limits:
     *   - Per email: 5 failed attempts within 15 minutes.
     *   - Per IP: 20 total attempts within 1 hour.
     *
     * Uses WordPress transients — same mechanism as the existing
     * enrollment rate limiter in Enrollment_Ajax::handle_submit().
     *
     * @param string $email The email being used to log in.
     * @return true|WP_Error True if allowed, WP_Error if rate-limited.
     */
    private static function check_login_rate_limit( $email ) {

        // Check IP-based limit first (broader protection).
        $ip      = self::get_client_ip();
        $ip_key  = 'mdcat_login_ip_' . md5($ip);
        $ip_hits = (int) get_transient($ip_key);

        if ($ip_hits >= 20) {
            return new WP_Error(
                'rate_limited',
                __('Too many requests. Please try again later.', 'mdcat-platform')
            );
        }

        // Check per-email failure limit.
        $email_key    = 'mdcat_login_email_' . md5(strtolower($email));
        $email_fails  = (int) get_transient($email_key);

        if ($email_fails >= 5) {
            return new WP_Error(
                'too_many_attempts',
                __('Too many failed login attempts. Please try again later.', 'mdcat-platform')
            );
        }

        return true;
    }

    /**
     * Record a login attempt for rate-limiting purposes.
     *
     * Always increments the IP counter (pass or fail).
     * Only increments the email counter on failure.
     *
     * @param string $email  The email used for login.
     * @param bool   $failed Whether the attempt failed.
     */
    private static function record_login_attempt( $email, $failed ) {

        // Always count IP attempts.
        $ip      = self::get_client_ip();
        $ip_key  = 'mdcat_login_ip_' . md5($ip);
        $ip_hits = (int) get_transient($ip_key);

        set_transient($ip_key, $ip_hits + 1, HOUR_IN_SECONDS);

        // Only count email failures.
        if ($failed) {
            $email_key   = 'mdcat_login_email_' . md5(strtolower($email));
            $email_fails = (int) get_transient($email_key);

            set_transient($email_key, $email_fails + 1, 15 * MINUTE_IN_SECONDS);
        }
    }

    /**
     * Get the client IP address.
     *
     * Checks X-Forwarded-For first for reverse proxy environments,
     * falls back to REMOTE_ADDR. Same pattern used by
     * Enrollment_Ajax::get_client_ip().
     *
     * @return string Client IP address.
     */
    private static function get_client_ip() {

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            return trim($ips[0]);
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '127.0.0.1';
    }
}
