<?php

/**
 *
 * TrackSure Free - API Filters
 *
 * Filters that run during REST API requests.
 * These need to be loaded for both admin and API contexts.
 *
 * @package TrackSure\Free
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add plugin detection status to settings API response.
 *
 * AUTO-DETECTS all registered integrations from Integrations Manager.
 * NO HARDCODED LIST - reads from single source of truth!
 *
 * This filter runs during REST API /settings requests.
 *
 * @param array $settings Settings array from API.
 * @return array Settings with detection status added.
 */
function tracksure_add_integration_detection( $settings ) {
	// Get Integrations Manager (single source of truth for detection logic).
	$core                 = TrackSure_Core::get_instance();
	$integrations_manager = $core->get_service( 'integrations_manager' );

	if ( ! $integrations_manager ) {
		return $settings;
	}

	// Get ALL registered integrations from manager.
	$all_integrations = $integrations_manager->get_registered_integrations();

	// Check each integration's plugin and add detection flag.
	// Uses Integration Manager's is_plugin_active() method (supports arrays for Free/Pro).
	foreach ( $all_integrations as $integration_id => $integration ) {
		// Skip if no auto_detect path defined.
		if ( empty( $integration['auto_detect'] ) ) {
			continue;
		}

		$detection_key = $integration_id . '_detected';
		// Use Integration Manager's method - SINGLE SOURCE OF TRUTH for detection logic!
		$settings[ $detection_key ] = $integrations_manager->is_plugin_active( $integration['auto_detect'] );
	}

	return $settings;
}
add_filter( 'tracksure_rest_get_settings', 'tracksure_add_integration_detection', 10, 1 );
