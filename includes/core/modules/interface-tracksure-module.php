<?php

/**
 *
 * TrackSure Module Interface
 *
 * Base interface that all modules must implement.
 * Free and Pro plugins extend this to register capabilities.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module interface.
 */
interface TrackSure_Module_Interface {



	/**
	 * Get module ID.
	 *
	 * @return string Unique module identifier.
	 */
	public function get_id();

	/**
	 * Get module name.
	 *
	 * @return string Human-readable module name.
	 */
	public function get_name();

	/**
	 * Get module version.
	 *
	 * @return string Module version.
	 */
	public function get_version();

	/**
	 * Get module configuration.
	 *
	 * @return array Module configuration.
	 */
	public function get_config();

	/**
	 * Initialize module.
	 *
	 * Called when module is loaded by core.
	 */
	public function init();

	/**
	 * Register module capabilities.
	 *
	 * Registers dashboards, destinations, integrations, features.
	 */
	public function register_capabilities();
}
