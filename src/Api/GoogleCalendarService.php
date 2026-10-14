<?php
/**
 * Service Wrapper for Google Calendar API v3.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Api;

use Exception;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Channel as GoogleChannel;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime as GoogleEventDateTime;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;
use WPCalibrate\CalendarSync\Logging\Logger;
use WPCalibrate\CalendarSync\Support\DateTime as WpDateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GoogleCalendarService
 */
class GoogleCalendarService {

	/**
	 * Client factory.
	 */
	private GoogleClientFactory $client_factory;

	/**
	 * Logger.
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param GoogleClientFactory $client_factory Client factory.
	 * @param Logger|null         $logger         Logger instance.
	 */
	public function __construct( GoogleClientFactory $client_factory, ?Logger $logger = null ) {
		$this->client_factory = $client_factory;
		$this->logger         = $logger;
	}

	/**
	 * Get Google Calendar API service instance.
	 *
	 * @return GoogleCalendar
	 */
	public function get_api_service(): GoogleCalendar {
		$client = $this->client_factory->create_client();
		return new GoogleCalendar( $client );
	}

	/**
	 * Fetch all calendars accessible to the connected account.
	 *
	 * @return array<int, array<string, mixed>>
	 * @throws Exception On API or network failure.
	 */
	public function list_calendars(): array {
		try {
			$service  = $this->get_api_service();
			$calendar_list = $service->calendarList->listCalendarList();
			$items    = array();

			foreach ( $calendar_list->getItems() as $entry ) {
				$items[] = array(
					'id'          => $entry->getId(),
					'name'        => $entry->getSummary(),
					'description' => $entry->getDescription(),
					'timezone'    => $entry->getTimeZone(),
					'primary'     => (bool) $entry->getPrimary(),
					'access_role' => $entry->getAccessRole(),
				);
			}

			return $items;
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'list_calendars', 'Failed to fetch calendar list: ' . $e->getMessage() );
			}
			throw $e;
		}
	}

	/**
	 * Query events for a calendar (handles pagination and sync tokens).
	 *
	 * @param string               $calendar_id Calendar ID.
	 * @param array<string, mixed> $options     Query options (syncToken, pageToken, singleEvents, etc.).
	 * @return array{items: array<int, array<string, mixed>>, nextPageToken: ?string, nextSyncToken: ?string}
	 * @throws GoogleServiceException|Exception
	 */
	public function fetch_events_page( string $calendar_id, array $options = array() ): array {
		$service = $this->get_api_service();
		$params  = array(
			'maxResults'   => isset( $options['maxResults'] ) ? (int) $options['maxResults'] : 250,
			'showDeleted'  => true,
			'singleEvents' => true,
		);

		if ( ! empty( $options['syncToken'] ) ) {
			$params['syncToken'] = (string) $options['syncToken'];
		} else {
			// When initial sync or without syncToken, bounded time window or recent/upcoming.
			if ( ! empty( $options['timeMin'] ) ) {
				$params['timeMin'] = (string) $options['timeMin'];
			}
			if ( ! empty( $options['timeMax'] ) ) {
				$params['timeMax'] = (string) $options['timeMax'];
			}
		}

		if ( ! empty( $options['pageToken'] ) ) {
			$params['pageToken'] = (string) $options['pageToken'];
		}

		try {
			$events_response = $service->events->listEvents( $calendar_id, $params );

			$items = array();
			foreach ( $events_response->getItems() as $event ) {
				$items[] = $this->normalize_google_event( $event, $calendar_id );
			}

			return array(
				'items'         => $items,
				'nextPageToken' => $events_response->getNextPageToken(),
				'nextSyncToken' => $events_response->getNextSyncToken(),
			);
		} catch ( GoogleServiceException $e ) {
			// Catch 410 Gone indicating syncToken expired/invalid.
			if ( 410 === $e->getCode() ) {
				if ( $this->logger ) {
					$this->logger->warning(
						'fetch_events_page',
						sprintf( 'Google returned HTTP 410 Sync Token expired for calendar: %s', $calendar_id )
					);
				}
			}
			throw $e;
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'fetch_events_page',
					sprintf( 'Error fetching events for calendar %s: %s', $calendar_id, $e->getMessage() )
				);
			}
			throw $e;
		}
	}

	/**
	 * Create a new event in Google Calendar.
	 *
	 * @param string               $calendar_id Google Calendar ID.
	 * @param array<string, mixed> $event_data  Normalized event fields.
	 * @return array<string, mixed> Normalized remote event data.
	 * @throws Exception
	 */
	public function insert_event( string $calendar_id, array $event_data ): array {
		$service = $this->get_api_service();
		$event   = new GoogleEvent();

		$event->setSummary( (string) ( $event_data['title'] ?? '' ) );
		if ( ! empty( $event_data['description'] ) ) {
			$event->setDescription( (string) $event_data['description'] );
		}
		if ( ! empty( $event_data['location'] ) ) {
			$event->setLocation( (string) $event_data['location'] );
		}

		$all_day = ! empty( $event_data['all_day'] );
		$tz      = (string) ( $event_data['event_timezone'] ?? 'UTC' );

		$start = new GoogleEventDateTime();
		$end   = new GoogleEventDateTime();

		if ( $all_day ) {
			$start_date = substr( (string) ( $event_data['start_utc'] ?? '' ), 0, 10 );
			$end_date   = substr( (string) ( $event_data['end_utc'] ?? '' ), 0, 10 );
			$start->setDate( $start_date );
			$end->setDate( $end_date );
		} else {
			$g_start = WpDateTime::to_google_format( (string) $event_data['start_utc'], false, $tz );
			$g_end   = WpDateTime::to_google_format( (string) $event_data['end_utc'], false, $tz );
			$start->setDateTime( $g_start['dateTime'] ?? '' );
			$start->setTimeZone( $g_start['timeZone'] ?? 'UTC' );
			$end->setDateTime( $g_end['dateTime'] ?? '' );
			$end->setTimeZone( $g_end['timeZone'] ?? 'UTC' );
		}

		$event->setStart( $start );
		$event->setEnd( $end );

		$created = $service->events->insert( $calendar_id, $event );

		return $this->normalize_google_event( $created, $calendar_id );
	}

	/**
	 * Update an existing event in Google Calendar.
	 *
	 * @param string               $calendar_id Google Calendar ID.
	 * @param string               $event_id    Google Event ID.
	 * @param array<string, mixed> $event_data  Updated fields.
	 * @return array<string, mixed> Normalized remote event.
	 * @throws Exception
	 */
	public function update_event( string $calendar_id, string $event_id, array $event_data ): array {
		$service = $this->get_api_service();
		$event   = $service->events->get( $calendar_id, $event_id );

		$event->setSummary( (string) ( $event_data['title'] ?? $event->getSummary() ) );
		$event->setDescription( (string) ( $event_data['description'] ?? $event->getDescription() ) );
		$event->setLocation( (string) ( $event_data['location'] ?? $event->getLocation() ) );

		$all_day = ! empty( $event_data['all_day'] );
		$tz      = (string) ( $event_data['event_timezone'] ?? 'UTC' );

		$start = new GoogleEventDateTime();
		$end   = new GoogleEventDateTime();

		if ( $all_day ) {
			$start_date = substr( (string) ( $event_data['start_utc'] ?? '' ), 0, 10 );
			$end_date   = substr( (string) ( $event_data['end_utc'] ?? '' ), 0, 10 );
			$start->setDate( $start_date );
			$end->setDate( $end_date );
		} else {
			$g_start = WpDateTime::to_google_format( (string) $event_data['start_utc'], false, $tz );
			$g_end   = WpDateTime::to_google_format( (string) $event_data['end_utc'], false, $tz );
			$start->setDateTime( $g_start['dateTime'] ?? '' );
			$start->setTimeZone( $g_start['timeZone'] ?? 'UTC' );
			$end->setDateTime( $g_end['dateTime'] ?? '' );
			$end->setTimeZone( $g_end['timeZone'] ?? 'UTC' );
		}

		$event->setStart( $start );
		$event->setEnd( $end );

		$updated = $service->events->update( $calendar_id, $event_id, $event );

		return $this->normalize_google_event( $updated, $calendar_id );
	}

	/**
	 * Delete an event from Google Calendar.
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $event_id    Event ID.
	 * @return bool
	 */
	public function delete_event( string $calendar_id, string $event_id ): bool {
		try {
			$service = $this->get_api_service();
			$service->events->delete( $calendar_id, $event_id );
			return true;
		} catch ( GoogleServiceException $e ) {
			// If already 404/410, it is already deleted remotely.
			if ( 404 === $e->getCode() || 410 === $e->getCode() ) {
				return true;
			}
			throw $e;
		}
	}

	/**
	 * Setup a push notification watch channel for a calendar.
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $channel_id  Unique channel ID (UUID).
	 * @param string $webhook_url Public HTTPS REST webhook endpoint URL.
	 * @param string $token       Private verification token.
	 * @param int    $ttl_seconds Lifetime in seconds.
	 * @return array{resourceId: string, expiration: string} Channel subscription details.
	 * @throws Exception
	 */
	public function watch_calendar( string $calendar_id, string $channel_id, string $webhook_url, string $token, int $ttl_seconds = 604800 ): array {
		$service = $this->get_api_service();
		$channel = new GoogleChannel();

		$channel->setId( $channel_id );
		$channel->setType( 'web_hook' );
		$channel->setAddress( $webhook_url );
		$channel->setToken( $token );
		$channel->setParams( array( 'ttl' => (string) $ttl_seconds ) );

		$response = $service->events->watch( $calendar_id, $channel );

		return array(
			'resourceId' => (string) $response->getResourceId(),
			'expiration' => (string) $response->getExpiration(),
		);
	}

	/**
	 * Stop an active push notification channel.
	 *
	 * @param string $channel_id  Channel UUID.
	 * @param string $resource_id Google Resource ID.
	 * @return bool
	 */
	public function stop_channel( string $channel_id, string $resource_id ): bool {
		try {
			$service = $this->get_api_service();
			$channel = new GoogleChannel();
			$channel->setId( $channel_id );
			$channel->setResourceId( $resource_id );

			$service->channels->stop( $channel );
			return true;
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->warning(
					'stop_channel',
					sprintf( 'Could not stop push channel %s: %s', $channel_id, $e->getMessage() )
				);
			}
			return false;
		}
	}

	/**
	 * Convert Google Event object to normalized associative array for local storage.
	 *
	 * @param GoogleEvent $event       Google Event object.
	 * @param string      $calendar_id Calendar ID.
	 * @return array<string, mixed>
	 */
	public function normalize_google_event( GoogleEvent $event, string $calendar_id ): array {
		$start_obj = $event->getStart();
		$end_obj   = $event->getEnd();

		$all_day = false;
		$start_utc = '';
		$end_utc   = '';
		$tz        = 'UTC';

		if ( $start_obj && ! empty( $start_obj->getDate() ) ) {
			// All-day event.
			$all_day   = true;
			$start_utc = WpDateTime::all_day_to_utc_mysql( $start_obj->getDate() );
			// Google end dates for all-day events are exclusive, so end date is midnight of that day.
			$end_utc   = WpDateTime::all_day_to_utc_mysql( $end_obj ? $end_obj->getDate() : $start_obj->getDate() );
		} elseif ( $start_obj && ! empty( $start_obj->getDateTime() ) ) {
			$tz        = (string) ( $start_obj->getTimeZone() ?: 'UTC' );
			$start_utc = WpDateTime::to_utc_mysql( $start_obj->getDateTime(), $tz );
			$end_utc   = $end_obj && ! empty( $end_obj->getDateTime() )
				? WpDateTime::to_utc_mysql( $end_obj->getDateTime(), (string) ( $end_obj->getTimeZone() ?: $tz ) )
				: $start_utc;
		}

		$status = $event->getStatus() ?: 'confirmed';

		return array(
			'google_event_id'           => $event->getId(),
			'google_calendar_id'        => $calendar_id,
			'google_recurring_event_id' => $event->getRecurringEventId() ?: null,
			'etag'                      => $event->getEtag() ?: null,
			'html_link'                 => $event->getHtmlLink() ?: null,
			'event_status'              => $status,
			'title'                     => (string) ( $event->getSummary() ?: __( '(No title)', 'wpcalibrate-calendar-sync' ) ),
			'description'               => $event->getDescription() ?: '',
			'location'                  => $event->getLocation() ?: '',
			'start_utc'                 => $start_utc,
			'end_utc'                   => $end_utc,
			'all_day'                   => $all_day ? 1 : 0,
			'event_timezone'            => $tz,
			'visibility'                => $event->getVisibility() ?: 'default',
			'transparency'              => $event->getTransparency() ?: 'opaque',
			'google_updated_at'         => $event->getUpdated() ? WpDateTime::to_utc_mysql( $event->getUpdated() ) : null,
		);
	}
}
