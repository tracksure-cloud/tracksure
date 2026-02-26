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

		// PHASE 1: Group events by destination for batch delivery.
		// This allows batch-capable destinations (Meta CAPI, GA4 MP) to send
		// multiple events in a single HTTP request, reducing overhead.
		$destination_batches = array(); // destination_id => array of { item, mapped_event }
		$item_states         = array(); // outbox_id => { destinations_status, all_completed, has_failures }

		foreach ($items as $item) {
			$payload = ! empty($item->payload) ? json_decode($item->payload, true) : array();
			if (! $payload) {
				$this->mark_failed_new_schema($item->outbox_id, 'Invalid payload JSON');
				continue;
			}

			$destinations        = ! empty($item->destinations) ? json_decode($item->destinations, true) : array();
			$destinations_status = ! empty($item->destinations_status) ? json_decode($item->destinations_status, true) : array();

			if (! is_array($destinations) || ! is_array($destinations_status)) {
				$this->mark_failed_new_schema($item->outbox_id, 'Invalid destinations format');
				continue;
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

			// Initialize item state tracking.
			$item_states[$item->outbox_id] = array(
				'item'                => $item,
				'payload'             => $payload,
				'destinations_status' => $destinations_status,
				'all_completed'       => true,
				'has_failures'        => false,
			);

			foreach ($destinations as $destination) {
				// Skip already successful destinations.
				if (isset($destinations_status[$destination]['status']) && $destinations_status[$destination]['status'] === 'success') {
					continue;
				}

				// Check retry limit for this destination.
				$dest_retry_count = isset($destinations_status[$destination]['retry_count']) ? (int) $destinations_status[$destination]['retry_count'] : 0;

				if ($dest_retry_count >= $this->max_retries) {
					$item_states[$item->outbox_id]['destinations_status'][$destination]['status']                = 'failed';
					$item_states[$item->outbox_id]['destinations_status'][$destination]['failed_permanently_at'] = current_time('mysql', 1);
					$item_states[$item->outbox_id]['has_failures'] = true;
					continue;
				}

				// Map event to destination format.
				$mapped_event = $this->event_mapper->map_to_destination($payload, $destination);

				if (! $mapped_event) {
					$item_states[$item->outbox_id]['destinations_status'][$destination]['status']         = 'skipped';
					$item_states[$item->outbox_id]['destinations_status'][$destination]['skipped_reason'] = 'Event not supported';
					continue;
				}

				// Group by destination for batch delivery.
				if (! isset($destination_batches[$destination])) {
					$destination_batches[$destination] = array();
				}

				$destination_batches[$destination][] = array(
					'outbox_id'    => $item->outbox_id,
					'mapped_event' => $mapped_event,
					'retry_count'  => $dest_retry_count,
				);
			}
		}

		// PHASE 2: Send batches to each destination.
		foreach ($destination_batches as $destination => $batch_entries) {
			/**
			 * Try batch delivery first (Meta CAPI supports up to 1000 events per call).
			 *
			 * Batch-capable destinations register via 'tracksure_deliver_batch' filter.
			 * This reduces HTTP overhead from N calls to 1 call per destination.
			 *
			 * @since 1.3.0
			 *
			 * @param array|false $batch_result False if no batch handler registered, or array of per-event results.
			 * @param string      $destination Destination ID (meta, ga4, tiktok, etc).
			 * @param array       $mapped_events Array of destination-formatted events from Event Mapper.
			 * @return array|false Array of results keyed by index, or false if batch not supported.
			 */
			$mapped_events = array_map(function ($entry) {
				return $entry['mapped_event'];
			}, $batch_entries);

			$batch_result = apply_filters(
				'tracksure_deliver_batch',
				false,
				$destination,
				$mapped_events
			);

			if (is_array($batch_result)) {
				// Batch delivery was handled — process per-event results.
				foreach ($batch_entries as $index => $entry) {
					$outbox_id    = $entry['outbox_id'];
					$retry_count  = $entry['retry_count'];
					$event_result = isset($batch_result[$index]) ? $batch_result[$index] : array('success' => false, 'error' => 'Missing batch result');

					$this->update_item_state($item_states, $outbox_id, $destination, $event_result, $retry_count);
				}
			} else {
				// No batch handler — fall back to individual delivery.
				foreach ($batch_entries as $entry) {
					$result = apply_filters(
						'tracksure_deliver_mapped_event',
						array(
							'success' => false,
							'error'   => 'No delivery handler registered for ' . $destination,
						),
						$destination,
						$entry['mapped_event'],
						$entry['outbox_id']
					);

					$this->update_item_state($item_states, $entry['outbox_id'], $destination, $result, $entry['retry_count']);
				}
			}
		}

		// PHASE 3: Write back all item states to DB.
		foreach ($item_states as $outbox_id => $state) {
			$overall_status = $state['all_completed'] ? 'completed' : ($state['has_failures'] ? 'processing' : 'processing');

			$wpdb->update(
				$wpdb->prefix . 'tracksure_outbox',
				array(
					'destinations_status' => wp_json_encode($state['destinations_status']),
					'status'              => $overall_status,
					'retry_count'         => (int) $state['item']->retry_count + 1,
					'updated_at'          => current_time('mysql', 1),
				),
				array('outbox_id' => $outbox_id),
				array('%s', '%s', '%d', '%s'),
				array('%d')
			);
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
	 * Update item state after delivery attempt (shared by batch and individual paths).
	 *
	 * @param array  $item_states Reference to item states array.
	 * @param int    $outbox_id Outbox item ID.
	 * @param string $destination Destination ID.
	 * @param array  $result Delivery result with 'success' and 'error'.
	 * @param int    $retry_count Current retry count for this destination.
	 */
	private function update_item_state(&$item_states, $outbox_id, $destination, $result, $retry_count)
	{
		if (! isset($item_states[$outbox_id])) {
			return;
		}

		$payload = $item_states[$outbox_id]['payload'];

		if ($result['success']) {
			$item_states[$outbox_id]['destinations_status'][$destination] = array(
				'status'      => 'success',
				'sent_at'     => current_time('mysql', 1),
				'retry_count' => $retry_count,
			);

			// Update destinations_sent in events table.
			$this->update_destinations_sent($payload['event_id'], $destination);
		} else {
			++$retry_count;
			$item_states[$outbox_id]['destinations_status'][$destination] = array(
				'status'       => $retry_count >= $this->max_retries ? 'failed' : 'retry',
				'error'        => $result['error'],
				'retry_count'  => $retry_count,
				'last_attempt' => current_time('mysql', 1),
			);

			$item_states[$outbox_id]['all_completed'] = false;
			$item_states[$outbox_id]['has_failures']  = true;

			if ($retry_count >= $this->max_retries && defined('WP_DEBUG') && WP_DEBUG) {

				error_log(
					"[TrackSure] Delivery Worker: Max retries reached - event_id={$payload['event_id']}, destination={$destination}, error={$result['error']}"
				);
			}
		}
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
}
