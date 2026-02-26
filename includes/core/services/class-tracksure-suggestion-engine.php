<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Debug logging intentionally used for suggestion diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Suggestion Engine
 *
 * CORE BUSINESS LOGIC for Smart Insights (rule-based suggestion system).
 * NO AI/ML - Uses mathematical patterns and statistical thresholds.
 * 
 * Architecture (Clean Separation):
 * - This file: Business logic (12 rule checks, SQL queries, pattern detection)
 * - Controller file: REST API layer (routing, permissions, formatting)
 *
 * Why separate from controller?
 * - Can be called from multiple sources (REST API, CLI tools, WP-Cron jobs)
 * - Easier to test business logic without HTTP layer
 * - Can add more rule checks without touching API code
 * - Extensions can register custom suggestion rules
 *
 * Features:
 * - 12 rule-based checks (traffic anomalies, temporal patterns, conversion issues)
 * - Dynamic thresholds based on site traffic (20+ sessions = actionable data)
 * - Priority system (high/medium/low urgency)
 * - Cache support (5-minute TTL for performance)
 * - Always returns helpful insights (no empty states)
 *
 * Rule Categories:
 * 1. Real-time monitoring (anomaly detection, traffic drops/spikes)
 * 2. Temporal intelligence (weekday vs weekend, hourly patterns)
 * 3. Conversion optimization (low CVR, cart abandonment, checkout drop-off)
 * 4. Traffic quality (source performance, returning visitors)
 * 5. Technical health (data quality, goal configuration)
 * 6. Product opportunities (high views, low conversions)
 *
 * Provides actionable insights based on mathematical patterns. *
 * Direct database queries required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics.
 * All queries use $wpdb->prepare() for security.
 * 
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Suggestion engine class.
 */
class TrackSure_Suggestion_Engine
{



	/**
	 * Single instance.
	 *
	 * @var TrackSure_Suggestion_Engine
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	private $cache_group = 'tracksure_suggestions';

	/**
	 * Cache expiration (5 minutes for near-realtime suggestions).
	 *
	 * @var int
	 */
	private $cache_expiration = 300;

	/**
	 * Get single instance.
	 *
	 * @return TrackSure_Suggestion_Engine
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
		$core     = TrackSure_Core::get_instance();
		$this->db = $core->get_service('db');
	}

	/**
	 * Get all suggestions.
	 *
	 * @param int $limit Maximum number of suggestions.
	 * @return array
	 */
	public function get_suggestions($limit = 5)
	{
		$cache_key = 'all_suggestions_' . $limit;
		// Temporarily disable cache for debugging
		// $cached    = wp_cache_get($cache_key, $this->cache_group);
		// if (false !== $cached) {
		// return $cached;
		// }

		$suggestions = array();

		// Run all rule checks.
		$suggestions[] = $this->check_traffic_anomaly(); // NEW: Real-time anomaly
		$suggestions[] = $this->check_temporal_patterns(); // NEW: Time intelligence insights
		$suggestions[] = $this->check_high_traffic_low_conversions();
		$suggestions[] = $this->check_cart_abandonment();
		$suggestions[] = $this->check_no_goals();
		$suggestions[] = $this->check_mobile_performance();
		$suggestions[] = $this->check_quality_score();
		$suggestions[] = $this->check_checkout_drop_off();
		$suggestions[] = $this->check_product_opportunities();
		$suggestions[] = $this->check_traffic_quality();
		$suggestions[] = $this->check_returning_visitors();
		$suggestions[] = $this->check_general_insights(); // NEW: Always-on general insights

		// Filter out null suggestions.
		$suggestions = array_filter(
			$suggestions,
			function ($s) {
				return ! is_null($s);
			}
		);

		// Sort by priority (high → medium → low).
		usort(
			$suggestions,
			function ($a, $b) {
				$priority_order = array(
					'high'   => 1,
					'medium' => 2,
					'low'    => 3,
				);
				return $priority_order[$a['priority']] <=> $priority_order[$b['priority']];
			}
		);

		// Limit results.
		$suggestions = array_slice($suggestions, 0, $limit);

		// Cache results for performance.
		wp_cache_set($cache_key, $suggestions, $this->cache_group, $this->cache_expiration);

		return $suggestions;
	}

