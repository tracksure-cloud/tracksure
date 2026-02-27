<?php

/**
 * Background cleanup worker for stale data.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Debug logging + direct DB queries required for cleanup operations, $wpdb->prefix is safe

/**
 *
 * TrackSure Cleanup Worker
 *
 * Deletes old data based on retention policy.
 * Runs daily via WP-Cron.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleanup worker class.
 */
class TrackSure_Cleanup_Worker {



	/**
	 * Maximum seconds a single cleanup run is allowed to take.
	 * Prevents timeout on shared hosting where max_execution_time is 30-60s.
	 *
	 * @var int
	 */
	const TIME_BOX_SECONDS = 20;

	/**
	 * Maximum rows to delete per batch loop iteration.
	 * Keeps each DELETE from locking the table for too long.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 10000;

	/**
	 * Instance.
	 *
	 * @var TrackSure_Cleanup_Worker
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Timestamp when the current cleanup run started.
	 *
	 * @var float
	 */
	private $start_time = 0;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Cleanup_Worker
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->db = TrackSure_DB::get_instance();
	}

	/**
	 * Check whether the time box has been exceeded.
	 *
	 * @return bool True if we should stop processing.
	 */
	private function is_time_exceeded() {
		$max_time = self::TIME_BOX_SECONDS;

		// Respect the server's max_execution_time if it is lower.
		$ini_max = (int) ini_get( 'max_execution_time' );
		if ( $ini_max > 0 ) {
			$max_time = min( $max_time, max( 5, $ini_max - 5 ) );
		}

		return ( microtime( true ) - $this->start_time ) >= $max_time;
	}

	/**
	 * Clean up old data with time-boxing and concurrency lock.
	 */
	public function cleanup() {
		// Concurrency lock — prevent overlapping cron runs.
		if ( get_transient( 'tracksure_cleanup_lock' ) ) {
			return;
		}
		set_transient( 'tracksure_cleanup_lock', 1, 5 * MINUTE_IN_SECONDS );

		$this->start_time = microtime( true );

		$retention_days = absint( get_option( 'tracksure_retention_days', 90 ) );

		if ( $retention_days === 0 ) {
			delete_transient( 'tracksure_cleanup_lock' );
			return; // Unlimited retention.
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$this->cleanup_events( $cutoff_date );
		if ( ! $this->is_time_exceeded() ) {
			$this->cleanup_sessions( $cutoff_date );
		}
		if ( ! $this->is_time_exceeded() ) {
			$this->cleanup_touchpoints( $cutoff_date );
		}
		if ( ! $this->is_time_exceeded() ) {
			$this->cleanup_conversions( $cutoff_date );
		}
		if ( ! $this->is_time_exceeded() ) {
			$this->cleanup_outbox( $cutoff_date );
		}
		if ( ! $this->is_time_exceeded() ) {
			$this->cleanup_aggregates( $cutoff_date );
		}

		delete_transient( 'tracksure_cleanup_lock' );

		/**
		 * Fires after cleanup.
		 *
		 * @since 1.0.0
		 *
		 * @param string $cutoff_date Cutoff date.
		 * @param int    $retention_days Retention days.
		 */
		do_action( 'tracksure_cleanup_completed', $cutoff_date, $retention_days );
	}

	/**
	 * Batched DELETE helper — deletes rows in chunks of BATCH_SIZE within the time box.
	 *
	 * @param string $table Full table name (with prefix).
	 * @param string $column Date column to filter on.
	 * @param string $cutoff_date Cutoff date (rows older than this are deleted).
	 * @return int Total deleted rows.
	 */
	private function batched_delete( $table, $column, $cutoff_date ) {
		global $wpdb;

		$total_deleted = 0;

		do {
			if ( $this->is_time_exceeded() ) {
				break;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table and $column are hardcoded by callers.
			$deleted = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE {$column} < %s LIMIT %d",
					$cutoff_date,
					self::BATCH_SIZE
				)
			);

			$total_deleted += $deleted;

			// If we deleted fewer than BATCH_SIZE, the table is clean.
		} while ( $deleted >= self::BATCH_SIZE );

		return $total_deleted;
	}

	/**
	 * Cleanup events.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_events( $cutoff_date ) {
		global $wpdb;
		$this->batched_delete( "{$wpdb->prefix}tracksure_events", 'created_at', $cutoff_date );
	}

	/**
	 * Cleanup sessions.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_sessions( $cutoff_date ) {
		global $wpdb;
		$this->batched_delete( "{$wpdb->prefix}tracksure_sessions", 'started_at', $cutoff_date );
	}

	/**
	 * Cleanup touchpoints.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_touchpoints( $cutoff_date ) {
		global $wpdb;
		$this->batched_delete( "{$wpdb->prefix}tracksure_touchpoints", 'touched_at', $cutoff_date );
	}

	/**
	 * Cleanup conversions.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_conversions( $cutoff_date ) {
		global $wpdb;
		$this->batched_delete( "{$wpdb->prefix}tracksure_conversions", 'converted_at', $cutoff_date );
	}

	/**
	 * Cleanup outbox (completed/failed items only).
	 *
	 * Uses a shorter time window (7/30 days) independent of the retention period.
	 *
	 * @param string $cutoff_date Cutoff date (unused — outbox has its own windows).
	 */
	private function cleanup_outbox( $cutoff_date ) {
		global $wpdb;
		$table = "{$wpdb->prefix}tracksure_outbox";

		// Delete completed items older than 7 days.
		$seven_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		do {
			if ( $this->is_time_exceeded() ) {
				return;
			}
			$deleted = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE status = 'completed' AND updated_at < %s LIMIT %d",
					$seven_days_ago,
					self::BATCH_SIZE
				)
			);
		} while ( $deleted >= self::BATCH_SIZE );

		// Delete failed items older than 30 days.
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		do {
			if ( $this->is_time_exceeded() ) {
				return;
			}
			$deleted = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE status = 'failed' AND updated_at < %s LIMIT %d",
					$thirty_days_ago,
					self::BATCH_SIZE
				)
			);
		} while ( $deleted >= self::BATCH_SIZE );
	}

	/**
	 * Cleanup aggregates.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_aggregates( $cutoff_date ) {
		global $wpdb;
		$this->batched_delete( "{$wpdb->prefix}tracksure_agg_hourly", 'hour_start', $cutoff_date );

		if ( ! $this->is_time_exceeded() ) {
			$this->batched_delete(
				"{$wpdb->prefix}tracksure_agg_daily",
				'date',
				gmdate( 'Y-m-d', strtotime( $cutoff_date ) )
			);
		}
	}
}
