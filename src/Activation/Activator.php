<?php
/**
 * Plugin Activation Handler.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Activation;

use WPCalibrate\CalendarSync\Database\MigrationManager;
use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Sync\RecoveryScheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 */
class Activator {

	/**
	 * Minimum PHP version.
	 */
	public const MIN_PHP_VERSION = '8.2.0';

	/**
	 * Minimum WordPress version.
	 */
	public const MIN_WP_VERSION = '6.0.0';

	/**
	 * Execute activation routines.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// 1. Verify environment prerequisites.
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			deactivate_plugins( plugin_basename( dirname( __DIR__, 2 ) . '/wpcalibrate-calendar-sync.php' ) );
			wp_die(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					esc_html__( 'WPCalibrate Calendar Sync requires PHP version %1$s or higher. Your server is running PHP %2$s.', 'wpcalibrate-calendar-sync' ),
					self::MIN_PHP_VERSION,
					PHP_VERSION
				),
				esc_html__( 'Plugin Activation Error', 'wpcalibrate-calendar-sync' ),
				array( 'back_link' => true )
			);
		}

		global $wp_version;
		if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			deactivate_plugins( plugin_basename( dirname( __DIR__, 2 ) . '/wpcalibrate-calendar-sync.php' ) );
			wp_die(
				sprintf(
					/* translators: 1: required WP version, 2: current WP version */
					esc_html__( 'WPCalibrate Calendar Sync requires WordPress version %1$s or higher. Your site is running WordPress %2$s.', 'wpcalibrate-calendar-sync' ),
					self::MIN_WP_VERSION,
					$wp_version
				),
				esc_html__( 'Plugin Activation Error', 'wpcalibrate-calendar-sync' ),
				array( 'back_link' => true )
			);
		}

		// 2. Run database migrations idempotently.
		$logger = new Logger();
		$migration_manager = new MigrationManager( $logger );
		$migration_manager->run_migrations();

		// 3. Register default non-secret settings.
		if ( false === get_option( 'wpcs_recovery_interval' ) ) {
			add_option( 'wpcs_recovery_interval', RecoveryScheduler::DEFAULT_INTERVAL, '', false );
		}

		if ( false === get_option( 'wpcs_display_settings' ) ) {
			add_option(
				'wpcs_display_settings',
				array(
					'default_view'     => 'agenda',
					'limit'            => 20,
					'show_description' => true,
					'show_location'    => true,
				),
				'',
				false
			);
		}

		// 4. Schedule periodic WP-Cron recovery event.
		if ( ! wp_next_scheduled( RecoveryScheduler::HOOK_NAME ) ) {
			wp_schedule_event( time() + 60, 'wpcs_interval', RecoveryScheduler::HOOK_NAME );
		}

		$logger->info( 'activation', 'WPCalibrate Calendar Sync activated successfully.' );
	}
}
