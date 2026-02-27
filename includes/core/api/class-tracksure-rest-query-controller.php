<?php

/**
 * REST API query controller.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for query diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure REST Query Controller
 *
 * Handles admin queries for analytics data.
 * Endpoints: GET /query/overview, /query/realtime, /query/sessions,
 * /query/journey, /query/funnel *
 * Direct database queries required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics.
 * All queries use $wpdb->prepare() for security.
 *
 * Table name interpolation is safe because:
 * - All table names use $wpdb->prefix (controlled by WordPress)
 * - No user input in table names
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query controller class.
 */
class TrackSure_REST_Query_Controller extends TrackSure_REST_Controller {






	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Session manager service.
	 *
	 * @var TrackSure_Session_Manager
	 */
	private $session_manager;

	/**
	 * Journey engine service.
	 *
	 * @var TrackSure_Journey_Engine
	 */
	private $journey_engine;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$core                  = TrackSure_Core::get_instance();
		$this->db              = $core->get_service( 'db' );
		$this->session_manager = $core->get_service( 'session_manager' );
		$this->journey_engine  = $core->get_service( 'journey' );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$date_args = $this->get_date_range_args(); // Now inherited from base class

		// GET /query/overview - Overview metrics.
		register_rest_route(
			$this->namespace,
			'/query/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);

		// GET /query/realtime - Realtime data.
		register_rest_route(
			$this->namespace,
			'/query/realtime',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_realtime' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// GET /query/sessions - Sessions list.
		register_rest_route(
			$this->namespace,
			'/query/sessions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sessions' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array_merge(
					$date_args,
					array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					)
				),
			)
		);

