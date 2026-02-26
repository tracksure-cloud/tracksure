<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for background queue diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Action Scheduler Integration
 *
 * Provides background queue processing using Action Scheduler for reliability.
 * Falls back to WP-Cron if Action Scheduler is not available.
 *
 * Architecture:
 * 1. Browser/Server events → Event Recorder → outbox table (immediate, per-request)
 * 2. This scheduler → Delivery Worker → process_outbox() → destinations (background)
 *
 * The Delivery Worker handles batching, retries, and per-destination delivery.
 * This scheduler simply triggers it on a reliable interval.
 *
 * @package TrackSure
 * @subpackage Core\Services
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class TrackSure_Action_Scheduler
{

	/**
	 * Outbox delivery action name.
	 *
	 * Triggers: Delivery Worker → process_outbox() → destinations (Meta CAPI, GA4 MP, etc.)
	 */
	const DELIVERY_ACTION = 'tracksure_deliver_events';

	/**
	 * Default delivery interval in seconds.
	 *
	 * Action Scheduler: runs exactly every N seconds (reliable).
	 * WP-Cron fallback: runs on next page load after N seconds (best-effort).
	 *
	 * @var int
	 */
	const DELIVERY_INTERVAL = 60;

	/**
	 * Default outbox batch size per delivery run.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Initialize Action Scheduler integration.
	 */
	public function __construct()
	{
		// CRITICAL: Register custom cron interval EARLY (before 'init').
		// wp_schedule_event() reads 'cron_schedules' filter during init.
		// If we register the filter too late, the schedule name is unknown
		// and WP-Cron silently fails to schedule the event.
		add_filter('cron_schedules', array($this, 'add_cron_intervals'));

		add_action('init', array($this, 'schedule_recurring_tasks'));
		add_action(self::DELIVERY_ACTION, array($this, 'deliver_events'));
	}

	/**
	 * Schedule recurring background tasks.
	 */
	public function schedule_recurring_tasks()
	{
		// Use Action Scheduler if available (WooCommerce ships it since 3.0).
		if (function_exists('as_has_scheduled_action')) {
			if (! as_has_scheduled_action(self::DELIVERY_ACTION)) {
				as_schedule_recurring_action(
					time(),
					self::DELIVERY_INTERVAL,
					self::DELIVERY_ACTION,
					array(),
					'tracksure'
				);
			}
		} else {
			// Fallback to WP-Cron (runs on page loads, less precise).
			$this->schedule_wp_cron_tasks();
		}
	}

	/**
	 * Fallback to WP-Cron if Action Scheduler not available.
	 */
	private function schedule_wp_cron_tasks()
	{
		// cron_schedules filter already registered in constructor (must be early).
		if (! wp_next_scheduled(self::DELIVERY_ACTION)) {
			wp_schedule_event(time(), 'tracksure_every_minute', self::DELIVERY_ACTION);
		}
	}

	/**
	 * Add custom WP-Cron interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_intervals($schedules)
	{
		$schedules['tracksure_every_minute'] = array(
			'interval' => self::DELIVERY_INTERVAL,
			'display'  => __('Every Minute', 'tracksure'),
		);

		return $schedules;
	}

	/**
	 * Deliver pending outbox events to all destinations.
	 *
	 * Delegates entirely to the Delivery Worker which handles:
	 * - Batch grouping per destination (Meta, GA4, TikTok, etc.)
	 * - Per-destination retry with exponential backoff
	 * - Batch HTTP calls (Meta CAPI: up to 100/call, GA4 MP: up to 25/call)
	 */
	public function deliver_events()
	{
		if (! class_exists('TrackSure_Delivery_Worker')) {
			return;
		}

		$worker     = TrackSure_Delivery_Worker::get_instance();
		$batch_size = apply_filters('tracksure_delivery_batch_size', self::BATCH_SIZE);

		$worker->process_outbox($batch_size);
	}

	/**
	 * Unschedule all tasks (for deactivation).
	 */
	public static function unschedule_all()
	{
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions(self::DELIVERY_ACTION, array(), 'tracksure');
		}

		wp_clear_scheduled_hook(self::DELIVERY_ACTION);
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @return bool
	 */
	public static function is_action_scheduler_available()
	{
		return function_exists('as_schedule_recurring_action');
	}

	/**
	 * Get queue stats for diagnostics.
	 *
	 * @return array
	 */
	public static function get_stats()
	{
		global $wpdb;

		$stats = array(
			'scheduler_type'    => self::is_action_scheduler_available() ? 'Action Scheduler' : 'WP-Cron',
			'delivery_interval' => self::DELIVERY_INTERVAL,
			'batch_size'        => self::BATCH_SIZE,
		);

		// Outbox queue depth.
		$stats['outbox_pending'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_outbox WHERE status IN ('pending', 'processing')"
		);

		// Action Scheduler stats if available.
		if (function_exists('as_get_scheduled_actions')) {
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
