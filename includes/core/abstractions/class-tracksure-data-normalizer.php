<?php

/**
 *
 * TrackSure Data Normalizer
 *
 * Normalizes data from ANY ecommerce platform into a universal schema.
 * This ensures TrackSure's database and ad destinations receive consistent data
 * regardless of whether the source is WooCommerce, EDD, SureCart, Paddle, etc.
 *
 * @package TrackSure\Core\Abstractions
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Normalizer Class
 */
class TrackSure_Data_Normalizer {


	/**
	 * Instance.
	 *
	 * @var TrackSure_Data_Normalizer
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Data_Normalizer
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
		// Private constructor for singleton.
	}

	/**
	 * Normalize order data to universal schema.
	 *
	 * Universal Order Schema:
	 * {
	 *     transaction_id: string (required) - Unique order ID
	 *     value: float (required) - Total order value
	 *     currency: string (required) - ISO 4217 currency code
	 *     tax: float - Tax amount
	 *     shipping: float - Shipping cost
	 *     discount: float - Total discount
	 *     coupon_codes: array - Applied coupon codes
	 *     payment_method: string - Payment method (card, paypal, stripe, etc.)
	 *     payment_gateway: string - Gateway name (WooCommerce, EDD, Paddle, etc.)
	 *     payment_type: string - Payment type (one_time, subscription, etc.)
	 *     items: array - Array of normalized products
	 *     is_first_purchase: bool - Customer's first order
	 *     customer_lifetime_value: float - Total CLV
	 *     subscription_plan: string - Subscription plan name (if subscription)
	 *     billing_interval: string - Billing interval (month, year)
	 *     next_billing_date: string - Next billing date (ISO 8601)
	 * }
	 *
	 * @param array $raw_order_data Platform-specific order data.
	 * @return array Normalized order data.
	 */
	public function normalize_order( $raw_order_data ) {
		// Required fields.
		$normalized = array(
			'transaction_id' => isset( $raw_order_data['transaction_id'] ) ? $raw_order_data['transaction_id'] : '',
			'value'          => isset( $raw_order_data['value'] ) ? floatval( $raw_order_data['value'] ) : 0.0,
			'currency'       => isset( $raw_order_data['currency'] ) ? strtoupper( $raw_order_data['currency'] ) : 'USD',
		);

		// Optional fields with defaults.
		$optional_fields = array(
			'tax'                     => 0.0,
			'shipping'                => 0.0,
			'discount'                => 0.0,
			'coupon_codes'            => array(),
			'payment_method'          => '',
			'payment_gateway'         => '',
			'payment_type'            => 'one_time',
			'items'                   => array(),
			'is_first_purchase'       => false,
			'customer_lifetime_value' => 0.0,
			'subscription_plan'       => '',
			'billing_interval'        => '',
			'next_billing_date'       => '',
		);

		foreach ( $optional_fields as $field => $default ) {
			if ( isset( $raw_order_data[ $field ] ) ) {
				$value = $raw_order_data[ $field ];

				// Type casting.
				if ( in_array( $field, array( 'tax', 'shipping', 'discount', 'customer_lifetime_value' ) ) ) {
					$value = floatval( $value );
				} elseif ( $field === 'is_first_purchase' ) {
					$value = (bool) $value;
				} elseif ( $field === 'coupon_codes' && ! is_array( $value ) ) {
					$value = array( $value );
				}

				$normalized[ $field ] = $value;
			} else {
				$normalized[ $field ] = $default;
			}
		}

		// Normalize items array.
		if ( ! empty( $normalized['items'] ) ) {
			$normalized['items'] = array_map( array( $this, 'normalize_product' ), $normalized['items'] );
		}

		return $normalized;
	}

