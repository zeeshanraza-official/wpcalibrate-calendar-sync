<?php
/**
 * Plugin Name:       WPCalibrate Calendar Sync
 * Plugin URI:        https://wpcalibrate.com
 * Description:       Synchronize Google Calendar events with WordPress via secure OAuth 2.0. Supports two-way sync, Gutenberg blocks, shortcodes, and push notifications.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Tested up to:      7.1.3
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-calendar-sync
 * Domain Path:       /languages
 * Update URI:        https://github.com/zeeshanraza-official/wpcalibrate-calendar-sync/
 *
 * @package           WPCalibrate\CalendarSync
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Minimum environment requirements constants.
define( 'WPCS_VERSION', '1.0.0' );
define( 'WPCS_MIN_PHP_VERSION', '8.2.0' );
define( 'WPCS_MIN_WP_VERSION', '6.0.0' );
define( 'WPCS_FILE', __FILE__ );
define( 'WPCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCS_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader.
if ( file_exists( WPCS_PATH . 'vendor/autoload.php' ) ) {
	require_once WPCS_PATH . 'vendor/autoload.php';
}

/**
 * Check environmental requirements before booting.
 *
 * @return bool
 */
function wpcs_check_requirements(): bool {
	global $wp_version;

	if ( version_compare( PHP_VERSION, WPCS_MIN_PHP_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						esc_html__( 'WPCalibrate Calendar Sync requires PHP version %1$s or higher. Your server is running PHP %2$s.', 'wpcalibrate-calendar-sync' ),
						WPCS_MIN_PHP_VERSION,
						PHP_VERSION
					)
				);
			}
		);
		return false;
	}

	if ( version_compare( $wp_version, WPCS_MIN_WP_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				global $wp_version;
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: 1: required WordPress version, 2: current WordPress version */
						esc_html__( 'WPCalibrate Calendar Sync requires WordPress version %1$s or higher. Your site is running WordPress %2$s.', 'wpcalibrate-calendar-sync' ),
						WPCS_MIN_WP_VERSION,
						$wp_version
					)
				);
			}
		);
		return false;
	}

	return true;
}

// Register activation and deactivation hooks.
register_activation_hook(
	__FILE__,
	static function () {
		if ( wpcs_check_requirements() && class_exists( 'WPCalibrate\CalendarSync\Activation\Activator' ) ) {
			\WPCalibrate\CalendarSync\Activation\Activator::activate();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		if ( class_exists( 'WPCalibrate\CalendarSync\Activation\Deactivator' ) ) {
			\WPCalibrate\CalendarSync\Activation\Deactivator::deactivate();
		}
	}
);

/**
 * Bootstrap plugin.
 *
 * @return void
 */
function wpcs_init_plugin(): void {
	if ( ! wpcs_check_requirements() ) {
		return;
	}

	if ( class_exists( 'WPCalibrate\CalendarSync\Plugin' ) ) {
		\WPCalibrate\CalendarSync\Plugin::get_instance()->boot();
	}
}
add_action( 'plugins_loaded', 'wpcs_init_plugin' );
