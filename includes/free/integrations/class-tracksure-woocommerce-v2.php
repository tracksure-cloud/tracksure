<?php

/**
 *
 * WooCommerce Integration (V2 - Event Builder Pattern)
 *
 * Hooks into WooCommerce actions to track e-commerce events.
 * Uses TrackSure_WooCommerce_Adapter for data extraction.
 * Uses TrackSure_Event_Builder for event creation.
 *
 * ARCHITECTURE NOTE:
 * - This is the HOOK LAYER - WordPress action/filter management
 * - NO direct data extraction - delegates to WooCommerce Adapter
 * - Registers hooks at 'init' priority 5 (WordPress 6.7+ requirement)
 * - Clean, focused responsibility: Track events via hooks
 *
 * WHY SEPARATE FROM ADAPTER:
 * - Separation of Concerns: Hook management vs data extraction
 * - Adapter can be reused by webhooks, REST API, CLI
 * - Integration focused on WordPress lifecycle
 * - Easier to maintain and test independently
 *
 * DEPENDENCIES:
 * - class-tracksure-woocommerce-adapter.php (data layer)
 * - TrackSure_Event_Builder (event creation)
 * - TrackSure_Event_Recorder (event storage)
 *
 * @package TrackSure\Free\Integrations
 * @since 1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Integration Class
 */
class TrackSure_WooCommerce_V2 {





	/**
	 * Core instance.
	 *
	 * @var TrackSure_Core
	 */
	private $core;

	/**
	 * Event Builder instance.
	 *
	 * @var TrackSure_Event_Builder
	 */
	private $event_builder;

	/**
	 * Event Recorder instance.
	 *
	 * @var TrackSure_Event_Recorder
	 */
	private $event_recorder;

	/**
	 * Session Manager instance.
	 *
	 * @var TrackSure_Session_Manager
	 */
	private $session_manager;

	/**
	 * WooCommerce Adapter instance.
	 *
	 * @var TrackSure_WooCommerce_Adapter
	 */
	private $adapter;

	/**
	 * Data Normalizer instance.
	 *
	 * @var TrackSure_Data_Normalizer
	 */
	private $normalizer;

	/**
	 * Events queued for browser output.
	 *
	 * @var array
	 */
	private $browser_events = array();

	/**
	 * Track if view_item has already fired for current page load.
	 * Prevents duplicate events when using multiple hooks.
	 *
	 * @var bool
	 */
	private $view_item_tracked = false;

