<?php

/**
 *
 * TrackSure Settings Schema
 *
 * Centralized configuration for all plugin settings.
 * Single source of truth for PHP, REST API, JavaScript, and React admin.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Settings schema and configuration class.
 */
class TrackSure_Settings_Schema
{




	/**
	 * Get all settings definitions.
	 *
	 * @return array Settings schema.
	 */
	public static function get_all_settings()
	{
		$settings = array(
			// Core System Settings.
			'tracksure_version'            => array(
				'type'        => 'string',
				'readonly'    => true,
				'default'     => TRACKSURE_VERSION,
				'label'       => __('Plugin Version', 'tracksure'),
				'description' => __('Current installed version', 'tracksure'),
				'category'    => 'system',
				'in_rest'     => false,
				'in_js'       => false,
			),

			'tracksure_db_version'         => array(
				'type'        => 'string',
				'readonly'    => true,
				'default'     => TRACKSURE_DB_VERSION,
				'label'       => __('Database Version', 'tracksure'),
				'description' => __('Current database schema version', 'tracksure'),
				'category'    => 'system',
				'in_rest'     => false,
				'in_js'       => false,
			),

			'tracksure_keep_data_on_uninstall'       => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __('Keep Data on Uninstall', 'tracksure'),
				'description' => __('Preserve all event data and settings when uninstalling the plugin', 'tracksure'),
				'category'    => 'system',
				'in_rest'     => true,
				'in_js'       => false,
			),

			// Tracking Control.
			'tracksure_tracking_enabled'   => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __('Enable Tracking', 'tracksure'),
				'description' => __('Master switch to enable/disable all tracking', 'tracksure'),
				'category'    => 'tracking',
				'in_rest'     => true,
				'in_js'       => true,
				'js_key'      => 'trackingEnabled',
			),

