<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Debug logging and direct DB queries intentionally used for goal evaluation diagnostics

/**
 *
 * TrackSure Goal Evaluator
 *
 * Production-ready service for evaluating events against active goals
 * and triggering conversions with comprehensive attribution tracking.
 *
 * Features:
 * - Event-to-goal matching with condition evaluation
 * - Multiple trigger types (pageview, click, form, scroll, time, engagement)
 * - CSS selector matching for click triggers
 * - Conversion deduplication (prevents duplicate conversions)
 * - First-touch and last-touch attribution
 * - Dynamic and fixed conversion values
 * - Extensible via WordPress filters
 * - Optimized with transient caching (5-minute TTL)
 * - N+1 query prevention
 *
 * Called automatically by Event Recorder after each event is stored. *
 * Direct database queries required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics.
 * All queries use $wpdb->prepare() for security.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 *
 * @package TrackSure\Core\Services
 * @since 1.0.0
 * @version 2.1.0 - Enhanced PHPDoc, type hints, and optimizations
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Goal Evaluator class.
 *
 * Singleton service for event-to-goal evaluation and conversion tracking.
 *
 * @since 1.0.0
 */
class TrackSure_Goal_Evaluator
{



	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var TrackSure_Goal_Evaluator|null
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @since 1.0.0
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Cached active goals.
	 *
	 * In-memory cache for active goals to avoid repeated database queries
	 * within the same request. Separate from transient cache.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $active_goals = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return TrackSure_Goal_Evaluator Singleton instance.
	 */
	public static function get_instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Private to enforce singleton pattern.
	 *
	 * @since 1.0.0
	 */
	private function __construct()
	{
		$this->db = TrackSure_DB::get_instance();
	}

	/**
	 * Evaluate an event against all active goals.
	 *
	 * Checks if the event matches any active goal's conditions and triggers.
	 * Multiple goals can be triggered by a single event.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Enhanced type safety and documentation
	 *
	 * @param string $event_id    Event ID (UUID).
	 * @param array  $event_data  Event data array.
	 * @param array  $session     Session data array.
	 * @return bool True if any goal was triggered, false otherwise.
	 */
	public function evaluate_event(string $event_id, array $event_data, array $session): bool
	{
		$goals = $this->get_active_goals();

		if (empty($goals)) {
			return false;
		}

		$event_name   = isset($event_data['event_name']) ? sanitize_text_field($event_data['event_name']) : '';
		$event_params = isset($event_data['event_params']) ? $event_data['event_params'] : array();

		// Decode if string.
		if (is_string($event_params)) {
			$event_params = ! empty($event_params) ? json_decode($event_params, true) : array();
		}

		if (! is_array($event_params)) {
			$event_params = array();
		}

		$triggered = false;

		foreach ($goals as $goal) {
			// Check if event name matches.
			if ($goal->event_name !== $event_name) {
				continue;
			}

			// Evaluate trigger type and match logic.
			if (! $this->evaluate_trigger_match($goal, $event_data, $event_params)) {
				continue;
			}

			// Evaluate conditions.
			if ($this->evaluate_conditions($goal, $event_params)) {
				$this->trigger_conversion($event_id, $goal, $event_data, $session);
				$triggered = true;
			}
		}

		return $triggered;
	}

