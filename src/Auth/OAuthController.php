<?php
/**
 * Google OAuth 2.0 Flow Controller.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Auth;

use Exception;
use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Support\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OAuthController
 */
class OAuthController {

	/**
	 * Connection settings option.
	 */
	public const OPTION_CONNECTION = 'wpcs_connection';

	/**
	 * State transient prefix.
	 */
	private const STATE_TRANSIENT_PREFIX = 'wpcs_oauth_state_';

	/**
	 * Full Calendar Read/Write Scope.
	 */
	public const SCOPE_FULL = 'https://www.googleapis.com/auth/calendar';

	/**
	 * Calendar Read-Only Scope.
	 */
	public const SCOPE_READONLY = 'https://www.googleapis.com/auth/calendar.readonly';

	/**
	 * Secret storage instance.
	 */
	private SecretStorage $secret_storage;

	/**
	 * Logger instance.
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param SecretStorage $secret_storage Secret storage service.
	 * @param Logger|null   $logger         Logger service.
	 */
	public function __construct( SecretStorage $secret_storage, ?Logger $logger = null ) {
		$this->secret_storage = $secret_storage;
		$this->logger         = $logger;
	}

	/**
	 * Get secret storage instance.
	 *
	 * @return SecretStorage
	 */
	public function get_secret_storage(): SecretStorage {
		return $this->secret_storage;
	}

	/**
	 * Get registered/authorized redirect URI for this WordPress site.
	 *
	 * @return string
	 */
	public static function get_redirect_uri(): string {
		return admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=connection&wpcs_action=oauth_callback' );
	}

	/**
	 * Check if Google is currently connected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$connection = get_option( self::OPTION_CONNECTION, array() );
		if ( ! is_array( $connection ) || ( $connection['status'] ?? '' ) !== 'connected' ) {
			return false;
		}

		return ! empty( $connection['client_id'] )
			&& $this->secret_storage->has_secret( 'client_secret' )
			&& $this->secret_storage->has_secret( 'refresh_token' );
	}

	/**
	 * Get connection details.
	 *
	 * @return array<string, mixed>
	 */
	public function get_connection_details(): array {
		$details = get_option( self::OPTION_CONNECTION, array() );
		return is_array( $details ) ? $details : array();
	}

