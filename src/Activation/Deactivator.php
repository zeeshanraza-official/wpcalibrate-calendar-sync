<?php
/**
 * Plugin Deactivation Handler.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Activation;

use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Sync\RecoveryScheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Execute deactivation cleanup.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// 1. Unschedule plugin cron event.
		$timestamp = wp_next_scheduled( RecoveryScheduler::HOOK_NAME );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, RecoveryScheduler::HOOK_NAME );
			$timestamp = wp_next_scheduled( RecoveryScheduler::HOOK_NAME );
		}

		$logger = new Logger();
		$logger->info( 'deactivation', 'WPCalibrate Calendar Sync deactivated. Scheduled crons cleared. Event data and credentials preserved.' );
	}
}
