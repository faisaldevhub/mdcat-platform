<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Chapters {

    /**
     * Load the chapter module admin handlers.
     */
    public static function init() {

        MDCAT_Platform_Chapters_Handler::init();
    }

    /**
     * Render the Chapters admin screen.
     */
    public static function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mdcat-platform'));
        }

        $action     = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        $chapter_id = isset($_GET['chapter_id']) ? absint(wp_unslash($_GET['chapter_id'])) : 0;

        if ('add' === $action || 'edit' === $action) {
            self::render_form($chapter_id);
            return;
        }

        self::render_list();
    }

    /**
     * Load the chapters listing view.
     */
    private static function render_list() {

        $chapters = MDCAT_Platform_Chapters_Handler::get_chapters();

        require MDCAT_PLATFORM_PATH . 'modules/chapters/views/chapters-page.php';
    }

    /**
     * Load the add/edit form view with subjects for the dropdown.
     *
     * @param int $chapter_id Chapter ID for edit mode.
     */
    private static function render_form( $chapter_id = 0 ) {

        $chapter  = null;
        $subjects = MDCAT_Platform_Chapters_Handler::get_subjects();

        if ($chapter_id) {
            $chapter = MDCAT_Platform_Chapters_Handler::get_chapter($chapter_id);
        }

        require MDCAT_PLATFORM_PATH . 'modules/chapters/views/chapter-form.php';
    }
}
