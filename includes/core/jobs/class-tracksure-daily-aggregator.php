<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for aggregation job diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Daily Aggregator
 *
 * Rolls up hourly aggregations into daily buckets for historical reporting.
 * Runs via cron daily at 1:00 AM. *
 * Direct database queries required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics.
 * All queries use $wpdb->prepare() for security.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 *
 * @package TrackSure\Core\Jobs
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Daily Aggregator class.
 */
class TrackSure_Daily_Aggregator {


	/**
	 * Maximum seconds a single aggregation run may take.
	 *
	 * @var int
	 */
	const TIME_BOX_SECONDS = 25;

	/**
	 * Instance.
	 *
	 * @var TrackSure_Daily_Aggregator
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Run start time.
	 *
	 * @var float
	 */
	private $start_time = 0;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Daily_Aggregator
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
	 * Aggregate yesterday.
	 * Called by cron job daily.
	 */
	public function aggregate_yesterday() {
		// Concurrency lock — prevent overlapping cron runs.
		if ( get_transient( 'tracksure_daily_agg_lock' ) ) {
			return;
		}
		set_transient( 'tracksure_daily_agg_lock', 1, 10 * MINUTE_IN_SECONDS );

		$this->start_time = microtime( true );

		$date = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$this->aggregate_date( $date );

		delete_transient( 'tracksure_daily_agg_lock' );
	}

