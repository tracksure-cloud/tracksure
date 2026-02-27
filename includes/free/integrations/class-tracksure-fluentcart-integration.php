<?php

/**
 * FluentCart integration module.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.Security.ValidatedSanitizedInput -- Debug logging and FluentCart data extraction requires direct DB access

/**
 *
 * FluentCart Integration (Event Builder Pattern)
 *
 * Hooks into FluentCart actions to track e-commerce events.
 * Uses TrackSure_FluentCart_Adapter for data extraction.
 * Uses TrackSure_Event_Builder for event creation.
 *
 * ARCHITECTURE NOTE:
 * - This is the HOOK LAYER - WordPress action/filter management
 * - NO direct data extraction - delegates to FluentCart Adapter
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
 * - class-tracksure-fluentcart-adapter.php (data layer)
 * - TrackSure_Event_Builder (event creation)
 * - TrackSure_Event_Recorder (event storage)
 *
 * SUPPORTED EVENTS:
 * - view_item (product page view)
 * - add_to_cart
 * - view_cart
 * - begin_checkout
 * - add_payment_info
 * - purchase
 * - refund
 *
 * @package TrackSure\Free\Integrations
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FluentCart Integration Class
 */
class TrackSure_FluentCart_Integration {





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
	 * FluentCart Adapter instance.
	 *
	 * @var TrackSure_FluentCart_Adapter
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
	 * Guards to prevent duplicate checkout events per session.
	 *
	 * @var array
	 */
	private $checkout_flags = array();

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
		require_once plugin_dir_path( __FILE__ ) . '../adapters/class-tracksure-fluentcart-adapter.php';
		$this->adapter    = new TrackSure_FluentCart_Adapter();
		$this->normalizer = TrackSure_Data_Normalizer::get_instance();

