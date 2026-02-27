<?php

/**
 *
 * TrackSure WooCommerce Adapter
 *
 * Extracts data from WooCommerce orders, products, cart, and customers
 * into TrackSure's universal schema. This adapter is used by the WooCommerce
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
 * - Maintainability: WooCommerce API changes isolated here
 *
 * USED BY:
 * - class-tracksure-woocommerce-v2.php (integration)
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
 * WooCommerce Adapter Class
 */
class TrackSure_WooCommerce_Adapter implements TrackSure_Ecommerce_Adapter {



	/**
	 * Data normalizer instance.
	 *
	 * @var TrackSure_Data_Normalizer
	 */
	private $normalizer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->normalizer = TrackSure_Data_Normalizer::get_instance();
	}

	/**
	 * Get platform name.
	 *
	 * @return string
	 */
	public function get_platform_name() {
		return 'woocommerce';
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Extract order data from WC_Order object.
	 *
	 * @param WC_Order|int $order WC_Order object or order ID.
	 * @return array|false Normalized order data or false on failure.
	 */
	public function extract_order_data( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		// Extract basic order data.
		$raw_order = array(
			'transaction_id'  => (string) $order->get_id(),
			'value'           => (float) $order->get_total(),
			'currency'        => $order->get_currency(),
			'tax'             => (float) $order->get_total_tax(),
			'shipping'        => (float) $order->get_shipping_total(),
			'discount'        => (float) $order->get_discount_total(),
			'coupon_codes'    => $order->get_coupon_codes(),
			'payment_method'  => $order->get_payment_method(),
			'payment_gateway' => $order->get_payment_method_title(),
			'payment_type'    => 'one_time', // Default, check for subscription below
			'items'           => array(),
		);

		// Extract line items with type safety.
		foreach ( $order->get_items() as $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) || ! method_exists( $item, 'get_product' ) ) {
				continue;
			}

			$product = $item->get_product();
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$product_data = $this->extract_product_data_from_item( $item, $product );
				if ( $product_data ) {
					$raw_order['items'][] = $product_data;
				}
			}
		}

		// Check if this is a subscription order (with proper function existence checks).
		if (
			class_exists( 'WC_Subscriptions_Order' ) &&
			function_exists( 'wcs_order_contains_subscription' ) &&
			function_exists( 'wcs_get_subscriptions_for_order' ) &&
			wcs_order_contains_subscription( $order )
		) {

			$raw_order['payment_type'] = 'subscription';

			// Get subscription details.
			$subscriptions = wcs_get_subscriptions_for_order( $order );
			if ( ! empty( $subscriptions ) && is_array( $subscriptions ) ) {
				$subscription = reset( $subscriptions );
				if ( $subscription && is_object( $subscription ) ) {
					$raw_order['subscription_plan'] = method_exists( $subscription, 'get_id' ) ? $subscription->get_id() : '';
					$raw_order['billing_interval']  = method_exists( $subscription, 'get_billing_period' ) ? $subscription->get_billing_period() : '';
					$raw_order['next_billing_date'] = method_exists( $subscription, 'get_date' ) ? $subscription->get_date( 'next_payment' ) : '';
				}
			}
		}

		// Check if this is customer's first purchase (optimized).
		$customer_id = $order->get_customer_id();
		if ( $customer_id > 0 ) {
			$customer_orders = wc_get_orders(
				array(
					'customer_id' => $customer_id,
					'status'      => array( 'wc-completed', 'wc-processing' ),
					'limit'       => 2,
					'return'      => 'ids', // More efficient - only get IDs for count check
				)
			);

			$order_count                    = is_array( $customer_orders ) ? count( $customer_orders ) : 0;
			$raw_order['is_first_purchase'] = ( $order_count === 1 );

			// Calculate customer lifetime value using WooCommerce function (more efficient).
			if ( function_exists( 'wc_get_customer_total_spent' ) ) {
				$raw_order['customer_lifetime_value'] = (float) wc_get_customer_total_spent( $customer_id );
			} else {
				$raw_order['customer_lifetime_value'] = 0.0;
			}
		} else {
			$raw_order['is_first_purchase']       = true;
			$raw_order['customer_lifetime_value'] = 0.0;
		}

		// Normalize using universal schema.
		return $this->normalizer->normalize_order( $raw_order );
	}

	/**
	 * Extract product data from WC_Product object.
	 *
	 * @param WC_Product|int $product WC_Product object or product ID.
	 * @return array|false Normalized product data or false on failure.
	 */
	public function extract_product_data( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		// Get categories.
		$categories     = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		$category_names = is_array( $categories ) ? $categories : array();

		// Extract raw product data.
		$raw_product = array(
			'item_id'        => (string) $product->get_id(),
			'item_sku'       => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
			'item_name'      => $product->get_name(),
			'item_category'  => isset( $category_names[0] ) ? $category_names[0] : '',
			'item_category2' => isset( $category_names[1] ) ? $category_names[1] : '',
			'item_category3' => isset( $category_names[2] ) ? $category_names[2] : '',
			'item_brand'     => '', // WooCommerce doesn't have brands by default
			'item_variant'   => '',
			'price'          => (float) $product->get_price(),
			'quantity'       => 1,
			'currency'       => get_woocommerce_currency(),
			'item_url'       => $product->get_permalink(),
			'image_url'      => wp_get_attachment_url( $product->get_image_id() ),
		);

		// Get variant info if this is a variation (with method existence check).
		if ( $product->is_type( 'variation' ) && method_exists( $product, 'get_variation_attributes' ) ) {
			$variation_attributes = $product->get_variation_attributes();
			if ( is_array( $variation_attributes ) && ! empty( $variation_attributes ) ) {
				$attributes                  = array_values( array_filter( $variation_attributes ) );
				$raw_product['item_variant'] = implode( ' / ', $attributes );
			}
		}

		// Check for brands (various plugins).
		if ( function_exists( 'get_the_terms' ) ) {
			$brands = get_the_terms( $product->get_id(), 'product_brand' );
			if ( $brands && ! is_wp_error( $brands ) ) {
				$raw_product['item_brand'] = $brands[0]->name;
			}
		}

		// Normalize using universal schema.
		return $this->normalizer->normalize_product( $raw_product );
	}

	/**
	 * Extract product data from order item.
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @param WC_Product            $product Product object.
	 * @return array Normalized product data.
	 */
	private function extract_product_data_from_item( $item, $product ) {
		$product_data = $this->extract_product_data( $product );

		if ( $product_data && is_array( $product_data ) ) {
			// Override quantity from order item.
			$quantity                 = $item->get_quantity();
			$product_data['quantity'] = $quantity;

			// Calculate unit price (protect against division by zero).
			if ( $quantity > 0 ) {
				$product_data['price'] = (float) $item->get_total() / $quantity;
			} else {
				$product_data['price'] = 0.0;
			}
		}

		return $product_data;
	}

	/**
	 * Extract cart data from WooCommerce cart.
	 *
	 * @return array|false Normalized cart data or false on failure.
	 */
	public function extract_cart_data() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$cart = WC()->cart;

		// Extract cart items with validation.
		$cart_items    = array();
		$cart_contents = $cart->get_cart();

		if ( is_array( $cart_contents ) ) {
			foreach ( $cart_contents as $cart_item_key => $cart_item ) {
				// Validate cart item structure.
				if ( ! is_array( $cart_item ) || empty( $cart_item['data'] ) ) {
					continue;
				}

				$product = $cart_item['data'];
				if ( $product && is_a( $product, 'WC_Product' ) ) {
					$product_data = $this->extract_product_data( $product );
					if ( $product_data && is_array( $product_data ) ) {
						$product_data['quantity'] = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
						$cart_items[]             = $product_data;
					}
				}
			}
		}

		// Extract raw cart data.
		$raw_cart = array(
			'cart_id'           => '', // WooCommerce doesn't have persistent cart IDs
			'cart_value'        => (float) $cart->get_total( 'edit' ),
			'cart_items'        => $cart_items,
			'cart_coupon_codes' => $cart->get_applied_coupons(),
			'currency'          => get_woocommerce_currency(),
		);

		// Normalize using universal schema.
		return $this->normalizer->normalize_cart( $raw_cart );
	}

	/**
	 * Extract user data from WooCommerce customer.
	 *
	 * @param WC_Customer|int|null $user WC_Customer object, user ID, or null for current user.
	 * @return array|false Normalized user data or false on failure.
	 */
	public function extract_user_data( $user = null ) {
		// Get customer object.
		$customer = null;

		if ( is_null( $user ) ) {
			// Use current user.
			if ( function_exists( 'WC' ) && WC()->customer ) {
				$customer = WC()->customer;
			}
		} elseif ( is_numeric( $user ) ) {
			// Get customer by user ID.
			$customer = new WC_Customer( $user );
		} elseif ( is_a( $user, 'WC_Customer' ) ) {
			$customer = $user;
		} elseif ( is_a( $user, 'WC_Order' ) ) {
			// Extract from order.
			return $this->extract_user_data_from_order( $user );
		}

		if ( ! $customer || ! is_a( $customer, 'WC_Customer' ) ) {
			return false;
		}

		// Extract raw user data.
		$raw_user = array(
			'email'        => $customer->get_email(),
			'phone'        => $customer->get_billing_phone(),
			'first_name'   => $customer->get_first_name() ? $customer->get_first_name() : $customer->get_billing_first_name(),
			'last_name'    => $customer->get_last_name() ? $customer->get_last_name() : $customer->get_billing_last_name(),
			'country'      => $customer->get_billing_country(),
			'city'         => $customer->get_billing_city(),
			'state'        => $customer->get_billing_state(),
			'zip'          => $customer->get_billing_postcode(),
			'address'      => $customer->get_billing_address_1() . ( $customer->get_billing_address_2() ? ' ' . $customer->get_billing_address_2() : '' ),
			'user_id'      => $customer->get_id(),
			'is_logged_in' => $customer->get_id() > 0,
			'customer_id'  => (string) $customer->get_id(),
		);

		// Get order history (with function existence checks).
		if ( $customer->get_id() > 0 ) {
			if ( function_exists( 'wc_get_customer_order_count' ) ) {
				$raw_user['total_orders'] = (int) wc_get_customer_order_count( $customer->get_id() );
			} else {
				$raw_user['total_orders'] = 0;
			}

			if ( function_exists( 'wc_get_customer_total_spent' ) ) {
				$raw_user['total_revenue'] = (float) wc_get_customer_total_spent( $customer->get_id() );
			} else {
				$raw_user['total_revenue'] = 0.0;
			}

			// Get last order date.
			$last_order = wc_get_orders(
				array(
					'customer_id' => $customer->get_id(),
					'limit'       => 1,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);

			if ( ! empty( $last_order ) && is_array( $last_order ) && isset( $last_order[0] ) ) {
				$order_date = $last_order[0]->get_date_created();
				if ( $order_date && is_a( $order_date, 'WC_DateTime' ) ) {
					$raw_user['last_order_date'] = $order_date->date( 'c' );
				}
			}
		} else {
			$raw_user['total_orders']  = 0;
			$raw_user['total_revenue'] = 0.0;
		}

		// Get user role.
		if ( $customer->get_id() ) {
			$wp_user = get_userdata( $customer->get_id() );
			if ( $wp_user ) {
				$raw_user['user_role'] = ! empty( $wp_user->roles ) ? $wp_user->roles[0] : '';
			}
		}

		// Normalize using universal schema.
		return $this->normalizer->normalize_user( $raw_user );
	}

	/**
	 * Extract user data from order.
	 *
	 * @param WC_Order $order Order object.
	 * @return array|false Normalized user data.
	 */
	private function extract_user_data_from_order( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$raw_user = array(
			'email'        => $order->get_billing_email(),
			'phone'        => $order->get_billing_phone(),
			'first_name'   => $order->get_billing_first_name(),
			'last_name'    => $order->get_billing_last_name(),
			'country'      => $order->get_billing_country(),
			'city'         => $order->get_billing_city(),
			'state'        => $order->get_billing_state(),
			'zip'          => $order->get_billing_postcode(),
			'address'      => $order->get_billing_address_1() . ( $order->get_billing_address_2() ? ' ' . $order->get_billing_address_2() : '' ),
			'user_id'      => $order->get_customer_id(),
			'is_logged_in' => $order->get_customer_id() > 0,
			'customer_id'  => (string) $order->get_customer_id(),
		);

		return $this->normalizer->normalize_user( $raw_user );
	}

	/**
	 * Get order by ID.
	 *
	 * @param int|string $order_id Order ID.
	 * @return WC_Order|false
	 */
	public function get_order( $order_id ) {
		$order = wc_get_order( $order_id );
		return $order ? $order : false;
	}

	/**
	 * Get product by ID.
	 *
	 * @param int|string $product_id Product ID.
	 * @return WC_Product|false
	 */
	public function get_product( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? $product : false;
	}
}
