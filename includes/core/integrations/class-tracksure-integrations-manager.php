<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only fires when WP_DEBUG is true

/**
 *
 * TrackSure Integrations Manager
 *
 * Centrally manages all integration handlers (WooCommerce, FluentCart, EDD, etc).
 * Auto-detects installed plugins and loads handlers based on settings.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Integrations Manager Class
 */
class TrackSure_Integrations_Manager
{



	/**
	 * Core instance.
	 *
	 * @var TrackSure_Core
	 */
	private $core;

	/**
	 * Registered integrations.
	 *
	 * @var array
	 */
	private $registered_integrations = array();

	/**
	 * Loaded integration handlers.
	 *
	 * @var array
	 */
	private $handlers = array();

	/**
	 * Constructor.
	 *
	 * @param TrackSure_Core $core Core instance.
	 */
	public function __construct($core)
	{
		$this->core = $core;

		// CRITICAL: Delay handler loading to 'init' hook to avoid translation loading conflicts.
		// Loading during 'plugins_loaded' can trigger WooCommerce autoloader too early,
		// causing "Translation loading for the woocommerce domain was triggered too early" notice.
		// WordPress 6.7+ requires translations to be loaded at 'init' action or later.
		add_action('init', array($this, 'load_handlers'), 5);
	}

	/**
	 * Load enabled integration handlers.
	 *
	 * Extensions (Free, Pro, 3rd-party) register handlers via action hook.
	 */
	public function load_handlers()
	{
		/**
		 * Allow modules to register integration handlers.
		 *
		 * Extensions should call $manager->register_integration() with handler details.
		 *
		 * @param TrackSure_Integrations_Manager $manager This manager instance.
		 */
		do_action('tracksure_load_integration_handlers', $this);

		// Load all registered integrations.
		foreach ($this->registered_integrations as $integration) {
			$this->load_integration_handler(
				$integration['id'],
				$integration['class_name'],
				$integration['file_path']
			);
		}
	}

	/**
	 * Register an integration handler.
	 *
	 * Called by Free/Pro/3rd-party modules via 'tracksure_load_integration_handlers' action.
	 *
	 * @param array $config Integration configuration array.
	 *   Required fields:
	 *   - id: string (e.g., 'woocommerce', 'fluentcart').
	 *   - name: string (e.g., 'WooCommerce').
	 *   - enabled_key: string (settings option key).
	 *   - class_name: string (handler class name).
	 *   - file_path: string (absolute path to handler file).
	 *   Optional fields:
	 *   - icon: string (Lucide icon name, default: 'Puzzle').
	 *   - order: int (display order, default: 999).
	 *   - auto_detect: string (plugin path for auto-detection, e.g., 'woocommerce/woocommerce.php').
	 *   - settings_fields: array (settings keys for this integration).
	 *   - tracked_events: array (event types this integration supports).
	 * @return bool Success.
	 */
	public function register_integration($config)
	{
		// Validate config is array.
		if (! is_array($config)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('TrackSure: register_integration() requires array config');
			}
			return false;
		}

