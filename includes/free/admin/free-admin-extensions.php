<?php

/**
 *
 * TrackSure Free - Admin Extensions
 *
 * Registers Free plugin's settings, destinations, and integrations into core admin.
 *
 * NOTE: This file only registers KEY REFERENCES and UI metadata.
 * ALL setting definitions (type, default, validation) come from TrackSure_Settings_Schema.
 *
 * @package TrackSure\Free
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Free admin extensions.
 *
 * @param TrackSure_Admin_Extensions $registry Extensions registry.
 */
function tracksure_free_register_admin_extensions( $registry ) {
	$registry->register_extension(
		array(
			'id'       => 'tracksure-free',
			'name'     => 'TrackSure Free',
			'version'  => TRACKSURE_VERSION,

			// Core Settings (reference schema keys only).
			'settings' => array(
				// Tracking Settings.
				array(
					'id'          => 'core-tracking',
					'category'    => 'tracking',
					'title'       => __( 'Core Tracking', 'tracksure' ),
					'description' => __( 'Master tracking controls', 'tracksure' ),
					'order'       => 10,
					'fields'      => array(
						'tracksure_tracking_enabled',
						'tracksure_track_admins',
						'tracksure_session_timeout',
					),
				),

				// Privacy Settings.
				array(
					'id'          => 'privacy-compliance',
					'category'    => 'privacy',
					'title'       => __( 'Privacy & Compliance', 'tracksure' ),
					'description' => __( 'GDPR, CCPA, and consent management', 'tracksure' ),
					'order'       => 10,
					'fields'      => array(
						'tracksure_consent_mode',        // Consent requirement (disabled/opt-in/opt-out/auto)
						'tracksure_respect_dnt',
						'tracksure_anonymize_ip',
						'tracksure_exclude_ips',
						'tracksure_retention_days',
					),
				),

				// Performance Settings.
				array(
					'id'          => 'performance-batching',
					'category'    => 'performance',
					'title'       => __( 'Performance', 'tracksure' ),
					'description' => __( 'Event batching and optimization', 'tracksure' ),
					'order'       => 10,
					'fields'      => array(
						'tracksure_batch_size',
						'tracksure_batch_timeout',
					),
				),

				// Attribution Settings.
				array(
					'id'          => 'attribution-config',
					'category'    => 'attribution',
					'title'       => __( 'Attribution', 'tracksure' ),
					'description' => __( 'First-touch and last-touch attribution', 'tracksure' ),
					'order'       => 10,
					'fields'      => array(
						'tracksure_attribution_window',
						'tracksure_attribution_model',
					),
				),

				// Advanced Settings (System).
				array(
					'id'          => 'advanced-system',
					'category'    => 'advanced',
					'title'       => __( 'System', 'tracksure' ),
					'description' => __( 'System configuration', 'tracksure' ),
					'order'       => 10,
					'fields'      => array(
						'keep_data_on_uninstall', // Toggle to preserve data when uninstalling
					),
				),
			),

			// Destinations: REMOVED - Single source of truth is Destinations Manager!
			// React reads destinations from Destinations Manager, not from here.
			// See: class-tracksure-free.php load_destination_handlers()

			// Integrations: REMOVED - Single source of truth is Integrations Manager!
			// React reads integrations from Integrations Manager, not from here.
			// See: class-tracksure-free.php register_integrations()
		)
	);
}
add_action( 'tracksure_register_admin_extensions', 'tracksure_free_register_admin_extensions' );
