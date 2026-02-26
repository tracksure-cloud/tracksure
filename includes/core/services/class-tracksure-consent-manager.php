<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging for consent management diagnostics

/**
 *
 * TrackSure Consent Manager
 *
 * Manages user consent for tracking and data collection.
 * Integrates with popular consent management plugins (Cookiebot, OneTrust, etc.).
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Consent Manager class.
 */
class TrackSure_Consent_Manager {






	/**
	 * Instance.
	 *
	 * @var TrackSure_Consent_Manager
	 */
	private static $instance = null;

	/**
	 * Consent mode.
	 *
	 * @var string disabled, opt-in, opt-out, auto.
	 */
	private $consent_mode;

	/**
	 * Google Consent Mode V2 state.
	 * 
	 * Maps to Google's consent categories for ad platforms.
	 * Default: 'granted' (worldwide users don't need consent by default)
	 *
	 * @var array
	 */
	private $consent_state = array(
		'ad_storage'              => 'granted',
		'analytics_storage'       => 'granted',
		'functionality_storage'   => 'granted',
		'personalization_storage' => 'granted',
		'security_storage'        => 'granted',
		'ad_user_data'            => 'granted',
		'ad_personalization'      => 'granted',
	);

	/**
	 * Detected consent plugin.
	 *
	 * @var string|null
	 */
	private $detected_plugin = null;

	/**
	 * Cache for is_tracking_allowed() result to avoid redundant checks.
	 *
	 * @var bool|null
	 */
	private $tracking_allowed_cache = null;

	/**
	 * Cache for detected consent plugin to avoid redundant class_exists() calls.
	 *
	 * @var bool Indicates if plugin detection has been cached.
	 */
	private $plugin_detection_cached = false;

	/**
	 * Registered 3rd party consent plugins.
	 *
	 * @var array Array of plugin_id => callback pairs.
	 */
	private $registered_plugins = array();

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Consent_Manager
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
		$this->consent_mode = get_option( 'tracksure_consent_mode', 'disabled' );

		// Detect active consent plugin
		$this->detect_consent_plugin();

		// Initialize Google Consent Mode V2 state
		$this->init_consent_state();

