<?php
/**
 * Diagnostic and audit logger with secret redaction and auto-pruning.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Logging;

use WPCalibrate\CalendarSync\Support\Sanitization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 */
class Logger {

	/**
	 * Option name for storing recent logs.
	 */
	public const OPTION_NAME = 'wpcs_recent_logs';

	/**
	 * Maximum number of retained log entries.
	 */
	public const MAX_ENTRIES = 500;

	/**
	 * Default retention duration in days.
	 */
	public const DEFAULT_RETENTION_DAYS = 14;

	/**
	 * Log levels.
	 */
	public const LEVEL_INFO    = 'info';
	public const LEVEL_WARNING = 'warning';
	public const LEVEL_ERROR   = 'error';

	/**
	 * Record an info log.
	 *
	 * @param string               $operation Operation name.
	 * @param string               $message   Log message.
	 * @param array<string, mixed> $context   Additional metadata.
	 * @return void
	 */
	public function info( string $operation, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $operation, $message, $context );
	}

	/**
	 * Record a warning log.
	 *
	 * @param string               $operation Operation name.
	 * @param string               $message   Log message.
	 * @param array<string, mixed> $context   Additional metadata.
	 * @return void
	 */
	public function warning( string $operation, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARNING, $operation, $message, $context );
	}

	/**
	 * Record an error log.
	 *
	 * @param string               $operation Operation name.
	 * @param string               $message   Log message.
	 * @param array<string, mixed> $context   Additional metadata.
	 * @return void
	 */
	public function error( string $operation, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $operation, $message, $context );
	}

	/**
	 * Store a sanitized log entry.
	 *
	 * @param string               $level     Severity level.
	 * @param string               $operation Operation name.
	 * @param string               $message   Log message.
	 * @param array<string, mixed> $context   Additional context.
	 * @return void
	 */
	public function log( string $level, string $operation, string $message, array $context = array() ): void {
		$clean_message = Sanitization::redact_secrets( $message );
		$clean_context = array();

		foreach ( $context as $k => $v ) {
			if ( is_string( $v ) ) {
				$clean_context[ $k ] = Sanitization::redact_secrets( $v );
			} elseif ( is_scalar( $v ) || null === $v ) {
				$clean_context[ $k ] = $v;
			} else {
				$encoded = wp_json_encode( $v );
				$clean_context[ $k ] = false !== $encoded ? Sanitization::redact_secrets( $encoded ) : '[data]';
			}
		}

		$entry = array(
			'id'        => wp_generate_uuid4(),
			'timestamp' => gmdate( 'Y-m-d H:i:s' ),
			'level'     => $level,
			'operation' => sanitize_text_field( $operation ),
			'message'   => $clean_message,
			'context'   => $clean_context,
		);

		$logs = $this->get_logs();
		array_unshift( $logs, $entry );

		// Cap maximum entries.
		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_NAME, $logs, false );
	}

	/**
	 * Retrieve stored logs.
	 *
	 * @param string|null $level Optional level filter.
	 * @param int         $limit Limit returned count.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_logs( ?string $level = null, int $limit = 100 ): array {
		$logs = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $logs ) ) {
			return array();
		}

		if ( ! empty( $level ) ) {
			$logs = array_values(
				array_filter(
					$logs,
					static function ( $item ) use ( $level ) {
						return isset( $item['level'] ) && $item['level'] === $level;
					}
				)
			);
		}

		if ( $limit > 0 && count( $logs ) > $limit ) {
			$logs = array_slice( $logs, 0, $limit );
		}

		return $logs;
	}

	/**
	 * Purge logs older than given number of days.
	 *
	 * @param int $days Number of days retention.
	 * @return int Number of purged items.
	 */
	public function purge_expired( int $days = self::DEFAULT_RETENTION_DAYS ): int {
		$logs = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $logs ) || empty( $logs ) ) {
			return 0;
		}

		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$original_count = count( $logs );

		$filtered = array_values(
			array_filter(
				$logs,
				static function ( $entry ) use ( $threshold ) {
					return isset( $entry['timestamp'] ) && $entry['timestamp'] >= $threshold;
				}
			)
		);

		$removed = $original_count - count( $filtered );
		if ( $removed > 0 ) {
			update_option( self::OPTION_NAME, $filtered, false );
		}

		return $removed;
	}

	/**
	 * Clear all logs entirely.
	 *
	 * @return void
	 */
	public function clear_all(): void {
		delete_option( self::OPTION_NAME );
	}
}
