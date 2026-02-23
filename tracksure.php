<?php

/**
 * Plugin Name: TrackSure
 * Plugin URI: https://tracksure.cloud
 * Description: Server-side tracking, analytics and pixel manager for WordPress. Boost ROAS with Conversion API (CAPI), recover lost conversions from iOS and cookie blockers, and run privacy-friendly first-party analytics with or without ads.
 * Version: 1.0.0
 * Author: TrackSure Team
 * Author URI: https://profiles.wordpress.org/tracksure/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tracksure
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package TrackSure
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

// Define plugin constants.
define('TRACKSURE_VERSION', '1.0.0');
define('TRACKSURE_DB_VERSION', '1.0.0');
define('TRACKSURE_PLUGIN_FILE', __FILE__);
define('TRACKSURE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRACKSURE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRACKSURE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Core paths (bundled inside free plugin).
define('TRACKSURE_CORE_DIR', TRACKSURE_PLUGIN_DIR . 'includes/core/');
define('TRACKSURE_CORE_URL', TRACKSURE_PLUGIN_URL . 'includes/core/');
define('TRACKSURE_FREE_DIR', TRACKSURE_PLUGIN_DIR . 'includes/free/');
define('TRACKSURE_REGISTRY_DIR', TRACKSURE_PLUGIN_DIR . 'registry/');
define('TRACKSURE_ADMIN_DIR', TRACKSURE_PLUGIN_DIR . 'admin/');

/**
 * Main TrackSure Plugin Class.
 *
 * Bootstrap pattern: Loads Core engine + Free module pack.
 * Fires 'tracksure_loaded' hook for Pro/3rd-party extensions.
 *
 * @since 1.0.0
 */
final class TrackSure
{

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @var TrackSure|null
	 */
	private static $instance = null;

	/**
	 * Core engine instance.
	 *
	 * @since 1.0.0
	 * @var TrackSure_Core|null
	 */
	public $core = null;

	/**
	 * Free module instance.
	 *
	 * @since 1.0.0
	 * @var TrackSure_Free|null
	 */
	public $free = null;

	/**
	 * Get plugin instance (Singleton).
	 *
	 * @since 1.0.0
	 * @return TrackSure
	 */
	public static function instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize plugin.
	 *
	 * @since 1.0.0
	 */
	private function __construct()
	{
		add_action('plugins_loaded', array($this, 'init'), 10);
	}

	/**
	 * Initialize plugin: Load Core engine + Free module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init()
	{
		// 1. Load Core engine (bundled inside).
		if (! file_exists(TRACKSURE_CORE_DIR . 'class-tracksure-core.php')) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__('TrackSure Error: Core engine file not found. Please reinstall the plugin.', 'tracksure')
					);
				}
			);
			return;
		}

		require_once TRACKSURE_CORE_DIR . 'class-tracksure-core.php';
		$this->core = TrackSure_Core::get_instance();

		// 2. Load Free module pack.
		if (! file_exists(TRACKSURE_FREE_DIR . 'class-tracksure-free.php')) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__('TrackSure Error: Free module file not found. Please reinstall the plugin.', 'tracksure')
					);
				}
			);
			return;
		}

		require_once TRACKSURE_FREE_DIR . 'class-tracksure-free.php';
		$this->free = new TrackSure_Free($this->core);

		// 3. Fire extension hook (Pro/3rd-party can register features).
		do_action('tracksure_loaded', $this->core);
	}

	/**
	 * Activation callback: Create database tables and schedule cron jobs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate()
	{
		// Load required core files.
		require_once TRACKSURE_CORE_DIR . 'class-tracksure-db.php';
		require_once TRACKSURE_CORE_DIR . 'class-tracksure-settings-schema.php';
		require_once TRACKSURE_CORE_DIR . 'class-tracksure-installer.php';

		// Create database tables.
		TrackSure_Installer::install();

		// Set flag to flush permalinks on next admin load.
		// Can't flush here because TrackSure_Core isn't loaded yet.
		update_option('tracksure_needs_permalink_flush', '1');

		// Set flag to redirect to settings page on first admin load.
		set_transient('tracksure_activation_redirect', true, 60);

		// Clear all scheduled cron jobs to prevent duplicates.
		wp_clear_scheduled_hook('tracksure_aggregate_hourly');
		wp_clear_scheduled_hook('tracksure_aggregate_daily');
		wp_clear_scheduled_hook('tracksure_delivery_worker');
		wp_clear_scheduled_hook('tracksure_cleanup_data');
		wp_clear_scheduled_hook('tracksure_cleanup_logs');

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback: Clean up scheduled tasks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate()
	{
		// Clear all scheduled cron jobs.
		wp_clear_scheduled_hook('tracksure_aggregate_hourly');
		wp_clear_scheduled_hook('tracksure_aggregate_daily');
		wp_clear_scheduled_hook('tracksure_delivery_worker');
		wp_clear_scheduled_hook('tracksure_cleanup_data');
		wp_clear_scheduled_hook('tracksure_cleanup_logs');

		// Flush rewrite rules to remove REST API routes.
		flush_rewrite_rules();
	}
}

/**
 * Get main plugin instance.
 *
 * @since 1.0.0
 * @return TrackSure
 */
function tracksure()
{
	return TrackSure::instance();
}

/**
 * Redirect to TrackSure settings page after plugin activation.
 *
 * Uses a transient flag set during activate() to ensure redirect only happens once.
 * Skips redirect during bulk activations, WP-CLI, and AJAX requests.
 *
 * @since 1.0.0
 * @return void
 */
function tracksure_activation_redirect()
{
	// Check if we need to redirect.
	if (! get_transient('tracksure_activation_redirect')) {
		return;
	}

	// Delete the transient so this only runs once.
	delete_transient('tracksure_activation_redirect');

	// Don't redirect during bulk activation (activating multiple plugins at once).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check, no data processed.
	if (isset($_GET['activate-multi'])) {
		return;
	}

	// Don't redirect during AJAX, WP-CLI, or REST API requests.
	if (wp_doing_ajax() || (defined('WP_CLI') && constant('WP_CLI')) || defined('REST_REQUEST')) {
		return;
	}

	// Only redirect if user has permission to access TrackSure.
	if (! current_user_can('manage_options')) {
		return;
	}

	// Redirect to TrackSure settings page (React app #/settings route).
	wp_safe_redirect(admin_url('admin.php?page=tracksure#/settings'));
	exit;
}

// Register activation/deactivation hooks BEFORE initializing plugin.
register_activation_hook(TRACKSURE_PLUGIN_FILE, array('TrackSure', 'activate'));
register_deactivation_hook(TRACKSURE_PLUGIN_FILE, array('TrackSure', 'deactivate'));

// Redirect to settings page after first activation.
add_action('admin_init', 'tracksure_activation_redirect');

// Initialize plugin.
tracksure();