		// Output Google Consent Mode V2 to browser (priority 1 - before any tracking tags).
		add_action( 'wp_head', array( $this, 'output_consent_mode_script' ), 1 );
	}

	/**
	 * Check if tracking is allowed based on consent.
	 *
	 * @return bool True if tracking is allowed.
	 */
	public function is_tracking_allowed() {
		// Return cached result if available (single source of truth per request).
		if ( null !== $this->tracking_allowed_cache ) {
			return $this->tracking_allowed_cache;
		}

		// Auto-detect consent requirement based on user's country.
		$auto_mode = $this->get_auto_consent_mode();
		if ( $auto_mode !== 'auto' ) {
			// Manual consent mode override.
			$this->consent_mode = $auto_mode;
		}

		// If consent mode is disabled, always allow.
		if ( 'disabled' === $this->consent_mode ) {
			$this->tracking_allowed_cache = true;
			return true;
		}

		// Check for consent cookie or plugin integration.
		$consent_given = $this->check_consent();

		// Opt-in mode: require explicit consent.
		if ( 'opt-in' === $this->consent_mode ) {
			$this->tracking_allowed_cache = $consent_given;
			return $consent_given;
		}

		// Opt-out mode: allow unless explicitly denied.
		if ( 'opt-out' === $this->consent_mode ) {
			$result                       = ! $this->check_consent_denied();
			$this->tracking_allowed_cache = $result;
			return $result;
		}

		// Default: allow.
		$this->tracking_allowed_cache = true;
		return true;
	}

	/**
	 * Check if consent has been given.
	 *
	 * @return bool True if consent is given.
	 */
	private function check_consent() {
		// PRIORITY 1: Check real-time consent override from browser.
		// consent-listeners.js sends consent changes via REST API, stored as short-lived transient.
		// This bridges the gap between browser consent change and cookie being available.
		$client_ip     = TrackSure_Utilities::get_client_ip();
		$transient_key = 'tracksure_consent_' . md5( $client_ip );
		$override      = get_transient( $transient_key );

		if ( is_array( $override ) ) {
			// Browser sent explicit consent state — check analytics_storage.
			if ( isset( $override['analytics_storage'] ) && $override['analytics_storage'] === 'granted' ) {
				return true;
			}
			if ( isset( $override['analytics_storage'] ) && $override['analytics_storage'] === 'denied' ) {
				return false;
			}
		}

		// PRIORITY 2: Check for TrackSure consent cookie.
		if ( isset( $_COOKIE['_ts_consent'] ) && 'true' === sanitize_text_field( wp_unslash( $_COOKIE['_ts_consent'] ) ) ) {
			return true;
		}

		// Check integrations with 20+ popular consent plugins.
		$consent_checks = array(
			'check_cookiebot_consent',              // Cookiebot (most popular)
			'check_onetrust_consent',               // OneTrust (enterprise)
			'check_complianz_consent',              // Complianz GDPR/CCPA
			'check_cookie_notice_consent',          // Cookie Notice (5M+ active)
			'check_gdpr_cookie_consent_consent',    // GDPR Cookie Consent (800K+)
			'check_cookieyes_consent',              // CookieYes (500K+)
			'check_cookie_law_info_consent',        // Cookie Law Info (500K+)
			'check_termly_consent',                 // Termly (300K+)
			'check_gdpr_compliance_consent',        // GDPR Cookie Compliance (300K+)
			'check_borlabs_consent',                // Borlabs Cookie (200K+)
			'check_moove_gdpr_consent',             // Moove GDPR (100K+)
			'check_wp_autoterms_consent',           // WP AutoTerms (100K+)
			'check_iubenda_consent',                // Iubenda (privacy suite)
			'check_real_cookie_banner_consent',     // Real Cookie Banner (100K+)
			'check_cookiepro_consent',              // CookiePro by OneTrust
			'check_quantcast_consent',              // Quantcast Choice
			'check_usercentrics_consent',           // Usercentrics (enterprise)
			'check_trustarc_consent',               // TrustArc (enterprise)
			'check_osano_consent',                  // Osano (privacy platform)
			'check_civic_consent',                  // Civic Cookie Control
			'check_cookiescript_consent',           // Cookie Script
			'check_cookie_consent_consent',         // WP Cookie Consent
		);

		foreach ( $consent_checks as $check_method ) {
			if ( method_exists( $this, $check_method ) && $this->$check_method() ) {
				return true;
			}
		}

		/**
		 * Filter consent check result.
		 *
		 * Allow third-party consent plugins to hook in.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $consent_given Default consent status.
		 */
		return apply_filters( 'tracksure_consent_given', false );
	}

	/**
	 * Check if consent has been explicitly denied.
	 *
	 * @return bool True if consent is denied.
	 */
	private function check_consent_denied() {
		// Check for TrackSure consent cookie.
		if ( isset( $_COOKIE['_ts_consent'] ) && 'false' === sanitize_text_field( wp_unslash( $_COOKIE['_ts_consent'] ) ) ) {
			return true;
		}

		/**
		 * Filter consent denial check.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $consent_denied Default denial status.
		 */
		return apply_filters( 'tracksure_consent_denied', false );
	}

	/**
	 * DRY Helper: Parse JSON cookie and check consent categories.
	 * 
	 * Eliminates duplicate cookie parsing logic across 20+ plugin checks.
	 * Single source of truth for cookie consent validation.
	 * 
	 * @param string $cookie_name Name of the cookie to parse.
	 * @param array  $category_keys Mapping of category => consent key to check.
	 * @param string $decode_method Decode method: 'json', 'urlencoded', or 'string'.
	 * @return bool True if any consent category is granted.
	 */
	private function parse_consent_cookie( $cookie_name, $category_keys, $decode_method = 'json' ) {
		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			return false;
		}

		$raw_cookie = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );

		// Decode based on method
		if ( $decode_method === 'json' ) {
			$data = json_decode( stripslashes( $raw_cookie ), true );
			if ( ! is_array( $data ) ) {
				return false;
			}
		} elseif ( $decode_method === 'urlencoded' ) {
			parse_str( $raw_cookie, $data );
			if ( ! is_array( $data ) ) {
				return false;
			}
		} else {
			// String search for simple cookies
			$data = $raw_cookie;
		}

		// Check if any consent category is granted
		foreach ( $category_keys as $category => $consent_key ) {
			if ( is_array( $data ) ) {
				// JSON/array format
				if ( isset( $data[ $consent_key ] ) && ( $data[ $consent_key ] === 'yes' || $data[ $consent_key ] === true || $data[ $consent_key ] === 1 || $data[ $consent_key ] === '1' ) ) {
					return true;
				}
			} else {
				// String search format (for simple cookies like OneTrust)
				if ( false !== strpos( $data, $consent_key ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check Cookiebot consent.
	 *
	 * @return bool True if Cookiebot consent is given.
	 */
	private function check_cookiebot_consent() {
		if ( ! function_exists( 'cookiebot_active' ) ) {
			return false;
		}

		// Cookiebot stores consent in cookie: CookieConsent (JSON format).
		return $this->parse_consent_cookie(
			'CookieConsent',
			array(
				'statistics' => 'statistics',
				'marketing'  => 'marketing',
			),
			'json'
		);
	}

	/**
	 * Check OneTrust consent.
	 *
	 * @return bool True if OneTrust consent is given.
	 */
	private function check_onetrust_consent() {
		// OneTrust stores consent in OptanonConsent cookie (string format).
		// Format: groups=C0001:1,C0002:1,C0003:1,C0004:1
		return $this->parse_consent_cookie(
			'OptanonConsent',
			array(
				'analytics' => 'C0002:1',
				'targeting' => 'C0004:1',
			),
			'string'
		);
	}

	/**
	 * Check Complianz consent.
	 *
	 * @return bool True if Complianz consent is given.
	 */
	private function check_complianz_consent() {
		if ( ! function_exists( 'cmplz_has_consent' ) ) {
			return false;
		}

		// Check for statistics or marketing consent.
		return cmplz_has_consent( 'statistics' ) || cmplz_has_consent( 'marketing' );
	}

	/**
	 * Invalidate consent cache (called after consent state changes).
	 *
	 * Allows fresh consent checks after browser consent update.
	 */
	public function invalidate_cache() {
		$this->tracking_allowed_cache = null;
	}

	/**
	 * Get consent mode.
	 *
	 * @return string Consent mode (disabled, opt-in, opt-out).
	 */
	public function get_consent_mode() {
		return $this->consent_mode;
	}

	/**
	 * Set consent mode.
	 *
	 * @param string $mode Consent mode.
	 */
	public function set_consent_mode( $mode ) {
		$valid_modes = array( 'disabled', 'opt-in', 'opt-out' );
		if ( in_array( $mode, $valid_modes, true ) ) {
			$this->consent_mode = $mode;
			update_option( 'tracksure_consent_mode', $mode );
		}
	}

	/**
	 * Get LDU (Limited Data Use) parameters for Meta CAPI.
	 *
	 * @return array|null LDU parameters or null if full consent.
	 */
	public function get_ldu_params() {
		if ( ! $this->is_tracking_allowed() ) {
			// User has denied consent - use LDU mode.
			return array(
				'data_processing_options'         => array( 'LDU' ),
				'data_processing_options_country' => 0,
				'data_processing_options_state'   => 0,
			);
		}

		// Full consent - no LDU restrictions.
		return null;
	}

	/**
	 * Check if user should be tracked (exclude admins if configured).
	 *
	 * @return bool True if user should be tracked.
	 */
	public function should_track_user() {
		// Check if admin tracking is disabled.
		if ( ! get_option( 'tracksure_track_admins', false ) && current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check if IP is excluded.
		$excluded_ips = get_option( 'tracksure_exclude_ips', array() );
		if ( ! empty( $excluded_ips ) ) {
			$client_ip = TrackSure_Utilities::get_client_ip();
			if ( in_array( $client_ip, $excluded_ips, true ) ) {
				return false;
			}
		}

		/**
		 * Filter whether user should be tracked.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $should_track Default tracking decision.
		 */
		return apply_filters( 'tracksure_should_track_user', true );
	}

	/**
	 * Anonymize event data if consent is limited.
	 *
	 * PHILOSOPHY: Never block events - always track, but anonymize PII when consent denied.
	 * This ensures 100% event tracking while maintaining GDPR/CCPA/LGPD compliance.
	 *
	 * @param array $event_data Event data.
	 * @return array Anonymized event data.
	 */
	public function anonymize_if_needed( $event_data ) {
		if ( $this->is_tracking_allowed() ) {
			return $event_data;
		}

		// Consent denied - anonymize PII but preserve event structure.
		// Remove/anonymize IP address.
		if ( isset( $event_data['ip_address'] ) ) {
			$event_data['ip_address'] = TrackSure_Utilities::anonymize_ip( $event_data['ip_address'] );
		}

		// Remove user-specific identifiers (prevents cross-device tracking).
		unset( $event_data['user_id'] );
		unset( $event_data['email'] );
		unset( $event_data['phone'] );
		unset( $event_data['external_id'] );

		// Add anonymization flag for destinations to apply privacy modes.
		$event_data['_anonymized'] = true;

		/**
		 * Filter anonymized event data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $event_data Anonymized event data.
		 */
		return apply_filters( 'tracksure_anonymize_event', $event_data );
	}

	/**
	 * Get consent metadata for event params.
	 *
	 * Returns standardized consent metadata that destinations can include in events.
	 *
	 * @return array Consent metadata.
	 */
	public function get_consent_metadata() {
		return array(
			'consent_granted' => $this->is_tracking_allowed() ? 'yes' : 'no',
			'consent_mode'    => $this->get_consent_mode(),
			'consent_plugin'  => $this->has_consent_plugin() ? 'detected' : 'none',
			'detected_plugin' => $this->get_detected_plugin(),
		);
	}

	/**
	 * Get detected consent plugin ID.
	 *
	 * @return string|null Plugin ID or null if no plugin detected.
	 */
	public function get_detected_plugin() {
		return $this->detected_plugin;
	}

	/**
	 * Get auto consent mode based on user's country.
	 *
	 * Automatically determines consent requirement based on IP geolocation.
	 * - EU/EEA/UK/CH: opt-in (GDPR)
	 * - California: opt-out (CCPA)
	 * - Brazil: opt-in (LGPD)
	 * - Other: disabled (no consent required)
	 *
	 * @return string Consent mode (opt-in, opt-out, disabled, or 'auto').
	 */
	private function get_auto_consent_mode() {
		// If manual mode is set, use that.
		if ( 'disabled' !== $this->consent_mode && 'auto' !== get_option( 'tracksure_consent_mode', 'disabled' ) ) {
			return $this->consent_mode;
		}

		// Get user's country from IP.
		$user_country = $this->get_user_country();

		// GDPR countries (EU/EEA + UK + Switzerland).
		$gdpr_countries = array(
			// EU member states.
			'AT',
			'BE',
			'BG',
			'HR',
			'CY',
			'CZ',
			'DK',
			'EE',
			'FI',
			'FR',
			'DE',
			'GR',
			'HU',
			'IE',
			'IT',
			'LV',
			'LT',
			'LU',
			'MT',
			'NL',
			'PL',
			'PT',
			'RO',
			'SK',
			'SI',
			'ES',
			'SE',
			// UK (UK GDPR).
			'GB',
			// EEA (not EU).
			'IS',
			'LI',
			'NO',
			// Switzerland (FADP).
			'CH',
		);

		if ( in_array( $user_country, $gdpr_countries, true ) ) {
			return 'opt-in'; // Require explicit consent.
		}

		// Brazil (LGPD).
		if ( 'BR' === $user_country ) {
			return 'opt-in';
		}

		// California (CCPA) - requires opt-out.
		if ( 'US' === $user_country && $this->is_california_user() ) {
			return 'opt-out';
		}

		// Default: No consent required.
		return 'disabled';
	}

	/**
	 * Get user's country from IP address.
	 *
	 * Delegates to TrackSure_Geolocation (single source of truth)
	 * which checks: CloudFlare header → GeoIP extension → MaxMind local DB → remote APIs.
	 *
	 * This method adds consent-specific fallback logic on top:
	 * - Applies 'tracksure_user_country' filter for custom overrides
	 * - GDPR-safe default: treats unknown countries as EU (opt-in)
	 *
	 * @return string Two-letter country code (e.g., 'US', 'GB', 'DE', 'EU').
	 */
	private function get_user_country() {
		$client_ip   = TrackSure_Utilities::get_client_ip();
		$geolocation = TrackSure_Geolocation::get_instance();
		$country     = $geolocation->get_country_code( $client_ip );

		/**
		 * Filter user country detection.
		 *
		 * Allows custom geolocation services to hook in.
		 *
		 * @since 1.0.1
		 *
		 * @param string $country Detected country code ('XX' = unknown).
		 * @param string $client_ip User's IP address.
		 */
		$country = apply_filters( 'tracksure_user_country', $country, $client_ip );

		// GDPR-safe fallback: If country unknown, apply conservative approach.
		// Better to require consent (opt-in) than risk GDPR violation.
		if ( 'XX' === $country ) {
			/**
			 * Filter unknown country fallback mode.
			 *
			 * @since 1.0.1
			 *
			 * @param string $fallback_mode Default fallback ('opt-in' for GDPR safety).
			 * @param string $client_ip User's IP address.
			 */
			$fallback_mode = apply_filters( 'tracksure_unknown_country_fallback_mode', 'opt-in', $client_ip );

			if ( 'opt-in' === $fallback_mode ) {
				$country = 'EU'; // Synthetic country code for GDPR region.
			}
		}

		return $country;
	}

	/**
	 * Check if user is from California (for CCPA).
	 *
	 * Uses TrackSure_Geolocation region data when available.
	 * Falls back to filter for manual override.
	 *
	 * @return bool True if California resident.
	 */
	private function is_california_user() {
		$geolocation = TrackSure_Geolocation::get_instance();
		$region      = $geolocation->get_region();

		// Check if region data indicates California.
		if ( ! empty( $region ) ) {
			$region_lower  = strtolower( $region );
			$is_california = ( 'california' === $region_lower || 'ca' === $region_lower );
		} else {
			$is_california = false;
		}

		/**
		 * Filter California detection.
		 *
		 * @since 1.0.1
		 *
		 * @param bool   $is_california Detected result (true if region is California).
		 * @param string $region        Raw region string from geolocation, or empty.
		 */
		return apply_filters( 'tracksure_is_california_user', $is_california, $region );
	}

	// ========================================.
	// CONSENT PLUGIN INTEGRATIONS (20+ Plugins).
	// ========================================.

	/**
	 * Check Cookie Notice by dFactory consent.
	 *
	 * Plugin: https://wordpress.org/plugins/cookie-notice/
	 * Active installs: 5M+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_cookie_notice_consent() {
		if ( ! class_exists( 'Cookie_Notice' ) ) {
			return false;
		}

		// Cookie Notice uses 'cookie_notice_accepted' cookie.
		if ( isset( $_COOKIE['cookie_notice_accepted'] ) && 'true' === sanitize_text_field( wp_unslash( $_COOKIE['cookie_notice_accepted'] ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check GDPR Cookie Consent by WebToffee.
	 *
	 * Plugin: https://wordpress.org/plugins/cookie-law-info/
	 * Active installs: 800K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_gdpr_cookie_consent_consent() {
		if ( isset( $_COOKIE['wpl_viewed_cookie'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['wpl_viewed_cookie'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $consent['analytics'] );
		}
		return false;
	}

	/**
	 * Check CookieYes consent.
	 *
	 * Plugin: https://wordpress.org/plugins/cookie-law-info/ (CookieYes variant)
	 * Active installs: 500K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_cookieyes_consent() {
		if ( isset( $_COOKIE['cookieyes-consent'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['cookieyes-consent'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $consent['analytics'] ) && 'yes' === $consent['analytics'];
		}
		return false;
	}

	/**
	 * Check Cookie Law Info consent.
	 *
	 * Plugin: https://wordpress.org/plugins/cookie-law-info/
	 * Active installs: 500K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_cookie_law_info_consent() {
		if ( isset( $_COOKIE['cookielawinfo-checkbox-analytics'] ) && 'yes' === sanitize_text_field( wp_unslash( $_COOKIE['cookielawinfo-checkbox-analytics'] ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check Termly consent.
	 *
	 * Plugin: https://wordpress.org/plugins/uk-cookie-consent/
	 * Active installs: 300K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_termly_consent() {
		if ( isset( $_COOKIE['termly_consent'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['termly_consent'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $consent['analytics'] );
		}
		return false;
	}

	/**
	 * Check GDPR Cookie Compliance consent.
	 *
	 * Plugin: https://wordpress.org/plugins/gdpr-cookie-compliance/
	 * Active installs: 300K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_gdpr_compliance_consent() {
		if ( isset( $_COOKIE['moove_gdpr_popup'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['moove_gdpr_popup'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $consent['analytics'] );
		}
		return false;
	}

	/**
	 * Check Borlabs Cookie consent.
	 *
	 * Plugin: https://borlabs.io/borlabs-cookie/
	 * Active installs: 200K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_borlabs_consent() {
		// Borlabs v3+ has a PHP API.
		if ( class_exists( '\\Borlabs\\Cookie\\System\\Consent\\ConsentManager' ) ) {
			try {
				$consent = \Borlabs\Cookie\System\Consent\ConsentManager::getInstance();
				return $consent->hasConsent( 'statistics' );
			} catch ( \Exception $e ) {
				// Fallback to cookie check.
			}
		}

		// Borlabs v2 uses cookies.
		if ( isset( $_COOKIE['borlabs-cookie'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['borlabs-cookie'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $consent['statistics'] );
		}

		return false;
	}

	/**
	 * Check Moove GDPR consent.
	 *
	 * Plugin: https://wordpress.org/plugins/gdpr-cookie-compliance/
	 * Active installs: 100K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_moove_gdpr_consent() {
		return $this->check_gdpr_compliance_consent(); // Same plugin.
	}

	/**
	 * Check WP AutoTerms consent.
	 *
	 * Plugin: https://wordpress.org/plugins/auto-terms-of-service-and-privacy-policy/
	 * Active installs: 100K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_wp_autoterms_consent() {
		if ( isset( $_COOKIE['wpautoterms-cookies-notice'] ) && 'accepted' === sanitize_text_field( wp_unslash( $_COOKIE['wpautoterms-cookies-notice'] ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check Iubenda consent.
	 *
	 * Plugin: https://wordpress.org/plugins/iubenda-cookie-law-solution/
	 * Privacy suite
	 *
	 * @return bool True if consent is given.
	 */
	private function check_iubenda_consent() {
		// Iubenda stores consent in multiple cookies with dynamic suffixes.
		// Check for analytics category (3).
		foreach ( $_COOKIE as $name => $value ) {
			$name_safe = sanitize_key( wp_unslash( $name ) );
			if ( 0 === strpos( $name_safe, '_iub_cs-' ) ) {
				$consent_data = sanitize_text_field( wp_unslash( $value ) );
				$consent      = json_decode( stripslashes( $consent_data ), true );

				// Validate JSON decode succeeded.
				if ( is_array( $consent ) && ! empty( $consent['purposes']['3'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Check CookiePro by OneTrust consent.
	 *
	 * Plugin: Enterprise CMP
	 *
	 * @return bool True if consent is given.
	 */
	private function check_cookiepro_consent() {
		return $this->check_onetrust_consent(); // Same vendor.
	}

	/**
	 * Check Quantcast Choice consent.
	 *
	 * Plugin: https://wordpress.org/plugins/quantcast-choice/
	 * Publisher solution
	 *
	 * @return bool True if consent is given.
	 */
	private function check_quantcast_consent() {
		if ( isset( $_COOKIE['euconsent-v2'] ) ) {
			// Quantcast uses TCF 2.0 consent string (complex decoding).
			// For simplicity, check if consent string exists and is not empty.
			$consent_string = sanitize_text_field( wp_unslash( $_COOKIE['euconsent-v2'] ) );
			return ! empty( $consent_string ) && strlen( $consent_string ) > 10;
		}
		return false;
	}

	/**
	 * Check Usercentrics consent.
	 *
	 * Plugin: Enterprise CMP
	 *
	 * @return bool True if consent is given.
	 */
	private function check_usercentrics_consent() {
		if ( isset( $_COOKIE['uc_user_interaction'] ) && 'true' === sanitize_text_field( wp_unslash( $_COOKIE['uc_user_interaction'] ) ) ) {
			// Check for analytics consent.
			if ( isset( $_COOKIE['uc_settings'] ) ) {
				$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['uc_settings'] ) );
				$settings     = json_decode( stripslashes( $consent_data ), true );
				return ! empty( $settings['analytics'] );
			}
		}
		return false;
	}

	/**
	 * Check TrustArc consent.
	 *
	 * Plugin: Enterprise CMP
	 *
	 * @return bool True if consent is given.
	 */
	private function check_trustarc_consent() {
		if ( isset( $_COOKIE['notice_preferences'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['notice_preferences'] ) );
			$prefs        = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $prefs['analytics'] );
		}
		return false;
	}

	/**
	 * Check Osano consent.
	 *
	 * Plugin: https://www.osano.com/
	 * Privacy platform
	 *
	 * @return bool True if consent is given.
	 */
	private function check_osano_consent() {
		if ( isset( $_COOKIE['osano_consentmanager_uuid'] ) ) {
			// Check for analytics consent.
			if ( isset( $_COOKIE['osano_consentmanager'] ) ) {
				$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['osano_consentmanager'] ) );
				$consent      = json_decode( stripslashes( $consent_data ), true );
				return ! empty( $consent['ANALYTICS'] );
			}
		}
		return false;
	}

	/**
	 * Check Civic Cookie Control consent.
	 *
	 * Plugin: Civic UK CMP
	 *
	 * @return bool True if consent is given.
	 */
	private function check_civic_consent() {
		if ( isset( $_COOKIE['CookieControl'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['CookieControl'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $consent['optionalCookies']['analytics'] );
		}
		return false;
	}

	/**
	 * Check Cookie Script consent.
	 *
	 * Plugin: https://cookie-script.com/
	 *
	 * @return bool True if consent is given.
	 */
	private function check_cookiescript_consent() {
		if ( isset( $_COOKIE['CookieScriptConsent'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['CookieScriptConsent'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $consent['categories'] ) && in_array( 'performance', $consent['categories'], true );
		}
		return false;
	}

	/**
	 * Check WP Cookie Consent consent.
	 *
	 * Plugin: https://wordpress.org/plugins/wp-gdpr-cookie-notice/
	 *
	 * @return bool True if consent is given.
	 */
	private function check_cookie_consent_consent() {
		if ( isset( $_COOKIE['wp_gdpr_cookie_notice'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['wp_gdpr_cookie_notice'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			return ! empty( $consent['analytics'] );
		}
		return false;
	}

	/**
	 * Check Real Cookie Banner consent.
	 *
	 * Plugin: https://wordpress.org/plugins/real-cookie-banner/
	 * Active installs: 100K+
	 *
	 * @return bool True if consent is given.
	 */
	private function check_real_cookie_banner_consent() {
		// Real Cookie Banner uses PHP API when available
		if ( function_exists( 'RCB' ) ) {
			$rcb = \RCB();
			if ( method_exists( $rcb, 'getRevision' ) ) {
				$revision = $rcb->getRevision();
				if ( $revision && method_exists( $revision, 'hasConsent' ) ) {
					return $revision->hasConsent( 'http' );
				}
			}
		}

		// Fallback to cookie check - Real Cookie Banner stores consent in 'real-cookie-banner-consents' cookie
		if ( isset( $_COOKIE['real-cookie-banner-consents'] ) ) {
			$consent_data = sanitize_text_field( wp_unslash( $_COOKIE['real-cookie-banner-consents'] ) );
			$consent      = json_decode( stripslashes( $consent_data ), true );
			// Check if any essential or statistics groups are accepted
			if ( is_array( $consent ) ) {
				foreach ( $consent as $group_id => $accepted ) {
					if ( $accepted === true ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	// ========================================.
	// PUBLIC API FOR 3RD PARTY INTEGRATIONS.
	// ========================================.

	/**
	 * Check if any consent plugin is active.
	 *
	 * Used to detect if site has a consent management solution installed.
	 *
	 * @return bool True if consent plugin detected.
	 */
	public function has_consent_plugin() {
		// Check for common plugin functions/classes.
		$plugin_checks = array(
			'cookiebot_active',           // Cookiebot
			'cmplz_has_consent',          // Complianz
			'Cookie_Notice',              // Cookie Notice (class)
			'OneTrust',                   // OneTrust
			'BorlabsCookie',              // Borlabs
		);

		foreach ( $plugin_checks as $check ) {
			if ( function_exists( $check ) || class_exists( $check ) ) {
				return true;
			}
		}

		// Check for consent cookies.
		$consent_cookies = array(
			'CookieConsent',              // Cookiebot
			'OptanonConsent',             // OneTrust
			'cookie_notice_accepted',     // Cookie Notice
			'cmplz_consented_services',   // Complianz
			'cookieyes-consent',          // CookieYes
			'cookielawinfo-checkbox-analytics', // Cookie Law Info
			'borlabs-cookie',             // Borlabs
			'moove_gdpr_popup',           // Moove GDPR
		);

		foreach ( $consent_cookies as $cookie ) {
			$cookie_safe = sanitize_key( $cookie );
			if ( isset( $_COOKIE[ $cookie_safe ] ) ) {
				return true;
			}
		}

		// Check registered 3rd party plugins.
		if ( ! empty( $this->registered_plugins ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Register a 3rd party consent plugin.
	 *
	 * Allows custom consent plugins to integrate with TrackSure.
	 *
	 * @param string   $plugin_id Unique plugin identifier.
	 * @param callable $callback Function that returns true if consent granted.
	 * @return bool True if registered successfully.
	 */
	public function register_plugin( $plugin_id, $callback ) {
		if ( ! is_callable( $callback ) ) {
			return false;
		}

		$this->registered_plugins[ $plugin_id ] = $callback;
		return true;
	}

	/**
	 * Check consent from registered 3rd party plugins.
	 *
	 * @return bool True if any registered plugin reports consent granted.
	 */
	private function check_registered_plugins() {
		foreach ( $this->registered_plugins as $plugin_id => $callback ) {
			try {
				if ( call_user_func( $callback ) ) {
					return true;
				}
			} catch ( \Exception $e ) {
				// Plugin callback failed - skip it.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "[TrackSure] Consent plugin '{$plugin_id}' callback failed: " . $e->getMessage() );
				}
			}
		}
		return false;
	}

	// ========================================
	// GOOGLE CONSENT MODE V2 SUPPORT
	// ========================================

	/**
	 * Detect active consent plugin.
	 * 
	 * Uses WordPress is_plugin_active() for reliable detection,
	 * with additional class/function checks verified from actual plugin source code.
	 * 
	 * @return void
	 */
	private function detect_consent_plugin() {
		// Ensure function exists
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check in priority order - first match wins

		// 1. Complianz GDPR/CCPA (300K+ installs)
		// Verified: Defines cmplz_has_consent() and cmplz_get_cookiebanner() functions
		if ( is_plugin_active( 'complianz-gdpr/complianz-gpdr.php' ) ) {
			$this->detected_plugin = 'complianz';
			return;
		}

		// 2. Cookie Notice (5M+ installs)
		// Verified: Defines Cookie_Notice class in cookie-notice.php
		if ( is_plugin_active( 'cookie-notice/cookie-notice.php' ) ) {
			$this->detected_plugin = 'cookie_notice';
			return;
		}

		// 3. Cookiebot (400K+ installs)
		// Verified: Loads via vendor/autoload.php, uses cybot\cookiebot namespace
		if ( is_plugin_active( 'cookiebot/cookiebot.php' ) ) {
			$this->detected_plugin = 'cookiebot';
			return;
		}

		// 4. CookieYes / Cookie Law Info (rebranded plugin)
		// Verified: Defines CLI_VERSION constant, NO CookieYes class exists
		// Plugin file: cookie-law-info/cookie-law-info.php
		if ( is_plugin_active( 'cookie-law-info/cookie-law-info.php' ) ) {
			$this->detected_plugin = 'cookieyes';
			return;
		}

		// 5. iubenda (100K+ installs)
		// Verified: Defines lowercase 'iubenda' class and iubenda() function
		if ( is_plugin_active( 'iubenda-cookie-law-solution/iubenda_cookie_solution.php' ) ) {
			$this->detected_plugin = 'iubenda';
			return;
		}

		// 6. Real Cookie Banner (100K+ installs)
		// Verified: Defines RCB_VERSION constant and DevOwl\RealCookieBanner namespace
		if ( is_plugin_active( 'real-cookie-banner/index.php' ) ) {
			$this->detected_plugin = 'real_cookie_banner';
			return;
		}

		// 7. Moove GDPR Cookie Compliance (300K+ installs)
		// Verified: Defines Moove_GDPR_Actions, Moove_GDPR_Content, Moove_GDPR_Options classes
		if ( is_plugin_active( 'gdpr-cookie-compliance/moove-gdpr.php' ) ) {
			$this->detected_plugin = 'moove_gdpr';
			return;
		}

		// 8. GDPR Cookie Consent (800K+ installs)
		if ( is_plugin_active( 'gdpr-cookie-consent/gdpr-cookie-consent.php' ) ) {
			$this->detected_plugin = 'gdpr_cookie_consent';
			return;
		}

		// 9. OneTrust (Enterprise)
		if ( is_plugin_active( 'onetrust-cookie-compliance/onetrust.php' ) ) {
			$this->detected_plugin = 'onetrust';
			return;
		}

		// 10. Borlabs Cookie (200K+ installs)
		if ( is_plugin_active( 'borlabs-cookie/borlabs-cookie.php' ) ) {
			$this->detected_plugin = 'borlabs';
			return;
		}

		// 11. Termly (300K+ installs)
		if ( is_plugin_active( 'uk-cookie-consent/uk-cookie-consent.php' ) ) {
			$this->detected_plugin = 'termly';
			return;
		}

		// 12. WP AutoTerms (100K+ installs)
		if ( is_plugin_active( 'auto-terms-of-service-and-privacy-policy/auto-terms-of-service-and-privacy-policy.php' ) ) {
			$this->detected_plugin = 'wp_autoterms';
			return;
		}

		// 13. WP GDPR Compliance
		if ( is_plugin_active( 'wp-gdpr-compliance/wp-gdpr-compliance.php' ) ) {
			$this->detected_plugin = 'wp_gdpr_compliance';
			return;
		}

		/**
		 * Filter detected consent plugin.
		 * 
		 * Allows 3rd party code to override or add custom detection.
		 * 
		 * @param string|null $detected_plugin Detected plugin ID or null.
		 */
		$this->detected_plugin = apply_filters( 'tracksure_detected_consent_plugin', null );
	}

	/**
	 * Initialize Google Consent Mode V2 state from detected plugin.
	 * 
	 * Reads consent cookies and maps to Google's consent categories.
	 */
	private function init_consent_state() {
		// If no consent plugin detected, grant all by default (worldwide audience)
		if ( ! $this->detected_plugin ) {
			$this->consent_state = apply_filters( 'tracksure_default_consent_state', $this->consent_state );
			return;
		}

		// Extract consent from detected plugin
		switch ( $this->detected_plugin ) {
			case 'cookieyes':
				$this->extract_cookieyes_consent_state();
				break;

			case 'complianz':
				$this->extract_complianz_consent_state();
				break;

			case 'onetrust':
				$this->extract_onetrust_consent_state();
				break;

			case 'cookiebot':
				$this->extract_cookiebot_consent_state();
				break;

			case 'cookie_notice':
				$this->extract_cookie_notice_consent_state();
				break;

			case 'gdpr_cookie_consent':
				$this->extract_gdpr_cookie_consent_state();
				break;

			case 'borlabs':
				$this->extract_borlabs_consent_state();
				break;

			default:
				// Unknown plugin - default to denied (GDPR-safe)
				$this->consent_state = array(
					'ad_storage'              => 'denied',
					'analytics_storage'       => 'denied',
					'functionality_storage'   => 'granted',
					'personalization_storage' => 'denied',
					'security_storage'        => 'granted',
					'ad_user_data'            => 'denied',
					'ad_personalization'      => 'denied',
				);
				break;
		}
	}

	/**
	 * Get Google Consent Mode V2 state.
	 * 
	 * Returns current consent status for all Google consent categories.
	 * Used by Meta Pixel, Google Ads, GA4, and other ad platforms.
	 * 
	 * @return array Consent state array.
	 */
	public function get_consent_state() {
		return apply_filters( 'tracksure_consent_state', $this->consent_state, $this->detected_plugin );
	}

	/**
	 * Output Google Consent Mode V2 script to browser.
	 * 
	 * Injects consent state into page <head> before any tracking tags load.
	 * This ensures Google Ads, GA4, and Meta Pixel respect user consent.
	 * 
	 * @since 1.0.1
	 */
	public function output_consent_mode_script() {
		// Only output if consent mode is not disabled.
		if ( 'disabled' === $this->consent_mode ) {
			return;
		}

		$consent_state    = $this->get_consent_state();
		$tracking_allowed = $this->is_tracking_allowed();

		// Output Google Consent Mode V2 default state using wp_print_inline_script_tag (WP 5.7+).
		$inline_js  = "window.dataLayer = window.dataLayer || [];\n";
		$inline_js .= "function gtag() { dataLayer.push(arguments); }\n";
		$inline_js .= "gtag('consent', 'default', {\n";
		$inline_js .= "  'ad_storage': '" . esc_js( $consent_state['ad_storage'] ) . "',\n";
		$inline_js .= "  'analytics_storage': '" . esc_js( $consent_state['analytics_storage'] ) . "',\n";
		$inline_js .= "  'ad_user_data': '" . esc_js( $consent_state['ad_user_data'] ) . "',\n";
		$inline_js .= "  'ad_personalization': '" . esc_js( $consent_state['ad_personalization'] ) . "',\n";
		$inline_js .= "  'functionality_storage': '" . esc_js( $consent_state['functionality_storage'] ) . "',\n";
		$inline_js .= "  'personalization_storage': '" . esc_js( $consent_state['personalization_storage'] ) . "',\n";
		$inline_js .= "  'security_storage': '" . esc_js( $consent_state['security_storage'] ) . "'\n";
		$inline_js .= "});\n";
		$inline_js .= "window.trackSureConsent = {\n";
		$inline_js .= "  mode: '" . esc_js( $this->consent_mode ) . "',\n";
		$inline_js .= '  granted: ' . ( $tracking_allowed ? 'true' : 'false' ) . ",\n";
		$inline_js .= "  plugin: '" . esc_js( $this->detected_plugin ? $this->detected_plugin : 'none' ) . "',\n";
		$inline_js .= '  state: ' . wp_json_encode( $consent_state ) . "\n";
		$inline_js .= '};';

		wp_print_inline_script_tag(
			$inline_js,
			array(
				'data-cfasync'     => 'false',
				'data-no-optimize' => '1',
			)
		);
	}

	/**
	 * Extract consent state from CookieYes.
	 */
	private function extract_cookieyes_consent_state() {
		// CookieYes stores consent in cookie: cookieyes-consent
		// Format: consent:yes,action:yes,necessary:yes,functional:yes,analytics:yes,performance:yes,advertisement:yes
		if ( ! isset( $_COOKIE['cookieyes-consent'] ) ) {
			// No consent given yet - default to denied
			$this->consent_state = array(
				'ad_storage'              => 'denied',
				'analytics_storage'       => 'denied',
				'functionality_storage'   => 'granted',
				'personalization_storage' => 'denied',
				'security_storage'        => 'granted',
				'ad_user_data'            => 'denied',
				'ad_personalization'      => 'denied',
			);
			return;
		}

		$consent_cookie = sanitize_text_field( wp_unslash( $_COOKIE['cookieyes-consent'] ) );
		$consent_parts  = explode( ',', $consent_cookie );
		$consent_map    = array();

		foreach ( $consent_parts as $part ) {
			$kv = explode( ':', $part );
			if ( count( $kv ) === 2 ) {
				$consent_map[ trim( $kv[0] ) ] = trim( $kv[1] );
			}
		}

		// Map CookieYes categories to Google Consent Mode V2
		$this->consent_state = array(
			'ad_storage'              => isset( $consent_map['advertisement'] ) && $consent_map['advertisement'] === 'yes' ? 'granted' : 'denied',
			'analytics_storage'       => isset( $consent_map['analytics'] ) && $consent_map['analytics'] === 'yes' ? 'granted' : 'denied',
			'functionality_storage'   => isset( $consent_map['functional'] ) && $consent_map['functional'] === 'yes' ? 'granted' : 'denied',
			'personalization_storage' => isset( $consent_map['functional'] ) && $consent_map['functional'] === 'yes' ? 'granted' : 'denied',
			'security_storage'        => 'granted',
			'ad_user_data'            => isset( $consent_map['advertisement'] ) && $consent_map['advertisement'] === 'yes' ? 'granted' : 'denied',
			'ad_personalization'      => isset( $consent_map['advertisement'] ) && $consent_map['advertisement'] === 'yes' ? 'granted' : 'denied',
		);
	}

	/**
	 * Extract consent state from Complianz.
	 */
	private function extract_complianz_consent_state() {
		// Complianz has function to check consent by category
		$marketing_consent  = function_exists( 'cmplz_has_consent' ) && cmplz_has_consent( 'marketing' );
		$analytics_consent  = function_exists( 'cmplz_has_consent' ) && cmplz_has_consent( 'statistics' );
		$functional_consent = function_exists( 'cmplz_has_consent' ) && cmplz_has_consent( 'functional' );

		$this->consent_state = array(
			'ad_storage'              => $marketing_consent ? 'granted' : 'denied',
			'analytics_storage'       => $analytics_consent ? 'granted' : 'denied',
			'functionality_storage'   => $functional_consent ? 'granted' : 'denied',
			'personalization_storage' => $functional_consent ? 'granted' : 'denied',
			'security_storage'        => 'granted',
			'ad_user_data'            => $marketing_consent ? 'granted' : 'denied',
			'ad_personalization'      => $marketing_consent ? 'granted' : 'denied',
		);
	}

	/**
	 * Extract consent state from OneTrust.
	 */
	private function extract_onetrust_consent_state() {
		// OneTrust stores consent in OptanonConsent cookie
		if ( ! isset( $_COOKIE['OptanonConsent'] ) ) {
			$this->consent_state = array(
				'ad_storage'              => 'denied',
				'analytics_storage'       => 'denied',
				'functionality_storage'   => 'granted',
				'personalization_storage' => 'denied',
				'security_storage'        => 'granted',
				'ad_user_data'            => 'denied',
				'ad_personalization'      => 'denied',
			);
			return;
		}

		$consent_cookie = sanitize_text_field( wp_unslash( $_COOKIE['OptanonConsent'] ) );

		// Parse OneTrust consent groups (C0001=targeting, C0002=performance, C0003=functional, C0004=social)
		$targeting_consent   = strpos( $consent_cookie, 'C0001:1' ) !== false;
		$performance_consent = strpos( $consent_cookie, 'C0002:1' ) !== false;
		$functional_consent  = strpos( $consent_cookie, 'C0003:1' ) !== false;

		$this->consent_state = array(
			'ad_storage'              => $targeting_consent ? 'granted' : 'denied',
			'analytics_storage'       => $performance_consent ? 'granted' : 'denied',
			'functionality_storage'   => $functional_consent ? 'granted' : 'denied',
			'personalization_storage' => $functional_consent ? 'granted' : 'denied',
			'security_storage'        => 'granted',
			'ad_user_data'            => $targeting_consent ? 'granted' : 'denied',
			'ad_personalization'      => $targeting_consent ? 'granted' : 'denied',
		);
	}

	/**
	 * Extract consent state from Cookiebot.
	 */
	private function extract_cookiebot_consent_state() {
		// Cookiebot stores consent in CookieConsent cookie
		if ( ! isset( $_COOKIE['CookieConsent'] ) ) {
			$this->consent_state = array(
				'ad_storage'              => 'denied',
				'analytics_storage'       => 'denied',
				'functionality_storage'   => 'granted',
				'personalization_storage' => 'denied',
				'security_storage'        => 'granted',
				'ad_user_data'            => 'denied',
				'ad_personalization'      => 'denied',
			);
			return;
		}

		$consent_cookie = sanitize_text_field( wp_unslash( $_COOKIE['CookieConsent'] ) );

		// Cookiebot format: {necessary:true, preferences:true, statistics:true, marketing:true}
		$marketing_consent   = strpos( $consent_cookie, '"marketing":true' ) !== false;
		$statistics_consent  = strpos( $consent_cookie, '"statistics":true' ) !== false;
		$preferences_consent = strpos( $consent_cookie, '"preferences":true' ) !== false;

		$this->consent_state = array(
			'ad_storage'              => $marketing_consent ? 'granted' : 'denied',
			'analytics_storage'       => $statistics_consent ? 'granted' : 'denied',
			'functionality_storage'   => $preferences_consent ? 'granted' : 'denied',
			'personalization_storage' => $preferences_consent ? 'granted' : 'denied',
			'security_storage'        => 'granted',
			'ad_user_data'            => $marketing_consent ? 'granted' : 'denied',
			'ad_personalization'      => $marketing_consent ? 'granted' : 'denied',
		);
	}

	/**
	 * Extract consent state from Cookie Notice.
	 */
	private function extract_cookie_notice_consent_state() {
		// Cookie Notice stores simple consent in cookie_notice_accepted.
		$consent = isset( $_COOKIE['cookie_notice_accepted'] ) && sanitize_text_field( wp_unslash( $_COOKIE['cookie_notice_accepted'] ) ) === 'true';

		$this->consent_state = array(
			'ad_storage'              => $consent ? 'granted' : 'denied',
			'analytics_storage'       => $consent ? 'granted' : 'denied',
			'functionality_storage'   => 'granted',
			'personalization_storage' => $consent ? 'granted' : 'denied',
			'security_storage'        => 'granted',
			'ad_user_data'            => $consent ? 'granted' : 'denied',
			'ad_personalization'      => $consent ? 'granted' : 'denied',
		);
	}

	/**
	 * Extract consent state from GDPR Cookie Consent.
	 */
	private function extract_gdpr_cookie_consent_state() {
		// Similar to Cookie Notice.
		$consent = isset( $_COOKIE['viewed_cookie_policy'] ) && sanitize_text_field( wp_unslash( $_COOKIE['viewed_cookie_policy'] ) ) === 'yes';

		$this->consent_state = array(
			'ad_storage'              => $consent ? 'granted' : 'denied',
			'analytics_storage'       => $consent ? 'granted' : 'denied',
			'functionality_storage'   => 'granted',
			'personalization_storage' => $consent ? 'granted' : 'denied',
			'security_storage'        => 'granted',
			'ad_user_data'            => $consent ? 'granted' : 'denied',
			'ad_personalization'      => $consent ? 'granted' : 'denied',
		);
	}

	/**
	 * Extract consent state from Borlabs Cookie.
	 */
	private function extract_borlabs_consent_state() {
		// Borlabs Cookie stores consent in borlabs-cookie cookie
		if ( ! isset( $_COOKIE['borlabs-cookie'] ) ) {
			$this->consent_state = array(
				'ad_storage'              => 'denied',
				'analytics_storage'       => 'denied',
				'functionality_storage'   => 'granted',
				'personalization_storage' => 'denied',
				'security_storage'        => 'granted',
				'ad_user_data'            => 'denied',
				'ad_personalization'      => 'denied',
			);
			return;
		}

		$consent_cookie = sanitize_text_field( wp_unslash( $_COOKIE['borlabs-cookie'] ) );
		$consent_data   = json_decode( $consent_cookie, true );

		if ( ! is_array( $consent_data ) || ! isset( $consent_data['consents'] ) ) {
			$this->consent_state = array(
				'ad_storage'              => 'denied',
				'analytics_storage'       => 'denied',
				'functionality_storage'   => 'granted',
				'personalization_storage' => 'denied',
				'security_storage'        => 'granted',
				'ad_user_data'            => 'denied',
				'ad_personalization'      => 'denied',
			);
			return;
		}

		$consents            = $consent_data['consents'];
		$marketing_consent   = isset( $consents['marketing'] ) && $consents['marketing'];
		$statistics_consent  = isset( $consents['statistics'] ) && $consents['statistics'];
		$preferences_consent = isset( $consents['preferences'] ) && $consents['preferences'];

		$this->consent_state = array(
			'ad_storage'              => $marketing_consent ? 'granted' : 'denied',
			'analytics_storage'       => $statistics_consent ? 'granted' : 'denied',
			'functionality_storage'   => $preferences_consent ? 'granted' : 'denied',
			'personalization_storage' => $preferences_consent ? 'granted' : 'denied',
			'security_storage'        => 'granted',
			'ad_user_data'            => $marketing_consent ? 'granted' : 'denied',
			'ad_personalization'      => $marketing_consent ? 'granted' : 'denied',
		);
	}
}
