<?php
/**
 * Shortcode Handler for [wpcalibrate_calendar].
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcode
 */
class Shortcode {

	/**
	 * Shortcode tag name.
	 */
	public const TAG = 'wpcalibrate_calendar';

	/**
	 * Calendar renderer.
	 */
	private CalendarRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param CalendarRenderer $renderer Calendar renderer.
	 */
	public function __construct( CalendarRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register shortcode with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render shortcode callback.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_shortcode( $atts ): string {
		$atts_array = is_array( $atts ) ? $atts : array();
		return $this->renderer->render( $atts_array );
	}
}