			'tracksure_track_admins'       => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __('Track Administrators', 'tracksure'),
				'description' => __('Track logged-in administrators (useful for testing)', 'tracksure'),
				'category'    => 'tracking',
				'in_rest'     => true,
				'in_js'       => true,
				'js_key'      => 'trackAdmins',
			),

			'tracksure_session_timeout'    => array(
				'type'        => 'integer',
				'readonly'    => false,
				'default'     => 30,
				'label'       => __('Session Timeout', 'tracksure'),
				'description' => __('Session expiration time (minutes)', 'tracksure'),
				'category'    => 'tracking',
				'in_rest'     => true,
				'in_js'       => true,
				'js_key'      => 'sessionTimeout',
				'min'         => 5,
				'max'         => 120,
				'unit'        => 'minutes',
			),

			// Performance.
			'tracksure_batch_size'         => array(
				'type'        => 'integer',
				'readonly'    => false,
				'default'     => 10,
				'label'       => __('Batch Size', 'tracksure'),
				'description' => __('Number of events per batch', 'tracksure'),
				'category'    => 'performance',
				'in_rest'     => true,
				'in_js'       => true,
				'js_key'      => 'batchSize',
				'min'         => 1,
				'max'         => 50,
				'unit'        => 'events',
			),

			'tracksure_batch_timeout'      => array(
				'type'        => 'integer',
				'readonly'    => false,
				'default'     => 2000,
				'label'       => __('Batch Timeout', 'tracksure'),
				'description' => __('Time to wait before sending batch', 'tracksure'),
				'category'    => 'performance',
				'in_rest'     => true,
				'in_js'       => true,
				'js_key'      => 'batchTimeout',
				'min'         => 500,
				'max'         => 10000,
				'unit'        => 'milliseconds',
			),

			// Privacy & Compliance.
			'tracksure_respect_dnt'        => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __('Respect Do Not Track', 'tracksure'),
				'description' => __('Honor browser DNT header', 'tracksure'),
				'category'    => 'privacy',
				'in_rest'     => true,
				'in_js'       => true,
				'js_key'      => 'respectDNT',
			),

			'tracksure_anonymize_ip'       => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __('Anonymize IP Addresses', 'tracksure'),
				'description' => __('Remove last octet from IP addresses', 'tracksure'),
				'category'    => 'privacy',
				'in_rest'     => true,
				'in_js'       => false,
			),

			'tracksure_exclude_ips'        => array(
				'type'        => 'string',
				'readonly'    => false,
				'default'     => '',
				'label'       => __('Exclude IP Addresses', 'tracksure'),
				'description' => __('Comma-separated list of IPs to exclude', 'tracksure'),
				'category'    => 'privacy',
				'in_rest'     => true,
				'in_js'       => false,
				'placeholder' => '192.168.1.1, 10.0.0.1',
			),

			'tracksure_consent_mode'       => array(
				'type'        => 'string',
				'readonly'    => false,
				'default'     => 'disabled',
				'label'       => __('Consent Mode', 'tracksure'),
				'description' => __('GDPR/CCPA compliance mode - automatically detect based on visitor location or manually set', 'tracksure'),
				'help'        => __('Auto mode: Applies opt-in for EU/UK/CH/BR (GDPR/LGPD), opt-out for California (CCPA), disabled for others. Recommended for global sites.', 'tracksure'),
				'category'    => 'privacy',
				'in_rest'     => true,
				'in_js'       => true,
				'js_key'      => 'consentMode',
				'options'     => array(
					'disabled' => __('Disabled - No consent required', 'tracksure'),
					'opt-in'   => __('Opt-in - Explicit consent required (GDPR)', 'tracksure'),
					'opt-out'  => __('Opt-out - Track by default, allow opt-out (CCPA)', 'tracksure'),
					'auto'     => __('Auto - Detect based on visitor location (Recommended)', 'tracksure'),
				),
			),

			'tracksure_retention_days'     => array(
				'type'        => 'integer',
				'readonly'    => false,
				'default'     => 90,
				'label'       => __('Data Retention Period', 'tracksure'),
				'description' => __('How long to keep raw event data in days. Aggregated reports are preserved forever.', 'tracksure'),
				'help'        => __(
					'Recommended: 90 days for most sites .
					Increase to 365 days if you have low traffic or need long-term raw data analysis. Note: 100K-1M+ events may cause database bloat with longer retention.',
					'tracksure'
				),
				'category'    => 'privacy',
				'in_rest'     => true,
				'in_js'       => false,
				'min'         => 7,
				'max'         => 730,
				'unit'        => 'days',
			),

			// Attribution.
			'tracksure_attribution_window' => array(
				'type'        => 'integer',
				'readonly'    => false,
				'default'     => 30,
				'label'       => __('Attribution Window', 'tracksure'),
				'description' => __('Conversion attribution lookback period', 'tracksure'),
				'category'    => 'attribution',
				'in_rest'     => true,
				'in_js'       => false,
				'min'         => 1,
				'max'         => 90,
				'unit'        => 'days',
			),

			'tracksure_attribution_model'  => array(
				'type'        => 'string',
				'readonly'    => false,
				'default'     => 'last_touch',
				'label'       => __('Default Attribution Model', 'tracksure'),
				'description' => __('Model used for reporting (Free: first/last touch)', 'tracksure'),
				'category'    => 'attribution',
				'in_rest'     => true,
				'in_js'       => false,
				'options'     => array(
					'first_touch' => __('First Touch', 'tracksure'),
					'last_touch'  => __('Last Touch', 'tracksure'),
				),
			),
		);

		/**
		 * Allow extensions (Free/Pro/3rd-party) to add their settings.
		 *
		 * This filter enables complete separation of concerns:
		 * - Core provides infrastructure settings only
		 * - Free/Pro add their destination/integration settings
		 * - 3rd-party plugins can extend without core modifications
		 *
		 * @since 1.0.0
		 * @param array $settings Settings schema array.
		 */
		return apply_filters('tracksure_settings_schema', $settings);
	}

	/**
	 * Get settings for JavaScript config.
	 *
	 * @return array Settings to pass to browser.
	 */
	public static function get_js_config()
	{
		// Try cache first (5-minute transient, cleared when settings change).
		$cache_key = 'tracksure_js_config';
		$cached    = get_transient($cache_key);

		if ($cached !== false && is_array($cached)) {
			// Still need to add user data (changes per request).
			if (is_user_logged_in()) {
				$current_user   = wp_get_current_user();
				$cached['user'] = array(
					'email'      => $current_user->user_email,
					'first_name' => $current_user->first_name,
					'last_name'  => $current_user->last_name,
					'user_id'    => $current_user->ID,
				);
			}
			return $cached;
		}

		$schema = self::get_all_settings();
		$config = array();

		// Add endpoint (not in schema).
		$config['endpoint'] = rest_url('ts/v1/collect');
		$config['restUrl']  = rest_url();
		$config['nonce']    = wp_create_nonce('wp_rest');

		foreach ($schema as $key => $meta) {
			if (! empty($meta['in_js'])) {
				$js_key = $meta['js_key'] ?? $key;
				$value  = get_option($key, $meta['default']);

				// Type cast properly (consistent with REST API).
				if ($meta['type'] === 'boolean') {
					// Normalize: true/1/'1' -> true, anything else -> false.
					$value = (bool) $value && $value !== '0' && $value !== 0;
				} elseif ($meta['type'] === 'integer') {
					$value = (int) $value;
				}

				$config[$js_key] = $value;
			}
		}

		// Add enabled destinations and full metadata (centralized - single source of truth).
		$core                 = TrackSure_Core::get_instance();
		$destinations_manager = $core->get_service('destinations_manager');
		if ($destinations_manager) {
			// Simple array of enabled IDs for quick checks.
			$config['enabledDestinations'] = $destinations_manager->get_enabled_destination_ids();

			// Full metadata for React rendering (name, icon, order, reconciliation key).
			$config['destinationsMetadata'] = $destinations_manager->get_destinations_for_js_config();
		}

		// Add enabled integrations (IDs only — full metadata comes via trackSureExtensions).
		$integrations_manager = $core->get_service('integrations_manager');
		if ($integrations_manager) {
			$config['enabledIntegrations'] = $integrations_manager->get_enabled_integration_ids();
		}

		// Cache config (without user data) for 5 minutes.
		// Automatically cleared when settings updated via REST API.
		set_transient($cache_key, $config, 5 * MINUTE_IN_SECONDS);

		// Add logged-in user data (changes per request, don't cache).
		if (is_user_logged_in()) {
			$current_user   = wp_get_current_user();
			$config['user'] = array(
				'email'      => $current_user->user_email,
				'first_name' => $current_user->first_name,
				'last_name'  => $current_user->last_name,
				'user_id'    => $current_user->ID,
			);
		}

		return $config;
	}

	/**
	 * Get settings for REST API.
	 *
	 * @return array Settings exposed via REST.
	 */
	public static function get_rest_settings()
	{
		$schema   = self::get_all_settings();
		$settings = array();

		foreach ($schema as $key => $meta) {
			if (! empty($meta['in_rest'])) {
				$value = get_option($key, $meta['default']);

				// Type cast for proper REST API response.
				if ($meta['type'] === 'boolean') {
					$value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
				} elseif ($meta['type'] === 'integer') {
					$value = (int) $value;
				} elseif ($meta['type'] === 'array' && ! is_array($value)) {
					$value = ! empty($value) ? (array) $value : array();
				}

				$settings[$key] = $value;
			}
		}

		return $settings;
	}

	/**
	 * Get settings by category.
	 *
	 * @param string $category Category name.
	 * @return array Settings in category.
	 */
	public static function get_by_category($category)
	{
		$schema   = self::get_all_settings();
		$settings = array();

		foreach ($schema as $key => $meta) {
			if ($meta['category'] === $category) {
				$settings[$key] = $meta;
			}
		}

		return $settings;
	}

	/**
	 * Validate setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value to validate.
	 * @return array ['valid' => bool, 'value' => mixed, 'error' => string].
	 */
	public static function validate($key, $value)
	{
		$schema = self::get_all_settings();

		if (! isset($schema[$key])) {
			return array(
				'valid' => false,
				/* translators: %s is the setting key name */
				'error' => sprintf(__('Unknown setting: %s', 'tracksure'), $key),
			);
		}

		$meta = $schema[$key];

		// Check readonly.
		if (! empty($meta['readonly'])) {
			return array(
				'valid' => false,
				'error' => __('Setting is read-only', 'tracksure'),
			);
		}

		// Type validation.
		switch ($meta['type']) {
			case 'boolean':
				$value = (bool) $value;
				break;

			case 'integer':
				$value = (int) $value;
				if (isset($meta['min']) && $value < $meta['min']) {
					return array(
						'valid' => false,
						/* translators: %d is the minimum allowed value */
						'error' => sprintf(__('Value must be at least %d', 'tracksure'), $meta['min']),
					);
				}
				if (isset($meta['max']) && $value > $meta['max']) {
					return array(
						'valid' => false,
						/* translators: %d is the maximum allowed value */
						'error' => sprintf(__('Value must be at most %d', 'tracksure'), $meta['max']),
					);
				}
				break;

			case 'string':
				$value = (string) $value;
				if (isset($meta['options']) && ! in_array($value, array_keys($meta['options']), true)) {
					return array(
						'valid' => false,
						'error' => __('Invalid option selected', 'tracksure'),
					);
				}
				break;

			case 'array':
				if (! is_array($value)) {
					return array(
						'valid' => false,
						'error' => __('Value must be an array', 'tracksure'),
					);
				}
				break;
		}

		return array(
			'valid' => true,
			'value' => $value,
		);
	}

	/**
	 * Get default values.
	 *
	 * @return array Default values for all settings.
	 */
	public static function get_defaults()
	{
		$schema   = self::get_all_settings();
		$defaults = array();

		foreach ($schema as $key => $meta) {
			$defaults[$key] = $meta['default'];
		}

		return $defaults;
	}
}
