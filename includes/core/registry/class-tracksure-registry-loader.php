<?php

/**
 * Module registry loader.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging for registry loading diagnostics

/**
 *
 * TrackSure Registry Loader
 *
 * Loads and parses JSON registry files.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Registry Loader class.
 */
class TrackSure_Registry_Loader {





	/**
	 * Registry directory.
	 *
	 * @var string
	 */
	private $registry_dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->registry_dir = TRACKSURE_REGISTRY_DIR;
	}

	/**
	 * Load events from events.json.
	 *
	 * @return array
	 */
	public function load_events() {
		$file = $this->registry_dir . 'events.json';

		if ( ! file_exists( $file ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents.
		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'TrackSure: Failed to parse events.json - ' . json_last_error_msg() );
			}
			return array();
		}

		$events = isset( $data['events'] ) ? $data['events'] : array();

		/**
		 * Filter events after loading from JSON.
		 *
		 * @since 1.0.0
		 *
		 * @param array $events Events array.
		 */
		return apply_filters( 'tracksure_loaded_events', $events );
	}

	/**
	 * Load parameters from params.json.
	 *
	 * @return array
	 */
	public function load_parameters() {
		$file = $this->registry_dir . 'params.json';

		if ( ! file_exists( $file ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents.
		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'TrackSure: Failed to parse params.json - ' . json_last_error_msg() );
			}
			return array();
		}

		$parameters = isset( $data['parameters'] ) ? $data['parameters'] : array();

		/**
		 * Filter parameters after loading from JSON.
		 *
		 * @since 1.0.0
		 *
		 * @param array $parameters Parameters array.
		 */
		return apply_filters( 'tracksure_loaded_parameters', $parameters );
	}

	/**
	 * Validate JSON file structure.
	 *
	 * @param string $file File path.
	 * @param string $type Type (events or params).
	 * @return array Validation result with 'valid' (bool) and 'errors' (array).
	 */
	public function validate_json( $file, $type ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		if ( ! file_exists( $file ) ) {
			$result['valid']    = false;
			$result['errors'][] = 'File does not exist';
			return $result;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents.
		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$result['valid']    = false;
			$result['errors'][] = 'Invalid JSON: ' . json_last_error_msg();
			return $result;
		}

		// Validate structure based on type.
		if ( 'events' === $type ) {
			if ( ! isset( $data['events'] ) || ! is_array( $data['events'] ) ) {
				$result['valid']    = false;
				$result['errors'][] = 'Missing or invalid "events" array';
			}
		} elseif ( 'params' === $type ) {
			if ( ! isset( $data['parameters'] ) || ! is_array( $data['parameters'] ) ) {
				$result['valid']    = false;
				$result['errors'][] = 'Missing or invalid "parameters" array';
			}
		}

		return $result;
	}
}
