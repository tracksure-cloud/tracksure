<?php

/**
 * Destinations manager for analytics platforms.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for destination routing diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Destinations Manager
 *
 * Centrally manages all destination handlers (Meta CAPI, GA4, etc).
 * Loads handlers dynamically based on extension registration and settings.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Destinations Manager Class
 */
class TrackSure_Destinations_Manager {






	/**
	 * Core instance.
	 *
	 * @var TrackSure_Core
	 */
	private $core;

	/**
	 * Registered destinations.
	 *
	 * @var array
	 */
	private $registered_destinations = array();

	/**
	 * Loaded destination handlers.
	 *
	 * @var array
	 */
	private $handlers = array();

	/**
	 * Constructor.
	 *
	 * @param TrackSure_Core $core Core instance.
	 */
	public function __construct( $core ) {
		$this->core = $core;

		// CRITICAL: Delay handler loading to 'init' hook for consistency with integrations.
		// This prevents potential translation loading conflicts with any destination
		// that might check for plugin classes (WooCommerce, etc).
		// WordPress 6.7+ requires translations to be loaded at 'init' action or later.
		add_action( 'init', array( $this, 'load_handlers' ), 5 );

		// Listen for events and distribute to handlers.
		add_action( 'tracksure_event_recorded', array( $this, 'distribute_event' ), 10, 2 );
	}

	/**
	 * Load enabled destination handlers.
	 *
	 * Extensions (Free, Pro, 3rd-party) register handlers via action hook.
	 */
	public function load_handlers() {
		/**
		 * Allow modules to register destination handlers.
		 *
		 * Extensions should call $manager->register_destination() with handler details.
		 *
		 * @param TrackSure_Destinations_Manager $manager This manager instance.
		 */
		do_action( 'tracksure_load_destination_handlers', $this );

		// Load all registered destinations.
		foreach ( $this->registered_destinations as $destination ) {
			$this->load_destination_handler(
				$destination['id'],
				$destination['class_name'],
				$destination['file_path']
			);
		}
	}

	/**
	 * Register a destination handler.
	 *
	 * Called by Free/Pro/3rd-party modules via 'tracksure_load_destination_handlers' action.
	 *
	 * NEW: Supports array-based registration for full metadata (recommended)
	 * OLD: Still supports positional parameters for backward compatibility
	 *
	 * @param string|array $dest_id_or_config Destination ID (old) or full config array (new).
	 * @param string       $enabled_key       Optional. Settings key (old method only).
	 * @param string       $class_name        Optional. Handler class (old method only).
	 * @param string       $file_path         Optional. Handler file path (old method only).
	 * @return bool Success.
	 */
	public function register_destination( $dest_id_or_config, $enabled_key = '', $class_name = '', $file_path = '' ) {
		// NEW METHOD: Array-based registration with full metadata.
		if ( is_array( $dest_id_or_config ) ) {
			$config = $dest_id_or_config;

			// Validate required fields.
			$required = array( 'id', 'name', 'enabled_key', 'class_name', 'file_path' );
			foreach ( $required as $field ) {
				if ( empty( $config[ $field ] ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

						error_log( "[TrackSure] Destinations Manager: Missing required field '{$field}' in registration" );
					}
					return false;
				}
			}

			// Store FULL metadata for ALL destinations (enabled or disabled).
			// React UI needs to know about all destinations to show toggles.
			$this->registered_destinations[ $config['id'] ] = array(
				'id'                 => $config['id'],
				'name'               => $config['name'],
				'description'        => isset( $config['description'] ) ? $config['description'] : '',
				'icon'               => isset( $config['icon'] ) ? $config['icon'] : 'Target',
				'order'              => isset( $config['order'] ) ? (int) $config['order'] : 999,
				'enabled_key'        => $config['enabled_key'],
				'class_name'         => $config['class_name'],
				'file_path'          => $config['file_path'],
				'settings_fields'    => isset( $config['settings_fields'] ) ? $config['settings_fields'] : array(),
				'reconciliation_key' => isset( $config['reconciliation_key'] ) ? $config['reconciliation_key'] : $config['id'],
				'custom_config'      => isset( $config['custom_config'] ) ? $config['custom_config'] : null, // Custom React component name
			);

			return true;
		}

		// OLD METHOD: Positional parameters (backward compatibility).
		$dest_id = $dest_id_or_config;

		// Store with minimal metadata (old format) - always register.
		$this->registered_destinations[ $dest_id ] = array(
			'id'                 => $dest_id,
			'class_name'         => $class_name,
			'file_path'          => $file_path,
			// Add defaults for missing metadata.
			'name'               => ucfirst( str_replace( array( '-', '_' ), ' ', $dest_id ) ),
			'icon'               => 'Target',
			'order'              => 999,
			'enabled_key'        => $enabled_key,
			'settings_fields'    => array(),
			'reconciliation_key' => $dest_id,
		);

		return true;
	}

