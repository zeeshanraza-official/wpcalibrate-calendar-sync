<?php
/**
 * Plugin Overview Tab: Purpose, How to Use, and Methods.
 *
 * @package WPCalibrate\CalendarSync
 */

declare( strict_types=1 );

namespace WPCalibrate\CalendarSync\Admin;

use WPCalibrate\CalendarSync\Auth\OAuthController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Overview
 */
class Overview {

	/**
	 * Render the Overview tab content.
	 *
	 * @return void
	 */
	public static function render(): void {
		$dark_icon_url      = plugin_dir_url( dirname( __DIR__ ) ) . 'assets/branding/icon-dark.png';
		$connection_tab_url = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'connection' ), admin_url( 'admin.php' ) );
		$calendars_tab_url  = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'calendars' ), admin_url( 'admin.php' ) );
		$sync_tab_url       = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'synchronization' ), admin_url( 'admin.php' ) );
		$display_tab_url    = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'display' ), admin_url( 'admin.php' ) );
		$events_url         = admin_url( 'admin.php?page=' . Menu::EVENTS_PAGE_SLUG );
		$diagnostics_url    = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'diagnostics' ), admin_url( 'admin.php' ) );

		$connection_data    = get_option( OAuthController::OPTION_CONNECTION, array() );
		$is_demo            = is_array( $connection_data ) && ! empty( $connection_data['is_demo'] );
		?>
		<div class="wpcs-overview-container">
			<!-- Welcome Header Banner -->
			<div class="wpcs-overview-banner">
				<div class="wpcs-overview-logo">
					<img src="<?php echo esc_url( $dark_icon_url ); ?>" alt="WPCalibrate" />
				</div>
				<div class="wpcs-overview-header-text">
					<h2><?php esc_html_e( 'Welcome to WPCalibrate Calendar Sync', 'wpcalibrate-calendar-sync' ); ?></h2>
					<p class="wpcs-overview-lead">
						<?php esc_html_e( 'Enterprise-grade, two-way Google Calendar synchronization for WordPress. Store events locally for lightning-fast frontend delivery, protect OAuth credentials with authenticated encryption, and automate syncing via push webhooks and WP-Cron recovery.', 'wpcalibrate-calendar-sync' ); ?>
					</p>
					<div class="wpcs-badge-group">
						<?php if ( $is_demo ) : ?>
							<span class="wpcs-badge wpcs-badge-warning" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a;">
								<?php esc_html_e( 'Demo Mode Active (3 Calendars, 12 Sample Events)', 'wpcalibrate-calendar-sync' ); ?>
							</span>
						<?php endif; ?>
						<span class="wpcs-badge wpcs-badge-success"><?php esc_html_e( 'WordPress 6.0+ / 7.1+ Ready', 'wpcalibrate-calendar-sync' ); ?></span>
						<span class="wpcs-badge wpcs-badge-success"><?php esc_html_e( 'PHP 8.2 / 8.3 Strict', 'wpcalibrate-calendar-sync' ); ?></span>
						<span class="wpcs-badge wpcs-badge-info"><?php esc_html_e( 'Sodium / OpenSSL AES-256-GCM', 'wpcalibrate-calendar-sync' ); ?></span>
						<span class="wpcs-badge wpcs-badge-warning"><?php esc_html_e( 'Google Calendar API v3', 'wpcalibrate-calendar-sync' ); ?></span>
						<span class="wpcs-badge wpcs-badge-info"><?php esc_html_e( 'Zero Visitor API Latency', 'wpcalibrate-calendar-sync' ); ?></span>
						<span class="wpcs-badge wpcs-badge-success"><?php esc_html_e( 'Gutenberg & Shortcode Ready', 'wpcalibrate-calendar-sync' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Quick Actions Bar -->
			<div class="wpcs-quick-actions-bar">
				<a href="<?php echo esc_url( $connection_tab_url ); ?>" class="button button-primary">
					<span class="dashicons dashicons-admin-network" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php echo $is_demo ? esc_html__( 'Google Connection (Demo Active)', 'wpcalibrate-calendar-sync' ) : esc_html__( 'Connect Google Account', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( $calendars_tab_url ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-calendar" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Manage Calendars', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( $sync_tab_url ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Synchronization Settings', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( $display_tab_url ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Display Options', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( $events_url ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-list-view" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Calendar Events', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( $diagnostics_url ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-clipboard" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Diagnostics & Logs', 'wpcalibrate-calendar-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( $connection_tab_url ); ?>" class="button button-secondary" style="<?php echo $is_demo ? 'border-color: #a78bfa; color: #5b21b6; background: #f5f3ff;' : 'border-color: #93c5fd; color: #1e40af; background: #eff6ff;'; ?>">
					<span class="dashicons dashicons-database" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php echo $is_demo ? esc_html__( 'Demo Data Settings', 'wpcalibrate-calendar-sync' ) : esc_html__( 'Load Demo Data', 'wpcalibrate-calendar-sync' ); ?>
				</a>
			</div>

			<!-- Section 1: Purpose & Architecture -->
			<div class="wpcs-overview-card" style="margin-top: 24px;">
				<h3>
					<span class="dashicons dashicons-info" style="color: #0284c7; vertical-align: middle; margin-right: 8px;"></span>
					<?php esc_html_e( 'Plugin Purpose & Architecture', 'wpcalibrate-calendar-sync' ); ?>
				</h3>
				<p>
					<?php esc_html_e( 'Traditional WordPress calendar plugins frequently make live API requests to Google Calendar on public page loads. This architecture severely degrades site performance, consumes external API quotas, and causes website timeouts or fatal errors if Google or network connections experience latency.', 'wpcalibrate-calendar-sync' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'WPCalibrate Calendar Sync decouples API synchronization completely from visitor presentation through a dedicated, indexed database cache ({$wpdb->prefix}wpcs_events). When visitors browse your site, events are rendered directly from the local database in milliseconds with zero external API calls. Background sync operates deterministically through Google push webhooks, admin actions, and scheduled WP-Cron recovery.', 'wpcalibrate-calendar-sync' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'Security is engineered at the core: client secrets and refresh tokens are stored using authenticated encryption (Sodium crypto_secretbox or OpenSSL AES-256-GCM) with salt-derived keys. Synchronization concurrency is strictly guarded with transient-based mutex locks to prevent race conditions during parallel requests.', 'wpcalibrate-calendar-sync' ); ?>
				</p>
			</div>

			<!-- Section 2: How to Use (Step by Step) -->
			<div class="wpcs-overview-card" style="margin-top: 20px;">
				<h3>
					<span class="dashicons dashicons-clipboard" style="color: #0284c7; vertical-align: middle; margin-right: 8px;"></span>
					<?php esc_html_e( 'How to Use WPCalibrate Calendar Sync (Step-by-Step)', 'wpcalibrate-calendar-sync' ); ?>
				</h3>
				<div class="wpcs-steps-grid">
					<div class="wpcs-step-item">
						<div class="wpcs-step-number">1</div>
						<div class="wpcs-step-content">
							<h4><?php esc_html_e( 'Set Up Google Cloud Platform (GCP)', 'wpcalibrate-calendar-sync' ); ?></h4>
							<p>
								<?php esc_html_e( 'Open Google Cloud Console, create or select a project, and enable the Google Calendar API. Configure the OAuth Consent Screen (External or Internal) and add your email under test users if required.', 'wpcalibrate-calendar-sync' ); ?>
							</p>
						</div>
					</div>

					<div class="wpcs-step-item">
						<div class="wpcs-step-number">2</div>
						<div class="wpcs-step-content">
							<h4><?php esc_html_e( 'Create OAuth 2.0 Web Client Credentials', 'wpcalibrate-calendar-sync' ); ?></h4>
							<p>
								<?php
								printf(
									/* translators: %s: Link to Connection tab */
									esc_html__( 'Under GCP Credentials, create an OAuth Client ID for Web Application. Copy the Authorized Redirect URI provided in %s and paste it into GCP.', 'wpcalibrate-calendar-sync' ),
									'<a href="' . esc_url( $connection_tab_url ) . '"><strong>' . esc_html__( 'Connection Settings', 'wpcalibrate-calendar-sync' ) . '</strong></a>'
								);
								?>
							</p>
						</div>
					</div>

					<div class="wpcs-step-item">
						<div class="wpcs-step-number">3</div>
						<div class="wpcs-step-content">
							<h4><?php esc_html_e( 'Connect Google Account', 'wpcalibrate-calendar-sync' ); ?></h4>
							<p>
								<?php esc_html_e( 'Enter your Client ID and Client Secret in the Connection tab and click Save Credentials. Then click "Connect Google Account" to grant calendar access via Google OAuth 2.0.', 'wpcalibrate-calendar-sync' ); ?>
							</p>
						</div>
					</div>

					<div class="wpcs-step-item">
						<div class="wpcs-step-number">4</div>
						<div class="wpcs-step-content">
							<h4><?php esc_html_e( 'Select Calendars & Direction', 'wpcalibrate-calendar-sync' ); ?></h4>
							<p>
								<?php
								printf(
									/* translators: %s: Link to Calendars tab */
									esc_html__( 'Go to %s to select which Google Calendars to sync. Set the synchronization direction: Two-way, Google to WordPress (read-only), or WordPress to Google.', 'wpcalibrate-calendar-sync' ),
									'<a href="' . esc_url( $calendars_tab_url ) . '"><strong>' . esc_html__( 'Calendars Tab', 'wpcalibrate-calendar-sync' ) . '</strong></a>'
								);
								?>
							</p>
						</div>
					</div>

					<div class="wpcs-step-item">
						<div class="wpcs-step-number">5</div>
						<div class="wpcs-step-content">
							<h4><?php esc_html_e( 'Run Initial Sync & Enable Automation', 'wpcalibrate-calendar-sync' ); ?></h4>
							<p>
								<?php
								printf(
									/* translators: %s: Link to Sync tab */
									esc_html__( 'In %s, click "Sync All Calendars Now" to populate your local database. Scheduled 15-minute WP-Cron recovery and real-time push webhook notifications handle all ongoing changes.', 'wpcalibrate-calendar-sync' ),
									'<a href="' . esc_url( $sync_tab_url ) . '"><strong>' . esc_html__( 'Synchronization Tab', 'wpcalibrate-calendar-sync' ) . '</strong></a>'
								);
								?>
							</p>
						</div>
					</div>

					<div class="wpcs-step-item">
						<div class="wpcs-step-number">6</div>
						<div class="wpcs-step-content">
							<h4><?php esc_html_e( 'Display Calendars on Frontend', 'wpcalibrate-calendar-sync' ); ?></h4>
							<p>
								<?php esc_html_e( 'Embed the Gutenberg block "WPCalibrate Calendar" in the block editor, or use shortcode [wpcalibrate_calendar view="agenda"] or [wpcalibrate_calendar view="month"] in any page builder or template.', 'wpcalibrate-calendar-sync' ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>

			<!-- Section 3: Core Methods & Architectural Flow -->
			<div class="wpcs-overview-card" style="margin-top: 20px;">
				<h3>
					<span class="dashicons dashicons-admin-settings" style="color: #0284c7; vertical-align: middle; margin-right: 8px;"></span>
					<?php esc_html_e( 'Core Methods & Architectural Data Flow', 'wpcalibrate-calendar-sync' ); ?>
				</h3>
				<p>
					<?php esc_html_e( 'The following table documents the core subsystems, primary classes, and methods governing synchronization, security, caching, and frontend presentation:', 'wpcalibrate-calendar-sync' ); ?>
				</p>
				<div class="wpcs-methods-table-wrapper">
					<table class="widefat striped" style="border: 1px solid #e2e8f0;">
						<thead>
							<tr>
								<th style="width: 22%; font-weight: 700;"><?php esc_html_e( 'Subsystem & Class', 'wpcalibrate-calendar-sync' ); ?></th>
								<th style="width: 33%; font-weight: 700;"><?php esc_html_e( 'Key Methods', 'wpcalibrate-calendar-sync' ); ?></th>
								<th style="width: 45%; font-weight: 700;"><?php esc_html_e( 'Architectural Role & Description', 'wpcalibrate-calendar-sync' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>
									<code>Auth\OAuthController</code>
								</td>
								<td>
									<code>generate_auth_url()</code><br />
									<code>handle_callback()</code><br />
									<code>disconnect()</code>
								</td>
								<td>
									<?php esc_html_e( 'Coordinates Google OAuth 2.0 web-server authorization flow, anti-CSRF state token validation, token exchange, and token revocation on disconnect.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Auth\SecretStorage</code>
								</td>
								<td>
									<code>encrypt()</code><br />
									<code>decrypt()</code><br />
									<code>get_secret()</code><br />
									<code>set_secret()</code>
								</td>
								<td>
									<?php esc_html_e( 'Authenticated symmetric encryption for refresh tokens and secrets using Sodium (crypto_secretbox) or OpenSSL AES-256-GCM. Autoload disabled in wp_options.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Sync\SyncService</code>
								</td>
								<td>
									<code>sync_calendar()</code><br />
									<code>sync_all()</code><br />
									<code>push_event_to_google()</code>
								</td>
								<td>
									<?php esc_html_e( 'Central synchronization engine. Executes initial full sync, delta incremental sync using nextSyncToken, automatic HTTP 410 invalid-token recovery, and WordPress-to-Google event dispatching.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Sync\SyncLock</code>
								</td>
								<td>
									<code>acquire()</code><br />
									<code>release()</code><br />
									<code>is_locked()</code>
								</td>
								<td>
									<?php esc_html_e( 'Transient-based concurrency mutex (300s TTL per calendar) preventing race conditions from concurrent cron, webhook, or admin triggers.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Api\GoogleCalendarService</code>
								</td>
								<td>
									<code>list_calendars()</code><br />
									<code>list_events_incremental()</code><br />
									<code>insert_event()</code><br />
									<code>delete_event()</code>
								</td>
								<td>
									<?php esc_html_e( 'Direct API client abstraction wrapping Google Calendar API v3 service. Manages pagination, checksum calculation, and Google API exception normalizations.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Sync\PushNotificationManager</code>
								</td>
								<td>
									<code>register_channel()</code><br />
									<code>stop_channel()</code><br />
									<code>renew_expiring_channels()</code>
								</td>
								<td>
									<?php esc_html_e( 'Establishes Google push notification watch channels on HTTPS sites. Automatically verifies and renews channels within 24 hours of expiration.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Http\WebhookController</code>
								</td>
								<td>
									<code>handle_webhook()</code>
								</td>
								<td>
									<?php esc_html_e( 'REST endpoint (/wpcalibrate/v1/webhook) validating X-Goog-Channel-ID, X-Goog-Channel-Token, and X-Goog-Resource-ID timing-safely via hash_equals before triggering background sync.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Events\EventRepository</code>
								</td>
								<td>
									<code>upsert_from_google()</code><br />
									<code>get_events()</code><br />
									<code>delete_by_google_id()</code><br />
									<code>compute_checksum()</code>
								</td>
								<td>
									<?php esc_html_e( 'Manages persistent event records in custom table {$wpdb->prefix}wpcs_events. Handles UTC normalization, all-day boundary protection, and delta change detection.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Frontend\CalendarRenderer</code>
								</td>
								<td>
									<code>render_agenda()</code><br />
									<code>render_month()</code><br />
									<code>render_shortcode()</code>
								</td>
								<td>
									<?php esc_html_e( 'Renders accessible (WCAG 2.2 AA), responsive Agenda list and Month grid calendar views exclusively from local database cache with zero external API calls.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Sync\RecoveryScheduler</code>
								</td>
								<td>
									<code>run_recovery_sync()</code><br />
									<code>schedule_cron()</code>
								</td>
								<td>
									<?php esc_html_e( 'Schedules recurring WP-Cron event (wpcs_recovery_sync_cron every 15 minutes) to ensure offline resilience and renew push webhook channels.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
							<tr>
								<td>
									<code>Support\DemoDataManager</code>
								</td>
								<td>
									<code>install_demo_data()</code><br />
									<code>remove_demo_data()</code><br />
									<code>is_demo_active()</code>
								</td>
								<td>
									<?php esc_html_e( 'One-click evaluation test suite: installs a simulated Google OAuth 2.0 connection, 3 color-coded demo calendars, and 12 dynamic relative-date events for zero-friction testing without GCP credentials.', 'wpcalibrate-calendar-sync' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}
}
