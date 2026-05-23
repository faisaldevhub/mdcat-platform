<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Admin_Menu {

    public static function init() {

        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    public static function register_menu() {

        add_menu_page(
            'MDCAT Platform',
            'MDCAT Platform',
            'manage_options',
            'mdcat-platform',
            [__CLASS__, 'dashboard_page'],
            'dashicons-welcome-learn-more',
            25
        );

        add_submenu_page(
            'mdcat-platform',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'mdcat-platform',
            [__CLASS__, 'dashboard_page']
        );

        add_submenu_page(
            'mdcat-platform',
            'Subjects',
            'Subjects',
            'manage_options',
            'mdcat-subjects',
            [__CLASS__, 'subjects_page']
        );

        add_submenu_page(
            'mdcat-platform',
            'Chapters',
            'Chapters',
            'manage_options',
            'mdcat-chapters',
            [__CLASS__, 'chapters_page']
        );

        add_submenu_page(
            'mdcat-platform',
            'Quizzes',
            'Quizzes',
            'manage_options',
            'mdcat-quizzes',
            [__CLASS__, 'quizzes_page']
        );

        add_submenu_page(
            'mdcat-platform',
            'Questions',
            'Questions',
            'manage_options',
            'mdcat-questions',
            [__CLASS__, 'questions_page']
        );
    }

    /**
     * Dashboard Page
     */

    public static function dashboard_page() {

        echo '<div class="wrap">';
        echo '<h1>MDCAT Platform Dashboard</h1>';
        echo '<p>Welcome to your custom LMS system.</p>';
        echo '</div>';
    }

    /**
     * Subjects Page
     */

    public static function subjects_page() {

        if (!class_exists('MDCAT_Platform_Subjects')) {
            wp_die(esc_html__('Subjects module is not available.', 'mdcat-platform'));
        }

        MDCAT_Platform_Subjects::render_page();
    }

    /**
     * Chapters Page
     */

    public static function chapters_page() {

        if (!class_exists('MDCAT_Platform_Chapters')) {
            wp_die(esc_html__('Chapters module is not available.', 'mdcat-platform'));
        }

        MDCAT_Platform_Chapters::render_page();
    }

    /**
     * Quizzes Page
     */

    public static function quizzes_page() {

        echo '<div class="wrap">';
        echo '<h1>Quizzes Management</h1>';
        echo '</div>';
    }

    /**
     * Questions Page
     */

    public static function questions_page() {

        echo '<div class="wrap">';
        echo '<h1>Questions Management</h1>';
        echo '</div>';
    }
}
