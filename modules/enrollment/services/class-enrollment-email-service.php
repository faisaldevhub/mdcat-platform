<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Enrollment_Email_Service {

    /**
     * Send approval email with login credentials.
     *
     * Uses wp_mail() which respects any SMTP plugin the site
     * has installed. The email contains the student's username,
     * generated password, and a direct link to the login page.
     *
     * @param string $email     Recipient email address.
     * @param string $full_name Student's full name.
     * @param string $password  Generated password.
     * @return bool True if email was accepted for delivery, false otherwise.
     */
    public static function send_approval_email( $email, $full_name, $password ) {

        $login_url = wp_login_url();
        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %s: site name */
            __('Your %s Account is Ready', 'mdcat-platform'),
            $site_name
        );

        $message = self::build_approval_message($full_name, $email, $password, $login_url, $site_name);

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Build the approval email body text.
     *
     * @param string $full_name  Student's full name.
     * @param string $email      Student's email / username.
     * @param string $password   Generated password.
     * @param string $login_url  WordPress login URL.
     * @param string $site_name  Site name.
     * @return string Email body.
     */
    private static function build_approval_message( $full_name, $email, $password, $login_url, $site_name ) {

        $message  = sprintf(
            /* translators: %s: student's first name */
            __('Welcome to %s!', 'mdcat-platform'),
            $site_name
        ) . "\n\n";

        $message .= sprintf(
            /* translators: %s: student's full name */
            __('Dear %s,', 'mdcat-platform'),
            $full_name
        ) . "\n\n";

        $message .= __('Your enrollment has been approved. Here are your login credentials:', 'mdcat-platform') . "\n\n";

        $message .= sprintf(
            __('Username: %s', 'mdcat-platform'),
            $email
        ) . "\n";

        $message .= sprintf(
            __('Password: %s', 'mdcat-platform'),
            $password
        ) . "\n\n";

        $message .= sprintf(
            __('Login here: %s', 'mdcat-platform'),
            $login_url
        ) . "\n\n";

        $message .= __('Please change your password after your first login.', 'mdcat-platform') . "\n\n";

        $message .= __('If you did not request this account, please ignore this email.', 'mdcat-platform') . "\n\n";

        $message .= sprintf(
            '— %s',
            $site_name
        );

        return $message;
    }
}
