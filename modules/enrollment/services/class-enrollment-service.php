<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Enrollment_Service {

    /**
     * Get the enrollment requests table name.
     *
     * @return string
     */
    public static function get_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_enrollment_requests';
    }

    /**
     * Create a new enrollment request.
     *
     * Stores the student's details and payment screenshot. If a
     * rejected request with the same email already exists, it is
     * replaced with the new submission (re-application support).
     *
     * @param array $data {
     *     @type string $full_name       Student's full name.
     *     @type string $email           Student's email address.
     *     @type string $phone           Student's phone number.
     *     @type string $city            Student's city.
     *     @type string $screenshot_url  URL to the uploaded screenshot.
     *     @type string $screenshot_path Server file path to the screenshot.
     * }
     * @return int|WP_Error Request ID on success, WP_Error on failure.
     */
    public static function create_request( $data ) {

        global $wpdb;

        $table = self::get_table_name();

        // Check for existing request with this email.
        $existing = self::get_request_by_email($data['email']);

        if ($existing) {

            // If rejected, allow re-submission by updating the existing record.
            if ('rejected' === $existing->status) {

                // Delete old screenshot file if it exists.
                if (!empty($existing->screenshot_path) && file_exists($existing->screenshot_path)) {
                    @unlink($existing->screenshot_path);
                }

                $updated = $wpdb->update(
                    $table,
                    [
                        'full_name'       => sanitize_text_field($data['full_name']),
                        'phone'           => sanitize_text_field($data['phone']),
                        'city'            => sanitize_text_field($data['city']),
                        'screenshot_url'  => esc_url_raw($data['screenshot_url']),
                        'screenshot_path' => sanitize_text_field($data['screenshot_path']),
                        'status'          => 'pending',
                        'admin_notes'     => null,
                        'reviewed_by'     => null,
                        'reviewed_at'     => null,
                        'wp_user_id'      => null,
                        'created_at'      => current_time('mysql'),
                    ],
                    ['id' => absint($existing->id)],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                    ['%d']
                );

                if (false === $updated) {
                    return new WP_Error(
                        'update_failed',
                        __('Could not update enrollment request. Please try again.', 'mdcat-platform')
                    );
                }

                return absint($existing->id);
            }

            // If pending, tell them to wait.
            if ('pending' === $existing->status) {
                return new WP_Error(
                    'duplicate_pending',
                    __('An enrollment request with this email is already pending review.', 'mdcat-platform')
                );
            }

            // If approved, they already have an account.
            if ('approved' === $existing->status) {
                return new WP_Error(
                    'already_approved',
                    __('An account with this email has already been approved. Please log in.', 'mdcat-platform')
                );
            }
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'full_name'       => sanitize_text_field($data['full_name']),
                'email'           => sanitize_email($data['email']),
                'phone'           => sanitize_text_field($data['phone']),
                'city'            => sanitize_text_field($data['city']),
                'screenshot_url'  => esc_url_raw($data['screenshot_url']),
                'screenshot_path' => sanitize_text_field($data['screenshot_path']),
                'status'          => 'pending',
                'created_at'      => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return new WP_Error(
                'insert_failed',
                __('Could not save enrollment request. Please try again.', 'mdcat-platform')
            );
        }

        return absint($wpdb->insert_id);
    }

    /**
     * Get a single enrollment request by ID.
     *
     * @param int $request_id Request ID.
     * @return object|null Request object or null.
     */
    public static function get_request( $request_id ) {

        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                absint($request_id)
            )
        );
    }

    /**
     * Get a single enrollment request by email.
     *
     * @param string $email Email address.
     * @return object|null Request object or null.
     */
    public static function get_request_by_email( $email ) {

        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE email = %s",
                sanitize_email($email)
            )
        );
    }

    /**
     * Get enrollment requests filtered by status.
     *
     * @param string $status Optional. Filter by status: 'pending', 'approved', 'rejected', or 'all'.
     * @return array Array of request objects.
     */
    public static function get_requests( $status = 'all' ) {

        global $wpdb;

        $table   = self::get_table_name();
        $allowed = ['pending', 'approved', 'rejected'];

        if (in_array($status, $allowed, true)) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC",
                    $status
                )
            );
        }

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC"
        );
    }

    /**
     * Get the count of requests by status.
     *
     * @return array Associative array with counts keyed by status.
     */
    public static function get_counts() {

        global $wpdb;

        $table   = self::get_table_name();
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
        );

        $counts = [
            'pending'  => 0,
            'approved' => 0,
            'rejected' => 0,
            'total'    => 0,
        ];

        foreach ($results as $row) {
            $counts[$row->status] = absint($row->count);
            $counts['total']     += absint($row->count);
        }

        return $counts;
    }

    /**
     * Approve an enrollment request.
     *
     * Creates a WordPress user account and updates the request status.
     * This method orchestrates the full approval workflow by delegating
     * to the user and email services.
     *
     * @param int $request_id  Enrollment request ID.
     * @param int $admin_id    Admin user ID performing the approval.
     * @return array|WP_Error  Success data or WP_Error on failure.
     */
    public static function approve_request( $request_id, $admin_id ) {

        global $wpdb;

        $request = self::get_request($request_id);

        if (!$request) {
            return new WP_Error(
                'not_found',
                __('Enrollment request not found.', 'mdcat-platform')
            );
        }

        if ('pending' !== $request->status) {
            return new WP_Error(
                'already_reviewed',
                sprintf(
                    /* translators: %s: current status */
                    __('This request has already been %s.', 'mdcat-platform'),
                    $request->status
                )
            );
        }

        // Check if email already exists as a WordPress user.
        if (email_exists($request->email)) {
            return new WP_Error(
                'email_exists',
                __('A WordPress account with this email already exists.', 'mdcat-platform')
            );
        }

        // Create the WordPress user account.
        $user_result = MDCAT_Platform_Enrollment_User_Service::create_student(
            $request->email,
            $request->full_name
        );

        if (is_wp_error($user_result)) {
            return $user_result;
        }

        $wp_user_id = $user_result['user_id'];
        $password   = $user_result['password'];

        // Update the enrollment request status.
        $table = self::get_table_name();

        $wpdb->update(
            $table,
            [
                'status'      => 'approved',
                'wp_user_id'  => absint($wp_user_id),
                'reviewed_by' => absint($admin_id),
                'reviewed_at' => current_time('mysql'),
            ],
            ['id' => absint($request_id)],
            ['%s', '%d', '%d', '%s'],
            ['%d']
        );

        // Send credentials email.
        $email_sent = MDCAT_Platform_Enrollment_Email_Service::send_approval_email(
            $request->email,
            $request->full_name,
            $password
        );

        return [
            'request_id' => absint($request_id),
            'wp_user_id' => absint($wp_user_id),
            'email'      => $request->email,
            'email_sent' => $email_sent,
            'password'   => $password,
        ];
    }

    /**
     * Reject an enrollment request.
     *
     * Marks the request as rejected with an optional reason.
     * No user account is created. The student can re-submit later.
     *
     * @param int    $request_id  Enrollment request ID.
     * @param int    $admin_id    Admin user ID performing the rejection.
     * @param string $reason      Optional rejection reason.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public static function reject_request( $request_id, $admin_id, $reason = '' ) {

        global $wpdb;

        $request = self::get_request($request_id);

        if (!$request) {
            return new WP_Error(
                'not_found',
                __('Enrollment request not found.', 'mdcat-platform')
            );
        }

        if ('pending' !== $request->status) {
            return new WP_Error(
                'already_reviewed',
                sprintf(
                    /* translators: %s: current status */
                    __('This request has already been %s.', 'mdcat-platform'),
                    $request->status
                )
            );
        }

        $table = self::get_table_name();

        $updated = $wpdb->update(
            $table,
            [
                'status'      => 'rejected',
                'admin_notes' => sanitize_textarea_field($reason),
                'reviewed_by' => absint($admin_id),
                'reviewed_at' => current_time('mysql'),
            ],
            ['id' => absint($request_id)],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        if (false === $updated) {
            return new WP_Error(
                'update_failed',
                __('Could not reject enrollment request. Please try again.', 'mdcat-platform')
            );
        }

        return true;
    }

    /**
     * Delete an enrollment request and its screenshot file.
     *
     * @param int $request_id Request ID.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public static function delete_request( $request_id ) {

        global $wpdb;

        $request = self::get_request($request_id);

        if (!$request) {
            return new WP_Error(
                'not_found',
                __('Enrollment request not found.', 'mdcat-platform')
            );
        }

        // Delete screenshot file.
        if (!empty($request->screenshot_path) && file_exists($request->screenshot_path)) {
            @unlink($request->screenshot_path);
        }

        $table   = self::get_table_name();
        $deleted = $wpdb->delete($table, ['id' => absint($request_id)], ['%d']);

        if (!$deleted) {
            return new WP_Error(
                'delete_failed',
                __('Could not delete enrollment request.', 'mdcat-platform')
            );
        }

        return true;
    }
}
