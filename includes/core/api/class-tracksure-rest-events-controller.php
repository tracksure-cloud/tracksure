<?php

/**
 *
 * TrackSure REST Events Controller
 *
 * Handles manual event submission for testing/diagnostics.
 * This is separate from the main ingest endpoint to provide
 * admin-only event creation with nonce-based authentication.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Events controller class.
 */
class TrackSure_REST_Events_Controller extends TrackSure_REST_Controller
{



	/**
	 * Event recorder service.
	 *
	 * @var TrackSure_Event_Recorder
	 */
	private $event_recorder;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$core                 = TrackSure_Core::get_instance();
		$this->event_recorder = $core->get_service('event_recorder');
	}

	/**
	 * Register routes.
	 */
	public function register_routes()
	{
		// POST /events - Create test event(s) from admin.
		register_rest_route(
			$this->namespace,
			'/events',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_events'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => array(
					'events' => array(
						'required'          => true,
						'type'              => 'array',
						'description'       => 'Array of event objects to create',
						'validate_callback' => array($this, 'validate_events'),
					),
				),
			)
		);
	}

	/**
	 * Validate events array.
	 *
	 * @param mixed           $param   Parameter value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $key     Parameter key.
	 * @return bool|WP_Error
	 */
	public function validate_events($param, $request, $key)
	{
		if (! is_array($param) || empty($param)) {
			return new WP_Error(
				'invalid_events',
				'Events must be a non-empty array'
			);
		}

		foreach ($param as $event) {
			if (! is_array($event) || ! isset($event['event_name'])) {
				return new WP_Error(
					'invalid_event',
					'Each event must have an event_name'
				);
			}
		}

		return true;
	}

	/**
	 * Create test events.
	 *
	 * Accepts events from diagnostics page for testing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_events($request)
	{
		$events = $request->get_param('events');

		if (! $this->event_recorder) {
			return $this->prepare_error(
				'service_unavailable',
				'Event recorder service not available',
				503
			);
		}

		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		foreach ($events as $event_data) {
			try {
				// Enrich event with admin context.
				$event_data = $this->enrich_test_event($event_data);

				// Record event.
				$result = $this->event_recorder->record($event_data);

				if ($result['success']) {
					$results['success'][] = array(
						'event_name' => $event_data['event_name'],
						'event_id'   => $result['event_id'],
					);
				} else {
					$results['failed'][] = array(
						'event_name' => $event_data['event_name'],
						'error'      => implode(', ', $result['errors'] ?? array('Unknown error')),
					);
				}
			} catch (Exception $e) {
				$results['failed'][] = array(
					'event_name' => $event_data['event_name'] ?? 'unknown',
					'error'      => $e->getMessage(),
				);
			}
		}

		return $this->prepare_success(
			array(
				'success_count' => count($results['success']),
				'failed_count'  => count($results['failed']),
				'results'       => $results,
			)
		);
	}

	/**
	 * Enrich test event with context.
	 *
	 * Adds admin user context and test metadata.
	 *
	 * @param array $event_data Event data.
	 * @return array Enriched event data.
	 */
	private function enrich_test_event($event_data)
	{
		$current_user = wp_get_current_user();

		// Add required fields if missing.
		if (! isset($event_data['occurred_at'])) {
			$event_data['occurred_at'] = gmdate('Y-m-d H:i:s'); // UTC
		}

		if (! isset($event_data['dedupe_key'])) {
			$event_data['dedupe_key'] = 'test_' . wp_generate_uuid4();
		}

		if (! isset($event_data['client_id'])) {
			$event_data['client_id'] = wp_generate_uuid4();
		}

		if (! isset($event_data['session_id'])) {
			$event_data['session_id'] = wp_generate_uuid4();
		}

		// Initialize event_params if not set.
		if (! isset($event_data['event_params'])) {
			$event_data['event_params'] = array();
		}

		// Mark as test event in params.
		$event_data['event_params']['is_test_event']   = true;
		$event_data['event_params']['test_source']     = 'admin_diagnostics';
		$event_data['event_params']['test_user_id']    = $current_user->ID;
		$event_data['event_params']['test_user_login'] = $current_user->user_login;

		// Add page context if missing (in event_params as expected by event recorder).
		if (! isset($event_data['event_params']['page_url'])) {
			$event_data['event_params']['page_url'] = home_url('/diagnostics-test');
		}

		if (! isset($event_data['event_params']['page_path'])) {
			$event_data['event_params']['page_path'] = '/diagnostics-test';
		}

		if (! isset($event_data['event_params']['page_title'])) {
			$event_data['event_params']['page_title'] = 'Diagnostics Test';
		}

		return $event_data;
	}
}
