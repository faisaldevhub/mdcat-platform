/**
 * MDCAT Platform — Bulk Import Controller
 *
 * Handles file upload, import execution via AJAX, and
 * result rendering for the admin import page.
 */

(function () {

    'use strict';

    var MDCATBulkImportController = {

        file: null,

        /**
         * Initialize the import controller.
         */
        init: function () {

            if (typeof MDCATBulkImport === 'undefined') {
                return;
            }

            this.bindEvents();
        },

        /**
         * Bind all UI event handlers.
         */
        bindEvents: function () {

            var self = this;

            var zone  = document.getElementById('mdcat-upload-zone');
            var input = document.getElementById('mdcat-csv-input');

            if (zone) {
                zone.addEventListener('click', function () {
                    if (input) {
                        input.click();
                    }
                });

                zone.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    zone.classList.add('mdcat-bulk-import__upload-zone--dragover');
                });

                zone.addEventListener('dragleave', function () {
                    zone.classList.remove('mdcat-bulk-import__upload-zone--dragover');
                });

                zone.addEventListener('drop', function (e) {
                    e.preventDefault();
                    zone.classList.remove('mdcat-bulk-import__upload-zone--dragover');

                    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                        self.handleFileSelect(e.dataTransfer.files[0]);
                    }
                });
            }

            if (input) {
                input.addEventListener('change', function () {
                    if (input.files && input.files.length) {
                        self.handleFileSelect(input.files[0]);
                    }
                });
            }

            var startBtn = document.getElementById('mdcat-start-import');

            if (startBtn) {
                startBtn.addEventListener('click', function () {
                    self.handleImport();
                });
            }

            var removeBtn = document.getElementById('mdcat-file-remove');

            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    self.resetUpload();
                });
            }

            var templateBtn = document.getElementById('mdcat-download-template');

            if (templateBtn) {
                templateBtn.addEventListener('click', function () {
                    self.downloadTemplate();
                });
            }

            var anotherBtn = document.getElementById('mdcat-import-another');

            if (anotherBtn) {
                anotherBtn.addEventListener('click', function () {
                    self.resetAll();
                });
            }
        },

        /**
         * Handle file selection (from input or drag-drop).
         */
        handleFileSelect: function (file) {

            // Validate file type.
            var ext = file.name.split('.').pop().toLowerCase();

            if ('csv' !== ext) {
                alert(MDCATBulkImport.i18n.invalid_file);
                return;
            }

            // Validate file size (max 10MB).
            if (file.size > 10 * 1024 * 1024) {
                alert(MDCATBulkImport.i18n.file_too_large);
                return;
            }

            this.file = file;

            // Show file info.
            var fileInfo = document.getElementById('mdcat-file-info');
            var fileName = document.getElementById('mdcat-file-name');
            var zone     = document.getElementById('mdcat-upload-zone');
            var options  = document.getElementById('mdcat-import-options');

            if (fileName) {
                fileName.textContent = file.name + ' (' + this.formatSize(file.size) + ')';
            }

            if (fileInfo) {
                fileInfo.style.display = 'flex';
            }

            if (zone) {
                zone.style.display = 'none';
            }

            if (options) {
                options.style.display = 'block';
            }
        },

        /**
         * Execute the import via AJAX.
         */
        handleImport: function () {

            if (!this.file) {
                return;
            }

            var self = this;

            // Read options.
            var autoCreate    = document.getElementById('mdcat-auto-create');
            var duplicateMode = document.getElementById('mdcat-duplicate-mode');

            var formData = new FormData();
            formData.append('action', 'mdcat_bulk_import_upload');
            formData.append('nonce', MDCATBulkImport.nonce);
            formData.append('csv_file', this.file);
            formData.append('auto_create', autoCreate && autoCreate.checked ? '1' : '0');
            formData.append('duplicate_mode', duplicateMode ? duplicateMode.value : 'skip');

            // Show progress, hide options.
            this.showProgress();

            var xhr = new XMLHttpRequest();
            xhr.open('POST', MDCATBulkImport.ajax_url, true);

            xhr.onreadystatechange = function () {

                if (xhr.readyState !== 4) {
                    return;
                }

                try {
                    var response = JSON.parse(xhr.responseText);

                    if (response.success) {
                        self.showResults(response.data, true);
                    } else {
                        self.showResults(response.data, false);
                    }
                } catch (e) {
                    self.showResults({
                        errors: [{ row: 0, field: '', message: 'An unexpected error occurred.' }]
                    }, false);
                }
            };

            xhr.send(formData);
        },

        /**
         * Show the progress bar and hide other sections.
         */
        showProgress: function () {

            var options  = document.getElementById('mdcat-import-options');
            var progress = document.getElementById('mdcat-import-progress');
            var bar      = document.getElementById('mdcat-progress-bar');
            var text     = document.getElementById('mdcat-progress-text');

            if (options) {
                options.style.display = 'none';
            }

            if (progress) {
                progress.style.display = 'block';
            }

            if (bar) {
                bar.style.width = '100%';
            }

            if (text) {
                text.textContent = MDCATBulkImport.i18n.importing;
            }
        },

        /**
         * Render the import results.
         */
        showResults: function (data, success) {

            var progress = document.getElementById('mdcat-import-progress');
            var results  = document.getElementById('mdcat-import-results');
            var fileInfo = document.getElementById('mdcat-file-info');

            if (progress) {
                progress.style.display = 'none';
            }

            if (fileInfo) {
                fileInfo.style.display = 'none';
            }

            if (results) {
                results.style.display = 'block';
            }

            // Render summary cards.
            this.renderCards(data, success);

            // Render created entities.
            this.renderCreatedEntities(data);

            // Render errors.
            this.renderErrors(data);
        },

        /**
         * Render result summary cards.
         */
        renderCards: function (data, success) {

            var container = document.getElementById('mdcat-results-cards');

            if (!container) {
                return;
            }

            var i18n = MDCATBulkImport.i18n;

            var cards = [];

            if (success) {
                cards = [
                    { value: data.inserted || 0, label: i18n.inserted, modifier: 'inserted' },
                    { value: data.duplicates_count || 0, label: i18n.duplicates, modifier: 'duplicates' },
                    { value: data.errors ? data.errors.length : 0, label: i18n.errors, modifier: 'errors' },
                    { value: data.total_rows || 0, label: i18n.total, modifier: 'total' },
                ];
            } else {
                var errorCount = 0;

                if (data.errors && Array.isArray(data.errors)) {
                    errorCount = data.errors.length;
                } else if (data.error_count) {
                    errorCount = data.error_count;
                }

                cards = [
                    { value: 0, label: i18n.inserted, modifier: 'inserted' },
                    { value: 0, label: i18n.duplicates, modifier: 'duplicates' },
                    { value: errorCount, label: i18n.errors, modifier: 'errors' },
                    { value: data.total_rows || 0, label: i18n.total, modifier: 'total' },
                ];
            }

            var html = '';

            for (var i = 0; i < cards.length; i++) {
                var c = cards[i];
                html += '<div class="mdcat-bulk-import__result-card mdcat-bulk-import__result-card--' + c.modifier + '">';
                html += '<div class="mdcat-bulk-import__result-value">' + this.escapeHtml(String(c.value)) + '</div>';
                html += '<div class="mdcat-bulk-import__result-label">' + this.escapeHtml(c.label) + '</div>';
                html += '</div>';
            }

            container.innerHTML = html;
        },

        /**
         * Render auto-created entities section.
         */
        renderCreatedEntities: function (data) {

            var container = document.getElementById('mdcat-created-entities');
            var list      = document.getElementById('mdcat-created-list');

            if (!container || !list || !data.created_entities) {
                return;
            }

            var created = data.created_entities;
            var total   = (created.subjects || 0) + (created.chapters || 0) + (created.collections || 0);

            if (!total) {
                container.style.display = 'none';
                return;
            }

            var i18n = MDCATBulkImport.i18n;
            var html = '<div class="mdcat-bulk-import__created-list">';

            if (created.subjects) {
                html += '<div>' + this.escapeHtml(i18n.subjects_created) + ': <strong>' + created.subjects + '</strong></div>';
            }

            if (created.chapters) {
                html += '<div>' + this.escapeHtml(i18n.chapters_created) + ': <strong>' + created.chapters + '</strong></div>';
            }

            if (created.collections) {
                html += '<div>' + this.escapeHtml(i18n.collections_created) + ': <strong>' + created.collections + '</strong></div>';
            }

            html += '</div>';

            list.innerHTML = html;
            container.style.display = 'block';
        },

        /**
         * Render error details table.
         */
        renderErrors: function (data) {

            var container = document.getElementById('mdcat-error-details');
            var tableWrap = document.getElementById('mdcat-error-table');

            if (!container || !tableWrap) {
                return;
            }

            var errors = data.errors;

            if (!errors || !errors.length) {
                container.style.display = 'none';
                return;
            }

            var i18n = MDCATBulkImport.i18n;

            var html = '<table class="mdcat-bulk-import__error-table">';
            html += '<thead><tr>';
            html += '<th>' + this.escapeHtml(i18n.row) + '</th>';
            html += '<th>' + this.escapeHtml(i18n.field) + '</th>';
            html += '<th>' + this.escapeHtml(i18n.message) + '</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            for (var i = 0; i < errors.length; i++) {
                var err = errors[i];

                // Handle both error object formats.
                var row     = err.row || '';
                var field   = err.field || '';
                var message = err.message || (typeof err === 'string' ? err : '');

                html += '<tr>';
                html += '<td>' + this.escapeHtml(String(row)) + '</td>';
                html += '<td>' + this.escapeHtml(String(field)) + '</td>';
                html += '<td>' + this.escapeHtml(String(message)) + '</td>';
                html += '</tr>';
            }

            html += '</tbody></table>';

            tableWrap.innerHTML = html;
            container.style.display = 'block';
        },

        /**
         * Download the CSV template file.
         */
        downloadTemplate: function () {

            var url = MDCATBulkImport.ajax_url +
                '?action=mdcat_bulk_import_template' +
                '&nonce=' + encodeURIComponent(MDCATBulkImport.nonce);

            window.location.href = url;
        },

        /**
         * Reset to the upload state (remove selected file).
         */
        resetUpload: function () {

            this.file = null;

            var zone     = document.getElementById('mdcat-upload-zone');
            var fileInfo = document.getElementById('mdcat-file-info');
            var options  = document.getElementById('mdcat-import-options');
            var input    = document.getElementById('mdcat-csv-input');

            if (zone) {
                zone.style.display = 'block';
            }

            if (fileInfo) {
                fileInfo.style.display = 'none';
            }

            if (options) {
                options.style.display = 'none';
            }

            if (input) {
                input.value = '';
            }
        },

        /**
         * Reset the entire page to initial state.
         */
        resetAll: function () {

            this.resetUpload();

            var progress = document.getElementById('mdcat-import-progress');
            var results  = document.getElementById('mdcat-import-results');
            var created  = document.getElementById('mdcat-created-entities');
            var errors   = document.getElementById('mdcat-error-details');

            if (progress) {
                progress.style.display = 'none';
            }

            if (results) {
                results.style.display = 'none';
            }

            if (created) {
                created.style.display = 'none';
            }

            if (errors) {
                errors.style.display = 'none';
            }
        },

        /**
         * Format a file size in bytes to a human-readable string.
         */
        formatSize: function (bytes) {

            if (bytes < 1024) {
                return bytes + ' B';
            }

            if (bytes < 1024 * 1024) {
                return (bytes / 1024).toFixed(1) + ' KB';
            }

            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        },

        /**
         * Escape HTML for safe rendering.
         */
        escapeHtml: function (str) {

            if (!str) {
                return '';
            }

            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    /* ============================================================
       Boot
       ============================================================ */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            MDCATBulkImportController.init();
        });
    } else {
        MDCATBulkImportController.init();
    }

})();
