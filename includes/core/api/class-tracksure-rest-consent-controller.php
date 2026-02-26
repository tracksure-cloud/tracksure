<?php

/**
 *
 * TrackSure REST API - Consent Management Controller
 *
 * Handles consent-related REST API endpoints for React admin panel.
 *
 * @package TrackSure\Core\API
 * @since 1.0.2
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Consent REST API Controller.
 */
class TrackSure_REST_Consent_Controller extends TrackSure_REST_Controller
{



	/**
	 * Register routes.
	 */
	public function register_routes()
	{
		// GET /tracksure/v1/consent/status - Get consent configuration and status.
		register_rest_route(
			$this->namespace,
			'/consent/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_consent_status'),
				'permission_callback' => array($this, 'check_read_permission'),
			)
		);

		// GET /tracksure/v1/consent/warning - Get consent warning for React admin.
		register_rest_route(
			$this->namespace,
			'/consent/warning',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_consent_warning'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// POST /tracksure/v1/consent/warning/dismiss - Dismiss consent warning.
		register_rest_route(
			$this->namespace,
			'/consent/warning/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'dismiss_consent_warning'),
				'permission_callback' => array($this, 'check_admin_permission'),
			)
		);

		// GET /tracksure/v1/consent/metadata - Get consent metadata for events.
		register_rest_route(
			$this->namespace,
			'/consent/metadata',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_consent_metadata'),
				'permission_callback' => array($this, 'check_read_permission'),
			)
		);

		// GET /tracksure/v1/consent/state - Get Google Consent Mode V2 state.
		register_rest_route(
			$this->namespace,
			'/consent/state',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_consent_state'),
				'permission_callback' => array($this, 'check_read_permission'),
			)
		);

		// POST /tracksure/v1/consent/update - Update consent state (for browser consent changes).
		register_rest_route(
			$this->namespace,
			'/consent/update',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'update_consent_state'),
				'permission_callback' => '__return_true', // Public endpoint for browser consent updates.
				'args'                => array(
					'consent_state' => array(
						'required'          => true,
						'type'              => 'object',
						'validate_callback' => array($this, 'validate_consent_state'),
					),
				),
			)
		);
	}

	/**
	 * Get consent status and configuration.
	 *
	 * Endpoint: GET /tracksure/v1/consent/status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_consent_status($request)
	{
		$consent_manager = TrackSure_Consent_Manager::get_instance();

		$status = array(
			'consent_mode'        => get_option('tracksure_consent_mode', 'disabled'),
			'is_tracking_allowed' => $consent_manager->is_tracking_allowed(),
			'has_consent_plugin'  => $consent_manager->has_consent_plugin(),
			'consent_metadata'    => $consent_manager->get_consent_metadata(),
		);

		return rest_ensure_response($status);
	}

	/**
	 * Get consent warning for React admin panel.
	 *
	 * Endpoint: GET /tracksure/v1/consent/warning
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_consent_warning($request)
	{
		$warning = tracksure_get_consent_warning_status();

		if (null === $warning) {
			return rest_ensure_response(
				array(
					'show_warning' => false,
				)
			);
		}

		return rest_ensure_response($warning);
	}

	/**
	 * Dismiss consent warning.
	 *
	 * Endpoint: POST /tracksure/v1/consent/warning/dismiss
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function dismiss_consent_warning($request)
	{
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'tracksure_consent_warning_dismissed', true);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __('Consent warning dismissed successfully.', 'tracksure'),
			)
		);
	}

	/**
	 * Get consent metadata.
	 *
	 * Endpoint: GET /tracksure/v1/consent/metadata
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_consent_metadata($request)
	{
		$consent_manager = TrackSure_Consent_Manager::get_instance();

		return rest_ensure_response($consent_manager->get_consent_metadata());
	}

	/**
	 * Get Google Consent Mode V2 state.
	 *
	 * Endpoint: GET /tracksure/v1/consent/state
	 *
	 * Returns current consent status for all Google consent categories,
	 * detected consent plugin, user country, and effective consent mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_consent_state($request)
	{
		$consent_manager = TrackSure_Consent_Manager::get_instance();
		$consent_state   = $consent_manager->get_consent_state();

		$response = array(
			'consent_mode'      => get_option('tracksure_consent_mode', 'disabled'),
			'tracking_allowed'  => $consent_manager->is_tracking_allowed(),
			'detected_plugin'   => $consent_manager->get_detected_plugin(),
			'consent_state'     => $consent_state,
			'supported_plugins' => $this->get_supported_plugins(),
		);

		return rest_ensure_response($response);
	}

	/**
	 * Update consent state (for real-time browser consent changes).
	 *
	 * Endpoint: POST /tracksure/v1/consent/update
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_consent_state($request)
	{
		$consent_state = $request->get_param('consent_state');

		// Store consent override in a short-lived transient keyed by client IP.
		// This makes consent changes from browser available to server-side checks
		// (Consent Manager reads this in check_consent()).
		// Transient expires in 5 minutes — by then the consent plugin's own cookie
		// will be set and the Consent Manager's cookie-based checks take over.
		$client_ip     = TrackSure_Utilities::get_client_ip();
		$transient_key = 'tracksure_consent_' . md5($client_ip);

		set_transient($transient_key, $consent_state, 5 * MINUTE_IN_SECONDS);

		// Invalidate Consent Manager cache so next check sees updated state.
		$consent_manager = TrackSure_Consent_Manager::get_instance();
		$consent_manager->invalidate_cache();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __('Consent state updated successfully.', 'tracksure'),
				'state'   => $consent_state,
			)
		);
	}

	/**
	 * Validate consent state parameter.
	 *
	 * @param array $value Consent state value.
	 * @return bool True if valid.
	 */
	public function validate_consent_state($value)
	{
		if (! is_array($value)) {
			return false;
		}

		$required_keys = array('ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization');

		foreach ($required_keys as $key) {
			if (! isset($value[$key]) || ! in_array($value[$key], array('granted', 'denied'), true)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get list of supported consent plugins.
	 *
	 * @return array List of supported plugins with metadata.
	 */
	private function get_supported_plugins()
	{
		return array(
			// Top 6 - Most popular plugins (all recommended)
			array(
				'id'          => 'complianz',
				'name'        => 'Complianz GDPR/CCPA',
				'slug'        => 'complianz-gdpr',
				'url'         => 'https://wordpress.org/plugins/complianz-gdpr/',
				'recommended' => true,
			),
			array(
				'id'          => 'cookie_notice',
				'name'        => 'Cookie Notice',
				'slug'        => 'cookie-notice',
				'url'         => 'https://wordpress.org/plugins/cookie-notice/',
				'recommended' => true,
			),
			array(
				'id'          => 'cookiebot',
				'name'        => 'Cookiebot',
				'slug'        => 'cookiebot',
				'url'         => 'https://wordpress.org/plugins/cookiebot/',
				'recommended' => true,
			),
			array(
				'id'          => 'cookieyes',
				'name'        => 'CookieYes',
				'slug'        => 'cookie-law-info',
				'url'         => 'https://wordpress.org/plugins/cookie-law-info/',
				'recommended' => true,
			),
			array(
				'id'          => 'iubenda',
				'name'        => 'iubenda',
				'slug'        => 'iubenda-cookie-law-solution',
				'url'         => 'https://wordpress.org/plugins/iubenda-cookie-law-solution/',
				'recommended' => true,
			),
			array(
				'id'          => 'real_cookie_banner',
				'name'        => 'Real Cookie Banner',
				'slug'        => 'real-cookie-banner',
				'url'         => 'https://wordpress.org/plugins/real-cookie-banner/',
				'recommended' => true,
			),
			array(
				'id'          => 'gdpr_cookie_consent',
				'name'        => 'GDPR Cookie Consent',
				'slug'        => 'gdpr-cookie-consent',
				'url'         => 'https://wordpress.org/plugins/gdpr-cookie-consent/',
				'recommended' => true,
			),

			// Additional supported plugins
			array(
				'id'          => 'onetrust',
				'name'        => 'OneTrust',
				'slug'        => 'onetrust-cookie-compliance',
				'url'         => 'https://www.onetrust.com/',
				'recommended' => false,
			),
			array(
				'id'          => 'borlabs',
				'name'        => 'Borlabs Cookie',
				'slug'        => 'borlabs-cookie',
				'url'         => 'https://borlabs.io/borlabs-cookie/',
				'recommended' => false,
			),
			array(
				'id'          => 'termly',
				'name'        => 'Termly',
				'slug'        => 'uk-cookie-consent',
				'url'         => 'https://wordpress.org/plugins/uk-cookie-consent/',
				'recommended' => false,
			),
			array(
				'id'          => 'wp_autoterms',
				'name'        => 'WP AutoTerms',
				'slug'        => 'auto-terms-of-service-and-privacy-policy',
				'url'         => 'https://wordpress.org/plugins/auto-terms-of-service-and-privacy-policy/',
				'recommended' => false,
			),
			array(
				'id'          => 'moove_gdpr',
				'name'        => 'GDPR Cookie Compliance',
				'slug'        => 'gdpr-cookie-compliance',
				'url'         => 'https://wordpress.org/plugins/gdpr-cookie-compliance/',
				'recommended' => false,
			),
		);
	}

	/**
	 * Check if user has read permission.
	 *
	 * @return bool True if user can read.
	 */
	public function check_read_permission()
	{
		return current_user_can('read');
	}

	/**
	 * Check if user has admin permission.
	 *
	 * Verifies user capability and nonce for state-changing requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function check_admin_permission($request)
	{
		return parent::check_admin_permission($request);
	}
}
