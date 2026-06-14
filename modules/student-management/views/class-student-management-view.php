<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Student_Management_View {

    /**
     * Render the student management admin page.
     *
     * Routes between the directory view and the profile view based
     * on the presence of a student_id query parameter. Profile is
     * rendered as a separate page (not a modal) per design decision.
     *
     * Enqueues admin-scoped CSS/JS assets and outputs the HTML shell.
     * All data is populated via AJAX calls from the JavaScript controller.
     */
    public static function render() {

        self::enqueue_assets();

        $student_id = isset($_GET['student_id']) ? absint($_GET['student_id']) : 0;

        if ($student_id) {
            self::render_profile_page($student_id);
        } else {
            self::render_directory_page();
        }
    }

    /**
     * Render the student directory page.
     */
    private static function render_directory_page() {

        ?>
        <div class="wrap mdcat-students" id="mdcat-students-app" data-view="directory">

            <h1 class="mdcat-students__title">
                <?php esc_html_e('Student Management', 'mdcat-platform'); ?>
            </h1>

            <p class="mdcat-students__subtitle">
                <?php esc_html_e('View, search, and manage student accounts, progress, and activity.', 'mdcat-platform'); ?>
            </p>

            <!-- Search and Filters -->
            <div class="mdcat-students__toolbar">

                <div class="mdcat-students__search-wrap">
                    <input
                        type="text"
                        id="mdcat-student-search"
                        class="mdcat-students__search"
                        placeholder="<?php esc_attr_e('Search by name or email...', 'mdcat-platform'); ?>"
                    />
                </div>

                <div class="mdcat-students__filters">
                    <select id="mdcat-student-status-filter" class="mdcat-students__filter">
                        <option value="all"><?php esc_html_e('All Status', 'mdcat-platform'); ?></option>
                        <option value="active"><?php esc_html_e('Active', 'mdcat-platform'); ?></option>
                        <option value="suspended"><?php esc_html_e('Suspended', 'mdcat-platform'); ?></option>
                    </select>

                    <select id="mdcat-student-sort" class="mdcat-students__filter">
                        <option value="registered"><?php esc_html_e('Sort: Registration Date', 'mdcat-platform'); ?></option>
                        <option value="name"><?php esc_html_e('Sort: Name', 'mdcat-platform'); ?></option>
                        <option value="attempts"><?php esc_html_e('Sort: Attempts', 'mdcat-platform'); ?></option>
                        <option value="last_activity"><?php esc_html_e('Sort: Last Activity', 'mdcat-platform'); ?></option>
                    </select>
                </div>

            </div>

            <!-- Student Directory Table -->
            <div class="mdcat-students__directory" id="mdcat-students-directory">
                <div class="mdcat-students__loading">
                    <?php esc_html_e('Loading students...', 'mdcat-platform'); ?>
                </div>
            </div>

            <!-- Pagination -->
            <div class="mdcat-students__pagination" id="mdcat-students-pagination"></div>

        </div>
        <?php
    }

    /**
     * Render the student profile page (separate page view).
     *
     * @param int $student_id WordPress user ID.
     */
    private static function render_profile_page( $student_id ) {

        $back_url = admin_url('admin.php?page=mdcat-students');

        ?>
        <div class="wrap mdcat-students" id="mdcat-students-app" data-view="profile" data-student-id="<?php echo esc_attr($student_id); ?>">

            <div class="mdcat-students__profile-header">
                <a href="<?php echo esc_url($back_url); ?>" class="mdcat-students__back-link">
                    &larr; <?php esc_html_e('Back to Student Directory', 'mdcat-platform'); ?>
                </a>
            </div>

            <!-- Profile Overview -->
            <div class="mdcat-students__profile-overview" id="mdcat-student-overview">
                <div class="mdcat-students__loading">
                    <?php esc_html_e('Loading student profile...', 'mdcat-platform'); ?>
                </div>
            </div>

            <!-- Profile Tabs -->
            <div class="mdcat-students__profile-tabs" id="mdcat-student-tabs">
                <button class="mdcat-students__tab mdcat-students__tab--active" data-tab="progress">
                    <?php esc_html_e('Progress', 'mdcat-platform'); ?>
                </button>
                <button class="mdcat-students__tab" data-tab="analytics">
                    <?php esc_html_e('Analytics', 'mdcat-platform'); ?>
                </button>
                <button class="mdcat-students__tab" data-tab="activity">
                    <?php esc_html_e('Activity', 'mdcat-platform'); ?>
                </button>
                <button class="mdcat-students__tab" data-tab="enrollment">
                    <?php esc_html_e('Enrollment', 'mdcat-platform'); ?>
                </button>
            </div>

            <!-- Tab Content Panels -->
            <div class="mdcat-students__tab-content" id="mdcat-student-tab-content">
                <div class="mdcat-students__loading">
                    <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
                </div>
            </div>

            <!-- Attempt History (loaded separately with pagination) -->
            <div class="mdcat-students__attempts-pagination" id="mdcat-student-attempts-pagination" style="display:none;"></div>

        </div>
        <?php
    }

    /**
     * Enqueue admin student management assets.
     *
     * Registers and enqueues CSS/JS only on the student management page.
     * Localizes the script with AJAX URL, nonce, and i18n strings.
     */
    private static function enqueue_assets() {

        wp_enqueue_style(
            'mdcat-student-management',
            MDCAT_PLATFORM_URL . 'assets/css/student-management.css',
            [],
            MDCAT_PLATFORM_VERSION
        );

        wp_enqueue_script(
            'mdcat-student-management',
            MDCAT_PLATFORM_URL . 'assets/js/student-management.js',
            [],
            MDCAT_PLATFORM_VERSION,
            true
        );

        wp_localize_script(
            'mdcat-student-management',
            'MDCATStudentManagement',
            [
                'ajax_url'     => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('mdcat_student_management_nonce'),
                'students_url' => admin_url('admin.php?page=mdcat-students'),
                'i18n'         => [
                    'suspend_confirm'  => __('Are you sure you want to suspend this student? They will lose access to quizzes and the dashboard.', 'mdcat-platform'),
                    'activate_confirm' => __('Are you sure you want to activate this student?', 'mdcat-platform'),
                    'no_students'      => __('No students found.', 'mdcat-platform'),
                    'no_attempts'      => __('No quiz attempts recorded yet.', 'mdcat-platform'),
                    'no_enrollment'    => __('No enrollment record found for this student.', 'mdcat-platform'),
                    'error_generic'    => __('Something went wrong. Please try again.', 'mdcat-platform'),
                    'loading'          => __('Loading...', 'mdcat-platform'),
                    'suspending'       => __('Suspending...', 'mdcat-platform'),
                    'activating'       => __('Activating...', 'mdcat-platform'),
                ],
            ]
        );
    }
}
