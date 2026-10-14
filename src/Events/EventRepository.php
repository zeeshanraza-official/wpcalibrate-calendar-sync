<?php
/**
 * Event Database Repository.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Events;

use WPCalibrate\CalendarSync\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EventRepository
 */
class EventRepository {

	/**
	 * Compute change detection checksum for an event's normalized payload.
	 *
	 * @param array<string, mixed> $data Event fields.
	 * @return string SHA-256 hash.
	 */
	public static function compute_checksum( array $data ): string {
		$normalized = array(
			'title'       => trim( (string) ( $data['title'] ?? '' ) ),
			'description' => trim( (string) ( $data['description'] ?? '' ) ),
			'location'    => trim( (string) ( $data['location'] ?? '' ) ),
			'start_utc'   => trim( (string) ( $data['start_utc'] ?? '' ) ),
			'end_utc'     => trim( (string) ( $data['end_utc'] ?? '' ) ),
			'all_day'     => ! empty( $data['all_day'] ) ? 1 : 0,
			'timezone'    => trim( (string) ( $data['event_timezone'] ?? 'UTC' ) ),
			'status'      => trim( (string) ( $data['event_status'] ?? 'confirmed' ) ),
		);
		ksort( $normalized );
		return hash( 'sha256', (string) wp_json_encode( $normalized ) );
	}

