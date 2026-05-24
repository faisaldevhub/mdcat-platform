<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Review_Service {

    /**
     * Fetch a completed attempt review for a user.
     *
     * Review data includes correct answers and explanations, so ownership and
     * completion checks live here rather than in frontend or AJAX code.
     *
     * @param int $attempt_id Attempt ID.
     * @param int $user_id    WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_attempt_review( $attempt_id, $user_id ) {

        $attempt_id = absint($attempt_id);
        $user_id    = absint($user_id);

        if (!$attempt_id || !$user_id) {
            return new WP_Error('invalid_review_request', __('A valid attempt and user are required.', 'mdcat-platform'));
        }

        $attempt = self::get_attempt($attempt_id);

        if (!$attempt) {
            return new WP_Error('invalid_attempt', __('The selected attempt does not exist.', 'mdcat-platform'));
        }

        if (absint($attempt->user_id) !== $user_id) {
            return new WP_Error('forbidden_attempt', __('You do not have access to this attempt review.', 'mdcat-platform'));
        }

        if ('completed' !== sanitize_key($attempt->status)) {
            return new WP_Error('attempt_not_completed', __('Only completed attempts can be reviewed.', 'mdcat-platform'));
        }

        return [
            'attempt'    => self::format_attempt($attempt),
            'collection' => self::get_collection_context(absint($attempt->collection_id)),
            'score'      => [
                'score'           => (float) $attempt->score,
                'total_questions' => absint($attempt->total_questions),
                'correct_answers' => absint($attempt->correct_answers),
                'wrong_answers'   => absint($attempt->wrong_answers),
            ],
            'questions'  => self::get_review_questions($attempt_id, absint($attempt->collection_id)),
        ];
    }

    /**
     * Fetch the attempt row used for ownership and completion validation.
     *
     * @param int $attempt_id Attempt ID.
     * @return object|null
     */
    private static function get_attempt( $attempt_id ) {

        global $wpdb;

        $attempts_table = self::get_attempts_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, collection_id, score, total_questions, correct_answers, wrong_answers,
                    status, started_at, completed_at
                FROM {$attempts_table}
                WHERE id = %d",
                $attempt_id
            )
        );
    }

    /**
     * Fetch collection/chapter/subject context for review headers.
     *
     * @param int $collection_id Collection ID.
     * @return array
     */
    private static function get_collection_context( $collection_id ) {

        global $wpdb;

        $collections_table = self::get_collections_table_name();
        $chapters_table    = self::get_chapters_table_name();
        $subjects_table    = self::get_subjects_table_name();

        $context = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT collections.id AS collection_id, collections.title AS collection_title,
                    chapters.id AS chapter_id, chapters.name AS chapter_title,
                    subjects.id AS subject_id, subjects.name AS subject_title
                FROM {$collections_table} AS collections
                LEFT JOIN {$chapters_table} AS chapters ON collections.chapter_id = chapters.id
                LEFT JOIN {$subjects_table} AS subjects ON chapters.subject_id = subjects.id
                WHERE collections.id = %d",
                $collection_id
            )
        );

        if (!$context) {
            return [
                'collection_id'    => $collection_id,
                'collection_title' => __('Collection unavailable', 'mdcat-platform'),
                'chapter_id'       => 0,
                'chapter_title'    => __('Chapter unavailable', 'mdcat-platform'),
                'subject_id'       => 0,
                'subject_title'    => __('Subject unavailable', 'mdcat-platform'),
            ];
        }

        return [
            'collection_id'    => absint($context->collection_id),
            'collection_title' => $context->collection_title ? $context->collection_title : __('Collection unavailable', 'mdcat-platform'),
            'chapter_id'       => absint($context->chapter_id),
            'chapter_title'    => $context->chapter_title ? $context->chapter_title : __('Chapter unavailable', 'mdcat-platform'),
            'subject_id'       => absint($context->subject_id),
            'subject_title'    => $context->subject_title ? $context->subject_title : __('Subject unavailable', 'mdcat-platform'),
        ];
    }

    /**
     * Fetch question review rows and answer state.
     *
     * @param int $attempt_id    Attempt ID.
     * @param int $collection_id Collection ID.
     * @return array
     */
    private static function get_review_questions( $attempt_id, $collection_id ) {

        global $wpdb;

        $questions_table = self::get_questions_table_name();
        $answers_table   = self::get_attempt_answers_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT questions.id AS question_id, questions.question, questions.option_a, questions.option_b,
                    questions.option_c, questions.option_d, questions.correct_option, questions.explanation,
                    questions.difficulty, answers.selected_option, answers.is_correct, answers.answered_at
                FROM {$questions_table} AS questions
                LEFT JOIN {$answers_table} AS answers
                    ON answers.question_id = questions.id
                    AND answers.attempt_id = %d
                WHERE questions.collection_id = %d
                ORDER BY questions.sort_order ASC, questions.id ASC",
                $attempt_id,
                $collection_id
            )
        );

        $questions = [];

        foreach ((array) $rows as $row) {
            $selected_option = sanitize_key($row->selected_option);
            $correct_option  = sanitize_key($row->correct_option);

            $questions[] = [
                'question_id'     => absint($row->question_id),
                'question'        => $row->question,
                'options'         => [
                    'a' => $row->option_a,
                    'b' => $row->option_b,
                    'c' => $row->option_c,
                    'd' => $row->option_d,
                ],
                'selected_option' => $selected_option,
                'correct_option'  => $correct_option,
                'explanation'     => $row->explanation,
                'difficulty'      => sanitize_key($row->difficulty),
                'is_correct'      => (bool) absint($row->is_correct),
                'answered_at'     => $row->answered_at,
            ];
        }

        return $questions;
    }

    /**
     * Format attempt metadata for review consumers.
     *
     * @param object $attempt Attempt row.
     * @return array
     */
    private static function format_attempt( $attempt ) {

        return [
            'attempt_id'    => absint($attempt->id),
            'user_id'       => absint($attempt->user_id),
            'collection_id' => absint($attempt->collection_id),
            'status'        => sanitize_key($attempt->status),
            'started_at'    => $attempt->started_at,
            'completed_at'  => $attempt->completed_at,
        ];
    }

    /**
     * Get attempts table name.
     *
     * @return string
     */
    private static function get_attempts_table_name() {

        return MDCAT_Platform_Attempts_Handler::get_attempts_table_name();
    }

    /**
     * Get attempt answers table name.
     *
     * @return string
     */
    private static function get_attempt_answers_table_name() {

        return MDCAT_Platform_Attempts_Handler::get_attempt_answers_table_name();
    }

    /**
     * Get questions table name.
     *
     * @return string
     */
    private static function get_questions_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_questions';
    }

    /**
     * Get collections table name.
     *
     * @return string
     */
    private static function get_collections_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_collections';
    }

    /**
     * Get chapters table name.
     *
     * @return string
     */
    private static function get_chapters_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_chapters';
    }

    /**
     * Get subjects table name.
     *
     * @return string
     */
    private static function get_subjects_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_subjects';
    }
}