	/**
	 * Save client credentials submitted by administrator.
	 *
	 * @param string $client_id     Google Client ID.
	 * @param string $client_secret Google Client Secret (if provided).
	 * @return array{success: bool, message: string}
	 */
	public function save_client_credentials( string $client_id, string $client_secret ): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'wpcalibrate-calendar-sync' ),
			);
		}

		if ( ! $this->secret_storage->is_encryption_available() ) {
			return array(
				'success' => false,
				'message' => __( 'Server requirement missing: Sodium or OpenSSL AES-256-GCM authenticated encryption is required to store credentials securely.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$client_id = sanitize_text_field( trim( $client_id ) );
		$connection = $this->get_connection_details();
		$connection['client_id'] = $client_id;

		if ( '' !== trim( $client_secret ) ) {
			try {
				$this->secret_storage->store_secret( 'client_secret', trim( $client_secret ) );
			} catch ( Exception $e ) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Encryption failure while saving client secret: %s', 'wpcalibrate-calendar-sync' ),
						$e->getMessage()
					),
				);
			}
		}

		update_option( self::OPTION_CONNECTION, $connection, false );

		if ( $this->logger ) {
			$this->logger->info( 'oauth_save_credentials', 'Updated Google OAuth client credentials.' );
		}

		return array(
			'success' => true,
			'message' => __( 'OAuth credentials saved successfully.', 'wpcalibrate-calendar-sync' ),
		);
	}

	/**
	 * Generate an authorization URL to start the Google OAuth flow.
	 *
	 * @param bool $read_only Request read-only scope if true.
	 * @return string Auth URL or empty on failure.
	 */
	public function create_auth_url( bool $read_only = false ): string {
		$connection = $this->get_connection_details();
		$client_id  = (string) ( $connection['client_id'] ?? '' );
		if ( '' === $client_id || ! $this->secret_storage->has_secret( 'client_secret' ) ) {
			return '';
		}

		$state = wp_generate_password( 40, false );
		set_transient( self::STATE_TRANSIENT_PREFIX . $state, 'valid', 900 ); // 15 mins.

		$scope = $read_only ? self::SCOPE_READONLY : self::SCOPE_FULL;

		$params = array(
			'response_type'          => 'code',
			'client_id'              => $client_id,
			'redirect_uri'           => self::get_redirect_uri(),
			'scope'                  => $scope,
			'state'                  => $state,
			'access_type'            => 'offline',
			'prompt'                 => 'consent',
			'include_granted_scopes' => 'true',
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Handle OAuth 2.0 authorization callback from Google.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state Anti-CSRF state token.
	 * @return array{success: bool, message: string}
	 */
	public function handle_callback( string $code, string $state ): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions to authorize connection.', 'wpcalibrate-calendar-sync' ),
			);
		}

		// Verify state.
		$state_key = self::STATE_TRANSIENT_PREFIX . $state;
		if ( false === get_transient( $state_key ) ) {
			if ( $this->logger ) {
				$this->logger->warning( 'oauth_callback', 'OAuth state verification failed. Possible CSRF attempt.' );
			}
			return array(
				'success' => false,
				'message' => __( 'OAuth state parameter invalid or expired. Please re-try connecting.', 'wpcalibrate-calendar-sync' ),
			);
		}
		delete_transient( $state_key );

		$connection    = $this->get_connection_details();
		$client_id     = (string) ( $connection['client_id'] ?? '' );
		$client_secret = $this->secret_storage->get_secret( 'client_secret' );

		if ( '' === $client_id || empty( $client_secret ) ) {
			return array(
				'success' => false,
				'message' => __( 'Client ID or Secret is missing.', 'wpcalibrate-calendar-sync' ),
			);
		}

		// Exchange code for tokens via Google token endpoint.
		$token_response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => self::get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $token_response ) ) {
			if ( $this->logger ) {
				$this->logger->error(
					'oauth_callback',
					'Token exchange failed: ' . $token_response->get_error_message()
				);
			}
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Network error contacting Google: %s', 'wpcalibrate-calendar-sync' ),
					$token_response->get_error_message()
				),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $token_response );
		$body_raw      = wp_remote_retrieve_body( $token_response );
		$body          = json_decode( $body_raw, true );

		if ( 200 !== $response_code || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$err_desc = is_array( $body ) ? ( $body['error_description'] ?? $body['error'] ?? 'Unknown error' ) : 'Invalid response';
			if ( $this->logger ) {
				$this->logger->error(
					'oauth_callback',
					sprintf( 'Google rejected token request (HTTP %d): %s', $response_code, $err_desc )
				);
			}
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: HTTP status code, 2: error detail */
					__( 'Google authorization error (HTTP %1$d): %2$s', 'wpcalibrate-calendar-sync' ),
					$response_code,
					esc_html( (string) $err_desc )
				),
			);
		}

		// Save encrypted tokens.
		try {
			$this->secret_storage->store_secret( 'access_token', (string) $body['access_token'] );
			$expires_in = (int) ( $body['expires_in'] ?? 3600 );
			$this->secret_storage->store_secret( 'access_token_expires_at', (string) ( time() + $expires_in ) );

			if ( ! empty( $body['refresh_token'] ) ) {
				$this->secret_storage->store_secret( 'refresh_token', (string) $body['refresh_token'] );
			} elseif ( ! $this->secret_storage->has_secret( 'refresh_token' ) ) {
				return array(
					'success' => false,
					'message' => __( 'Google did not return a refresh token. Please disconnect and reconnect ensuring offline access consent is approved.', 'wpcalibrate-calendar-sync' ),
				);
			}
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error */
					__( 'Encryption failed when storing access/refresh tokens: %s', 'wpcalibrate-calendar-sync' ),
					$e->getMessage()
				),
			);
		}

		// Update connection record.
		$connection['status']         = 'connected';
		$connection['connected_at']   = gmdate( 'Y-m-d H:i:s' );
		$connection['scopes_granted'] = isset( $body['scope'] ) ? explode( ' ', (string) $body['scope'] ) : array( self::SCOPE_FULL );
		update_option( self::OPTION_CONNECTION, $connection, false );

		if ( $this->logger ) {
			$this->logger->info( 'oauth_connected', 'Google account successfully connected via OAuth 2.0.' );
		}

		return array(
			'success' => true,
			'message' => __( 'Google Calendar successfully connected.', 'wpcalibrate-calendar-sync' ),
		);
	}

	/**
	 * Refresh access token using stored refresh token.
	 *
	 * @return string|null New access token or null on failure.
	 */
	public function refresh_access_token(): ?string {
		$connection    = $this->get_connection_details();
		$client_id     = (string) ( $connection['client_id'] ?? '' );
		$client_secret = $this->secret_storage->get_secret( 'client_secret' );
		$refresh_token = $this->secret_storage->get_secret( 'refresh_token' );

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) ) {
			return null;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( $this->logger ) {
				$this->logger->error( 'token_refresh', 'Network error during token refresh: ' . $response->get_error_message() );
			}
			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			if ( $this->logger ) {
				$this->logger->error(
					'token_refresh',
					sprintf( 'Failed to refresh token (HTTP %d). Refresh token may be revoked or expired.', $status )
				);
			}
			return null;
		}

		$new_access_token = (string) $body['access_token'];
		$expires_in       = (int) ( $body['expires_in'] ?? 3600 );

		$this->secret_storage->store_secret( 'access_token', $new_access_token );
		$this->secret_storage->store_secret( 'access_token_expires_at', (string) ( time() + $expires_in ) );

		// If a new refresh token was provided in rotation, update it.
		if ( ! empty( $body['refresh_token'] ) ) {
			$this->secret_storage->store_secret( 'refresh_token', (string) $body['refresh_token'] );
		}

		return $new_access_token;
	}

	/**
	 * Get valid access token, auto-refreshing if expired or within 60s of expiration.
	 *
	 * @return string|null
	 */
	public function get_valid_access_token(): ?string {
		$expires_at = (int) ( $this->secret_storage->get_secret( 'access_token_expires_at' ) ?? 0 );
		$token      = $this->secret_storage->get_secret( 'access_token' );

		if ( empty( $token ) || time() >= ( $expires_at - 60 ) ) {
			return $this->refresh_access_token();
		}

		return $token;
	}

	/**
	 * Disconnect Google Account.
	 *
	 * Revokes token remotely where possible and removes local credentials.
	 * Synchronized event records are preserved.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function disconnect(): array {
		if ( ! Capabilities::can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$token = $this->secret_storage->get_secret( 'access_token' );
		if ( ! empty( $token ) ) {
			wp_remote_post(
				'https://oauth2.googleapis.com/revoke',
				array(
					'timeout' => 10,
					'body'    => array( 'token' => $token ),
				)
			);
		}

		// Delete stored secrets.
		$this->secret_storage->delete_secret( 'access_token' );
		$this->secret_storage->delete_secret( 'refresh_token' );
		$this->secret_storage->delete_secret( 'access_token_expires_at' );

		// Update connection record to disconnected.
		$connection           = $this->get_connection_details();
		$connection['status'] = 'disconnected';
		unset( $connection['scopes_granted'] );
		update_option( self::OPTION_CONNECTION, $connection, false );

		if ( $this->logger ) {
			$this->logger->info( 'oauth_disconnect', 'Disconnected Google account and removed access/refresh credentials.' );
		}

		return array(
			'success' => true,
			'message' => __( 'Google Calendar disconnected.', 'wpcalibrate-calendar-sync' ),
		);
	}
}
