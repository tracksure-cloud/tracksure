<?php

/**
 *
 * TrackSure Module Registry
 *
 * Manages module registration and capability system.
 * Free/Pro plugins register via do_action('tracksure_register_module').
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module registry class.
 */
class TrackSure_Module_Registry {


	/**
	 * Instance.
	 *
	 * @var TrackSure_Module_Registry
	 */
	private static $instance = null;

	/**
	 * Registered modules.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Registered capabilities.
	 *
	 * @var array
	 */
	private $capabilities = array(
		'dashboards'   => array(),
		'destinations' => array(),
		'integrations' => array(),
		'features'     => array(),
	);

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Module_Registry
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Load persisted modules.
		$this->load_persisted_modules();
	}

	/**
	 * Register module.
	 *
	 * @param string $module_id Module ID.
	 * @param string $module_path Module file path.
	 * @param array  $module_config Module configuration.
	 * @return bool Success.
	 */
	public function register_module( $module_id, $module_path, $module_config = array() ) {
		if ( isset( $this->modules[ $module_id ] ) ) {
			return false; // Already registered.
		}

		// Validate module file exists.
		if ( ! file_exists( $module_path ) ) {
			return false;
		}

		// Store module.
		$this->modules[ $module_id ] = array(
			'id'       => $module_id,
			'path'     => $module_path,
			'config'   => $module_config,
			'loaded'   => false,
			'instance' => null,
		);

		// Persist to database.
		$this->persist_modules();

		/**
		 * Fires when module is registered.
		 *
		 * @since 1.0.0
		 *
		 * @param string $module_id Module ID.
		 * @param array  $module_data Module data.
		 */
		do_action( 'tracksure_module_registered', $module_id, $this->modules[ $module_id ] );

		return true;
	}

	/**
	 * Load module.
	 *
	 * @param string $module_id Module ID.
	 * @return bool Success.
	 */
	public function load_module( $module_id ) {
		if ( ! isset( $this->modules[ $module_id ] ) ) {
			return false;
		}

		if ( $this->modules[ $module_id ]['loaded'] ) {
			return true; // Already loaded.
		}

		// Require module file.
		require_once $this->modules[ $module_id ]['path'];

		// Try to instantiate module class.
		$module_class = $this->get_module_class_name( $module_id );

		if ( class_exists( $module_class ) ) {
			$instance = new $module_class();

			// Check if implements interface.
			if ( $instance instanceof TrackSure_Module_Interface ) {
				$this->modules[ $module_id ]['instance'] = $instance;
				$this->modules[ $module_id ]['loaded']   = true;

				// Initialize module.
				$instance->init();

				// Register capabilities.
				$instance->register_capabilities();

				/**
				 * Fires when module is loaded.
				 *
				 * @since 1.0.0
				 *
				 * @param string $module_id Module ID.
				 * @param object $instance Module instance.
				 */
				do_action( 'tracksure_module_loaded', $module_id, $instance );

				return true;
			}
		}

		return false;
	}

	/**
	 * Load all registered modules.
	 */
	public function load_all_modules() {
		foreach ( array_keys( $this->modules ) as $module_id ) {
			$this->load_module( $module_id );
		}
	}

	/**
	 * Register capability.
	 *
	 * @param string $type Capability type (dashboards/destinations/integrations/features).
	 * @param string $id Capability ID.
	 * @param array  $config Capability configuration.
	 * @return bool Success.
	 */
	public function register_capability( $type, $id, $config ) {
		if ( ! isset( $this->capabilities[ $type ] ) ) {
			return false;
		}

		$this->capabilities[ $type ][ $id ] = $config;

		/**
		 * Fires when capability is registered.
		 *
		 * @since 1.0.0
		 *
		 * @param string $type Capability type.
		 * @param string $id Capability ID.
		 * @param array  $config Capability config.
		 */
		do_action( 'tracksure_capability_registered', $type, $id, $config );

		return true;
	}

	/**
	 * Get capabilities by type.
	 *
	 * @param string $type Capability type.
	 * @return array Capabilities.
	 */
	public function get_capabilities( $type ) {
		return isset( $this->capabilities[ $type ] ) ? $this->capabilities[ $type ] : array();
	}

	/**
	 * Get all capabilities.
	 *
	 * @return array All capabilities.
	 */
	public function get_all_capabilities() {
		return $this->capabilities;
	}

	/**
	 * Get registered modules.
	 *
	 * @return array Modules.
	 */
	public function get_modules() {
		return $this->modules;
	}

	/**
	 * Get module instance.
	 *
	 * @param string $module_id Module ID.
	 * @return object|null Module instance or null.
	 */
	public function get_module( $module_id ) {
		if ( isset( $this->modules[ $module_id ]['instance'] ) ) {
			return $this->modules[ $module_id ]['instance'];
		}
		return null;
	}

	/**
	 * Check if module is loaded.
	 *
	 * @param string $module_id Module ID.
	 * @return bool True if loaded.
	 */
	public function is_module_loaded( $module_id ) {
		return isset( $this->modules[ $module_id ]['loaded'] ) && $this->modules[ $module_id ]['loaded'];
	}

	/**
	 * Get module class name from ID.
	 *
	 * @param string $module_id Module ID.
	 * @return string Class name.
	 */
	private function get_module_class_name( $module_id ) {
		// Convert module_id to class name: tracksure-free -> TrackSure_Free_Module.
		$parts = explode( '-', $module_id );
		$parts = array_map( 'ucfirst', $parts );
		return implode( '_', $parts ) . '_Module';
	}

	/**
	 * Persist modules to database.
	 */
	private function persist_modules() {
		$modules_data = array();

		foreach ( $this->modules as $module_id => $module_data ) {
			$modules_data[ $module_id ] = array(
				'id'     => $module_data['id'],
				'path'   => $module_data['path'],
				'config' => $module_data['config'],
			);
		}

		update_option( 'tracksure_registered_modules', $modules_data );
	}

	/**
	 * Load persisted modules from database.
	 */
	private function load_persisted_modules() {
		$modules_data = get_option( 'tracksure_registered_modules', array() );

		foreach ( $modules_data as $module_id => $module_data ) {
			if ( file_exists( $module_data['path'] ) ) {
				$this->modules[ $module_id ] = array(
					'id'       => $module_data['id'],
					'path'     => $module_data['path'],
					'config'   => $module_data['config'],
					'loaded'   => false,
					'instance' => null,
				);
			}
		}
	}
}
