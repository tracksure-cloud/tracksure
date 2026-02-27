<?php

/**
 * Admin UI handler for TrackSure.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for admin UI diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Admin UI
 *
 * Enqueues React SPA for admin interface.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI class.
 */
class TrackSure_Admin_UI {






	/**
	 * Instance.
	 *
	 * @var TrackSure_Admin_UI
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Admin_UI
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'TrackSure', 'tracksure' ),
			__( 'TrackSure', 'tracksure' ),
			'manage_options',
			'tracksure',
			array( $this, 'render_admin_page' ),
			'dashicons-chart-area',
			56
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		?>
		<div id="tracksure-admin-root"></div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only load on TrackSure admin pages.
		if ( empty( $hook ) || strpos( $hook, 'tracksure' ) === false ) {
			return;
		}

		// Use TRACKSURE_ADMIN_DIR which points to plugin root admin folder.
		$admin_dir = defined( 'TRACKSURE_ADMIN_DIR' ) ? TRACKSURE_ADMIN_DIR : TRACKSURE_PLUGIN_DIR . 'admin/';
		$admin_url = defined(
			'TRACKSURE_ADMIN_DIR'
		) ? str_replace(
			TRACKSURE_PLUGIN_DIR,
			TRACKSURE_PLUGIN_URL,
			$admin_dir
		) : TRACKSURE_PLUGIN_URL . 'admin/';

		// Check if built assets exist.
		$script_file = $admin_dir . 'dist/tracksure-admin.js';
		if ( ! file_exists( $script_file ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'TrackSure admin assets not built. Run: cd admin && npm install && npm run build', 'tracksure' )
			);
			return;
		}

		// Enqueue React and ReactDOM from WordPress core.
		wp_enqueue_script( 'react' );
		wp_enqueue_script( 'react-dom' );
		wp_enqueue_script( 'wp-i18n' );

		// Webpack code splitting: Enqueue chunks in correct order
		// 1. Runtime (webpack module loader - MUST be first)
		$runtime_file = $admin_dir . 'dist/runtime.js';
		if ( file_exists( $runtime_file ) ) {
			wp_enqueue_script(
				'tracksure-runtime',
				$admin_url . 'dist/runtime.js',
				array( 'react', 'react-dom', 'wp-i18n' ),
				filemtime( $runtime_file ),
				true
			);
		}

		// 2. Core vendors (axios, react-query, etc. - small, frequently used)
		$vendors_file = $admin_dir . 'dist/vendors.js';
		if ( file_exists( $vendors_file ) ) {
			wp_enqueue_script(
				'tracksure-vendors',
				$admin_url . 'dist/vendors.js',
				array( 'react', 'react-dom', 'tracksure-runtime' ),
				filemtime( $vendors_file ),
				true
			);
		}

		// 3. React Router (navigation - needed immediately)
		$router_file = $admin_dir . 'dist/react-router.js';
		if ( file_exists( $router_file ) ) {
			wp_enqueue_script(
				'tracksure-react-router',
				$admin_url . 'dist/react-router.js',
				array( 'react', 'react-dom', 'tracksure-runtime', 'tracksure-vendors' ),
				filemtime( $router_file ),
				true
			);
		}

		// 4. Lucide icons (tree-shaken icons - loaded upfront for UI)
		$lucide_file = $admin_dir . 'dist/lucide.js';
		if ( file_exists( $lucide_file ) ) {
			wp_enqueue_script(
				'tracksure-lucide',
				$admin_url . 'dist/lucide.js',
				array( 'react', 'react-dom', 'tracksure-runtime' ),
				filemtime( $lucide_file ),
				true
			);
		}

		// 5. Common code (shared across pages)
		$common_file = $admin_dir . 'dist/common.js';
		if ( file_exists( $common_file ) ) {
			wp_enqueue_script(
				'tracksure-common',
				$admin_url . 'dist/common.js',
				array( 'react', 'react-dom', 'tracksure-runtime', 'tracksure-vendors' ),
				filemtime( $common_file ),
				true
			);
		}

		// 6. Main app (depends on all core chunks)
		$deps = array( 'react', 'react-dom', 'tracksure-runtime' );
		if ( file_exists( $vendors_file ) ) {
			$deps[] = 'tracksure-vendors';
		}
		if ( file_exists( $router_file ) ) {
			$deps[] = 'tracksure-react-router';
		}
		if ( file_exists( $lucide_file ) ) {
			$deps[] = 'tracksure-lucide';
		}
		if ( file_exists( $common_file ) ) {
			$deps[] = 'tracksure-common';
		}

		wp_enqueue_script(
			'tracksure-admin',
			$admin_url . 'dist/tracksure-admin.js',
			$deps,
			filemtime( $script_file ),
			true
		);

		// Pass config to JavaScript.
		// Detect currency from active e-commerce platform (WooCommerce, FluentCart, EDD, SureCart).
		$currency        = 'USD';
		$currency_symbol = '$';

		if ( function_exists( 'get_woocommerce_currency' ) ) {
			// WooCommerce (also covers CartFlows which uses WooCommerce).
			$currency        = get_woocommerce_currency();
			$currency_symbol = html_entity_decode( get_woocommerce_currency_symbol() );
		} elseif ( class_exists( 'FluentCart\App\Helpers\Helper' ) ) {
			// FluentCart.
			try {
				$fc_currency = \FluentCart\App\Helpers\Helper::shopConfig( 'currency' );
				if ( ! empty( $fc_currency ) ) {
					$currency = $fc_currency;
					// FluentCart may provide symbol via shopConfig.
					$fc_symbol = \FluentCart\App\Helpers\Helper::shopConfig( 'currency_sign' );
					if ( ! empty( $fc_symbol ) ) {
						$currency_symbol = html_entity_decode( $fc_symbol );
					}
				}
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fallback to USD if FluentCart API fails.
			}
		} elseif ( function_exists( 'edd_get_currency' ) ) {
			// Easy Digital Downloads.
			$currency = edd_get_currency();
			if ( function_exists( 'edd_currency_symbol' ) ) {
				$currency_symbol = html_entity_decode( edd_currency_symbol( $currency ) );
			}
		} elseif ( function_exists( 'surecart' ) && class_exists( '\SureCart\Models\ApiToken' ) ) {
			// SureCart — currency comes from store settings.
			$sc_currency = get_option( 'surecart_store_currency', 'USD' );
			if ( ! empty( $sc_currency ) ) {
				$currency = strtoupper( $sc_currency );
			}
		}

		/**
		 * Filter the detected currency for admin UI.
		 * Allows 3rd-party e-commerce platforms to provide their currency.
		 *
		 * @since 1.0.0
		 *
		 * @param string $currency ISO 4217 currency code.
		 * @param string $currency_symbol Currency symbol.
		 */
		$currency = apply_filters( 'tracksure_admin_currency', $currency, $currency_symbol );

