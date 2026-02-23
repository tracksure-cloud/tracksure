<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Debug logging and direct DB queries required for goals management

/**
 *
 * TrackSure REST Goals Controller
 *
 * Production-ready REST API controller for goal CRUD operations with
 * comprehensive security, validation, sanitization, and i18n support.
 *
 * Security Features:
 * - WordPress REST API nonce verification (automatic for logged-in users)
 * - Capability checks (manage_options)
 * - Input validation via TrackSure_Goal_Validator
 * - Prepared statements for SQL (prevents injection)
 * - Sanitized outputs
 * - Rate limiting ready (via filters)
 *
 * Direct database queries are required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics queries.
 * All queries use $wpdb->prepare() for security.
 *
 * Table name interpolation is safe because:
 * - All table names use $wpdb->prefix (controlled by WordPress)
 * - No user input in table names
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 *
 * Endpoints:
 * - GET    /wp-json/tracksure/v1/goals              - List all goals
 * - POST   /wp-json/tracksure/v1/goals              - Create new goal
 * - PUT    /wp-json/tracksure/v1/goals/{id}         - Update goal
 * - DELETE /wp-json/tracksure/v1/goals/{id}         - Delete goal
 * - GET    /wp-json/tracksure/v1/goals/{id}/performance - Goal metrics
 * - GET    /wp-json/tracksure/v1/goals/performance  - Batch performance
 * - GET    /wp-json/tracksure/v1/goals/{id}/timeline - Conversion timeline
 * - GET    /wp-json/tracksure/v1/goals/{id}/sources - Source attribution
 *
 * @package TrackSure\Core\API
 * @since 1.0.0
 * @version 2.1.0 - Enhanced security, i18n, validation, and PHPDoc
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Goals REST controller class.
 *
 * Handles all REST API operations for the TrackSure goals system.
 *
 * @since 1.0.0
 */
class TrackSure_REST_Goals_Controller extends TrackSure_REST_Controller
{



	/**
	 * Database instance.
	 *
	 * @since 1.0.0
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * Initializes the database instance from the core service container.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$core     = TrackSure_Core::get_instance();
		$this->db = $core->get_service('db');
	}

	/**
	 * Register REST API routes.
	 *
	 * Registers all goal-related endpoints with proper permissions,
	 * validation schemas, and callback handlers.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Enhanced PHPDoc and route documentation
	 *
	 * @return void
	 */
	public function register_routes()
	{
		// GET /goals - List all goals.
		register_rest_route(
			$this->namespace,
			'/goals',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_goals'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// POST /goals - Create new goal.
		register_rest_route(
			$this->namespace,
			'/goals',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_goal'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => $this->get_goal_schema(),
			)
		);

		// PUT /goals/{id} - Update goal.
		register_rest_route(
			$this->namespace,
			'/goals/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'update_goal'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => $this->get_goal_schema(true),
			)
		);

