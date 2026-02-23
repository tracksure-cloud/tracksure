<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Background queue processing with direct DB queries

/**
 *
 * TrackSure Action Scheduler Integration
 *
 * Provides background queue processing using Action Scheduler for reliability.
 * Falls back to WP-Cron if Action Scheduler is not available.
 *
 * @package TrackSure
 * @subpackage Core\Services
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class TrackSure_Action_Scheduler
{


	/**
	 * Queue processing action name
	 */
	const QUEUE_ACTION = 'tracksure_process_queue';

	/**
	 * Destination delivery action name
	 */
	const DELIVERY_ACTION = 'tracksure_deliver_events';

	/**
	 * Initialize Action Scheduler integration
	 */
	public function __construct()
	{
		// Schedule recurring queue processing.
		add_action('init', array($this, 'schedule_recurring_tasks'));

		// Register action handlers.
		add_action(self::QUEUE_ACTION, array($this, 'process_queue'));
		add_action(self::DELIVERY_ACTION, array($this, 'deliver_events'));
	}

	/**
	 * Schedule recurring background tasks
	 */
	public function schedule_recurring_tasks()
	{
		// Use Action Scheduler if available (WooCommerce provides it).
		if (function_exists('as_has_scheduled_action')) {
			// Schedule queue processing every 5 seconds.
			if (! as_has_scheduled_action(self::QUEUE_ACTION)) {
				as_schedule_recurring_action(
					time(),
					5, // Every 5 seconds
					self::QUEUE_ACTION,
					array(),
					'tracksure'
				);
			}

			// Schedule destination delivery every 10 seconds.
			if (! as_has_scheduled_action(self::DELIVERY_ACTION)) {
				as_schedule_recurring_action(
					time(),
					10, // Every 10 seconds
					self::DELIVERY_ACTION,
					array(),
					'tracksure'
				);
			}
		} else {
			// Fallback to WP-Cron.
			$this->schedule_wp_cron_tasks();
		}
	}

	/**
	 * Fallback to WP-Cron if Action Scheduler not available
	 */
	private function schedule_wp_cron_tasks()
	{
		// Register custom interval.
		add_filter('cron_schedules', array($this, 'add_cron_intervals'));

		// Schedule queue processing.
		if (! wp_next_scheduled(self::QUEUE_ACTION)) {
			wp_schedule_event(time(), 'tracksure_every_5_seconds', self::QUEUE_ACTION);
		}

		// Schedule delivery.
		if (! wp_next_scheduled(self::DELIVERY_ACTION)) {
			wp_schedule_event(time(), 'tracksure_every_10_seconds', self::DELIVERY_ACTION);
		}
	}

	/**
	 * Add custom WP-Cron intervals
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_intervals($schedules)
	{
		$schedules['tracksure_every_5_seconds'] = array(
			'interval' => 5,
			'display'  => __('Every 5 Seconds', 'tracksure'),
		);

		$schedules['tracksure_every_10_seconds'] = array(
			'interval' => 10,
			'display'  => __('Every 10 Seconds', 'tracksure'),
		);

		return $schedules;
	}

	/**
	 * Process event queue in background
	 *
	 * This flushes any pending events to database
	 */
	public function process_queue()
	{
		// Flush any pending events in queue.
		if (class_exists('TrackSure_Event_Queue')) {
			$queue_size = TrackSure_Event_Queue::get_queue_size();

			if ($queue_size > 0) {
				TrackSure_Event_Queue::flush();

				// Log for debugging.
				if (defined('WP_DEBUG') && WP_DEBUG) {
					if (defined('WP_DEBUG') && WP_DEBUG) {

						error_log(sprintf('[TrackSure] Processed %d events from queue', $queue_size));
					}
				}
			}
		}
	}

	/**
	 * Deliver events to destinations in background
	 *
	 * This sends pending events to Meta CAPI, GA4 MP, etc.
	 */
	public function deliver_events()
	{
		// Get events that need to be delivered to destinations.
		global $wpdb;
		// Get last 100 events (most recent first).
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_id, visitor_id, session_id, event_name, event_source, 
						occurred_at, created_at, page_url, page_title, event_params 
				FROM {$wpdb->prefix}tracksure_events 
				WHERE created_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
				ORDER BY created_at DESC 
				LIMIT %d",
				1,
				100
			),
			ARRAY_A
		);

		if (empty($events)) {
			return;
		}

		// Trigger destination delivery hook for each event.
		foreach ($events as $event) {
			/**
			 * Fire event recorded hook for destination delivery
			 *
			 * Destinations hook into this to send events to their platforms
			 */
			do_action('tracksure_event_recorded', $event['event_id'], $event);
		}

		// Log for debugging.
		if (defined('WP_DEBUG') && WP_DEBUG) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log(sprintf('[TrackSure] Delivered %d events to destinations', count($events)));
			}
		}
	}

	/**
	 * Unschedule all tasks (for deactivation)
	 */
	public static function unschedule_all()
	{
		// Action Scheduler cleanup.
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions(self::QUEUE_ACTION, array(), 'tracksure');
			as_unschedule_all_actions(self::DELIVERY_ACTION, array(), 'tracksure');
		}

		// WP-Cron cleanup.
		wp_clear_scheduled_hook(self::QUEUE_ACTION);
		wp_clear_scheduled_hook(self::DELIVERY_ACTION);
	}

	/**
	 * Check if Action Scheduler is available
	 *
	 * @return bool
	 */
	public static function is_action_scheduler_available()
	{
		return function_exists('as_schedule_recurring_action');
	}

	/**
	 * Get queue stats for diagnostics
	 *
	 * @return array
	 */
	public static function get_stats()
	{
		$stats = array(
			'scheduler_type' => self::is_action_scheduler_available() ? 'Action Scheduler' : 'WP-Cron',
			'queue_size'     => class_exists('TrackSure_Event_Queue') ? TrackSure_Event_Queue::get_queue_size() : 0,
		);

		// Get Action Scheduler stats if available.
		if (function_exists('as_get_scheduled_actions')) {
			$stats['scheduled_queue_tasks'] = count(
				as_get_scheduled_actions(
					array(
						'hook'   => self::QUEUE_ACTION,
						'status' => 'pending',
					)
				)
			);

			$stats['scheduled_delivery_tasks'] = count(
				as_get_scheduled_actions(
					array(
						'hook'   => self::DELIVERY_ACTION,
						'status' => 'pending',
					)
				)
			);
		}

		return $stats;
	}
}

// Initialize.
new TrackSure_Action_Scheduler();
