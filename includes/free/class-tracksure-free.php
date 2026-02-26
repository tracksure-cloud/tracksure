<?php

/**
 *
 * TrackSure Free Module Pack
 *
 * Registers free features with core:
 * - Meta + GA4 destinations (via extension registration)
 * - WooCommerce integration (via extension registration)
 * - First-party dashboards
 *
 * NOTE: Destinations and Integrations are now managed by Core managers.
 * This class only registers metadata and dashboard routes.
 *
 * @package TrackSure
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Free Module Pack Class
 */
class TrackSure_Free {




	/**
	 * Core instance.
	 *
	 * @var TrackSure_Core
	 */
	private $core;

	/**
	 * Constructor.
	 *
	 * @param TrackSure_Core $core Core instance.
	 */
	public function __construct( $core ) {
		$this->core = $core;

		// NOTE: Destinations and Integrations are managed by Core managers.
		// Free module registers its handlers via action hooks.

		// Register handlers with Core.
		$this->register_destinations();
		$this->register_integrations();
		$this->register_settings();

		// Initialize dashboards and admin extensions.
		$this->init_dashboards();
		$this->init_query_filters();
		$this->init_admin_extensions();
		$this->init_api_filters();
		$this->init_ga4_setup_guide(); // Must run for both admin and REST API

		// Add Free destinations to enabled list dynamically.
		add_filter( 'tracksure_enabled_destinations', array( $this, 'add_free_destinations' ), 10, 3 );
	}

	/**
	 * Add Free plugin's enabled destinations to the list.
	 *
	 * Called via tracksure_enabled_destinations filter in Event Recorder.
	 * Each extension checks its own settings and adds to the list.
	 *
	 * @param array $enabled_destinations Current list of enabled destinations.
	 * @param array $event_data Event data.
	 * @param array $session Session data.
	 * @return array Updated list with Free destinations added.
	 */
	public function add_free_destinations( $enabled_destinations, $event_data, $session ) {
		// Get option values (WordPress stores booleans as 1/0).
		$meta_enabled = get_option( 'tracksure_free_meta_enabled', false );
		$ga4_enabled  = get_option( 'tracksure_free_ga4_enabled', false );

		// Normalize booleans for consistent checking.
		$meta_enabled = (bool) $meta_enabled && $meta_enabled !== '0' && $meta_enabled !== 0;
		$ga4_enabled  = (bool) $ga4_enabled && $ga4_enabled !== '0' && $ga4_enabled !== 0;

		// Check Meta Conversions API.
		// Only add if enabled AND credentials are configured.
		if ( $meta_enabled ) {
			$pixel_id = get_option( 'tracksure_free_meta_pixel_id', '' );
			if ( ! empty( $pixel_id ) && trim( $pixel_id ) !== '' ) {
				$enabled_destinations[] = 'meta';
			}
		}

		// Check Google Analytics 4.
		// Only add if enabled AND credentials are configured.
		if ( $ga4_enabled ) {
			$measurement_id = get_option( 'tracksure_free_ga4_measurement_id', '' );
			if ( ! empty( $measurement_id ) && trim( $measurement_id ) !== '' ) {
				$enabled_destinations[] = 'ga4';
			}
		}

		return $enabled_destinations;
	}

	/**
	 * Register Free destination handlers with Core.
	 *
	 * Called via tracksure_load_destination_handlers action hook.
	 */
	private function register_destinations() {
		// Use class method instead of anonymous function to avoid circular references.
		add_action( 'tracksure_load_destination_handlers', array( $this, 'load_destination_handlers' ) );
	}

	/**
	 * Load destination handlers for Free module.
	 * 
	 * SINGLE SOURCE OF TRUTH for destination metadata.
	 * React admin reads from Destinations Manager, not from duplicate extension registry.
	 *
	 * @param TrackSure_Destinations_Manager $manager Destinations manager instance.
	 */
	public function load_destination_handlers( $manager ) {
		// Register Meta Conversions API.
		$manager->register_destination(
			array(
				'id'                 => 'meta',
				'name'               => 'Meta Conversions API',
				'description'        => 'Send events to Facebook & Instagram with server-side Conversions API',
				'icon'               => 'Facebook',
				'order'              => 10,
				'enabled_key'        => 'tracksure_free_meta_enabled',
				'class_name'         => 'TrackSure_Meta_Destination',
				'file_path'          => TRACKSURE_FREE_DIR . 'destinations/class-tracksure-meta-destination.php',
				'settings_fields'    => array(
					'tracksure_free_meta_pixel_id',
					'tracksure_free_meta_access_token',
					'tracksure_free_meta_test_event_code',
				),
				'reconciliation_key' => 'meta',
			)
		);

		// Register Google Analytics 4.
		$manager->register_destination(
			array(
				'id'                 => 'ga4',
				'name'               => 'Google Analytics 4',
				'description'        => 'Send events to GA4 using Measurement Protocol with browser + server tracking',
				'icon'               => 'BarChart2',
				'order'              => 20,
				'enabled_key'        => 'tracksure_free_ga4_enabled',
				'class_name'         => 'TrackSure_GA4_Destination',
				'file_path'          => TRACKSURE_FREE_DIR . 'destinations/class-tracksure-ga4-destination.php',
				'settings_fields'    => array(
					'tracksure_free_ga4_measurement_id',
					'tracksure_free_ga4_api_secret',
					'tracksure_free_ga4_debug_mode',
					'tracksure_free_ga4_consent_mode',
				),
				'reconciliation_key' => 'ga4',
			)
		);
	}