	/**
	 * Insert a new local event record.
	 *
	 * @param array<string, mixed> $data Event data.
	 * @return int|false ID of inserted row or false on failure.
	 */
	public function insert( array $data ): int|false {
		global $wpdb;
		$table = Schema::get_table_name();

		$now = gmdate( 'Y-m-d H:i:s' );
		$data['created_at']       = $now;
		$data['updated_at']       = $now;
		$data['local_updated_at'] = $now;

		if ( empty( $data['checksum'] ) ) {
			$data['checksum'] = self::compute_checksum( $data );
		}

		$format = array(
			'%s', // google_event_id
			'%s', // google_calendar_id
			'%s', // google_recurring_event_id
			'%s', // original_start_utc
			'%s', // etag
			'%s', // html_link
			'%s', // event_status
			'%s', // title
			'%s', // description
			'%s', // location
			'%s', // start_utc
			'%s', // end_utc
			'%d', // all_day
			'%s', // event_timezone
			'%s', // visibility
			'%s', // transparency
			'%s', // google_updated_at
			'%s', // local_updated_at
			'%s', // sync_status
			'%d', // local_dirty
			'%s', // checksum
			'%s', // created_at
			'%s', // updated_at
		);

		$fields = array(
			'google_event_id'           => (string) ( $data['google_event_id'] ?? '' ),
			'google_calendar_id'        => (string) ( $data['google_calendar_id'] ?? '' ),
			'google_recurring_event_id' => ! empty( $data['google_recurring_event_id'] ) ? (string) $data['google_recurring_event_id'] : null,
			'original_start_utc'        => ! empty( $data['original_start_utc'] ) ? (string) $data['original_start_utc'] : null,
			'etag'                      => ! empty( $data['etag'] ) ? (string) $data['etag'] : null,
			'html_link'                 => ! empty( $data['html_link'] ) ? (string) $data['html_link'] : null,
			'event_status'              => (string) ( $data['event_status'] ?? 'confirmed' ),
			'title'                     => (string) ( $data['title'] ?? '' ),
			'description'               => ! empty( $data['description'] ) ? (string) $data['description'] : null,
			'location'                  => ! empty( $data['location'] ) ? (string) $data['location'] : null,
			'start_utc'                 => (string) ( $data['start_utc'] ?? $now ),
			'end_utc'                   => (string) ( $data['end_utc'] ?? $now ),
			'all_day'                   => ! empty( $data['all_day'] ) ? 1 : 0,
			'event_timezone'            => (string) ( $data['event_timezone'] ?? 'UTC' ),
			'visibility'                => (string) ( $data['visibility'] ?? 'default' ),
			'transparency'              => (string) ( $data['transparency'] ?? 'opaque' ),
			'google_updated_at'         => ! empty( $data['google_updated_at'] ) ? (string) $data['google_updated_at'] : null,
			'local_updated_at'          => $data['local_updated_at'],
			'sync_status'               => (string) ( $data['sync_status'] ?? 'synced' ),
			'local_dirty'               => ! empty( $data['local_dirty'] ) ? 1 : 0,
			'checksum'                  => $data['checksum'],
			'created_at'                => $data['created_at'],
			'updated_at'                => $data['updated_at'],
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $fields, $format );
		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing event by local ID.
	 *
	 * @param int                  $id   Local ID.
	 * @param array<string, mixed> $data Updated fields.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$table = Schema::get_table_name();

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		if ( ! isset( $data['checksum'] ) && ( isset( $data['title'] ) || isset( $data['start_utc'] ) ) ) {
			$existing = $this->get_by_id( $id );
			if ( $existing ) {
				$merged = array_merge( (array) $existing, $data );
				$data['checksum'] = self::compute_checksum( $merged );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Upsert an event synchronized from Google.
	 *
	 * @param array<string, mixed> $data Event data.
	 * @return int Local event record ID.
	 */
	public function upsert_from_google( array $data ): int {
		$cal_id   = (string) ( $data['google_calendar_id'] ?? '' );
		$event_id = (string) ( $data['google_event_id'] ?? '' );

		$existing = $this->get_by_google_id( $cal_id, $event_id );
		$new_checksum = self::compute_checksum( $data );
		$data['checksum']    = $new_checksum;
		$data['sync_status'] = 'synced';
		$data['local_dirty'] = 0;

		if ( $existing ) {
			$id = (int) $existing->id;
			// If already identical checksum and not dirty, skip DB update.
			if ( $existing->checksum === $new_checksum && (int) $existing->local_dirty === 0 && $existing->event_status === ( $data['event_status'] ?? 'confirmed' ) ) {
				return $id;
			}
			$this->update( $id, $data );
			return $id;
		}

		$new_id = $this->insert( $data );
		return false !== $new_id ? $new_id : 0;
	}

	/**
	 * Retrieve a single event by primary ID.
	 *
	 * @param int $id Local event ID.
	 * @return object|null
	 */
	public function get_by_id( int $id ): ?object {
		global $wpdb;
		$table = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * Retrieve a single event by Google calendar ID and event ID.
	 *
	 * @param string $calendar_id Google calendar ID.
	 * @param string $event_id    Google event ID.
	 * @return object|null
	 */
	public function get_by_google_id( string $calendar_id, string $event_id ): ?object {
		global $wpdb;
		$table = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE google_calendar_id = %s AND google_event_id = %s LIMIT 1",
				$calendar_id,
				$event_id
			)
		);

		return $row ?: null;
	}

	/**
	 * Query events with filters, pagination, and sorting.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, object>
	 */
	public function get_events( array $args = array() ): array {
		global $wpdb;
		$table = Schema::get_table_name();

		$where = array( '1=1' );
		$params = array();

		// Calendar filter.
		if ( ! empty( $args['calendar_ids'] ) ) {
			$cal_ids = (array) $args['calendar_ids'];
			$placeholders = implode( ',', array_fill( 0, count( $cal_ids ), '%s' ) );
			$where[] = "google_calendar_id IN ({$placeholders})";
			foreach ( $cal_ids as $cid ) {
				$params[] = (string) $cid;
			}
		} elseif ( ! empty( $args['calendar_id'] ) ) {
			$where[] = 'google_calendar_id = %s';
			$params[] = (string) $args['calendar_id'];
		}

		// Status filter.
		$status = $args['status'] ?? 'confirmed';
		if ( 'all' !== $status ) {
			$where[] = 'event_status = %s';
			$params[] = (string) $status;
		}

		// Sync status filter.
		if ( ! empty( $args['sync_status'] ) && 'all' !== $args['sync_status'] ) {
			$where[] = 'sync_status = %s';
			$params[] = (string) $args['sync_status'];
		}

		// Date range filters.
		if ( ! empty( $args['start_utc'] ) ) {
			$where[] = 'end_utc >= %s';
			$params[] = (string) $args['start_utc'];
		}
		if ( ! empty( $args['end_utc'] ) ) {
			$where[] = 'start_utc <= %s';
			$params[] = (string) $args['end_utc'];
		}

		// Search filter.
		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[] = '(title LIKE %s OR description LIKE %s OR location LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		// Ordering allowlist.
		$allowed_order_by = array(
			'start_utc'        => 'start_utc',
			'end_utc'          => 'end_utc',
			'title'            => 'title',
			'id'               => 'id',
			'local_updated_at' => 'local_updated_at',
		);
		$order_by_key = (string) ( $args['order_by'] ?? 'start_utc' );
		$order_by = $allowed_order_by[ $order_by_key ] ?? 'start_utc';

		$order = strtoupper( (string) ( $args['order'] ?? 'ASC' ) );
		if ( 'DESC' !== $order ) {
			$order = 'ASC';
		}

		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 100;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count total events matching given query arguments.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return int Total matching records.
	 */
	public function count_events( array $args = array() ): int {
		global $wpdb;
		$table = Schema::get_table_name();

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['calendar_ids'] ) ) {
			$cal_ids = (array) $args['calendar_ids'];
			$placeholders = implode( ',', array_fill( 0, count( $cal_ids ), '%s' ) );
			$where[] = "google_calendar_id IN ({$placeholders})";
			foreach ( $cal_ids as $cid ) {
				$params[] = (string) $cid;
			}
		} elseif ( ! empty( $args['calendar_id'] ) ) {
			$where[] = 'google_calendar_id = %s';
			$params[] = (string) $args['calendar_id'];
		}

		$status = $args['status'] ?? 'confirmed';
		if ( 'all' !== $status ) {
			$where[] = 'event_status = %s';
			$params[] = (string) $status;
		}

		if ( ! empty( $args['sync_status'] ) && 'all' !== $args['sync_status'] ) {
			$where[] = 'sync_status = %s';
			$params[] = (string) $args['sync_status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[] = '(title LIKE %s OR description LIKE %s OR location LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $params ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Mark an event as cancelled (soft delete matching Google Calendar behavior).
	 *
	 * @param string $calendar_id Google Calendar ID.
	 * @param string $event_id    Google Event ID.
	 * @return bool
	 */
	public function mark_as_cancelled( string $calendar_id, string $event_id ): bool {
		global $wpdb;
		$table = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'event_status'     => 'cancelled',
				'sync_status'      => 'synced',
				'local_dirty'      => 0,
				'updated_at'       => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'google_calendar_id' => $calendar_id,
				'google_event_id'    => $event_id,
			)
		);

		return false !== $result;
	}

	/**
	 * Permanently delete an event by primary ID.
	 *
	 * @param int $id Local event ID.
	 * @return bool
	 */
	public function delete_by_id( int $id ): bool {
		global $wpdb;
		$table = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete all events belonging to a specific calendar ID.
	 *
	 * @param string $calendar_id Calendar ID.
	 * @return int Deleted row count.
	 */
	public function delete_by_calendar( string $calendar_id ): int {
		global $wpdb;
		$table = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			$table,
			array( 'google_calendar_id' => $calendar_id ),
			array( '%s' )
		);

		return false !== $deleted ? $deleted : 0;
	}

	/**
	 * Fetch events marked as dirty or pending outbound sync.
	 *
	 * @param int $limit Maximum number of records to return.
	 * @return array<int, object>
	 */
	public function get_dirty_events( int $limit = 50 ): array {
		global $wpdb;
		$table = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE local_dirty = 1 OR sync_status = 'pending_push' ORDER BY updated_at ASC LIMIT %d",
				max( 1, $limit )
			)
		);

		return is_array( $rows ) ? $rows : array();
	}
}