		// GET /query/journey/{session_id} - Session journey.
		register_rest_route(
			$this->namespace,
			'/query/journey/(?P<session_id>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_journey' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'session_id' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		// GET /query/funnel - Funnel analysis (extensible).
		register_rest_route(
			$this->namespace,
			'/query/funnel',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_funnel' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array_merge(
					$date_args,
					array(
						'steps' => array(
							'required' => true,
							'type'     => 'array',
						),
					)
				),
			)
		);

		// GET /query/registry - Get events/params registry.
		register_rest_route(
			$this->namespace,
			'/query/registry',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_registry' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// GET /query/logs - Get recent error logs.
		register_rest_route(
			$this->namespace,
			'/query/logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'level' => array(
						'type'    => 'string',
						'enum'    => array( 'error', 'warning', 'info', 'debug' ),
						'default' => null,
					),
				),
			)
		);

		// GET /query/traffic-sources - Traffic sources breakdown.
		register_rest_route(
			$this->namespace,
			'/query/traffic-sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_traffic_sources' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);

		// GET /query/attribution - Multi-touch attribution analysis.
		register_rest_route(
			$this->namespace,
			'/query/attribution',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_attribution' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array_merge(
					$date_args,
					array(
						'model' => array(
							'type'    => 'string',
							'default' => 'last_touch',
							'enum'    => array( 'first_touch', 'last_touch', 'linear', 'time_decay', 'position_based' ),
						),
					)
				),
			)
		);

		// GET /query/pages - Pages performance.
		register_rest_route(
			$this->namespace,
			'/query/pages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pages' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);

		// GET /query/visitors - Visitors (Journeys) list.
		register_rest_route(
			$this->namespace,
			'/query/visitors',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_visitors' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array_merge(
					$date_args,
					array(
						'filter' => array(
							'type'    => 'string',
							'default' => 'all',
							'enum'    => array( 'all', 'converted', 'returning' ),
						),
					)
				),
			)
		);

		// GET /query/visitor/{visitor_id}/journey - Visitor journey (all sessions).
		register_rest_route(
			$this->namespace,
			'/query/visitor/(?P<visitor_id>\d+)/journey',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_visitor_journey' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'visitor_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);

		// GET /query/active-pages - Real-time active pages.
		register_rest_route(
			$this->namespace,
			'/query/active-pages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_active_pages' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'minutes' => array(
						'type'    => 'integer',
						'default' => 5,
						'minimum' => 1,
						'maximum' => 60,
					),
				),
			)
		);

		// GET /query/attribution/insights - Journey insights (aggregated).
		register_rest_route(
			$this->namespace,
			'/query/attribution/insights',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_attribution_insights' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);

		// GET /query/attribution/paths - Top conversion paths.
		register_rest_route(
			$this->namespace,
			'/query/attribution/paths',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_attribution_paths' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array_merge(
					$date_args,
					array(
						'limit' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					)
				),
			)
		);

		// GET /query/attribution/device-patterns - Device journey patterns.
		register_rest_route(
			$this->namespace,
			'/query/attribution/device-patterns',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_device_patterns' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);

		// GET /query/conversions/breakdown - Single vs multi-touch breakdown.
		register_rest_route(
			$this->namespace,
			'/query/conversions/breakdown',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversions_breakdown' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);

		// GET /query/conversions/time-to-convert - Time to conversion histogram.
		register_rest_route(
			$this->namespace,
			'/query/conversions/time-to-convert',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_time_to_convert_histogram' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);

		// GET /query/attribution/models - Attribution models comparison.
		register_rest_route(
			$this->namespace,
			'/query/attribution/models',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_attribution_models' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);
	}

	/**
	 * Get overview metrics.
	 *
	 * Core returns basic aggregates. Free/Pro extend via filter.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_overview( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );
		$segment    = sanitize_text_field( $request->get_param( 'segment' ) );

		// Validate date format (YYYY-MM-DD).
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_start ) ) {
			return new WP_Error(
				'invalid_date_start',
				'Invalid start date format. Use YYYY-MM-DD.',
				array( 'status' => 400 )
			);
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_end ) ) {
			return new WP_Error(
				'invalid_date_end',
				'Invalid end date format. Use YYYY-MM-DD.',
				array( 'status' => 400 )
			);
		}

		// Check cache first (5 minute TTL).
		$cache_key = 'tracksure_overview_v2_' . md5( serialize( array( $date_start, $date_end, $segment ) ) );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $this->prepare_success( $cached );
		}

		// Get enhanced visitor-based metrics.
		$metrics = $this->db->get_enhanced_metrics( $date_start, $date_end );

		// Get device breakdown.
		$devices = $this->db->get_device_breakdown( $date_start, $date_end );

		// Get source breakdown (top 10).
		$top_sources = $this->db->get_source_breakdown( $date_start, $date_end, 10 );

		// Get country breakdown (top 10).
		$top_countries = $this->db->get_country_breakdown( $date_start, $date_end, 10 );

		// Get top pages (visitor-based).
		$top_pages = $this->db->get_top_pages_visitor_based( $date_start, $date_end, 10 );

		// Get daily breakdown for chart (visitor-based).
		$chart_data = $this->get_daily_breakdown( $date_start, $date_end, $segment );

		// Get previous period for comparison (Phase 1 - Comparative Metrics).
		$previous_period = $this->get_previous_period( $date_start, $date_end );

		// Get time intelligence insights (Phase 2 - Temporal Analysis).
		$time_intelligence = $this->get_time_intelligence( $date_end );

		// Combine into comprehensive response.
		$response = array(
			'metrics'           => $metrics,
			'previous_period'   => $previous_period,
			'devices'           => $devices,
			'top_sources'       => $top_sources,
			'top_countries'     => $top_countries,
			'top_pages'         => $top_pages,
			'chart_data'        => $chart_data,
			'time_intelligence' => $time_intelligence,
			'data_updated_at'   => gmdate( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filter overview metrics.
		 *
		 * Free/Pro can add attribution breakdowns, conversion data, etc.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $response Overview response data.
		 * @param string $date_start Start date.
		 * @param string $date_end End date.
		 */
		$response = apply_filters( 'tracksure_query_overview', $response, $date_start, $date_end );

		// Cache for 5 minutes.
		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

		return $this->prepare_success( $response );
	}

	/**
	 * Get daily breakdown for chart visualization.
	 *
	 * @param string $date_start Start date (YYYY-MM-DD).
	 * @param string $date_end End date (YYYY-MM-DD).
	 * @param string $segment Optional segment filter.
	 * @return array Chart data with labels and series.
	 */
	private function get_daily_breakdown( $date_start, $date_end, $segment = null ) {
		global $wpdb;

		$start_datetime = $date_start . ' 00:00:00';
		$end_datetime   = $date_end . ' 23:59:59';

		// Build WHERE clause for segment filtering.
		$segment_where = '';
		if ( ! empty( $segment ) ) {
			switch ( $segment ) {
				case 'new':
					$segment_where = ' AND s.session_number = 1';
					break;
				case 'returning':
					$segment_where = ' AND s.session_number > 1';
					break;
				case 'converted':
					$segment_where = ' AND EXISTS (SELECT 1 FROM ' .
						$wpdb->prefix . 'tracksure_events e2 WHERE e2.session_id = s.session_id AND e2.is_conversion = 1)';
					break;
			}
		}
		// OPTIMIZED: Use LEFT JOINs instead of correlated subqueries (100x faster).
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    DATE(s.started_at) as date,
                    COUNT(DISTINCT s.visitor_id) as visitors,
                    COUNT(DISTINCT CASE WHEN s.session_number = 1 THEN s.visitor_id END) as new_visitors,
                    COUNT(DISTINCT s.session_id) as sessions,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'page_view' THEN e.event_id END) as pageviews,
                    COUNT(DISTINCT c.conversion_id) as conversions,
                    COALESCE(SUM(c.conversion_value), 0) as revenue
                FROM {$wpdb->prefix}tracksure_sessions s
                LEFT JOIN {$wpdb->prefix}tracksure_events e ON s.session_id = e.session_id
                LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON s.session_id = c.session_id
                WHERE s.started_at >= %s AND s.started_at <= %s{$segment_where}
                GROUP BY DATE(s.started_at)
                ORDER BY date ASC",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		// Format for frontend charts.
		$labels       = array();
		$visitors     = array();
		$new_visitors = array();
		$sessions     = array();
		$pageviews    = array();
		$conversions  = array();
		$revenue      = array();

		foreach ( $results as $row ) {
			$labels[]       = gmdate( 'M j', strtotime( $row['date'] ) );
			$visitors[]     = (int) $row['visitors'];
			$new_visitors[] = (int) $row['new_visitors'];
			$sessions[]     = (int) $row['sessions'];
			$pageviews[]    = (int) $row['pageviews'];
			$conversions[]  = (int) $row['conversions'];
			$revenue[]      = (float) $row['revenue'];
		}

		return array(
			'labels'       => $labels,
			'visitors'     => $visitors,
			'new_visitors' => $new_visitors,
			'sessions'     => $sessions,
			'pageviews'    => $pageviews,
			'conversions'  => $conversions,
			'revenue'      => $revenue,
		);
	}

	/**
	 * Get realtime data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_realtime( $request ) {
		// Check cache first (10 second TTL - balance between freshness and performance).
		$cache_key = 'tracksure_realtime_v4';
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $this->prepare_success( $cached );
		}

		$active_sessions = $this->session_manager->get_realtime_sessions();

		// Ensure active_sessions is always an array.
		if ( ! is_array( $active_sessions ) ) {
			$active_sessions = array();
		}

		// Get recent events (last 5 minutes - reduced from 30 for better performance).
		$recent_events = $this->db->get_recent_events( 5 );

		// Ensure recent_events is always an array.
		if ( ! is_array( $recent_events ) ) {
			$recent_events = array();
		}

		// Group sessions by current page to create active_pages array.
		// Count UNIQUE VISITORS per page (not sessions) to prevent double-counting.
		$active_pages  = array();
		$page_visitors = array(); // page => array of visitor_ids

		foreach ( $active_sessions as $session ) {
			// Convert stdClass to array if needed.
			$session_array = is_object( $session ) ? (array) $session : $session;

			if ( isset( $session_array['current_page'] ) && ! empty( $session_array['current_page'] ) ) {
				$page = $session_array['current_page'];
				$vid  = isset( $session_array['visitor_id'] ) ? $session_array['visitor_id'] : null;
				if ( ! isset( $page_visitors[ $page ] ) ) {
					$page_visitors[ $page ] = array();
				}
				if ( $vid !== null ) {
					$page_visitors[ $page ][ $vid ] = true;
				}
			}
		}

		// Convert to format expected by React: [{path: string, users: number}].
		foreach ( $page_visitors as $path => $visitors ) {
			$active_pages[] = array(
				'path'  => $path,
				'users' => count( $visitors ),
			);
		}

		// Sort by most users first.
		usort(
			$active_pages,
			function ( $a, $b ) {
				return $b['users'] - $a['users'];
			}
		);

		// Format recent_events to match React expectations: [{event: string, page: string, title: string, time: string}].
		$formatted_events = array();
		foreach ( $recent_events as $event ) {
			// Convert stdClass to array if needed.
			$event_array = is_object( $event ) ? (array) $event : $event;

			$event_name = isset( $event_array['event_name'] ) ? $event_array['event_name'] : 'page_view';
			$page_url   = isset( $event_array['page_url'] ) ? $event_array['page_url'] : '/';
			$page_title = isset( $event_array['page_title'] ) && ! empty( $event_array['page_title'] )
				? $event_array['page_title']
				: $page_url;

			// Get Unix timestamp (already converted by DB query).
			$timestamp = isset( $event_array['occurred_at'] ) ? (int) $event_array['occurred_at'] : time();

			$formatted_event = array(
				'event'         => $event_name,
				'page'          => $page_url,
				'title'         => $page_title,
				'time'          => $timestamp,
				'is_conversion' => isset( $event_array['is_conversion'] ) && $event_array['is_conversion'] == 1,
			);

			// Add conversion value if it's a conversion event.
			if ( $formatted_event['is_conversion'] && isset( $event_array['conversion_value'] ) ) {
				$formatted_event['conversion_value'] = (float) $event_array['conversion_value'];
			}

			// Parse event_params if available.
			if ( ! empty( $event_array['event_params'] ) ) {
				$params = json_decode( $event_array['event_params'], true );
				if ( is_array( $params ) ) {
					$formatted_event['params'] = $params;
				}
			}

			$formatted_events[] = $formatted_event;
		}

		// Group active sessions by device, country, and source.
		// Count UNIQUE VISITORS (not sessions) to prevent double-counting when
		// one visitor has multiple sessions in the last 5 minutes.
		$device_visitors  = array(); // device => array of visitor_ids
		$country_visitors = array(); // country => array of visitor_ids
		$source_visitors  = array(); // source_key => array of visitor_ids
		$source_meta      = array(); // source_key => ['source' => ..., 'medium' => ...]

		foreach ( $active_sessions as $session ) {
			$session_array = is_object( $session ) ? (array) $session : $session;
			$vid           = isset( $session_array['visitor_id'] ) ? $session_array['visitor_id'] : null;

			// Count unique visitors per device.
			if ( isset( $session_array['device_type'] ) && ! empty( $session_array['device_type'] ) ) {
				$device = $session_array['device_type'];
				if ( ! isset( $device_visitors[ $device ] ) ) {
					$device_visitors[ $device ] = array();
				}
				if ( $vid !== null ) {
					$device_visitors[ $device ][ $vid ] = true;
				}
			}

			// Count unique visitors per country.
			if ( isset( $session_array['country'] ) && ! empty( $session_array['country'] ) ) {
				$country = $session_array['country'];
				if ( ! isset( $country_visitors[ $country ] ) ) {
					$country_visitors[ $country ] = array();
				}
				if ( $vid !== null ) {
					$country_visitors[ $country ][ $vid ] = true;
				}
			}

			// Count unique visitors per source/medium.
			$source = '(direct)';
			$medium = '(none)';

			if ( isset( $session_array['utm_source'] ) && ! empty( $session_array['utm_source'] ) ) {
				$source = $session_array['utm_source'];
			}

			if ( isset( $session_array['utm_medium'] ) && ! empty( $session_array['utm_medium'] ) ) {
				$medium = $session_array['utm_medium'];
			}

			$source_key = $source . ' / ' . $medium;
			if ( ! isset( $source_visitors[ $source_key ] ) ) {
				$source_visitors[ $source_key ] = array();
				$source_meta[ $source_key ]     = array(
					'source' => $source,
					'medium' => $medium,
				);
			}
			if ( $vid !== null ) {
				$source_visitors[ $source_key ][ $vid ] = true;
			}
		}

		// Format devices array (unique visitors).
		$active_devices = array();
		foreach ( $device_visitors as $device => $visitors ) {
			$active_devices[] = array(
				'device' => $device,
				'users'  => count( $visitors ),
			);
		}
		usort(
			$active_devices,
			function ( $a, $b ) {
				return $b['users'] - $a['users'];
			}
		);

		// Format countries array (unique visitors).
		$active_countries = array();
		foreach ( $country_visitors as $country => $visitors ) {
			$active_countries[] = array(
				'country' => $country,
				'users'   => count( $visitors ),
			);
		}
		usort(
			$active_countries,
			function ( $a, $b ) {
				return $b['users'] - $a['users'];
			}
		);

		// Format sources array (unique visitors).
		$active_sources = array();
		foreach ( $source_visitors as $source_key => $visitors ) {
			$active_sources[] = array(
				'source' => $source_meta[ $source_key ]['source'],
				'medium' => $source_meta[ $source_key ]['medium'],
				'users'  => count( $visitors ),
			);
		}
		usort(
			$active_sources,
			function ( $a, $b ) {
				return $b['users'] - $a['users'];
			}
		);

		// Count unique visitors, not sessions (1 visitor with 2 sessions = 1 active user)
		$unique_visitor_ids = array();
		foreach ( $active_sessions as $session ) {
			$s = is_object( $session ) ? (array) $session : $session;
			if ( ! empty( $s['visitor_id'] ) ) {
				$unique_visitor_ids[ $s['visitor_id'] ] = true;
			}
		}

		$data = array(
			'active_users'     => count( $unique_visitor_ids ),
			'active_pages'     => $active_pages,
			'active_devices'   => $active_devices,
			'active_countries' => $active_countries,
			'active_sources'   => $active_sources,
			'recent_events'    => $formatted_events,
			'timestamp'        => time(),
		);

		/**
		 * Filter realtime data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Realtime data.
		 */
		$data = apply_filters( 'tracksure_query_realtime', $data );

		// Cache for 10 seconds (balance between real-time freshness and performance).
		set_transient( $cache_key, $data, 10 );

		return $this->prepare_success( $data );
	}

	/**
	 * Get sessions list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_sessions( $request ) {
		$date_start = $request->get_param( 'date_start' );
		$date_end   = $request->get_param( 'date_end' );
		$segment    = sanitize_text_field( $request->get_param( 'segment' ) );
		$page       = $request->get_param( 'page' );
		$per_page   = $request->get_param( 'per_page' );

		// Check cache first (5 minute TTL).
		$cache_key = 'tracksure_sessions_v2_' . md5( serialize( array( $date_start, $date_end, $segment, $page, $per_page ) ) );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $this->prepare_success( $cached );
		}

		// OPTIMIZED: Get sessions and count in a single efficient call.
		$result = $this->db->get_sessions_with_count(
			array(
				'date_start' => $date_start,
				'date_end'   => $date_end,
				'segment'    => $segment,
				'page'       => $page,
				'per_page'   => $per_page,
			)
		);

		// Ensure sessions is always an array.
		if ( ! isset( $result['sessions'] ) || ! is_array( $result['sessions'] ) ) {
			$result['sessions'] = array();
		}

		// Ensure total is a number.
		if ( ! isset( $result['total'] ) || ! is_numeric( $result['total'] ) ) {
			$result['total'] = 0;
		}

		$data = array(
			'sessions'    => $result['sessions'],
			'total'       => (int) $result['total'],
			'page'        => (int) $page,
			'per_page'    => (int) $per_page,
			'total_pages' => $per_page > 0 ? ceil( $result['total'] / $per_page ) : 0,
		);

		// Cache for 5 minutes.
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		return $this->prepare_success( $data );
	}

	/**
	 * Get session journey.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_journey( $request ) {
		$session_id = $request->get_param( 'session_id' );

		$journey = $this->journey_engine->get_session_journey( $session_id );

		if ( empty( $journey ) ) {
			return $this->prepare_error(
				'session_not_found',
				'Session not found.',
				404
			);
		}

		/**
		 * Filter session journey data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $journey Journey data.
		 * @param int   $session_id Session ID.
		 */
		$journey = apply_filters( 'tracksure_query_journey', $journey, $session_id );

		return $this->prepare_success( $journey );
	}

	/**
	 * Get visitor journey (all sessions for a visitor).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_visitor_journey( $request ) {
		$visitor_id = (int) $request->get_param( 'visitor_id' );

		$journey = $this->journey_engine->get_visitor_journey( $visitor_id );

		if ( empty( $journey ) ) {
			return $this->prepare_error(
				'visitor_not_found',
				'Visitor not found.',
				404
			);
		}

		/**
		 * Filter visitor journey data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $journey Journey data.
		 * @param int   $visitor_id Visitor ID.
		 */
		$journey = apply_filters( 'tracksure_query_visitor_journey', $journey, $visitor_id );

		return $this->prepare_success( $journey );
	}

	/**
	 * Get funnel analysis.
	 *
	 * Core provides structure. Free/Pro implement calculation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_funnel( $request ) {
		$date_start = $request->get_param( 'date_start' );
		$date_end   = $request->get_param( 'date_end' );
		$steps      = $request->get_param( 'steps' );

		/**
		 * Calculate funnel data.
		 *
		 * Free implements basic funnel, Pro adds advanced segmentation.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $funnel_data Initial funnel structure.
		 * @param array  $steps Funnel steps (event names).
		 * @param string $date_start Start date.
		 * @param string $date_end End date.
		 */
		$funnel_data = apply_filters(
			'tracksure_calculate_funnel',
			array(
				'steps' => $steps,
				'data'  => array(),
			),
			$steps,
			$date_start,
			$date_end
		);

		return $this->prepare_success( $funnel_data );
	}

	/**
	 * Get registry.
	 *
	 * Returns centralized events/params registry for admin UI.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_registry( $request ) {
		$core     = TrackSure_Core::get_instance();
		$registry = $core->get_service( 'registry' );

		/**
		 * Filter registry data before sending to admin.
		 *
		 * Free/Pro can extend with additional events/params/destinations.
		 *
		 * @since 1.0.0
		 *
		 * @param array $registry_data Registry data.
		 */
		$registry_data = apply_filters(
			'tracksure_filter_registry',
			array(
				'version'      => 1,
				'events'       => $registry ? $registry->get_events() : array(),
				'destinations' => array(),
				'models'       => array( 'first_touch', 'last_touch', 'linear', 'time_decay', 'position_based' ),
			)
		);

		return $this->prepare_success( $registry_data );
	}

	/**
	 * Get recent error logs.
	 *
	 * Returns recent logs from the logger service for diagnostics UI.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_logs( $request ) {
		global $wpdb;

		$core   = TrackSure_Core::get_instance();
		$logger = $core->get_service( 'logger' );

		if ( ! $logger ) {
			return $this->prepare_success(
				array(
					'logs'    => array(),
					'message' => 'Logger service not available',
				)
			);
		}

		// Check if logs table exists.
		$table_name   = $wpdb->prefix . 'tracksure_logs';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

		if ( ! $table_exists ) {
			return $this->prepare_success(
				array(
					'logs'    => array(),
					'message' => 'Logs table not created. Please reinstall or update the plugin.',
				)
			);
		}

		$limit = (int) $request->get_param( 'limit' );
		$level = $request->get_param( 'level' );

		$logs = $logger->get_recent_logs( $limit, $level );

		return $this->prepare_success(
			array(
				'logs'  => $logs,
				'count' => count( $logs ),
				'limit' => $limit,
				'level' => $level,
			)
		);
	}

	/**
	 * Get multi-touch attribution data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_attribution( $request ) {
		global $wpdb;

		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );
		$model      = sanitize_text_field( $request->get_param( 'model' ) );
		// Check if attribution table exists.
		$attribution_table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables
				 WHERE table_schema = DATABASE()
				 AND table_name = %s',
				$wpdb->prefix . 'tracksure_conversion_attribution'
			)
		);

		if ( ! $attribution_table_exists ) {
			// Fallback to session-based attribution.
			return $this->get_simple_attribution( $date_start, $date_end, $model );
		}

		// Query attribution data by source/medium.
		$attribution_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    COALESCE(t.utm_source, '(direct)') as source,
                    COALESCE(t.utm_medium, '(none)') as medium,
                    t.channel,
                    COUNT(DISTINCT ca.conversion_id) as conversions,
                    COALESCE(SUM(ca.credit_value), 0) as revenue,
                    AVG(ca.credit_percent) as avg_credit,
                    COUNT(DISTINCT t.visitor_id) as visitors
                FROM {$wpdb->prefix}tracksure_conversion_attribution ca
                INNER JOIN {$wpdb->prefix}tracksure_touchpoints t ON t.touchpoint_id = ca.touchpoint_id
                INNER JOIN {$wpdb->prefix}tracksure_conversions c ON c.conversion_id = ca.conversion_id
                WHERE ca.attribution_model = %s
                  AND DATE(c.created_at) >= %s AND DATE(c.created_at) <= %s
                GROUP BY COALESCE(t.utm_source, '(direct)'), COALESCE(t.utm_medium, '(none)'), t.channel
                ORDER BY revenue DESC
                LIMIT 100",
				$model,
				$date_start,
				$date_end
			)
		);

		$total_conversions = 0;
		$total_revenue     = 0;

		$formatted = array_map(
			function ( $row ) use ( &$total_conversions, &$total_revenue ) {
				$conversions = absint( $row->conversions ?? 0 );
				$revenue     = floatval( $row->revenue ?? 0 );

				$total_conversions += $conversions;
				$total_revenue     += $revenue;

				return array(
					'source'      => sanitize_text_field( $row->source ?? '(direct)' ),
					'medium'      => sanitize_text_field( $row->medium ?? '(none)' ),
					'channel'     => sanitize_text_field( $row->channel ?? 'direct' ),
					'conversions' => $conversions,
					'revenue'     => round( $revenue, 2 ),
					'avg_credit'  => round( floatval( $row->avg_credit ?? 0 ), 4 ),
					'visitors'    => absint( $row->visitors ?? 0 ),
				);
			},
			! empty( $attribution_data ) ? $attribution_data : array()
		);

		return $this->prepare_success(
			array(
				'model'             => $model,
				'sources'           => $formatted,
				'total_conversions' => $total_conversions,
				'total_revenue'     => round( $total_revenue, 2 ),
			)
		);
	}

	/**
	 * Simple attribution fallback (when attribution table doesn't exist).
	 *
	 * @param string $date_start Start date.
	 * @param string $date_end End date.
	 * @param string $model Attribution model.
	 * @return WP_REST_Response
	 */
	private function get_simple_attribution( $date_start, $date_end, $model ) {
		global $wpdb;
		// Use session data as fallback.
		if ( $model === 'first_touch' ) {
			// Get first touch from visitor's first session.
			$attribution_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
                        COALESCE(s.utm_source, '(direct)') as source,
                        COALESCE(s.utm_medium, '(none)') as medium,
                        COUNT(DISTINCT c.conversion_id) as conversions,
                        COALESCE(SUM(c.conversion_value), 0) as revenue,
                        COUNT(DISTINCT s.visitor_id) as visitors
                    FROM {$wpdb->prefix}tracksure_conversions c
                    INNER JOIN {$wpdb->prefix}tracksure_sessions s ON c.visitor_id = s.visitor_id
                    WHERE s.session_number = 1
                        AND DATE(c.created_at) >= %s AND DATE(c.created_at) <= %s
                    GROUP BY COALESCE(s.utm_source, '(direct)'), COALESCE(s.utm_medium, '(none)')
                    ORDER BY conversions DESC
                    LIMIT 20",
					$date_start,
					$date_end
				)
			);
		} else {
			// Last touch - use conversion session.
			$attribution_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
                        COALESCE(s.utm_source, '(direct)') as source,
                        COALESCE(s.utm_medium, '(none)') as medium,
                        COUNT(DISTINCT c.conversion_id) as conversions,
                        COALESCE(SUM(c.conversion_value), 0) as revenue,
                        COUNT(DISTINCT s.visitor_id) as visitors
                    FROM {$wpdb->prefix}tracksure_conversions c
                    INNER JOIN {$wpdb->prefix}tracksure_sessions s ON c.session_id = s.session_id
                    WHERE DATE(c.created_at) >= %s AND DATE(c.created_at) <= %s
                    GROUP BY COALESCE(s.utm_source, '(direct)'), COALESCE(s.utm_medium, '(none)')
                    ORDER BY conversions DESC
                    LIMIT 20",
					$date_start,
					$date_end
				)
			);
		}

		$total_conversions = 0;
		$total_revenue     = 0;

		$formatted = array_map(
			function ( $row ) use ( &$total_conversions, &$total_revenue ) {
				$conversions = absint( $row->conversions ?? 0 );
				$revenue     = floatval( $row->revenue ?? 0 );

				$total_conversions += $conversions;
				$total_revenue     += $revenue;

				return array(
					'source'      => sanitize_text_field( $row->source ?? '(direct)' ),
					'medium'      => sanitize_text_field( $row->medium ?? '(none)' ),
					'conversions' => $conversions,
					'revenue'     => round( $revenue, 2 ),
					'avg_credit'  => 1.0, // Full credit in simple model
					'visitors'    => absint( $row->visitors ?? 0 ),
				);
			},
			! empty( $attribution_data ) ? $attribution_data : array()
		);

		return $this->prepare_success(
			array(
				'model'             => $model,
				'sources'           => $formatted,
				'total_conversions' => $total_conversions,
				'total_revenue'     => round( $total_revenue, 2 ),
				'fallback'          => true, // Indicate this is simplified attribution
			)
		);
	}

	/**
	 * Get traffic sources breakdown.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_traffic_sources( $request ) {
		global $wpdb;

		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );
		$segment    = sanitize_text_field( $request->get_param( 'segment' ) );

		// Validate date format.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_end ) ) {
			return new WP_Error( 'invalid_date', 'Invalid date format. Use YYYY-MM-DD.', array( 'status' => 400 ) );
		}

		// Check cache first (5 minute TTL).
		$cache_key = 'tracksure_traffic_sources_v2_' . md5( serialize( array( $date_start, $date_end, $segment ) ) );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $this->prepare_success( $cached );
		}
		// Build segment WHERE clause.
		$segment_where = '';
		if ( ! empty( $segment ) ) {
			switch ( $segment ) {
				case 'new':
					$segment_where = ' AND s.session_number = 1';
					break;
				case 'returning':
					$segment_where = ' AND s.session_number > 1';
					break;
				case 'converted':
					$segment_where = ' AND EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'tracksure_events e2 WHERE e2.session_id = s.session_id AND e2.is_conversion = 1)';
					break;
			}
		}

		// Query raw data with segment filtering (REVERTED - simpler is faster).
		$sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    COALESCE(NULLIF(s.utm_source, ''), '(direct)') as source,
                    COALESCE(NULLIF(s.utm_medium, ''), '(none)') as medium,
                    COUNT(DISTINCT s.session_id) as sessions,
                    COUNT(DISTINCT s.visitor_id) as unique_visitors,
                    COUNT(DISTINCT CASE WHEN e.is_conversion = 1 THEN s.session_id END) as conversions,
                    COALESCE(SUM(CASE WHEN e.is_conversion = 1 THEN e.conversion_value ELSE 0 END), 0) as revenue,
                    CASE 
                        WHEN COUNT(DISTINCT s.session_id) > 0 
                        THEN ROUND(COUNT(DISTINCT CASE WHEN e.is_conversion = 1 THEN s.session_id END) * 100.0 / COUNT(DISTINCT s.session_id), 2)
                        ELSE 0 
                    END as conversion_rate
                FROM {$wpdb->prefix}tracksure_sessions s
                LEFT JOIN {$wpdb->prefix}tracksure_events e ON s.session_id = e.session_id
                WHERE s.started_at >= %s AND s.started_at <= %s{$segment_where}
                GROUP BY COALESCE(NULLIF(s.utm_source, ''), '(direct)'), COALESCE(NULLIF(s.utm_medium, ''), '(none)')
                ORDER BY sessions DESC
                LIMIT 20",
				$date_start . ' 00:00:00',
				$date_end . ' 23:59:59'
			)
		);

		// Get attribution data separately (optional - faster without if table doesn't exist).
		$first_touch_map = array();
		$last_touch_map  = array();

		// Check if conversions table exists.
		$conversions_table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables
				 WHERE table_schema = DATABASE()
				 AND table_name = %s',
				$wpdb->prefix . 'tracksure_conversions'
			)
		);

		if ( $conversions_table_exists ) {
			$first_touch_attribution = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
                        COALESCE(NULLIF(first_touch_source, ''), '(direct)') as source,
                        COALESCE(NULLIF(first_touch_medium, ''), '(none)') as medium,
                        COUNT(*) as first_touch_conversions,
                        SUM(conversion_value) as first_touch_revenue
                    FROM {$wpdb->prefix}tracksure_conversions
                    WHERE converted_at >= %s AND converted_at <= %s
                    GROUP BY first_touch_source, first_touch_medium",
					$date_start . ' 00:00:00',
					$date_end . ' 23:59:59'
				)
			);

			foreach ( $first_touch_attribution as $row ) {
				$key                     = $row->source . '|' . $row->medium;
				$first_touch_map[ $key ] = array(
					'conversions' => (int) $row->first_touch_conversions,
					'revenue'     => (float) $row->first_touch_revenue,
				);
			}

			$last_touch_attribution = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
                        COALESCE(NULLIF(last_touch_source, ''), '(direct)') as source,
                        COALESCE(NULLIF(last_touch_medium, ''), '(none)') as medium,
                        COUNT(*) as last_touch_conversions,
                        SUM(conversion_value) as last_touch_revenue
                    FROM {$wpdb->prefix}tracksure_conversions
                    WHERE converted_at >= %s AND converted_at <= %s
                    GROUP BY last_touch_source, last_touch_medium",
					$date_start . ' 00:00:00',
					$date_end . ' 23:59:59'
				)
			);

			foreach ( $last_touch_attribution as $row ) {
				$key                    = $row->source . '|' . $row->medium;
				$last_touch_map[ $key ] = array(
					'conversions' => (int) $row->last_touch_conversions,
					'revenue'     => (float) $row->last_touch_revenue,
				);
			}
		}

		// Calculate total unique visitors and conversions across all sources.
		$total_conversions     = 0;
		$total_unique_visitors = 0;
		$source_array          = array();

		foreach ( $sources as $row ) {
			$conversions     = (int) $row->conversions;
			$revenue         = (float) $row->revenue;
			$conversion_rate = (float) $row->conversion_rate;
			$sessions        = (int) $row->sessions;
			$unique_visitors = (int) $row->unique_visitors;
			$aov             = $conversions > 0 ? round( $revenue / $conversions, 2 ) : 0;

			$total_conversions    += $conversions;
			$total_unique_visitors = max( $total_unique_visitors, $unique_visitors ); // Use max to avoid double counting

			$source_array[] = array(
				'source'                  => $row->source,
				'medium'                  => $row->medium,
				'sessions'                => $sessions,
				'unique_visitors'         => $unique_visitors,
				'conversions'             => $conversions,
				'revenue'                 => $revenue,
				'conversion_rate'         => $conversion_rate,
				'aov'                     => $aov,
				// Attribution data.
				'first_touch_conversions' => (int) $row->first_touch_conversions,
				'first_touch_revenue'     => (float) $row->first_touch_revenue,
				'last_touch_conversions'  => (int) $row->last_touch_conversions,
				'last_touch_revenue'      => (float) $row->last_touch_revenue,
			);
		}

		$data = array(
			'sources'           => $source_array,
			'total_conversions' => $total_conversions,
			'unique_visitors'   => $total_unique_visitors,
			'message'           => empty( $sources ) ? __( 'No traffic data yet', 'tracksure' ) : '',
		);

		// Cache for 5 minutes.
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		return $this->prepare_success( $data );
	}

	/**
	 * Get pages performance.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_pages( $request ) {
		global $wpdb;

		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );
		$segment    = sanitize_text_field( $request->get_param( 'segment' ) );

		// Validate date format.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_end ) ) {
			return new WP_Error( 'invalid_date', 'Invalid date format. Use YYYY-MM-DD.', array( 'status' => 400 ) );
		}

		// Check cache first (5 minute TTL).
		$cache_key = 'tracksure_pages_' . md5( serialize( array( $date_start, $date_end, $segment ) ) );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $this->prepare_success( $cached );
		}
		// Build segment WHERE clause.
		$segment_join  = '';
		$segment_where = '';
		if ( ! empty( $segment ) ) {
			$segment_join = "INNER JOIN {$wpdb->prefix}tracksure_sessions s ON e.session_id = s.session_id";
			switch ( $segment ) {
				case 'new':
					$segment_where = ' AND s.session_number = 1';
					break;
				case 'returning':
					$segment_where = ' AND s.session_number > 1';
					break;
				case 'converted':
					$segment_where = ' AND EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'tracksure_events e2 WHERE e2.session_id = s.session_id AND e2.is_conversion = 1)';
					break;
			}
		}

		// Query raw data with segment filtering.
		// FIXED: Include ALL events (not just page_view) to capture conversions from purchase events.
		// Count only page_view events for views, but include all events for conversions.
		$pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    e.page_url as path,
                    e.page_title as title,
                    SUM(CASE WHEN e.event_name = 'page_view' THEN 1 ELSE 0 END) as views,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'page_view' THEN e.session_id END) as sessions,
                    COUNT(DISTINCT c.conversion_id) as conversions,
                    COALESCE(SUM(c.conversion_value), 0) as revenue,
                    CASE 
                        WHEN COUNT(DISTINCT CASE WHEN e.event_name = 'page_view' THEN e.session_id END) > 0 
                        THEN ROUND(COUNT(DISTINCT c .
                        	conversion_id) * 100.0 / COUNT(DISTINCT CASE WHEN e.event_name = 'page_view' THEN e.session_id END), 2)
                        ELSE 0 
                    END as conversion_rate
                FROM {$wpdb->prefix}tracksure_events e
                {$segment_join}
                LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON c.event_id = e.event_id 
                    AND DATE(c.converted_at) >= %s AND DATE(c.converted_at) <= %s
                WHERE e.page_url IS NOT NULL AND e.page_url != ''
                  AND DATE(e.created_at) >= %s AND DATE(e.created_at) <= %s{$segment_where}
                GROUP BY e.page_url, e.page_title
                HAVING views > 0 OR conversions > 0
                ORDER BY conversions DESC, views DESC",
				$date_start,
				$date_end,
				$date_start,
				$date_end
			)
		);

		// Calculate time on page from actual page_exit events (user tracked time).
		// CRITICAL: JavaScript tracks time via page_exit event with time_on_page parameter.
		// Previous query was wrong - it calculated from page_view timestamps instead of using actual tracked time.
		$time_on_page = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    page_url,
                    FLOOR(AVG(time_seconds)) as avg_time_seconds
                FROM (
                    SELECT 
                        page_url,
                        CAST(JSON_UNQUOTE(JSON_EXTRACT(event_params, '$.time_on_page')) AS UNSIGNED) as time_seconds
                    FROM {$wpdb->prefix}tracksure_events
                    WHERE event_name IN ('page_exit', 'time_on_page_threshold')
                      AND DATE(created_at) >= %s AND DATE(created_at) <= %s
                      AND page_url IS NOT NULL
                      AND JSON_EXTRACT(event_params, '$.time_on_page') IS NOT NULL
                      AND JSON_EXTRACT(event_params, '$.time_on_page') > 0
                ) as tracked_times
                GROUP BY page_url",
				$date_start,
				$date_end
			),
			OBJECT_K
		);

		$bounce_rates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    landing_page as page_url,
                    ROUND(SUM(CASE WHEN event_count <= 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as bounce_rate
                FROM {$wpdb->prefix}tracksure_sessions
                WHERE DATE(started_at) >= %s AND DATE(started_at) <= %s
                  AND landing_page IS NOT NULL
                GROUP BY landing_page",
				$date_start,
				$date_end
			),
			OBJECT_K
		);

		// Calculate totals for header (from ALL pages, not just top 50).
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    SUM(CASE WHEN e.event_name = 'page_view' THEN 1 ELSE 0 END) as total_views,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'page_view' THEN e.session_id END) as total_sessions,
                    COUNT(DISTINCT c.conversion_id) as total_conversions,
                    COALESCE(SUM(c.conversion_value), 0) as total_revenue,
                    COUNT(DISTINCT e.page_url) as unique_pages
                FROM {$wpdb->prefix}tracksure_events e
                {$segment_join}
                LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON c.event_id = e.event_id 
                    AND DATE(c.converted_at) >= %s AND DATE(c.converted_at) <= %s
                WHERE e.page_url IS NOT NULL AND e.page_url != ''
                  AND DATE(e.created_at) >= %s AND DATE(e.created_at) <= %s{$segment_where}",
				$date_start,
				$date_end,
				$date_start,
				$date_end
			)
		);

		$data = array(
			'totals'     => array(
				'total_views'       => (int) ( $totals->total_views ?? 0 ),
				'total_sessions'    => (int) ( $totals->total_sessions ?? 0 ),
				'total_conversions' => (int) ( $totals->total_conversions ?? 0 ),
				'total_revenue'     => (float) ( $totals->total_revenue ?? 0 ),
				'unique_pages'      => (int) ( $totals->unique_pages ?? 0 ),
			),
			'pages'      => array_map(
				function ( $row ) use ( $time_on_page, $bounce_rates ) {
					$conversions = isset( $row->conversions ) ? (int) $row->conversions : 0;
					$revenue = isset( $row->revenue ) ? (float) $row->revenue : 0;
					$aov = $conversions > 0 ? round( $revenue / $conversions, 2 ) : 0;

					// Get time on page for this URL.
					$time_seconds = 0;
					if ( isset( $time_on_page[ $row->path ] ) && isset( $time_on_page[ $row->path ]->avg_time_seconds ) ) {
						$time_seconds = (int) $time_on_page[ $row->path ]->avg_time_seconds;
					}
					$minutes = floor( $time_seconds / 60 );
					$seconds = $time_seconds % 60;
					$time_formatted = sprintf( '%d:%02d', $minutes, $seconds );

					// Get bounce rate for this URL.
					$bounce_rate = 0;
					if ( isset( $bounce_rates[ $row->path ] ) && isset( $bounce_rates[ $row->path ]->bounce_rate ) ) {
						$bounce_rate = (float) $bounce_rates[ $row->path ]->bounce_rate;
					}
					$bounce_formatted = number_format( $bounce_rate, 1 ) . '%';

					return array(
						'path'            => $row->path,
						'title'           => isset( $row->title ) ? $row->title : '',
						'views'           => (int) $row->views,
						'sessions'        => isset( $row->sessions ) ? (int) $row->sessions : 0,
						'conversions'     => $conversions,
						'revenue'         => $revenue,
						'conversion_rate' => isset( $row->conversion_rate ) ? (float) $row->conversion_rate : 0,
						'aov'             => $aov,
						'time'            => $time_formatted,
						'bounce'          => $bounce_formatted,
					);
				},
				! empty( $pages ) ? $pages : array()
			),
			'breakdowns' => $this->get_pages_breakdowns( $date_start, $date_end, $segment_join, $segment_where ),
			'message'    => empty( $pages ) ? __( 'No page data yet', 'tracksure' ) : '',
		);

		// Cache for 5 minutes.
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		return $this->prepare_success( $data );
	}

	/**
	 * Get device/country/source breakdowns for pages.
	 *
	 * @param string $date_start Start date.
	 * @param string $date_end End date.
	 * @param string $segment_join Segment JOIN clause.
	 * @param string $segment_where Segment WHERE clause.
	 * @return array Device, country, and source breakdowns.
	 */
	private function get_pages_breakdowns( $date_start, $date_end, $segment_join = '', $segment_where = '' ) {
		global $wpdb;
		// Device breakdown
		$devices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					s.device_type,
					COUNT(DISTINCT s.session_id) as sessions,
					COUNT(DISTINCT CASE WHEN e.event_name = 'page_view' THEN e.event_id END) as pageviews,
					COUNT(DISTINCT CASE WHEN c.conversion_id IS NOT NULL THEN c.conversion_id END) as conversions,
					COALESCE(SUM(c.conversion_value), 0) as revenue
				FROM {$wpdb->prefix}tracksure_sessions s
				INNER JOIN {$wpdb->prefix}tracksure_events e ON s.session_id = e.session_id
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON c.event_id = e.event_id 
					AND DATE(c.converted_at) >= %s AND DATE(c.converted_at) <= %s
				WHERE DATE(s.started_at) >= %s AND DATE(s.started_at) <= %s
					AND s.device_type IS NOT NULL
				GROUP BY s.device_type
				ORDER BY pageviews DESC
				LIMIT 10",
				$date_start,
				$date_end,
				$date_start,
				$date_end
			)
		);

		// Country breakdown
		$countries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					s.country,
					COUNT(DISTINCT s.session_id) as sessions,
					COUNT(DISTINCT CASE WHEN e.event_name = 'page_view' THEN e.event_id END) as pageviews,
					COUNT(DISTINCT CASE WHEN c.conversion_id IS NOT NULL THEN c.conversion_id END) as conversions,
					COALESCE(SUM(c.conversion_value), 0) as revenue
				FROM {$wpdb->prefix}tracksure_sessions s
				INNER JOIN {$wpdb->prefix}tracksure_events e ON s.session_id = e.session_id
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON c.event_id = e.event_id 
					AND DATE(c.converted_at) >= %s AND DATE(c.converted_at) <= %s
				WHERE DATE(s.started_at) >= %s AND DATE(s.started_at) <= %s
					AND s.country IS NOT NULL
					AND s.country != ''
				GROUP BY s.country
				ORDER BY pageviews DESC
				LIMIT 10",
				$date_start,
				$date_end,
				$date_start,
				$date_end
			)
		);

		// Source breakdown
		$sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					CONCAT(COALESCE(s.utm_source, '(direct)'), ' / ', COALESCE(s.utm_medium, '(none)')) as source_medium,
					COUNT(DISTINCT s.session_id) as sessions,
					COUNT(DISTINCT CASE WHEN e.event_name = 'page_view' THEN e.event_id END) as pageviews,
					COUNT(DISTINCT CASE WHEN c.conversion_id IS NOT NULL THEN c.conversion_id END) as conversions,
					COALESCE(SUM(c.conversion_value), 0) as revenue
				FROM {$wpdb->prefix}tracksure_sessions s
				INNER JOIN {$wpdb->prefix}tracksure_events e ON s.session_id = e.session_id
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON c.event_id = e.event_id 
					AND DATE(c.converted_at) >= %s AND DATE(c.converted_at) <= %s
				WHERE DATE(s.started_at) >= %s AND DATE(s.started_at) <= %s
				GROUP BY source_medium
				ORDER BY pageviews DESC
				LIMIT 10",
				$date_start,
				$date_end,
				$date_start,
				$date_end
			)
		);

		// Format data
		$formatted_devices = array_map(
			function ( $row ) {
				return [
					'device'      => $row->device_type,
					'sessions'    => (int) $row->sessions,
					'pageviews'   => (int) $row->pageviews,
					'conversions' => (int) $row->conversions,
					'revenue'     => (float) $row->revenue,
				];
			},
			! empty( $devices ) ? $devices : []
		);

		$formatted_countries = array_map(
			function ( $row ) {
				return [
					'country_code' => $row->country, // ISO code (e.g., "US", "GB")
					'country'      => TrackSure_Countries::get_name( $row->country ), // Full name (e.g., "United States")
					'sessions'     => (int) $row->sessions,
					'pageviews'    => (int) $row->pageviews,
					'conversions'  => (int) $row->conversions,
					'revenue'      => (float) $row->revenue,
				];
			},
			! empty( $countries ) ? $countries : []
		);

		$formatted_sources = array_map(
			function ( $row ) {
				return [
					'source'      => $row->source_medium,
					'sessions'    => (int) $row->sessions,
					'pageviews'   => (int) $row->pageviews,
					'conversions' => (int) $row->conversions,
					'revenue'     => (float) $row->revenue,
				];
			},
			! empty( $sources ) ? $sources : []
		);

		return [
			'devices'   => $formatted_devices,
			'countries' => $formatted_countries,
			'sources'   => $formatted_sources,
		];
	}

	/**
	 * Get visitors list for Journeys page.
	 *
	 * Returns visitor-level aggregates across sessions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_visitors( $request ) {
		global $wpdb;

		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );
		$filter     = sanitize_text_field( $request->get_param( 'filter' ) );
		// Check cache first (5 minute TTL).
		$cache_key = 'tracksure_visitors_v2_' . md5( $date_start . $date_end . $filter );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $this->prepare_success( $cached );
		}

		// OPTIMIZED: Pre-calculate first/last touch in separate indexed queries.
		// Then join with main aggregation (10-100x faster than nested subqueries).
		$filter_clause = '';
		if ( $filter === 'converted' ) {
			$filter_clause = 'HAVING conversions > 0';
		} elseif ( $filter === 'returning' ) {
			$filter_clause = 'HAVING session_count > 1';
		}

		// Step 1: Get visitor aggregates with first/last session touch.
		$visitors = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    v.visitor_id,
                    UNIX_TIMESTAMP(v.created_at) as first_seen,
                    UNIX_TIMESTAMP(MAX(s.last_activity_at)) as last_seen,
                    COUNT(DISTINCT s.session_id) as session_count,
                    COUNT(DISTINCT c.conversion_id) as conversions,
                    COALESCE(SUM(c.conversion_value), 0) as revenue,
                    GROUP_CONCAT(DISTINCT s.device_type) as devices,
                    (SELECT CONCAT(COALESCE(utm_source, '(direct)'), '/', COALESCE(utm_medium, '(none)'))
                     FROM {$wpdb->prefix}tracksure_sessions
                     WHERE visitor_id = v.visitor_id
                     AND started_at >= %s AND started_at <= CONCAT(%s, ' 23:59:59')
                     ORDER BY started_at ASC
                     LIMIT 1) as first_touch,
                    (SELECT CONCAT(COALESCE(utm_source, '(direct)'), '/', COALESCE(utm_medium, '(none)'))
                     FROM {$wpdb->prefix}tracksure_sessions
                     WHERE visitor_id = v.visitor_id
                     AND started_at >= %s AND started_at <= CONCAT(%s, ' 23:59:59')
                     ORDER BY started_at DESC
                     LIMIT 1) as last_touch
                FROM {$wpdb->prefix}tracksure_visitors v
                INNER JOIN {$wpdb->prefix}tracksure_sessions s ON v.visitor_id = s.visitor_id
                LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON s.session_id = c.session_id
                WHERE s.started_at >= %s AND s.started_at <= CONCAT(%s, ' 23:59:59')
                GROUP BY v.visitor_id
                {$filter_clause}
                ORDER BY last_seen DESC",
				$date_start,
				$date_end,
				$date_start,
				$date_end,
				$date_start,
				$date_end
			)
		);

		$data = array(
			'visitors' => array_map(
				function ( $row ) {
					return array(
						'visitor_id'    => (int) $row->visitor_id,
						'first_seen'    => (int) $row->first_seen,
						'last_seen'     => (int) $row->last_seen,
						'session_count' => (int) $row->session_count,
						'conversions'   => (int) $row->conversions,
						'revenue'       => (float) $row->revenue,
						'first_touch'   => $row->first_touch,
						'last_touch'    => $row->last_touch,
						'devices'       => $row->devices,
					);
				},
				! empty( $visitors ) ? $visitors : array()
			),
			'total'    => count( $visitors ),
			'message'  => empty( $visitors ) ? __( 'No visitors found for the selected period.', 'tracksure' ) : '',
		);

		// Cache for 5 minutes (transient more reliable than wp_cache).
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		return $this->prepare_success( $data );
	}

	/**
	 * Get active pages (last N minutes).
	 *
	 * Returns pages currently being viewed by active users.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_active_pages( $request ) {
		global $wpdb;

		$minutes      = (int) $request->get_param( 'minutes' );
		$active_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    page_url as path,
                    page_title as title,
                    COUNT(DISTINCT session_id) as active_users,
                    MAX(timestamp) as last_activity,
                    COUNT(CASE WHEN is_conversion = 1 THEN 1 END) as recent_conversions
                FROM {$wpdb->prefix}tracksure_events
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)
                    AND page_url IS NOT NULL 
                    AND page_url != ''
                GROUP BY page_url, page_title
                ORDER BY active_users DESC, last_activity DESC
                LIMIT 10",
				$minutes
			)
		);

		$data = array(
			'pages'              => array_map(
				function ( $row ) {
					return array(
						'path'               => $row->path,
						'title'              => $row->title ? $row->title : $row->path,
						'active_users'       => (int) $row->active_users,
						'last_activity'      => $row->last_activity,
						'recent_conversions' => (int) $row->recent_conversions,
					);
				},
				! empty( $active_pages ) ? $active_pages : array()
			),
			'total_active_users' => array_sum( array_column( $active_pages, 'active_users' ) ),
			'timestamp'          => gmdate( 'Y-m-d H:i:s' ),
		);

		return $this->prepare_success( $data );
	}

	// get_date_range_args() removed - now inherited from base class TrackSure_REST_Controller.

	/**
	 * Calculate previous period metrics for comparison.
	 *
	 * @param string $date_start Current period start date.
	 * @param string $date_end Current period end date.
	 * @return array Previous period metrics.
	 */
	private function get_previous_period( $date_start, $date_end ) {
		// Calculate period duration
		$start = new DateTime( $date_start );
		$end   = new DateTime( $date_end );
		$diff  = $start->diff( $end )->days;

		// Calculate previous period dates
		$prev_end = clone $start;
		$prev_end->modify( '-1 day' );
		$prev_start = clone $prev_end;
		$prev_start->modify( '-' . $diff . ' days' );

		// Get metrics for previous period
		$prev_metrics = $this->db->get_enhanced_metrics(
			$prev_start->format( 'Y-m-d' ),
			$prev_end->format( 'Y-m-d' )
		);

		return array(
			'unique_visitors'              => isset( $prev_metrics['unique_visitors'] ) ? (float) $prev_metrics['unique_visitors'] : 0,
			'total_conversions'            => isset( $prev_metrics['total_conversions'] ) ? (float) $prev_metrics['total_conversions'] : 0,
			'conversion_rate'              => isset( $prev_metrics['conversion_rate'] ) ? (float) $prev_metrics['conversion_rate'] : 0,
			'total_revenue'                => isset( $prev_metrics['total_revenue'] ) ? (float) $prev_metrics['total_revenue'] : 0,
			'total_sessions'               => isset( $prev_metrics['total_sessions'] ) ? (float) $prev_metrics['total_sessions'] : 0,
			'avg_session_duration_seconds' => isset( $prev_metrics['avg_session_duration_seconds'] ) ? (float) $prev_metrics['avg_session_duration_seconds'] : 0,
			'bounce_rate'                  => isset( $prev_metrics['bounce_rate'] ) ? (float) $prev_metrics['bounce_rate'] : 0,
			'events_per_session'           => isset( $prev_metrics['events_per_session'] ) ? (float) $prev_metrics['events_per_session'] : 0,
		);
	}

	/**
	 * Calculate time intelligence insights.
	 *
	 * @param string $date_end Current period end date.
	 * @return array Time intelligence data.
	 */
	private function get_time_intelligence( $date_end ) {
		global $wpdb;

		// Use last 30 days for stable patterns
		$end_date   = new DateTime( $date_end );
		$start_date = clone $end_date;
		$start_date->modify( '-30 days' );

		$start_datetime = $start_date->format( 'Y-m-d' ) . ' 00:00:00';
		$end_datetime   = $end_date->format( 'Y-m-d' ) . ' 23:59:59';
		// Get best converting day
		$best_day = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					LOWER(DAYNAME(s.started_at)) as day,
					COUNT(DISTINCT c.conversion_id) as conversions,
					COUNT(DISTINCT s.session_id) as sessions,
					(COUNT(DISTINCT c.conversion_id) / COUNT(DISTINCT s.session_id) * 100) as conversion_rate
				FROM {$wpdb->prefix}tracksure_sessions s
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON s.session_id = c.session_id
				WHERE s.started_at >= %s AND s.started_at <= %s
				GROUP BY DAYOFWEEK(s.started_at), day
				HAVING conversions > 0
				ORDER BY conversion_rate DESC
				LIMIT 1",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		// Get peak hours (top 10)
		$peak_hours = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					HOUR(s.started_at) as hour,
					COUNT(DISTINCT s.visitor_id) as visitors,
					COUNT(DISTINCT c.conversion_id) as conversions
				FROM {$wpdb->prefix}tracksure_sessions s
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON s.session_id = c.session_id
				WHERE s.started_at >= %s AND s.started_at <= %s
				GROUP BY hour
				ORDER BY conversions DESC, visitors DESC
				LIMIT 10",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		// Get weekend vs weekday
		$weekend_weekday = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					CASE WHEN DAYOFWEEK(s.started_at) IN (1, 7) THEN 'weekend' ELSE 'weekday' END as period,
					COUNT(DISTINCT s.visitor_id) as visitors,
					COUNT(DISTINCT c.conversion_id) as conversions,
					(COUNT(DISTINCT c.conversion_id) / NULLIF(COUNT(DISTINCT s.session_id), 0) * 100) as conversion_rate
				FROM {$wpdb->prefix}tracksure_sessions s
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON s.session_id = c.session_id
				WHERE s.started_at >= %s AND s.started_at <= %s
				GROUP BY period",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		// Format response
		$intelligence = array();

		if ( $best_day ) {
			$intelligence['best_converting_day'] = array(
				'day'             => $best_day['day'],
				'conversion_rate' => (float) $best_day['conversion_rate'],
				'conversions'     => (int) $best_day['conversions'],
			);
		}

		if ( ! empty( $peak_hours ) ) {
			$intelligence['peak_hours'] = array_map(
				function ( $row ) {
					return array(
						'hour'        => (int) $row['hour'],
						'visitors'    => (int) $row['visitors'],
						'conversions' => (int) $row['conversions'],
					);
				},
				$peak_hours
			);
		}

		if ( count( $weekend_weekday ) === 2 ) {
			$weekend_data = null;
			$weekday_data = null;

			foreach ( $weekend_weekday as $row ) {
				if ( $row['period'] === 'weekend' ) {
					$weekend_data = $row;
				} else {
					$weekday_data = $row;
				}
			}

			if ( $weekend_data && $weekday_data ) {
				$intelligence['weekend_vs_weekday'] = array(
					'weekend' => array(
						'visitors'        => (int) $weekend_data['visitors'],
						'conversions'     => (int) $weekend_data['conversions'],
						'conversion_rate' => (float) $weekend_data['conversion_rate'],
					),
					'weekday' => array(
						'visitors'        => (int) $weekday_data['visitors'],
						'conversions'     => (int) $weekday_data['conversions'],
						'conversion_rate' => (float) $weekday_data['conversion_rate'],
					),
				);
			}
		}

		return $intelligence;
	}

	/**
	 * Get attribution insights (aggregated).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_attribution_insights( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );

		$core                  = TrackSure_Core::get_instance();
		$attribution_analytics = $core->get_service( 'attribution_analytics' );

		$insights = $attribution_analytics->get_journey_insights( $date_start, $date_end );

		return $this->prepare_success( $insights );
	}

	/**
	 * Get top conversion paths.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_attribution_paths( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );
		$limit      = absint( $request->get_param( 'limit' ) );

		$core                  = TrackSure_Core::get_instance();
		$attribution_analytics = $core->get_service( 'attribution_analytics' );

		$paths = $attribution_analytics->get_conversion_paths( $date_start, $date_end, $limit );

		return $this->prepare_success(
			array(
				'paths' => $paths,
				'total' => count( $paths ),
			)
		);
	}

	/**
	 * Get device journey patterns.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_device_patterns( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );

		$core                  = TrackSure_Core::get_instance();
		$attribution_analytics = $core->get_service( 'attribution_analytics' );

		$patterns = $attribution_analytics->get_device_patterns( $date_start, $date_end );

		return $this->prepare_success(
			array(
				'patterns' => $patterns,
				'total'    => count( $patterns ),
			)
		);
	}

	/**
	 * Get conversion breakdown (single vs multi-touch).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_conversions_breakdown( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );

		$core                  = TrackSure_Core::get_instance();
		$attribution_analytics = $core->get_service( 'attribution_analytics' );

		$breakdown = $attribution_analytics->get_conversion_breakdown( $date_start, $date_end );

		return $this->prepare_success( $breakdown );
	}

	/**
	 * Get time to conversion histogram.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_time_to_convert_histogram( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );

		$core                  = TrackSure_Core::get_instance();
		$attribution_analytics = $core->get_service( 'attribution_analytics' );

		$histogram = $attribution_analytics->get_time_to_convert_histogram( $date_start, $date_end );

		return $this->prepare_success(
			array(
				'buckets' => $histogram,
				'total'   => array_sum( array_column( $histogram, 'count' ) ),
			)
		);
	}

	/**
	 * Get attribution models comparison.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_attribution_models( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );

		$core                  = TrackSure_Core::get_instance();
		$attribution_analytics = $core->get_service( 'attribution_analytics' );

		$models = $attribution_analytics->get_attribution_models_comparison( $date_start, $date_end );

		return $this->prepare_success(
			array(
				'models'           => $models,
				'available_models' => array_keys( $models ),
			)
		);
	}
}
