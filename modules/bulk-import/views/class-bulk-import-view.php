<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Bulk_Import_View {

    /**
     * Render the bulk import admin page.
     *
     * Enqueues admin-scoped assets and outputs the HTML shell.
     * File upload and import logic is handled by JavaScript via AJAX.
     */
    public static function render() {

        self::enqueue_assets();

        ?>
        <div class="wrap mdcat-bulk-import">

            <h1 class="mdcat-bulk-import__title">
                <?php esc_html_e('Import Questions', 'mdcat-platform'); ?>
            </h1>

            <p class="mdcat-bulk-import__subtitle">
                <?php esc_html_e('Upload a CSV file to import multiple questions at once.', 'mdcat-platform'); ?>
            </p>

            <!-- Template Download -->
            <div class="mdcat-bulk-import__template">
                <button type="button" class="button mdcat-bulk-import__template-btn" id="mdcat-download-template">
                    <?php esc_html_e('Download CSV Template', 'mdcat-platform'); ?>
                </button>
                <span class="mdcat-bulk-import__template-hint">
                    <?php esc_html_e('Download a sample CSV file with the correct column headers and example data.', 'mdcat-platform'); ?>
                </span>
            </div>

            <!-- Upload Zone -->
            <div class="mdcat-bulk-import__upload-zone" id="mdcat-upload-zone">
                <div class="mdcat-bulk-import__upload-content">
                    <span class="mdcat-bulk-import__upload-icon">📁</span>
                    <p class="mdcat-bulk-import__upload-text">
                        <?php esc_html_e('Drag & drop a CSV file here, or click to browse', 'mdcat-platform'); ?>
                    </p>
                    <p class="mdcat-bulk-import__upload-hint">
                        <?php esc_html_e('Maximum file size: 10MB — Maximum rows: 15,000', 'mdcat-platform'); ?>
                    </p>
                    <input type="file" id="mdcat-csv-input" accept=".csv" class="mdcat-bulk-import__file-input" />
                </div>
            </div>

            <!-- File Info (shown after file selected) -->
            <div class="mdcat-bulk-import__file-info" id="mdcat-file-info" style="display:none;">
                <span class="mdcat-bulk-import__file-name" id="mdcat-file-name"></span>
                <button type="button" class="button mdcat-bulk-import__file-remove" id="mdcat-file-remove">
                    <?php esc_html_e('Remove', 'mdcat-platform'); ?>
                </button>
            </div>

            <!-- Import Options -->
            <div class="mdcat-bulk-import__options" id="mdcat-import-options" style="display:none;">

                <h2 class="mdcat-bulk-import__options-title">
                    <?php esc_html_e('Import Options', 'mdcat-platform'); ?>
                </h2>

                <table class="form-table mdcat-bulk-import__options-table">
                    <tr>
                        <th scope="row">
                            <label for="mdcat-auto-create">
                                <?php esc_html_e('Auto-create entities', 'mdcat-platform'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="mdcat-auto-create" checked />
                                <?php esc_html_e('Automatically create subjects, chapters, and collections that do not exist.', 'mdcat-platform'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-duplicate-mode">
                                <?php esc_html_e('Duplicate handling', 'mdcat-platform'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="mdcat-duplicate-mode">
                                <option value="skip"><?php esc_html_e('Skip duplicates (import unique questions only)', 'mdcat-platform'); ?></option>
                                <option value="error"><?php esc_html_e('Flag as errors (do not import if duplicates found)', 'mdcat-platform'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <button type="button" class="button button-primary button-hero mdcat-bulk-import__start-btn" id="mdcat-start-import">
                    <?php esc_html_e('Start Import', 'mdcat-platform'); ?>
                </button>
            </div>

            <!-- Progress (shown during import) -->
            <div class="mdcat-bulk-import__progress" id="mdcat-import-progress" style="display:none;">
                <div class="mdcat-bulk-import__progress-bar-wrap">
                    <div class="mdcat-bulk-import__progress-bar" id="mdcat-progress-bar"></div>
                </div>
                <p class="mdcat-bulk-import__progress-text" id="mdcat-progress-text">
                    <?php esc_html_e('Importing questions...', 'mdcat-platform'); ?>
                </p>
            </div>

            <!-- Results (shown after import) -->
            <div class="mdcat-bulk-import__results" id="mdcat-import-results" style="display:none;">

                <h2 class="mdcat-bulk-import__results-title">
                    <?php esc_html_e('Import Results', 'mdcat-platform'); ?>
                </h2>

                <div class="mdcat-bulk-import__results-cards" id="mdcat-results-cards"></div>

                <!-- Created Entities -->
                <div class="mdcat-bulk-import__created" id="mdcat-created-entities" style="display:none;">
                    <h3><?php esc_html_e('Auto-Created Entities', 'mdcat-platform'); ?></h3>
                    <div id="mdcat-created-list"></div>
                </div>

                <!-- Error Details -->
                <div class="mdcat-bulk-import__errors" id="mdcat-error-details" style="display:none;">
                    <h3><?php esc_html_e('Error Details', 'mdcat-platform'); ?></h3>
                    <div class="mdcat-bulk-import__error-table-wrap" id="mdcat-error-table"></div>
                </div>

                <!-- Import Another -->
                <button type="button" class="button mdcat-bulk-import__another-btn" id="mdcat-import-another">
                    <?php esc_html_e('Import Another File', 'mdcat-platform'); ?>
                </button>
            </div>

        </div>
        <?php
    }

    /**
     * Enqueue admin import assets.
     */
    private static function enqueue_assets() {

        wp_enqueue_style(
            'mdcat-bulk-import',
            MDCAT_PLATFORM_URL . 'assets/css/bulk-import.css',
            [],
            MDCAT_PLATFORM_VERSION
        );

        wp_enqueue_script(
            'mdcat-bulk-import',
            MDCAT_PLATFORM_URL . 'assets/js/bulk-import.js',
            [],
            MDCAT_PLATFORM_VERSION,
            true
        );

        wp_localize_script(
            'mdcat-bulk-import',
            'MDCATBulkImport',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('mdcat_bulk_import_nonce'),
                'i18n'     => [
                    'importing'        => __('Importing questions...', 'mdcat-platform'),
                    'complete'         => __('Import complete!', 'mdcat-platform'),
                    'failed'           => __('Import failed.', 'mdcat-platform'),
                    'invalid_file'     => __('Only CSV files are supported.', 'mdcat-platform'),
                    'file_too_large'   => __('File exceeds the maximum size of 10MB.', 'mdcat-platform'),
                    'inserted'         => __('Inserted', 'mdcat-platform'),
                    'duplicates'       => __('Duplicates', 'mdcat-platform'),
                    'errors'           => __('Errors', 'mdcat-platform'),
                    'total'            => __('Total Rows', 'mdcat-platform'),
                    'subjects_created' => __('Subjects created', 'mdcat-platform'),
                    'chapters_created' => __('Chapters created', 'mdcat-platform'),
                    'collections_created' => __('Collections created', 'mdcat-platform'),
                    'row'              => __('Row', 'mdcat-platform'),
                    'field'            => __('Field', 'mdcat-platform'),
                    'message'          => __('Message', 'mdcat-platform'),
                ],
            ]
        );
    }
}
