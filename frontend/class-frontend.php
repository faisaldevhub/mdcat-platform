<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Frontend {

    /**
     * Register frontend shortcode and assets.
     */
    public static function init() {

        add_shortcode('mdcat_dashboard', [__CLASS__, 'render_dashboard_shortcode']);
        add_shortcode('mdcat_streak', [__CLASS__, 'render_streak_shortcode']);
        add_shortcode('mdcat_quiz', [__CLASS__, 'render_quiz_shortcode']);
        add_shortcode('mdcat_attempt_history', [__CLASS__, 'render_attempt_history_shortcode']);
        add_shortcode('mdcat_performance', [__CLASS__, 'render_performance_shortcode']);
        add_shortcode('mdcat_bookmarks', [__CLASS__, 'render_bookmarks_shortcode']);
        add_shortcode('mdcat_wrong_questions', [__CLASS__, 'render_wrong_questions_shortcode']);
        add_shortcode('mdcat_subject_progress', [__CLASS__, 'render_subject_progress_shortcode']);
        add_shortcode('mdcat_enrollment_form', [__CLASS__, 'render_enrollment_form_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
    }

    /**
     * Register quiz frontend assets. The script is enqueued only when the shortcode renders.
     */
    public static function register_assets() {

        wp_register_style(
            'mdcat-quiz-engine',
            MDCAT_PLATFORM_URL . 'assets/css/quiz-engine.css',
            [],
            MDCAT_PLATFORM_VERSION
        );

        wp_register_script(
            'mdcat-quiz-engine',
            MDCAT_PLATFORM_URL . 'assets/js/quiz-engine.js',
            [],
            MDCAT_PLATFORM_VERSION,
            true
        );

        wp_localize_script(
            'mdcat-quiz-engine',
            'MDCATQuiz',
            [
                'ajax_url'        => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('mdcat_quiz_nonce'),
                'current_user_id' => get_current_user_id(),
                'is_logged_in'    => is_user_logged_in(),
                'login_url'       => wp_login_url(),
                'i18n'            => [
                    'start_quiz'        => __('Start Quiz', 'mdcat-platform'),
                    'loading'           => __('Loading...', 'mdcat-platform'),
                    'select_answer'     => __('Please select an answer.', 'mdcat-platform'),
                    'correct'           => __('Correct', 'mdcat-platform'),
                    'wrong'             => __('Wrong', 'mdcat-platform'),
                    'quiz_complete'     => __('Quiz Complete', 'mdcat-platform'),
                    'login_required'    => __('Please log in to start this quiz.', 'mdcat-platform'),
                    'missing_collection' => __('Quiz collection is not configured.', 'mdcat-platform'),
                    'request_failed'    => __('Something went wrong. Please try again.', 'mdcat-platform'),
                    'question_of'       => __('Question %1$d of %2$d', 'mdcat-platform'),
                    'history_empty'     => __('No completed attempts found.', 'mdcat-platform'),
                    'review_answers'    => __('Review Answers', 'mdcat-platform'),
                    'review_title'      => __('Answer Review', 'mdcat-platform'),
                    'your_answer'       => __('Your answer', 'mdcat-platform'),
                    'correct_answer'    => __('Correct answer', 'mdcat-platform'),
                    'explanation'       => __('Explanation', 'mdcat-platform'),
                    'analytics_empty'   => __('No performance data available yet.', 'mdcat-platform'),
                    'bookmark'          => __('Bookmark', 'mdcat-platform'),
                    'bookmarked'        => __('Bookmarked', 'mdcat-platform'),
                    'bookmarks_empty'   => __('No bookmarked questions yet.', 'mdcat-platform'),
                    'wrong_empty'       => __('No wrong questions found yet.', 'mdcat-platform'),
                    'dashboard_welcome'         => __('Welcome back!', 'mdcat-platform'),
                    'dashboard_empty'           => __('No dashboard data available yet. Start practicing to see your progress!', 'mdcat-platform'),
                    'dashboard_total_attempts'  => __('Total Attempts', 'mdcat-platform'),
                    'dashboard_accuracy'        => __('Accuracy', 'mdcat-platform'),
                    'dashboard_correct'         => __('Correct Answers', 'mdcat-platform'),
                    'dashboard_wrong'           => __('Wrong Answers', 'mdcat-platform'),
                    'dashboard_bookmarks'       => __('Bookmarks', 'mdcat-platform'),
                    'dashboard_weak_topics'     => __('Weak Topics', 'mdcat-platform'),
                    'dashboard_performance'     => __('Performance Snapshot', 'mdcat-platform'),
                    'dashboard_strong'          => __('Strong Subjects', 'mdcat-platform'),
                    'dashboard_weak'            => __('Weak Subjects', 'mdcat-platform'),
                    'dashboard_no_strong'       => __('Keep practicing to build strong subjects.', 'mdcat-platform'),
                    'dashboard_no_weak'         => __('Great job! No weak subjects detected.', 'mdcat-platform'),
                    'dashboard_quick_actions'   => __('Quick Actions', 'mdcat-platform'),
                    'dashboard_continue'        => __('Continue Practice', 'mdcat-platform'),
                    'dashboard_my_bookmarks'    => __('My Bookmarks', 'mdcat-platform'),
                    'dashboard_wrong_questions' => __('Wrong Questions', 'mdcat-platform'),
                    'dashboard_attempt_history' => __('Attempt History', 'mdcat-platform'),
                    'dashboard_analytics'       => __('Performance Analytics', 'mdcat-platform'),
                    'dashboard_recent'          => __('Recent Activity', 'mdcat-platform'),
                    'dashboard_no_activity'     => __('No recent activity yet.', 'mdcat-platform'),
                    'dashboard_subject'         => __('Subject', 'mdcat-platform'),
                    'dashboard_chapter'         => __('Chapter', 'mdcat-platform'),
                    'dashboard_quiz'            => __('Quiz', 'mdcat-platform'),
                    'dashboard_score'           => __('Score', 'mdcat-platform'),
                    'dashboard_date'            => __('Date', 'mdcat-platform'),
                    'streak_title'              => __('Study Streak', 'mdcat-platform'),
                    'streak_current'            => __('Current Streak', 'mdcat-platform'),
                    'streak_longest'            => __('Longest Streak', 'mdcat-platform'),
                    'streak_last_active'        => __('Last Active', 'mdcat-platform'),
                    'streak_total_days'         => __('Total Active Days', 'mdcat-platform'),
                    'streak_days'               => __('days', 'mdcat-platform'),
                    'streak_empty'              => __('Complete a quiz to start your streak!', 'mdcat-platform'),
                    'streak_today'              => __('Today', 'mdcat-platform'),
                    'streak_yesterday'          => __('Yesterday', 'mdcat-platform'),
                    'streak_never'              => __('No activity yet', 'mdcat-platform'),
                    'access_login_required'     => __('Login Required', 'mdcat-platform'),
                    'access_login_message'      => __('Please log in to access this content.', 'mdcat-platform'),
                    'access_login_button'       => __('Log In', 'mdcat-platform'),
                    'access_denied'             => __('Access Restricted', 'mdcat-platform'),
                    'progress_title'            => __('Subject Progress', 'mdcat-platform'),
                    'progress_empty'            => __('No subjects available yet.', 'mdcat-platform'),
                    'progress_completed'        => __('completed', 'mdcat-platform'),
                    'progress_collections'      => __('collections', 'mdcat-platform'),
                    'progress_of'               => __('of', 'mdcat-platform'),
                    'chapter_progress_title'    => __('Chapter Progress', 'mdcat-platform'),
                    'chapter_progress_empty'    => __('No chapters available yet.', 'mdcat-platform'),
                    'overall_progress_title'    => __('Overall Progress', 'mdcat-platform'),
                    'overall_progress_label'    => __('Curriculum Completed', 'mdcat-platform'),
                    'continue_title'            => __('Continue Learning', 'mdcat-platform'),
                    'continue_subject'          => __('Subject', 'mdcat-platform'),
                    'continue_chapter'          => __('Chapter', 'mdcat-platform'),
                    'continue_next'             => __('Next Quiz', 'mdcat-platform'),
                    'continue_resume'           => __('Resume Learning', 'mdcat-platform'),
                    'continue_completed'        => __('Curriculum Completed', 'mdcat-platform'),
                    'continue_completed_msg'    => __('Congratulations! You have completed the entire curriculum.', 'mdcat-platform'),
                    'continue_review'           => __('Review Quizzes', 'mdcat-platform'),
                ],
            ]
        );

        if (self::page_has_quiz_shortcode()) {
            self::enqueue_quiz_assets();
        }
    }

    /**
     * Render the student dashboard container.
     *
     * The HTML shell provides mounting points for all four dashboard sections.
     * The JavaScript controller populates these with live data via AJAX.
     *
     * @return string
     */
    public static function render_dashboard_shortcode() {

        $guard = MDCAT_Platform_Access_Middleware::require_login();

        if (true !== $guard) {
            return $guard;
        }

        self::enqueue_quiz_assets();

        ob_start();
        ?>
        <div class="mdcat-dashboard">
            <div class="mdcat-dashboard__loading">
                <?php esc_html_e('Loading your dashboard...', 'mdcat-platform'); ?>
            </div>

            <div class="mdcat-dashboard__message" hidden></div>

            <div class="mdcat-dashboard__content" hidden>

                <div class="mdcat-dashboard__progress-hub">

                    <section class="mdcat-dashboard__section mdcat-dashboard__section--overall-progress">
                        <div class="mdcat-dashboard__overall-progress"></div>
                    </section>

                    <section class="mdcat-dashboard__section mdcat-dashboard__section--continue-learning">
                        <div class="mdcat-dashboard__continue-learning"></div>
                    </section>

                    <section class="mdcat-dashboard__section mdcat-dashboard__section--progress">
                        <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Subject Progress', 'mdcat-platform'); ?></h2>
                        <div class="mdcat-dashboard__progress"></div>
                    </section>

                    <section class="mdcat-dashboard__section mdcat-dashboard__section--chapter-progress">
                        <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Chapter Progress', 'mdcat-platform'); ?></h2>
                        <div class="mdcat-dashboard__chapter-progress"></div>
                    </section>

                </div>

                <section class="mdcat-dashboard__section mdcat-dashboard__section--stats">
                    <div class="mdcat-dashboard__stats-grid"></div>
                </section>

                <section class="mdcat-dashboard__section mdcat-dashboard__section--xp">
                    <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Experience Points', 'mdcat-platform'); ?></h2>
                    <div class="mdcat-dashboard__xp-widget"></div>
                </section>

                <section class="mdcat-dashboard__section mdcat-dashboard__section--streak">
                    <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Study Streak', 'mdcat-platform'); ?></h2>
                    <div class="mdcat-dashboard__streak"></div>
                </section>

                <section class="mdcat-dashboard__section mdcat-dashboard__section--badges">
                    <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Badges', 'mdcat-platform'); ?></h2>
                    <div class="mdcat-dashboard__badge-showcase"></div>
                </section>

                <section class="mdcat-dashboard__section mdcat-dashboard__section--snapshot">
                    <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Performance Snapshot', 'mdcat-platform'); ?></h2>
                    <div class="mdcat-dashboard__snapshot"></div>
                </section>

                <section class="mdcat-dashboard__section mdcat-dashboard__section--leaderboard">
                    <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Leaderboard', 'mdcat-platform'); ?></h2>
                    <div class="mdcat-dashboard__leaderboard-widget"></div>
                </section>

                <section class="mdcat-dashboard__section mdcat-dashboard__section--actions">
                    <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Quick Actions', 'mdcat-platform'); ?></h2>
                    <div class="mdcat-dashboard__actions-grid"></div>
                </section>

                <section class="mdcat-dashboard__section mdcat-dashboard__section--activity">
                    <h2 class="mdcat-dashboard__section-title"><?php esc_html_e('Recent Activity', 'mdcat-platform'); ?></h2>
                    <div class="mdcat-dashboard__activity"></div>
                </section>

            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render the standalone streak widget.
     *
     * Provides a self-contained streak display that can be placed on any page.
     * The JavaScript controller fetches data independently from the dashboard.
     *
     * @return string
     */
    public static function render_streak_shortcode() {

        $guard = MDCAT_Platform_Access_Middleware::require_login();

        if (true !== $guard) {
            return $guard;
        }

        self::enqueue_quiz_assets();

        ob_start();
        ?>
        <div class="mdcat-streak">
            <div class="mdcat-streak__loading">
                <?php esc_html_e('Loading streak data...', 'mdcat-platform'); ?>
            </div>

            <div class="mdcat-streak__message" hidden></div>

            <div class="mdcat-streak__content" hidden>
                <div class="mdcat-streak__cards-grid"></div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render the base quiz container.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_quiz_shortcode( $atts ) {

        $atts = shortcode_atts(
            [
                'collection_id' => 0,
            ],
            $atts,
            'mdcat_quiz'
        );

        $collection_id = absint($atts['collection_id']);

        $guard = MDCAT_Platform_Access_Middleware::require_quiz_access($collection_id);

        if (true !== $guard) {
            return $guard;
        }

        self::enqueue_quiz_assets();

        ob_start();
        ?>
        <div class="mdcat-quiz" data-collection-id="<?php echo esc_attr($collection_id); ?>">
            <div class="mdcat-quiz__header">
                <div class="mdcat-quiz__timer" aria-live="polite"></div>
                <div class="mdcat-quiz__progress" aria-live="polite"></div>
            </div>

            <div class="mdcat-quiz__message" aria-live="polite"></div>

            <div class="mdcat-quiz__start">
                <button type="button" class="button mdcat-quiz__start-button">
                    <?php esc_html_e('Start Quiz', 'mdcat-platform'); ?>
                </button>
            </div>

            <div class="mdcat-quiz__loading" hidden>
                <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
            </div>

            <div class="mdcat-quiz__question" hidden></div>

            <div class="mdcat-quiz__result" hidden></div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render the student attempt history container.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_attempt_history_shortcode( $atts ) {

        $guard = MDCAT_Platform_Access_Middleware::require_login();

        if (true !== $guard) {
            return $guard;
        }

        $atts = shortcode_atts(
            [
                'per_page' => 20,
            ],
            $atts,
            'mdcat_attempt_history'
        );

        self::enqueue_quiz_assets();

        ob_start();
        ?>
        <div class="mdcat-attempt-history" data-per-page="<?php echo esc_attr(absint($atts['per_page'])); ?>">
            <div class="mdcat-attempt-history__loading">
                <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
            </div>

            <div class="mdcat-attempt-history__message" hidden></div>

            <div class="mdcat-attempt-history__table-wrap" hidden>
                <table class="mdcat-attempt-history__table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Subject', 'mdcat-platform'); ?></th>
                            <th scope="col"><?php esc_html_e('Chapter', 'mdcat-platform'); ?></th>
                            <th scope="col"><?php esc_html_e('Collection', 'mdcat-platform'); ?></th>
                            <th scope="col"><?php esc_html_e('Score', 'mdcat-platform'); ?></th>
                            <th scope="col"><?php esc_html_e('Correct', 'mdcat-platform'); ?></th>
                            <th scope="col"><?php esc_html_e('Wrong', 'mdcat-platform'); ?></th>
                            <th scope="col"><?php esc_html_e('Date', 'mdcat-platform'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render the student performance analytics container.
     *
     * @return string
     */
    public static function render_performance_shortcode() {

        $guard = MDCAT_Platform_Access_Middleware::require_login();

        if (true !== $guard) {
            return $guard;
        }

        self::enqueue_quiz_assets();

        ob_start();
        ?>
        <div class="mdcat-performance">
            <div class="mdcat-performance__loading">
                <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
            </div>

            <div class="mdcat-performance__message" hidden></div>

            <div class="mdcat-performance__content" hidden>
                <section class="mdcat-performance__section">
                    <h3><?php esc_html_e('Subject Performance', 'mdcat-platform'); ?></h3>
                    <div class="mdcat-performance__table-wrap">
                        <table class="mdcat-performance__table mdcat-performance__subject-table">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e('Subject', 'mdcat-platform'); ?></th>
                                    <th scope="col"><?php esc_html_e('Accuracy', 'mdcat-platform'); ?></th>
                                    <th scope="col"><?php esc_html_e('Correct', 'mdcat-platform'); ?></th>
                                    <th scope="col"><?php esc_html_e('Wrong', 'mdcat-platform'); ?></th>
                                    <th scope="col"><?php esc_html_e('Total', 'mdcat-platform'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </section>

                <section class="mdcat-performance__section">
                    <h3><?php esc_html_e('Chapter Performance', 'mdcat-platform'); ?></h3>
                    <div class="mdcat-performance__table-wrap">
                        <table class="mdcat-performance__table mdcat-performance__chapter-table">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e('Subject', 'mdcat-platform'); ?></th>
                                    <th scope="col"><?php esc_html_e('Chapter', 'mdcat-platform'); ?></th>
                                    <th scope="col"><?php esc_html_e('Accuracy', 'mdcat-platform'); ?></th>
                                    <th scope="col"><?php esc_html_e('Performance Label', 'mdcat-platform'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render bookmarked questions revision container.
     *
     * @return string
     */
    public static function render_bookmarks_shortcode() {

        $guard = MDCAT_Platform_Access_Middleware::require_login();

        if (true !== $guard) {
            return $guard;
        }

        return self::render_revision_list('bookmarks');
    }

    /**
     * Render wrong questions revision container.
     *
     * @return string
     */
    public static function render_wrong_questions_shortcode() {

        $guard = MDCAT_Platform_Access_Middleware::require_login();

        if (true !== $guard) {
            return $guard;
        }

        return self::render_revision_list('wrong');
    }

    /**
     * Render the standalone subject progress container.
     *
     * Shows completion percentages per subject with progress bars.
     * The JavaScript controller fetches data independently via AJAX.
     *
     * @return string
     */
    public static function render_subject_progress_shortcode() {

        $guard = MDCAT_Platform_Access_Middleware::require_login();

        if (true !== $guard) {
            return $guard;
        }

        self::enqueue_quiz_assets();

        ob_start();
        ?>
        <div class="mdcat-subject-progress">
            <div class="mdcat-subject-progress__loading">
                <?php esc_html_e('Loading progress data...', 'mdcat-platform'); ?>
            </div>

            <div class="mdcat-subject-progress__message" hidden></div>

            <div class="mdcat-subject-progress__content" hidden>
                <div class="mdcat-subject-progress__list"></div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Enqueue the quiz assets together.
     */
    private static function enqueue_quiz_assets() {

        wp_enqueue_style('mdcat-quiz-engine');
        wp_enqueue_script('mdcat-quiz-engine');
    }

    /**
     * Render a revision question list container.
     *
     * @param string $type Revision list type.
     * @return string
     */
    private static function render_revision_list( $type ) {

        $type = sanitize_key($type);

        self::enqueue_quiz_assets();

        ob_start();
        ?>
        <div class="mdcat-revision-list" data-revision-type="<?php echo esc_attr($type); ?>">
            <div class="mdcat-revision-list__loading">
                <?php esc_html_e('Loading...', 'mdcat-platform'); ?>
            </div>

            <div class="mdcat-revision-list__message" hidden></div>

            <div class="mdcat-revision-list__items" hidden></div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Detect shortcode usage early enough for styles to load in the document head.
     *
     * @return bool
     */
    private static function page_has_quiz_shortcode() {

        global $post;

        if (!$post || empty($post->post_content)) {
            return false;
        }

        return has_shortcode($post->post_content, 'mdcat_dashboard') || has_shortcode($post->post_content, 'mdcat_streak') || has_shortcode($post->post_content, 'mdcat_quiz') || has_shortcode($post->post_content, 'mdcat_attempt_history') || has_shortcode($post->post_content, 'mdcat_performance') || has_shortcode($post->post_content, 'mdcat_bookmarks') || has_shortcode($post->post_content, 'mdcat_wrong_questions') || has_shortcode($post->post_content, 'mdcat_subject_progress') || has_shortcode($post->post_content, 'mdcat_enrollment_form');
    }

    /**
     * Render the enrollment form shortcode.
     *
     * Delegates to the enrollment form view class which handles
     * guest detection, form rendering, and asset enqueuing.
     *
     * @return string HTML output.
     */
    public static function render_enrollment_form_shortcode() {

        if (!class_exists('MDCAT_Platform_Enrollment_Form_View')) {
            return '';
        }

        return MDCAT_Platform_Enrollment_Form_View::render();
    }
}
