<?php

/**
 * Uninstall TrackSure plugin.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Direct database queries required for uninstall cleanup
// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Uninstall TrackSure
 *
 * Removes all plugin data from the database (optional).
 * Only runs when plugin is uninstalled via WordPress admin.
 *
 * @package TrackSure
 * @since 1.0.0
 */



// Check if user wants to keep data.
// Setting is stored as individual WordPress option via REST settings controller.
$tracksure_keep_data = get_option( 'tracksure_keep_data_on_uninstall', false );
if ( $tracksure_keep_data ) {
	return; // Keep data, don't delete.
}

global $wpdb;

// Delete database tables.
$tracksure_tables = array(
	$wpdb->prefix . 'tracksure_events',
	$wpdb->prefix . 'tracksure_visitors',
	$wpdb->prefix . 'tracksure_sessions',
	$wpdb->prefix . 'tracksure_conversions',
	$wpdb->prefix . 'tracksure_touchpoints',
	$wpdb->prefix . 'tracksure_conversion_attribution',
	$wpdb->prefix . 'tracksure_goals',
	$wpdb->prefix . 'tracksure_outbox',
	$wpdb->prefix . 'tracksure_click_ids',
	$wpdb->prefix . 'tracksure_agg_hourly',
	$wpdb->prefix . 'tracksure_agg_daily',
	$wpdb->prefix . 'tracksure_agg_product_daily',
	$wpdb->prefix . 'tracksure_funnels',
	$wpdb->prefix . 'tracksure_funnel_steps',
	$wpdb->prefix . 'tracksure_logs', // ADD: Missing logs table
);

foreach ( $tracksure_tables as $tracksure_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$tracksure_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete core options.
delete_option( 'tracksure_settings' );
delete_option( 'tracksure_keep_data_on_uninstall' );
delete_option( 'tracksure_version' );
delete_option( 'tracksure_db_version' );
delete_option( 'tracksure_public_token' );
delete_option( 'tracksure_needs_permalink_flush' ); // ADD: Cleanup activation flag
delete_option( 'tracksure_permalinks_flushed' ); // ADD: Old flag cleanup
delete_option( 'tracksure_registered_modules' ); // ADD: Module registry cleanup
delete_option( 'tracksure_last_hourly_agg' ); // ADD: Aggregation timestamps
delete_option( 'tracksure_last_daily_agg' ); // ADD: Aggregation timestamps

// Delete Free plugin destination settings.
delete_option( 'tracksure_free_meta_enabled' );
delete_option( 'tracksure_free_meta_pixel_id' );
delete_option( 'tracksure_free_meta_access_token' );
delete_option( 'tracksure_free_meta_test_event_code' );
delete_option( 'tracksure_free_ga4_enabled' );
delete_option( 'tracksure_free_ga4_measurement_id' );
delete_option( 'tracksure_free_ga4_api_secret' );

// Delete Free plugin integration settings.
delete_option( 'woo_integration_enabled' );

// Delete all options with tracksure prefix (Pro/3rd-party extensions).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'tracksure_' ) . '%',
		$wpdb->esc_like( '_tracksure_' ) . '%'
	)
);

// Clear scheduled cron jobs.
wp_clear_scheduled_hook( 'tracksure_aggregate_hourly' );
wp_clear_scheduled_hook( 'tracksure_aggregate_daily' );
wp_clear_scheduled_hook( 'tracksure_delivery_worker' );
wp_clear_scheduled_hook( 'tracksure_cleanup_data' );
wp_clear_scheduled_hook( 'tracksure_cleanup_logs' );

// Delete transients (cache).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_tracksure_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_tracksure_' ) . '%'
	)
);

// Flush rewrite rules.
flush_rewrite_rules();
