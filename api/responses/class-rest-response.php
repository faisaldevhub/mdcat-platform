<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Standardized REST API response builder for the MDCAT Platform.
 *
 * Every REST controller must use this class to format responses.
 * This ensures a consistent JSON envelope across all endpoints,
 * making the API predictable for the Next.js frontend.
 *
 * Success format:
 *
 *     {
 *         "success": true,
 *         "message": "Operation completed successfully",
 *         "data": { ... }
 *     }
 *
 * Error format:
 *
 *     {
 *         "success": false,
 *         "code": "error_code",
 *         "message": "Human-readable error message",
 *         "errors": { ... }
 *     }
 *
 * Usage in controllers:
 *
 *     // Success with data.
 *     return MDCAT_Platform_REST_Response::success($data, 'Dashboard loaded successfully');
 *
 *     // Error with code and message.
 *     return MDCAT_Platform_REST_Response::error('missing_field', 'Email is required.', 400);
 *
 *     // Convert WP_Error from a service.
 *     $result = Some_Service::do_something();
 *     if (is_wp_error($result)) {
 *         return MDCAT_Platform_REST_Response::from_wp_error($result);
 *     }
 *
 *     // Paginated list.
 *     return MDCAT_Platform_REST_Response::paginated($items, $page, $per_page, $total);
 */
class MDCAT_Platform_REST_Response {

    /**
     * HTTP status codes mapped to WP_Error codes.
     *
     * When converting a WP_Error via from_wp_error(), the error code
     * is looked up here to determine the HTTP status. If no match is
     * found, the default status (400) is used.
     *
     * This map is intentionally kept inside the class rather than in
     * JWT_Config because it covers error codes from all modules —
     * not just authentication.
     *
     * @var array<string, int>
     */
    private static $error_status_map = [

        // Authentication errors → 401 Unauthorized.
        'login_required'       => 401,
        'not_logged_in'        => 401,
        'missing_token'        => 401,
        'token_expired'        => 401,
        'token_invalid'        => 401,
        'token_malformed'      => 401,
        'token_not_valid_yet'  => 401,
        'token_type_mismatch'  => 401,
        'token_invalid_subject' => 401,
        'token_error'          => 401,
        'user_not_found'       => 401,
        'jwt_secret_missing'   => 401,
        'invalid_username'     => 401,
        'invalid_email'        => 401,
        'incorrect_password'   => 401,

        // Authorization errors → 403 Forbidden.
        'account_suspended'    => 403,
        'unauthorized'         => 403,
        'forbidden'            => 403,

        // Not found → 404.
        'not_found'            => 404,

        // Validation errors → 422 Unprocessable Entity.
        'validation_failed'       => 422,
        'invalid_input'           => 422,
        'invalid_request'         => 422,
        'required_field_missing'  => 422,

        // Rate limiting → 429.
        'rate_limited'         => 429,
        'too_many_attempts'    => 429,
    ];

    /**
     * Build a success response.
     *
     * @param mixed  $data    Response payload (array, object, or null).
     * @param string $message Human-readable success message.
     * @param int    $status  HTTP status code. Default 200.
     * @return WP_REST_Response
     */
    public static function success( $data = null, $message = '', $status = 200 ) {

        $body = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        return new WP_REST_Response($body, $status);
    }

    /**
     * Build an error response.
     *
     * @param string $code    Machine-readable error code (snake_case).
     * @param string $message Human-readable error description.
     * @param int    $status  HTTP status code. Default 400.
     * @param array  $errors  Optional field-level validation errors.
     * @return WP_REST_Response
     */
    public static function error( $code, $message, $status = 400, $errors = [] ) {

        $body = [
            'success' => false,
            'code'    => $code,
            'message' => $message,
            'errors'  => (object) $errors,
        ];

        return new WP_REST_Response($body, $status);
    }

    /**
     * Convert a WP_Error into a standardized error response.
     *
     * Extracts the error code and message from the WP_Error, then
     * resolves the HTTP status via the internal error-to-status map.
     * If the error code is not in the map, the provided $default_status
     * is used.
     *
     * Services throughout the plugin return WP_Error objects. This
     * method allows controllers to pass them through without manually
     * mapping codes to HTTP statuses:
     *
     *     $result = Quiz_Engine::start_attempt($user_id, $collection_id);
     *     if (is_wp_error($result)) {
     *         return MDCAT_Platform_REST_Response::from_wp_error($result);
     *     }
     *
     * @param WP_Error $error          The WordPress error object.
     * @param int      $default_status Fallback HTTP status if code is not mapped. Default 400.
     * @return WP_REST_Response
     */
    public static function from_wp_error( $error, $default_status = 400 ) {

        $code    = $error->get_error_code();
        $message = $error->get_error_message();

        // Resolve HTTP status from the error code map.
        $status = isset(self::$error_status_map[$code])
            ? self::$error_status_map[$code]
            : $default_status;

        // Collect additional error data if present.
        $error_data = $error->get_error_data($code);
        $errors     = is_array($error_data) ? $error_data : [];

        return self::error($code, $message, $status, $errors);
    }

    /**
     * Build a paginated success response.
     *
     * Wraps a list of items with pagination metadata. The Next.js
     * frontend uses the pagination object to render page controls
     * and determine if more data is available.
     *
     * @param array  $items    List of result items.
     * @param int    $page     Current page number (1-indexed).
     * @param int    $per_page Items per page.
     * @param int    $total    Total number of items across all pages.
     * @param string $message  Human-readable success message.
     * @return WP_REST_Response
     */
    public static function paginated( $items, $page, $per_page, $total, $message = '' ) {

        $data = [
            'items'      => $items,
            'pagination' => [
                'page'        => absint($page),
                'per_page'    => absint($per_page),
                'total_items' => absint($total),
                'total_pages' => absint(ceil($total / max(1, $per_page))),
            ],
        ];

        return self::success($data, $message);
    }
}
