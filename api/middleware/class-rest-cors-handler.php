<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CORS handler for the MDCAT Platform REST API.
 *
 * Implements a whitelist-based CORS strategy. Only origins explicitly
 * listed in the allowed origins array receive CORS headers. All other
 * origins are silently ignored (the browser enforces the block).
 *
 * Why no wildcard (*) origin:
 *
 *   Access-Control-Allow-Origin: * is incompatible with credentials.
 *   The MDCAT API sends Authorization headers (Bearer tokens) and may
 *   use httpOnly cookies for refresh tokens — both require
 *   Access-Control-Allow-Credentials: true. Per the CORS spec (Fetch
 *   Standard §3.2.5), a credentialed request MUST have a specific
 *   origin, not "*". Using "*" would cause browsers to reject every
 *   authenticated request. Additionally, wildcards allow any website
 *   to make API calls, which is a security risk for an API that
 *   handles student data.
 *
 * Configuring allowed origins:
 *
 *   Option 1 — WordPress option (stored in wp_options):
 *
 *     update_option('mdcat_cors_allowed_origins', [
 *         'https://mdcatinsecond.com',
 *         'https://www.mdcatinsecond.com',
 *     ]);
 *
 *   Option 2 — Filter (in theme or companion plugin):
 *
 *     add_filter('mdcat_api_allowed_origins', function ($origins) {
 *         $origins[] = 'https://staging.mdcatinsecond.com';
 *         return $origins;
 *     });
 *
 *   Development defaults (always included when WP_DEBUG is true):
 *
 *     http://localhost:3000  — Next.js dev server
 *     http://localhost:5173  — Vite dev server
 *     http://127.0.0.1:3000  — Alternative localhost
 *
 * Activation:
 *
 *   This class is loaded by the API Loader but must have its init()
 *   method called to hook into WordPress. The API Loader will call
 *   MDCAT_Platform_REST_CORS_Handler::init() during bootstrap.
 */
class MDCAT_Platform_REST_CORS_Handler {

    /**
     * Hook CORS handling into WordPress.
     *
     * Registers two hooks:
     *   - rest_pre_serve_request: adds CORS headers to REST responses.
     *   - rest_api_init: handles OPTIONS preflight via a catch-all route.
     */
    public static function init() {

        // Add CORS headers to all REST API responses.
        add_filter('rest_pre_serve_request', [__CLASS__, 'add_cors_headers'], 10, 4);

        // Handle OPTIONS preflight at the earliest opportunity.
        add_action('rest_api_init', [__CLASS__, 'handle_preflight'], 1);
    }

    /**
     * Add CORS headers to REST API responses.
     *
     * Checks the request's Origin header against the whitelist. If
     * the origin is allowed, CORS headers are added. If not, no
     * CORS headers are sent — the browser will block the request.
     *
     * @param bool             $served  Whether the request has been served.
     * @param WP_HTTP_Response $result  Response object.
     * @param WP_REST_Request  $request Request object.
     * @param WP_REST_Server   $server  REST server.
     * @return bool
     */
    public static function add_cors_headers( $served, $result, $request, $server ) {

        // Only apply CORS headers to MDCAT API routes.
        $route     = $request->get_route();
        $namespace = '/' . MDCAT_Platform_API_Loader::API_NAMESPACE;

        if (0 !== strpos($route, $namespace)) {
            return $served;
        }

        $origin = self::get_request_origin();

        if (empty($origin)) {
            return $served;
        }

        if (!self::is_origin_allowed($origin)) {
            return $served;
        }

        // Origin is whitelisted — send CORS headers.
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Vary by Origin so caches don't serve the wrong CORS headers
        // to different origins.
        header('Vary: Origin');

        return $served;
    }

    /**
     * Handle OPTIONS preflight requests.
     *
     * Browsers send an OPTIONS request before cross-origin requests
     * that include custom headers (like Authorization). WordPress
     * does not natively respond to OPTIONS for custom namespaces,
     * so we intercept it here.
     *
     * Must run early (priority 1 on rest_api_init) before WordPress
     * attempts to route the request and returns a 404.
     */
    public static function handle_preflight() {

        if ('OPTIONS' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        // Only handle preflight for MDCAT API routes.
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $namespace   = MDCAT_Platform_API_Loader::API_NAMESPACE;

        if (false === strpos($request_uri, $namespace)) {
            return;
        }

        $origin = self::get_request_origin();

        if (empty($origin) || !self::is_origin_allowed($origin)) {
            return;
        }

        // Send CORS headers and an empty 200 response.
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        header('Vary: Origin');
        header('Content-Length: 0');
        header('Content-Type: text/plain');

        status_header(200);
        exit;
    }

    /**
     * Check if an origin is in the allowed whitelist.
     *
     * @param string $origin The origin to check.
     * @return bool True if allowed, false otherwise.
     */
    public static function is_origin_allowed( $origin ) {

        $allowed = self::get_allowed_origins();

        // Normalize: strip trailing slashes for consistent comparison.
        $origin = rtrim($origin, '/');

        foreach ($allowed as $allowed_origin) {
            if (rtrim($allowed_origin, '/') === $origin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the list of allowed origins.
     *
     * Builds the whitelist from three sources in order:
     *
     *   1. WordPress option 'mdcat_cors_allowed_origins' — set via
     *      the admin panel or wp-cli.
     *   2. Development defaults — localhost origins added automatically
     *      when WP_DEBUG is true.
     *   3. Filter 'mdcat_api_allowed_origins' — allows themes or
     *      companion plugins to add origins dynamically.
     *
     * @return array List of allowed origin URLs.
     */
    public static function get_allowed_origins() {

        // 1. Origins from WordPress options (admin-configurable).
        $stored = get_option('mdcat_cors_allowed_origins', []);

        if (!is_array($stored)) {
            $stored = [];
        }

        $origins = $stored;

        // 2. Development defaults (only when WP_DEBUG is on).
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $dev_origins = [
                'http://localhost:3000',
                'http://localhost:5173',
                'http://127.0.0.1:3000',
            ];

            $origins = array_merge($origins, $dev_origins);
        }

        /**
         * Filter the list of allowed CORS origins.
         *
         * Use this filter to add origins dynamically — for example,
         * from environment variables or deployment configuration.
         *
         * Example:
         *
         *     add_filter('mdcat_api_allowed_origins', function ($origins) {
         *         $origins[] = 'https://staging.mdcatinsecond.com';
         *         return $origins;
         *     });
         *
         * @param array $origins Current list of allowed origin URLs.
         */
        $origins = apply_filters('mdcat_api_allowed_origins', $origins);

        // Deduplicate and re-index.
        return array_values(array_unique($origins));
    }

    /**
     * Get the Origin header from the current request.
     *
     * @return string The Origin header value, or empty string if absent.
     */
    private static function get_request_origin() {

        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            return sanitize_url(wp_unslash($_SERVER['HTTP_ORIGIN']));
        }

        return '';
    }
}
