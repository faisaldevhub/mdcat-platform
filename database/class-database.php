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

    }
}
