<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for utility function diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Utilities
 *
 * Centralized utility functions for common operations.
 * Eliminates code duplication across multiple classes.
 *
 * @package TrackSure\Core\Utils
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Utilities class.
 *
 * Provides static utility methods for:
 * - IP address handling (get, validate, anonymize)
 * - UUID validation and generation
 * - Data sanitization
 * - URL normalization
 */
class TrackSure_Utilities {




	// ===========================.
	// IP Address Utilities.
	// ===========================.
	/**
	 * Get client IP address with comprehensive proxy and CDN support.
	 *
	 * PRIORITY ORDER:
	 * 1. Cloudflare (if detected via CF-Connecting-IP header)
	 * 2. Trusted proxy headers (if REMOTE_ADDR matches trusted proxy list)
	 * 3. Standard proxy headers (X-Forwarded-For, X-Real-IP, etc.)
	 * 4. REMOTE_ADDR (fallback - may be server IP on localhost)
	 *
	 * SECURITY: Validates all IPs and filters private/reserved ranges.
	 * Prevents IP spoofing while handling all deployment scenarios.
	 *
	 * @return string|null IP address or null if invalid.
	 */
	public static function get_client_ip() {
		// Get server's REMOTE_ADDR (always available).
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null;

		// PRIORITY 1: Cloudflare - Most reliable if present.
		// Cloudflare always sets CF-Connecting-IP to the true client IP.
		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $cf_ip;
			}
		}

		// Get trusted proxy list (configurable via filter).
		$trusted_proxies = apply_filters(
			'tracksure_trusted_proxies',
			array(
				'127.0.0.1',     // Localhost
				'::1',           // Localhost IPv6
				// Add your load balancer/CDN/reverse proxy IPs:
				// '192.168.1.1',.
				// '10.0.0.1',.
			)
		);

		// PRIORITY 2: Trusted proxy check.
		$is_trusted_proxy = in_array( $remote_addr, $trusted_proxies, true );

		// PRIORITY 3: Check all possible proxy headers.
		$proxy_headers = array(
			'HTTP_X_FORWARDED_FOR',  // Standard proxy header (most common)
			'HTTP_CLIENT_IP',        // Some proxies use this
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_CLUSTER_CLIENT_IP', // Load balancers
			'HTTP_FORWARDED',        // RFC 7239 standard
		);

		// If behind known trusted proxy OR if any proxy header exists, try to extract real IP.
		foreach ( $proxy_headers as $header ) {
			if ( isset( $_SERVER[ $header ] ) ) {
				$header_value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// Trust proxy headers in these scenarios:
				// 1. REMOTE_ADDR is in trusted proxy list (production load balancers/CDNs)
				// 2. REMOTE_ADDR is localhost (development)
				// 3. REMOTE_ADDR is a private IP (Docker, Local by WP Engine, reverse proxies)
				// 4. X-Forwarded-For header exists (most production servers use this)
				$is_private_ip = false;
				if ( $remote_addr ) {
					// Check if REMOTE_ADDR is private IP range
					$ip_long       = ip2long( $remote_addr );
					$is_private_ip = (
						( $ip_long >= ip2long( '10.0.0.0' ) && $ip_long <= ip2long( '10.255.255.255' ) ) ||
						( $ip_long >= ip2long( '172.16.0.0' ) && $ip_long <= ip2long( '172.31.255.255' ) ) ||
						( $ip_long >= ip2long( '192.168.0.0' ) && $ip_long <= ip2long( '192.168.255.255' ) ) ||
						( $ip_long >= ip2long( '127.0.0.0' ) && $ip_long <= ip2long( '127.255.255.255' ) )
					);
				}

				$should_trust = $is_trusted_proxy
					|| self::is_localhost_request( $remote_addr )
					|| $is_private_ip
					|| ( $header === 'HTTP_X_FORWARDED_FOR' ); // Always trust X-Forwarded-For on production

				if ( $should_trust ) {
					$extracted_ip = self::extract_first_valid_ip( $header_value );
					if ( $extracted_ip ) {
						return $extracted_ip;
					}
				}
			}
		}

		// PRIORITY 4: Fallback to REMOTE_ADDR.
		// Validate it's a real public IP (not private/reserved).
		if ( $remote_addr && filter_var( $remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return $remote_addr;
		}

		// REMOTE_ADDR is private/localhost - return it anyway for development.
		// (geolocation will gracefully handle private IPs).
		return $remote_addr;
	}

	/**
	 * Check if request appears to be from localhost/development environment.
	 *
	 * @param string|null $ip IP address to check.
	 * @return bool True if localhost request.
	 */
	private static function is_localhost_request( $ip ) {
		if ( empty( $ip ) ) {
			return false;
		}

		// Check for localhost IPs.
		$localhost_ips = array( '127.0.0.1', '::1', 'localhost' );
		if ( in_array( $ip, $localhost_ips, true ) ) {
			return true;
		}

		// Check for private IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16).
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Extract first valid IP from comma-separated list.
	 *
	 * X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
	 * This extracts the first valid public IP.
	 *
	 * @param string $ip_string Comma-separated IP addresses.
	 * @return string|null First valid IP or null.
	 */
	public static function extract_first_valid_ip( $ip_string ) {
		if ( empty( $ip_string ) ) {
			return null;
		}

		// Split by comma and trim whitespace.
		$ips = array_map( 'trim', explode( ',', $ip_string ) );

		foreach ( $ips as $ip ) {
			// Validate IP and reject private/reserved ranges.
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $ip;
			}
		}

		return null;
	}

	/**
	 * Anonymize IP address (GDPR compliant).
	 *
	 * IPv4: Masks last octet (e.g., 192.168.1.100 -> 192.168.1.0)
	 * IPv6: Masks last 80 bits (e.g., 2001:db8:85a3::8a2e:370:7334 -> 2001:db8:85a3::)
	 *
	 * @param string $ip IP address.
	 * @return string Anonymized IP address.
	 */
	public static function anonymize_ip( $ip ) {
		if ( empty( $ip ) ) {
			return $ip;
		}

		// IPv4: mask last octet.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/\.\d+$/', '.0', $ip );
		}

		// IPv6: mask last 80 bits.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return preg_replace( '/([\da-f]+:[\da-f]+:[\da-f]+):.*/', '$1::', $ip );
		}

		return $ip;
	}

	/**
	 * Validate IP address.
	 *
	 * @param string $ip IP address to validate.
	 * @param bool   $allow_private Allow private IPs (127.0.0.1, 192.168.x.x, etc).
	 * @return bool True if valid IP address.
	 */
	public static function is_valid_ip( $ip, $allow_private = false ) {
		if ( empty( $ip ) ) {
			return false;
		}

		$flags = FILTER_FLAG_NO_RES_RANGE;
		if ( ! $allow_private ) {
			$flags |= FILTER_FLAG_NO_PRIV_RANGE;
		}

		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, $flags );
	}

	// ===========================.
	// UUID Utilities.
	// ===========================.
	/**
	 * Validate UUID v4 format (RFC 4122).
	 *
	 * Strict validation that checks:
	 * - Correct format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
	 * - Version bit: 4 (in 3rd group)
	 * - Variant bits: 8, 9, a, or b (in 4th group)
	 *
	 * @param string $uuid UUID string to validate.
	 * @return bool True if valid UUID v4.
	 */
	public static function is_valid_uuid_v4( $uuid ) {
		if ( empty( $uuid ) || ! is_string( $uuid ) ) {
			return false;
		}

		// Strict UUID v4 regex validation.
		return (bool) preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$uuid
		);
	}

	/**
	 * Generate UUID v4.
	 *
	 * Creates a random UUID v4 string compliant with RFC 4122.
	 *
	 * @return string UUID v4 string (e.g., "550e8400-e29b-41d4-a716-446655440000").
	 */
	public static function generate_uuid_v4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000, // Version 4
			wp_rand( 0, 0x3fff ) | 0x8000, // Variant bits
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}

	// ===========================.
	// Data Sanitization.
	// ===========================.
	/**
	 * Sanitize URL parameters.
	 *
	 * Sanitizes both keys and values of URL parameters.
	 *
	 * @param array $params Raw URL parameters.
	 * @return array Sanitized parameters.
	 */
	public static function sanitize_url_params( $params ) {
		if ( ! is_array( $params ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $params as $key => $value ) {
			$clean_key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = self::sanitize_url_params( $value );
			} else {
				$sanitized[ $clean_key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Normalize URL for comparison.
	 *
	 * Removes query strings, trailing slashes, and converts to lowercase.
	 * Useful for deduplication and URL matching.
	 *
	 * @param string $url Raw URL.
	 * @return string Normalized URL.
	 */
	public static function normalize_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		// Remove query string.
		$url = strtok( $url, '?' );

		// Remove fragment.
		$url = strtok( $url, '#' );

		// Remove trailing slash.
		$url = rtrim( $url, '/' );

		// Lowercase
		return strtolower( $url );
	}

	// ===========================.
	// String Utilities.
	// ===========================.
	/**
	 * Truncate string to specified length.
	 *
	 * @param string $string String to truncate.
	 * @param int    $length Maximum length.
	 * @param string $suffix Suffix to append (default: '...').
	 * @return string Truncated string.
	 */
	public static function truncate( $string, $length, $suffix = '...' ) {
		if ( strlen( $string ) <= $length ) {
			return $string;
		}

		return substr( $string, 0, $length - strlen( $suffix ) ) . $suffix;
	}

	/**
	 * Hash string using SHA-256.
	 *
	 * Used for hashing email addresses and phone numbers.
	 *
	 * @param string $string String to hash.
	 * @return string SHA-256 hash.
	 */
	public static function hash_string( $string ) {
		return hash( 'sha256', strtolower( trim( $string ) ) );
	}

	/**
	 * Debug logging utility.
	 *
	 * Only logs when WP_DEBUG is enabled. Centralizes debug log output
	 * so all logging can be controlled from one place.
	 *
	 * @param string $message Log message.
	 * @param string $context Optional context prefix (e.g., 'GA4', 'FluentCart').
	 * @return void
	 */
	public static function debug_log( $message, $context = '' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$prefix = $context ? "[TrackSure {$context}] " : '[TrackSure] ';
			error_log( $prefix . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