	/**
	 * Load a destination handler.
	 *
	 * @param string $dest_id Destination ID.
	 * @param string $class_name Handler class name.
	 * @param string $file_path Path to handler file.
	 * @return bool Success.
	 */
	public function load_destination_handler( $dest_id, $class_name, $file_path ) {
		// Only load handler if destination is actually enabled in settings.
		if ( ! $this->is_destination_enabled( $dest_id ) ) {
			return false; // Registered but not enabled - skip loading class
		}

		// Check if already loaded.
		if ( isset( $this->handlers[ $dest_id ] ) ) {
			return false;
		}

		// Check if file exists.
		if ( ! file_exists( $file_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( sprintf( '[TrackSure] Destination handler file not found: %s', $file_path ) );
			}
			return false;
		}

		// Require the handler file.
		require_once $file_path;

		// Check if class exists.
		if ( ! class_exists( $class_name ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( sprintf( '[TrackSure] Destination handler class not found: %s', $class_name ) );
			}
			return false;
		}

		// Instantiate the handler.
		try {
			$handler                    = new $class_name( $this->core );
			$this->handlers[ $dest_id ] = $handler;

			/**
			 * Fires after a destination handler is loaded.
			 *
			 * @param string $dest_id Destination ID.
			 * @param object $handler Handler instance.
			 */
			do_action( 'tracksure_destination_handler_loaded', $dest_id, $handler );

			return true;
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( sprintf( '[TrackSure] Failed to load destination handler %s: %s', $class_name, $e->getMessage() ) );
			}
			return false;
		}
	}

	/**
	 * Get destinations metadata for JavaScript config.
	 *
	 * Returns ONLY the data needed by browser (no class/file paths).
	 * Used by Settings Schema to inject into window.trackSureConfig.
	 *
	 * @return array Destinations metadata for JS.
	 */
	public function get_destinations_for_js_config() {
		$js_destinations = array();

		foreach ( $this->registered_destinations as $dest_id => $dest ) {
			$js_destinations[ $dest_id ] = array(
				'id'                => $dest['id'],
				'name'              => $dest['name'],
				'icon'              => $dest['icon'],
				'order'             => $dest['order'],
				'reconciliationKey' => $dest['reconciliation_key'],
			);
		}

		return $js_destinations;
	}

	/**
	 * Get enabled destination IDs.
	 *
	 * Simple array of IDs for quick checks.
	 * Used by Settings Schema for enabledDestinations config.
	 *
	 * @return array Enabled destination IDs.
	 */
	public function get_enabled_destination_ids() {
		$enabled_ids = array();

		foreach ( $this->registered_destinations as $dest_id => $config ) {
			// Check if destination is enabled via settings.
			if ( ! empty( $config['enabled_key'] ) ) {
				$is_enabled = get_option( $config['enabled_key'], false );

				// Normalize: true/1/'1' -> enabled, anything else -> disabled.
				if ( (bool) $is_enabled && $is_enabled !== '0' && $is_enabled !== 0 ) {
					$enabled_ids[] = $dest_id;
				}
			}
		}

		return $enabled_ids;
	}

	/**
	 * Distribute event to all enabled destination handlers.
	 *
	 * @param int   $event_id Event ID from database.
	 * @param array $event_data Event data.
	 */
	public function distribute_event( $event_id, $event_data ) {
		if ( empty( $this->handlers ) ) {
			return; // No handlers loaded.
		}

		foreach ( $this->handlers as $dest_id => $handler ) {
			// Check if handler has the method to receive events.
			if ( ! method_exists( $handler, 'handle_event' ) ) {
				continue;
			}

			try {
				$handler->handle_event( $event_id, $event_data );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

					error_log( sprintf( '[TrackSure] Error distributing event to %s: %s', $dest_id, $e->getMessage() ) );
				}
			}
		}
	}

	/**
	 * Get loaded handlers.
	 *
	 * @return array Loaded handlers.
	 */
	public function get_handlers() {
		return $this->handlers;
	}

	/**
	 * Get registered destinations.
	 *
	 * @return array Registered destinations metadata.
	 */
	public function get_registered_destinations() {
		return $this->registered_destinations;
	}

	/**
	 * Check if destination is enabled.
	 *
	 * @param string $dest_id Destination ID.
	 * @return bool True if enabled.
	 */
	/**
	 * Check if a destination is enabled in settings.
	 *
	 * @param string $dest_id Destination ID.
	 * @return bool True if enabled.
	 */
	public function is_destination_enabled( $dest_id ) {
		// Check if destination is registered.
		if ( ! isset( $this->registered_destinations[ $dest_id ] ) ) {
			return false;
		}

		// Get the enabled_key from registration.
		$enabled_key = $this->registered_destinations[ $dest_id ]['enabled_key'];

		// Check the option value (WordPress stores booleans as 1/0).
		$is_enabled = get_option( $enabled_key, false );

		// Normalize: true/1/'1' -> true, anything else -> false.
		$result = (bool) $is_enabled && $is_enabled !== '0' && $is_enabled !== 0;

		return $result;
	}
}