	/**
	 * Get all active goals with caching.
	 *
	 * Uses two-level caching strategy:
	 * 1. In-memory cache (fastest, per-request)
	 * 2. Transient cache (5-minute TTL, cross-request)
	 *
	 * Optimized query selects only necessary columns for performance.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Enhanced caching documentation
	 *
	 * @return array Array of goal objects with decoded JSON fields.
	 */
	private function get_active_goals(): array
	{
		// Check in-memory cache first (fastest).
		if (null !== $this->active_goals) {
			return $this->active_goals;
		}

		// Check transient cache (5 minute TTL).
		$cache_key = 'tracksure_active_goals_server';
		$cached    = get_transient($cache_key);

		if ($cached !== false && is_array($cached)) {
			$this->active_goals = $cached;
			return $this->active_goals;
		}

		global $wpdb;

		// Static query — no user input, only $wpdb->prefix (WordPress-controlled).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; table name uses $wpdb->prefix.
		$base_query = "SELECT 
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
            WHERE is_active = 1 
            ORDER BY goal_id ASC";

		/**
		 * Filter active goals SQL query.
		 *
		 * Allows Pro/3rd party extensions to modify the query string
		 * (e.g., add WHERE conditions, JOIN tables, change ordering).
		 * Callbacks MUST return properly prepared/escaped SQL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $query SQL query string (no user input).
		 */
		$query = apply_filters('tracksure_active_goals_query', $base_query);

		// Query active goals (explicit columns for performance).
		$this->active_goals = $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query; filter may modify.

		if (! is_array($this->active_goals)) {
			$this->active_goals = array();
		}

		// Parse JSON columns.
		foreach ($this->active_goals as $goal) {
			if (! empty($goal->conditions)) {
				$goal->conditions = json_decode($goal->conditions, true);
			} else {
				$goal->conditions = array();
			}

			if (! empty($goal->match_logic)) {
				$goal->match_logic = json_decode($goal->match_logic, true);
			} else {
				$goal->match_logic = array();
			}
		}

		/**
		 * Filter active goals after database retrieval.
		 *
		 * Allows Pro/3rd party extensions to modify goals
		 * (e.g., add custom trigger types, validation rules, computed properties).
		 *
		 * @since 1.0.0
		 *
		 * @param array $goals Array of goal objects.
		 */
		$this->active_goals = apply_filters('tracksure_active_goals', $this->active_goals);

		// Cache for 5 minutes.
		set_transient($cache_key, $this->active_goals, 5 * MINUTE_IN_SECONDS);

		return $this->active_goals;
	}