	/**
	 * Aggregate a specific date.
	 *
	 * @param string $date Date (Y-m-d format).
	 */
	public function aggregate_date( $date ) {
		global $wpdb;
		// Roll up hourly data into daily.
		$sql = "
		INSERT INTO {$wpdb->prefix}tracksure_agg_daily (
			date,
			utm_source,
			utm_medium,
			utm_campaign,
			channel,
			page_path,
			country,
			device_type,
			sessions,
			pageviews,
			unique_visitors,
			new_visitors,
			returning_visitors,
			total_engagement_time,
			bounced_sessions,
			conversions,
			conversion_value,
			transactions,
			revenue,
			tax,
			shipping,
			items_sold,
			form_views,
			form_starts,
			form_submits,
			form_abandons,
			created_at,
			updated_at
		)
		SELECT
			%s as date,
			utm_source,
			utm_medium,
			utm_campaign,
			channel,
			page_path,
			country,
			device_type,
			SUM(sessions) as sessions,
			SUM(pageviews) as pageviews,
			SUM(unique_visitors) as unique_visitors,
			SUM(new_visitors) as new_visitors,
			SUM(returning_visitors) as returning_visitors,
			SUM(total_engagement_time) as total_engagement_time,
			SUM(bounced_sessions) as bounced_sessions,
			SUM(conversions) as conversions,
			SUM(conversion_value) as conversion_value,
			SUM(transactions) as transactions,
			SUM(revenue) as revenue,
			SUM(tax) as tax,
			SUM(shipping) as shipping,
			SUM(items_sold) as items_sold,
			SUM(form_views) as form_views,
			SUM(form_starts) as form_starts,
			SUM(form_submits) as form_submits,
			SUM(form_abandons) as form_abandons,
			NOW() as created_at,
			NOW() as updated_at
		FROM {$wpdb->prefix}tracksure_agg_hourly
		WHERE DATE(hour_start) = %s
		GROUP BY
			utm_source,
			utm_medium,
			utm_campaign,
			channel,
			page_path,
			country,
			device_type
		ON DUPLICATE KEY UPDATE
			sessions = VALUES(sessions),
			pageviews = VALUES(pageviews),
			unique_visitors = VALUES(unique_visitors),
			new_visitors = VALUES(new_visitors),
			returning_visitors = VALUES(returning_visitors),
			total_engagement_time = VALUES(total_engagement_time),
			bounced_sessions = VALUES(bounced_sessions),
			conversions = VALUES(conversions),
			conversion_value = VALUES(conversion_value),
			transactions = VALUES(transactions),
			revenue = VALUES(revenue),
			tax = VALUES(tax),
			shipping = VALUES(shipping),
			items_sold = VALUES(items_sold),
			form_views = VALUES(form_views),
			form_starts = VALUES(form_starts),
			form_submits = VALUES(form_submits),
			form_abandons = VALUES(form_abandons),
			updated_at = NOW()
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Variable $sql contains prepared SQL from above
		$result = $wpdb->query( $wpdb->prepare( $sql, $date, $date ) );

		if ( $result === false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] Daily Aggregator: Failed to aggregate - ' . $wpdb->last_error );
			}
			return false;
		}

		// Calculate derived metrics.
		$this->calculate_derived_metrics( $date );

		/**
		 * Fires after daily aggregation completes.
		 *
		 * @since 1.0.0
		 * @param string $date Date.
		 * @param int    $rows_aggregated Number of rows aggregated.
		 */
		do_action( 'tracksure_daily_aggregation_complete', $date, $result );

		// Invalidate transient caches for this date.
		$this->invalidate_caches_for_date( $date );

		// Record last successful daily aggregation time (UTC) for stale-aggregation safety net.
		update_option( 'tracksure_last_daily_agg', gmdate( 'Y-m-d H:i:s' ), false );

		return true;
	}

	/**
	 * Invalidate cached metrics after aggregation.
	 *
	 * Uses a scoped SQL LIKE delete targeting only dashboard/API response transients.
	 * This is more targeted than a blanket '%tracksure_%' pattern but still
	 * covers all parameterized cache keys (e.g. tracksure_overview_v2_{hash}).
	 *
	 * @param string $date Date that was aggregated.
	 */
	private function invalidate_caches_for_date( $date ) {
		global $wpdb;

		// These prefixes match the actual cache keys set by REST API controllers.
		$transient_prefixes = array(
			'tracksure_overview_v2_',
			'tracksure_realtime_v',
			'tracksure_sessions_v2_',
			'tracksure_traffic_sources_v2_',
			'tracksure_pages_',
			'tracksure_visitors_v2_',
			'tracksure_goal_perf_',
			'tracksure_goals_perf_',
			'tracksure_goals_overview_',
			'tracksure_agg_metrics_',
		);

		foreach ( $transient_prefixes as $prefix ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_' . $prefix ) . '%',
					$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
				)
			);
		}

		// Also clear specific non-parameterized transients.
		delete_transient( 'tracksure_active_goals' );
		delete_transient( 'tracksure_active_goals_server' );

		/** This action is documented in class-tracksure-hourly-aggregator.php */
		do_action( 'tracksure_invalidate_caches' );
	}

	/**
	 * Calculate derived metrics.
	 *
	 * @param string $date Date (Y-m-d format).
	 */
	private function calculate_derived_metrics( $date ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"
			UPDATE {$wpdb->prefix}tracksure_agg_daily
			SET
				avg_session_duration = CASE WHEN sessions > 0 THEN total_engagement_time / sessions ELSE 0 END,
				avg_pages_per_session = CASE WHEN sessions > 0 THEN pageviews / sessions ELSE 0 END,
				conversion_rate = CASE WHEN sessions > 0 THEN conversions / sessions ELSE 0 END,
				updated_at = NOW()
			WHERE date = %s
		",
				$date
			)
		);
	}

	/**
	 * Re-aggregate a specific date (for backfilling or corrections).
	 *
	 * @param string $date Date (Y-m-d format).
	 */
	public function re_aggregate_date( $date ) {
		$this->aggregate_date( $date );
	}

	/**
	 * Aggregate date range with time-boxing.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date End date (Y-m-d).
	 */
	public function aggregate_date_range( $start_date, $end_date ) {
		if ( ! $this->start_time ) {
			$this->start_time = microtime( true );
		}

		$current_date = $start_date;

		while ( strtotime( $current_date ) <= strtotime( $end_date ) ) {
			// Time-box: stop if we're approaching the limit.
			$max_time = self::TIME_BOX_SECONDS;
			$ini_max  = (int) ini_get( 'max_execution_time' );
			if ( $ini_max > 0 ) {
				$max_time = min( $max_time, max( 5, $ini_max - 5 ) );
			}
			if ( ( microtime( true ) - $this->start_time ) >= $max_time ) {
				break;
			}

			$this->aggregate_date( $current_date );
			$current_date = gmdate( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
		}
	}
}
