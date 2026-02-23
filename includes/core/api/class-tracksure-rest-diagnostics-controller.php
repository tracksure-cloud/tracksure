<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Diagnostic endpoint for troubleshooting, uses direct DB queries for system health checks

/**
 *
 * TrackSure REST Diagnostics Controller
 *
 * Handles diagnostic and testing endpoints for admin.
 * Endpoints: GET /diagnostics/cron, GET /diagnostics/health
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Diagnostics controller class.
 */
class TrackSure_REST_Diagnostics_Controller extends TrackSure_REST_Controller
{



	/**
	 * Register routes.
	 */
	public function register_routes()
	{
		// GET /diagnostics/cron - Check cron health.
		register_rest_route(
			$this->namespace,
			'/diagnostics/cron',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_cron_status'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// GET /diagnostics/health - System health check.
		register_rest_route(
			$this->namespace,
			'/diagnostics/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_health_status'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// GET /diagnostics/delivery - Delivery statistics.
		register_rest_route(
			$this->namespace,
			'/diagnostics/delivery',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_delivery_stats'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => array(
					'period' => array(
						'description'       => 'Time period for stats',
						'type'              => 'string',
						'default'           => '7d',
						'enum'              => array('1h', '24h', '7d', '30d'),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get cron status.
	 *
	 * Returns WordPress cron job status and TrackSure scheduled events.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_cron_status($request)
	{
		$cron_disabled = defined('DISABLE_WP_CRON') && constant('DISABLE_WP_CRON');

		// Get all cron jobs.
		$cron_jobs = _get_cron_array();

		// Find TrackSure jobs.
		$tracksure_jobs = array();
		foreach ($cron_jobs as $timestamp => $hooks) {
			foreach ($hooks as $hook => $events) {
				if (strpos($hook, 'tracksure_') === 0) {
					foreach ($events as $key => $event) {
						$tracksure_jobs[] = array(
							'hook'      => $hook,
							'next_run'  => gmdate('Y-m-d H:i:s', $timestamp),
							'timestamp' => $timestamp,
							'schedule'  => isset($event['schedule']) ? $event['schedule'] : 'once',
							'args'      => isset($event['args']) ? $event['args'] : array(),
						);
					}
				}
			}
		}

		// Sort by next run time.
		usort(
			$tracksure_jobs,
			function ($a, $b) {
				return $a['timestamp'] - $b['timestamp'];
			}
		);

		// Get cron schedules.
		$schedules           = wp_get_schedules();
		$tracksure_schedules = array();
		foreach ($schedules as $key => $schedule) {
			if (strpos($key, 'tracksure_') === 0) {
				$tracksure_schedules[$key] = $schedule;
			}
		}

		return $this->prepare_success(
			array(
				'cron_enabled'        => ! $cron_disabled,
				'cron_disabled'       => $cron_disabled,
				'current_time'        => gmdate('Y-m-d H:i:s'),
				'tracksure_jobs'      => $tracksure_jobs,
				'tracksure_schedules' => $tracksure_schedules,
				'total_cron_jobs'     => count($cron_jobs),
				'status'              => ! $cron_disabled && count($tracksure_jobs) > 0 ? 'healthy' : 'warning',
			)
		);
	}

	/**
	 * Get system health status.
	 *
	 * Returns various system health indicators.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_health_status($request)
	{
		global $wpdb;

		$core = TrackSure_Core::get_instance();
		$db   = $core->get_service('db');

		$health = array(
			'database' => array(
				'status'  => 'healthy',
				'message' => 'Database connection OK',
			),
			'tables'   => array(
				'status'  => 'healthy',
				'message' => 'All tables exist',
			),
			'tracking' => array(
				'status'  => 'healthy',
				'message' => 'Tracking is enabled',
			),
		);

		// Check database connection.
		$db_check = $wpdb->get_var('SELECT 1');
		if ($db_check !== '1') {
			$health['database'] = array(
				'status'  => 'error',
				'message' => 'Database connection failed',
			);
		}

		// Check if tables exist.
		$events_table = $wpdb->prefix . 'tracksure_events';
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events_table)) === $events_table;

		if (! $table_exists) {
			$health['tables'] = array(
				'status'  => 'error',
				'message' => 'Events table missing',
			);
		}

		// Check if tracking is enabled.
		$tracking_enabled = get_option('tracksure_tracking_enabled', true);
		if (! $tracking_enabled) {
			$health['tracking'] = array(
				'status'  => 'warning',
				'message' => 'Tracking is disabled in settings - no data is being collected',
			);
		} else {
			// Check admin tracking.
			$track_admins       = get_option('tracksure_track_admins', false);
			$health['tracking'] = array(
				'status'  => 'healthy',
				'message' => sprintf(
					'Tracking is active%s',
					$track_admins ? ' (including administrators)' : ' (excluding administrators)'
				),
			);
		}

		// Get recent event count (last 5 minutes).
		if ($table_exists) {
			// Use UTC time since created_at is stored in UTC.
			$recent_events           = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_events 
					WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d MINUTE)",
					5
				)
			);
			$health['recent_events'] = array(
				'count'   => (int) $recent_events,
				'period'  => '5 minutes',
				'status'  => $recent_events > 0 ? 'healthy' : 'warning',
				'message' => sprintf(
					'%d events received in last 5 minutes',
					$recent_events
				),
			);
		}

		// Overall status.
		$overall_status = 'healthy';
		foreach ($health as $check) {
			if (isset($check['status']) && $check['status'] === 'error') {
				$overall_status = 'error';
				break;
			} elseif (isset($check['status']) && $check['status'] === 'warning' && $overall_status !== 'error') {
				$overall_status = 'warning';
			}
		}

		// Get delivery stats (if table exists).
		$delivery_stats = array();
		if ($table_exists) {
			$delivery_stats     = $this->get_quick_delivery_stats();
			$health['delivery'] = array(
				'status'  => 'healthy',
				'message' => sprintf(
					'Browser: %d%%, Server: %d%%, Both: %d%%',
					$delivery_stats['browser_percent'],
					$delivery_stats['server_percent'],
					$delivery_stats['both_percent']
				),
			);
		}

		return $this->prepare_success(
			array(
				'status'         => $overall_status,
				'checks'         => $health,
				'delivery_stats' => $delivery_stats,
				'timestamp'      => time(),
			)
		);
	}

	/**
	 * Get quick delivery stats for health check.
	 *
	 * @return array Delivery statistics.
	 */
	private function get_quick_delivery_stats()
	{
		global $wpdb;

		// Get counts for last 24 hours.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as total,
					SUM(CASE WHEN browser_fired = 1 THEN 1 ELSE 0 END) as browser_count,
					SUM(CASE WHEN server_fired = 1 THEN 1 ELSE 0 END) as server_count,
					SUM(CASE WHEN browser_fired = 1 AND server_fired = 1 THEN 1 ELSE 0 END) as both_count
				FROM {$wpdb->prefix}tracksure_events
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)",
				24
			),
			ARRAY_A
		);

		if (! $stats || $stats['total'] == 0) {
			return array(
				'total'           => 0,
				'browser_count'   => 0,
				'server_count'    => 0,
				'both_count'      => 0,
				'browser_percent' => 0,
				'server_percent'  => 0,
				'both_percent'    => 0,
			);
		}

		return array(
			'total'           => (int) $stats['total'],
			'browser_count'   => (int) $stats['browser_count'],
			'server_count'    => (int) $stats['server_count'],
			'both_count'      => (int) $stats['both_count'],
			'browser_percent' => round(($stats['browser_count'] / $stats['total']) * 100, 1),
			'server_percent'  => round(($stats['server_count'] / $stats['total']) * 100, 1),
			'both_percent'    => round(($stats['both_count'] / $stats['total']) * 100, 1),
		);
	}

	/**
	 * Get delivery statistics.
	 *
	 * Returns detailed delivery stats (browser vs server vs both).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_delivery_stats($request)
	{
		global $wpdb;

		$period = $request->get_param('period') ?: '7d';

		// Convert period to hours.
		$hours_map = array(
			'1h'  => 1,
			'24h' => 24,
			'7d'  => 168,
			'30d' => 720,
		);

		$hours = isset($hours_map[$period]) ? $hours_map[$period] : 168;

		// Overall delivery stats.
		$overall = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN browser_fired = 1 THEN 1 ELSE 0 END) as browser_count,
                    SUM(CASE WHEN server_fired = 1 THEN 1 ELSE 0 END) as server_count,
                    SUM(CASE WHEN browser_fired = 1 AND server_fired = 1 THEN 1 ELSE 0 END) as both_count,
                    SUM(CASE WHEN browser_fired = 0 AND server_fired = 1 THEN 1 ELSE 0 END) as server_only,
                    SUM(CASE WHEN browser_fired = 1 AND server_fired = 0 THEN 1 ELSE 0 END) as browser_only
                FROM {$wpdb->prefix}tracksure_events
                WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)",
				$hours
			),
			ARRAY_A
		);

		// Per-event breakdown.
		$by_event = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    event_name,
                    COUNT(*) as total,
                    SUM(CASE WHEN browser_fired = 1 THEN 1 ELSE 0 END) as browser_count,
                    SUM(CASE WHEN server_fired = 1 THEN 1 ELSE 0 END) as server_count,
                    SUM(CASE WHEN browser_fired = 1 AND server_fired = 1 THEN 1 ELSE 0 END) as both_count
                FROM {$wpdb->prefix}tracksure_events
                WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)
                GROUP BY event_name
                ORDER BY total DESC
                LIMIT 20",
				$hours
			),
			ARRAY_A
		);

		// Calculate percentages.
		if ($overall && $overall['total'] > 0) {
			$overall['browser_percent']      = round(($overall['browser_count'] / $overall['total']) * 100, 1);
			$overall['server_percent']       = round(($overall['server_count'] / $overall['total']) * 100, 1);
			$overall['both_percent']         = round(($overall['both_count'] / $overall['total']) * 100, 1);
			$overall['server_only_percent']  = round(($overall['server_only'] / $overall['total']) * 100, 1);
			$overall['browser_only_percent'] = round(($overall['browser_only'] / $overall['total']) * 100, 1);
		}

		// Calculate percentages for each event.
		if ($by_event) {
			foreach ($by_event as &$event) {
				if ($event['total'] > 0) {
					$event['browser_percent'] = round(($event['browser_count'] / $event['total']) * 100, 1);
					$event['server_percent']  = round(($event['server_count'] / $event['total']) * 100, 1);
					$event['both_percent']    = round(($event['both_count'] / $event['total']) * 100, 1);
				}
			}
		}

		// Timeline data (hourly for last 24h, daily for longer periods).
		if ($hours <= 24) {
			$timeline = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
                        DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00') as time_bucket,
                        COUNT(*) as total,
                        SUM(CASE WHEN browser_fired = 1 THEN 1 ELSE 0 END) as browser_count,
                        SUM(CASE WHEN server_fired = 1 THEN 1 ELSE 0 END) as server_count,
                        SUM(CASE WHEN browser_fired = 1 AND server_fired = 1 THEN 1 ELSE 0 END) as both_count
                    FROM {$wpdb->prefix}tracksure_events
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
                    GROUP BY time_bucket
                    ORDER BY time_bucket ASC",
					$hours
				),
				ARRAY_A
			);
		} else {
			$timeline = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
                        DATE(created_at) as time_bucket,
                        COUNT(*) as total,
                        SUM(CASE WHEN browser_fired = 1 THEN 1 ELSE 0 END) as browser_count,
                        SUM(CASE WHEN server_fired = 1 THEN 1 ELSE 0 END) as server_count,
                        SUM(CASE WHEN browser_fired = 1 AND server_fired = 1 THEN 1 ELSE 0 END) as both_count
                    FROM {$wpdb->prefix}tracksure_events
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
                    GROUP BY DATE(created_at)
                    ORDER BY time_bucket ASC",
					$hours
				),
				ARRAY_A
			);
		}

		return $this->prepare_success(
			array(
				'period'   => $period,
				'overall'  => $overall,
				'by_event' => $by_event,
				'timeline' => $timeline,
			)
		);
	}
}