	/**
	 * Evaluate trigger type and match logic for custom goals.
	 *
	 * Handles various trigger types:
	 * - pageview: Always matches (default)
	 * - click: Matches by CSS selector or element ID
	 * - form_submit: Matches by form ID
	 * - scroll_depth: Matches when scroll % >= threshold
	 * - time_on_page: Matches when time >= threshold (seconds)
	 * - engagement: Requires both scroll AND time thresholds
	 * - custom_event: Always matches if event_name matches
	 *
	 * Extensible via 'tracksure_evaluate_custom_trigger' filter.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Enhanced PHPDoc and type safety
	 *
	 * @param object $goal         Goal object with trigger configuration.
	 * @param array  $event_data   Full event data array.
	 * @param array  $event_params Event parameters array.
	 * @return bool True if trigger conditions are met, false otherwise.
	 */
	private function evaluate_trigger_match(object $goal, array $event_data, array $event_params): bool
	{
		/**
		 * Filter for custom trigger type evaluation.
		 *
		 * Allows Pro/3rd party extensions to handle custom trigger types
		 * before default logic runs.
		 *
		 * @since 1.0.0
		 *
		 * @param bool|null $custom_match  Null by default, return bool to override.
		 * @param object    $goal          Goal object.
		 * @param array     $event_data    Full event data.
		 * @param array     $event_params  Event parameters.
		 */
		$custom_match = apply_filters(
			'tracksure_evaluate_custom_trigger',
			null,
			$goal,
			$event_data,
			$event_params
		);

		if ($custom_match !== null) {
			return (bool) $custom_match;
		}

		// If no trigger_type set, default behavior (always match).
		if (empty($goal->trigger_type) || $goal->trigger_type === 'pageview') {
			return true;
		}

		// Parse match_logic if exists.
		$match_logic = array();
		if (! empty($goal->match_logic)) {
			if (is_string($goal->match_logic)) {
				$match_logic = json_decode($goal->match_logic, true);
			} else {
				$match_logic = $goal->match_logic;
			}
		}

		if (! is_array($match_logic)) {
			$match_logic = array();
		}

		$page_url  = isset($event_params['page_url']) ? sanitize_url($event_params['page_url']) : '';
		$page_path = isset($event_params['page_path']) ? sanitize_text_field($event_params['page_path']) : '';

		// Evaluate based on trigger type.
		switch ($goal->trigger_type) {
			case 'click':
				// Check trigger_config first, fallback to match_logic for backward compat.
				$css_selector = isset($goal->trigger_config->css_selector)
					? $goal->trigger_config->css_selector
					: (isset($match_logic['css_selector']) ? $match_logic['css_selector'] : null);

				if ($css_selector && ! empty($event_params['element_selector'])) {
					return $this->match_css_selector($event_params['element_selector'], $css_selector);
				}

				$element_id = isset($goal->trigger_config->element_id)
					? $goal->trigger_config->element_id
					: (isset($match_logic['element_id']) ? $match_logic['element_id'] : null);

				if ($element_id && ! empty($event_params['element_id'])) {
					return $event_params['element_id'] === $element_id;
				}
				return true; // No selector specified, match all clicks

			case 'form_submit':
				// Check trigger_config first, fallback to match_logic.
				$form_id = isset($goal->trigger_config->form_id)
					? $goal->trigger_config->form_id
					: (isset($match_logic['form_id']) ? $match_logic['form_id'] : null);

				if ($form_id && ! empty($event_params['form_id'])) {
					return $event_params['form_id'] === $form_id;
				}
				return true; // No form ID specified, match all form submits

			case 'scroll_depth':
			case 'scroll': // Backward compatibility
				// Check trigger_config first, fallback to match_logic.
				$depth_threshold = isset($goal->trigger_config->scroll_depth)
					? (int) $goal->trigger_config->scroll_depth
					: (isset($match_logic['depth_threshold']) ? (int) $match_logic['depth_threshold'] : 75);

				if (! empty($event_params['scroll_depth'])) {
					return (int) $event_params['scroll_depth'] >= $depth_threshold;
				}
				return false; // No scroll data, can't evaluate

			case 'time_on_page':
				// Check trigger_config first, fallback to match_logic.
				$time_threshold = isset($goal->trigger_config->time_seconds)
					? (int) $goal->trigger_config->time_seconds
					: (isset($match_logic['time_threshold']) ? (int) $match_logic['time_threshold'] : 30);

				if (! empty($event_params['time_on_page'])) {
					return (int) $event_params['time_on_page'] >= $time_threshold;
				}
				return false; // No time data, can't evaluate

			case 'engagement':
				// Requires both scroll and time thresholds to be met.
				$scroll_threshold = isset($goal->trigger_config->scroll_depth)
					? (int) $goal->trigger_config->scroll_depth
					: 50; // Default 50% scroll
				$time_threshold   = isset($goal->trigger_config->time_seconds)
					? (int) $goal->trigger_config->time_seconds
					: 30; // Default 30 seconds

				$scroll_met = ! empty($event_params['scroll_depth']) &&
					(int) $event_params['scroll_depth'] >= $scroll_threshold;
				$time_met   = ! empty($event_params['time_on_page']) &&
					(int) $event_params['time_on_page'] >= $time_threshold;

				return $scroll_met && $time_met;

			case 'custom_event':
				// Custom events always match if event_name matches.
				return true;

			default:
				/**
				 * Filter for unknown trigger types.
				 *
				 * @since 1.0.0
				 *
				 * @param bool|null $custom_result  Null by default, return bool to handle.
				 * @param object    $goal           Goal object.
				 * @param array     $event_data     Full event data.
				 * @param array     $event_params   Event parameters.
				 */
				$custom_result = apply_filters('tracksure_goal_custom_trigger_eval', null, $goal, $event_data, $event_params);
				if ($custom_result !== null) {
					return (bool) $custom_result;
				}
				return true; // Unknown trigger, match by event_name only
		}
	}

