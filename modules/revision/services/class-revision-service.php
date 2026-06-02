<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Revision_Service {

    /**
     * Add a bookmark for a question.
     *
     * @param int $user_id     WordPress user ID.
     * @param int $question_id Question ID.
     * @return array|WP_Error
     */
    public static function add_bookmark( $user_id, $question_id ) {

        global $wpdb;

        $user_id     = absint($user_id);
        $question_id = absint($question_id);

        if (!$user_id || !$question_id) {
            return new WP_Error('invalid_bookmark_request', __('A valid user and question are required.', 'mdcat-platform'));
        }

        if (!self::question_exists($question_id)) {
            return new WP_Error('invalid_question', __('The selected question does not exist.', 'mdcat-platform'));
        }

        if (self::is_bookmarked($user_id, $question_id)) {
            return [
                'question_id'   => $question_id,
                'is_bookmarked' => true,
            ];
        }

        $inserted = $wpdb->insert(
            self::get_bookmarks_table_name(),
            [
                'user_id'     => $user_id,
                'question_id' => $question_id,
                'created_at'  => current_time('mysql'),
            ],
            [
                '%d',
                '%d',
                '%s',
            ]
        );

        if (!$inserted) {
            return new WP_Error('bookmark_create_failed', __('Unable to bookmark this question.', 'mdcat-platform'));
        }

        return [
            'question_id'   => $question_id,
            'is_bookmarked' => true,
        ];
    }

    /**
     * Remove a bookmark for a question.
     *
     * @param int $user_id     WordPress user ID.
     * @param int $question_id Question ID.
     * @return array|WP_Error
     */
    public static function remove_bookmark( $user_id, $question_id ) {

        global $wpdb;

        $user_id     = absint($user_id);
        $question_id = absint($question_id);

        if (!$user_id || !$question_id) {
            return new WP_Error('invalid_bookmark_request', __('A valid user and question are required.', 'mdcat-platform'));
        }

        $wpdb->delete(
            self::get_bookmarks_table_name(),
            [
                'user_id'     => $user_id,
                'question_id' => $question_id,
            ],
            [
                '%d',
                '%d',
            ]
        );

        return [
            'question_id'   => $question_id,
            'is_bookmarked' => false,
        ];
    }

    /**
     * Check whether a user has bookmarked a question.
     *
     * @param int $user_id     WordPress user ID.
     * @param int $question_id Question ID.
     * @return bool
     */
    public static function is_bookmarked( $user_id, $question_id ) {

        global $wpdb;

        $user_id     = absint($user_id);
        $question_id = absint($question_id);

        if (!$user_id || !$question_id) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . self::get_bookmarks_table_name() . " WHERE user_id = %d AND question_id = %d",
                $user_id,
                $question_id
            )
        );
    }

    /**
     * Fetch bookmarked questions for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_bookmarked_questions( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        return self::get_question_dataset($user_id, 'bookmarks');
    }

    /**
     * Fetch dynamically tracked wrong questions for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_wrong_questions( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        return self::get_question_dataset($user_id, 'wrong');
    }

    /**
     * Toggle a bookmark state.
     *
     * @param int $user_id     WordPress user ID.
     * @param int $question_id Question ID.
     * @return array|WP_Error
     */
    public static function toggle_bookmark( $user_id, $question_id ) {

        if (self::is_bookmarked($user_id, $question_id)) {
            return self::remove_bookmark($user_id, $question_id);
        }

        return self::add_bookmark($user_id, $question_id);
    }

    /**
     * Get revision question data for bookmarks or wrong answers.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $type    Dataset type.
     * @return array
     */
    private static function get_question_dataset( $user_id, $type ) {

        global $wpdb;

        $tables = self::get_tables();

        if ('wrong' === $type) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT questions.id AS question_id, questions.question, questions.option_a, questions.option_b,
                        questions.option_c, questions.option_d, questions.correct_option, questions.explanation,
                        questions.difficulty, collections.title AS collection_title, chapters.name AS chapter_title,
                        subjects.name AS subject_title, MAX(answers.answered_at) AS last_seen_at,
                        COUNT(answers.id) AS wrong_count
                    FROM {$tables['answers']} AS answers
                    INNER JOIN {$tables['attempts']} AS attempts ON answers.attempt_id = attempts.id
                    INNER JOIN {$tables['questions']} AS questions ON answers.question_id = questions.id
                    LEFT JOIN {$tables['collections']} AS collections ON questions.collection_id = collections.id
                    LEFT JOIN {$tables['chapters']} AS chapters ON collections.chapter_id = chapters.id
                    LEFT JOIN {$tables['subjects']} AS subjects ON chapters.subject_id = subjects.id
                    WHERE attempts.user_id = %d
                        AND attempts.status = %s
                        AND answers.is_correct = %d
                    GROUP BY questions.id, questions.question, questions.option_a, questions.option_b,
                        questions.option_c, questions.option_d, questions.correct_option, questions.explanation,
                        questions.difficulty, collections.title, chapters.name, subjects.name
                    ORDER BY last_seen_at DESC, wrong_count DESC",
                    $user_id,
                    'completed',
                    0
                )
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT questions.id AS question_id, questions.question, questions.option_a, questions.option_b,
                        questions.option_c, questions.option_d, questions.correct_option, questions.explanation,
                        questions.difficulty, collections.title AS collection_title, chapters.name AS chapter_title,
                        subjects.name AS subject_title, bookmarks.created_at AS bookmarked_at, 0 AS wrong_count
                    FROM {$tables['bookmarks']} AS bookmarks
                    INNER JOIN {$tables['questions']} AS questions ON bookmarks.question_id = questions.id
                    LEFT JOIN {$tables['collections']} AS collections ON questions.collection_id = collections.id
                    LEFT JOIN {$tables['chapters']} AS chapters ON collections.chapter_id = chapters.id
                    LEFT JOIN {$tables['subjects']} AS subjects ON chapters.subject_id = subjects.id
                    WHERE bookmarks.user_id = %d
                    ORDER BY bookmarks.created_at DESC",
                    $user_id
                )
            );
        }

        return self::format_questions($rows);
    }

    /**
     * Format question rows for frontend revision views.
     *
     * @param array $rows Raw database rows.
     * @return array
     */
    private static function format_questions( $rows ) {

        $questions = [];

        foreach ((array) $rows as $row) {
            $questions[] = [
                'question_id'      => absint($row->question_id),
                'question'         => $row->question,
                'options'          => [
                    'a' => $row->option_a,
                    'b' => $row->option_b,
                    'c' => $row->option_c,
                    'd' => $row->option_d,
                ],
                'correct_option'   => sanitize_key($row->correct_option),
                'explanation'      => $row->explanation,
                'difficulty'       => sanitize_key($row->difficulty),
                'collection_title' => $row->collection_title ? $row->collection_title : __('Collection unavailable', 'mdcat-platform'),
                'chapter_title'    => $row->chapter_title ? $row->chapter_title : __('Chapter unavailable', 'mdcat-platform'),
                'subject_title'    => $row->subject_title ? $row->subject_title : __('Subject unavailable', 'mdcat-platform'),
                'wrong_count'      => isset($row->wrong_count) ? absint($row->wrong_count) : 0,
            ];
        }

        return $questions;
    }

    /**
     * Validate question existence before bookmarking.
     *
     * @param int $question_id Question ID.
     * @return bool
     */
    private static function question_exists( $question_id ) {

        global $wpdb;

        $tables = self::get_tables();

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$tables['questions']} WHERE id = %d",
                absint($question_id)
            )
        );
    }

    /**
     * Get bookmarks table name.
     *
     * @return string
     */
    private static function get_bookmarks_table_name() {

        global $wpdb;

        return $wpdb->prefix . 'mdcat_bookmarks';
    }

    /**
     * Get table names used by revision datasets.
     *
     * @return array
     */
    private static function get_tables() {

        global $wpdb;

        return [
            'attempts'    => MDCAT_Platform_Attempts_Handler::get_attempts_table_name(),
            'answers'     => MDCAT_Platform_Attempts_Handler::get_attempt_answers_table_name(),
            'bookmarks'   => self::get_bookmarks_table_name(),
            'questions'   => $wpdb->prefix . 'mdcat_questions',
            'collections' => $wpdb->prefix . 'mdcat_collections',
            'chapters'    => $wpdb->prefix . 'mdcat_chapters',
            'subjects'    => $wpdb->prefix . 'mdcat_subjects',
        ];
    }
}
