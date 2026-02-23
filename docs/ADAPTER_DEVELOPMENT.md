# 🔌 TrackSure Adapter Development Guide

**Complete guide to creating ecommerce platform adapters for TrackSure**

---

## 📚 **Table of Contents**

1. [Overview](#overview)
2. [What is an Adapter?](#what-is-an-adapter)
3. [Adapter Interface](#adapter-interface)
4. [Creating an Adapter](#creating-an-adapter)
5. [Data Normalization](#data-normalization)
6. [Best Practices](#best-practices)
7. [Examples](#examples)

---

## 📖 **Overview**

**Adapters** are the bridge between ecommerce platforms and TrackSure's universal data schema. They extract platform-specific data and transform it into a standardized format that works with ALL destinations (Meta, GA4, TikTok, etc.).

**One Adapter → All Destinations**

```
WooCommerce Order
       ↓
WooCommerce Adapter (extract & normalize)
       ↓
Universal Schema
       ↓
Meta CAPI ✅  GA4 ✅  TikTok ✅  Pinterest ✅
```

---

## 🎯 **What is an Adapter?**

### **Purpose**

Adapters solve the **platform abstraction** problem:

- ✅ Extract data from ANY ecommerce platform
- ✅ Normalize to universal schema
- ✅ Ensure consistency across destinations
- ✅ Isolate platform-specific logic
- ✅ Enable easy testing

### **Separation of Concerns**

```
Integration (Hook Layer)
  ↓ Listens to platform events
  ↓ Passes platform objects
Adapter (Data Layer)
  ↓ Extracts raw data
  ↓ Normalizes to universal schema
  ↓ Returns standardized data
Event Builder
  ↓ Constructs events
Destinations
  ↓ Deliver to ad platforms
```

### **Why Separate Adapters?**

**❌ Without Adapter**:

- Each destination must understand WooCommerce, EDD, Shopify, etc.
- 10 platforms × 5 destinations = 50 integrations 😱
- Platform updates break multiple destinations

**✅ With Adapter**:

- Each platform has ONE adapter
- All destinations use universal schema
- 10 platforms + 5 destinations = 15 components 🎉
- Platform updates only affect adapter

---

## 🎨 **Adapter Interface**

All adapters must implement `TrackSure_Ecommerce_Adapter`:

```php
interface TrackSure_Ecommerce_Adapter {

    /**
     * Get platform name
     *
     * @return string Platform identifier (woocommerce, edd, surecart, etc.)
     */
    public function get_platform_name();

    /**
     * Check if platform is active
     *
     * @return bool
     */
    public function is_active();

    /**
     * Extract order data from platform-specific order object
     *
     * @param mixed $order Platform-specific order object
     * @return array|false Normalized order data or false on failure
     */
    public function extract_order_data($order);

    /**
     * Extract product data from platform-specific product object
     *
     * @param mixed $product Platform-specific product object
     * @return array|false Normalized product data or false on failure
     */
    public function extract_product_data($product);

    /**
     * Extract cart data from platform-specific cart object
     *
     * @return array|false Normalized cart data or false on failure
     */
    public function extract_cart_data();

    /**
     * Extract user data from platform-specific user/customer object
     *
     * @param mixed $user Platform-specific user object (optional)
     * @return array|false Normalized user data or false on failure
     */
    public function extract_user_data($user = null);

    /**
     * Get order by ID
     *
     * @param int|string $order_id Platform-specific order identifier
     * @return mixed|false Platform-specific order object or false
     */
    public function get_order($order_id);

    /**
     * Get product by ID
     *
     * @param int|string $product_id Platform-specific product identifier
     * @return mixed|false Platform-specific product object or false
     */
    public function get_product($product_id);
}
```

---

## 🚀 **Creating an Adapter**

### **Step 1: Create Adapter Class**

**File**: `includes/adapters/class-tracksure-myplatform-adapter.php`

```php
<?php

/**
 * TrackSure MyPlatform Adapter
 *
 * Extracts data from MyPlatform orders, products, cart, and customers
 * into TrackSure's universal schema.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TrackSure_MyPlatform_Adapter implements TrackSure_Ecommerce_Adapter {

    /**
     * Data normalizer instance
     *
     * @var TrackSure_Data_Normalizer
     */
    private $normalizer;

    /**
     * Constructor
     */
    public function __construct() {
        $this->normalizer = TrackSure_Data_Normalizer::get_instance();
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function get_platform_name() {
        return 'myplatform';
    }

    /**
     * Check if platform is active
     *
     * @return bool
     */
    public function is_active() {
        return class_exists('MyPlatform');
    }

    /**
     * Extract order data
     *
     * @param mixed $order MyPlatform order object or ID
     * @return array|false
     */
    public function extract_order_data($order) {
        // Get order object if ID provided
        if (is_numeric($order)) {
            $order = $this->get_order($order);
        }

        if (!$order) {
            return false;
        }

        // Extract raw order data (platform-specific)
        $raw_order = [
            'transaction_id' => (string) $order->get_id(),
            'value' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'tax' => (float) $order->get_tax_total(),
            'shipping' => (float) $order->get_shipping_total(),
            'discount' => (float) $order->get_discount_total(),
            'coupon_codes' => $order->get_coupon_codes(),
            'payment_method' => $order->get_payment_method(),
            'payment_type' => 'one_time',
            'items' => [],
        ];

        // Extract line items
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $product_data = $this->extract_product_data_from_item($item, $product);
                if ($product_data) {
                    $raw_order['items'][] = $product_data;
                }
            }
        }

        // Normalize to universal schema
        return $this->normalizer->normalize_order($raw_order);
    }

    /**
     * Extract product data
     *
     * @param mixed $product MyPlatform product object or ID
     * @return array|false
     */
    public function extract_product_data($product) {
        // Get product object if ID provided
        if (is_numeric($product)) {
            $product = $this->get_product($product);
        }

        if (!$product) {
            return false;
        }

        // Extract raw product data
        $raw_product = [
            'item_id' => (string) $product->get_id(),
            'item_name' => $product->get_name(),
            'item_brand' => $this->get_product_brand($product),
            'item_category' => $this->get_product_category($product),
            'item_variant' => $this->get_product_variant($product),
            'price' => (float) $product->get_price(),
            'quantity' => 1,
        ];

        // Normalize
        return $this->normalizer->normalize_product($raw_product);
    }

    /**
     * Extract cart data
     *
     * @return array|false
     */
    public function extract_cart_data() {
        // Check if cart exists
        if (!function_exists('myplatform_get_cart')) {
            return false;
        }

        $cart = myplatform_get_cart();
        if (!$cart || $cart->is_empty()) {
            return false;
        }

        // Extract cart data
        $raw_cart = [
            'currency' => myplatform_get_currency(),
            'value' => (float) $cart->get_total(),
            'items' => [],
        ];

        // Extract cart items
        foreach ($cart->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $raw_cart['items'][] = [
                    'item_id' => (string) $product->get_id(),
                    'item_name' => $product->get_name(),
                    'price' => (float) $product->get_price(),
                    'quantity' => (int) $item->get_quantity(),
                ];
            }
        }

        // Normalize
        return $this->normalizer->normalize_cart($raw_cart);
    }

    /**
     * Extract user data
     *
     * @param mixed $user User object or null for current user
     * @return array|false
     */
    public function extract_user_data($user = null) {
        // Get current user if not provided
        if (!$user && is_user_logged_in()) {
            $user = wp_get_current_user();
        }

        if (!$user || !$user->exists()) {
            return false;
        }

        // Extract raw user data
        $raw_user = [
            'user_id' => (string) $user->ID,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => get_user_meta($user->ID, 'billing_phone', true),
            'city' => get_user_meta($user->ID, 'billing_city', true),
            'state' => get_user_meta($user->ID, 'billing_state', true),
            'country' => get_user_meta($user->ID, 'billing_country', true),
            'postal_code' => get_user_meta($user->ID, 'billing_postcode', true),
        ];

        // Normalize
        return $this->normalizer->normalize_user($raw_user);
    }

    /**
     * Get order by ID
     *
     * @param int|string $order_id
     * @return mixed|false
     */
    public function get_order($order_id) {
        if (!function_exists('myplatform_get_order')) {
            return false;
        }

        return myplatform_get_order($order_id);
    }

    /**
     * Get product by ID
     *
     * @param int|string $product_id
     * @return mixed|false
     */
    public function get_product($product_id) {
        if (!function_exists('myplatform_get_product')) {
            return false;
        }

        return myplatform_get_product($product_id);
    }

    /**
     * Helper: Extract product data from order item
     *
     * @param mixed $item Order item
     * @param mixed $product Product object
     * @return array|false
     */
    private function extract_product_data_from_item($item, $product) {
        return [
            'item_id' => (string) $product->get_id(),
            'item_name' => $product->get_name(),
            'item_brand' => $this->get_product_brand($product),
            'item_category' => $this->get_product_category($product),
            'item_variant' => $this->get_product_variant($product),
            'price' => (float) $item->get_price(),
            'quantity' => (int) $item->get_quantity(),
        ];
    }

    /**
     * Helper: Get product brand
     *
     * @param mixed $product
     * @return string
     */
    private function get_product_brand($product) {
        // Platform-specific logic
        $brand = get_post_meta($product->get_id(), '_brand', true);
        return $brand ? sanitize_text_field($brand) : '';
    }

    /**
     * Helper: Get product category
     *
     * @param mixed $product
     * @return string
     */
    private function get_product_category($product) {
        $categories = get_the_terms($product->get_id(), 'product_cat');
        if ($categories && !is_wp_error($categories)) {
            return sanitize_text_field($categories[0]->name);
        }
        return '';
    }

    /**
     * Helper: Get product variant
     *
     * @param mixed $product
     * @return string
     */
    private function get_product_variant($product) {
        if (method_exists($product, 'get_variation_attributes')) {
            $attributes = $product->get_variation_attributes();
            if (!empty($attributes)) {
                return implode(' / ', array_values($attributes));
            }
        }
        return '';
    }
}
```

### **Step 2: Register Adapter in Integration**

**File**: `includes/integrations/class-tracksure-myplatform-integration.php`

```php
<?php

class TrackSure_MyPlatform_Integration {

    private $core;
    private $adapter;

    public function __construct($core) {
        $this->core = $core;

        // Load adapter
        require_once MY_EXT_DIR . 'includes/adapters/class-tracksure-myplatform-adapter.php';
        $this->adapter = new TrackSure_MyPlatform_Adapter();

        // Initialize hooks
        $this->init_hooks();
    }

    private function init_hooks() {
        // Order completed
        add_action('myplatform_order_completed', [$this, 'track_purchase'], 10, 1);

        // Product viewed
        add_action('myplatform_product_viewed', [$this, 'track_view_item'], 10, 1);

        // Add to cart
        add_action('myplatform_add_to_cart', [$this, 'track_add_to_cart'], 10, 2);
    }

    public function track_purchase($order_id) {
        try {
            // Use adapter to extract order data
            $order_data = $this->adapter->extract_order_data($order_id);

            if (!$order_data) {
                return;
            }

            // Build event
            $event_builder = $this->core->get_service('event_builder');
            $event_data = $event_builder->build_event('purchase', $order_data);

            // Record event
            $event_recorder = $this->core->get_service('event_recorder');
            $event_recorder->record($event_data);

        } catch (Exception $e) {
            $logger = $this->core->get_service('logger');
            $logger->error('MyPlatform purchase tracking failed: ' . $e->getMessage());
        }
    }

    public function track_view_item($product_id) {
        try {
            // Use adapter to extract product data
            $product_data = $this->adapter->extract_product_data($product_id);

            if (!$product_data) {
                return;
            }

            // Build event
            $event_builder = $this->core->get_service('event_builder');
            $event_data = $event_builder->build_event('view_item', [
                'currency' => myplatform_get_currency(),
                'value' => $product_data['price'],
                'items' => [$product_data],
            ]);

            // Record event
            $event_recorder = $this->core->get_service('event_recorder');
            $event_recorder->record($event_data);

        } catch (Exception $e) {
            $logger = $this->core->get_service('logger');
            $logger->error('MyPlatform view item failed: ' . $e->getMessage());
        }
    }

    public function track_add_to_cart($product_id, $quantity) {
        try {
            $product_data = $this->adapter->extract_product_data($product_id);

            if (!$product_data) {
                return;
            }

            // Update quantity
            $product_data['quantity'] = $quantity;

            // Build event
            $event_builder = $this->core->get_service('event_builder');
            $event_data = $event_builder->build_event('add_to_cart', [
                'currency' => myplatform_get_currency(),
                'value' => $product_data['price'] * $quantity,
                'items' => [$product_data],
            ]);

            // Record event
            $event_recorder = $this->core->get_service('event_recorder');
            $event_recorder->record($event_data);

        } catch (Exception $e) {
            $logger = $this->core->get_service('logger');
            $logger->error('MyPlatform add to cart failed: ' . $e->getMessage());
        }
    }
}
```

---

## 📐 **Data Normalization**

### **Universal Schema Structure**

#### **Order Schema**

```php
[
    'transaction_id' => '12345',           // Required: Unique order ID
    'affiliation' => 'Online Store',       // Optional: Store name
    'value' => 199.99,                     // Required: Order total
    'currency' => 'USD',                   // Required: Currency code
    'tax' => 15.99,                        // Optional: Tax amount
    'shipping' => 10.00,                   // Optional: Shipping cost
    'discount' => 20.00,                   // Optional: Discount amount
    'coupon_codes' => ['SUMMER10'],        // Optional: Applied coupons
    'payment_method' => 'credit_card',     // Optional: Payment method
    'payment_type' => 'one_time',          // Optional: one_time|subscription
    'subscription_plan' => 'premium',      // Optional: For subscriptions
    'billing_interval' => 'monthly',       // Optional: For subscriptions
    'items' => [                           // Required: Array of products
        // Product items (see Product Schema)
    ],
]
```

#### **Product Schema**

```php
[
    'item_id' => 'SKU123',                 // Required: Product SKU/ID
    'item_name' => 'Premium Widget',       // Required: Product name
    'item_brand' => 'WidgetCo',            // Optional: Brand name
    'item_category' => 'Electronics',      // Optional: Category
    'item_category2' => 'Laptops',         // Optional: Subcategory
    'item_variant' => 'Blue / Large',      // Optional: Variant
    'price' => 99.99,                      // Required: Unit price
    'quantity' => 2,                       // Required: Quantity
    'discount' => 10.00,                   // Optional: Discount per unit
    'coupon' => 'SAVE10',                  // Optional: Product coupon
]
```

#### **Cart Schema**

```php
[
    'currency' => 'USD',                   // Required: Currency
    'value' => 299.98,                     // Required: Cart total
    'items' => [                           // Required: Cart items
        // Product items
    ],
]
```

#### **User Schema**

```php
[
    'user_id' => '123',                    // Optional: User ID
    'email' => 'user@example.com',         // Optional: Email (hashed)
    'phone' => '+15551234567',             // Optional: Phone (hashed)
    'first_name' => 'John',                // Optional: First name (hashed)
    'last_name' => 'Doe',                  // Optional: Last name (hashed)
    'city' => 'New York',                  // Optional: City (hashed)
    'state' => 'NY',                       // Optional: State/province
    'country' => 'US',                     // Optional: Country code
    'postal_code' => '10001',              // Optional: ZIP/postal (hashed)
]
```

### **Using Data Normalizer**

TrackSure provides `TrackSure_Data_Normalizer` for automatic normalization:

```php
// Get normalizer instance
$normalizer = TrackSure_Data_Normalizer::get_instance();

// Normalize order
$normalized_order = $normalizer->normalize_order($raw_order);

// Normalize product
$normalized_product = $normalizer->normalize_product($raw_product);

// Normalize cart
$normalized_cart = $normalizer->normalize_cart($raw_cart);

// Normalize user
$normalized_user = $normalizer->normalize_user($raw_user);
```

---

## ✅ **Best Practices**

### **1. Type Safety**

Always cast values to correct types:

```php
// ✅ GOOD
$order_data = [
    'transaction_id' => (string) $order->get_id(),
    'value' => (float) $order->get_total(),
    'tax' => (float) $order->get_tax_total(),
    'items' => (array) $order->get_items(),
];

// ❌ BAD
$order_data = [
    'transaction_id' => $order->get_id(), // Could be int
    'value' => $order->get_total(),       // Could be string
    'tax' => $order->get_tax_total(),     // Could be null
];
```

### **2. Null Safety**

Handle missing data gracefully:

```php
// ✅ GOOD
$brand = $product->get_brand();
$order_data['brand'] = $brand ? sanitize_text_field($brand) : '';

// ❌ BAD
$order_data['brand'] = $product->get_brand(); // Could be null
```

### **3. Method Existence Checks**

Always verify methods exist:

```php
// ✅ GOOD
if (method_exists($product, 'get_variation_attributes')) {
    $attributes = $product->get_variation_attributes();
}

// ❌ BAD
$attributes = $product->get_variation_attributes(); // Fatal error if missing
```

### **4. Error Handling**

Return false on failure:

```php
public function extract_order_data($order) {
    if (!$order || !is_object($order)) {
        return false; // Clear failure signal
    }

    // ... extraction logic

    return $this->normalizer->normalize_order($raw_order);
}
```

### **5. Consistent Currency**

Ensure currency is included:

```php
// ✅ GOOD
$cart_data = [
    'currency' => $this->get_currency(),  // Always included
    'value' => (float) $cart->get_total(),
];

// ❌ BAD
$cart_data = [
    'value' => $cart->get_total(), // Missing currency context
];
```

### **6. Quantity Normalization**

Always include quantity:

```php
// For single products
$product_data['quantity'] = 1;

// For cart/order items
$product_data['quantity'] = (int) $item->get_quantity();
```

---

## 📝 **Examples**

### **Example 1: Easy Digital Downloads Adapter**

```php
<?php

class TrackSure_EDD_Adapter implements TrackSure_Ecommerce_Adapter {

    private $normalizer;

    public function __construct() {
        $this->normalizer = TrackSure_Data_Normalizer::get_instance();
    }

    public function get_platform_name() {
        return 'edd';
    }

    public function is_active() {
        return class_exists('Easy_Digital_Downloads');
    }

    public function extract_order_data($payment) {
        if (is_numeric($payment)) {
            $payment = edd_get_payment($payment);
        }

        if (!$payment) {
            return false;
        }

        $raw_order = [
            'transaction_id' => (string) $payment->ID,
            'value' => (float) edd_get_payment_amount($payment->ID),
            'currency' => edd_get_payment_currency_code($payment->ID),
            'tax' => (float) edd_get_payment_tax($payment->ID),
            'discount' => (float) edd_get_payment_discount($payment->ID),
            'payment_method' => edd_get_payment_gateway($payment->ID),
            'items' => [],
        ];

        // Extract downloads
        $downloads = edd_get_payment_meta_downloads($payment->ID);
        if (is_array($downloads)) {
            foreach ($downloads as $download) {
                $raw_order['items'][] = [
                    'item_id' => (string) $download['id'],
                    'item_name' => get_the_title($download['id']),
                    'price' => (float) $download['price'],
                    'quantity' => (int) $download['quantity'],
                ];
            }
        }

        return $this->normalizer->normalize_order($raw_order);
    }

    public function extract_product_data($download) {
        if (is_numeric($download)) {
            $download = edd_get_download($download);
        }

        if (!$download) {
            return false;
        }

        $raw_product = [
            'item_id' => (string) $download->ID,
            'item_name' => $download->post_title,
            'item_category' => $this->get_download_category($download->ID),
            'price' => (float) edd_get_download_price($download->ID),
            'quantity' => 1,
        ];

        return $this->normalizer->normalize_product($raw_product);
    }

    public function extract_cart_data() {
        if (!function_exists('edd_get_cart_contents')) {
            return false;
        }

        $cart_items = edd_get_cart_contents();
        if (empty($cart_items)) {
            return false;
        }

        $raw_cart = [
            'currency' => edd_get_currency(),
            'value' => (float) edd_get_cart_total(),
            'items' => [],
        ];

        foreach ($cart_items as $item) {
            $raw_cart['items'][] = [
                'item_id' => (string) $item['id'],
                'item_name' => get_the_title($item['id']),
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
            ];
        }

        return $this->normalizer->normalize_cart($raw_cart);
    }

    public function extract_user_data($user = null) {
        if (!$user && is_user_logged_in()) {
            $user = wp_get_current_user();
        }

        if (!$user || !$user->exists()) {
            return false;
        }

        $raw_user = [
            'user_id' => (string) $user->ID,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];

        return $this->normalizer->normalize_user($raw_user);
    }

    public function get_order($payment_id) {
        return edd_get_payment($payment_id);
    }

    public function get_product($download_id) {
        return edd_get_download($download_id);
    }

    private function get_download_category($download_id) {
        $categories = wp_get_post_terms($download_id, 'download_category');
        if (!empty($categories) && !is_wp_error($categories)) {
            return sanitize_text_field($categories[0]->name);
        }
        return '';
    }
}
```

---

### **Example 2: SureCart Adapter**

```php
<?php

class TrackSure_SureCart_Adapter implements TrackSure_Ecommerce_Adapter {

    private $normalizer;

    public function __construct() {
        $this->normalizer = TrackSure_Data_Normalizer::get_instance();
    }

    public function get_platform_name() {
        return 'surecart';
    }

    public function is_active() {
        return function_exists('surecart');
    }

    public function extract_order_data($order) {
        if (is_string($order)) {
            $order = \SureCart\Models\Order::find($order);
        }

        if (!$order) {
            return false;
        }

        $raw_order = [
            'transaction_id' => $order->id,
            'value' => (float) ($order->amount_due / 100), // Cents to dollars
            'currency' => strtoupper($order->currency),
            'tax' => (float) ($order->tax_amount / 100),
            'discount' => (float) ($order->discount_amount / 100),
            'items' => [],
        ];

        // Extract line items
        if (!empty($order->line_items->data)) {
            foreach ($order->line_items->data as $item) {
                $raw_order['items'][] = [
                    'item_id' => $item->price->product->id ?? '',
                    'item_name' => $item->price->product->name ?? '',
                    'price' => (float) ($item->unit_amount / 100),
                    'quantity' => (int) $item->quantity,
                ];
            }
        }

        return $this->normalizer->normalize_order($raw_order);
    }

    public function extract_product_data($product) {
        if (is_string($product)) {
            $product = \SureCart\Models\Product::find($product);
        }

        if (!$product) {
            return false;
        }

        // Get default price
        $price = 0;
        if (!empty($product->prices->data)) {
            $price = (float) ($product->prices->data[0]->unit_amount / 100);
        }

        $raw_product = [
            'item_id' => $product->id,
            'item_name' => $product->name,
            'price' => $price,
            'quantity' => 1,
        ];

        return $this->normalizer->normalize_product($raw_product);
    }

    public function extract_cart_data() {
        // SureCart uses session-based cart
        $cart = \SureCart\Models\Cart::get();

        if (!$cart || empty($cart->line_items->data)) {
            return false;
        }

        $raw_cart = [
            'currency' => strtoupper($cart->currency),
            'value' => (float) ($cart->amount_due / 100),
            'items' => [],
        ];

        foreach ($cart->line_items->data as $item) {
            $raw_cart['items'][] = [
                'item_id' => $item->price->product->id ?? '',
                'item_name' => $item->price->product->name ?? '',
                'price' => (float) ($item->unit_amount / 100),
                'quantity' => (int) $item->quantity,
            ];
        }

        return $this->normalizer->normalize_cart($raw_cart);
    }

    public function extract_user_data($user = null) {
        if (!$user && is_user_logged_in()) {
            $user = wp_get_current_user();
        }

        if (!$user || !$user->exists()) {
            return false;
        }

        // Get SureCart customer
        $customer = \SureCart\Models\Customer::where('user_id', $user->ID)->first();

        $raw_user = [
            'user_id' => (string) $user->ID,
            'email' => $customer->email ?? $user->user_email,
            'first_name' => $customer->first_name ?? $user->first_name,
            'last_name' => $customer->last_name ?? $user->last_name,
        ];

        return $this->normalizer->normalize_user($raw_user);
    }

    public function get_order($order_id) {
        return \SureCart\Models\Order::find($order_id);
    }

    public function get_product($product_id) {
        return \SureCart\Models\Product::find($product_id);
    }
}
```

---

## 📖 **See Also**

- **[MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md)** - Creating TrackSure modules
- **[DESTINATION_DEVELOPMENT.md](DESTINATION_DEVELOPMENT.md)** - Creating ad platform destinations
- **[HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md)** - Available hooks
- **[CLASS_REFERENCE.md](CLASS_REFERENCE.md)** - Core classes reference
- **[CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md)** - System architecture

---

**Need Help?** Adapter development questions? Check the existing adapters in `includes/free/adapters/` for reference!
