<?php

if (!defined('ABSPATH')) {
    exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

/**
 * JWT token service for the MDCAT Platform REST API.
 *
 * Handles token generation, decoding, and validation. All
 * configuration (secret key, expiry durations, algorithm, issuer)
 * is sourced exclusively from MDCAT_Platform_JWT_Config.
 *
 * This class is a pure token service — it contains no configuration
 * logic, no WordPress user management, and no HTTP concerns.
 *
 * Usage:
 *
 *     // Generate tokens after successful authentication.
 *     $access  = MDCAT_Platform_JWT_Handler::generate_access_token(42, 'user@example.com');
 *     $refresh = MDCAT_Platform_JWT_Handler::generate_refresh_token(42);
 *
 *     // Validate an incoming access token.
 *     $user_id = MDCAT_Platform_JWT_Handler::validate_access_token($token);
 *     if (is_wp_error($user_id)) {
 *         // Handle error: expired, tampered, wrong type, etc.
 *     }
 */
class MDCAT_Platform_JWT_Handler {

    /**
     * Generate a signed access token.
     *
     * The access token carries the user's ID and email in its payload.
     * It is used in the Authorization header for all authenticated
     * API requests. Lifetime is controlled by JWT_Config.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $email   User email address.
     * @return string|WP_Error Signed JWT string, or WP_Error if the secret key is unavailable.
     */
    public static function generate_access_token( $user_id, $email ) {

        $secret = MDCAT_Platform_JWT_Config::get_secret_key();

        if (is_wp_error($secret)) {
            return $secret;
        }

        $now    = time();
        $expiry = MDCAT_Platform_JWT_Config::get_access_token_expiry();

        $payload = [
            'iss'  => MDCAT_Platform_JWT_Config::get_issuer(),
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $expiry,
            'sub'  => absint($user_id),
            'type' => MDCAT_Platform_JWT_Config::TOKEN_TYPE_ACCESS,
            'data' => [
                'user_id' => absint($user_id),
                'email'   => sanitize_email($email),
            ],
        ];

        return JWT::encode($payload, $secret, MDCAT_Platform_JWT_Config::ALGORITHM);
    }

    /**
     * Generate a signed refresh token.
     *
     * The refresh token carries only the user ID — no email or other
     * user data. It is used exclusively by the /auth/refresh endpoint
     * to obtain a new access token without re-entering credentials.
     *
     * The refresh token intentionally omits user data because:
     *   - It has a long lifetime (30 days by default).
     *   - User data (email, name) may change during that period.
     *   - The refresh endpoint loads fresh user data when issuing
     *     the new access token.
     *
     * @param int $user_id WordPress user ID.
     * @return string|WP_Error Signed JWT string, or WP_Error if the secret key is unavailable.
     */
    public static function generate_refresh_token( $user_id ) {

        $secret = MDCAT_Platform_JWT_Config::get_secret_key();

        if (is_wp_error($secret)) {
            return $secret;
        }

        $now    = time();
        $expiry = MDCAT_Platform_JWT_Config::get_refresh_token_expiry();

        $payload = [
            'iss'  => MDCAT_Platform_JWT_Config::get_issuer(),
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $expiry,
            'sub'  => absint($user_id),
            'type' => MDCAT_Platform_JWT_Config::TOKEN_TYPE_REFRESH,
        ];

        return JWT::encode($payload, $secret, MDCAT_Platform_JWT_Config::ALGORITHM);
    }

    /**
     * Decode and verify a JWT token.
     *
     * Validates the token's signature, expiry, and structure using
     * the firebase/php-jwt library. Returns the decoded payload
     * object on success, or a WP_Error with a specific error code
     * indicating why validation failed.
     *
     * Error codes returned:
     *   - token_expired       — token's exp claim is in the past.
     *   - token_invalid       — signature does not match.
     *   - token_not_valid_yet — token's nbf claim is in the future.
     *   - token_malformed     — token structure is invalid.
     *   - token_error         — unexpected decoding failure.
     *
     * @param string $token Raw JWT string.
     * @return object|WP_Error Decoded payload object, or WP_Error.
     */
    public static function decode_token( $token ) {

        $secret = MDCAT_Platform_JWT_Config::get_secret_key();

        if (is_wp_error($secret)) {
            return $secret;
        }

        try {

            $decoded = JWT::decode(
                $token,
                new Key($secret, MDCAT_Platform_JWT_Config::ALGORITHM)
            );

            return $decoded;

        } catch (ExpiredException $e) {

            return new WP_Error(
                'token_expired',
                __('Token has expired.', 'mdcat-platform')
            );

        } catch (SignatureInvalidException $e) {

            return new WP_Error(
                'token_invalid',
                __('Token signature is invalid.', 'mdcat-platform')
            );

        } catch (BeforeValidException $e) {

            return new WP_Error(
                'token_not_valid_yet',
                __('Token is not yet valid.', 'mdcat-platform')
            );

        } catch (\UnexpectedValueException $e) {

            return new WP_Error(
                'token_malformed',
                __('Token is malformed.', 'mdcat-platform')
            );

        } catch (\Exception $e) {

            return new WP_Error(
                'token_error',
                __('Token could not be processed.', 'mdcat-platform')
            );
        }
    }

    /**
     * Validate an access token and return the user ID.
     *
     * Decodes the token, then verifies that the 'type' claim is
     * 'access'. This prevents refresh tokens from being used as
     * access tokens in the Authorization header.
     *
     * @param string $token Raw JWT string.
     * @return int|WP_Error WordPress user ID, or WP_Error on failure.
     */
    public static function validate_access_token( $token ) {

        $decoded = self::decode_token($token);

        if (is_wp_error($decoded)) {
            return $decoded;
        }

        if (!isset($decoded->type) || $decoded->type !== MDCAT_Platform_JWT_Config::TOKEN_TYPE_ACCESS) {
            return new WP_Error(
                'token_type_mismatch',
                __('Expected an access token.', 'mdcat-platform')
            );
        }

        if (!isset($decoded->sub) || absint($decoded->sub) === 0) {
            return new WP_Error(
                'token_invalid_subject',
                __('Token does not contain a valid user identifier.', 'mdcat-platform')
            );
        }

        return absint($decoded->sub);
    }

    /**
     * Validate a refresh token and return the user ID.
     *
     * Decodes the token, then verifies that the 'type' claim is
     * 'refresh'. This prevents access tokens from being used to
     * obtain new tokens via the refresh endpoint.
     *
     * @param string $token Raw JWT string.
     * @return int|WP_Error WordPress user ID, or WP_Error on failure.
     */
    public static function validate_refresh_token( $token ) {

        $decoded = self::decode_token($token);

        if (is_wp_error($decoded)) {
            return $decoded;
        }

        if (!isset($decoded->type) || $decoded->type !== MDCAT_Platform_JWT_Config::TOKEN_TYPE_REFRESH) {
            return new WP_Error(
                'token_type_mismatch',
                __('Expected a refresh token.', 'mdcat-platform')
            );
        }

        if (!isset($decoded->sub) || absint($decoded->sub) === 0) {
            return new WP_Error(
                'token_invalid_subject',
                __('Token does not contain a valid user identifier.', 'mdcat-platform')
            );
        }

        return absint($decoded->sub);
    }
}
