<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Attribution analytics requires direct DB queries for aggregated insights

/**
 * TrackSure Attribution Analytics Service
 *
 * Provides aggregated attribution insights for multi-session visitor journeys.
 * - Journey metrics (avg sessions, time to convert)
 * - Device journey patterns
 * - Attribution model comparison (all 5 models included)
 * - Conversion breakdown (single vs multi-touch)
 *
 * @package TrackSure\Core\Services
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Attribution Analytics class.
 */
class TrackSure_Attribution_Analytics {


	/**
	 * Instance.
	 *
	 * @var TrackSure_Attribution_Analytics
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
	 * @return TrackSure_Attribution_Analytics
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
	 * Get journey insights (aggregated).
	 *
	 * Returns overall metrics about visitor journeys:
	 * - Average sessions to convert
	 * - Average time to convert (days)
	 * - Multi-touch vs single-touch conversion breakdown
	 * - Total conversions and revenue
	 *
	 * @param string $date_start Start date (Y-m-d).
	 * @param string $date_end End date (Y-m-d).
	 * @return array Journey metrics.
	 */
	public function get_journey_insights( $date_start, $date_end ) {
		global $wpdb;
		// Get aggregated conversion metrics.
		$insights = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(DISTINCT c.conversion_id) as total_conversions,
					SUM(c.conversion_value) as total_revenue,
					AVG(c.sessions_to_convert) as avg_sessions_to_convert,
					AVG(c.time_to_convert) as avg_time_to_convert_seconds,
					SUM(CASE WHEN c.sessions_to_convert > 1 THEN 1 ELSE 0 END) as multi_touch_count,
					SUM(CASE WHEN c.sessions_to_convert = 1 THEN 1 ELSE 0 END) as single_touch_count,
					COUNT(DISTINCT c.visitor_id) as unique_converters
				FROM {$wpdb->prefix}tracksure_conversions c
				WHERE DATE(c.converted_at) >= %s 
					AND DATE(c.converted_at) <= %s",
				$date_start,
				$date_end
			),
			ARRAY_A
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( $wpdb->last_error ) {
				error_log( 'MySQL Error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		if ( ! $insights || empty( $insights['total_conversions'] ) ) {
			return array(
				'total_conversions'           => 0,
				'total_revenue'               => 0,
				'avg_sessions_to_convert'     => 0,
				'avg_time_to_convert_days'    => 0,
				'multi_touch_percentage'      => 0,
				'single_touch_percentage'     => 0,
				'multi_touch_count'           => 0,
				'single_touch_count'          => 0,
				'unique_converters'           => 0,
				'conversion_rate_per_visitor' => 0,
			);
		}

		$total_conversions  = (int) $insights['total_conversions'];
		$multi_touch_count  = (int) $insights['multi_touch_count'];
		$single_touch_count = (int) $insights['single_touch_count'];

		// Convert time_to_convert from seconds to days.
		$avg_time_days = ! empty( $insights['avg_time_to_convert_seconds'] )
			? round( $insights['avg_time_to_convert_seconds'] / 86400, 1 )
			: 0;

		return array(
			'total_conversions'           => $total_conversions,
			'total_revenue'               => round( (float) $insights['total_revenue'], 2 ),
			'avg_sessions_to_convert'     => round( (float) $insights['avg_sessions_to_convert'], 1 ),
			'avg_time_to_convert_days'    => $avg_time_days,
			'multi_touch_percentage'      => $total_conversions > 0
				? round( ( $multi_touch_count / $total_conversions ) * 100, 1 )
				: 0,
			'single_touch_percentage'     => $total_conversions > 0
				? round( ( $single_touch_count / $total_conversions ) * 100, 1 )
				: 0,
			'multi_touch_count'           => $multi_touch_count,
			'single_touch_count'          => $single_touch_count,
			'unique_converters'           => (int) $insights['unique_converters'],
			'conversion_rate_per_visitor' => (int) $insights['unique_converters'] > 0
				? round( ( $total_conversions / (int) $insights['unique_converters'] ), 2 )
				: 0,
		);
	}

