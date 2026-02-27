<?php

/**
 * Event recorder service for persisting tracking events.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB -- Debug logging + direct DB queries required for event recording, $wpdb->prefix is safe

/**
 *
 * TrackSure Event Recorder
 *
 * Validates and records events to the database.
 * Handles event deduplication, enrichment, and queue management.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Event Recorder class.
 */
class TrackSure_Event_Recorder {



	/**
	 * Pending conversion attribution data — processed on shutdown after response is sent.
	 *
	 * @var array
	 */
	private $pending_conversions = array();

	/**
	 * Pending goal evaluation data — processed on shutdown after response is sent.
	 *
	 * @var array
	 */
	private $pending_goals = array();

	/**
	 * Instance.
	 *
	 * @var TrackSure_Event_Recorder
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Registry instance.
	 *
	 * @var TrackSure_Registry
	 */
	private $registry;

	/**
	 * Session manager instance.
	 *
	 * @var TrackSure_Session_Manager
	 */
	private $session_manager;

	/**
	 * Logger instance.
	 *
	 * @var TrackSure_Logger
	 */
	private $logger;

	/**
	 * Consent manager instance.
	 *
	 * @var TrackSure_Consent_Manager
	 */
	private $consent_manager;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Event_Recorder
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
		$this->db              = TrackSure_DB::get_instance();
		$this->registry        = TrackSure_Registry::get_instance();
		$this->session_manager = TrackSure_Session_Manager::get_instance();
		$this->consent_manager = TrackSure_Consent_Manager::get_instance();
		$this->logger          = new TrackSure_Logger();
	}



	/**
	 * Record an event.
	 *
	 * @param array $event_data Event data.
	 * @return array Result with success (bool), event_id (string), and errors (array).
	 */
	public function record( $event_data ) {
		$result = array(
			'success'  => false,
			'event_id' => null,
			'errors'   => array(),
		);

		// Validate required fields (event_id from browser is CRITICAL).
		$required_fields = array( 'event_name', 'client_id', 'session_id', 'event_id' );
		foreach ( $required_fields as $field ) {
			if ( empty( $event_data[ $field ] ) ) {
				$result['errors'][] = sprintf( 'Missing required field: %s', esc_html( $field ) );
			}
		}

		// Validate event_id is valid UUID.
		if ( ! empty( $event_data['event_id'] ) && ! TrackSure_Utilities::is_valid_uuid_v4( $event_data['event_id'] ) ) {
			$result['errors'][] = 'Invalid event_id format (must be UUID)';
		}

		if ( ! empty( $result['errors'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TrackSure] Event Recorder validation failed: ' . wp_json_encode( $result['errors'] ) );
			}
			return $result;
		}

		// SECURITY: Check rate limits (prevent abuse/spam/DDoS).
		$rate_limiter = TrackSure_Rate_Limiter::get_instance();
		$client_ip    = TrackSure_Utilities::get_client_ip();

		if ( ! $rate_limiter->check_rate_limit( $event_data['client_id'], $client_ip ) ) {
			// Silently reject (don't return error to avoid probing).
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] Event Recorder: Rate limit exceeded - silently rejecting' );
			}
			return $result; // Return empty result (fail closed)
		}

		// BOT DETECTION: Filter out known bots to keep analytics clean.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( $this->is_bot( $user_agent ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] Event Recorder: Bot detected - rejecting: ' . substr( $user_agent, 0, 100 ) );
			}
			return $result; // Silently reject bot traffic
		}

		// Validate event against registry.
		$event_params = isset( $event_data['event_params'] ) ? $event_data['event_params'] : array();
		$validation   = $this->registry->validate_event( $event_data['event_name'], $event_params );

		if ( ! $validation['valid'] ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TrackSure] Event Recorder: Registry validation FAILED - ' . wp_json_encode( $validation['errors'] ) );
			}
			$result['errors'] = $validation['errors'];
			return $result;
		}

		// Check consent.
		$consent_granted = $this->consent_manager->is_tracking_allowed();

		// Store consent state for later use.
		$event_data['_consent_granted'] = $consent_granted;

		// ========================================.
		// PHASE 1: EVENT DEDUPLICATION.
		// ========================================.
		// STRATEGY: Use deterministic event_id for browser+server deduplication.
		//
		// HOW IT WORKS:
		// - Browser SDK: Generates event_id = Hash(session_id + event_name + timestamp + product_id).
		// - Server hook: Generates event_id using SAME formula.
		// - Result: Browser and server create IDENTICAL event_id for same action.
		//
		// BENEFITS:
		// - Meta CAPI: Same event_id = proper deduplication in Ads Manager.
		// - Google Ads: Same client_id + timestamp = deduplication.
		// - TikTok/Twitter: Same event_id across Pixel + API = deduplication.
		// - Database: Only one record stored (browser_fired + server_fired flags).
		// // If existing event found: Update flags, queue for destinations, SKIP goal evaluation.
		$existing_event = $this->db->get_event_by_id( $event_data['event_id'] );

		if ( $existing_event ) {
			// Event already processed - just update flags.
			$update_data = array();

			// Update browser_fired flag if this is browser-side submission.
			if ( ! empty( $event_data['browser_fired'] ) && empty( $existing_event['browser_fired'] ) ) {
				$update_data['browser_fired']    = 1;
				$update_data['browser_fired_at'] = isset( $event_data['browser_fired_at'] ) ? $event_data['browser_fired_at'] : current_time( 'mysql', 1 );
			}

			// Update server_fired flag if this is server-side submission.
			if ( ! empty( $event_data['server_fired'] ) && empty( $existing_event['server_fired'] ) ) {
				$update_data['server_fired'] = 1;
			}

			// Merge event_params (server data is more authoritative for e-commerce events).
			if ( ! empty( $event_data['event_params'] ) ) {
				$decoded         = isset( $existing_event['event_params'] ) ? json_decode( $existing_event['event_params'], true ) : null;
				$existing_params = is_array( $decoded ) ? $decoded : array();
				$new_params      = $event_data['event_params'];

				// Server data wins for product/value fields (more reliable than browser).
				$merged_params               = array_merge( $existing_params, $new_params );
				$update_data['event_params'] = wp_json_encode( $merged_params );
			}

			// Update the event record.
			if ( ! empty( $update_data ) ) {
				$this->db->update_event( $existing_event['event_id'], $update_data );
			}

			// Return success with duplicate flag (use existing event_id).
			return array(
				'success'   => true,
				'event_id'  => $existing_event['event_id'],
				'duplicate' => true,
				'merged'    => true,
				'errors'    => array(),
			);
		}

		// ========================================.
		// CONTINUE WITH NEW EVENT RECORDING.
		// ========================================.
		// Get or create session.
		$session_context = $this->build_session_context( $event_data );

		$session = $this->session_manager->get_or_create_session(
			$event_data['session_id'],
			$event_data['client_id'],
			$session_context
		);

		if ( ! $session ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] Event Recorder: Session creation FAILED!' );
			}
			$result['errors'][] = 'Failed to create/retrieve session';
			return $result;
		}

		// Enrich event data.
		$enriched_data = $this->enrich_event_data( $event_data, $session );

		// URL NORMALIZATION CHECK: If URL should be excluded, skip recording.
		if ( $enriched_data === null ) {
			return $result; // Return failure (URL should not be tracked)
		}

		// Check if this is a conversion event.
		$event_schema     = $this->registry->get_event( $event_data['event_name'] );
		$is_conversion    = false;
		$conversion_value = null;

		if ( $event_schema && isset( $event_schema['is_conversion'] ) ) {
			$is_conversion = (bool) $event_schema['is_conversion'];
		}

		// Extract conversion value if present (check event_params first, then root level).
		if ( $is_conversion ) {
			// Check in event_params (browser events send here).
			if ( isset( $event_params['value'] ) ) {
				$conversion_value = (float) $event_params['value'];
			} elseif ( isset( $event_params['order_total'] ) ) {
				$conversion_value = (float) $event_params['order_total'];
			} elseif ( isset( $event_data['value'] ) ) { // Fallback to root level (server-side events).
				$conversion_value = (float) $event_data['value'];
			} elseif ( isset( $event_data['order_total'] ) ) {
				$conversion_value = (float) $event_data['order_total'];
			}
		}

		// OPTIMIZED: Use Event Queue for batch inserts (100 events per query).
		// Build complete event data array.
		$event_source = isset( $event_data['event_source'] ) ? sanitize_text_field( $event_data['event_source'] ) : 'server';

		// Determine browser_fired and server_fired flags based on event_source.
		// CRITICAL: Browser events should have browser_fired=1 and server_fired=0 (until server confirms).
		// CRITICAL: Server events should have browser_fired=0 and server_fired=1 (until browser confirms).
		$browser_fired = 0;
		$server_fired  = 1;

		if ( $event_source === 'browser' ) {
			// Browser-originated event
			$browser_fired = 1;
			$server_fired  = 0; // Server hasn't fired yet (will be updated if server hook fires)
		}

		// Allow explicit override from event_data (for manual control).
		if ( isset( $event_data['browser_fired'] ) ) {
			$browser_fired = intval( $event_data['browser_fired'] );
		}
		if ( isset( $event_data['server_fired'] ) ) {
			$server_fired = intval( $event_data['server_fired'] );
		}

		$complete_event_data = array(
			'event_id'          => sanitize_text_field( $event_data['event_id'] ), // UUID from browser
			'visitor_id'        => $session['visitor_id'],
			'session_id'        => $session['session_id'],
			'event_name'        => sanitize_text_field( $event_data['event_name'] ),
			'event_source'      => $event_source,
			'browser_fired'     => $browser_fired,
			'server_fired'      => $server_fired,
			'browser_fired_at'  => ! empty( $event_data['browser_fired_at'] ) ? sanitize_text_field( $event_data['browser_fired_at'] ) : null,
			'destinations_sent' => ( ! empty( $event_data['destinations_sent'] ) && $event_data['destinations_sent'] !== '' ) ? wp_json_encode( $event_data['destinations_sent'] ) : null,
			'event_params'      => $event_params, // Will be JSON encoded in insert_event()
			'user_data'         => isset( $event_data['user_data'] ) && is_array( $event_data['user_data'] ) ? $event_data['user_data'] : null,
			'ecommerce_data'    => isset( $event_data['ecommerce_data'] ) && is_array( $event_data['ecommerce_data'] ) ? $event_data['ecommerce_data'] : null,
			// CRITICAL: Browser now sends Unix timestamp in seconds (e.g., 1762902353)
			// Convert to MySQL DATETIME format (Y-m-d H:i:s) for storage
			'occurred_at'       => isset( $event_data['occurred_at'] ) ? ( function () use ( $event_data ) {
				$timestamp = (int) $event_data['occurred_at'];
				$datetime = gmdate( 'Y-m-d H:i:s', $timestamp );
				return $datetime;
			} )() : gmdate( 'Y-m-d H:i:s' ),
			'created_at'        => gmdate( 'Y-m-d H:i:s' ), // Server processing time (UTC)
			'page_url'          => isset( $enriched_data['page_url'] ) ? esc_url_raw( $enriched_data['page_url'] ) : null,
			'page_path'         => isset( $enriched_data['page_path'] )
				? sanitize_text_field( $enriched_data['page_path'] )
				: ( isset( $enriched_data['page_url'] ) ? wp_parse_url( $enriched_data['page_url'], PHP_URL_PATH ) : null ),
			'page_title'        => isset( $enriched_data['page_title'] ) ? sanitize_text_field( $enriched_data['page_title'] ) : null,
			'referrer'          => isset( $enriched_data['referrer'] ) ? esc_url_raw( $enriched_data['referrer'] ) : null,
			'user_agent'        => ! empty( $enriched_data['user_agent'] ) ? sanitize_text_field( $enriched_data['user_agent'] ) : null,
			'ip_address'        => isset( $enriched_data['ip_address'] ) ? $enriched_data['ip_address'] : null,
			'device_type'       => ! empty( $enriched_data['device_type'] ) ? sanitize_text_field( $enriched_data['device_type'] ) : null,
			'browser'           => ! empty( $enriched_data['browser'] ) ? sanitize_text_field( $enriched_data['browser'] ) : null,
			'os'                => ! empty( $enriched_data['os'] ) ? sanitize_text_field( $enriched_data['os'] ) : null,
			'country'           => ! empty( $enriched_data['country'] ) ? sanitize_text_field( $enriched_data['country'] ) : null,
			'region'            => ! empty( $enriched_data['region'] ) ? sanitize_text_field( $enriched_data['region'] ) : null,
			'city'              => ! empty( $enriched_data['city'] ) ? sanitize_text_field( $enriched_data['city'] ) : null,
			'is_conversion'     => $is_conversion ? 1 : 0,
			'conversion_value'  => $conversion_value,
			'consent_granted'   => $consent_granted ? 1 : 0,
		);

		// Enqueue for batch insert (100 events per INSERT query).
		TrackSure_Event_Queue::enqueue( $complete_event_data );

		$event_id = $event_data['event_id']; // Use original UUID

		$result['success']  = true;
		$result['event_id'] = $event_id;

		// Queue to outbox for server-side delivery (batch processing via Delivery Worker).
		$this->queue_to_outbox( $event_data, $enriched_data, $session );

		// NON-BLOCKING delivery for conversion events.
		// Instead of calling process_outbox() synchronously (which would make HTTP calls
		// to Meta CAPI / GA4 MP during the visitor's request and slow down the page),
		// we schedule it to run after the response is sent via the 'shutdown' hook.
		// This keeps the /collect endpoint fast even on slow shared hosting.
		if ( $is_conversion ) {
			// Use shutdown hook to deliver after response is sent to the visitor.
			// fastcgi_finish_request() or litespeed_finish_request() will flush the response
			// first if available, so the visitor never waits for external API calls.
			if ( ! has_action( 'shutdown', array( $this, 'deferred_conversion_delivery' ) ) ) {
				add_action( 'shutdown', array( $this, 'deferred_conversion_delivery' ) );
			}
		}

		// PROMPT DELIVERY: Nudge WP-Cron to run immediately after queuing.
		// spawn_cron() fires a non-blocking HTTP loopback to wp-cron.php,
		// which triggers the delivery worker in a separate PHP process.
		// WordPress auto-throttles this to once per WP_CRON_LOCK_TIMEOUT (60s),
		// so calling it on every event is safe — no extra HTTP calls are made
		// if cron was already spawned within the last 60 seconds.
		// This ensures events are delivered within ~60s even on low-traffic sites
		// without requiring system cron configuration or wp-config.php edits.
		if ( ! $is_conversion ) {
			spawn_cron();
		}

		/**
		 * Fires after an event is recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param string $event_id Event ID (UUID).
		 * @param array  $event_data Event data.
		 * @param array  $session Session data.
		 */
		do_action( 'tracksure_event_recorded', $event_id, $event_data, $session );

		// DEFERRED: Conversion attribution + goal evaluation run AFTER the response is sent.
		// These operations involve 8+ DB queries (with multi-model attribution, up to 23+
		// queries for 3 touchpoints). Running them inline would block the visitor's request
		// on /collect or WooCommerce thank-you page.
		// Instead, we queue them to the same 'shutdown' hook that handles delivery.
		// fastcgi_finish_request() / litespeed_finish_request() flush the response first.
		if ( $is_conversion && $event_id ) {
			$this->pending_conversions[] = array(
				'visitor_id'       => $session['visitor_id'],
				'session_id'       => $session['session_id'],
				'event_id'         => $event_id,
				'conversion_type'  => $event_data['event_name'],
				'conversion_value' => $conversion_value,
				'currency'         => isset( $event_data['currency'] ) ? $event_data['currency'] : 'USD',
				'transaction_id'   => isset( $event_data['transaction_id'] ) ? $event_data['transaction_id'] : null,
				'items_count'      => isset( $event_data['items_count'] ) ? $event_data['items_count'] : 0,
				'converted_at'     => isset( $event_data['occurred_at'] ) ? $event_data['occurred_at'] : current_time( 'mysql', 1 ),
			);

			// Register shutdown handler once to process all pending conversions + goals.
			if ( ! has_action( 'shutdown', array( $this, 'deferred_conversion_attribution' ) ) ) {
				add_action( 'shutdown', array( $this, 'deferred_conversion_attribution' ) );
			}
		}

		// Goals also deferred — they query the DB for goal conditions + create conversion records.
		$this->pending_goals[] = array(
			'event_id'   => $event_id,
			'event_data' => $event_data,
			'session'    => $session,
		);
		if ( ! has_action( 'shutdown', array( $this, 'deferred_goal_evaluation' ) ) ) {
			add_action( 'shutdown', array( $this, 'deferred_goal_evaluation' ) );
		}

		return $result;
	}

	/**
	 * Build session context from event data.
	 *
	 * @param array $event_data Event data.
	 * @return array Session context.
	 */
	private function build_session_context( $event_data ) {
		$context = array();

		// Attribution fields (only fields that exist in sessions table).
		$attribution_fields = array(
			'referrer',
			'landing_page',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_term',
			'utm_content',
			'gclid',      // Google Ads
			'fbclid',     // Meta/Facebook Ads
			'msclkid',    // Microsoft/Bing Ads
			'ttclid',     // TikTok Ads
			'twclid',     // Twitter/X Ads
			'li_fat_id',  // LinkedIn Ads
			'irclickid',  // Impact Radius
			'ScCid',      // Snapchat Ads
		);

		foreach ( $attribution_fields as $field ) {
			if ( isset( $event_data[ $field ] ) ) {
				$context[ $field ] = $event_data[ $field ];
			}
		}

		// Use page_url as landing_page if not set (but don't include page_url itself).
		if ( isset( $event_data['page_url'] ) && empty( $context['landing_page'] ) ) {
			$context['landing_page'] = $event_data['page_url'];
		}

		// Device/browser fields.
		$device_fields = array( 'device_type', 'browser', 'os', 'country', 'region', 'city' );
		foreach ( $device_fields as $field ) {
			if ( isset( $event_data[ $field ] ) ) {
				$context[ $field ] = $event_data[ $field ];
			}
		}

		return $context;
	}

	/**
	 * Enrich event data with server-side information.
	 *
	 * @param array $event_data Raw event data.
	 * @param array $session Session data.
	 * @return array Enriched event data.
	 */
	private function enrich_event_data( $event_data, $session ) {
		$enriched = $event_data;

		// Preserve page_url and page_title from root level.
		if ( empty( $enriched['page_url'] ) && isset( $event_data['page_url'] ) ) {
			$enriched['page_url'] = $event_data['page_url'];
		}
		if ( empty( $enriched['page_title'] ) && isset( $event_data['page_title'] ) ) {
			$enriched['page_title'] = $event_data['page_title'];
		}

		// Fallback: Extract page_title from current WordPress page (for server-side events).
		if ( empty( $enriched['page_title'] ) ) {
			// Get from wp_title filter (most accurate).
			$enriched['page_title'] = wp_get_document_title();

			// If still empty, try global post.
			if ( empty( $enriched['page_title'] ) && isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) ) {
				$enriched['page_title'] = get_the_title( $GLOBALS['post'] );
			}
		}

		// ========================================.
		// PHASE 1: Extract page_url from event_params (server-side events).
		// ========================================.
		// Server-side WooCommerce events store URL in event_params.item_url.
		// Extract it here so it's available throughout the system.
		if ( empty( $enriched['page_url'] ) && isset( $event_data['event_params'] ) ) {
			$event_params = $event_data['event_params'];

			// Decode JSON if needed.
			if ( is_string( $event_params ) ) {
				$decoded      = ! empty( $event_params ) ? json_decode( $event_params, true ) : null;
				$event_params = is_array( $decoded ) ? $decoded : array();
			}

			// Check for item_url (WooCommerce product events).
			if ( is_array( $event_params ) && isset( $event_params['item_url'] ) && ! empty( $event_params['item_url'] ) ) {
				$enriched['page_url'] = $event_params['item_url'];
			}
		}

		// ========================================.
		// PHASE 2: URL NORMALIZATION (SINGLE SOURCE OF TRUTH).
		// ========================================.
		// Apply URL normalization to ensure clean, consistent URLs while preserving marketing parameters.
		// This removes:
		// - Visual builder parameters (Elementor, Divi, etc.)
		// - AJAX endpoints (admin-ajax.php)
		// - WooCommerce order keys (/order-received/123/?key=xyz → /order-received/)
		// - Session IDs and unnecessary parameters
		// While keeping:
		// - UTM parameters (utm_source, utm_medium, etc.)
		// - Ad platform IDs (gclid, fbclid, etc.)
		// - Meaningful query strings
		if ( ! empty( $enriched['page_url'] ) ) {
			$normalized_url = TrackSure_URL_Normalizer::normalize(
				$enriched['page_url'],
				[
					'keep_marketing_params' => true,
					'apply_ecommerce_rules' => true,
					'remove_trailing_slash' => false,
				]
			);

			// If URL should be excluded (admin-ajax, builders, etc.), skip recording
			if ( $normalized_url === null ) {
				// Return empty result to signal this event should be skipped
				return null;
			}

			$enriched['page_url'] = $normalized_url;

			// Extract clean page_path for grouping
			$enriched['page_path'] = TrackSure_URL_Normalizer::get_clean_path( $normalized_url );
		}

		// Add user agent if not present.
		if ( empty( $enriched['user_agent'] ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$enriched['user_agent'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		// Add IP address if not present.
		if ( empty( $enriched['ip_address'] ) ) {
			$enriched['ip_address'] = TrackSure_Utilities::get_client_ip();

			// Anonymize based on consent and settings.
			// If consent denied, anonymize all PII (GDPR compliance).
			$consent_granted = $this->consent_manager->is_tracking_allowed();
			if ( ! $consent_granted ) {
				$enriched = $this->consent_manager->anonymize_if_needed( $enriched );
			} elseif ( get_option( 'tracksure_anonymize_ip', false ) ) {
				// If consent granted but IP anonymization enabled, only anonymize IP.
				$enriched['ip_address'] = TrackSure_Utilities::anonymize_ip( $enriched['ip_address'] );
			}
		}

		// Perform geolocation lookup if IP is available and location not yet set.
		if ( ! empty( $enriched['ip_address'] ) && empty( $enriched['country'] ) ) {
			$core        = TrackSure_Core::get_instance();
			$geolocation = $core->get_service( 'geolocation' );

			if ( $geolocation ) {
				$location = $geolocation->get_location_from_ip( $enriched['ip_address'] );

				if ( is_array( $location ) ) {
					$enriched['country'] = $location['country'];
					$enriched['region']  = $location['region'];
					$enriched['city']    = $location['city'];

					// Allow filtering of geolocation data (for localhost mocking, testing, etc.)
					$location = apply_filters( 'tracksure_geolocation_data', $location, $enriched['ip_address'] );
					if ( is_array( $location ) ) {
						$enriched['country'] = $location['country'];
						$enriched['region']  = $location['region'];
						$enriched['city']    = $location['city'];
					}
				}
			}
		}

		// ========================================.
		// PHASE: Browser/OS Detection with Session Fallback.
		// ========================================.
		// STRATEGY:
		// 1. Detect from current user_agent (browser events).
		// 2. Fallback to session data (server events without UA).
		// 3. This ensures ALL events in a session have consistent browser/OS.
		if ( ! empty( $enriched['user_agent'] ) ) {
			// Detect from current request (browser event).
			if ( empty( $enriched['device_type'] ) ) {
				$enriched['device_type'] = $this->detect_device_type( $enriched['user_agent'] );
			}
			if ( empty( $enriched['browser'] ) ) {
				$enriched['browser'] = $this->detect_browser( $enriched['user_agent'] );
			}
			if ( empty( $enriched['os'] ) ) {
				$enriched['os'] = $this->detect_os( $enriched['user_agent'] );
			}
		} elseif ( ! empty( $session ) ) {
			// No user_agent (server-side event) → Use session data as fallback.
			if ( empty( $enriched['device_type'] ) && ! empty( $session['device_type'] ) ) {
				$enriched['device_type'] = $session['device_type'];
			}
			if ( empty( $enriched['browser'] ) && ! empty( $session['browser'] ) ) {
				$enriched['browser'] = $session['browser'];
			}
			if ( empty( $enriched['os'] ) && ! empty( $session['os'] ) ) {
				$enriched['os'] = $session['os'];
			}
		}

		// Update session with device_type, browser, OS, country, and last_activity_at.
		// last_activity_at is updated on EVERY event to track session duration accurately.
		if ( ! empty( $session['session_id'] ) ) {
			global $wpdb;
			$update_data = array();

			// ALWAYS update last_activity_at on every event (for accurate session duration)
			$update_data['last_activity_at'] = gmdate( 'Y-m-d H:i:s' );

			// Update device/location info if available
			if ( ! empty( $enriched['device_type'] ) ) {
				$update_data['device_type'] = $enriched['device_type'];
			}
			if ( ! empty( $enriched['browser'] ) ) {
				$update_data['browser'] = $enriched['browser'];
			}
			if ( ! empty( $enriched['os'] ) ) {
				$update_data['os'] = $enriched['os'];
			}
			if ( ! empty( $enriched['country'] ) ) {
				$update_data['country'] = $enriched['country'];
			}
			if ( ! empty( $enriched['region'] ) ) {
				$update_data['region'] = $enriched['region'];
			}
			if ( ! empty( $enriched['city'] ) ) {
				$update_data['city'] = $enriched['city'];
			}

			if ( ! empty( $update_data ) ) {
				// Build format array matching the number of columns in $update_data (all strings).
				$data_format = array_fill( 0, count( $update_data ), '%s' );
				$wpdb->update(
					$wpdb->prefix . 'tracksure_sessions',
					$update_data,
					array( 'session_id' => $session['session_id'] ),
					$data_format,
					array( '%s' )
				);
			}
		}

		/**
		 * Filter enriched event data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $enriched Enriched event data.
		 * @param array $event_data Original event data.
		 * @param array $session Session data.
		 */
		return apply_filters( 'tracksure_enrich_event_data', $enriched, $event_data, $session );
	}







	/**
	 * Sanitize IP address.
	 *
	 * @param string $ip IP address.
	 * @return string|null Sanitized IP or null if invalid.
	 */
	private function sanitize_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
		return null;
	}

	/**
	 * Detect device type from user agent.
	 *
	 * @param string $user_agent User agent string.
	 * @return string Device type (mobile, tablet, desktop).
	 */
	private function detect_device_type( $user_agent ) {
		if ( preg_match( '/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $user_agent ) ) {
			return 'tablet';
		}
		if ( preg_match( '/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $user_agent ) ) {
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * Detect browser from user agent.
	 * Now supports Chromium-based Edge, Opera, Brave, Vivaldi, and more.
	 *
	 * @param string $user_agent User agent string.
	 * @return string Browser name.
	 */
	private function detect_browser( $user_agent ) {
		// Order matters - check specific browsers before generic ones.
		// Chromium Edge (Edg or EdgA for Android).
		if ( preg_match( '/Edg[\/A]/i', $user_agent ) ) {
			return 'Edge';
		} elseif ( preg_match( '/OPR\//i', $user_agent ) || preg_match( '/Opera/i', $user_agent ) ) { // Opera.
			return 'Opera';
		} elseif ( preg_match( '/Brave/i', $user_agent ) ) { // Brave.
			return 'Brave';
		} elseif ( preg_match( '/Vivaldi/i', $user_agent ) ) { // Vivaldi.
			return 'Vivaldi';
		} elseif ( preg_match( '/UCBrowser/i', $user_agent ) ) { // UC Browser.
			return 'UC Browser';
		} elseif ( preg_match( '/SamsungBrowser/i', $user_agent ) ) { // Samsung Internet.
			return 'Samsung Internet';
		} elseif ( preg_match( '/Chrome/i', $user_agent ) && ! preg_match( '/Edge/i', $user_agent ) ) { // Chrome (must check after Chromium-based browsers).
			return 'Chrome';
		} elseif ( preg_match( '/Safari/i', $user_agent ) && ! preg_match( '/Chrome/i', $user_agent ) ) { // Safari (must check after Chrome since Chrome includes Safari in UA).
			return 'Safari';
		} elseif ( preg_match( '/Firefox|FxiOS/i', $user_agent ) ) { // Firefox.
			return 'Firefox';
		} elseif ( preg_match( '/MSIE|Trident/i', $user_agent ) ) { // Internet Explorer.
			return 'Internet Explorer';
		}

		// Extension point for Free/Pro to add custom browser detection.
		return apply_filters( 'tracksure_detect_browser', 'Unknown', $user_agent );
	}

	/**
	 * Detect OS from user agent.
	 *
	 * @param string $user_agent User agent string.
	 * @return string OS name.
	 */
	private function detect_os( $user_agent ) {
		if ( preg_match( '/Windows NT 10/i', $user_agent ) ) {
			return 'Windows 10';
		} elseif ( preg_match( '/Windows NT 6.3/i', $user_agent ) ) {
			return 'Windows 8.1';
		} elseif ( preg_match( '/Windows/i', $user_agent ) ) {
			return 'Windows';
		} elseif ( preg_match( '/Mac OS X/i', $user_agent ) ) {
			return 'macOS';
		} elseif ( preg_match( '/Linux/i', $user_agent ) ) {
			return 'Linux';
		} elseif ( preg_match( '/Android/i', $user_agent ) ) {
			return 'Android';
		} elseif ( preg_match( '/iOS|iPhone|iPad/i', $user_agent ) ) {
			return 'iOS';
		}
		return 'Unknown';
	}

	/**
	 * Check if event triggers any goals and create conversions.
	 *
	 * @param int   $event_id Event ID.
	 * @param array $event_data Event data.
	 * @param array $session Session data.
	 */
	private function check_goals( $event_id, $event_data, $session ) {
		// Use the Goal Evaluator service (centralized goal evaluation logic).
		$evaluator = TrackSure_Goal_Evaluator::get_instance();
		$evaluator->evaluate_event( $event_id, $event_data, $session );
	}

	/**
	 * Create conversion record.
	 *
	 * @param int   $goal_id Goal ID.
	 * @param int   $event_id Event ID.
	 * @param array $event_data Event data.
	 * @param array $session Session data.
	 */
	private function create_conversion( $goal_id, $event_id, $event_data, $session ) {
		$event_params = isset( $event_data['event_params'] ) ? $event_data['event_params'] : array();

		$conversion_data = array(
			'goal_id'       => $goal_id,
			'visitor_id'    => $session['visitor_id'],
			'session_id'    => $session['session_id'],
			'event_id'      => $event_id,
			'value'         => isset( $event_params['value'] ) ? floatval( $event_params['value'] ) : 0.00,
			'currency'      => isset( $event_params['currency'] ) ? sanitize_text_field( $event_params['currency'] ) : 'USD',
			'snapshot_data' => wp_json_encode(
				array(
					'event_name'   => $event_data['event_name'],
					'event_params' => $event_params,
				)
			),
			'converted_at'  => current_time( 'mysql', 1 ),
		);

		$conversion_id = $this->db->insert_conversion( $conversion_data );

		if ( $conversion_id ) {
			/**
			 * Fires after a conversion is recorded.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $conversion_id Conversion ID.
			 * @param int   $goal_id Goal ID.
			 * @param array $event_data Event data.
			 * @param array $session Session data.
			 */
			do_action( 'tracksure_conversion_recorded', $conversion_id, $goal_id, $event_data, $session );
		}
	}


	/**
	 * Compiled bot regex pattern (cached across calls within the same request).
	 *
	 * @var string|null
	 */
	private static $bot_regex = null;
	/**
	 * Detect if user agent is a bot.
	 *
	 * Uses a single compiled regex instead of 120+ individual strpos() calls.
	 * The regex is compiled once per request and cached in a static property.
	 *
	 * @param string $user_agent User agent string.
	 * @return bool True if bot detected.
	 */
	private function is_bot( $user_agent ) {
		if ( empty( $user_agent ) ) {
			return false;
		}

		// Compile regex once per request (120+ patterns → 1 preg_match).
		if ( null === self::$bot_regex ) {
			$bot_patterns = array(
				// Search engine bots
				'bot',
				'crawl',
				'spider',
				'slurp',
				'google\-inspectiontool',
				'storebot\-google',
				'baiduspider',
				'duckduckgo',
				'exabot',
				'sogou',
				'ia_archiver',
				'seznambot',
				// Social media crawlers
				'facebookexternalhit',
				'facebot',
				'facebookbot',
				'twitterbot',
				'linkedinbot',
				'whatsapp',
				'telegram',
				'slackbot',
				'discordbot',
				'pinterestbot',
				'redditbot',
				'snapchatbot',
				'instagrambot',
				'tiktokbot',
				// Monitoring & uptime tools
				'pingdom',
				'uptimerobot',
				'statuspage',
				'lighthouse',
				'pagespeed',
				'gtmetrix',
				'webpagetest',
				'sitebulb',
				'newrelic',
				'datadog',
				'site24x7',
				'monitis',
				// SEO tools
				'ahrefsbot',
				'semrushbot',
				'mj12bot',
				'majestic',
				'dotbot',
				'opensiteexplorer',
				'screaming frog',
				'serpstatbot',
				'petalbot',
				'blexbot',
				'spinn3r',
				'sistrix',
				'seokicks',
				'linkdexbot',
				'lipperhey',
				'buzzbot',
				'rogerbot',
				'domainappender',
				// Security scanners
				'nmap',
				'nikto',
				'nessus',
				'openvas',
				'qualys',
				'acunetix',
				'burp',
				'owasp',
				'metasploit',
				'sqlmap',
				'havij',
				// Headless browsers
				'headlesschrome',
				'chrome\-lighthouse',
				'phantomjs',
				'slimerjs',
				'puppeteer',
				'playwright',
				'selenium',
				'webdriver',
				// Ad verification & scrapers
				'adsbot',
				'adbeat',
				'mediapartners\-google',
				'admantx',
				'integral ad science',
				'moatbot',
				'doubleclick',
				// Archive bots
				'archive\.org',
				'wayback',
				'heritrix',
				'httrack',
				'webarchiver',
				// Email clients
				'mail\.ru',
				'thunderbird',
				'mailchimp',
				// Dev & testing tools
				'curl',
				'wget',
				'python\-requests',
				'go\-http\-client',
				'apache\-httpclient',
				'postman',
				'insomnia',
				'httpie',
				'node\-fetch',
				'axios',
				// Content aggregators
				'feedfetcher',
				'feedly',
				'flipboard',
				'apple\-pubsub',
				'newsblur',
				'netvibes',
				// Scraping frameworks
				'scrapy',
				'beautifulsoup',
				'nutch',
				'mechanize',
				'jsoup',
				'gohttp',
				'grabber',
				// AI & ML bots
				'gptbot',
				'chatgpt\-user',
				'anthropic\-ai',
				'claude\-web',
				'cohere\-ai',
				'perplexitybot',
				'you\-bot',
				// Other known bots
				'check_http',
				'nagios',
				'zabbix',
				'prtg',
				'libwww',
				'snoopy',
				'feedparser',
				'simplepie',
				'magpie',
				'validator',
				'w3c',
				'wappalyzer',
				'builtwith',
				'netcraft',
				'censys',
				'shodan',
				'masscan',
				'zgrab',
				'clickagy',
				'megaindex',
				'cliqzbot',
				'applebot',
				'safednsbot',
				'ccbot',
				'gigabot',
				'facebookcatalog',
				'yacybot',
				'panscient',
			);

			self::$bot_regex = '/' . implode( '|', $bot_patterns ) . '/i';
		}

		// Single regex match instead of 120+ strpos() calls.
		if ( preg_match( self::$bot_regex, $user_agent ) ) {
			return true;
		}

		// Additional heuristics: suspiciously short user agent.
		if ( strlen( $user_agent ) > 0 && strlen( $user_agent ) < 20 ) {
			if ( stripos( $user_agent, 'mobile' ) === false && stripos( $user_agent, 'android' ) === false ) {
				return true;
			}
		}

		/** This filter is documented in class-tracksure-event-recorder.php */
		return (bool) apply_filters( 'tracksure_is_bot', false, $user_agent );
	}

	/**
	 * Deferred conversion attribution — runs AFTER the response is sent to the visitor.
	 *
	 * Processes all pending conversion records that were queued during event recording.
	 * Runs on 'shutdown' hook after fastcgi_finish_request() flushes the output buffer,
	 * so the visitor's browser gets the response immediately.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deferred_conversion_attribution() {
		// Flush the response to the visitor FIRST.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		if ( empty( $this->pending_conversions ) ) {
			return;
		}

		$conversion_recorder = TrackSure_Conversion_Recorder::get_instance();
		if ( ! $conversion_recorder ) {
			return;
		}

		foreach ( $this->pending_conversions as $conversion ) {
			try {
				$conversion_recorder->record_conversion( $conversion );
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[TrackSure] Deferred conversion attribution failed: ' . $e->getMessage() );
				}
			}
		}

		$this->pending_conversions = array();
	}

	/**
	 * Deferred goal evaluation — runs AFTER the response is sent to the visitor.
	 *
	 * Processes all pending goal checks that were queued during event recording.
	 * Runs on 'shutdown' hook so goal condition queries don't block the visitor.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deferred_goal_evaluation() {
		// Flush the response to the visitor FIRST.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		if ( empty( $this->pending_goals ) ) {
			return;
		}

		foreach ( $this->pending_goals as $goal_data ) {
			try {
				$this->check_goals(
					$goal_data['event_id'],
					$goal_data['event_data'],
					$goal_data['session']
				);
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[TrackSure] Deferred goal evaluation failed: ' . $e->getMessage() );
				}
			}
		}

		$this->pending_goals = array();
	}

	/**
	 * Deferred conversion delivery — runs AFTER the response is sent to the visitor.
	 *
	 * Called via 'shutdown' hook so the visitor's browser gets the response immediately.
	 * The actual HTTP calls to Meta CAPI / GA4 Measurement Protocol happen in the
	 * background after fastcgi_finish_request() flushes the output buffer.
	 *
	 * This ensures conversion events are delivered promptly (within same request cycle)
	 * without blocking the website visitor — critical for performance on shared hosting.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deferred_conversion_delivery() {
		// Flush the response to the visitor FIRST, then do the heavy lifting.
		// fastcgi_finish_request() (PHP-FPM) or litespeed_finish_request() (LiteSpeed)
		// sends the response immediately and continues PHP execution in the background.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		// Now safely process conversion events — visitor already has their response.
		$delivery_worker = TrackSure_Delivery_Worker::get_instance();
		if ( $delivery_worker ) {
			$delivery_worker->process_outbox( 10 );
		}
	}

	/**
	 * Queue event to outbox for server-side delivery.
	 *
	 * Events are queued to outbox for batch processing by Delivery Worker.
	 * This enables retry logic, rate limiting, and proper error handling.
	 *
	 * @param array $event_data Event data.
	 * @param array $enriched_data Enriched event data.
	 * @param array $session Session data.
	 * @return void
	 */
	private function queue_to_outbox( $event_data, $enriched_data, $session ) {
		global $wpdb;

		/**
		 * Get enabled destinations dynamically.
		 *
		 * Extensions add their destinations via 'tracksure_enabled_destinations' filter.
		 * This avoids hardcoding destination checks in core - each extension
		 * (Free/Pro/3rd-party) checks its own settings and adds to the list.
		 *
		 * @param array $enabled_destinations List of enabled destination IDs.
		 * @param array $event_data Raw event data.
		 * @param array $session Session data.
		 */
		$enabled_destinations = apply_filters( 'tracksure_enabled_destinations', array(), $event_data, $session );

		if ( empty( $enabled_destinations ) ) {
			return;
		}

		// Build complete event payload (will be mapped by Event Mapper during delivery).
		// IMPORTANT: Merge browser-provided user_data (fbp, fbc, email) with server-enriched data.
		$browser_user_data = isset( $event_data['user_data'] ) && is_array( $event_data['user_data'] ) ? $event_data['user_data'] : array();
		$server_user_data  = $this->build_user_data( $enriched_data, $session );

		// Browser data takes priority for fbp/fbc/email (more accurate).
		// Server data provides IP/user agent/geolocation.
		$merged_user_data = array_merge( $server_user_data, $browser_user_data );

		$payload = array(
			'event_id'        => $event_data['event_id'],
			'event_name'      => $event_data['event_name'],
			'event_params'    => isset( $event_data['event_params'] ) ? $event_data['event_params'] : array(),
			'occurred_at'     => isset( $event_data['occurred_at'] ) ? $event_data['occurred_at'] : current_time( 'mysql', 1 ),
			'client_id'       => $event_data['client_id'],
			'session_id'      => $event_data['session_id'],
			'user_data'       => $merged_user_data,
			'page_context'    => array(
				'page_url'      => isset( $enriched_data['page_url'] ) ? $enriched_data['page_url'] : null,
				'page_title'    => isset( $enriched_data['page_title'] ) ? $enriched_data['page_title'] : null,
				'page_path'     => isset( $enriched_data['page_path'] )
					? $enriched_data['page_path']
					: ( isset( $enriched_data['page_url'] ) ? wp_parse_url( $enriched_data['page_url'], PHP_URL_PATH ) : null ),
				'page_referrer' => isset( $enriched_data['referrer'] ) ? $enriched_data['referrer'] : null,
			),
			'session_context' => array(
				'device_type' => isset( $enriched_data['device_type'] ) ? $enriched_data['device_type'] : ( isset( $session['device_type'] ) ? $session['device_type'] : null ),
				'browser'     => isset( $enriched_data['browser'] ) ? $enriched_data['browser'] : ( isset( $session['browser'] ) ? $session['browser'] : null ),
				'os'          => isset( $enriched_data['os'] ) ? $enriched_data['os'] : ( isset( $session['os'] ) ? $session['os'] : null ),
				'country'     => isset( $enriched_data['country'] ) ? $enriched_data['country'] : ( isset( $session['country'] ) ? $session['country'] : null ),
				'region'      => isset( $enriched_data['region'] ) ? $enriched_data['region'] : ( isset( $session['region'] ) ? $session['region'] : null ),
				'city'        => isset( $enriched_data['city'] ) ? $enriched_data['city'] : ( isset( $session['city'] ) ? $session['city'] : null ),
			),
		);

		// Get TrackSure tables.
		// Build destinations_status object for per-destination tracking.
		$destinations_status = array();
		foreach ( $enabled_destinations as $destination ) {
			$destinations_status[ $destination ] = array(
				'status'      => 'pending',
				'retry_count' => 0,
				'queued_at'   => current_time( 'mysql', 1 ),
			);
		}

		// Check if event already queued to outbox (race condition prevention).
		// This can happen if browser and server fire event simultaneously.
		$existing_outbox = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT outbox_id FROM {$wpdb->prefix}tracksure_outbox WHERE event_id = %s LIMIT 1",
				$event_data['event_id']
			)
		);

		if ( $existing_outbox ) {
			return; // Already queued, skip duplicate
		}

		// ✅ OPTIMIZED: Create ONE row per event with destinations array.
		$outbox_data = array(
			'event_id'            => $event_data['event_id'],
			'destinations'        => wp_json_encode( $enabled_destinations ),
			'destinations_status' => wp_json_encode( $destinations_status ),
			'payload'             => wp_json_encode( $payload ),
			'status'              => 'pending',
			'retry_count'         => 0,
			'created_at'          => current_time( 'mysql', 1 ),
			'updated_at'          => current_time( 'mysql', 1 ),
		);

		$result = $wpdb->insert( $wpdb->prefix . 'tracksure_outbox', $outbox_data );

		if ( ! $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[TrackSure] Event Recorder: Failed to queue to outbox - event_id={$event_data['event_id']}, error=" . $wpdb->last_error );
		}

		// Invalidate cached outbox count so stats reflect the new item.
		wp_cache_delete( 'tracksure_outbox_pending_count', 'tracksure' );
	}

	/**
	 * Build user data from enriched data and session.
	 *
	 * @param array $enriched_data Enriched event data.
	 * @param array $session Session data.
	 * @return array User data.
	 */
	private function build_user_data( $enriched_data, $session ) {
		$user_data = array();

		// Get WordPress user if logged in.
		if ( isset( $session['user_id'] ) && $session['user_id'] > 0 ) {
			$user = get_userdata( $session['user_id'] );
			if ( $user ) {
				$user_data['email']      = $user->user_email;
				$user_data['first_name'] = $user->first_name;
				$user_data['last_name']  = $user->last_name;
				$user_data['user_id']    = (string) $user->ID;
			}
		}

		// Add location data if available.
		if ( isset( $enriched_data['country'] ) ) {
			$user_data['country'] = $enriched_data['country'];
		}
		if ( isset( $enriched_data['region'] ) ) {
			$user_data['state'] = $enriched_data['region'];
		}
		if ( isset( $enriched_data['city'] ) ) {
			$user_data['city'] = $enriched_data['city'];
		}

		// Add client IP (for server-side attribution).
		if ( isset( $enriched_data['ip_address'] ) ) {
			$user_data['client_ip_address'] = $enriched_data['ip_address'];
		}

		// Add user agent (for server-side attribution).
		if ( isset( $enriched_data['user_agent'] ) ) {
			$user_data['client_user_agent'] = $enriched_data['user_agent'];
		}

		return $user_data;
	}
}