	/**
	 * Register Free integration handlers with Core.
	 *
	 * Called via tracksure_load_integration_handlers action hook.
	 */
	private function register_integrations() {
		// Use class method instead of anonymous function to avoid circular references.
		add_action( 'tracksure_load_integration_handlers', array( $this, 'load_integration_handlers' ) );
	}

	/**
	 * Load integration handlers for Free module.
	 *
	 * SINGLE SOURCE OF TRUTH for integration metadata (name, description, icon, fields).
	 * React Admin reads from Integrations Manager, NOT from admin-extensions.php.
	 *
	 * @param TrackSure_Integrations_Manager $manager Integrations manager instance.
	 */
	public function load_integration_handlers( $manager ) {
		// Register WooCommerce integration - Single source of truth for ALL metadata.
		$manager->register_integration(
			array(
				'id'              => 'woocommerce',
				'name'            => 'WooCommerce',
				'description'     => 'Track product views, cart, checkout, and purchase events from WooCommerce',
				'icon'            => 'ShoppingCart',
				'order'           => 10,
				'auto_detect'     => 'woocommerce/woocommerce.php',
				'plugin_name'     => 'WooCommerce',
				'enabled_key'     => 'woo_integration_enabled',
				'class_name'      => 'TrackSure_WooCommerce_V2',
				'file_path'       => TRACKSURE_FREE_DIR . 'integrations/class-tracksure-woocommerce-v2.php',
				'settings_fields' => array(), // Integration enabled = all events tracked
				'tracked_events'  => array( 'view_item', 'add_to_cart', 'begin_checkout', 'purchase' ),
			)
		);

		// Register FluentCart integration - Supports both Free and Pro versions.
		$manager->register_integration(
			array(
				'id'              => 'fluentcart',
				'name'            => 'FluentCart',
				'description'     => 'Track FluentCart products, cart, checkout, purchase, and refunds',
				'icon'            => 'ShoppingBag',
				'order'           => 20,
				'auto_detect'     => 'fluent-cart/fluent-cart.php',
				'plugin_name'     => 'FluentCart',
				'enabled_key'     => 'fluentcart_integration_enabled',
				'class_name'      => 'TrackSure_FluentCart_Integration',
				'file_path'       => TRACKSURE_FREE_DIR . 'integrations/class-tracksure-fluentcart-integration.php',
				'settings_fields' => array(), // Integration enabled = all events tracked
				'tracked_events'  => array( 'view_item', 'add_to_cart', 'view_cart', 'begin_checkout', 'add_payment_info', 'purchase', 'refund' ),
			)
		);

		// Future: Register additional Free integrations here.
	}

	/**
	 * Register Free module settings with Core schema.
	 *
	 * Adds Free module destination and integration settings via filter hook.
	 */
	private function register_settings() {
		add_filter( 'tracksure_settings_schema', array( $this, 'add_free_settings' ) );
	}

