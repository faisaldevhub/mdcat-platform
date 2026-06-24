<?php
/**
 * MDCAT Platform API — Static Smoke Test
 *
 * Validates the API layer by loading all files in isolation and
 * checking class definitions, method signatures, and dependency
 * chains without requiring a running WordPress instance.
 *
 * Run: php smoke-test.php
 */

$base = __DIR__ . '/';
$results = [];
$pass = 0;
$fail = 0;

function test($name, $condition, $detail = '') {
    global $results, $pass, $fail;
    $status = $condition ? 'PASS' : 'FAIL';
    $condition ? $pass++ : $fail++;
    $results[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
}

echo "=== MDCAT Platform API — Smoke Test ===\n\n";

// =============================================
// 1. FILE EXISTENCE
// =============================================
echo "--- 1. File Existence ---\n";

$files = [
    'api/class-api-loader.php',
    'api/auth/class-jwt-config.php',
    'api/auth/class-jwt-handler.php',
    'api/responses/class-rest-response.php',
    'api/middleware/class-rest-auth-middleware.php',
    'api/middleware/class-rest-cors-handler.php',
    'api/controllers/class-rest-base-controller.php',
    'api/controllers/class-rest-auth-controller.php',
];

foreach ($files as $file) {
    $exists = file_exists($base . $file);
    test("File: $file", $exists, $exists ? 'Found' : 'MISSING');
}

// =============================================
// 2. SYNTAX VALIDATION
// =============================================
echo "--- 2. Syntax Validation ---\n";

foreach ($files as $file) {
    $output = [];
    $code = 0;
    exec('php -l "' . $base . $file . '" 2>&1', $output, $code);
    $ok = ($code === 0);
    test("Syntax: $file", $ok, $ok ? 'No errors' : implode(' ', $output));
}

// =============================================
// 3. COMPOSER AUTOLOADER
// =============================================
echo "--- 3. Composer ---\n";

$autoload = $base . 'vendor/autoload.php';
test('vendor/autoload.php exists', file_exists($autoload));

if (file_exists($autoload)) {
    require_once $autoload;
    test('Firebase\JWT\JWT class loaded', class_exists('Firebase\JWT\JWT'));
    test('Firebase\JWT\Key class loaded', class_exists('Firebase\JWT\Key'));
    test('Firebase\JWT\ExpiredException exists', class_exists('Firebase\JWT\ExpiredException'));
}

// =============================================
// 4. CLASS DEFINITIONS (static analysis)
// =============================================
echo "--- 4. Class Definitions ---\n";

// We can't load WordPress-dependent classes, but we can parse them.
$class_checks = [
    'api/class-api-loader.php'                        => 'MDCAT_Platform_API_Loader',
    'api/auth/class-jwt-config.php'                   => 'MDCAT_Platform_JWT_Config',
    'api/auth/class-jwt-handler.php'                  => 'MDCAT_Platform_JWT_Handler',
    'api/responses/class-rest-response.php'            => 'MDCAT_Platform_REST_Response',
    'api/middleware/class-rest-auth-middleware.php'     => 'MDCAT_Platform_REST_Auth_Middleware',
    'api/middleware/class-rest-cors-handler.php'        => 'MDCAT_Platform_REST_CORS_Handler',
    'api/controllers/class-rest-base-controller.php'   => 'MDCAT_Platform_REST_Base_Controller',
    'api/controllers/class-rest-auth-controller.php'   => 'MDCAT_Platform_REST_Auth_Controller',
];

foreach ($class_checks as $file => $class) {
    $content = file_get_contents($base . $file);
    $found = (strpos($content, "class $class") !== false);
    test("Class $class defined", $found, "in $file");
}

// =============================================
// 5. METHOD EXISTENCE (grep-based)
// =============================================
echo "--- 5. Method Signatures ---\n";

$method_checks = [
    // API Loader
    ['api/class-api-loader.php', 'API_NAMESPACE', 'const'],
    ['api/class-api-loader.php', 'function init(', 'method'],
    ['api/class-api-loader.php', 'function load_dependencies(', 'method'],
    ['api/class-api-loader.php', 'function register_routes(', 'method'],

    // JWT Config
    ['api/auth/class-jwt-config.php', 'function get_secret_key(', 'method'],
    ['api/auth/class-jwt-config.php', 'function get_access_token_expiry(', 'method'],
    ['api/auth/class-jwt-config.php', 'function get_refresh_token_expiry(', 'method'],
    ['api/auth/class-jwt-config.php', 'function get_issuer(', 'method'],

    // JWT Handler
    ['api/auth/class-jwt-handler.php', 'function generate_access_token(', 'method'],
    ['api/auth/class-jwt-handler.php', 'function generate_refresh_token(', 'method'],
    ['api/auth/class-jwt-handler.php', 'function decode_token(', 'method'],
    ['api/auth/class-jwt-handler.php', 'function validate_access_token(', 'method'],
    ['api/auth/class-jwt-handler.php', 'function validate_refresh_token(', 'method'],

    // REST Response
    ['api/responses/class-rest-response.php', 'function success(', 'method'],
    ['api/responses/class-rest-response.php', 'function error(', 'method'],
    ['api/responses/class-rest-response.php', 'function from_wp_error(', 'method'],
    ['api/responses/class-rest-response.php', 'function paginated(', 'method'],

    // Auth Middleware
    ['api/middleware/class-rest-auth-middleware.php', 'function authenticate(', 'method'],
    ['api/middleware/class-rest-auth-middleware.php', 'function get_token_from_header(', 'method'],
    ['api/middleware/class-rest-auth-middleware.php', 'function set_current_user(', 'method'],
    ['api/middleware/class-rest-auth-middleware.php', '_authenticated_user_id', 'attribute'],
    ['api/middleware/class-rest-auth-middleware.php', '_authenticated_email', 'attribute'],
    ['api/middleware/class-rest-auth-middleware.php', '_authenticated_user', 'attribute'],

    // CORS Handler
    ['api/middleware/class-rest-cors-handler.php', 'function add_cors_headers(', 'method'],
    ['api/middleware/class-rest-cors-handler.php', 'function handle_preflight(', 'method'],
    ['api/middleware/class-rest-cors-handler.php', 'function is_origin_allowed(', 'method'],
    ['api/middleware/class-rest-cors-handler.php', 'function get_allowed_origins(', 'method'],
    ['api/middleware/class-rest-cors-handler.php', 'API_NAMESPACE', 'namespace-ref'],

    // Base Controller
    ['api/controllers/class-rest-base-controller.php', 'function init_namespace(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function success(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function error(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function get_current_user_id(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function get_current_user(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function get_authenticated_email(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function validate_pagination(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function check_public_access(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function check_student_access(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function check_dashboard_access(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function check_quiz_access(', 'method'],
    ['api/controllers/class-rest-base-controller.php', 'function check_attempt_owner(', 'method'],

    // Auth Controller
    ['api/controllers/class-rest-auth-controller.php', 'function register_routes(', 'method'],
    ['api/controllers/class-rest-auth-controller.php', 'function login(', 'method'],
    ['api/controllers/class-rest-auth-controller.php', 'function refresh(', 'method'],
    ['api/controllers/class-rest-auth-controller.php', 'function logout(', 'method'],
    ['api/controllers/class-rest-auth-controller.php', 'function me(', 'method'],
    ['api/controllers/class-rest-auth-controller.php', 'function format_user(', 'method'],
    ['api/controllers/class-rest-auth-controller.php', 'function check_login_rate_limit(', 'method'],
];

foreach ($method_checks as $check) {
    $content = file_get_contents($base . $check[0]);
    $found = (strpos($content, $check[1]) !== false);
    $label = basename($check[0]) . ' → ' . $check[1];
    test($label, $found, $check[2]);
}

// =============================================
// 6. DEPENDENCY CHAIN VALIDATION
// =============================================
echo "--- 6. Dependency Chains ---\n";

// JWT Handler must reference JWT Config, never hardcode values.
$handler = file_get_contents($base . 'api/auth/class-jwt-handler.php');
test('JWT Handler references JWT_Config', strpos($handler, 'MDCAT_Platform_JWT_Config') !== false);
test('JWT Handler no hardcoded 86400', strpos($handler, '86400') === false, 'No hardcoded expiry');
test('JWT Handler no hardcoded HS256', strpos($handler, "'HS256'") === false, 'No hardcoded algorithm');

// CORS handler must reference API_NAMESPACE, never hardcode namespace.
$cors = file_get_contents($base . 'api/middleware/class-rest-cors-handler.php');
test('CORS references API_NAMESPACE', strpos($cors, 'API_NAMESPACE') !== false);
test('CORS no hardcoded mdcat/v1', strpos($cors, "'mdcat/v1'") === false);

// Base Controller must reference API_NAMESPACE.
$base_ctrl = file_get_contents($base . 'api/controllers/class-rest-base-controller.php');
test('Base Controller references API_NAMESPACE', strpos($base_ctrl, 'API_NAMESPACE') !== false);
test('Base Controller no hardcoded namespace', strpos($base_ctrl, "'mdcat/v1'") === false);

// API Loader is the single source for namespace.
$loader = file_get_contents($base . 'api/class-api-loader.php');
test('API Loader defines API_NAMESPACE', strpos($loader, "const API_NAMESPACE = 'mdcat/v1'") !== false);

// Auth Controller extends Base Controller.
$auth_ctrl = file_get_contents($base . 'api/controllers/class-rest-auth-controller.php');
test('Auth Controller extends Base Controller',
    strpos($auth_ctrl, 'extends MDCAT_Platform_REST_Base_Controller') !== false);

// =============================================
// 7. JWT PAYLOAD CLAIMS
// =============================================
echo "--- 7. JWT Payload Claims ---\n";

$claims = ['iss', 'iat', 'nbf', 'exp', 'sub', 'type'];
foreach ($claims as $claim) {
    test("Access token has '$claim' claim",
        strpos($handler, "'$claim'") !== false, 'in generate_access_token');
}

// =============================================
// 8. RESPONSE FORMAT
// =============================================
echo "--- 8. Response Format ---\n";

$response = file_get_contents($base . 'api/responses/class-rest-response.php');
test("Success format has 'success' key", strpos($response, "'success' => true") !== false);
test("Success format has 'message' key", strpos($response, "'message' => \$message") !== false);
test("Success format has 'data' key", strpos($response, "'data'    => \$data") !== false);
test("Error format has 'success' key", strpos($response, "'success' => false") !== false);
test("Error format has 'code' key", strpos($response, "'code'    => \$code") !== false);
test("Error format has 'errors' key", strpos($response, "'errors'") !== false);

// HTTP status code mappings.
test('401 for token_expired', strpos($response, "'token_expired'") !== false);
test('403 for account_suspended', strpos($response, "'account_suspended'") !== false);
test('404 for not_found', strpos($response, "'not_found'") !== false);
test('422 for validation_failed', strpos($response, "'validation_failed'") !== false);
test('429 for rate_limited', strpos($response, "'rate_limited'") !== false);

// =============================================
// 9. AUTH CONTRACT COMPLIANCE
// =============================================
echo "--- 9. Auth Contract Compliance ---\n";

// Login response shape.
test('Login returns access_token', strpos($auth_ctrl, "'access_token'") !== false);
test('Login returns refresh_token', strpos($auth_ctrl, "'refresh_token'") !== false);
test('Login returns token_type', strpos($auth_ctrl, "'token_type'") !== false);
test('Login returns expires_in', strpos($auth_ctrl, "'expires_in'") !== false);
test('Login returns user object', strpos($auth_ctrl, "'user'") !== false);

// User object shape.
test('User has id', strpos($auth_ctrl, "'id'") !== false);
test('User has display_name', strpos($auth_ctrl, "'display_name'") !== false);
test('User has email', strpos($auth_ctrl, "'email'") !== false);
test('User has role', strpos($auth_ctrl, "'role'") !== false);
test('User has avatar_url', strpos($auth_ctrl, "'avatar_url'") !== false);

// Refresh response.
test('Refresh returns user_id', strpos($auth_ctrl, "'user_id'") !== false);

// Route registration.
test('Route /auth/login registered', strpos($auth_ctrl, "'/auth/login'") !== false);
test('Route /auth/refresh registered', strpos($auth_ctrl, "'/auth/refresh'") !== false);
test('Route /auth/logout registered', strpos($auth_ctrl, "'/auth/logout'") !== false);
test('Route /auth/me registered', strpos($auth_ctrl, "'/auth/me'") !== false);

// =============================================
// 10. SECURITY CHECKS
// =============================================
echo "--- 10. Security ---\n";

// No wildcard CORS origin.
test('No wildcard Access-Control-Allow-Origin', strpos($cors, "'*'") === false);

// Login flow: suspension check before token generation.
$login_body = substr($auth_ctrl, strpos($auth_ctrl, 'function login('));
$suspension_pos = strpos($login_body, 'is_suspended');
$token_gen_pos = strpos($login_body, 'generate_access_token');
test('Suspension check before token generation',
    $suspension_pos !== false && $token_gen_pos !== false && $suspension_pos < $token_gen_pos);

// Secret key fallback.
$config = file_get_contents($base . 'api/auth/class-jwt-config.php');
test('Secret key has AUTH_KEY check', strpos($config, 'AUTH_KEY') !== false);
test('Secret key has wp_salt fallback', strpos($config, 'wp_salt') !== false);
test('Secret key has WP_Error fallback', strpos($config, 'jwt_secret_missing') !== false);

// CORS scoped to mdcat namespace.
test('CORS add_cors_headers scoped to namespace',
    strpos($cors, 'get_route()') !== false && strpos($cors, 'API_NAMESPACE') !== false);
test('CORS preflight scoped to namespace',
    strpos($cors, 'REQUEST_URI') !== false && strpos($cors, 'API_NAMESPACE') !== false);

// Rate limiting in auth controller.
test('Rate limiting: per-email check', strpos($auth_ctrl, 'mdcat_login_email_') !== false);
test('Rate limiting: per-IP check', strpos($auth_ctrl, 'mdcat_login_ip_') !== false);

// ABSPATH guard on every file.
foreach ($files as $file) {
    $content = file_get_contents($base . $file);
    test("ABSPATH guard: $file", strpos($content, "defined('ABSPATH')") !== false);
}

// =============================================
// 11. LOADER INTEGRATION
// =============================================
echo "--- 11. Loader Integration ---\n";

$class_loader = file_get_contents($base . 'includes/class-loader.php');
test('class-loader.php requires api-loader', strpos($class_loader, 'api/class-api-loader.php') !== false);
test('class-loader.php calls API_Loader::init()', strpos($class_loader, 'MDCAT_Platform_API_Loader::init()') !== false);
test('class-loader.php has file_exists guard', strpos($class_loader, "file_exists(MDCAT_PLATFORM_PATH . 'api/class-api-loader.php')") !== false);
test('class-loader.php has error_log fallback', strpos($class_loader, 'error_log') !== false);

// API is loaded AFTER all existing modules.
$api_pos = strpos($class_loader, 'api/class-api-loader.php');
$frontend_pos = strpos($class_loader, 'Frontend::init()');
test('API loads after Frontend::init()', $api_pos > $frontend_pos);

// =============================================
// RESULTS SUMMARY
// =============================================
echo "\n=== RESULTS ===\n\n";

foreach ($results as $r) {
    $icon = $r['status'] === 'PASS' ? '[PASS]' : '[FAIL]';
    $line = "$icon {$r['name']}";
    if ($r['detail']) {
        $line .= " — {$r['detail']}";
    }
    echo "$line\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total: " . ($pass + $fail) . " | Pass: $pass | Fail: $fail\n";
echo ($fail === 0) ? "STATUS: ALL TESTS PASSED\n" : "STATUS: $fail TEST(S) FAILED\n";
