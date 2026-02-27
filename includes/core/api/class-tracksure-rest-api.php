<?php

/**
 *
 * TrackSure REST API Base
 *
 * Registers routes and bootstraps API controllers.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure REST API class.
 */
class TrackSure_REST_API {






	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'ts/v1';

	/**
	 * Instance.
	 *
	 * @var TrackSure_REST_API
	 */
	private static $instance = null;

	/**
	 * Controllers.
	 *
	 * @var array
	 */
	private $controllers = array();

	/**
	 * Get instance.
	 *
	 * @return TrackSure_REST_API
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Load controllers.
		$this->load_controllers();

		// Register each controller's routes.
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}

		/**
		 * Fires after core routes registered.
		 *
		 * Use this to register additional routes (Free/Pro).
		 *
		 * @since 1.0.0
		 *
		 * @param string $namespace API namespace.
		 */
		do_action( 'tracksure_rest_api_init', self::NAMESPACE );
	}

	/**
	 * Load and instantiate REST API controllers.
	 * 
	 * Controllers are loaded in logical order:
	 * 1. Ingest - Event ingestion from browser/server
	 * 2. Query - Analytics and reporting queries
	 * 3. Goals - Conversion goal management
	 * 4. Settings - Plugin configuration
	 * 5. Registry - Event/parameter definitions (exposes events.json to browser)
	 * 6. Suggestions - Smart suggestions for admin UI
	 * 7. Consent - Privacy and consent management
	 * 8. Diagnostics - Health checks and debugging
	 * 9. Events - Event log viewing and filtering
	 * 10. Products - Product catalog for e-commerce
	 * 11. Quality - Data quality monitoring
	 * 12. Pixel Callback - Browser pixel confirmation tracking
	 * 
	 * Note: The registry controller enables client-side validation by exposing
	 * events.json and params.json to the browser SDK via REST API.
	 * 
	 * @since 1.0.0
	 * @since 1.1.0 Added Registry controller for client-side validation.
	 */
	private function load_controllers() {
		require_once __DIR__ . '/class-tracksure-rest-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-ingest-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-query-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-goals-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-settings-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-registry-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-diagnostics-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-events-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-products-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-quality-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-suggestions-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-pixel-callback-controller.php';
		require_once __DIR__ . '/class-tracksure-rest-consent-controller.php';

		$this->controllers = array(
			new TrackSure_REST_Ingest_Controller(),
			new TrackSure_REST_Query_Controller(),
			new TrackSure_REST_Goals_Controller(),
			new TrackSure_REST_Settings_Controller(),
			new TrackSure_REST_Registry_Controller(), // Registry API: Exposes events.json and params.json to browser/React
			new TrackSure_REST_Suggestions_Controller(),
			new TrackSure_REST_Consent_Controller(),
			new TrackSure_REST_Diagnostics_Controller(),
			new TrackSure_REST_Events_Controller(),
			new TrackSure_REST_Products_Controller(),
			new TrackSure_REST_Quality_Controller(),
			new TrackSure_REST_Pixel_Callback_Controller(),
		);

		/**
		 * Filter REST controllers.
		 *
		 * Allows Free/Pro/3rd party to register additional controllers.
		 *
		 * @since 1.0.0
		 *
		 * @param array $controllers Array of controller instances.
		 */
		$this->controllers = apply_filters( 'tracksure_rest_controllers', $this->controllers );
	}

	/**
	 * Get API namespace.
	 *
	 * @return string
	 */
	public static function get_namespace() {
		return self::NAMESPACE;
	}

	/**
	 * Check if request has valid admin permission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public static function check_admin_permission( $request = null ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				'You do not have permission to access this resource.',
				array( 'status' => 403 )
			);
		}
		return true;
	}
}
