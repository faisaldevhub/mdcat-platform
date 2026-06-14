<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Enrollment_Admin_View {

    /**
     * Render the admin enrollment requests page.
     *
     * Outputs the HTML shell with filter tabs, request list container,
     * and detail/review modal. All data is populated via AJAX from
     * the enrollment-admin.js controller.
     */
    public static function render() {

        self::enqueue_assets();

        ?>
        <div class="wrap mdcat-enrollment-admin">

            <h1 class="mdcat-enrollment-admin__title">
                <?php esc_html_e('Enrollment Requests', 'mdcat-platform'); ?>
            </h1>

            <p class="mdcat-enrollment-admin__subtitle">
                <?php esc_html_e('Review, approve, or reject student enrollment requests.', 'mdcat-platform'); ?>
            </p>

            <!-- Status Filter Tabs -->
            <div class="mdcat-enrollment-admin__tabs" id="mdcat-enrollment-tabs">
                <button class="mdcat-enrollment-admin__tab mdcat-enrollment-admin__tab--active" data-status="all">
                    <?php esc_html_e('All', 'mdcat-platform'); ?>
                    <span class="mdcat-enrollment-admin__tab-count" id="mdcat-count-total">0</span>
                </button>
                <button class="mdcat-enrollment-admin__tab" data-status="pending">
                    <?php esc_html_e('Pending', 'mdcat-platform'); ?>
                    <span class="mdcat-enrollment-admin__tab-count" id="mdcat-count-pending">0</span>
                </button>
                <button class="mdcat-enrollment-admin__tab" data-status="approved">
                    <?php esc_html_e('Approved', 'mdcat-platform'); ?>
                    <span class="mdcat-enrollment-admin__tab-count" id="mdcat-count-approved">0</span>
                </button>
                <button class="mdcat-enrollment-admin__tab" data-status="rejected">
                    <?php esc_html_e('Rejected', 'mdcat-platform'); ?>
                    <span class="mdcat-enrollment-admin__tab-count" id="mdcat-count-rejected">0</span>
                </button>
            </div>

            <!-- Requests List -->
            <div class="mdcat-enrollment-admin__list" id="mdcat-enrollment-list">
                <div class="mdcat-enrollment-admin__loading">
                    <?php esc_html_e('Loading enrollment requests...', 'mdcat-platform'); ?>
                </div>
            </div>

            <!-- Detail / Review Modal -->
            <div class="mdcat-enrollment-admin__modal" id="mdcat-enrollment-modal" style="display:none;">
                <div class="mdcat-enrollment-admin__modal-overlay" id="mdcat-enrollment-modal-overlay"></div>
                <div class="mdcat-enrollment-admin__modal-content">

                    <button class="mdcat-enrollment-admin__modal-close" id="mdcat-enrollment-modal-close">&times;</button>

                    <h2 class="mdcat-enrollment-admin__modal-title">
                        <?php esc_html_e('Enrollment Request Details', 'mdcat-platform'); ?>
                    </h2>

                    <div class="mdcat-enrollment-admin__modal-body" id="mdcat-enrollment-modal-body">
                        <!-- Populated by JS -->
                    </div>

                    <div class="mdcat-enrollment-admin__modal-actions" id="mdcat-enrollment-modal-actions">
                        <!-- Populated by JS -->
                    </div>

                </div>
            </div>

            <!-- Rejection Reason Modal -->
            <div class="mdcat-enrollment-admin__reject-modal" id="mdcat-enrollment-reject-modal" style="display:none;">
                <div class="mdcat-enrollment-admin__modal-overlay" id="mdcat-enrollment-reject-overlay"></div>
                <div class="mdcat-enrollment-admin__modal-content mdcat-enrollment-admin__modal-content--small">

                    <button class="mdcat-enrollment-admin__modal-close" id="mdcat-enrollment-reject-close">&times;</button>

                    <h2 class="mdcat-enrollment-admin__modal-title">
                        <?php esc_html_e('Reject Enrollment', 'mdcat-platform'); ?>
                    </h2>

                    <div class="mdcat-enrollment-admin__reject-body">
                        <label for="mdcat-enrollment-reject-reason" class="mdcat-enrollment-admin__label">
                            <?php esc_html_e('Rejection Reason (optional)', 'mdcat-platform'); ?>
                        </label>
                        <textarea
                            id="mdcat-enrollment-reject-reason"
                            class="mdcat-enrollment-admin__textarea"
                            rows="4"
                            placeholder="<?php esc_attr_e('Enter a reason for rejection...', 'mdcat-platform'); ?>"
                        ></textarea>
                    </div>

                    <div class="mdcat-enrollment-admin__reject-actions">
                        <button class="button" id="mdcat-enrollment-reject-cancel">
                            <?php esc_html_e('Cancel', 'mdcat-platform'); ?>
                        </button>
                        <button class="button button-primary mdcat-enrollment-admin__btn--reject" id="mdcat-enrollment-reject-confirm">
                            <?php esc_html_e('Confirm Rejection', 'mdcat-platform'); ?>
                        </button>
                    </div>

                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Enqueue admin enrollment assets.
     */
    private static function enqueue_assets() {

        wp_enqueue_style(
            'mdcat-enrollment-admin',
            MDCAT_PLATFORM_URL . 'assets/css/enrollment.css',
            [],
            MDCAT_PLATFORM_VERSION
        );

        wp_enqueue_script(
            'mdcat-enrollment-admin',
            MDCAT_PLATFORM_URL . 'assets/js/enrollment-admin.js',
            [],
            MDCAT_PLATFORM_VERSION,
            true
        );

        wp_localize_script(
            'mdcat-enrollment-admin',
            'MDCATEnrollmentAdmin',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('mdcat_enrollment_admin_nonce'),
                'i18n'     => [
                    'approve_confirm' => __('Are you sure you want to approve this enrollment? A WordPress account will be created and credentials emailed.', 'mdcat-platform'),
                    'delete_confirm'  => __('Are you sure you want to delete this enrollment request? This action cannot be undone.', 'mdcat-platform'),
                    'approving'       => __('Approving...', 'mdcat-platform'),
                    'rejecting'       => __('Rejecting...', 'mdcat-platform'),
                    'deleting'        => __('Deleting...', 'mdcat-platform'),
                    'no_requests'     => __('No enrollment requests found.', 'mdcat-platform'),
                    'error_generic'   => __('Something went wrong. Please try again.', 'mdcat-platform'),
                ],
            ]
        );
    }
}
