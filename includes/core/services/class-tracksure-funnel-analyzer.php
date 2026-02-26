<?php

/**
 *
 * TrackSure Funnel Analyzer
 *
 * Analyzes conversion funnels and identifies drop-off points.
 * Supports event-based, URL-based, and mixed funnels. *
 * Direct database queries required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics.
 * All queries use $wpdb->prepare() for security.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 *
 * @package TrackSure\Core\Services
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Funnel Analyzer class.
 */
class TrackSure_Funnel_Analyzer {




	/**
	 * Instance.
	 *
	 * @var TrackSure_Funnel_Analyzer
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
	 * @return TrackSure_Funnel_Analyzer
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
	 * Analyze funnel.
	 *
	 * @param int    $funnel_id Funnel ID.
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date End date (Y-m-d).
	 * @param array  $filters Optional filters (utm_source, utm_medium, device_type, etc).
	 * @return array Funnel analysis results.
	 */
	public function analyze_funnel( $funnel_id, $start_date, $end_date, $filters = array() ) {
		$funnel = $this->get_funnel( $funnel_id );

		if ( ! $funnel ) {
			return array( 'error' => 'Funnel not found' );
		}

		$steps = $this->get_funnel_steps( $funnel_id );

		if ( empty( $steps ) ) {
			return array( 'error' => 'No steps defined for this funnel' );
		}

		// Calculate funnel based on type.
		if ( $funnel['funnel_type'] === 'event_based' ) {
			return $this->analyze_event_based_funnel( $funnel, $steps, $start_date, $end_date, $filters );
		} elseif ( $funnel['funnel_type'] === 'url_based' ) {
			return $this->analyze_url_based_funnel( $funnel, $steps, $start_date, $end_date, $filters );
		}

		return array( 'error' => 'Unsupported funnel type' );
	}

	/**
	 * Analyze event-based funnel.
	 *
	 * @param array  $funnel Funnel data.
	 * @param array  $steps Funnel steps.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param array  $filters Filters.
	 * @return array Analysis results.
	 */
	private function analyze_event_based_funnel( $funnel, $steps, $start_date, $end_date, $filters ) {
		global $wpdb;
		$results = array(
			'funnel_id'        => $funnel['funnel_id'],
			'funnel_name'      => $funnel['funnel_name'],
			'steps'            => array(),
			'total_entered'    => 0,
			'total_completed'  => 0,
			'completion_rate'  => 0,
			'biggest_drop_off' => null,
		);

		$previous_count = 0;

		foreach ( $steps as $index => $step ) {
			$step_number = $index + 1;
			$step_event  = $step['event_name'];

			// Build WHERE clauses using $wpdb->prepare() placeholders.
			$where_clauses = array(
				'e.occurred_at >= %s',
				'e.occurred_at <= %s',
				'e.event_name = %s',
			);
			$where_values  = array(
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$step_event,
			);

			// Apply optional filters with prepare() placeholders.
			if ( ! empty( $filters['utm_source'] ) ) {
				$where_clauses[] = 's.utm_source = %s';
				$where_values[]  = $filters['utm_source'];
			}
			if ( ! empty( $filters['utm_medium'] ) ) {
				$where_clauses[] = 's.utm_medium = %s';
				$where_values[]  = $filters['utm_medium'];
			}
			if ( ! empty( $filters['device_type'] ) ) {
				$where_clauses[] = 's.device_type = %s';
				$where_values[]  = $filters['device_type'];
			}
			if ( ! empty( $filters['country'] ) ) {
				$where_clauses[] = 's.country = %s';
				$where_values[]  = $filters['country'];
			}

			$where_sql = implode( ' AND ', $where_clauses );

			// For first step: count all sessions that started funnel.
			if ( $index === 0 ) {
				// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are in $where_sql variable (built from %s array above).
				$sql = $wpdb->prepare(
					"SELECT COUNT(DISTINCT e.session_id) as count
					FROM {$wpdb->prefix}tracksure_events e
					LEFT JOIN {$wpdb->prefix}tracksure_sessions s ON e.session_id = s.session_id
					WHERE {$where_sql}",
					$where_values
				);
				// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

				$count                    = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
				$results['total_entered'] = $count;
			} else {
				// For subsequent steps: count sessions that completed previous steps AND this step.
				$previous_events    = array_slice( array_column( $steps, 'event_name' ), 0, $step_number );
				$in_placeholders    = implode( ', ', array_fill( 0, count( $previous_events ), '%s' ) );
				$previous_count_val = count( $previous_events );

				// Merge: base WHERE values + IN clause event names + HAVING count.
				$all_values = array_merge( $where_values, $previous_events, array( $previous_count_val ) );

				// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are in $where_sql/$in_placeholders variables (built from %s/%d arrays above).
				$sql = $wpdb->prepare(
					"SELECT COUNT(DISTINCT e.session_id) as count
					FROM {$wpdb->prefix}tracksure_events e
					LEFT JOIN {$wpdb->prefix}tracksure_sessions s ON e.session_id = s.session_id
					WHERE {$where_sql}
					AND e.session_id IN (
						SELECT DISTINCT session_id
						FROM {$wpdb->prefix}tracksure_events
						WHERE event_name IN ({$in_placeholders})
						GROUP BY session_id
						HAVING COUNT(DISTINCT event_name) = %d
					)",
					$all_values
				);
				// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

				$count = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
			}

			// Calculate metrics.
			$percentage     = $results['total_entered'] > 0 ? ( $count / $results['total_entered'] ) * 100 : 0;
			$drop_off_count = $previous_count > 0 ? $previous_count - $count : 0;
			$drop_off_rate  = $previous_count > 0 ? ( $drop_off_count / $previous_count ) * 100 : 0;

			$step_result = array(
				'step_number'    => $step_number,
				'step_name'      => $step['step_name'],
				'event_name'     => $step_event,
				'sessions'       => $count,
				'percentage'     => round( $percentage, 2 ),
				'drop_off_count' => $drop_off_count,
				'drop_off_rate'  => round( $drop_off_rate, 2 ),
			);

			$results['steps'][] = $step_result;

			// Track biggest drop-off.
			if ( $index > 0 && ( $results['biggest_drop_off'] === null || $drop_off_rate > $results['biggest_drop_off']['drop_off_rate'] ) ) {
				$results['biggest_drop_off'] = $step_result;
			}

			$previous_count = $count;

			// Track completion (last step).
			if ( $index === count( $steps ) - 1 ) {
				$results['total_completed'] = $count;
			}
		}

		$results['completion_rate'] = $results['total_entered'] > 0
			? round( ( $results['total_completed'] / $results['total_entered'] ) * 100, 2 )
			: 0;

		return $results;
	}

