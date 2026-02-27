<?php

/**
 * REST API pixel callback controller.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Pixel callback endpoint uses direct DB queries for event status updates

/**
 *
 * TrackSure REST Pixel Callback Controller
 *
 * Receives browser confirmation when pixels fire successfully.
 * Updates event record to mark browser_fired=1 for transparent reporting.
 *
 * Endpoint: POST /wp-json/tracksure/v1/pixel-callback
 *
 * @package TrackSure\Core\API
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pixel Callback REST Controller
 */
class TrackSure_REST_Pixel_Callback_Controller extends WP_REST_Controller {





	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ts/v1';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'cb';

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db = TrackSure_DB::get_instance();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'confirm_pixel_fired' ),
					// Public: browser SDK confirms pixel fired from anonymous visitor's browser.
					'permission_callback' => '__return_true',
					'args'                => array(
						'event_id'    => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => 'Event UUID',
							'validate_callback' => array( $this, 'validate_uuid' ),
						),
						'destination' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => 'Destination name (meta, ga4, etc.)',
						),
						'status'      => array(
							'required'    => false,
							'type'        => 'string',
							'enum'        => array( 'success', 'error' ),
							'default'     => 'success',
							'description' => 'Pixel firing status',
						),
					),
				),
			)
		);
	}

	/**
	 * Confirm pixel fired successfully.
	 *
	 * Browser JS calls this after pixel fires to update database.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function confirm_pixel_fired( $request ) {
		$event_id    = sanitize_text_field( $request->get_param( 'event_id' ) );
		$destination = sanitize_text_field( $request->get_param( 'destination' ) );
		$status      = sanitize_text_field( $request->get_param( 'status' ) );

		// Get event from database.
		global $wpdb;

		$event = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT event_id, browser_fired, server_fired, browser_fired_at, destinations_sent FROM {$wpdb->prefix}tracksure_events WHERE event_id = %s LIMIT 1",
				$event_id
			),
			ARRAY_A
		);

		if ( ! $event ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Event not found',
				),
				404
			);
		}

		// Update browser_fired flag and timestamp.
		$update_data = array(
			'browser_fired'    => 1,
			'browser_fired_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		// Update destinations_sent JSON array - CRITICAL: Handle empty strings.
		$destinations_sent = ! empty( $event['destinations_sent'] ) && $event['destinations_sent'] !== '' ? json_decode( $event['destinations_sent'], true ) : null;
		if ( ! is_array( $destinations_sent ) ) {
			$destinations_sent = array();
		}

		// Add this destination to the array if not already present.
		if ( ! in_array( $destination, $destinations_sent ) ) {
			$destinations_sent[] = $destination;
		}

		// Ensure we don't save empty array as empty string - use NULL if empty.
		$update_data['destinations_sent'] = ! empty( $destinations_sent ) ? wp_json_encode( $destinations_sent ) : null;

		// Perform update.
		$updated = $wpdb->update(
			$wpdb->prefix . 'tracksure_events',
			$update_data,
			array( 'event_id' => $event_id ),
			array( '%d', '%s', '%s' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Failed to update event',
					'error'   => $wpdb->last_error,
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'event_id' => $event_id,
				'message'  => 'Browser pixel confirmation recorded',
			),
			200
		);
	}

	/**
	 * Validate UUID format.
	 *
	 * @param mixed           $value   Value to validate.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool
	 */
	public function validate_uuid( $value, $request, $param ) {
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
