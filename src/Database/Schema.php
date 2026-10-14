<?php
/**
 * Database Table Schema Definitions.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema
 */
class Schema {

	/**
	 * Table name suffix.
	 */
	public const TABLE_SUFFIX = 'wpcs_events';

	/**
	 * Schema version constant.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Option name for tracking schema version.
	 */
	public const VERSION_OPTION = 'wpcs_schema_version';

	/**
	 * Get the full qualified table name for events.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Get the SQL DDL statement for dbDelta().
	 *
	 * Note: dbDelta requires two spaces after PRIMARY KEY, specific index formatting,
	 * and lowercase field types.
	 *
	 * @return string
	 */
	public static function get_events_table_sql(): string {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table_name} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  google_event_id varchar(255) NOT NULL,
  google_calendar_id varchar(255) NOT NULL,
  google_recurring_event_id varchar(255) DEFAULT NULL,
  original_start_utc datetime DEFAULT NULL,
  etag varchar(255) DEFAULT NULL,
  html_link text DEFAULT NULL,
  event_status varchar(50) NOT NULL DEFAULT 'confirmed',
  title text NOT NULL,
  description longtext DEFAULT NULL,
  location text DEFAULT NULL,
  start_utc datetime NOT NULL,
  end_utc datetime NOT NULL,
  all_day tinyint(1) NOT NULL DEFAULT 0,
  event_timezone varchar(100) NOT NULL DEFAULT 'UTC',
  visibility varchar(50) NOT NULL DEFAULT 'default',
  transparency varchar(50) NOT NULL DEFAULT 'opaque',
  google_updated_at datetime DEFAULT NULL,
  local_updated_at datetime NOT NULL,
  sync_status varchar(50) NOT NULL DEFAULT 'synced',
  local_dirty tinyint(1) NOT NULL DEFAULT 0,
  checksum varchar(64) NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uk_cal_event (google_calendar_id(150), google_event_id(150)),
  KEY idx_google_cal (google_calendar_id(150)),
  KEY idx_google_event (google_event_id(150)),
  KEY idx_dates (start_utc, end_utc),
  KEY idx_status (event_status),
  KEY idx_sync_status (sync_status),
  KEY idx_local_dirty (local_dirty),
  KEY idx_recurring_id (google_recurring_event_id(150))
) {$charset_collate};";
	}
}
