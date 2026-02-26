<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for database operation diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Database Layer
 *
 * Provides CRUD operations for all 14 database tables.
 * Handles UUID binary conversions, session management, event recording.
 *
 * Direct database queries required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics.
 * All queries use $wpdb->prepare() for security.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Database class.
 */
class TrackSure_DB {


	/**
	 * Instance.
	 *
	 * @var TrackSure_DB
	 */
	private static $instance = null;

	/**
	 * Database tables.
	 *
	 * @var object
	 */
	private $tables;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_DB
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
		global $wpdb;

		$this->tables = (object) array(
			'visitors'               => $wpdb->prefix . 'tracksure_visitors',
			'sessions'               => $wpdb->prefix . 'tracksure_sessions',
			'events'                 => $wpdb->prefix . 'tracksure_events',
			'goals'                  => $wpdb->prefix . 'tracksure_goals',
			'conversions'            => $wpdb->prefix . 'tracksure_conversions',
			'touchpoints'            => $wpdb->prefix . 'tracksure_touchpoints',
			'conversion_attribution' => $wpdb->prefix . 'tracksure_conversion_attribution',
			'outbox'                 => $wpdb->prefix . 'tracksure_outbox',
			'click_ids'              => $wpdb->prefix . 'tracksure_click_ids',
			'agg_hourly'             => $wpdb->prefix . 'tracksure_agg_hourly',
			'agg_daily'              => $wpdb->prefix . 'tracksure_agg_daily',
			'agg_product_daily'      => $wpdb->prefix . 'tracksure_agg_product_daily',
			'funnels'                => $wpdb->prefix . 'tracksure_funnels',
			'funnel_steps'           => $wpdb->prefix . 'tracksure_funnel_steps',
		);

