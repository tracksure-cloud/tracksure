<?php

/**
 * Frontend tracking script asset loader.
 *
 * @package TrackSure
 */

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

		// Use minified files on production (SCRIPT_DEBUG = false, the default).
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// 1. Enqueue main tracking script (neutral name avoids ad-blocker keyword matching).
		// Uses 'defer' strategy (WP 6.3+) so the script doesn't block HTML parsing.
		// Falls back to footer loading on older WP versions.
		wp_enqueue_script(
			'ts-web',
			TRACKSURE_PLUGIN_URL . "assets/js/ts-web{$suffix}.js",
			array(),
			TRACKSURE_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		// Pass configuration to script using centralized schema.
		$config = TrackSure_Settings_Schema::get_js_config();

		// Inline lightweight registry to eliminate the extra /registry HTTP request.
		// Only event names + required_params are needed for client-side validation
		// (full definitions are ~60 KB and NOT needed in the browser).
		$config['registry'] = $this->get_inline_registry();

		wp_localize_script(
			'ts-web',
			'trackSureConfig',
			$config
		);

		// 2. Enqueue currency handler + minicart only when ecommerce is active.
		// No need to load 33 KB of JS on non-ecommerce sites.
		if ($this->is_ecommerce_active()) {
			wp_enqueue_script(
				'ts-currency',
				TRACKSURE_PLUGIN_URL . "assets/js/ts-currency{$suffix}.js",
				array(),
				TRACKSURE_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);

			wp_enqueue_script(
				'ts-minicart',
				TRACKSURE_PLUGIN_URL . "assets/js/ts-minicart{$suffix}.js",
				array('ts-web', 'ts-currency'),
				TRACKSURE_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}

		// 3. Enqueue consent change listeners (depends on ts-web for config).
		wp_enqueue_script(
			'ts-consent-listeners',
			TRACKSURE_PLUGIN_URL . "assets/js/consent-listeners{$suffix}.js",
			array('ts-web'),
			TRACKSURE_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		// 4. Enqueue goals tracking only when active goals exist.
		$active_goals = $this->get_active_goals();
		if (! empty($active_goals)) {
			wp_enqueue_script(
				'ts-goal-constants',
				TRACKSURE_PLUGIN_URL . "admin/tracksure-goal-constants{$suffix}.js",
				array(),
				TRACKSURE_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);

			wp_enqueue_script(
				'ts-goals',
				TRACKSURE_PLUGIN_URL . "admin/tracking-goals{$suffix}.js",
				array('ts-goal-constants', 'ts-web'),
				TRACKSURE_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);

			wp_localize_script(
				'ts-goals',
				'tracksure_goals',
				$active_goals
			);
		}
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
					$decoded_conditions = json_decode($goal['conditions'], true);
					$goal['conditions'] = ! empty($decoded_conditions) ? $decoded_conditions : array();
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
	 * Build a lightweight inline registry for the browser SDK.
	 *
	 * Returns only event names and their required_params — enough for
	 * client-side validation without the full ~60 KB of definitions.
	 * Cached for 1 hour (registry changes are rare).
	 *
	 * @return array { events: { event_name => { required_params: [...] } }, version: string }
	 */
	private function get_inline_registry()
	{
		$cache_key = 'tracksure_inline_registry';
		$cached    = get_transient($cache_key);

		if ($cached !== false && is_array($cached)) {
			return $cached;
		}

		$registry  = TrackSure_Registry::get_instance();
		$events    = $registry->get_events();
		$event_map = array();

		foreach ($events as $event) {
			$name = $event['name'] ?? '';
			if (empty($name)) {
				continue;
			}
			$entry = array();
			if (! empty($event['required_params'])) {
				$entry['required_params'] = $event['required_params'];
			}
			$event_map[ $name ] = $entry;
		}

		$result = array(
			'events'  => $event_map,
			'version' => TRACKSURE_VERSION,
		);

		set_transient($cache_key, $result, HOUR_IN_SECONDS);

		return $result;
	}

	/**
	 * Check if any supported ecommerce plugin is active.
	 *
	 * Prevents loading ~33 KB of minicart + currency JS on non-ecommerce sites.
	 *
	 * @return bool
	 */
	private function is_ecommerce_active()
	{
		return class_exists('WooCommerce') ||
			class_exists('Easy_Digital_Downloads') ||
			class_exists('SureCart') || defined('SURECART_PLUGIN_FILE') ||
			class_exists('FluentCart\\App\\App') ||
			class_exists('Cartflows_Loader') || class_exists('FunnelKit_Funnel_Builder_Loader');
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
