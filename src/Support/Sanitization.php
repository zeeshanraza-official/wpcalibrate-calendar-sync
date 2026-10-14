<?php
/**
 * Sanitization and Redaction Helpers.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sanitization
 */
class Sanitization {

	/**
	 * Redact sensitive substrings (client secrets, tokens, codes) from any log or string.
	 *
	 * @param string $content Raw string.
	 * @return string Redacted string.
	 */
	public static function redact_secrets( string $content ): string {
		// Patterns for Google tokens, refresh tokens, auth codes, client secrets.
		$patterns = array(
			'/("client_secret"\s*:\s*")[^"]+(")/i'        => '$1[REDACTED]$2',
			'/("refresh_token"\s*:\s*")[^"]+(")/i'        => '$1[REDACTED]$2',
			'/("access_token"\s*:\s*")[^"]+(")/i'         => '$1[REDACTED]$2',
			'/(client_secret=)[^& \r\n]+/i'              => '$1[REDACTED]',
			'/(code=)[^& \r\n]+/i'                       => '$1[REDACTED]',
			'/(token=)[^& \r\n]+/i'                      => '$1[REDACTED]',
			'/(\b1\/\/[a-zA-Z0-9_\-]{20,}\b)/'          => '[REDACTED_REFRESH_TOKEN]',
			'/(\bya29\.[a-zA-Z0-9_\.\-]{25,}\b)/'        => '[REDACTED_ACCESS_TOKEN]',
			'/(\bGOCSPX-[a-zA-Z0-9_\-]{20,}\b)/'        => '[REDACTED_CLIENT_SECRET]',
		);

		return (string) preg_replace( array_keys( $patterns ), array_values( $patterns ), $content );
	}

	/**
	 * Mask a secret string for display in settings UI (e.g., ••••••••abcd).
	 *
	 * @param string $secret Plain text secret.
	 * @param int    $visible_chars Number of trailing characters to reveal.
	 * @return string Masked string.
	 */
	public static function mask_secret( string $secret, int $visible_chars = 4 ): string {
		$length = strlen( $secret );
		if ( 0 === $length ) {
			return '';
		}
		if ( $length <= $visible_chars ) {
			return str_repeat( '•', $length );
		}
		$hidden = str_repeat( '•', 8 );
		$suffix = substr( $secret, -$visible_chars );
		return $hidden . $suffix;
	}

	/**
	 * Sanitize calendar configuration arrays.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized array.
	 */
	public static function sanitize_calendar_config( array $input ): array {
		$valid_directions = array( 'google_to_wp', 'wp_to_google', 'two_way' );

		$direction = sanitize_key( (string) ( $input['direction'] ?? 'two_way' ) );
		if ( ! in_array( $direction, $valid_directions, true ) ) {
			$direction = 'two_way';
		}

		return array(
			'id'              => sanitize_text_field( (string) ( $input['id'] ?? '' ) ),
			'name'            => sanitize_text_field( (string) ( $input['name'] ?? '' ) ),
			'timezone'        => sanitize_text_field( (string) ( $input['timezone'] ?? 'UTC' ) ),
			'enabled'         => ! empty( $input['enabled'] ),
			'direction'       => $direction,
			'sync_token'      => sanitize_text_field( (string) ( $input['sync_token'] ?? '' ) ),
			'last_sync_utc'   => sanitize_text_field( (string) ( $input['last_sync_utc'] ?? '' ) ),
			'last_attempt_utc'=> sanitize_text_field( (string) ( $input['last_attempt_utc'] ?? '' ) ),
			'last_error'      => sanitize_text_field( (string) ( $input['last_error'] ?? '' ) ),
			'channel_id'      => sanitize_text_field( (string) ( $input['channel_id'] ?? '' ) ),
			'resource_id'     => sanitize_text_field( (string) ( $input['resource_id'] ?? '' ) ),
			'channel_token'   => sanitize_text_field( (string) ( $input['channel_token'] ?? '' ) ),
			'channel_expires' => sanitize_text_field( (string) ( $input['channel_expires'] ?? '' ) ),
		);
	}
}
