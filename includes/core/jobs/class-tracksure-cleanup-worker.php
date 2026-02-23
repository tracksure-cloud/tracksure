<?php
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
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Cleanup worker class.
 */
class TrackSure_Cleanup_Worker
{



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
	 * Get instance.
	 *
	 * @return TrackSure_Cleanup_Worker
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
		$this->db = TrackSure_DB::get_instance();
	}

	/**
	 * Clean up old data.
	 */
	public function cleanup()
	{
		$retention_days = absint(get_option('tracksure_retention_days', 90));

		if ($retention_days === 0) {
			return; // Unlimited retention.
		}

		$cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

		$this->cleanup_events($cutoff_date);
		$this->cleanup_sessions($cutoff_date);
		$this->cleanup_touchpoints($cutoff_date);
		$this->cleanup_conversions($cutoff_date);
		$this->cleanup_outbox($cutoff_date);
		$this->cleanup_aggregates($cutoff_date);

		/**
		 * Fires after cleanup.
		 *
		 * @since 1.0.0
		 *
		 * @param string $cutoff_date Cutoff date.
		 * @param int    $retention_days Retention days.
		 */
		do_action('tracksure_cleanup_completed', $cutoff_date, $retention_days);
	}

	/**
	 * Cleanup events.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_events($cutoff_date)
	{
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_events WHERE created_at < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Cleanup sessions.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_sessions($cutoff_date)
	{
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_sessions WHERE started_at < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Cleanup touchpoints.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_touchpoints($cutoff_date)
	{
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_touchpoints WHERE touched_at < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Cleanup conversions.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_conversions($cutoff_date)
	{
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_conversions WHERE converted_at < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Cleanup outbox (completed/failed items only).
	 *
	 * ✅ OPTIMIZED: Fixed 7-day cleanup for completed items (not retention period)
	 *
	 * @param string $cutoff_date Cutoff date (unused - always 7 days).
	 */
	private function cleanup_outbox($cutoff_date)
	{
		global $wpdb;
		// ✅ FIXED: Always use 7-day window for completed items (not retention period).
		$seven_days_ago = gmdate('Y-m-d H:i:s', strtotime('-7 days'));

		// Delete completed items older than 7 days (batch delete to avoid table locks).
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_outbox 
                WHERE status = 'completed'
                AND updated_at < %s
                LIMIT 10000",
				$seven_days_ago
			)
		);

		// Delete old failed items (older than 30 days).
		$thirty_days_ago = gmdate('Y-m-d H:i:s', strtotime('-30 days'));

		$deleted_failed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_outbox 
                WHERE status = 'failed'
                AND updated_at < %s
                LIMIT 10000",
				$thirty_days_ago
			)
		);

		if ($deleted_failed) {
		}
	}

	/**
	 * Cleanup aggregates.
	 *
	 * @param string $cutoff_date Cutoff date.
	 */
	private function cleanup_aggregates($cutoff_date)
	{
		global $wpdb;
		// Delete hourly aggregates.
		$deleted_hourly = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_agg_hourly WHERE hour_start < %s",
				$cutoff_date
			)
		);

		// Delete daily aggregates.
		$deleted_daily = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_agg_daily WHERE date < %s",
				gmdate('Y-m-d', strtotime($cutoff_date))
			)
		);
	}
}
