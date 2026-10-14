<?php
/**
 * License Manager Abstraction.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\License;

use WPCalibrate\CalendarSync\Auth\SecretStorage;
use WPCalibrate\CalendarSync\Support\Capabilities;
use WPCalibrate\CalendarSync\Support\Sanitization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LicenseManager
 */
class LicenseManager {

	/**
	 * License details option.
	 */
	public const OPTION_NAME = 'wpcs_license';

	/**
	 * Secret key name for license key.
	 */
	public const SECRET_KEY = 'license_key';

	/**
	 * Status constants.
	 */
	public const STATUS_NOT_CONFIGURED = 'not_configured';
	public const STATUS_INACTIVE       = 'inactive';
	public const STATUS_ACTIVE         = 'active';
	public const STATUS_EXPIRED        = 'expired';
	public const STATUS_INVALID        = 'invalid';

	/**
	 * Optional provider implementation.
	 */
	private ?LicenseProviderInterface $provider;

	/**
	 * Secret storage.
	 */
	private SecretStorage $secret_storage;

	/**
	 * Constructor.
	 *
	 * @param SecretStorage                  $secret_storage Secret storage.
	 * @param LicenseProviderInterface|null $provider       Optional license provider.
	 */
	public function __construct( SecretStorage $secret_storage, ?LicenseProviderInterface $provider = null ) {
		$this->secret_storage = $secret_storage;
		$this->provider       = $provider;
	}

	/**
	 * Set a custom license provider.
	 *
	 * @param LicenseProviderInterface|null $provider Provider.
	 * @return void
	 */
	public function set_provider( ?LicenseProviderInterface $provider ): void {
		$this->provider = $provider;
	}

	/**
	 * Check if an external provider is configured.
	 *
	 * @return bool
	 */
	public function has_provider(): bool {
		return null !== $this->provider && $this->provider->is_configured();
	}

	/**
	 * Get current license state details.
	 *
	 * @return array<string, mixed>
	 */
	public function get_license_data(): array {
		$data = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( ! $this->has_provider() ) {
			$data['status']  = self::STATUS_NOT_CONFIGURED;
			$data['message'] = __( 'Licensing service integration is not configured for this development build.', 'wpcalibrate-calendar-sync' );
		}

		return array(
			'status'       => (string) ( $data['status'] ?? self::STATUS_NOT_CONFIGURED ),
			'message'      => (string) ( $data['message'] ?? '' ),
			'expires'      => (string) ( $data['expires'] ?? '' ),
			'renewal_url'  => (string) ( $data['renewal_url'] ?? '' ),
			'last_checked' => (string) ( $data['last_checked'] ?? '' ),
		);
	}

	/**
	 * Get masked license key for display in settings.
	 *
	 * @return string
	 */
	public function get_masked_key(): string {
		$raw = $this->secret_storage->get_secret( self::SECRET_KEY );
		if ( empty( $raw ) ) {
			return '';
		}
		return Sanitization::mask_secret( $raw, 4 );
	}

	/**
	 * Save license key.
	 *
	 * @param string $key Plaintext license key.
	 * @return bool
	 */
	public function save_license_key( string $key ): bool {
		if ( ! Capabilities::can_manage() ) {
			return false;
		}

		$clean_key = sanitize_text_field( trim( $key ) );
		if ( '' === $clean_key ) {
			$this->secret_storage->delete_secret( self::SECRET_KEY );
			$data = $this->get_license_data();
			$data['status'] = $this->has_provider() ? self::STATUS_INACTIVE : self::STATUS_NOT_CONFIGURED;
			update_option( self::OPTION_NAME, $data, false );
			return true;
		}

		return $this->secret_storage->store_secret( self::SECRET_KEY, $clean_key );
	}

	/**
	 * Activate license key.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function activate(): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'wpcalibrate-calendar-sync' ),
			);
		}

		if ( ! $this->has_provider() ) {
			return array(
				'success' => false,
				'message' => __( 'Licensing service integration is not configured for this development build. License activation is disabled.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$key = $this->secret_storage->get_secret( self::SECRET_KEY );
		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid license key.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$res = $this->provider->activate( $key );

		$data = array(
			'status'       => $res['status'],
			'message'      => $res['message'],
			'expires'      => $res['expires'] ?? '',
			'renewal_url'  => $res['renewal_url'] ?? '',
			'last_checked' => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( self::OPTION_NAME, $data, false );

		return array(
			'success' => $res['success'],
			'message' => $res['message'],
		);
	}

	/**
	 * Deactivate license key.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function deactivate(): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'wpcalibrate-calendar-sync' ),
			);
		}

		if ( ! $this->has_provider() ) {
			$data = array(
				'status'       => self::STATUS_NOT_CONFIGURED,
				'message'      => __( 'Licensing service integration is not configured for this development build.', 'wpcalibrate-calendar-sync' ),
				'expires'      => '',
				'renewal_url'  => '',
				'last_checked' => gmdate( 'Y-m-d H:i:s' ),
			);
			update_option( self::OPTION_NAME, $data, false );
			return array(
				'success' => true,
				'message' => __( 'License deactivated locally.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$key = $this->secret_storage->get_secret( self::SECRET_KEY );
		if ( ! empty( $key ) ) {
			$res = $this->provider->deactivate( $key );
			$data = array(
				'status'       => self::STATUS_INACTIVE,
				'message'      => $res['message'],
				'expires'      => '',
				'renewal_url'  => '',
				'last_checked' => gmdate( 'Y-m-d H:i:s' ),
			);
			update_option( self::OPTION_NAME, $data, false );
			return $res;
		}

		return array(
			'success' => true,
			'message' => __( 'License deactivated.', 'wpcalibrate-calendar-sync' ),
		);
	}
}
