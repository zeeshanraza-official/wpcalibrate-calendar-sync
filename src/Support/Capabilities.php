<?php
/**
 * Capability definitions and permission helpers.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Capabilities
 */
class Capabilities {

	/**
	 * Default capability required to manage calendar synchronization.
	 */
	public const MANAGE_CALENDAR = 'manage_options';

	/**
	 * Check if the current user can manage calendar synchronization.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		$capability = (string) apply_filters( 'wpcs_calendar_capability', self::MANAGE_CALENDAR );
		return current_user_can( $capability );
	}

	/**
	 * Enforce management capability, dying with 403 if unauthorized.
	 *
	 * @return void
	 */
	public static function verify_manage(): void {
		if ( ! self::can_manage() ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'wpcalibrate-calendar-sync' ),
				esc_html__( 'Forbidden', 'wpcalibrate-calendar-sync' ),
				array( 'response' => 403 )
			);
		}
	}
}