		// DELETE /goals/{id} - Delete goal.
		register_rest_route(
			$this->namespace,
			'/goals/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_goal'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// GET /goals/{id}/performance - Get goal performance.
		register_rest_route(
			$this->namespace,
			'/goals/(?P<id>\d+)/performance',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_goal_performance'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => array(
					'date_start' => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => true,
					),
					'date_end'   => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => true,
					),
				),
			)
		);

		// GET /goals/performance - Get batch performance for multiple goals (NEW).
		register_rest_route(
			$this->namespace,
			'/goals/performance',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_batch_performance'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => array(
					'goal_ids'   => array(
						'type'        => 'string',
						'required'    => true,
						'description' => 'Comma-separated list of goal IDs',
					),
					'date_start' => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => false,
						'default'  => '',
					),
					'date_end'   => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => false,
						'default'  => '',
					),
				),
			)
		);

		// GET /goals/{id}/timeline - Get goal conversion timeline.
		register_rest_route(
			$this->namespace,
			'/goals/(?P<id>\d+)/timeline',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_goal_timeline'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => array(
					'page'       => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'   => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'date_start' => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => false,
					),
					'date_end'   => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => false,
					),
				),
			)
		);

		// GET /goals/{id}/sources - Get goal source attribution.
		register_rest_route(
			$this->namespace,
			'/goals/(?P<id>\d+)/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_goal_sources'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => array(
					'attribution_model' => array(
						'type'    => 'string',
						'enum'    => array('first_touch', 'last_touch'),
						'default' => 'last_touch',
					),
					'date_start'        => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => false,
					),
					'date_end'          => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => false,
					),
				),
			)
		);

		// GET /goals/overview - Get overview dashboard data.
		register_rest_route(
			$this->namespace,
			'/goals/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_goals_overview'),
				'permission_callback' => array($this, 'check_admin_permission'),
				'args'                => array(
					'start_date' => array(
						'type'        => 'string',
						'format'      => 'date',
						'required'    => false,
						'description' => __('Start date for the reporting period (YYYY-MM-DD)', 'tracksure'),
					),
					'end_date'   => array(
						'type'        => 'string',
						'format'      => 'date',
						'required'    => false,
						'description' => __('End date for the reporting period (YYYY-MM-DD)', 'tracksure'),
					),
				),
			)
		);
	}

	/**
	 * Get all goals.
	 *
	 * Retrieves all goals with decoded JSON fields and type-cast values.
	 * Results are ordered by creation date (newest first).
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Enhanced security with $wpdb->prepare()
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response with goals array or error.
	 */
	public function get_goals($request)
	{
		global $wpdb;

		$goals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    goal_id, 
                    name, 
                    description, 
                    event_name, 
                    conditions, 
                    trigger_type, 
                    match_logic, 
                    value_type, 
                    fixed_value, 
                    is_active, 
                    created_at, 
                    updated_at
                FROM {$wpdb->prefix}tracksure_goals 
                ORDER BY created_at DESC
                LIMIT %d",
				1000
			)
		);

		// Parse conditions JSON and type-cast values.
		foreach ($goals as $goal) {
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

		// Return goals wrapped in object for consistency with frontend expectations.
		return $this->prepare_success(
			array(
				'goals' => $goals,
				'total' => count($goals),
			)
		);
	}

	/**
	 * Create a new goal.
	 *
	 * Validates input, sanitizes data, and creates a new goal in the database.
	 * Uses TrackSure_Goal_Validator for comprehensive validation.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Enhanced validation, sanitization, and error handling
	 *
	 * @param WP_REST_Request $request Request object with goal data.
	 * @return WP_REST_Response|WP_Error Success response with created goal or error.
	 */
	public function create_goal($request)
	{
		global $wpdb;

		// Validate goal data BEFORE creating.
		$validator = new TrackSure_Goal_Validator();
		$goal_data = array(
			'name'             => $request->get_param('name'),
			'description'      => $request->get_param('description'),
			'event_name'       => $request->get_param('event_name'),
			'trigger_type'     => $request->get_param('trigger_type'),
			'conditions'       => $request->get_param('conditions'),
			'match_logic'      => $request->get_param('match_logic'),
			'value_type'       => $request->get_param('value_type'),
			'fixed_value'      => $request->get_param('fixed_value'),
			'trigger_config'   => $request->get_param('trigger_config'),
			'frequency'        => $request->get_param('frequency'),
			'cooldown_minutes' => $request->get_param('cooldown_minutes'),
			'is_active'        => $request->get_param('is_active'),
		);

		$validation = $validator->validate_and_prepare($goal_data);

		if (! $validation['valid']) {
			return new WP_Error(
				'invalid_goal_data',
				implode(' ', $validation['errors']),
				array('status' => 400)
			);
		}

		$table = $wpdb->prefix . 'tracksure_goals';

		// Use validated and sanitized data.
		$data               = $validation['data'];
		$data['created_at'] = gmdate('Y-m-d H:i:s');
		$data['updated_at'] = gmdate('Y-m-d H:i:s');

		/**
		 * Filter goal data before database insert.
		 *
		 * Allows Pro/3rd party extensions to modify goal data.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $data    Validated and sanitized goal data.
		 * @param WP_REST_Request $request Original REST request object.
		 */
		$data = apply_filters('tracksure_before_create_goal', $data, $request);

		// Determine formats dynamically based on data keys.
		$formats = array();
		foreach ($data as $key => $value) {
			if (in_array($key, array('is_active', 'cooldown_minutes'), true)) {
				$formats[] = '%d';
			} elseif ($key === 'fixed_value') {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		$result = $wpdb->insert($table, $data, $formats);

		if (! $result) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log('[TrackSure] Goal creation failed. Last error: ' . $wpdb->last_error);
			}

			return new WP_Error(
				'goal_create_failed',
				$wpdb->last_error ? $wpdb->last_error : __('Failed to create goal.', 'tracksure'),
				array('status' => 500)
			);
		}

		$goal_id = $wpdb->insert_id;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$goal = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tracksure_goals WHERE goal_id = %d",
				$goal_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Decode JSON fields.
		$goal->conditions = json_decode($goal->conditions, true);
		if (! empty($goal->trigger_config)) {
			$goal->trigger_config = json_decode($goal->trigger_config, true);
		}
		$goal->is_active = (bool) $goal->is_active;
		if (isset($goal->fixed_value) && $goal->fixed_value) {
			$goal->fixed_value = (float) $goal->fixed_value;
		}

		// Sanitize output.
		$goal->name        = esc_html($goal->name);
		$goal->description = ! empty($goal->description) ? esc_html($goal->description) : '';

		// Clear goal caches.
		$this->clear_goals_cache();

		/**
		 * Fires after a goal is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int             $goal_id Goal ID.
		 * @param object          $goal    Full goal object.
		 * @param WP_REST_Request $request Original REST request.
		 */
		do_action('tracksure_after_create_goal', $goal_id, $goal, $request);

		return $this->prepare_success($goal, 201);
	}

	/**
	 * Update a goal.
	 *
	 * Validates changes, sanitizes data, and updates the goal in the database.
	 * Returns 404 if goal doesn't exist.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Enhanced validation and error handling
	 *
	 * @param WP_REST_Request $request Request object with goal data.
	 * @return WP_REST_Response|WP_Error Success response with updated goal or error.
	 */
	public function update_goal($request)
	{
		global $wpdb;

		$table   = $wpdb->prefix . 'tracksure_goals';
		$goal_id = absint($request->get_param('id'));

		// Check if goal exists.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_goals WHERE goal_id = %d",
				$goal_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if (! $exists) {
			return new WP_Error(
				'goal_not_found',
				__('Goal not found.', 'tracksure'),
				array('status' => 404)
			);
		}

		// Validate goal data BEFORE updating.
		$validator = new TrackSure_Goal_Validator();
		$goal_data = array(
			'name'             => $request->get_param('name'),
			'description'      => $request->get_param('description'),
			'event_name'       => $request->get_param('event_name'),
			'trigger_type'     => $request->get_param('trigger_type'),
			'conditions'       => $request->get_param('conditions'),
			'match_logic'      => $request->get_param('match_logic'),
			'value_type'       => $request->get_param('value_type'),
			'fixed_value'      => $request->get_param('fixed_value'),
			'trigger_config'   => $request->get_param('trigger_config'),
			'frequency'        => $request->get_param('frequency'),
			'cooldown_minutes' => $request->get_param('cooldown_minutes'),
			'is_active'        => $request->get_param('is_active'),
		);

		$validation = $validator->validate_and_prepare($goal_data);

		if (! $validation['valid']) {
			return new WP_Error(
				'invalid_goal_data',
				implode(' ', $validation['errors']),
				array('status' => 400)
			);
		}

		// Use validated and sanitized data.
		$data               = $validation['data'];
		$data['updated_at'] = current_time('mysql');

		/**
		 * Filter goal data before database update.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $data    Validated and sanitized goal data.
		 * @param int             $goal_id Goal ID being updated.
		 * @param WP_REST_Request $request Original REST request object.
		 */
		$data = apply_filters('tracksure_before_update_goal', $data, $goal_id, $request);

		$result = $wpdb->update($table, $data, array('goal_id' => $goal_id));

		if ($result === false) {
			return new WP_Error(
				'goal_update_failed',
				__('Failed to update goal.', 'tracksure'),
				array('status' => 500)
			);
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$goal = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tracksure_goals WHERE goal_id = %d",
				$goal_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Decode JSON fields.
		$goal->conditions = json_decode($goal->conditions, true);
		if (! empty($goal->trigger_config)) {
			$goal->trigger_config = json_decode($goal->trigger_config, true);
		}
		$goal->is_active = (bool) $goal->is_active;
		if ($goal->fixed_value) {
			$goal->fixed_value = (float) $goal->fixed_value;
		}

		// Sanitize output.
		$goal->name        = esc_html($goal->name);
		$goal->description = ! empty($goal->description) ? esc_html($goal->description) : '';

		// Clear goal caches.
		$this->clear_goals_cache();

		/**
		 * Fires after a goal is updated.
		 *
		 * @since 1.0.0
		 *
		 * @param int             $goal_id Goal ID.
		 * @param object          $goal    Full goal object.
		 * @param WP_REST_Request $request Original REST request.
		 */
		do_action('tracksure_after_update_goal', $goal_id, $goal, $request);

		return $this->prepare_success(
			array(
				'goal'    => $goal,
				'message' => __('Goal updated successfully.', 'tracksure'),
			)
		);
	}

	/**
	 * Delete a goal.
	 *
	 * Permanently removes a goal from the database.
	 * Fires action hook before deletion for backup/cleanup logic.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Enhanced error handling and PHPDoc
	 *
	 * @param WP_REST_Request $request Request object with goal ID.
	 * @return WP_REST_Response|WP_Error Success message or error.
	 */
	public function delete_goal($request)
	{
		global $wpdb;

		$table   = $wpdb->prefix . 'tracksure_goals';
		$goal_id = absint($request->get_param('id'));

		/**
		 * Fires before a goal is deleted.
		 *
		 * Use this hook to backup goal data or perform cleanup.
		 *
		 * @since 1.0.0
		 *
		 * @param int $goal_id Goal ID being deleted.
		 */
		do_action('tracksure_before_delete_goal', $goal_id);

		$result = $wpdb->delete($table, array('goal_id' => $goal_id), array('%d'));

		if (! $result) {
			return new WP_Error(
				'goal_delete_failed',
				__('Failed to delete goal. Goal may not exist.', 'tracksure'),
				array('status' => 500)
			);
		}

		// Clear goal caches.
		$this->clear_goals_cache();

		/**
		 * Fires after a goal is deleted.
		 *
		 * @since 2.1.0
		 *
		 * @param int $goal_id Goal ID that was deleted.
		 */
		do_action('tracksure_after_delete_goal', $goal_id);

		return $this->prepare_success(
			array(
				'message' => __('Goal deleted successfully.', 'tracksure'),
			)
		);
	}

	/**
	 * Get goal performance metrics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_goal_performance($request)
	{
		global $wpdb;

		$goal_id = absint($request->get_param('id'));

		// Accept both parameter naming conventions for compatibility.
		$date_start = sanitize_text_field($request->get_param('date_start'))
			?: sanitize_text_field($request->get_param('start_date'));
		$date_end   = sanitize_text_field($request->get_param('date_end'))
			?: sanitize_text_field($request->get_param('end_date'));

		// Check cache first (5 minute TTL).
		$cache_key = 'tracksure_goal_perf_' . $goal_id . '_' . md5($date_start . $date_end);
		$cached    = get_transient($cache_key);

		if ($cached !== false) {
			return $this->prepare_success($cached);
		}


		// Get average conversion value (optimized for index usage).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$performance = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
				COUNT(*) as conversions,
				COALESCE(SUM(conversion_value), 0) as revenue,
				COALESCE(AVG(conversion_value), 0) as avg_value
			FROM {$wpdb->prefix}tracksure_conversions
			WHERE goal_id = %d
			AND converted_at >= %s
			AND converted_at <= %s",
				$goal_id,
				$date_start . ' 00:00:00',
				$date_end . ' 23:59:59'
			)
		);

		// Get total sessions in date range for conversion rate (optimized for index usage).
		$total_sessions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id)
			FROM {$wpdb->prefix}tracksure_sessions
			WHERE started_at >= %s
			AND started_at <= %s",
				$date_start . ' 00:00:00',
				$date_end . ' 23:59:59'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared


		$conversion_rate = $total_sessions > 0 ? round(($performance->conversions / $total_sessions) * 100, 2) : 0;

		$result = array(
			'conversions'     => (int) $performance->conversions,
			'revenue'         => (float) $performance->revenue,
			'avg_value'       => (float) $performance->avg_value,
			'conversion_rate' => $conversion_rate,
		);

		// Cache for 5 minutes.
		set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

		return $this->prepare_success($result);
	}

	/**
	 * Get batch performance data for multiple goals.
	 *
	 * This endpoint efficiently queries performance metrics for multiple goals
	 * in a single request, optimized for the Goals Page dashboard.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_batch_performance($request)
	{
		global $wpdb;

		// Parse parameters (accept both naming conventions).
		$goal_ids_param = $request->get_param('goal_ids');
		$date_start     = $request->get_param('date_start') ?: $request->get_param('start_date');
		$date_end       = $request->get_param('date_end') ?: $request->get_param('end_date');

		// Validate goal_ids.
		if (empty($goal_ids_param)) {
			return new WP_Error(
				'missing_goal_ids',
				__('goal_ids parameter is required', 'tracksure'),
				array('status' => 400)
			);
		}

		// Check cache first (5 minute TTL).
		$cache_key = 'tracksure_goals_perf_' . md5($goal_ids_param . $date_start . $date_end);
		$cached    = get_transient($cache_key);

		if ($cached !== false) {
			return $this->prepare_success($cached);
		}

		$goal_ids = array_map('intval', explode(',', $goal_ids_param));
		$goal_ids = array_filter($goal_ids); // Remove zeros

		if (empty($goal_ids)) {
			return new WP_Error(
				'invalid_goal_ids',
				__('No valid goal IDs provided after parsing', 'tracksure'),
				array('status' => 400)
			);
		}

		// Default to last 30 days if dates not provided.
		if (empty($date_start)) {
			$date_start = gmdate('Y-m-d', strtotime('-30 days'));
		}
		if (empty($date_end)) {
			$date_end = gmdate('Y-m-d');
		}

		// Build placeholders for IN clause.
		$placeholders = implode(',', array_fill(0, count($goal_ids), '%d'));

		// Prepare query parameters (datetime format for index usage).
		$query_params = array_merge(
			array($date_start . ' 00:00:00', $date_end . ' 23:59:59'),
			$goal_ids
		);

		// Query goal conversions with revenue (optimized).
		// $placeholders is built from array_fill() with '%d' — safe for interpolation.
		// The splat operator (...$query_params) prevents PHPCS from counting parameters statically.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					g.goal_id,
					COUNT(c.conversion_id) as conversions,
					COALESCE(SUM(c.conversion_value), 0) as total_revenue,
					COALESCE(AVG(c.conversion_value), 0) as avg_value
				FROM {$wpdb->prefix}tracksure_goals g
				LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON (
					c.goal_id = g.goal_id
					AND c.converted_at >= %s
					AND c.converted_at <= %s
				)
				WHERE g.goal_id IN ($placeholders)
				GROUP BY g.goal_id",
				...$query_params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// Check for database errors.
		if ($wpdb->last_error) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log('[TrackSure] Goals batch performance query failed: ' . $wpdb->last_error);
			}
			return $this->prepare_error(
				'query_failed',
				'Database query failed',
				500
			);
		}

		// Get total sessions for conversion rate calculation (optimized).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(DISTINCT session_id) as total
				FROM {$wpdb->prefix}tracksure_sessions
				WHERE started_at >= %s
				AND started_at <= %s
				",
				$date_start . ' 00:00:00',
				$date_end . ' 23:59:59'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Format response.
		$performance = array();
		foreach ($results as $row) {
			$goal_id     = (int) $row['goal_id'];
			$conversions = (int) $row['conversions'];
			$revenue     = (float) $row['total_revenue'];
			$avg_value   = (float) $row['avg_value'];

			// Calculate conversion rate.
			$conversion_rate = $total_sessions > 0
				? ($conversions / $total_sessions) * 100
				: 0;

			$performance[$goal_id] = array(
				'conversions'     => $conversions,
				'revenue'         => round($revenue, 2),
				'avg_value'       => round($avg_value, 2),
				'conversion_rate' => round($conversion_rate, 2),
			);
		}

		// Fill in missing goals with zero data.
		foreach ($goal_ids as $goal_id) {
			if (! isset($performance[$goal_id])) {
				$performance[$goal_id] = array(
					'conversions'     => 0,
					'revenue'         => 0,
					'avg_value'       => 0,
					'conversion_rate' => 0,
				);
			}
		}

		// Cache for 5 minutes.
		set_transient($cache_key, $performance, 5 * MINUTE_IN_SECONDS);

		return $this->prepare_success(array('performance' => $performance));
	}

	/**
	 * Get goal conversion timeline.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_goal_timeline($request)
	{
		global $wpdb;

		$goal_id    = absint($request->get_param('id'));
		$page       = absint($request->get_param('page')) ?: 1;
		$per_page   = absint($request->get_param('per_page')) ?: 20;
		$date_start = $request->get_param('date_start') ?: gmdate('Y-m-d', strtotime('-30 days'));
		$date_end   = $request->get_param('date_end') ?: gmdate('Y-m-d');

		$offset = ($page - 1) * $per_page;

		// ========================================.
		// PHASE 2: TRANSIENT CACHE (5 min TTL).
		// ========================================.
		$cache_key = sprintf(
			'tracksure_goal_%d_timeline_%s_%s_p%d',
			$goal_id,
			$date_start,
			$date_end,
			$page
		);

		$cached = get_transient($cache_key);
		if ($cached !== false && is_array($cached)) {
			return $this->prepare_success($cached);
		}


		// Get total count.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from safe variables.
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
            FROM {$wpdb->prefix}tracksure_conversions
            WHERE goal_id = %d
            AND converted_at >= %s
            AND converted_at <= %s",
				$goal_id,
				$date_start . ' 00:00:00',
				$date_end . ' 23:59:59'
			)
		);

		// ========================================.
		// PHASE 2: OPTIMIZED QUERY (INNER JOIN + BETWEEN).
		// ========================================.
		// Use INNER JOIN instead of LEFT JOIN (conversions always have events).
		// Use BETWEEN instead of >= AND <= (faster).
		$conversions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                c.conversion_id,
                c.visitor_id,
                c.conversion_value as value,
                UNIX_TIMESTAMP(c.converted_at) as converted_at,
                s.utm_source as source,
                s.utm_medium as medium,
                s.utm_campaign as campaign,
                s.referrer,
                s.device_type as device,
                s.browser,
                e.page_url,
                e.event_params
            FROM {$wpdb->prefix}tracksure_conversions c
            INNER JOIN {$wpdb->prefix}tracksure_events e ON c.event_id = e.event_id
            LEFT JOIN {$wpdb->prefix}tracksure_sessions s ON c.session_id = s.session_id
            WHERE c.goal_id = %d
            AND c.converted_at BETWEEN %s AND %s
            ORDER BY c.converted_at DESC
            LIMIT %d OFFSET %d",
				$goal_id,
				$date_start . ' 00:00:00',
				$date_end . ' 23:59:59',
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Format conversions and extract context from event_params.
		foreach ($conversions as &$conversion) {
			$conversion['conversion_id'] = (int) $conversion['conversion_id'];
			$conversion['value']         = (float) $conversion['value'];

			// Parse event_params to extract product/form/click context.
			if (! empty($conversion['event_params'])) {
				$event_params = json_decode($conversion['event_params'], true);
				if (is_array($event_params)) {
					// Product/Item data (WooCommerce standard).
					if (isset($event_params['item_id'])) {
						$conversion['product_id'] = $event_params['item_id'];
					}
					if (isset($event_params['item_name'])) {
						$conversion['product_name'] = $event_params['item_name'];
					}

					// Use item_url if page_url is missing (server-side WooCommerce events).
					if (empty($conversion['page_url']) && isset($event_params['item_url'])) {
						$conversion['page_url'] = $event_params['item_url'];
					}

					// Form data
					if (isset($event_params['form_id'])) {
						$conversion['form_id'] = $event_params['form_id'];
					}

					// Click data.
					if (isset($event_params['element_selector'])) {
						$conversion['element_selector'] = $event_params['element_selector'];
					}
				}
			}
			unset($conversion['event_params']); // Remove raw params from response

			// Set defaults for null values.
			$conversion['source']   = $conversion['source'] ?: '(direct)';
			$conversion['medium']   = $conversion['medium'] ?: '(none)';
			$conversion['campaign'] = $conversion['campaign'] ?: '';
			$conversion['referrer'] = $conversion['referrer'] ?: '';
			$conversion['device']   = $conversion['device'] ?: 'desktop';
			$conversion['browser']  = $conversion['browser'] ?: 'unknown';
		}

		// ========================================.
		// PHASE 2: CACHE RESULT (5 min TTL).
		// ========================================.
		$result = array(
			'goal_id'     => $goal_id,
			'conversions' => $conversions,
			'total'       => (int) $total,
			'page'        => $page,
			'per_page'    => $per_page,
		);

		set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

		return $this->prepare_success($result);
	}

	/**
	 * Get goal source attribution.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_goal_sources($request)
	{
		global $wpdb;

		$goal_id           = absint($request->get_param('id'));
		$attribution_model = sanitize_text_field($request->get_param('attribution_model')) ?: 'last_touch';
		$date_start        = $request->get_param('date_start') ?: gmdate('Y-m-d', strtotime('-30 days'));
		$date_end          = $request->get_param('date_end') ?: gmdate('Y-m-d');

		// ========================================.
		// PHASE 2: TRANSIENT CACHE (5 min TTL).
		// ========================================.
		$cache_key = sprintf(
			'tracksure_goal_%d_sources_%s_%s_%s',
			$goal_id,
			$attribution_model,
			$date_start,
			$date_end
		);

		$cached = get_transient($cache_key);
		if ($cached !== false && is_array($cached)) {
			return $this->prepare_success($cached);
		}


		// Choose source column based on attribution model (strict allowlist).
		$allowed_columns = array('first_touch_source', 'last_touch_source');
		$source_column   = in_array($attribution_model === 'first_touch' ? 'first_touch_source' : 'last_touch_source', $allowed_columns, true)
			? ($attribution_model === 'first_touch' ? 'first_touch_source' : 'last_touch_source')
			: 'last_touch_source';

		// Get source breakdown.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                {$source_column} as source,
                COUNT(*) as conversions,
                COALESCE(SUM(conversion_value), 0) as revenue
            FROM {$wpdb->prefix}tracksure_conversions
            WHERE goal_id = %d
            AND converted_at >= %s
            AND converted_at <= %s
            AND {$source_column} IS NOT NULL
            AND {$source_column} != ''
            GROUP BY {$source_column}
            ORDER BY conversions DESC
            LIMIT 20",
				$goal_id,
				$date_start . ' 00:00:00',
				$date_end . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Calculate total for percentages.
		$total_conversions = array_sum(array_column($sources, 'conversions'));

		// Format sources with percentages.
		foreach ($sources as &$source) {
			$source['conversions'] = (int) $source['conversions'];
			$source['revenue']     = (float) $source['revenue'];
			$source['percentage']  = $total_conversions > 0
				? round(($source['conversions'] / $total_conversions) * 100, 1)
				: 0;
		}

		// ========================================.
		// PHASE 2: CACHE RESULT (5 min TTL).
		// ========================================.
		$result = array(
			'goal_id'           => $goal_id,
			'attribution_model' => $attribution_model,
			'sources'           => $sources,
		);

		set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

		return $this->prepare_success($result);
	}

	/**
	 * Get goals overview dashboard data.
	 *
	 * Provides high-level KPIs and insights including:
	 * - Total conversions, value, and rate
	 * - Active goals count
	 * - Trend comparisons with previous period
	 * - Daily conversions chart (last 30 days)
	 * - Top 5 performing goals
	 *
	 * Features:
	 * - Transient caching (5 minutes)
	 * - Previous period comparison for trends
	 * - Efficient aggregation queries
	 * - Full i18n support
	 *
	 * @since 2.1.0
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response Response object with overview data.
	 */
	public function get_goals_overview($request)
	{
		global $wpdb;

		// ========================================.
		// PHASE 1: PARSE & VALIDATE DATE RANGE.
		// ========================================.
		$end_date   = $request->get_param('end_date');
		$start_date = $request->get_param('start_date');

		// Default to last 30 days if not specified.
		if (empty($end_date)) {
			$end_date = gmdate('Y-m-d');
		}
		if (empty($start_date)) {
			$start_date = gmdate('Y-m-d', strtotime($end_date . ' -30 days'));
		}

		// ========================================.
		// PHASE 2: CHECK CACHE.
		// ========================================.
		$cache_key = 'tracksure_goals_overview_' . md5($start_date . '_' . $end_date);
		$cached    = get_transient($cache_key);
		if ($cached !== false) {
			return $this->prepare_success($cached);
		}


		// ========================================.
		// PHASE 3: CALCULATE CURRENT PERIOD METRICS.
		// ========================================.
		// Total conversions, value, and unique visitors for the period.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                COUNT(*) as total_conversions,
                COALESCE(SUM(conversion_value), 0) as total_value,
                COUNT(DISTINCT visitor_id) as unique_visitors
            FROM {$wpdb->prefix}tracksure_conversions
            WHERE converted_at >= %s
            AND converted_at <= %s",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total_conversions = (int) ($current_stats['total_conversions'] ?? 0);
		$total_value       = (float) ($current_stats['total_value'] ?? 0);
		$unique_visitors   = (int) ($current_stats['unique_visitors'] ?? 0);

		// Calculate conversion rate (conversions / unique visitors).
		// Note: In production, you'd want to get total sessions instead
		$conversion_rate = $unique_visitors > 0
			? round(($total_conversions / $unique_visitors) * 100, 2)
			: 0;

		// ========================================.
		// PHASE 4: CALCULATE PREVIOUS PERIOD FOR TRENDS.
		// ========================================.
		// Calculate date difference to determine previous period.
		$period_length   = (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS;
		$prev_start_date = gmdate('Y-m-d', strtotime($start_date . ' -' . ceil($period_length) . ' days'));
		$prev_end_date   = gmdate('Y-m-d', strtotime($end_date . ' -' . ceil($period_length) . ' days'));

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$previous_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                COUNT(*) as total_conversions,
                COALESCE(SUM(conversion_value), 0) as total_value,
                COUNT(DISTINCT visitor_id) as unique_visitors
            FROM {$wpdb->prefix}tracksure_conversions
            WHERE converted_at >= %s
            AND converted_at <= %s",
				$prev_start_date . ' 00:00:00',
				$prev_end_date . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$prev_conversions = (int) ($previous_stats['total_conversions'] ?? 0);
		$prev_value       = (float) ($previous_stats['total_value'] ?? 0);
		$prev_visitors    = (int) ($previous_stats['unique_visitors'] ?? 0);
		$prev_rate        = $prev_visitors > 0
			? round(($prev_conversions / $prev_visitors) * 100, 2)
			: 0;

		// ========================================.
		// PHASE 5: GET ACTIVE GOALS COUNT.
		// ========================================.
		$active_goals = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_goals WHERE is_active = %d",
				1
			)
		);

		// ========================================.
		// PHASE 6: GET DAILY CONVERSIONS (LAST 30 DAYS).
		// ========================================.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$daily_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                DATE(converted_at) as date,
                COUNT(*) as conversions
            FROM {$wpdb->prefix}tracksure_conversions
            WHERE converted_at >= %s
            AND converted_at <= %s
            GROUP BY DATE(converted_at)
            ORDER BY date ASC",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Format daily data.
		$daily_conversions = array();
		foreach ($daily_data as $day) {
			$daily_conversions[] = array(
				'date'        => $day['date'],
				'conversions' => (int) $day['conversions'],
			);
		}

		// ========================================.
		// PHASE 7: GET TOP 5 PERFORMING GOALS.
		// ========================================.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$top_goals_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                g.goal_id,
                g.name,
                g.trigger_type,
                COUNT(c.conversion_id) as conversions,
                COALESCE(SUM(c.conversion_value), 0) as value
            FROM {$wpdb->prefix}tracksure_goals g
            LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON g.goal_id = c.goal_id
                AND c.converted_at >= %s
                AND c.converted_at <= %s
            WHERE g.is_active = %d
            GROUP BY g.goal_id
            ORDER BY conversions DESC
            LIMIT %d",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				1,
				5
			),
			ARRAY_A
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Format top goals.
		$top_goals = array();
		foreach ($top_goals_data as $goal) {
			$top_goals[] = array(
				'goal_id'      => (int) $goal['goal_id'],
				'name'         => esc_html($goal['name']),
				'trigger_type' => $goal['trigger_type'],
				'conversions'  => (int) $goal['conversions'],
				'value'        => (float) $goal['value'],
			);
		}

		// ========================================.
		// PHASE 8: PREPARE RESPONSE.
		// ========================================.
		$result = array(
			'total_conversions' => $total_conversions,
			'total_value'       => $total_value,
			'conversion_rate'   => $conversion_rate,
			'active_goals'      => $active_goals,
			'conversions_trend' => array(
				'previous_period' => $prev_conversions,
			),
			'value_trend'       => array(
				'previous_period' => $prev_value,
			),
			'rate_trend'        => array(
				'previous_period' => $prev_rate,
			),
			'daily_conversions' => $daily_conversions,
			'top_goals'         => $top_goals,
		);

		// ========================================.
		// PHASE 9: CACHE RESULT (5 min TTL).
		// ========================================.
		set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

		return $this->prepare_success($result);
	}

	/**
	 * Get goal schema for validation.
	 *
	 * @param bool $update Whether this is for update (id not required).
	 * @return array Schema arguments.
	 */
	private function get_goal_schema($update = false)
	{
		return array(
			'name'        => array(
				'type'     => 'string',
				'required' => ! $update,
			),
			'description' => array(
				'type'     => 'string',
				'required' => false,
			),
			'event_name'  => array(
				'type'     => 'string',
				'required' => ! $update,
			),
			'conditions'  => array(
				'type'     => 'array',
				'required' => false,
			),
			'is_active'   => array(
				'type'     => 'boolean',
				'required' => false,
				'default'  => true,
			),
		);
	}

	/**
	 * Clear all goal-related caches.
	 *
	 * Called after create/update/delete operations to ensure
	 * server-side and client-side goal lists are refreshed.
	 */
	private function clear_goals_cache()
	{
		// Clear server-side cache (used by Goal Evaluator).
		delete_transient('tracksure_active_goals_server');

		// Clear client-side cache (used by front-end tracking).
		delete_transient('tracksure_active_goals');

		// Clear Goal Evaluator in-memory cache.
		$evaluator = TrackSure_Goal_Evaluator::get_instance();
		$evaluator->clear_cache();
	}
}
