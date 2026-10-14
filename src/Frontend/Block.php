<?php
/**
 * Gutenberg Block Registration.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block
 */
class Block {

	/**
	 * Block namespace/name.
	 */
	public const BLOCK_NAME = 'wpcalibrate/calendar';

	/**
	 * Calendar renderer.
	 */
	private CalendarRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param CalendarRenderer $renderer Renderer service.
	 */
	public function __construct( CalendarRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register the dynamic block.
	 *
	 * @return void
	 */
	public function register(): void {
		$block_dir = dirname( __DIR__, 2 ) . '/blocks/calendar';

		if ( function_exists( 'register_block_type' ) ) {
			register_block_type(
				$block_dir,
				array(
					'render_callback' => array( $this, 'render_block' ),
				)
			);
		}
	}

	/**
	 * Block render callback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block inner content.
	 * @return string Rendered calendar HTML.
	 */
	public function render_block( array $attributes, string $content = '' ): string {
		return $this->renderer->render( $attributes );
	}
}
