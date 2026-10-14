<?php
/**
 * Main Plugin Orchestrator and Lifecycle Manager.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync;

use WPCalibrate\CalendarSync\Admin\DiagnosticsPage;
use WPCalibrate\CalendarSync\Admin\EventsPage;
use WPCalibrate\CalendarSync\Admin\Menu;
use WPCalibrate\CalendarSync\Admin\SettingsPage;
use WPCalibrate\CalendarSync\Api\GoogleCalendarService;
use WPCalibrate\CalendarSync\Api\GoogleClientFactory;
use WPCalibrate\CalendarSync\Auth\OAuthController;
use WPCalibrate\CalendarSync\Auth\SecretStorage;
use WPCalibrate\CalendarSync\Database\MigrationManager;
use WPCalibrate\CalendarSync\Events\EventRepository;
use WPCalibrate\CalendarSync\Events\EventService;
use WPCalibrate\CalendarSync\Frontend\Block;
use WPCalibrate\CalendarSync\Frontend\CalendarRenderer;
use WPCalibrate\CalendarSync\Frontend\Shortcode;
use WPCalibrate\CalendarSync\Http\RestController;
use WPCalibrate\CalendarSync\Http\WebhookController;
use WPCalibrate\CalendarSync\License\LicenseManager;
use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Support\DemoDataManager;
use WPCalibrate\CalendarSync\Support\GitHubUpdater;
use WPCalibrate\CalendarSync\Sync\PushNotificationManager;
use WPCalibrate\CalendarSync\Sync\RecoveryScheduler;
use WPCalibrate\CalendarSync\Sync\SyncLock;
use WPCalibrate\CalendarSync\Sync\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Plugin version.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Singleton instance.
	 */
	private static ?Plugin $instance = null;

	/**
	 * Component services.
	 */
	private Logger $logger;
	private SecretStorage $secret_storage;
	private OAuthController $oauth_controller;
	private GoogleClientFactory $google_client_factory;
	private GoogleCalendarService $google_calendar_service;
	private EventRepository $event_repository;
	private EventService $event_service;
	private SyncLock $sync_lock;
	private SyncService $sync_service;
	private PushNotificationManager $push_manager;
	private RecoveryScheduler $scheduler;
	private LicenseManager $license_manager;
	private DiagnosticsPage $diagnostics;
	private DemoDataManager $demo_manager;
	private SettingsPage $settings_page;
	private EventsPage $events_page;
	private Menu $admin_menu;
	private CalendarRenderer $calendar_renderer;
	private Shortcode $shortcode;
	private Block $block;
	private RestController $rest_controller;
	private WebhookController $webhook_controller;
	private MigrationManager $migration_manager;
	private GitHubUpdater $github_updater;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->initialize_services();
	}

	/**
	 * Instantiate and wire all component services.
	 *
	 * @return void
	 */
	private function initialize_services(): void {
		$this->logger                 = new Logger();
		$this->secret_storage         = new SecretStorage();
		$this->oauth_controller       = new OAuthController( $this->secret_storage, $this->logger );
		$this->google_client_factory  = new GoogleClientFactory( $this->oauth_controller );
		$this->google_calendar_service= new GoogleCalendarService( $this->google_client_factory, $this->logger );
		$this->event_repository       = new EventRepository();
		$this->event_service          = new EventService( $this->event_repository, $this->logger );
		$this->sync_lock              = new SyncLock();
		$this->sync_service           = new SyncService( $this->event_repository, $this->google_calendar_service, $this->sync_lock, $this->logger );
		$this->push_manager           = new PushNotificationManager( $this->google_calendar_service, $this->sync_service, $this->logger );
		$this->scheduler              = new RecoveryScheduler( $this->sync_service, $this->push_manager, $this->logger );
		$this->license_manager        = new LicenseManager( $this->secret_storage );
		$this->diagnostics            = new DiagnosticsPage( $this->oauth_controller, $this->secret_storage, $this->sync_service, $this->scheduler, $this->logger );
		$this->demo_manager           = new DemoDataManager( $this->secret_storage, $this->event_repository, $this->sync_service, $this->logger );
		$this->settings_page          = new SettingsPage(
			$this->oauth_controller,
			$this->sync_service,
			$this->push_manager,
			$this->scheduler,
			$this->license_manager,
			$this->diagnostics,
			$this->logger,
			$this->google_calendar_service,
			$this->demo_manager
		);
		$this->events_page            = new EventsPage( $this->event_service, $this->event_repository, $this->sync_service );
		$this->admin_menu             = new Menu( $this->settings_page, $this->events_page );
		$this->calendar_renderer      = new CalendarRenderer( $this->event_repository );
		$this->shortcode              = new Shortcode( $this->calendar_renderer );
		$this->block                  = new Block( $this->calendar_renderer );
		$this->rest_controller        = new RestController( $this->event_repository, $this->sync_service );
		$this->webhook_controller      = new WebhookController( $this->sync_service, $this->logger );
		$this->migration_manager      = new MigrationManager( $this->logger );
		$this->github_updater         = new GitHubUpdater(
			'zeeshanraza-official/wpcalibrate-calendar-sync',
			defined( 'WPCS_FILE' ) ? WPCS_FILE : dirname( __DIR__ ) . '/wpcalibrate-calendar-sync.php',
			defined( 'WPCS_VERSION' ) ? WPCS_VERSION : '1.0.0'
		);
	}

	/**
	 * Register all WordPress actions and filters.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Localization.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Shortcode & Block.
		$this->shortcode->register();
		add_action( 'init', array( $this->block, 'register' ) );

		// Admin menus & screens.
		if ( is_admin() ) {
			$this->admin_menu->register();
			add_action( 'admin_init', array( $this, 'check_database_upgrades' ) );
			$this->github_updater->register();
		}

		// REST API & Webhooks.
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->webhook_controller, 'register_routes' ) );

		// WP-Cron schedules and recovery handler.
		add_filter( 'cron_schedules', array( $this->scheduler, 'add_cron_schedules' ) );
		add_action( RecoveryScheduler::HOOK_NAME, array( $this->scheduler, 'run' ) );
	}

	/**
	 * Load translation textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wpcalibrate-calendar-sync',
			false,
			dirname( plugin_basename( dirname( __DIR__ ) . '/wpcalibrate-calendar-sync.php' ) ) . '/languages'
		);
	}

	/**
	 * Idempotent database schema check during admin requests.
	 *
	 * @return void
	 */
	public function check_database_upgrades(): void {
		$this->migration_manager->run_migrations();
	}

	/**
	 * Accessors for testing and services.
	 */
	public function get_logger(): Logger {
		return $this->logger;
	}

	public function get_secret_storage(): SecretStorage {
		return $this->secret_storage;
	}

	public function get_oauth_controller(): OAuthController {
		return $this->oauth_controller;
	}

	public function get_event_repository(): EventRepository {
		return $this->event_repository;
	}

	public function get_sync_service(): SyncService {
		return $this->sync_service;
	}

	public function get_recovery_scheduler(): RecoveryScheduler {
		return $this->scheduler;
	}

	public function get_license_manager(): LicenseManager {
		return $this->license_manager;
	}

	public function get_demo_manager(): DemoDataManager {
		return $this->demo_manager;
	}

	public function get_github_updater(): GitHubUpdater {
		return $this->github_updater;
	}
}
