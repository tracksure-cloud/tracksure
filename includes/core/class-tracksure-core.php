<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for core initialization diagnostics, only fires when WP_DEBUG=true
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Consent data read from cookies, no state change

/**
 *
 * TrackSure Core Main Class
 *
 * The central service container and module registry for TrackSure.
 * All tracking, analytics, and attribution logic lives here.
 * Free/Pro/3rd-party plugins register modules into this core.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 * @throws Exception
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main TrackSure Core class.
 *
 * Singleton pattern ensures only one instance exists.
 *
 * @throws Exception
 */
final class TrackSure_Core {






	/**
	 * Single instance of the class.
	 *
	 * @var TrackSure_Core
	 * @throws Exception
	 */
	private static $instance = null;

	/**
	 * Service container.
	 *
	 * @var array
	 * @throws Exception
	 */
	private $services = array();

	/**
	 * Registered module packs (Free, Pro, 3rd-party).
	 *
	 * @var array
	 * @throws Exception
	 */
	private $modules = array();

	/**
	 * Module capabilities registry.
	 *
	 * @var array
	 * @throws Exception
	 */
	private $capabilities = array(
		'dashboards'   => array(),
		'destinations' => array(),
		'integrations' => array(),
		'features'     => array(),
	);

	/**
	 * Core loaded flag.
	 *
	 * @var bool
	 * @throws Exception
	 */
	private $loaded = false;

	/**
	 * Get single instance.
	 *
	 * @return TrackSure_Core
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
		$this->define_constants();
		$this->load_dependencies();
		$this->init_hooks();
		// Boot services IMMEDIATELY so they're available when tracksure_loaded hook fires.
		// Pro plugins hook into tracksure_loaded and need managers to be ready.
		$this->boot_services();
	}

	/**
	 * Define additional constants.
	 */
	private function define_constants() {
		if ( ! defined( 'TRACKSURE_ASSETS_URL' ) ) {
			define( 'TRACKSURE_ASSETS_URL', TRACKSURE_CORE_URL . 'assets/' );
		}
		if ( ! defined( 'TRACKSURE_BUILD_URL' ) ) {
			define( 'TRACKSURE_BUILD_URL', TRACKSURE_CORE_URL . 'build/' );
		}
		if ( ! defined( 'TRACKSURE_REGISTRY_DIR' ) ) {
			define( 'TRACKSURE_REGISTRY_DIR', TRACKSURE_CORE_DIR . 'registry/' );
		}
	}

