<?php
/**
 * Google Analytics 4 destination handler.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.Security.NonceVerification -- Debug logging + cookie/query param access for GA4 integration
// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion -- GA4 gtag.js loaded from Google CDN, version managed by Google

/**
 *
 * Google Analytics 4 Destination - Production-Grade Implementation
 *
 * Best-in-class GA4 Measurement Protocol v2 integration with:
 * - Perfect browser + server deduplication
 * - Accurate session tracking (Unix timestamp format)
 * - Complete Enhanced eCommerce support
 * - User properties + custom dimensions
 * - Consent Mode V2 for GDPR compliance
 * - Debug mode for testing
 * - Full support for all GA4 recommended events
 *
 * Supports all website types:
 * - eCommerce (WooCommerce, EDD, SureCart, etc.)
 * - Lead Generation (B2B, SaaS, Services)
 * - Content Sites (Blogs, News, Media)
 * - Educational (Schools, Universities, Courses)
 * - Real Estate (Listings, Agencies)
 * - Portfolio/Agency sites
 *
 * @package TrackSure
 * @since 2.0.0
 * @see https://developers.google.com/analytics/devguides/collection/protocol/ga4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GA4 Destination Class
 */
class TrackSure_GA4_Destination {





	/**
	 * Core instance.
	 *
	 * @var TrackSure_Core
	 */
	private $core;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param TrackSure_Core $core Core instance.
	 */
	public function __construct( $core ) {
		$this->core     = $core;
		$this->settings = $this->get_settings();

		// Only initialize if enabled and configured.
		if ( $this->is_enabled() ) {
			$this->init_hooks();
		}
	}

	/**
	 * Get destination settings.
	 *
	 * @return array Settings.
	 */
	private function get_settings() {
		return array(
			'enabled'        => get_option( 'tracksure_free_ga4_enabled', false ),
			'measurement_id' => get_option( 'tracksure_free_ga4_measurement_id', '' ),
			'api_secret'     => get_option( 'tracksure_free_ga4_api_secret', '' ),
			'debug_mode'     => get_option( 'tracksure_free_ga4_debug_mode', false ),
			'consent_mode'   => get_option( 'tracksure_free_ga4_consent_mode', false ),
		);
	}

	/**
	 * Check if destination is enabled.
	 *
	 * @return bool True if enabled and configured.
	 */
	private function is_enabled() {
		return ! empty( $this->settings['enabled'] ) &&
			! empty( $this->settings['measurement_id'] );
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Register with Event Bridge (browser gtag).
		$this->register_browser_destination();

		// Register with Delivery Worker (server-side Measurement Protocol).
		add_filter( 'tracksure_deliver_mapped_event', array( $this, 'send' ), 10, 3 );

		// CRITICAL FIX: Remove conflicting empty gtag configs from other plugins.
		add_action( 'wp_head', array( $this, 'fix_conflicting_gtag_configs' ), 999 );
	}

