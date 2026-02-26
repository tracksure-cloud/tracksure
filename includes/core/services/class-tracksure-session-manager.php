<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for session lifecycle diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Session Manager
 *
 * Manages visitor sessions with 30-minute timeout, session numbering,
 * returning visitor detection, and session lifecycle.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Session Manager class.
 */
class TrackSure_Session_Manager {





	/**
	 * Instance.
	 *
	 * @var TrackSure_Session_Manager
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Session timeout in seconds.
	 *
	 * @var int
	 */
	private $session_timeout = 1800; // 30 minutes.

	/**
	 * Current session data.
	 *
	 * @var array|null
	 */
	private $current_session = null;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Session_Manager
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
		$this->session_timeout = absint( get_option( 'tracksure_session_timeout', 30 ) ) * 60;
	}

	/**
	 * Get client ID from browser or generate new one.
	 *
	 * Tries to read from JavaScript localStorage (tracksure_client_id) via cookie fallback.
	 * If not found, uses server-side fingerprint via transient (IP + UA + Accept-Language hash).
	 * Works in cookieless environments (Brave, Tor, Safari ITP, ad blockers).
	 *
	 * @return string Client UUID.
	 */
	public function get_client_id_from_browser() {
		// Try to get from cookie that JavaScript should set.
		if ( isset( $_COOKIE['_ts_cid'] ) ) {
			$client_id = sanitize_text_field( wp_unslash( $_COOKIE['_ts_cid'] ) );
			if ( TrackSure_Utilities::is_valid_uuid_v4( $client_id ) ) {
				return $client_id;
			}
		}

		// Fallback: Server-side fingerprint via short-lived transient.
		// REPLACES @session_start() which caused PHP file-based session locking,
		// serializing ALL concurrent requests for the same visitor.
		// This approach:
		// 1. Uses IP + User-Agent hash as a consistent fingerprint
		// 2. Stores the generated UUID in a transient (1 hour TTL)
		// 3. Works with cookieless browsers (Brave shield, Tor, Safari ITP, ad blockers)
		// 4. No file locking — fully concurrent on shared hosting
		// 5. With object cache (Redis/Memcached): 0 DB queries (in-memory)
		$fingerprint_key = $this->get_server_fingerprint_key( 'cid' );
		$cached_cid      = get_transient( $fingerprint_key );

		if ( $cached_cid && TrackSure_Utilities::is_valid_uuid_v4( $cached_cid ) ) {
			return $cached_cid;
		}

		// Generate new UUID v4 and store in transient (1 hour — covers a typical session).
		$client_id = $this->generate_uuid();
		set_transient( $fingerprint_key, $client_id, HOUR_IN_SECONDS );

		return $client_id;
	}

	/**
	 * Get session ID from browser or generate new one.
	 *
	 * Tries to read from JavaScript sessionStorage (tracksure_session_id) via cookie fallback.
	 * If not found, uses server-side fingerprint via transient (same approach as client ID).
	 * Works in cookieless environments without PHP session file locking.
	 *
	 * @return string Session UUID.
	 */
	public function get_session_id_from_browser() {
		// Try to get from cookie that JavaScript should set.
		if ( isset( $_COOKIE['_ts_sid'] ) ) {
			$session_id = sanitize_text_field( wp_unslash( $_COOKIE['_ts_sid'] ) );
			if ( TrackSure_Utilities::is_valid_uuid_v4( $session_id ) ) {
				return $session_id;
			}
		}

		// Fallback: Server-side fingerprint via short-lived transient.
		// Same approach as get_client_id_from_browser() — see comments there.
		$fingerprint_key = $this->get_server_fingerprint_key( 'sid' );
		$cached_sid      = get_transient( $fingerprint_key );

		if ( $cached_sid && TrackSure_Utilities::is_valid_uuid_v4( $cached_sid ) ) {
			return $cached_sid;
		}

		// Generate new UUID v4 and store in transient (30 min — session scope).
		$session_id = $this->generate_uuid();
		set_transient( $fingerprint_key, $session_id, 30 * MINUTE_IN_SECONDS );

		return $session_id;
	}

	/**
	 * Generate UUID v4.
	 *
	 * @return string UUID v4 string.
	 */
	private function generate_uuid() {
		return TrackSure_Utilities::generate_uuid_v4();
	}

	/**
	 * Get a deterministic transient key based on server-side fingerprint.
	 *
	 * Uses IP + User-Agent hash as a consistent, cookieless fingerprint.
	 * This replaces PHP session (@session_start) which causes file-based locking
	 * that serializes all concurrent requests from the same visitor.
	 *
	 * With object cache (Redis/Memcached): zero DB queries (pure in-memory lookup).
	 * Without object cache: falls back to wp_options transients (still no file locking).
	 *
	 * @param string $type Fingerprint type ('cid' for client, 'sid' for session).
	 * @return string Transient key for this visitor+type combination.
	 */
	private function get_server_fingerprint_key( $type ) {
		$ip = '';
		if ( class_exists( 'TrackSure_Utilities' ) && method_exists( 'TrackSure_Utilities', 'get_client_ip' ) ) {
			$ip = TrackSure_Utilities::get_client_ip();
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		// Include Accept-Language for better differentiation on corporate networks
		// where multiple users share the same IP + User-Agent (e.g., managed browsers).
		$accept_lang = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
			: '';

		// wp_hash() uses AUTH_SALT so the fingerprint is site-specific and irreversible.
		$hash = wp_hash( $ip . '|' . $ua . '|' . $accept_lang );

		// Transient key max 172 chars. 'ts_fp_' + type(3) + '_' + hash(64) = ~74 chars.
		return 'ts_fp_' . $type . '_' . $hash;
	}

	/**
	 * Get or create session.
	 *
	 * @param string $session_id Session UUID.
	 * @param string $client_id Client UUID.
	 * @param array  $session_data Session context data.
	 * @return array Session data with id, session_number, is_returning.
	 */
	public function get_or_create_session( $session_id, $client_id, $session_data = array() ) {
		// Get or create visitor (core only tracks identity, not attribution).
		$visitor_id = $this->db->get_or_create_visitor( $client_id, array() );

		// Check if session exists and is still valid.
		$existing_session = $this->db->get_session( $session_id );

		if ( $existing_session ) {
			// Check if session has timed out.
			$last_activity = strtotime( $existing_session->last_activity_at );
			$now           = time();

			if ( ( $now - $last_activity ) < $this->session_timeout ) {
				// Session is still valid - update last activity and UTM params if changed.
				$update_data = array(
					'event_count' => isset( $existing_session->event_count ) ? (int) $existing_session->event_count + 1 : 1,
				);

				// Update UTM parameters if new ones are provided (user clicked UTM link mid-session).
				if ( ! empty( $session_data['utm_source'] ) && $existing_session->utm_source !== $session_data['utm_source'] ) {
					$utm_fields = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid' );
					foreach ( $utm_fields as $field ) {
						if ( isset( $session_data[ $field ] ) ) {
							$update_data[ $field ] = $session_data[ $field ];
						}
					}
				}

				$this->db->upsert_session( $session_id, $visitor_id, $update_data );

				$this->current_session = (array) $existing_session;
				return $this->current_session;
			}

			// Session has timed out - create new session with incremented session_number.
			$session_number = (int) $existing_session->session_number + 1;
			$is_returning   = true;
		} else {
			// New session - check if returning visitor.
			$session_count  = $this->db->get_visitor_session_count( $visitor_id );
			$session_number = $session_count + 1;
			$is_returning   = $session_count > 0;
		}

		// Create new session.
		// ✅ FIX: Extract UTM parameters and attribution fields for new sessions.
		$utm_fields = array(
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_term',
			'utm_content',
			'gclid',      // Google Click ID
			'fbclid',     // Facebook Click ID
			'msclkid',    // Microsoft/Bing Ads Click ID
			'ttclid',     // TikTok Click ID
			'twclid',     // Twitter Click ID
			'li_fat_id',  // LinkedIn Click ID
			'irclickid',  // Impact Radius Click ID
			'ScCid',      // Snapchat Click ID
			'referrer',
			'landing_page',
		);

		$new_session_data = array(
			'session_number' => $session_number,
			'is_returning'   => $is_returning ? 1 : 0,
		);

		// Extract UTM/attribution fields from session_data.
		foreach ( $utm_fields as $field ) {
			if ( isset( $session_data[ $field ] ) ) {
				$new_session_data[ $field ] = $session_data[ $field ];
			}
		}

		// ✅ ADVANCED ATTRIBUTION: If no UTM parameters, use Attribution Resolver.
		// This handles organic search, social referrals, and direct traffic.
		// Always resolve — even without referrer (true direct traffic gets (direct)/(none)).
		if ( empty( $new_session_data['utm_source'] ) ) {
			$attribution_resolver = TrackSure_Attribution_Resolver::get_instance();
			$resolved             = $attribution_resolver->resolve( $new_session_data );

			// Populate source/medium/campaign from resolved attribution.
			$new_session_data['utm_source'] = $resolved['source'];
			$new_session_data['utm_medium'] = $resolved['medium'];
			if ( ! empty( $resolved['campaign'] ) ) {
				$new_session_data['utm_campaign'] = $resolved['campaign'];
			}
		}

		$db_session_id = $this->db->upsert_session( $session_id, $visitor_id, $new_session_data );

		// Retrieve full session data.
		$session_record        = $this->db->get_session( $session_id );
		$this->current_session = (array) $session_record;

		// Fire session_start event.
		if ( $db_session_id ) {
			// Ensure session_id UUID is available in hook data for touchpoint recording.
			$session_data['session_id'] = $session_id;

			// FIX: Merge resolved attribution into session_data for hooks.
			// Without this, touchpoints receive NULL utm_source for non-UTM traffic
			// (organic search, social, AI referral, direct) because the hook was
			// passing the original unresolved session_data instead of the resolved one.
			$session_data['utm_source']   = $new_session_data['utm_source'] ?? $session_data['utm_source'] ?? null;
			$session_data['utm_medium']   = $new_session_data['utm_medium'] ?? $session_data['utm_medium'] ?? null;
			$session_data['utm_campaign'] = $new_session_data['utm_campaign'] ?? $session_data['utm_campaign'] ?? null;

			/**
			 * Fires when a new session starts.
			 *
			 * Use this hook to implement attribution logic (Free/Pro).
			 *
			 * @since 1.0.0
			 *
			 * @param int    $db_session_id Session database ID.
			 * @param int    $visitor_id Visitor ID.
			 * @param array  $session_data Session context data (utm_*, referrer, session_id, etc).
			 * @param bool   $is_returning Is returning visitor.
			 * @param int    $session_number Session sequence number.
			 */
			do_action( 'tracksure_session_started', $db_session_id, $visitor_id, $session_data, $is_returning, $session_number );
		}

		return $this->current_session;
	}

	/**
	 * Get visitor ID for session.
	 *
	 * @param string $session_id Session UUID.
	 * @return int|null Visitor ID or null.
	 */
	public function get_session_visitor_id( $session_id ) {
		$session = $this->db->get_session( $session_id );
		return $session ? (int) $session->visitor_id : null;
	}

	/**
	 * Get current session.
	 *
	 * @return array|null
	 */
	public function get_current_session() {
		return $this->current_session;
	}

	/**
	 * Check if session is active.
	 *
	 * @param string $session_id Session UUID.
	 * @return bool
	 */
	public function is_session_active( $session_id ) {
		$session = $this->db->get_session( $session_id );
		if ( ! $session ) {
			return false;
		}

		$last_activity = strtotime( $session->last_activity_at );
		$now           = time();

		return ( $now - $last_activity ) < $this->session_timeout;
	}

	/**
	 * Get session timeout in seconds.
	 *
	 * @return int
	 */
	public function get_session_timeout() {
		return $this->session_timeout;
	}

	/**
	 * Get realtime active sessions.
	 *
	 * @return array
	 */
	public function get_realtime_sessions() {
		return $this->db->get_realtime_active_sessions();
	}

	/**
	 * Get visitor session count.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return int Session count.
	 */
	public function get_visitor_session_count( $visitor_id ) {
		return $this->db->get_visitor_session_count( $visitor_id );
	}
}
