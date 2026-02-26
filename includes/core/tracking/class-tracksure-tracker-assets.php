<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Debug logging + direct DB queries for goals, $wpdb->prefix is safe

/**
 *
 * TrackSure Tracker Assets
 *
 * Enqueues browser tracking script with configuration.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Tracker assets class.
 */
class TrackSure_Tracker_Assets
{




	/**
	 * Instance.
	 *
	 * @var TrackSure_Tracker_Assets
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Tracker_Assets
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
		add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking_script'));
	}

	/**
	 * Enqueue tracking script.
	 */
	public function enqueue_tracking_script()
	{
		// Don't track if user opted out.
		if (! $this->should_enqueue()) {
			return;
		}

		// 1. Enqueue currency handler (no dependencies - pure utility).
		wp_enqueue_script(
			'ts-currency',
			TRACKSURE_PLUGIN_URL . 'assets/js/ts-currency.js',
			array(),
			TRACKSURE_VERSION,
			true
		);

		// 2. Enqueue goal constants (shared constants for JS/React/PHP).
		wp_enqueue_script(
			'ts-goal-constants',
			TRACKSURE_PLUGIN_URL . 'admin/tracksure-goal-constants.js',
			array(),
			TRACKSURE_VERSION,
			true
		);

		// 3. Enqueue main tracking script (neutral name avoids ad-blocker keyword matching).
		// Uses 'defer' strategy (WP 6.3+) so the script doesn't block HTML parsing.
		// Falls back to footer loading on older WP versions.
		wp_enqueue_script(
			'ts-web',
			TRACKSURE_PLUGIN_URL . 'assets/js/ts-web.js',
			array(),
			TRACKSURE_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		// Pass configuration to script using centralized schema.
		$config = TrackSure_Settings_Schema::get_js_config();
		wp_localize_script(
			'ts-web',
			'trackSureConfig',
			$config
		);

		// 4. Enqueue Universal MiniCart tracking (depends on ts-web + ts-currency).
		wp_enqueue_script(
			'ts-minicart',
			TRACKSURE_PLUGIN_URL . 'assets/js/ts-minicart.js',
			array('ts-web', 'ts-currency'),
			TRACKSURE_VERSION,
			true
		);

		// 5. Enqueue consent change listeners (depends on ts-web for config).
		wp_enqueue_script(
			'ts-consent-listeners',
			TRACKSURE_PLUGIN_URL . 'assets/js/consent-listeners.js',
			array('ts-web'),
			TRACKSURE_VERSION,
			true
		);

		// 6. Enqueue goals tracking script (depends on constants + main tracker).
		wp_enqueue_script(
			'ts-goals',
			TRACKSURE_PLUGIN_URL . 'admin/tracking-goals.js',
			array('ts-goal-constants', 'ts-web'), // Depends on both
			TRACKSURE_VERSION,
			true
		);

		// Pass active goals to script.
		wp_localize_script(
			'ts-goals',
			'tracksure_goals',
			$this->get_active_goals()
		);
	}

	/**
	 * Get active goals for front-end tracking.
	 *
	 * @return array
	 */
	private function get_active_goals()
	{
		global $wpdb;


		// Check cache first (5 minute TTL).
		$cache_key = 'tracksure_active_goals';
		$cached    = get_transient($cache_key);

		if ($cached !== false) {
			return $cached;
		}

		// Query active goals.
		$goals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                goal_id,
                name,
                event_name,
                trigger_type,
                conditions,
                match_logic,
                value_type,
                fixed_value,
                is_active
            FROM {$wpdb->prefix}tracksure_goals
            WHERE is_active = %d
            ORDER BY goal_id ASC",
				1
			),
			ARRAY_A
		);

		if ($wpdb->last_error) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log('[TrackSure] Failed to fetch active goals: ' . $wpdb->last_error);
			}
			return array();
		}

		// Parse JSON fields.
		$goals = array_map(
			function ($goal) {
				if (isset($goal['conditions']) && is_string($goal['conditions'])) {
					$goal['conditions'] = json_decode($goal['conditions'], true) ?: array();
				}
				if (isset($goal['match_logic']) && is_string($goal['match_logic'])) {
					$goal['match_logic'] = json_decode($goal['match_logic'], true);
				}
				return $goal;
			},
			$goals
		);

		// Cache for 5 minutes.
		set_transient($cache_key, $goals, 5 * MINUTE_IN_SECONDS);

		return $goals;
	}

	/**
	 * Check if script should be enqueued.
	 *
	 * @return bool
	 */
	private function should_enqueue()
	{
		// Master switch - tracking enabled?
		$tracking_enabled = get_option('tracksure_tracking_enabled', false);
		if (! $tracking_enabled) {
			return false;
		}

		// Don't track admins if disabled.
		$track_admins = get_option('tracksure_track_admins', false);
		$is_admin     = current_user_can('manage_options');
		if (! $track_admins && $is_admin) {
			return false;
		}

		// Check excluded IPs.
		$excluded_ips = get_option('tracksure_exclude_ips', '');
		if ($excluded_ips) {
			$excluded_ips = array_map('trim', explode(',', $excluded_ips));
			$client_ip    = TrackSure_Utilities::get_client_ip();

			if (in_array($client_ip, $excluded_ips, true)) {
				return false;
			}
		}

		/**
		 * Filter whether tracking script should be enqueued.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $should_enqueue Whether to enqueue script.
		 */
		return apply_filters('tracksure_should_enqueue_tracker', true);
	}

	/**
	 * Check if auto-tracking is enabled.
	 *
	 * @return bool
	 */
	private function is_auto_track_enabled()
	{
		/**
		 * Filter whether auto-tracking is enabled.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $auto_track Whether auto-tracking is enabled.
		 */
		return apply_filters('tracksure_auto_track', true);
	}
}