		$config = array(
			'apiUrl'         => rest_url( 'ts/v1' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'siteUrl'        => get_site_url(),
			'timezone'       => wp_timezone_string(),
			'dateFormat'     => get_option( 'date_format' ),
			'isEcommerce'    => $this->is_ecommerce_active(),
			'currency'       => $currency,
			'currencySymbol' => $currency_symbol,
		);

		wp_localize_script( 'tracksure-admin', 'trackSureAdmin', $config );

		// Trigger extension registration hook for UI organization.
		// Extensions (Free/Pro) register settings groups, destinations, integrations UI metadata.
		$extensions_registry = TrackSure_Admin_Extensions::get_instance();

		/**
		 * Allow Free/Pro/3rd party to register admin UI extensions.
		 *
		 * @since 1.0.0
		 *
		 * @param TrackSure_Admin_Extensions $extensions Extensions registry instance.
		 */
		do_action( 'tracksure_register_admin_extensions', $extensions_registry );

		// Get registered extensions with enriched destination/integration metadata from Managers.
		$extensions_data = $this->get_extensions_with_manager_data( $extensions_registry );

		wp_localize_script( 'tracksure-admin', 'trackSureExtensions', $extensions_data );

		/**
		 * Allow Free/Pro to enqueue additional scripts/styles.
		 *
		 * @since 1.0.0
		 *
		 * @param string $hook Current admin page hook.
		 */
		do_action( 'tracksure_admin_enqueue_scripts', $hook );
	}

	/**
	 * Check if e-commerce is active.
	 *
	 * Detects major WordPress e-commerce platforms.
	 * Used to show/hide revenue metrics and e-commerce features in admin UI.
	 *
	 * Supported Platforms:
	 * - WooCommerce
	 * - Easy Digital Downloads (EDD)
	 * - SureCart
	 * - FluentCart
	 * - CartFlows (WooCommerce funnel builder)
	 *
	 * @since 1.0.0
	 * @return bool True if any e-commerce platform is active.
	 */
	private function is_ecommerce_active() {
		return class_exists( 'WooCommerce' ) ||
			class_exists( 'Easy_Digital_Downloads' ) ||
			class_exists( 'SureCart' ) ||
			class_exists( 'FluentCart\\App\\App' ) ||
			class_exists( 'Cartflows_Loader' );
	}

