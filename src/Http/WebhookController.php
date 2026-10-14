<?php
/**
 * REST Webhook Endpoint for Google Push Notifications.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Http;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Sync\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebhookController
 */
class WebhookController {

	/**
	 * REST route namespace.
	 */
	public const REST_NAMESPACE = 'wpcalibrate/v1';

	/**
	 * REST route.
	 */
	public const REST_ROUTE = '/webhook';

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
	 * @param SyncService $sync_service Sync service.
	 * @param Logger|null $logger       Logger instance.
	 */
	public function __construct( SyncService $sync_service, ?Logger $logger = null ) {
		$this->sync_service = $sync_service;
		$this->logger       = $logger;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE, // POST.
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'validate_webhook_permission' ),
			)
		);
	}

	/**
	 * Permission callback: Validate Google push headers against active channels in DB.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function validate_webhook_permission( WP_REST_Request $request ): bool|WP_Error {
		$channel_id    = (string) $request->get_header( 'x_goog_channel_id' );
		$channel_token = (string) $request->get_header( 'x_goog_channel_token' );
		$resource_id   = (string) $request->get_header( 'x_goog_resource_id' );

		if ( empty( $channel_id ) || empty( $channel_token ) || empty( $resource_id ) ) {
			if ( $this->logger ) {
				$this->logger->warning( 'webhook', 'Rejected request: Missing required Google push headers.' );
			}
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid push notification headers.', 'wpcalibrate-calendar-sync' ),
				array( 'status' => 403 )
			);
		}

		$matched_cal = $this->find_matching_calendar( $channel_id, $channel_token, $resource_id );
		if ( null === $matched_cal ) {
			if ( $this->logger ) {
				$this->logger->warning(
					'webhook',
					sprintf( 'Rejected push notification: No matching local channel for channel ID %s.', $channel_id )
				);
			}
			return new WP_Error(
				'rest_forbidden',
				__( 'Unrecognized channel credentials.', 'wpcalibrate-calendar-sync' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle valid push notification request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$channel_id    = (string) $request->get_header( 'x_goog_channel_id' );
		$channel_token = (string) $request->get_header( 'x_goog_channel_token' );
		$resource_id   = (string) $request->get_header( 'x_goog_resource_id' );
		$resource_state= (string) $request->get_header( 'x_goog_resource_state' );

		$matched_cal = $this->find_matching_calendar( $channel_id, $channel_token, $resource_id );
		if ( null === $matched_cal ) {
			return new WP_REST_Response( array( 'status' => 'not_found' ), 404 );
		}

		// When Google first watches a channel, it sends state 'sync'.
		if ( 'sync' === $resource_state ) {
			if ( $this->logger ) {
				$this->logger->info(
					'webhook_sync_handshake',
					sprintf( 'Received channel verification handshake for calendar %s.', $matched_cal )
				);
			}
			return new WP_REST_Response( array( 'status' => 'handshake_acknowledged' ), 200 );
		}

		if ( $this->logger ) {
			$this->logger->info(
				'webhook_notification',
				sprintf( 'Push notification received for calendar %s (State: %s). Scheduling sync.', $matched_cal, $resource_state )
			);
		}

		// Trigger incremental sync for this specific calendar.
		$this->sync_service->sync_calendar( $matched_cal, false );

		return new WP_REST_Response( array( 'status' => 'synchronized' ), 200 );
	}

	/**
	 * Match channel headers against configured calendars.
	 *
	 * @param string $channel_id    Channel UUID.
	 * @param string $channel_token Security token.
	 * @param string $resource_id   Google Resource ID.
	 * @return string|null Matching Google Calendar ID or null.
	 */
	private function find_matching_calendar( string $channel_id, string $channel_token, string $resource_id ): ?string {
		$calendars = $this->sync_service->get_configured_calendars();

		foreach ( $calendars as $cal_id => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}
			if (
				! empty( $config['channel_id'] )
				&& hash_equals( (string) $config['channel_id'], $channel_id )
				&& ! empty( $config['channel_token'] )
				&& hash_equals( (string) $config['channel_token'], $channel_token )
				&& ! empty( $config['resource_id'] )
				&& hash_equals( (string) $config['resource_id'], $resource_id )
			) {
				return (string) $cal_id;
			}
		}

		return null;
	}
}
