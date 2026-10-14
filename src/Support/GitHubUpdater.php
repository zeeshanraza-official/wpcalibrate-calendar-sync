<?php
/**
 * GitHub Automatic Plugin Update Checker.
 *
 * Integrates WordPress core update mechanisms with GitHub releases for seamless
 * dashboard updates directly from GitHub.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Support;

use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GitHubUpdater
 */
class GitHubUpdater {

	/**
	 * GitHub Repository owner and name.
	 */
	private string $repo;

	/**
	 * Main plugin file path relative to plugins directory.
	 */
	private string $plugin_basename;

	/**
	 * Plugin slug.
	 */
	private string $slug;

	/**
	 * Current installed plugin version.
	 */
	private string $current_version;

	/**
	 * Transient cache key.
	 */
	private const TRANSIENT_KEY = 'wpcs_github_latest_release';

	/**
	 * Cache duration in seconds (6 hours).
	 */
	private const CACHE_TTL = 21600;

	/**
	 * Constructor.
	 *
	 * @param string $repo            GitHub repository (e.g. 'zeeshanraza-official/wpcalibrate-calendar-sync').
	 * @param string $plugin_file     Full path to main plugin file.
	 * @param string $current_version Current plugin version.
	 */
	public function __construct( string $repo, string $plugin_file, string $current_version ) {
		$this->repo            = trim( $repo, '/' );
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->slug            = dirname( $this->plugin_basename );
		if ( '.' === $this->slug || empty( $this->slug ) ) {
			$this->slug = 'wpcalibrate-calendar-sync';
		}
		$this->current_version = $current_version;
	}

	/**
	 * Register update hooks into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information_modal' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_release_cache' ), 10, 2 );
	}

	/**
	 * Check GitHub releases and inject update info if a newer version exists.
	 *
	 * @param object|false $transient Plugins update transient.
	 * @return object
	 */
	public function check_for_plugin_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$release = $this->get_latest_release();
		if ( empty( $release ) ) {
			return $transient;
		}

		$remote_version = ltrim( (string) ( $release['tag_name'] ?? '' ), 'v' );
		if ( empty( $remote_version ) ) {
			return $transient;
		}

		if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
			$download_url = $this->extract_package_url( $release );

			$item = (object) array(
				'id'            => 'github.com/' . $this->repo,
				'slug'          => $this->slug,
				'plugin'        => $this->plugin_basename,
				'new_version'   => $remote_version,
				'url'           => $release['html_url'] ?? 'https://github.com/' . $this->repo,
				'package'       => $download_url,
				'requires_php'  => '8.2',
				'requires'      => '6.0',
				'tested'        => '7.1.3',
				'icons'         => array(
					'1x' => WPCS_URL . 'assets/branding/icon-dark.png',
					'2x' => WPCS_URL . 'assets/branding/icon-dark.png',
				),
				'banners'       => array(),
				'banners_rtl'   => array(),
			);

			$transient->response[ $this->plugin_basename ] = $item;
		} else {
			// Inform WordPress no update is needed.
			$item = (object) array(
				'id'            => 'github.com/' . $this->repo,
				'slug'          => $this->slug,
				'plugin'        => $this->plugin_basename,
				'new_version'   => $this->current_version,
				'url'           => $release['html_url'] ?? 'https://github.com/' . $this->repo,
				'package'       => '',
			);
			$transient->no_update[ $this->plugin_basename ] = $item;
		}

		return $transient;
	}

	/**
	 * Provide plugin information modal content for the WordPress Dashboard.
	 *
	 * @param false|object|array $result Result object or false.
	 * @param string             $action The API action being performed.
	 * @param object             $args   Plugin information arguments.
	 * @return false|object
	 */
	public function plugin_information_modal( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== $this->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( empty( $release ) ) {
			return $result;
		}

		$remote_version = ltrim( (string) ( $release['tag_name'] ?? $this->current_version ), 'v' );
		$download_url   = $this->extract_package_url( $release );

		$info = new stdClass();
		$info->name           = 'WPCalibrate Calendar Sync';
		$info->slug           = $this->slug;
		$info->version        = $remote_version;
		$info->author         = '<a href="https://wpcalibrate.com">WPCalibrate</a>';
		$info->author_profile = 'https://wpcalibrate.com';
		$info->homepage       = 'https://github.com/' . $this->repo;
		$info->requires       = '6.0';
		$info->requires_php   = '8.2';
		$info->tested         = '7.1.3';
		$info->download_link  = $download_url;
		$info->trunk          = $download_url;
		$info->last_updated   = gmdate( 'Y-m-d', strtotime( (string) ( $release['published_at'] ?? 'now' ) ) );

		$body = (string) ( $release['body'] ?? 'Regular maintenance and stability improvements.' );

		$info->sections = array(
			'description' => '<p>' . esc_html__( 'Enterprise-grade, two-way Google Calendar synchronization for WordPress with local DB caching, encrypted secret storage, and push webhook support.', 'wpcalibrate-calendar-sync' ) . '</p>',
			'changelog'   => '<div>' . nl2br( esc_html( $body ) ) . '</div>',
			'installation'=> '<p>' . esc_html__( 'Automatic update via WordPress Dashboard or download ZIP from GitHub releases.', 'wpcalibrate-calendar-sync' ) . '</p>',
		);

		$info->banners = array(
			'high' => '',
			'low'  => '',
		);

		return $info;
	}

	/**
	 * Query GitHub API for the latest release with caching.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_latest_release(): ?array {
		// Bypass cache if user clicked "Check Again" on update-core.php.
		global $pagenow;
		$force_check = 'update-core.php' === $pagenow && isset( $_GET['force-check'] );

		if ( ! $force_check ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$url      = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'headers'    => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				),
				'sslverify'  => true,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		set_transient( self::TRANSIENT_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * Extract ZIP package URL from GitHub release assets or fallback to zipball.
	 *
	 * @param array<string, mixed> $release GitHub release payload.
	 * @return string
	 */
	private function extract_package_url( array $release ): string {
		$assets = $release['assets'] ?? array();
		if ( is_array( $assets ) ) {
			foreach ( $assets as $asset ) {
				$name = (string) ( $asset['name'] ?? '' );
				if ( str_ends_with( $name, '.zip' ) && ! empty( $asset['browser_download_url'] ) ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}

		return (string) ( $release['zipball_url'] ?? '' );
	}

	/**
	 * Clear cached release data upon successful plugin upgrade.
	 *
	 * @param mixed $upgrader Upgrader instance.
	 * @param array $options  Upgrade options.
	 * @return void
	 */
	public function clear_release_cache( $upgrader, array $options ): void {
		if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) ) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}
}
