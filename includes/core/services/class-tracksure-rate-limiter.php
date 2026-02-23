<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery -- Rate limiter requires direct DB for real-time spam protection

/**
 *
 * TrackSure Rate Limiter
 *
 * Prevents abuse and protects against:
 * - Bot spam (millions of fake events)
 * - DDoS via tracking pixel
 * - Database bloat from malicious traffic
 * - Storage costs from spam data
 *
 * Strategy:
 * - Per-client limit: 100 events/minute (normal user = ~10-20 events/minute)
 * - Per-IP limit: 1000 events/minute (protects against bot farms)
 * - Uses WordPress Transients (fast, cached in memory)
 * - Fails silently (no error response to avoid attackers probing limits)
 *
 * @package TrackSure\Core
 * @since 1.0.1
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * TrackSure Rate Limiter class.
 */
class TrackSure_Rate_Limiter
{



	/**
	 * Instance.
	 *
	 * @var TrackSure_Rate_Limiter
	 */
	private static $instance = null;

	/**
	 * Rate limit settings (can be filtered)
	 *
	 * PRODUCTION-READY LIMITS:
	 * - 10,000 events/min per client = 166 events/sec (handles heavy user activity)
	 * - 50,000 events/min per IP = 833 events/sec (handles shared IPs, office networks)
	 * - Still protects against DDoS/bot attacks (normal site = 100-1000 events/min total)
	 *
	 * To customize, use filter:
	 * add_filter('tracksure_rate_limits', function($limits) {
	 *   $limits['client_per_minute'] = 20000; // 20k events/min
	 *   return $limits;
	 * });
	 *
	 * @var array
	 */
	private $limits = array(
		'client_per_minute' => 10000,  // Events per minute per client_id (production scale)
		'ip_per_minute'     => 50000,  // Events per minute per IP (shared networks)
		'client_window'     => 60,     // Seconds
		'ip_window'         => 60,     // Seconds
	);

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Rate_Limiter
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		// Allow customization via filter.
		$this->limits = apply_filters('tracksure_rate_limits', $this->limits);
	}

	/**
	 * Check if request should be rate limited.
	 *
	 * @param string $client_id Client UUID.
	 * @param string $ip IP address.
	 * @return bool True if allowed, false if rate limited.
	 */
	public function check_rate_limit($client_id, $ip)
	{
		// Check client rate limit.
		$client_allowed = $this->check_client_rate($client_id);

		// Check IP rate limit.
		$ip_allowed = $this->check_ip_rate($ip);

		// Must pass both checks.
		return $client_allowed && $ip_allowed;
	}

	/**
	 * Check client rate limit.
	 *
	 * @param string $client_id Client UUID.
	 * @return bool True if allowed.
	 */
	private function check_client_rate($client_id)
	{
		if (empty($client_id)) {
			return true; // Allow if no client_id (shouldn't happen, but fail open)
		}

		$key   = 'tracksure_rate_client_' . md5($client_id);
		$count = (int) get_transient($key);

		// Check if limit exceeded.
		if ($count >= $this->limits['client_per_minute']) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log(
					sprintf(
						'[TrackSure] Rate limit exceeded for client: %s (count: %d, limit: %d)',
						substr($client_id, 0, 8) . '...',
						$count,
						$this->limits['client_per_minute']
					)
				);
			}

			// Fire action for monitoring (Free/Pro can hook here).
			do_action('tracksure_rate_limit_exceeded', 'client', $client_id, $count);

			return false;
		}

		// Increment counter.
		set_transient($key, $count + 1, $this->limits['client_window']);

		return true;
	}

	/**
	 * Check IP rate limit.
	 *
	 * @param string $ip IP address.
	 * @return bool True if allowed.
	 */
	private function check_ip_rate($ip)
	{
		if (empty($ip)) {
			return true; // Allow if no IP (shouldn't happen, but fail open)
		}

		$key   = 'tracksure_rate_ip_' . md5($ip);
		$count = (int) get_transient($key);

		// Check if limit exceeded.
		if ($count >= $this->limits['ip_per_minute']) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log(
					sprintf(
						'[TrackSure] Rate limit exceeded for IP: %s (count: %d, limit: %d)',
						$ip,
						$count,
						$this->limits['ip_per_minute']
					)
				);
			}

			// Fire action for monitoring (Free/Pro can hook here).
			do_action('tracksure_rate_limit_exceeded', 'ip', $ip, $count);

			return false;
		}

		// Increment counter.
		set_transient($key, $count + 1, $this->limits['ip_window']);

		return true;
	}

	/**
	 * Get current rate limit status for client.
	 *
	 * @param string $client_id Client UUID.
	 * @return array Status with count, limit, remaining, reset_at.
	 */
	public function get_client_status($client_id)
	{
		$key       = 'tracksure_rate_client_' . md5($client_id);
		$count     = (int) get_transient($key);
		$limit     = $this->limits['client_per_minute'];
		$remaining = max(0, $limit - $count);

		return array(
			'count'            => $count,
			'limit'            => $limit,
			'remaining'        => $remaining,
			'reset_in_seconds' => $this->get_ttl($key),
		);
	}

	/**
	 * Get current rate limit status for IP.
	 *
	 * @param string $ip IP address.
	 * @return array Status with count, limit, remaining, reset_at.
	 */
	public function get_ip_status($ip)
	{
		$key       = 'tracksure_rate_ip_' . md5($ip);
		$count     = (int) get_transient($key);
		$limit     = $this->limits['ip_per_minute'];
		$remaining = max(0, $limit - $count);

		return array(
			'count'            => $count,
			'limit'            => $limit,
			'remaining'        => $remaining,
			'reset_in_seconds' => $this->get_ttl($key),
		);
	}

	/**
	 * Get transient TTL (time to live).
	 *
	 * @param string $key Transient key.
	 * @return int Seconds until expiration.
	 */
	private function get_ttl($key)
	{
		global $wpdb;

		$timeout_key = '_transient_timeout_' . $key;
		$timeout     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				$timeout_key
			)
		);

		if ($timeout) {
			return max(0, (int) $timeout - time());
		}

		return 0;
	}

	/**
	 * Clear rate limit for client (admin override).
	 *
	 * @param string $client_id Client UUID.
	 * @return bool Success.
	 */
	public function clear_client_limit($client_id)
	{
		$key = 'tracksure_rate_client_' . md5($client_id);
		return delete_transient($key);
	}

	/**
	 * Clear rate limit for IP (admin override).
	 *
	 * @param string $ip IP address.
	 * @return bool Success.
	 */
	public function clear_ip_limit($ip)
	{
		$key = 'tracksure_rate_ip_' . md5($ip);
		return delete_transient($key);
	}

	/**
	 * Get rate limit statistics (for diagnostics page).
	 *
	 * @return array Statistics.
	 */
	public function get_statistics()
	{
		global $wpdb;

		// Count active rate limit keys.
		$client_keys = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like('_transient_tracksure_rate_client_') . '%'
			)
		);

		$ip_keys = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like('_transient_tracksure_rate_ip_') . '%'
			)
		);

		return array(
			'active_clients' => (int) $client_keys,
			'active_ips'     => (int) $ip_keys,
			'limits'         => $this->limits,
		);
	}
}
