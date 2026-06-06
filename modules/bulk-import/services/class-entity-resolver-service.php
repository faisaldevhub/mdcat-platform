<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Entity_Resolver_Service {

    /**
     * In-memory lookup maps for entity resolution.
     *
     * Pre-loaded once before processing to avoid N+1 queries.
     * Maps are keyed by lowercase entity name for case-insensitive matching.
     */
    private static $subject_map    = null;
    private static $chapter_map    = null;
    private static $collection_map = null;

    /**
     * Resolve Subject → Chapter → Collection names to database IDs.
     *
     * Iterates each validated row and resolves its entity hierarchy
     * to database IDs. In auto-create mode, missing entities are
     * created on the fly following the same schema as the existing
     * Subjects/Chapters/Collections handlers.
     *
     * @param array $rows        Validated row arrays.
     * @param bool  $auto_create Whether to create missing entities.
     * @return array ['resolved' => [...], 'errors' => [...], 'created' => [...]]
     */
    public static function resolve( $rows, $auto_create = true ) {

        self::load_entity_maps();

        $resolved = [];
        $errors   = [];
        $created  = [
            'subjects'    => 0,
            'chapters'    => 0,
            'collections' => 0,
        ];
        $created_ids = [
            'subjects'    => [],
            'chapters'    => [],
            'collections' => [],
        ];

        foreach ($rows as $row) {

            $row_number = isset($row['_row_number']) ? absint($row['_row_number']) : 0;

            // Resolve subject.
            $subject_name = trim($row['subject']);
            $subject_id   = self::resolve_subject($subject_name);

            if (!$subject_id && $auto_create) {
                $subject_id = self::create_subject($subject_name);
                $created['subjects']++;
                $created_ids['subjects'][] = $subject_id;
            }

            if (!$subject_id) {
                $errors[] = [
                    'row'     => $row_number,
                    'field'   => 'subject',
                    'message' => sprintf(
                        /* translators: 1: row number, 2: subject name */
                        __('Row %1$d: Subject "%2$s" does not exist.', 'mdcat-platform'),
                        $row_number,
                        $subject_name
                    ),
                ];
                continue;
            }

            // Resolve chapter under the resolved subject.
            $chapter_name = trim($row['chapter']);
            $chapter_id   = self::resolve_chapter($chapter_name, $subject_id);

            if (!$chapter_id && $auto_create) {
                $chapter_id = self::create_chapter($chapter_name, $subject_id);
                $created['chapters']++;
                $created_ids['chapters'][] = $chapter_id;
            }

            if (!$chapter_id) {
                $errors[] = [
                    'row'     => $row_number,
                    'field'   => 'chapter',
                    'message' => sprintf(
                        /* translators: 1: row number, 2: chapter name */
                        __('Row %1$d: Chapter "%2$s" does not exist.', 'mdcat-platform'),
                        $row_number,
                        $chapter_name
                    ),
                ];
                continue;
            }

            // Resolve collection under the resolved chapter.
            $collection_title = trim($row['collection']);
            $collection_type  = isset($row['collection_type']) ? strtolower(trim($row['collection_type'])) : 'exercise';
            $collection_id    = self::resolve_collection($collection_title, $chapter_id);

            if (!$collection_id && $auto_create) {
                $collection_id = self::create_collection($collection_title, $chapter_id, $collection_type);
                $created['collections']++;
                $created_ids['collections'][] = $collection_id;
            }

            if (!$collection_id) {
                $errors[] = [
                    'row'     => $row_number,
                    'field'   => 'collection',
                    'message' => sprintf(
                        /* translators: 1: row number, 2: collection title */
                        __('Row %1$d: Collection "%2$s" does not exist.', 'mdcat-platform'),
                        $row_number,
                        $collection_title
                    ),
                ];
                continue;
            }

            // Attach resolved collection_id to the row.
            $row['collection_id'] = $collection_id;
            $resolved[] = $row;
        }

        // Reset static maps for next invocation.
        self::reset_maps();

        return [
            'resolved'    => $resolved,
            'errors'      => $errors,
            'created'     => $created,
            'created_ids' => $created_ids,
        ];
    }

    /**
     * Pre-load all existing entities into in-memory maps.
     *
     * Each map is keyed by a composite string for scoped lookup:
     * - Subjects: lowercase name → id
     * - Chapters: "subject_id::lowercase_name" → id
     * - Collections: "chapter_id::lowercase_title" → id
     *
     * This runs 3 queries total, regardless of how many rows are imported.
     */
    private static function load_entity_maps() {

        global $wpdb;

        // Load subjects.
        $subjects_table = MDCAT_Platform_Subjects_Handler::get_table_name();
        $subjects       = $wpdb->get_results("SELECT id, name FROM {$subjects_table}");

        self::$subject_map = [];

        foreach ((array) $subjects as $s) {
            self::$subject_map[strtolower(trim($s->name))] = absint($s->id);
        }

        // Load chapters.
        $chapters_table = MDCAT_Platform_Chapters_Handler::get_table_name();
        $chapters       = $wpdb->get_results("SELECT id, subject_id, name FROM {$chapters_table}");

        self::$chapter_map = [];

        foreach ((array) $chapters as $c) {
            $key = absint($c->subject_id) . '::' . strtolower(trim($c->name));
            self::$chapter_map[$key] = absint($c->id);
        }

        // Load collections.
        $collections_table = MDCAT_Platform_Collections_Handler::get_table_name();
        $collections       = $wpdb->get_results("SELECT id, chapter_id, title FROM {$collections_table}");

        self::$collection_map = [];

        foreach ((array) $collections as $col) {
            $key = absint($col->chapter_id) . '::' . strtolower(trim($col->title));
            self::$collection_map[$key] = absint($col->id);
        }
    }

    /**
     * Resolve a subject name to its database ID.
     *
     * @param string $name Subject name.
     * @return int|null Subject ID or null if not found.
     */
    private static function resolve_subject( $name ) {

        $key = strtolower(trim($name));

        return isset(self::$subject_map[$key]) ? self::$subject_map[$key] : null;
    }

    /**
     * Resolve a chapter name to its database ID within a specific subject.
     *
     * @param string $name       Chapter name.
     * @param int    $subject_id Parent subject ID.
     * @return int|null Chapter ID or null if not found.
     */
    private static function resolve_chapter( $name, $subject_id ) {

        $key = absint($subject_id) . '::' . strtolower(trim($name));

        return isset(self::$chapter_map[$key]) ? self::$chapter_map[$key] : null;
    }

    /**
     * Resolve a collection title to its database ID within a specific chapter.
     *
     * @param string $title      Collection title.
     * @param int    $chapter_id Parent chapter ID.
     * @return int|null Collection ID or null if not found.
     */
    private static function resolve_collection( $title, $chapter_id ) {

        $key = absint($chapter_id) . '::' . strtolower(trim($title));

        return isset(self::$collection_map[$key]) ? self::$collection_map[$key] : null;
    }

    /**
     * Create a new subject and register it in the lookup map.
     *
     * Uses the same schema as Subjects_Handler::create_subject().
     *
     * @param string $name Subject name.
     * @return int New subject ID.
     */
    private static function create_subject( $name ) {

        global $wpdb;

        $name = sanitize_text_field($name);
        $slug = sanitize_title($name);

        $wpdb->insert(
            MDCAT_Platform_Subjects_Handler::get_table_name(),
            [
                'name'       => $name,
                'slug'       => $slug,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s']
        );

        $new_id = absint($wpdb->insert_id);

        // Register in map for subsequent rows.
        self::$subject_map[strtolower(trim($name))] = $new_id;

        return $new_id;
    }

    /**
     * Create a new chapter and register it in the lookup map.
     *
     * Uses the same schema as Chapters_Handler::create_chapter().
     *
     * @param string $name       Chapter name.
     * @param int    $subject_id Parent subject ID.
     * @return int New chapter ID.
     */
    private static function create_chapter( $name, $subject_id ) {

        global $wpdb;

        $name = sanitize_text_field($name);
        $slug = sanitize_title($name);

        $wpdb->insert(
            MDCAT_Platform_Chapters_Handler::get_table_name(),
            [
                'subject_id' => absint($subject_id),
                'name'       => $name,
                'slug'       => $slug,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        $new_id = absint($wpdb->insert_id);

        $key = absint($subject_id) . '::' . strtolower(trim($name));
        self::$chapter_map[$key] = $new_id;

        return $new_id;
    }

    /**
     * Create a new collection and register it in the lookup map.
     *
     * Uses the same schema as Collections_Handler::create_collection().
     * Defaults to 'exercise' type and 'active' status.
     *
     * @param string $title      Collection title.
     * @param int    $chapter_id Parent chapter ID.
     * @param string $type       Collection type.
     * @return int New collection ID.
     */
    private static function create_collection( $title, $chapter_id, $type = 'exercise' ) {

        global $wpdb;

        $title = sanitize_text_field($title);
        $type  = sanitize_key($type);

        // Validate type against allowed types.
        $allowed_types = array_keys(MDCAT_Platform_Collections_Handler::get_allowed_types());

        if (!in_array($type, $allowed_types, true)) {
            $type = 'exercise';
        }

        $wpdb->insert(
            MDCAT_Platform_Collections_Handler::get_table_name(),
            [
                'chapter_id'  => absint($chapter_id),
                'title'       => $title,
                'type'        => $type,
                'description' => '',
                'sort_order'  => 0,
                'status'      => 'active',
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        $new_id = absint($wpdb->insert_id);

        $key = absint($chapter_id) . '::' . strtolower(trim($title));
        self::$collection_map[$key] = $new_id;

        return $new_id;
    }

    /**
     * Reset static maps for clean state between invocations.
     */
    private static function reset_maps() {

        self::$subject_map    = null;
        self::$chapter_map    = null;
        self::$collection_map = null;
    }
}
