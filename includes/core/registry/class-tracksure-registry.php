<?php

/**
 *
 * TrackSure Registry
 *
 * Central registry for events, parameters, and validation.
 * Loads from JSON files and provides query/validation methods.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Registry class.
 */
class TrackSure_Registry {



	/**
	 * Instance.
	 *
	 * @var TrackSure_Registry
	 */
	private static $instance = null;

	/**
	 * Registry cache.
	 *
	 * @var TrackSure_Registry_Cache
	 */
	private $cache;

	/**
	 * Events registry.
	 *
	 * @var array
	 */
	private $events = array();

	/**
	 * Parameters registry.
	 *
	 * @var array
	 */
	private $parameters = array();

	/**
	 * Registry loaded flag.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Registry
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
		$this->cache = new TrackSure_Registry_Cache();
		$this->load_registry();
	}

	/**
	 * Load registry from JSON files.
	 */
	private function load_registry() {
		if ( $this->loaded ) {
			return;
		}

		// Try object cache first (Redis/Memcached if available).
		$cache_group   = 'tracksure_registry';
		$events_cached = wp_cache_get( 'events', $cache_group );
		$params_cached = wp_cache_get( 'parameters', $cache_group );

		if ( $events_cached !== false && $params_cached !== false ) {
			$this->events     = $events_cached;
			$this->parameters = $params_cached;
			$this->loaded     = true;
			return;
		}

		// Try transient cache (fallback).
		$cached_events = $this->cache->get( 'events' );
		$cached_params = $this->cache->get( 'parameters' );

		if ( $cached_events && $cached_params ) {
			$this->events     = $cached_events;
			$this->parameters = $cached_params;

			// Also set in object cache for this request.
			wp_cache_set( 'events', $this->events, $cache_group, DAY_IN_SECONDS );
			wp_cache_set( 'parameters', $this->parameters, $cache_group, DAY_IN_SECONDS );

			$this->loaded = true;
			return;
		}

		// Load from JSON files (slowest path).
		$loader = new TrackSure_Registry_Loader();

		$this->events     = $loader->load_events();
		$this->parameters = $loader->load_parameters();

		// Cache in both locations.
		$this->cache->set( 'events', $this->events );
		$this->cache->set( 'parameters', $this->parameters );
		wp_cache_set( 'events', $this->events, $cache_group, DAY_IN_SECONDS );
		wp_cache_set( 'parameters', $this->parameters, $cache_group, DAY_IN_SECONDS );

		$this->loaded = true;

		/**
		 * Fires after registry is loaded.
		 *
		 * @since 1.0.0
		 *
		 * @param TrackSure_Registry $registry Registry instance.
		 */
		do_action( 'tracksure_registry_loaded', $this );
	}

	/**
	 * Get all events.
	 *
	 * @return array
	 */
	public function get_events() {
		return $this->events;
	}

	/**
	 * Get event by name.
	 *
	 * @param string $event_name Event name.
	 * @return array|null Event data or null if not found.
	 */
	public function get_event( $event_name ) {
		foreach ( $this->events as $event ) {
			if ( $event['name'] === $event_name ) {
				return $event;
			}
		}
		return null;
	}

	/**
	 * Check if event exists.
	 *
	 * @param string $event_name Event name.
	 * @return bool
	 */
	public function event_exists( $event_name ) {
		return null !== $this->get_event( $event_name );
	}

	/**
	 * Get events by category.
	 *
	 * @param string $category Category name.
	 * @return array
	 */
	public function get_events_by_category( $category ) {
		return array_filter(
			$this->events,
			function ( $event ) use ( $category ) {
				return isset( $event['category'] ) && $event['category'] === $category;
			}
		);
	}

	/**
	 * Get automatically collected events.
	 *
	 * @return array
	 */
	public function get_auto_events() {
		return array_filter(
			$this->events,
			function ( $event ) {
				return ! empty( $event['automatically_collected'] );
			}
		);
	}

	/**
	 * Get all parameters.
	 *
	 * @return array
	 */
	public function get_parameters() {
		return $this->parameters;
	}

	/**
	 * Get parameter by name.
	 *
	 * @param string $param_name Parameter name.
	 * @return array|null Parameter data or null if not found.
	 */
	public function get_parameter( $param_name ) {
		foreach ( $this->parameters as $param ) {
			if ( $param['name'] === $param_name ) {
				return $param;
			}
		}
		return null;
	}

	/**
	 * Check if parameter exists.
	 *
	 * @param string $param_name Parameter name.
	 * @return bool
	 */
	public function parameter_exists( $param_name ) {
		return null !== $this->get_parameter( $param_name );
	}

