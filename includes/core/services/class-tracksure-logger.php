<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Logger service uses direct DB for error storage

/**
 *
 * TrackSure Logger Service
 *
 * Centralized error logging with database storage.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Logger service class.
 */
class TrackSure_Logger
{


	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Maximum age for logs (days).
	 *
	 * @var int
	 */
	private $retention_days = 30;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $wpdb;
		$this->table = $wpdb->prefix . 'tracksure_logs';
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public function log_error($message, $context = array())
	{
		$this->log('error', $message, $context);
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Warning message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public function log_warning($message, $context = array())
	{
		$this->log('warning', $message, $context);
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Info message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public function log_info($message, $context = array())
	{
		$this->log('info', $message, $context);
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Debug message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public function log_debug($message, $context = array())
	{
		if (! WP_DEBUG) {
			return; // Skip debug logs if WP_DEBUG is off
		}
		$this->log('debug', $message, $context);
	}

	/**
	 * Write log entry to database.
	 *
	 * @param string $level   Log level (error, warning, info, debug).
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private function log($level, $message, $context = array())
	{
		global $wpdb;

		$wpdb->insert(
			$this->table,
			array(
				'level'        => sanitize_text_field($level),
				'message'      => sanitize_text_field($message),
				'context_json' => ! empty($context) ? wp_json_encode($context) : null,
				'occurred_at'  => current_time('mysql', true),
				'ip_address'   => TrackSure_Utilities::get_client_ip(),
			),
			array('%s', '%s', '%s', '%s', '%s')
		);

		// Also log to PHP error_log for critical errors.
		if ($level === 'error') {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log.
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log(sprintf('[TrackSure] %s: %s', strtoupper($level), $message));
			}
		}
	}

	/**
	 * Get recent logs.
	 *
	 * @param int    $limit   Number of logs to retrieve.
	 * @param string $level   Filter by log level (optional).
	 * @return array          Array of log entries.
	 */
	public function get_recent_logs($limit = 20, $level = null)
	{
		global $wpdb;

		$query = "SELECT log_id, level, message, context_json, occurred_at FROM {$wpdb->prefix}tracksure_logs";

		if ($level) {
			$query .= $wpdb->prepare(' WHERE level = %s', $level);
		}

		$query .= ' ORDER BY occurred_at DESC';
		$query .= $wpdb->prepare(' LIMIT %d', $limit);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query dynamically built with safe methods and prepared LIMIT
		return $wpdb->get_results($query, ARRAY_A);
	}

	/**
	 * Clear old logs based on retention policy.
	 *
	 * @return int Number of deleted logs.
	 */
	public function cleanup_old_logs()
	{
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}tracksure_logs WHERE occurred_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$this->retention_days
			)
		);

		$this->log_info(
			sprintf(
				/* translators: %d: number of deleted log entries */
				__('Cleaned up %d old log entries', 'tracksure'),
				$deleted
			)
		);

		return $deleted;
	}

	/**
	 * Create database table.
	 *
	 * Called during plugin activation.
	 *
	 * @return void
	 */
	public static function create_table()
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tracksure_logs (
            log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            context_json LONGTEXT,
            occurred_at DATETIME NOT NULL,
            ip_address VARCHAR(45),
            PRIMARY KEY  (log_id),
            KEY level (level),
            KEY occurred_at (occurred_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}
}