	/**
	 * Constructor.
	 *
	 * @param TrackSure_Core $core Core instance.
	 */
	public function __construct( $core ) {
		$this->core            = $core;
		$this->event_builder   = $core->get_service( 'event_builder' );
		$this->event_recorder  = $core->get_service( 'event_recorder' );
		$this->session_manager = $core->get_service( 'session_manager' );

		// Initialize adapter and normalizer.
		require_once plugin_dir_path( __FILE__ ) . '../adapters/class-tracksure-woocommerce-adapter.php';
		$this->adapter    = new TrackSure_WooCommerce_Adapter();
		$this->normalizer = TrackSure_Data_Normalizer::get_instance();

		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * DEDUPLICATION ARCHITECTURE:
	 * ============================
	 * Browser and server events use IDENTICAL event_id for deduplication.
	 * Event Builder generates: event_id = Hash(session_id + event_name + product_id/order_id)
	 * 
	 * Event Recorder (class-tracksure-event-recorder.php lines 230-260):
	 * - Checks if event_id exists in database
	 * - If EXISTS: Updates browser_fired or server_fired flags (no duplicate)
	 * - If NEW: Creates event record
	 * - Result: Single event in database with both flags set
	 * 
	 * Ad Platform Deduplication:
	 * - Meta CAPI: Uses event_id for Pixel + CAPI deduplication
	 * - Google Ads: Uses client_id + timestamp
	 * - All platforms: Same event_id = automatic deduplication
	 *
	 * TRACKING STRATEGY:
	 * ==================
	 * 1. PAGE VIEWS (view_item, view_cart, begin_checkout, purchase):
	 *    - wp_footer detects page type server-side
	 *    - Outputs JavaScript with data
	 *    - Universal, theme-independent, 100% reliable
	 * 
	 * 2. USER ACTIONS (add_to_cart):
	 *    - Browser: Tracks button clicks immediately (tracksure-web.js)
	 *    - Server: WooCommerce hooks capture product data
	 *    - AJAX: Fragments filter outputs data to browser
	 *    - All use same event_id = single event record
	 */
	private function init_hooks() {
		// ✅ CLIENT-SIDE UNIVERSAL PAGE EVENT DETECTOR
		// Tracks: view_item, view_cart, begin_checkout, purchase
		add_action( 'wp_footer', array( $this, 'output_woocommerce_tracker' ), 5 );

		// ✅ SERVER-SIDE DATA CAPTURE HOOKS
		// These hooks fire when actual WooCommerce actions happen

		// Add to Cart: Captures product data when item added to cart
		add_action( 'woocommerce_add_to_cart', array( $this, 'capture_add_to_cart_data' ), 10, 6 );

		// AJAX Add to Cart: Send event data back to browser via fragments
		// This ensures AJAX add-to-cart (shop pages, quick view) works properly
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_to_cart_fragments' ), 10, 1 );

		// Purchase: Server-side conversion tracking on thank you page
		add_action( 'woocommerce_thankyou', array( $this, 'capture_purchase_data' ), 10, 1 );

		// Login: Track successful login
		add_action( 'wp_login', array( $this, 'track_login' ), 10, 2 );
	}

	/**
	 * Universal WooCommerce Event Tracker (Client-Side).
	 *
	 * Single JavaScript output that detects ALL WooCommerce page types:
	 * - Product page → view_item
	 * - Cart page → view_cart
	 * - Checkout page → begin_checkout
	 * - Thank you page → purchase
	 * - Account page → account_page_view
	 *
	 * ARCHITECTURE:
	 * - Server-side: Detects page type, extracts data using adapter
	 * - Client-side: JavaScript waits for Event Bridge, sends event
	 * - Universal: Works on ALL themes (Divi, Elementor, Astra, etc.)
	 * - Reliable: No dependency on WordPress hook timing
	 */
	public function output_woocommerce_tracker() {
		// Detect page type and extract corresponding data
		$page_type  = null;
		$event_name = null;
		$event_data = null;

		// 1. CHECK: Product Page (view_item)
		if ( is_singular( 'product' ) ) {
			global $post;
			if ( $post && $post->ID ) {
				$product = wc_get_product( $post->ID );
				if ( $product && is_a( $product, 'WC_Product' ) ) {
					$page_type  = 'product';
					$event_name = 'view_item';
					$event_data = $this->adapter->extract_product_data( $product );
				}
			}
		} elseif ( is_cart() && ! is_checkout() ) { // 2. CHECK: Cart Page (view_cart).
			$cart_data = $this->adapter->extract_cart_data();
			if ( $cart_data ) {
				$page_type  = 'cart';
				$event_name = 'view_cart';
				// CRITICAL: Only coupon available on cart page - tax/shipping calculated on checkout
				$event_data = array(
					'value'    => $cart_data['cart_value'],
					'currency' => $cart_data['currency'],
					'items'    => $cart_data['cart_items'],
					'coupon'   => isset( $cart_data['coupon_codes'] ) && ! empty( $cart_data['coupon_codes'] ) ? implode( ',', $cart_data['coupon_codes'] ) : '',
				);
			}
		} elseif ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			// 3. CHECK: Checkout Page (begin_checkout) - but NOT thank you page.
			// Supports both Classic Checkout and WooCommerce Blocks Checkout.

			// WooCommerce Blocks Checkout detection
			$is_blocks_checkout = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' );

			// Deduplication check using transient (more reliable than cookie)
			$session_id  = $this->session_manager->get_session_id_from_browser();
			$tracked_key = 'tracksure_wc_checkout_' . md5( $session_id );

			// Skip if already tracked in this session (prevents duplicates on page refresh)
			if ( ! get_transient( $tracked_key ) ) {
				$cart_data = $this->adapter->extract_cart_data();
				if ( $cart_data && ! empty( $cart_data['cart_items'] ) ) {
					$page_type  = 'checkout';
					$event_name = 'begin_checkout';
					// CRITICAL: Include ALL parameters matching view_cart (tax, shipping, coupon)
					$event_data = array(
						'value'    => $cart_data['cart_value'],
						'currency' => $cart_data['currency'],
						'items'    => $cart_data['cart_items'],
						'tax'      => isset( $cart_data['tax'] ) ? $cart_data['tax'] : 0,
						'shipping' => isset( $cart_data['shipping'] ) ? $cart_data['shipping'] : 0,
						'coupon'   => isset( $cart_data['coupon_codes'] ) && ! empty( $cart_data['coupon_codes'] ) ? implode( ',', $cart_data['coupon_codes'] ) : '',
					);

					// Mark as tracked (5 minute expiry - shorter to allow re-testing)
					set_transient( $tracked_key, true, 300 );
				}
			}
		} elseif ( is_checkout() && is_wc_endpoint_url( 'order-received' ) ) {
			// 4. CHECK: Thank You Page (purchase).
			// NOTE: Uses separate meta key (_tracksure_purchase_browser_sent) from the server-side
			// capture_purchase_data() which uses _tracksure_purchase_tracked.
			// Both fire independently. Deduplication happens at the destination level via shared event_id.
			global $wp;
			$order_id = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;

			if ( $order_id && ! get_post_meta( $order_id, '_tracksure_purchase_browser_sent', true ) ) {
				$order = wc_get_order( $order_id );
				if ( $order && is_a( $order, 'WC_Order' ) ) {
					$ecommerce_data = $this->adapter->extract_order_data( $order );
					$user_data      = $this->adapter->extract_user_data( $order );

					if ( $ecommerce_data && $user_data ) {
						$page_type  = 'thankyou';
						$event_name = 'purchase';
						$event_data = array(
							'transaction_id' => $ecommerce_data['transaction_id'],
							'value'          => $ecommerce_data['value'],
							'currency'       => $ecommerce_data['currency'],
							'tax'            => $ecommerce_data['tax'],
							'shipping'       => $ecommerce_data['shipping'],
							'coupon'         => ! empty( $ecommerce_data['coupon_codes'] ) ? implode( ',', $ecommerce_data['coupon_codes'] ) : '',
							'items'          => $ecommerce_data['items'],
						);

						// Mark browser output as done (separate from server-side tracking).
						update_post_meta( $order_id, '_tracksure_purchase_browser_sent', time() );
					}
				}
			}
		} elseif ( is_account_page() && is_user_logged_in() ) {
			// 5. CHECK: My Account Page (account_page_view) - only for logged-in users.
			// Detect specific account section
			global $wp;
			$account_section = 'dashboard'; // Default

			if ( is_wc_endpoint_url( 'orders' ) ) {
				$account_section = 'orders';
			} elseif ( is_wc_endpoint_url( 'downloads' ) ) {
				$account_section = 'downloads';
			} elseif ( is_wc_endpoint_url( 'edit-address' ) ) {
				$account_section = 'addresses';
			} elseif ( is_wc_endpoint_url( 'edit-account' ) ) {
				$account_section = 'account-details';
			} elseif ( is_wc_endpoint_url( 'payment-methods' ) ) {
				$account_section = 'payment-methods';
			} elseif ( is_wc_endpoint_url( 'subscriptions' ) ) {
				$account_section = 'subscriptions';
			} elseif ( is_wc_endpoint_url( 'view-order' ) ) {
				$account_section = 'view-order';
			}

			$page_type  = 'account';
			$event_name = 'account_page_view';
			$event_data = array(
				'page_type'       => 'woocommerce_account',
				'account_section' => $account_section,
			);
		}

		// If no WooCommerce page detected, exit
		if ( ! $page_type || ! $event_name || ! $event_data ) {
			return;
		}

		// Build full event for recording
		$full_event = $this->event_builder->build_event( $event_name, $event_data, array( 'event_source' => 'server' ) );

		// Record to database for server-side tracking
		if ( isset( $full_event['page_context']['page_url'] ) ) {
			$full_event['page_url'] = $full_event['page_context']['page_url'];
		}
		if ( isset( $full_event['page_context']['page_title'] ) ) {
			$full_event['page_title'] = $full_event['page_context']['page_title'];
		}
		$this->event_recorder->record( $full_event );

		// Output client-side JavaScript
		$event_id      = isset( $full_event['event_id'] ) ? $full_event['event_id'] : wp_generate_uuid4();
		$browser_event = array(
			'event_name'   => $event_name,
			'event_id'     => $event_id,
			'event_params' => $event_data,
		);
		$event_json    = wp_json_encode( $browser_event, JSON_HEX_TAG | JSON_HEX_AMP );

		// Escape variables for safe JS output.
		$safe_page_type  = esc_js( $page_type );
		$safe_event_name = esc_js( $event_name );

		$script  = "(function() {\n";
		$script .= "  console.log('[TrackSure WooCommerce] {$safe_page_type} page detected - preparing {$safe_event_name} event');\n";
		$script .= "  \n";
		$script .= "  function sendWooCommerceEvent() {\n";
		$script .= "    if (!window.TrackSure || typeof window.TrackSure.sendToPixels !== 'function') {\n";
		$script .= "      console.log('[TrackSure WooCommerce] Event Bridge not ready yet, waiting...');\n";
		$script .= "      setTimeout(sendWooCommerceEvent, 100);\n";
		$script .= "      return;\n";
		$script .= "    }\n";
		$script .= "    \n";
		$script .= "    console.log('[TrackSure WooCommerce] ✅ Event Bridge ready! Sending {$safe_event_name} event');\n";
		$script .= "    var event = {$event_json};\n";
		$script .= "    console.log('[TrackSure WooCommerce] Event data:', event);\n";
		$script .= "    window.TrackSure.sendToPixels(event);\n";
		$script .= "    console.log('[TrackSure WooCommerce] ✅ {$safe_event_name} event sent to Event Bridge');\n";
		$script .= "  }\n";
		$script .= "  \n";
		$script .= "  if (document.readyState === 'loading') {\n";
		$script .= "    document.addEventListener('DOMContentLoaded', sendWooCommerceEvent);\n";
		$script .= "  } else {\n";
		$script .= "    sendWooCommerceEvent();\n";
		$script .= "  }\n";
		$script .= '})();';

		// Attach to tracksure-web using WordPress enqueue API (DRY principle)
		if ( wp_script_is( 'ts-web', 'enqueued' ) ) {
			wp_add_inline_script( 'ts-web', $script, 'after' );
		}
	}

