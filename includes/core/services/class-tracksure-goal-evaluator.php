<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Debug logging and direct DB queries intentionally used for goal evaluation diagnostics

/**
 * TrackSure Goal Evaluator.
 *
 * Production-ready service for evaluating events against active goals
 * and triggering conversions with comprehensive attribution tracking.
 *
 * Architecture:
 * - Called automatically by Event Recorder after each event is stored.
 * - Uses event_name index for O(1) goal lookup instead of scanning all goals.
 * - Two-level caching: in-memory (per-request) + transient (5-min TTL).
 * - Session-level conversion dedup prevents duplicate goal fires.
 *
 * Features:
 * - Event-to-goal matching with condition evaluation
 * - Multiple trigger types (pageview, click, form, scroll, time, engagement)
 * - CSS selector matching for click triggers
 * - Conversion deduplication (event-level + session-level)
 * - First-touch and last-touch attribution
 * - Dynamic and fixed conversion values
 * - Extensible via WordPress filters
 * - N+1 query prevention
 *
 * Direct database queries required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics.
 * All queries use $wpdb->prepare() for security.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 *
 * @package TrackSure\Core\Services
 * @since   1.0.0
 * @version 2.2.0 - Event-name indexing, session dedup, regex normalization
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Goal Evaluator class.
 *
 * Singleton service for event-to-goal evaluation and conversion tracking.
 * Uses an event_name-indexed Map for O(1) goal lookup per event.
 *
 * @since 1.0.0
 */
class TrackSure_Goal_Evaluator {




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
	 * Cached active goals (flat array).
	 *
	 * In-memory cache for active goals to avoid repeated database queries
	 * within the same request. Separate from transient cache.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $active_goals = null;

	/**
	 * Goals indexed by event_name for O(1) lookup.
	 *
	 * Built from $active_goals in get_goals_for_event().
	 * Key = event_name, Value = array of goal objects.
	 *
	 * @since 2.2.0
	 * @var array<string, array>|null
	 */
	private $goals_by_event = null;

	/**
	 * Session-level conversion dedup set.
	 *
	 * Tracks goal_id + session_id pairs already converted in this request
	 * to prevent the same goal from converting twice in the same session
	 * when frequency is 'once' or 'session'.
	 *
	 * @since 2.2.0
	 * @var array<string, true>
	 */
	private $session_conversions = array();

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return TrackSure_Goal_Evaluator Singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
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
	private function __construct() {
		$this->db = TrackSure_DB::get_instance();
	}

	/**
	 * Evaluate an event against all active goals.
	 *
	 * Uses event_name-indexed lookup for O(1) goal retrieval instead of
	 * scanning all active goals. Only goals matching the event_name are
	 * evaluated for trigger match and conditions.
	 *
	 * Session-level dedup prevents the same goal from converting twice
	 * in the same session when frequency is 'once' or 'session'.
	 *
	 * @since 1.0.0
	 * @since 2.2.0 O(1) event_name lookup, session dedup
	 *
	 * @param string $event_id   Event ID (UUID).
	 * @param array  $event_data Event data array with 'event_name' and 'event_params'.
	 * @param array  $session    Session data array with 'session_id' and 'visitor_id'.
	 * @return bool True if any goal was triggered, false otherwise.
	 */
	public function evaluate_event( string $event_id, array $event_data, array $session ): bool {
		$event_name = isset( $event_data['event_name'] ) ? sanitize_text_field( $event_data['event_name'] ) : '';

		if ( empty( $event_name ) ) {
			return false;
		}

		// O(1) lookup: only get goals that match this event_name.
		$matching_goals = $this->get_goals_for_event( $event_name );

		if ( empty( $matching_goals ) ) {
			return false;
		}

		$event_params = isset( $event_data['event_params'] ) ? $event_data['event_params'] : array();

		// Decode JSON string if needed.
		if ( is_string( $event_params ) ) {
			$event_params = ! empty( $event_params ) ? json_decode( $event_params, true ) : array();
		}

		if ( ! is_array( $event_params ) ) {
			$event_params = array();
		}

		// Merge top-level page fields into event_params so condition evaluation
		// can resolve page_url, page_path, page_title regardless of whether
		// they were stored inside the event_params JSON or as separate columns.
		$page_fields = array( 'page_url', 'page_path', 'page_title' );
		foreach ( $page_fields as $field ) {
			if ( ! isset( $event_params[ $field ] ) && isset( $event_data[ $field ] ) ) {
				$event_params[ $field ] = $event_data[ $field ];
			}
		}

		$session_id = isset( $session['session_id'] ) ? $session['session_id'] : '';
		$triggered  = false;

		foreach ( $matching_goals as $goal ) {
			// Session-level dedup: skip if this goal+session already converted.
			$dedup_key = $goal->goal_id . '_' . $session_id;
			if ( isset( $this->session_conversions[ $dedup_key ] ) ) {
				continue;
			}

			// Evaluate trigger type match.
			if ( ! $this->evaluate_trigger_match( $goal, $event_data, $event_params ) ) {
				continue;
			}

			// Evaluate conditions.
			if ( $this->evaluate_conditions( $goal, $event_params ) ) {
				$this->trigger_conversion( $event_id, $goal, $event_data, $session );
				$this->session_conversions[ $dedup_key ] = true;
				$triggered                               = true;
			}
		}

		return $triggered;
	}

