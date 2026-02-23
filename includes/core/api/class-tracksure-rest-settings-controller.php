<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for settings diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure REST Settings Controller
 *
 * Handles settings read/write operations.
 * Endpoint: GET/PUT /settings
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Settings controller class.
 */
class TrackSure_REST_Settings_Controller extends TrackSure_REST_Controller
{



	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// Auto-clear caches when ANY TrackSure option is updated outside REST API.
		// This ensures cache consistency when settings are modified via WP-CLI, admin_ajax, etc.
		add_action('update_option', array($this, 'auto_clear_caches_on_option_update'), 10, 3);
	}

	/**
	 * Auto-clear caches when TrackSure options are updated.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	public function auto_clear_caches_on_option_update($option_name, $old_value, $new_value)
	{
		// Only clear for TrackSure options.
		if (strpos($option_name, 'tracksure_') !== 0) {
			return;
		}

		// Clear all TrackSure caches.
		delete_transient('tracksure_rest_settings');
		delete_transient('tracksure_js_config');
		wp_cache_delete('js_config', 'tracksure');
		wp_cache_delete('rest_settings', 'tracksure');
	}

	// No longer need hardcoded array - using TrackSure_Settings_Schema instead.
	/**
	 * Register routes.
	 */
	public function register_routes()
	{
		// GET /settings - Get settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_settings'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// PUT /settings - Update settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'update_settings'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => $this->get_settings_schema(),
			)
		);

		// POST /settings/regenerate-token - Regenerate API token.
		register_rest_route(
			$this->namespace,
			'/settings/regenerate-token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'regenerate_token'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings($request)
	{
		// Try cache first (5-minute transient).
		// NOTE: Settings are global, not user-specific. Use single cache key.
		$cache_key = 'tracksure_rest_settings';
		$cached    = get_transient($cache_key);

		if ($cached !== false && is_array($cached)) {
			return $this->prepare_success($cached);
		}

		// Start with ALL schema settings (with defaults for missing keys).
		$schema   = TrackSure_Settings_Schema::get_all_settings();
		$settings = array();

		// Populate with schema defaults, then override with saved values.
		foreach ($schema as $key => $meta) {
			// Only include settings exposed to REST API.
			if (! empty($meta['in_rest'])) {
				// Get saved value from database, with FALSE as default to distinguish from "not set".
				$saved_value = get_option($key, '__TRACKSURE_NOT_SET__');

				// If option exists in database, use it and properly type cast.
				if ($saved_value !== '__TRACKSURE_NOT_SET__') {
					// Type cast based on schema type.
					if ($meta['type'] === 'boolean') {
						// WordPress stores booleans as 1/0 (integer or string).
						// Strict boolean conversion for all possible representations.
						$settings[$key] = ($saved_value === 1 || $saved_value === '1' || $saved_value === true);
					} elseif ($meta['type'] === 'integer') {
						$settings[$key] = (int) $saved_value;
					} elseif ($meta['type'] === 'array' && ! is_array($saved_value)) {
						$settings[$key] = ! empty($saved_value) ? (array) $saved_value : array();
					} else {
						$settings[$key] = $saved_value;
					}
				} else {
					// Option not set in database, use schema default.
					$settings[$key] = $meta['default'];
				}
			}
		}

		/**
		 * Filter settings response.
		 *
		 * Free/Pro can add their own settings.
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings Settings array.
		 */
		$settings = apply_filters('tracksure_rest_get_settings', $settings);

		// Add enabled destinations using existing Destinations Manager.
		$core                 = TrackSure_Core::get_instance();
		$destinations_manager = $core->get_service('destinations');
		if ($destinations_manager) {
			$registered           = $destinations_manager->get_registered_destinations();
			$enabled_destinations = array();

			foreach ($registered as $dest_id => $dest_config) {
				if ($destinations_manager->is_destination_enabled($dest_id)) {
					$enabled_destinations[] = $dest_id;
				}
			}

			$settings['_enabled_destinations'] = $enabled_destinations;
		}

		// Add detected integrations using existing Integrations Manager.
		$integrations_manager = $core->get_service('integrations');
		if ($integrations_manager) {
			$registered            = $integrations_manager->get_registered_integrations();
			$detected_integrations = array();

			foreach ($registered as $integration_id => $integration_config) {
				if ($integrations_manager->is_integration_loaded($integration_id)) {
					$detected_integrations[] = $integration_id;
				}
			}

			$settings['_detected_integrations'] = $detected_integrations;
		}

		// Cache for 5 minutes (automatically cleared when settings updated).
		set_transient($cache_key, $settings, 5 * MINUTE_IN_SECONDS);

		return $this->prepare_success($settings);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings($request)
	{
		$updated = array();
		$changed = array();
		$errors  = array();
		$schema  = TrackSure_Settings_Schema::get_all_settings();
		$params  = $request->get_json_params();

		if (empty($params) || ! is_array($params)) {
			return $this->prepare_error(
				'invalid_request',
				'Invalid settings data'
			);
		}

		foreach ($params as $key => $value) {
			// Skip read-only/computed values (start with _, end with _detected, etc.).
			if (strpos($key, '_') === 0 || substr($key, -9) === '_detected') {
				// These are computed values added by GET endpoint - silently skip.
				continue;
			}

			// Get meta from schema (need it for type checking later).
			$meta = isset($schema[$key]) ? $schema[$key] : null;

			// Only accept settings that are in the schema.
			if (! $meta) {
				// Allow filter for extensions to add custom settings validation.
				$allowed = apply_filters('tracksure_rest_allow_setting', false, $key, $value);
				if (! $allowed) {
					$errors[] = $key;
					continue; // Skip unknown settings
				}
			} else {
				// Skip readonly settings.
				if (! empty($meta['readonly'])) {
					continue;
				}

				// Validate using schema.
				$validated = TrackSure_Settings_Schema::validate($key, $value);
				if (! $validated['valid']) {
					return $this->prepare_error(
						'invalid_setting',
						sprintf(
							'Invalid value for %s: %s',
							esc_html($key),
							esc_html($validated['error'])
						)
					);
				}

				// Normalize empty values based on type.
				if ($value === '' || $value === null) {
					if ($meta['type'] === 'boolean') {
						$value = 0;
					} elseif ($meta['type'] === 'integer') {
						$value = 0;
					} elseif ($meta['type'] === 'array') {
						$value = array();
					} else {
						$value = '';
					}
				}

				// Type cast for WordPress storage (always store consistent types).
				if ($meta['type'] === 'boolean') {
					// ALWAYS store as integer 1 or 0 for consistency.
					// Handle all truthy representations explicitly.
					$value = ($value === true || $value === 1 || $value === '1') ? 1 : 0;
				} elseif ($meta['type'] === 'integer') {
					$value = (int) $value;
				} elseif ($meta['type'] === 'array' && ! is_array($value)) {
					$value = (array) $value;
				}
			}

			// Get old value before updating.
			$old_value = get_option($key);

			// Normalize old value for accurate comparison.
			if ($meta && isset($meta['type'])) {
				if ($meta['type'] === 'boolean') {
					// Use same strict conversion as new value
					$old_value = ($old_value === 1 || $old_value === '1' || $old_value === true) ? 1 : 0;
				} elseif ($meta['type'] === 'integer') {
					$old_value = (int) $old_value;
				} elseif ($meta['type'] === 'string') {
					$old_value = (string) $old_value;
					$value     = (string) $value;
				}
			}

			// Only update if value actually changed (after normalization).
			if ($old_value !== $value) {
				// Update option.
				$result = update_option($key, $value);

				$updated[]       = $key;
				$changed[$key] = array(
					'old' => $old_value,
					'new' => $value,
				);

				// Fire individual setting change hook.
				do_action('tracksure_setting_changed', $key, $old_value, $value);

				// Fire specific hooks for critical settings.
				if ($key === 'tracksure_tracking_enabled') {
					do_action('tracksure_tracking_toggled', $value);
				}
			}
		}

		/**
		 * Fires after settings updated.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $updated Updated setting keys.
		 * @param WP_REST_Request $request Request object.
		 */
		do_action('tracksure_rest_update_settings', $updated, $request);

		/**
		 * Fires after settings batch updated.
		 *
		 * @since 1.0.0
		 *
		 * @param array $changed Array of changed settings with old and new values.
		 */
		if (! empty($changed)) {
			do_action('tracksure_settings_batch_updated', $changed);
		}

		// CRITICAL: Clear ALL caches after save to ensure fresh data.
		// This fixes the issue where refetch() after save returns stale cached data.
		delete_transient('tracksure_rest_settings');
		delete_transient('tracksure_js_config');
		delete_transient('tracksure_active_goals');

		// Clear WordPress object cache if available.
		wp_cache_delete('js_config', 'tracksure');
		wp_cache_delete('active_goals', 'tracksure');
		wp_cache_delete('rest_settings', 'tracksure');

		// Return error if any validation failed.
		if (! empty($errors)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[TrackSure REST] Settings validation errors: ' . implode(', ', $errors));
			}
			return $this->prepare_error(
				'invalid_parameters',
				'Invalid parameter(s): ' . implode(', ', $errors),
				400
			);
		}

		return $this->prepare_success(
			array(
				'success' => true,
				'updated' => $updated,
			)
		);
	}

	/**
	 * Regenerate API token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function regenerate_token($request)
	{
		$new_token = TrackSure_Installer::generate_token();
		update_option('tracksure_api_token', $new_token);

		return $this->prepare_success(
			array(
				'success'   => true,
				'api_token' => $new_token, // Use api_token to match field ID
			)
		);
	}

	/**
	 * Get settings schema (now generated from centralized schema).
	 *
	 * @return array
	 */
	private function get_settings_schema()
	{
		$schema = TrackSure_Settings_Schema::get_all_settings();
		$args   = array();

		foreach ($schema as $key => $meta) {
			if (empty($meta['in_rest']) || ! empty($meta['readonly'])) {
				continue;
			}

			$arg = array('type' => $meta['type']);

			// Add options if defined.
			if (isset($meta['options'])) {
				$arg['enum'] = array_keys($meta['options']);
			}

			// Add min/max for integers.
			if ($meta['type'] === 'integer') {
				if (isset($meta['min'])) {
					$arg['minimum'] = $meta['min'];
				}
				if (isset($meta['max'])) {
					$arg['maximum'] = $meta['max'];
				}
			}

			$args[$key] = $arg;
		}

		return $args;
	}
}
