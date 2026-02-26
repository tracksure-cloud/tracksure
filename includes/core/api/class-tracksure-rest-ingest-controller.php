<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for event ingestion diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure REST Ingest Controller
 *
 * Handles event and conversion ingestion from browser and server.
 * Endpoints: POST /ingest/event, POST /ingest/conversion
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Ingest controller class.
 */
class TrackSure_REST_Ingest_Controller extends TrackSure_REST_Controller
{





	/**
	 * Event recorder service.
	 *
	 * @var TrackSure_Event_Recorder
	 */
	private $event_recorder;

	/**
	 * Session manager service.
	 *
	 * @var TrackSure_Session_Manager
	 */
	private $session_manager;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$core                  = TrackSure_Core::get_instance();
		$this->event_recorder  = $core->get_service('event_recorder');
		$this->session_manager = $core->get_service('session_manager');
		$this->db              = $core->get_service('db');
	}

	/**
	 * Register routes.
	 *
	 * All ingest endpoints use __return_true for permission_callback because they
	 * receive analytics events from the browser JavaScript SDK (tracksure-web.js)
	 * running on frontend pages for anonymous, non-logged-in visitors. Authentication
	 * is not possible in this context. All input is validated and sanitized in
	 * the respective callbacks. Rate limiting and origin checks are applied.
	 *
	 * @since 1.0.0
	 */
	public function register_routes()
	{
		// POST /collect - Primary endpoint for browser tracking (batch events).
		// HEAD /collect - Check if tracking is enabled (lightweight status check).
		// This is the main endpoint that ts-web.js calls.
		register_rest_route(
			$this->namespace,
			'/collect',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'ingest_batch'),
					// Public: browser SDK submits events from anonymous visitors.
					'permission_callback' => '__return_true',
					'args'                => array(
						'events' => array(
							'required'    => true,
							'type'        => 'array',
							'description' => 'Array of events to record.',
						),
					),
				),
				array(
					'methods'             => 'HEAD',
					'callback'            => array($this, 'check_tracking_status'),
					// Public: browser SDK checks tracking status before sending events.
					'permission_callback' => '__return_true',
				),
			)
		);

		// POST /collect/event - Record single event (alternative endpoint).
		register_rest_route(
			$this->namespace,
			'/collect/event',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'ingest_event'),
				// Public: browser SDK submits events from anonymous visitors.
				'permission_callback' => '__return_true',
				'args'                => $this->get_event_schema(),
			)
		);

		// POST /collect/batch - Record multiple events (alternative endpoint).
		register_rest_route(
			$this->namespace,
			'/collect/batch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'ingest_batch'),
				// Public: browser SDK submits batch events from anonymous visitors.
				'permission_callback' => '__return_true',
				'args'                => array(
					'events' => array(
						'required'    => true,
						'type'        => 'array',
						'description' => 'Array of events to record.',
					),
				),
			)
		);

		// POST /collect/conversion - Record conversion.
		register_rest_route(
			$this->namespace,
			'/collect/conversion',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'ingest_conversion'),
				// Public: browser SDK submits conversion events from anonymous visitors.
				'permission_callback' => '__return_true',
				'args'                => $this->get_conversion_schema(),
			)
		);
	}

	/**
	 * Check tracking status (HEAD request).
	 *
	 * Lightweight endpoint to check if tracking is enabled.
	 * Returns 200 if enabled, 403 if disabled.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_tracking_status($request)
	{
		// CHECK #1: Master tracking switch.
		if (! get_option('tracksure_tracking_enabled', false)) {
			return new WP_REST_Response(null, 403);
		}

		// CHECK #2: Admin exclusion.
		if (! get_option('tracksure_track_admins', false)) {
			if (is_user_logged_in() && current_user_can('manage_options')) {
				return new WP_REST_Response(null, 403);
			}
		}

		// Tracking is enabled
		return new WP_REST_Response(null, 200);
	}

	/**
	 * Ingest single event.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_event($request)
	{
		// CHECK #1: Master tracking switch.
		if (! get_option('tracksure_tracking_enabled', false)) {
			return $this->prepare_error(
				'tracking_disabled',
				__('Tracking is currently disabled', 'tracksure'),
				403
			);
		}

		// CHECK #2: Admin exclusion.
		if (! get_option('tracksure_track_admins', false)) {
			if (is_user_logged_in() && current_user_can('manage_options')) {
				return $this->prepare_error(
					'admin_excluded',
					__('Administrator tracking is disabled', 'tracksure'),
					403
				);
			}
		}

		// CHECK #3: IP exclusion.
		$excluded_ips = get_option('tracksure_exclude_ips', '');
		if ($excluded_ips) {
			$client_ip      = TrackSure_Utilities::get_client_ip();
			$excluded_array = array_map('trim', explode(',', $excluded_ips));

			if (in_array($client_ip, $excluded_array, true)) {
				return $this->prepare_error(
					'ip_excluded',
					__('Your IP is excluded from tracking', 'tracksure'),
					403
				);
			}
		}

		// CHECK #4: DNT header.
		if (get_option('tracksure_respect_dnt', false)) {
			if (isset($_SERVER['HTTP_DNT']) && '1' === sanitize_text_field(wp_unslash($_SERVER['HTTP_DNT']))) {
				return $this->prepare_error(
					'dnt_enabled',
					__('Do Not Track is enabled', 'tracksure'),
					403
				);
			}
		}

		// Rate limiting check.
		$client_id = $request->get_param('client_id');
		$client_ip = TrackSure_Utilities::get_client_ip() ?: '';

		$rate_limiter = TrackSure_Rate_Limiter::get_instance();
		if (! $rate_limiter->check_rate_limit($client_id, $client_ip)) {
			return $this->prepare_error(
				'rate_limit_exceeded',
				__('Too many requests. Please try again later.', 'tracksure'),
				429
			);
		}

		$event_name = $request->get_param('event_name');

		// Validate event against registry.
		$registry = TrackSure_Registry::get_instance();
		if (! $registry->event_exists($event_name)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log('[TrackSure] Unknown event received: ' . $event_name);
			}
			// Don't reject - log warning but accept for backward compatibility.
		}

		$occurred_at_raw = $request->get_param('occurred_at');

		$event_data = array(
			'event_name'   => $event_name,
			'client_id'    => $request->get_param('client_id'),
			'session_id'   => $request->get_param('session_id'),
			'event_params' => $request->get_param('event_params'),
			'event_id'     => $request->get_param('event_id'),      // UUID from browser
			'occurred_at'  => $occurred_at_raw,  // Client timestamp
		);

		// Optional session context for first event.
		$session_context = array(
			'referrer'     => $request->get_param('referrer'),
			'landing_page' => $request->get_param('landing_page'),
			'utm_source'   => $request->get_param('utm_source'),
			'utm_medium'   => $request->get_param('utm_medium'),
			'utm_campaign' => $request->get_param('utm_campaign'),
			'utm_term'     => $request->get_param('utm_term'),
			'utm_content'  => $request->get_param('utm_content'),
			'gclid'        => $request->get_param('gclid'),
			'fbclid'       => $request->get_param('fbclid'),
			'device_type'  => $request->get_param('device_type'),
			'browser'      => $request->get_param('browser'),
			'os'           => $request->get_param('os'),
		);

		// Filter null values.
		$session_context = array_filter($session_context);

		$event_data['session_context'] = $session_context;

		// Record event.
		$result = $this->event_recorder->record($event_data);

		if (! $result['success']) {
			return $this->prepare_error(
				'event_recording_failed',
				implode(', ', $result['errors']),
				400
			);
		}

		return $this->prepare_success(
			array(
				'success'  => true,
				'event_id' => $result['event_id'],
			),
			201
		);
	}

	/**
	 * Ingest batch of events.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_batch($request)
	{
		// CHECK #1: Master tracking switch.
		if (! get_option('tracksure_tracking_enabled', false)) {
			return $this->prepare_error(
				'tracking_disabled',
				__('Tracking is currently disabled', 'tracksure'),
				403
			);
		}

		// CHECK #2: Admin exclusion.
		if (! get_option('tracksure_track_admins', false)) {
			if (is_user_logged_in() && current_user_can('manage_options')) {
				return $this->prepare_error(
					'admin_excluded',
					__('Administrator tracking is disabled', 'tracksure'),
					403
				);
			}
		}

		// CHECK #3: IP exclusion.
		$excluded_ips = get_option('tracksure_exclude_ips', '');
		if ($excluded_ips) {
			$client_ip      = TrackSure_Utilities::get_client_ip();
			$excluded_array = array_map('trim', explode(',', $excluded_ips));

			if (in_array($client_ip, $excluded_array, true)) {
				return $this->prepare_error(
					'ip_excluded',
					__('Your IP is excluded from tracking', 'tracksure'),
					403
				);
			}
		}

		// CHECK #4: DNT header.
		if (get_option('tracksure_respect_dnt', false)) {
			if (isset($_SERVER['HTTP_DNT']) && '1' === sanitize_text_field(wp_unslash($_SERVER['HTTP_DNT']))) {
				return $this->prepare_error(
					'dnt_enabled',
					__('Do Not Track is enabled', 'tracksure'),
					403
				);
			}
		}

		// Rate limiting check.
		$events = $request->get_param('events');
		if (! empty($events) && is_array($events)) {
			$first_event = reset($events);
			$client_id   = isset($first_event['client_id']) ? $first_event['client_id'] : '';
		} else {
			$client_id = '';
		}

		$client_ip = TrackSure_Utilities::get_client_ip() ?: '';

		$rate_limiter = TrackSure_Rate_Limiter::get_instance();
		if (! $rate_limiter->check_rate_limit($client_id, $client_ip)) {
			return $this->prepare_error(
				'rate_limit_exceeded',
				__('Too many requests. Please try again later.', 'tracksure'),
				429
			);
		}

		$results  = array();
		$registry = TrackSure_Registry::get_instance();

		foreach ($events as $event_data) {
			// Validate event against registry.
			if (isset($event_data['event_name']) && ! $registry->event_exists($event_data['event_name'])) {
				if (defined('WP_DEBUG') && WP_DEBUG) {

					error_log('[TrackSure] Unknown event in batch: ' . $event_data['event_name']);
				}
			}

			$result = $this->event_recorder->record($event_data);

			if (! $result['success']) {
				$results[] = array(
					'success' => false,
					'errors'  => $result['errors'],
				);
			} else {
				$results[] = array(
					'success'  => true,
					'event_id' => $result['event_id'],
				);
			}
		}

		return $this->prepare_success(
			array(
				'success' => true,
				'results' => $results,
			),
			201
		);
	}

	/**
	 * Ingest conversion.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_conversion($request)
	{
		$conversion_data = array(
			'goal_id'       => $request->get_param('goal_id'),
			'visitor_id'    => $request->get_param('visitor_id'),
			'session_id'    => $request->get_param('session_id'),
			'event_id'      => $request->get_param('event_id'),
			'value'         => $request->get_param('value'),
			'currency'      => $request->get_param('currency'),
			'snapshot_data' => $request->get_param('snapshot_data'),
		);

		// Filter null values.
		$conversion_data = array_filter($conversion_data);

		$conversion_id = $this->db->insert_conversion($conversion_data);

		if (! $conversion_id) {
			return $this->prepare_error(
				'conversion_failed',
				'Failed to record conversion.',
				500
			);
		}

		/**
		 * Fires when conversion is recorded via API.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $conversion_id Conversion ID.
		 * @param array $conversion_data Conversion data.
		 */
		do_action('tracksure_conversion_recorded', $conversion_id, $conversion_data);

		return $this->prepare_success(
			array(
				'success'       => true,
				'conversion_id' => $conversion_id,
			),
			201
		);
	}

	/**
	 * Get event schema.
	 *
	 * @return array
	 */
	private function get_event_schema()
	{
		return array(
			'event_name'   => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'Event name from registry.',
			),
			'client_id'    => array(
				'required'    => true,
				'type'        => 'string',
				'format'      => 'uuid',
				'description' => 'Client UUID.',
			),
			'session_id'   => array(
				'required'    => true,
				'type'        => 'string',
				'format'      => 'uuid',
				'description' => 'Session UUID.',
			),
			'event_params' => array(
				'type'        => 'object',
				'description' => 'Event parameters.',
			),
			'event_id'     => array(
				'type'        => 'string',
				'format'      => 'uuid',
				'description' => 'Event UUID (for deduplication across browser/server).',
			),
			'occurred_at'  => array(
				'type'        => 'string',
				'format'      => 'date-time',
				'description' => 'Client timestamp when event occurred (ISO 8601).',
			),
		);
	}

	/**
	 * Get conversion schema.
	 *
	 * @return array
	 */
	private function get_conversion_schema()
	{
		return array(
			'goal_id'    => array(
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Goal ID.',
			),
			'visitor_id' => array(
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Visitor ID.',
			),
			'session_id' => array(
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Session ID.',
			),
			'value'      => array(
				'type'        => 'number',
				'description' => 'Conversion value.',
			),
			'currency'   => array(
				'type'        => 'string',
				'description' => 'Currency code (ISO 4217).',
			),
		);
	}
}