	/**
	 * Match CSS selector (improved matching for goal patterns).
	 *
	 * @param string $actual_selector Actual element selector from event.
	 * @param string $pattern Expected pattern from goal.
	 * @return bool True if matches.
	 */
	private function match_css_selector($actual_selector, $pattern)
	{
		if (empty($actual_selector) || empty($pattern)) {
			return false;
		}

		// Exact match.
		if ($actual_selector === $pattern) {
			return true;
		}

		// ID selector: #contact-form.
		if (strpos($pattern, '#') === 0) {
			$pattern_id = substr($pattern, 1);
			// Check if actual selector contains this ID.
			return strpos($actual_selector, '#' . $pattern_id) !== false;
		}

		// Class selector: .btn or .btn.primary.
		if (strpos($pattern, '.') === 0) {
			// Extract all classes from pattern.
			$pattern_classes = array_filter(explode('.', $pattern));
			foreach ($pattern_classes as $class) {
				if (! empty($class) && strpos($actual_selector, '.' . $class) === false) {
					return false; // All classes must match
				}
			}
			return true;
		}

		// Attribute selector: a[href^="tel:"], button[type="submit"].
		if (preg_match('/^([a-z]+)\[([^\]]+)\]$/i', $pattern, $matches)) {
			$tag  = $matches[1]; // e.g., 'a'
			$attr = $matches[2]; // e.g., 'href^="tel:"'

			// Check if actual selector starts with this tag.
			if (strpos($actual_selector, $tag) !== 0) {
				return false;
			}

			// Parse attribute condition.
			if (preg_match('/^([a-z-]+)([\^\$\*]?)="([^"]+)"$/i', $attr, $attr_matches)) {
				// We can't check actual attributes from CSS selector alone,.
				// but we can match against event params if provided separately.
				// For now, if tag matches, consider it a match.
				return true;
			}
		}

		// Tag selector with classes: a.phone-link, button.cta.
		if (preg_match('/^([a-z]+)(\.[\w.-]+)?$/i', $pattern)) {
			$pattern_parts = explode('.', $pattern);
			$pattern_tag   = $pattern_parts[0];

			// Check tag
			if (strpos($actual_selector, $pattern_tag) !== 0) {
				return false;
			}

			// Check classes if present.
			for ($i = 1; $i < count($pattern_parts); $i++) {
				if (strpos($actual_selector, '.' . $pattern_parts[$i]) === false) {
					return false;
				}
			}
			return true;
		}

		// Fallback: simple contains matching (backwards compatibility).
		return strpos($actual_selector, $pattern) !== false;
	}

	/**
	 * Evaluate goal conditions against event parameters.
	 *
	 * @param object $goal Goal object.
	 * @param array  $event_params Event parameters.
	 * @return bool True if all conditions met.
	 */
	private function evaluate_conditions($goal, $event_params)
	{
		// No conditions = always match.
		if (empty($goal->conditions) || ! is_array($goal->conditions)) {
			return true;
		}

		foreach ($goal->conditions as $condition) {
			$param_key = isset($condition['param']) ? $condition['param'] : '';
			$operator  = isset($condition['operator']) ? $condition['operator'] : 'equals';
			$value     = isset($condition['value']) ? $condition['value'] : '';

			// Get actual value from event params.
			$actual_value = isset($event_params[$param_key]) ? $event_params[$param_key] : null;

			// Evaluate condition.
			$result = $this->evaluate_condition_operator($actual_value, $operator, $value);

			if (! $result) {
				return false; // All conditions must pass
			}
		}

		return true;
	}

