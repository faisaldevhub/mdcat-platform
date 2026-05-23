<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Collections_Handler {

    /**
     * Register admin form actions for collection CRUD.
     */
    public static function init() {

        add_action('admin_post_mdcat_save_collection', [__CLASS__, 'save_collection']);
        add_action('admin_post_mdcat_delete_collection', [__CLASS__, 'delete_collection']);
    }

    /**
     * Get the custom collections table name.
     *
     * @return string
     */
    public static function get_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_collections';
    }

    /**
     * Get the custom chapters table name.
     *
     * @return string
     */
    public static function get_chapters_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_chapters';
    }

    /**
     * Get the custom subjects table name.
     *
     * @return string
     */
    public static function get_subjects_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_subjects';
    }

    /**
     * Allowed collection type options.
     *
     * @return array
     */
    public static function get_allowed_types() {

        return [
            'exercise'      => __('Exercise', 'mdcat-platform'),
            'practice_test' => __('Practice Test', 'mdcat-platform'),
            'past_paper'    => __('Past Paper', 'mdcat-platform'),
            'book_lines'    => __('Book Lines', 'mdcat-platform'),
            'mini_test'     => __('Mini Test', 'mdcat-platform'),
        ];
    }

    /**
     * Allowed collection status options.
     *
     * @return array
     */
    public static function get_allowed_statuses() {

        return [
            'active'   => __('Active', 'mdcat-platform'),
            'inactive' => __('Inactive', 'mdcat-platform'),
        ];
    }

    /**
     * Fetch collections with related chapter and subject names.
     *
     * @return array
     */
    public static function get_collections() {

        global $wpdb;

        $collections_table = self::get_table_name();
        $chapters_table    = self::get_chapters_table_name();
        $subjects_table    = self::get_subjects_table_name();

        return $wpdb->get_results(
            "SELECT collections.id, collections.chapter_id, collections.title, collections.type, collections.description,
                collections.sort_order, collections.status, collections.created_at,
                chapters.name AS chapter_name, subjects.name AS subject_name
            FROM {$collections_table} AS collections
            LEFT JOIN {$chapters_table} AS chapters ON collections.chapter_id = chapters.id
            LEFT JOIN {$subjects_table} AS subjects ON chapters.subject_id = subjects.id
            ORDER BY collections.sort_order ASC, collections.id DESC"
        );
    }

    /**
     * Fetch one collection by ID.
     *
     * @param int $collection_id Collection ID.
     * @return object|null
     */
    public static function get_collection( $collection_id ) {

        global $wpdb;

        $table_name    = self::get_table_name();
        $collection_id = absint($collection_id);

        if (!$collection_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, chapter_id, title, type, description, sort_order, status, created_at FROM {$table_name} WHERE id = %d",
                $collection_id
            )
        );
    }

    /**
     * Fetch chapters with subject names for the nested relational dropdown.
     *
     * @return array
     */
    public static function get_chapters() {

        global $wpdb;

        $chapters_table = self::get_chapters_table_name();
        $subjects_table = self::get_subjects_table_name();

        return $wpdb->get_results(
            "SELECT chapters.id, chapters.name AS chapter_name, subjects.name AS subject_name
            FROM {$chapters_table} AS chapters
            LEFT JOIN {$subjects_table} AS subjects ON chapters.subject_id = subjects.id
            ORDER BY subjects.name ASC, chapters.name ASC"
        );
    }

    /**
     * Create or update a collection from the admin form.
     */
    public static function save_collection() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage collections.', 'mdcat-platform'));
        }

        check_admin_referer('mdcat_save_collection', 'mdcat_collection_nonce');

        $collection_id = isset($_POST['collection_id']) ? absint(wp_unslash($_POST['collection_id'])) : 0;
        $chapter_id    = isset($_POST['chapter_id']) ? absint(wp_unslash($_POST['chapter_id'])) : 0;
        $title         = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $type          = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $description   = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $sort_order    = isset($_POST['sort_order']) ? absint(wp_unslash($_POST['sort_order'])) : 0;
        $status        = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';

        if (!$chapter_id || !self::chapter_exists($chapter_id)) {
            self::redirect_with_message('invalid_chapter');
        }

        if ('' === $title) {
            self::redirect_with_message('missing_title');
        }

        if (!array_key_exists($type, self::get_allowed_types())) {
            self::redirect_with_message('invalid_type');
        }

        if (!array_key_exists($status, self::get_allowed_statuses())) {
            self::redirect_with_message('invalid_status');
        }

        if ($collection_id) {
            self::update_collection($collection_id, $chapter_id, $title, $type, $description, $sort_order, $status);
            self::redirect_with_message('updated');
        }

        self::create_collection($chapter_id, $title, $type, $description, $sort_order, $status);
        self::redirect_with_message('created');
    }

    /**
     * Delete a collection after nonce and capability checks.
     */
    public static function delete_collection() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete collections.', 'mdcat-platform'));
        }

        $collection_id = isset($_GET['collection_id']) ? absint(wp_unslash($_GET['collection_id'])) : 0;

        check_admin_referer('mdcat_delete_collection_' . $collection_id);

        if ($collection_id) {
            global $wpdb;

            $wpdb->delete(
                self::get_table_name(),
                ['id' => $collection_id],
                ['%d']
            );
        }

        self::redirect_with_message('deleted');
    }

    /**
     * Validate that a selected chapter exists before saving a collection.
     *
     * @param int $chapter_id Chapter ID.
     * @return bool
     */
    private static function chapter_exists( $chapter_id ) {

        global $wpdb;

        $chapters_table = self::get_chapters_table_name();
        $chapter_id     = absint($chapter_id);

        if (!$chapter_id) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id) FROM {$chapters_table} WHERE id = %d",
                $chapter_id
            )
        );
    }

    /**
     * Insert a collection row.
     *
     * @param int    $chapter_id  Chapter ID.
     * @param string $title       Collection title.
     * @param string $type        Collection type.
     * @param string $description Collection description.
     * @param int    $sort_order  Sort order.
     * @param string $status      Collection status.
     */
    private static function create_collection( $chapter_id, $title, $type, $description, $sort_order, $status ) {

        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            [
                'chapter_id'   => $chapter_id,
                'title'        => $title,
                'type'         => $type,
                'description'  => $description,
                'sort_order'   => $sort_order,
                'status'       => $status,
                'created_at'   => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );
    }

    /**
     * Update a collection row.
     *
     * @param int    $collection_id Collection ID.
     * @param int    $chapter_id    Chapter ID.
     * @param string $title         Collection title.
     * @param string $type          Collection type.
     * @param string $description   Collection description.
     * @param int    $sort_order    Sort order.
     * @param string $status        Collection status.
     */
    private static function update_collection( $collection_id, $chapter_id, $title, $type, $description, $sort_order, $status ) {

        global $wpdb;

        $wpdb->update(
            self::get_table_name(),
            [
                'chapter_id'  => $chapter_id,
                'title'       => $title,
                'type'        => $type,
                'description' => $description,
                'sort_order'  => $sort_order,
                'status'      => $status,
            ],
            ['id' => $collection_id],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ],
            ['%d']
        );
    }

    /**
     * Redirect back to the collections page with a status message.
     *
     * @param string $message Message key.
     */
    private static function redirect_with_message( $message ) {

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'          => 'mdcat-collections',
                    'mdcat_message' => sanitize_key($message),
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
