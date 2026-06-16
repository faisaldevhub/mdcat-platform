<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Notification_Email_Service {

    /**
     * Send a badge unlock notification email.
     *
     * @param string $email     Recipient email.
     * @param string $full_name Student's full name.
     * @param array  $badge     Badge data: name, description, icon.
     * @return bool
     */
    public static function send_badge_email( $email, $full_name, $badge ) {

        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %1$s: badge name, %2$s: site name */
            __('🏅 You earned a new badge on %s!', 'mdcat-platform'),
            $site_name
        );

        $message  = sprintf(__('Congratulations %s!', 'mdcat-platform'), $full_name) . "\n\n";
        $message .= sprintf(__('You just earned the "%s" badge!', 'mdcat-platform'), $badge['name']) . "\n\n";
        $message .= $badge['description'] . "\n\n";
        $message .= __('Keep up the great work!', 'mdcat-platform') . "\n\n";
        $message .= sprintf('— %s', $site_name);

        return self::send($email, $subject, $message);
    }

    /**
     * Send an achievement unlock notification email.
     *
     * @param string $email       Recipient email.
     * @param string $full_name   Student's full name.
     * @param array  $achievement Achievement data: name, description, icon.
     * @return bool
     */
    public static function send_achievement_email( $email, $full_name, $achievement ) {

        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %s: site name */
            __('🏆 Achievement unlocked on %s!', 'mdcat-platform'),
            $site_name
        );

        $message  = sprintf(__('Well done %s!', 'mdcat-platform'), $full_name) . "\n\n";
        $message .= sprintf(__('You unlocked the "%s" achievement!', 'mdcat-platform'), $achievement['name']) . "\n\n";
        $message .= $achievement['description'] . "\n\n";
        $message .= __('Your dedication is paying off.', 'mdcat-platform') . "\n\n";
        $message .= sprintf('— %s', $site_name);

        return self::send($email, $subject, $message);
    }

    /**
     * Send an enrollment rejected notification email.
     *
     * @param string $email     Recipient email.
     * @param string $full_name Student's full name.
     * @param string $reason    Optional rejection reason.
     * @return bool
     */
    public static function send_enrollment_rejected_email( $email, $full_name, $reason = '' ) {

        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %s: site name */
            __('Enrollment Update — %s', 'mdcat-platform'),
            $site_name
        );

        $message  = sprintf(__('Dear %s,', 'mdcat-platform'), $full_name) . "\n\n";
        $message .= __('Unfortunately, your enrollment request could not be approved at this time.', 'mdcat-platform') . "\n\n";

        if (!empty($reason)) {
            $message .= sprintf(__('Reason: %s', 'mdcat-platform'), $reason) . "\n\n";
        }

        $message .= __('You are welcome to submit a new enrollment request.', 'mdcat-platform') . "\n\n";
        $message .= sprintf('— %s', $site_name);

        return self::send($email, $subject, $message);
    }

    /**
     * Send a plain-text email via wp_mail().
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param string $message Email body.
     * @return bool
     */
    private static function send( $to, $subject, $message ) {

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($to, $subject, $message, $headers);
    }
}
