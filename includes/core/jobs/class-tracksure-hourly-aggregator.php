<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Debug logging and direct DB queries required for aggregation

/**
 *
 * TrackSure Hourly Aggregator
 *
 * Aggregates event data into hourly buckets for fast dashboard queries.
 * Runs via cron every hour at :05 past the hour. *
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
if (! defined('ABSPATH')) {
	exit;
}

/**
 * TrackSure Hourly Aggregator class.
 */
class TrackSure_Hourly_Aggregator
{



	/**
	 * Instance.
	 *
	 * @var TrackSure_Hourly_Aggregator
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
	 * @return TrackSure_Hourly_Aggregator
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
	 * Aggregate last hour.
	 * Called by cron job every hour.
	 */
	public function aggregate_last_hour()
	{
		$hour_start = gmdate('Y-m-d H:00:00', strtotime('-1 hour'));
		$hour_end   = gmdate('Y-m-d H:59:59', strtotime('-1 hour'));

		$this->aggregate_hour($hour_start, $hour_end);
	}

	/**
	 * Aggregate a specific hour.
	 *
	 * @param string $hour_start Hour start datetime.
	 * @param string $hour_end Hour end datetime.
	 */
	public function aggregate_hour($hour_start, $hour_end)
	{
		global $wpdb;
		// Multi-dimensional aggregation query.
		$sql = "
		INSERT INTO {$wpdb->prefix}tracksure_agg_hourly (
			hour_start,
			utm_source,
			utm_medium,
			utm_campaign,
			channel,
			page_url_hash,
			page_path,
			page_title,
			country,
			device_type,
			browser,
			os,
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
			form_views,
			form_starts,
			form_submits,
			created_at,
			updated_at
		)
		SELECT
			%s as hour_start,
			s.utm_source,
			s.utm_medium,
			s.utm_campaign,
			CASE
				WHEN s.utm_medium IN ('cpc', 'ppc', 'paid') THEN 'paid'
				WHEN s.utm_source IN ('facebook', 'instagram', 'twitter', 'linkedin') THEN 'social'
				WHEN s.utm_medium = 'email' THEN 'email'
				WHEN s.utm_source IN ('google', 'bing') AND s.utm_medium = 'organic' THEN 'organic'
				WHEN s.referrer IS NOT NULL AND s.utm_source != '(direct)' THEN 'referral'
				ELSE 'direct'
			END as channel,
			SHA2(e.page_url, 256) as page_url_hash,
			SUBSTRING_INDEX(SUBSTRING_INDEX(e.page_url, '/', 4), '?', 1) as page_path,
			e.page_title,
			s.country,
			s.device_type,
			s.browser,
			s.os,
			COUNT(DISTINCT s.session_id) as sessions,
			COUNT(CASE WHEN e.event_name = 'page_view' THEN 1 END) as pageviews,
			COUNT(DISTINCT s.visitor_id) as unique_visitors,
			COUNT(DISTINCT CASE WHEN s.is_returning = 0 THEN s.visitor_id END) as new_visitors,
			COUNT(DISTINCT CASE WHEN s.is_returning = 1 THEN s.visitor_id END) as returning_visitors,
			COALESCE(SUM(CASE WHEN e .
				event_name = 'time_on_page' THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.time_seconds')) AS UNSIGNED) END), 0) as total_engagement_time,
			COUNT(DISTINCT CASE WHEN s.event_count <= 1 THEN s.session_id END) as bounced_sessions,
			COUNT(CASE WHEN e.is_conversion = 1 THEN 1 END) as conversions,
			COALESCE(SUM(CASE WHEN e.is_conversion = 1 THEN e.conversion_value END), 0) as conversion_value,
			COUNT(DISTINCT CASE WHEN e.event_name = 'purchase' THEN e.event_id END) as transactions,
			COALESCE(SUM(CASE WHEN e .
				event_name = 'purchase' THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.value')) AS DECIMAL(10,2)) END), 0) as revenue,
			COUNT(CASE WHEN e.event_name = 'form_view' THEN 1 END) as form_views,
			COUNT(CASE WHEN e.event_name = 'form_start' THEN 1 END) as form_starts,
			COUNT(CASE WHEN e.event_name = 'form_submit' THEN 1 END) as form_submits,
			NOW() as created_at,
			NOW() as updated_at
		FROM {$wpdb->prefix}tracksure_events e
		LEFT JOIN {$wpdb->prefix}tracksure_sessions s ON e.session_id = s.session_id
		WHERE e.occurred_at >= %s AND e.occurred_at <= %s
		GROUP BY
			s.utm_source,
			s.utm_medium,
			s.utm_campaign,
			channel,
			page_url_hash,
			page_path,
			s.country,
			s.device_type,
			s.browser,
			s.os
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
			form_views = VALUES(form_views),
			form_starts = VALUES(form_starts),
			form_submits = VALUES(form_submits),
			updated_at = NOW()
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Variable $sql contains prepared SQL from above
		$result = $wpdb->query($wpdb->prepare($sql, $hour_start, $hour_start, $hour_end));

		if ($result === false) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log('[TrackSure] Hourly Aggregator: Failed to aggregate - ' . $wpdb->last_error);
			}
			return false;
		}

		// Aggregate product performance.
		$this->aggregate_products($hour_start, $hour_end);

		// Calculate derived metrics.
		$this->calculate_derived_metrics($hour_start);

		/**
		 * Fires after hourly aggregation completes.
		 *
		 * @since 1.0.0
		 * @param string $hour_start Hour start datetime.
		 * @param int    $rows_aggregated Number of rows aggregated.
		 */
		do_action('tracksure_hourly_aggregation_complete', $hour_start, $result);

		// Invalidate transient caches.
		$this->invalidate_caches();

		// Record last successful hourly aggregation time (UTC) for stale-aggregation safety net.
		update_option('tracksure_last_hourly_agg', gmdate('Y-m-d H:i:s'), false);

		return true;
	}

	/**
	 * Invalidate cached metrics after aggregation.
	 */
	private function invalidate_caches()
	{
		global $wpdb;

		// Delete all transients matching tracksure patterns.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like('_transient_tracksure_') . '%',
				$wpdb->esc_like('_transient_timeout_tracksure_') . '%'
			)
		);
	}

