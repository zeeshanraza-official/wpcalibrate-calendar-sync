<?php
/**
 * WP-Cron Scheduled Recovery and Channel Renewal.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Sync;

use WPCalibrate\CalendarSync\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecoveryScheduler
 */
class RecoveryScheduler {

	/**
	 * Cron action hook name.
	 */
	public const HOOK_NAME = 'wpcs_recovery_sync_cron';

	/**
	 * Settings option name for recovery interval in minutes.
	 */
	public const OPTION_INTERVAL = 'wpcs_recovery_interval';

	/**
	 * Default interval in minutes.
	 */
	public const DEFAULT_INTERVAL = 15;

	/**
	 * Sync service.
	 */
	private SyncService $sync_service;

	/**
	 * Push notification manager.
	 */
	private PushNotificationManager $push_manager;

	/**
	 * Logger.
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param SyncService             $sync_service Sync service.
	 * @param PushNotificationManager $push_manager Push notification manager.
	 * @param Logger|null             $logger       Logger instance.
	 */
	public function __construct(
		SyncService $sync_service,
		PushNotificationManager $push_manager,
		?Logger $logger = null
	) {
		$this->sync_service = $sync_service;
		$this->push_manager = $push_manager;
		$this->logger       = $logger;
	}

	/**
	 * Register custom WP-Cron schedules.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_cron_schedules( array $schedules ): array {
		$interval_min = $this->get_interval();

		$schedules['wpcs_interval'] = array(
			'interval' => $interval_min * MINUTE_IN_SECONDS,
			'display'  => sprintf(
				/* translators: %d: interval in minutes */
				__( 'Every %d minutes (WPCalibrate Calendar Sync)', 'wpcalibrate-calendar-sync' ),
				$interval_min
			),
		);

		return $schedules;
	}

	/**
	 * Get configured interval in minutes.
	 *
	 * @return int
	 */
	public function get_interval(): int {
		$val = (int) get_option( self::OPTION_INTERVAL, self::DEFAULT_INTERVAL );
		return max( 5, min( 1440, $val ) ); // Bounded between 5 mins and 24 hours.
	}

	/**
	 * Schedule periodic cron if not already scheduled.
	 *
	 * @return void
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK_NAME ) ) {
			wp_schedule_event( time() + 60, 'wpcs_interval', self::HOOK_NAME );
		}
	}

	/**
	 * Unschedule cron event.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK_NAME );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_NAME );
			$timestamp = wp_next_scheduled( self::HOOK_NAME );
		}
	}

	/**
	 * Reschedule with new interval.
	 *
	 * @param int $interval_minutes New interval in minutes.
	 * @return void
	 */
	public function update_interval( int $interval_minutes ): void {
		$safe_interval = max( 5, min( 1440, $interval_minutes ) );
		update_option( self::OPTION_INTERVAL, $safe_interval, false );
		$this->unschedule();
		$this->schedule();
	}

	/**
	 * Execute the scheduled recovery tasks.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( $this->logger ) {
			$this->logger->info( 'cron_recovery', 'Starting periodic WP-Cron recovery and sync.' );
		}

		// 1. Renew any expiring push channels.
		$renewed = $this->push_manager->renew_expiring_channels();

		// 2. Perform incremental synchronization for all enabled calendars.
		$results = $this->sync_service->sync_all( false );

		// 3. Purge expired logs.
		if ( $this->logger ) {
			$purged = $this->logger->purge_expired();
			$this->logger->info(
				'cron_recovery',
				sprintf( 'Periodic recovery finished. Renewed %d channels, purged %d old log entries.', $renewed, $purged )
			);
		}
	}
}