		// Check if FluentCart is active.
		if ( ! $this->adapter->is_active() ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * FLUENTCART HOOKS (verified from source code):
	 * - fluent_cart/cart/item_added - When item added to cart (Cart.php:226)
	 * - fluent_cart/before_checkout_page_start - Checkout page loads (CheckoutRenderer.php:155)
	 * - fluent_cart/before_checkout_form - Before checkout form renders (CheckoutRenderer.php:211)
	 * - fluent_cart/before_payment_methods - Before payment methods shown (CheckoutRenderer.php:233)
	 * - fluent_cart/order_paid_done - Order payment completed (actions.php:132)
	 * - fluent_cart/order_refunded - Order refunded (OrderRefund.php:141)
	 * - fluent_cart/order_fully_refunded - Order fully refunded (OrderRefund.php:144)
	 */
	private function init_hooks() {
		// Product view - Track on template_redirect when viewing fluent-products post type.
		add_action( 'template_redirect', array( $this, 'track_view_item_on_product_page' ), 10 );

		// Add to cart - FluentCart fires this after item added.
		add_action( 'fluent_cart/cart/item_added', array( $this, 'track_add_to_cart' ), 10, 1 );

		// View cart - When checkout page starts loading.
		add_action( 'fluent_cart/before_checkout_page_start', array( $this, 'track_view_cart' ), 10, 1 );

		// Begin checkout - ACTUAL FluentCart hook.
		add_action( 'fluent_cart/before_checkout_form', array( $this, 'track_begin_checkout' ), 10, 1 );

		// Add payment info - Before payment methods displayed.
		add_action( 'fluent_cart/before_payment_methods', array( $this, 'track_add_payment_info' ), 10, 1 );

		// Purchase - ACTUAL FluentCart hook.
		add_action( 'fluent_cart/order_paid_done', array( $this, 'track_purchase' ), 10, 1 );
		// Fallback: fire purchase when order is created (covers offline/manual payments that never hit order_paid_done).
		add_action( 'fluent_cart/order_created', array( $this, 'track_purchase' ), 10, 1 );

		// Refund - ACTUAL FluentCart hooks.
		add_action( 'fluent_cart/order_refunded', array( $this, 'track_refund' ), 10, 1 );
		add_action( 'fluent_cart/order_fully_refunded', array( $this, 'track_refund' ), 10, 1 );
		add_action( 'fluent_cart/order_partially_refunded', array( $this, 'track_refund' ), 10, 1 );

		// Fallback checkout tracking (guards if FluentCart hooks don’t run).
		add_action( 'template_redirect', array( $this, 'maybe_track_checkout_events' ), 11 );

		// Output browser events.
		add_action( 'wp_footer', array( $this, 'output_browser_events' ), 999 );

		// Enqueue FluentCart AJAX event listener
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_fluentcart_ajax_listener' ), 20 );

		// REMOVED: AJAX tracking script - causes SDK not loaded errors
		// FluentCart add_to_cart tracking is handled server-side via hooks above

		// Login: Track successful login
		add_action( 'wp_login', array( $this, 'track_login' ), 10, 2 );
	}

	/**
	 * Track product view on product page via template_redirect.
	 * FluentCart doesn't have a dedicated product_view hook.
	 */
	public function track_view_item_on_product_page() {
		if ( ! $this->event_builder ) {
			return;
		}

		// Check if this is a FluentCart product page.
		if ( ! $this->is_fluentcart_product_page() ) {
			return;
		}

		// Get product from query.
		global $wp_query;
		$product_id = get_the_ID();

		if ( ! $product_id ) {
			return;
		}

		// FluentCart uses custom post type 'fluent-products' (plural).
		if ( get_post_type( $product_id ) !== 'fluent-products' ) {
			return;
		}

		$product_data = $this->adapter->extract_product_data( $product_id );
		if ( ! $product_data ) {
			return;
		}

		$event = $this->event_builder->build_event(
			'view_item',
			array(
				'item_id'   => $product_data['item_id'],   // Required at root per registry
				'item_name' => $product_data['item_name'], // Product name for Meta content_name
				'quantity'  => isset( $product_data['quantity'] ) ? $product_data['quantity'] : 1, // Quantity for Meta
				'currency'  => $product_data['currency'],
				'value'     => $product_data['price'],     // Value for Meta Pixel
				'price'     => $product_data['price'],     // Fallback for mappers that check price
				'items'     => array( $product_data ),
			),
			array(
				'event_source'   => 'server',
				'ecommerce_data' => $product_data,
			)
		);

		// CRITICAL: Pass page_context to root level for Event Recorder.
		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		// Record the event.
		$this->event_recorder->record( $event );
		$this->queue_browser_event( $event );  // CRITICAL: Queue for browser so Meta Pixel can track it
	}

	/**
	 * Check if current page is a FluentCart product page.
	 *
	 * FluentCart uses 'fluent-products' as custom post type.
	 * Default URL pattern: /item/product-name/
	 *
	 * @return bool
	 */
	private function is_fluentcart_product_page() {
		// FluentCart uses 'fluent-products' (plural) as post type.
		$result = is_singular( 'fluent-products' ) && defined( 'FLUENTCART_VERSION' );

		return $result;
	}

	/**
	 * Enqueue FluentCart AJAX event listener script.
	 * 
	 * This script listens to FluentCart's AJAX responses and fires TrackSure events.
	 */
	public function enqueue_fluentcart_ajax_listener() {
		// Only enqueue on pages where FluentCart might be present
		if ( ! $this->adapter->is_active() ) {
			return;
		}

		// Inline script to handle AJAX responses.
		$script  = '(function() {';
		$script .= '  console.log("[TrackSure FluentCart] AJAX listener initialized");';
		$script .= '  if (typeof jQuery !== "undefined") {';
		$script .= '    jQuery(document).ajaxComplete(function(event, xhr, settings) {';
		$script .= '      if (settings.url && settings.url.indexOf("fluent_cart") !== -1) {';
		$script .= '        console.log("[TrackSure FluentCart] FluentCart AJAX detected");';
		$script .= '        try {';
		$script .= '          var response = JSON.parse(xhr.responseText);';
		$script .= '          if (response && response.tracksure_events && Array.isArray(response.tracksure_events)) {';
		$script .= '            console.log("[TrackSure FluentCart] Found " + response.tracksure_events.length + " events in AJAX response");';
		$script .= '            response.tracksure_events.forEach(function(evt) {';
		$script .= '              if (window.TrackSure && typeof window.TrackSure.sendToPixels === "function") {';
		$script .= '                console.log("[TrackSure FluentCart] Sending AJAX event:", evt.event_name, evt);';
		$script .= '                window.TrackSure.sendToPixels(evt);';
		$script .= '              } else {';
		$script .= '                console.warn("[TrackSure FluentCart] TrackSure SDK not loaded, event skipped");';
		$script .= '              }';
		$script .= '            });';
		$script .= '          }';
		$script .= '        } catch (e) {}';
		$script .= '      }';
		$script .= '    });';
		$script .= '  }';
		$script .= '})();';

		wp_add_inline_script( 'ts-web', $script, 'after' );
	}

	/**
	 * Track product page view (legacy method - kept for compatibility).
	 *
	 * @param int|object $product Product ID or object.
	 */
	public function track_view_item( $product ) {
		if ( ! $this->event_builder ) {
			return;
		}

		$product_data = $this->adapter->extract_product_data( $product );
		if ( ! $product_data ) {
			return;
		}

		$event = $this->event_builder->build_event(
			'view_item',
			array(
				'value'    => $product_data['price'],
				'currency' => $product_data['currency'],
				'items'    => array( $product_data ),
			),
			array(
				'event_source'   => 'server',
				'ecommerce_data' => $product_data,
			)
		);

		// CRITICAL: Pass page_context to root level for Event Recorder.
		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		$this->event_recorder->record( $event );
		$this->queue_browser_event( $event );
	}

	/**
	 * Track add to cart.
	 *
	 * FluentCart hook signature: fluent_cart/cart/item_added
	 * Passes array: ['cart' => Cart object, 'item' => item array]
	 *
	 * @param array $data Hook data containing cart and item.
	 */
	public function track_add_to_cart( $data ) {
		if ( ! $this->event_builder || ! is_array( $data ) ) {
			return;
		}

		// FluentCart hook only passes 'cart' object, NOT 'item'
		// cart_data is stored as JSON string in cart->attributes['cart_data']
		$cart = isset( $data['cart'] ) ? $data['cart'] : null;

		if ( ! $cart ) {
			return;
		}

		// Get the last item added from cart_data
		// FluentCart Cart is Laravel Eloquent model - access attributes safely
		$cart_data_json = null;
		try {
			// Try direct property access first
			if ( isset( $cart->cart_data ) ) {
				$cart_data_json = $cart->cart_data;
			} elseif ( method_exists( $cart, 'getAttribute' ) ) { // Try getAttribute method (Laravel Eloquent).
				$cart_data_json = $cart->getAttribute( 'cart_data' );
			} elseif ( method_exists( $cart, 'getAttributes' ) ) { // Try getAttributes method for protected attributes array.
				$attrs = $cart->getAttributes();
				if ( isset( $attrs['cart_data'] ) ) {
					$cart_data_json = $attrs['cart_data'];
				}
			}
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TrackSure FluentCart] Error accessing cart_data: ' . $e->getMessage() );
			}
			return;
		}

