<?php

/**
 *
 * TrackSure Event Queue
 *
 * Batches events for bulk insertion to handle high traffic (100K+ visitors).
 * Events are queued in memory and flushed on shutdown or when batch size reached. *
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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Queue class.
 */
class TrackSure_Event_Queue {






	/**
	 * Queue storage.
	 *
	 * @var array
	 */
	private static $queue = array();

	/**
	 * Maximum batch size before auto-flush.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Add event to queue.
	 *
	 * @param array $event_data Event data array.
	 */
	public static function enqueue( $event_data ) {
		self::$queue[] = $event_data;

		// Auto-flush if batch size reached.
		if ( count( self::$queue ) >= self::BATCH_SIZE ) {
			self::flush();
		}
	}

	/**
	 * Flush queue to database using multi-row INSERT.
	 */
	public static function flush() {
		if ( empty( self::$queue ) ) {
			return;
		}

		global $wpdb;

		$queue = self::$queue;

		// Build multi-row INSERT query.
		$placeholders = array();
		$values       = array();

		// Sentinel value: prepare() will quote this; we replace it with literal NULL after.
		$null_sentinel = '___TRACKSURE_SQL_NULL___';

		foreach ( $queue as $event ) {
			// All values including JSON go through prepare(). NULL JSON uses sentinel.
			$placeholders[] = '(%s, %s, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %f, %d)';

			// Ensure event_params is JSON string or NULL (not empty string).
			$event_params = isset( $event['event_params'] ) && $event['event_params'] !== '' ? $event['event_params'] : null;
			if ( is_array( $event_params ) ) {
				$event_params = wp_json_encode( $event_params );
			} elseif ( empty( $event_params ) ) {
				$event_params = null;
			}

			// Ensure user_data is JSON string or NULL (not empty string).
			$user_data = isset( $event['user_data'] ) && $event['user_data'] !== '' ? $event['user_data'] : null;
			if ( is_array( $user_data ) ) {
				$user_data = wp_json_encode( $user_data );
			} elseif ( empty( $user_data ) ) {
				$user_data = null;
			}

			// Ensure ecommerce_data is JSON string or NULL (not empty string).
			$ecommerce_data = isset( $event['ecommerce_data'] ) && $event['ecommerce_data'] !== '' ? $event['ecommerce_data'] : null;
			if ( is_array( $ecommerce_data ) ) {
				$ecommerce_data = wp_json_encode( $ecommerce_data );
			} elseif ( empty( $ecommerce_data ) ) {
				$ecommerce_data = null;
			}

			// Ensure destinations_sent is JSON string or NULL (not empty string).
			$destinations_sent = isset( $event['destinations_sent'] ) && $event['destinations_sent'] !== '' ? $event['destinations_sent'] : null;
			if ( is_array( $destinations_sent ) ) {
				$destinations_sent = wp_json_encode( $destinations_sent );
			} elseif ( empty( $destinations_sent ) ) {
				$destinations_sent = null;
			}

			// Compute browser_fired_at — nullable timestamp field.
			$browser_fired_at = ( ! empty( $event['browser_fired_at'] ) && $event['browser_fired_at'] !== '' ) ? $event['browser_fired_at'] : null;

			// Convert IP to binary if present.
			$ip_binary = null;
			if ( ! empty( $event['ip_address'] ) && filter_var( $event['ip_address'], FILTER_VALIDATE_IP ) ) {
				$ip_binary = inet_pton( $event['ip_address'] );
			}

			$values[] = $event['event_id'];
			$values[] = isset( $event['visitor_id'] ) ? $event['visitor_id'] : null;
			$values[] = $event['session_id'];
			$values[] = $event['event_name'];
			$values[] = isset( $event['event_source'] ) ? $event['event_source'] : 'server';
			$values[] = isset( $event['browser_fired'] ) ? (int) $event['browser_fired'] : 0;
			$values[] = isset( $event['server_fired'] ) ? (int) $event['server_fired'] : 1;
			// JSON-nullable fields: use sentinel for NULL (literal NULL after prepare).
			$values[] = is_null( $browser_fired_at ) ? $null_sentinel : $browser_fired_at;     // 8. browser_fired_at
			$values[] = is_null( $destinations_sent ) ? $null_sentinel : $destinations_sent;   // 9. destinations_sent
			$values[] = is_null( $event_params ) ? $null_sentinel : $event_params;             // 10. event_params
			$values[] = is_null( $user_data ) ? $null_sentinel : $user_data;                   // 11. user_data
			$values[] = is_null( $ecommerce_data ) ? $null_sentinel : $ecommerce_data;         // 12. ecommerce_data
			$values[] = isset( $event['occurred_at'] ) ? $event['occurred_at'] : current_time( 'mysql', 1 ); // 13. occurred_at
			$values[] = current_time( 'mysql', 1 );                                   // 14. created_at (server time)
			// CRITICAL: All VARCHAR fields - empty string '' must become NULL
			$values[] = ( ! empty( $event['page_url'] ) && $event['page_url'] !== '' ) ? $event['page_url'] : null;    // 15. page_url
			$values[] = ( ! empty( $event['page_path'] ) && $event['page_path'] !== '' ) ? $event['page_path'] : null;  // 16. page_path
			$values[] = ( ! empty( $event['page_title'] ) && $event['page_title'] !== '' ) ? $event['page_title'] : null; // 17. page_title
			// page_url_hash is GENERATED column - skip.
			$values[] = ( ! empty( $event['referrer'] ) && $event['referrer'] !== '' ) ? $event['referrer'] : null;   // 18. referrer
			$values[] = ( ! empty( $event['user_agent'] ) && $event['user_agent'] !== '' ) ? $event['user_agent'] : null; // 19. user_agent
			$values[] = $ip_binary;                                                 // 20. ip_address (binary)
			$values[] = ( ! empty( $event['device_type'] ) && $event['device_type'] !== '' ) ? $event['device_type'] : null; // 21. device_type
			$values[] = ( ! empty( $event['browser'] ) && $event['browser'] !== '' ) ? $event['browser'] : null;      // 22. browser
			$values[] = ( ! empty( $event['os'] ) && $event['os'] !== '' ) ? $event['os'] : null;                // 23. os
			// Convert empty strings to NULL for VARCHAR columns (country, region, city)
			$values[] = ! empty( $event['country'] ) ? $event['country'] : null;      // 24. country
			$values[] = ! empty( $event['region'] ) ? $event['region'] : null;        // 25. region
			$values[] = ! empty( $event['city'] ) ? $event['city'] : null;            // 26. city
			$values[] = isset( $event['is_conversion'] ) ? $event['is_conversion'] : 0; // 27. is_conversion
			$values[] = isset( $event['conversion_value'] ) ? $event['conversion_value'] : null; // 28. conversion_value
			$values[] = isset( $event['consent_granted'] ) ? $event['consent_granted'] : 1; // 29. consent_granted
		}

		$sql = "INSERT IGNORE INTO {$wpdb->prefix}tracksure_events 
        (event_id, visitor_id, session_id, event_name, event_source, browser_fired, server_fired,
         browser_fired_at, destinations_sent, event_params, user_data, ecommerce_data,
         occurred_at, created_at, page_url, page_path, page_title, referrer, user_agent, ip_address,
         device_type, browser, os, country, region, city, is_conversion, conversion_value, consent_granted)
            VALUES " . implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic multi-row INSERT with table prefix.
		$prepared_sql = $wpdb->prepare( $sql, $values );

		// Replace sentinel values with literal SQL NULL for JSON columns.
		$prepared_sql = str_replace( "'" . $null_sentinel . "'", 'NULL', $prepared_sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fully prepared above; sentinel→NULL is safe.
		$wpdb->query( $prepared_sql );

		// Clear queue after successful insert.
		self::$queue = array();

		/**
		 * Fires after event queue is flushed.
		 *
		 * @since 1.0.0
		 *
		 * @param int $count Number of events flushed.
		 */
		do_action( 'tracksure_queue_flushed', count( $queue ) );
	}

	/**
	 * Get current queue size.
	 *
	 * @return int
	 */
	public static function get_queue_size() {
		return count( self::$queue );
	}

	/**
	 * Clear queue without flushing.
	 */
	public static function clear() {
		self::$queue = array();
	}
}

// Flush on shutdown to ensure all events are saved.
add_action( 'shutdown', array( 'TrackSure_Event_Queue', 'flush' ) );