	/**
	 * Get device journey patterns.
	 *
	 * Analyzes how visitors switch devices during their conversion journey.
	 * Examples: "desktop only", "mobile to desktop", "mobile to tablet to desktop"
	 *
	 * @param string $date_start Start date (Y-m-d).
	 * @param string $date_end End date (Y-m-d).
	 * @return array Device patterns.
	 */
	public function get_device_patterns( $date_start, $date_end ) {
		global $wpdb;
		// Get device journey patterns for converted visitors.
		// Pre-aggregate conversion metrics per visitor FIRST, then join sessions
		// for device pattern only. This avoids cross-join revenue inflation.
		$patterns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					GROUP_CONCAT(DISTINCT s.device_type ORDER BY s.started_at SEPARATOR ' → ') as device_pattern,
					ca.conversions,
					ca.revenue,
					1 as visitors,
					ca.avg_sessions
				FROM (
					SELECT 
						visitor_id,
						COUNT(*) as conversions,
						SUM(conversion_value) as revenue,
						AVG(sessions_to_convert) as avg_sessions,
						MAX(converted_at) as last_converted_at
					FROM {$wpdb->prefix}tracksure_conversions
					WHERE DATE(converted_at) >= %s 
						AND DATE(converted_at) <= %s
					GROUP BY visitor_id
				) ca
				INNER JOIN {$wpdb->prefix}tracksure_sessions s 
					ON s.visitor_id = ca.visitor_id
					AND s.started_at <= ca.last_converted_at
				GROUP BY ca.visitor_id, ca.conversions, ca.revenue, ca.avg_sessions
				ORDER BY ca.conversions DESC
				LIMIT 100",
				$date_start,
				$date_end
			),
			ARRAY_A
		);

		if ( empty( $patterns ) ) {
			return array();
		}

		// Aggregate patterns (combine identical patterns).
		$aggregated = array();
		foreach ( $patterns as $pattern ) {
			$pattern_key = $pattern['device_pattern'];

			if ( ! isset( $aggregated[ $pattern_key ] ) ) {
				$aggregated[ $pattern_key ] = array(
					'pattern'      => $pattern_key,
					'conversions'  => 0,
					'revenue'      => 0,
					'visitors'     => 0,
					'avg_sessions' => 0,
					'count'        => 0,
				);
			}

			$aggregated[ $pattern_key ]['conversions']  += (int) $pattern['conversions'];
			$aggregated[ $pattern_key ]['revenue']      += (float) $pattern['revenue'];
			$aggregated[ $pattern_key ]['visitors']     += (int) $pattern['visitors'];
			$aggregated[ $pattern_key ]['avg_sessions'] += (float) $pattern['avg_sessions'];
			++$aggregated[ $pattern_key ]['count'];
		}

		// Calculate averages and format.
		$formatted = array();
		foreach ( $aggregated as $key => $data ) {
			$formatted[] = array(
				'pattern'      => $data['pattern'],
				'conversions'  => $data['conversions'],
				'revenue'      => round( $data['revenue'], 2 ),
				'visitors'     => $data['visitors'],
				'avg_sessions' => round( $data['avg_sessions'] / $data['count'], 1 ),
			);
		}

		// Sort by conversions DESC.
		usort(
			$formatted,
			function ( $a, $b ) {
				return $b['conversions'] - $a['conversions'];
			}
		);

		return array_slice( $formatted, 0, 10 ); // Top 10 patterns.
	}

	/**
	 * Get conversion breakdown (single-touch vs multi-touch).
	 *
	 * @param string $date_start Start date (Y-m-d).
	 * @param string $date_end End date (Y-m-d).
	 * @return array Breakdown data.
	 */
	public function get_conversion_breakdown( $date_start, $date_end ) {
		global $wpdb;
		$breakdown = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					SUM(CASE WHEN sessions_to_convert = 1 THEN 1 ELSE 0 END) as single_touch_count,
					SUM(CASE WHEN sessions_to_convert = 1 THEN conversion_value ELSE 0 END) as single_touch_revenue,
					SUM(CASE WHEN sessions_to_convert > 1 THEN 1 ELSE 0 END) as multi_touch_count,
					SUM(CASE WHEN sessions_to_convert > 1 THEN conversion_value ELSE 0 END) as multi_touch_revenue,
					AVG(CASE WHEN sessions_to_convert > 1 THEN sessions_to_convert ELSE NULL END) as multi_touch_avg_sessions,
					AVG(CASE WHEN sessions_to_convert > 1 THEN time_to_convert ELSE NULL END) as multi_touch_avg_time,
					COUNT(*) as total_conversions,
					SUM(conversion_value) as total_revenue
				FROM {$wpdb->prefix}tracksure_conversions
				WHERE DATE(converted_at) >= %s 
					AND DATE(converted_at) <= %s",
				$date_start,
				$date_end
			),
			ARRAY_A
		);

		if ( empty( $breakdown ) || empty( $breakdown['total_conversions'] ) ) {
			return array(
				'single_touch' => array(
					'count'      => 0,
					'percentage' => 0,
					'revenue'    => 0,
					'avg_value'  => 0,
				),
				'multi_touch'  => array(
					'count'        => 0,
					'percentage'   => 0,
					'revenue'      => 0,
					'avg_value'    => 0,
					'avg_sessions' => 0,
					'avg_days'     => 0,
				),
				'total'        => array(
					'conversions' => 0,
					'revenue'     => 0,
				),
			);
		}

		$total_conversions = (int) $breakdown['total_conversions'];
		$single_count      = (int) $breakdown['single_touch_count'];
		$multi_count       = (int) $breakdown['multi_touch_count'];
		$single_revenue    = (float) $breakdown['single_touch_revenue'];
		$multi_revenue     = (float) $breakdown['multi_touch_revenue'];

		return array(
			'single_touch' => array(
				'count'      => $single_count,
				'percentage' => $total_conversions > 0
					? round( ( $single_count / $total_conversions ) * 100, 1 )
					: 0,
				'revenue'    => round( $single_revenue, 2 ),
				'avg_value'  => $single_count > 0
					? round( $single_revenue / $single_count, 2 )
					: 0,
			),
			'multi_touch'  => array(
				'count'        => $multi_count,
				'percentage'   => $total_conversions > 0
					? round( ( $multi_count / $total_conversions ) * 100, 1 )
					: 0,
				'revenue'      => round( $multi_revenue, 2 ),
				'avg_value'    => $multi_count > 0
					? round( $multi_revenue / $multi_count, 2 )
					: 0,
				'avg_sessions' => round( (float) $breakdown['multi_touch_avg_sessions'], 1 ),
				'avg_days'     => round( (float) $breakdown['multi_touch_avg_time'] / 86400, 1 ),
			),
			'total'        => array(
				'conversions' => $total_conversions,
				'revenue'     => round( (float) $breakdown['total_revenue'], 2 ),
			),
		);
	}

	/**
	 * Get time to conversion histogram data.
	 *
	 * Returns bucketed data for histogram chart:
	 * 0-1 day, 1-3 days, 3-7 days, 7-14 days, 14-30 days, 30+ days
	 *
	 * @param string $date_start Start date (Y-m-d).
	 * @param string $date_end End date (Y-m-d).
	 * @return array Histogram buckets.
	 */
	public function get_time_to_convert_histogram( $date_start, $date_end ) {
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					time_to_convert,
					conversion_value
				FROM {$wpdb->prefix}tracksure_conversions
				WHERE DATE(converted_at) >= %s 
					AND DATE(converted_at) <= %s
					AND time_to_convert IS NOT NULL",
				$date_start,
				$date_end
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		// Define buckets (in seconds).
		$buckets = array(
			'0-1 day'    => array(
				'min'     => 0,
				'max'     => 86400,
				'count'   => 0,
				'revenue' => 0,
			),
			'1-3 days'   => array(
				'min'     => 86400,
				'max'     => 259200,
				'count'   => 0,
				'revenue' => 0,
			),
			'3-7 days'   => array(
				'min'     => 259200,
				'max'     => 604800,
				'count'   => 0,
				'revenue' => 0,
			),
			'7-14 days'  => array(
				'min'     => 604800,
				'max'     => 1209600,
				'count'   => 0,
				'revenue' => 0,
			),
			'14-30 days' => array(
				'min'     => 1209600,
				'max'     => 2592000,
				'count'   => 0,
				'revenue' => 0,
			),
			'30+ days'   => array(
				'min'     => 2592000,
				'max'     => PHP_INT_MAX,
				'count'   => 0,
				'revenue' => 0,
			),
		);

		// Bucket the data.
		foreach ( $results as $row ) {
			$time_to_convert = (int) $row['time_to_convert'];
			$value           = (float) $row['conversion_value'];

			foreach ( $buckets as $label => &$bucket ) {
				if ( $time_to_convert >= $bucket['min'] && $time_to_convert < $bucket['max'] ) {
					++$bucket['count'];
					$bucket['revenue'] += $value;
					break;
				}
			}
		}

		// Format for chart.
		$formatted = array();
		foreach ( $buckets as $label => $bucket ) {
			$formatted[] = array(
				'label'     => $label,
				'count'     => $bucket['count'],
				'revenue'   => round( $bucket['revenue'], 2 ),
				'avg_value' => $bucket['count'] > 0
					? round( $bucket['revenue'] / $bucket['count'], 2 )
					: 0,
			);
		}

		return $formatted;
	}

	/**
	 * Get attribution model comparison.
	 *
	 * All five attribution models are included.
	 * Extensions can add or remove models via the tracksure_attribution_models filter.
	 *
	 * @param string $date_start Start date (Y-m-d).
	 * @param string $date_end End date (Y-m-d).
	 * @return array Model comparison by source.
	 */
	public function get_attribution_models_comparison( $date_start, $date_end ) {
		global $wpdb;
		// All attribution models included.
		$models = array( 'first_touch', 'last_touch', 'linear', 'time_decay', 'position_based' );

		/**
		 * Filter attribution models for comparison.
		 *
		 * Extensions can add or remove models.
		 *
		 * @param array $models Attribution models.
		 */
		$models = apply_filters( 'tracksure_attribution_models', $models );

		$comparison = array();

		foreach ( $models as $model ) {
			// Get attribution data for this model.
			$sources = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
						COALESCE(ca.utm_source, '(direct)') as source,
						COALESCE(ca.utm_medium, '(none)') as medium,
						ca.channel,
						COUNT(DISTINCT ca.conversion_id) as conversions,
						SUM(ca.credit_value) as revenue,
						AVG(ca.credit_percent) as avg_credit
					FROM {$wpdb->prefix}tracksure_conversion_attribution ca
					INNER JOIN {$wpdb->prefix}tracksure_conversions c ON c.conversion_id = ca.conversion_id
					WHERE ca.attribution_model = %s
						AND DATE(c.converted_at) >= %s 
						AND DATE(c.converted_at) <= %s
					GROUP BY COALESCE(ca.utm_source, '(direct)'), COALESCE(ca.utm_medium, '(none)'), ca.channel
					ORDER BY revenue DESC
					LIMIT 20",
					$model,
					$date_start,
					$date_end
				),
				ARRAY_A
			);

			$comparison[ $model ] = array_map(
				function ( $row ) {
					return array(
						'source'      => sanitize_text_field( $row['source'] ),
						'medium'      => sanitize_text_field( $row['medium'] ),
						'channel'     => sanitize_text_field( $row['channel'] ),
						'conversions' => (int) $row['conversions'],
						'revenue'     => round( (float) $row['revenue'], 2 ),
						'avg_credit'  => round( (float) $row['avg_credit'], 1 ),
					);
				},
				$sources ?: array()
			);
		}

		return $comparison;
	}

	/**
	 * Get top conversion paths.
	 *
	 * Uses the funnel analyzer's existing method but formats for API.
	 *
	 * @param string $date_start Start date (Y-m-d).
	 * @param string $date_end End date (Y-m-d).
	 * @param int    $limit Limit results.
	 * @return array Conversion paths.
	 */
	public function get_conversion_paths( $date_start, $date_end, $limit = 20 ) {
		$funnel_analyzer = TrackSure_Funnel_Analyzer::get_instance();
		$paths           = $funnel_analyzer->get_conversion_paths( $date_start, $date_end, $limit );

		if ( empty( $paths ) ) {
			return array();
		}

		// Format for consistent API response.
		return array_map(
			function ( $path ) {
				return array(
					'path'                    => sanitize_text_field( $path['conversion_path'] ?? '' ),
					'conversions'             => (int) ( $path['conversions'] ?? 0 ),
					'total_value'             => round( (float) ( $path['total_value'] ?? 0 ), 2 ),
					'avg_time_to_convert'     => round( (float) ( $path['avg_time_to_convert'] ?? 0 ) / 86400, 1 ),
					'avg_sessions_to_convert' => round( (float) ( $path['avg_sessions_to_convert'] ?? 0 ), 1 ),
				);
			},
			$paths
		);
	}
}