	/**
	 * Calculate derived metrics (avg session duration, conversion rate, etc).
	 *
	 * @param string $hour_start Hour start datetime.
	 */
	private function calculate_derived_metrics($hour_start)
	{
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"
			UPDATE {$wpdb->prefix}tracksure_agg_hourly
			SET
				avg_session_duration = CASE WHEN sessions > 0 THEN total_engagement_time / sessions ELSE 0 END,
				avg_pages_per_session = CASE WHEN sessions > 0 THEN pageviews / sessions ELSE 0 END,
				conversion_rate = CASE WHEN sessions > 0 THEN conversions / sessions ELSE 0 END,
				updated_at = NOW()
			WHERE hour_start = %s
		",
				$hour_start
			)
		);
	}

	/**
	 * Aggregate product performance (e-commerce).
	 *
	 * NOTE: Product data is stored differently per event type:
	 * - view_item, add_to_cart: event_params has flat fields (item_id, item_name, price, quantity)
	 * - purchase: event_params.items is an array of products (WooCommerce can have multiple items)
	 *
	 * This query aggregates view_item and add_to_cart from event_params directly.
	 * For purchases, we need a separate process since items is an array that needs to be exploded.
	 *
	 * @param string $hour_start Hour start datetime.
	 * @param string $hour_end Hour end datetime.
	 */
	public function aggregate_products($hour_start, $hour_end)
	{
		global $wpdb;
		// Convert hour to date for daily product aggregation.
		$date = gmdate('Y-m-d', strtotime($hour_start));

		// STEP 1: Aggregate views and add_to_carts (flat event_params structure).
		$sql_views_carts = "
		INSERT INTO {$wpdb->prefix}tracksure_agg_product_daily (
			date,
			product_id,
			product_name,
			product_category,
			utm_source,
			utm_medium,
			views,
			add_to_carts,
			checkouts,
			purchases,
			items_sold,
			revenue,
			created_at,
			updated_at
		)
		SELECT
			%s as date,
			COALESCE(
				JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.item_id')),
				JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.product_id'))
			) as product_id,
			COALESCE(
				JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.item_name')),
				JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.product_name'))
			) as product_name,
			JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.item_category')) as product_category,
			s.utm_source,
			s.utm_medium,
			COUNT(CASE WHEN e.event_name = 'view_item' THEN 1 END) as views,
			COUNT(CASE WHEN e.event_name = 'add_to_cart' THEN 1 END) as add_to_carts,
			COUNT(CASE WHEN e.event_name = 'begin_checkout' THEN 1 END) as checkouts,
			0 as purchases,
			0 as items_sold,
			0 as revenue,
			NOW() as created_at,
			NOW() as updated_at
		FROM {$wpdb->prefix}tracksure_events e
		LEFT JOIN {$wpdb->prefix}tracksure_sessions s ON e.session_id = s.session_id
		WHERE e.occurred_at >= %s AND e.occurred_at <= %s
		AND e.event_name IN ('view_item', 'add_to_cart', 'begin_checkout')
		AND (
			JSON_EXTRACT(e.event_params, '$.item_id') IS NOT NULL OR
			JSON_EXTRACT(e.event_params, '$.product_id') IS NOT NULL
		)
		GROUP BY
			product_id,
			product_name,
			product_category,
			s.utm_source,
			s.utm_medium
		ON DUPLICATE KEY UPDATE
			views = views + VALUES(views),
			add_to_carts = add_to_carts + VALUES(add_to_carts),
			checkouts = checkouts + VALUES(checkouts),
			conversion_rate = CASE WHEN (views + VALUES(views)) > 0 THEN (purchases + VALUES(purchases)) / (views + VALUES(views)) * 100 ELSE 0 END,
			updated_at = NOW()
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Variable $sql_views_carts contains prepared SQL from above
		$result1 = $wpdb->query($wpdb->prepare($sql_views_carts, $date, $hour_start, $hour_end));

		if ($result1 === false) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[TrackSure] Product Aggregator (views/carts): Failed - ' . $wpdb->last_error);
			}
		}

		// STEP 2: Aggregate purchases using ecommerce_data.items array.
		// Since MySQL can't easily explode JSON arrays, we'll fetch purchase events and process them in PHP.
		$purchase_events = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT
				e.event_id,
				e.event_params,
				e.ecommerce_data,
				s.utm_source,
				s.utm_medium
			FROM {$wpdb->prefix}tracksure_events e
			LEFT JOIN {$wpdb->prefix}tracksure_sessions s ON e.session_id = s.session_id
			WHERE e.occurred_at >= %s
			AND e.occurred_at <= %s
			AND e.event_name = 'purchase'
			AND e.ecommerce_data IS NOT NULL
		",
				$hour_start,
				$hour_end
			),
			ARRAY_A
		);

		$purchase_count = 0;
		if ($purchase_events) {
			foreach ($purchase_events as $event) {
				$ecommerce_data = json_decode($event['ecommerce_data'], true);
				if (! $ecommerce_data || empty($ecommerce_data['items'])) {
					continue;
				}

				$utm_source = $event['utm_source'] ?: '';
				$utm_medium = $event['utm_medium'] ?: '';

				// Process each item in the purchase.
				foreach ($ecommerce_data['items'] as $item) {
					$product_id       = $item['item_id'] ?? '';
					$product_name     = $item['item_name'] ?? '';
					$product_category = $item['item_category'] ?? '';
					$price            = floatval($item['price'] ?? 0);
					$quantity         = intval($item['quantity'] ?? 1);
					$revenue          = $price * $quantity;

					if (! $product_id) {
						continue;
					}

					// Insert or update product aggregation for this purchase item.
					$wpdb->query(
						$wpdb->prepare(
							"
						INSERT INTO {$wpdb->prefix}tracksure_agg_product_daily (
							date,
							product_id,
							product_name,
							product_category,
							utm_source,
							utm_medium,
							views,
							add_to_carts,
							checkouts,
							purchases,
							items_sold,
							revenue,
							created_at,
							updated_at
						) VALUES (
							%s, %s, %s, %s, %s, %s,
							0, 0, 0, 1, %d, %f,
							NOW(), NOW()
						)
						ON DUPLICATE KEY UPDATE
							purchases = purchases + 1,
							items_sold = items_sold + %d,
							revenue = revenue + %f,
							conversion_rate = CASE WHEN views > 0 THEN (purchases + 1) / views * 100 ELSE 0 END,
							updated_at = NOW()
					",
							$date,
							$product_id,
							$product_name,
							$product_category,
							$utm_source,
							$utm_medium,
							$quantity,
							$revenue,
							$quantity,
							$revenue
						)
					);

					++$purchase_count;
				}
			}
		}

		return true;
	}

	/**
	 * Re-aggregate a specific hour (for backfilling or corrections).
	 *
	 * @param string $hour_start Hour start datetime.
	 */
	public function re_aggregate_hour($hour_start)
	{
		$hour_end = gmdate('Y-m-d H:59:59', strtotime($hour_start));
		$this->aggregate_hour($hour_start, $hour_end);
	}
}
