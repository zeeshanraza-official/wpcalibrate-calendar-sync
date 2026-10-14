<?php
/**
 * Core Synchronization Engine.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Sync;

use Exception;
use Google\Service\Exception as GoogleServiceException;
use WPCalibrate\CalendarSync\Api\GoogleCalendarService;
use WPCalibrate\CalendarSync\Events\EventRepository;
use WPCalibrate\CalendarSync\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SyncService
 */
class SyncService {

	/**
	 * Settings option name for configured calendars.
	 */
	public const OPTION_CALENDARS = 'wpcs_calendars';

	/**
	 * Repository.
	 */
	private EventRepository $repository;

	/**
	 * Google Calendar Service wrapper.
	 */
	private GoogleCalendarService $google_service;

	/**
	 * Lock manager.
	 */
	private SyncLock $lock;

	/**
	 * Logger.
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param EventRepository       $repository     Event repository.
	 * @param GoogleCalendarService $google_service Google calendar API service.
	 * @param SyncLock              $lock           Lock manager.
	 * @param Logger|null           $logger         Logger instance.
	 */
	public function __construct(
		EventRepository $repository,
		GoogleCalendarService $google_service,
		SyncLock $lock,
		?Logger $logger = null
	) {
		$this->repository     = $repository;
		$this->google_service = $google_service;
		$this->lock           = $lock;
		$this->logger         = $logger;
	}

	/**
	 * Get all configured calendars.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_configured_calendars(): array {
		$calendars = get_option( self::OPTION_CALENDARS, array() );
		return is_array( $calendars ) ? $calendars : array();
	}

	/**
	 * Save configured calendars.
	 *
	 * @param array<string, array<string, mixed>> $calendars Calendar map.
	 * @return bool
	 */
	public function save_configured_calendars( array $calendars ): bool {
		return update_option( self::OPTION_CALENDARS, $calendars, false );
	}

	/**
	 * Synchronize all enabled calendars.
	 *
	 * @param bool $force_full_resync Force clear of sync tokens.
	 * @return array<string, array{success: bool, imported: int, updated: int, deleted: int, error?: string}>
	 */
	public function sync_all( bool $force_full_resync = false ): array {
		$calendars = $this->get_configured_calendars();
		$results   = array();

		foreach ( $calendars as $cal_id => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}
			$results[ $cal_id ] = $this->sync_calendar( (string) $cal_id, $force_full_resync );
		}