	/**
	 * Load core dependencies.
	 */
	private function load_dependencies() {
		// Files are directly in TRACKSURE_CORE_DIR (no extra 'includes/' subdirectory).
		$includes = TRACKSURE_CORE_DIR;

		// Core utilities (load first - used by other classes).
		require_once $includes . 'utils/class-tracksure-utilities.php';
		require_once $includes . 'utils/countries.php';
		require_once $includes . 'utils/class-tracksure-location-formatter.php';

		// Currency handling (used by all e-commerce adapters).
		require_once $includes . 'class-tracksure-currency-config.php';
		require_once $includes . 'class-tracksure-currency-handler.php';

		// Core infrastructure.
		require_once $includes . 'class-tracksure-hooks.php';
		require_once $includes . 'class-tracksure-db.php';
		require_once $includes . 'class-tracksure-installer.php';
		require_once $includes . 'class-tracksure-settings-schema.php';
		require_once $includes . 'class-tracksure-event-bridge.php';

		// Registry system.
		require_once $includes . 'registry/class-tracksure-registry.php';
		require_once $includes . 'registry/class-tracksure-registry-loader.php';
		require_once $includes . 'registry/class-tracksure-registry-cache.php';

		// Abstractions (interfaces and base classes) - MUST load before implementations.
		require_once $includes . 'abstractions/interface-tracksure-ecommerce-adapter.php';
		require_once $includes . 'abstractions/class-tracksure-data-normalizer.php';

		// Services.
		require_once $includes . 'services/class-tracksure-logger.php';
		require_once $includes . 'services/class-tracksure-rate-limiter.php';
		require_once $includes . 'services/class-tracksure-url-normalizer.php'; // URL normalization (single source of truth)
		require_once $includes . 'services/class-tracksure-session-manager.php';
		require_once $includes . 'services/class-tracksure-attribution-resolver.php';
		require_once $includes . 'services/class-tracksure-journey-engine.php';
		require_once $includes . 'services/class-tracksure-event-builder.php'; // NEW: Centralized event building
		require_once $includes . 'services/class-tracksure-event-mapper.php'; // NEW: Registry-based event mapping
		require_once $includes . 'services/class-tracksure-event-recorder.php';
		require_once $includes . 'services/class-tracksure-event-queue.php';
		require_once $includes . 'services/class-tracksure-action-scheduler.php';
		require_once $includes . 'services/class-tracksure-goal-validator.php'; // Goal validation
		require_once $includes . 'services/class-tracksure-goal-evaluator.php';
		require_once $includes . 'services/class-tracksure-consent-manager.php'; // Full-featured with geography + Consent Mode V2
		require_once $includes . 'services/class-tracksure-geolocation.php';
		require_once $includes . 'services/class-tracksure-suggestion-engine.php';

		// Attribution & Journey services (NEW).
		require_once $includes . 'services/class-tracksure-touchpoint-recorder.php';
		require_once $includes . 'services/class-tracksure-conversion-recorder.php';
		require_once $includes . 'services/class-tracksure-funnel-analyzer.php';
		require_once $includes . 'services/class-tracksure-attribution-analytics.php'; // NEW: Attribution insights & aggregated analytics
		require_once $includes . 'services/class-tracksure-attribution-hooks.php';

		// REST API.
		require_once $includes . 'api/class-tracksure-rest-api.php';
		require_once $includes . 'api/tracksure-consent-api.php'; // Public consent API for 3rd parties

		// Module system.
		require_once $includes . 'modules/interface-tracksure-module.php';
		require_once $includes . 'modules/class-tracksure-module-registry.php';

		// Destinations & Integrations Managers.
		require_once $includes . 'destinations/class-tracksure-destinations-manager.php';
		require_once $includes . 'integrations/class-tracksure-integrations-manager.php';

		// Background jobs.
		require_once $includes . 'jobs/class-tracksure-delivery-worker.php';
		require_once $includes . 'jobs/class-tracksure-cleanup-worker.php';

		// Aggregation workers (NEW).
		require_once $includes . 'jobs/class-tracksure-hourly-aggregator.php';
		require_once $includes . 'jobs/class-tracksure-daily-aggregator.php';

		// Admin UI.
		if ( is_admin() ) {
			require_once $includes . 'admin/class-tracksure-admin-ui.php';
			require_once $includes . 'admin/class-tracksure-admin-extensions.php';
		}

		// Front-end tracking.
		if ( ! is_admin() ) {
			require_once $includes . 'tracking/class-tracksure-tracker-assets.php';
			require_once $includes . 'tracking/class-tracksure-checkout-tracking.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Localization.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Database upgrade check.
		add_action( 'admin_init', array( $this, 'maybe_upgrade_database' ) );

		// Ensure permalinks are flushed (one-time check on admin load).
		add_action( 'admin_init', array( $this, 'ensure_permalinks_flushed' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Cron schedules.
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Module registration hook.
		add_action( 'tracksure_register_module', array( $this, 'register_module' ), 10, 3 );

		// Late init for modules.
		add_action( 'init', array( $this, 'init_modules' ), 20 );
	}

	/**
	 * Boot core services.
	 * Called on plugins_loaded priority 999 to ensure proper initialization order.
	 */
	public function boot_services() {
		// Prevent double initialization.
		if ( $this->loaded ) {
			return;
		}

		// Initialize service container.
		$this->services['logger']                = new TrackSure_Logger();
		$this->services['db']                    = TrackSure_DB::get_instance();
		$this->services['registry']              = TrackSure_Registry::get_instance();
		$this->services['rate_limiter']          = TrackSure_Rate_Limiter::get_instance();
		$this->services['url_normalizer']        = new TrackSure_URL_Normalizer(); // URL normalization service
		$this->services['session_manager']       = TrackSure_Session_Manager::get_instance();
		$this->services['attribution']           = TrackSure_Attribution_Resolver::get_instance();
		$this->services['journey']               = TrackSure_Journey_Engine::get_instance();
		$this->services['attribution_analytics'] = TrackSure_Attribution_Analytics::get_instance(); // NEW: Attribution insights & analytics
		$this->services['event_builder']         = TrackSure_Event_Builder::get_instance(); // NEW: Centralized event building
		$this->services['event_mapper']          = TrackSure_Event_Mapper::get_instance(); // NEW: Registry-based event mapping
		$this->services['event_recorder']        = TrackSure_Event_Recorder::get_instance();
		$this->services['consent_manager']       = TrackSure_Consent_Manager::get_instance();
		$this->services['geolocation']           = TrackSure_Geolocation::get_instance(); // NEW: Geolocation service
		$this->services['rest_api']              = TrackSure_REST_API::get_instance();
		$this->services['module_registry']       = TrackSure_Module_Registry::get_instance();

		// Initialize attribution hooks (CRITICAL - connects session manager to touchpoint recorder).
		$this->services['attribution_hooks'] = TrackSure_Attribution_Hooks::get_instance();

		// Initialize Event Bridge (coordinates browser + server tracking).
		$this->services['event_bridge'] = new TrackSure_Event_Bridge( $this );

		// Initialize destinations & integrations managers.
		$this->services['destinations_manager'] = new TrackSure_Destinations_Manager( $this );
		$this->services['integrations_manager'] = new TrackSure_Integrations_Manager( $this );

		// Initialize background jobs.
		$this->services['delivery_worker'] = TrackSure_Delivery_Worker::get_instance();
		$this->services['cleanup_worker']  = TrackSure_Cleanup_Worker::get_instance();

		// REMOVED: Suggestion Engine has database schema errors (missing columns: goals.status, outbox.error_msg, events.utm_source, visitors.first_seen_at).
		// Feature will be re-implemented in future release with correct schema.
		// $this->services['suggestion_engine'] = TrackSure_Suggestion_Engine::get_instance();

		// Initialize admin UI.
		if ( is_admin() ) {
			$this->services['admin_ui']         = TrackSure_Admin_UI::get_instance();
			$this->services['admin_extensions'] = TrackSure_Admin_Extensions::get_instance();
		}

		// Initialize front-end tracker.
		if ( ! is_admin() ) {
			$this->services['tracker_assets'] = TrackSure_Tracker_Assets::get_instance();
		}

		// Schedule background jobs.
		$this->schedule_jobs();

		// Mark as loaded.
		$this->loaded = true;

		/**
		 * Fires after TrackSure Core services are booted.
		 *
		 * @since 1.0.0
		 *
		 * @param TrackSure_Core $core The core instance.
		 */
		do_action( 'tracksure_core_booted', $this );
	}

	/**
	 * Get service from container.
	 *
	 * @param string $service Service name.
	 * @return mixed|null Service instance or null if not found.
	 */
	public function get_service( $service ) {
		return isset( $this->services[ $service ] ) ? $this->services[ $service ] : null;
	}

	/**
	 * Check if core is loaded.
	 *
	 * @return bool
	 */
	public function is_loaded() {
		return $this->loaded;
	}

	/**
	 * Register a module pack (Free, Pro, or 3rd-party).
	 *
	 * @param string $module_id Module ID (e.g., 'tracksure-free', 'tracksure-pro').
	 * @param string $module_path Module directory path.
	 * @param array  $module_config Module configuration.
	 */
	public function register_module( $module_id, $module_path, $module_config = array() ) {
		if ( isset( $this->modules[ $module_id ] ) ) {
			return; // Already registered.
		}

		$this->modules[ $module_id ] = array(
			'id'      => $module_id,
			'path'    => $module_path,
			'config'  => $module_config,
			'version' => isset( $module_config['version'] ) ? $module_config['version'] : '1.0.0',
			'loaded'  => false,
		);

		// Store in database for persistence.
		$registered_modules               = get_option( 'tracksure_registered_modules', array() );
		$registered_modules[ $module_id ] = array(
			'version'       => $this->modules[ $module_id ]['version'],
			'registered_at' => time(),
		);
		update_option( 'tracksure_registered_modules', $registered_modules );

		/**
		 * Fires when a module is registered.
		 *
		 * @since 1.0.0
		 *
		 * @param string $module_id Module ID.
		 * @param string $module_path Module path.
		 * @param array  $module_config Module configuration.
		 * @throws Exception
		 */
		do_action( 'tracksure_module_registered', $module_id, $module_path, $module_config );
	}

	/**
	 * Initialize all registered modules.
	 */
	public function init_modules() {
		foreach ( $this->modules as $module_id => $module ) {
			if ( $module['loaded'] ) {
				continue;
			}

			// Load module bootstrap file if exists.
			$bootstrap_file = trailingslashit( $module['path'] ) . 'bootstrap.php';
			if ( file_exists( $bootstrap_file ) ) {
				require_once $bootstrap_file;
				$this->modules[ $module_id ]['loaded'] = true;
			}
		}

		/**
		 * Fires after all modules are initialized.
		 *
		 * @since 1.0.0
		 */
		do_action( 'tracksure_modules_initialized' );
	}

	/**
	 * Get registered modules.
	 *
	 * @return array
	 */
	public function get_modules() {
		return $this->modules;
	}

	/**
	 * Register a capability (dashboard, destination, integration, feature).
	 *
	 * @param string $type Capability type.
	 * @param string $id Capability ID.
	 * @param array  $config Capability configuration.
	 */
	public function register_capability( $type, $id, $config ) {
		$module_registry = $this->get_service( 'module_registry' );
		if ( $module_registry ) {
			$module_registry->register_capability( $type, $id, $config );
		}
	}

	/**
	 * Get capabilities by type.
	 *
	 * @param string $type Capability type.
	 * @return array
	 */
	public function get_capabilities( $type ) {
		$module_registry = $this->get_service( 'module_registry' );
		return $module_registry ? $module_registry->get_capabilities( $type ) : array();
	}

	/**
	 * Load plugin textdomain for translations.
	 *
	 * Note: Core is bundled inside main plugin, so textdomain
	 * is handled by the main plugin (tracksure.php).
	 */
	public function load_textdomain() {
		// Textdomain already loaded by main plugin.
		// Core uses 'tracksure' text domain, not 'tracksure-core'.
	}

	/**
	 * Check and run database upgrade if needed.
	 *
	 * @throws Exception
	 */
	// public function maybe_upgrade_database().
	// {
	// Only run once per admin session.
	// if (get_transient('tracksure_db_check_done')) {.
	// return;
	// }

	// if (TrackSure_Installer::needs_upgrade()) {.
	// TrackSure_Installer::install();
	// }

	// Cache check for 1 hour.
	// set_transient('tracksure_db_check_done', true, HOUR_IN_SECONDS);
	// }

	/**
	 * Ensure permalinks are flushed for REST API routes.
	 *
	 * Runs once on admin_init after plugin activation.
	 */
	public function ensure_permalinks_flushed() {
		// Check if permalink flush is needed (set during activation).
		if ( get_option( 'tracksure_needs_permalink_flush' ) === '1' ) {
			// Hard flush permalinks.
			flush_rewrite_rules( true );

			// Clear the flag.
			delete_option( 'tracksure_needs_permalink_flush' );
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$rest_api = TrackSure_REST_API::get_instance();
		$rest_api->register_routes();
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
		// NOTE: The 'tracksure_every_minute' schedule for delivery is registered
		// by TrackSure_Action_Scheduler class (its own cron_schedules filter).

		// Every 5 minutes.
		$schedules['tracksure_five_minutes'] = array(
			'interval' => 300,
			'display'  => 'Every 5 Minutes (TrackSure)',
		);

		// Every 15 minutes.
		$schedules['tracksure_fifteen_minutes'] = array(
			'interval' => 900,
			'display'  => 'Every 15 Minutes (TrackSure)',
		);

		// Hourly aggregation.
		$schedules['tracksure_hourly'] = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => 'TrackSure Hourly Aggregation',
		);

		// Daily aggregation.
		$schedules['tracksure_daily'] = array(
			'interval' => DAY_IN_SECONDS,
			'display'  => 'TrackSure Daily Aggregation',
		);

		return $schedules;
	}

	/**
	 * Schedule background jobs.
	 */
	private function schedule_jobs() {
		// NOTE: Delivery worker scheduling is handled entirely by TrackSure_Action_Scheduler
		// (uses Action Scheduler if available, falls back to WP-Cron).
		// Do NOT add 'tracksure_delivery_worker' here — it would create duplicate delivery runs.
		$jobs = array(
			'tracksure_aggregate_hourly' => 'hourly',
			'tracksure_aggregate_daily'  => 'daily',
			'tracksure_cleanup_data'     => 'daily',
			'tracksure_cleanup_logs'     => 'daily',
		);

		foreach ( $jobs as $hook => $recurrence ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time(), $recurrence, $hook );
			}
		}

		// Clean up legacy delivery worker hook (was removed in favor of Action Scheduler integration).
		if ( wp_next_scheduled( 'tracksure_delivery_worker' ) ) {
			wp_clear_scheduled_hook( 'tracksure_delivery_worker' );
		}

		// Register job handlers.
		add_action( 'tracksure_aggregate_hourly', array( $this, 'run_hourly_aggregation' ) );
		add_action( 'tracksure_aggregate_daily', array( $this, 'run_daily_aggregation' ) );
		add_action( 'tracksure_cleanup_data', array( $this, 'run_cleanup' ) );
		add_action( 'tracksure_cleanup_logs', array( $this, 'run_log_cleanup' ) );

		// Force aggregation check on admin load (for low-traffic sites).
		add_action( 'admin_init', array( $this, 'maybe_force_aggregation' ) );

		// Check database schema upgrades.
		add_action( 'admin_init', array( $this, 'maybe_upgrade_database' ) );
	}

	/**
	 * Force aggregation if it hasn't run recently.
	 * Prevents empty data on low-traffic sites where WP-Cron doesn't trigger.
	 */
	public function maybe_force_aggregation() {
		// Only run on TrackSure admin pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin menu page slug, not a form submission.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( empty( $page ) || strpos( $page, 'tracksure' ) === false ) {
			return;
		}

		// CRITICAL: Prevent recursion - check if we're already running aggregation.
		if ( get_transient( 'tracksure_aggregation_running' ) ) {
			return;
		}

		// CRITICAL: Check available memory before running heavy queries.
		$memory_limit       = ini_get( 'memory_limit' );
		$memory_limit_bytes = wp_convert_hr_to_bytes( $memory_limit );
		$memory_used        = memory_get_usage( true );
		$memory_available   = $memory_limit_bytes - $memory_used;

		// Need at least 64MB free to safely run aggregation.
		if ( $memory_available < ( 64 * 1024 * 1024 ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( '[TrackSure] Skipping aggregation - insufficient memory. Available: ' . size_format( $memory_available ) . ', Needed: 64MB' );
			}
			return;
		}

		$last_hourly = get_option( 'tracksure_last_hourly_agg', 0 );
		$last_daily  = get_option( 'tracksure_last_daily_agg', 0 );

		// If hourly aggregation hasn't run in 2 hours, force it (but not on every page load).
		if ( $last_hourly && ( time() - strtotime( $last_hourly ) > 7200 ) ) {
			// Set lock to prevent concurrent runs.
			set_transient( 'tracksure_aggregation_running', true, 300 ); // 5 minute lock

			try {
				$hourly_aggregator = TrackSure_Hourly_Aggregator::get_instance();
				$hourly_aggregator->aggregate_last_hour();
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

					error_log( '[TrackSure] Hourly aggregation failed: ' . $e->getMessage() );
				}
			} finally {
				delete_transient( 'tracksure_aggregation_running' );
			}
		}

