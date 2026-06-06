<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Import_Validator_Service {

    /**
     * Maximum number of errors before halting validation.
     */
    const MAX_ERRORS = 100;

    /**
     * Validate all parsed rows against field-level rules.
     *
     * Returns an array of valid rows and an array of error objects.
     * Stops early if the error count exceeds MAX_ERRORS to avoid
     * flooding the admin with thousands of messages.
     *
     * @param array $rows Normalized row arrays from the CSV parser.
     * @return array ['valid' => [...], 'errors' => [...]]
     */
    public static function validate_rows( $rows ) {

        $valid  = [];
        $errors = [];

        foreach ($rows as $row) {

            $row_errors = self::validate_row($row);

            if (empty($row_errors)) {
                $valid[] = $row;
            } else {
                foreach ($row_errors as $error) {
                    $errors[] = $error;
                }
            }

            if (count($errors) >= self::MAX_ERRORS) {
                $errors[] = [
                    'row'     => 0,
                    'field'   => '',
                    'message' => sprintf(
                        /* translators: %d: max errors */
                        __('Validation stopped after %d errors. Fix the issues above and re-upload.', 'mdcat-platform'),
                        self::MAX_ERRORS
                    ),
                ];
                break;
            }
        }

        return [
            'valid'  => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Validate a single row against all field rules.
     *
     * @param array $row Normalized row data with _row_number.
     * @return array Array of error objects (empty if row is valid).
     */
    private static function validate_row( $row ) {

        $errors     = [];
        $row_number = isset($row['_row_number']) ? absint($row['_row_number']) : 0;

        // Required text fields.
        $required_fields = [
            'subject'    => __('Subject', 'mdcat-platform'),
            'chapter'    => __('Chapter', 'mdcat-platform'),
            'collection' => __('Collection', 'mdcat-platform'),
            'question'   => __('Question', 'mdcat-platform'),
            'option_a'   => __('Option A', 'mdcat-platform'),
            'option_b'   => __('Option B', 'mdcat-platform'),
            'option_c'   => __('Option C', 'mdcat-platform'),
            'option_d'   => __('Option D', 'mdcat-platform'),
        ];

        foreach ($required_fields as $field => $label) {
            $error = self::validate_required($row, $field, $label, $row_number);

            if ($error) {
                $errors[] = $error;
            }
        }

        // Max length checks for name/title fields.
        $length_fields = [
            'subject'    => 255,
            'chapter'    => 255,
            'collection' => 255,
        ];

        foreach ($length_fields as $field => $max) {
            $error = self::validate_max_length($row, $field, $max, $row_number);

            if ($error) {
                $errors[] = $error;
            }
        }

        // Correct option validation.
        $error = self::validate_correct_option($row, $row_number);

        if ($error) {
            $errors[] = $error;
        }

        // Collection type validation (optional field).
        if (!empty($row['collection_type'])) {
            $error = self::validate_collection_type($row, $row_number);

            if ($error) {
                $errors[] = $error;
            }
        }

        // Difficulty validation (optional field).
        if (!empty($row['difficulty'])) {
            $error = self::validate_difficulty($row, $row_number);

            if ($error) {
                $errors[] = $error;
            }
        }

        // Marks validation (optional field).
        if (!empty($row['marks'])) {
            $error = self::validate_marks($row, $row_number);

            if ($error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Validate that a required field is non-empty.
     *
     * @param array  $row        Row data.
     * @param string $field      Field key.
     * @param string $label      Human-readable field name.
     * @param int    $row_number CSV row number.
     * @return array|null Error object or null.
     */
    private static function validate_required( $row, $field, $label, $row_number ) {

        $value = isset($row[$field]) ? trim($row[$field]) : '';

        if ('' === $value) {
            return [
                'row'     => $row_number,
                'field'   => $field,
                'message' => sprintf(
                    /* translators: 1: row number, 2: field label */
                    __('Row %1$d: %2$s is required.', 'mdcat-platform'),
                    $row_number,
                    $label
                ),
            ];
        }

        return null;
    }

    /**
     * Validate that a field does not exceed the maximum length.
     *
     * @param array  $row        Row data.
     * @param string $field      Field key.
     * @param int    $max        Maximum character length.
     * @param int    $row_number CSV row number.
     * @return array|null Error object or null.
     */
    private static function validate_max_length( $row, $field, $max, $row_number ) {

        $value = isset($row[$field]) ? trim($row[$field]) : '';

        if (mb_strlen($value) > $max) {
            return [
                'row'     => $row_number,
                'field'   => $field,
                'message' => sprintf(
                    /* translators: 1: row number, 2: field name, 3: max length */
                    __('Row %1$d: %2$s exceeds %3$d characters.', 'mdcat-platform'),
                    $row_number,
                    $field,
                    $max
                ),
            ];
        }

        return null;
    }

    /**
     * Validate the correct_option field is a, b, c, or d.
     *
     * Accepts both upper and lower case. The value is normalized
     * to lowercase before returning to the caller.
     *
     * @param array $row        Row data.
     * @param int   $row_number CSV row number.
     * @return array|null Error object or null.
     */
    private static function validate_correct_option( $row, $row_number ) {

        $value   = isset($row['correct_option']) ? strtolower(trim($row['correct_option'])) : '';
        $allowed = array_keys(MDCAT_Platform_Questions_Handler::get_allowed_correct_options());

        if ('' === $value || !in_array($value, $allowed, true)) {
            return [
                'row'     => $row_number,
                'field'   => 'correct_option',
                'message' => sprintf(
                    /* translators: %d: row number */
                    __('Row %d: Correct option must be A, B, C, or D.', 'mdcat-platform'),
                    $row_number
                ),
            ];
        }

        return null;
    }

    /**
     * Validate the collection_type field against allowed types.
     *
     * @param array $row        Row data.
     * @param int   $row_number CSV row number.
     * @return array|null Error object or null.
     */
    private static function validate_collection_type( $row, $row_number ) {

        $value   = isset($row['collection_type']) ? strtolower(trim($row['collection_type'])) : '';
        $allowed = array_keys(MDCAT_Platform_Collections_Handler::get_allowed_types());

        if ('' !== $value && !in_array($value, $allowed, true)) {
            return [
                'row'     => $row_number,
                'field'   => 'collection_type',
                'message' => sprintf(
                    /* translators: 1: row number, 2: invalid value */
                    __('Row %1$d: Invalid collection type "%2$s".', 'mdcat-platform'),
                    $row_number,
                    $value
                ),
            ];
        }

        return null;
    }

    /**
     * Validate the difficulty field against allowed values.
     *
     * @param array $row        Row data.
     * @param int   $row_number CSV row number.
     * @return array|null Error object or null.
     */
    private static function validate_difficulty( $row, $row_number ) {

        $value   = isset($row['difficulty']) ? strtolower(trim($row['difficulty'])) : '';
        $allowed = array_keys(MDCAT_Platform_Questions_Handler::get_allowed_difficulties());

        if ('' !== $value && !in_array($value, $allowed, true)) {
            return [
                'row'     => $row_number,
                'field'   => 'difficulty',
                'message' => sprintf(
                    /* translators: 1: row number */
                    __('Row %1$d: Difficulty must be easy, medium, or hard.', 'mdcat-platform'),
                    $row_number
                ),
            ];
        }

        return null;
    }

    /**
     * Validate the marks field is a positive number.
     *
     * @param array $row        Row data.
     * @param int   $row_number CSV row number.
     * @return array|null Error object or null.
     */
    private static function validate_marks( $row, $row_number ) {

        $value = isset($row['marks']) ? trim($row['marks']) : '';

        if ('' !== $value && (!is_numeric($value) || (float) $value <= 0)) {
            return [
                'row'     => $row_number,
                'field'   => 'marks',
                'message' => sprintf(
                    /* translators: %d: row number */
                    __('Row %d: Marks must be a positive number.', 'mdcat-platform'),
                    $row_number
                ),
            ];
        }

        return null;
    }
}
