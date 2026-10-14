<?php
/**
 * Settings Page Controller.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Admin;

use Exception;
use WPCalibrate\CalendarSync\Api\GoogleCalendarService;
use WPCalibrate\CalendarSync\Auth\OAuthController;
use WPCalibrate\CalendarSync\License\LicenseManager;
use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Support\Capabilities;
use WPCalibrate\CalendarSync\Support\DemoDataManager;
use WPCalibrate\CalendarSync\Support\Sanitization;
use WPCalibrate\CalendarSync\Sync\PushNotificationManager;
use WPCalibrate\CalendarSync\Sync\RecoveryScheduler;
use WPCalibrate\CalendarSync\Sync\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsPage
 */
class SettingsPage {

	/**
	 * General display settings option name.
	 */
	public const OPTION_DISPLAY = 'wpcs_display_settings';

	/**
	 * Data retention setting option.
	 */
	public const OPTION_DELETE_DATA_ON_UNINSTALL = 'wpcs_delete_data_on_uninstall';

	/**
	 * Subsystems.
	 */
	private OAuthController $oauth_controller;
	private SyncService $sync_service;
	private PushNotificationManager $push_manager;
	private RecoveryScheduler $scheduler;
	private LicenseManager $license_manager;
	private DiagnosticsPage $diagnostics;
	private Logger $logger;
	private ?GoogleCalendarService $google_service;
	private ?DemoDataManager $demo_manager;

	/**
	 * Constructor.
	 */
	public function __construct(
		OAuthController $oauth_controller,
		SyncService $sync_service,
		PushNotificationManager $push_manager,
		RecoveryScheduler $scheduler,
		LicenseManager $license_manager,
		DiagnosticsPage $diagnostics,
		Logger $logger,
		?GoogleCalendarService $google_service = null,
		?DemoDataManager $demo_manager = null
	) {
		$this->oauth_controller = $oauth_controller;
		$this->sync_service     = $sync_service;
		$this->push_manager     = $push_manager;
		$this->scheduler        = $scheduler;
		$this->license_manager  = $license_manager;
		$this->diagnostics      = $diagnostics;
		$this->logger           = $logger;
		$this->google_service   = $google_service;
		$this->demo_manager     = $demo_manager;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		Capabilities::verify_manage();

		$this->handle_form_actions();

		$current_tab = sanitize_key( (string) ( $_GET['tab'] ?? 'overview' ) );
		$valid_tabs  = array( 'overview', 'connection', 'calendars', 'synchronization', 'display', 'diagnostics', 'license', 'support' );
		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = 'overview';
		}

		$brand_logo_url = plugin_dir_url( dirname( __DIR__ ) ) . 'assets/branding/icon-dark.png';
		?>
		<div class="wrap wpcs-admin-wrap">
			<header class="wpcs-admin-header">
				<div class="wpcs-brand-meta">
					<?php if ( file_exists( dirname( __DIR__, 2 ) . '/assets/branding/icon-dark.png' ) ) : ?>
						<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="WPCalibrate" class="wpcs-brand-logo" width="36" height="36" />
					<?php endif; ?>
					<div class="wpcs-brand-titles">
						<h1 class="wpcs-plugin-title"><?php esc_html_e( 'WPCalibrate Calendar Sync', 'wpcalibrate-calendar-sync' ); ?></h1>
						<span class="wpcs-plugin-version"><?php esc_html_e( 'v1.0.0', 'wpcalibrate-calendar-sync' ); ?></span>
					</div>
				</div>
			</header>

			<nav class="nav-tab-wrapper wpcs-nav-tabs" aria-label="<?php esc_attr_e( 'Settings Navigation', 'wpcalibrate-calendar-sync' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=overview' ) ); ?>" class="nav-tab <?php echo 'overview' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Overview', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=connection' ) ); ?>" class="nav-tab <?php echo 'connection' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Connection', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=calendars' ) ); ?>" class="nav-tab <?php echo 'calendars' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Calendars', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=synchronization' ) ); ?>" class="nav-tab <?php echo 'synchronization' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Synchronization', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=display' ) ); ?>" class="nav-tab <?php echo 'display' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Display', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=diagnostics' ) ); ?>" class="nav-tab <?php echo 'diagnostics' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Diagnostics', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=license' ) ); ?>" class="nav-tab <?php echo 'license' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=support' ) ); ?>" class="nav-tab <?php echo 'support' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Support', 'wpcalibrate-calendar-sync' ); ?>
				</a>
			</nav>

