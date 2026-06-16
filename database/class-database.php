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

            PRIMARY KEY (id),
            KEY collection_id (collection_id)

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

        /**
         * Enrollment Requests Table
         *
         * Stores student enrollment requests submitted via the public
         * enrollment form. Each request tracks student details, payment
         * screenshot, review status, and the resulting WordPress user ID
         * when approved.
         */

        $enrollment_requests_table = $wpdb->prefix . 'mdcat_enrollment_requests';

        $sql_enrollment_requests = "CREATE TABLE $enrollment_requests_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            city VARCHAR(255) NOT NULL,
            screenshot_url VARCHAR(500) NOT NULL,
            screenshot_path VARCHAR(500) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            admin_notes TEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            wp_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY created_at (created_at)

        ) $charset_collate;";

        dbDelta($sql_enrollment_requests);

        /**
         * XP Transactions Table (Gamification)
         *
         * Append-only log of all XP awards. Total XP is always derived
         * via SUM(amount) — never stored as a separate counter.
         * The source column identifies the trigger (quiz_completion,
         * streak_milestone, accuracy_bonus, badge_unlock, etc).
         */

        $xp_transactions_table = $wpdb->prefix . 'mdcat_xp_transactions';

        $sql_xp_transactions = "CREATE TABLE $xp_transactions_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            amount INT NOT NULL,
            source VARCHAR(50) NOT NULL,
            source_id BIGINT UNSIGNED NULL,
            description VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY source (source),
            KEY created_at (created_at),
            KEY user_created (user_id, created_at)

        ) $charset_collate;";

        dbDelta($sql_xp_transactions);

        /**
         * User Rewards Table (Gamification)
         *
         * Stores earned badges and achievements in a single table.
         * The reward_type column distinguishes between 'badge' and
         * 'achievement'. The UNIQUE KEY on (user_id, reward_type,
         * reward_slug) prevents duplicate awards during the
         * evaluate-on-every-quiz-completion flow.
         */

        $user_rewards_table = $wpdb->prefix . 'mdcat_user_rewards';

        $sql_user_rewards = "CREATE TABLE $user_rewards_table (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            reward_type VARCHAR(20) NOT NULL,
            reward_slug VARCHAR(100) NOT NULL,
            earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY user_reward (user_id, reward_type, reward_slug),
            KEY user_id (user_id),
            KEY reward_type (reward_type),
            KEY reward_slug (reward_slug),
            KEY earned_at (earned_at)

        ) $charset_collate;";

        dbDelta($sql_user_rewards);

    }
}
