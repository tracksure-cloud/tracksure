<?php

/**
 *
 * TrackSure Ecommerce Adapter Interface
 *
 * Universal interface for extracting ecommerce data from ANY platform.
 * Implement this once per platform (WooCommerce, EDD, SureCart, Paddle, etc.)
 * and the data automatically works with ALL ad destinations.
 *
 * @package TrackSure\Core\Abstractions
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ecommerce Adapter Interface
 */
interface TrackSure_Ecommerce_Adapter {

	/**
	 * Get platform name.
	 *
	 * @return string Platform identifier (woocommerce, edd, surecart, paddle, etc.)
	 */
	public function get_platform_name();

	/**
	 * Check if platform is active.
	 *
	 * @return bool True if platform is available and active.
	 */
	public function is_active();

	/**
	 * Extract order data from platform-specific order object.
	 *
	 * @param mixed $order Platform-specific order object.
	 * @return array|false Normalized order data or false on failure.
	 */
	public function extract_order_data( $order );

	/**
	 * Extract product data from platform-specific product object.
	 *
	 * @param mixed $product Platform-specific product object.
	 * @return array|false Normalized product data or false on failure.
	 */
	public function extract_product_data( $product );

	/**
	 * Extract cart data from platform-specific cart object.
	 *
	 * @return array|false Normalized cart data or false on failure.
	 */
	public function extract_cart_data();

	/**
	 * Extract user data from platform-specific user/customer object.
	 *
	 * @param mixed $user Platform-specific user object (optional, uses current user if null).
	 * @return array|false Normalized user data or false on failure.
	 */
	public function extract_user_data( $user = null );

	/**
	 * Get order by ID.
	 *
	 * @param int|string $order_id Platform-specific order identifier.
	 * @return mixed|false Platform-specific order object or false.
	 */
	public function get_order( $order_id );

	/**
	 * Get product by ID.
	 *
	 * @param int|string $product_id Platform-specific product identifier.
	 * @return mixed|false Platform-specific product object or false.
	 */
	public function get_product( $product_id );
}