		// Validate required fields.
		$required = array('id', 'name', 'enabled_key', 'class_name', 'file_path');
		foreach ($required as $field) {
			if (empty($config[$field])) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log("[TrackSure] Integrations Manager: Missing required field '{$field}' in registration");
				}
				return false;
			}
		}

		// Store FULL metadata for ALL integrations (enabled or disabled, installed or not).
		// React UI needs to know about all integrations to show toggles and detection status.
		// Handler loading happens separately based on enabled status and plugin detection.
		$this->registered_integrations[$config['id']] = array(
			'id'              => $config['id'],
			'name'            => $config['name'],
			'description'     => isset($config['description']) ? $config['description'] : '',
			'icon'            => isset($config['icon']) ? $config['icon'] : 'Puzzle',
			'order'           => isset($config['order']) ? (int) $config['order'] : 999,
			'enabled_key'     => $config['enabled_key'],
			'class_name'      => $config['class_name'],
			'file_path'       => $config['file_path'],
			'auto_detect'     => isset($config['auto_detect']) ? $config['auto_detect'] : '',
			'plugin_name'     => isset($config['plugin_name']) ? $config['plugin_name'] : $config['name'],
			'settings_fields' => isset($config['settings_fields']) ? $config['settings_fields'] : array(),
			'tracked_events'  => isset($config['tracked_events']) ? $config['tracked_events'] : array(),
		);

		return true;
	}

	/**
	 * Load an integration handler.
	 *
	 * Only loads if:
	 * 1. Integration is enabled in settings
	 * 2. Plugin is active (if auto_detect specified)
	 * 3. Handler class file exists
	 *
	 * @param string $integration_id Integration ID.
	 * @param string $class_name Handler class name.
	 * @param string $file_path Path to handler file.
	 * @return bool Success.
	 */
	public function load_integration_handler($integration_id, $class_name, $file_path)
	{
		// Check if integration is registered.
		if (! isset($this->registered_integrations[$integration_id])) {
			return false;
		}

		$integration = $this->registered_integrations[$integration_id];

		// Check if integration is enabled in settings.
		$is_enabled = get_option($integration['enabled_key'], true);
		if (! ($is_enabled === 1 || $is_enabled === '1' || $is_enabled === true)) {
			return false;
		}



		// Check if plugin is active (if auto_detect specified).
		if (! empty($integration['auto_detect']) && ! $this->is_plugin_active($integration['auto_detect'])) {
			return false;
		}


		// Check if already loaded.
		if (isset($this->handlers[$integration_id])) {
			return false;
		}

		// Check if file exists.
		if (! file_exists($file_path)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log(sprintf('[TrackSure] Integration handler file not found: %s', $file_path));
			}
			return false;
		}

		// Require the handler file.
		require_once $file_path;

		// Check if class exists.
		if (! class_exists($class_name)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log(sprintf('[TrackSure] Integration handler class not found: %s', $class_name));
			}
			return false;
		}

		// Instantiate the handler.
		try {
			$handler                           = new $class_name($this->core);
			$this->handlers[$integration_id] = $handler;

			/**
			 * Fires after an integration handler is loaded.
			 *
			 * @param string $integration_id Integration ID.
			 * @param object $handler Handler instance.
			 */
			do_action('tracksure_integration_handler_loaded', $integration_id, $handler);

			return true;
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log(sprintf('[TrackSure] Failed to load integration handler %s: %s', $class_name, $e->getMessage()));
			}
			return false;
		}
	}

	/**
	 * Check if a plugin is active.
	 *
	 * Supports both single plugin path (string) and multiple paths (array) for Free/Pro detection.
	 * This is the SINGLE SOURCE OF TRUTH for plugin detection logic in TrackSure.
	 *
	 * @param string|array $plugin_path Plugin file path or array of paths (for Free/Pro detection).
	 * @return bool True if any plugin is active.
	 */
	public function is_plugin_active($plugin_path)
	{
		if (! function_exists('is_plugin_active')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Support arrays for Free/Pro plugin detection.
		if (is_array($plugin_path)) {
			foreach ($plugin_path as $path) {
				if (is_plugin_active($path)) {
					return true;
				}
			}
			return false;
		}

		return is_plugin_active($plugin_path);
	}

	/**
	 * Get loaded handlers.
	 *
	 * @return array Loaded handlers.
	 */
	public function get_handlers()
	{
		return $this->handlers;
	}

	/**
	 * Get registered integrations.
	 *
	 * @return array Registered integrations metadata.
	 */
	public function get_registered_integrations()
	{
		return $this->registered_integrations;
	}

	/**
	 * Get enabled integration IDs.
	 *
	 * Simple array of IDs for quick checks.
	 *
	 * @return array Enabled integration IDs.
	 */
	public function get_enabled_integration_ids()
	{
		$enabled_ids = array();

		foreach ($this->registered_integrations as $integration_id => $config) {
			// Check if integration is enabled via settings.
			if (! empty($config['enabled_key'])) {
				$is_enabled = get_option($config['enabled_key'], true); // Default true for integrations

				// Normalize: true/1/'1' -> enabled, anything else -> disabled.
				if ((bool) $is_enabled && $is_enabled !== '0' && $is_enabled !== 0) {
					// Also check if plugin is active (if auto_detect specified).
					if (! empty($config['auto_detect']) && ! $this->is_plugin_active($config['auto_detect'])) {
						continue; // Plugin not active, skip
					}
					$enabled_ids[] = $integration_id;
				}
			}
		}

		return $enabled_ids;
	}

	/**
	 * Check if integration is loaded.
	 *
	 * @param string $integration_id Integration ID.
	 * @return bool True if loaded.
	 */
	public function is_integration_loaded($integration_id)
	{
		return isset($this->handlers[$integration_id]);
	}

	/**
	 * Get integration handler.
	 *
	 * @param string $integration_id Integration ID.
	 * @return object|null Handler instance or null.
	 */
	public function get_handler($integration_id)
	{
		return $this->handlers[$integration_id] ?? null;
	}
}