	/**
	 * Evaluate a single condition operator.
	 * âœ… FIXED: Added missing operators (starts_with, ends_with, matches_regex)
	 *
	 * @param mixed  $actual_value Actual value from event.
	 * @param string $operator Operator (equals, contains, starts_with, ends_with, regex, etc.).
	 * @param mixed  $expected_value Expected value from condition.
	 * @return bool True if condition met.
	 */
	private function evaluate_condition_operator($actual_value, $operator, $expected_value)
	{
		switch ($operator) {
			case 'equals':
				return $actual_value == $expected_value;

			case 'not_equals':
				return $actual_value != $expected_value;

			case 'greater_than':
				return (float) $actual_value > (float) $expected_value;

			case 'less_than':
				return (float) $actual_value < (float) $expected_value;

			case 'greater_than_or_equal': // âœ… FIXED: Standardized name
			case 'greater_or_equal': // Backward compat
				return (float) $actual_value >= (float) $expected_value;

			case 'less_than_or_equal': // âœ… FIXED: Standardized name
			case 'less_or_equal': // Backward compat
				return (float) $actual_value <= (float) $expected_value;

			case 'contains':
				return strpos((string) $actual_value, (string) $expected_value) !== false;

			case 'not_contains':
				return strpos((string) $actual_value, (string) $expected_value) === false;

			case 'starts_with': // âœ… ADDED: Missing operator
				return strpos((string) $actual_value, (string) $expected_value) === 0;

			case 'ends_with': // âœ… ADDED: Missing operator
				$expected_len = strlen((string) $expected_value);
				return substr((string) $actual_value, -$expected_len) === (string) $expected_value;

			case 'matches_regex': // âœ… ADDED: Pro feature operator
			case 'regex': // Alias
				// Suppress warnings for invalid regex.
				return @preg_match($expected_value, (string) $actual_value) === 1;

			default:
				// Allow Pro/3rd party to add custom operators.
				$result = apply_filters('tracksure_goal_custom_operator', null, $operator, $actual_value, $expected_value);
				if ($result !== null) {
					return $result;
				}
				return false;
		}
	}