			<main class="wpcs-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'overview':
						Overview::render();
						break;
					case 'connection':
						$this->render_connection_tab();
						break;
					case 'calendars':
						$this->render_calendars_tab();
						break;
					case 'synchronization':
						$this->render_synchronization_tab();
						break;
					case 'display':
						$this->render_display_tab();
						break;
					case 'diagnostics':
						$this->render_diagnostics_tab();
						break;
					case 'license':
						$this->render_license_tab();
						break;
					case 'support':
						$this->render_support_tab();
						break;
				}
				?>
			</main>
		</div>
		<?php
	}

	/**
	 * Handle admin state-changing form actions safely.
	 *
	 * @return void
	 */
	private function handle_form_actions(): void {
		// Handle OAuth callback first if present.
		if ( isset( $_GET['wpcs_action'] ) && 'oauth_callback' === $_GET['wpcs_action'] ) {
			$code  = sanitize_text_field( (string) ( $_GET['code'] ?? '' ) );
			$state = sanitize_text_field( (string) ( $_GET['state'] ?? '' ) );
			if ( ! empty( $code ) && ! empty( $state ) ) {
				$res = $this->oauth_controller->handle_callback( $code, $state );
				if ( $res['success'] ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				}
			}
			return;
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['wpcs_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( (string) $_POST['wpcs_nonce'] ), 'wpcs_settings_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page.', 'wpcalibrate-calendar-sync' ) );
		}

		$action = sanitize_key( (string) ( $_POST['wpcs_action'] ?? '' ) );

		switch ( $action ) {
			case 'save_credentials':
				$cid = sanitize_text_field( (string) ( $_POST['client_id'] ?? '' ) );
				$sec = sanitize_text_field( (string) ( $_POST['client_secret'] ?? '' ) );
				$res = $this->oauth_controller->save_client_credentials( $cid, $sec );
				$notice_class = $res['success'] ? 'notice-success' : 'notice-error';
				echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				break;

			case 'disconnect_google':
				$res = $this->oauth_controller->disconnect();
				echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				break;

			case 'save_calendars':
				$raw_calendars = $_POST['calendars'] ?? array();
				$sanitized     = array();
				if ( is_array( $raw_calendars ) ) {
					foreach ( $raw_calendars as $cid => $cfg ) {
						if ( is_array( $cfg ) ) {
							$cfg['id'] = $cid;
							$sanitized[ $cid ] = Sanitization::sanitize_calendar_config( $cfg );
						}
					}
				}
				$this->sync_service->save_configured_calendars( $sanitized );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Calendar settings saved.', 'wpcalibrate-calendar-sync' ) . '</p></div>';
				break;

			case 'sync_now':
				$cal_id = sanitize_text_field( (string) ( $_POST['sync_calendar_id'] ?? '' ) );
				if ( ! empty( $cal_id ) ) {
					$res = $this->sync_service->sync_calendar( $cal_id, false );
					if ( $res['success'] ) {
						echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
							/* translators: 1: imported, 2: updated, 3: deleted */
							esc_html__( 'Synchronization complete. Imported: %1$d, Updated: %2$d, Deleted: %3$d.', 'wpcalibrate-calendar-sync' ),
							$res['imported'],
							$res['updated'],
							$res['deleted']
						) . '</p></div>';
					} else {
						echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(
							/* translators: %s: error message */
							esc_html__( 'Synchronization failed: %s', 'wpcalibrate-calendar-sync' ),
							esc_html( $res['error'] ?? 'Unknown error' )
						) . '</p></div>';
					}
				} else {
					$results = $this->sync_service->sync_all( false );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All enabled calendars synchronized.', 'wpcalibrate-calendar-sync' ) . '</p></div>';
				}
				break;

			case 'full_resync_all':
				$results = $this->sync_service->sync_all( true );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Full resynchronization completed for all calendars.', 'wpcalibrate-calendar-sync' ) . '</p></div>';
				break;

			case 'save_sync_settings':
				$interval = (int) ( $_POST['recovery_interval'] ?? 15 );
				$this->scheduler->update_interval( $interval );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Synchronization schedule updated.', 'wpcalibrate-calendar-sync' ) . '</p></div>';
				break;

			case 'save_display_settings':
				$settings = array(
					'default_view'     => sanitize_key( (string) ( $_POST['default_view'] ?? 'agenda' ) ),
					'limit'            => max( 1, (int) ( $_POST['limit'] ?? 20 ) ),
					'show_description' => ! empty( $_POST['show_description'] ),
					'show_location'    => ! empty( $_POST['show_location'] ),
				);
				update_option( self::OPTION_DISPLAY, $settings, false );
				update_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, ! empty( $_POST['delete_data_on_uninstall'] ), false );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Display and data retention settings saved.', 'wpcalibrate-calendar-sync' ) . '</p></div>';
				break;

			case 'save_license':
				$key = sanitize_text_field( (string) ( $_POST['license_key'] ?? '' ) );
				$this->license_manager->save_license_key( $key );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'License key saved.', 'wpcalibrate-calendar-sync' ) . '</p></div>';
				break;

			case 'activate_license':
				$res = $this->license_manager->activate();
				$notice_class = $res['success'] ? 'notice-success' : 'notice-warning';
				echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				break;

			case 'install_demo_data':
				if ( $this->demo_manager ) {
					$res = $this->demo_manager->install_demo_data();
					$notice_class = $res['success'] ? 'notice-success' : 'notice-error';
					echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				}
				break;

			case 'remove_demo_data':
				if ( $this->demo_manager ) {
					$res = $this->demo_manager->remove_demo_data();
					$notice_class = $res['success'] ? 'notice-info' : 'notice-error';
					echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				}
				break;
		}
	}

	/**
	 * Connection Tab.
	 */
	private function render_connection_tab(): void {
		$details       = $this->oauth_controller->get_connection_details();
		$is_connected  = $this->oauth_controller->is_connected();
		$client_id     = (string) ( $details['client_id'] ?? '' );
		$redirect_uri  = OAuthController::get_redirect_uri();
		$has_secret    = $this->oauth_controller->get_secret_storage()->has_secret( 'client_secret' );
		$enc_available = $this->oauth_controller->get_secret_storage()->is_encryption_available();
		$enc_engine    = $this->oauth_controller->get_secret_storage()->get_encryption_engine();
		$is_demo       = $this->demo_manager ? $this->demo_manager->is_demo_active() : false;
		?>
		<?php if ( $is_demo ) : ?>
			<div class="wpcs-demo-active-banner">
				<div class="wpcs-demo-active-left">
					<span class="dashicons dashicons-info wpcs-demo-active-icon"></span>
					<div>
						<h4 class="wpcs-demo-active-title"><?php esc_html_e( 'Demo Mode is Currently Active', 'wpcalibrate-calendar-sync' ); ?></h4>
						<p class="wpcs-demo-active-desc">
							<?php esc_html_e( 'Simulated Google OAuth 2.0 connection is active with 3 demo calendars and 12 realistic sample events. You can test calendar views, the Gutenberg block, and frontend shortcodes right away.', 'wpcalibrate-calendar-sync' ); ?>
						</p>
					</div>
				</div>
				<div class="wpcs-demo-active-actions">
					<form method="post" action="" style="display:inline-block; margin:0;">
						<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
						<input type="hidden" name="wpcs_action" value="remove_demo_data" />
						<button type="submit" class="button button-secondary wpcs-btn-demo-remove" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove all demo calendars, events, and simulated OAuth credentials?', 'wpcalibrate-calendar-sync' ) ); ?>');">
							<span class="dashicons dashicons-trash" style="margin-top:3px; margin-right:3px;"></span>
							<?php esc_html_e( 'Remove Demo Data & Reset', 'wpcalibrate-calendar-sync' ); ?>
						</button>
					</form>
				</div>
			</div>
		<?php endif; ?>

		<div class="wpcs-card">
			<h2><?php esc_html_e( 'Google OAuth 2.0 Connection', 'wpcalibrate-calendar-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Connect your WordPress site to your Google Cloud Project to synchronize Google Calendar events.', 'wpcalibrate-calendar-sync' ); ?>
			</p>

			<?php if ( ! $enc_available ) : ?>
				<div class="notice notice-error inline">
					<p><strong><?php esc_html_e( 'Server Encryption Requirement Missing:', 'wpcalibrate-calendar-sync' ); ?></strong> <?php esc_html_e( 'Sodium or OpenSSL AES-256-GCM is required to store OAuth refresh tokens and client secrets securely. OAuth connection is disabled until this requirement is met.', 'wpcalibrate-calendar-sync' ); ?></p>
				</div>
			<?php elseif ( $is_demo ) : ?>
				<div class="wpcs-status-badge wpcs-status-demo">
					<span class="wpcs-dot wpcs-dot-demo"></span>
					<?php esc_html_e( 'Connected to Google Calendar (Demo Mode)', 'wpcalibrate-calendar-sync' ); ?>
					<span class="wpcs-engine-tag"><?php esc_html_e( 'Simulated OAuth 2.0', 'wpcalibrate-calendar-sync' ); ?></span>
				</div>
			<?php else : ?>
				<div class="wpcs-status-badge <?php echo $is_connected ? 'wpcs-status-active' : 'wpcs-status-inactive'; ?>">
					<span class="wpcs-dot"></span>
					<?php echo $is_connected ? esc_html__( 'Connected to Google Calendar', 'wpcalibrate-calendar-sync' ) : esc_html__( 'Not Connected', 'wpcalibrate-calendar-sync' ); ?>
					<span class="wpcs-engine-tag"><?php echo esc_html( sprintf( __( 'Storage: %s', 'wpcalibrate-calendar-sync' ), strtoupper( $enc_engine ) ) ); ?></span>
				</div>
			<?php endif; ?>

			<form method="post" action="" class="wpcs-form">
				<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
				<input type="hidden" name="wpcs_action" value="save_credentials" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpcs_client_id"><?php esc_html_e( 'Google Client ID', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<input type="text" id="wpcs_client_id" name="client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpcs_client_secret"><?php esc_html_e( 'Google Client Secret', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<input type="password" id="wpcs_client_secret" name="client_secret" value="" placeholder="<?php echo $has_secret ? '••••••••••••••••' : ''; ?>" class="regular-text" autocomplete="new-password" />
							<p class="description">
								<?php
								if ( $has_secret ) {
									esc_html_e( 'A client secret is securely stored in encrypted format. Leave blank to keep existing secret.', 'wpcalibrate-calendar-sync' );
								} else {
									esc_html_e( 'Enter the Client Secret provided in your Google Cloud Console.', 'wpcalibrate-calendar-sync' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Authorized Redirect URI', 'wpcalibrate-calendar-sync' ); ?></th>
						<td>
							<div class="wpcs-copy-field">
								<input type="text" id="wpcs_redirect_uri" value="<?php echo esc_attr( $redirect_uri ); ?>" class="large-text code" readonly />
								<button type="button" class="button button-secondary wpcs-btn-copy" data-target="wpcs_redirect_uri"><?php esc_html_e( 'Copy URI', 'wpcalibrate-calendar-sync' ); ?></button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Copy this exact URI into your Google Cloud OAuth Client "Authorized redirect URIs" setting.', 'wpcalibrate-calendar-sync' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary" <?php disabled( ! $enc_available ); ?>>
						<?php esc_html_e( 'Save Credentials', 'wpcalibrate-calendar-sync' ); ?>
					</button>
				</p>
			</form>

			<?php if ( ! empty( $client_id ) && $has_secret && $enc_available ) : ?>
				<hr />
				<div class="wpcs-auth-actions">
					<?php if ( ! $is_connected ) : ?>
						<a href="<?php echo esc_url( $this->oauth_controller->create_auth_url( false ) ); ?>" class="button button-primary button-hero">
							<?php esc_html_e( 'Connect Google Account', 'wpcalibrate-calendar-sync' ); ?>
						</a>
					<?php else : ?>
						<form method="post" action="" style="display:inline-block;">
							<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
							<input type="hidden" name="wpcs_action" value="disconnect_google" />
							<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect Google Calendar? Your local synchronized events will be preserved.', 'wpcalibrate-calendar-sync' ) ); ?>');">
								<?php esc_html_e( 'Disconnect Google Account', 'wpcalibrate-calendar-sync' ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Demo Data Testing Card -->
		<div class="wpcs-card wpcs-demo-card">
			<div class="wpcs-demo-header">
				<span class="dashicons dashicons-welcome-widgets-menus wpcs-demo-card-icon"></span>
				<div class="wpcs-demo-title-group">
					<h3 class="wpcs-demo-card-title"><?php esc_html_e( 'Testing & Evaluation: One-Click Demo Data', 'wpcalibrate-calendar-sync' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Want to test the plugin without creating Google Cloud Platform credentials? Load a simulated Google OAuth 2.0 connection, 3 color-coded sample calendars, and 12 realistic events.', 'wpcalibrate-calendar-sync' ); ?>
					</p>
				</div>
			</div>

			<div class="wpcs-demo-features-grid">
				<div class="wpcs-demo-feature-item">
					<span class="dashicons dashicons-yes-alt wpcs-demo-check"></span>
					<div>
						<strong><?php esc_html_e( 'Simulated Google OAuth 2.0', 'wpcalibrate-calendar-sync' ); ?></strong>
						<p><?php esc_html_e( 'Unlocks all plugin tabs, calendars, and sync screens with mock encrypted tokens.', 'wpcalibrate-calendar-sync' ); ?></p>
					</div>
				</div>
				<div class="wpcs-demo-feature-item">
					<span class="dashicons dashicons-yes-alt wpcs-demo-check"></span>
					<div>
						<strong><?php esc_html_e( '3 Sample Calendars', 'wpcalibrate-calendar-sync' ); ?></strong>
						<p><?php esc_html_e( 'Company Events (blue), Engineering (green), and Consultations (purple).', 'wpcalibrate-calendar-sync' ); ?></p>
					</div>
				</div>
				<div class="wpcs-demo-feature-item">
					<span class="dashicons dashicons-yes-alt wpcs-demo-check"></span>
					<div>
						<strong><?php esc_html_e( '12 Dynamic Sample Events', 'wpcalibrate-calendar-sync' ); ?></strong>
						<p><?php esc_html_e( 'All-day and timed events relative to today for Agenda, Month, and Block testing.', 'wpcalibrate-calendar-sync' ); ?></p>
					</div>
				</div>
			</div>

			<div class="wpcs-demo-actions">
				<form method="post" action="" style="display:inline-block; margin:0;">
					<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
					<input type="hidden" name="wpcs_action" value="install_demo_data" />
					<button type="submit" class="button button-primary wpcs-btn-demo-install">
						<span class="dashicons dashicons-database-add" style="margin-top:3px; margin-right:4px;"></span>
						<?php echo $is_demo ? esc_html__( 'Re-install / Refresh Demo Data', 'wpcalibrate-calendar-sync' ) : esc_html__( 'Load Demo Connection & Sample Data', 'wpcalibrate-calendar-sync' ); ?>
					</button>
				</form>

				<?php if ( $is_demo ) : ?>
					<form method="post" action="" style="display:inline-block; margin:0;">
						<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
						<input type="hidden" name="wpcs_action" value="remove_demo_data" />
						<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove all demo calendars, events, and simulated OAuth credentials?', 'wpcalibrate-calendar-sync' ) ); ?>');">
							<span class="dashicons dashicons-trash" style="margin-top:3px; margin-right:4px;"></span>
							<?php esc_html_e( 'Remove Demo Data & Reset', 'wpcalibrate-calendar-sync' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Calendars Tab.
	 */
	private function render_calendars_tab(): void {
		$is_connected = $this->oauth_controller->is_connected();
		$calendars    = $this->sync_service->get_configured_calendars();

		if ( ! $is_connected ) {
			?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Please connect your Google Account in the Connection tab to discover and select calendars.', 'wpcalibrate-calendar-sync' ); ?></p>
			</div>
			<?php
			return;
		}

		// Option to refresh from Google API if clicked.
		if ( isset( $_GET['refresh_calendars'] ) && wp_verify_nonce( sanitize_text_field( (string) $_GET['_wpnonce'] ), 'wpcs_refresh_cals' ) ) {
			if ( $this->google_service ) {
				try {
					$remote_cals = $this->google_service->list_calendars();
					foreach ( $remote_cals as $rc ) {
						$cid = (string) $rc['id'];
						if ( ! isset( $calendars[ $cid ] ) ) {
							$calendars[ $cid ] = array(
								'id'        => $cid,
								'name'      => $rc['name'],
								'timezone'  => $rc['timezone'],
								'enabled'   => false,
								'direction' => 'two_way',
							);
						} else {
							$calendars[ $cid ]['name']     = $rc['name'];
							$calendars[ $cid ]['timezone'] = $rc['timezone'];
						}
					}
					$this->sync_service->save_configured_calendars( $calendars );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Calendar list refreshed from Google.', 'wpcalibrate-calendar-sync' ) . '</p></div>';
				} catch ( Exception $e ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(
						/* translators: %s: error */
						esc_html__( 'Failed to fetch calendars: %s', 'wpcalibrate-calendar-sync' ),
						esc_html( $e->getMessage() )
					) . '</p></div>';
				}
			}
		}
		?>
		<div class="wpcs-card">
			<div class="wpcs-card-header">
				<h2><?php esc_html_e( 'Configured Calendars', 'wpcalibrate-calendar-sync' ); ?></h2>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpcalibrate-calendar-sync&tab=calendars&refresh_calendars=1' ), 'wpcs_refresh_cals' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Refresh Calendar List', 'wpcalibrate-calendar-sync' ); ?>
				</a>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
				<input type="hidden" name="wpcs_action" value="save_calendars" />

				<?php if ( empty( $calendars ) ) : ?>
					<p><?php esc_html_e( 'No calendars discovered yet. Click "Refresh Calendar List" above to fetch your calendars.', 'wpcalibrate-calendar-sync' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 50px;"><?php esc_html_e( 'Sync', 'wpcalibrate-calendar-sync' ); ?></th>
								<th><?php esc_html_e( 'Calendar Name', 'wpcalibrate-calendar-sync' ); ?></th>
								<th><?php esc_html_e( 'Timezone', 'wpcalibrate-calendar-sync' ); ?></th>
								<th><?php esc_html_e( 'Sync Direction', 'wpcalibrate-calendar-sync' ); ?></th>
								<th><?php esc_html_e( 'Last Sync', 'wpcalibrate-calendar-sync' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wpcalibrate-calendar-sync' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $calendars as $cid => $cal ) : ?>
								<tr>
									<td>
										<input type="checkbox" name="calendars[<?php echo esc_attr( $cid ); ?>][enabled]" value="1" <?php checked( ! empty( $cal['enabled'] ) ); ?> />
										<input type="hidden" name="calendars[<?php echo esc_attr( $cid ); ?>][name]" value="<?php echo esc_attr( $cal['name'] ?? '' ); ?>" />
										<input type="hidden" name="calendars[<?php echo esc_attr( $cid ); ?>][timezone]" value="<?php echo esc_attr( $cal['timezone'] ?? 'UTC' ); ?>" />
									</td>
									<td>
										<strong><?php echo esc_html( $cal['name'] ?? $cid ); ?></strong>
										<br /><small class="text-muted"><?php echo esc_html( $cid ); ?></small>
									</td>
									<td><?php echo esc_html( $cal['timezone'] ?? 'UTC' ); ?></td>
									<td>
										<select name="calendars[<?php echo esc_attr( $cid ); ?>][direction]">
											<option value="two_way" <?php selected( ( $cal['direction'] ?? '' ) === 'two_way' ); ?>><?php esc_html_e( 'Two-way (Sync Both)', 'wpcalibrate-calendar-sync' ); ?></option>
											<option value="google_to_wp" <?php selected( ( $cal['direction'] ?? '' ) === 'google_to_wp' ); ?>><?php esc_html_e( 'Google to WordPress (Read-only)', 'wpcalibrate-calendar-sync' ); ?></option>
											<option value="wp_to_google" <?php selected( ( $cal['direction'] ?? '' ) === 'wp_to_google' ); ?>><?php esc_html_e( 'WordPress to Google (Push-only)', 'wpcalibrate-calendar-sync' ); ?></option>
										</select>
									</td>
									<td>
										<?php echo ! empty( $cal['last_sync_utc'] ) ? esc_html( $cal['last_sync_utc'] . ' UTC' ) : esc_html__( 'Never', 'wpcalibrate-calendar-sync' ); ?>
									</td>
									<td>
										<?php if ( ! empty( $cal['last_error'] ) ) : ?>
											<span class="wpcs-badge wpcs-badge-error" title="<?php echo esc_attr( $cal['last_error'] ); ?>"><?php esc_html_e( 'Error', 'wpcalibrate-calendar-sync' ); ?></span>
										<?php elseif ( ! empty( $cal['enabled'] ) ) : ?>
											<span class="wpcs-badge wpcs-badge-success"><?php esc_html_e( 'Active', 'wpcalibrate-calendar-sync' ); ?></span>
										<?php else : ?>
											<span class="wpcs-badge wpcs-badge-muted"><?php esc_html_e( 'Disabled', 'wpcalibrate-calendar-sync' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Calendar Settings', 'wpcalibrate-calendar-sync' ); ?></button>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Synchronization Tab.
	 */
	private function render_synchronization_tab(): void {
		$interval   = $this->scheduler->get_interval();
		$cron_next  = wp_next_scheduled( RecoveryScheduler::HOOK_NAME );
		$is_webhook = PushNotificationManager::is_webhook_eligible();
		$webhook_url= PushNotificationManager::get_webhook_url();
		$calendars  = $this->sync_service->get_configured_calendars();
		?>
		<div class="wpcs-card">
			<h2><?php esc_html_e( 'Synchronization Controls & Schedule', 'wpcalibrate-calendar-sync' ); ?></h2>

			<div class="wpcs-sync-grid">
				<div class="wpcs-sync-box">
					<h3><?php esc_html_e( 'Manual Synchronization', 'wpcalibrate-calendar-sync' ); ?></h3>
					<p><?php esc_html_e( 'Trigger an immediate on-demand synchronization for your active calendars.', 'wpcalibrate-calendar-sync' ); ?></p>
					<form method="post" action="" style="display:inline-block; margin-right: 12px;">
						<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
						<input type="hidden" name="wpcs_action" value="sync_now" />
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Sync All Enabled Now', 'wpcalibrate-calendar-sync' ); ?></button>
					</form>
					<form method="post" action="" style="display:inline-block;">
						<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
						<input type="hidden" name="wpcs_action" value="full_resync_all" />
						<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Trigger full resync? This resets sync tokens and verifies all events.', 'wpcalibrate-calendar-sync' ) ); ?>');">
							<?php esc_html_e( 'Controlled Full Resync', 'wpcalibrate-calendar-sync' ); ?>
						</button>
					</form>
				</div>

				<div class="wpcs-sync-box">
					<h3><?php esc_html_e( 'Google Push Notifications', 'wpcalibrate-calendar-sync' ); ?></h3>
					<?php if ( $is_webhook ) : ?>
						<p><span class="wpcs-badge wpcs-badge-success"><?php esc_html_e( 'Supported (HTTPS active)', 'wpcalibrate-calendar-sync' ); ?></span></p>
						<p class="description"><?php echo esc_html( sprintf( __( 'Webhook URL: %s', 'wpcalibrate-calendar-sync' ), $webhook_url ) ); ?></p>
					<?php else : ?>
						<p><span class="wpcs-badge wpcs-badge-muted"><?php esc_html_e( 'WP-Cron fallback (HTTPS or public site required for push)', 'wpcalibrate-calendar-sync' ); ?></span></p>
					<?php endif; ?>
				</div>
			</div>

			<hr />

			<form method="post" action="">
				<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
				<input type="hidden" name="wpcs_action" value="save_sync_settings" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpcs_interval"><?php esc_html_e( 'WP-Cron Recovery Interval', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<select id="wpcs_interval" name="recovery_interval">
								<option value="5" <?php selected( 5 === $interval ); ?>><?php esc_html_e( 'Every 5 Minutes', 'wpcalibrate-calendar-sync' ); ?></option>
								<option value="15" <?php selected( 15 === $interval ); ?>><?php esc_html_e( 'Every 15 Minutes (Default)', 'wpcalibrate-calendar-sync' ); ?></option>
								<option value="30" <?php selected( 30 === $interval ); ?>><?php esc_html_e( 'Every 30 Minutes', 'wpcalibrate-calendar-sync' ); ?></option>
								<option value="60" <?php selected( 60 === $interval ); ?>><?php esc_html_e( 'Hourly', 'wpcalibrate-calendar-sync' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Next scheduled recovery run:', 'wpcalibrate-calendar-sync' ); ?>
								<strong><?php echo $cron_next ? esc_html( gmdate( 'Y-m-d H:i:s', $cron_next ) . ' UTC' ) : esc_html__( 'Not scheduled', 'wpcalibrate-calendar-sync' ); ?></strong>
							</p>
							<div class="notice notice-info inline" style="margin-top: 10px;">
								<p>
									<strong><?php esc_html_e( 'Note on WP-Cron:', 'wpcalibrate-calendar-sync' ); ?></strong>
									<?php esc_html_e( 'WordPress built-in cron relies on site visitor traffic to trigger executions. On low-traffic sites, consider setting up a real server cron job to execute wp-cron.php for reliable scheduled synchronization.', 'wpcalibrate-calendar-sync' ); ?>
								</p>
							</div>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Schedule Settings', 'wpcalibrate-calendar-sync' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Display Tab.
	 */
	private function render_display_tab(): void {
		$display_settings = get_option( self::OPTION_DISPLAY, array() );
		$default_view     = $display_settings['default_view'] ?? 'agenda';
		$limit            = (int) ( $display_settings['limit'] ?? 20 );
		$show_description = ! empty( $display_settings['show_description'] );
		$show_location    = ! empty( $display_settings['show_location'] );
		$delete_on_uninst = ! empty( get_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, false ) );
		?>
		<div class="wpcs-card">
			<h2><?php esc_html_e( 'Frontend Display & Data Retention Defaults', 'wpcalibrate-calendar-sync' ); ?></h2>

			<form method="post" action="">
				<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
				<input type="hidden" name="wpcs_action" value="save_display_settings" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpcs_default_view"><?php esc_html_e( 'Default Calendar View', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<select id="wpcs_default_view" name="default_view">
								<option value="agenda" <?php selected( 'agenda' === $default_view ); ?>><?php esc_html_e( 'Agenda (List View)', 'wpcalibrate-calendar-sync' ); ?></option>
								<option value="month" <?php selected( 'month' === $default_view ); ?>><?php esc_html_e( 'Month Grid View', 'wpcalibrate-calendar-sync' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpcs_limit"><?php esc_html_e( 'Events per Agenda View', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<input type="number" id="wpcs_limit" name="limit" value="<?php echo esc_attr( (string) $limit ); ?>" min="1" max="250" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Information Visibility', 'wpcalibrate-calendar-sync' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="show_description" value="1" <?php checked( $show_description ); ?> />
									<?php esc_html_e( 'Show event description', 'wpcalibrate-calendar-sync' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="show_location" value="1" <?php checked( $show_location ); ?> />
									<?php esc_html_e( 'Show event location', 'wpcalibrate-calendar-sync' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shortcode Usage', 'wpcalibrate-calendar-sync' ); ?></th>
						<td>
							<code>[wpcalibrate_calendar]</code>
							<p class="description">
								<?php esc_html_e( 'Usable anywhere in content, Gutenberg, Elementor, or page builders. Attributes: view="agenda|month", limit="20", show_description="1", show_location="1".', 'wpcalibrate-calendar-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Data Retention on Uninstall', 'wpcalibrate-calendar-sync' ); ?></th>
						<td>
							<label style="color: #b91c1c; font-weight: 600;">
								<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $delete_on_uninst ); ?> />
								<?php esc_html_e( 'Delete all plugin data when uninstalled', 'wpcalibrate-calendar-sync' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'If checked, deleting the plugin via WordPress Plugins screen will drop the events table and remove all credentials, logs, and settings. Leave unchecked to preserve synchronized events.', 'wpcalibrate-calendar-sync' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wpcalibrate-calendar-sync' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Diagnostics Tab.
	 */
	private function render_diagnostics_tab(): void {
		$report = $this->diagnostics->get_diagnostics_report();
		$json   = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		?>
		<div class="wpcs-card">
			<div class="wpcs-card-header">
				<h2><?php esc_html_e( 'System Health & Diagnostics', 'wpcalibrate-calendar-sync' ); ?></h2>
				<button type="button" class="button button-secondary wpcs-btn-copy" data-target="wpcs_diagnostics_text">
					<?php esc_html_e( 'Copy Diagnostics (Sanitized)', 'wpcalibrate-calendar-sync' ); ?>
				</button>
			</div>

			<p class="description">
				<?php esc_html_e( 'All sensitive credentials (passwords, tokens, client secrets, license keys) are automatically redacted from this output.', 'wpcalibrate-calendar-sync' ); ?>
			</p>

			<textarea id="wpcs_diagnostics_text" class="large-text code" rows="18" readonly><?php echo esc_textarea( (string) $json ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * License Tab.
	 */
	private function render_license_tab(): void {
		$data       = $this->license_manager->get_license_data();
		$masked_key = $this->license_manager->get_masked_key();
		?>
		<div class="wpcs-card">
			<h2><?php esc_html_e( 'WPCalibrate Plugin License', 'wpcalibrate-calendar-sync' ); ?></h2>

			<div class="notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'Integration Status:', 'wpcalibrate-calendar-sync' ); ?></strong>
					<?php echo esc_html( $data['message'] ); ?>
				</p>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field( 'wpcs_settings_action', 'wpcs_nonce' ); ?>
				<input type="hidden" name="wpcs_action" value="save_license" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpcs_license_key"><?php esc_html_e( 'License Key', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<input type="text" id="wpcs_license_key" name="license_key" value="" placeholder="<?php echo esc_attr( $masked_key ?: 'XXXX-XXXX-XXXX-XXXX' ); ?>" class="regular-text" />
							<p class="description">
								<?php
								if ( ! empty( $masked_key ) ) {
									echo esc_html( sprintf( __( 'Current key: %s (Masked)', 'wpcalibrate-calendar-sync' ), $masked_key ) );
								} else {
									esc_html_e( 'Enter your WPCalibrate purchase license key.', 'wpcalibrate-calendar-sync' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'wpcalibrate-calendar-sync' ); ?></th>
						<td>
							<span class="wpcs-badge wpcs-badge-muted"><?php echo esc_html( strtoupper( str_replace( '_', ' ', $data['status'] ) ) ); ?></span>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Key', 'wpcalibrate-calendar-sync' ); ?></button>
					<button type="submit" name="wpcs_action" value="activate_license" class="button button-secondary"><?php esc_html_e( 'Activate License', 'wpcalibrate-calendar-sync' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Support Tab.
	 */
	private function render_support_tab(): void {
		?>
		<div class="wpcs-card">
			<h2><?php esc_html_e( 'Official WPCalibrate Support', 'wpcalibrate-calendar-sync' ); ?></h2>
			<p><?php esc_html_e( 'Need help configuring your Google Cloud OAuth credentials or customizing your calendar synchronization? Reach our team through any of the verified channels below.', 'wpcalibrate-calendar-sync' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Email Support', 'wpcalibrate-calendar-sync' ); ?></th>
					<td><a href="mailto:support@wpcalibrate.com">support@wpcalibrate.com</a></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Official Website', 'wpcalibrate-calendar-sync' ); ?></th>
					<td><a href="https://wpcalibrate.com" target="_blank" rel="noopener noreferrer">https://wpcalibrate.com</a></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Marketplace & Add-ons', 'wpcalibrate-calendar-sync' ); ?></th>
					<td><a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer">https://marketplace.wpcalibrate.com/</a></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Phone / WhatsApp', 'wpcalibrate-calendar-sync' ); ?></th>
					<td><a href="tel:+447474795976">+447474795976</a></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Get DemoDataManager instance.
	 *
	 * @return DemoDataManager|null
	 */
	public function get_demo_manager(): ?DemoDataManager {
		return $this->demo_manager;
	}
}
