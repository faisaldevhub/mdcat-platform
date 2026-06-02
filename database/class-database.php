<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Database {

    public static function create_tables() {

        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        /**
         * Subjects Table
         */

        $subjects_table = $wpdb->prefix . 'mdcat_subjects';

        $sql_subjects = "CREATE TABLE $subjects_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id)

        ) $charset_collate;";

        dbDelta($sql_subjects);

        /**
         * Chapters Table
         */

        $chapters_table = $wpdb->prefix . 'mdcat_chapters';

        $sql_chapters = "CREATE TABLE $chapters_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subject_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id)

        ) $charset_collate;";

        dbDelta($sql_chapters);

        /**
         * Collections Table
         */

        $collections_table = $wpdb->prefix . 'mdcat_collections';

        $sql_collections = "CREATE TABLE $collections_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chapter_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            description TEXT NULL,
            sort_order INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id)

        ) $charset_collate;";

        dbDelta($sql_collections);

        /**
         * Quizzes Table
         */

        $quizzes_table = $wpdb->prefix . 'mdcat_quizzes';

        $sql_quizzes = "CREATE TABLE $quizzes_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chapter_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            total_questions INT DEFAULT 0,
            timer_minutes INT DEFAULT 30,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id)

        ) $charset_collate;";

        dbDelta($sql_quizzes);

        /**
         * Questions Table
         */

        $questions_table = $wpdb->prefix . 'mdcat_questions';

        $sql_questions = "CREATE TABLE $questions_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            collection_id BIGINT UNSIGNED NOT NULL,
            question TEXT NOT NULL,
            option_a TEXT NOT NULL,
            option_b TEXT NOT NULL,
            option_c TEXT NOT NULL,
            option_d TEXT NOT NULL,
            correct_option VARCHAR(1) NOT NULL,
            explanation TEXT NULL,
            difficulty VARCHAR(20) DEFAULT 'easy',
            marks DECIMAL(8,2) DEFAULT 1.00,
            sort_order INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id)

        ) $charset_collate;";

        dbDelta($sql_questions);

        /**
         * Attempts Table
         */

        $attempts_table = $wpdb->prefix . 'mdcat_attempts';

        $sql_attempts = "CREATE TABLE $attempts_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            collection_id BIGINT UNSIGNED NOT NULL,
            score DECIMAL(10,2) DEFAULT 0.00,
            total_questions INT UNSIGNED DEFAULT 0,
            correct_answers INT UNSIGNED DEFAULT 0,
            wrong_answers INT UNSIGNED DEFAULT 0,
            time_taken INT UNSIGNED DEFAULT 0,
            status VARCHAR(20) DEFAULT 'in_progress',
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,

            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY collection_id (collection_id),
            KEY status (status),
            KEY user_status (user_id, status),
            KEY completed_at (completed_at)

        ) $charset_collate;";

        dbDelta($sql_attempts);

        /**
         * Attempt Answers Table
         */

        $attempt_answers_table = $wpdb->prefix . 'mdcat_attempt_answers';

        $sql_attempt_answers = "CREATE TABLE $attempt_answers_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            selected_option VARCHAR(1) NOT NULL,
            is_correct TINYINT(1) DEFAULT 0,
            answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY attempt_id (attempt_id),
            KEY question_id (question_id),
            KEY is_correct (is_correct),
            KEY attempt_question (attempt_id, question_id)

        ) $charset_collate;";

        dbDelta($sql_attempt_answers);

        /**
         * Bookmarks Table
         */

        $bookmarks_table = $wpdb->prefix . 'mdcat_bookmarks';

        $sql_bookmarks = "CREATE TABLE $bookmarks_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY user_question (user_id, question_id),
            KEY user_id (user_id),
            KEY question_id (question_id),
            KEY created_at (created_at)

        ) $charset_collate;";

        dbDelta($sql_bookmarks);

        /**
         * Daily Activity Table (Gamification)
         *
         * Stores one row per user per calendar date. The unique constraint
         * on (user_id, activity_date) enables safe INSERT ... ON DUPLICATE
         * KEY UPDATE operations for atomic activity recording.
         */

        $daily_activity_table = $wpdb->prefix . 'mdcat_daily_activity';

        $sql_daily_activity = "CREATE TABLE $daily_activity_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            activity_date DATE NOT NULL,
            attempts_count INT UNSIGNED DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY user_date (user_id, activity_date),
            KEY user_id (user_id),
            KEY activity_date (activity_date)

        ) $charset_collate;";

        dbDelta($sql_daily_activity);

    }
}
