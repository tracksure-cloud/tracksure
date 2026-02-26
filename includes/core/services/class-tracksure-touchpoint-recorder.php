<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Touchpoint recording requires direct DB queries for attribution tracking

/**
 *
 * TrackSure Touchpoint Recorder
 *
 * Records visitor touchpoints for attribution tracking.
 * Creates touchpoints on new sessions or UTM parameter changes.
 *
 * @package TrackSure\Core\Services
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * TrackSure Touchpoint Recorder class.
 */
class TrackSure_Touchpoint_Recorder
{



	/**
	 * Instance.
	 *
	 * @var TrackSure_Touchpoint_Recorder
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Touchpoint_Recorder
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
		$this->db = TrackSure_DB::get_instance();
	}

	/**
	 * Maybe record a touchpoint.
	 *
	 * Touchpoints are created when:
	 * 1. New session starts
	 * 2. UTM parameters change within existing session
	 * 3. Conversion event occurs
	 *
	 * @param array $session_data Session data with UTM parameters.
	 * @param array $event_data Event data (optional, for conversion touchpoints).
	 * @return int|false Touchpoint ID on success, false on failure.
	 */
	public function maybe_record_touchpoint($session_data, $event_data = array())
	{
		global $wpdb;

		if (empty($session_data['visitor_id']) || empty($session_data['session_id'])) {
			return false;
		}

		// Check if we should create a touchpoint.
		$should_create = $this->should_create_touchpoint($session_data);

		if (! $should_create) {
			return false;
		}

		// Get next sequence number for this visitor.
		$touchpoint_seq = $this->get_next_touchpoint_seq($session_data['visitor_id']);

		// Calculate channel from UTM parameters.
		$channel = $this->calculate_channel($session_data);

		// Get page data from recent event if not provided.
		if (empty($event_data['page_url']) && ! empty($session_data['session_id'])) {
			$recent_event = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT page_url, page_title FROM {$wpdb->prefix}tracksure_events
                WHERE session_id = %s
                ORDER BY created_at DESC LIMIT 1",
					$session_data['session_id']
				),
				ARRAY_A
			);

