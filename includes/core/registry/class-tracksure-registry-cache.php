<?php

/**
 *
 * TrackSure Registry Cache
 *
 * Handles caching for registry data using WordPress transients.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Registry Cache class.
 */
class TrackSure_Registry_Cache {



	/**
	 * Cache prefix.
	 *
	 * @var string
	 */
	private $prefix = 'tracksure_registry_';

	/**
	 * Cache expiration (in seconds).
	 *
	 * @var int
	 */
	private $expiration = DAY_IN_SECONDS;

	/**
	 * Get cached data.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached data or false if not found.
	 */
	public function get( $key ) {
		$cache_key = $this->get_cache_key( $key );
		return get_transient( $cache_key );
	}

	/**
	 * Set cached data.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @return bool
	 */
	public function set( $key, $data ) {
		$cache_key = $this->get_cache_key( $key );
		return set_transient( $cache_key, $data, $this->expiration );
	}

	/**
	 * Delete cached data.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( $key ) {
		$cache_key = $this->get_cache_key( $key );
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all registry cache.
	 */
	public function clear_all() {
		$this->delete( 'events' );
		$this->delete( 'parameters' );
	}

	/**
	 * Get full cache key.
	 *
	 * @param string $key Base key.
	 * @return string
	 */
	private function get_cache_key( $key ) {
		return $this->prefix . $key;
	}
}
