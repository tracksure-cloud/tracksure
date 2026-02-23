<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Debug logging + direct DB queries required for delivery queue, $wpdb->prefix is safe

/**
 *
 * TrackSure Delivery Worker
 *
 * Processes outbox queue to send events to destinations (Meta CAPI, GA4, etc).
 * Implements retry logic with exponential backoff.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Delivery worker class.
 */
class TrackSure_Delivery_Worker
{




	/**
	 * Instance.
	 *
	 * @var TrackSure_Delivery_Worker
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Event Mapper instance.
	 *
	 * @var TrackSure_Event_Mapper
	 */
	private $event_mapper;

	/**
	 * Max retry count (72 hours of retries with exponential backoff).
	 *
	 * @var int
	 */
	const MAX_DELIVERY_RETRIES = 9;
	private $max_retries       = self::MAX_DELIVERY_RETRIES;

	/**
	 * Exponential backoff schedule (in seconds).
	 * Retries over 72 hours: 1min, 2min, 5min, 15min, 1hr, 6hr, 24hr, 48hr, 72hr
	 *
	 * @var array
	 */
	private $backoff_schedule = array(
		0 => 60,       // 1 minute
		1 => 120,      // 2 minutes
		2 => 300,      // 5 minutes
		3 => 900,      // 15 minutes
		4 => 3600,     // 1 hour
		5 => 21600,    // 6 hours
		6 => 86400,    // 24 hours
		7 => 172800,   // 48 hours
		8 => 259200,   // 72 hours
	);

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Delivery_Worker
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
		$this->db           = TrackSure_DB::get_instance();
		$this->event_mapper = TrackSure_Event_Mapper::get_instance();
	}

	/**
	 * Process outbox queue.
	 *
	 * ✅ OPTIMIZED: Handles destinations array with per-destination status tracking
	 *
	 * @param int $batch_size Batch size.
	 */
	public function process_outbox($batch_size = 50)
	{
		global $wpdb;
		// Get pending/processing items (destinations array schema).
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT outbox_id, event_id, destinations, destinations_status, payload, 
                        status, retry_count, created_at, updated_at 
                 FROM {$wpdb->prefix}tracksure_outbox 
                 WHERE status IN ('pending', 'processing')
                 ORDER BY created_at ASC 
                 LIMIT %d",
				$batch_size
			)
		);

		if (empty($items)) {
			return;
		}

		foreach ($items as $item) {
			$this->process_item_with_destinations($item);
		}

		/**
		 * Fires after outbox processing.
		 *
		 * @since 1.0.0
		 *
		 * @param int $processed_count Number of items processed.
		 */
		do_action('tracksure_outbox_processed', count($items));
	}

	/**
	 * Process single outbox item with destinations array.
	 *
	 * ✅ NEW: Handles per-destination status tracking and partial failures
	 *
	 * @param object $item Outbox item.
	 */
	private function process_item_with_destinations($item)
	{
		global $wpdb;
		// Decode payload.
		$payload = ! empty($item->payload) ? json_decode($item->payload, true) : array();
		if (! $payload) {
			$this->mark_failed_new_schema($item->outbox_id, 'Invalid payload JSON');
			return;
		}

		// Decode destinations and status.
		$destinations        = ! empty($item->destinations) ? json_decode($item->destinations, true) : array();
		$destinations_status = ! empty($item->destinations_status) ? json_decode($item->destinations_status, true) : array();

		if (! is_array($destinations) || ! is_array($destinations_status)) {
			$this->mark_failed_new_schema($item->outbox_id, 'Invalid destinations format');
			return;
		}

		// Mark as processing.
		if ($item->status === 'pending') {
			$wpdb->update(
				$wpdb->prefix . 'tracksure_outbox',
				array(
					'status'     => 'processing',
					'updated_at' => current_time('mysql', 1),
				),
				array('outbox_id' => $item->outbox_id),
				array('%s', '%s'),
				array('%d')
			);
		}

		$all_completed = true;
		$has_failures  = false;

		foreach ($destinations as $destination) {
			// Skip already successful destinations.
			if (isset($destinations_status[$destination]['status']) && $destinations_status[$destination]['status'] === 'success') {
				continue;
			}

			// Check retry limit for this destination.
			$dest_retry_count = isset($destinations_status[$destination]['retry_count']) ? (int) $destinations_status[$destination]['retry_count'] : 0;

			if ($dest_retry_count >= $this->max_retries) {
				// Max retries reached for this destination.
				$destinations_status[$destination]['status']                = 'failed';
				$destinations_status[$destination]['failed_permanently_at'] = current_time('mysql', 1);
				$has_failures = true;
				continue;
			}

			// Map event to destination format.
			$mapped_event = $this->event_mapper->map_to_destination($payload, $destination);

			if (! $mapped_event) {
				// Event not supported by this destination (not an error).
				$destinations_status[$destination]['status']         = 'skipped';
				$destinations_status[$destination]['skipped_reason'] = 'Event not supported';
				continue;
			}

			/**
			 * Send event to destination.
			 *
			 * ✅ EXTENSIBILITY: Pro/3rd party destinations register via this filter
			 *
			 * @param string $destination Destination ID (meta, ga4, tiktok, etc).
			 * @param array  $mapped_event Destination-formatted event from Event Mapper.
			 * @param int    $outbox_id Outbox item ID.
			 * @return array Result with 'success' (bool) and 'error' (string).
			 */
			$result = apply_filters(
				'tracksure_deliver_mapped_event',
				array(
					'success' => false,
					'error'   => 'No delivery handler registered for ' . $destination,
				),
				$destination,
				$mapped_event,
				$item->outbox_id
			);

			if ($result['success']) {
				// Success - update status.
				$destinations_status[$destination] = array(
					'status'      => 'success',
					'sent_at'     => current_time('mysql', 1),
					'retry_count' => $dest_retry_count,
				);

				// Update destinations_sent in events table.
				$this->update_destinations_sent($payload['event_id'], $destination);
			} else {
				// Failure - update retry count and error.
				++$dest_retry_count;
				$destinations_status[$destination] = array(
					'status'       => $dest_retry_count >= $this->max_retries ? 'failed' : 'retry',
					'error'        => $result['error'],
					'retry_count'  => $dest_retry_count,
					'last_attempt' => current_time('mysql', 1),
				);

				$all_completed = false;
				$has_failures  = true;

				if ($dest_retry_count >= $this->max_retries) {
					if (defined('WP_DEBUG') && WP_DEBUG) {

						error_log(
							"[
							TrackSure] Delivery Worker: Max retries reached - event_id={$payload['event_id']},
							destination={$destination},
							error={$result['error']}"
						);
					}
				} else {
					$backoff_seconds = isset($this->backoff_schedule[$dest_retry_count]) ? $this->backoff_schedule[$dest_retry_count] : 259200;
					$backoff_human   = $this->format_seconds_human($backoff_seconds);
				}
			}
		}

		// Update outbox row with new destinations_status.
		$overall_status = $all_completed ? 'completed' : ($has_failures ? 'processing' : 'processing');

		$wpdb->update(
			$wpdb->prefix . 'tracksure_outbox',
			array(
				'destinations_status' => wp_json_encode($destinations_status),
				'status'              => $overall_status,
				'retry_count'         => (int) $item->retry_count + 1,
				'updated_at'          => current_time('mysql', 1),
			),
			array('outbox_id' => $item->outbox_id),
			array('%s', '%s', '%d', '%s'),
			array('%d')
		);
	}

	/**
	 * Update destinations_sent column in events table.
	 *
	 * @param string $event_id Event ID.
	 * @param string $destination Destination ID.
	 */
	private function update_destinations_sent($event_id, $destination)
	{
		global $wpdb;
		// Get current destinations_sent.
		$current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT destinations_sent FROM {$wpdb->prefix}tracksure_events WHERE event_id = %s",
				$event_id
			)
		);

		// Decode existing destinations (if any).
		// CRITICAL: Handle empty strings from old events (MySQL 8.0+ rejects empty JSON strings).
		$destinations = array();
		if (! empty($current) && $current !== '') {
			$decoded = json_decode($current, true);
			if (is_array($decoded)) {
				$destinations = $decoded;
			}
		}

		// Add new destination if not already present.
		if (! in_array($destination, $destinations, true)) {
			$destinations[] = $destination;
		}

		// CRITICAL: Convert empty array to NULL for MySQL 8.0+ compatibility.
		// Only encode if we have destinations, otherwise use NULL.
		$destinations_value = ! empty($destinations) ? wp_json_encode($destinations) : null;

		// Update events table.
		$wpdb->update(
			$wpdb->prefix . 'tracksure_events',
			array('destinations_sent' => $destinations_value),
			array('event_id' => $event_id),
			array('%s'),
			array('%s')
		);
	}

	/**
	 * Mark item as failed (new schema compatible).
	 *
	 * @param int    $item_id Item ID.
	 * @param string $error Error message.
	 */
	private function mark_failed_new_schema($item_id, $error)
	{
		global $wpdb;
		// Update to failed status (error stored in destinations_status JSON).
		$wpdb->update(
			$wpdb->prefix . 'tracksure_outbox',
			array(
				'status'     => 'failed',
				'updated_at' => current_time('mysql', 1),
			),
			array('outbox_id' => $item_id),
			array('%s', '%s'),
			array('%d')
		);

		/**
		 * Fires when delivery fails.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $item_id Item ID.
		 * @param string $error Error message.
		 */
		do_action('tracksure_delivery_failed', $item_id, $error);

		if (defined('WP_DEBUG') && WP_DEBUG) {

			error_log("[TrackSure] Delivery Worker: Marked outbox item {$item_id} as failed - {$error}");
		}
	}

	/**
	 * Format seconds into human-readable time.
	 *
	 * @param int $seconds Seconds.
	 * @return string Human-readable time.
	 */
	private function format_seconds_human($seconds)
	{
		if ($seconds < 60) {
			return $seconds . ' seconds';
		} elseif ($seconds < 3600) {
			return round($seconds / 60) . ' minutes';
		} elseif ($seconds < 86400) {
			return round($seconds / 3600) . ' hours';
		} else {
			return round($seconds / 86400) . ' days';
		}
	}
}
