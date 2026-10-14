<?php
/**
 * Date and time normalization utilities.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DateTime
 */
class DateTime {

	/**
	 * UTC timezone instance.
	 */
	private static ?DateTimeZone $utc_timezone = null;

	/**
	 * Get UTC DateTimeZone.
	 *
	 * @return DateTimeZone
	 */
	public static function utc(): DateTimeZone {
		if ( null === self::$utc_timezone ) {
			self::$utc_timezone = new DateTimeZone( 'UTC' );
		}
		return self::$utc_timezone;
	}

	/**
	 * Get WordPress site configured timezone.
	 *
	 * @return DateTimeZone
	 */
	public static function site_timezone(): DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		$tz_string = get_option( 'timezone_string' );
		if ( ! empty( $tz_string ) ) {
			try {
				return new DateTimeZone( $tz_string );
			} catch ( Exception ) {
				// Fallback to offset.
			}
		}
		$offset  = (float) get_option( 'gmt_offset', 0 );
		$hours   = (int) $offset;
		$minutes = (int) ( ( $offset - $hours ) * 60 );
		$sign    = $offset < 0 ? '-' : '+';
		return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), abs( $minutes ) ) );
	}

	/**
	 * Convert an ISO 8601 or date/time string to UTC MySQL format (Y-m-d H:i:s).
	 *
	 * @param string      $datetime_str Input date/time string.
	 * @param string|null $fallback_tz  Timezone identifier if not included in the string.
	 * @return string MySQL formatted UTC datetime string.
	 */
	public static function to_utc_mysql( string $datetime_str, ?string $fallback_tz = null ): string {
		$datetime_str = trim( $datetime_str );
		if ( '' === $datetime_str ) {
			return '';
		}

		try {
			$tz = null;
			if ( ! empty( $fallback_tz ) ) {
				try {
					$tz = new DateTimeZone( $fallback_tz );
				} catch ( Exception ) {
					$tz = self::utc();
				}
			}

			$dt = new DateTimeImmutable( $datetime_str, $tz ?? self::utc() );
			$dt_utc = $dt->setTimezone( self::utc() );
			return $dt_utc->format( 'Y-m-d H:i:s' );
		} catch ( Exception ) {
			return '';
		}
	}

	/**
	 * Format an all-day date for storage (Y-m-d 00:00:00 in UTC).
	 *
	 * @param string $date_str Y-m-d format date string.
	 * @return string
	 */
	public static function all_day_to_utc_mysql( string $date_str ): string {
		$parts = explode( 'T', $date_str );
		$date_only = trim( $parts[0] );
		return $date_only . ' 00:00:00';
	}

	/**
	 * Format UTC MySQL datetime to site-local display string.
	 *
	 * @param string $utc_datetime Y-m-d H:i:s UTC string.
	 * @param string $format       PHP date format (empty for WP default).
	 * @param bool   $is_all_day   Whether this is an all-day event.
	 * @return string
	 */
	public static function to_site_display( string $utc_datetime, string $format = '', bool $is_all_day = false ): string {
		if ( empty( $utc_datetime ) ) {
			return '';
		}

		try {
			if ( $is_all_day ) {
				// All day dates must not be shifted by timezone offset.
				$dt = new DateTimeImmutable( $utc_datetime, self::utc() );
				$date_format = empty( $format ) ? (string) get_option( 'date_format', 'F j, Y' ) : $format;
				if ( function_exists( 'wp_date' ) ) {
					return wp_date( $date_format, $dt->getTimestamp(), self::utc() );
				}
				return $dt->format( $date_format );
			}

			$dt_utc = new DateTimeImmutable( $utc_datetime, self::utc() );
			$dt_local = $dt_utc->setTimezone( self::site_timezone() );

			if ( empty( $format ) ) {
				$format = sprintf(
					'%s %s',
					(string) get_option( 'date_format', 'F j, Y' ),
					(string) get_option( 'time_format', 'g:i a' )
				);
			}

			if ( function_exists( 'wp_date' ) ) {
				return wp_date( $format, $dt_local->getTimestamp(), self::site_timezone() );
			}

			return $dt_local->format( $format );
		} catch ( Exception ) {
			return $utc_datetime;
		}
	}

	/**
	 * Get ISO 8601 string for Google API payload.
	 *
	 * @param string      $utc_mysql  UTC datetime string.
	 * @param bool        $is_all_day Whether it is all-day.
	 * @param string|null $timezone   Timezone identifier.
	 * @return array{dateTime?: string, date?: string, timeZone?: string}
	 */
	public static function to_google_format( string $utc_mysql, bool $is_all_day = false, ?string $timezone = null ): array {
		if ( $is_all_day ) {
			$date_only = substr( $utc_mysql, 0, 10 );
			return array(
				'date' => $date_only,
			);
		}

		try {
			$dt_utc = new DateTimeImmutable( $utc_mysql, self::utc() );
			$target_tz = ! empty( $timezone ) ? new DateTimeZone( $timezone ) : self::site_timezone();
			$dt_local = $dt_utc->setTimezone( $target_tz );

			return array(
				'dateTime' => $dt_local->format( 'c' ),
				'timeZone' => $target_tz->getName(),
			);
		} catch ( Exception ) {
			return array(
				'dateTime' => ( new DateTimeImmutable( $utc_mysql, self::utc() ) )->format( 'c' ),
				'timeZone' => 'UTC',
			);
		}
	}
}
