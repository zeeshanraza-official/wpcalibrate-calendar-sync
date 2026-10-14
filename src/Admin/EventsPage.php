<?php
/**
 * Admin Events Management Page Controller.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Admin;

use WPCalibrate\CalendarSync\Events\EventRepository;
use WPCalibrate\CalendarSync\Events\EventService;
use WPCalibrate\CalendarSync\Support\Capabilities;
use WPCalibrate\CalendarSync\Support\DateTime as WpDateTime;
use WPCalibrate\CalendarSync\Sync\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EventsPage
 */
class EventsPage {

	/**
	 * Event service.
	 */
	private EventService $event_service;

	/**
	 * Event repository.
	 */
	private EventRepository $repository;

	/**
	 * Sync service.
	 */
	private SyncService $sync_service;

	/**
	 * Constructor.
	 *
	 * @param EventService    $event_service Event service.
	 * @param EventRepository $repository    Event repository.
	 * @param SyncService     $sync_service  Sync service.
	 */
	public function __construct(
		EventService $event_service,
		EventRepository $repository,
		SyncService $sync_service
	) {
		$this->event_service = $event_service;
		$this->repository    = $repository;
		$this->sync_service  = $sync_service;
	}

	/**
	 * Render Events management screen.
	 *
	 * @return void
	 */
	public function render(): void {
		Capabilities::verify_manage();

		$this->handle_event_actions();

		$action = sanitize_key( (string) ( $_GET['action'] ?? 'list' ) );

		if ( 'new' === $action ) {
			$this->render_event_form();
			return;
		}

		if ( 'edit' === $action && ! empty( $_GET['event_id'] ) ) {
			$event_id = (int) $_GET['event_id'];
			$event    = $this->repository->get_by_id( $event_id );
			if ( $event ) {
				$this->render_event_form( $event );
				return;
			}
		}

		$this->render_events_list();
	}

	/**
	 * Process form actions for creating, editing, and deleting events.
	 *
	 * @return void
	 */
	private function handle_event_actions(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['wpcs_event_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( (string) $_POST['wpcs_event_nonce'] ), 'wpcs_event_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wpcalibrate-calendar-sync' ) );
		}

		$sub_action = sanitize_key( (string) ( $_POST['wpcs_event_sub_action'] ?? '' ) );

		if ( 'create' === $sub_action ) {
			$res = $this->event_service->create_event( $_POST );
			$cls = $res['success'] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
		} elseif ( 'update' === $sub_action && ! empty( $_POST['event_id'] ) ) {
			$event_id = (int) $_POST['event_id'];
			$res      = $this->event_service->update_event( $event_id, $_POST );
			$cls      = $res['success'] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
		} elseif ( 'delete' === $sub_action && ! empty( $_POST['event_id'] ) ) {
			$event_id = (int) $_POST['event_id'];
			$res      = $this->event_service->delete_event( $event_id );
			$cls      = $res['success'] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
		}
	}

