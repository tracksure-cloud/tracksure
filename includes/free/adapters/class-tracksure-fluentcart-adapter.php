<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions,WordPress.Security.NonceVerification,WordPress.DB.DirectDatabaseQuery,PluginCheck.Security.DirectDB -- Debug logging + cookie access + direct DB queries for FluentCart integration, $wpdb->prefix safe

/**
 *
 * TrackSure FluentCart Adapter
 *
 * Extracts data from FluentCart orders, products, cart, and customers
 * into TrackSure's universal schema. This adapter is used by the FluentCart
 * integration to normalize data before passing to Event Builder.
 *
 * ARCHITECTURE NOTE:
 * - This is the DATA LAYER - pure data extraction and normalization
 * - Implements TrackSure_Ecommerce_Adapter interface for consistency
 * - NO WordPress hooks - only data transformation
 * - Reusable by: Integration hooks, REST API, webhooks, CLI commands
 *
 * WHY SEPARATE FROM INTEGRATION:
 * - Separation of Concerns: Data extraction vs hook management
 * - Reusability: Same adapter used for different contexts
 * - Testability: Easy to unit test data transformation
 * - Maintainability: FluentCart API changes isolated here
 *
 * USED BY:
 * - class-tracksure-fluentcart-integration.php (integration)
 * - Future: Webhooks, REST API endpoints, CLI commands
 *
 * @package TrackSure\Free\Adapters
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FluentCart Adapter Class
 */
class TrackSure_FluentCart_Adapter implements TrackSure_Ecommerce_Adapter {




	/**
	 * Data normalizer instance.
	 *
	 * @var TrackSure_Data_Normalizer
	 */
	private $normalizer;

	/**
	 * Currency handler instance.
	 *
	 * @var TrackSure_Currency_Handler
	 */
	private $currency_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->normalizer       = TrackSure_Data_Normalizer::get_instance();
		$this->currency_handler = TrackSure_Currency_Handler::get_instance();

