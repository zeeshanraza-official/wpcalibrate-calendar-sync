<?php
/**
 * Factory for Google Client Instances.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Api;

use Google\Client as GoogleClient;
use RuntimeException;
use WPCalibrate\CalendarSync\Auth\OAuthController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GoogleClientFactory
 */
class GoogleClientFactory {

	/**
	 * OAuth controller instance.
	 */
	private OAuthController $oauth_controller;

	/**
	 * Custom/mock client instance for unit testing.
	 */
	private ?GoogleClient $mock_client = null;

	/**
	 * Constructor.
	 *
	 * @param OAuthController $oauth_controller OAuth controller.
	 */
	public function __construct( OAuthController $oauth_controller ) {
		$this->oauth_controller = $oauth_controller;
	}

	/**
	 * Set a mock client for testing.
	 *
	 * @param GoogleClient|null $client Mock Google client.
	 * @return void
	 */
	public function set_mock_client( ?GoogleClient $client ): void {
		$this->mock_client = $client;
	}

	/**
	 * Create and configure an authenticated Google Client.
	 *
	 * @return GoogleClient
	 * @throws RuntimeException If connection is not active or token cannot be obtained.
	 */
	public function create_client(): GoogleClient {
		if ( null !== $this->mock_client ) {
			return $this->mock_client;
		}

		if ( ! class_exists( GoogleClient::class ) ) {
			throw new RuntimeException( 'Google API PHP Client library is not loaded.' );
		}

		$connection    = $this->oauth_controller->get_connection_details();
		$client_id     = (string) ( $connection['client_id'] ?? '' );
		$client_secret = $this->oauth_controller->get_secret_storage()->get_secret( 'client_secret' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new RuntimeException( 'Google OAuth client credentials are not configured.' );
		}

		$access_token = $this->oauth_controller->get_valid_access_token();
		if ( empty( $access_token ) ) {
			throw new RuntimeException( 'Unable to obtain a valid Google access token. Please reconnect.' );
		}

		$client = new GoogleClient();
		$client->setApplicationName( 'WPCalibrate Calendar Sync' );
		$client->setClientId( $client_id );
		$client->setClientSecret( $client_secret );
		$client->setAccessToken( $access_token );

		return $client;
	}
}
