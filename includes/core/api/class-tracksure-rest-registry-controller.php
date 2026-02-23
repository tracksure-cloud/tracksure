<?php

/**
 *
 * TrackSure REST Registry Controller
 *
 * Exposes event and parameter registry to browser SDK and React admin.
 * 
 * This controller enables the registry-driven event architecture by providing
 * client-side access to events.json and params.json definitions. The browser
 * SDK (tracksure-web.js) loads the registry on initialization to enable
 * client-side validation and provide event metadata.
 * 
 * Endpoints:
 * - GET /registry/events - Get all event definitions
 * - GET /registry/params - Get all parameter definitions
 * - GET /registry - Get full registry (events + params + version)
 * - GET /registry/validate/{event} - Validate specific event name
 * 
 * All endpoints are public (no authentication required) to support browser SDK.
 * Data is cached server-side for optimal performance.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 * @since 1.1.0 Activated in controllers array for client-side validation.
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Registry REST controller class.
 */
class TrackSure_REST_Registry_Controller extends TrackSure_REST_Controller
{



	/**
	 * Register REST API routes.
	 * 
	 * All routes use __return_true for permission_callback because the browser
	 * JavaScript SDK (tracksure-web.js) needs to load event/parameter definitions
	 * from anonymous, non-logged-in visitors. This data is read-only and already
	 * publicly available in the shipped events.json/params.json files. No sensitive
	 * data is exposed.
	 *
	 * @since 1.0.0
	 */
	public function register_routes()
	{
		// GET /registry/events - Get events registry.
		register_rest_route(
			$this->namespace,
			'/registry/events',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_events'),
				// Public: read-only data used by browser SDK, no sensitive content.
				'permission_callback' => '__return_true',
			)
		);

		// GET /registry/params - Get parameters registry.
		register_rest_route(
			$this->namespace,
			'/registry/params',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_params'),
				// Public: read-only data used by browser SDK, no sensitive content.
				'permission_callback' => '__return_true',
			)
		);

		// GET /registry - Get full registry (events + params).
		register_rest_route(
			$this->namespace,
			'/registry',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_full_registry'),
				// Public: read-only data used by browser SDK, no sensitive content.
				'permission_callback' => '__return_true',
			)
		);

		// GET /registry/validate/{event} - Validate event name.
		register_rest_route(
			$this->namespace,
			'/registry/validate/(?P<event>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'validate_event'),
				// Public: read-only validation used by browser SDK, no sensitive content.
				'permission_callback' => '__return_true',
				'args'                => array(
					'event' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get events registry.
	 * 
	 * Returns all event definitions as a map (event_name => definition).
	 * Includes count for convenience.
	 * 
	 * Example response:
	 * {
	 *   "success": true,
	 *   "data": {
	 *     "events": {
	 *       "page_view": { "name": "page_view", "display_name": "Viewed Page", ... },
	 *       "add_to_cart": { "name": "add_to_cart", "display_name": "Added to Cart", ... }
	 *     },
	 *     "count": 50
	 *   }
	 * }
	 *
	 * @since 1.0.0
	 * 
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_events($request)
	{
		$registry = TrackSure_Registry::get_instance();
		$events   = $registry->get_events();

		// Transform for easy lookup.
		$events_map = array();
		foreach ($events as $event) {
			$events_map[$event['name']] = $event;
		}

		return $this->prepare_success(
			array(
				'events' => $events_map,
				'count'  => count($events),
			)
		);
	}

	/**
	 * Get parameters registry.
	 * 
	 * Returns all parameter definitions as a map (param_name => definition).
	 * Includes count for convenience.
	 * 
	 * Example response:
	 * {
	 *   "success": true,
	 *   "data": {
	 *     "parameters": {
	 *       "page_url": { "name": "page_url", "type": "string", "description": "...", ... },
	 *       "item_id": { "name": "item_id", "type": "string", ... }
	 *     },
	 *     "count": 120
	 *   }
	 * }
	 *
	 * @since 1.0.0
	 * 
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_params($request)
	{
		$registry = TrackSure_Registry::get_instance();
		$params   = $registry->get_parameters();

		// Transform for easy lookup.
		$params_map = array();
		foreach ($params as $param) {
			$params_map[$param['name']] = $param;
		}

		return $this->prepare_success(
			array(
				'parameters' => $params_map,
				'count'      => count($params),
			)
		);
	}

	/**
	 * Get full registry (events + parameters + version).
	 * 
	 * Primary endpoint used by browser SDK for client-side validation.
	 * Returns complete registry in single request for efficient initialization.
	 * 
	 * Response includes events map, parameters map, and schema version.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Now actively used by browser SDK (tracksure-web.js).
	 * 
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Registry data with events, parameters, and version.
	 */
	public function get_full_registry($request)
	{
		$registry = TrackSure_Registry::get_instance();
		$events   = $registry->get_events();
		$params   = $registry->get_parameters();

		// Transform for easy lookup.
		$events_map = array();
		foreach ($events as $event) {
			$events_map[$event['name']] = $event;
		}

		$params_map = array();
		foreach ($params as $param) {
			$params_map[$param['name']] = $param;
		}

		return $this->prepare_success(
			array(
				'events'     => $events_map,
				'parameters' => $params_map,
				'version'    => '1.0.0',
			)
		);
	}

	/**
	 * Validate event name exists in registry.
	 * 
	 * Utility endpoint for admin UI and debugging.
	 * Checks if an event name is registered and returns its definition.
	 * 
	 * Example successful response (valid event):
	 * success: true, data: { valid: true, event: {...} }
	 * 
	 * Example failed response (invalid event):
	 * success: true, data: { valid: false, message: "Event not registered" }
	 *
	 * @since 1.0.0
	 * 
	 * @param WP_REST_Request $request Request object with 'event' parameter.
	 * @return WP_REST_Response
	 */
	public function validate_event($request)
	{
		$event_name = $request->get_param('event');
		$registry   = TrackSure_Registry::get_instance();

		$is_valid = $registry->event_exists($event_name);

		if ($is_valid) {
			$event = $registry->get_event($event_name);

			return $this->prepare_success(
				array(
					'valid' => true,
					'event' => $event,
				)
			);
		}

		return $this->prepare_success(
			array(
				'valid'   => false,
				'message' => sprintf('Event "%s" is not registered', $event_name),
			)
		);
	}
}
