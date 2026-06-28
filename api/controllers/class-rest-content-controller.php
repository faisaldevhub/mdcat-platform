<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller for content browsing.
 *
 * Exposes read-only endpoints for subjects, chapters, and collections.
 * All endpoints require dashboard-level access (authenticated student
 * with an active account).
 *
 * Responses are intentionally flat — the frontend composes the content
 * hierarchy through separate API calls. This keeps responses small,
 * cache-friendly, and avoids N+1 query patterns.
 *
 * Endpoints:
 *
 *   GET /subjects               — List all subjects.
 *   GET /subjects/{id}          — Get a single subject.
 *   GET /chapters               — List chapters (filterable by subject_id).
 *   GET /chapters/{id}          — Get a single chapter.
 *   GET /collections            — List collections (filterable by chapter_id).
 *   GET /collections/{id}       — Get a single collection.
 *
 * All endpoints delegate to existing Handler classes:
 *
 *   - Subjects_Handler   → mdcat_subjects table
 *   - Chapters_Handler   → mdcat_chapters + mdcat_subjects tables
 *   - Collections_Handler → mdcat_collections + mdcat_chapters + mdcat_subjects tables
 */
class MDCAT_Platform_REST_Content_Controller
    extends MDCAT_Platform_REST_Base_Controller {

    /**
     * Register all content routes.
     */
    public static function register_routes() {

        // --- Subjects ---

        register_rest_route(self::$namespace, '/subjects', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_subjects'],
            'permission_callback' => [__CLASS__, 'check_public_access'],
        ]);

        register_rest_route(self::$namespace, '/subjects/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_subject'],
            'permission_callback' => [__CLASS__, 'check_public_access'],
        ]);

        // --- Chapters ---

        register_rest_route(self::$namespace, '/chapters', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_chapters'],
            'permission_callback' => [__CLASS__, 'check_public_access'],
        ]);

        register_rest_route(self::$namespace, '/chapters/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_chapter'],
            'permission_callback' => [__CLASS__, 'check_public_access'],
        ]);

        // --- Collections ---

        register_rest_route(self::$namespace, '/collections', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_collections'],
            'permission_callback' => [__CLASS__, 'check_public_access'],
        ]);

        register_rest_route(self::$namespace, '/collections/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_collection'],
            'permission_callback' => [__CLASS__, 'check_public_access'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Subjects
    // ------------------------------------------------------------------

    /**
     * List all subjects.
     *
     * Returns every subject in the curriculum ordered by name.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_subjects( $request ) {

        $rows = MDCAT_Platform_Subjects_Handler::get_subjects();

        $subjects = [];

        foreach ((array) $rows as $row) {
            $subjects[] = self::format_subject($row);
        }

        return self::success($subjects, 'Subjects loaded.');
    }

    /**
     * Get a single subject by ID.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_subject( $request ) {

        $subject_id = absint($request->get_param('id'));

        $subject = MDCAT_Platform_Subjects_Handler::get_subject($subject_id);

        if (!$subject) {
            return self::error('not_found', 'Subject not found.', 404);
        }

        return self::success(self::format_subject($subject), 'Subject loaded.');
    }

    // ------------------------------------------------------------------
    //  Chapters
    // ------------------------------------------------------------------

    /**
     * List chapters, optionally filtered by subject_id.
     *
     * When subject_id is provided, returns only chapters belonging to
     * that subject. Without it, returns all chapters across all subjects.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_chapters( $request ) {

        $subject_id = absint($request->get_param('subject_id'));

        $rows = MDCAT_Platform_Chapters_Handler::get_chapters();

        $chapters = [];

        foreach ((array) $rows as $row) {

            // Filter by subject_id if provided.
            if ($subject_id && absint($row->subject_id) !== $subject_id) {
                continue;
            }

            $chapters[] = self::format_chapter($row);
        }

        return self::success($chapters, 'Chapters loaded.');
    }

    /**
     * Get a single chapter by ID.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_chapter( $request ) {

        $chapter_id = absint($request->get_param('id'));

        $chapter = MDCAT_Platform_Chapters_Handler::get_chapter($chapter_id);

        if (!$chapter) {
            return self::error('not_found', 'Chapter not found.', 404);
        }

        // get_chapter() returns only the chapter row (no subject_name).
        // Fetch the subject name for the response.
        $subject = MDCAT_Platform_Subjects_Handler::get_subject(absint($chapter->subject_id));

        $formatted = [
            'id'           => absint($chapter->id),
            'name'         => $chapter->name,
            'slug'         => $chapter->slug,
            'subject_id'   => absint($chapter->subject_id),
            'subject_name' => $subject ? $subject->name : null,
            'created_at'   => $chapter->created_at,
        ];

        return self::success($formatted, 'Chapter loaded.');
    }

    // ------------------------------------------------------------------
    //  Collections
    // ------------------------------------------------------------------

    /**
     * List collections, optionally filtered by chapter_id.
     *
     * Only returns active collections (status = 'active') to prevent
     * students from seeing draft or disabled content.
     *
     * When chapter_id is provided, returns only collections belonging
     * to that chapter. Without it, returns all active collections.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_collections( $request ) {

        $chapter_id = absint($request->get_param('chapter_id'));

        $rows = MDCAT_Platform_Collections_Handler::get_collections();

        $collections = [];

        foreach ((array) $rows as $row) {

            // Only expose active collections to students.
            if (isset($row->status) && 'active' !== $row->status) {
                continue;
            }

            // Filter by chapter_id if provided.
            if ($chapter_id && absint($row->chapter_id) !== $chapter_id) {
                continue;
            }

            $collections[] = self::format_collection($row);
        }

        return self::success($collections, 'Collections loaded.');
    }

    /**
     * Get a single collection by ID.
     *
     * Returns collection metadata only — questions are NOT included.
     * Questions are served by the quiz engine after starting an attempt.
     *
     * Only active collections are returned. Requesting an inactive
     * collection returns 404 to prevent information leakage about
     * unpublished content.
     *
     * @param WP_REST_Request $request The incoming request.
     * @return WP_REST_Response
     */
    public static function get_collection( $request ) {

        $collection_id = absint($request->get_param('id'));

        $collection = MDCAT_Platform_Collections_Handler::get_collection($collection_id);

        if (!$collection) {
            return self::error('not_found', 'Collection not found.', 404);
        }

        // Block access to inactive collections.
        if (isset($collection->status) && 'active' !== $collection->status) {
            return self::error('not_found', 'Collection not found.', 404);
        }

        // get_collection() returns only the collection row (no chapter/subject names).
        // Fetch the chapter for context.
        $chapter = MDCAT_Platform_Chapters_Handler::get_chapter(absint($collection->chapter_id));
        $subject = $chapter ? MDCAT_Platform_Subjects_Handler::get_subject(absint($chapter->subject_id)) : null;

        $formatted = [
            'id'           => absint($collection->id),
            'title'        => $collection->title,
            'type'         => $collection->type,
            'description'  => $collection->description,
            'status'       => $collection->status,
            'sort_order'   => absint($collection->sort_order),
            'chapter_id'   => absint($collection->chapter_id),
            'chapter_name' => $chapter ? $chapter->name : null,
            'subject_name' => $subject ? $subject->name : null,
            'created_at'   => $collection->created_at,
        ];

        return self::success($formatted, 'Collection loaded.');
    }

    // ------------------------------------------------------------------
    //  Formatters
    // ------------------------------------------------------------------

    /**
     * Format a subject row for the API response.
     *
     * @param object $row Database row.
     * @return array
     */
    private static function format_subject( $row ) {

        return [
            'id'         => absint($row->id),
            'name'       => $row->name,
            'slug'       => $row->slug,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Format a chapter row for the API response.
     *
     * Chapters returned by get_chapters() include subject_name
     * via JOIN, so we can include it directly.
     *
     * @param object $row Database row from get_chapters().
     * @return array
     */
    private static function format_chapter( $row ) {

        return [
            'id'           => absint($row->id),
            'name'         => $row->name,
            'slug'         => $row->slug,
            'subject_id'   => absint($row->subject_id),
            'subject_name' => isset($row->subject_name) ? $row->subject_name : null,
            'created_at'   => $row->created_at,
        ];
    }

    /**
     * Format a collection row for the API response.
     *
     * Collections returned by get_collections() include chapter_name
     * and subject_name via JOIN.
     *
     * @param object $row Database row from get_collections().
     * @return array
     */
    private static function format_collection( $row ) {

        return [
            'id'           => absint($row->id),
            'title'        => $row->title,
            'type'         => $row->type,
            'description'  => isset($row->description) ? $row->description : null,
            'status'       => $row->status,
            'sort_order'   => absint($row->sort_order),
            'chapter_id'   => absint($row->chapter_id),
            'chapter_name' => isset($row->chapter_name) ? $row->chapter_name : null,
            'subject_name' => isset($row->subject_name) ? $row->subject_name : null,
            'created_at'   => $row->created_at,
        ];
    }
}
