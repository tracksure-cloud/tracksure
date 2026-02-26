<?php

/**
 *
 * TrackSure REST Quality Controller
 *
 * Handles data quality and signal health API endpoints.
 * THE MOAT - Shows tracking health transparency.
 *
 * Direct database queries are required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics queries.
 * All queries use $wpdb->prepare() for security.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Quality controller class.
 */
class TrackSure_REST_Quality_Controller extends TrackSure_REST_Controller
{



	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Cache group name.
	 *
	 * @var string
	 */
	private $cache_group = 'tracksure_quality';

	/**
	 * Cache expiration (10 minutes).
	 *
	 * @var int
	 */
	private $cache_expiration = 600;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$core     = TrackSure_Core::get_instance();
		$this->db = $core->get_service('db');
	}

	/**
	 * Register routes.
	 */
	public function register_routes()
	{
		// Get dynamic enum from Destinations Manager (includes all Free/Pro/3rd-party).
		$core                 = TrackSure_Core::get_instance();
		$destinations_manager = $core->get_service('destinations_manager');
		$destination_ids      = $destinations_manager ? $destinations_manager->get_enabled_destination_ids() : array();

		// Always include 'all' option.
		$destination_enum = array_merge($destination_ids, array('all'));

		// GET /quality/signal - Signal quality scores.
		register_rest_route(
			$this->namespace,
			'/quality/signal',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_signal_quality'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => array(
					'destination' => array(
						'type'     => 'string',
						'required' => false,
						'enum'     => $destination_enum, // Dynamic - supports Free/Pro/3rd-party
						'default'  => 'all',
					),
				),
			)
		);

		// GET /quality/deduplication - Deduplication stats.
		register_rest_route(
			$this->namespace,
			'/quality/deduplication',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_deduplication_stats'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// GET /quality/schema - Event schema validation.
		register_rest_route(
			$this->namespace,
			'/quality/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_schema_validation'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// GET /quality/reconciliation - Meta vs GA4 vs TrackSure comparison.
		register_rest_route(
			$this->namespace,
			'/quality/reconciliation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_reconciliation'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);
	}

	/**
	 * Get signal quality score per destination.
	 *
	 * Calculates 0-100 score based on:
	 * - Deduplication rate (40%)
	 * - Server-side coverage (40%)
	 * - Missing params rate (10%)
	 * - Delivery success rate (10%)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_signal_quality($request)
	{
		$destination = sanitize_text_field($request->get_param('destination'));

		$cache_key = 'signal_' . $destination;
		$cached    = wp_cache_get($cache_key, $this->cache_group);
		if (false !== $cached) {
			return $this->prepare_success($cached);
		}

		global $wpdb;
		// Get destinations dynamically from Destinations Manager.
		$destinations = array();
		if ('all' === $destination) {
			$core                 = TrackSure_Core::get_instance();
			$destinations_manager = $core->get_service('destinations_manager');
			if ($destinations_manager) {
				$destinations = $destinations_manager->get_enabled_destination_ids();
			}
			// Fallback if no destinations registered.
			if (empty($destinations)) {
				$destinations = array('meta', 'ga4');
			}
		} else {
			$destinations = array($destination);
		}

		$results = array();

		foreach ($destinations as $dest) {
			// Get total events (last 7 days).
			$total_events = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_events
					WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
					7
				)
			);

			// Get unique events (deduplication rate).
			$unique_events = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT event_id) FROM {$wpdb->prefix}tracksure_events
					WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
					7
				)
			);
			$dedup_rate    = $total_events > 0 ? ($unique_events / $total_events) * 100 : 100;

			// Get server-side coverage (unique events from last 7 days that were sent to outbox).
			$server_events   = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT o.event_id) 
                     FROM {$wpdb->prefix}tracksure_outbox o
                     INNER JOIN {$wpdb->prefix}tracksure_events e ON o.event_id = e.event_id
                     WHERE JSON_CONTAINS(o.destinations, %s)
                       AND e.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)",
					wp_json_encode($dest)
				)
			);
			$server_coverage = $total_events > 0 ? ($server_events / $total_events) * 100 : 0;

			// Get delivery success rate (count distinct events successfully delivered).
			$delivered_events = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT o.event_id) 
                     FROM {$wpdb->prefix}tracksure_outbox o
                     INNER JOIN {$wpdb->prefix}tracksure_events e ON o.event_id = e.event_id
                     WHERE JSON_CONTAINS(o.destinations, %s)
                       AND JSON_EXTRACT(o.destinations_status, CONCAT('$.', %s, '.status')) = 'success'
                       AND e.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)",
					wp_json_encode($dest),
					$dest
				)
			);
			$delivery_rate    = $server_events > 0 ? ($delivered_events / $server_events) * 100 : 0;

			// Get events with missing required params (for this destination).
			$required_params      = $this->get_required_params($dest);
			$missing_params_count = $this->count_missing_params($dest, $required_params);
			$missing_params_rate  = $total_events > 0 ? ($missing_params_count / $total_events) * 100 : 0;

			// Calculate quality score (0-100).
			$quality_score = min(
				100,
				round(
					($dedup_rate * 0.4) +
						($server_coverage * 0.4) +
						((100 - $missing_params_rate) * 0.1) +
						($delivery_rate * 0.1)
				)
			);

			// Get recommendations.
			$recommendations = $this->get_recommendations(
				$quality_score,
				$server_coverage,
				$dedup_rate,
				$delivery_rate,
				$missing_params_rate
			);

			// NEW SCHEMA: Get last failed event from destinations_status JSON.
			$last_failed = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT destinations_status, UNIX_TIMESTAMP(created_at) as created_at 
                     FROM {$wpdb->prefix}tracksure_outbox
                     WHERE JSON_CONTAINS(destinations, %s)
                       AND status = 'failed'
                     ORDER BY created_at DESC
                     LIMIT 1",
					wp_json_encode($dest)
				)
			);

			// Match quality label.
			$match_quality = 'excellent';
			if ($quality_score < 70) {
				$match_quality = 'needs_improvement';
			} elseif ($quality_score < 85) {
				$match_quality = 'good';
			}

			// Extract error from destinations_status JSON.
			$error_message = null;
			if ($last_failed && ! empty($last_failed->destinations_status)) {
				$destinations_status = json_decode($last_failed->destinations_status, true);
				if (isset($destinations_status[$dest]['error'])) {
					$error_message = $destinations_status[$dest]['error'];
				}
			}

			$results[$dest] = array(
				'destination'           => $dest,
				'quality_score'         => $quality_score,
				'dedup_rate'            => round($dedup_rate, 2),
				'server_side_coverage'  => round($server_coverage, 2),
				'delivery_success_rate' => round($delivery_rate, 2),
				'missing_params_rate'   => round($missing_params_rate, 2),
				'match_quality'         => $match_quality,
				'last_7_days_events'    => absint($total_events),
				'server_events'         => absint($server_events),
				'delivered_events'      => absint($delivered_events),
				'last_failed_event'     => $last_failed ? array(
					'error'      => sanitize_text_field($error_message ?: 'Unknown error'),
					'created_at' => $last_failed->created_at,
				) : null,
				'recommendations'       => $recommendations,
			);
		}

		$response = 'all' === $destination ? $results : $results[$destination];

		wp_cache_set($cache_key, $response, $this->cache_group, $this->cache_expiration);

		return $this->prepare_success($response);
	}

	/**
	 * Get deduplication statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_deduplication_stats($request)
	{
		$cache_key = 'dedup_stats';
		$cached    = wp_cache_get($cache_key, $this->cache_group);
		if (false !== $cached) {
			return $this->prepare_success($cached);
		}

		global $wpdb;
		// Get duplicate events (same event_id appearing multiple times).
		$duplicates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					event_id,
					event_name,
					COUNT(*) as occurrences,
					GROUP_CONCAT(DISTINCT event_source ORDER BY event_source) as sources
				FROM {$wpdb->prefix}tracksure_events
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY event_id
				HAVING COUNT(*) > %d
				ORDER BY occurrences DESC
				LIMIT %d",
				7,
				1,
				100
			)
		);

		// Get dedup stats by event type.
		$by_event_type = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					event_name,
					COUNT(*) as total_events,
					COUNT(DISTINCT event_id) as unique_events,
					(COUNT(*) - COUNT(DISTINCT event_id)) as duplicates,
					ROUND((COUNT(*) - COUNT(DISTINCT event_id)) * 100.0 / COUNT(*), 2) as dedup_rate
				FROM {$wpdb->prefix}tracksure_events
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY event_name
				ORDER BY dedup_rate DESC",
				7
			)
		);

		$formatted_duplicates = array_map(
			function ($dup) {
				return array(
					'event_id'    => sanitize_text_field($dup->event_id),
					'event_name'  => sanitize_text_field($dup->event_name),
					'occurrences' => absint($dup->occurrences),
					'sources'     => explode(',', sanitize_text_field($dup->sources)),
				);
			},
			$duplicates
		);

		$formatted_by_type = array_map(
			function ($type) {
				return array(
					'event_name' => sanitize_text_field($type->event_name),
					'total'      => absint($type->total_events),
					'duplicates' => absint($type->duplicates),
					'dedup_rate' => floatval($type->dedup_rate),
				);
			},
			$by_event_type
		);

		// Calculate totals.
		$total_events       = array_sum(array_column($formatted_by_type, 'total'));
		$total_duplicates   = array_sum(array_column($formatted_by_type, 'duplicates'));
		$unique_events      = $total_events - $total_duplicates;
		$overall_dedup_rate = $total_events > 0 ? ($total_duplicates / $total_events) * 100 : 0;

		$response = array(
			'total_events'     => $total_events,
			'unique_events'    => $unique_events,
			'duplicate_events' => $total_duplicates,
			'dedup_rate'       => round($overall_dedup_rate, 2),
			'by_event_type'    => $formatted_by_type,
		);

		wp_cache_set($cache_key, $response, $this->cache_group, $this->cache_expiration);

		return $this->prepare_success($response);
	}

	/**
	 * Get event schema validation results.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_schema_validation($request)
	{
		$cache_key = 'schema_validation';
		$cached    = wp_cache_get($cache_key, $this->cache_group);
		if (false !== $cached) {
			return $this->prepare_success($cached);
		}

		global $wpdb;
		// Define expected schema per event type.
		$schemas = array(
			'purchase'    => array(
				'required' => array('value', 'currency', 'items'),
				'optional' => array('transaction_id', 'shipping', 'tax'),
			),
			'view_item'   => array(
				'required' => array('items'),
				'optional' => array('value', 'currency'),
			),
			'add_to_cart' => array(
				'required' => array('items'),
				'optional' => array('value', 'currency'),
			),
		);

		$validation_results = array();

		foreach ($schemas as $event_name => $schema) {
			// Count events.
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_events
                     WHERE event_name = %s
                       AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)",
					$event_name
				)
			);

			if ($total === 0) {
				continue;
			}

			$missing_params = array();

			foreach ($schema['required'] as $param) {
				$missing_count = $wpdb->get_var(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix (safe).
						"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_events
						 WHERE event_name = %s
						   AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
						   AND (JSON_UNQUOTE(JSON_EXTRACT(event_params, CONCAT('$.', %s))) IS NULL
						        OR JSON_UNQUOTE(JSON_EXTRACT(event_params, CONCAT('$.', %s))) = '')",
						$event_name,
						$param,
						$param
					)
				);

				if ($missing_count > 0) {
					$missing_params[] = array(
						'param'         => $param,
						'missing_count' => absint($missing_count),
						'missing_rate'  => round(($missing_count / $total) * 100, 2),
						'severity'      => 'error',
					);
				}
			}

			$validation_results[] = array(
				'event_name'     => $event_name,
				'total_events'   => absint($total),
				'valid_events'   => absint($total - array_sum(array_column($missing_params, 'missing_count'))),
				'invalid_events' => absint(array_sum(array_column($missing_params, 'missing_count'))),
				'missing_params' => $missing_params,
				'status'         => empty($missing_params) ? 'valid' : 'needs_attention',
			);
		}

		$response = array(
			'schemas' => $validation_results,
			'summary' => array(
				'total_schemas'   => count($validation_results),
				'valid_schemas'   => count(
					array_filter(
						$validation_results,
						function ($r) {
							return $r['status'] === 'valid';
						}
					)
				),
				'invalid_schemas' => count(
					array_filter(
						$validation_results,
						function ($r) {
							return $r['status'] === 'needs_attention';
						}
					)
				),
			),
		);

		wp_cache_set($cache_key, $response, $this->cache_group, $this->cache_expiration);

		return $this->prepare_success($response);
	}

	/**
	 * Get reconciliation data (Meta vs GA4 vs TrackSure).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_reconciliation($request)
	{
		$cache_key = 'reconciliation';
		$cached    = wp_cache_get($cache_key, $this->cache_group);
		if (false !== $cached) {
			return $this->prepare_success($cached);
		}

		global $wpdb;
		// Get TrackSure counts (last 7 days).
		$tracksure_events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					event_name,
					COUNT(*) as count
				FROM {$wpdb->prefix}tracksure_events
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY event_name",
				7
			)
		);

		// Get outbox delivery stats per destination.
		$meta_delivered = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event_name')) as event_name,
					COUNT(*) as count
				FROM {$wpdb->prefix}tracksure_outbox
				WHERE JSON_CONTAINS(destinations, '\"meta\"')
				AND JSON_EXTRACT(destinations_status, '$.meta.status') = %s
				AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY event_name",
				'success',
				7
			)
		);

		$ga4_delivered = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event_name')) as event_name,
					COUNT(*) as count
				FROM {$wpdb->prefix}tracksure_outbox
				WHERE JSON_CONTAINS(destinations, '\"ga4\"')
				AND JSON_EXTRACT(destinations_status, '$.ga4.status') = %s
				AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY event_name",
				'success',
				7
			)
		);

		// Format comparison table.
		$comparison  = array();
		$event_types = array_unique(array_column($tracksure_events, 'event_name'));

		foreach ($event_types as $event_name) {
			$ts_count = absint(
				array_sum(
					array_column(
						array_filter(
							$tracksure_events,
							function ($e) use ($event_name) {
								return $e->event_name === $event_name;
							}
						),
						'count'
					)
				)
			);

			$meta_count = absint(
				array_sum(
					array_column(
						array_filter(
							$meta_delivered,
							function ($e) use ($event_name) {
								return $e->event_name === $event_name;
							}
						),
						'count'
					)
				)
			);

			$ga4_count = absint(
				array_sum(
					array_column(
						array_filter(
							$ga4_delivered,
							function ($e) use ($event_name) {
								return $e->event_name === $event_name;
							}
						),
						'count'
					)
				)
			);

			$comparison[] = array(
				'event_name'    => sanitize_text_field($event_name),
				'tracksure'     => $ts_count,
				'meta'          => $meta_count,
				'ga4'           => $ga4_count,
				'meta_diff'     => $ts_count - $meta_count,
				'ga4_diff'      => $ts_count - $ga4_count,
				'meta_coverage' => $ts_count > 0 ? round(($meta_count / $ts_count) * 100, 2) : 0,
				'ga4_coverage'  => $ts_count > 0 ? round(($ga4_count / $ts_count) * 100, 2) : 0,
			);
		}

		$response = array(
			'comparison' => $comparison,
			'explainer'  => array(
				'why_different' => array(
					array(
						'reason'      => 'Browser-side only events',
						'description' => 'Meta Pixel and GA4 gtag.js fire in the browser. Ad blockers block 30-50% of these events.',
					),
					array(
						'reason'      => 'Deduplication',
						'description' => 'TrackSure counts each event once (by event_id). Destinations may count the same event from both browser and server.',
					),
					array(
						'reason'      => 'Consent blocking',
						'description' => 'If users decline consent, browser pixels don\'t fire, but TrackSure still tracks (with consent flag).',
					),
					array(
						'reason'      => 'Delayed processing',
						'description' => 'TrackSure records instantly. Destination platforms may take 24-48 hours to process server events.',
					),
					array(
						'reason'      => 'Event filtering',
						'description' => 'Some events may not be mapped/sent to certain destinations if not supported.',
					),
				),
			),
		);

		wp_cache_set($cache_key, $response, $this->cache_group, $this->cache_expiration);

		return $this->prepare_success($response);
	}

	/**
	 * Get required params for a destination.
	 *
	 * @param string $destination Destination name.
	 * @return array
	 */
	private function get_required_params($destination)
	{
		$required_params = array(
			'meta' => array(
				'purchase'    => array('value', 'currency', 'event_id'),
				'view_item'   => array('event_id'),
				'add_to_cart' => array('event_id'),
			),
			'ga4'  => array(
				'purchase'    => array('value', 'currency', 'items'),
				'view_item'   => array('items'),
				'add_to_cart' => array('items'),
			),
		);

		return $required_params[$destination] ?? array();
	}

	/**
	 * Count events with missing required params.
	 *
	 * @param string $destination Destination name.
	 * @param array  $required_params Required params map.
	 * @return int
	 */
	private function count_missing_params($destination, $required_params)
	{
		global $wpdb;
		$missing_count = 0;

		foreach ($required_params as $event_name => $params) {
			foreach ($params as $param) {
				$count          = $wpdb->get_var(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix (safe).
						"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_events
						 WHERE event_name = %s
						   AND occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
						   AND (JSON_UNQUOTE(JSON_EXTRACT(event_params, CONCAT('$.', %s))) IS NULL
						        OR JSON_UNQUOTE(JSON_EXTRACT(event_params, CONCAT('$.', %s))) = '')",
						$event_name,
						$param,
						$param
					)
				);
				$missing_count += absint($count);
			}
		}

		return $missing_count;
	}

	/**
	 * Get recommendations based on quality metrics.
	 *
	 * @param int   $quality_score Overall score.
	 * @param float $server_coverage Server coverage %.
	 * @param float $dedup_rate Dedup %.
	 * @param float $delivery_rate Delivery success %.
	 * @param float $missing_params_rate Missing params %.
	 * @return array
	 */
	private function get_recommendations($quality_score, $server_coverage, $dedup_rate, $delivery_rate, $missing_params_rate)
	{
		$recs = array();

		if ($server_coverage < 80) {
			$recs[] = sprintf(
				'⚠️ Enable server-side delivery: Only %.1f%% of events are sent server-side. Enable server delivery to bypass ad blockers.',
				$server_coverage
			);
		}

		if ($dedup_rate < 95) {
			$recs[] = sprintf(
				'⚠️ Fix event duplication: %.1f%% of events are duplicated. Check for multiple tracking scripts or conflicting plugins.',
				100 - $dedup_rate
			);
		}

		if ($delivery_rate < 90) {
			$recs[] = sprintf(
				'⚠️ Improve delivery success rate: Only %.1f%% of server events delivered successfully. Check API credentials and error logs.',
				$delivery_rate
			);
		}

		if ($missing_params_rate > 10) {
			$recs[] = sprintf(
				'⚠️ Fix missing parameters: %.1f%% of events are missing required parameters. This affects match quality.',
				$missing_params_rate
			);
		}

		if ($quality_score >= 85) {
			$recs[] = '✅ Excellent signal quality! Your tracking setup is performing optimally.';
		}

		// Always return array (empty if no recommendations).
		return $recs;
	}
}
