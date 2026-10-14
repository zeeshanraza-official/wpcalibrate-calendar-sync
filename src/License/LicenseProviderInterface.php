<?php
/**
 * License Provider Contract.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface LicenseProviderInterface
 *
 * Defines methods required for external licensing providers (e.g. EDD, WooCommerce, LemonSqueezy, or custom API).
 */
interface LicenseProviderInterface {

	/**
	 * Check if the licensing provider endpoint is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key Raw license key.
	 * @return array{success: bool, status: string, message: string, expires?: string, renewal_url?: string}
	 */
	public function activate( string $license_key ): array;

	/**
	 * Deactivate a license key.
	 *
	 * @param string $license_key Raw license key.
	 * @return array{success: bool, status: string, message: string}
	 */
	public function deactivate( string $license_key ): array;

	/**
	 * Check / validate current license status.
	 *
	 * @param string $license_key Raw license key.
	 * @return array{success: bool, status: string, message: string, expires?: string, renewal_url?: string}
	 */
	public function check( string $license_key ): array;
}