	/**
	 * Rule 1: High traffic but low conversions.
	 *
	 * @return array|null
	 */
	private function check_high_traffic_low_conversions()
	{
		global $wpdb;
		// Get sessions and conversions (last 7 days).
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(DISTINCT session_id) as sessions,
					(SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_conversions 
					 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)) as conversions
				FROM {$wpdb->prefix}tracksure_events
				WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				7,
				7
			)
		);

		$sessions    = absint($stats->sessions ?? 0);
		$conversions = absint($stats->conversions ?? 0);

		if ($sessions < 20) {
			return null; // Not enough data
		}

		$conversion_rate = $sessions > 0 ? ($conversions / $sessions) * 100 : 0;

		// If conversion rate < 2% with significant traffic.
		if ($conversion_rate < 2.0) {
			return array(
				'priority'    => 'high',
				'title'       => 'Low conversion rate needs attention',
				'description' => sprintf(
					'You have %d sessions but only %d conversions (%.2f%%). This suggests visitors aren\'t finding what they need.',
					$sessions,
					$conversions,
					$conversion_rate
				),
				'action'      => 'Add clear CTAs to high-traffic pages',
				'metric'      => array(
					'label' => 'Conversion Rate',
					'value' => round($conversion_rate, 2) . '%',
					'trend' => 'down',
				),
			);
		}

		return null;
	}

	/**
	 * Rule 2: High cart abandonment rate.
	 *
	 * @return array|null
	 */
	private function check_cart_abandonment()
	{
		global $wpdb;
		// Get add_to_cart vs purchase events.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					SUM(CASE WHEN event_name = 'add_to_cart' THEN 1 ELSE 0 END) as add_to_carts,
					SUM(CASE WHEN event_name = 'purchase' THEN 1 ELSE 0 END) as purchases
				FROM {$wpdb->prefix}tracksure_events
				WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				AND event_name IN ('add_to_cart', 'purchase')",
				7
			)
		);

		$add_to_carts = absint($stats->add_to_carts ?? 0);
		$purchases    = absint($stats->purchases ?? 0);

		if ($add_to_carts < 5) {
			return null; // Not enough data
		}

		$abandonment_rate = $add_to_carts > 0 ? (($add_to_carts - $purchases) / $add_to_carts) * 100 : 0;

		// If abandonment > 70%.
		if ($abandonment_rate > 70) {
			return array(
				'priority'    => 'high',
				'title'       => 'High cart abandonment detected',
				'description' => sprintf(
					'%.1f%% of users add to cart but don\'t purchase. Common causes: unexpected shipping costs, complicated checkout, or missing payment options.',
					$abandonment_rate
				),
				'action'      => 'Review checkout UX and add trust signals',
				'metric'      => array(
					'label' => 'Abandonment Rate',
					'value' => round($abandonment_rate, 1) . '%',
					'trend' => 'up',
				),
			);
		}

		return null;
	}

	/**
	 * Rule 3: No goals configured.
	 *
	 * @return array|null
	 */
	private function check_no_goals()
	{
		global $wpdb;
		$goal_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_goals WHERE is_active = %d",
				1
			)
		);

		if ($goal_count === 0) {
			return array(
				'priority'    => 'medium',
				'title'       => 'Set up goals to track conversions',
				'description' => 'You haven\'t configured any goals yet. Goals help you measure what matters (purchases, form submits, phone clicks, etc.).',
				'action'      => 'Go to Goals page and create your first goal',
				'metric'      => array(
					'label' => 'Active Goals',
					'value' => '0',
					'trend' => 'neutral',
				),
			);
		}

		return null;
	}

	/**
	 * Rule 4: Mobile conversion rate significantly lower than desktop.
	 *
	 * @return array|null
	 */
	private function check_mobile_performance()
	{
		global $wpdb;
		// Get conversions by device.
		$device_stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					e.device_type,
					COUNT(DISTINCT e.session_id) as sessions,
					COUNT(DISTINCT c.conversion_id) as conversions
				FROM {$wpdb->prefix}tracksure_events e
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c 
					ON c.session_id = e.session_id
					AND c.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				WHERE e.occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				AND e.device_type IN ('desktop', 'mobile')
				GROUP BY e.device_type",
				7,
				7
			)
		);

		$desktop_rate = 0;
		$mobile_rate  = 0;

		foreach ($device_stats as $stat) {
			$rate = $stat->sessions > 0 ? ($stat->conversions / $stat->sessions) * 100 : 0;
			if ($stat->device_type === 'desktop') {
				$desktop_rate = $rate;
			} elseif ($stat->device_type === 'mobile') {
				$mobile_rate = $rate;
			}
		}

		// If mobile is less than 60% of desktop conversion rate.
		if ($desktop_rate > 0 && $mobile_rate < ($desktop_rate * 0.6)) {
			return array(
				'priority'    => 'medium',
				'title'       => 'Mobile conversion rate is low',
				'description' => sprintf(
					'Mobile converts at %.1f%% vs desktop at %.1f%%. Your mobile experience may need optimization.',
					$mobile_rate,
					$desktop_rate
				),
				'action'      => 'Test mobile checkout flow and page speed',
				'metric'      => array(
					'label' => 'Mobile vs Desktop',
					'value' => sprintf('%.1f%% vs %.1f%%', $mobile_rate, $desktop_rate),
					'trend' => 'down',
				),
			);
		}

		return null;
	}

	/**
	 * Rule 5: Data quality score below threshold.
	 *
	 * @return array|null
	 */
	private function check_quality_score()
	{
		// Get quality score from Quality Controller (if available).
		$quality_controller = new TrackSure_REST_Quality_Controller();
		$request            = new WP_REST_Request('GET', '/ts/v1/quality/signal');
		$request->set_param('destination', 'meta');

		$response = $quality_controller->get_signal_quality($request);
		if (is_wp_error($response)) {
			return null;
		}

		$data          = $response->get_data();
		$quality_score = $data['quality_score'] ?? 100;

		if ($quality_score < 70) {
			return array(
				'priority'    => 'high',
				'title'       => 'Tracking quality needs improvement',
				'description' => sprintf(
					'Your signal quality score is %d/100. This affects how well ad platforms can optimize your campaigns.',
					$quality_score
				),
				'action'      => 'Visit Data Quality page for recommendations',
				'metric'      => array(
					'label' => 'Quality Score',
					'value' => $quality_score . '/100',
					'trend' => 'down',
				),
			);
		}

		return null;
	}

	/**
	 * Rule 6: High checkout drop-off.
	 *
	 * @return array|null
	 */
	private function check_checkout_drop_off()
	{
		global $wpdb;
		// Get checkout funnel.
		$funnel = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					SUM(CASE WHEN event_name = 'begin_checkout' THEN 1 ELSE 0 END) as begin_checkouts,
					SUM(CASE WHEN event_name = 'purchase' THEN 1 ELSE 0 END) as purchases
				FROM {$wpdb->prefix}tracksure_events
				WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				AND event_name IN ('begin_checkout', 'purchase')",
				7
			)
		);

		$begin_checkouts = absint($funnel->begin_checkouts ?? 0);
		$purchases       = absint($funnel->purchases ?? 0);

		if ($begin_checkouts < 5) {
			return null;
		}

		$drop_off_rate = $begin_checkouts > 0 ? (($begin_checkouts - $purchases) / $begin_checkouts) * 100 : 0;

		// If > 60% drop off at checkout.
		if ($drop_off_rate > 60) {
			return array(
				'priority'    => 'high',
				'title'       => 'Many users abandon during checkout',
				'description' => sprintf(
					'%.1f%% of users who start checkout don\'t complete. Check for hidden costs, confusing forms, or missing payment methods.',
					$drop_off_rate
				),
				'action'      => 'Simplify checkout and add guest checkout option',
				'metric'      => array(
					'label' => 'Checkout Drop-off',
					'value' => round($drop_off_rate, 1) . '%',
					'trend' => 'up',
				),
			);
		}

		return null;
	}

	/**
	 * Rule 7: Product opportunities (high views, low carts).
	 *
	 * @return array|null
	 */
	private function check_product_opportunities()
	{
		global $wpdb;
		// Find products with high views but low add-to-cart rate.
		$opportunities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					e.event_params->>'$.items[0].item_id' as product_id,
					e.event_params->>'$.items[0].item_name' as product_name,
					SUM(CASE WHEN e.event_name = 'view_item' THEN 1 ELSE 0 END) as views,
					SUM(CASE WHEN e.event_name = 'add_to_cart' THEN 1 ELSE 0 END) as add_to_carts
				FROM {$wpdb->prefix}tracksure_events e
				WHERE e.occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				AND e.event_name IN ('view_item', 'add_to_cart')
				AND e.event_params->>'$.items[0].item_id' IS NOT NULL
				GROUP BY e.event_params->>'$.items[0].item_id'
				HAVING views > %d AND (add_to_carts * 1.0 / views) < 0.10
				ORDER BY views DESC
				LIMIT %d",
				7,
				20,
				1
			)
		);

		if (empty($opportunities)) {
			return null;
		}

		$product = $opportunities[0];
		$views   = absint($product->views);
		$carts   = absint($product->add_to_carts);
		$rate    = $views > 0 ? ($carts / $views) * 100 : 0;

		return array(
			'priority'    => 'medium',
			'title'       => sprintf('Product "%s" has low add-to-cart rate', substr($product->product_name, 0, 40)),
			'description' => sprintf(
				'This product has %d views but only %d add-to-carts (%.1f%%). Consider improving product images, description, or pricing.',
				$views,
				$carts,
				$rate
			),
			'action'      => 'Review product page and add customer reviews',
			'metric'      => array(
				'label' => 'Add-to-Cart Rate',
				'value' => round($rate, 1) . '%',
				'trend' => 'down',
			),
		);
	}

	/**
	 * Rule 8: Low-quality traffic sources.
	 *
	 * @return array|null
	 */
	private function check_traffic_quality()
	{
		global $wpdb;
		// Get traffic sources with high bounce rate.
		$sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					s.utm_source,
					COUNT(DISTINCT e.session_id) as sessions,
					COUNT(DISTINCT c.conversion_id) as conversions,
					AVG(TIMESTAMPDIFF(SECOND, e.occurred_at, 
						(SELECT MAX(e2.occurred_at) FROM {$wpdb->prefix}tracksure_events e2 
						 WHERE e2.session_id = e.session_id))) as avg_session_duration
				FROM {$wpdb->prefix}tracksure_events e
				INNER JOIN {$wpdb->prefix}tracksure_sessions s ON s.session_id = e.session_id
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c 
					ON c.session_id = e.session_id
					AND c.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				WHERE e.occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				AND s.utm_source IS NOT NULL
				AND s.utm_source != ''
				GROUP BY s.utm_source
				HAVING sessions > %d AND (conversions * 1.0 / sessions) < 0.01
				ORDER BY sessions DESC
				LIMIT %d",
				7,
				7,
				10,
				1
			)
		);

		if (empty($sources)) {
			return null;
		}

		$source      = $sources[0];
		$source_name = sanitize_text_field($source->utm_source);
		$sessions    = absint($source->sessions);
		$conversions = absint($source->conversions);
		$rate        = $sessions > 0 ? ($conversions / $sessions) * 100 : 0;

		return array(
			'priority'    => 'medium',
			'title'       => sprintf('Low conversion rate from "%s"', $source_name),
			'description' => sprintf(
				'Traffic from %s has %d sessions but only %d conversions (%.2f%%). Either improve targeting or reduce spend here.',
				$source_name,
				$sessions,
				$conversions,
				$rate
			),
			'action'      => sprintf('Review "%s" campaign targeting and ad copy', $source_name),
			'metric'      => array(
				'label' => 'Conversion Rate',
				'value' => round($rate, 2) . '%',
				'trend' => 'down',
			),
		);
	}

	/**
	 * Rule 9: Low returning visitor rate.
	 *
	 * @return array|null
	 */
	private function check_returning_visitors()
	{
		global $wpdb;
		// Get new vs returning.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					SUM(CASE WHEN v.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 ELSE 0 END) as new_visitors,
					SUM(CASE WHEN v.created_at < DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 ELSE 0 END) as returning_visitors
				FROM {$wpdb->prefix}tracksure_visitors v
				INNER JOIN {$wpdb->prefix}tracksure_events e ON e.visitor_id = v.visitor_id
				WHERE e.occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				7,
				7,
				7
			)
		);

		$new       = absint($stats->new_visitors ?? 0);
		$returning = absint($stats->returning_visitors ?? 0);
		$total     = $new + $returning;

		if ($total < 20) {
			return null;
		}

		$returning_rate = $total > 0 ? ($returning / $total) * 100 : 0;

		// If < 20% returning visitors.
		if ($returning_rate < 20) {
			return array(
				'priority'    => 'low',
				'title'       => 'Low returning visitor rate',
				'description' => sprintf(
					'Only %.1f%% of visitors return. Consider starting a newsletter, remarketing campaigns, or content series.',
					$returning_rate
				),
				'action'      => 'Add newsletter signup and create remarketing audiences',
				'metric'      => array(
					'label' => 'Returning Visitors',
					'value' => round($returning_rate, 1) . '%',
					'trend' => 'neutral',
				),
			);
		}

		return null;
	}

	/**
	 * Rule 10: Real-time traffic anomaly detection (NEW).
	 * Compares last hour vs 7-day average to detect sudden drops/spikes.
	 *
	 * @return array|null
	 */
	private function check_traffic_anomaly()
	{
		global $wpdb;
		// Get sessions in last hour.
		$current_hour_sessions = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT session_id)
					FROM {$wpdb->prefix}tracksure_events
					WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)",
					1
				)
			)
		);

		// Get average hourly sessions over last 7 days.
		$avg_hourly_sessions = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT session_id) / 168
					FROM {$wpdb->prefix}tracksure_events
					WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
					7
				)
			)
		);

		if ($avg_hourly_sessions < 3) {
			return null; // Not enough historical data
		}

		// Check for 50%+ drop.
		if ($current_hour_sessions < ($avg_hourly_sessions * 0.5)) {
			$drop_percent = round((1 - ($current_hour_sessions / $avg_hourly_sessions)) * 100, 1);

			return array(
				'priority'    => 'high',
				'title'       => 'Traffic dropped ' . $drop_percent . '% in the last hour',
				'description' => sprintf(
					'Only %d sessions in the last hour vs %d average. Check if tracking code is working or if there\'s a technical issue.',
					$current_hour_sessions,
					round($avg_hourly_sessions)
				),
				'action'      => 'Check website status and tracking code installation',
				'metric'      => array(
					'label' => 'Traffic Change',
					'value' => '-' . $drop_percent . '%',
					'trend' => 'down',
				),
			);
		}

		// Check for 200%+ spike (potential bot traffic).
		if ($current_hour_sessions > ($avg_hourly_sessions * 2)) {
			$spike_percent = round((($current_hour_sessions / $avg_hourly_sessions) - 1) * 100, 1);

			return array(
				'priority'    => 'medium',
				'title'       => 'Traffic spiked ' . $spike_percent . '% in the last hour',
				'description' => sprintf(
					'%d sessions in the last hour vs %d average. This could be viral traffic or bot activity.',
					$current_hour_sessions,
					round($avg_hourly_sessions)
				),
				'action'      => 'Monitor for bot traffic patterns in Sessions page',
				'metric'      => array(
					'label' => 'Traffic Change',
					'value' => '+' . $spike_percent . '%',
					'trend' => 'up',
				),
			);
		}

		return null;
	}

	/**
	 * Rule 11: Temporal pattern insights (NEW - Phase 3.2).
	 * Uses time intelligence data to suggest optimal posting/ad times.
	 *
	 * @return array|null
	 */
	private function check_temporal_patterns()
	{
		global $wpdb;
		// Get weekend vs weekday performance (last 30 days for stability).
		$temporal_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
						CASE WHEN DAYOFWEEK(s.started_at) IN (1, 7) THEN 'weekend' ELSE 'weekday' END as period,
						COUNT(DISTINCT s.visitor_id) as visitors,
						COUNT(DISTINCT c.conversion_id) as conversions,
						(COUNT(DISTINCT c.conversion_id) / NULLIF(COUNT(DISTINCT s.session_id), 0) * 100) as conversion_rate
					FROM {$wpdb->prefix}tracksure_sessions s
					LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON s.session_id = c.session_id
					WHERE s.started_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
						AND s.started_at <= NOW()
					GROUP BY period",
				30
			)
		);

		if (empty($temporal_data) || count($temporal_data) < 2) {
			return null;
		}

		$weekend_data = null;
		$weekday_data = null;

		foreach ($temporal_data as $data) {
			if ($data->period === 'weekend') {
				$weekend_data = $data;
			} else {
				$weekday_data = $data;
			}
		}

		if (! $weekend_data || ! $weekday_data) {
			return null;
		}

		$weekend_rate = (float) $weekend_data->conversion_rate;
		$weekday_rate = (float) $weekday_data->conversion_rate;

		// Check if there's a significant difference (>20%).
		if ($weekend_rate > 0 && $weekday_rate > 0) {
			$difference      = abs($weekend_rate - $weekday_rate);
			$percentage_diff = ($difference / min($weekend_rate, $weekday_rate)) * 100;

			if ($percentage_diff > 20) {
				$better_period = $weekend_rate > $weekday_rate ? 'weekend' : 'weekday';
				$better_rate   = max($weekend_rate, $weekday_rate);
				$worse_rate    = min($weekend_rate, $weekday_rate);

				return array(
					'priority'    => 'medium',
					'title'       => sprintf('%s converts %d%% better', ucfirst($better_period), round($percentage_diff)),
					'description' => sprintf(
						'Your %s conversion rate is %.1f%% vs %.1f%% on %ss. Consider scheduling campaigns and content for optimal days.',
						$better_period,
						$better_rate,
						$worse_rate,
						$better_period === 'weekend' ? 'weekday' : 'weekend'
					),
					'action'      => sprintf('Increase ad spend and content posting on %ss', $better_period),
					'metric'      => array(
						'label' => ucfirst($better_period) . ' Performance',
						'value' => '+' . round($percentage_diff) . '%',
						'trend' => 'up',
					),
				);
			}
		}

		return null;
	}

	/**
	 * Rule 12: General actionable insights (NEW).
	 * Provides helpful suggestions based on available data, always returns something helpful.
	 *
	 * @return array|null
	 */
	private function check_general_insights()
	{
		global $wpdb;
		// Get basic metrics from last 7 days.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(DISTINCT e.session_id) as sessions,
					COUNT(DISTINCT e.visitor_id) as visitors,
					COUNT(DISTINCT c.conversion_id) as conversions,
					SUM(CASE WHEN e.event_name = 'page_view' THEN 1 ELSE 0 END) as pageviews
				FROM {$wpdb->prefix}tracksure_events e
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON e.session_id = c.session_id 
					AND c.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				WHERE e.occurred_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				7,
				7
			)
		);

		if (! $stats || absint($stats->sessions ?? 0) < 5) {
			// Very little data - suggest basic setup.
			return array(
				'priority'    => 'medium',
				'title'       => 'Get more value from your analytics',
				'description' => 'You\'re tracking basic metrics. Set up goals to measure conversions and understand what drives business results.',
				'action'      => 'Create your first goal to track form submissions, purchases, or key actions',
				'metric'      => array(
					'label' => 'Current Sessions',
					'value' => absint($stats->sessions ?? 0),
					'trend' => 'neutral',
				),
			);
		}

		$sessions          = absint($stats->sessions);
		$visitors          = absint($stats->visitors);
		$conversions       = absint($stats->conversions);
		$pageviews         = absint($stats->pageviews);
		$pages_per_session = $sessions > 0 ? round($pageviews / $sessions, 1) : 0;

		// Check for good engagement but missing conversion tracking.
		if ($conversions === 0 && $sessions > 10) {
			return array(
				'priority'    => 'high',
				'title'       => 'You have traffic but aren\'t tracking conversions',
				'description' => sprintf(
					'You have %d sessions but no conversions tracked. Set up goals to measure form submits, purchases, or other key actions.',
					$sessions
				),
				'action'      => 'Go to Goals page and create conversion tracking',
				'metric'      => array(
					'label' => 'Conversions Tracked',
					'value' => '0',
					'trend' => 'down',
				),
			);
		}

		// Check for low pages per session (engagement).
		if ($pages_per_session > 0 && $pages_per_session < 2.0 && $sessions > 10) {
			return array(
				'priority'    => 'medium',
				'title'       => 'Visitors aren\'t exploring your site',
				'description' => sprintf(
					'Average %.1f pages per session. Add internal links, related content, or clear navigation to increase engagement.',
					$pages_per_session
				),
				'action'      => 'Add "Related Posts" or "You May Also Like" sections to key pages',
				'metric'      => array(
					'label' => 'Pages per Session',
					'value' => number_format($pages_per_session, 1),
					'trend' => 'down',
				),
			);
		}

		// Check for good traffic growth opportunity.
		if ($sessions > 15 && $sessions < 100) {
			$conversion_rate = $sessions > 0 ? round(($conversions / $sessions) * 100, 1) : 0;

			return array(
				'priority'    => 'low',
				'title'       => 'Growing traffic - optimize for conversions',
				'description' => sprintf(
					'You have %d sessions with %.1f%% conversion rate. Focus on converting existing traffic before scaling up.',
					$sessions,
					$conversion_rate
				),
				'action'      => 'A/B test headlines, CTAs, and landing pages to improve conversion rate',
				'metric'      => array(
					'label' => 'Current Sessions',
					'value' => number_format($sessions),
					'trend' => 'up',
				),
			);
		}

		// Default: Encourage data-driven decisions.
		if ($sessions > 5) {
			return array(
				'priority'    => 'low',
				'title'       => 'Use data to guide improvements',
				'description' => sprintf(
					'With %d visitors and %d conversions, you have enough data to spot patterns. Review your top pages and traffic sources regularly.',
					$visitors,
					$conversions
				),
				'action'      => 'Set a weekly reminder to review analytics and test one improvement',
				'metric'      => array(
					'label' => 'Data Points',
					'value' => number_format($pageviews) . ' pageviews',
					'trend' => 'neutral',
				),
			);
		}

		return null;
	}
}
