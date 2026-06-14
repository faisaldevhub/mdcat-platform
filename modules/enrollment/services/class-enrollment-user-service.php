<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Enrollment_User_Service {

    /**
     * Default role assigned to students created via enrollment approval.
     */
    const STUDENT_ROLE = 'subscriber';

    /**
     * Create a WordPress user account for an approved student.
     *
     * Uses the student's email as the username and generates a
     * secure random password. The user is assigned the subscriber
     * role which is the standard WordPress role for non-admin users.
     *
     * @param string $email     Student's email address (used as username).
     * @param string $full_name Student's full name (used as display name).
     * @return array|WP_Error   Array with user_id and password on success.
     */
    public static function create_student( $email, $full_name ) {

        $email     = sanitize_email($email);
        $full_name = sanitize_text_field($full_name);

        if (!is_email($email)) {
            return new WP_Error(
                'invalid_email',
                __('Invalid email address.', 'mdcat-platform')
            );
        }

        // Check if username or email already taken.
        if (username_exists($email)) {
            return new WP_Error(
                'username_exists',
                __('A user with this email already exists.', 'mdcat-platform')
            );
        }

        if (email_exists($email)) {
            return new WP_Error(
                'email_exists',
                __('A user with this email already exists.', 'mdcat-platform')
            );
        }

        // Generate a secure random password.
        $password = wp_generate_password(12, true, false);

        // Parse name into first and last.
        $name_parts = self::parse_name($full_name);

        $user_id = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $full_name,
            'first_name'   => $name_parts['first'],
            'last_name'    => $name_parts['last'],
            'role'         => self::STUDENT_ROLE,
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        return [
            'user_id'  => absint($user_id),
            'password' => $password,
        ];
    }

    /**
     * Parse a full name into first and last name components.
     *
     * Splits on the first space. "Ahmed Khan" becomes
     * first="Ahmed", last="Khan". Single-word names have
     * an empty last name.
     *
     * @param string $full_name Full name string.
     * @return array Associative array with 'first' and 'last' keys.
     */
    private static function parse_name( $full_name ) {

        $parts = explode(' ', trim($full_name), 2);

        return [
            'first' => $parts[0],
            'last'  => isset($parts[1]) ? $parts[1] : '',
        ];
    }
}
