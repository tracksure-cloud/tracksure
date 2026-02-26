<?php
/**
 * Customer journey engine.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Journey analysis requires direct DB queries for session timeline construction

/**
 *
 * TrackSure Journey Engine
 *
 * Tracks user journey touchpoints, builds session timelines,
 * and provides path analysis utilities.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Journey Engine class.
 */
class TrackSure_Journey_Engine {




	/**
	 * Instance.
	 *
	 * @var TrackSure_Journey_Engine
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
	 * @return TrackSure_Journey_Engine
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
		$this->db = TrackSure_DB::get_instance();
	}

	/**
	 * Get session journey timeline (ordered events with touchpoints).
	 *
	 * @param string $session_id Session UUID.
	 * @return array Journey timeline with events, touchpoints, and attribution.
	 */
	public function get_session_journey( $session_id ) {
		global $wpdb;
		// Get session data.
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT session_id, visitor_id, session_number, is_returning, 
                        UNIX_TIMESTAMP(started_at) as started_at, 
                        UNIX_TIMESTAMP(last_activity_at) as last_activity_at, 
                        referrer, landing_page, utm_source, utm_medium, utm_campaign, utm_term, utm_content, 
                        gclid, fbclid, msclkid, ttclid, twclid, li_fat_id, irclickid, ScCid, 
                        device_type, browser, os, country, region, city, event_count, created_at, updated_at 
                 FROM {$wpdb->prefix}tracksure_sessions WHERE session_id = %s",
				$session_id
			),
			ARRAY_A
		);

		if ( ! $session ) {
			return array();
		}

		// Get events for this session.
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    event_id,
                    event_name,
                    page_url,
                    page_path,
                    page_title,
                    event_params,
                    UNIX_TIMESTAMP(occurred_at) as occurred_at,
                    is_conversion,
                    conversion_value
                FROM {$wpdb->prefix}tracksure_events
                WHERE session_id = %s
                ORDER BY occurred_at ASC",
				$session_id
			),
			ARRAY_A
		);

		// Get touchpoints for this session.
		$touchpoints = $this->db->get_session_touchpoints( $session_id );

		$journey   = array();
		$prev_time = null;

		foreach ( $events as $event ) {
			$timestamp = (int) $event['occurred_at'];

			$journey_item = array(
				'event_id'         => $event['event_id'],
				'event_name'       => $event['event_name'],
				'page_url'         => $event['page_url'],
				'page_path'        => $event['page_path'],
				'page_title'       => $event['page_title'],
				'event_params'     => ! empty( $event['event_params'] ) ? json_decode( $event['event_params'], true ) : array(),
				'occurred_at'      => $event['occurred_at'],
				'is_conversion'    => (bool) $event['is_conversion'],
				'conversion_value' => $event['conversion_value'] ? (float) $event['conversion_value'] : null,
				'time_delta'       => null,
			);

			// Calculate time delta from previous event.
			if ( null !== $prev_time ) {
				$delta_seconds              = $timestamp - $prev_time;
				$journey_item['time_delta'] = $this->format_time_delta( $delta_seconds );
			}

			$journey[] = $journey_item;
			$prev_time = $timestamp;
		}

		// Build attribution data with safe null checks.
		$attribution = array(
			'first_touch' => array(
				'source'       => ! empty( $session['utm_source'] ) ? $session['utm_source'] : '(direct)',
				'medium'       => ! empty( $session['utm_medium'] ) ? $session['utm_medium'] : '(none)',
				'campaign'     => ! empty( $session['utm_campaign'] ) ? $session['utm_campaign'] : null,
				'referrer'     => ! empty( $session['referrer'] ) ? $session['referrer'] : null,
				'landing_page' => ! empty( $session['landing_page'] ) ? $session['landing_page'] : null,
			),
		);

		// Get last touchpoint for last_touch attribution.
		if ( ! empty( $touchpoints ) ) {
			$last_touchpoint           = end( $touchpoints );
			$attribution['last_touch'] = array(
				'source'   => ! empty( $last_touchpoint['utm_source'] ) ? $last_touchpoint['utm_source'] : '(direct)',
				'medium'   => ! empty( $last_touchpoint['utm_medium'] ) ? $last_touchpoint['utm_medium'] : '(none)',
				'campaign' => ! empty( $last_touchpoint['utm_campaign'] ) ? $last_touchpoint['utm_campaign'] : null,
				'page_url' => ! empty( $last_touchpoint['page_url'] ) ? $last_touchpoint['page_url'] : null,
			);
		} else {
			$attribution['last_touch'] = $attribution['first_touch'];
		}

		return array(
			'session'     => array(
				'sessionId'     => $session['session_id'],
				'visitorId'     => (int) $session['visitor_id'],
				'sessionNumber' => (int) $session['session_number'],
				'isReturning'   => (bool) $session['is_returning'],
				'startedAt'     => $session['started_at'],
				'lastSeenAt'    => $session['last_activity_at'],
				'source'        => ! empty( $session['utm_source'] ) ? $session['utm_source'] : null,
				'medium'        => ! empty( $session['utm_medium'] ) ? $session['utm_medium'] : null,
				'campaign'      => ! empty( $session['utm_campaign'] ) ? $session['utm_campaign'] : null,
				'device'        => ! empty( $session['device_type'] ) ? $session['device_type'] : null,
				'browser'       => ! empty( $session['browser'] ) ? $session['browser'] : null,
				'os'            => ! empty( $session['os'] ) ? $session['os'] : null,
				'country'       => ! empty( $session['country'] ) ? $session['country'] : null,
				'city'          => ! empty( $session['city'] ) ? $session['city'] : null,
				'referrer'      => ! empty( $session['referrer'] ) ? $session['referrer'] : null,
				'landingPage'   => ! empty( $session['landing_page'] ) ? $session['landing_page'] : null,
			),
			'events'      => $journey,
			'touchpoints' => $touchpoints,
			'attribution' => $attribution,
		);
	}

	/**
	 * Format time delta in human-readable format.
	 *
	 * @param int $seconds Seconds.
	 * @return string Formatted delta (e.g., "18s", "2m 30s", "1h 5m").
	 */
	private function format_time_delta( $seconds ) {
		if ( $seconds < 60 ) {
			return sprintf( '%ds', $seconds );
		} elseif ( $seconds < 3600 ) {
			$minutes = floor( $seconds / 60 );
			$secs    = $seconds % 60;
			return sprintf( '%dm %ds', $minutes, $secs );
		} else {
			$hours   = floor( $seconds / 3600 );
			$minutes = floor( ( $seconds % 3600 ) / 60 );
			return sprintf( '%dh %dm', $hours, $minutes );
		}
	}

	/**
	 * Get visitor journey summary (all sessions).
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return array Journey summary with sessions and conversion count.
	 */
	public function get_visitor_journey_summary( $visitor_id ) {
		global $wpdb;
		// Get all sessions for this visitor.
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, visitor_id, session_number, is_returning, started_at, last_activity_at, 
                        referrer, landing_page, utm_source, utm_medium, utm_campaign, utm_term, utm_content, 
                        gclid, fbclid, msclkid, ttclid, twclid, li_fat_id, irclickid, ScCid, 
                        device_type, browser, os, country, region, city, event_count, created_at, updated_at 
                 FROM {$wpdb->prefix}tracksure_sessions WHERE visitor_id = %d ORDER BY started_at ASC",
				$visitor_id
			)
		);

		// Get conversion count.
		$conversion_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_conversions WHERE visitor_id = %d",
				$visitor_id
			)
		);

		// Get touchpoints.
		$touchpoints = $this->db->get_visitor_touchpoints( $visitor_id );

		return array(
			'visitor_id'       => $visitor_id,
			'session_count'    => count( $sessions ),
			'conversion_count' => (int) $conversion_count,
			'sessions'         => $sessions,
			'touchpoints'      => $touchpoints,
		);
	}

	/**
	 * Get common converting paths (extensible via filter).
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param int    $limit Limit.
	 * @return array Common paths with conversion counts.
	 */
	public function get_common_paths( $start_date, $end_date, $limit = 50 ) {
		/**
		 * Filter common converting paths.
		 *
		 * Extensions can implement path aggregation logic.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $paths Empty array.
		 * @param string $start_date Start date.
		 * @param string $end_date End date.
		 * @param int    $limit Limit.
		 */
		return apply_filters( 'tracksure_common_paths', array(), $start_date, $end_date, $limit );
	}

	/**     * Get complete visitor journey (all sessions with events and funnel).
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return array Complete journey with sessions, events, funnel.
	 */
	public function get_visitor_journey( $visitor_id ) {
		global $wpdb;
		// Get all sessions for this visitor (INDEXED query - FAST).
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, visitor_id, session_number, 
                        UNIX_TIMESTAMP(started_at) as started_at, 
                        UNIX_TIMESTAMP(last_activity_at) as last_activity_at,
                        utm_source, utm_medium, utm_campaign, device_type, browser, os,
                        referrer, landing_page, event_count
                 FROM {$wpdb->prefix}tracksure_sessions
                 WHERE visitor_id = %d
                 ORDER BY started_at ASC",
				$visitor_id
			),
			ARRAY_A
		);

		if ( empty( $sessions ) ) {
			return array();
		}

		// Enrich each session with events using EXISTING method.
		foreach ( $sessions as &$session ) {
			$journey_data      = $this->get_session_journey( $session['session_id'] );
			$session['events'] = isset( $journey_data['events'] ) ? $journey_data['events'] : array();
		}

		// Calculate aggregated funnel across all sessions.
		$funnel_steps = $this->calculate_visitor_funnel( $sessions );

		return array(
			'visitor_id'     => $visitor_id,
			'total_sessions' => count( $sessions ),
			'total_events'   => array_sum( array_column( $sessions, 'event_count' ) ),
			'sessions'       => $sessions,
			'funnel_steps'   => $funnel_steps,
			'first_seen'     => $sessions[0]['started_at'],
			'last_seen'      => end( $sessions )['last_activity_at'],
		);
	}

	/**
	 * Calculate aggregated funnel across all visitor sessions.
	 *
	 * @param array $sessions Array of sessions with events.
	 * @return array Funnel steps with counts and percentages.
	 */
	private function calculate_visitor_funnel( $sessions ) {
		// Collect all events from all sessions.
		$all_events = array();
		foreach ( $sessions as $session ) {
			if ( ! empty( $session['events'] ) ) {
				$all_events = array_merge( $all_events, $session['events'] );
			}
		}

		// Define eCommerce funnel steps.
		$steps = array(
			array(
				'event' => 'page_view',
				'label' => 'Visited Site',
			),
			array(
				'event' => 'view_item',
				'label' => 'Viewed Product',
			),
			array(
				'event' => 'add_to_cart',
				'label' => 'Added to Cart',
			),
			array(
				'event' => 'begin_checkout',
				'label' => 'Started Checkout',
			),
			array(
				'event' => 'purchase',
				'label' => 'Completed Purchase',
			),
		);

		$funnel           = array();
		$first_step_count = 0;

		// First pass: count events per step.
		$step_counts = array();
		foreach ( $steps as $index => $step ) {
			$count = 0;
			foreach ( $all_events as $event ) {
				if ( $event['event_name'] === $step['event'] ) {
					++$count;
				}
			}

			// First step should at least equal session count.
			if ( $index === 0 ) {
				$count            = max( $count, count( $sessions ) );
				$first_step_count = $count;
			}

			$step_counts[ $index ] = $count;
		}

		// Second pass: calculate percentages relative to first step.
		foreach ( $steps as $index => $step ) {
			$count      = $step_counts[ $index ];
			$percentage = $first_step_count > 0 ? ( $count / $first_step_count * 100 ) : 0;

			$funnel[] = array(
				'step'       => $step['event'],
				'label'      => $step['label'],
				'count'      => $count,
				'percentage' => round( $percentage, 1 ),
			);
		}

		return $funnel;
	}

	/**     * Build path string from touchpoints.
	 *
	 * @param array $touchpoints Touchpoints array.
	 * @return string Path string (e.g., "google/organic > facebook/social > direct").
	 */
	public function build_path_string( $touchpoints ) {
		$path_parts = array();

		foreach ( $touchpoints as $touchpoint ) {
			$source       = isset( $touchpoint->source ) ? $touchpoint->source : '(unknown)';
			$medium       = isset( $touchpoint->medium ) ? $touchpoint->medium : '(none)';
			$path_parts[] = $source . '/' . $medium;
		}

		return implode( ' > ', $path_parts );
	}

	/**
	 * Calculate time to convert in days.
	 *
	 * @param string $first_seen_at First visitor timestamp.
	 * @param string $converted_at Conversion timestamp.
	 * @return int Days to convert.
	 */
	public function calculate_days_to_convert( $first_seen_at, $converted_at ) {
		$first = strtotime( $first_seen_at );
		$conv  = strtotime( $converted_at );
		return max( 0, floor( ( $conv - $first ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Calculate sessions to convert.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @param int $conversion_session_id Session where conversion happened.
	 * @return int Session count up to conversion.
	 */
	public function calculate_sessions_to_convert( $visitor_id, $conversion_session_id ) {
		global $wpdb;
		// Get session where conversion happened.
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT session_number FROM {$wpdb->prefix}tracksure_sessions WHERE id = %d",
				$conversion_session_id
			)
		);

		return $session ? (int) $session->session_number : 0;
	}
}
