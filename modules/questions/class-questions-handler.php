<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Questions_Handler {

    /**
     * Register admin form actions for question CRUD.
     */
    public static function init() {

        add_action('admin_post_mdcat_save_question', [__CLASS__, 'save_question']);
        add_action('admin_post_mdcat_delete_question', [__CLASS__, 'delete_question']);
    }

    /**
     * Get the custom questions table name.
     *
     * @return string
     */
    public static function get_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_questions';
    }

    /**
     * Get the custom collections table name.
     *
     * @return string
     */
    public static function get_collections_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_collections';
    }

    /**
     * Get the custom chapters table name.
     *
     * @return string
     */
    public static function get_chapters_table_name() {

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
     * Allowed correct option values.
     *
     * @return array
     */
    public static function get_allowed_correct_options() {

        return [
            'a' => __('A', 'mdcat-platform'),
            'b' => __('B', 'mdcat-platform'),
            'c' => __('C', 'mdcat-platform'),
            'd' => __('D', 'mdcat-platform'),
        ];
    }

    /**
     * Allowed difficulty values.
     *
     * @return array
     */
    public static function get_allowed_difficulties() {

        return [
            'easy'   => __('Easy', 'mdcat-platform'),
            'medium' => __('Medium', 'mdcat-platform'),
            'hard'   => __('Hard', 'mdcat-platform'),
        ];
    }

    /**
     * Allowed status values.
     *
     * @return array
     */
    public static function get_allowed_statuses() {

        return [
            'active'   => __('Active', 'mdcat-platform'),
            'inactive' => __('Inactive', 'mdcat-platform'),
        ];
    }

    /**
     * Fetch questions with collection, chapter, and subject names.
     *
     * @return array
     */
    public static function get_questions() {

        global $wpdb;

        $questions_table   = self::get_table_name();
        $collections_table = self::get_collections_table_name();
        $chapters_table    = self::get_chapters_table_name();
        $subjects_table    = self::get_subjects_table_name();

        return $wpdb->get_results(
            "SELECT questions.id, questions.collection_id, questions.question, questions.correct_option,
                questions.difficulty, questions.marks, questions.sort_order, questions.status, questions.created_at,
                collections.title AS collection_title, chapters.name AS chapter_name, subjects.name AS subject_name
            FROM {$questions_table} AS questions
            LEFT JOIN {$collections_table} AS collections ON questions.collection_id = collections.id
            LEFT JOIN {$chapters_table} AS chapters ON collections.chapter_id = chapters.id
            LEFT JOIN {$subjects_table} AS subjects ON chapters.subject_id = subjects.id
            ORDER BY questions.sort_order ASC, questions.id DESC"
        );
    }

    /**
     * Fetch one question by ID.
     *
     * @param int $question_id Question ID.
     * @return object|null
     */
    public static function get_question( $question_id ) {

        global $wpdb;

        $table_name  = self::get_table_name();
        $question_id = absint($question_id);

        if (!$question_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, collection_id, question, option_a, option_b, option_c, option_d, correct_option,
                    explanation, difficulty, marks, sort_order, status, created_at
                FROM {$table_name}
                WHERE id = %d",
                $question_id
            )
        );
    }

    /**
     * Fetch collections with chapter and subject names for the deep relational dropdown.
     *
     * @return array
     */
    public static function get_collections() {

        global $wpdb;

        $collections_table = self::get_collections_table_name();
        $chapters_table    = self::get_chapters_table_name();
        $subjects_table    = self::get_subjects_table_name();

        return $wpdb->get_results(
            "SELECT collections.id, collections.title AS collection_title,
                chapters.name AS chapter_name, subjects.name AS subject_name
            FROM {$collections_table} AS collections
            LEFT JOIN {$chapters_table} AS chapters ON collections.chapter_id = chapters.id
            LEFT JOIN {$subjects_table} AS subjects ON chapters.subject_id = subjects.id
            ORDER BY subjects.name ASC, chapters.name ASC, collections.title ASC"
        );
    }

    /**
     * Create or update a question from the admin form.
     */
    public static function save_question() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage questions.', 'mdcat-platform'));
        }

        check_admin_referer('mdcat_save_question', 'mdcat_question_nonce');

        $question_id    = isset($_POST['question_id']) ? absint(wp_unslash($_POST['question_id'])) : 0;
        $collection_id  = isset($_POST['collection_id']) ? absint(wp_unslash($_POST['collection_id'])) : 0;
        $question       = isset($_POST['question']) ? sanitize_textarea_field(wp_unslash($_POST['question'])) : '';
        $option_a       = isset($_POST['option_a']) ? sanitize_text_field(wp_unslash($_POST['option_a'])) : '';
        $option_b       = isset($_POST['option_b']) ? sanitize_text_field(wp_unslash($_POST['option_b'])) : '';
        $option_c       = isset($_POST['option_c']) ? sanitize_text_field(wp_unslash($_POST['option_c'])) : '';
        $option_d       = isset($_POST['option_d']) ? sanitize_text_field(wp_unslash($_POST['option_d'])) : '';
        $correct_option = isset($_POST['correct_option']) ? sanitize_key(wp_unslash($_POST['correct_option'])) : '';
        $explanation    = isset($_POST['explanation']) ? sanitize_textarea_field(wp_unslash($_POST['explanation'])) : '';
        $difficulty     = isset($_POST['difficulty']) ? sanitize_key(wp_unslash($_POST['difficulty'])) : '';
        $marks_raw      = isset($_POST['marks']) ? sanitize_text_field(wp_unslash($_POST['marks'])) : '';
        $sort_order     = isset($_POST['sort_order']) ? absint(wp_unslash($_POST['sort_order'])) : 0;
        $status         = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';

        if (!$collection_id || !self::collection_exists($collection_id)) {
            self::redirect_with_message('invalid_collection');
        }

        if (
            '' === $question ||
            '' === $option_a ||
            '' === $option_b ||
            '' === $option_c ||
            '' === $option_d
        ) {
            self::redirect_with_message('missing_required');
        }

        if (!array_key_exists($correct_option, self::get_allowed_correct_options())) {
            self::redirect_with_message('invalid_correct_option');
        }

        if (!array_key_exists($difficulty, self::get_allowed_difficulties())) {
            self::redirect_with_message('invalid_difficulty');
        }

        if ('' === $marks_raw || !is_numeric($marks_raw)) {
            self::redirect_with_message('invalid_marks');
        }

        if (!array_key_exists($status, self::get_allowed_statuses())) {
            self::redirect_with_message('invalid_status');
        }

        $marks = (float) $marks_raw;

        if ($question_id) {
            self::update_question(
                $question_id,
                $collection_id,
                $question,
                $option_a,
                $option_b,
                $option_c,
                $option_d,
                $correct_option,
                $explanation,
                $difficulty,
                $marks,
                $sort_order,
                $status
            );
            self::redirect_with_message('updated');
        }

        self::create_question(
            $collection_id,
            $question,
            $option_a,
            $option_b,
            $option_c,
            $option_d,
            $correct_option,
            $explanation,
            $difficulty,
            $marks,
            $sort_order,
            $status
        );
        self::redirect_with_message('created');
    }

    /**
     * Delete a question after nonce and capability checks.
     */
    public static function delete_question() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete questions.', 'mdcat-platform'));
        }

        $question_id = isset($_GET['question_id']) ? absint(wp_unslash($_GET['question_id'])) : 0;

        check_admin_referer('mdcat_delete_question_' . $question_id);

        if ($question_id) {
            global $wpdb;

            $wpdb->delete(
                self::get_table_name(),
                ['id' => $question_id],
                ['%d']
            );
        }

        self::redirect_with_message('deleted');
    }

    /**
     * Validate that a selected collection exists before saving a question.
     *
     * @param int $collection_id Collection ID.
     * @return bool
     */
    private static function collection_exists( $collection_id ) {

        global $wpdb;

        $collections_table = self::get_collections_table_name();
        $collection_id     = absint($collection_id);

        if (!$collection_id) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id) FROM {$collections_table} WHERE id = %d",
                $collection_id
            )
        );
    }

    /**
     * Insert a question row.
     */
    private static function create_question(
        $collection_id,
        $question,
        $option_a,
        $option_b,
        $option_c,
        $option_d,
        $correct_option,
        $explanation,
        $difficulty,
        $marks,
        $sort_order,
        $status
    ) {

        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            [
                'collection_id'  => $collection_id,
                'question'       => $question,
                'option_a'       => $option_a,
                'option_b'       => $option_b,
                'option_c'       => $option_c,
                'option_d'       => $option_d,
                'correct_option' => $correct_option,
                'explanation'    => $explanation,
                'difficulty'     => $difficulty,
                'marks'          => $marks,
                'sort_order'     => $sort_order,
                'status'         => $status,
                'created_at'     => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%d',
                '%s',
                '%s',
            ]
        );
    }

    /**
     * Update a question row.
     */
    private static function update_question(
        $question_id,
        $collection_id,
        $question,
        $option_a,
        $option_b,
        $option_c,
        $option_d,
        $correct_option,
        $explanation,
        $difficulty,
        $marks,
        $sort_order,
        $status
    ) {

        global $wpdb;

        $wpdb->update(
            self::get_table_name(),
            [
                'collection_id'  => $collection_id,
                'question'       => $question,
                'option_a'       => $option_a,
                'option_b'       => $option_b,
                'option_c'       => $option_c,
                'option_d'       => $option_d,
                'correct_option' => $correct_option,
                'explanation'    => $explanation,
                'difficulty'     => $difficulty,
                'marks'          => $marks,
                'sort_order'     => $sort_order,
                'status'         => $status,
            ],
            ['id' => $question_id],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%d',
                '%s',
            ],
            ['%d']
        );
    }

    /**
     * Redirect back to the questions page with a status message.
     *
     * @param string $message Message key.
     */
    private static function redirect_with_message( $message ) {

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'          => 'mdcat-questions',
                    'mdcat_message' => sanitize_key($message),
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