	/**
	 * Validate event data against registry.
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_params Event parameters.
	 * @return array Validation result with 'valid' (bool) and 'errors' (array).
	 */
	public function validate_event( $event_name, $event_params ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Check if event exists.
		$event = $this->get_event( $event_name );
		if ( ! $event ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf( 'Event "%s" is not registered in the registry.', esc_html( $event_name ) );
			return $result;
		}

		// Check required parameters.
		if ( ! empty( $event['required_params'] ) ) {
			foreach ( $event['required_params'] as $required_param ) {
				if ( ! isset( $event_params[ $required_param ] ) ) {
					$result['valid']    = false;
					$result['errors'][] = sprintf( 'Missing required parameter: %s', esc_html( $required_param ) );
				}
			}
		}

		// Validate parameter types.
		foreach ( $event_params as $param_name => $param_value ) {
			$param_def = $this->get_parameter( $param_name );
			if ( ! $param_def ) {
				// Parameter not in registry - allow for flexibility.
				continue;
			}

			// Type validation.
			$expected_type = isset( $param_def['type'] ) ? $param_def['type'] : 'string';
			$actual_type   = gettype( $param_value );

			if ( 'number' === $expected_type && ! is_numeric( $param_value ) ) {
				$result['valid']    = false;
				$result['errors'][] = sprintf( 'Parameter "%s" should be numeric, got %s', esc_html( $param_name ), esc_html( $actual_type ) );
			} elseif ( 'integer' === $expected_type && ! is_int( $param_value ) ) {
				$result['valid']    = false;
				$result['errors'][] = sprintf( 'Parameter "%s" should be integer, got %s', esc_html( $param_name ), esc_html( $actual_type ) );
			} elseif ( 'boolean' === $expected_type && ! is_bool( $param_value ) ) {
				$result['valid']    = false;
				$result['errors'][] = sprintf( 'Parameter "%s" should be boolean, got %s', esc_html( $param_name ), esc_html( $actual_type ) );
			} elseif ( 'array' === $expected_type && ! is_array( $param_value ) ) {
				$result['valid']    = false;
				$result['errors'][] = sprintf( 'Parameter "%s" should be array, got %s', esc_html( $param_name ), esc_html( $actual_type ) );
			}
		}

		/**
		 * Filter event validation result.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $result Validation result.
		 * @param string $event_name Event name.
		 * @param array  $event_params Event parameters.
		 */
		return apply_filters( 'tracksure_validate_event', $result, $event_name, $event_params );
	}

	/**
	 * Register custom event (from module packs).
	 *
	 * @param array $event_data Event data.
	 * @return bool
	 */
	public function register_event( $event_data ) {
		$required_fields = array( 'name', 'display_name', 'category' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $event_data[ $field ] ) ) {
				return false;
			}
		}

		// Check if event already exists.
		if ( $this->event_exists( $event_data['name'] ) ) {
			return false;
		}

		// Add event to registry.
		$this->events[] = wp_parse_args(
			$event_data,
			array(
				'description'             => '',
				'automatically_collected' => false,
				'required_params'         => array(),
				'optional_params'         => array(),
			)
		);

		// Invalidate cache.
		$this->cache->delete( 'events' );
		wp_cache_delete( 'events', 'tracksure_registry' );

		/**
		 * Fires when a custom event is registered.
		 *
		 * @since 1.0.0
		 *
		 * @param array $event_data Event data.
		 */
		do_action( 'tracksure_event_registered', $event_data );

		return true;
	}

	/**
	 * Register custom parameter (from module packs).
	 *
	 * @param array $param_data Parameter data.
	 * @return bool
	 */
	public function register_parameter( $param_data ) {
		$required_fields = array( 'name', 'display_name', 'type' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $param_data[ $field ] ) ) {
				return false;
			}
		}

		// Check if parameter already exists.
		if ( $this->parameter_exists( $param_data['name'] ) ) {
			return false;
		}

		// Add parameter to registry.
		$this->parameters[] = wp_parse_args(
			$param_data,
			array(
				'description' => '',
				'example'     => null,
			)
		);

		// Invalidate cache.
		$this->cache->delete( 'parameters' );
		wp_cache_delete( 'parameters', 'tracksure_registry' );

		/**
		 * Fires when a custom parameter is registered.
		 *
		 * @since 1.0.0
		 *
		 * @param array $param_data Parameter data.
		 */
		do_action( 'tracksure_parameter_registered', $param_data );

		return true;
	}

	/**
	 * Clear registry cache.
	 */
	public function clear_cache() {
		// Clear transient cache.
		$this->cache->delete( 'events' );
		$this->cache->delete( 'parameters' );

		// Clear object cache.
		$cache_group = 'tracksure_registry';
		wp_cache_delete( 'events', $cache_group );
		wp_cache_delete( 'parameters', $cache_group );

		$this->loaded = false;
		$this->load_registry();
	}

	/**
	 * Get registry statistics.
	 *
	 * @return array
	 */
	public function get_stats() {
		return array(
			'total_events'     => count( $this->events ),
			'auto_events'      => count( $this->get_auto_events() ),
			'total_parameters' => count( $this->parameters ),
			'event_categories' => array_unique( array_column( $this->events, 'category' ) ),
		);
	}
}