	/**
	 * Get goals matching a specific event_name. O(1) indexed lookup.
	 *
	 * Builds the event_name index on first call (lazy initialization),
	 * then returns the matching goals array directly.
	 *
	 * @since 2.2.0
	 *
	 * @param string $event_name The event name to look up.
	 * @return array Array of goal objects matching the event_name.
	 */
	private function get_goals_for_event( string $event_name ): array {
		// Build index if not yet done.
		if ( null === $this->goals_by_event ) {
			$all_goals            = $this->get_active_goals();
			$this->goals_by_event = array();

			foreach ( $all_goals as $goal ) {
				$key = $goal->event_name;
				if ( ! isset( $this->goals_by_event[ $key ] ) ) {
					$this->goals_by_event[ $key ] = array();
				}
				$this->goals_by_event[ $key ][] = $goal;
			}
		}

		return isset( $this->goals_by_event[ $event_name ] ) ? $this->goals_by_event[ $event_name ] : array();
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
	private function get_active_goals(): array {
		// Check in-memory cache first (fastest).
		if ( null !== $this->active_goals ) {
			return $this->active_goals;
		}

		// Check transient cache (5 minute TTL).
		$cache_key = 'tracksure_active_goals_server';
		$cached    = get_transient( $cache_key );

		if ( $cached !== false && is_array( $cached ) ) {
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
                trigger_config,
                value_type,
                fixed_value,
                frequency,
                cooldown_minutes,
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
		$query = apply_filters( 'tracksure_active_goals_query', $base_query );

		// Query active goals (explicit columns for performance).
		$this->active_goals = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query; filter may modify.

		if ( ! is_array( $this->active_goals ) ) {
			$this->active_goals = array();
		}

		// Parse JSON columns.
		foreach ( $this->active_goals as $goal ) {
			if ( ! empty( $goal->conditions ) ) {
				$goal->conditions = json_decode( $goal->conditions, true );
			} else {
				$goal->conditions = array();
			}

			// match_logic is a plain string ('all' or 'any'), NOT JSON.
			if ( empty( $goal->match_logic ) || ! in_array( $goal->match_logic, array( 'all', 'any' ), true ) ) {
				$goal->match_logic = 'all';
			}

			// Decode trigger_config JSON.
			if ( ! empty( $goal->trigger_config ) ) {
				$goal->trigger_config = json_decode( $goal->trigger_config );
			} else {
				$goal->trigger_config = null;
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
		$this->active_goals = apply_filters( 'tracksure_active_goals', $this->active_goals );

		// Cache for 5 minutes.
		set_transient( $cache_key, $this->active_goals, 5 * MINUTE_IN_SECONDS );

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
	 * - video_play: Matches by video URL, title, or type from trigger_config
	 * - download: Matches by file type, name, or URL from trigger_config
	 * - outbound_link: Matches by link domain or URL from trigger_config
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
	private function evaluate_trigger_match( object $goal, array $event_data, array $event_params ): bool {
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

		if ( $custom_match !== null ) {
			return (bool) $custom_match;
		}

		// If no trigger_type set, default behavior (always match).
		if ( empty( $goal->trigger_type ) || $goal->trigger_type === 'pageview' ) {
			return true;
		}

		// trigger_config is already decoded in get_active_goals().
		$trigger_config = $goal->trigger_config;

		$page_url  = isset( $event_params['page_url'] ) ? sanitize_url( $event_params['page_url'] ) : '';
		$page_path = isset( $event_params['page_path'] ) ? sanitize_text_field( $event_params['page_path'] ) : '';

		// Evaluate based on trigger type.
		switch ( $goal->trigger_type ) {
			case 'click':
				$css_selector = isset( $trigger_config->css_selector )
					? $trigger_config->css_selector
					: null;

				if ( $css_selector && ! empty( $event_params['element_selector'] ) ) {
					return $this->match_css_selector( $event_params['element_selector'], $css_selector );
				}

				$element_id = isset( $trigger_config->element_id )
					? $trigger_config->element_id
					: null;

				if ( $element_id && ! empty( $event_params['element_id'] ) ) {
					return $event_params['element_id'] === $element_id;
				}
				return true; // No selector specified, match all clicks

			case 'form_submit':
				$form_id = isset( $trigger_config->form_id )
					? $trigger_config->form_id
					: null;

				if ( $form_id && ! empty( $event_params['form_id'] ) ) {
					return $event_params['form_id'] === $form_id;
				}
				return true; // No form ID specified, match all form submits

			case 'scroll_depth':
			case 'scroll': // Backward compatibility
				$depth_threshold = isset( $trigger_config->scroll_depth )
					? (int) $trigger_config->scroll_depth
					: 75;

				if ( ! empty( $event_params['scroll_depth'] ) ) {
					return (int) $event_params['scroll_depth'] >= $depth_threshold;
				}
				return false; // No scroll data, can't evaluate

			case 'time_on_page':
				$time_threshold = isset( $trigger_config->time_seconds )
					? (int) $trigger_config->time_seconds
					: 30;

				if ( ! empty( $event_params['time_on_page'] ) ) {
					return (int) $event_params['time_on_page'] >= $time_threshold;
				}
				return false; // No time data, can't evaluate

			case 'engagement':
				// Requires both scroll and time thresholds to be met.
				$scroll_threshold = isset( $trigger_config->scroll_depth )
					? (int) $trigger_config->scroll_depth
					: 50; // Default 50% scroll
				$time_threshold   = isset( $trigger_config->time_seconds )
					? (int) $trigger_config->time_seconds
					: 30; // Default 30 seconds

				$scroll_met = ! empty( $event_params['scroll_depth'] ) &&
					(int) $event_params['scroll_depth'] >= $scroll_threshold;
				$time_met   = ! empty( $event_params['time_on_page'] ) &&
					(int) $event_params['time_on_page'] >= $time_threshold;

				return $scroll_met && $time_met;

			case 'custom_event':
				// Custom events always match if event_name matches.
				return true;

			case 'video_play':
				// Match video URL or title from trigger_config against event params.
				if ( ! empty( $trigger_config->video_url ) && ! empty( $event_params['video_url'] ) ) {
					if ( stripos( $event_params['video_url'], $trigger_config->video_url ) === false ) {
						return false;
					}
				}
				if ( ! empty( $trigger_config->video_title ) && ! empty( $event_params['video_title'] ) ) {
					if ( stripos( $event_params['video_title'], $trigger_config->video_title ) === false ) {
						return false;
					}
				}
				if ( ! empty( $trigger_config->video_type ) && ! empty( $event_params['video_type'] ) ) {
					if ( strtolower( $event_params['video_type'] ) !== strtolower( $trigger_config->video_type ) ) {
						return false;
					}
				}
				return true;

			case 'download':
				// Match file type or file name from trigger_config against event params.
				if ( ! empty( $trigger_config->file_type ) && ! empty( $event_params['file_type'] ) ) {
					if ( strtolower( $event_params['file_type'] ) !== strtolower( $trigger_config->file_type ) ) {
						return false;
					}
				}
				if ( ! empty( $trigger_config->file_name ) && ! empty( $event_params['file_name'] ) ) {
					if ( stripos( $event_params['file_name'], $trigger_config->file_name ) === false ) {
						return false;
					}
				}
				if ( ! empty( $trigger_config->link_url ) && ! empty( $event_params['link_url'] ) ) {
					if ( stripos( $event_params['link_url'], $trigger_config->link_url ) === false ) {
						return false;
					}
				}
				return true;

			case 'outbound_link':
				// Match link domain or URL from trigger_config against event params.
				if ( ! empty( $trigger_config->link_domain ) && ! empty( $event_params['link_domain'] ) ) {
					if ( stripos( $event_params['link_domain'], $trigger_config->link_domain ) === false ) {
						return false;
					}
				}
				if ( ! empty( $trigger_config->link_url ) && ! empty( $event_params['link_url'] ) ) {
					if ( stripos( $event_params['link_url'], $trigger_config->link_url ) === false ) {
						return false;
					}
				}
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
				$custom_result = apply_filters( 'tracksure_goal_custom_trigger_eval', null, $goal, $event_data, $event_params );
				if ( $custom_result !== null ) {
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
	private function match_css_selector( $actual_selector, $pattern ) {
		if ( empty( $actual_selector ) || empty( $pattern ) ) {
			return false;
		}

		// Exact match.
		if ( $actual_selector === $pattern ) {
			return true;
		}

		// ID selector: #contact-form.
		if ( strpos( $pattern, '#' ) === 0 ) {
			$pattern_id = substr( $pattern, 1 );
			// Check if actual selector contains this ID.
			return strpos( $actual_selector, '#' . $pattern_id ) !== false;
		}

		// Class selector: .btn or .btn.primary.
		if ( strpos( $pattern, '.' ) === 0 ) {
			// Extract all classes from pattern.
			$pattern_classes = array_filter( explode( '.', $pattern ) );
			foreach ( $pattern_classes as $class ) {
				if ( ! empty( $class ) && strpos( $actual_selector, '.' . $class ) === false ) {
					return false; // All classes must match
				}
			}
			return true;
		}

		// Attribute selector: a[href^="tel:"], button[type="submit"].
		if ( preg_match( '/^([a-z]+)\[([^\]]+)\]$/i', $pattern, $matches ) ) {
			$tag  = $matches[1]; // e.g., 'a'
			$attr = $matches[2]; // e.g., 'href^="tel:"'

			// Check if actual selector starts with this tag.
			if ( strpos( $actual_selector, $tag ) !== 0 ) {
				return false;
			}

			// Parse attribute condition.
			if ( preg_match( '/^([a-z-]+)([\^\$\*]?)="([^"]+)"$/i', $attr, $attr_matches ) ) {
				// We can't check actual attributes from CSS selector alone,.
				// but we can match against event params if provided separately.
				// For now, if tag matches, consider it a match.
				return true;
			}
		}

		// Tag selector with classes: a.phone-link, button.cta.
		if ( preg_match( '/^([a-z]+)(\.[\w.-]+)?$/i', $pattern ) ) {
			$pattern_parts = explode( '.', $pattern );
			$pattern_tag   = $pattern_parts[0];

			// Check tag
			if ( strpos( $actual_selector, $pattern_tag ) !== 0 ) {
				return false;
			}

			// Check classes if present.
			for ( $i = 1; $i < count( $pattern_parts ); $i++ ) {
				if ( strpos( $actual_selector, '.' . $pattern_parts[ $i ] ) === false ) {
					return false;
				}
			}
			return true;
		}

		// Fallback: simple contains matching (backwards compatibility).
		return strpos( $actual_selector, $pattern ) !== false;
	}

	/**
	 * Evaluate goal conditions against event parameters.
	 *
	 * @param object $goal Goal object.
	 * @param array  $event_params Event parameters.
	 * @return bool True if all conditions met.
	 */
	private function evaluate_conditions( $goal, $event_params ) {
		// No conditions = always match.
		if ( empty( $goal->conditions ) || ! is_array( $goal->conditions ) ) {
			return true;
		}

		$match_logic = isset( $goal->match_logic ) && $goal->match_logic === 'any' ? 'any' : 'all';

		foreach ( $goal->conditions as $condition ) {
			$param_key = isset( $condition['param'] ) ? $condition['param'] : '';
			$operator  = isset( $condition['operator'] ) ? $condition['operator'] : 'equals';
			$value     = isset( $condition['value'] ) ? $condition['value'] : '';

			// Get actual value from event params.
			$actual_value = isset( $event_params[ $param_key ] ) ? $event_params[ $param_key ] : null;

			// Evaluate condition.
			$result = $this->evaluate_condition_operator( $actual_value, $operator, $value );

			if ( $match_logic === 'any' && $result ) {
				return true; // At least one condition passed.
			}

			if ( $match_logic === 'all' && ! $result ) {
				return false; // All conditions must pass.
			}
		}

		// 'any' logic: none matched -> false. 'all' logic: all matched -> true.
		return $match_logic === 'all';
	}

	/**
	 * Evaluate a single condition operator.
	 *
	 * Supports: equals, not_equals, contains, not_contains, starts_with,
	 * ends_with, greater_than, less_than, greater_than_or_equal,
	 * less_than_or_equal, matches_regex.
	 *
	 * Regex patterns are auto-normalized: if the pattern lacks delimiters,
	 * '/' delimiters are added for preg_match() compatibility. This ensures
	 * JS bare patterns (e.g. "^foo.*bar$") work identically on the PHP side.
	 *
	 * @since 1.0.0
	 * @since 2.2.0 Auto-normalize regex delimiters for JS/PHP consistency
	 *
	 * @param mixed  $actual_value   Actual value from event parameters.
	 * @param string $operator       Operator name (equals, contains, regex, etc.).
	 * @param mixed  $expected_value Expected value from goal condition.
	 * @return bool True if condition is satisfied.
	 */
	private function evaluate_condition_operator( $actual_value, $operator, $expected_value ) {
		switch ( $operator ) {
			case 'equals':
				// Use string comparison for consistency with JS strict ===.
				// Numeric values are cast to strings for comparison.
				return (string) $actual_value === (string) $expected_value;

			case 'not_equals':
				return (string) $actual_value !== (string) $expected_value;

			case 'greater_than':
				return (float) $actual_value > (float) $expected_value;

			case 'less_than':
				return (float) $actual_value < (float) $expected_value;

			case 'greater_than_or_equal':
			case 'greater_or_equal': // Backward compat.
				return (float) $actual_value >= (float) $expected_value;

			case 'less_than_or_equal':
			case 'less_or_equal': // Backward compat.
				return (float) $actual_value <= (float) $expected_value;

			case 'contains':
				return strpos( (string) $actual_value, (string) $expected_value ) !== false;

			case 'not_contains':
				return strpos( (string) $actual_value, (string) $expected_value ) === false;

			case 'starts_with':
				return strpos( (string) $actual_value, (string) $expected_value ) === 0;

			case 'ends_with':
				$expected_len = strlen( (string) $expected_value );
				return $expected_len > 0 && substr( (string) $actual_value, -$expected_len ) === (string) $expected_value;

			case 'matches_regex':
			case 'regex': // Alias.
				$pattern = (string) $expected_value;
				// Auto-add delimiters if missing (JS sends bare patterns like "^foo.*$").
				if ( ! empty( $pattern ) && $pattern[0] !== '/' && $pattern[0] !== '#' && $pattern[0] !== '~' ) {
					$pattern = '/' . str_replace( '/', '\\/', $pattern ) . '/';
				}
				// Suppress warnings for invalid regex.
				return @preg_match( $pattern, (string) $actual_value ) === 1;

			default:
				/**
				 * Filter for custom condition operators.
				 *
				 * Allows Pro/3rd party extensions to add custom operators.
				 *
				 * @since 1.0.0
				 *
				 * @param bool|null $result         Null by default, return bool to handle.
				 * @param string    $operator       Operator name.
				 * @param mixed     $actual_value   Actual value from event.
				 * @param mixed     $expected_value Expected value from condition.
				 */
				$result = apply_filters( 'tracksure_goal_custom_operator', null, $operator, $actual_value, $expected_value );
				if ( $result !== null ) {
					return $result;
				}
				return false;
		}
	}

	/**
	 * Trigger a conversion for a goal.
	 *
	 * Performs event-level dedup (same event_id + goal_id pair), extracts
	 * conversion value (fixed or dynamic), and delegates to the Conversion
	 * Recorder for insert, touchpoint linking, and attribution.
	 *
	 * @since 1.0.0
	 * @since 2.2.0 Enhanced PHPDoc
	 *
	 * @param string $event_id  Event ID (UUID).
	 * @param object $goal      Goal object with value_type, fixed_value, etc.
	 * @param array  $event_data Full event data (may contain 'value', 'order_total').
	 * @param array  $session    Session data with 'visitor_id' and 'session_id'.
	 * @return void
	 */
	private function trigger_conversion( $event_id, $goal, $event_data, $session ) {
		global $wpdb;

		// ========================================.
		// PHASE 1: CONVERSION DEDUPLICATION.
		// ========================================.
		// Check if conversion already exists for this event_id + goal_id.
		// Prevents duplicate conversions from browser + server tracking!

		$existing_conversion = $this->db->get_conversion_by_event_and_goal( $event_id, $goal->goal_id );

		if ( $existing_conversion ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					'[TrackSure] Goal Evaluator: Duplicate conversion prevented - event_id=' .
						$event_id . ', goal_id=' . $goal->goal_id . ', existing_conversion_id=' . $existing_conversion
				);
			}
			return; // CRITICAL: Stop here - conversion already recorded, prevent database duplicate key error
		}

		// Extract conversion value based on value_type.
		$conversion_value = 0;
		$value_type       = isset( $goal->value_type ) ? $goal->value_type : 'none';

		switch ( $value_type ) {
			case 'fixed':
				// Use fixed_value from goal definition.
				$conversion_value = isset( $goal->fixed_value ) ? (float) $goal->fixed_value : 0;
				break;

			case 'dynamic':
				// Extract from event data (ecommerce, form, etc.).
				if ( isset( $event_data['value'] ) ) {
					$conversion_value = (float) $event_data['value'];
				} elseif ( isset( $event_data['order_total'] ) ) {
					$conversion_value = (float) $event_data['order_total'];
				} elseif ( isset( $event_data['conversion_value'] ) ) {
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

		// Build conversion data for Conversion Recorder.
		$conversion_data = array(
			'visitor_id'       => $session['visitor_id'],
			'session_id'       => $session['session_id'],
			'event_id'         => $event_id,
			'conversion_type'  => 'goal',
			'goal_id'          => $goal->goal_id,
			'conversion_value' => $conversion_value,
			'currency'         => isset( $event_data['currency'] ) ? $event_data['currency'] : 'USD',
			'converted_at'     => current_time( 'mysql', 1 ),
		);

		/**
		 * Filter conversion data before recording.
		 *
		 * Allows Pro/3rd party to modify conversion data.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $conversion_data Conversion data array.
		 * @param object $goal            Goal object.
		 * @param array  $event_data      Full event data.
		 * @param array  $session         Session data.
		 */
		$conversion_data = apply_filters( 'tracksure_goal_conversion_data', $conversion_data, $goal, $event_data, $session );

		// Use Conversion Recorder for the single source of truth.
		// It handles: insert, touchpoint linking, attribution model calculation, dedup.
		$conversion_id = 0;

		if ( class_exists( 'TrackSure_Conversion_Recorder' ) ) {
			$conversion_recorder = TrackSure_Conversion_Recorder::get_instance();
			$conversion_id       = $conversion_recorder->record_conversion( $conversion_data );

			if ( $conversion_id && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TrackSure] Goal Evaluator: Conversion + attribution recorded for goal conversion ' . $conversion_id );
			}
		}

		/**
		 * Fires when a goal conversion is triggered.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $conversion_id Conversion ID (0 if recorder unavailable).
		 * @param object $goal Goal object.
		 * @param string $event_id Event ID (UUID).
		 * @param array  $session Session data.
		 */
		do_action( 'tracksure_goal_conversion', $conversion_id, $goal, $event_id, $session );
	}

	/**
	 * Clear cached goals.
	 *
	 * Call this method after CRUD operations on goals to ensure
	 * evaluator uses fresh data.
	 *
	 * Clears in-memory cache, event_name index, and transient cache.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Added transient cache clearing
	 * @since 2.2.0 Added event_name index clearing
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->active_goals   = null;
		$this->goals_by_event = null;
		delete_transient( 'tracksure_active_goals_server' );
	}
}