	/**
	 * Add Free module settings to Core schema.
	 *
	 * @param array $settings Existing settings from Core.
	 * @return array Modified settings with Free module additions.
	 */
	public function add_free_settings( $settings ) {
		$free_settings = array(
			// Integrations (auto-detected, not stored - kept for compatibility).
			'tracksure_enabled_integrations'      => array(
				'type'        => 'array',
				'readonly'    => false,
				'default'     => array(),
				'label'       => __( 'Enabled Integrations', 'tracksure' ),
				'description' => __( 'Active e-commerce and form integrations', 'tracksure' ),
				'category'    => 'integrations',
				'in_rest'     => true,
				'in_js'       => false,
			),

			// ============================================================.
			// DESTINATIONS SETTINGS.
			// ============================================================.
			// Meta Conversions API (Facebook/Instagram).
			'tracksure_free_meta_enabled'         => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __( 'Enable Meta CAPI', 'tracksure' ),
				'description' => __( 'Send events to Facebook Conversions API', 'tracksure' ),
				'category'    => 'destinations',
				'group'       => 'meta',
				'in_rest'     => true,
				'in_js'       => false,
			),

			'tracksure_free_meta_pixel_id'        => array(
				'type'        => 'string',
				'readonly'    => false,
				'default'     => '',
				'label'       => __( 'Meta Pixel ID', 'tracksure' ),
				'description' => __( 'Your Facebook Pixel ID (numeric)', 'tracksure' ),
				'category'    => 'destinations',
				'group'       => 'meta',
				'in_rest'     => true,
				'in_js'       => false,
				'placeholder' => '1234567890',
				'required_if' => 'tracksure_free_meta_enabled',
			),

			'tracksure_free_meta_access_token'    => array(
				'type'        => 'string',
				'readonly'    => false,
				'default'     => '',
				'label'       => __( 'Meta Access Token', 'tracksure' ),
				'description' => __( 'Conversions API Access Token from Meta Events Manager', 'tracksure' ),
				'category'    => 'destinations',
				'group'       => 'meta',
				'in_rest'     => true,
				'in_js'       => false,
				'placeholder' => 'EAAG...',
				'sensitive'   => true,
				'required_if' => 'tracksure_free_meta_enabled',
			),

			'tracksure_free_meta_test_event_code' => array(
				'type'        => 'string',
				'readonly'    => false,
				'default'     => '',
				'label'       => __( 'Test Event Code', 'tracksure' ),
				'description' => __( 'Test event code from Meta Events Manager (optional, for testing)', 'tracksure' ),
				'category'    => 'destinations',
				'group'       => 'meta',
				'in_rest'     => true,
				'in_js'       => false,
				'placeholder' => 'TEST12345',
			),

			// Google Analytics 4.
			'tracksure_free_ga4_enabled'          => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __( 'Enable GA4', 'tracksure' ),
				'description' => __( 'Send events to Google Analytics 4', 'tracksure' ),
				'category'    => 'destinations',
				'group'       => 'ga4',
				'in_rest'     => true,
				'in_js'       => false,
			),

			'tracksure_free_ga4_measurement_id'   => array(
				'type'        => 'string',
				'readonly'    => false,
				'default'     => '',
				'label'       => __( 'GA4 Measurement ID', 'tracksure' ),
				'description' => __( 'Your GA4 Measurement ID (starts with G-)', 'tracksure' ),
				'category'    => 'destinations',
				'group'       => 'ga4',
				'in_rest'     => true,
				'in_js'       => false,
				'placeholder' => 'G-XXXXXXXXXX',
				'required_if' => 'tracksure_free_ga4_enabled',
			),

			'tracksure_free_ga4_api_secret'       => array(
				'type'        => 'string',
				'readonly'    => false,
				'default'     => '',
				'label'       => __( 'GA4 API Secret', 'tracksure' ),
				'description' => __( 'Measurement Protocol API Secret from GA4 Admin', 'tracksure' ),
				'category'    => 'destinations',
				'group'       => 'ga4',
				'in_rest'     => true,
				'in_js'       => false,
				'placeholder' => 'XXXXXXXXXXXXXXXX',
				'sensitive'   => true,
				'required_if' => 'tracksure_free_ga4_enabled',
			),

