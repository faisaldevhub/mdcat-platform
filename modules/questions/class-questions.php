<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Questions {

    /**
     * Load the question module admin handlers.
     */
    public static function init() {

        MDCAT_Platform_Questions_Handler::init();
    }

    /**
     * Render the Questions admin screen.
     */
    public static function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mdcat-platform'));
        }

        $action      = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        $question_id = isset($_GET['question_id']) ? absint(wp_unslash($_GET['question_id'])) : 0;

        if ('add' === $action || 'edit' === $action) {
            self::render_form($question_id);
            return;
        }

        self::render_list();
    }

    /**
     * Load the questions listing view.
     */
    private static function render_list() {

        $questions       = MDCAT_Platform_Questions_Handler::get_questions();
        $correct_options = MDCAT_Platform_Questions_Handler::get_allowed_correct_options();
        $difficulties    = MDCAT_Platform_Questions_Handler::get_allowed_difficulties();
        $statuses        = MDCAT_Platform_Questions_Handler::get_allowed_statuses();

        require MDCAT_PLATFORM_PATH . 'modules/questions/views/questions-page.php';
    }

    /**
     * Load the add/edit form view with collections for the dropdown.
     *
     * @param int $question_id Question ID for edit mode.
     */
    private static function render_form( $question_id = 0 ) {

        $question        = null;
        $collections     = MDCAT_Platform_Questions_Handler::get_collections();
        $correct_options = MDCAT_Platform_Questions_Handler::get_allowed_correct_options();
        $difficulties    = MDCAT_Platform_Questions_Handler::get_allowed_difficulties();
        $statuses        = MDCAT_Platform_Questions_Handler::get_allowed_statuses();

        if ($question_id) {
            $question = MDCAT_Platform_Questions_Handler::get_question($question_id);
        }

        require MDCAT_PLATFORM_PATH . 'modules/questions/views/question-form.php';
    }
}