	/**
	 * Render Event List table with search, filters, and pagination.
	 *
	 * @return void
	 */
	private function render_events_list(): void {
		$calendars = $this->sync_service->get_configured_calendars();

		$cal_filter  = sanitize_text_field( (string) ( $_GET['calendar_id'] ?? '' ) );
		$status_filt = sanitize_text_field( (string) ( $_GET['status'] ?? 'all' ) );
		$search      = sanitize_text_field( (string) ( $_GET['s'] ?? '' ) );
		$page        = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$per_page    = 20;
		$offset      = ( $page - 1 ) * $per_page;

		$query_args = array(
			'limit'    => $per_page,
			'offset'   => $offset,
			'order_by' => 'start_utc',
			'order'    => 'DESC',
		);

		if ( ! empty( $cal_filter ) ) {
			$query_args['calendar_id'] = $cal_filter;
		}
		if ( 'all' !== $status_filt ) {
			$query_args['status'] = $status_filt;
		}
		if ( ! empty( $search ) ) {
			$query_args['search'] = $search;
		}

		$events = $this->repository->get_events( $query_args );
		$total  = $this->repository->count_events( $query_args );
		$pages  = (int) ceil( $total / $per_page );
		?>
		<div class="wrap wpcs-admin-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Synchronized Calendar Events', 'wpcalibrate-calendar-sync' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::EVENTS_PAGE_SLUG . '&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New Event', 'wpcalibrate-calendar-sync' ); ?>
			</a>
			<hr class="wp-header-end" />

			<form method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( Menu::EVENTS_PAGE_SLUG ); ?>" />

				<div class="tablenav top">
					<div class="alignleft actions">
						<select name="calendar_id">
							<option value=""><?php esc_html_e( 'All Calendars', 'wpcalibrate-calendar-sync' ); ?></option>
							<?php foreach ( $calendars as $cid => $c ) : ?>
								<option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $cal_filter === $cid ); ?>>
									<?php echo esc_html( $c['name'] ?? $cid ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<select name="status">
							<option value="all" <?php selected( 'all' === $status_filt ); ?>><?php esc_html_e( 'All Statuses', 'wpcalibrate-calendar-sync' ); ?></option>
							<option value="confirmed" <?php selected( 'confirmed' === $status_filt ); ?>><?php esc_html_e( 'Confirmed', 'wpcalibrate-calendar-sync' ); ?></option>
							<option value="cancelled" <?php selected( 'cancelled' === $status_filt ); ?>><?php esc_html_e( 'Cancelled', 'wpcalibrate-calendar-sync' ); ?></option>
						</select>

						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search events...', 'wpcalibrate-calendar-sync' ); ?>" />
						<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wpcalibrate-calendar-sync' ); ?></button>
					</div>

					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $total, 'wpcalibrate-calendar-sync' ), number_format_i18n( $total ) ) ); ?></span>
						<?php if ( $pages > 1 ) : ?>
							<span class="pagination-links">
								<?php
								echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'prev_text' => '&laquo;',
										'next_text' => '&raquo;',
										'total'     => $pages,
										'current'   => $page,
									)
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'wpcalibrate-calendar-sync' ); ?></th>
							<th><?php esc_html_e( 'Calendar', 'wpcalibrate-calendar-sync' ); ?></th>
							<th><?php esc_html_e( 'Start (Local Time)', 'wpcalibrate-calendar-sync' ); ?></th>
							<th><?php esc_html_e( 'End (Local Time)', 'wpcalibrate-calendar-sync' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wpcalibrate-calendar-sync' ); ?></th>
							<th><?php esc_html_e( 'Sync State', 'wpcalibrate-calendar-sync' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wpcalibrate-calendar-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $events ) ) : ?>
							<tr>
								<td colspan="7"><?php esc_html_e( 'No synchronized events found.', 'wpcalibrate-calendar-sync' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $events as $ev ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $ev->title ); ?></strong>
										<?php if ( ! empty( $ev->location ) ) : ?>
											<br /><small class="text-muted">&#x1F4CD; <?php echo esc_html( $ev->location ); ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $calendars[ $ev->google_calendar_id ]['name'] ?? $ev->google_calendar_id ); ?></td>
									<td>
										<?php echo esc_html( WpDateTime::to_site_display( $ev->start_utc, '', (bool) $ev->all_day ) ); ?>
									</td>
									<td>
										<?php echo esc_html( WpDateTime::to_site_display( $ev->end_utc, '', (bool) $ev->all_day ) ); ?>
									</td>
									<td>
										<?php if ( 'confirmed' === $ev->event_status ) : ?>
											<span class="wpcs-badge wpcs-badge-success"><?php esc_html_e( 'Confirmed', 'wpcalibrate-calendar-sync' ); ?></span>
										<?php else : ?>
											<span class="wpcs-badge wpcs-badge-error"><?php echo esc_html( ucfirst( $ev->event_status ) ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'synced' === $ev->sync_status ) : ?>
											<span class="wpcs-badge wpcs-badge-success"><?php esc_html_e( 'Synced', 'wpcalibrate-calendar-sync' ); ?></span>
										<?php elseif ( 'conflict' === $ev->sync_status ) : ?>
											<span class="wpcs-badge wpcs-badge-warning"><?php esc_html_e( 'Conflict', 'wpcalibrate-calendar-sync' ); ?></span>
										<?php else : ?>
											<span class="wpcs-badge wpcs-badge-muted"><?php echo esc_html( ucfirst( $ev->sync_status ) ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::EVENTS_PAGE_SLUG . '&action=edit&event_id=' . (int) $ev->id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'wpcalibrate-calendar-sync' ); ?>
										</a>
										<?php if ( ! empty( $ev->html_link ) ) : ?>
											<a href="<?php echo esc_url( $ev->html_link ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small" title="<?php esc_attr_e( 'Open in Google Calendar', 'wpcalibrate-calendar-sync' ); ?>">
												&nearr;
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Event Create / Edit Form.
	 *
	 * @param object|null $event Existing event if editing.
	 * @return void
	 */
	private function render_event_form( ?object $event = null ): void {
		$calendars = $this->sync_service->get_configured_calendars();
		$is_edit   = null !== $event;
		$title     = $is_edit ? $event->title : '';
		$desc      = $is_edit ? $event->description : '';
		$location  = $is_edit ? $event->location : '';
		$cal_id    = $is_edit ? $event->google_calendar_id : '';
		$all_day   = $is_edit ? (bool) $event->all_day : false;
		$tz        = $is_edit ? $event->event_timezone : WpDateTime::site_timezone()->getName();

		$start_date = '';
		$start_time = '09:00';
		$end_date   = '';
		$end_time   = '10:00';

		if ( $is_edit && ! empty( $event->start_utc ) ) {
			$start_date = substr( $event->start_utc, 0, 10 );
			$start_time = substr( $event->start_utc, 11, 5 );
		} else {
			$start_date = gmdate( 'Y-m-d' );
		}

		if ( $is_edit && ! empty( $event->end_utc ) ) {
			$end_date = substr( $event->end_utc, 0, 10 );
			$end_time = substr( $event->end_utc, 11, 5 );
		} else {
			$end_date = gmdate( 'Y-m-d' );
		}

		// Check if selected calendar allows outbound modifications.
		$is_read_only = false;
		if ( $is_edit && isset( $calendars[ $cal_id ] ) ) {
			$dir = (string) ( $calendars[ $cal_id ]['direction'] ?? 'two_way' );
			$is_read_only = 'google_to_wp' === $dir;
		}
		?>
		<div class="wrap wpcs-admin-wrap">
			<h1><?php echo $is_edit ? esc_html__( 'Edit Synchronized Event', 'wpcalibrate-calendar-sync' ) : esc_html__( 'Add New Event', 'wpcalibrate-calendar-sync' ); ?></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::EVENTS_PAGE_SLUG ) ); ?>">&larr; <?php esc_html_e( 'Back to Events List', 'wpcalibrate-calendar-sync' ); ?></a></p>

			<?php if ( $is_read_only ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Notice: This calendar is configured as "Google to WordPress (Read-only)". Modifications made here cannot be sent to Google Calendar.', 'wpcalibrate-calendar-sync' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::EVENTS_PAGE_SLUG ) ); ?>" class="wpcs-card">
				<?php wp_nonce_field( 'wpcs_event_action', 'wpcs_event_nonce' ); ?>
				<input type="hidden" name="wpcs_event_sub_action" value="<?php echo $is_edit ? 'update' : 'create'; ?>" />
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event->id ); ?>" />
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpcs_event_cal"><?php esc_html_e( 'Calendar', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<select id="wpcs_event_cal" name="google_calendar_id" required <?php disabled( $is_edit ); ?>>
								<option value=""><?php esc_html_e( '-- Select Calendar --', 'wpcalibrate-calendar-sync' ); ?></option>
								<?php foreach ( $calendars as $cid => $c ) : ?>
									<option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $cal_id === $cid ); ?>>
										<?php echo esc_html( $c['name'] ?? $cid ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( $is_edit ) : ?>
								<input type="hidden" name="google_calendar_id" value="<?php echo esc_attr( $cal_id ); ?>" />
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpcs_event_title"><?php esc_html_e( 'Event Title', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<input type="text" id="wpcs_event_title" name="title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpcs_event_desc"><?php esc_html_e( 'Description', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<textarea id="wpcs_event_desc" name="description" rows="4" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpcs_event_loc"><?php esc_html_e( 'Location', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<input type="text" id="wpcs_event_loc" name="location" value="<?php echo esc_attr( $location ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'All Day Event', 'wpcalibrate-calendar-sync' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="all_day" id="wpcs_all_day_check" value="1" <?php checked( $all_day ); ?> />
								<?php esc_html_e( 'This event takes place all day', 'wpcalibrate-calendar-sync' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Date & Time', 'wpcalibrate-calendar-sync' ); ?></th>
						<td>
							<fieldset>
								<label><?php esc_html_e( 'Start:', 'wpcalibrate-calendar-sync' ); ?></label>
								<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" required />
								<input type="time" name="start_time" value="<?php echo esc_attr( $start_time ); ?>" />
								<br /><br />
								<label><?php esc_html_e( 'End:', 'wpcalibrate-calendar-sync' ); ?></label>
								<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" required />
								<input type="time" name="end_time" value="<?php echo esc_attr( $end_time ); ?>" />
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpcs_timezone"><?php esc_html_e( 'Timezone', 'wpcalibrate-calendar-sync' ); ?></label></th>
						<td>
							<input type="text" id="wpcs_timezone" name="event_timezone" value="<?php echo esc_attr( $tz ); ?>" class="regular-text" />
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php echo $is_edit ? esc_html__( 'Update Event', 'wpcalibrate-calendar-sync' ) : esc_html__( 'Create Event', 'wpcalibrate-calendar-sync' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}
}