		if ( ! $cart_data_json ) {
			return;
		}

		// cart_data is already an array (hook passes it decoded)
		// If it's a string, decode it. If it's already an array, use it directly.
		if ( is_string( $cart_data_json ) ) {
			$cart_items = json_decode( $cart_data_json, true );
		} else {
			$cart_items = $cart_data_json; // Already an array
		}

		if ( ! is_array( $cart_items ) || empty( $cart_items ) ) {
			return;
		}

		// Get the last item (most recently added)
		$item = end( $cart_items );
		if ( ! $item ) {
			return;
		}

		// FluentCart items have 'post_id' (WordPress post ID), not 'product_id'
		$product_id = isset( $item['post_id'] ) ? $item['post_id'] : ( isset( $item['id'] ) ? $item['id'] : 0 );
		if ( ! $product_id ) {
			return;
		}

		$product_data = $this->adapter->extract_product_data( $product_id );
		if ( ! $product_data ) {
			return;
		}

		// Update quantity from cart item (FluentCart items are arrays).
		if ( isset( $item['quantity'] ) ) {
			$product_data['quantity'] = (int) $item['quantity'];
		}

		$event = $this->event_builder->build_event(
			'add_to_cart',
			array(
				'item_id'  => $product_data['item_id'],  // Required at root per registry
				'quantity' => $product_data['quantity'], // Registry requires quantity at root
				'value'    => $product_data['price'] * $product_data['quantity'],
				'currency' => $product_data['currency'],
				'items'    => array( $product_data ),
			),
			array(
				'event_source'   => 'server',
				'ecommerce_data' => $product_data,
			)
		);

