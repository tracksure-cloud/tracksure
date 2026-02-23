<?php

/**
 *
 * TrackSure REST Suggestions Controller
 *
 * REST API endpoint for Smart Insights suggestions.
 * 
 * Architecture (Clean Separation):
 * - This file: REST API layer (routing, permissions, request/response formatting)
 * - Engine file: Business logic (12 rule-based checks, SQL queries, thresholds)
 *
 * Why two files?
 * - Single Responsibility Principle
 * - API layer can change without touching business logic
 * - Engine can be called from multiple sources (REST API, CLI, cron jobs)
 * - Easier testing (mock API, test engine separately)
 *
 * Flow:
 * 1. Frontend calls: GET /wp-json/tracksure/v1/suggestions?limit=10
 * 2. This controller validates permissions & parameters
 * 3. Calls TrackSure_Suggestion_Engine->get_suggestions()
 * 4. Returns formatted JSON response
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suggestions controller class.
 */
class TrackSure_REST_Suggestions_Controller extends TrackSure_REST_Controller {



	/**
	 * Suggestion engine instance.
	 *
	 * @var TrackSure_Suggestion_Engine
	 */
	private $engine;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->engine = TrackSure_Suggestion_Engine::get_instance();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /suggestions - Get actionable suggestions.
		register_rest_route(
			$this->namespace,
			'/suggestions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_suggestions' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 5,
						'minimum' => 1,
						'maximum' => 20,
					),
				),
			)
		);
	}

	/**
	 * Get suggestions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_suggestions( $request ) {
		$limit = absint( $request->get_param( 'limit' ) );

		$suggestions = $this->engine->get_suggestions( $limit );

		return $this->prepare_success( $suggestions );
	}
}