		// If daily aggregation hasn't run in 25 hours, force it (but not on every page load).
		if ( $last_daily && ( time() - strtotime( $last_daily ) > 90000 ) ) {
			// Set lock to prevent concurrent runs.
			set_transient( 'tracksure_aggregation_running', true, 300 ); // 5 minute lock

			try {
				$daily_aggregator = TrackSure_Daily_Aggregator::get_instance();
				$daily_aggregator->aggregate_yesterday();
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

					error_log( '[TrackSure] Daily aggregation failed: ' . $e->getMessage() );
				}
			} finally {
				delete_transient( 'tracksure_aggregation_running' );
			}
		}
	}

	/**
	 * Check if database needs upgrading.
	 *
	 * REMOVED: Plugin not published yet - users will uninstall/reinstall during development.
	 * Will add migration logic when publishing to WordPress.org.
	 *
	 * @deprecated Not needed for unpublished plugin
	 */
	public function maybe_upgrade_database() {
		// No-op - fresh installs only during development.
		// Migration logic will be added if publishing to WordPress.org.
	}

	/**
	 * Run hourly aggregation job.
	 */
	public function run_hourly_aggregation() {
		$hourly_aggregator = TrackSure_Hourly_Aggregator::get_instance();
		$hourly_aggregator->aggregate_last_hour();
	}

	/**
	 * Run daily aggregation job.
	 */
	public function run_daily_aggregation() {
		$daily_aggregator = TrackSure_Daily_Aggregator::get_instance();
		$daily_aggregator->aggregate_yesterday();
	}

	/**
	 * Run cleanup job.
	 */
	public function run_cleanup() {
		if ( isset( $this->services['cleanup_worker'] ) ) {
			$this->services['cleanup_worker']->cleanup();
		}
	}

	/**
	 * Run delivery worker job.
	 */
	public function run_delivery() {
		if ( isset( $this->services['delivery_worker'] ) ) {
			$this->services['delivery_worker']->process_outbox();
		}
	}

	/**
	 * Run log cleanup job.
	 */
	public function run_log_cleanup() {
		if ( isset( $this->services['logger'] ) ) {
			$this->services['logger']->cleanup_old_logs();
		}
	}

	/**
	 * Create database tables.
	 * Called on plugin activation.
	 */
	public function create_tables() {
		require_once TRACKSURE_CORE_DIR . 'class-tracksure-installer.php';
		TrackSure_Installer::install();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @throws Exception When attempting to unserialize singleton instance.
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}
}
