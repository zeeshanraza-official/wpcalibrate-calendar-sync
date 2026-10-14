<?php
/**
 * Demo Data Manager for WPCalibrate Calendar Sync.
 *
 * Provides simulated Google OAuth 2.0 connection, demo calendars, and realistic sample events
 * for frictionless evaluation and testing of admin management, blocks, and shortcodes.
 *
 * @package WPCalibrate\CalendarSync
 */

declare( strict_types=1 );

namespace WPCalibrate\CalendarSync\Support;

use WPCalibrate\CalendarSync\Auth\OAuthController;
use WPCalibrate\CalendarSync\Auth\SecretStorage;
use WPCalibrate\CalendarSync\Database\Schema;
use WPCalibrate\CalendarSync\Events\EventRepository;
use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Sync\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DemoDataManager
 */
class DemoDataManager {

	/**
	 * Demo Client ID.
	 */
	public const DEMO_CLIENT_ID = 'demo-wpcs-7849201.apps.googleusercontent.com';

	/**
	 * Demo user email.
	 */
	public const DEMO_USER_EMAIL = 'demo.tester@wpcalibrate.com';

	/**
	 * Secret storage instance.
	 */
	private SecretStorage $secret_storage;

	/**
	 * Event repository.
	 */
	private EventRepository $repository;

	/**
	 * Sync service.
	 */
	private SyncService $sync_service;

	/**
	 * Logger service.
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param SecretStorage   $secret_storage Secret storage service.
	 * @param EventRepository $repository     Event repository service.
	 * @param SyncService     $sync_service   Sync service.
	 * @param Logger|null     $logger         Logger service.
	 */
	public function __construct(
		SecretStorage $secret_storage,
		EventRepository $repository,
		SyncService $sync_service,
		?Logger $logger = null
	) {
		$this->secret_storage = $secret_storage;
		$this->repository     = $repository;
		$this->sync_service   = $sync_service;
		$this->logger         = $logger;
	}

	/**
	 * Check if demo mode is currently active.
	 *
	 * @return bool
	 */
	public function is_demo_active(): bool {
		$connection = get_option( OAuthController::OPTION_CONNECTION, array() );
		return is_array( $connection ) && ! empty( $connection['is_demo'] );
	}

	/**
	 * Get current count of demo events and calendars.
	 *
	 * @return array{events_count: int, calendars_count: int}
	 */
	public function get_demo_counts(): array {
		global $wpdb;
		$table = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$events_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE google_calendar_id LIKE 'demo-%' OR google_event_id LIKE 'demo_%'"
		);

		$calendars = $this->sync_service->get_configured_calendars();
		$cal_count = 0;
		foreach ( $calendars as $cid => $cfg ) {
			if ( ! empty( $cfg['is_demo'] ) || str_starts_with( (string) $cid, 'demo-' ) ) {
				$cal_count++;
			}
		}

