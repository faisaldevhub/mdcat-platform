<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Bulk_Import_Ajax {

    const NONCE_ACTION = 'mdcat_bulk_import_nonce';
    const NONCE_FIELD  = 'nonce';

    /**
     * Register admin-only AJAX actions for bulk import.
     */
    public static function init() {

        add_action('wp_ajax_mdcat_bulk_import_upload', [__CLASS__, 'handle_upload']);
        add_action('wp_ajax_mdcat_bulk_import_template', [__CLASS__, 'download_template']);
    }

    /**
     * Handle the CSV file upload and run the full import pipeline.
     *
     * Pipeline: Upload → Parse → Validate → Resolve → Deduplicate → Insert.
     * Each phase can return errors that halt the pipeline.
     */
    public static function handle_upload() {

        self::verify_request();

        // Extend time limit for large imports.
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        // Validate file upload.
        if (empty($_FILES['csv_file'])) {
            self::send_error('no_file', __('No file was uploaded.', 'mdcat-platform'));
        }

        $file = $_FILES['csv_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            self::send_error('upload_error', __('File upload failed. Please try again.', 'mdcat-platform'));
        }

        // Validate file type.
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ('csv' !== $file_ext) {
            self::send_error('invalid_type', __('Only CSV files are supported.', 'mdcat-platform'));
        }

        // Validate file size (max 10MB).
        $max_size = 10 * 1024 * 1024;

        if ($file['size'] > $max_size) {
            self::send_error('file_too_large', __('File exceeds the maximum size of 10MB.', 'mdcat-platform'));
        }

        // Concurrency lock — prevent parallel imports (SEC-3).
        $lock_key = 'mdcat_bulk_import_lock';

        if (get_transient($lock_key)) {
            self::send_error('import_in_progress', __('An import is already in progress. Please wait.', 'mdcat-platform'), 423);
        }

        set_transient($lock_key, true, 300);

        // Copy uploaded file to a safe location (BUG-5).
        $safe_path = wp_tempnam('mdcat_import_');

        if (!copy($file['tmp_name'], $safe_path)) {
            delete_transient($lock_key);
            self::send_error('copy_failed', __('Could not process the uploaded file.', 'mdcat-platform'));
        }

        // Register cleanup for lock and temp file on shutdown.
        register_shutdown_function(function() use ($safe_path, $lock_key) {
            if (file_exists($safe_path)) {
                @unlink($safe_path);
            }
            delete_transient($lock_key);
        });

        $file_path = $safe_path;

        // Read import options.
        $auto_create    = isset($_POST['auto_create']) && '1' === $_POST['auto_create'];
        $duplicate_mode = isset($_POST['duplicate_mode']) ? sanitize_key($_POST['duplicate_mode']) : 'skip';

        if (!in_array($duplicate_mode, ['skip', 'error'], true)) {
            $duplicate_mode = 'skip';
        }

        // Phase 1: Parse CSV.
        $parsed = MDCAT_Platform_CSV_Parser_Service::parse($file_path);

        if (!empty($parsed['errors'])) {
            wp_send_json_error([
                'phase'  => 'parse',
                'errors' => $parsed['errors'],
            ]);
        }

        // Phase 2: Validate rows.
        $validated = MDCAT_Platform_Import_Validator_Service::validate_rows($parsed['rows']);

        if (!empty($validated['errors'])) {
            wp_send_json_error([
                'phase'       => 'validation',
                'errors'      => $validated['errors'],
                'total_rows'  => count($parsed['rows']),
                'valid_rows'  => count($validated['valid']),
                'error_count' => count($validated['errors']),
            ]);
        }

        // Phase 3: Resolve entities.
        $resolved = MDCAT_Platform_Entity_Resolver_Service::resolve($validated['valid'], $auto_create);

        if (!empty($resolved['errors'])) {

            // Clean up any entities created before the error (INTEGRITY-1).
            self::cleanup_created_entities($resolved['created_ids']);

            wp_send_json_error([
                'phase'       => 'resolution',
                'errors'      => $resolved['errors'],
                'total_rows'  => count($parsed['rows']),
                'valid_rows'  => count($validated['valid']),
                'error_count' => count($resolved['errors']),
            ]);
        }

        // Phase 4+5: Deduplicate + Insert.
        $result = MDCAT_Platform_Import_Processor_Service::process($resolved['resolved'], $duplicate_mode);

        // Clean up orphaned entities if no questions were inserted (INTEGRITY-1).
        $has_created = ($resolved['created']['subjects'] + $resolved['created']['chapters'] + $resolved['created']['collections']) > 0;

        if (0 === $result['inserted'] && $has_created) {
            self::cleanup_created_entities($resolved['created_ids']);
            $resolved['created'] = ['subjects' => 0, 'chapters' => 0, 'collections' => 0];
        }

        // Build final summary.
        wp_send_json_success([
            'total_rows'       => count($parsed['rows']),
            'valid_rows'       => count($validated['valid']),
            'inserted'         => $result['inserted'],
            'duplicates_count' => count($result['duplicates']),
            'duplicates'       => array_slice($result['duplicates'], 0, 50),
            'errors'           => $result['errors'],
            'created_entities' => $resolved['created'],
        ]);
    }

    /**
     * Serve the CSV template file for download.
     */
    public static function download_template() {

        self::verify_request();

        $csv_content = MDCAT_Platform_CSV_Parser_Service::generate_template();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mdcat-questions-template.csv"');
        header('Content-Length: ' . strlen($csv_content));
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo $csv_content;
        exit;
    }

    /**
     * Verify nonce and admin capability.
     */
    private static function verify_request() {

        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            self::send_error('invalid_nonce', __('Security check failed.', 'mdcat-platform'), 403);
        }

        if (!current_user_can('manage_options')) {
            self::send_error('unauthorized', __('You do not have permission to import questions.', 'mdcat-platform'), 403);
        }
    }

    /**
     * Send a normalized JSON error response.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status.
     */
    private static function send_error( $code, $message, $status = 400 ) {

        wp_send_json_error(
            [
                'code'    => sanitize_key($code),
                'message' => $message,
            ],
            absint($status)
        );
    }

    /**
     * Delete auto-created entities that are no longer needed.
     *
     * Called when no questions were inserted, to prevent orphaned
     * subjects, chapters, and collections in the database.
     * Deletes in reverse hierarchical order.
     *
     * @param array $created_ids Arrays of entity IDs keyed by type.
     */
    private static function cleanup_created_entities( $created_ids ) {

        global $wpdb;

        // Delete collections first (leaf entities).
        foreach ($created_ids['collections'] as $id) {
            $wpdb->delete(
                MDCAT_Platform_Collections_Handler::get_table_name(),
                ['id' => absint($id)],
                ['%d']
            );
        }

        // Delete chapters.
        foreach ($created_ids['chapters'] as $id) {
            $wpdb->delete(
                MDCAT_Platform_Chapters_Handler::get_table_name(),
                ['id' => absint($id)],
                ['%d']
            );
        }

        // Delete subjects (root entities).
        foreach ($created_ids['subjects'] as $id) {
            $wpdb->delete(
                MDCAT_Platform_Subjects_Handler::get_table_name(),
                ['id' => absint($id)],
                ['%d']
            );
        }
    }
}
