<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Attempts_Handler {

    /**
     * Bootstrap future attempt backend hooks.
     *
     * This module currently exposes the database layer only.
     */
    public static function init() {

        // Future quiz runtime, analytics, and leaderboard hooks will be registered here.
    }

    /**
     * Get the custom attempts table name.
     *
     * @return string
     */
    public static function get_attempts_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_attempts';
    }

    /**
     * Get the custom attempt answers table name.
     *
     * @return string
     */
    public static function get_attempt_answers_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_attempt_answers';
    }

    /**
     * Allowed attempt lifecycle statuses.
     *
     * @return array
     */
    public static function get_allowed_statuses() {

        return [
            'in_progress' => __('In Progress', 'mdcat-platform'),
            'completed'   => __('Completed', 'mdcat-platform'),
            'abandoned'   => __('Abandoned', 'mdcat-platform'),
        ];
    }

    /**
     * Allowed selected option values for attempt answers.
     *
     * @return array
     */
    public static function get_allowed_selected_options() {

        return [
            'a' => __('A', 'mdcat-platform'),
            'b' => __('B', 'mdcat-platform'),
            'c' => __('C', 'mdcat-platform'),
            'd' => __('D', 'mdcat-platform'),
        ];
    }

    /**
     * Sanitize and normalize an attempt status for future writes.
     *
     * @param string $status Attempt status.
     * @return string
     */
    public static function sanitize_status( $status ) {

        $status = sanitize_key($status);

        if (!array_key_exists($status, self::get_allowed_statuses())) {
            return 'in_progress';
        }

        return $status;
    }

    /**
     * Sanitize and normalize a selected option for future answer writes.
     *
     * @param string $selected_option Selected option.
     * @return string
     */
    public static function sanitize_selected_option( $selected_option ) {

        $selected_option = sanitize_key($selected_option);

        if (!array_key_exists($selected_option, self::get_allowed_selected_options())) {
            return '';
        }

        return $selected_option;
    }
}