	/**
	 * Get extensions with enriched Manager data.
	 *
	 * CLEAN ARCHITECTURE (v2.0 - NO DUPLICATION):
	 * - Extensions registry: Settings groups, custom pages, global configs ONLY
	 * - Destinations/Integrations: REMOVED from extension registry (was duplicate)
	 * - Single source of truth: Destinations Manager + Integrations Manager
	 * - Auto-assign to extensions by matching enabled_key prefix
	 *
	 * @param TrackSure_Admin_Extensions $registry Extensions registry.
	 * @return array Extensions data for React.
	 */
	private function get_extensions_with_manager_data( $registry ) {
		$core                 = TrackSure_Core::get_instance();
		$destinations_manager = $core->get_service( 'destinations_manager' );
		$integrations_manager = $core->get_service( 'integrations_manager' );

		// Get base extensions (with settings groups).
		$extensions = $registry->get_extensions();

		// Get ALL destinations from Manager (single source of truth).
		$all_destinations = $destinations_manager ? $destinations_manager->get_registered_destinations() : array();

		// Get ALL integrations from Manager (single source of truth).
		$all_integrations = $integrations_manager ? $integrations_manager->get_registered_integrations() : array();

		// Enrich each extension with Manager data.
		foreach ( $extensions as &$extension ) {
			// Determine extension's enabled_key prefix.
			$extension_prefix = '';
			if ( $extension['id'] === 'tracksure-free' ) {
				$extension_prefix = 'tracksure_free_';
			} elseif ( $extension['id'] === 'tracksure-pro' ) {
				// Pro destinations use 'tracksure_google_ads_enabled', 'tracksure_tiktok_enabled', etc.
				// Pro integrations use 'tracksure_edd_enabled', etc.
				$extension_prefix = 'tracksure_';
			}

			// Auto-assign destinations to this extension by matching enabled_key prefix.
			$extension['destinations'] = array();
			if ( $extension_prefix && $destinations_manager ) {
				foreach ( $all_destinations as $dest ) {
					// Match destinations to extension by enabled_key prefix.
					// Skip Free destinations (tracksure_free_*) when processing Pro.
					if ( strpos( $dest['enabled_key'], $extension_prefix ) === 0 ) {
						if ( $extension['id'] === 'tracksure-pro' && strpos( $dest['enabled_key'], 'tracksure_free_' ) === 0 ) {
							continue; // Skip Free destinations in Pro.
						}
						$extension['destinations'][] = $this->format_destination_for_react( $dest );
					}
				}
			}

			// Auto-assign integrations to this extension by matching enabled_key prefix.
			$extension['integrations'] = array();
			if ( $extension_prefix && $integrations_manager ) {
				foreach ( $all_integrations as $int ) {
					// Match integrations to extension by enabled_key prefix.
					// Skip Free integrations when processing Pro.
					// Special case: woo_integration_enabled and fluentcart_integration_enabled are Free
					$is_free_integration = strpos( $int['enabled_key'], 'tracksure_free_' ) === 0
						|| $int['enabled_key'] === 'woo_integration_enabled'
						|| $int['enabled_key'] === 'fluentcart_integration_enabled';

					if ( $extension['id'] === 'tracksure-free' ) {
						// Free: Only tracksure_free_* or old naming (woo_*, fluentcart_*)
						if ( $is_free_integration ) {
							$extension['integrations'][] = $this->format_integration_for_react( $int, $integrations_manager );
						}
					} elseif ( $extension['id'] === 'tracksure-pro' ) {
						// Pro: Only tracksure_* (but NOT tracksure_free_*)
						if ( strpos( $int['enabled_key'], 'tracksure_' ) === 0 && ! $is_free_integration ) {
							$extension['integrations'][] = $this->format_integration_for_react( $int, $integrations_manager );
						}
					}
				}
			}
		}

		return $extensions;
	}

