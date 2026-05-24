<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Frontend {

    /**
     * Register frontend shortcode and assets.
     */
    public static function init() {

        add_shortcode('mdcat_quiz', [__CLASS__, 'render_quiz_shortcode']);
        add_shortcode('mdcat_attempt_history', [__CLASS__, 'render_attempt_history_shortcode']);
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
                ],
            ]
        );

        if (self::page_has_quiz_shortcode()) {
            self::enqueue_quiz_assets();
        }
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
     * Enqueue the quiz assets together.
     */
    private static function enqueue_quiz_assets() {

        wp_enqueue_style('mdcat-quiz-engine');
        wp_enqueue_script('mdcat-quiz-engine');
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

        return has_shortcode($post->post_content, 'mdcat_quiz') || has_shortcode($post->post_content, 'mdcat_attempt_history');
    }
}
