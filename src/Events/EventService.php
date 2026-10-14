<?php
/**
 * Event Business Logic Service.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Events;

use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Support\Capabilities;
use WPCalibrate\CalendarSync\Support\DateTime as WpDateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EventService
 */
class EventService {

	/**
	 * Repository.
	 */
	private EventRepository $repository;

	/**
	 * Logger.
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param EventRepository $repository Event repository.
	 * @param Logger|null     $logger     Optional logger.
	 */
	public function __construct( EventRepository $repository, ?Logger $logger = null ) {
		$this->repository = $repository;
		$this->logger     = $logger;
	}

	/**
	 * Get event repository.
	 *
	 * @return EventRepository
	 */
	public function get_repository(): EventRepository {
		return $this->repository;
	}

	/**
	 * Create a new event from WordPress admin.
	 *
	 * @param array<string, mixed> $input Form input data.
	 * @return array{success: bool, id?: int, message?: string}
	 */
	public function create_event( array $input ): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions to create events.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$title       = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		$calendar_id = sanitize_text_field( (string) ( $input['google_calendar_id'] ?? '' ) );

		if ( '' === $title || '' === $calendar_id ) {
			return array(
				'success' => false,
				'message' => __( 'Title and Calendar are required fields.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$all_day = ! empty( $input['all_day'] );
		$tz      = sanitize_text_field( (string) ( $input['event_timezone'] ?? 'UTC' ) );

		if ( $all_day ) {
			$start_utc = WpDateTime::all_day_to_utc_mysql( (string) ( $input['start_date'] ?? '' ) );
			$end_utc   = WpDateTime::all_day_to_utc_mysql( (string) ( $input['end_date'] ?? $input['start_date'] ?? '' ) );
		} else {
			$start_raw = trim( sprintf( '%s %s', (string) ( $input['start_date'] ?? '' ), (string) ( $input['start_time'] ?? '00:00:00' ) ) );
			$end_raw   = trim( sprintf( '%s %s', (string) ( $input['end_date'] ?? '' ), (string) ( $input['end_time'] ?? '01:00:00' ) ) );
			$start_utc = WpDateTime::to_utc_mysql( $start_raw, $tz );
			$end_utc   = WpDateTime::to_utc_mysql( $end_raw, $tz );
		}

		if ( empty( $start_utc ) || empty( $end_utc ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid event start or end date/time.', 'wpcalibrate-calendar-sync' ),
			);
		}

		// Ensure end is not before start.
		if ( $end_utc < $start_utc ) {
			$end_utc = $start_utc;
		}

		// Generate a temporary local Google Event ID for outbound tracking.
		$temp_event_id = 'wp_' . wp_generate_uuid4();

		$data = array(
			'google_event_id'    => $temp_event_id,
			'google_calendar_id' => $calendar_id,
			'title'              => $title,
			'description'        => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
			'location'           => sanitize_text_field( (string) ( $input['location'] ?? '' ) ),
			'start_utc'          => $start_utc,
			'end_utc'            => $end_utc,
			'all_day'            => $all_day ? 1 : 0,
			'event_timezone'     => $tz,
			'event_status'       => 'confirmed',
			'sync_status'        => 'pending_push',
			'local_dirty'        => 1,
		);

		$new_id = $this->repository->insert( $data );
		if ( false === $new_id ) {
			return array(
				'success' => false,
				'message' => __( 'Database insertion failed.', 'wpcalibrate-calendar-sync' ),
			);
		}

		if ( $this->logger ) {
			$this->logger->info(
				'create_event',
				sprintf( 'Created local event "%s" (ID: %d) pending outbound sync.', $title, $new_id ),
				array( 'calendar_id' => $calendar_id, 'local_id' => $new_id )
			);
		}

		return array(
			'success' => true,
			'id'      => $new_id,
			'message' => __( 'Event created locally.', 'wpcalibrate-calendar-sync' ),
		);
	}

	/**
	 * Update an existing local event.
	 *
	 * @param int                  $id    Local event ID.
	 * @param array<string, mixed> $input Form data.
	 * @return array{success: bool, message: string}
	 */
	public function update_event( int $id, array $input ): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$existing = $this->repository->get_by_id( $id );
		if ( ! $existing ) {
			return array(
				'success' => false,
				'message' => __( 'Event not found.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$all_day = ! empty( $input['all_day'] );
		$tz      = sanitize_text_field( (string) ( $input['event_timezone'] ?? $existing->event_timezone ) );

		if ( $all_day ) {
			$start_utc = WpDateTime::all_day_to_utc_mysql( (string) ( $input['start_date'] ?? '' ) );
			$end_utc   = WpDateTime::all_day_to_utc_mysql( (string) ( $input['end_date'] ?? $input['start_date'] ?? '' ) );
		} else {
			$start_raw = trim( sprintf( '%s %s', (string) ( $input['start_date'] ?? '' ), (string) ( $input['start_time'] ?? '00:00:00' ) ) );
			$end_raw   = trim( sprintf( '%s %s', (string) ( $input['end_date'] ?? '' ), (string) ( $input['end_time'] ?? '01:00:00' ) ) );
			$start_utc = WpDateTime::to_utc_mysql( $start_raw, $tz );
			$end_utc   = WpDateTime::to_utc_mysql( $end_raw, $tz );
		}

		if ( empty( $start_utc ) || empty( $end_utc ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid date/time.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$title = sanitize_text_field( (string) ( $input['title'] ?? $existing->title ) );

		$update_data = array(
			'title'            => $title,
			'description'      => sanitize_textarea_field( (string) ( $input['description'] ?? $existing->description ) ),
			'location'         => sanitize_text_field( (string) ( $input['location'] ?? $existing->location ) ),
			'start_utc'        => $start_utc,
			'end_utc'          => $end_utc,
			'all_day'          => $all_day ? 1 : 0,
			'event_timezone'   => $tz,
			'sync_status'      => 'pending_push',
			'local_dirty'      => 1,
			'local_updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		$updated = $this->repository->update( $id, $update_data );
		if ( ! $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update event in database.', 'wpcalibrate-calendar-sync' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Event updated locally.', 'wpcalibrate-calendar-sync' ),
		);
	}

	/**
	 * Delete or cancel a local event.
	 *
	 * @param int $id Local event ID.
	 * @return array{success: bool, message: string}
	 */
	public function delete_event( int $id ): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$existing = $this->repository->get_by_id( $id );
		if ( ! $existing ) {
			return array(
				'success' => false,
				'message' => __( 'Event not found.', 'wpcalibrate-calendar-sync' ),
			);
		}

		// Mark as pending deletion / cancelled so sync service can notify Google if needed.
		$updated = $this->repository->update(
			$id,
			array(
				'event_status'     => 'cancelled',
				'sync_status'      => 'pending_push',
				'local_dirty'      => 1,
				'local_updated_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		return array(
			'success' => $updated,
			'message' => $updated ? __( 'Event marked for cancellation.', 'wpcalibrate-calendar-sync' ) : __( 'Failed to cancel event.', 'wpcalibrate-calendar-sync' ),
		);
	}
}
