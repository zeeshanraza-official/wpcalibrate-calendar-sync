<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'wpcs_delete_data_on_uninstall', false );

if ( ! empty( $delete_data ) ) {
	global $wpdb;

	// 1. Drop events database table.
	$table_name = $wpdb->prefix . 'wpcs_events';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	// 2. Unschedule any remaining cron events.
	$timestamp = wp_next_scheduled( 'wpcs_recovery_sync_cron' );
	while ( false !== $timestamp ) {
		wp_unschedule_event( $timestamp, 'wpcs_recovery_sync_cron' );
		$timestamp = wp_next_scheduled( 'wpcs_recovery_sync_cron' );
	}

	// 3. Delete strictly scoped plugin options (never touch other WPCalibrate plugins).
	$options_to_delete = array(
		'wpcs_connection',
		'wpcs_calendars',
		'wpcs_secrets',
		'wpcs_recent_logs',
		'wpcs_license',
		'wpcs_display_settings',
		'wpcs_recovery_interval',
		'wpcs_delete_data_on_uninstall',
		'wpcs_schema_version',
	);

	foreach ( $options_to_delete as $opt ) {
		delete_option( $opt );
	}

	// 4. Delete scoped transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_wpcs_%',
			'_transient_timeout_wpcs_%'
		)
	);
}
