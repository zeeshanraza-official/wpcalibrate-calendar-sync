<?php
/**
 * Admin Menu Management and WPCalibrate Top-Level Menu Isolation.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Admin;

use WPCalibrate\CalendarSync\Support\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu
 */
class Menu {

	/**
	 * Top-level WPCalibrate menu slug.
	 */
	public const PARENT_SLUG = 'wpcalibrate';

	/**
	 * Calendar Sync main page slug.
	 */
	public const PAGE_SLUG = 'wpcalibrate-calendar-sync';

	/**
	 * Events management page slug.
	 */
	public const EVENTS_PAGE_SLUG = 'wpcalibrate-calendar-events';

	/**
	 * Settings page controller.
	 */
	private SettingsPage $settings_page;

	/**
	 * Events page controller.
	 */
	private EventsPage $events_page;

	/**
	 * Constructor.
	 *
	 * @param SettingsPage $settings_page Settings page controller.
	 * @param EventsPage   $events_page   Events page controller.
	 */
	public function __construct( SettingsPage $settings_page, EventsPage $events_page ) {
		$this->settings_page = $settings_page;
		$this->events_page   = $events_page;
	}

	/**
	 * Register admin menu hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Run at priority 99 to ensure parent menu detection and coexistence with other WPCalibrate plugins.
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_head', array( $this, 'render_admin_menu_styles' ) );
	}

	/**
	 * Output inline CSS in admin_head to strictly constrain the WPCalibrate admin menu icon globally.
	 *
	 * Constrains icon to 20x20px with proper padding on all WordPress admin pages,
	 * preventing oversized icon overflow on screens where plugin admin.css is not enqueued.
	 *
	 * @return void
	 */
	public function render_admin_menu_styles(): void {
		$icon_dark_url = esc_url( plugin_dir_url( dirname( __DIR__ ) ) . 'assets/branding/icon-dark.png' );
		?>
		<style id="wpcalibrate-menu-icon-style">
			#adminmenu #toplevel_page_wpcalibrate .wp-menu-image img,
			#adminmenu a.toplevel_page_wpcalibrate .wp-menu-image img,
			#adminmenu a[href*="page=wpcalibrate"] .wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
				max-width: 20px !important;
				max-height: 20px !important;
				padding-top: 7px !important;
				object-fit: contain !important;
				box-sizing: content-box !important;
				opacity: 0.8;
				transition: opacity 0.15s ease-in-out;
			}
			#adminmenu #toplevel_page_wpcalibrate:hover .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate.current .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate.wp-menu-open .wp-menu-image img {
				opacity: 1 !important;
			}
			body.admin-color-light #adminmenu #toplevel_page_wpcalibrate .wp-menu-image img,
			body.admin-color-light #adminmenu a.toplevel_page_wpcalibrate .wp-menu-image img,
			body.admin-color-light #adminmenu a[href*="page=wpcalibrate"] .wp-menu-image img {
				content: url('<?php echo $icon_dark_url; ?>') !important;
			}
		</style>
		<?php
	}

	/**
	 * Safely register top-level WPCalibrate menu if missing, and add plugin submenus.
	 *
	 * @return void
	 */
	public function register_admin_menus(): void {
		if ( ! Capabilities::can_manage() ) {
			return;
		}

		global $menu;

		// Safely check if a WPCalibrate parent menu was already registered by another plugin.
		$parent_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && self::PARENT_SLUG === $item[2] ) {
					$parent_exists = true;
					break;
				}
			}
		}

		$icon_white_url = plugin_dir_url( dirname( __DIR__ ) ) . 'assets/branding/icon-white.png';

		if ( ! $parent_exists ) {
			add_menu_page(
				'WPCalibrate',
				'WPCalibrate',
				Capabilities::MANAGE_CALENDAR,
				self::PARENT_SLUG,
				array( $this->settings_page, 'render' ),
				$icon_white_url,
				59
			);
		}

		// Register isolated Calendar Sync submenu.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Calendar Sync', 'wpcalibrate-calendar-sync' ),
			__( 'Calendar Sync', 'wpcalibrate-calendar-sync' ),
			Capabilities::MANAGE_CALENDAR,
			self::PAGE_SLUG,
			array( $this->settings_page, 'render' )
		);

		// Register Calendar Events submenu.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Calendar Events', 'wpcalibrate-calendar-sync' ),
			__( 'Calendar Events', 'wpcalibrate-calendar-sync' ),
			Capabilities::MANAGE_CALENDAR,
			self::EVENTS_PAGE_SLUG,
			array( $this->events_page, 'render' )
		);
	}

	/**
	 * Enqueue admin scripts & styles strictly on this plugin's screens.
	 *
	 * @param string $hook Admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load assets on our specific pages.
		if ( ! str_contains( $hook, self::PAGE_SLUG ) && ! str_contains( $hook, self::EVENTS_PAGE_SLUG ) ) {
			return;
		}

		$base_url = plugin_dir_url( dirname( __DIR__ ) );

		wp_enqueue_style(
			'wpcs-admin-css',
			$base_url . 'assets/admin/css/admin.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'wpcs-admin-js',
			$base_url . 'assets/admin/js/admin.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'wpcs-admin-js',
			'wpcsAdminData',
			array(
				'nonce'   => wp_create_nonce( 'wpcs_admin_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'copied'       => __( 'Copied to clipboard!', 'wpcalibrate-calendar-sync' ),
					'copyFailed'   => __( 'Failed to copy.', 'wpcalibrate-calendar-sync' ),
					'syncing'      => __( 'Synchronizing...', 'wpcalibrate-calendar-sync' ),
					'confirmResync'=> __( 'Are you sure you want to trigger a full resync? This will verify all events from Google.', 'wpcalibrate-calendar-sync' ),
				),
			)
		);
	}
}