	/**
	 * Capture add to cart data (server-side hook).
	 *
	 * Fires when WooCommerce adds item to cart (button clicks, AJAX, manual).
	 * Records server-side event AND stores data for browser fragments.
	 *
	 * DEDUPLICATION: Event Builder generates same event_id for browser + server.
	 * Event Recorder merges them into single database record.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Quantity added.
	 * @param int    $variation_id Variation ID (0 if not a variation).
	 * @param array  $variation Variation attributes.
	 * @param array  $cart_item_data Additional cart item data.
	 */
	public function capture_add_to_cart_data( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$product_data = $this->adapter->extract_product_data( $product );
		if ( ! $product_data ) {
			return;
		}

		$product_data['quantity'] = (int) $quantity;

		// Build server-side event (with deterministic event_id)
		$event = $this->event_builder->build_event(
			'add_to_cart',
			$product_data,
			array(
				'event_source' => 'server',
				'server_fired' => true,
			)
		);

		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		// Record to database (deduplication happens here)
		$this->event_recorder->record( $event );

		// Store last add-to-cart data for AJAX fragments
		// This allows browser to receive event data via woocommerce_add_to_cart_fragments
		WC()->session->set(
			'tracksure_last_add_to_cart',
			array(
				'event'        => $event,
				'product_data' => $product_data,
				'timestamp'    => time(),
			)
		);
	}

