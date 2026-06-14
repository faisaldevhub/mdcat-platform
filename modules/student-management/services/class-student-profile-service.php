<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Student_Profile_Service {

    /**
     * Build a complete student profile by composing existing services.
     *
     * This is a pure orchestration layer — it makes NO direct database
     * queries. All data is sourced from existing service methods.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error Complete student profile data.
     */
    public static function get_student_profile( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $overview = self::get_student_overview($user_id);

        if (is_wp_error($overview)) {
            return $overview;
        }

        return [
            'overview'   => $overview,
            'enrollment' => self::get_enrollment_info($user_id),
            'progress'   => self::get_progress_data($user_id),
            'analytics'  => self::get_learning_analytics($user_id),
            'streak'     => self::get_streak_data($user_id),
        ];
    }

    /**
     * Get basic student overview from WordPress user data.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error
     */
    public static function get_student_overview( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('user_not_found', __('Student not found.', 'mdcat-platform'));
        }

        $status = get_user_meta($user_id, 'mdcat_account_status', true);

        return [
            'user_id'        => absint($user->ID),
            'display_name'   => $user->display_name,
            'email'          => $user->user_email,
            'registered'     => $user->user_registered,
            'account_status' => $status ? $status : 'active',
            'role'           => implode(', ', $user->roles),
        ];
    }

    /**
     * Get enrollment information for a student.
     *
     * Reuses MDCAT_Platform_Enrollment_Service::get_request_by_email()
     * to look up the enrollment record via the student's email.
     *
     * @param int $user_id WordPress user ID.
     * @return array|null Enrollment data or null if no record exists.
     */
    public static function get_enrollment_info( $user_id ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return null;
        }

        $user = get_userdata($user_id);

        if (!$user) {
            return null;
        }

        $request = MDCAT_Platform_Enrollment_Service::get_request_by_email($user->user_email);

        if (!$request) {
            return null;
        }

        return [
            'request_id'     => absint($request->id),
            'full_name'      => $request->full_name,
            'email'          => $request->email,
            'phone'          => $request->phone,
            'city'           => $request->city,
            'screenshot_url' => $request->screenshot_url,
            'status'         => $request->status,
            'admin_notes'    => $request->admin_notes,
            'reviewed_at'    => $request->reviewed_at,
            'created_at'     => $request->created_at,
        ];
    }

    /**
     * Get learning analytics for a student.
     *
     * Reuses MDCAT_Platform_Performance_Analytics for subject and
     * chapter performance calculations.
     *
     * @param int $user_id WordPress user ID.
     * @return array
     */
    public static function get_learning_analytics( $user_id ) {

        $user_id = absint($user_id);

        $subject_performance = MDCAT_Platform_Performance_Analytics::get_subject_performance($user_id);
        $chapter_performance = MDCAT_Platform_Performance_Analytics::get_chapter_performance($user_id);

        return [
            'subject_performance' => is_wp_error($subject_performance) ? [] : $subject_performance,
            'chapter_performance' => is_wp_error($chapter_performance) ? [] : $chapter_performance,
        ];
    }

    /**
     * Get progress/completion data for a student.
     *
     * Reuses MDCAT_Platform_Progress_Service for subject, chapter,
     * and overall completion calculations.
     *
     * @param int $user_id WordPress user ID.
     * @return array
     */
    public static function get_progress_data( $user_id ) {

        $user_id = absint($user_id);

        $subject_completion = MDCAT_Platform_Progress_Service::get_subject_completion($user_id);
        $chapter_completion = MDCAT_Platform_Progress_Service::get_chapter_completion($user_id);
        $overall_completion = MDCAT_Platform_Progress_Service::get_overall_completion($user_id);

        return [
            'subject_completion' => is_wp_error($subject_completion) ? [] : $subject_completion,
            'chapter_completion' => is_wp_error($chapter_completion) ? [] : $chapter_completion,
            'overall_completion' => is_wp_error($overall_completion)
                ? ['total_collections' => 0, 'completed_collections' => 0, 'completion_percentage' => 0]
                : $overall_completion,
        ];
    }

    /**
     * Get streak/gamification data for a student.
     *
     * Reuses MDCAT_Platform_Streak_Service for streak summary.
     *
     * @param int $user_id WordPress user ID.
     * @return array
     */
    public static function get_streak_data( $user_id ) {

        $user_id = absint($user_id);

        $streak = MDCAT_Platform_Streak_Service::get_streak_summary($user_id);

        if (is_wp_error($streak)) {
            return [
                'current_streak'    => 0,
                'longest_streak'    => 0,
                'total_active_days' => 0,
                'last_active_date'  => null,
            ];
        }

        return $streak;
    }

    /**
     * Get paginated attempt history for a student.
     *
     * Delegates entirely to MDCAT_Platform_Attempt_History.
     *
     * @param int   $user_id WordPress user ID.
     * @param array $args    Pagination arguments.
     * @return array|WP_Error
     */
    public static function get_attempt_history( $user_id, $args = [] ) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('A valid user is required.', 'mdcat-platform'));
        }

        return MDCAT_Platform_Attempt_History::get_user_attempt_history($user_id, $args);
    }
}
