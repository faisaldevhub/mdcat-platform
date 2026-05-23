<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Chapters_Handler {

    /**
     * Register admin form actions for chapter CRUD.
     */
    public static function init() {

        add_action('admin_post_mdcat_save_chapter', [__CLASS__, 'save_chapter']);
        add_action('admin_post_mdcat_delete_chapter', [__CLASS__, 'delete_chapter']);
    }

    /**
     * Get the custom chapters table name.
     *
     * @return string
     */
    public static function get_table_name() {

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
     * Fetch chapters with their related subject names.
     *
     * @return array
     */
    public static function get_chapters() {

        global $wpdb;

        $chapters_table = self::get_table_name();
        $subjects_table = self::get_subjects_table_name();

        return $wpdb->get_results(
            "SELECT chapters.id, chapters.subject_id, chapters.name, chapters.slug, chapters.created_at, subjects.name AS subject_name
            FROM {$chapters_table} AS chapters
            LEFT JOIN {$subjects_table} AS subjects ON chapters.subject_id = subjects.id
            ORDER BY chapters.id DESC"
        );
    }

    /**
     * Fetch one chapter by ID.
     *
     * @param int $chapter_id Chapter ID.
     * @return object|null
     */
    public static function get_chapter( $chapter_id ) {

        global $wpdb;

        $table_name = self::get_table_name();
        $chapter_id = absint($chapter_id);

        if (!$chapter_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, subject_id, name, slug, created_at FROM {$table_name} WHERE id = %d",
                $chapter_id
            )
        );
    }

    /**
     * Fetch subjects for the relational dropdown.
     *
     * @return array
     */
    public static function get_subjects() {

        global $wpdb;

        $subjects_table = self::get_subjects_table_name();

        return $wpdb->get_results(
            "SELECT id, name FROM {$subjects_table} ORDER BY name ASC"
        );
    }

    /**
     * Create or update a chapter from the admin form.
     */
    public static function save_chapter() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage chapters.', 'mdcat-platform'));
        }

        check_admin_referer('mdcat_save_chapter', 'mdcat_chapter_nonce');

        $chapter_id = isset($_POST['chapter_id']) ? absint(wp_unslash($_POST['chapter_id'])) : 0;
        $subject_id = isset($_POST['subject_id']) ? absint(wp_unslash($_POST['subject_id'])) : 0;
        $name       = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $slug       = sanitize_title($name);

        if (!$subject_id || !self::subject_exists($subject_id)) {
            self::redirect_with_message('invalid_subject');
        }

        if ('' === $name) {
            self::redirect_with_message('missing_name');
        }

        if ($chapter_id) {
            self::update_chapter($chapter_id, $subject_id, $name, $slug);
            self::redirect_with_message('updated');
        }

        self::create_chapter($subject_id, $name, $slug);
        self::redirect_with_message('created');
    }

    /**
     * Delete a chapter after nonce and capability checks.
     */
    public static function delete_chapter() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete chapters.', 'mdcat-platform'));
        }

        $chapter_id = isset($_GET['chapter_id']) ? absint(wp_unslash($_GET['chapter_id'])) : 0;

        check_admin_referer('mdcat_delete_chapter_' . $chapter_id);

        if ($chapter_id) {
            global $wpdb;

            $wpdb->delete(
                self::get_table_name(),
                ['id' => $chapter_id],
                ['%d']
            );
        }

        self::redirect_with_message('deleted');
    }

    /**
     * Validate that a selected subject exists before saving a chapter.
     *
     * @param int $subject_id Subject ID.
     * @return bool
     */
    private static function subject_exists( $subject_id ) {

        global $wpdb;

        $subjects_table = self::get_subjects_table_name();
        $subject_id     = absint($subject_id);

        if (!$subject_id) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id) FROM {$subjects_table} WHERE id = %d",
                $subject_id
            )
        );
    }

    /**
     * Insert a chapter row.
     *
     * @param int    $subject_id Subject ID.
     * @param string $name       Chapter name.
     * @param string $slug       Chapter slug.
     */
    private static function create_chapter( $subject_id, $name, $slug ) {

        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            [
                'subject_id' => $subject_id,
                'name'       => $name,
                'slug'       => $slug,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    /**
     * Update a chapter row.
     *
     * @param int    $chapter_id Chapter ID.
     * @param int    $subject_id Subject ID.
     * @param string $name       Chapter name.
     * @param string $slug       Chapter slug.
     */
    private static function update_chapter( $chapter_id, $subject_id, $name, $slug ) {

        global $wpdb;

        $wpdb->update(
            self::get_table_name(),
            [
                'subject_id' => $subject_id,
                'name'       => $name,
                'slug'       => $slug,
            ],
            ['id' => $chapter_id],
            [
                '%d',
                '%s',
                '%s',
            ],
            ['%d']
        );
    }

    /**
     * Redirect back to the chapters page with a status message.
     *
     * @param string $message Message key.
     */
    private static function redirect_with_message( $message ) {

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'          => 'mdcat-chapters',
                    'mdcat_message' => sanitize_key($message),
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