		// CRITICAL: Pass page_context to root level for Event Recorder.
		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		$this->event_recorder->record( $event );
		$this->queue_browser_event( $event );
	}

	/**
	 * Track cart page view.
	 * 
	 * FluentCart hook signature: fluent_cart/before_checkout_page_start
	 * Passes array: ['cart' => Cart object]
	 *
	 * @param array $data Hook data containing cart.
	 */
	public function track_view_cart( $data = array() ) {
		// Prevent duplicate view_cart for the same session.
		$session_id     = ( $this->session_manager && method_exists( $this->session_manager, 'get_session_id_from_browser' ) )
			? $this->session_manager->get_session_id_from_browser()
			: '';
		$view_cart_flag = $session_id ? 'tracksure_fc_view_cart_' . $session_id : '';
		if ( $view_cart_flag && get_transient( $view_cart_flag ) ) {
			return;
		}

		if ( ! $this->event_builder ) {
			return;
		}

		// Extract cart from hook data if available.
		$cart = isset( $data['cart'] ) ? $data['cart'] : null;

		$cart_data = $this->adapter->extract_cart_data( $cart );
		if ( ! $cart_data || empty( $cart_data['cart_items'] ) ) {
			return;
		}

		$event = $this->event_builder->build_event(
			'view_cart',
			array(
				'value'    => $cart_data['cart_value'],
				'currency' => $cart_data['currency'],
				'items'    => $cart_data['cart_items'],
				'coupon'   => isset( $cart_data['coupon_codes'] ) && ! empty( $cart_data['coupon_codes'] ) ? implode( ',', $cart_data['coupon_codes'] ) : '',
			),
			array(
				'event_source'   => 'server',
				'ecommerce_data' => $cart_data,
			)
		);

		// CRITICAL: Pass page_context to root level for Event Recorder.
		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		$this->event_recorder->record( $event );
		$this->queue_browser_event( $event );

		if ( $view_cart_flag ) {
			set_transient( $view_cart_flag, true, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Track begin checkout (via action hook).
	 * 
	 * FluentCart hook signature: fluent_cart/before_checkout_form
	 * Passes array: ['cart' => Cart object]
	 *
	 * @param array $data Hook data containing cart.
	 */
	public function track_begin_checkout( $data = array() ) {
		// Prevent duplicate begin_checkout for the same session.
		$session_id = ( $this->session_manager && method_exists( $this->session_manager, 'get_session_id_from_browser' ) )
			? $this->session_manager->get_session_id_from_browser()
			: '';
		$begin_flag = $session_id ? 'tracksure_fc_begin_checkout_' . $session_id : '';
		if ( $begin_flag && get_transient( $begin_flag ) ) {
			return;
		}

		if ( ! $this->event_builder ) {
			return;
		}

		// Extract cart from hook data if available.
		$cart = isset( $data['cart'] ) ? $data['cart'] : null;

		$cart_data = $this->adapter->extract_cart_data( $cart );
		if ( ! $cart_data || empty( $cart_data['cart_items'] ) ) {
			return;
		}

		$event = $this->event_builder->build_event(
			'begin_checkout',
			array(
				'value'    => $cart_data['cart_value'],
				'currency' => $cart_data['currency'],
				'items'    => $cart_data['cart_items'],
				'tax'      => isset( $cart_data['tax'] ) ? $cart_data['tax'] : 0,
				'shipping' => isset( $cart_data['shipping'] ) ? $cart_data['shipping'] : 0,
				'coupon'   => isset( $cart_data['coupon_codes'] ) && ! empty( $cart_data['coupon_codes'] ) ? implode( ',', $cart_data['coupon_codes'] ) : '',
			),
			array(
				'event_source'   => 'server',
				'ecommerce_data' => $cart_data,
			)
		);

		// CRITICAL: Pass page_context to root level for Event Recorder.
		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		$this->event_recorder->record( $event );
		$this->queue_browser_event( $event );

		if ( $begin_flag ) {
			set_transient( $begin_flag, true, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Track add payment info.
	 * 
	 * FluentCart hook signature: fluent_cart/after_payment_methods
	 * Passes array: ['cart' => Cart object]
	 *
	 * @param array $data Hook data containing cart.
	 */
	public function track_add_payment_info( $data = array() ) {
		if ( ! $this->event_builder ) {
			return;
		}

		// Extract cart from hook data if available.
		$cart = isset( $data['cart'] ) ? $data['cart'] : null;

		$cart_data = $this->adapter->extract_cart_data( $cart );
		if ( ! $cart_data || empty( $cart_data['cart_items'] ) ) {
			return;
		}

		// Try to get payment method from cart.
		$payment_method = isset( $cart->payment_method ) ? $cart->payment_method : 'unknown';


		$event = $this->event_builder->build_event(
			'add_payment_info',
			array(
				'value'          => $cart_data['cart_value'],
				'currency'       => $cart_data['currency'],
				'payment_method' => $payment_method,
				'items'          => $cart_data['cart_items'],
			),
			array(
				'event_source'   => 'server',
				'ecommerce_data' => $cart_data,
			)
		);

		// CRITICAL: Pass page_context to root level for Event Recorder.
		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		$this->event_recorder->record( $event );
		$this->queue_browser_event( $event );
	}

	/**
	 * Track purchase (order completed).
	 * 
	 * FluentCart hook signature: fluent_cart/order_paid_done
	 * Passes array: ['order' => Order object, 'subscription' => Subscription object (optional)]
	 *
	 * @param array $data Hook data containing order.
	 */
	public function track_purchase( $data ) {
		if ( ! $this->event_builder || ! is_array( $data ) ) {
			return;
		}

		$order = isset( $data['order'] ) ? $data['order'] : null;
		if ( ! $order ) {
			return;
		}

		$order_data = $this->adapter->extract_order_data( $order );
		if ( ! $order_data ) {
			return;
		}

		// Check if already tracked (prevent duplicate tracking).
		$order_id = $order_data['transaction_id'];
		if ( get_transient( "tracksure_fluentcart_purchase_{$order_id}" ) ) {
			return;
		}

		$event = $this->event_builder->build_event(
			'purchase',
			array(
				'transaction_id' => $order_data['transaction_id'],
				'value'          => $order_data['value'],
				'currency'       => $order_data['currency'],
				'tax'            => $order_data['tax'],
				'shipping'       => $order_data['shipping'],
				'items'          => $order_data['items'],
			),
			array(
				'event_source'   => 'server',
				'ecommerce_data' => $order_data,
			)
		);

		// CRITICAL: Pass page_context to root level for Event Recorder.
		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		// Add custom properties.
		if ( ! empty( $order_data['coupon_codes'] ) ) {
			$event['event_params']['coupon'] = implode( ',', $order_data['coupon_codes'] );
		}
		if ( isset( $order_data['is_first_purchase'] ) ) {
			$event['event_params']['is_first_purchase'] = $order_data['is_first_purchase'];
		}

		$this->event_recorder->record( $event );
		$this->queue_browser_event( $event );

		// Mark as tracked (1 hour expiry).
		set_transient( "tracksure_fluentcart_purchase_{$order_id}", true, HOUR_IN_SECONDS );
	}

	/**
	 * Track refund.
	 * 
	 * FluentCart hook signatures:
	 * - fluent_cart/order_refunded (all refunds)
	 * - fluent_cart/order_fully_refunded (full refunds)
	 * - fluent_cart/order_partially_refunded (partial refunds)
	 * 
	 * Passes array: ['order' => Order object, 'refund' => Refund object]
	 *
	 * @param array $data Hook data containing order and refund.
	 */
	public function track_refund( $data ) {
		if ( ! $this->event_builder ) {
			return;
		}

		// Extract order from data array.
		$order = null;
		if ( is_array( $data ) ) {
			$order = isset( $data['order'] ) ? $data['order'] : null;
		} elseif ( is_object( $data ) ) {
			// Fallback: sometimes order object is passed directly.
			$order = $data;
		}

		if ( ! $order ) {
			return;
		}

		$order_data = $this->adapter->extract_order_data( $order );
		if ( ! $order_data ) {
			return;
		}

		$event = $this->event_builder->build_event(
			'refund',
			array(
				'transaction_id' => $order_data['transaction_id'],
				'value'          => $order_data['value'],
				'currency'       => $order_data['currency'],
				'items'          => $order_data['items'],
			),
			array(
				'event_source'   => 'server',
				'ecommerce_data' => $order_data,
			)
		);

		// CRITICAL: Pass page_context to root level for Event Recorder.
		if ( isset( $event['page_context']['page_url'] ) ) {
			$event['page_url'] = $event['page_context']['page_url'];
		}
		if ( isset( $event['page_context']['page_title'] ) ) {
			$event['page_title'] = $event['page_context']['page_title'];
		}

		$this->event_recorder->record( $event );
		$this->queue_browser_event( $event );
	}

	/**
	 * Check if current page is FluentCart checkout page.
	 *
	 * Uses multiple detection methods for compatibility:
	 * 1. FluentCart helper function (if exists)
	 * 2. FluentCart settings (checkout page ID)
	 * 3. Page slug pattern matching
	 * 4. URL pattern matching
	 *
	 * @return bool True if on checkout page.
	 */
	private function is_fluentcart_checkout_page() {
		// Method 1: Check FluentCart helper function if exists.
		if ( function_exists( 'fluentcart_is_checkout_page' ) ) {
			return fluentcart_is_checkout_page();
		}

		// Method 2: Check if FluentCart settings define checkout page.
		if ( function_exists( 'fluentcart_get_option' ) ) {
			$checkout_page_id = fluentcart_get_option( 'checkout_page_id' );
			if ( $checkout_page_id && is_page( $checkout_page_id ) ) {
				return true;
			}
		}

		// Method 3: Check page slug.
		global $post;
		if ( $post && isset( $post->post_name ) && strpos( $post->post_name, 'checkout' ) !== false ) {
			return true;
		}

		// Method 4: Check request URI.
		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/checkout' ) !== false ) {

			return true;
		}

		return false;
	}

	/**
	 * Fallback to ensure checkout events fire even if FluentCart hooks don’t run.
	 * Executes on template_redirect when on checkout page.
	 */
	public function maybe_track_checkout_events() {
		if ( ! $this->is_fluentcart_checkout_page() ) {
			return;
		}

		// Ensure we only fire once per page load (not per session - too aggressive)
		static $checkout_tracked = false;
		if ( $checkout_tracked ) {
			return;
		}

		// Get cart from FluentCart - FluentCart doesn't have a direct cart getter
		// Instead, we'll rely on FluentCart's own hooks to pass cart data
		// This fallback only needs to trigger the begin_checkout event manually
		$cart = null;

		// Try to get cart from global session or FluentCart's cart singleton if available
		// FluentCart stores cart in wp_fluent_carts table and retrieves via hash in cookie
		if ( class_exists( '\\FluentCart\\App\\Models\\Cart' ) ) {
			// FluentCart uses 'fc_cart_hash' cookie to identify cart
			$cart_hash = isset( $_COOKIE['fc_cart_hash'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['fc_cart_hash'] ) ) : null;

			if ( $cart_hash ) {
				try {
					// Query cart from database using hash
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table query, direct DB required
					$cart_row = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}fluent_carts WHERE hash = %s AND is_active = 1 ORDER BY id DESC LIMIT 1",
							$cart_hash
						)
					);

					if ( $cart_row ) {
						// Create Cart model instance from database row
						$cart = new \FluentCart\App\Models\Cart();
						foreach ( $cart_row as $key => $value ) {
							$cart->$key = $value;
						}
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[TrackSure FluentCart] Error getting cart from database: ' . $e->getMessage() );
					}
				}
			}
		}

		$cart_data = $this->adapter ? $this->adapter->extract_cart_data( $cart ) : false;
		if ( ! $cart_data || empty( $cart_data['items'] ) ) {
			return;
		}

		// Mark as processed for this page load
		$checkout_tracked = true;

		// Fire begin_checkout directly
		$this->track_begin_checkout( array( 'cart' => $cart ) );
	}

	/**
	 * Queue event for browser output.
	 *
	 * For AJAX requests, use wp_send_json_success to inject events into response.
	 * For regular requests, queue for wp_footer output.
	 *
	 * @param array $event Event data.
	 */
	private function queue_browser_event( $event ) {
		// Store in memory for regular page loads
		$this->browser_events[] = $event;

		// For AJAX requests, store in transient AND attach to AJAX response
		$is_async_request = ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		if ( $is_async_request ) {
			// Store in transient for next page load (fallback)
			$user_id       = get_current_user_id();
			$guest_id      = $user_id ? $user_id : ( isset( $_COOKIE['_ts_cid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['_ts_cid'] ) ) : wp_generate_uuid4() );
			$transient_key = 'tracksure_fc_browser_events_' . md5( $guest_id );
			$stored_events = get_transient( $transient_key );
			if ( ! is_array( $stored_events ) ) {
				$stored_events = array();
			}
			$stored_events[] = $event;
			set_transient( $transient_key, $stored_events, 300 ); // 5 min expiry

			// Use WordPress AJAX filter to inject event into response
			add_filter(
				'fluent_cart/ajax_response',
				function ( $response ) use ( $event ) {
					if ( ! isset( $response['tracksure_events'] ) ) {
						$response['tracksure_events'] = array();
					}
					$response['tracksure_events'][] = $event;
					return $response;
				},
				999
			);
		}
	}

	/**
	 * Output queued browser events.
	 */
	public function output_browser_events() {
		// Load events from transient (for AJAX-triggered events)
		$user_id       = get_current_user_id();
		$guest_id      = $user_id ? $user_id : ( isset( $_COOKIE['_ts_cid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['_ts_cid'] ) ) : '' );
		$transient_key = 'tracksure_fc_browser_events_' . md5( $guest_id );
		$stored_events = get_transient( $transient_key );
		if ( is_array( $stored_events ) && ! empty( $stored_events ) ) {
			// Check if we're on checkout page - if so, filter out add_to_cart events (they're superseded by begin_checkout)
			$is_checkout_page = $this->is_fluentcart_checkout_page();

			// Deduplicate by event_id to avoid double output when same event exists in memory.
			$existing_ids = array();
			foreach ( $this->browser_events as $queued ) {
				if ( isset( $queued['event_id'] ) ) {
					$existing_ids[ $queued['event_id'] ] = true;
				}
			}

			foreach ( $stored_events as $stored_event ) {
				$stored_id = isset( $stored_event['event_id'] ) ? $stored_event['event_id'] : null;

				// Skip duplicate events
				if ( $stored_id && isset( $existing_ids[ $stored_id ] ) ) {
					continue;
				}

				// CRITICAL: On checkout page, don't show old add_to_cart events from cart drawer
				// They're superseded by begin_checkout which fires on this page
				if ( $is_checkout_page && isset( $stored_event['event_name'] ) && $stored_event['event_name'] === 'add_to_cart' ) {
					continue;
				}

				$this->browser_events[] = $stored_event;
			}
			delete_transient( $transient_key ); // Clear after retrieval
		}


		if ( empty( $this->browser_events ) ) {
			return;
		}

		// Build inline script for browser events with retry mechanism (like WooCommerce).
		$events_script  = "(function() {\n";
		$events_script .= "  console.log('[TrackSure FluentCart] Preparing to send " . count( $this->browser_events ) . " browser event(s)');\n";
		$events_script .= "  \n";
		$events_script .= "  function sendFluentCartEvents() {\n";
		$events_script .= "    if (!window.TrackSure || typeof window.TrackSure.sendToPixels !== 'function') {\n";
		$events_script .= "      console.log('[TrackSure FluentCart] Event Bridge not ready yet, retrying in 100ms...');\n";
		$events_script .= "      setTimeout(sendFluentCartEvents, 100);\n";
		$events_script .= "      return;\n";
		$events_script .= "    }\n";
		$events_script .= "    \n";
		$events_script .= "    console.log('[TrackSure FluentCart] ✅ Event Bridge ready! Sending events...');\n";

		foreach ( $this->browser_events as $event ) {
			$json           = wp_json_encode( $event, JSON_HEX_TAG | JSON_HEX_AMP );
			$event_name     = esc_js( $event['event_name'] ?? 'unknown' );
			$events_script .= "    \n";
			$events_script .= "    // TrackSure Event: {$event_name}\n";
			$events_script .= "    console.log('[TrackSure FluentCart] Sending {$event_name} event:', {$json});\n";
			$events_script .= "    window.TrackSure.sendToPixels({$json});\n";
			$events_script .= "    console.log('[TrackSure FluentCart] ✅ {$event_name} event sent successfully');\n";
		}

		$events_script .= "  }\n";
		$events_script .= "  \n";
		$events_script .= "  // Start sending events when DOM is ready\n";
		$events_script .= "  if (document.readyState === 'loading') {\n";
		$events_script .= "    document.addEventListener('DOMContentLoaded', sendFluentCartEvents);\n";
		$events_script .= "  } else {\n";
		$events_script .= "    sendFluentCartEvents();\n";
		$events_script .= "  }\n";
		$events_script .= '})();';

		// Output safely using WordPress inline script tag function
		wp_print_inline_script_tag(
			$events_script,
			array( 'id' => 'tracksure-fluentcart-events' )
		);
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
				'method' => 'fluentcart', // Login method (fluentcart, wordpress, social, etc.)
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
		
		// Clear the browser_events array to prevent duplicate output on subsequent calls
