<?php

/**
 *
 * TrackSure Hooks System
 *
 * Centralized hook management for core actions and filters.
 * Provides extension points for module packs (Free/Pro/3rd-party).
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Hooks class.
 */
class TrackSure_Hooks {


	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Core hooks are managed by TrackSure_Core class.
		// This class provides documentation and utility methods.
	}

	/**
	 * Fire action when event is recorded.
	 *
	 * @param array $event_data Event data.
	 */
	public static function event_recorded( $event_data ) {
		/**
		 * Fires when an event is recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param array $event_data Event data including visitor_id, session_id, event_name, params.
		 */
		do_action( 'tracksure_event_recorded', $event_data );
	}

	/**
	 * Fire action when conversion occurs.
	 *
	 * @param int   $conversion_id Conversion ID.
	 * @param array $conversion_data Conversion data.
	 */
	public static function conversion_recorded( $conversion_id, $conversion_data ) {
		/**
		 * Fires when a conversion is recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $conversion_id Conversion ID.
		 * @param array $conversion_data Conversion data.
		 */
		do_action( 'tracksure_conversion_recorded', $conversion_id, $conversion_data );
	}

	/**
	 * Filter event data before recording.
	 *
	 * @param array $event_data Event data.
	 * @return array Filtered event data.
	 */
	public static function filter_event_data( $event_data ) {
		/**
		 * Filters event data before recording.
		 *
		 * @since 1.0.0
		 *
		 * @param array $event_data Event data to be recorded.
		 */
		return apply_filters( 'tracksure_filter_event_data', $event_data );
	}

	/**
	 * Filter attribution models.
	 *
	 * @param array $models Attribution models.
	 * @return array Filtered models.
	 */
	public static function filter_attribution_models( $models ) {
		/**
		 * Filters available attribution models.
		 *
		 * @since 1.0.0
		 *
		 * @param array $models Attribution models (e.g., first_touch, last_touch, linear, time_decay).
		 */
		return apply_filters( 'tracksure_attribution_models', $models );
	}

	/**
	 * Filter destinations list.
	 *
	 * @param array $destinations Destinations.
	 * @return array Filtered destinations.
	 */
	public static function filter_destinations( $destinations ) {
		/**
		 * Filters available destinations.
		 *
		 * @since 1.0.0
		 *
		 * @param array $destinations Destination configurations (e.g., meta_capi, ga4, google_ads).
		 */
		return apply_filters( 'tracksure_destinations', $destinations );
	}

	/**
	 * Filter integrations list.
	 *
	 * @param array $integrations Integrations.
	 * @return array Filtered integrations.
	 */
	public static function filter_integrations( $integrations ) {
		/**
		 * Filters available integrations.
		 *
		 * @since 1.0.0
		 *
		 * @param array $integrations Integration configurations (e.g., woocommerce, edd, fluentcart).
		 */
		return apply_filters( 'tracksure_integrations', $integrations );
	}

	/**
	 * Filter admin dashboard widgets.
	 *
	 * @param array $widgets Dashboard widgets.
	 * @return array Filtered widgets.
	 */
	public static function filter_dashboard_widgets( $widgets ) {
		/**
		 * Filters admin dashboard widgets.
		 *
		 * @since 1.0.0
		 *
		 * @param array $widgets Widget configurations.
		 */
		return apply_filters( 'tracksure_dashboard_widgets', $widgets );
	}

	/**
	 * Filter admin navigation items.
	 *
	 * @param array $nav_items Navigation items.
	 * @return array Filtered nav items.
	 */
	public static function filter_admin_nav( $nav_items ) {
		/**
		 * Filters admin navigation items.
		 *
		 * @since 1.0.0
		 *
		 * @param array $nav_items Navigation item configurations.
		 */
		return apply_filters( 'tracksure_admin_nav', $nav_items );
	}

	/**
	 * Filter REST API routes.
	 *
	 * @param array $routes REST routes.
	 * @return array Filtered routes.
	 */
	public static function filter_rest_routes( $routes ) {
		/**
		 * Filters REST API routes.
		 *
		 * @since 1.0.0
		 *
		 * @param array $routes Route configurations.
		 */
		return apply_filters( 'tracksure_rest_routes', $routes );
	}

	/**
	 * Register module hook.
	 *
	 * @param string $module_id Module ID.
	 * @param string $module_path Module path.
	 * @param array  $module_config Module configuration.
	 */
	public static function register_module( $module_id, $module_path, $module_config ) {
		/**
		 * Action hook to register a module pack.
		 *
		 * @since 1.0.0
		 *
		 * @param string $module_id Module identifier.
		 * @param string $module_path Absolute path to module directory.
		 * @param array  $module_config Module configuration array.
		 */
		do_action( 'tracksure_register_module', $module_id, $module_path, $module_config );
	}
}
