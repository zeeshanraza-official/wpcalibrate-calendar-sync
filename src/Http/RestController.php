<?php
/**
 * REST API Controller for Calendar Events and Diagnostics.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Http;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPCalibrate\CalendarSync\Events\EventRepository;
use WPCalibrate\CalendarSync\Support\Capabilities;
use WPCalibrate\CalendarSync\Sync\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestController
 */
class RestController {

	/**
	 * Namespace.
	 */
	public const REST_NAMESPACE = 'wpcalibrate/v1';

	/**
	 * Event repository.
	 */
	private EventRepository $repository;

	/**
	 * Sync service.
	 */
	private SyncService $sync_service;

	/**
	 * Constructor.
	 *
	 * @param EventRepository $repository   Event repository.
	 * @param SyncService     $sync_service Sync service.
	 */
	public function __construct( EventRepository $repository, SyncService $sync_service ) {
		$this->repository   = $repository;
		$this->sync_service = $sync_service;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Public read-only event retrieval for frontend calendars.
		register_rest_route(
			self::REST_NAMESPACE,
			'/events',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_events' ),
				'permission_callback' => '__return_true', // Public events query.
				'args'                => array(
					'calendar_ids' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'start_utc'    => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_utc'      => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Admin trigger sync.
		register_rest_route(
			self::REST_NAMESPACE,
			'/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'trigger_sync' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Admin capability verification callback.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission(): bool|WP_Error {
		if ( ! Capabilities::can_manage() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage calendar synchronization.', 'wpcalibrate-calendar-sync' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Retrieve events for calendar display.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_events( WP_REST_Request $request ): WP_REST_Response {
		$calendar_ids_raw = (string) $request->get_param( 'calendar_ids' );
		$cal_ids          = array();
		if ( ! empty( $calendar_ids_raw ) ) {
			$cal_ids = array_map( 'trim', explode( ',', $calendar_ids_raw ) );
		}

		$start_utc = (string) $request->get_param( 'start_utc' );
		$end_utc   = (string) $request->get_param( 'end_utc' );

		$args = array(
			'status'    => 'confirmed',
			'order_by'  => 'start_utc',
			'order'     => 'ASC',
			'limit'     => 250,
		);

		if ( ! empty( $cal_ids ) ) {
			$args['calendar_ids'] = $cal_ids;
		}
		if ( ! empty( $start_utc ) ) {
			$args['start_utc'] = $start_utc;
		}
		if ( ! empty( $end_utc ) ) {
			$args['end_utc'] = $end_utc;
		}

		$rows = $this->repository->get_events( $args );
		$events = array();

		foreach ( $rows as $row ) {
			$events[] = array(
				'id'          => (int) $row->id,
				'calendar_id' => $row->google_calendar_id,
				'title'       => esc_html( $row->title ),
				'description' => wp_kses_post( (string) $row->description ),
				'location'    => esc_html( (string) $row->location ),
				'start_utc'   => $row->start_utc,
				'end_utc'     => $row->end_utc,
				'all_day'     => (bool) $row->all_day,
				'timezone'    => $row->event_timezone,
				'html_link'   => esc_url( (string) $row->html_link ),
			);
		}

		return new WP_REST_Response( array( 'events' => $events ), 200 );
	}

	/**
	 * Trigger manual synchronization via REST.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function trigger_sync( WP_REST_Request $request ): WP_REST_Response {
		$calendar_id = (string) $request->get_param( 'calendar_id' );
		$full_resync = (bool) $request->get_param( 'full_resync' );

		if ( ! empty( $calendar_id ) ) {
			$result = $this->sync_service->sync_calendar( $calendar_id, $full_resync );
			return new WP_REST_Response( $result, 200 );
		}

		$results = $this->sync_service->sync_all( $full_resync );
		return new WP_REST_Response( array( 'results' => $results ), 200 );
	}
}
