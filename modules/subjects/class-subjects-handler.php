<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Subjects_Handler {

    /**
     * Register admin form actions for subject CRUD.
     */
    public static function init() {

        add_action('admin_post_mdcat_save_subject', [__CLASS__, 'save_subject']);
        add_action('admin_post_mdcat_delete_subject', [__CLASS__, 'delete_subject']);
    }

    /**
     * Get the custom subjects table name.
     *
     * @return string
     */
    public static function get_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_subjects';
    }

    /**
     * Fetch all subjects for the admin list table.
     *
     * @return array
     */
    public static function get_subjects() {

        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            "SELECT id, name, slug, created_at FROM {$table_name} ORDER BY id DESC"
        );
    }

    /**
     * Fetch one subject by ID.
     *
     * @param int $subject_id Subject ID.
     * @return object|null
     */
    public static function get_subject( $subject_id ) {

        global $wpdb;

        $table_name = self::get_table_name();
        $subject_id = absint($subject_id);

        if (!$subject_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, slug, created_at FROM {$table_name} WHERE id = %d",
                $subject_id
            )
        );
    }

    /**
     * Create or update a subject from the admin form.
     */
    public static function save_subject() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage subjects.', 'mdcat-platform'));
        }

        check_admin_referer('mdcat_save_subject', 'mdcat_subject_nonce');

        $subject_id = isset($_POST['subject_id']) ? absint(wp_unslash($_POST['subject_id'])) : 0;
        $name       = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $slug       = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';

        if ('' === $name) {
            self::redirect_with_message('missing_name');
        }

        if ('' === $slug) {
            $slug = sanitize_title($name);
        }

        if ($subject_id) {
            self::update_subject($subject_id, $name, $slug);
            self::redirect_with_message('updated');
        }

        self::create_subject($name, $slug);
        self::redirect_with_message('created');
    }

    /**
     * Delete a subject after nonce and capability checks.
     */
    public static function delete_subject() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete subjects.', 'mdcat-platform'));
        }

        $subject_id = isset($_GET['subject_id']) ? absint(wp_unslash($_GET['subject_id'])) : 0;

        check_admin_referer('mdcat_delete_subject_' . $subject_id);

        if ($subject_id) {
            global $wpdb;

            $wpdb->delete(
                self::get_table_name(),
                ['id' => $subject_id],
                ['%d']
            );
        }

        self::redirect_with_message('deleted');
    }

    /**
     * Insert a subject row.
     *
     * @param string $name Subject name.
     * @param string $slug Subject slug.
     */
    private static function create_subject( $name, $slug ) {

        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            [
                'name'       => $name,
                'slug'       => $slug,
                'created_at' => current_time('mysql'),
            ],
            [
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    /**
     * Update a subject row.
     *
     * @param int    $subject_id Subject ID.
     * @param string $name       Subject name.
     * @param string $slug       Subject slug.
     */
    private static function update_subject( $subject_id, $name, $slug ) {

        global $wpdb;

        $wpdb->update(
            self::get_table_name(),
            [
                'name' => $name,
                'slug' => $slug,
            ],
            ['id' => $subject_id],
            [
                '%s',
                '%s',
            ],
            ['%d']
        );
    }

    /**
     * Redirect back to the subjects page with a status message.
     *
     * @param string $message Message key.
     */
    private static function redirect_with_message( $message ) {

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'          => 'mdcat-subjects',
                    'mdcat_message' => sanitize_key($message),
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