	/**
	 * Analyze URL-based funnel.
	 *
	 * @param array  $funnel Funnel data.
	 * @param array  $steps Funnel steps.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param array  $filters Filters.
	 * @return array Analysis results.
	 */
	private function analyze_url_based_funnel( $funnel, $steps, $start_date, $end_date, $filters ) {
		// Similar implementation for URL-based funnels.
		// Uses page_url matching instead of event_name matching.
		return array( 'error' => 'URL-based funnels not yet implemented' );
	}

	/**
	 * Get conversion paths.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param int    $limit Limit.
	 * @return array Top conversion paths.
	 */
	public function get_conversion_paths( $start_date, $end_date, $limit = 20 ) {
		global $wpdb;
		$sql = "
		SELECT
			GROUP_CONCAT(
				CONCAT(
					COALESCE(t.utm_source, '(direct)'),
					'/',
					COALESCE(t.utm_medium, '(none)'),
					'/',
					COALESCE(t.channel, 'unknown')
				)
				ORDER BY t.touchpoint_seq
				SEPARATOR ' → '
			) as conversion_path,
			COUNT(DISTINCT t.conversion_id) as conversions,
			SUM(c.conversion_value) as total_value,
			AVG(c.time_to_convert) as avg_time_to_convert,
			AVG(c.sessions_to_convert) as avg_sessions_to_convert
		FROM {$wpdb->prefix}tracksure_touchpoints t
		INNER JOIN {$wpdb->prefix}tracksure_conversions c ON t.conversion_id = c.conversion_id
		WHERE c.converted_at >= %s
		AND c.converted_at <= %s
		AND t.is_conversion_touchpoint = 1
		GROUP BY t.conversion_id
		ORDER BY conversions DESC
		LIMIT %d
		";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql is safe and uses placeholders.
		return $wpdb->get_results(
			$wpdb->prepare( $sql, $start_date . ' 00:00:00', $end_date . ' 23:59:59', $limit ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get exit paths (abandonment).
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param int    $limit Limit.
	 * @return array Top exit paths.
	 */
	public function get_exit_paths( $start_date, $end_date, $limit = 20 ) {
		global $wpdb;
		$sql = "
		SELECT
			GROUP_CONCAT(
				CONCAT(
					COALESCE(t.utm_source, '(direct)'),
					'/',
					COALESCE(t.utm_medium, '(none)')
				)
				ORDER BY t.touchpoint_seq
				SEPARATOR ' → '
			) as exit_path,
			t.page_path as exit_page,
			COUNT(DISTINCT t.visitor_id) as visitors
		FROM {$wpdb->prefix}tracksure_touchpoints t
		LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON t.visitor_id = c.visitor_id
		WHERE t.touched_at >= %s
		AND t.touched_at <= %s
		AND c.conversion_id IS NULL
		GROUP BY t.visitor_id
		ORDER BY visitors DESC
		LIMIT %d
		";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql is safe and uses placeholders.
		return $wpdb->get_results(
			$wpdb->prepare( $sql, $start_date . ' 00:00:00', $end_date . ' 23:59:59', $limit ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get funnel.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return array|null Funnel data.
	 */
	private function get_funnel( $funnel_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT funnel_id, name, description, steps_count, is_active, created_at, updated_at 
             FROM {$wpdb->prefix}tracksure_funnels WHERE funnel_id = %d AND is_active = 1",
				$funnel_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get funnel steps.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return array Funnel steps.
	 */
	private function get_funnel_steps( $funnel_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT step_id, funnel_id, step_order, step_name, event_name, match_type, match_value, 
                    is_required, created_at, updated_at 
             FROM {$wpdb->prefix}tracksure_funnel_steps WHERE funnel_id = %d ORDER BY step_order ASC",
				$funnel_id
			),
			ARRAY_A
		);
	}

	/**
	 * Create default funnels.
	 * Called during installation or when WooCommerce is detected.
	 */
	public static function create_default_funnels() {
		global $wpdb;
		$db = TrackSure_DB::get_instance();
		// WooCommerce E-commerce Funnel.
		if ( class_exists( 'WooCommerce' ) ) {
			$wpdb->insert(
				$wpdb->prefix . 'tracksure_funnels',
				array(
					'funnel_name' => 'WooCommerce Purchase Funnel',
					'funnel_type' => 'event_based',
					'is_active'   => 1,
					'time_window' => 1800,
					'created_at'  => current_time( 'mysql', true ),
					'updated_at'  => current_time( 'mysql', true ),
				)
			);

			$funnel_id = $wpdb->insert_id;

			$steps = array(
				array(
					'step_order' => 1,
					'step_name'  => 'Product View',
					'event_name' => 'view_item',
				),
				array(
					'step_order' => 2,
					'step_name'  => 'Add to Cart',
					'event_name' => 'add_to_cart',
				),
				array(
					'step_order' => 3,
					'step_name'  => 'Begin Checkout',
					'event_name' => 'begin_checkout',
				),
				array(
					'step_order' => 4,
					'step_name'  => 'Purchase',
					'event_name' => 'purchase',
				),
			);

			foreach ( $steps as $step ) {
				$wpdb->insert(
					$wpdb->prefix . 'tracksure_funnel_steps',
					array_merge(
						$step,
						array(
							'funnel_id' => $funnel_id,
							'step_type' => 'event',
						)
					)
				);
			}
		}

		// Lead Generation Funnel.
		$wpdb->insert(
			$wpdb->prefix . 'tracksure_funnels',
			array(
				'funnel_name' => 'Lead Generation Funnel',
				'funnel_type' => 'event_based',
				'is_active'   => 1,
				'time_window' => 1800,
				'created_at'  => current_time( 'mysql', true ),
				'updated_at'  => current_time( 'mysql', true ),
			)
		);

		$funnel_id = $wpdb->insert_id;

		$steps = array(
			array(
				'step_order' => 1,
				'step_name'  => 'Landing Page',
				'event_name' => 'page_view',
			),
			array(
				'step_order' => 2,
				'step_name'  => 'Form View',
				'event_name' => 'form_view',
			),
			array(
				'step_order' => 3,
				'step_name'  => 'Form Start',
				'event_name' => 'form_start',
			),
			array(
				'step_order' => 4,
				'step_name'  => 'Form Submit',
				'event_name' => 'form_submit',
			),
		);

		foreach ( $steps as $step ) {
			$wpdb->insert(
				$wpdb->prefix . 'tracksure_funnel_steps',
				array_merge(
					$step,
					array(
						'funnel_id' => $funnel_id,
						'step_type' => 'event',
					)
				)
			);
		}
	}
}
