<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Subjects {

    /**
     * Load the subject module admin handlers.
     */
    public static function init() {

        MDCAT_Platform_Subjects_Handler::init();
    }

    /**
     * Render the Subjects admin screen.
     */
    public static function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mdcat-platform'));
        }

        $action     = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        $subject_id = isset($_GET['subject_id']) ? absint(wp_unslash($_GET['subject_id'])) : 0;

        if ('add' === $action || 'edit' === $action) {
            self::render_form($subject_id);
            return;
        }

        self::render_list();
    }

    /**
     * Load the subjects listing view.
     */
    private static function render_list() {

        $subjects = MDCAT_Platform_Subjects_Handler::get_subjects();

        require MDCAT_PLATFORM_PATH . 'modules/subjects/views/subjects-page.php';
    }

    /**
     * Load the add/edit form view.
     *
     * @param int $subject_id Subject ID for edit mode.
     */
    private static function render_form( $subject_id = 0 ) {

        $subject = null;

        if ($subject_id) {
            $subject = MDCAT_Platform_Subjects_Handler::get_subject($subject_id);
        }

        require MDCAT_PLATFORM_PATH . 'modules/subjects/views/subject-form.php';
    }
}
