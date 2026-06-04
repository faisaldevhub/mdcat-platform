<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Admin_Reports_View {

    /**
     * Render the admin reporting dashboard page.
     *
     * Enqueues admin-scoped assets and outputs the HTML shell.
     * All data is populated via AJAX calls from the JavaScript controller.
     */
    public static function render() {

        self::enqueue_assets();

        ?>
        <div class="wrap mdcat-admin-reports">

            <h1 class="mdcat-admin-reports__title">
                <?php esc_html_e('MDCAT Platform Dashboard', 'mdcat-platform'); ?>
            </h1>

            <p class="mdcat-admin-reports__subtitle">
                <?php esc_html_e('Platform-wide statistics, student insights, and performance reports.', 'mdcat-platform'); ?>
            </p>

            <!-- Overview Statistics Cards -->
            <section class="mdcat-admin-reports__section mdcat-admin-reports__section--stats">
                <div class="mdcat-admin-reports__stats-grid">
                    <div class="mdcat-admin-reports__stats-loading">
                        <?php esc_html_e('Loading statistics...', 'mdcat-platform'); ?>
                    </div>
                </div>
            </section>

            <!-- Student Reporting -->
            <section class="mdcat-admin-reports__section mdcat-admin-reports__section--students">
                <div class="mdcat-admin-reports__section-row">

                    <div class="mdcat-admin-reports__panel">
                        <h2 class="mdcat-admin-reports__panel-title">
                            <?php esc_html_e('Most Active Students', 'mdcat-platform'); ?>
                        </h2>
                        <div class="mdcat-admin-reports__most-active">
                            <div class="mdcat-admin-reports__panel-loading">
                                <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="mdcat-admin-reports__panel">
                        <h2 class="mdcat-admin-reports__panel-title">
                            <?php esc_html_e('Top Performers', 'mdcat-platform'); ?>
                        </h2>
                        <div class="mdcat-admin-reports__top-performers">
                            <div class="mdcat-admin-reports__panel-loading">
                                <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Performance Reporting -->
            <section class="mdcat-admin-reports__section mdcat-admin-reports__section--performance">
                <h2 class="mdcat-admin-reports__section-title">
                    <?php esc_html_e('Subject Performance Report', 'mdcat-platform'); ?>
                </h2>
                <div class="mdcat-admin-reports__performance-report">
                    <div class="mdcat-admin-reports__panel-loading">
                        <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
                    </div>
                </div>

                <div class="mdcat-admin-reports__section-row">

                    <div class="mdcat-admin-reports__panel mdcat-admin-reports__panel--strongest">
                        <h3 class="mdcat-admin-reports__panel-title">
                            <?php esc_html_e('Strongest Subjects', 'mdcat-platform'); ?>
                        </h3>
                        <div class="mdcat-admin-reports__strongest">
                            <div class="mdcat-admin-reports__panel-loading">
                                <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="mdcat-admin-reports__panel mdcat-admin-reports__panel--weakest">
                        <h3 class="mdcat-admin-reports__panel-title">
                            <?php esc_html_e('Weakest Subjects', 'mdcat-platform'); ?>
                        </h3>
                        <div class="mdcat-admin-reports__weakest">
                            <div class="mdcat-admin-reports__panel-loading">
                                <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Activity Monitoring -->
            <section class="mdcat-admin-reports__section mdcat-admin-reports__section--activity">
                <h2 class="mdcat-admin-reports__section-title">
                    <?php esc_html_e('Recent Platform Activity', 'mdcat-platform'); ?>
                </h2>
                <div class="mdcat-admin-reports__activity-feed">
                    <div class="mdcat-admin-reports__panel-loading">
                        <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
                    </div>
                </div>
            </section>

        </div>
        <?php
    }

    /**
     * Enqueue admin reporting assets.
     *
     * Registers and enqueues CSS/JS only on the admin dashboard page.
     * Localizes the script with AJAX URL and a dedicated admin nonce.
     */
    private static function enqueue_assets() {

        wp_enqueue_style(
            'mdcat-admin-reports',
            MDCAT_PLATFORM_URL . 'assets/css/admin-reports.css',
            [],
            MDCAT_PLATFORM_VERSION
        );

        wp_enqueue_script(
            'mdcat-admin-reports',
            MDCAT_PLATFORM_URL . 'assets/js/admin-reports.js',
            [],
            MDCAT_PLATFORM_VERSION,
            true
        );

        wp_localize_script(
            'mdcat-admin-reports',
            'MDCATAdminReports',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('mdcat_admin_reports_nonce'),
            ]
        );
    }
}
