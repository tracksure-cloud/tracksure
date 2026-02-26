<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery -- Debug logging + direct DB queries for geolocation caching

/**
 *
 * TrackSure Geolocation Service
 *
 * Provides IP-based geolocation lookup with caching and multi-provider fallback.
 *
 * Provider Chain (in order):
 * 1. PRIMARY: ipapi.co (free, 30k requests/month, highly accurate worldwide)
 * 2. SECONDARY: ip-api.com (free, 45 requests/minute, accurate for worldwide locations)
 * 3. TERTIARY: WordPress.com geolocation API (unlimited, reliable fallback)
 *
 * @package TrackSure\Core
 * @since 1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Geolocation class.
 */
class TrackSure_Geolocation {





	/**
	 * Instance.
	 *
	 * @var TrackSure_Geolocation
	 */
	private static $instance = null;

	/**
	 * In-memory cache.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Geolocation
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
		// No initialization needed.
	}

	/**
	 * Get just the country code for the current visitor's IP.
	 *
	 * Convenience method — single source of truth for country detection.
	 * Used by Consent Manager, auto-consent mode, and anywhere
	 * only a country code (not full city/region) is needed.
	 *
	 * @since 1.0.1
	 *
	 * @param string|null $ip IP address. Defaults to current visitor IP.
	 * @return string Two-letter ISO country code, or 'XX' if unknown.
	 */
	public function get_country_code( $ip = null ) {
		if ( null === $ip ) {
			$ip = TrackSure_Utilities::get_client_ip();
		}

		$location = $this->get_location_from_ip( $ip );

		return ! empty( $location['country'] ) ? $location['country'] : 'XX';
	}

	/**
	 * Get the region/state for the current visitor's IP.
	 *
	 * Convenience method for state-level checks (e.g., CCPA California detection).
	 *
	 * @since 1.0.1
	 *
	 * @param string|null $ip IP address. Defaults to current visitor IP.
	 * @return string|null Region name, or null if unknown.
	 */
	public function get_region( $ip = null ) {
		if ( null === $ip ) {
			$ip = TrackSure_Utilities::get_client_ip();
		}

		$location = $this->get_location_from_ip( $ip );

		return ! empty( $location['region'] ) ? $location['region'] : null;
	}