	/**
	 * Add TrackSure event data to WooCommerce AJAX fragments.
	 *
	 * When user adds product to cart via AJAX (shop pages, quick view),
	 * WooCommerce updates cart widget using fragments.
	 * We inject our event data so browser JavaScript can fire the pixel.
	 *
	 * FLOW:
	 * 1. User clicks "Add to Cart" (AJAX)
	 * 2. capture_add_to_cart_data() stores event in session
	 * 3. WooCommerce calls this filter
	 * 4. We inject event data in fragments response
	 * 5. Browser JavaScript detects data and fires pixel
	 *
	 * @param array $fragments AJAX fragments.
	 * @return array Modified fragments with TrackSure event data.
	 */
	public function add_to_cart_fragments( $fragments ) {
		// Get last add-to-cart event from session
		$last_add = WC()->session->get( 'tracksure_last_add_to_cart' );

		if ( ! $last_add || ! isset( $last_add['event'] ) ) {
			return $fragments;
		}

		// Check if event is recent (within last 10 seconds)
		if ( ( time() - $last_add['timestamp'] ) > 10 ) {
			// Stale event, clear it
			WC()->session->set( 'tracksure_last_add_to_cart', null );
			return $fragments;
		}

		$event        = $last_add['event'];
		$product_data = $last_add['product_data'];

		// Create browser event object
		$browser_event = array(
			'event_name'   => 'add_to_cart',
			'event_id'     => $event['event_id'], // SAME event_id as server = deduplication!
			'event_params' => $product_data,
			'_source'      => 'woocommerce_ajax',
		);

		// Inject into fragments as hidden div that JavaScript will detect
		$fragments['div.tracksure-ajax-event'] = '<div class="tracksure-ajax-event" style="display:none;" data-event="' . esc_attr( wp_json_encode( $browser_event ) ) . '"></div>';

		// Clear session data (one-time use)
		WC()->session->set( 'tracksure_last_add_to_cart', null );

		return $fragments;
	}