	/**
	 * Trigger a conversion for a goal.
	 *
	 * @param string $event_id Event ID (UUID).
	 * @param object $goal Goal object.
	 * @param array  $event_data Event data.
	 * @param array  $session Session data.
	 */
	private function trigger_conversion($event_id, $goal, $event_data, $session)
	{
		global $wpdb;

		// ========================================.
		// PHASE 1: CONVERSION DEDUPLICATION.
		// ========================================.
		// Check if conversion already exists for this event_id + goal_id.
		// Prevents duplicate conversions from browser + server tracking!

		$existing_conversion = $this->db->get_conversion_by_event_and_goal($event_id, $goal->goal_id);

		if ($existing_conversion) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				if (defined('WP_DEBUG') && WP_DEBUG) {

					error_log(
						'[TrackSure] Goal Evaluator: Duplicate conversion prevented - event_id=' .
							$event_id . ', goal_id=' . $goal->goal_id . ', existing_conversion_id=' . $existing_conversion
					);
				}
			}
			return; // CRITICAL: Stop here - conversion already recorded, prevent database duplicate key error
		}

		// Extract conversion value based on value_type.
		$conversion_value = 0;
		$value_type       = isset($goal->value_type) ? $goal->value_type : 'none';

		switch ($value_type) {
			case 'fixed':
				// Use fixed_value from goal definition.
				$conversion_value = isset($goal->fixed_value) ? (float) $goal->fixed_value : 0;
				break;

			case 'dynamic':
				// Extract from event data (ecommerce, form, etc.).
				if (isset($event_data['value'])) {
					$conversion_value = (float) $event_data['value'];
				} elseif (isset($event_data['order_total'])) {
					$conversion_value = (float) $event_data['order_total'];
				} elseif (isset($event_data['conversion_value'])) {
					$conversion_value = (float) $event_data['conversion_value'];
				}
				break;

			case 'none':
			default:
				// No value tracking.
				$conversion_value = 0;
				break;
		}

		// Allow Pro/3rd party to modify conversion value (e.g., apply multipliers, discounts).
		$conversion_value = apply_filters(
			'tracksure_goal_conversion_value',
			$conversion_value,
			$goal,
			$event_data,
			$session
		);

		// Get first and last touch attribution.
		$first_touch = $this->get_first_touch($session['visitor_id']);
		$last_touch  = $this->get_last_touch($session['session_id']);

		// Insert conversion record with full attribution.
		$data = array(
			'goal_id'              => $goal->goal_id,
			'session_id'           => $session['session_id'],
			'visitor_id'           => $session['visitor_id'],
			'event_id'             => $event_id,
			'conversion_type'      => 'goal',
			'conversion_value'     => $conversion_value,
			'currency'             => isset($event_data['currency']) ? $event_data['currency'] : 'USD',
			'converted_at'         => current_time('mysql'),

			// Attribution data (matching technical guide schema).
			'first_touch_source'   => isset($first_touch['source']) ? $first_touch['source'] : null,
			'first_touch_medium'   => isset($first_touch['medium']) ? $first_touch['medium'] : null,
			'first_touch_campaign' => isset($first_touch['campaign']) ? $first_touch['campaign'] : null,
			'last_touch_source'    => isset($last_touch['source']) ? $last_touch['source'] : null,
			'last_touch_medium'    => isset($last_touch['medium']) ? $last_touch['medium'] : null,
			'last_touch_campaign'  => isset($last_touch['campaign']) ? $last_touch['campaign'] : null,
		);

		// Allow Pro/3rd party to modify conversion data (e.g., add custom fields, override values).
		$data = apply_filters('tracksure_goal_conversion_data', $data, $goal, $event_data, $session);

		// Use Conversion Recorder for the single source of truth.
		// It handles: insert, touchpoint linking, attribution model calculation, dedup.
		// Do NOT also do a direct $wpdb->insert() â€” that causes double counting.
		if (class_exists('TrackSure_Conversion_Recorder')) {
			$conversion_recorder = TrackSure_Conversion_Recorder::get_instance();
			$conversion_id       = $conversion_recorder->record_conversion(
				array(
					'visitor_id'       => $session['visitor_id'],
					'session_id'       => $session['session_id'],
					'event_id'         => $event_id,
					'conversion_type'  => 'goal',
					'goal_id'          => $goal->goal_id,
					'conversion_value' => $conversion_value,
					'currency'         => isset($event_data['currency']) ? $event_data['currency'] : 'USD',
					'converted_at'     => current_time('mysql', 1),
				)
			);

			if ($conversion_id && defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[TrackSure] Goal Evaluator: Conversion + attribution recorded for goal conversion ' . $conversion_id);
			}
		}

		/**
		 * Fires when a goal conversion is triggered.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $conversion_id Conversion ID.
		 * @param object $goal Goal object.
		 * @param string $event_id Event ID (UUID).
		 * @param array  $session Session data.
		 */
		do_action('tracksure_goal_conversion', $conversion_id, $goal, $event_id, $session);
	}

	/**
	 * Clear cached goals.
	 *
	 * Call this method after CRUD operations on goals to ensure
	 * evaluator uses fresh data.
	 *
	 * Clears both in-memory cache and transient cache.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Added transient cache clearing
	 *
	 * @return void
	 */
	public function clear_cache(): void
	{
		$this->active_goals = null;
		delete_transient('tracksure_active_goals_server');
	}

	/**
	 * Get first touch attribution for visitor.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return array|null First touch attribution data.
	 */
	private function get_first_touch($visitor_id)
	{
		global $wpdb;

		$first_session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT utm_source, utm_medium, utm_campaign
            FROM {$wpdb->prefix}tracksure_sessions
            WHERE visitor_id = %d
            ORDER BY started_at ASC
            LIMIT 1",
				$visitor_id
			),
			ARRAY_A
		);

		if (! $first_session) {
			return null;
		}

		return array(
			'source'   => $first_session['utm_source'] ?: '(direct)',
			'medium'   => $first_session['utm_medium'] ?: '(none)',
			'campaign' => $first_session['utm_campaign'],
		);
	}

	/**
	 * Get last touch attribution for current session.
	 *
	 * @param string $session_id Session ID.
	 * @return array|null Last touch attribution data.
	 */
	private function get_last_touch($session_id)
	{
		global $wpdb;

		$current_session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT utm_source, utm_medium, utm_campaign
            FROM {$wpdb->prefix}tracksure_sessions
            WHERE session_id = %s
            LIMIT 1",
				$session_id
			),
			ARRAY_A
		);

		if (! $current_session) {
			return null;
		}

		return array(
			'source'   => $current_session['utm_source'] ?: '(direct)',
			'medium'   => $current_session['utm_medium'] ?: '(none)',
			'campaign' => $current_session['utm_campaign'],
		);
	}
}
