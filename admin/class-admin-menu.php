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
            'Collections',
            'Collections',
            'manage_options',
            'mdcat-collections',
            [__CLASS__, 'collections_page']
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

        add_submenu_page(
            'mdcat-platform',
            'Import Questions',
            'Import Questions',
            'manage_options',
            'mdcat-import-questions',
            [__CLASS__, 'import_questions_page']
        );

        add_submenu_page(
            'mdcat-platform',
            'Enrollment Requests',
            'Enrollment Requests',
            'manage_options',
            'mdcat-enrollment-requests',
            [__CLASS__, 'enrollment_page']
        );

        add_submenu_page(
            'mdcat-platform',
            'Students',
            'Students',
            'manage_options',
            'mdcat-students',
            [__CLASS__, 'students_page']
        );
    }

    /**
     * Dashboard Page
     */

    public static function dashboard_page() {

        if (!class_exists('MDCAT_Platform_Admin_Reports_View')) {
            wp_die(esc_html__('Admin Reports module is not available.', 'mdcat-platform'));
        }

        MDCAT_Platform_Admin_Reports_View::render();
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
     * Collections Page
     */

    public static function collections_page() {

        if (!class_exists('MDCAT_Platform_Collections')) {
            wp_die(esc_html__('Collections module is not available.', 'mdcat-platform'));
        }

        MDCAT_Platform_Collections::render_page();
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

        if (!class_exists('MDCAT_Platform_Questions')) {
            wp_die(esc_html__('Questions module is not available.', 'mdcat-platform'));
        }

        MDCAT_Platform_Questions::render_page();
    }

    /**
     * Import Questions Page
     */

    public static function import_questions_page() {

        if (!class_exists('MDCAT_Platform_Bulk_Import_View')) {
            wp_die(esc_html__('Bulk Import module is not available.', 'mdcat-platform'));
        }

        MDCAT_Platform_Bulk_Import_View::render();
    }

    /**
     * Enrollment Requests Page
     */

    public static function enrollment_page() {

        if (!class_exists('MDCAT_Platform_Enrollment_Admin_View')) {
            wp_die(esc_html__('Enrollment module is not available.', 'mdcat-platform'));
        }

        MDCAT_Platform_Enrollment_Admin_View::render();
    }

    /**
     * Students Page
     */

    public static function students_page() {

        if (!class_exists('MDCAT_Platform_Student_Management_View')) {
            wp_die(esc_html__('Student Management module is not available.', 'mdcat-platform'));
        }

        MDCAT_Platform_Student_Management_View::render();
    }
}
