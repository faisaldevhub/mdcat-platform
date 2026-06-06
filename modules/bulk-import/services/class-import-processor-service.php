<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Import_Processor_Service {

    /**
     * Number of rows per database transaction chunk.
     */
    const CHUNK_SIZE = 50;

    /**
     * Process resolved rows: detect duplicates and insert unique questions.
     *
     * @param array  $resolved_rows  Rows with collection_id resolved.
     * @param string $duplicate_mode How to handle duplicates: 'skip' or 'error'.
     * @return array ['inserted' => int, 'duplicates' => [...], 'errors' => [...]]
     */
    public static function process( $resolved_rows, $duplicate_mode = 'skip' ) {

        $collection_ids = self::extract_collection_ids($resolved_rows);
        $existing_map   = self::load_existing_questions($collection_ids);

        $unique     = [];
        $duplicates = [];

        foreach ($resolved_rows as $row) {

            $key = self::build_dedup_key($row);

            if (isset($existing_map[$key])) {
                $duplicates[] = [
                    'row'     => isset($row['_row_number']) ? absint($row['_row_number']) : 0,
                    'message' => sprintf(
                        /* translators: %d: row number */
                        __('Row %d: Duplicate question detected — skipped.', 'mdcat-platform'),
                        isset($row['_row_number']) ? absint($row['_row_number']) : 0
                    ),
                ];
                continue;
            }

            // Register in the map to catch intra-file duplicates.
            $existing_map[$key] = 'pending';
            $unique[] = $row;
        }

        // If duplicate mode is 'error' and duplicates were found, return without inserting.
        if ('error' === $duplicate_mode && !empty($duplicates)) {
            return [
                'inserted'   => 0,
                'duplicates' => $duplicates,
                'errors'     => [],
            ];
        }

        // Insert unique rows in chunks.
        $insert_result = self::insert_batch($unique);

        return [
            'inserted'   => $insert_result['inserted'],
            'duplicates' => $duplicates,
            'errors'     => $insert_result['errors'],
        ];
    }

    /**
     * Extract unique collection IDs from resolved rows.
     *
     * @param array $rows Resolved rows.
     * @return array Unique collection IDs.
     */
    private static function extract_collection_ids( $rows ) {

        $ids = [];

        foreach ($rows as $row) {
            if (isset($row['collection_id'])) {
                $ids[] = absint($row['collection_id']);
            }
        }

        return array_unique($ids);
    }

    /**
     * Pre-load existing questions for target collections into a hash map.
     *
     * The map is keyed by "collection_id::normalized_question_text" for
     * O(1) duplicate lookup per row.
     *
     * @param array $collection_ids Collection IDs to load.
     * @return array Hash map of existing question signatures.
     */
    private static function load_existing_questions( $collection_ids ) {

        global $wpdb;

        $map = [];

        if (empty($collection_ids)) {
            return $map;
        }

        $questions_table = MDCAT_Platform_Questions_Handler::get_table_name();

        // Build safe IN clause.
        $placeholders = implode(',', array_fill(0, count($collection_ids), '%d'));

        $query = $wpdb->prepare(
            "SELECT id, collection_id, question FROM {$questions_table} WHERE collection_id IN ({$placeholders})",
            $collection_ids
        );

        $existing = $wpdb->get_results($query);

        foreach ((array) $existing as $q) {
            $key       = absint($q->collection_id) . '::' . self::normalize_text($q->question);
            $map[$key] = absint($q->id);
        }

        return $map;
    }

    /**
     * Build a deduplication key for a row.
     *
     * Combines collection_id and normalized question text into a
     * unique string for hash map lookup.
     *
     * @param array $row Resolved row data.
     * @return string Dedup key.
     */
    private static function build_dedup_key( $row ) {

        $collection_id = isset($row['collection_id']) ? absint($row['collection_id']) : 0;
        $question_text = isset($row['question']) ? $row['question'] : '';

        return $collection_id . '::' . self::normalize_text($question_text);
    }

    /**
     * Normalize question text for duplicate comparison.
     *
     * Strips HTML, collapses whitespace, removes punctuation, and
     * converts to lowercase for fuzzy matching.
     *
     * @param string $text Raw question text.
     * @return string Normalized text.
     */
    private static function normalize_text( $text ) {

        $text = strtolower($text);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[^\w\s]/u', '', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Insert question rows in chunked transactions.
     *
     * Processes rows in batches of CHUNK_SIZE, wrapping each batch
     * in a database transaction for atomicity and performance.
     *
     * @param array $rows Unique rows to insert.
     * @return array ['inserted' => int, 'errors' => [...]]
     */
    private static function insert_batch( $rows ) {

        global $wpdb;

        $inserted = 0;
        $errors   = [];

        $chunks = array_chunk($rows, self::CHUNK_SIZE);

        foreach ($chunks as $chunk) {

            $wpdb->query('START TRANSACTION');
            $chunk_success  = true;
            $chunk_inserted = 0;

            foreach ($chunk as $row) {

                $result = self::insert_question($row);

                if ($result) {
                    $chunk_inserted++;
                } else {
                    $chunk_success = false;
                    $errors[] = [
                        'row'     => isset($row['_row_number']) ? absint($row['_row_number']) : 0,
                        'field'   => '',
                        'message' => sprintf(
                            /* translators: %d: row number */
                            __('Row %d: Database insert failed.', 'mdcat-platform'),
                            isset($row['_row_number']) ? absint($row['_row_number']) : 0
                        ),
                    ];
                }
            }

            if ($chunk_success) {
                $wpdb->query('COMMIT');
                $inserted += $chunk_inserted;
            } else {
                $wpdb->query('ROLLBACK');
            }
        }

        return [
            'inserted' => $inserted,
            'errors'   => $errors,
        ];
    }

    /**
     * Insert a single question row into the database.
     *
     * Uses the same column format and type specifications as
     * Questions_Handler::create_question() (lines 349–381).
     *
     * @param array $row Resolved and validated row data.
     * @return bool True on success, false on failure.
     */
    private static function insert_question( $row ) {

        global $wpdb;

        $result = $wpdb->insert(
            MDCAT_Platform_Questions_Handler::get_table_name(),
            [
                'collection_id'  => absint($row['collection_id']),
                'question'       => sanitize_textarea_field($row['question']),
                'option_a'       => sanitize_text_field($row['option_a']),
                'option_b'       => sanitize_text_field($row['option_b']),
                'option_c'       => sanitize_text_field($row['option_c']),
                'option_d'       => sanitize_text_field($row['option_d']),
                'correct_option' => sanitize_key(strtolower($row['correct_option'])),
                'explanation'    => sanitize_textarea_field(isset($row['explanation']) ? $row['explanation'] : ''),
                'difficulty'     => sanitize_key(strtolower(isset($row['difficulty']) ? $row['difficulty'] : 'medium')),
                'marks'          => (float) (isset($row['marks']) ? $row['marks'] : 1.00),
                'sort_order'     => 0,
                'status'         => 'active',
                'created_at'     => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%d',
                '%s',
                '%s',
            ]
        );

        return false !== $result;
    }
}
