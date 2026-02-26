<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for conversion tracking diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Conversion Recorder
 *
 * Records conversions and calculates attribution across multiple models.
 * Supports: first-touch, last-touch, linear, time-decay, position-based. *
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
 * TrackSure Conversion Recorder class.
 */
class TrackSure_Conversion_Recorder {




	/**
	 * Instance.
	 *
	 * @var TrackSure_Conversion_Recorder
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Touchpoint recorder instance.
	 *
	 * @var TrackSure_Touchpoint_Recorder
	 */
	private $touchpoint_recorder;

	/**
	 * Attribution lookback window in days.
	 *
	 * @var int
	 */
	private $lookback_days = 30;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Conversion_Recorder
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
		$this->db                  = TrackSure_DB::get_instance();
		$this->touchpoint_recorder = TrackSure_Touchpoint_Recorder::get_instance();

		// Get lookback window from settings (default 30 days).
		$this->lookback_days = (int) get_option( 'tracksure_attribution_window', 30 );
	}

	/**
	 * Record a conversion.
	 *
	 * @param array $conversion_data Conversion data.
	 * @return int|false Conversion ID on success, false on failure.
	 */
	public function record_conversion( $conversion_data ) {
		global $wpdb;
		// Validate required fields.
		if ( empty( $conversion_data['visitor_id'] ) || empty( $conversion_data['session_id'] ) || empty( $conversion_data['event_id'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] Conversion Recorder: Missing required fields' );
			}
			return false;
		}

		// DEDUPLICATION: Prevent multiple conversions for the same event_id.
		// This catches: dual WooCommerce hooks, event_recorder + goal_evaluator both calling us.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT conversion_id FROM {$wpdb->prefix}tracksure_conversions WHERE event_id = %s LIMIT 1",
				$conversion_data['event_id']
			)
		);
		if ( $existing ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TrackSure] Conversion Recorder: Skipped duplicate — conversion already exists for event_id=' . $conversion_data['event_id'] );
			}
			return (int) $existing;
		}

		// Get touchpoints for this conversion.
		// All timestamps are stored in UTC for consistency (WP.org best practice).
		// Callers should send converted_at in UTC (current_time('mysql', 1) or gmdate()).
		$converted_at = ! empty( $conversion_data['converted_at'] ) ? $conversion_data['converted_at'] : current_time( 'mysql', true );
		$touchpoints  = $this->touchpoint_recorder->get_conversion_touchpoints(
			$conversion_data['visitor_id'],
			$converted_at,
			$this->lookback_days
		);

		if ( empty( $touchpoints ) ) {
			// Still record conversion, but with null attribution.
		}

		// Calculate time and sessions to convert.
		$first_touchpoint    = ! empty( $touchpoints ) ? $touchpoints[0] : null;
		$time_to_convert     = null;
		$sessions_to_convert = 1;

		if ( $first_touchpoint ) {
			$time_to_convert     = strtotime( $converted_at ) - strtotime( $first_touchpoint['touched_at'] );
			$sessions_to_convert = count( array_unique( array_column( $touchpoints, 'session_id' ) ) );
		}

		// Get first and last touch attribution.
		$first_touch = $this->get_first_touch_attribution( $touchpoints );
		$last_touch  = $this->get_last_touch_attribution( $touchpoints );

		// Prepare conversion data.
		$conversion_insert_data = array(
			'visitor_id'           => $conversion_data['visitor_id'],
			'session_id'           => $conversion_data['session_id'],
			'event_id'             => $conversion_data['event_id'],
			'conversion_type'      => ! empty( $conversion_data['conversion_type'] ) ? $conversion_data['conversion_type'] : 'goal',
			'goal_id'              => ! empty( $conversion_data['goal_id'] ) ? $conversion_data['goal_id'] : null,
			'conversion_value'     => ! empty( $conversion_data['conversion_value'] ) ? $conversion_data['conversion_value'] : 0.00,
			'currency'             => ! empty( $conversion_data['currency'] ) ? $conversion_data['currency'] : 'USD',
			'transaction_id'       => ! empty( $conversion_data['transaction_id'] ) ? $conversion_data['transaction_id'] : null,
			'items_count'          => ! empty( $conversion_data['items_count'] ) ? $conversion_data['items_count'] : 0,
			'converted_at'         => $converted_at,
			'time_to_convert'      => $time_to_convert,
			'sessions_to_convert'  => $sessions_to_convert,
			'first_touch_source'   => $first_touch['utm_source'],
			'first_touch_medium'   => $first_touch['utm_medium'],
			'first_touch_campaign' => $first_touch['utm_campaign'],
			'last_touch_source'    => $last_touch['utm_source'],
			'last_touch_medium'    => $last_touch['utm_medium'],
			'last_touch_campaign'  => $last_touch['utm_campaign'],
			'created_at'           => current_time( 'mysql', true ),
		);

		// START TRANSACTION - Ensure all conversion data is inserted atomically.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Insert conversion.
			$result = $wpdb->insert(
				$wpdb->prefix . 'tracksure_conversions',
				$conversion_insert_data,
				array(
					'%d',
					'%s',
					'%s',
					'%s',
					'%d',
					'%f',
					'%s',
					'%s',
					'%d',
					'%s',
					'%d',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
				)
			);

			if ( $result === false ) {
				throw new Exception( 'Failed to insert conversion: ' . $wpdb->last_error );
			}

			$conversion_id = $wpdb->insert_id;

			// Update touchpoints with conversion reference.
			if ( ! empty( $touchpoints ) ) {
				$touchpoint_result = $this->link_touchpoints_to_conversion( $touchpoints, $conversion_id );
				if ( $touchpoint_result === false ) {
					throw new Exception( 'Failed to link touchpoints to conversion' );
				}
			}

			// Calculate attribution across all models.
			$attribution_result = $this->calculate_attribution( $conversion_id, $touchpoints, $conversion_insert_data['conversion_value'] );
			if ( $attribution_result === false ) {
				throw new Exception( 'Failed to calculate attribution' );
			}

			// COMMIT TRANSACTION - All operations succeeded.
			$wpdb->query( 'COMMIT' );
		} catch ( Exception $e ) {
			// ROLLBACK on any failure.
			$wpdb->query( 'ROLLBACK' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] Conversion recording failed (transaction rolled back): ' . $e->getMessage() );
			}
			return false;
		}

		/**
		 * Fires after a conversion is recorded.
		 *
		 * @since 1.0.0
		 * @param int   $conversion_id Conversion ID.
		 * @param array $conversion_data Conversion data.
		 */
		do_action( 'tracksure_conversion_recorded', $conversion_id, $conversion_insert_data );

		return $conversion_id;
	}

	/**
	 * Get first-touch attribution.
	 *
	 * @param array $touchpoints Touchpoints.
	 * @return array First touch data.
	 */
	private function get_first_touch_attribution( $touchpoints ) {
		if ( empty( $touchpoints ) ) {
			return array(
				'utm_source'   => null,
				'utm_medium'   => null,
				'utm_campaign' => null,
			);
		}

		$first = $touchpoints[0];
		return array(
			'utm_source'   => $first['utm_source'],
			'utm_medium'   => $first['utm_medium'],
			'utm_campaign' => $first['utm_campaign'],
		);
	}

	/**
	 * Get last-touch attribution.
	 *
	 * @param array $touchpoints Touchpoints.
	 * @return array Last touch data.
	 */
	private function get_last_touch_attribution( $touchpoints ) {
		if ( empty( $touchpoints ) ) {
			return array(
				'utm_source'   => null,
				'utm_medium'   => null,
				'utm_campaign' => null,
			);
		}

		$last = $touchpoints[ count( $touchpoints ) - 1 ];
		return array(
			'utm_source'   => $last['utm_source'],
			'utm_medium'   => $last['utm_medium'],
			'utm_campaign' => $last['utm_campaign'],
		);
	}

	/**
	 * Link touchpoints to conversion.
	 *
	 * @param array $touchpoints Touchpoints.
	 * @param int   $conversion_id Conversion ID.
	 */
	private function link_touchpoints_to_conversion( $touchpoints, $conversion_id ) {
		global $wpdb;
		$touchpoint_ids = array_column( $touchpoints, 'touchpoint_id' );

		if ( empty( $touchpoint_ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $touchpoint_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN() placeholders built from array_fill.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}tracksure_touchpoints
			SET conversion_id = %d, is_conversion_touchpoint = 1
			WHERE touchpoint_id IN ({$placeholders})",
				array_merge( array( $conversion_id ), $touchpoint_ids )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * Calculate attribution across all models.
	 *
	 * @param int   $conversion_id Conversion ID.
	 * @param array $touchpoints Touchpoints.
	 * @param float $conversion_value Conversion value.
	 */
	private function calculate_attribution( $conversion_id, $touchpoints, $conversion_value ) {
		if ( empty( $touchpoints ) ) {
			return;
		}

		$count = count( $touchpoints );

		// Free version: First-touch and Last-touch.
		$this->insert_attribution_credit(
			$conversion_id,
			$touchpoints[0],
			'first_touch',
			$conversion_value,
			100.00,
			1.0000,
			1
		);

		$this->insert_attribution_credit(
			$conversion_id,
			$touchpoints[ $count - 1 ],
			'last_touch',
			$conversion_value,
			100.00,
			1.0000,
			$count
		);

		// Update touchpoints table with canonical attribution weight (default: last-touch).
		// This field is used by the UI to display attribution weights.
		// All models are stored in conversion_attribution table for reporting.
		$this->update_canonical_attribution_weights( $touchpoints, 'last_touch' );

		// Multi-touch attribution models — all available in Free.
		$this->calculate_linear_attribution( $conversion_id, $touchpoints, $conversion_value );
		$this->calculate_time_decay_attribution( $conversion_id, $touchpoints, $conversion_value );
		$this->calculate_position_based_attribution( $conversion_id, $touchpoints, $conversion_value );
	}

	/**
	 * Update touchpoints table with canonical attribution weights.
	 *
	 * Default: Uses last-touch (100% to last touchpoint, 0% to others).
	 * The canonical model can be changed via the $canonical_model parameter.
	 *
	 * @param array  $touchpoints Touchpoints.
	 * @param string $canonical_model The canonical attribution model to use.
	 */
	private function update_canonical_attribution_weights( $touchpoints, $canonical_model = 'last_touch' ) {
		global $wpdb;
		$count = count( $touchpoints );

		foreach ( $touchpoints as $index => $touchpoint ) {
			$weight = 0.0000; // Default: no weight

			// Calculate weight based on canonical model.
			switch ( $canonical_model ) {
				case 'last_touch':
					// Last touchpoint gets 100%.
					if ( $index === $count - 1 ) {
						$weight = 1.0000;
					}
					break;

				case 'first_touch':
					// First touchpoint gets 100%.
					if ( $index === 0 ) {
						$weight = 1.0000;
					}
					break;

				case 'linear':
					// Equal weight to all.
					$weight = round( 1.0 / $count, 4 );
					break;

				default:
					// For other models, last-touch as fallback.
					if ( $index === $count - 1 ) {
						$weight = 1.0000;
					}
					break;
			}

			// Update touchpoint with canonical attribution weight.
			$wpdb->update(
				$wpdb->prefix . 'tracksure_touchpoints',
				array( 'attribution_weight' => $weight ),
				array( 'touchpoint_id' => $touchpoint['touchpoint_id'] ),
				array( '%f' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Calculate linear attribution (equal credit to all touchpoints).
	 *
	 * @param int   $conversion_id Conversion ID.
	 * @param array $touchpoints Touchpoints.
	 * @param float $conversion_value Conversion value.
	 */
	private function calculate_linear_attribution( $conversion_id, $touchpoints, $conversion_value ) {
		$count          = count( $touchpoints );
		$weight         = 1.0 / $count;
		$credit_percent = 100.00 / $count;
		$credit_value   = $conversion_value / $count;

		foreach ( $touchpoints as $index => $touchpoint ) {
			$this->insert_attribution_credit(
				$conversion_id,
				$touchpoint,
				'linear',
				$credit_value,
				$credit_percent,
				$weight,
				$index + 1
			);
		}
	}

	/**
	 * Calculate time-decay attribution (exponential decay, half-life 7 days).
	 *
	 * @param int   $conversion_id Conversion ID.
	 * @param array $touchpoints Touchpoints.
	 * @param float $conversion_value Conversion value.
	 */
	private function calculate_time_decay_attribution( $conversion_id, $touchpoints, $conversion_value ) {
		$weights = $this->calculate_time_decay_weights( $touchpoints );
		$count   = count( $touchpoints );

		foreach ( $touchpoints as $index => $touchpoint ) {
			$weight         = $weights[ $index ];
			$credit_percent = $weight * 100;
			$credit_value   = $conversion_value * $weight;

			$this->insert_attribution_credit(
				$conversion_id,
				$touchpoint,
				'time_decay',
				$credit_value,
				$credit_percent,
				$weight,
				$index + 1
			);
		}
	}

	/**
	 * Calculate time-decay weights.
	 *
	 * @param array $touchpoints Touchpoints.
	 * @return array Normalized weights.
	 */
	private function calculate_time_decay_weights( $touchpoints ) {
		$count = count( $touchpoints );
		if ( $count === 1 ) {
			return array( 1.0 );
		}

		$last_touch_time = strtotime( $touchpoints[ $count - 1 ]['touched_at'] );
		$weights         = array();
		$total_weight    = 0;

		// Calculate raw weights (half-life of 7 days).
		foreach ( $touchpoints as $touchpoint ) {
			$days_before_conversion = ( $last_touch_time - strtotime( $touchpoint['touched_at'] ) ) / 86400;
			$weight                 = pow( 0.5, $days_before_conversion / 7 );
			$weights[]              = $weight;
			$total_weight          += $weight;
		}

		// Normalize weights to sum to 1.0.
		return array_map(
			function ( $w ) use ( $total_weight ) {
				return $w / $total_weight;
			},
			$weights
		);
	}

	/**
	 * Calculate position-based attribution (40% first, 40% last, 20% middle).
	 *
	 * @param int   $conversion_id Conversion ID.
	 * @param array $touchpoints Touchpoints.
	 * @param float $conversion_value Conversion value.
	 */
	private function calculate_position_based_attribution( $conversion_id, $touchpoints, $conversion_value ) {
		$count = count( $touchpoints );

		if ( $count === 1 ) {
			// Single touchpoint gets 100%.
			$this->insert_attribution_credit(
				$conversion_id,
				$touchpoints[0],
				'position_based',
				$conversion_value,
				100.00,
				1.0000,
				1
			);
			return;
		}

		if ( $count === 2 ) {
			// Two touchpoints: 50% each.
			foreach ( $touchpoints as $index => $touchpoint ) {
				$this->insert_attribution_credit(
					$conversion_id,
					$touchpoint,
					'position_based',
					$conversion_value * 0.5,
					50.00,
					0.5000,
					$index + 1
				);
			}
			return;
		}

		// 40% first, 40% last, 20% middle.
		$first_weight  = 0.40;
		$last_weight   = 0.40;
		$middle_weight = 0.20 / ( $count - 2 );

		foreach ( $touchpoints as $index => $touchpoint ) {
			if ( $index === 0 ) {
				// First touchpoint.
				$weight = $first_weight;
			} elseif ( $index === $count - 1 ) {
				// Last touchpoint.
				$weight = $last_weight;
			} else {
				// Middle touchpoints.
				$weight = $middle_weight;
			}

			$credit_percent = $weight * 100;
			$credit_value   = $conversion_value * $weight;

			$this->insert_attribution_credit(
				$conversion_id,
				$touchpoint,
				'position_based',
				$credit_value,
				$credit_percent,
				$weight,
				$index + 1
			);
		}
	}

	/**
	 * Insert attribution credit.
	 *
	 * @param int    $conversion_id Conversion ID.
	 * @param array  $touchpoint Touchpoint data.
	 * @param string $model Attribution model.
	 * @param float  $credit_value Credit value.
	 * @param float  $credit_percent Credit percentage.
	 * @param float  $weight Attribution weight.
	 * @param int    $touchpoint_order Touchpoint order.
	 */
	private function insert_attribution_credit( $conversion_id, $touchpoint, $model, $credit_value, $credit_percent, $weight, $touchpoint_order ) {
		global $wpdb;
		$attribution_data = array(
			'conversion_id'      => $conversion_id,
			'touchpoint_id'      => $touchpoint['touchpoint_id'],
			'attribution_model'  => $model,
			'credit_value'       => round( $credit_value, 2 ),
			'credit_percent'     => round( $credit_percent, 2 ),
			'attribution_weight' => round( $weight, 4 ),
			'utm_source'         => $touchpoint['utm_source'],
			'utm_medium'         => $touchpoint['utm_medium'],
			'utm_campaign'       => $touchpoint['utm_campaign'],
			'channel'            => $touchpoint['channel'],
			'touchpoint_order'   => $touchpoint_order,
			'created_at'         => current_time( 'mysql', true ),
		);

		$wpdb->insert(
			$wpdb->prefix . 'tracksure_conversion_attribution',
			$attribution_data,
			array( '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Get attribution for a conversion.
	 *
	 * @param int    $conversion_id Conversion ID.
	 * @param string $model Attribution model (optional).
	 * @return array Attribution data.
	 */
	public function get_conversion_attribution( $conversion_id, $model = null ) {
		global $wpdb;
		$sql    = "SELECT attribution_id, conversion_id, touchpoint_id, attribution_model, credit_value, credit_percent, 
                       attribution_weight, utm_source, utm_medium, utm_campaign, channel, touchpoint_order, created_at 
                FROM {$wpdb->prefix}tracksure_conversion_attribution WHERE conversion_id = %d";
		$params = array( $conversion_id );

		if ( $model ) {
			$sql     .= ' AND attribution_model = %s';
			$params[] = $model;
		}

		$sql .= ' ORDER BY touchpoint_order ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL dynamically built and safely prepared with $wpdb->prepare()
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}
}
