<?php

/**
 *
 * TrackSure Trusted Proxy Configuration Helper
 *
 * Free/Pro modules can use this to auto-configure trusted proxies
 * for common CDN providers (Cloudflare, Fastly, etc.)
 *
 * @package TrackSure\Core
 * @since 1.0.1
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * TrackSure Trusted Proxy Helper
 */
class TrackSure_Trusted_Proxy_Helper
{



	/**
	 * Static fallback list of known Cloudflare IPv4 ranges.
	 *
	 * Bundled locally so the plugin works without remote HTTP requests.
	 * Source: https://www.cloudflare.com/ips-v4 (last updated 2025-01-15).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $default_ipv4 = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
	);

	/**
	 * Static fallback list of known Cloudflare IPv6 ranges.
	 *
	 * Bundled locally so the plugin works without remote HTTP requests.
	 * Source: https://www.cloudflare.com/ips-v6 (last updated 2025-01-15).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $default_ipv6 = array(
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Get Cloudflare IP ranges (IPv4 + IPv6).
	 *
	 * Returns a bundled static list by default. Optionally refreshes from
	 * the Cloudflare public API (https://www.cloudflare.com/ips-v4 and
	 * https://www.cloudflare.com/ips-v6) once per day and caches the result
	 * in a transient. Falls back to the bundled list on failure.
	 *
	 * @since 1.0.0
	 * @return array List of Cloudflare IP CIDR ranges.
	 */
	public static function get_cloudflare_ips()
	{
		// 1. Return transient cache if available.
		$cached = get_transient('tracksure_cloudflare_ips');
		if (false !== $cached && is_array($cached) && ! empty($cached)) {
			return $cached;
		}

		// 2. Try refreshing from Cloudflare API (public, no user data sent).
		$ips = self::fetch_cloudflare_ips_remote();

		// 3. Fall back to bundled static list if remote fetch failed.
		if (empty($ips)) {
			$ips = array_merge(self::$default_ipv4, self::$default_ipv6);
		}

		// Cache for 24 hours.
		set_transient('tracksure_cloudflare_ips', $ips, DAY_IN_SECONDS);

		return $ips;
	}

	/**
	 * Fetch Cloudflare IP ranges from the remote API.
	 *
	 * This contacts https://www.cloudflare.com/ips-v4 and
	 * https://www.cloudflare.com/ips-v6. No user data is transmitted.
	 * See the "External services" section in readme.txt.
	 *
	 * @since 1.0.0
	 * @return array IP ranges on success, empty array on failure.
	 */
	private static function fetch_cloudflare_ips_remote()
	{
		$ips = array();

		$ipv4_response = wp_remote_get('https://www.cloudflare.com/ips-v4');
		$ipv6_response = wp_remote_get('https://www.cloudflare.com/ips-v6');

		if (! is_wp_error($ipv4_response)) {
			$ipv4_body   = wp_remote_retrieve_body($ipv4_response);
			$ipv4_ranges = array_filter(explode("\n", trim($ipv4_body)));
			$ips         = array_merge($ips, $ipv4_ranges);
		}

		if (! is_wp_error($ipv6_response)) {
			$ipv6_body   = wp_remote_retrieve_body($ipv6_response);
			$ipv6_ranges = array_filter(explode("\n", trim($ipv6_body)));
			$ips         = array_merge($ips, $ipv6_ranges);
		}

		return $ips;
	}

	/**
	 * Check if request is from Cloudflare
	 *
	 * @return bool True if from Cloudflare
	 */
	public static function is_cloudflare()
	{
		// Quick check: Cloudflare sets CF-Connecting-IP header.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only checking header existence, value not used.
		if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			return true;
		}

		// Verify by IP range.
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
		if (! $remote_addr) {
			return false;
		}

		$cf_ranges = self::get_cloudflare_ips();
		foreach ($cf_ranges as $range) {
			if (self::ip_in_range($remote_addr, $range)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if IP is in CIDR range
	 *
	 * @param string $ip IP address to check.
	 * @param string $range CIDR range (e.g., "192.168.1.0/24").
	 * @return bool True if IP is in range.
	 */
	private static function ip_in_range($ip, $range)
	{
		if (strpos($range, '/') === false) {
			// Single IP
			return $ip === $range;
		}

		list($subnet, $mask) = explode('/', $range);

		// IPv4
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$ip_long     = ip2long($ip);
			$subnet_long = ip2long($subnet);
			$mask_long   = -1 << (32 - $mask);
			return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
		}

		// IPv6 (simplified check).
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$ip_bin     = inet_pton($ip);
			$subnet_bin = inet_pton($subnet);

			if ($ip_bin === false || $subnet_bin === false) {
				return false;
			}

			$mask_bytes = floor($mask / 8);
			$mask_bits  = $mask % 8;

			// Compare full bytes.
			if (substr($ip_bin, 0, $mask_bytes) !== substr($subnet_bin, 0, $mask_bytes)) {
				return false;
			}

			// Compare remaining bits.
			if ($mask_bits > 0) {
				$mask_value  = 0xFF << (8 - $mask_bits);
				$ip_byte     = ord($ip_bin[$mask_bytes]);
				$subnet_byte = ord($subnet_bin[$mask_bytes]);
				return ($ip_byte & $mask_value) === ($subnet_byte & $mask_value);
			}

			return true;
		}

		return false;
	}

	/**
	 * Get all trusted proxies (for use in filter hook)
	 *
	 * @return array List of trusted proxy IPs/ranges
	 */
	public static function get_trusted_proxies()
	{
		$proxies = array(
			'127.0.0.1',
			'::1',
		);

		// Add Cloudflare IPs if detected.
		if (self::is_cloudflare()) {
			$proxies = array_merge($proxies, self::get_cloudflare_ips());
		}

		// Allow custom proxies via settings (Free/Pro can add UI for this).
		$custom_proxies = get_option('tracksure_custom_trusted_proxies', array());
		if (is_array($custom_proxies) && ! empty($custom_proxies)) {
			$proxies = array_merge($proxies, $custom_proxies);
		}

		return array_unique($proxies);
	}
}

/**
 * Auto-configure trusted proxies filter
 *
 * Free/Pro can call this to enable auto-detection
 */
add_filter('tracksure_trusted_proxies', array('TrackSure_Trusted_Proxy_Helper', 'get_trusted_proxies'));
