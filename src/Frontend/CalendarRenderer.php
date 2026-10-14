<?php
/**
 * Frontend Calendar Renderer for Shortcodes and Gutenberg Blocks.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Frontend;

use DateTimeImmutable;
use Exception;
use WPCalibrate\CalendarSync\Events\EventRepository;
use WPCalibrate\CalendarSync\Support\DateTime as WpDateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CalendarRenderer
 */
class CalendarRenderer {

	/**
	 * Event repository.
	 */
	private EventRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param EventRepository $repository Event repository.
	 */
	public function __construct( EventRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Enqueue frontend CSS & JS assets only when calendar renders.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		$css_url = plugin_dir_url( dirname( __DIR__ ) ) . 'assets/public/css/calendar.css';
		$js_url  = plugin_dir_url( dirname( __DIR__ ) ) . 'assets/public/js/calendar.js';

		wp_enqueue_style(
			'wpcs-calendar',
			$css_url,
			array(),
			'1.0.0'
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'wpcs-calendar-rtl',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/public/css/calendar-rtl.css',
				array( 'wpcs-calendar' ),
				'1.0.0'
			);
		}

		wp_enqueue_script(
			'wpcs-calendar',
			$js_url,
			array(),
			'1.0.0',
			true
		);

		wp_localize_script(
			'wpcs-calendar',
			'wpcsCalendarData',
			array(
				'restUrl' => esc_url_raw( rest_url( 'wpcalibrate/v1/events' ) ),
				'i18n'    => array(
					'loading' => __( 'Loading events...', 'wpcalibrate-calendar-sync' ),
					'error'   => __( 'Failed to load events.', 'wpcalibrate-calendar-sync' ),
				),
			)
		);
	}

	/**
	 * Render calendar HTML with given attributes.
	 *
	 * @param array<string, mixed> $atts Display attributes.
	 * @return string Rendered HTML.
	 */
	public function render( array $atts = array() ): string {
		self::enqueue_assets();

		$defaults = array(
			'calendars'        => '',
			'view'             => 'agenda',
			'limit'            => 50,
			'show_description' => true,
			'show_location'    => true,
			'month'            => (int) gmdate( 'n' ),
			'year'             => (int) gmdate( 'Y' ),
		);

		$args = wp_parse_args( $atts, $defaults );

		// Parse calendars.
		$calendar_ids = array();
		if ( ! empty( $args['calendars'] ) ) {
			if ( is_array( $args['calendars'] ) ) {
				$calendar_ids = array_map( 'sanitize_text_field', $args['calendars'] );
			} else {
				$calendar_ids = array_filter( array_map( 'trim', explode( ',', (string) $args['calendars'] ) ) );
			}
		}

		$view = in_array( $args['view'], array( 'agenda', 'month' ), true ) ? $args['view'] : 'agenda';

		if ( 'month' === $view ) {
			return $this->render_month_view( $calendar_ids, (int) $args['year'], (int) $args['month'], $args );
		}

		return $this->render_agenda_view( $calendar_ids, $args );
	}

	/**
	 * Render Agenda (list) view.
	 *
	 * @param array<int, string>   $calendar_ids Filtered calendar IDs.
	 * @param array<string, mixed> $args         Attributes.
	 * @return string
	 */
	private function render_agenda_view( array $calendar_ids, array $args ): string {
		$now_utc = gmdate( 'Y-m-d H:i:s' );

		$query_args = array(
			'status'    => 'confirmed',
			'start_utc' => $now_utc,
			'order_by'  => 'start_utc',
			'order'     => 'ASC',
			'limit'     => max( 1, (int) $args['limit'] ),
		);

		if ( ! empty( $calendar_ids ) ) {
			$query_args['calendar_ids'] = $calendar_ids;
		}

		$events = $this->repository->get_events( $query_args );

		ob_start();
		?>
		<section class="wpcs-calendar wpcs-agenda-view" aria-label="<?php esc_attr_e( 'Events Agenda', 'wpcalibrate-calendar-sync' ); ?>">
			<?php if ( empty( $events ) ) : ?>
				<div class="wpcs-empty-state" role="status">
					<p><?php esc_html_e( 'No upcoming events scheduled at this time.', 'wpcalibrate-calendar-sync' ); ?></p>
				</div>
			<?php else : ?>
				<ul class="wpcs-event-list" role="list">
					<?php
					$current_date_header = '';
					foreach ( $events as $event ) :
						$event_date_header = WpDateTime::to_site_display( $event->start_utc, 'l, F j, Y', (bool) $event->all_day );
						if ( $event_date_header !== $current_date_header ) :
							$current_date_header = $event_date_header;
							?>
							<li class="wpcs-date-header" role="presentation">
								<h3 class="wpcs-date-heading"><?php echo esc_html( $current_date_header ); ?></h3>
							</li>
							<?php
						endif;
						?>
						<li class="wpcs-event-card" id="wpcs-event-<?php echo esc_attr( (string) $event->id ); ?>" tabindex="0">
							<div class="wpcs-event-time">
								<?php if ( $event->all_day ) : ?>
									<span class="wpcs-badge wpcs-all-day"><?php esc_html_e( 'All Day', 'wpcalibrate-calendar-sync' ); ?></span>
								<?php else : ?>
									<time datetime="<?php echo esc_attr( $event->start_utc ); ?>" class="wpcs-time-start">
										<?php echo esc_html( WpDateTime::to_site_display( $event->start_utc, (string) get_option( 'time_format', 'g:i a' ) ) ); ?>
									</time>
									<span class="wpcs-time-separator" aria-hidden="true">&ndash;</span>
									<time datetime="<?php echo esc_attr( $event->end_utc ); ?>" class="wpcs-time-end">
										<?php echo esc_html( WpDateTime::to_site_display( $event->end_utc, (string) get_option( 'time_format', 'g:i a' ) ) ); ?>
									</time>
								<?php endif; ?>
							</div>

							<div class="wpcs-event-details">
								<h4 class="wpcs-event-title">
									<?php if ( ! empty( $event->html_link ) ) : ?>
										<a href="<?php echo esc_url( $event->html_link ); ?>" target="_blank" rel="noopener noreferrer" class="wpcs-event-link">
											<?php echo esc_html( $event->title ); ?>
											<span class="screen-reader-text"><?php esc_html_e( '(opens in Google Calendar)', 'wpcalibrate-calendar-sync' ); ?></span>
										</a>
									<?php else : ?>
										<?php echo esc_html( $event->title ); ?>
									<?php endif; ?>
								</h4>

								<?php if ( ! empty( $args['show_location'] ) && ! empty( $event->location ) ) : ?>
									<div class="wpcs-event-location">
										<span class="wpcs-icon" aria-hidden="true">&#x1F4CD;</span>
										<span><?php echo esc_html( $event->location ); ?></span>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $args['show_description'] ) && ! empty( $event->description ) ) : ?>
									<div class="wpcs-event-description">
										<?php echo wp_kses_post( wpautop( $event->description ) ); ?>
									</div>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render Month Grid view.
	 *
	 * @param array<int, string>   $calendar_ids Calendar IDs.
	 * @param int                  $year         Target year.
	 * @param int                  $month        Target month (1-12).
	 * @param array<string, mixed> $args         Attributes.
	 * @return string
	 */
	private function render_month_view( array $calendar_ids, int $year, int $month, array $args ): string {
		try {
			$first_day = new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $month ), WpDateTime::site_timezone() );
		} catch ( Exception ) {
			$first_day = new DateTimeImmutable( 'first day of this month', WpDateTime::site_timezone() );
			$year      = (int) $first_day->format( 'Y' );
			$month     = (int) $first_day->format( 'n' );
		}

		$days_in_month = (int) $first_day->format( 't' );
		$month_start_utc = $first_day->setTimezone( WpDateTime::utc() )->format( 'Y-m-d H:i:s' );
		$month_end_utc   = $first_day->modify( '+1 month' )->setTimezone( WpDateTime::utc() )->format( 'Y-m-d H:i:s' );

		$query_args = array(
			'status'    => 'confirmed',
			'start_utc' => $month_start_utc,
			'end_utc'   => $month_end_utc,
			'order_by'  => 'start_utc',
			'order'     => 'ASC',
			'limit'     => 500,
		);

		if ( ! empty( $calendar_ids ) ) {
			$query_args['calendar_ids'] = $calendar_ids;
		}

		$events = $this->repository->get_events( $query_args );

		// Index events by day of month (1 to days_in_month).
		$events_by_day = array();
		foreach ( $events as $ev ) {
			$dt_local = ( new DateTimeImmutable( $ev->start_utc, WpDateTime::utc() ) )->setTimezone( WpDateTime::site_timezone() );
			$d = (int) $dt_local->format( 'j' );
			$events_by_day[ $d ][] = $ev;
		}

		$start_of_week = (int) get_option( 'start_of_week', 0 ); // 0 = Sunday, 1 = Monday.
		$first_day_weekday = (int) $first_day->format( 'w' ); // 0 = Sun, 6 = Sat.
		$blank_cells = ( $first_day_weekday - $start_of_week + 7 ) % 7;

		$prev_month = $month - 1;
		$prev_year  = $year;
		if ( $prev_month < 1 ) {
			$prev_month = 12;
			$prev_year--;
		}

		$next_month = $month + 1;
		$next_year  = $year;
		if ( $next_month > 12 ) {
			$next_month = 1;
			$next_year++;
		}

		ob_start();
		?>
		<section class="wpcs-calendar wpcs-month-view" aria-label="<?php esc_attr_e( 'Month Calendar', 'wpcalibrate-calendar-sync' ); ?>" data-year="<?php echo esc_attr( (string) $year ); ?>" data-month="<?php echo esc_attr( (string) $month ); ?>">
			<div class="wpcs-month-header">
				<h3 class="wpcs-month-title">
					<?php echo esc_html( wp_date( 'F Y', $first_day->getTimestamp(), WpDateTime::site_timezone() ) ); ?>
				</h3>
			</div>

			<table class="wpcs-month-table" role="grid" aria-readonly="true">
				<thead>
					<tr>
						<?php
						$weekdays = array(
							__( 'Sun', 'wpcalibrate-calendar-sync' ),
							__( 'Mon', 'wpcalibrate-calendar-sync' ),
							__( 'Tue', 'wpcalibrate-calendar-sync' ),
							__( 'Wed', 'wpcalibrate-calendar-sync' ),
							__( 'Thu', 'wpcalibrate-calendar-sync' ),
							__( 'Fri', 'wpcalibrate-calendar-sync' ),
							__( 'Sat', 'wpcalibrate-calendar-sync' ),
						);
						for ( $i = 0; $i < 7; $i++ ) {
							$idx = ( $start_of_week + $i ) % 7;
							echo '<th scope="col" class="wpcs-weekday">' . esc_html( $weekdays[ $idx ] ) . '</th>';
						}
						?>
					</tr>
				</thead>
				<tbody>
					<tr>
						<?php
						// Leading blank cells.
						for ( $i = 0; $i < $blank_cells; $i++ ) {
							echo '<td class="wpcs-cell wpcs-cell-empty" aria-hidden="true"></td>';
						}

						$current_col = $blank_cells;
						for ( $day = 1; $day <= $days_in_month; $day++ ) {
							if ( $current_col === 7 ) {
								echo '</tr><tr>';
								$current_col = 0;
							}

							$day_events = $events_by_day[ $day ] ?? array();
							$has_events = ! empty( $day_events );
							?>
							<td class="wpcs-cell wpcs-cell-day <?php echo $has_events ? 'wpcs-has-events' : ''; ?>" tabindex="0">
								<span class="wpcs-day-number"><?php echo esc_html( (string) $day ); ?></span>
								<?php if ( $has_events ) : ?>
									<ul class="wpcs-day-events-list">
										<?php foreach ( $day_events as $ev ) : ?>
											<li class="wpcs-day-event-item" title="<?php echo esc_attr( $ev->title ); ?>">
												<span class="wpcs-event-dot" aria-hidden="true"></span>
												<span class="wpcs-event-name"><?php echo esc_html( $ev->title ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
							<?php
							$current_col++;
						}

						// Trailing blank cells.
						if ( $current_col > 0 && $current_col < 7 ) {
							for ( $i = $current_col; $i < 7; $i++ ) {
								echo '<td class="wpcs-cell wpcs-cell-empty" aria-hidden="true"></td>';
							}
						}
						?>
					</tr>
				</tbody>
			</table>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
