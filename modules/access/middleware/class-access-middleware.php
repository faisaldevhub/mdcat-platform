<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Access_Middleware {

    const NONCE_ACTION = 'mdcat_quiz_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Guard: require a logged-in user.
     *
     * Returns true if the user is authenticated. Otherwise returns
     * an HTML string rendering the login-required card. Shortcodes
     * can use this as an early return to prevent rendering protected
     * content for guests.
     *
     * Usage in shortcodes:
     *
     *     $guard = MDCAT_Platform_Access_Middleware::require_login();
     *     if (true !== $guard) {
     *         return $guard;
     *     }
     *
     * @return true|string True if allowed, login-required HTML otherwise.
     */
    public static function require_login() {

        $access = MDCAT_Platform_Access_Control_Service::is_logged_in();

        if (true === $access) {
            return true;
        }

        return self::render_login_required();
    }

    /**
     * Guard: require quiz access for a specific collection.
     *
     * Checks both login status and collection-level permission.
     * Returns true if allowed, or an appropriate HTML card otherwise.
     *
     * @param int $collection_id Quiz collection ID.
     * @return true|string True if allowed, access-gate HTML otherwise.
     */
    public static function require_quiz_access( $collection_id ) {

        $login_check = MDCAT_Platform_Access_Control_Service::is_logged_in();

        if (is_wp_error($login_check)) {
            return self::render_login_required();
        }

        $user_id = get_current_user_id();
        $access  = MDCAT_Platform_Access_Control_Service::can_access_quiz($user_id, absint($collection_id));

        if (true === $access) {
            return true;
        }

        if (is_wp_error($access)) {
            return self::render_access_denied($access->get_error_message());
        }

        return self::render_access_denied(__('You do not have access to this quiz.', 'mdcat-platform'));
    }

    /**
     * Guard: require dashboard access.
     *
     * @return true|string True if allowed, access-gate HTML otherwise.
     */
    public static function require_dashboard_access() {

        $login_check = MDCAT_Platform_Access_Control_Service::is_logged_in();

        if (is_wp_error($login_check)) {
            return self::render_login_required();
        }

        $user_id = get_current_user_id();
        $access  = MDCAT_Platform_Access_Control_Service::can_access_dashboard($user_id);

        if (true === $access) {
            return true;
        }

        if (is_wp_error($access)) {
            return self::render_access_denied($access->get_error_message());
        }

        return self::render_access_denied(__('You do not have access to this page.', 'mdcat-platform'));
    }

    /**
     * AJAX guard: verify nonce, login, and optionally feature access.
     *
     * Sends a JSON error response and terminates execution if any
     * check fails. Future AJAX handlers should call this instead of
     * implementing their own verify_request() method.
     *
     * Usage in AJAX handlers:
     *
     *     MDCAT_Platform_Access_Middleware::verify_ajax_request();
     *     // Execution only reaches here if all checks pass
     *
     * @return void Dies on failure.
     */
    public static function verify_ajax_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            wp_send_json_error(
                [
                    'code'    => 'invalid_nonce',
                    'message' => __('Security check failed.', 'mdcat-platform'),
                ],
                403
            );
        }

        $login_check = MDCAT_Platform_Access_Control_Service::is_logged_in();

        if (is_wp_error($login_check)) {
            wp_send_json_error(
                [
                    'code'    => $login_check->get_error_code(),
                    'message' => $login_check->get_error_message(),
                ],
                401
            );
        }
    }

    /**
     * AJAX guard: verify request and quiz access for a collection.
     *
     * Combines nonce/login verification with collection-level access
     * control. Sends JSON error and dies on failure.
     *
     * @param int $collection_id Quiz collection ID.
     * @return void Dies on failure.
     */
    public static function verify_ajax_quiz_access( $collection_id ) {

        self::verify_ajax_request();

        $user_id = get_current_user_id();
        $access  = MDCAT_Platform_Access_Control_Service::can_access_quiz($user_id, absint($collection_id));

        if (is_wp_error($access)) {
            wp_send_json_error(
                [
                    'code'    => $access->get_error_code(),
                    'message' => $access->get_error_message(),
                ],
                403
            );
        }
    }

    /**
     * Render the login-required access gate card.
     *
     * Displayed to guest users in place of protected shortcode content.
     * Shows a clean card with a lock icon, message, and login button.
     *
     * @return string HTML output.
     */
    public static function render_login_required() {

        $login_url = wp_login_url(self::get_current_page_url());

        ob_start();
        ?>
        <div class="mdcat-access-gate mdcat-access-gate--login">
            <div class="mdcat-access-gate__icon">🔒</div>
            <h3 class="mdcat-access-gate__title">
                <?php esc_html_e('Login Required', 'mdcat-platform'); ?>
            </h3>
            <p class="mdcat-access-gate__message">
                <?php esc_html_e('Please log in to access this content. Your progress and data will be securely saved to your account.', 'mdcat-platform'); ?>
            </p>
            <a href="<?php echo esc_url($login_url); ?>" class="mdcat-access-gate__button">
                <?php esc_html_e('Log In', 'mdcat-platform'); ?>
            </a>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render the access-denied gate card.
     *
     * Displayed when a logged-in user lacks specific permissions
     * (e.g., not enrolled, no subscription). Future enrollment and
     * payment systems will trigger this state.
     *
     * @param string $reason Human-readable denial reason.
     * @return string HTML output.
     */
    public static function render_access_denied( $reason = '' ) {

        if (empty($reason)) {
            $reason = __('You do not have permission to access this content.', 'mdcat-platform');
        }

        ob_start();
        ?>
        <div class="mdcat-access-gate mdcat-access-gate--denied">
            <div class="mdcat-access-gate__icon">🚫</div>
            <h3 class="mdcat-access-gate__title">
                <?php esc_html_e('Access Restricted', 'mdcat-platform'); ?>
            </h3>
            <p class="mdcat-access-gate__message">
                <?php echo esc_html($reason); ?>
            </p>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get the current page URL for login redirect.
     *
     * After successful login, WordPress will redirect the user back
     * to the page they were trying to access.
     *
     * @return string Current page URL or home URL as fallback.
     */
    private static function get_current_page_url() {

        global $wp;

        if ($wp && !empty($wp->request)) {
            return home_url(add_query_arg([], $wp->request));
        }

        return home_url('/');
    }
}
