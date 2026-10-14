<?php
/**
 * Database Migration and Upgrade Manager.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Database;

use WPCalibrate\CalendarSync\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MigrationManager
 */
class MigrationManager {

	/**
	 * Logger instance.
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger|null $logger Optional logger.
	 */
	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Run required database migrations.
	 *
	 * @return bool True if migration was successful or already up to date.
	 */
	public function run_migrations(): bool {
		$installed_version = (string) get_option( Schema::VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, Schema::VERSION, '>=' ) && $this->table_exists() ) {
			return true;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql = Schema::get_events_table_sql();

		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
		}

		if ( ! $this->table_exists() ) {
			if ( $this->logger ) {
				$this->logger->error( 'migration', 'Failed to create or verify events table schema.' );
			}
			return false;
		}

		update_option( Schema::VERSION_OPTION, Schema::VERSION, false );

		if ( $this->logger ) {
			$this->logger->info(
				'migration',
				sprintf( 'Database schema migrated successfully to version %s.', Schema::VERSION )
			);
		}

		return true;
	}

	/**
	 * Verify if the events table exists.
	 *
	 * @return bool
	 */
	public function table_exists(): bool {
		global $wpdb;
		$table_name = Schema::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);
		return $found === $table_name;
	}

	/**
	 * Drop plugin tables (used during uninstall when data removal is requested).
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		global $wpdb;
		$table_name = Schema::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		delete_option( Schema::VERSION_OPTION );
	}
}