		return $results;
	}

	/**
	 * Synchronize a single calendar.
	 *
	 * @param string $calendar_id       Google Calendar ID.
	 * @param bool   $force_full_resync If true, discards sync token for a full resync.
	 * @return array{success: bool, imported: int, updated: int, deleted: int, error?: string}
	 */
	public function sync_calendar( string $calendar_id, bool $force_full_resync = false ): array {
		$calendars = $this->get_configured_calendars();
		if ( ! isset( $calendars[ $calendar_id ] ) ) {
			return array(
				'success'  => false,
				'imported' => 0,
				'updated'  => 0,
				'deleted'  => 0,
				'error'    => __( 'Calendar not configured.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$config    = $calendars[ $calendar_id ];
		$direction = (string) ( $config['direction'] ?? 'two_way' );

		// Step 1: Concurrency lock.
		if ( ! $this->lock->acquire( $calendar_id ) ) {
			if ( $this->logger ) {
				$this->logger->warning(
					'sync_calendar',
					sprintf( 'Sync skipped: Calendar %s is currently locked by another process.', $calendar_id )
				);
			}
			return array(
				'success'  => false,
				'imported' => 0,
				'updated'  => 0,
				'deleted'  => 0,
				'error'    => __( 'Synchronization in progress by another request.', 'wpcalibrate-calendar-sync' ),
			);
		}

		$now_utc = gmdate( 'Y-m-d H:i:s' );
		$config['last_attempt_utc'] = $now_utc;
		$calendars[ $calendar_id ]  = $config;
		$this->save_configured_calendars( $calendars );

		// Simulated sync for demo calendars.
		if ( ! empty( $config['is_demo'] ) || str_starts_with( $calendar_id, 'demo-' ) ) {
			$this->lock->release( $calendar_id );
			$config['last_sync_utc'] = $now_utc;
			$config['last_error']    = '';
			$calendars[ $calendar_id ] = $config;
			$this->save_configured_calendars( $calendars );

			return array(
				'success'  => true,
				'imported' => 0,
				'updated'  => 0,
				'deleted'  => 0,
				'is_demo'  => true,
			);
		}

		$stats = array(
			'imported' => 0,
			'updated'  => 0,
			'deleted'  => 0,
		);

		try {
			// Step 2: Flush outbound pending changes first if outbound sync is supported.
			if ( 'two_way' === $direction || 'wp_to_google' === $direction ) {
				$this->process_outbound_changes( $calendar_id );
			}

			// If calendar is configured strictly for wp_to_google, skip inbound sync.
			if ( 'wp_to_google' === $direction ) {
				$config['last_sync_utc'] = $now_utc;
				$config['last_error']    = '';
				$calendars[ $calendar_id ] = $config;
				$this->save_configured_calendars( $calendars );

				return array(
					'success'  => true,
					'imported' => 0,
					'updated'  => 0,
					'deleted'  => 0,
				);
			}

			// Step 3: Inbound sync (Google to WordPress).
			$sync_token = $force_full_resync ? '' : (string) ( $config['sync_token'] ?? '' );
			$sync_res   = $this->process_inbound_sync( $calendar_id, $sync_token );

			$stats['imported'] += $sync_res['imported'];
			$stats['updated']  += $sync_res['updated'];
			$stats['deleted']  += $sync_res['deleted'];

			// Save new sync token and timestamp on success.
			$config['sync_token']    = $sync_res['new_sync_token'];
			$config['last_sync_utc'] = $now_utc;
			$config['last_error']    = '';
			$calendars[ $calendar_id ] = $config;
			$this->save_configured_calendars( $calendars );

			if ( $this->logger ) {
				$this->logger->info(
					'sync_calendar',
					sprintf(
						'Calendar %s synchronized successfully. Imported: %d, Updated: %d, Deleted: %d.',
						$calendar_id,
						$stats['imported'],
						$stats['updated'],
						$stats['deleted']
					)
				);
			}

			return array(
				'success'  => true,
				'imported' => $stats['imported'],
				'updated'  => $stats['updated'],
				'deleted'  => $stats['deleted'],
			);
		} catch ( Exception $e ) {
			$error_msg = $e->getMessage();
			$config['last_error'] = $error_msg;
			$calendars[ $calendar_id ] = $config;
			$this->save_configured_calendars( $calendars );

			if ( $this->logger ) {
				$this->logger->error(
					'sync_calendar',
					sprintf( 'Sync failed for calendar %s: %s', $calendar_id, $error_msg )
				);
			}

			return array(
				'success'  => false,
				'imported' => $stats['imported'],
				'updated'  => $stats['updated'],
				'deleted'  => $stats['deleted'],
				'error'    => $error_msg,
			);
		} finally {
			$this->lock->release( $calendar_id );
		}
	}

	/**
	 * Process inbound sync pages from Google.
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $sync_token  Saved sync token (empty for initial sync).
	 * @return array{imported: int, updated: int, deleted: int, new_sync_token: string}
	 * @throws Exception
	 */
	private function process_inbound_sync( string $calendar_id, string $sync_token ): array {
		$imported = 0;
		$updated  = 0;
		$deleted  = 0;

		$page_token     = null;
		$new_sync_token = '';

		try {
			do {
				$options = array(
					'maxResults' => 250,
				);

				if ( ! empty( $sync_token ) ) {
					$options['syncToken'] = $sync_token;
				} else {
					// Time boundaries for initial sync (1 year back to 2 years future).
					$options['timeMin'] = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-1 year' ) );
					$options['timeMax'] = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+2 years' ) );
				}

				if ( ! empty( $page_token ) ) {
					$options['pageToken'] = $page_token;
				}

				$page = $this->google_service->fetch_events_page( $calendar_id, $options );

				foreach ( $page['items'] as $event_data ) {
					$g_id     = (string) ( $event_data['google_event_id'] ?? '' );
					$existing = $this->repository->get_by_google_id( $calendar_id, $g_id );

					// Handle deleted/cancelled events.
					if ( 'cancelled' === ( $event_data['event_status'] ?? '' ) ) {
						if ( $existing ) {
							$this->repository->mark_as_cancelled( $calendar_id, $g_id );
							$deleted++;
						}
						continue;
					}

					// Conflict resolution check.
					if ( $existing && (int) $existing->local_dirty === 1 ) {
						// There is an unpushed local change. Mark conflict instead of blindly overwriting.
						$this->repository->update(
							(int) $existing->id,
							array( 'sync_status' => 'conflict' )
						);
						if ( $this->logger ) {
							$this->logger->warning(
								'sync_conflict',
								sprintf( 'Conflict detected on event %s (ID %d). Marked conflict.', $g_id, (int) $existing->id )
							);
						}
						continue;
					}

					if ( $existing ) {
						$this->repository->upsert_from_google( $event_data );
						$updated++;
					} else {
						$this->repository->upsert_from_google( $event_data );
						$imported++;
					}
				}

				$page_token     = $page['nextPageToken'];
				$new_sync_token = (string) ( $page['nextSyncToken'] ?? '' );
			} while ( ! empty( $page_token ) );

			return array(
				'imported'       => $imported,
				'updated'        => $updated,
				'deleted'        => $deleted,
				'new_sync_token' => $new_sync_token,
			);
		} catch ( GoogleServiceException $e ) {
			// HTTP 410 Recovery: Invalid or expired sync token.
			if ( 410 === $e->getCode() && ! empty( $sync_token ) ) {
				if ( $this->logger ) {
					$this->logger->warning(
						'sync_recovery_410',
						sprintf( 'Sync token expired for %s. Executing controlled full resync.', $calendar_id )
					);
				}
				// Retry with empty sync_token.
				return $this->process_inbound_sync( $calendar_id, '' );
			}
			throw $e;
		}
	}

	/**
	 * Process and push local pending/dirty events to Google.
	 *
	 * @param string $calendar_id Google Calendar ID.
	 * @return void
	 */
	public function process_outbound_changes( string $calendar_id ): void {
		$dirty_events = $this->repository->get_dirty_events( 50 );

		foreach ( $dirty_events as $event ) {
			if ( $event->google_calendar_id !== $calendar_id ) {
				continue;
			}

			try {
				// Deletion / cancellation outbound push.
				if ( 'cancelled' === $event->event_status ) {
					if ( ! str_starts_with( $event->google_event_id, 'wp_' ) ) {
						$this->google_service->delete_event( $calendar_id, $event->google_event_id );
					}
					// Mark fully synced cancellation.
					$this->repository->update(
						(int) $event->id,
						array(
							'sync_status' => 'synced',
							'local_dirty' => 0,
						)
					);
					continue;
				}

				// If event has temporary ID 'wp_*', insert as new event on Google.
				if ( str_starts_with( $event->google_event_id, 'wp_' ) ) {
					$created = $this->google_service->insert_event(
						$calendar_id,
						(array) $event
					);

					$this->repository->update(
						(int) $event->id,
						array(
							'google_event_id'   => $created['google_event_id'],
							'etag'              => $created['etag'],
							'html_link'         => $created['html_link'],
							'google_updated_at' => $created['google_updated_at'],
							'sync_status'       => 'synced',
							'local_dirty'       => 0,
						)
					);
				} else {
					// Existing Google event: update remote event.
					$updated = $this->google_service->update_event(
						$calendar_id,
						$event->google_event_id,
						(array) $event
					);

					$this->repository->update(
						(int) $event->id,
						array(
							'etag'              => $updated['etag'],
							'google_updated_at' => $updated['google_updated_at'],
							'sync_status'       => 'synced',
							'local_dirty'       => 0,
						)
					);
				}
			} catch ( Exception $e ) {
				if ( $this->logger ) {
					$this->logger->error(
						'outbound_sync',
						sprintf( 'Outbound push failed for event ID %d: %s', (int) $event->id, $e->getMessage() )
					);
				}
				$this->repository->update(
					(int) $event->id,
					array(
						'sync_status' => 'error',
					)
				);
			}
		}
	}
}
