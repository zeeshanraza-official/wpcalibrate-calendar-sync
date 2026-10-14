<?php
/**
 * Concurrency and Mutual Exclusion Lock Manager.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SyncLock
 *
 * Prevents overlapping synchronization runs for the same calendar using WordPress transients.
 */
class SyncLock {

	/**
	 * Prefix for transient keys.
	 */
	private const TRANSIENT_PREFIX = 'wpcs_lock_';

	/**
	 * Default lock time-to-live in seconds (5 minutes).
	 */
	public const DEFAULT_TTL = 300;

	/**
	 * Acquire an exclusive lock for a specific calendar.
	 *
	 * @param string $calendar_id Calendar identifier.
	 * @param int    $ttl         Time to live in seconds.
	 * @return bool True if lock acquired, false if already locked.
	 */
	public function acquire( string $calendar_id, int $ttl = self::DEFAULT_TTL ): bool {
		$key   = $this->get_transient_key( $calendar_id );
		$token = wp_generate_uuid4();

		// Check if already locked.
		$existing = get_transient( $key );
		if ( false !== $existing ) {
			return false;
		}

		// Set transient lock.
		return set_transient( $key, $token, max( 30, $ttl ) );
	}

	/**
	 * Release lock for a calendar.
	 *
	 * @param string $calendar_id Calendar identifier.
	 * @return bool
	 */
	public function release( string $calendar_id ): bool {
		$key = $this->get_transient_key( $calendar_id );
		return delete_transient( $key );
	}

	/**
	 * Check whether a calendar is currently locked.
	 *
	 * @param string $calendar_id Calendar identifier.
	 * @return bool
	 */
	public function is_locked( string $calendar_id ): bool {
		$key = $this->get_transient_key( $calendar_id );
		return false !== get_transient( $key );
	}

	/**
	 * Build safe transient key for a calendar.
	 *
	 * @param string $calendar_id Raw calendar ID.
	 * @return string Sanitized transient key (max 64 chars in WP).
	 */
	private function get_transient_key( string $calendar_id ): string {
		$hash = md5( $calendar_id );
		return self::TRANSIENT_PREFIX . $hash;
	}
}
