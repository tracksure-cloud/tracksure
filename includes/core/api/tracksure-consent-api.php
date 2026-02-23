<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging for consent plugin integration diagnostics

/**
 *
 * TrackSure Public API - Consent Management
 *
 * Public functions for 3rd party consent plugin integration.
 *
 * @package TrackSure\Core
 * @since 1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a custom consent plugin with TrackSure.
 *
 * Allows 3rd party consent management plugins to integrate with TrackSure
 * without modifying core files.
 *
 * Example usage:
 * ```php
 * add_action('init', function() {
 *     tracksure_register_consent_plugin('my_consent_plugin', function() {
 *         return isset($_COOKIE['my_plugin_consent']) && $_COOKIE['my_plugin_consent'] === 'granted';
 *     });
 * });
 * ```
 *
 * @param string   $plugin_id Unique plugin identifier (lowercase, alphanumeric + underscores).
 * @param callable $callback  Function that returns true if consent is granted, false otherwise.
 * @return bool True if registration successful, false on error.
 */
function tracksure_register_consent_plugin( $plugin_id, $callback ) {
	// Validate plugin ID.
	if ( ! is_string( $plugin_id ) || empty( $plugin_id ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TrackSure] Invalid consent plugin ID: must be non-empty string' );
		}
		return false;
	}

	// Validate callback.
	if ( ! is_callable( $callback ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[TrackSure] Invalid consent plugin callback for '{$plugin_id}': must be callable" );
		}
		return false;
	}

	// Register with Consent Manager.
	$consent_manager = TrackSure_Consent_Manager::get_instance();
	return $consent_manager->register_plugin( $plugin_id, $callback );
}

/**
 * Check if user has granted tracking consent.
 *
 * This is the main function 3rd party code should use to check consent status.
 *
 * @return bool True if tracking is allowed, false otherwise.
 */
function tracksure_is_tracking_allowed() {
	$consent_manager = TrackSure_Consent_Manager::get_instance();
	return $consent_manager->is_tracking_allowed();
}

/**
 * Get current consent mode.
 *
 * @return string Consent mode: 'disabled', 'opt-in', 'opt-out', or 'auto'.
 */
function tracksure_get_consent_mode() {
	$consent_manager = TrackSure_Consent_Manager::get_instance();
	return $consent_manager->get_consent_mode();
}

/**
 * Check if site has a consent management plugin installed.
 *
 * @return bool True if consent plugin detected, false otherwise.
 */
function tracksure_has_consent_plugin() {
	$consent_manager = TrackSure_Consent_Manager::get_instance();
	return $consent_manager->has_consent_plugin();
}

/**
 * Get consent warning status for React admin panel.
 *
 * Returns data needed to show consent plugin warning in React UI.
 * React admin should call this via REST API and render the notice.
 *
 * @return array|null Warning data or null if no warning needed.
 */
function tracksure_get_consent_warning_status() {
	// Check if consent is required.
	$consent_mode = get_option( 'tracksure_consent_mode', 'disabled' );
	if ( $consent_mode === 'disabled' ) {
		return null; // Consent not required.
	}

	// Check if consent plugin is detected.
	if ( tracksure_has_consent_plugin() ) {
		return null; // Consent plugin found - all good.
	}

	// Check if user dismissed this notice.
	$user_id = get_current_user_id();
	if ( get_user_meta( $user_id, 'tracksure_consent_warning_dismissed', true ) ) {
		return null; // User dismissed the notice.
	}

	// Return warning data for React admin to display.
	return array(
		'show_warning'        => true,
		'consent_mode'        => $consent_mode,
		'message'             => __( 'No consent management plugin was detected on your site.', 'tracksure' ),
		'recommended_plugins' => array(
			array(
				'name'     => 'Cookie Notice by dFactory',
				'installs' => '5M+',
				'url'      => 'https://wordpress.org/plugins/cookie-notice/',
			),
			array(
				'name'     => 'GDPR Cookie Consent by WebToffee',
				'installs' => '800K+',
				'url'      => 'https://wordpress.org/plugins/cookie-law-info/',
			),
			array(
				'name'     => 'Cookiebot',
				'installs' => 'Enterprise',
				'url'      => 'https://www.cookiebot.com/',
			),
			array(
				'name'     => 'Complianz',
				'installs' => 'GDPR/CCPA',
				'url'      => 'https://wordpress.org/plugins/complianz-gdpr/',
			),
		),
		'alternatives'        => array(
			__( 'Change consent mode to "Disabled" in Privacy Settings if your country doesn\'t require consent', 'tracksure' ),
			__( 'Use a 3rd party consent service (Osano, OneTrust, Usercentrics, etc.)', 'tracksure' ),
		),
		'info_message'        => __( 'Without a consent plugin, TrackSure will anonymize user data in opt-in mode to ensure GDPR compliance while maintaining 100% event tracking.', 'tracksure' ),
	);
}

// Deprecated AJAX handler removed in v1.0.2
// Use REST API endpoint /tracksure/v1/consent/warning/dismiss instead.