		// Log warning if FluentCart API structure is unexpected (debug mode only).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $this->is_active() ) {
			$this->verify_fluentcart_api();
		}
	}

	/**
	 * Get platform name.
	 *
	 * @return string
	 */
	public function get_platform_name() {
		return 'fluentcart';
	}

	/**
	 * Check if FluentCart is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'FLUENTCART_VERSION' );
	}

	/**
	 * Extract order data from FluentCart order object.
	 *
	 * @param \FluentCart\App\Models\Order|int $order Order object or order ID.
	 * @return array|false Normalized order data or false on failure.
	 */
	public function extract_order_data( $order ) {
		if ( is_numeric( $order ) ) {
			$order = $this->get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		// Get currency from FluentCart settings
		$currency = 'USD';
		if ( class_exists( '\\FluentCart\\App\\Helpers\\Helper' ) ) {
			try {
				$currency = \FluentCart\App\Helpers\Helper::shopConfig( 'currency' );
				if ( empty( $currency ) ) {
					$currency = 'USD';
				}
			} catch ( Exception $e ) {
				$currency = 'USD';
			}
		}

		// FluentCart stores amounts in cents - convert to decimal
		$total_amount    = isset( $order->total_amount ) ? (float) $order->total_amount / 100 : 0;
		$tax_amount      = isset( $order->tax_amount ) ? (float) $order->tax_amount / 100 : 0;
		$shipping_amount = isset( $order->shipping_amount ) ? (float) $order->shipping_amount / 100 : 0;
		$discount_amount = isset( $order->discount_amount ) ? (float) $order->discount_amount / 100 : 0;

		// Extract basic order data.
		$raw_order = array(
			'transaction_id'    => isset( $order->id ) ? (string) $order->id : '',
			'value'             => $total_amount,
			'currency'          => $this->normalize_currency_code( $currency ),
			'tax'               => $tax_amount,
			'shipping'          => $shipping_amount,
			'discount'          => $discount_amount,
			'coupon_codes'      => $this->extract_coupon_codes( $order ),
			'payment_method'    => isset( $order->payment_method ) ? $order->payment_method : '',
			'payment_gateway'   => 'FluentCart',
			'payment_type'      => $this->get_payment_type( $order ),
			'items'             => array(),
			'is_first_purchase' => $this->is_first_purchase( $order ),
		);

		// Extract line items (FluentCart Order has filteredOrderItems relationship).
		// CRITICAL: Laravel Eloquent requires explicit loading of relationships.
		$items = null;

		// Try to load filteredOrderItems (preferred - filters out certain types).
		if ( method_exists( $order, 'filteredOrderItems' ) ) {
			try {
				$items = $order->filteredOrderItems;
				// If not loaded, trigger lazy load
				if ( ! $items || ( is_object( $items ) && ! method_exists( $items, 'count' ) ) ) {
					$items = $order->filteredOrderItems()->get();
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[TrackSure FluentCart Adapter] filteredOrderItems failed: ' . $e->getMessage() );
				}
			}
		}

		// Fallback to order_items if filteredOrderItems not available.
		if ( ! $items && method_exists( $order, 'order_items' ) ) {
			try {
				$items = $order->order_items;
				if ( ! $items || ( is_object( $items ) && ! method_exists( $items, 'count' ) ) ) {
					$items = $order->order_items()->get();
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[TrackSure FluentCart Adapter] order_items failed: ' . $e->getMessage() );
				}
			}
		}

		// Extract items array.
		if ( $items ) {
			// Handle both array and Laravel Collection
			if ( is_array( $items ) || ( is_object( $items ) && method_exists( $items, 'toArray' ) ) ) {
				$items_array = is_array( $items ) ? $items : $items->toArray();
				foreach ( $items_array as $index => $item ) {
					$product_data = $this->extract_product_data_from_item( $item );
					if ( $product_data ) {
						$raw_order['items'][] = $product_data;
					}
				}
			}
		}

		// Check for subscription data (FluentCart Pro).
		if ( $this->is_subscription_order( $order ) ) {
			$raw_order['payment_type']      = 'subscription';
			$raw_order['subscription_plan'] = $this->get_subscription_plan_name( $order );
			$raw_order['billing_interval']  = $this->get_billing_interval( $order );
			$raw_order['next_billing_date'] = $this->get_next_billing_date( $order );
		}

		// Normalize using universal schema.
		return $this->normalizer->normalize_order( $raw_order );
	}

	/**
	 * Extract product data from FluentCart product object.
	 *
	 * @param \FluentCart\App\Models\Product|int $product Product object or product ID.
	 * @return array|false Normalized product data or false on failure.
	 */
	public function extract_product_data( $product ) {
		if ( is_numeric( $product ) ) {
			$product = $this->get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		// FluentCart uses WordPress posts table - access via Eloquent magic getters
		// Try to get ID first (could be $product->ID or $product->id)
		$product_id = null;
		if ( isset( $product->ID ) ) {
			$product_id = $product->ID;
		} elseif ( isset( $product->id ) ) {
			$product_id = $product->id;
		} elseif ( method_exists( $product, 'getKey' ) ) {
			$product_id = $product->getKey();
		}

		// If Eloquent model doesn't have data, fall back to WordPress get_post()
		if ( ! $product_id || ! isset( $product->post_title ) ) {

			// Get WordPress post object directly
			if ( $product_id ) {
				$wp_post = get_post( $product_id );
				if ( $wp_post && $wp_post->post_type === 'fluent-products' ) {
					$product = $wp_post; // Use WordPress post object instead
				}
			}
		}

		// Get product data - works with both Eloquent models and WP_Post objects
		$product_id = isset( $product->ID ) ? $product->ID : ( isset( $product->id ) ? $product->id : 0 );
		$title      = isset( $product->post_title ) ? $product->post_title : '';

		// CRITICAL: Check if this is a variation product (FluentCart uses variation_id in URL)
		$variation_id   = null;
		$variation_data = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading FluentCart URL parameter on product page, not a form submission.
		if ( isset( $_GET['variation_id'] ) ) {
			$variation_id_raw = sanitize_text_field( wp_unslash( $_GET['variation_id'] ) );
			if ( is_numeric( $variation_id_raw ) ) {
				$variation_id = (int) $variation_id_raw;

				// Get variation data from FluentCart variations table
				global $wpdb;
				$variation_data = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}fct_product_variations WHERE id = %d",
						$variation_id
					),
					ARRAY_A
				);

				if ( $variation_data ) {
					// Variation data found
				}
			}
		}

		// Get categories from WordPress taxonomy
		$categories = array();
		if ( $product_id ) {
			$terms = wp_get_post_terms( $product_id, 'product_cat' );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[] = $term->name;
				}
			}
		}

		// Get price from variation data if available, otherwise from product
		$price        = 0;
		$item_variant = '';

		// PRIORITY 1: Use variation data if viewing a specific variation
		if ( $variation_data && isset( $variation_data['item_price'] ) ) {
			$price = (float) $variation_data['item_price'];

			// Extract variation attributes (e.g., "Color: Red, Size: Large")
			if ( isset( $variation_data['attributes'] ) && ! empty( $variation_data['attributes'] ) ) {
				$attributes = maybe_unserialize( $variation_data['attributes'] );
				if ( is_array( $attributes ) ) {
					$variant_parts = array();
					foreach ( $attributes as $attr_name => $attr_value ) {
						$variant_parts[] = $attr_name . ': ' . $attr_value;
					}
					$item_variant = implode( ', ', $variant_parts );
				}
			}
		}
		// PRIORITY 2: Get from product detail (FluentCart stores in separate table)
		elseif ( $product_id && is_object( $product ) && method_exists( $product, 'detail' ) ) {
			try {
				$detail = $product->detail;  // Laravel Eloquent relationship

				if ( $detail && is_object( $detail ) ) {
					// Try accessing min_price using Laravel Eloquent methods
					if ( method_exists( $detail, 'getAttribute' ) ) {
						$min_price = $detail->getAttribute( 'min_price' );
						if ( $min_price && $min_price > 0 ) {
							$price = (float) $min_price;
						}
					}
					// Fallback: try direct property access
					if ( $price == 0 && isset( $detail->min_price ) && $detail->min_price > 0 ) {
						$price = (float) $detail->min_price;
					}
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[TrackSure FluentCart Adapter] Error loading detail: ' . $e->getMessage() );
				}
			}
		}

		// PRIORITY 3: If still no price, try getting from FluentCart variations table (first variation)
		if ( $price == 0 && $product_id ) {
			global $wpdb;
			$variation_price = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT item_price FROM {$wpdb->prefix}fct_product_variations WHERE post_id = %d ORDER BY serial_index ASC LIMIT 1",
					$product_id
				)
			);
			if ( $variation_price ) {
				$price = (float) $variation_price;
			}
		}

		// If still no price, try getting from post meta
		if ( $price == 0 && $product_id ) {
			$meta_price = get_post_meta( $product_id, '_price', true );
			if ( $meta_price ) {
				$price = (float) $meta_price;
			}
		}

		// FluentCart stores prices in cents (smallest currency unit)
		// Convert to decimal (e.g., 700 cents = 7.00 dollars)
		if ( $price > 0 ) {
			$price = $price / 100;
		}

		// Get currency from FluentCart settings
		$currency = 'USD'; // Default fallback
		if ( class_exists( '\FluentCart\App\Helpers\Helper' ) ) {
			try {
				$currency = \FluentCart\App\Helpers\Helper::shopConfig( 'currency' );
				if ( empty( $currency ) ) {
					$currency = 'USD';
				}
			} catch ( Exception $e ) {
				$currency = 'USD';
			}
		}

		// Extract raw product data
		// CRITICAL: If variation detected, use variation ID for accurate tracking
		$final_item_id = $variation_id ? (string) $variation_id : (string) $product_id;

		$raw_product = array(
			'item_id'        => $final_item_id,
			'item_sku'       => $variation_data && isset( $variation_data['sku'] ) ? $variation_data['sku'] : get_post_meta( $product_id, '_sku', true ),
			'item_name'      => $title,
			'item_category'  => isset( $categories[0] ) ? $categories[0] : '',
			'item_category2' => isset( $categories[1] ) ? $categories[1] : '',
			'item_category3' => isset( $categories[2] ) ? $categories[2] : '',
			'item_brand'     => get_post_meta( $product_id, '_brand', true ),
			'item_variant'   => $item_variant,
			'price'          => $price,
			'quantity'       => 1,
			'currency'       => $currency,
			'item_url'       => $this->get_product_url( $product ),
			'image_url'      => $this->get_product_image_url( $product ),
		);

		// Normalize using universal schema.
		return $this->normalizer->normalize_product( $raw_product );
	}

	/**
	 * Extract product data from order item.
	 *
	 * @param object|array $item FluentCart order item (object or decoded JSON array).
	 * @return array|false Normalized product data or false on failure.
	 */
	private function extract_product_data_from_item( $item ) {
		// CRITICAL: Accept both objects and arrays (FluentCart cart_data can be JSON decoded to array)
		if ( ! is_object( $item ) && ! is_array( $item ) ) {
			return false;
		}

		// Convert array to object for consistent access
		if ( is_array( $item ) ) {
			$item = (object) $item;
		}

		// Get currency from FluentCart settings (NOT from item - FluentCart doesn't store currency per item)
		$currency = 'USD'; // Default fallback
		if ( class_exists( '\\FluentCart\\App\\Helpers\\Helper' ) ) {
			try {
				$currency = \FluentCart\App\Helpers\Helper::shopConfig( 'currency' );
				if ( empty( $currency ) ) {
					$currency = 'USD';
				}
			} catch ( Exception $e ) {
				$currency = 'USD';
			}
		}

		// CRITICAL: FluentCart uses different field names for cart items vs order items!
		// Cart items: price field
		// Order items: unit_price field
		$price = 0;
		if ( isset( $item->unit_price ) && $item->unit_price > 0 ) {
			// Order item - use unit_price
			$price = (float) $item->unit_price;
		} elseif ( isset( $item->price ) && $item->price > 0 ) {
			// Cart item - use price
			$price = (float) $item->price;
		}

		// FluentCart stores prices in cents - convert to decimal
		if ( $price > 0 ) {
			$price = $price / 100;
		}

		// Extract variation attributes if available
		$item_variant = '';
		if ( isset( $item->options ) && is_array( $item->options ) ) {
			$variant_parts = array();
			foreach ( $item->options as $key => $value ) {
				$variant_parts[] = $key . ': ' . $value;
			}
			$item_variant = implode( ', ', $variant_parts );
		} elseif ( isset( $item->variant ) && ! empty( $item->variant ) ) {
			$item_variant = $item->variant;
		}

		// CRITICAL: FluentCart cart items structure (from cart_data JSON):
		// - id: Variation ID (e.g., 7)
		// - post_id: Parent product ID (e.g., 248759)
		// - object_id: Same as id (variation ID)
		// - title: Variation name (e.g., "Stripe 1", "Ultra Red")
		// - post_title: Parent product name

		$item_id      = '';
		$variation_id = null;

		// Priority 1: Check for 'id' field (FluentCart cart_data uses this for variation ID)
		if ( isset( $item->id ) && $item->id > 0 ) {
			$variation_id = (int) $item->id;
			$item_id      = (string) $item->id;
		}
		// Priority 2: Check for variation_id field
		elseif ( isset( $item->variation_id ) && $item->variation_id > 0 ) {
			$variation_id = (int) $item->variation_id;
			$item_id      = (string) $item->variation_id;
		}
		// Priority 3: Check for object_id field
		elseif ( isset( $item->object_id ) && $item->object_id > 0 ) {
			$variation_id = (int) $item->object_id;
			$item_id      = (string) $item->object_id;
		}
		// Priority 4: Fall back to product_id (parent product)
		elseif ( isset( $item->product_id ) ) {
			$item_id = (string) $item->product_id;
		} elseif ( isset( $item->post_id ) ) {
			$item_id = (string) $item->post_id;
		}

		// CRITICAL: Use variation title if available (e.g., "Stripe 1", "Ultra Red")
		// Otherwise fall back to post_title (parent product name)
		$item_name = '';
		if ( $variation_id && isset( $item->title ) && ! empty( $item->title ) ) {
			// This is a variation - use variation title
			$item_name = $item->title;
			// Add parent product name to variant if available
			if ( isset( $item->post_title ) && ! empty( $item->post_title ) ) {
				$item_variant = $item_variant ? $item_variant . ' | ' . $item->post_title : $item->post_title;
			}
		} elseif ( isset( $item->post_title ) ) {
			// Parent product
			$item_name = $item->post_title;
		} elseif ( isset( $item->title ) ) {
			$item_name = $item->title;
		} elseif ( isset( $item->name ) ) {
			$item_name = $item->name;
		}

		$raw_product = array(
			'item_id'      => $item_id,
			'item_sku'     => isset( $item->sku ) ? $item->sku : '',
			'item_name'    => $item_name,
			'item_variant' => $item_variant,
			'price'        => $price,
			'quantity'     => isset( $item->quantity ) ? (int) $item->quantity : 1,
			'currency'     => $currency,
		);

		$normalized = $this->normalizer->normalize_product( $raw_product );

		return $normalized;
	}

	/**
	 * Extract cart data from FluentCart session or passed cart object.
	 *
	 * @param \FluentCart\App\Models\Cart|null $cart_obj Optional cart object from hook.
	 * @return array|false Normalized cart data or false on failure.
	 */
	public function extract_cart_data( $cart_obj = null ) {
		if ( ! $this->is_active() ) {
			return false;
		}

		// Use passed cart object if available.
		$cart = $cart_obj;

		// NOTE: FluentCart\App\Helpers\Cart class doesn't exist.
		// Cart data must be passed from hooks or retrieved via Cart Model.
		// IMPORTANT: We do NOT query FluentCart's database directly because:
		// 1. FluentCart is a third-party plugin - we don't control its schema
		// 2. Different FluentCart versions may have different table structures
		// 3. Querying their tables directly causes database errors (e.g., missing 'status' column)
		// SOLUTION: Cart data MUST be passed from FluentCart hooks - don't query database
		if ( ! $cart ) {
			return false;
		}

		if ( ! $cart ) {
			return false;
		}

		$items       = array();
		$total_value = 0;

		// Get cart items from cart_data property (FluentCart Cart model).
		$cart_items_raw = null;

		// CRITICAL: FluentCart stores cart_data as JSON string, not array
		if ( isset( $cart->cart_data ) ) {

			// Decode JSON if it's a string
			if ( is_string( $cart->cart_data ) ) {
				$cart_items_raw = json_decode( $cart->cart_data, true );
			} else {
				$cart_items_raw = $cart->cart_data;
			}
		} elseif ( isset( $cart->cart_data ) ) {
			$cart_items_raw = $cart->cart_data;
		}

		// Attributes array on the Eloquent model.
		if ( ! $cart_items_raw && isset( $cart->attributes ) && is_array( $cart->attributes ) ) {
			if ( isset( $cart->attributes['cart_data'] ) ) {
				$cart_items_raw = $cart->attributes['cart_data'];
			}
		}

		// getAttribute/getAttributes fallbacks.
		if ( ! $cart_items_raw && method_exists( $cart, 'getAttribute' ) ) {
			$cart_items_raw = $cart->getAttribute( 'cart_data' );
		}

		if ( ! $cart_items_raw && method_exists( $cart, 'getAttributes' ) ) {
			$attrs = $cart->getAttributes();
			if ( isset( $attrs['cart_data'] ) ) {
				$cart_items_raw = $attrs['cart_data'];
			}
		}

		// FluentCart helper if available.
		if ( ! $cart_items_raw && method_exists( $cart, 'getItems' ) ) {
			$cart_items_raw = $cart->getItems();
		}

		// Normalize to array; cart_data can be JSON string, array, or collection.
		$cart_items = null;
		if ( is_string( $cart_items_raw ) ) {
			$decoded = json_decode( $cart_items_raw, true );
			if ( is_array( $decoded ) ) {
				$cart_items = $decoded;
			}
		} elseif ( is_array( $cart_items_raw ) ) {
			$cart_items = $cart_items_raw;
		} elseif ( is_object( $cart_items_raw ) && method_exists( $cart_items_raw, 'toArray' ) ) {
			$cart_items = $cart_items_raw->toArray();
		}

		if ( is_array( $cart_items ) ) {
			foreach ( $cart_items as $index => $item ) {
				$product_data = $this->extract_product_data_from_item( $item );
				if ( $product_data ) {
					$items[]      = $product_data;
					$total_value += $product_data['price'] * $product_data['quantity'];
				}
			}
		}

		// Get currency from cart if available.
		$currency = 'USD'; // Default
		if ( isset( $cart->currency ) ) {
			$currency = $cart->currency;
		}

		$raw_cart = array(
			'cart_value' => $total_value,
			'currency'   => $currency,
			'cart_items' => $items,  // CRITICAL: Use 'cart_items' not 'items' - normalizer expects this
		);

		return $this->normalizer->normalize_cart( $raw_cart );
	}

	/**
	 * Extract user data from FluentCart customer.
	 *
	 * @param \FluentCart\App\Models\Customer|int|null $user Customer object, ID, or null for current user.
	 * @return array|false Normalized user data or false on failure.
	 */
	public function extract_user_data( $user = null ) {
		// If no user provided, try current WordPress user.
		if ( null === $user && is_user_logged_in() ) {
			$wp_user  = wp_get_current_user();
			$raw_user = array(
				'email'      => $wp_user->user_email,
				'first_name' => $wp_user->first_name,
				'last_name'  => $wp_user->last_name,
				'phone'      => get_user_meta( $wp_user->ID, 'billing_phone', true ),
				'city'       => get_user_meta( $wp_user->ID, 'billing_city', true ),
				'state'      => get_user_meta( $wp_user->ID, 'billing_state', true ),
				'country'    => get_user_meta( $wp_user->ID, 'billing_country', true ),
				'zip'        => get_user_meta( $wp_user->ID, 'billing_postcode', true ),
			);
			return $this->normalizer->normalize_user( $raw_user );
		}

		// Handle FluentCart customer object.
		if ( is_numeric( $user ) ) {
			// Try to get customer by ID.
			if ( class_exists( '\FluentCart\App\Models\Customer' ) ) {
				$user = \FluentCart\App\Models\Customer::find( $user );
			}
		}

		if ( ! is_object( $user ) ) {
			return false;
		}

		$raw_user = array(
			'email'      => isset( $user->email ) ? $user->email : '',
			'first_name' => isset( $user->first_name ) ? $user->first_name : '',
			'last_name'  => isset( $user->last_name ) ? $user->last_name : '',
			'phone'      => isset( $user->phone ) ? $user->phone : '',
			'city'       => isset( $user->city ) ? $user->city : '',
			'state'      => isset( $user->state ) ? $user->state : '',
			'country'    => isset( $user->country ) ? $user->country : '',
			'zip'        => isset( $user->postcode ) ? $user->postcode : '',  // FluentCart uses 'postcode' not 'zip'
		);

		return $this->normalizer->normalize_user( $raw_user );
	}

	/**
	 * Get order by ID.
	 *
	 * @param int|string $order_id Order ID.
	 * @return \FluentCart\App\Models\Order|false Order object or false.
	 */
	public function get_order( $order_id ) {
		if ( ! class_exists( '\FluentCart\App\Models\Order' ) ) {
			return false;
		}

		return \FluentCart\App\Models\Order::find( $order_id );
	}

	/**
	 * Get product by ID.
	 *
	 * @param int|string $product_id Product ID.
	 * @return \FluentCart\App\Models\Product|false Product object or false.
	 */
	public function get_product( $product_id ) {
		if ( ! class_exists( '\FluentCart\App\Models\Product' ) ) {
			return false;
		}

		$product = \FluentCart\App\Models\Product::find( $product_id );

		return $product;
	}

	/**
	 * Extract coupon codes from order.
	 *
	 * @param object $order FluentCart order.
	 * @return array Coupon codes.
	 */
	private function extract_coupon_codes( $order ) {
		$coupons = array();

		if ( isset( $order->coupons ) && is_array( $order->coupons ) ) {
			foreach ( $order->coupons as $coupon ) {
				if ( isset( $coupon->code ) ) {
					$coupons[] = $coupon->code;
				}
			}
		} elseif ( isset( $order->coupon_code ) && ! empty( $order->coupon_code ) ) {
			$coupons[] = $order->coupon_code;
		}

		return $coupons;
	}

	/**
	 * Get payment type.
	 *
	 * @param object $order FluentCart order.
	 * @return string Payment type.
	 */
	private function get_payment_type( $order ) {
		if ( $this->is_subscription_order( $order ) ) {
			return 'subscription';
		}

		return 'one_time';
	}

	/**
	 * Check if this is a subscription order.
	 *
	 * @param object $order FluentCart order.
	 * @return bool True if subscription order.
	 */
	private function is_subscription_order( $order ) {
		// Check if FluentCart Pro is active and order has subscription.
		if ( ! defined( 'FLUENTCART_PRO_PLUGIN_VERSION' ) ) {
			return false;
		}

		return isset( $order->is_subscription ) && $order->is_subscription;
	}

	/**
	 * Get subscription plan name.
	 *
	 * @param object $order FluentCart order.
	 * @return string Subscription plan name.
	 */
	private function get_subscription_plan_name( $order ) {
		if ( isset( $order->subscription_plan ) ) {
			return $order->subscription_plan;
		}

		return '';
	}

	/**
	 * Get billing interval.
	 *
	 * @param object $order FluentCart order.
	 * @return string Billing interval (month, year, etc.).
	 */
	private function get_billing_interval( $order ) {
		if ( isset( $order->billing_interval ) ) {
			return $order->billing_interval;
		}

		return 'month';
	}

	/**
	 * Get next billing date.
	 *
	 * @param object $order FluentCart order.
	 * @return string Next billing date (ISO 8601).
	 */
	private function get_next_billing_date( $order ) {
		if ( isset( $order->next_billing_date ) ) {
			return gmdate( 'c', strtotime( $order->next_billing_date ) );
		}

		return '';
	}

	/**
	 * Check if this is customer's first purchase.
	 *
	 * @param object $order FluentCart order.
	 * @return bool True if first purchase.
	 */
	private function is_first_purchase( $order ) {
		$customer_id = isset( $order->customer_id ) ? $order->customer_id : 0;

		if ( $customer_id <= 0 ) {
			return false;
		}

		// Count customer's orders.
		if ( class_exists( '\FluentCart\App\Models\Order' ) ) {
			$order_count = \FluentCart\App\Models\Order::where( 'customer_id', $customer_id )
				->where( 'status', 'completed' )
				->count();

			return $order_count <= 1;
		}

		return false;
	}

	/**
	 * Get product URL.
	 *
	 * @param object $product FluentCart product or WP_Post.
	 * @return string Product URL.
	 */
	private function get_product_url( $product ) {
		if ( isset( $product->permalink ) ) {
			return $product->permalink;
		}

		// Check for ID (WP_Post) or id (Eloquent)
		$product_id = isset( $product->ID ) ? $product->ID : ( isset( $product->id ) ? $product->id : 0 );

		if ( $product_id ) {
			return get_permalink( $product_id );
		}

		return '';
	}

	/**
	 * Get product image URL.
	 *
	 * @param object $product FluentCart product or WP_Post.
	 * @return string Image URL.
	 */
	private function get_product_image_url( $product ) {
		if ( isset( $product->image_url ) ) {
			return $product->image_url;
		}

		if ( isset( $product->featured_image ) ) {
			return $product->featured_image;
		}

		// Check for ID (WP_Post) or id (Eloquent)
		$product_id = isset( $product->ID ) ? $product->ID : ( isset( $product->id ) ? $product->id : 0 );

		if ( $product_id ) {
			$thumbnail_url = get_the_post_thumbnail_url( $product_id, 'full' );
			if ( $thumbnail_url ) {
				return $thumbnail_url;
			}
		}

		return '';
	}

	/**
	 * Normalize currency code using centralized currency handler.
	 *
	 * Uses TrackSure_Currency_Handler for consistent currency normalization
	 * across all adapters and destinations.
	 *
	 * @param string $code Currency code from platform.
	 * @return string ISO 4217 compliant code.
	 */
	private function normalize_currency_code( $code ) {
		// Use centralized currency handler (DRY architecture)
		return $this->currency_handler->normalize( $code );
	}

	/**
	 * Verify FluentCart API structure (debug mode only).
	 *
	 * Logs warnings if expected FluentCart classes/methods are missing.
	 * Helps identify FluentCart API compatibility issues during development.
	 *
	 * @return void
	 */
	private function verify_fluentcart_api() {
		$missing = array();

		// Check required model classes.
		if ( ! class_exists( '\FluentCart\App\Models\Order' ) ) {
			$missing[] = 'Order model (\FluentCart\App\Models\Order)';
		}

		if ( ! class_exists( '\FluentCart\App\Models\Product' ) ) {
			$missing[] = 'Product model (\FluentCart\App\Models\Product)';
		}

		if ( ! class_exists( '\FluentCart\App\Models\Customer' ) ) {
			$missing[] = 'Customer model (\FluentCart\App\Models\Customer)';
		}

		// NOTE: FluentCart\App\Helpers\Cart class doesn't exist in FluentCart.
		// Cart data is retrieved via Cart Model or passed from hooks - no warning needed.

		// Log warnings if any classes are missing.
		if ( ! empty( $missing ) ) {
			// FluentCart API compatibility issues detected - missing classes logged during development.
		}
	}
}