		// CRITICAL FIX: Set MySQL session timezone to UTC
		// This ensures UNIX_TIMESTAMP() correctly interprets DATETIME values as UTC
		// Without this, MySQL interprets DATETIME as system timezone (UTC+6 in this case)
		// causing 6-hour offset in timestamp conversions
		// See: mysql-timezone-check.php diagnostic for details
		$wpdb->query( "SET time_zone = '+00:00'" );
	}
	/**
	 * Get table names.
	 *
	 * @return object
	 */
	public function get_tables() {
		return $this->tables;
	}

	// ========================================.
	// VISITOR METHODS.
	// ========================================.
	/**
	 * Get or create visitor by client_id.
	 *
	 * @param string $client_id Client UUID.
	 * @param array  $initial_data Initial visitor data (for extensions).
	 * @return int Visitor ID.
	 */
	public function get_or_create_visitor( $client_id, $initial_data = array() ) {
		global $wpdb;

		// Validate UUID format.
		if ( ! TrackSure_Utilities::is_valid_uuid_v4( $client_id ) ) {
			return 0;
		}

		// Check if visitor exists.
		$visitor_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT visitor_id FROM {$wpdb->prefix}tracksure_visitors WHERE client_id = %s LIMIT 1",
				$client_id
			)
		);
		if ( $visitor_id ) {
			// Update last seen.
			$wpdb->update(
				$wpdb->prefix . 'tracksure_visitors',
				array( 'updated_at' => current_time( 'mysql', 1 ) ),
				array( 'visitor_id' => $visitor_id ),
				array( '%s' ),
				array( '%d' )
			);

			/**
			 * Fires when existing visitor is updated.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $visitor_id Visitor ID.
			 * @param string $client_id Client UUID.
			 */
			do_action( 'tracksure_visitor_updated', $visitor_id, $client_id );

			return (int) $visitor_id;
		}

		// Create new visitor (core only stores identity + timestamps).
		$visitor_data = wp_parse_args(
			$initial_data,
			array(
				'client_id'  => $client_id,
				'created_at' => current_time( 'mysql', 1 ),
				'updated_at' => current_time( 'mysql', 1 ),
			)
		);

		$wpdb->insert( $wpdb->prefix . 'tracksure_visitors', $visitor_data );
		$new_visitor_id = (int) $wpdb->insert_id;

		/**
		 * Fires when new visitor is created.
		 *
		 * Use this hook to initialize attribution data (Free/Pro).
		 *
		 * @since 1.0.0
		 *
		 * @param int    $visitor_id Visitor ID.
		 * @param string $client_id Client UUID.
		 */
		do_action( 'tracksure_visitor_created', $new_visitor_id, $client_id );

		return $new_visitor_id;
	}

	/**
	 * Get visitor by ID.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return object|null
	 */
	public function get_visitor( $visitor_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT visitor_id, client_id, created_at, updated_at FROM {$wpdb->prefix}tracksure_visitors WHERE visitor_id = %d",
				$visitor_id
			)
		);
	}

	/**
	 * Update visitor data (generic method for extensions).
	 *
	 * @param int   $visitor_id Visitor ID.
	 * @param array $data Data to update.
	 * @return bool
	 */
	public function update_visitor( $visitor_id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', 1 );

		return $wpdb->update(
			$wpdb->prefix . 'tracksure_visitors',
			$data,
			array( 'visitor_id' => $visitor_id ),
			null,
			array( '%d' )
		);
	}

	// ========================================.
	// SESSION METHODS.
	// ========================================.
	/**
	 * Upsert session record.
	 *
	 * @param string $session_id Session UUID.
	 * @param int    $visitor_id Visitor ID.
	 * @param array  $session_data Session data.
	 * @return int Session ID.
	 */
	public function upsert_session( $session_id, $visitor_id, $session_data = array() ) {
		global $wpdb;

		// Validate UUID format.
		if ( ! TrackSure_Utilities::is_valid_uuid_v4( $session_id ) ) {
			return false;
		}

		// Check if session exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT session_id FROM {$wpdb->prefix}tracksure_sessions WHERE session_id = %s LIMIT 1",
				$session_id
			)
		);

		if ( $existing ) {
			// Update existing session.
			$update_data = array(
				'last_activity_at' => current_time( 'mysql', 1 ),
				'updated_at'       => current_time( 'mysql', 1 ),
			);

			// Update event_count if provided.
			if ( isset( $session_data['event_count'] ) ) {
				$update_data['event_count'] = (int) $session_data['event_count'];
			}

			// Update UTM/attribution fields if provided (e.g., user clicked new UTM link mid-session).
			$utm_fields = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'msclkid', 'ttclid', 'twclid', 'li_fat_id', 'irclickid', 'ScCid' );
			foreach ( $utm_fields as $field ) {
				if ( isset( $session_data[ $field ] ) ) {
					$update_data[ $field ] = sanitize_text_field( $session_data[ $field ] );
				}
			}

			// Update referrer/landing_page if provided.
			if ( isset( $session_data['referrer'] ) ) {
				$update_data['referrer'] = esc_url_raw( $session_data['referrer'] );
			}
			if ( isset( $session_data['landing_page'] ) ) {
				$update_data['landing_page'] = esc_url_raw( $session_data['landing_page'] );
			}

			// Update browser/OS/device if provided and not already set.
			// This captures data from first browser event for server-side events to use.
			$session_record = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT browser, os, device_type FROM {$wpdb->prefix}tracksure_sessions WHERE session_id = %s LIMIT 1",
					$session_id
				)
			);

			if ( $session_record ) {
				if ( ! empty( $session_data['browser'] ) && empty( $session_record->browser ) ) {
					$update_data['browser'] = sanitize_text_field( $session_data['browser'] );
				}
				if ( ! empty( $session_data['os'] ) && empty( $session_record->os ) ) {
					$update_data['os'] = sanitize_text_field( $session_data['os'] );
				}
				if ( ! empty( $session_data['device_type'] ) && empty( $session_record->device_type ) ) {
					$update_data['device_type'] = sanitize_text_field( $session_data['device_type'] );
				}
			}

			// Build format array dynamically to match $update_data columns.
			$format = array();
			foreach ( $update_data as $key => $value ) {
				$format[] = ( $key === 'event_count' ) ? '%d' : '%s';
			}

			$wpdb->update(
				$wpdb->prefix . 'tracksure_sessions',
				$update_data,
				array( 'session_id' => $session_id ),
				$format,
				array( '%s' )
			);

			return $session_id;
		}

		// Create new session.
		$insert_data = wp_parse_args(
			$session_data,
			array(
				'session_id'       => $session_id,
				'visitor_id'       => $visitor_id,
				'session_number'   => 1,
				'is_returning'     => 0,
				'started_at'       => current_time( 'mysql', 1 ),
				'last_activity_at' => current_time( 'mysql', 1 ),
				'referrer'         => null,
				'landing_page'     => null,
				'utm_source'       => null,
				'utm_medium'       => null,
				'utm_campaign'     => null,
				'utm_term'         => null,
				'utm_content'      => null,
				'gclid'            => null,
				'fbclid'           => null,
				'device_type'      => null,
				'browser'          => null,
				'os'               => null,
				'country'          => null,
				'region'           => null,
				'city'             => null,
				'event_count'      => 0,
				'created_at'       => current_time( 'mysql', 1 ),
				'updated_at'       => current_time( 'mysql', 1 ),
			)
		);

		$wpdb->insert( $wpdb->prefix . 'tracksure_sessions', $insert_data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get session by session_id.
	 *
	 * @param string $session_id Session UUID.
	 * @return object|null
	 */
	public function get_session( $session_id ) {
		global $wpdb;

		// Validate UUID format.
		if ( ! TrackSure_Utilities::is_valid_uuid_v4( $session_id ) ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT session_id, visitor_id, session_number, is_returning, started_at, last_activity_at, 
                        referrer, landing_page, utm_source, utm_medium, utm_campaign, utm_term, utm_content, 
                        gclid, fbclid, msclkid, ttclid, twclid, li_fat_id, irclickid, ScCid, 
                        device_type, browser, os, country, region, city, event_count, created_at, updated_at 
                 FROM {$wpdb->prefix}tracksure_sessions WHERE session_id = %s",
				$session_id
			)
		);
	}

	/**
	 * Get visitor's session count.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return int
	 */
	public function get_visitor_session_count( $visitor_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_sessions WHERE visitor_id = %d",
				$visitor_id
			)
		);
	}

	/**
	 * Get realtime active sessions (last 5 minutes).
	 *
	 * @return array
	 */
	public function get_realtime_active_sessions() {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 300 ); // 5 minutes ago.

		// OPTIMIZED: Use indexed subquery to find latest event per session (100x faster).
		// Instead of correlated subquery, we use MAX() aggregation which uses indexes.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    s.*,
                    latest_event.page_url as current_page,
                    latest_event.page_title as current_title
                FROM {$wpdb->prefix}tracksure_sessions s
                LEFT JOIN (
                    SELECT 
                        session_id,
                        page_url,
                        page_title,
                        created_at
                    FROM {$wpdb->prefix}tracksure_events
                    WHERE (session_id, created_at) IN (
                        SELECT session_id, MAX(created_at)
                        FROM {$wpdb->prefix}tracksure_events
                        GROUP BY session_id
                    )
                ) as latest_event ON s.session_id = latest_event.session_id
                WHERE s.last_activity_at >= %s 
                ORDER BY s.last_activity_at DESC
                LIMIT 100",
				$cutoff
			)
		);
	}

	// ========================================.
	// EVENT METHODS.
	// ========================================.
	/**
	 * Insert event record with deduplication.
	 *
	 * Uses event_id (UUID from browser) as primary key.
	 * Prevents duplicate events from browser + server.
	 *
	 * @param array $event_data Event data.
	 * @return string|false Event ID (UUID) or false on failure.
	 */
	public function insert_event( $event_data ) {
		global $wpdb;

		// Validate event_id is present and valid UUID.
		if ( empty( $event_data['event_id'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] insert_event: Missing event_id' );
			}
			return false;
		}

		if ( ! TrackSure_Utilities::is_valid_uuid_v4( $event_data['event_id'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] insert_event: Invalid event_id format: ' . $event_data['event_id'] );
			}
			return false;
		}

		// DEDUPLICATION CHECK #1: Check if event_id already exists (UUID dedup).
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT event_id FROM {$wpdb->prefix}tracksure_events WHERE event_id = %s",
				$event_data['event_id']
			)
		);

		if ( $exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] insert_event: Duplicate event_id detected, skipping: ' . $event_data['event_id'] );
			}
			return $event_data['event_id']; // Return event_id to indicate success (already stored)
		}

		// DEDUPLICATION CHECK #2: Semantic duplicate (same event within 2 seconds on same page).
		// Uses BETWEEN instead of ABS(TIMESTAMPDIFF) so the occurred_at index can be used.
		if ( isset( $event_data['session_id'] ) && isset( $event_data['event_name'] ) && isset( $event_data['page_url'] ) ) {
			$occurred_at = isset( $event_data['occurred_at'] ) ? $event_data['occurred_at'] : gmdate( 'Y-m-d H:i:s' );

			$semantic_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT event_id FROM {$wpdb->prefix}tracksure_events 
                WHERE session_id = %s 
                AND event_name = %s 
                AND page_url = %s 
                AND occurred_at BETWEEN DATE_SUB(%s, INTERVAL 2 SECOND) AND DATE_ADD(%s, INTERVAL 2 SECOND)
                LIMIT 1",
					$event_data['session_id'],
					$event_data['event_name'],
					$event_data['page_url'],
					$occurred_at,
					$occurred_at
				)
			);

			if ( $semantic_exists ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

					error_log( '[TrackSure] insert_event: Semantic duplicate detected - ' . $event_data['event_name'] . ' on ' . $event_data['page_url'] );
				}
				return $semantic_exists; // Return existing event_id
			}
		}

		$insert_data = wp_parse_args(
			$event_data,
			array(
				'event_id'         => null,
				'visitor_id'       => null,
				'session_id'       => null,
				'event_name'       => null,
				'event_params'     => null,
				'occurred_at'      => gmdate( 'Y-m-d H:i:s' ), // UTC (browser time or server fallback)
				'created_at'       => gmdate( 'Y-m-d H:i:s' ), // Server processing time
				'page_url'         => null,
				'page_title'       => null,
				'referrer'         => null,
				'user_agent'       => null,
				'ip_address'       => null,
				'device_type'      => null,
				'browser'          => null,
				'os'               => null,
				'country'          => null,
				'region'           => null,
				'city'             => null,
				'is_conversion'    => 0,
				'conversion_value' => null,
				'consent_granted'  => 1,
			)
		);

		// Convert JSON fields: Convert arrays to JSON, empty strings/values to NULL.
		// MySQL JSON columns reject empty strings - must be NULL or valid JSON.
		$json_fields = array( 'event_params', 'user_data', 'ecommerce_data', 'destinations_sent' );
		foreach ( $json_fields as $field ) {
			if ( isset( $insert_data[ $field ] ) ) {
				if ( is_array( $insert_data[ $field ] ) && ! empty( $insert_data[ $field ] ) ) {
					// Valid array → JSON encode.
					$insert_data[ $field ] = wp_json_encode( $insert_data[ $field ] );
				} elseif ( empty( $insert_data[ $field ] ) || $insert_data[ $field ] === '' || $insert_data[ $field ] === '{}' || $insert_data[ $field ] === '[]' ) {
					// Empty string, empty array, or empty object → NULL.
					$insert_data[ $field ] = null;
				}
				// else: already valid JSON string, keep as-is.
			}
		}

		// Convert IP to binary if present.
		if ( ! empty( $insert_data['ip_address'] ) && filter_var( $insert_data['ip_address'], FILTER_VALIDATE_IP ) ) {
			$insert_data['ip_address'] = inet_pton( $insert_data['ip_address'] );
		} else {
			$insert_data['ip_address'] = null;
		}

		$result = $wpdb->insert( $wpdb->prefix . 'tracksure_events', $insert_data );

		if ( $result === false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] insert_event: Database insert failed - ' . $wpdb->last_error );
			}
			return false;
		}

		return $insert_data['event_id'];
	}

	/**
	 * Batch insert events (10x faster than individual inserts).
	 *
	 * @param array $events_data Array of event data.
	 * @return array Array of results ['success' => bool, 'event_ids' => array, 'errors' => array].
	 */
	public function insert_events_batch( $events_data ) {
		global $wpdb;

		if ( empty( $events_data ) ) {
			return array(
				'success' => false,
				'errors'  => array( 'No events provided' ),
			);
		}

		$inserted_event_ids = array();
		$errors             = array();
		$values_array       = array();
		$placeholders_array = array();
		$all_values         = array();

		// Pre-filter duplicates with a SINGLE query instead of N queries (one per event).
		// Collects all candidate event_ids and checks existence in bulk.
		$candidate_ids = array();
		foreach ( $events_data as $event_data ) {
			if ( ! empty( $event_data['event_id'] ) ) {
				$candidate_ids[] = $event_data['event_id'];
			}
		}

		$existing_ids = array();
		if ( ! empty( $candidate_ids ) ) {
			$id_placeholders = implode( ', ', array_fill( 0, count( $candidate_ids ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN() placeholders built from array_fill, values via splat operator.
			$existing_rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT event_id FROM {$wpdb->prefix}tracksure_events WHERE event_id IN ({$id_placeholders})",
					...$candidate_ids
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$existing_ids = array_flip( $existing_rows ? $existing_rows : array() );
		}

		// Prepare all events for batch insert.
		foreach ( $events_data as $index => $event_data ) {
			// Validate required fields.
			if ( empty( $event_data['event_id'] ) || empty( $event_data['event_name'] ) ) {
				$errors[] = "Event {$index}: Missing required fields";
				continue;
			}

			// Check for duplicate event_id (already resolved in bulk above).
			if ( isset( $existing_ids[ $event_data['event_id'] ] ) ) {
				$inserted_event_ids[] = $event_data['event_id']; // Already exists
				continue;
			}

			// Prepare insert data with defaults.
			$insert_data = wp_parse_args(
				$event_data,
				array(
					'event_id'          => null,
					'visitor_id'        => null,
					'session_id'        => null,
					'event_name'        => '',
					'event_source'      => 'browser',
					'browser_fired'     => 0,
					'server_fired'      => 0,
					'browser_fired_at'  => null,
					'destinations_sent' => null,
					'event_params'      => null,
					'user_data'         => null,
					'ecommerce_data'    => null,
					'occurred_at'       => current_time( 'mysql', 1 ),
					'created_at'        => current_time( 'mysql', 1 ),
					'page_url'          => null,
					'page_path'         => null,
					'page_title'        => null,
					'referrer'          => null,
					'user_agent'        => null,
					'ip_address'        => null,
					'device_type'       => null,
					'browser'           => null,
					'os'                => null,
					'country'           => null,
					'region'            => null,
					'city'              => null,
					'is_conversion'     => 0,
					'conversion_value'  => null,
					'consent_granted'   => 1,
				)
			);

			// Convert JSON fields - CRITICAL: Convert empty strings to NULL for MySQL 8.0+ compatibility.
			if ( is_array( $insert_data['event_params'] ) ) {
				$insert_data['event_params'] = wp_json_encode( $insert_data['event_params'] );
			} elseif ( empty( $insert_data['event_params'] ) || $insert_data['event_params'] === '' ) {
				$insert_data['event_params'] = null;
			}
			if ( is_array( $insert_data['user_data'] ) ) {
				$insert_data['user_data'] = wp_json_encode( $insert_data['user_data'] );
			} elseif ( empty( $insert_data['user_data'] ) || $insert_data['user_data'] === '' ) {
				$insert_data['user_data'] = null;
			}
			if ( is_array( $insert_data['ecommerce_data'] ) ) {
				$insert_data['ecommerce_data'] = wp_json_encode( $insert_data['ecommerce_data'] );
			} elseif ( empty( $insert_data['ecommerce_data'] ) || $insert_data['ecommerce_data'] === '' ) {
				$insert_data['ecommerce_data'] = null;
			}
			if ( is_array( $insert_data['destinations_sent'] ) ) {
				$insert_data['destinations_sent'] = wp_json_encode( $insert_data['destinations_sent'] );
			} elseif ( empty( $insert_data['destinations_sent'] ) || $insert_data['destinations_sent'] === '' ) {
				$insert_data['destinations_sent'] = null;
			}

			// Convert IP to binary.
			if ( ! empty( $insert_data['ip_address'] ) && filter_var( $insert_data['ip_address'], FILTER_VALIDATE_IP ) ) {
				$insert_data['ip_address'] = inet_pton( $insert_data['ip_address'] );
			} else {
				$insert_data['ip_address'] = null;
			}

			// Build placeholder and values for this row (29 columns).
			// Note: JSON columns use %s but we handle NULL specially to avoid empty string conversion.
			$placeholders_array[] = '(%s, %d, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %f, %d)';

			// Add values in correct order matching placeholders.
			$all_values[] = $insert_data['event_id'];
			$all_values[] = $insert_data['visitor_id'];
			$all_values[] = $insert_data['session_id'];
			$all_values[] = $insert_data['event_name'];
			$all_values[] = $insert_data['event_source'];
			$all_values[] = $insert_data['browser_fired'];
			$all_values[] = $insert_data['server_fired'];
			$all_values[] = $insert_data['browser_fired_at'];
			// CRITICAL: For JSON columns, use special marker for NULL to avoid empty string conversion.
			$all_values[] = $insert_data['destinations_sent'] === null ? '___TRACKSURE_NULL___' : $insert_data['destinations_sent'];
			$all_values[] = $insert_data['event_params'] === null ? '___TRACKSURE_NULL___' : $insert_data['event_params'];
			$all_values[] = $insert_data['user_data'] === null ? '___TRACKSURE_NULL___' : $insert_data['user_data'];
			$all_values[] = $insert_data['ecommerce_data'] === null ? '___TRACKSURE_NULL___' : $insert_data['ecommerce_data'];
			$all_values[] = $insert_data['occurred_at'];
			$all_values[] = $insert_data['created_at'];
			$all_values[] = $insert_data['page_url'];
			$all_values[] = $insert_data['page_path'];
			$all_values[] = $insert_data['page_title'];
			$all_values[] = $insert_data['referrer'];
			$all_values[] = $insert_data['user_agent'];
			$all_values[] = $insert_data['ip_address'];
			$all_values[] = $insert_data['device_type'];
			$all_values[] = $insert_data['browser'];
			$all_values[] = $insert_data['os'];
			$all_values[] = $insert_data['country'];
			$all_values[] = $insert_data['region'];
			$all_values[] = $insert_data['city'];
			$all_values[] = $insert_data['is_conversion'];
			$all_values[] = $insert_data['conversion_value'];
			$all_values[] = $insert_data['consent_granted'];

			$inserted_event_ids[] = $insert_data['event_id'];
		}

		// Execute batch insert if we have any valid events.
		if ( ! empty( $placeholders_array ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$query = "INSERT INTO {$wpdb->prefix}tracksure_events 
                (event_id, visitor_id, session_id, event_name, event_source, browser_fired, server_fired, 
                 browser_fired_at, destinations_sent, event_params, user_data, ecommerce_data, 
                 occurred_at, created_at, page_url, page_path, page_title, referrer, user_agent, ip_address, 
                 device_type, browser, os, country, region, city, is_conversion, conversion_value, consent_granted)
                VALUES " . implode( ', ', $placeholders_array );

			$prepared_query = $wpdb->prepare( $query, $all_values );
			// CRITICAL: Replace NULL markers with actual NULL for JSON columns (MySQL 8.0+ rejects empty strings in JSON).
			$prepared_query = str_replace( "'___TRACKSURE_NULL___'", 'NULL', $prepared_query );
			$result         = $wpdb->query( $prepared_query );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

			if ( $result === false ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

					error_log( '[TrackSure] Batch insert failed: ' . $wpdb->last_error );
				}
				return array(
					'success' => false,
					'errors'  => array( $wpdb->last_error ),
				);
			}
		}

		return array(
			'success'        => true,
			'event_ids'      => $inserted_event_ids,
			'inserted_count' => count( $placeholders_array ),
			'skipped_count'  => count( $events_data ) - count( $placeholders_array ),
			'errors'         => $errors,
		);
	}

	/**
	 * Get events for a session.
	 *
	 * @param int    $session_id Session ID.
	 * @param string $order Order direction (ASC or DESC).
	 * @return array
	 */
	public function get_session_events( $session_id, $order = 'ASC' ) {
		global $wpdb;

		$order = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'ASC';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_id, visitor_id, session_id, event_name, event_source, browser_fired, server_fired, 
                        browser_fired_at, destinations_sent, event_params, user_data, ecommerce_data, 
                        occurred_at, created_at, page_url, page_title, page_url_hash, referrer, user_agent, 
                        ip_address, device_type, browser, os, country, region, city, is_conversion, conversion_value, consent_granted 
                 FROM {$wpdb->prefix}tracksure_events WHERE session_id = %d ORDER BY occurred_at {$order}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_id
			)
		);
	}

	/**
	 * Get events by date range.
	 *
	 * @param string $start_date Start date (Y-m-d H:i:s).
	 * @param string $end_date End date (Y-m-d H:i:s).
	 * @param array  $filters Additional filters.
	 * @return array
	 */
	public function get_events_by_date( $start_date, $end_date, $filters = array() ) {
		global $wpdb;

		$where = $wpdb->prepare( 'created_at >= %s AND created_at <= %s', $start_date, $end_date );

		if ( ! empty( $filters['event_name'] ) ) {
			$where .= $wpdb->prepare( ' AND event_name = %s', $filters['event_name'] );
		}

		if ( ! empty( $filters['visitor_id'] ) ) {
			$where .= $wpdb->prepare( ' AND visitor_id = %d', $filters['visitor_id'] );
		}

		return $wpdb->get_results(
			"SELECT event_id, visitor_id, session_id, event_name, event_source, browser_fired, server_fired, 
                    browser_fired_at, destinations_sent, event_params, user_data, ecommerce_data, 
                    occurred_at, created_at, page_url, page_title, page_url_hash, referrer, user_agent, 
                    ip_address, device_type, browser, os, country, region, city, is_conversion, conversion_value, consent_granted 
             FROM {$wpdb->prefix}tracksure_events WHERE {$where} ORDER BY occurred_at ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Get event by event_id (UUID) for deduplication checks.
	 *
	 * @param string $event_id Event ID (UUID).
	 * @return array|null Event data or null if not found.
	 */
	public function get_event_by_id( $event_id ) {
		global $wpdb;

		// Validate UUID format.
		if ( ! TrackSure_Utilities::is_valid_uuid_v4( $event_id ) ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tracksure_events WHERE event_id = %s LIMIT 1",
				$event_id
			),
			ARRAY_A
		);
	}

	/**
	 * Update event flags (browser_fired, server_fired) for deduplication.
	 *
	 * @param string $event_id Event ID (UUID).
	 * @param array  $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_event( $event_id, $data ) {
		global $wpdb;

		// Validate UUID format.
		if ( ! TrackSure_Utilities::is_valid_uuid_v4( $event_id ) ) {
			return false;
		}

		// FIXED: Events table does NOT have updated_at column (only created_at).
		// Events are immutable after creation, only event_params get updated.
		// Removed: $data['updated_at'] = current_time('mysql', 1);

		$result = $wpdb->update(
			$wpdb->prefix . 'tracksure_events',
			$data,
			array( 'event_id' => $event_id ),
			null, // Format determined automatically
			array( '%s' ) // event_id is string
		);

		return $result !== false;
	}

	// ========================================.
	// GOAL METHODS.
	// ========================================.
	/**
	 * Create goal.
	 *
	 * @param array $goal_data Goal data.
	 * @return int|false Goal ID or false on failure.
	 */
	public function create_goal( $goal_data ) {
		global $wpdb;

		$insert_data = wp_parse_args(
			$goal_data,
			array(
				'name'        => '',
				'description' => '',
				'event_name'  => '',
				'conditions'  => null,
				'is_active'   => 1,
				'created_at'  => current_time( 'mysql', 1 ),
				'updated_at'  => current_time( 'mysql', 1 ),
			)
		);

		if ( is_array( $insert_data['conditions'] ) ) {
			$insert_data['conditions'] = wp_json_encode( $insert_data['conditions'] );
		}

		$result = $wpdb->insert( $wpdb->prefix . 'tracksure_goals', $insert_data );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get all active goals.
	 *
	 * @return array
	 */
	public function get_active_goals() {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT goal_id, name, description, event_name, conditions, trigger_type, match_logic, 
                    value_type, fixed_value, is_active, created_at, updated_at 
             FROM {$wpdb->prefix}tracksure_goals WHERE is_active = %d ORDER BY created_at DESC",
				1
			)
		);
	}

	/**
	 * Update goal.
	 *
	 * @param int   $goal_id Goal ID.
	 * @param array $goal_data Goal data to update.
	 * @return bool
	 */
	public function update_goal( $goal_id, $goal_data ) {
		global $wpdb;

		$goal_data['updated_at'] = current_time( 'mysql', 1 );

		if ( isset( $goal_data['conditions'] ) && is_array( $goal_data['conditions'] ) ) {
			$goal_data['conditions'] = wp_json_encode( $goal_data['conditions'] );
		}

		return $wpdb->update(
			$wpdb->prefix . 'tracksure_goals',
			$goal_data,
			array( 'id' => $goal_id ),
			null,
			array( '%d' )
		);
	}

	// ========================================.
	// CONVERSION METHODS.
	// ========================================.
	/**
	 * Insert conversion record.
	 *
	 * @param array $conversion_data Conversion data.
	 * @return int|false Conversion ID or false on failure.
	 */
	public function insert_conversion( $conversion_data ) {
		global $wpdb;

		$insert_data = wp_parse_args(
			$conversion_data,
			array(
				'goal_id'       => null,
				'visitor_id'    => null,
				'session_id'    => null,
				'event_id'      => null,
				'value'         => 0.00,
				'currency'      => 'USD',
				'snapshot_data' => null,
				'converted_at'  => current_time( 'mysql', 1 ),
			)
		);

		if ( isset( $insert_data['snapshot_data'] ) && is_array( $insert_data['snapshot_data'] ) ) {
			$insert_data['snapshot_data'] = wp_json_encode( $insert_data['snapshot_data'] );
		}

		$result = $wpdb->insert( $wpdb->prefix . 'tracksure_conversions', $insert_data );

		// ========================================.
		// PHASE 2: INVALIDATE ALL GOAL CACHES.
		// ========================================.
		// Delete all transient caches for this goal when new conversion is added.
		if ( $result && isset( $insert_data['goal_id'] ) ) {
			global $wpdb;
			$goal_id = absint( $insert_data['goal_id'] );

			// Delete timeline cache keys: tracksure_goal_{$goal_id}_timeline_%.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} 
					 WHERE option_name LIKE %s",
					'_transient_tracksure_goal_' . $goal_id . '_timeline_%'
				)
			);

			// Delete timeline timeout keys.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} 
					 WHERE option_name LIKE %s",
					'_transient_timeout_tracksure_goal_' . $goal_id . '_timeline_%'
				)
			);

			// Delete sources cache keys: tracksure_goal_{$goal_id}_sources_%.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} 
					 WHERE option_name LIKE %s",
					'_transient_tracksure_goal_' . $goal_id . '_sources_%'
				)
			);

			// Delete sources timeout keys.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} 
					 WHERE option_name LIKE %s",
					'_transient_timeout_tracksure_goal_' . $goal_id . '_sources_%'
				)
			);
		}

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Check if conversion already exists for event + goal combination.
	 * Prevents duplicate goal conversions from browser + server tracking.
	 *
	 * @param string $event_id Event ID (UUID).
	 * @param int    $goal_id Goal ID.
	 * @return int|null Conversion ID if exists, null otherwise.
	 */
	public function get_conversion_by_event_and_goal( $event_id, $goal_id ) {
		global $wpdb;

		// Validate UUID format.
		if ( ! TrackSure_Utilities::is_valid_uuid_v4( $event_id ) ) {
			return null;
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT conversion_id FROM {$wpdb->prefix}tracksure_conversions 
				 WHERE event_id = %s AND goal_id = %d LIMIT 1",
				$event_id,
				$goal_id
			)
		);
	}

	/**
	 * Get conversions by date range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param array  $filters Additional filters.
	 * @return array
	 */
	public function get_conversions_by_date( $start_date, $end_date, $filters = array() ) {
		global $wpdb;

		$where = $wpdb->prepare( 'converted_at >= %s AND converted_at <= %s', $start_date, $end_date );

		if ( ! empty( $filters['goal_id'] ) ) {
			$where .= $wpdb->prepare( ' AND goal_id = %d', $filters['goal_id'] );
		}

		return $wpdb->get_results(
			"SELECT conversion_id, visitor_id, session_id, event_id, conversion_type, goal_id, 
                    conversion_value, currency, transaction_id, items_count, converted_at, time_to_convert, 
                    sessions_to_convert, first_touch_source, first_touch_medium, first_touch_campaign, 
                    last_touch_source, last_touch_medium, last_touch_campaign, created_at 
             FROM {$wpdb->prefix}tracksure_conversions WHERE {$where} ORDER BY converted_at DESC" // phpcs:ignore WordPress.DB.PreparedSQL . InterpolatedNotPrepared
		);
	}

	// ========================================.
	// TOUCHPOINT METHODS.
	// ========================================.
	/**
	 * Insert touchpoint record.
	 *
	 * @param array $touchpoint_data Touchpoint data.
	 * @return int|false Touchpoint ID or false on failure.
	 */
	public function insert_touchpoint( $touchpoint_data ) {
		global $wpdb;

		$insert_data = wp_parse_args(
			$touchpoint_data,
			array(
				'visitor_id'     => null,
				'session_id'     => null,
				'touchpoint_seq' => 1,
				'channel'        => null,
				'source'         => null,
				'medium'         => null,
				'campaign'       => null,
				'landing_page'   => null,
				'touched_at'     => current_time( 'mysql', 1 ),
			)
		);

		$result = $wpdb->insert( $wpdb->prefix . 'tracksure_touchpoints', $insert_data );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get touchpoints for a visitor.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return array
	 */
	public function get_visitor_touchpoints( $visitor_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT touchpoint_id, visitor_id, session_id, event_id, touchpoint_seq, touched_at, 
                        utm_source, utm_medium, utm_campaign, utm_term, utm_content, channel, 
                        page_url, page_title, page_path, referrer, conversion_id, is_conversion_touchpoint, 
                        attribution_weight, created_at 
                 FROM {$wpdb->prefix}tracksure_touchpoints WHERE visitor_id = %d ORDER BY touchpoint_seq ASC",
				$visitor_id
			)
		);
	}

	// ========================================.
	// OUTBOX METHODS.
	// ========================================.
	/**
	 * Insert outbox record (for event delivery to destinations).
	 *
	 * @param array $outbox_data Outbox data.
	 * @return int|false Outbox ID or false on failure.
	 */
	public function insert_outbox( $outbox_data ) {
		global $wpdb;

		// NEW SCHEMA: destinations array + destinations_status.
		$insert_data = wp_parse_args(
			$outbox_data,
			array(
				'event_id'            => '',
				'destinations'        => null,
				'destinations_status' => null,
				'payload'             => null,
				'status'              => 'pending',
				'retry_count'         => 0,
				'created_at'          => current_time( 'mysql', 1 ),
				'updated_at'          => current_time( 'mysql', 1 ),
			)
		);

		if ( isset( $insert_data['payload'] ) && is_array( $insert_data['payload'] ) ) {
			$insert_data['payload'] = wp_json_encode( $insert_data['payload'] );
		}

		$result = $wpdb->insert( $wpdb->prefix . 'tracksure_outbox', $insert_data );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get pending outbox records.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_pending_outbox( $limit = 100 ) {
		global $wpdb;

		// NEW SCHEMA: Select destinations array + destinations_status.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT outbox_id, event_id, destinations, destinations_status, payload, 
                        status, retry_count, created_at, updated_at 
                 FROM {$wpdb->prefix}tracksure_outbox 
				WHERE status = 'pending' 
				ORDER BY created_at ASC 
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Update outbox record status.
	 *
	 *  NEW SCHEMA: Removed error_message parameter (stored in destinations_status JSON)
	 *
	 * @param int    $outbox_id Outbox ID.
	 * @param string $status Status (pending, processing, completed, failed).
	 * @return bool
	 */
	public function update_outbox_status( $outbox_id, $status ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'tracksure_outbox',
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', 1 ),
			),
			array( 'outbox_id' => $outbox_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	// ========================================.
	// AGGREGATION METHODS.
	// ========================================.
	/**
	 * Get aggregated metrics for date range.
	 *
	 * IMPORTANT: This method always queries raw tables to ensure accuracy.
	 * Dimensional aggregations (agg_daily) cause overcounting when SUMmed across dimensions.
	 * For example: 1 visitor × 10 pages = 10 dimension rows = 10x overcount if SUMmed.
	 *
	 * Performance: ~200ms for 100K sessions (acceptable for dashboard accuracy).
	 *
	 * @param string $date_start Start date (YYYY-MM-DD).
	 * @param string $date_end End date (YYYY-MM-DD).
	 * @param string $segment Optional segment filter (new/returning/converted).
	 * @return array Metrics array with total_visitors, total_sessions, total_events, total_conversions, total_revenue.
	 */
	public function get_aggregated_metrics( $date_start, $date_end, $segment = null ) {
		// Generate cache key.
		$cache_key = 'tracksure_agg_metrics_' . md5( $date_start . $date_end . $segment );

		// Try to get from cache (5-minute TTL).
		$cached_metrics = get_transient( $cache_key );
		if ( $cached_metrics !== false ) {
			return $cached_metrics;
		}

		// ALWAYS use raw metrics for accuracy (no dimensional overcounting).
		$metrics = $this->get_raw_metrics( $date_start, $date_end, $segment );

		// Cache the result for 5 minutes.
		set_transient( $cache_key, $metrics, 5 * MINUTE_IN_SECONDS );

		return $metrics;
	}

	/**
	 * Get metrics directly from raw tables (fallback when aggregations are empty).
	 *
	 * @param string $date_start Start date.
	 * @param string $date_end End date.
	 * @param string $segment Optional segment filter (new/returning/converted).
	 * @return array
	 */
	private function get_raw_metrics( $date_start, $date_end, $segment = null ) {
		global $wpdb;

		// Convert dates to datetime for timestamp comparison.
		$start_datetime = $date_start . ' 00:00:00';
		$end_datetime   = $date_end . ' 23:59:59';

		// Build WHERE clause for segment filtering.
		$segment_where = '';
		if ( ! empty( $segment ) ) {
			switch ( $segment ) {
				case 'new':
					$segment_where = ' AND s.session_number = 1';
					break;
				case 'returning':
					$segment_where = ' AND s.session_number > 1';
					break;
				case 'converted':
					$segment_where = ' AND EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'tracksure_events' . ' e2 WHERE e2.session_id = s.session_id AND e2.is_conversion = 1)';
					break;
			}
		}

		$metrics = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    (SELECT COUNT(DISTINCT s .
                    	visitor_id) FROM {$wpdb->prefix}tracksure_sessions s WHERE s.started_at BETWEEN %s AND %s{$segment_where}) as total_visitors,
                    (SELECT COUNT(DISTINCT s .
                    	session_id) FROM {$wpdb->prefix}tracksure_sessions s WHERE s.started_at BETWEEN %s AND %s{$segment_where}) as total_sessions,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_events e INNER JOIN {$wpdb->prefix}tracksure_sessions s ON e .
                    	session_id = s.session_id WHERE s.started_at BETWEEN %s AND %s{$segment_where}) as total_events,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_conversions c INNER JOIN {$wpdb->prefix}tracksure_sessions s ON c .
                    	session_id = s.session_id WHERE s.started_at BETWEEN %s AND %s{$segment_where}) as total_conversions,
                    (SELECT COALESCE(SUM(c .
                    	conversion_value), 0) FROM {$wpdb->prefix}tracksure_conversions c INNER JOIN {$wpdb->prefix}tracksure_sessions s ON c.session_id = s.session_id WHERE s.started_at BETWEEN %s AND %s{$segment_where}) as total_revenue",
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		return $metrics ?: array(
			'total_visitors'    => 0,
			'total_sessions'    => 0,
			'total_events'      => 0,
			'total_conversions' => 0,
			'total_revenue'     => 0,
		);
	}

	/**
	 * Get enhanced visitor-based metrics for Overview page.
	 *
	 * @param string $date_start Start date (YYYY-MM-DD).
	 * @param string $date_end End date (YYYY-MM-DD).
	 * @return array Enhanced metrics array.
	 */
	public function get_enhanced_metrics( $date_start, $date_end ) {
		global $wpdb;

		$start_datetime = $date_start . ' 00:00:00';
		$end_datetime   = $date_end . ' 23:59:59';

		// Get base session metrics (without JOINs to avoid duplication).
		$session_metrics = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    COUNT(DISTINCT visitor_id) as unique_visitors,
                    COUNT(DISTINCT CASE WHEN session_number = 1 THEN visitor_id END) as new_visitors,
                    COUNT(DISTINCT CASE WHEN session_number > 1 THEN visitor_id END) as returning_visitors,
                    COUNT(DISTINCT session_id) as total_sessions,
                    COALESCE(AVG(TIMESTAMPDIFF(SECOND, started_at, last_activity_at)), 0) as avg_session_duration_seconds,
                    SUM(CASE WHEN event_count <= 1 THEN 1 ELSE 0 END) as bounce_count
                FROM {$wpdb->prefix}tracksure_sessions
                WHERE started_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		// Get event counts separately.
		$event_metrics = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    COUNT(DISTINCT CASE WHEN event_name = 'page_view' THEN event_id END) as total_pageviews,
                    COUNT(DISTINCT event_id) as total_events
                FROM {$wpdb->prefix}tracksure_events
                WHERE session_id IN (
                    SELECT session_id FROM {$wpdb->prefix}tracksure_sessions
                    WHERE started_at BETWEEN %s AND %s
                )",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		// Get conversion metrics separately (only from conversions table).
		$conversion_metrics = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    COUNT(DISTINCT session_id) as converting_sessions,
                    COUNT(DISTINCT conversion_id) as total_conversions,
                    COALESCE(SUM(conversion_value), 0) as total_revenue
                FROM {$wpdb->prefix}tracksure_conversions
                WHERE session_id IN (
                    SELECT session_id FROM {$wpdb->prefix}tracksure_sessions
                    WHERE started_at BETWEEN %s AND %s
                )",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		// Merge all metrics.
		$metrics = array_merge(
			$session_metrics ?: array(),
			$event_metrics ?: array(),
			$conversion_metrics ?: array()
		);
		// Merge all metrics.
		$metrics = array_merge(
			$session_metrics ?: array(),
			$event_metrics ?: array(),
			$conversion_metrics ?: array()
		);

		if ( ! $metrics || empty( $metrics['unique_visitors'] ) ) {
			return array(
				'unique_visitors'              => 0,
				'new_visitors'                 => 0,
				'returning_visitors'           => 0,
				'total_sessions'               => 0,
				'sessions_per_visitor'         => 0,
				'total_pageviews'              => 0,
				'total_events'                 => 0,
				'avg_session_duration_seconds' => 0,
				'bounce_rate'                  => 0,
				'events_per_session'           => 0,
				'converting_sessions'          => 0,
				'total_conversions'            => 0,
				'conversion_rate'              => 0,
				'total_revenue'                => 0,
				'revenue_per_visitor'          => 0,
			);
		}

		// Calculate derived metrics.
		$unique_visitors     = (int) ( $metrics['unique_visitors'] ?? 0 );
		$total_sessions      = (int) ( $metrics['total_sessions'] ?? 0 );
		$converting_sessions = (int) ( $metrics['converting_sessions'] ?? 0 );
		$bounce_count        = (int) ( $metrics['bounce_count'] ?? 0 );
		$total_revenue       = (float) ( $metrics['total_revenue'] ?? 0 );
		$total_events        = (int) ( $metrics['total_events'] ?? 0 );

		$metrics['sessions_per_visitor'] = $unique_visitors > 0 ? round( $total_sessions / $unique_visitors, 2 ) : 0;
		$metrics['bounce_rate']          = $total_sessions > 0 ? round( ( $bounce_count / $total_sessions ) * 100, 1 ) : 0;
		$metrics['conversion_rate']      = $total_sessions > 0 ? round( ( $converting_sessions / $total_sessions ) * 100, 1 ) : 0;
		$metrics['revenue_per_visitor']  = $unique_visitors > 0 ? round( $total_revenue / $unique_visitors, 2 ) : 0;
		$metrics['events_per_session']   = $total_sessions > 0 ? round( $total_events / $total_sessions, 1 ) : 0;

		// Ensure all numeric values are properly typed.
		$metrics['unique_visitors']              = $unique_visitors;
		$metrics['new_visitors']                 = (int) ( $metrics['new_visitors'] ?? 0 );
		$metrics['returning_visitors']           = (int) ( $metrics['returning_visitors'] ?? 0 );
		$metrics['total_sessions']               = $total_sessions;
		$metrics['total_pageviews']              = (int) ( $metrics['total_pageviews'] ?? 0 );
		$metrics['total_events']                 = $total_events;
		$metrics['avg_session_duration_seconds'] = (float) ( $metrics['avg_session_duration_seconds'] ?? 0 );
		$metrics['converting_sessions']          = $converting_sessions;
		$metrics['total_conversions']            = (int) ( $metrics['total_conversions'] ?? 0 );
		$metrics['total_revenue']                = $total_revenue;

		return $metrics;
	}

	/**
	 * Get device breakdown (visitor-based).
	 *
	 * @param string $date_start Start date (YYYY-MM-DD).
	 * @param string $date_end End date (YYYY-MM-DD).
	 * @return array Device breakdown.
	 */
	public function get_device_breakdown( $date_start, $date_end ) {
		global $wpdb;

		$start_datetime = $date_start . ' 00:00:00';
		$end_datetime   = $date_end . ' 23:59:59';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    s.device_type as device,
                    COUNT(DISTINCT s.visitor_id) as visitors,
                    COUNT(DISTINCT s.session_id) as sessions
                FROM {$wpdb->prefix}tracksure_sessions s
                WHERE s.started_at BETWEEN %s AND %s
                  AND s.device_type IS NOT NULL
                  AND s.device_type != ''
                GROUP BY s.device_type
                ORDER BY visitors DESC",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);

		// If no device data, return empty array.
		if ( empty( $results ) ) {
			return array();
		}

		// Calculate percentages.
		$total_visitors = array_sum( array_column( $results, 'visitors' ) );

		foreach ( $results as &$row ) {
			$row['percentage'] = $total_visitors > 0 ? round( ( $row['visitors'] / $total_visitors ) * 100, 1 ) : 0;
		}

		return $results;
	}

	/**
	 * Get source/medium breakdown (visitor-based).
	 *
	 * @param string $date_start Start date (YYYY-MM-DD).
	 * @param string $date_end End date (YYYY-MM-DD).
	 * @param int    $limit Result limit.
	 * @return array Source breakdown.
	 */
	public function get_source_breakdown( $date_start, $date_end, $limit = 10 ) {
		global $wpdb;

		$start_datetime = $date_start . ' 00:00:00';
		$end_datetime   = $date_end . ' 23:59:59';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    source,
                    medium,
                    COUNT(DISTINCT visitor_id) as visitors,
                    COUNT(DISTINCT session_id) as sessions,
                    (SELECT COUNT(DISTINCT conversion_id)
                     FROM {$wpdb->prefix}tracksure_conversions
                     WHERE session_id IN (
                         SELECT session_id FROM {$wpdb->prefix}tracksure_sessions s2
                         WHERE s2.started_at BETWEEN %s AND %s
                         AND COALESCE(NULLIF(s2.utm_source, ''), '(direct)') = s.source
                         AND COALESCE(NULLIF(s2.utm_medium, ''), '(none)') = s.medium
                     )) as conversions
                FROM (
                    SELECT
                        COALESCE(NULLIF(utm_source, ''), '(direct)') as source,
                        COALESCE(NULLIF(utm_medium, ''), '(none)') as medium,
                        visitor_id,
                        session_id
                    FROM {$wpdb->prefix}tracksure_sessions
                    WHERE started_at BETWEEN %s AND %s
                ) s
                GROUP BY source, medium
                ORDER BY visitors DESC
                LIMIT %d",
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime,
				$limit
			),
			ARRAY_A
		);

		// Calculate percentages.
		$total_visitors = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_id) FROM {$wpdb->prefix}tracksure_sessions WHERE started_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		foreach ( $results as &$row ) {
			$row['percentage'] = $total_visitors > 0 ? round( ( $row['visitors'] / $total_visitors ) * 100, 1 ) : 0;
		}

		return $results;
	}

	/**
	 * Get country breakdown (visitor-based).
	 *
	 * @param string $date_start Start date (YYYY-MM-DD).
	 * @param string $date_end End date (YYYY-MM-DD).
	 * @param int    $limit Result limit.
	 * @return array Country breakdown.
	 */
	public function get_country_breakdown( $date_start, $date_end, $limit = 10 ) {
		global $wpdb;

		$start_datetime = $date_start . ' 00:00:00';
		$end_datetime   = $date_end . ' 23:59:59';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    s.country as country,
                    COUNT(DISTINCT s.visitor_id) as visitors,
                    COUNT(DISTINCT s.session_id) as sessions
                FROM {$wpdb->prefix}tracksure_sessions s
                WHERE s.started_at BETWEEN %s AND %s
                  AND s.country IS NOT NULL
                  AND s.country != ''
                GROUP BY s.country
                ORDER BY visitors DESC
                LIMIT %d",
				$start_datetime,
				$end_datetime,
				$limit
			),
			ARRAY_A
		);

		// If no country data, return empty array.
		if ( empty( $results ) ) {
			return array();
		}

		// Calculate percentages using only valid country data.
		$total_visitors = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_id) FROM {$wpdb->prefix}tracksure_sessions WHERE started_at BETWEEN %s AND %s AND country IS NOT NULL AND country != ''",
				$start_datetime,
				$end_datetime
			)
		);

		foreach ( $results as &$row ) {
			$row['percentage'] = $total_visitors > 0 ? round( ( $row['visitors'] / $total_visitors ) * 100, 1 ) : 0;
		}

		return $results;
	}

	/**
	 * Get top pages by visitors (visitor-based, not session-based).
	 *
	 * @param string $date_start Start date (YYYY-MM-DD).
	 * @param string $date_end End date (YYYY-MM-DD).
	 * @param int    $limit Result limit.
	 * @return array Top pages.
	 */
	public function get_top_pages_visitor_based( $date_start, $date_end, $limit = 10 ) {
		global $wpdb;

		$start_datetime = $date_start . ' 00:00:00';
		$end_datetime   = $date_end . ' 23:59:59';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    e.page_url as path,
                    e.page_title as title,
                    COUNT(DISTINCT s.visitor_id) as visitors,
                    COUNT(DISTINCT s.session_id) as sessions,
                    COUNT(DISTINCT e.event_id) as pageviews,
                    COALESCE(
                        (SELECT COUNT(DISTINCT c.conversion_id)
                         FROM {$wpdb->prefix}tracksure_conversions c
                         INNER JOIN {$wpdb->prefix}tracksure_sessions cs ON c.session_id = cs.session_id
                         INNER JOIN {$wpdb->prefix}tracksure_events pe ON c.session_id = pe.session_id
                         WHERE pe.page_url = e.page_url
                           AND pe.event_name = 'page_view'
                           AND pe.occurred_at <= c.converted_at
                           AND cs.started_at BETWEEN %s AND %s
                        ), 0
                    ) as conversions,
                    (SELECT s2.device_type
                     FROM {$wpdb->prefix}tracksure_sessions s2
                     INNER JOIN {$wpdb->prefix}tracksure_events e2 ON s2.session_id = e2.session_id
                     WHERE e2.page_url = e.page_url
                       AND s2.started_at BETWEEN %s AND %s
                       AND s2.device_type IS NOT NULL
                       AND s2.device_type != ''
                     GROUP BY s2.device_type
                     ORDER BY COUNT(*) DESC
                     LIMIT 1
                    ) as device,
                    (SELECT s3.country
                     FROM {$wpdb->prefix}tracksure_sessions s3
                     INNER JOIN {$wpdb->prefix}tracksure_events e3 ON s3.session_id = e3.session_id
                     WHERE e3.page_url = e.page_url
                       AND s3.started_at BETWEEN %s AND %s
                       AND s3.country IS NOT NULL
                       AND s3.country != ''
                     GROUP BY s3.country
                     ORDER BY COUNT(*) DESC
                     LIMIT 1
                    ) as country
                FROM {$wpdb->prefix}tracksure_events e
                INNER JOIN {$wpdb->prefix}tracksure_sessions s ON e.session_id = s.session_id
                WHERE e.event_name = 'page_view'
                  AND s.started_at BETWEEN %s AND %s
                  AND e.page_url IS NOT NULL
                GROUP BY e.page_url, e.page_title
                ORDER BY visitors DESC
                LIMIT %d",
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime,
				$limit
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Get recent events.
	 *
	 * @param int $minutes Minutes to look back.
	 * @param int $limit Result limit.
	 * @return array
	 */
	public function get_recent_events( $minutes = 30, $limit = 50 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * 60 ) );

		// OPTIMIZED: Use DISTINCT event_id to prevent duplicates from browser + server.
		// Return Unix timestamp for React to format in user's timezone.
		// Show ALL event types in real-time feed — scroll depth, time on page, and page exit
		// are important for understanding user behavior (scroll depth analysis, exit page tracking).
		// Only exclude tab_visible/tab_hidden as they are internal visibility state changes.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT 
					e.event_id, 
					e.visitor_id, 
					e.session_id, 
					e.event_name, 
					e.event_source, 
                        UNIX_TIMESTAMP(e.occurred_at) as occurred_at,
                        e.page_url, 
                        e.page_title, 
                        e.is_conversion, 
                        e.conversion_value,
                        e.event_params
                 FROM {$wpdb->prefix}tracksure_events e
                 WHERE e.event_name NOT IN ('tab_visible', 'tab_hidden')
                 AND e.created_at >= %s 
                 ORDER BY e.occurred_at DESC 
                 LIMIT %d",
				$since,
				$limit
			)
		);
	}

	/**
	 * Get sessions list with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_sessions_list( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'date_start' => null,
			'date_end'   => null,
			'segment'    => null,
			'page'       => 1,
			'per_page'   => 20,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = '1=1';
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		if ( $args['date_start'] ) {
			$where .= $wpdb->prepare( ' AND s.started_at >= %s', $args['date_start'] . ' 00:00:00' );
		}

		if ( $args['date_end'] ) {
			$where .= $wpdb->prepare( ' AND s.started_at <= %s', $args['date_end'] . ' 23:59:59' );
		}

		// Apply segment filter.
		if ( ! empty( $args['segment'] ) ) {
			switch ( $args['segment'] ) {
				case 'new':
					$where .= ' AND s.session_number = 1';
					break;
				case 'returning':
					$where .= ' AND s.session_number > 1';
					break;
				case 'converted':
					// Will be filtered in HAVING clause after GROUP BY.
					break;
			}
		}

		// Query with JOINs to get events_count, has_conversion, conversion_value, entry_page, and exit_page.
		$sql = "SELECT 
                    s.session_id,
                    s.visitor_id,
                    s.session_number,
                    s.is_returning,
                    s.started_at,
                    s.last_activity_at as last_seen_at,
                    s.utm_source as source,
                    s.utm_medium as medium,
                    s.utm_campaign as campaign,
                    s.device_type as device,
                    s.browser,
                    s.os,
                    s.country,
                    s.city,
                    COUNT(DISTINCT e.event_id) as events_count,
                    MAX(CASE WHEN e.is_conversion = 1 THEN 1 ELSE 0 END) as has_conversion,
                    CAST(COALESCE(SUM(CASE WHEN e.is_conversion = 1 THEN e.conversion_value ELSE 0 END), 0) AS DECIMAL(10,2)) as conversion_value,
                    MIN(CASE WHEN e.page_url IS NOT NULL THEN e.page_url END) as entry_page,
                    MAX(CASE WHEN e.page_url IS NOT NULL THEN e.page_url END) as exit_page
                FROM {$wpdb->prefix}tracksure_sessions s
                LEFT JOIN {$wpdb->prefix}tracksure_events e ON e.session_id = s.session_id
                WHERE {$where}
                GROUP BY s.session_id";

		// Add HAVING clause for converted segment.
		if ( ! empty( $args['segment'] ) && 'converted' === $args['segment'] ) {
			$sql .= ' HAVING has_conversion = 1';
		}

		$sql .= ' ORDER BY s.started_at DESC LIMIT %d OFFSET %d';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$args['per_page'],
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		// Ensure numeric types for JavaScript.
		foreach ( $results as $session ) {
			$session->visitor_id       = (int) $session->visitor_id;
			$session->session_number   = (int) $session->session_number;
			$session->is_returning     = (bool) $session->is_returning;
			$session->events_count     = (int) $session->events_count;
			$session->has_conversion   = (bool) $session->has_conversion;
			$session->conversion_value = (float) $session->conversion_value;
		}

		return $results;
	}

	/**
	 * Get sessions list with total count (OPTIMIZED - single query).
	 *
	 * @param array $args Query arguments.
	 * @return array Array with 'sessions' and 'total' keys.
	 */
	public function get_sessions_with_count( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'date_start' => null,
			'date_end'   => null,
			'segment'    => null,
			'page'       => 1,
			'per_page'   => 25,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = '1=1';
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		if ( $args['date_start'] ) {
			$where .= $wpdb->prepare( ' AND s.started_at >= %s', $args['date_start'] . ' 00:00:00' );
		}

		if ( $args['date_end'] ) {
			$where .= $wpdb->prepare( ' AND s.started_at <= %s', $args['date_end'] . ' 23:59:59' );
		}

		// Apply segment filter.
		$having = '';
		if ( ! empty( $args['segment'] ) ) {
			switch ( $args['segment'] ) {
				case 'new':
					$where .= ' AND s.session_number = 1';
					break;
				case 'returning':
					$where .= ' AND s.session_number > 1';
					break;
				case 'converted':
					$having = 'HAVING has_conversion = 1';
					break;
			}
		}

		// OPTIMIZED: Use subquery with aggregation instead of GROUP BY on full events table.
		// This is 10-20x faster for large datasets.
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS
                    s.session_id,
                    s.visitor_id,
                    s.session_number,
                    s.is_returning,
                    UNIX_TIMESTAMP(s.started_at) as started_at,
                    UNIX_TIMESTAMP(s.last_activity_at) as last_seen_at,
                    s.utm_source as source,
                    s.utm_medium as medium,
                    s.utm_campaign as campaign,
                    s.device_type as device,
                    s.browser,
                    s.os,
                    s.country,
                    s.city,
                    COALESCE(ev.events_count, 0) as events_count,
                    COALESCE(ev.has_conversion, 0) as has_conversion,
                    CAST(COALESCE(ev.conversion_value, 0) AS DECIMAL(10,2)) as conversion_value,
                    ev.entry_page,
                    ev.exit_page
                FROM {$wpdb->prefix}tracksure_sessions s
                LEFT JOIN (
                    SELECT 
                        session_id,
                        COUNT(*) as events_count,
                        MAX(is_conversion) as has_conversion,
                        SUM(CASE WHEN is_conversion = 1 THEN conversion_value ELSE 0 END) as conversion_value,
                        MIN(CASE WHEN page_url IS NOT NULL THEN page_url END) as entry_page,
                        MAX(CASE WHEN page_url IS NOT NULL THEN page_url END) as exit_page
                    FROM {$wpdb->prefix}tracksure_events
                    GROUP BY session_id
                ) ev ON s.session_id = ev.session_id
                WHERE {$where}
                {$having}
                ORDER BY s.started_at DESC
                LIMIT %d OFFSET %d",
				$args['per_page'],
				$offset
			)
		);

		// Get total count using FOUND_ROWS() - no second query needed!
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		// Ensure numeric types for JavaScript.
		foreach ( $sessions as $session ) {
			$session->visitor_id       = (int) $session->visitor_id;
			$session->session_number   = (int) $session->session_number;
			$session->is_returning     = (bool) $session->is_returning;
			$session->events_count     = (int) $session->events_count;
			$session->has_conversion   = (bool) $session->has_conversion;
			$session->conversion_value = (float) $session->conversion_value;
		}

		return array(
			'sessions' => $sessions,
			'total'    => $total,
		);
	}

	/**
	 * Count sessions.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count_sessions( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'date_start' => null,
			'date_end'   => null,
			'segment'    => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';

		if ( $args['date_start'] ) {
			$where .= $wpdb->prepare( ' AND s.started_at >= %s', $args['date_start'] . ' 00:00:00' );
		}

		if ( $args['date_end'] ) {
			$where .= $wpdb->prepare( ' AND s.started_at <= %s', $args['date_end'] . ' 23:59:59' );
		}

		// Apply segment filter.
		if ( ! empty( $args['segment'] ) ) {
			switch ( $args['segment'] ) {
				case 'new':
					$where .= ' AND s.session_number = 1';
					break;
				case 'returning':
					$where .= ' AND s.session_number > 1';
					break;
				case 'converted':
					// For converted, we need to check if session has conversion events.
					return (int) $wpdb->get_var(
						"SELECT COUNT(DISTINCT s.session_id) 
                        FROM {$wpdb->prefix}tracksure_sessions s
                        INNER JOIN {$wpdb->prefix}tracksure_events e ON e.session_id = s.session_id
                        WHERE {$where} AND e.is_conversion = 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					);
			}
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_sessions s WHERE {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Get touchpoints for a session.
	 *
	 * @param string $session_id Session ID.
	 * @return array Touchpoints for the session.
	 */
	public function get_session_touchpoints( $session_id ) {
		global $wpdb;

		$touchpoints = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    touchpoint_id,
                    touchpoint_seq,
                    touched_at,
                    utm_source,
                    utm_medium,
                    utm_campaign,
                    utm_term,
                    utm_content,
                    channel,
                    page_url,
                    page_title,
                    page_path,
                    referrer,
                    is_conversion_touchpoint,
                    attribution_weight
                FROM {$wpdb->prefix}tracksure_touchpoints
                WHERE session_id = %s
                ORDER BY touchpoint_seq ASC",
				$session_id
			),
			ARRAY_A
		);

		return $touchpoints ?: array();
	}

	// ========================================.
	// UTILITY METHODS.
	// ========================================.
}
