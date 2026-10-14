<?php
/**
 * Diagnostics & System Health Service.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Admin;

use WPCalibrate\CalendarSync\Auth\OAuthController;
use WPCalibrate\CalendarSync\Auth\SecretStorage;
use WPCalibrate\CalendarSync\Database\Schema;
use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Sync\PushNotificationManager;
use WPCalibrate\CalendarSync\Sync\RecoveryScheduler;
use WPCalibrate\CalendarSync\Sync\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DiagnosticsPage
 */
class DiagnosticsPage {

	/**
	 * OAuth controller.
	 */
	private OAuthController $oauth_controller;

	/**
	 * Secret storage.
	 */
	private SecretStorage $secret_storage;

	/**
	 * Sync service.
	 */
	private SyncService $sync_service;

	/**
	 * Recovery scheduler.
	 */
	private RecoveryScheduler $scheduler;

	/**
	 * Logger.
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param OAuthController   $oauth_controller OAuth controller.
	 * @param SecretStorage     $secret_storage   Secret storage.
	 * @param SyncService       $sync_service     Sync service.
	 * @param RecoveryScheduler $scheduler        Recovery scheduler.
	 * @param Logger            $logger           Logger.
	 */
	public function __construct(
		OAuthController $oauth_controller,
		SecretStorage $secret_storage,
		SyncService $sync_service,
		RecoveryScheduler $scheduler,
		Logger $logger
	) {
		$this->oauth_controller = $oauth_controller;
		$this->secret_storage   = $secret_storage;
		$this->sync_service     = $sync_service;
		$this->scheduler        = $scheduler;
		$this->logger           = $logger;
	}

	/**
	 * Generate sanitized diagnostics data array for display and clipboard copy.
	 *
	 * Guaranteed to exclude all secrets, tokens, and private credentials.
	 *
	 * @return array<string, mixed>
	 */
	public function get_diagnostics_report(): array {
		global $wp_version, $wpdb;

		$conn = $this->oauth_controller->get_connection_details();
		$cron_next = wp_next_scheduled( RecoveryScheduler::HOOK_NAME );

		$calendars = $this->sync_service->get_configured_calendars();
		$safe_calendars = array();
		foreach ( $calendars as $cid => $c ) {
			$safe_calendars[] = array(
				'calendar_id'      => sanitize_text_field( (string) $cid ),
				'name'             => sanitize_text_field( (string) ( $c['name'] ?? '' ) ),
				'direction'        => sanitize_key( (string) ( $c['direction'] ?? '' ) ),
				'enabled'          => ! empty( $c['enabled'] ),
				'has_sync_token'   => ! empty( $c['sync_token'] ),
				'last_sync_utc'    => sanitize_text_field( (string) ( $c['last_sync_utc'] ?? 'Never' ) ),
				'last_error'       => sanitize_text_field( (string) ( $c['last_error'] ?? 'None' ) ),
				'push_active'      => ! empty( $c['channel_id'] ),
				'channel_expires'  => sanitize_text_field( (string) ( $c['channel_expires'] ?? 'N/A' ) ),
			);
		}

		$recent_logs = $this->logger->get_logs( null, 25 );
		$safe_logs = array();
		foreach ( $recent_logs as $entry ) {
			$safe_logs[] = array(
				'timestamp' => $entry['timestamp'] ?? '',
				'level'     => $entry['level'] ?? '',
				'operation' => $entry['operation'] ?? '',
				'message'   => $entry['message'] ?? '',
			);
		}

		return array(
			'environment' => array(
				'plugin_version'    => '1.0.0',
				'wordpress_version' => $wp_version,
				'php_version'       => PHP_VERSION,
				'php_os'            => PHP_OS,
				'web_server'        => sanitize_text_field( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) ),
				'database_version'  => $wpdb->db_version(),
				'schema_version'    => get_option( Schema::VERSION_OPTION, 'Not installed' ),
				'is_ssl'            => is_ssl(),
				'site_url'          => site_url(),
				'timezone'          => get_option( 'timezone_string' ) ?: get_option( 'gmt_offset' ),
			),
			'security_encryption' => array(
				'encryption_engine'    => $this->secret_storage->get_encryption_engine(),
				'encryption_available' => $this->secret_storage->is_encryption_available(),
				'has_client_secret'    => $this->secret_storage->has_secret( 'client_secret' ),
				'has_refresh_token'    => $this->secret_storage->has_secret( 'refresh_token' ),
			),
			'google_connection' => array(
				'status'            => $conn['status'] ?? 'disconnected',
				'has_client_id'     => ! empty( $conn['client_id'] ),
				'connected_at'      => $conn['connected_at'] ?? 'N/A',
				'granted_scopes'    => $conn['scopes_granted'] ?? array(),
				'redirect_uri'      => OAuthController::get_redirect_uri(),
			),
			'push_and_cron' => array(
				'push_eligible'       => PushNotificationManager::is_webhook_eligible(),
				'webhook_url'         => PushNotificationManager::get_webhook_url(),
				'cron_interval_min'   => $this->scheduler->get_interval(),
				'cron_next_run'       => $cron_next ? gmdate( 'Y-m-d H:i:s', $cron_next ) . ' UTC' : 'Not scheduled',
				'server_cron_active'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			),
			'calendars' => $safe_calendars,
			'recent_sanitized_logs' => $safe_logs,
		);
	}
}