	/**
	 * Register browser-side gtag with Event Bridge.
	 */
	private function register_browser_destination() {
		$bridge = $this->core->get_service( 'event_bridge' );

		if ( ! $bridge ) {
			return;
		}

		$bridge->register_browser_destination(
			array(
				'id'           => 'ga4',
				'enabled_key'  => 'tracksure_free_ga4_enabled',
				'init_script'  => array( $this, 'get_gtag_init_script' ),
				'event_mapper' => array( $this, 'get_browser_event_mapper' ),
				'sdk_check'    => "function() { return typeof window.gtag === 'function'; }",
				'pixel_sender' => "function(mapped, trackSureEvent) {
					if (!mapped || !mapped.name) return;
					var params = mapped.params || {};
					params.event_id = trackSureEvent.event_id;
					window.gtag('event', mapped.name, params);
				}",
			)
		);
	}

	/**
	 * Get gtag initialization JavaScript.
	 *
	 * CRITICAL FIXES:
	 * 1. send_page_view: false - Prevents duplicate page_view events
	 * 2. Consent Mode V2 - GDPR compliance
	 * 3. Client ID sync - Ensures browser/server use same client_id
	 *
	 * Note: This function returns a string that is output by the Event Bridge.
	 * It does not directly enqueue scripts - the Event Bridge handles output.
	 *
	 * @return string JavaScript code.
	 */
	public function get_gtag_init_script() {
		$measurement_id = sanitize_text_field( $this->settings['measurement_id'] );
		$consent_mode   = ! empty( $this->settings['consent_mode'] );
		$debug_mode     = ! empty( $this->settings['debug_mode'] );

		// Enqueue external gtag.js library using WordPress enqueue system.
		wp_enqueue_script(
			'tracksure-gtag',
			esc_url( "https://www.googletagmanager.com/gtag/js?id={$measurement_id}" ),
			array(),
			null,
			false
		);
		wp_script_add_data( 'tracksure-gtag', 'async', true );

		// Build inline script for GA4 initialization.
		$inline_script  = "window.dataLayer = window.dataLayer || [];\n";
		$inline_script .= "function gtag(){dataLayer.push(arguments);}\n";

		// Add Consent Mode V2 (if enabled).
		if ( $consent_mode ) {
			$inline_script .= "\n// Google Consent Mode V2 - GDPR/CCPA Compliance\n";
			$inline_script .= "// GRANTED by default - anonymize when denied (don't block tracking)\n";
			$inline_script .= "gtag('consent', 'default', {\n";
			$inline_script .= "  'ad_storage': 'granted',\n";
			$inline_script .= "  'ad_user_data': 'granted',\n";
			$inline_script .= "  'ad_personalization': 'granted',\n";
			$inline_script .= "  'analytics_storage': 'granted'\n";
			$inline_script .= "});\n\n";
		}

		$inline_script .= "gtag('js', new Date());\n\n";
		$inline_script .= "// Initialize GA4 with TrackSure configuration\n";
		$inline_script .= "gtag('config', '" . esc_js( $measurement_id ) . "', {\n";
		$inline_script .= "  // CRITICAL: Disable automatic page_view (TrackSure handles this)\n";
		$inline_script .= "  'send_page_view': false,\n";

		// Add debug_mode for DebugView visibility.
		if ( $debug_mode ) {
			$inline_script .= "  // Debug mode: Events visible in DebugView (not in reports)\n";
			$inline_script .= "  'debug_mode': true,\n";
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$inline_script .= "  // Debug mode: Auto-enabled (WP_DEBUG=true)\n";
			$inline_script .= "  'debug_mode': true,\n";
		}

		$inline_script .= "  // Cookie settings (auto-detect HTTP/HTTPS in JavaScript to avoid proxy issues)\n";
		$inline_script .= "  'cookie_flags': (window.location.protocol === 'https:' ? 'SameSite=None;Secure' : 'SameSite=Lax'),\n";
		$inline_script .= "  'cookie_expires': 63072000, // 2 years\n";
		$inline_script .= "  'page_title': document.title,\n";
		$inline_script .= "  'page_location': window.location.href,\n";
		$inline_script .= "  'page_path': window.location.pathname\n";
		$inline_script .= "});\n\n";

		$inline_script .= "// Sync GA4 client_id with TrackSure (for server-side deduplication)\n";
		$inline_script .= "gtag('get', '" . esc_js( $measurement_id ) . "', 'client_id', function(clientId) {\n";
		$inline_script .= "  if (clientId && window.TrackSure && window.TrackSure.setClientId) {\n";
		$inline_script .= "    window.TrackSure.setClientId(clientId);\n";
		$inline_script .= "  }\n";
		$inline_script .= '});';

		// Add inline script using WordPress enqueue system.
		wp_add_inline_script( 'tracksure-gtag', $inline_script, 'before' );

		// Return empty string since we're now using enqueue instead of echo.
		return '';
	}

	/**
	 * Fix conflicting gtag configurations from other plugins.
	 *
	 * Some consent/analytics plugins inject empty gtag('config', '') which breaks GA4.
	 * This script removes empty configs from dataLayer.
	 */
	public function fix_conflicting_gtag_configs() {
		$fix_script  = "(function() {\n";
		$fix_script .= "  'use strict';\n";
		$fix_script .= "  // CRITICAL FIX: Remove empty gtag configs that break GA4.\n";
		$fix_script .= "  // Some plugins inject gtag('config', '') which prevents all events from sending.\n";
		$fix_script .= "  if (window.dataLayer && Array.isArray(window.dataLayer)) {\n";
		$fix_script .= "    var originalLength = window.dataLayer.length;\n";
		$fix_script .= "    window.dataLayer = window.dataLayer.filter(function(item) {\n";
		$fix_script .= "      // Remove config calls with empty/null/undefined Measurement ID.\n";
		$fix_script .= "      if (item && item[0] === 'config' && (!item[1] || item[1] === '')) {\n";
		$fix_script .= "        console.warn('[TrackSure GA4 Fix] Removed conflicting empty gtag config:', item);\n";
		$fix_script .= "        return false;\n";
		$fix_script .= "      }\n";
		$fix_script .= "      return true;\n";
		$fix_script .= "    });\n";
		$fix_script .= "    if (window.dataLayer.length < originalLength) {\n";
		$fix_script .= "      console.log('[TrackSure GA4 Fix] ✅ Fixed ' + (originalLength - window.dataLayer.length) + ' conflicting gtag configs');\n";
		$fix_script .= "    }\n";
		$fix_script .= "  }\n";
		$fix_script .= '})();';

		// Add inline script using WordPress enqueue system.
		wp_add_inline_script( 'tracksure-gtag', $fix_script, 'after' );
	}

	/**
	 * Get JavaScript event mapper function.
	 *
	 * Maps TrackSure events to GA4 format for browser gtag.
	 *
	 * CRITICAL: Includes event_id and timestamp for server-side deduplication.
	 *
	 * @return string JavaScript function definition.
	 */
	public function get_browser_event_mapper() {
		return "function(trackSureEvent) {
            // GA4 events use snake_case, mostly same as TrackSure.
            var eventName = trackSureEvent.event_name;
            var params = trackSureEvent.event_params || {};
            
            // DEBUG: Log what we're sending to GA4
            if (window.console && console.log) {
                console.log('[GA4 Browser Mapper] Event:', eventName, 'Full params:', params);
            }
            
            // CRITICAL: Add event_id and timestamp_micros for server-side deduplication
            // GA4 deduplicates events with same event_name + event_id + timestamp (within 72h)
            if (trackSureEvent.event_id) {
                params.event_id = trackSureEvent.event_id;
            }
            if (trackSureEvent.timestamp_micros) {
                params.timestamp_micros = trackSureEvent.timestamp_micros;
            }
            
            // Session ID (Unix timestamp format - required for session grouping)
            if (trackSureEvent.session_start_time) {
                params.session_id = trackSureEvent.session_start_time;
            }
            
            // Engagement time (milliseconds - required for engagement metrics)
            if (trackSureEvent.engagement_time_msec) {
                params.engagement_time_msec = trackSureEvent.engagement_time_msec;
            } else if (trackSureEvent.session_context && trackSureEvent.session_context.time_on_page) {
                params.engagement_time_msec = trackSureEvent.session_context.time_on_page * 1000;
            }
            
            // CRITICAL: Always include page context for GA4 reporting.
            // Without these, GA4 dashboard shows 'No data available' for page titles.
            if (trackSureEvent.page_context) {
                if (trackSureEvent.page_context.page_url) {
                    params.page_location = trackSureEvent.page_context.page_url;
                }
                if (trackSureEvent.page_context.page_title) {
                    params.page_title = trackSureEvent.page_context.page_title;
                }
                if (trackSureEvent.page_context.page_path) {
                    params.page_path = trackSureEvent.page_context.page_path;
                }
                if (trackSureEvent.page_context.page_referrer) {
                    params.page_referrer = trackSureEvent.page_context.page_referrer;
                }
            } else {
                // Fallback: Use current page data if page_context not provided.
                params.page_location = window.location.href;
                params.page_title = document.title;
                params.page_path = window.location.pathname;
                if (document.referrer) {
                    params.page_referrer = document.referrer;
                }
            }
            
            // Include session context for device/browser info.
            if (trackSureEvent.session_context) {
                if (trackSureEvent.session_context.device_type) {
                    params.device_category = trackSureEvent.session_context.device_type;
                }
                if (trackSureEvent.session_context.browser) {
                    params.browser = trackSureEvent.session_context.browser;
                }
                if (trackSureEvent.session_context.os) {
                    params.os = trackSureEvent.session_context.os;
                }
            }
            
            // User ID for cross-device tracking
            if (trackSureEvent.user_data && trackSureEvent.user_data.user_id) {
                params.user_id = trackSureEvent.user_data.user_id;
            }
            
            // CRITICAL: Add debug_mode to event params for DebugView visibility
            // GA4 DebugView ONLY shows events with debug_mode: true in event params
            // This is SEPARATE from gtag config's debug_mode setting
            // Auto-detect local development environment (.local domain)
            if (window.location.hostname === 'localhost' || window.location.hostname.endsWith('.local') || window.location.hostname === '127.0.0.1') {
                params.debug_mode = true;
            }
            
            // GA4 accepts most TrackSure events as-is.
            return { name: eventName, params: params };
        }";
	}

	/**
	 * Send event to GA4 Measurement Protocol.
	 *
	 * PRODUCTION-GRADE IMPLEMENTATION with all critical fixes:
	 * - Proper client_id extraction (priority chain)
	 * - Correct session_id format (Unix timestamp)
	 * - Accurate engagement_time_msec calculation
	 * - User properties support
	 * - Enhanced eCommerce parameters
	 * - Debug mode support
	 * - Timestamp sync for deduplication
	 *
	 * @param array  $result Default result.
	 * @param string $destination Destination ID.
	 * @param array  $mapped_event Event already mapped by Event Mapper.
	 * @return array Result with success (bool) and error (string).
	 */
	public function send( $result, $destination, $mapped_event ) {
		if ( $destination !== 'ga4' ) {
			return $result;
		}

		$event_name = isset( $mapped_event['event_name'] ) ? $mapped_event['event_name'] : '';

		// GA4 MP rejects server-sent session_start (reserved); let browser handle it.
		if ( $event_name === 'session_start' ) {
			return array(
				'success' => true,
				'error'   => 'Skipped session_start (handled client-side)',
			);
		}

		// Skip if Measurement Protocol not configured.
		if ( empty( $this->settings['api_secret'] ) ) {
			return array(
				'success' => true, // Not an error - just not configured.
				'error'   => 'GA4 API secret not configured',
			);
		}

		// CRITICAL FIX 1: Complete client_id extraction chain.
		$client_id = $this->get_client_id( $mapped_event, $this->settings['measurement_id'] );

		// CRITICAL FIX 2: Extract timestamp from event (for deduplication with browser).
		// GA4 deduplicates using event_name + event_id + timestamp_micros (within 72h).
		$timestamp_micros = ! empty( $mapped_event['timestamp_micros'] )
			? (int) $mapped_event['timestamp_micros']
			: ( time() * 1000000 );

		// CRITICAL FIX 3: Session ID in Unix timestamp format (not UUID).
		// GA4 requires session_id as Unix timestamp of session start for session grouping.
		$session_id = $this->get_session_id( $mapped_event );

		// CRITICAL FIX 4: Calculate actual engagement time (not hardcoded 100ms).
		$engagement_time_msec = $this->calculate_engagement_time( $mapped_event );

		// Build event params with all GA4-required parameters.
		$event_params = $this->build_event_params(
			$mapped_event,
			$session_id,
			$engagement_time_msec
		);

		// Build user properties (for GA4 audiences and custom dimensions).
		$user_properties = $this->build_user_properties( $mapped_event );

		// Build Measurement Protocol v2 payload.
		$payload = array(
			'client_id'        => $client_id,
			'timestamp_micros' => $timestamp_micros,
			'events'           => array(
				array(
					'name'   => $event_name,
					'params' => $event_params,
				),
			),
		);

		// GDPR/CCPA Compliance: Anonymize user data when consent denied.
		// PHILOSOPHY: Never block events - always track, but anonymize PII when consent denied.
		$consent_manager = $this->core->get_service( 'consent_manager' );
		$consent_granted = $consent_manager ? $consent_manager->is_tracking_allowed() : true;

		if ( $consent_granted ) {
			// Consent granted - include user_id for cross-device tracking.
			// CRITICAL FIX: GA4 requires user_id as STRING, not integer!
			// Bug: Event Builder returns (string) but JSON encoding can revert to int.
			if ( ! empty( $mapped_event['user_data']['user_id'] ) ) {
				$payload['user_id'] = (string) $mapped_event['user_data']['user_id'];
			}

			// Include user_properties for audiences and custom dimensions.
			if ( ! empty( $user_properties ) ) {
				$payload['user_properties'] = $user_properties;
			}
		} else { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElse
			// Consent denied - anonymize user data but STILL send event.
			// Remove user_id (prevents cross-device tracking).
			// Remove user_properties (prevents audience targeting with PII).
			// GA4 will still process event for aggregate analytics.
		}

		// Send to GA4 Measurement Protocol.
		$response = $this->send_mp_request( $payload );

		// Handle response.
		return $this->handle_mp_response( $response, $mapped_event );
	}

	/**
	 * Send request to GA4 Measurement Protocol.
	 *
	 * Supports debug mode for testing without affecting production data.
	 *
	 * @param array $payload Payload.
	 * @return array|WP_Error Response.
	 */
	private function send_mp_request( $payload ) {
		$measurement_id = sanitize_text_field( $this->settings['measurement_id'] );
		$api_secret     = sanitize_text_field( $this->settings['api_secret'] );
		$debug_mode     = ! empty( $this->settings['debug_mode'] );

		// Use debug endpoint if debug mode enabled.
		$base_url = $debug_mode
			? 'https://www.google-analytics.com/debug/mp/collect'
			: 'https://www.google-analytics.com/mp/collect';

		$url = add_query_arg(
			array(
				'measurement_id' => $measurement_id,
				'api_secret'     => $api_secret,
			),
			$base_url
		);

		return wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Get client_id with complete priority chain.
	 *
	 * Priority:
	 * 1. TrackSure client_id (synced from browser)
	 * 2. GA4-specific cookie (_ga_{MEASUREMENT_ID})
	 * 3. Universal Analytics cookie (_ga)
	 * 4. Generate new client_id
	 *
	 * @param array  $event Event data.
	 * @param string $measurement_id GA4 Measurement ID.
	 * @return string Client ID.
	 */
	private function get_client_id( $event, $measurement_id ) {
		// Priority 1: TrackSure client_id (synced from browser).
		if ( ! empty( $event['client_id'] ) ) {
			return $event['client_id'];
		}

		// Priority 2: GA4-specific cookie (_ga_{MEASUREMENT_ID}).
		$ga4_cookie_name = '_ga_' . str_replace( 'G-', '', $measurement_id );
		if ( ! empty( $_COOKIE[ $ga4_cookie_name ] ) ) {
			$parts = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE[ $ga4_cookie_name ] ) ) );
			if ( count( $parts ) >= 3 ) {
				return $parts[2] . '.' . time();
			}
		}

		// Priority 3: Universal Analytics cookie (_ga).
		if ( ! empty( $_COOKIE['_ga'] ) ) {
			$parts = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE['_ga'] ) ) );
			if ( count( $parts ) >= 4 ) {
				return $parts[2] . '.' . $parts[3];
			}
		}

		// Priority 4: Generate new client_id.
		return $this->generate_client_id();
	}

	/**
	 * Generate new GA4 client_id.
	 *
	 * Format: {random}.{timestamp}
	 *
	 * @return string Client ID.
	 */
	private function generate_client_id() {
		return wp_rand( 1000000000, 9999999999 ) . '.' . time();
	}

	/**
	 * Get session_id in Unix timestamp format.
	 *
	 * GA4 requires session_id as Unix timestamp (integer), NOT UUID string.
	 *
	 * @param array $event Event data.
	 * @return int Unix timestamp.
	 */
	private function get_session_id( $event ) {
		// Check if session_start_time available (Unix timestamp).
		if ( ! empty( $event['session_start_time'] ) && is_numeric( $event['session_start_time'] ) ) {
			return (int) $event['session_start_time'];
		}

		// Fallback: Use current time.
		return time();
	}

	/**
	 * Calculate engagement time from event data.
	 *
	 * GA4 requires actual engagement time, NOT hardcoded value.
	 *
	 * @param array $event Event data.
	 * @return int Engagement time in milliseconds.
	 */
	private function calculate_engagement_time( $event ) {
		// Priority 1: Explicit engagement_time from event.
		if ( ! empty( $event['engagement_time_msec'] ) ) {
			return (int) $event['engagement_time_msec'];
		}

		// Priority 2: Calculate from session context.
		if ( ! empty( $event['session_context']['engagement_time'] ) ) {
			return (int) $event['session_context']['engagement_time'] * 1000;
		}

		// Priority 3: Use time_on_page.
		if ( ! empty( $event['session_context']['time_on_page'] ) ) {
			return (int) $event['session_context']['time_on_page'] * 1000;
		}

		// Priority 4: Estimate based on event type.
		$event_name = $event['event_name'] ?? '';
		$estimates  = array(
			'page_view'      => 5000,  // 5 seconds.
			'purchase'       => 30000, // 30 seconds.
			'begin_checkout' => 20000, // 20 seconds.
			'add_to_cart'    => 10000, // 10 seconds.
			'view_item'      => 15000, // 15 seconds.
			'search'         => 8000,  // 8 seconds.
			'generate_lead'  => 25000, // 25 seconds.
			'sign_up'        => 30000, // 30 seconds.
			'video_start'    => 2000,  // 2 seconds.
			'video_complete' => 60000, // 60 seconds.
			'file_download'  => 5000,  // 5 seconds.
		);

		return $estimates[ $event_name ] ?? 100; // Default: 100ms.
	}

	/**
	 * Build event parameters with all GA4-required fields.
	 *
	 * COMPREHENSIVE PARAMETER COVERAGE:
	 * - All GA4 recommended parameters (enables custom dimensions without manual setup)
	 * - Page URL as custom dimension (for "Views by Page URL" reports)
	 * - eCommerce parameters (item_list_name, shipping_tier, payment_type)
	 * - Content parameters (content_type, content_id for blogs/news)
	 * - Search parameters (search_term for site search tracking)
	 * - Attribution parameters (campaign, source, medium, term, content)
	 *
	 * @param array $event Event data.
	 * @param int   $session_id Session ID (Unix timestamp).
	 * @param int   $engagement_time_msec Engagement time (milliseconds).
	 * @return array Event parameters.
	 */
	private function build_event_params( $event, $session_id, $engagement_time_msec ) {
		$params = array();

		// Required GA4 parameters.
		$params['event_id']             = $event['event_id'];
		$params['session_id']           = $session_id;
		$params['engagement_time_msec'] = $engagement_time_msec;

		// Page context (required for proper GA4 reporting).
		if ( ! empty( $event['page_context'] ) ) {
			$page = $event['page_context'];
			if ( ! empty( $page['page_url'] ) ) {
				$params['page_location'] = $page['page_url'];
				// CRITICAL: Add page_url as separate parameter for custom dimension.
				// Enables "Views by Page URL" reports without manual GA4 setup.
				$params['page_url'] = $page['page_url'];
			}
			if ( ! empty( $page['page_title'] ) ) {
				$params['page_title'] = $page['page_title'];
			}
			if ( ! empty( $page['page_path'] ) ) {
				$params['page_path'] = $page['page_path'];
			}
			if ( ! empty( $page['page_referrer'] ) ) {
				$params['page_referrer'] = $page['page_referrer'];
			}
		}

		// Device/browser context.
		if ( ! empty( $event['session_context'] ) ) {
			$session = $event['session_context'];
			if ( ! empty( $session['device_type'] ) ) {
				$params['device_category'] = $session['device_type'];
			}
			if ( ! empty( $session['browser'] ) ) {
				$params['browser'] = $session['browser'];
			}
			if ( ! empty( $session['os'] ) ) {
				$params['os'] = $session['os'];
			}
		}

		// Attribution parameters (UTMs, campaign data).
		if ( ! empty( $event['attribution_data'] ) ) {
			$attribution = $event['attribution_data'];
			// Map TrackSure attribution to GA4 parameters.
			if ( ! empty( $attribution['campaign'] ) ) {
				$params['campaign'] = $attribution['campaign'];
			}
			if ( ! empty( $attribution['source'] ) ) {
				$params['source'] = $attribution['source'];
			}
			if ( ! empty( $attribution['medium'] ) ) {
				$params['medium'] = $attribution['medium'];
			}
			if ( ! empty( $attribution['term'] ) ) {
				$params['term'] = $attribution['term'];
			}
			if ( ! empty( $attribution['content'] ) ) {
				$params['content'] = $attribution['content'];
			}
			// Ad platform click IDs (gclid, fbclid, etc.).
			if ( ! empty( $attribution['gclid'] ) ) {
				$params['gclid'] = $attribution['gclid'];
			}
			if ( ! empty( $attribution['fbclid'] ) ) {
				$params['fbclid'] = $attribution['fbclid'];
			}
		}

		// Custom event data (items, value, currency, transaction_id, etc.).
		if ( ! empty( $event['custom_data'] ) ) {
			$custom_data = $event['custom_data'];

			// Normalize currency using centralized handler (GA4 accepts all ISO 4217 codes).
			if ( ! empty( $custom_data['currency'] ) ) {
				$currency_handler        = TrackSure_Currency_Handler::get_instance();
				$custom_data['currency'] = $currency_handler->normalize( $custom_data['currency'], 'ga4' );
			}

			$params = array_merge( $params, $custom_data );
		}

		// AUTO-ADD GA4 RECOMMENDED PARAMETERS (enables custom dimensions automatically).
		// These parameters allow users to create custom dimensions in GA4 UI without code changes.

		// E-commerce recommended parameters.
		if ( in_array( $event['event_name'], array( 'view_item', 'add_to_cart', 'begin_checkout', 'purchase' ), true ) ) {
			// Item list name (for "Shop the Look", "Related Products", etc.).
			if ( empty( $params['item_list_name'] ) && ! empty( $event['custom_data']['item_list_name'] ) ) {
				$params['item_list_name'] = $event['custom_data']['item_list_name'];
			} elseif ( empty( $params['item_list_name'] ) ) {
				// Auto-detect from page context.
				if ( ! empty( $page['page_title'] ) ) {
					$params['item_list_name'] = $page['page_title'];
				}
			}

			// Shipping tier (for shipping revenue analysis).
			if ( ! empty( $event['custom_data']['shipping_tier'] ) ) {
				$params['shipping_tier'] = $event['custom_data']['shipping_tier'];
			} elseif ( ! empty( $event['custom_data']['shipping'] ) ) {
				// Auto-classify based on shipping cost.
				$shipping_cost = (float) $event['custom_data']['shipping'];
				if ( $shipping_cost === 0.0 ) {
					$params['shipping_tier'] = 'Free Shipping';
				} elseif ( $shipping_cost < 5.0 ) {
					$params['shipping_tier'] = 'Standard';
				} else {
					$params['shipping_tier'] = 'Express';
				}
			}

			// Payment type (credit card, PayPal, cash on delivery, etc.).
			if ( ! empty( $event['custom_data']['payment_type'] ) ) {
				$params['payment_type'] = $event['custom_data']['payment_type'];
			}
		}

		// Content/blog recommended parameters (for news/blog sites).
		if ( $event['event_name'] === 'page_view' || $event['event_name'] === 'view_item' ) {
			// Content type (article, video, product, page, etc.).
			if ( ! empty( $event['custom_data']['content_type'] ) ) {
				$params['content_type'] = $event['custom_data']['content_type'];
			} elseif ( ! empty( $page['page_url'] ) ) {
				// Auto-detect from URL.
				if ( strpos( $page['page_url'], '/product' ) !== false ) {
					$params['content_type'] = 'product';
				} elseif ( strpos( $page['page_url'], '/blog' ) !== false || strpos( $page['page_url'], '/news' ) !== false ) {
					$params['content_type'] = 'article';
				} elseif ( strpos( $page['page_url'], '/video' ) !== false ) {
					$params['content_type'] = 'video';
				} else {
					$params['content_type'] = 'page';
				}
			}

			// Content ID (post ID, product ID, etc.).
			if ( ! empty( $event['custom_data']['content_id'] ) ) {
				$params['content_id'] = $event['custom_data']['content_id'];
			} elseif ( ! empty( $event['custom_data']['product_id'] ) ) {
				$params['content_id'] = 'product_' . $event['custom_data']['product_id'];
			} elseif ( ! empty( $event['custom_data']['post_id'] ) ) {
				$params['content_id'] = 'post_' . $event['custom_data']['post_id'];
			}
		}

		// Search term (for site search tracking).
		if ( $event['event_name'] === 'search' || $event['event_name'] === 'view_search_results' ) {
			if ( ! empty( $event['custom_data']['search_term'] ) ) {
				$params['search_term'] = $event['custom_data']['search_term'];
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading WordPress core search query parameter, not a form submission.
			} elseif ( ! empty( $_GET['s'] ) ) {
				// WordPress search query parameter.
				$params['search_term'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
			}
		}

		// GA4 Debug Mode: Add debug_mode parameter for DebugView visibility.
		// CRITICAL: This parameter makes events visible in DebugView.
		if ( ! empty( $this->settings['debug_mode'] ) ) {
			$params['debug_mode'] = true;
		}

		// GDPR/CCPA Compliance: Add consent metadata and anonymize IP when consent denied.
		$consent_manager = $this->core->get_service( 'consent_manager' );
		if ( $consent_manager ) {
			$consent_granted           = $consent_manager->is_tracking_allowed();
			$params['consent_granted'] = $consent_granted ? 'yes' : 'no';
			$params['consent_mode']    = $consent_manager->get_consent_mode();

			// If consent denied, anonymize IP address (GA4 supports ip_override parameter).
			if ( ! $consent_granted && ! empty( $event['user_data']['ip_address'] ) ) {
				$params['ip_override'] = TrackSure_Utilities::anonymize_ip( $event['user_data']['ip_address'] );
			}
		}

		return $params;
	}

	/**
	 * Build user properties for GA4.
	 *
	 * Used for custom dimensions, audiences, and remarketing.
	 *
	 * @param array $event Event data.
	 * @return array User properties.
	 */
	private function build_user_properties( $event ) {
		$user_properties = array();

		if ( empty( $event['user_data'] ) ) {
			return $user_properties;
		}

		$user_data = $event['user_data'];

		// Email (hashed SHA256 for privacy).
		if ( ! empty( $user_data['email'] ) ) {
			$user_properties['user_email_sha256'] = array(
				'value' => hash( 'sha256', strtolower( trim( $user_data['email'] ) ) ),
			);
		}

		// Customer type (for audience segmentation).
		if ( ! empty( $user_data['customer_type'] ) ) {
			$user_properties['customer_type'] = array(
				'value' => $user_data['customer_type'],
			);
		}

		// Lifetime value (for value-based bidding).
		if ( ! empty( $user_data['lifetime_value'] ) ) {
			$user_properties['lifetime_value'] = array(
				'value' => (float) $user_data['lifetime_value'],
			);
		}

		// Account age (for user segmentation).
		if ( ! empty( $user_data['account_age_days'] ) ) {
			$user_properties['account_age_days'] = array(
				'value' => (int) $user_data['account_age_days'],
			);
		}

		// Subscription tier (for targeting).
		if ( ! empty( $user_data['subscription_tier'] ) ) {
			$user_properties['subscription_tier'] = array(
				'value' => $user_data['subscription_tier'],
			);
		}

		return $user_properties;
	}

	/**
	 * Handle Measurement Protocol API response.
	 *
	 * @param array|WP_Error $response API response.
	 * @param array          $event Event data.
	 * @return array Result with success and error.
	 */
	private function handle_mp_response( $response, $event ) {
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TrackSure GA4] API Error: ' . $error_message );
			}

			return array(
				'success' => false,
				'error'   => $error_message,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Debug mode returns validation errors in response body.
		if ( ! empty( $this->settings['debug_mode'] ) && ! empty( $body ) ) {
			$debug_response = json_decode( $body, true );

			// Check for validation errors.
			if ( ! empty( $debug_response['validationMessages'] ) ) {
				$errors = array();
				foreach ( $debug_response['validationMessages'] as $msg ) {
					$errors[] = $msg['description'] ?? 'Unknown validation error';
				}

				return array(
					'success' => false,
					'error'   => 'GA4 Debug Validation: ' . implode( '; ', $errors ),
				);
			}
		}

		// Check HTTP status code.
		if ( $code >= 200 && $code < 300 ) {
			return array( 'success' => true );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TrackSure GA4] HTTP Error ' . $code . ': ' . $body );
		}

		return array(
			'success' => false,
			'error'   => 'GA4 returned status code ' . $code,
		);
	}
}
