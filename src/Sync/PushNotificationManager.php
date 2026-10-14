<?php
/**
 * Push Notification (Google Watch Channels) Manager.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Sync;

use Exception;
use WPCalibrate\CalendarSync\Api\GoogleCalendarService;
use WPCalibrate\CalendarSync\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PushNotificationManager
 */
class PushNotificationManager {

	/**
	 * Default channel duration in seconds (7 days - max allowed by Google).
	 */
	public const CHANNEL_TTL = 604800;

	/**
	 * Lead time before expiration to trigger renewal (24 hours).
	 */
	public const RENEWAL_BUFFER = 86400;

	/**
	 * Google Calendar service.
	 */
	private GoogleCalendarService $google_service;

	/**
	 * Sync service.
	 */
	private SyncService $sync_service;

	/**
	 * Logger.
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param GoogleCalendarService $google_service Google calendar API service.
	 * @param SyncService           $sync_service   Sync service.
	 * @param Logger|null           $logger         Logger instance.
	 */
	public function __construct(
		GoogleCalendarService $google_service,
		SyncService $sync_service,
		?Logger $logger = null
	) {
		$this->google_service = $google_service;
		$this->sync_service   = $sync_service;
		$this->logger         = $logger;
	}

	/**
	 * Check if push notifications are possible in this environment.
	 *
	 * @return bool
	 */
	public static function is_webhook_eligible(): bool {
		$site_url = get_site_url();
		$is_ssl   = is_ssl() || str_starts_with( $site_url, 'https://' );
		$is_local = in_array(
			wp_parse_url( $site_url, PHP_URL_HOST ),
			array( 'localhost', '127.0.0.1', '::1', 'test.local' ),
			true
		);

		return $is_ssl && ! $is_local;
	}

	/**
	 * Get the public REST webhook URL.
	 *
	 * @return string
	 */
	public static function get_webhook_url(): string {
		return rest_url( 'wpcalibrate/v1/webhook' );
	}

	/**
	 * Setup a push notification channel for a specific calendar.
	 *
	 * @param string $calendar_id Calendar ID.
	 * @return array{success: bool, message: string}
	 */
	public function register_channel( string $calendar_id ): array {
		if ( ! self::is_webhook_eligible() ) {
			return array(
				'success' => false,
				'message' => __( 'Site is not eligible for Google push notifications (Requires publicly accessible HTTPS site). WP-Cron scheduled sync will be used instead.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$calendars = $this->sync_service->get_configured_calendars();
		if ( ! isset( $calendars[ $calendar_id ] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Calendar not configured.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$config = $calendars[ $calendar_id ];

		// If an existing channel is active, stop it before creating a new one.
		if ( ! empty( $config['channel_id'] ) && ! empty( $config['resource_id'] ) ) {
			$this->google_service->stop_channel( $config['channel_id'], $config['resource_id'] );
		}

		$channel_id    = wp_generate_uuid4();
		$channel_token = wp_generate_password( 48, false );
		$webhook_url   = self::get_webhook_url();

		try {
			$subscription = $this->google_service->watch_calendar(
				$calendar_id,
				$channel_id,
				$webhook_url,
				$channel_token,
				self::CHANNEL_TTL
			);

			$config['channel_id']      = $channel_id;
			$config['resource_id']     = $subscription['resourceId'];
			$config['channel_token']    = $channel_token;
			// Convert millisecond timestamp to UTC datetime if returned by Google.
			$expires_ms = (float) $subscription['expiration'];
			$config['channel_expires'] = gmdate( 'Y-m-d H:i:s', (int) ( $expires_ms / 1000 ) );

			$calendars[ $calendar_id ] = $config;
			$this->sync_service->save_configured_calendars( $calendars );

			if ( $this->logger ) {
				$this->logger->info(
					'watch_channel',
					sprintf( 'Registered push channel for calendar %s (expires: %s).', $calendar_id, $config['channel_expires'] )
				);
			}

			return array(
				'success' => true,
				'message' => __( 'Push notifications enabled successfully.', 'wpcalibrate-calendar-sync' ),
			);
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->warning(
					'watch_channel',
					sprintf( 'Could not register push notifications for %s: %s', $calendar_id, $e->getMessage() )
				);
			}
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Stop active push channel for a calendar.
	 *
	 * @param string $calendar_id Calendar ID.
	 * @return bool
	 */
	public function stop_channel( string $calendar_id ): bool {
		$calendars = $this->sync_service->get_configured_calendars();
		if ( ! isset( $calendars[ $calendar_id ] ) ) {
			return false;
		}

		$config = $calendars[ $calendar_id ];
		if ( empty( $config['channel_id'] ) || empty( $config['resource_id'] ) ) {
			return true;
		}

		$stopped = $this->google_service->stop_channel( $config['channel_id'], $config['resource_id'] );

		unset( $config['channel_id'], $config['resource_id'], $config['channel_token'], $config['channel_expires'] );
		$calendars[ $calendar_id ] = $config;
		$this->sync_service->save_configured_calendars( $calendars );

		return $stopped;
	}

	/**
	 * Check and renew push notification channels nearing expiration.
	 *
	 * @return int Number of renewed channels.
	 */
	public function renew_expiring_channels(): int {
		if ( ! self::is_webhook_eligible() ) {
			return 0;
		}

		$calendars = $this->sync_service->get_configured_calendars();
		$renewed   = 0;
		$now       = time();

		foreach ( $calendars as $cal_id => $config ) {
			if ( empty( $config['enabled'] ) || empty( $config['channel_expires'] ) ) {
				continue;
			}

			$expires_time = strtotime( $config['channel_expires'] . ' UTC' );
			if ( false !== $expires_time && ( $expires_time - $now ) <= self::RENEWAL_BUFFER ) {
				$res = $this->register_channel( (string) $cal_id );
				if ( $res['success'] ) {
					$renewed++;
				}
			}
		}

		return $renewed;
	}
}