	/**
	 * Capture purchase data (for server-side conversion tracking).
	 *
	 * This fires on thank you page.
	 * Complements the client-side purchase event from output_woocommerce_tracker().
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function capture_purchase_data( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		// Prevent duplicate — use SAME meta key as output_woocommerce_tracker()
		// so only ONE purchase event fires per order across both hooks.
		if ( get_post_meta( $order_id, '_tracksure_purchase_tracked', true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Mark as tracked (same key as output_woocommerce_tracker)
		update_post_meta( $order_id, '_tracksure_purchase_tracked', time() );

		// Extract order and user data
		$ecommerce_data = $this->adapter->extract_order_data( $order );
		$user_data      = $this->adapter->extract_user_data( $order );

		if ( ! $ecommerce_data || ! $user_data ) {
			return;
		}

		// Build event
		$event = $this->event_builder->build_event(
			'purchase',
			array(
				'transaction_id' => $ecommerce_data['transaction_id'],
				'value'          => $ecommerce_data['value'],
				'currency'       => $ecommerce_data['currency'],
				'tax'            => $ecommerce_data['tax'],
				'shipping'       => $ecommerce_data['shipping'],
				'coupon'         => ! empty( $ecommerce_data['coupon_codes'] ) ? implode( ',', $ecommerce_data['coupon_codes'] ) : '',
				'affiliation'    => get_bloginfo( 'name' ),
				'items'          => $ecommerce_data['items'],
			),
			array(
				'event_source'   => 'server',
				'user_data'      => $user_data,
				'ecommerce_data' => $ecommerce_data,
			)
		);

		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		$this->event_recorder->record( $event );
	}

	/**
	 * Track successful login.
	 *
	 * Fires on wp_login action.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user User object.
	 */
	public function track_login( $user_login, $user ) {
		// Build login event.
		$event = $this->event_builder->build_event(
			'login',
			array(
				'method' => 'woocommerce', // Login method (woocommerce, wordpress, social, etc.)
			),
			array(
				'event_source' => 'server',
			)
		);

		if ( $event ) {
			$this->event_recorder->record( $event );
		}
	}
}
