<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API bootstrap loader for the MDCAT Platform.
 *
 * This is the single entry point for the entire API layer. It loads
 * all API class files in dependency order and registers routes on
 * the rest_api_init action.
 *
 * Called from includes/class-loader.php after all modules have been
 * initialized, ensuring that services and filter hooks are available
 * before any API class references them.
 *
 * Adding a new controller:
 *
 *   1. Add require_once in load_dependencies() (after base controller).
 *   2. Add Controller::register_routes() in register_routes().
 *   3. That's it — the controller inherits everything from Base_Controller.
 */
class MDCAT_Platform_API_Loader {

    /**
     * REST API namespace for all MDCAT endpoints.
     *
     * Single source of truth — referenced by Base_Controller::$namespace.
     * Change this value to version the entire API (e.g., 'mdcat/v2').
     */
    const API_NAMESPACE = 'mdcat/v1';

    /**
     * Initialize the API layer.
     *
     * Loads all API class files immediately, then hooks route
     * registration to rest_api_init so routes are only registered
     * when a REST API request is being served.
     */
    public static function init() {

        self::load_dependencies();

        // Initialize CORS handling (must run before routes are registered).
        if (class_exists('MDCAT_Platform_REST_CORS_Handler')) {
            MDCAT_Platform_REST_CORS_Handler::init();
        }

        // Hook the JWT authentication lifecycle natively into WordPress.
        add_filter('determine_current_user', ['MDCAT_Platform_REST_Auth_Middleware', 'determine_current_user'], 15);

        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Load all API class files in dependency order.
     *
     * Order matters — each file may depend on classes defined in
     * files loaded before it:
     *
     *   1. JWT Config      — no dependencies.
     *   2. JWT Handler     — depends on JWT Config.
     *   3. REST Response   — no dependencies.
     *   4. Auth Middleware  — depends on JWT Handler.
     *   5. Base Controller  — depends on Auth Middleware + REST Response.
     *   6. Auth Controller  — depends on Base Controller.
     *
     * Each file is guarded by file_exists() to prevent fatal errors
     * during development if a file is temporarily missing. Missing
     * files are logged via error_log() for debugging.
     */
    private static function load_dependencies() {

        $api_path = MDCAT_PLATFORM_PATH . 'api/';

        $files = [
            // 1. JWT authentication layer.
            $api_path . 'auth/class-jwt-config.php',
            $api_path . 'auth/class-jwt-handler.php',

            // 2. Response formatting.
            $api_path . 'responses/class-rest-response.php',

            // 3. Middleware.
            $api_path . 'middleware/class-rest-auth-middleware.php',
            $api_path . 'middleware/class-rest-cors-handler.php',

            // 4. Controllers.
            $api_path . 'controllers/class-rest-base-controller.php',
            $api_path . 'controllers/class-rest-auth-controller.php',
            $api_path . 'controllers/class-rest-content-controller.php',
            $api_path . 'controllers/class-rest-dashboard-controller.php',
            $api_path . 'controllers/class-rest-quiz-controller.php',
            $api_path . 'controllers/class-rest-analytics-controller.php',
            $api_path . 'controllers/class-rest-revision-controller.php',
            $api_path . 'controllers/class-rest-gamification-controller.php',
            $api_path . 'controllers/class-rest-notification-controller.php',
        ];

        foreach ($files as $file) {

            if (file_exists($file)) {
                require_once $file;
            } else {
                error_log(
                    sprintf(
                        '[MDCAT Platform API] Missing dependency: %s',
                        str_replace(MDCAT_PLATFORM_PATH, '', $file)
                    )
                );
            }
        }
    }

    /**
     * Register all REST API routes.
     *
     * Called on the rest_api_init action. Each controller's
     * register_routes() method defines its own endpoints via
     * register_rest_route().
     *
     * Future controllers are added here as single lines:
     *
     *   MDCAT_Platform_REST_Subjects_Controller::register_routes();
     *   MDCAT_Platform_REST_Dashboard_Controller::register_routes();
     *   MDCAT_Platform_REST_Quiz_Controller::register_routes();
     */
    public static function register_routes() {

        // Set the namespace on the base controller from our constant.
        if (class_exists('MDCAT_Platform_REST_Base_Controller')) {
            MDCAT_Platform_REST_Base_Controller::init_namespace();
        }

        // Phase 1 — Authentication.
        if (class_exists('MDCAT_Platform_REST_Auth_Controller')) {
            MDCAT_Platform_REST_Auth_Controller::register_routes();
        }

        // Phase 2A — Content browsing.
        if (class_exists('MDCAT_Platform_REST_Content_Controller')) {
            MDCAT_Platform_REST_Content_Controller::register_routes();
        }

        // Phase 2B — Dashboard.
        if (class_exists('MDCAT_Platform_REST_Dashboard_Controller')) {
            MDCAT_Platform_REST_Dashboard_Controller::register_routes();
        }

        // Phase 2C — Quiz engine.
        if (class_exists('MDCAT_Platform_REST_Quiz_Controller')) {
            MDCAT_Platform_REST_Quiz_Controller::register_routes();
        }

        // Phase 2D — Analytics.
        if (class_exists('MDCAT_Platform_REST_Analytics_Controller')) {
            MDCAT_Platform_REST_Analytics_Controller::register_routes();
        }

        // Phase 2D — Revision.
        if (class_exists('MDCAT_Platform_REST_Revision_Controller')) {
            MDCAT_Platform_REST_Revision_Controller::register_routes();
        }

        // Phase 2E — Gamification.
        if (class_exists('MDCAT_Platform_REST_Gamification_Controller')) {
            MDCAT_Platform_REST_Gamification_Controller::register_routes();
        }

        // Phase 2F — Notifications.
        if (class_exists('MDCAT_Platform_REST_Notification_Controller')) {
            MDCAT_Platform_REST_Notification_Controller::register_routes();
        }
    }
}