			if ($recent_event) {
				$event_data['page_url']   = $recent_event['page_url'];
				$event_data['page_title'] = $recent_event['page_title'];
			} else {
				// If no events yet, try to get landing page from session.
				$session = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT landing_page FROM {$wpdb->prefix}tracksure_sessions
                    WHERE session_id = %s",
						$session_data['session_id']
					),
					ARRAY_A
				);

				if ($session && ! empty($session['landing_page'])) {
					$event_data['page_url'] = $session['landing_page'];
					// Extract title from URL if not available.
					$path                     = wp_parse_url($session['landing_page'], PHP_URL_PATH);
					$event_data['page_title'] = ! empty($path) ? basename($path) : 'Home';
				}
			}
		}

		// Prepare touchpoint data.
		$touchpoint_data = array(
			'visitor_id'     => $session_data['visitor_id'],
			'session_id'     => $session_data['session_id'],
			'event_id'       => ! empty($event_data['event_id']) ? $event_data['event_id'] : null,
			'touchpoint_seq' => $touchpoint_seq,
			'touched_at'     => current_time('mysql', true),
			'utm_source'     => ! empty($session_data['utm_source']) ? $session_data['utm_source'] : null,
			'utm_medium'     => ! empty($session_data['utm_medium']) ? $session_data['utm_medium'] : null,
			'utm_campaign'   => ! empty($session_data['utm_campaign']) ? $session_data['utm_campaign'] : null,
			'utm_term'       => ! empty($session_data['utm_term']) ? $session_data['utm_term'] : null,
			'utm_content'    => ! empty($session_data['utm_content']) ? $session_data['utm_content'] : null,
			'channel'        => $channel,
			'page_url'       => ! empty($event_data['page_url']) ? $event_data['page_url'] : null,
			'page_title'     => ! empty($event_data['page_title']) ? $event_data['page_title'] : null,
			'page_path'      => $this->extract_page_path(! empty($event_data['page_url']) ? $event_data['page_url'] : ''),
			'referrer'       => ! empty($session_data['referrer']) ? $session_data['referrer'] : null,
			'created_at'     => current_time('mysql', true),
		);

		// Insert touchpoint.
		$result = $wpdb->insert(
			$wpdb->prefix . 'tracksure_touchpoints',
			$touchpoint_data,
			array(
				'%d', // visitor_id
				'%s', // session_id
				'%s', // event_id
				'%d', // touchpoint_seq
				'%s', // touched_at
				'%s', // utm_source
				'%s', // utm_medium
				'%s', // utm_campaign
				'%s', // utm_term
				'%s', // utm_content
				'%s', // channel
				'%s', // page_url
				'%s', // page_title
				'%s', // page_path
				'%s', // referrer
				'%s', // created_at
			)
		);

		if ($result === false) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log('[TrackSure] Failed to insert touchpoint: ' . $wpdb->last_error);
			}
			return false;
		}

		$touchpoint_id = $wpdb->insert_id;

		/**
		 * Fires after a touchpoint is recorded.
		 *
		 * @since 1.0.0
		 * @param int   $touchpoint_id Touchpoint ID.
		 * @param array $touchpoint_data Touchpoint data.
		 */
		do_action('tracksure_touchpoint_recorded', $touchpoint_id, $touchpoint_data);

		return $touchpoint_id;
	}

	/**
	 * Check if we should create a touchpoint.
	 *
	 * @param array $session_data Session data.
	 * @return bool
	 */
	private function should_create_touchpoint($session_data)
	{
		global $wpdb;
		// Always create touchpoint for new sessions.
		if (! empty($session_data['session_number']) && $session_data['session_number'] == 1) {
			return true;
		}

		// Check if UTM parameters changed from last touchpoint.
		$last_touchpoint = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT utm_source, utm_medium, utm_campaign, utm_term, utm_content
			FROM {$wpdb->prefix}tracksure_touchpoints
			WHERE visitor_id = %d
			ORDER BY touchpoint_seq DESC
			LIMIT 1",
				$session_data['visitor_id']
			),
			ARRAY_A
		);

		if (! $last_touchpoint) {
			return true; // No previous touchpoint
		}

		// Check if any UTM parameter changed.
		$utm_changed = (
			$this->normalize_utm($session_data['utm_source'] ?? '') !== $this->normalize_utm($last_touchpoint['utm_source'] ?? '') ||
			$this->normalize_utm($session_data['utm_medium'] ?? '') !== $this->normalize_utm($last_touchpoint['utm_medium'] ?? '') ||
			$this->normalize_utm($session_data['utm_campaign'] ?? '') !== $this->normalize_utm($last_touchpoint['utm_campaign'] ?? '') ||
			$this->normalize_utm($session_data['utm_term'] ?? '') !== $this->normalize_utm($last_touchpoint['utm_term'] ?? '') ||
			$this->normalize_utm($session_data['utm_content'] ?? '') !== $this->normalize_utm($last_touchpoint['utm_content'] ?? '')
		);

		return $utm_changed;
	}

	/**
	 * Get next touchpoint sequence number for visitor.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return int
	 */
	private function get_next_touchpoint_seq($visitor_id)
	{
		global $wpdb;
		$max_seq = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(touchpoint_seq) FROM {$wpdb->prefix}tracksure_touchpoints WHERE visitor_id = %d",
				$visitor_id
			)
		);

		return $max_seq ? ($max_seq + 1) : 1;
	}

	/**
	 * Calculate channel from UTM parameters and referrer.
	 *
	 * Uses Attribution Resolver for consistent channel classification.
	 *
	 * @param array $session_data Session data.
	 * @return string Channel name.
	 */
	private function calculate_channel($session_data)
	{
		// Use Attribution Resolver for consistent channel calculation.
		$attribution_resolver = TrackSure_Attribution_Resolver::get_instance();
		$resolved             = $attribution_resolver->resolve($session_data);

		return $resolved['channel'];
	}

	/**
	 * Extract page path from URL.
	 *
	 * @param string $url Full URL.
	 * @return string Page path.
	 */
	private function extract_page_path($url)
	{
		if (empty($url)) {
			return '';
		}

		$parsed = wp_parse_url($url);
		return ! empty($parsed['path']) ? $parsed['path'] : '/';
	}

	/**
	 * Normalize UTM parameter for comparison.
	 *
	 * @param string $utm UTM value.
	 * @return string Normalized value.
	 */
	private function normalize_utm($utm)
	{
		return strtolower(trim($utm));
	}

	/**
	 * Get touchpoints for a visitor.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @param int $limit Limit.
	 * @return array Touchpoints.
	 */
	public function get_visitor_touchpoints($visitor_id, $limit = 100)
	{
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
			FROM {$wpdb->prefix}tracksure_touchpoints
			WHERE visitor_id = %d
			ORDER BY touchpoint_seq ASC
			LIMIT %d",
				$visitor_id,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get touchpoints for a conversion (lookback window).
	 *
	 * @param int    $visitor_id Visitor ID.
	 * @param string $converted_at Conversion datetime.
	 * @param int    $lookback_days Lookback window in days (default 30).
	 * @return array Touchpoints.
	 */
	public function get_conversion_touchpoints($visitor_id, $converted_at, $lookback_days = 30)
	{
		global $wpdb;
		$lookback_date = gmdate('Y-m-d H:i:s', strtotime($converted_at . " - {$lookback_days} days"));

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
			FROM {$wpdb->prefix}tracksure_touchpoints
			WHERE visitor_id = %d
			AND touched_at >= %s
			AND touched_at <= %s
			ORDER BY touchpoint_seq ASC",
				$visitor_id,
				$lookback_date,
				$converted_at
			),
			ARRAY_A
		);
	}
}
