<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Collections {

    /**
     * Load the collection module admin handlers.
     */
    public static function init() {

        MDCAT_Platform_Collections_Handler::init();
    }

    /**
     * Render the Collections admin screen.
     */
    public static function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mdcat-platform'));
        }

        $action        = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        $collection_id = isset($_GET['collection_id']) ? absint(wp_unslash($_GET['collection_id'])) : 0;

        if ('add' === $action || 'edit' === $action) {
            self::render_form($collection_id);
            return;
        }

        self::render_list();
    }

    /**
     * Load the collections listing view.
     */
    private static function render_list() {

        $collections = MDCAT_Platform_Collections_Handler::get_collections();
        $types       = MDCAT_Platform_Collections_Handler::get_allowed_types();
        $statuses    = MDCAT_Platform_Collections_Handler::get_allowed_statuses();

        require MDCAT_PLATFORM_PATH . 'modules/collections/views/collections-page.php';
    }

    /**
     * Load the add/edit form view with chapters for the dropdown.
     *
     * @param int $collection_id Collection ID for edit mode.
     */
    private static function render_form( $collection_id = 0 ) {

        $collection = null;
        $chapters   = MDCAT_Platform_Collections_Handler::get_chapters();
        $types      = MDCAT_Platform_Collections_Handler::get_allowed_types();
        $statuses   = MDCAT_Platform_Collections_Handler::get_allowed_statuses();

        if ($collection_id) {
            $collection = MDCAT_Platform_Collections_Handler::get_collection($collection_id);
        }

        require MDCAT_PLATFORM_PATH . 'modules/collections/views/collection-form.php';
    }
}
