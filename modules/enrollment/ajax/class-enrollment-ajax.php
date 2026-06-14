<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Enrollment_Ajax {

    const NONCE_ACTION       = 'mdcat_enrollment_nonce';
    const NONCE_FIELD        = 'nonce';
    const ADMIN_NONCE_ACTION = 'mdcat_enrollment_admin_nonce';

    /**
     * Register AJAX actions for the enrollment module.
     *
     * The submit action uses both wp_ajax_ and wp_ajax_nopriv_
     * because guests (not logged in) need to submit enrollment forms.
     * Admin actions use wp_ajax_ only.
     */
    public static function init() {

        // Public: enrollment form submission (guests + logged-in users).
        add_action('wp_ajax_mdcat_enrollment_submit', [__CLASS__, 'handle_submit']);
        add_action('wp_ajax_nopriv_mdcat_enrollment_submit', [__CLASS__, 'handle_submit']);

        // Admin: approve, reject, delete, get requests.
        add_action('wp_ajax_mdcat_enrollment_approve', [__CLASS__, 'handle_approve']);
        add_action('wp_ajax_mdcat_enrollment_reject', [__CLASS__, 'handle_reject']);
        add_action('wp_ajax_mdcat_enrollment_delete', [__CLASS__, 'handle_delete']);
        add_action('wp_ajax_mdcat_enrollment_get_requests', [__CLASS__, 'handle_get_requests']);
    }

    /**
     * Handle enrollment form submission from a guest student.
     *
     * Validates all fields, handles the screenshot upload, and
     * creates the enrollment request via the service layer.
     */
    public static function handle_submit() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed. Please refresh and try again.', 'mdcat-platform'), 403);
        }

        // Rate limiting: max 3 submissions per IP per hour.
        $ip       = self::get_client_ip();
        $rate_key = 'mdcat_enrollment_rate_' . md5($ip);
        $attempts = absint(get_transient($rate_key));

        if ($attempts >= 3) {
            self::send_error('rate_limited', __('Too many requests. Please try again later.', 'mdcat-platform'), 429);
        }

        // Validate required fields.
        $full_name = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '';
        $email     = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone     = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $city      = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';

        $errors = [];

        if (empty($full_name)) {
            $errors[] = __('Full name is required.', 'mdcat-platform');
        }

        if (empty($email) || !is_email($email)) {
            $errors[] = __('A valid email address is required.', 'mdcat-platform');
        }

        if (empty($phone)) {
            $errors[] = __('Phone number is required.', 'mdcat-platform');
        }

        if (empty($city)) {
            $errors[] = __('City is required.', 'mdcat-platform');
        }

        // Check if email already has a WordPress account.
        if (!empty($email) && email_exists($email)) {
            $errors[] = __('An account with this email already exists. Please log in.', 'mdcat-platform');
        }

        // Validate screenshot upload.
        if (empty($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = __('Payment screenshot is required.', 'mdcat-platform');
        }

        if (!empty($errors)) {
            wp_send_json_error([
                'code'   => 'validation_failed',
                'errors' => $errors,
            ], 400);
        }

        // Validate file type and size.
        $file         = $_FILES['payment_screenshot'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size      = 5 * 1024 * 1024; // 5MB.

        $file_type = wp_check_filetype($file['name']);

        if (!$file_type['type'] || !in_array($file_type['type'], $allowed_types, true)) {
            self::send_error('invalid_file_type', __('Only image files (JPG, PNG, WebP) are accepted.', 'mdcat-platform'));
        }

        if ($file['size'] > $max_size) {
            self::send_error('file_too_large', __('Screenshot exceeds the maximum size of 5MB.', 'mdcat-platform'));
        }

        // Handle file upload to the enrollment uploads directory.
        $upload_result = self::handle_screenshot_upload($file);

        if (is_wp_error($upload_result)) {
            self::send_error(
                $upload_result->get_error_code(),
                $upload_result->get_error_message()
            );
        }

        // Create the enrollment request.
        $request_id = MDCAT_Platform_Enrollment_Service::create_request([
            'full_name'       => $full_name,
            'email'           => $email,
            'phone'           => $phone,
            'city'            => $city,
            'screenshot_url'  => $upload_result['url'],
            'screenshot_path' => $upload_result['file'],
        ]);

        if (is_wp_error($request_id)) {

            // Clean up uploaded file on failure.
            if (file_exists($upload_result['file'])) {
                @unlink($upload_result['file']);
            }

            self::send_error(
                $request_id->get_error_code(),
                $request_id->get_error_message()
            );
        }

        // Increment rate limiter.
        set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

        wp_send_json_success([
            'message' => __('Your enrollment request has been submitted successfully. You will receive an email with your login credentials once approved.', 'mdcat-platform'),
        ]);
    }

    /**
     * Handle admin approval of an enrollment request.
     */
    public static function handle_approve() {

        self::verify_admin_request();

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;

        if (!$request_id) {
            self::send_error('missing_id', __('Request ID is required.', 'mdcat-platform'));
        }

        $admin_id = get_current_user_id();
        $result   = MDCAT_Platform_Enrollment_Service::approve_request($request_id, $admin_id);

        if (is_wp_error($result)) {
            self::send_error($result->get_error_code(), $result->get_error_message());
        }

        $response = [
            'message'    => __('Enrollment approved. Student account has been created.', 'mdcat-platform'),
            'wp_user_id' => $result['wp_user_id'],
            'email'      => $result['email'],
            'email_sent' => $result['email_sent'],
        ];

        // If email failed, include password so admin can share manually.
        if (!$result['email_sent']) {
            $response['message']  = __('User created but email delivery failed. Please share the credentials manually.', 'mdcat-platform');
            $response['password'] = $result['password'];
        }

        wp_send_json_success($response);
    }

    /**
     * Handle admin rejection of an enrollment request.
     */
    public static function handle_reject() {

        self::verify_admin_request();

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $reason     = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

        if (!$request_id) {
            self::send_error('missing_id', __('Request ID is required.', 'mdcat-platform'));
        }

        $admin_id = get_current_user_id();
        $result   = MDCAT_Platform_Enrollment_Service::reject_request($request_id, $admin_id, $reason);

        if (is_wp_error($result)) {
            self::send_error($result->get_error_code(), $result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Enrollment request has been rejected.', 'mdcat-platform'),
        ]);
    }

    /**
     * Handle admin deletion of an enrollment request.
     */
    public static function handle_delete() {

        self::verify_admin_request();

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;

        if (!$request_id) {
            self::send_error('missing_id', __('Request ID is required.', 'mdcat-platform'));
        }

        $result = MDCAT_Platform_Enrollment_Service::delete_request($request_id);

        if (is_wp_error($result)) {
            self::send_error($result->get_error_code(), $result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Enrollment request has been deleted.', 'mdcat-platform'),
        ]);
    }

    /**
     * Handle admin request to get enrollment requests list.
     */
    public static function handle_get_requests() {

        self::verify_admin_request();

        $status   = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'all';
        $requests = MDCAT_Platform_Enrollment_Service::get_requests($status);
        $counts   = MDCAT_Platform_Enrollment_Service::get_counts();

        // Format requests for the response.
        $formatted = [];

        foreach ($requests as $request) {
            $formatted[] = [
                'id'             => absint($request->id),
                'full_name'      => esc_html($request->full_name),
                'email'          => esc_html($request->email),
                'phone'          => esc_html($request->phone),
                'city'           => esc_html($request->city),
                'screenshot_url' => esc_url($request->screenshot_url),
                'status'         => esc_html($request->status),
                'admin_notes'    => esc_html($request->admin_notes),
                'reviewed_by'    => absint($request->reviewed_by),
                'wp_user_id'     => absint($request->wp_user_id),
                'created_at'     => esc_html($request->created_at),
                'reviewed_at'    => esc_html($request->reviewed_at),
            ];
        }

        wp_send_json_success([
            'requests' => $formatted,
            'counts'   => $counts,
        ]);
    }

    /**
     * Handle screenshot file upload.
     *
     * Uploads to wp-content/uploads/mdcat-enrollments/ directory
     * using wp_handle_upload(). Returns the file URL and path.
     *
     * @param array $file $_FILES array element.
     * @return array|WP_Error Upload result with 'url' and 'file' keys.
     */
    private static function handle_screenshot_upload( $file ) {

        // Use a custom upload directory.
        add_filter('upload_dir', [__CLASS__, 'set_upload_dir']);

        $upload_overrides = [
            'test_form' => false,
            'mimes'     => [
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'webp'     => 'image/webp',
            ],
        ];

        $result = wp_handle_upload($file, $upload_overrides);

        // Remove the filter immediately.
        remove_filter('upload_dir', [__CLASS__, 'set_upload_dir']);

        if (isset($result['error'])) {
            return new WP_Error('upload_failed', $result['error']);
        }

        return $result;
    }

    /**
     * Override the WordPress upload directory for enrollment screenshots.
     *
     * Routes uploads to wp-content/uploads/mdcat-enrollments/YYYY/MM/
     * instead of the default uploads directory.
     *
     * @param array $dirs WordPress upload directory info.
     * @return array Modified directory info.
     */
    public static function set_upload_dir( $dirs ) {

        $subdir = '/mdcat-enrollments' . $dirs['subdir'];

        $dirs['path']   = $dirs['basedir'] . $subdir;
        $dirs['url']    = $dirs['baseurl'] . $subdir;
        $dirs['subdir'] = $subdir;

        return $dirs;
    }

    /**
     * Verify admin AJAX request: nonce + manage_options capability.
     *
     * @return void Dies on failure.
     */
    private static function verify_admin_request() {

        if (!check_ajax_referer(self::ADMIN_NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!current_user_can('manage_options')) {
            self::send_error('unauthorized', __('You do not have permission to manage enrollments.', 'mdcat-platform'), 403);
        }
    }

    /**
     * Send a normalized JSON error response.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     */
    private static function send_error( $code, $message, $status = 400 ) {

        wp_send_json_error(
            [
                'code'    => sanitize_key($code),
                'message' => $message,
            ],
            absint($status)
        );
    }

    /**
     * Get the client IP address for rate limiting.
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
