<?php

/**
 *
 * TrackSure REST Controller Base
 *
 * Base class for all REST API controllers.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base REST controller class.
 */
abstract class TrackSure_REST_Controller extends WP_REST_Controller {




	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ts/v1';

	/**
	 * Check admin permission.
	 *
	 * Verifies user capability and nonce for state-changing requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( $request ) {
		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'tracksure' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		// For state-changing requests, verify nonce explicitly.
		if ( in_array( $request->get_method(), array( 'POST', 'PUT', 'DELETE', 'PATCH' ), true ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'Cookie nonce is invalid', 'tracksure' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Prepare error response.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param int    $status HTTP status code.
	 * @return WP_Error
	 */
	protected function prepare_error( $code, $message, $status = 400 ) {
		return new WP_Error(
			sanitize_key( $code ),
			esc_html( $message ),
			array( 'status' => absint( $status ) )
		);
	}

	/**
	 * Prepare success response.
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function prepare_success( $data, $status = 200 ) {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Get common date range query args for REST endpoints.
	 *
	 * @return array
	 */
	protected function get_date_range_args() {
		return array(
			'date_start' => array(
				'type'              => 'string',
				'format'            => 'date',
				'description'       => 'Start date (YYYY-MM-DD).',
				'default'           => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
			'date_end'   => array(
				'type'              => 'string',
				'format'            => 'date',
				'description'       => 'End date (YYYY-MM-DD).',
				'default'           => gmdate( 'Y-m-d' ),
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
		);
	}

	/**
	 * Validate date format (YYYY-MM-DD).
	 *
	 * @param mixed           $value   Date value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool|WP_Error
	 */
	public function validate_date_format( $value, $request, $param ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $this->prepare_error(
				'invalid_date_format',
				sprintf( 'Parameter %s must be in format YYYY-MM-DD.', esc_html( $param ) ),
				400
			);
		}

		// Check if date is valid.
		$parts = explode( '-', $value );
		if ( count( $parts ) !== 3 || ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return $this->prepare_error(
				'invalid_date',
				sprintf( 'Parameter %s is not a valid date.', esc_html( $param ) ),
				400
			);
		}

		return true;
	}

	/**
	 * Validate segment parameter.
	 *
	 * @param mixed           $value   Segment value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool|WP_Error
	 */
	public function validate_segment( $value, $request, $param ) {
		$valid_segments = array( 'all', 'new', 'returning', 'converted' );

		if ( ! in_array( $value, $valid_segments, true ) ) {
			return $this->prepare_error(
				'invalid_segment',
				sprintf(
					'Parameter %s must be one of: %s.',
					$param,
					implode( ', ', $valid_segments )
				),
				400
			);
		}

		return true;
	}

	/**
	 * Sanitize and validate integer ID.
	 *
	 * @param mixed $value ID value.
	 * @return int|WP_Error
	 */
	protected function sanitize_id( $value ) {
		$id = absint( $value );

		if ( $id <= 0 ) {
			return $this->prepare_error( 'invalid_id', 'Invalid ID provided.', 400 );
		}

		return $id;
	}

	/**
	 * Parse pagination parameters from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array Associative array with 'page', 'per_page', 'offset'.
	 */
	protected function get_pagination_params( $request ) {
		$page     = max( 1, absint( $request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1 ) );
		$per_page = max( 1, absint( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 20 ) );
		$offset   = ( $page - 1 ) * $per_page;

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'offset'   => $offset,
		);
	}

	/**
	 * Prepare pagination headers for response.
	 *
	 * @param WP_REST_Response $response    Response object.
	 * @param int              $total_items Total number of items.
	 * @param int              $per_page    Items per page.
	 * @param int              $page        Current page.
	 * @return WP_REST_Response
	 */
	protected function add_pagination_headers( $response, $total_items, $per_page, $page ) {
		$total_pages = ceil( $total_items / $per_page );

		$response->header( 'X-WP-Total', $total_items );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}
}
