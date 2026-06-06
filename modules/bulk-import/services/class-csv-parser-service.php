<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_CSV_Parser_Service {

    /**
     * Maximum number of data rows allowed per CSV file.
     */
    const MAX_ROWS = 15000;

    /**
     * Required CSV column headers.
     *
     * These must be present in the header row for the CSV to be valid.
     * Headers are matched case-insensitively after trimming.
     *
     * @return array
     */
    public static function get_required_headers() {

        return [
            'subject',
            'chapter',
            'collection',
            'question',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'correct_option',
        ];
    }

    /**
     * Optional CSV column headers with their default values.
     *
     * If these columns are absent or their cells are empty, the
     * default value is used instead.
     *
     * @return array
     */
    public static function get_optional_headers() {

        return [
            'collection_type' => 'exercise',
            'explanation'     => '',
            'difficulty'      => 'medium',
            'marks'           => '1.00',
        ];
    }

    /**
     * Parse a CSV file into normalized row arrays.
     *
     * Uses streaming fgetcsv() to keep memory constant regardless
     * of file size. Each data row is mapped to an associative array
     * keyed by normalized header names.
     *
     * @param string $file_path Absolute path to the uploaded CSV file.
     * @return array ['headers' => [...], 'rows' => [...], 'errors' => [...]]
     */
    public static function parse( $file_path ) {

        $result = [
            'headers' => [],
            'rows'    => [],
            'errors'  => [],
        ];

        if (!file_exists($file_path) || !is_readable($file_path)) {
            $result['errors'][] = __('CSV file could not be read.', 'mdcat-platform');
            return $result;
        }

        $handle = fopen($file_path, 'r');

        if (!$handle) {
            $result['errors'][] = __('CSV file could not be opened.', 'mdcat-platform');
            return $result;
        }

        // Read and validate header row.
        $raw_headers = fgetcsv($handle);

        if (!$raw_headers || !is_array($raw_headers)) {
            fclose($handle);
            $result['errors'][] = __('CSV file has no header row.', 'mdcat-platform');
            return $result;
        }

        // Handle UTF-8 BOM marker.
        if (isset($raw_headers[0])) {
            $raw_headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $raw_headers[0]);
        }

        $header_map = self::build_header_map($raw_headers);
        $validation = self::validate_headers($header_map);

        if (!$validation['valid']) {
            fclose($handle);

            foreach ($validation['missing'] as $missing) {
                $result['errors'][] = sprintf(
                    /* translators: %s: column name */
                    __('Missing required column: %s.', 'mdcat-platform'),
                    $missing
                );
            }

            return $result;
        }

        $result['headers'] = array_keys($header_map);

        // Read data rows using streaming fgetcsv.
        $row_number = 1;

        while (($csv_row = fgetcsv($handle)) !== false) {

            $row_number++;

            // Skip completely empty rows.
            if (self::is_empty_row($csv_row)) {
                continue;
            }

            if (count($result['rows']) >= self::MAX_ROWS) {
                $result['errors'][] = sprintf(
                    /* translators: %d: maximum row count */
                    __('CSV exceeds the maximum of %d rows.', 'mdcat-platform'),
                    self::MAX_ROWS
                );
                break;
            }

            $normalized = self::normalize_row($csv_row, $header_map, $row_number);
            $result['rows'][] = $normalized;
        }

        fclose($handle);

        if (empty($result['rows']) && empty($result['errors'])) {
            $result['errors'][] = __('CSV file contains no data rows.', 'mdcat-platform');
        }

        return $result;
    }

    /**
     * Build a map of normalized header names to their column indices.
     *
     * @param array $raw_headers Raw header values from the first CSV row.
     * @return array Associative array [normalized_name => column_index].
     */
    private static function build_header_map( $raw_headers ) {

        $map = [];

        foreach ($raw_headers as $index => $header) {
            $normalized = self::normalize_header($header);

            if ('' !== $normalized) {
                $map[$normalized] = $index;
            }
        }

        return $map;
    }

    /**
     * Validate that all required headers are present in the header map.
     *
     * @param array $header_map Normalized header map.
     * @return array ['valid' => bool, 'missing' => array]
     */
    private static function validate_headers( $header_map ) {

        $required = self::get_required_headers();
        $missing  = [];

        foreach ($required as $header) {
            if (!isset($header_map[$header])) {
                $missing[] = $header;
            }
        }

        return [
            'valid'   => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Normalize a single header value.
     *
     * Converts to lowercase, replaces spaces/dashes with underscores,
     * and strips non-alphanumeric characters.
     *
     * @param string $header Raw header value.
     * @return string Normalized header.
     */
    private static function normalize_header( $header ) {

        $header = strtolower(trim($header));
        $header = preg_replace('/[\s\-]+/', '_', $header);
        $header = preg_replace('/[^a-z0-9_]/', '', $header);

        return $header;
    }

    /**
     * Map a raw CSV row to a normalized associative array.
     *
     * Each cell is mapped by header name. Optional fields receive
     * their default value if the column is missing or empty.
     *
     * @param array $csv_row     Raw CSV row values.
     * @param array $header_map  Normalized header to column index map.
     * @param int   $row_number  Original CSV row number (1-indexed, includes header).
     * @return array Normalized row data with _row_number metadata.
     */
    private static function normalize_row( $csv_row, $header_map, $row_number ) {

        $all_headers = array_merge(
            self::get_required_headers(),
            array_keys(self::get_optional_headers())
        );

        $defaults = self::get_optional_headers();
        $row      = ['_row_number' => $row_number];

        foreach ($all_headers as $header) {

            if (isset($header_map[$header]) && isset($csv_row[$header_map[$header]])) {
                $value = trim($csv_row[$header_map[$header]]);
            } else {
                $value = '';
            }

            // Apply default for optional fields with empty values.
            if ('' === $value && isset($defaults[$header])) {
                $value = $defaults[$header];
            }

            $row[$header] = $value;
        }

        return $row;
    }

    /**
     * Check if a CSV row is completely empty.
     *
     * @param array $csv_row Raw CSV row values.
     * @return bool
     */
    private static function is_empty_row( $csv_row ) {

        foreach ($csv_row as $cell) {
            if ('' !== trim((string) $cell)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate a CSV template string with headers and example rows.
     *
     * @return string CSV content ready for download.
     */
    public static function generate_template() {

        $headers = array_merge(
            self::get_required_headers(),
            array_keys(self::get_optional_headers())
        );

        $example_rows = [
            [
                'Biology',
                'Cell Biology',
                'Exercise 1',
                'What is the powerhouse of the cell?',
                'Nucleus',
                'Mitochondria',
                'Ribosome',
                'Golgi body',
                'b',
                'exercise',
                'Mitochondria is responsible for ATP production through cellular respiration.',
                'easy',
                '1.00',
            ],
            [
                'Chemistry',
                'Organic Chemistry',
                'Practice Test 1',
                'What is the functional group of alcohols?',
                'Carboxyl',
                'Hydroxyl',
                'Amino',
                'Carbonyl',
                'b',
                'practice_test',
                'Alcohols contain the -OH (hydroxyl) functional group.',
                'medium',
                '1.50',
            ],
        ];

        $output = fopen('php://temp', 'r+');

        fputcsv($output, $headers);

        foreach ($example_rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return $csv_content;
    }
}