	/**
	 * Format destination for React (from Manager data).
	 *
	 * Keys use camelCase to match TypeScript DestinationConfig interface.
	 * Note: custom_config remains snake_case as both PHP and React use it that way.
	 *
	 * @param array $dest Destination data from Manager.
	 * @return array Formatted for React.
	 */
	private function format_destination_for_react( $dest ) {
		return array(
			'id'            => $dest['id'],
			'name'          => $dest['name'],
			'description'   => isset( $dest['description'] ) ? $dest['description'] : '',
			'icon'          => isset( $dest['icon'] ) ? $dest['icon'] : 'Target',
			'order'         => isset( $dest['order'] ) ? $dest['order'] : 999,
			'enabledKey'    => $dest['enabled_key'],
			'custom_config' => isset( $dest['custom_config'] ) ? $dest['custom_config'] : null,
			'fields'        => $this->enrich_fields( isset( $dest['settings_fields'] ) ? $dest['settings_fields'] : array(), TrackSure_Settings_Schema::get_all_settings() ),
		);
	}

	/**
	 * Format integration for React (from Manager data).
	 *
	 * Keys use camelCase to match TypeScript IntegrationConfig interface.
	 * - auto_detect path is resolved to boolean 'detected' (React doesn't need file paths)
	 * - plugin_name removed (not used by React)
	 *
	 * @param array                               $int                  Integration data from Manager.
	 * @param TrackSure_Integrations_Manager|null $integrations_manager Manager instance for plugin detection.
	 * @return array Formatted for React.
	 */
	private function format_integration_for_react( $int, $integrations_manager = null ) {
		// Resolve auto_detect path to boolean — React only needs the result, not the file path.
		$detected = false;
		if ( $integrations_manager && ! empty( $int['auto_detect'] ) ) {
			$detected = $integrations_manager->is_plugin_active( $int['auto_detect'] );
		}

		return array(
			'id'          => $int['id'],
			'name'        => $int['name'],
			'description' => isset( $int['description'] ) ? $int['description'] : '',
			'icon'        => isset( $int['icon'] ) ? $int['icon'] : 'Puzzle',
			'order'       => isset( $int['order'] ) ? $int['order'] : 999,
			'enabledKey'  => $int['enabled_key'],
			'detected'    => $detected,
			'events'      => isset( $int['tracked_events'] ) ? $int['tracked_events'] : array(),
			'fields'      => $this->enrich_fields( isset( $int['settings_fields'] ) ? $int['settings_fields'] : array(), TrackSure_Settings_Schema::get_all_settings() ),
		);
	}

	/**
	 * Enrich field keys with schema metadata.
	 *
	 * @param array $field_keys Field key strings.
	 * @param array $schema Settings schema.
	 * @return array Enriched field objects.
	 */
	private function enrich_fields( $field_keys, $schema ) {
		$enriched = array();

		foreach ( $field_keys as $key ) {
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}

			$field = $schema[ $key ];
			$type  = $this->map_field_type( $field['type'], $field );

			$enriched_field = array(
				'id'           => $key,
				'type'         => $type,
				'label'        => $field['label'] ?? '',
				'description'  => $field['description'] ?? '',
				'defaultValue' => $field['default'] ?? null,
			);

			if ( $type === 'select' && isset( $field['options'] ) ) {
				$enriched_field['options'] = $this->format_options( $field['options'] );
			}

			if ( $type === 'number' ) {
				if ( isset( $field['min'] ) ) {
					$enriched_field['min'] = $field['min'];
				}
				if ( isset( $field['max'] ) ) {
					$enriched_field['max'] = $field['max'];
				}
			}

			if ( isset( $field['placeholder'] ) ) {
				$enriched_field['placeholder'] = $field['placeholder'];
			}

			if ( isset( $field['sensitive'] ) && $field['sensitive'] ) {
				$enriched_field['sensitive'] = true;
			}

			$enriched[] = $enriched_field;
		}

		return $enriched;
	}

	/**
	 * Map field type to React input type.
	 *
	 * @param string $type Schema type.
	 * @param array  $field Field config.
	 * @return string React input type.
	 */
	private function map_field_type( $type, $field = array() ) {
		if ( isset( $field['options'] ) ) {
			return 'select';
		}
		if ( isset( $field['sensitive'] ) && $field['sensitive'] ) {
			return 'password';
		}

		$map = array(
			'boolean' => 'toggle',
			'integer' => 'number',
			'string'  => 'text',
			'array'   => 'textarea', // For now, arrays render as textareas for JSON input
		);

		return $map[ $type ] ?? 'text';
	}

	/**
	 * Format options for React.
	 *
	 * @param array $options Options (key => label).
	 * @return array Formatted options.
	 */
	private function format_options( $options ) {
		$formatted = array();
		foreach ( $options as $value => $label ) {
			$formatted[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $formatted;
	}
}