		return array(
			'events_count'    => $events_count,
			'calendars_count' => $cal_count,
		);
	}

	/**
	 * Install demo Google OAuth 2.0 connection, calendars, and sample events.
	 *
	 * @return array{success: bool, message: string, events_count: int, calendars_count: int}
	 */
	public function install_demo_data(): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success'         => false,
				'message'         => __( 'Insufficient permissions.', 'wpcalibrate-calendar-sync' ),
				'events_count'    => 0,
				'calendars_count' => 0,
			);
		}

		// 1. Establish simulated Google OAuth 2.0 connection in SecretStorage and wp_options.
		if ( $this->secret_storage->is_encryption_available() ) {
			$this->secret_storage->store_secret( 'client_secret', 'demo-secret-mock-wpcs-2026' );
			$this->secret_storage->store_secret( 'refresh_token', 'demo-refresh-token-mock-wpcs-2026' );
			$this->secret_storage->store_secret( 'access_token', 'demo-access-token-mock-wpcs-2026' );
			$this->secret_storage->store_secret( 'access_token_expires_at', (string) ( time() + ( 365 * 86400 ) ) );
		}

		$now_utc = gmdate( 'Y-m-d H:i:s' );

		$connection_data = array(
			'status'         => 'connected',
			'client_id'      => self::DEMO_CLIENT_ID,
			'user_email'     => self::DEMO_USER_EMAIL,
			'connected_at'   => $now_utc,
			'scopes_granted' => array(
				OAuthController::SCOPE_FULL,
				OAuthController::SCOPE_READONLY,
			),
			'is_demo'        => true,
		);
		update_option( OAuthController::OPTION_CONNECTION, $connection_data, false );

		// 2. Configure 3 demo Google Calendars with diverse colors and sync directions.
		$tz = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
		$demo_calendars = array(
			'demo-cal-company' => array(
				'id'               => 'demo-cal-company',
				'name'             => __( 'Company Events & All-Hands', 'wpcalibrate-calendar-sync' ),
				'timezone'         => $tz,
				'enabled'          => true,
				'direction'        => 'two_way',
				'color'            => '#0284c7',
				'is_demo'          => true,
				'last_sync_utc'    => $now_utc,
				'last_attempt_utc' => $now_utc,
			),
			'demo-cal-team' => array(
				'id'               => 'demo-cal-team',
				'name'             => __( 'Product & Engineering Schedule', 'wpcalibrate-calendar-sync' ),
				'timezone'         => $tz,
				'enabled'          => true,
				'direction'        => 'two_way',
				'color'            => '#059669',
				'is_demo'          => true,
				'last_sync_utc'    => $now_utc,
				'last_attempt_utc' => $now_utc,
			),
			'demo-cal-workshops' => array(
				'id'               => 'demo-cal-workshops',
				'name'             => __( 'Client Consultations & Webinars', 'wpcalibrate-calendar-sync' ),
				'timezone'         => $tz,
				'enabled'          => true,
				'direction'        => 'google_to_wp',
				'color'            => '#7c3aed',
				'is_demo'          => true,
				'last_sync_utc'    => $now_utc,
				'last_attempt_utc' => $now_utc,
			),
		);

		// Merge with existing calendars if any, preserving user non-demo calendars.
		$existing_calendars = $this->sync_service->get_configured_calendars();
		$merged_calendars   = array_merge( $existing_calendars, $demo_calendars );
		$this->sync_service->save_configured_calendars( $merged_calendars );

		// 3. Purge any previous demo events to ensure fresh, clean sample dataset.
		global $wpdb;
		$table = Schema::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$table} WHERE google_calendar_id LIKE 'demo-%' OR google_event_id LIKE 'demo_%'" );

		// 4. Generate 12 rich sample events with dynamic dates relative to today.
		$events = $this->get_sample_events_dataset( $tz );
		$inserted_count = 0;

		foreach ( $events as $event ) {
			$res = $this->repository->insert( $event );
			if ( false !== $res ) {
				$inserted_count++;
			}
		}

		if ( $this->logger ) {
			$this->logger->info(
				'demo_data_installed',
				sprintf( 'Loaded demo connection and %d sample events across 3 demo calendars.', $inserted_count )
			);
		}

		return array(
			'success'         => true,
			'message'         => sprintf(
				/* translators: 1: event count, 2: calendar count */
				__( 'Demo mode activated! Loaded Google OAuth connection, %1$d sample calendars, and %2$d upcoming/recent events.', 'wpcalibrate-calendar-sync' ),
				count( $demo_calendars ),
				$inserted_count
			),
			'events_count'    => $inserted_count,
			'calendars_count' => count( $demo_calendars ),
		);
	}

	/**
	 * Remove demo connection, calendars, and sample events.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function remove_demo_data(): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'wpcalibrate-calendar-sync' ),
			);
		}

		global $wpdb;
		$table = Schema::get_table_name();

		// 1. Delete all demo events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$table} WHERE google_calendar_id LIKE 'demo-%' OR google_event_id LIKE 'demo_%'" );

		// 2. Remove demo calendars.
		$calendars = $this->sync_service->get_configured_calendars();
		$cleaned   = array();
		foreach ( $calendars as $cid => $cfg ) {
			if ( empty( $cfg['is_demo'] ) && ! str_starts_with( (string) $cid, 'demo-' ) ) {
				$cleaned[ $cid ] = $cfg;
			}
		}
		$this->sync_service->save_configured_calendars( $cleaned );

		// 3. If connection was in demo mode, reset to disconnected clean state.
		if ( $this->is_demo_active() ) {
			$this->secret_storage->delete_secret( 'client_secret' );
			$this->secret_storage->delete_secret( 'refresh_token' );
			$this->secret_storage->delete_secret( 'access_token' );
			$this->secret_storage->delete_secret( 'access_token_expires_at' );

			$connection = array(
				'status'       => 'disconnected',
				'client_id'    => '',
				'user_email'   => '',
				'connected_at' => '',
				'is_demo'      => false,
			);
			update_option( OAuthController::OPTION_CONNECTION, $connection, false );
		}

		if ( $this->logger ) {
			$this->logger->info( 'demo_data_removed', 'Removed demo connection, calendars, and sample events.' );
		}

		return array(
			'success' => true,
			'message' => __( 'Demo connection and sample events removed successfully.', 'wpcalibrate-calendar-sync' ),
		);
	}

	/**
	 * Build array of 12 realistic sample events with dynamic dates.
	 *
	 * @param string $timezone Site timezone.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_sample_events_dataset( string $timezone ): array {
		$today = gmdate( 'Y-m-d' );

		return array(
			array(
				'google_event_id'    => 'demo_evt_allhands_today',
				'google_calendar_id' => 'demo-cal-company',
				'title'              => __( 'Q4 Strategic All-Hands & Product Roadmap', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Company-wide quarterly meeting reviewing annual goals, customer success milestones, and upcoming product launches.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Main Auditorium & Zoom Live', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 00:00:00', strtotime( $today ) ),
				'end_utc'            => gmdate( 'Y-m-d 23:59:59', strtotime( $today ) ),
				'all_day'            => 1,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_consult_tomorrow',
				'google_calendar_id' => 'demo-cal-workshops',
				'title'              => __( 'Client Onboarding & Solutions Architecture', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Technical deep-dive with enterprise client reviewing API integration, synchronization schedules, and security requirements.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Google Meet (https://meet.google.com/demo-wpcs)', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 10:00:00', strtotime( $today . ' +1 day' ) ),
				'end_utc'            => gmdate( 'Y-m-d 11:30:00', strtotime( $today . ' +1 day' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_sprint_2d',
				'google_calendar_id' => 'demo-cal-team',
				'title'              => __( 'WPCalibrate Core Sprint Planning', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Bi-weekly backlog grooming, task estimation, and priority allocation for the engineering team.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Innovation Lab Suite A', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 14:00:00', strtotime( $today . ' +2 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 15:30:00', strtotime( $today . ' +2 days' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_design_4d',
				'google_calendar_id' => 'demo-cal-team',
				'title'              => __( 'UI/UX Design Review & Prototype Walkthrough', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Reviewing new Gutenberg calendar block inspector controls, mobile Agenda views, and accessibility improvements.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Design Studio Room B', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 13:00:00', strtotime( $today . ' +4 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 14:30:00', strtotime( $today . ' +4 days' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_summit_7d',
				'google_calendar_id' => 'demo-cal-company',
				'title'              => __( 'Annual WordPress Technology Summit 2026', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Keynote presentations, breakout technical sessions, and community roundtables on modern WordPress architecture.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Metropolitan Convention Center', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 00:00:00', strtotime( $today . ' +7 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 23:59:59', strtotime( $today . ' +7 days' ) ),
				'all_day'            => 1,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_workshop_9d',
				'google_calendar_id' => 'demo-cal-workshops',
				'title'              => __( 'Enterprise API Integration Masterclass', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Hands-on workshop teaching developers how to utilize push webhooks, timing-safe authorization, and token refreshing.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Virtual Training Hall 1', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 15:00:00', strtotime( $today . ' +9 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 17:00:00', strtotime( $today . ' +9 days' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_marketing_12d',
				'google_calendar_id' => 'demo-cal-company',
				'title'              => __( 'Marketing & Growth Strategy Sync', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Discussion of Q4 promotional campaigns, SEO content calendar, and customer case studies.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Executive Boardroom', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 11:00:00', strtotime( $today . ' +12 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 12:00:00', strtotime( $today . ' +12 days' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'tentative',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_security_15d',
				'google_calendar_id' => 'demo-cal-team',
				'title'              => __( 'Quarterly Security & Penetration Audit', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Comprehensive external and internal audit of encrypted secret storage, webhook header validation, and database queries.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Security Operations Center', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 00:00:00', strtotime( $today . ' +15 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 23:59:59', strtotime( $today . ' +15 days' ) ),
				'all_day'            => 1,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_advisory_18d',
				'google_calendar_id' => 'demo-cal-workshops',
				'title'              => __( 'Customer Advisory Board Roundtable', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Quarterly feedback session with agency partners and high-volume calendar administrators.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Global Broadcast Studio', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 16:00:00', strtotime( $today . ' +18 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 17:30:00', strtotime( $today . ' +18 days' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_devops_21d',
				'google_calendar_id' => 'demo-cal-team',
				'title'              => __( 'DevOps & Infrastructure Resilience Drill', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Simulating network partition, Google 410 sync token expiration, and database migration rollbacks.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'War Room 4 & Slack #ops', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 09:00:00', strtotime( $today . ' +21 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 11:00:00', strtotime( $today . ' +21 days' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_partner_25d',
				'google_calendar_id' => 'demo-cal-company',
				'title'              => __( 'Global Developer & Partner Keynote', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Live streaming keynote showcasing upcoming features, headless integrations, and WooCommerce sync roadmap.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Live Stream Hub', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 17:00:00', strtotime( $today . ' +25 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 19:00:00', strtotime( $today . ' +25 days' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
			array(
				'google_event_id'    => 'demo_evt_past_retro_2d',
				'google_calendar_id' => 'demo-cal-team',
				'title'              => __( 'Sprint Retrospective & Knowledge Share', 'wpcalibrate-calendar-sync' ),
				'description'        => __( 'Past sprint wrap-up reviewing achievements and deployment performance.', 'wpcalibrate-calendar-sync' ),
				'location'           => __( 'Main Conference Room', 'wpcalibrate-calendar-sync' ),
				'start_utc'          => gmdate( 'Y-m-d 14:00:00', strtotime( $today . ' -2 days' ) ),
				'end_utc'            => gmdate( 'Y-m-d 15:00:00', strtotime( $today . ' -2 days' ) ),
				'all_day'            => 0,
				'event_timezone'     => $timezone,
				'event_status'       => 'confirmed',
				'sync_status'        => 'synced',
			),
		);
	}
}