			'tracksure_free_ga4_debug_mode'       => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __( 'GA4 Debug Mode', 'tracksure' ),
				'description' => __( 'Send events to GA4 debug endpoint for testing (events visible in DebugView, not in reports)', 'tracksure' ),
				'category'    => 'destinations',
				'group'       => 'ga4',
				'in_rest'     => true,
				'in_js'       => false,
			),

			'tracksure_free_ga4_consent_mode'     => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => false,
				'label'       => __( 'GA4 Consent Mode V2', 'tracksure' ),
				'description' => __(
					'Enable Google Consent Mode V2. Tracking is allowed by default. When a consent plugin is installed (Complianz, CookieYes, etc.), user choices are respected automatically.',
					'tracksure'
				),
				'category'    => 'destinations',
				'group'       => 'ga4',
				'in_rest'     => true,
				'in_js'       => true,
				'js_key'      => 'ga4ConsentMode',
			),

			// ============================================================.
			// INTEGRATIONS SETTINGS.
			// ============================================================.
			// WooCommerce Integration.
			'woo_integration_enabled'             => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => true,
				'label'       => __( 'Enable WooCommerce Integration', 'tracksure' ),
				'description' => __( 'Track WooCommerce product views, cart, checkout, purchases', 'tracksure' ),
				'category'    => 'integrations',
				'group'       => 'woocommerce',
				'in_rest'     => true,
				'in_js'       => false,
			),

			// FluentCart Integration.
			'fluentcart_integration_enabled'      => array(
				'type'        => 'boolean',
				'readonly'    => false,
				'default'     => true,
				'label'       => __( 'Enable FluentCart Integration', 'tracksure' ),
				'description' => __( 'Track FluentCart product views, cart, checkout, purchases (supports Free and Pro)', 'tracksure' ),
				'category'    => 'integrations',
				'group'       => 'fluentcart',
				'in_rest'     => true,
				'in_js'       => false,
			),
		);

		// Merge Free settings with Core settings.
		return array_merge( $settings, $free_settings );
	}

	/**
	 * Initialize API filters for REST requests.
	 *
	 * Loads filters that need to run during API requests (not just admin pages).
	 */
	private function init_api_filters() {
		// Load the integration detection filter for API requests.
		require_once TRACKSURE_FREE_DIR . 'admin/free-api-filters.php';
	}

	/**
	 * Initialize query filters to format data for Free dashboards.
	 */
	private function init_query_filters() {
		add_filter( 'tracksure_query_overview', array( $this, 'format_overview_data' ), 10, 3 );
	}

	/**
	 * Format overview data for Free dashboard.
	 *
	 * @param array  $response Core response with metrics, devices, top_sources, etc.
	 * @param string $date_start Start date.
	 * @param string $date_end End date.
	 * @return array Unmodified response (Core now provides complete visitor-based data).
	 */
	public function format_overview_data( $response, $date_start, $date_end ) {
		// Core now provides visitor-based metrics with proper structure.
		// Free module no longer needs to reformat - just pass through.
		// Pro/Enterprise can still add attribution, advanced segments, etc.

		return $response;
	}

	/**
	 * Initialize first-party dashboards.
	 */
	private function init_dashboards() {
		// Admin menu is handled by Core.
		// Free module just registers dashboard routes.
		add_filter( 'tracksure/admin/routes', array( $this, 'register_dashboard_routes' ) );
	}

	/**
	 * Register dashboard routes.
	 *
	 * @param array $routes Existing routes.
	 * @return array Modified routes.
	 */
	public function register_dashboard_routes( $routes ) {
		$routes[] = array(
			'path'      => '/',
			'component' => 'OverviewPage',
			'label'     => __( 'Overview', 'tracksure' ),
			'icon'      => 'dashboard',
			'position'  => 10,
		);

		$routes[] = array(
			'path'      => '/realtime',
			'component' => 'RealtimePage',
			'label'     => __( 'Real-Time', 'tracksure' ),
			'icon'      => 'visibility',
			'position'  => 20,
		);

		$routes[] = array(
			'path'      => '/traffic-sources',
			'component' => 'TrafficSourcesPage',
			'label'     => __( 'Traffic Sources', 'tracksure' ),
			'icon'      => 'traffic',
			'position'  => 30,
		);

		$routes[] = array(
			'path'      => '/pages',
			'component' => 'PagesPage',
			'label'     => __( 'Top Pages', 'tracksure' ),
			'icon'      => 'insert_drive_file',
			'position'  => 40,
		);

		$routes[] = array(
			'path'      => '/goals',
			'component' => 'GoalsPage',
			'label'     => __( 'Goals', 'tracksure' ),
			'icon'      => 'flag',
			'position'  => 50,
		);

		$routes[] = array(
			'path'      => '/settings',
			'component' => 'SettingsPage',
			'label'     => __( 'Settings', 'tracksure' ),
			'icon'      => 'settings',
			'position'  => 100,
		);

		return $routes;
	}

	/**
	 * Initialize admin extensions (Settings, Destinations, Integrations UI).
	 *
	 * IMPORTANT: Admin Extensions provides UI organization (settings groups, tabs).
	 * Destination/Integration metadata comes from Managers (single source of truth).
	 * This hybrid approach eliminates duplication while maintaining proper UI structure.
	 */
	private function init_admin_extensions() {
		if ( ! is_admin() ) {
			return;
		}

		// Load Free admin extensions registration.
		// NOTE: Only registers settings groups and extension IDs.
		// Actual destination/integration data pulled from Managers at runtime.
		require_once TRACKSURE_FREE_DIR . 'admin/free-admin-extensions.php';
	}

	/**
	 * Initialize GA4 Setup Guide.
	 *
	 * Must run for both admin pages and REST API requests.
	 */
	private function init_ga4_setup_guide() {
		// Load GA4 Setup Guide (admin notice for required manual steps + REST API).
		if ( file_exists( TRACKSURE_FREE_DIR . 'destinations/class-tracksure-ga4-setup-guide.php' ) ) {
			require_once TRACKSURE_FREE_DIR . 'destinations/class-tracksure-ga4-setup-guide.php';
			// Initialize the setup guide to register REST API endpoints
			new TrackSure_GA4_Setup_Guide();
		}
	}
}
