<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for background queue diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Action Scheduler Integration
 *
 * Provides background queue processing for outbox event delivery.
 * Uses a tiered approach for maximum compatibility:
 *
 * Tier 1: Action Scheduler (if available from WooCommerce, EDD, or standalone plugin)
 *         - Reliable, runs exactly every N seconds via its own runner
 *         - NOT bundled—auto-detected at runtime
 * Tier 2: WP-Cron (built into WordPress core, always available)
 *         - Page-load dependent: runs on next visitor request after interval expires
 *         - On low-traffic sites, events may wait until next visit
 *
 * Both tiers are fully functional. TrackSure does NOT require WooCommerce or any
 * external plugin. WP-Cron is the default and works reliably on all hosting.
 *
 * Zero configuration required: The Event Recorder calls spawn_cron() after each
 * outbox write, which sends a non-blocking loopback request to wp-cron.php.
 * This ensures delivery starts within ~60 seconds on ANY hosting — shared, VPS,
 * dedicated, nginx, Apache, LiteSpeed — without the user editing wp-config.php
 * or setting up system cron jobs.
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

defined( 'ABSPATH' ) || exit;

class TrackSure_Action_Scheduler {


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
	 * Transient key used for concurrency lock.
	 *
	 * Prevents multiple delivery processes from running simultaneously
	 * (e.g., overlapping cron runs on shared hosting).
	 *
	 * @var string
	 */
	const LOCK_KEY = 'tracksure_delivery_lock';

	/**
	 * Lock duration in seconds (auto-expires to prevent deadlocks).
	 *
	 * @var int
	 */
	const LOCK_DURATION = 120;

	/**
	 * Initialize Action Scheduler integration.
	 */
	public function __construct() {
		// CRITICAL: Register custom cron interval EARLY (before 'init').
		// wp_schedule_event() reads 'cron_schedules' filter during init.
		// If we register the filter too late, the schedule name is unknown
		// and WP-Cron silently fails to schedule the event.
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );

		add_action( 'init', array( $this, 'schedule_recurring_tasks' ) );
		add_action( self::DELIVERY_ACTION, array( $this, 'deliver_events' ) );
	}

	/**
	 * Schedule recurring background tasks.
	 *
	 * Auto-detects Action Scheduler at runtime. If available (e.g., WooCommerce,
	 * Easy Digital Downloads, or standalone Action Scheduler plugin), uses it for
	 * precise scheduling. Otherwise falls back to WP-Cron (built into WordPress core).
	 */
	public function schedule_recurring_tasks() {
		// Tier 1: Action Scheduler (auto-detected, not bundled).
		// Available when WooCommerce 3.0+, EDD 3.0+, or standalone AS plugin is active.
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::DELIVERY_ACTION ) ) {
				as_schedule_recurring_action(
					time(),
					self::DELIVERY_INTERVAL,
					self::DELIVERY_ACTION,
					array(),
					'tracksure'
				);
			}
		} else {
			// Tier 2: WP-Cron (built into WordPress core — always available).
			// Page-load dependent but works on every WordPress install including shared hosting.
			$this->schedule_wp_cron_tasks();
		}
	}

	/**
	 * Schedule delivery via WP-Cron (built into WordPress core).
	 *
	 * WP-Cron is page-load dependent: it runs when a visitor hits the site
	 * after the scheduled interval has passed. For busy sites this is fine.
	 * For low-traffic sites, spawn_cron() (called from Event Recorder after
	 * each outbox write) nudges cron to run immediately after events are queued.
	 */
	private function schedule_wp_cron_tasks() {
		// cron_schedules filter already registered in constructor (must be early).
		if ( ! wp_next_scheduled( self::DELIVERY_ACTION ) ) {
			wp_schedule_event( time(), 'tracksure_every_minute', self::DELIVERY_ACTION );
		}
	}

	/**
	 * Add custom WP-Cron interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_intervals( $schedules ) {
		$schedules['tracksure_every_minute'] = array(
			'interval' => self::DELIVERY_INTERVAL,
			'display'  => __( 'Every Minute', 'tracksure' ),
		);

		return $schedules;
	}

	/**
	 * Deliver pending outbox events to all destinations.
	 *
	 * Uses a transient-based lock to prevent concurrent runs (e.g., overlapping
	 * cron triggers on shared hosting). Lock auto-expires after LOCK_DURATION
	 * seconds to prevent deadlocks from crashed processes.
	 *
	 * Delegates entirely to the Delivery Worker which handles:
	 * - Batch grouping per destination (Meta, GA4, TikTok, etc.)
	 * - Per-destination retry with exponential backoff
	 * - Batch HTTP calls (Meta CAPI: up to 100/call, GA4 MP: up to 25/call)
	 */
	public function deliver_events() {
		// Concurrency lock: prevent overlapping delivery runs.
		// Uses set_transient which is atomic on most hosts (MySQL INSERT or Memcached add).
		if ( get_transient( self::LOCK_KEY ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TrackSure] Delivery Worker: Skipped — another delivery process is running.' );
			}
			return;
		}
		set_transient( self::LOCK_KEY, time(), self::LOCK_DURATION );

		try {
			if ( ! class_exists( 'TrackSure_Delivery_Worker' ) ) {
				return;
			}

			$worker     = TrackSure_Delivery_Worker::get_instance();
			$batch_size = apply_filters( 'tracksure_delivery_batch_size', self::BATCH_SIZE );

			$worker->process_outbox( $batch_size );
		} finally {
			// Always release lock, even on error.
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Unschedule all tasks (for deactivation).
	 *
	 * Cleans up both Action Scheduler and WP-Cron hooks to prevent orphaned tasks.
	 */
	public static function unschedule_all() {
		// Clean up Action Scheduler tasks (if AS is available).
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::DELIVERY_ACTION, array(), 'tracksure' );
		}

		// Clean up WP-Cron tasks.
		wp_clear_scheduled_hook( self::DELIVERY_ACTION );

		// Release any active delivery lock.
		delete_transient( self::LOCK_KEY );
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * Action Scheduler is NOT part of WordPress core. It is a library shipped by
	 * WooCommerce (3.0+), Easy Digital Downloads (3.0+), and available as a
	 * standalone plugin. TrackSure does NOT require it — WP-Cron works fine.
	 *
	 * @return bool True if Action Scheduler API functions are available.
	 */
	public static function is_action_scheduler_available() {
		return function_exists( 'as_schedule_recurring_action' );
	}

	/**
	 * Get queue stats for diagnostics.
	 *
	 * @return array Queue statistics including scheduler type, pending items, and config.
	 */
	public static function get_stats() {
		global $wpdb;

		$stats = array(
			'scheduler_type'    => self::is_action_scheduler_available() ? 'Action Scheduler' : 'WP-Cron',
			'delivery_interval' => self::DELIVERY_INTERVAL,
			'batch_size'        => self::BATCH_SIZE,
		);

		// Outbox queue depth (cached for 30 seconds to avoid repeated COUNT queries).
		$cache_key      = 'tracksure_outbox_pending_count';
		$outbox_pending = wp_cache_get( $cache_key, 'tracksure' );

		if ( false === $outbox_pending ) {
			$outbox_pending = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, no WP API available
				"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_outbox WHERE status IN ('pending', 'processing')"
			);
			wp_cache_set( $cache_key, $outbox_pending, 'tracksure', 30 );
		}

		$stats['outbox_pending'] = $outbox_pending;

		// Action Scheduler stats if available.
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
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