	/**
	 * Get location data from IP address.
	 *
	 * Unified lookup chain (single source of truth):
	 * 1. CloudFlare header (instant, no cost)
	 * 2. PHP GeoIP extension (instant, if installed)
	 * 3. MaxMind GeoLite2 local DB (instant, if file exists)
	 * 4. Remote API providers (ipapi.co, ip-api.com, WordPress.com)
	 *
	 * Results are cached in memory + transients (24 hours).
	 */
	public function get_location_from_ip( $ip ) {
		// Validate IP address.
		if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			// Skip logging for localhost/development IPs (127.0.0.1, ::1, private ranges).
			if ( ! empty( $ip ) && ! in_array( $ip, array( '127.0.0.1', '::1' ) ) && ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

					error_log( '[TrackSure] Geolocation: Invalid IP address - ' . $ip );
				}
			}
			return array(
				'country' => null,
				'region'  => null,
				'city'    => null,
			);
		}

		// Check in-memory cache first.
		if ( isset( $this->cache[ $ip ] ) ) {
			return $this->cache[ $ip ];
		}

		// Check transient cache (1 day).
		$cache_key = 'tracksure_geo_' . md5( $ip );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			$this->cache[ $ip ] = $cached;
			return $cached;
		}

		// Perform geolocation lookup.
		$location = $this->lookup_ip( $ip );

		// Cache results (24 hours).
		set_transient( $cache_key, $location, DAY_IN_SECONDS );
		$this->cache[ $ip ] = $location;

		return $location;
	}

	/**
	 * Perform IP lookup using unified multi-source fallback chain.
	 *
	 * LOCAL sources (instant, no HTTP):
	 * 1. CloudFlare CF-IPCountry header (free, always set behind CF)
	 * 2. PHP GeoIP extension (if server has it installed)
	 * 3. MaxMind GeoLite2 local database (if .mmdb file exists)
	 *
	 * REMOTE sources (HTTP, cached by caller):
	 * 4. ipapi.co (30k/month, highly accurate)
	 * 5. ip-api.com (45/min, very accurate)
	 * 6. WordPress.com (unlimited, reliable)
	 *
	 * @param string $ip IP address.
	 * @return array Location data with country, region, city keys.
	 */
	private function lookup_ip( $ip ) {
		$default_location = array(
			'country' => null,
			'region'  => null,
			'city'    => null,
		);

		// ── LOCAL SOURCE 1: CloudFlare header (instant) ──
		// CloudFlare sets HTTP_CF_IPCOUNTRY for every proxied request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_text_field.
		if ( isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$cf_country = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) );
			if ( ! empty( $cf_country ) && 'XX' !== $cf_country ) {
				return array(
					'country' => $cf_country,
					'region'  => null, // CF only provides country.
					'city'    => null,
				);
			}
		}

		// ── LOCAL SOURCE 2: PHP GeoIP extension (instant) ──
		if ( function_exists( 'geoip_country_code_by_name' ) ) {
			$geoip_country = geoip_country_code_by_name( $ip );
			if ( $geoip_country && 'XX' !== $geoip_country ) {
				return array(
					'country' => sanitize_text_field( $geoip_country ),
					'region'  => null,
					'city'    => null,
				);
			}
		}

		// ── LOCAL SOURCE 3: MaxMind GeoLite2 local database ──
		if ( class_exists( 'GeoIp2\\Database\\Reader' ) ) {
			$location = $this->lookup_maxmind( $ip );
			if ( ! empty( $location['country'] ) ) {
				return $location;
			}
		}

		// ── REMOTE SOURCE 4: ipapi.co ──
		// Reduced timeout to 1.5s (from 3s) — if the API can't respond in 1.5s
		// it's better to skip geo than block the visitor for 3s.
		$ipapi_url      = 'https://ipapi.co/' . urlencode( $ip ) . '/json/';
		$ipapi_response = wp_remote_get(
			$ipapi_url,
			array(
				'timeout'   => 1.5,
				'sslverify' => true,
				'headers'   => array(
					'User-Agent' => 'TrackSure/1.0',
				),
			)
		);

		if ( ! is_wp_error( $ipapi_response ) ) {
			$status_code = wp_remote_retrieve_response_code( $ipapi_response );
			if ( 200 === $status_code ) {
				$body = wp_remote_retrieve_body( $ipapi_response );
				$data = json_decode( $body, true );

				// ipapi.co returns error field if rate limited or failed.
				if ( is_array( $data ) && ! isset( $data['error'] ) && isset( $data['country'] ) ) {
					$location = array(
						'country' => isset( $data['country'] ) && ! empty( $data['country'] ) ? sanitize_text_field( $data['country'] ) : null,
						'region'  => isset( $data['region'] ) && ! empty( $data['region'] ) ? sanitize_text_field( $data['region'] ) : null,
						'city'    => isset( $data['city'] ) && ! empty( $data['city'] ) ? sanitize_text_field( $data['city'] ) : null,
					);

					if ( $location['country'] ) {
						return $location;
					}
				}
			}
		}

		// ── REMOTE SOURCE 5: ip-api.com (free, 45 req/min, very accurate) ──
		// Only try this if ipapi.co failed — this is HTTP-only (no SSL).
		$ipapi_com_url      = 'http://ip-api.com/json/' . urlencode( $ip ) . '?fields=status,message,country,countryCode,regionName,city';
		$ipapi_com_response = wp_remote_get(
			$ipapi_com_url,
			array(
				'timeout'   => 1.5,
				'sslverify' => false, // HTTP endpoint (free tier)
				'headers'   => array(
					'User-Agent' => 'TrackSure/1.0',
				),
			)
		);

		if ( ! is_wp_error( $ipapi_com_response ) ) {
			$status_code = wp_remote_retrieve_response_code( $ipapi_com_response );
			if ( 200 === $status_code ) {
				$body = wp_remote_retrieve_body( $ipapi_com_response );
				$data = json_decode( $body, true );

				if ( is_array( $data ) && isset( $data['status'] ) && 'success' === $data['status'] ) {
					$location = array(
						'country' => isset( $data['countryCode'] ) && ! empty( $data['countryCode'] ) ? sanitize_text_field( $data['countryCode'] ) : null,
						'region'  => isset( $data['regionName'] ) && ! empty( $data['regionName'] ) ? sanitize_text_field( $data['regionName'] ) : null,
						'city'    => isset( $data['city'] ) && ! empty( $data['city'] ) ? sanitize_text_field( $data['city'] ) : null,
					);

					if ( $location['country'] ) {
						return $location;
					}
				}
			}
		}

		// ── REMOTE SOURCE 6: WordPress.com geolocation API (unlimited, reliable) ──
		$wpcom_url      = 'https://public-api.wordpress.com/geo/?ip=' . urlencode( $ip );
		$wpcom_response = wp_remote_get(
			$wpcom_url,
			array(
				'timeout'   => 1.5,
				'sslverify' => true,
				'headers'   => array(
					'User-Agent' => 'TrackSure/1.0',
				),
			)
		);

		if ( ! is_wp_error( $wpcom_response ) ) {
			$status_code = wp_remote_retrieve_response_code( $wpcom_response );
			if ( 200 === $status_code ) {
				$body = wp_remote_retrieve_body( $wpcom_response );
				$data = json_decode( $body, true );

				if ( is_array( $data ) ) {
					$location = array(
						'country' => isset( $data['country_short'] ) && ! empty( $data['country_short'] ) ? sanitize_text_field( $data['country_short'] ) : null,
						'region'  => isset( $data['region'] ) && ! empty( $data['region'] ) ? sanitize_text_field( $data['region'] ) : null,
						'city'    => isset( $data['city'] ) && ! empty( $data['city'] ) ? sanitize_text_field( $data['city'] ) : null,
					);

					if ( $location['country'] ) {
						return $location;
					}
				}
			}
		}

		// All 6 sources failed.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TrackSure] Geolocation: All 6 sources failed for IP ' . $ip );
		}

		return $default_location;
	}

	/**
	 * Lookup IP using MaxMind GeoLite2 local database.
	 *
	 * Checks uploads directory first (recommended), then plugin data directory.
	 *
	 * @param string $ip IP address.
	 * @return array Location data with country, region, city keys.
	 */
	private function lookup_maxmind( $ip ) {
		$default = array(
			'country' => null,
			'region'  => null,
			'city'    => null,
		);

		try {
			// Check uploads directory first (recommended), then plugin directory.
			$upload_dir = wp_upload_dir();
			$db_path    = $upload_dir['basedir'] . '/tracksure/GeoLite2-Country.mmdb';
			if ( ! file_exists( $db_path ) ) {
				$db_path = defined( 'TRACKSURE_PLUGIN_DIR' )
					? TRACKSURE_PLUGIN_DIR . 'data/GeoLite2-Country.mmdb'
					: '';
			}

			if ( empty( $db_path ) || ! file_exists( $db_path ) ) {
				return $default;
			}

			$reader  = new \GeoIp2\Database\Reader( $db_path );
			$record  = $reader->country( $ip );
			$country = $record->country->isoCode;

			if ( ! empty( $country ) ) {
				return array(
					'country' => sanitize_text_field( $country ),
					'region'  => null, // Country DB doesn't include region/city.
					'city'    => null,
				);
			}
		} catch ( \Exception $e ) {
			// MaxMind lookup failed — fall through to remote providers.
		}

		return $default;
	}

	/**
	 * Clear geolocation cache for specific IP.
	 *
	 * @param string $ip IP address.
	 * @return bool True on success.
	 */
	public function clear_cache( $ip ) {
		unset( $this->cache[ $ip ] );
		$cache_key = 'tracksure_geo_' . md5( $ip );
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all geolocation cache.
	 *
	 * @return bool True on success.
	 */
	public function clear_all_cache() {
		global $wpdb;

		$this->cache = array();

		// Delete all transients starting with tracksure_geo_.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_tracksure_geo_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_tracksure_geo_' ) . '%'
			)
		);

		return true;
	}
}