	/**
	 * Normalize product data to universal schema.
	 *
	 * Universal Product Schema:
	 * {
	 *     item_id: string (required) - Product ID or SKU
	 *     item_sku: string - Stock keeping unit
	 *     item_name: string (required) - Product name
	 *     item_category: string - Primary category
	 *     item_category2: string - Secondary category
	 *     item_category3: string - Tertiary category
	 *     item_brand: string - Product brand
	 *     item_variant: string - Variant (size, color, etc.)
	 *     price: float (required) - Unit price
	 *     quantity: int - Quantity
	 *     currency: string - Currency code
	 *     item_url: string - Product page URL
	 *     image_url: string - Product image URL
	 *     position: int - Position in list (for impressions)
	 * }
	 *
	 * @param array $raw_product_data Platform-specific product data.
	 * @return array Normalized product data.
	 */
	public function normalize_product( $raw_product_data ) {
		// Required fields.
		$normalized = array(
			'item_id'   => isset( $raw_product_data['item_id'] ) ? strval( $raw_product_data['item_id'] ) : '',
			'item_name' => isset( $raw_product_data['item_name'] ) ? $raw_product_data['item_name'] : '',
			'price'     => isset( $raw_product_data['price'] ) ? floatval( $raw_product_data['price'] ) : 0.0,
		);

		// Optional fields.
		$optional_fields = array(
			'item_sku'       => '',
			'item_category'  => '',
			'item_category2' => '',
			'item_category3' => '',
			'item_brand'     => '',
			'item_variant'   => '',
			'quantity'       => 1,
			'currency'       => 'USD',
			'item_url'       => '',
			'image_url'      => '',
			'position'       => 0,
		);

		foreach ( $optional_fields as $field => $default ) {
			if ( isset( $raw_product_data[ $field ] ) ) {
				$value = $raw_product_data[ $field ];

				// Type casting.
				if ( $field === 'quantity' || $field === 'position' ) {
					$value = intval( $value );
				}

				$normalized[ $field ] = $value;
			} else {
				$normalized[ $field ] = $default;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize cart data to universal schema.
	 *
	 * Universal Cart Schema:
	 * {
	 *     cart_id: string - Unique cart identifier
	 *     cart_value: float - Total cart value
	 *     cart_items: array - Array of normalized products
	 *     cart_quantity_total: int - Total items in cart
	 *     cart_coupon_codes: array - Applied coupons
	 *     currency: string - Currency code
	 * }
	 *
	 * @param array $raw_cart_data Platform-specific cart data.
	 * @return array Normalized cart data.
	 */
	public function normalize_cart( $raw_cart_data ) {
		$normalized = array(
			'cart_id'             => isset( $raw_cart_data['cart_id'] ) ? $raw_cart_data['cart_id'] : '',
			'cart_value'          => isset( $raw_cart_data['cart_value'] ) ? floatval( $raw_cart_data['cart_value'] ) : 0.0,
			'cart_items'          => array(),
			'cart_quantity_total' => 0,
			'cart_coupon_codes'   => array(),
			'currency'            => isset( $raw_cart_data['currency'] ) ? strtoupper( $raw_cart_data['currency'] ) : 'USD',
		);

		// Normalize items.
		if ( isset( $raw_cart_data['cart_items'] ) && is_array( $raw_cart_data['cart_items'] ) ) {
			$normalized['cart_items'] = array_map( array( $this, 'normalize_product' ), $raw_cart_data['cart_items'] );

			// Calculate total quantity.
			foreach ( $normalized['cart_items'] as $item ) {
				$normalized['cart_quantity_total'] += isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;
			}
		}

		// Normalize coupon codes.
		if ( isset( $raw_cart_data['cart_coupon_codes'] ) ) {
			$normalized['cart_coupon_codes'] = is_array( $raw_cart_data['cart_coupon_codes'] )
				? $raw_cart_data['cart_coupon_codes']
				: array( $raw_cart_data['cart_coupon_codes'] );
		}

		return $normalized;
	}

	/**
	 * Normalize user data to universal schema.
	 *
	 * Universal User Schema:
	 * {
	 *     email: string - Raw email (hashed before sending to destinations)
	 *     email_sha256: string - SHA256 hashed email
	 *     phone: string - Raw phone (hashed before sending)
	 *     phone_sha256: string - SHA256 hashed phone
	 *     first_name: string
	 *     last_name: string
	 *     full_name: string
	 *     country: string - ISO 3166-1 alpha-2
	 *     city: string
	 *     state: string
	 *     zip: string
	 *     address: string - Street address
	 *     user_id: int - WordPress user ID
	 *     user_role: string - WordPress user role
	 *     is_logged_in: bool
	 *     customer_id: string - Platform-specific customer ID
	 *     total_orders: int
	 *     total_revenue: float
	 *     last_order_date: string - ISO 8601
	 * }
	 *
	 * @param array $raw_user_data Platform-specific user data.
	 * @return array Normalized user data.
	 */
	public function normalize_user( $raw_user_data ) {
		$normalized = array(
			'email'           => '',
			'email_sha256'    => '',
			'phone'           => '',
			'phone_sha256'    => '',
			'first_name'      => '',
			'last_name'       => '',
			'full_name'       => '',
			'country'         => '',
			'city'            => '',
			'state'           => '',
			'zip'             => '',
			'address'         => '',
			'user_id'         => 0,
			'user_role'       => '',
			'is_logged_in'    => false,
			'customer_id'     => '',
			'total_orders'    => 0,
			'total_revenue'   => 0.0,
			'last_order_date' => '',
		);

		foreach ( $normalized as $field => $default ) {
			if ( isset( $raw_user_data[ $field ] ) ) {
				$value = $raw_user_data[ $field ];

				// Type casting.
				if ( $field === 'user_id' || $field === 'total_orders' ) {
					$value = intval( $value );
				} elseif ( $field === 'total_revenue' ) {
					$value = floatval( $value );
				} elseif ( $field === 'is_logged_in' ) {
					$value = (bool) $value;
				}

				$normalized[ $field ] = $value;
			}
		}

		// Auto-hash email and phone if provided.
		if ( ! empty( $normalized['email'] ) && empty( $normalized['email_sha256'] ) ) {
			$normalized['email_sha256'] = hash( 'sha256', strtolower( trim( $normalized['email'] ) ) );
		}

		if ( ! empty( $normalized['phone'] ) && empty( $normalized['phone_sha256'] ) ) {
			// Remove non-numeric characters.
			$clean_phone                = preg_replace( '/[^0-9]/', '', $normalized['phone'] );
			$normalized['phone_sha256'] = hash( 'sha256', $clean_phone );
		}

		// Build full name if not provided.
		if ( empty( $normalized['full_name'] ) && ( ! empty( $normalized['first_name'] ) || ! empty( $normalized['last_name'] ) ) ) {
			$normalized['full_name'] = trim( $normalized['first_name'] . ' ' . $normalized['last_name'] );
		}

		return $normalized;
	}

	/**
	 * Normalize form data to universal schema.
	 *
	 * Universal Form Schema:
	 * {
	 *     form_id: string (required)
	 *     form_name: string
	 *     form_type: string (contact, quote, registration, booking, etc.)
	 *     form_location: string - URL where form appears
	 *     lead_value: float
	 *     fields_summary: array - Non-sensitive field types collected
	 * }
	 *
	 * @param array $raw_form_data Platform-specific form data.
	 * @return array Normalized form data.
	 */
	public function normalize_form( $raw_form_data ) {
		$normalized = array(
			'form_id'        => isset( $raw_form_data['form_id'] ) ? $raw_form_data['form_id'] : '',
			'form_name'      => isset( $raw_form_data['form_name'] ) ? $raw_form_data['form_name'] : '',
			'form_type'      => isset( $raw_form_data['form_type'] ) ? $raw_form_data['form_type'] : 'contact',
			'form_location'  => isset( $raw_form_data['form_location'] ) ? $raw_form_data['form_location'] : '',
			'lead_value'     => isset( $raw_form_data['lead_value'] ) ? floatval( $raw_form_data['lead_value'] ) : 0.0,
			'fields_summary' => isset( $raw_form_data['fields_summary'] ) && is_array( $raw_form_data['fields_summary'] )
				? $raw_form_data['fields_summary']
				: array(),
		);

		return $normalized;
	}
}
