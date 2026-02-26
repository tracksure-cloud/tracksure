# 🚶 TrackSure Code Walkthrough

This document walks you through complete code flows in TrackSure, showing exactly what happens at each step with real code examples.

---

## Table of Contents

1. [Complete Purchase Event Flow](#complete-purchase-event-flow)
2. [Page View Tracking Flow](#page-view-tracking-flow)
3. [Session Management Flow](#session-management-flow)
4. [Consent Check Flow](#consent-check-flow)
5. [Destination Delivery Flow](#destination-delivery-flow)
6. [Attribution Flow](#attribution-flow)
7. [REST API Request Flow](#rest-api-request-flow)

---

## Complete Purchase Event Flow

Let's trace what happens when a customer completes a WooCommerce purchase, from start to finish.

### Step 1: Customer Completes Checkout

**Location**: WooCommerce (external plugin)

```php
// WooCommerce creates order and fires hook
$order_id = 12345;
do_action('woocommerce_thankyou', $order_id);
```

---

### Step 2: Integration Captures Hook

**File**: `includes/free/integrations/class-tracksure-woocommerce-v2.php`

**Code**:

```php
class TrackSure_WooCommerce_V2 {
    private $core;

    public function __construct($core) {
        $this->core = $core;

        // Listen for WooCommerce purchase completion
        add_action('woocommerce_thankyou', array($this, 'track_purchase'), 10, 1);
    }

    public function track_purchase($order_id) {
        // Get WooCommerce order object
        $order = wc_get_order($order_id);

        if (!$order) {
            return; // Order not found
        }

        // Check if already tracked (prevent duplicates)
        if ($order->get_meta('_tracksure_tracked')) {
            return;
        }

        // 🔹 STEP 3: Extract order data using adapter
        $this->extract_and_track_order($order);

        // Mark as tracked
        $order->update_meta_data('_tracksure_tracked', true);
        $order->save();
    }
}
```

---

### Step 3: Adapter Extracts Order Data

**File**: `includes/free/adapters/class-tracksure-woocommerce-adapter.php`

**Code**:

```php
class TrackSure_WooCommerce_Adapter implements TrackSure_Ecommerce_Adapter {

    public function extract_order_data($order) {
        // Extract standardized order data
        $order_data = array(
            'transaction_id' => (string) $order->get_id(),
            'value' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'tax' => (float) $order->get_total_tax(),
            'shipping' => (float) $order->get_shipping_total(),
            'coupon' => implode(',', $order->get_coupon_codes()),
        );

        // Extract line items (products)
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            $items[] = array(
                'item_id' => $product->get_sku() ?: $product->get_id(),
                'item_name' => $item->get_name(),
                'price' => (float) $item->get_subtotal() / $item->get_quantity(),
                'quantity' => (int) $item->get_quantity(),
                'item_category' => $this->get_product_category($product),
            );
        }

        $order_data['items'] = $items;

        return $order_data;
    }

    public function extract_user_data($user = null) {
        if (!$user && is_user_logged_in()) {
            $user = wp_get_current_user();
        }

        if (!$user) {
            return array();
        }

        return array(
            'email' => $user->user_email,
            'phone' => get_user_meta($user->ID, 'billing_phone', true),
            'first_name' => $user->user_firstname,
            'last_name' => $user->user_lastname,
            'city' => get_user_meta($user->ID, 'billing_city', true),
            'state' => get_user_meta($user->ID, 'billing_state', true),
            'country' => get_user_meta($user->ID, 'billing_country', true),
            'zip' => get_user_meta($user->ID, 'billing_postcode', true),
        );
    }
}
```

**Output**:

```php
$order_data = array(
    'transaction_id' => '12345',
    'value' => 99.99,
    'currency' => 'USD',
    'tax' => 5.00,
    'shipping' => 10.00,
    'coupon' => 'SAVE10',
    'items' => array(
        array(
            'item_id' => 'SKU-123',
            'item_name' => 'Blue T-Shirt',
            'price' => 29.99,
            'quantity' => 2,
            'item_category' => 'Apparel',
        ),
        array(
            'item_id' => 'SKU-456',
            'item_name' => 'Red Hat',
            'price' => 19.99,
            'quantity' => 1,
            'item_category' => 'Accessories',
        ),
    ),
);

$user_data = array(
    'email' => 'customer@example.com',
    'phone' => '+1234567890',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'city' => 'New York',
    'state' => 'NY',
    'country' => 'US',
    'zip' => '10001',
);
```

---

### Step 4: Event Builder Constructs Event

**File**: `includes/core/services/class-tracksure-event-builder.php`

**Code**:

```php
class TrackSure_Event_Builder {
    private $core;

    public function build_event($event_name, $params, $user_data = array()) {
        // Generate unique event ID
        $event_id = $this->generate_event_id();

        // Get current session
        $session_manager = $this->core->get_service('session_manager');
        $session = $session_manager->get_or_create_session();

        // Build base event structure
        $event = array(
            'event_id' => $event_id,
            'event_name' => $event_name,
            'event_time' => current_time('timestamp'),
            'params' => $params,
            'user_data' => $user_data,
            'session_id' => $session['session_id'],
            'visitor_id' => $session['visitor_id'],
        );

        // Add page context
        $event['page_url'] = $this->get_current_url();
        $event['page_title'] = wp_title('', false);
        $event['page_referrer'] = wp_get_referer();

        // Add UTM parameters from session
        if (!empty($session['utm_source'])) {
            $event['utm'] = array(
                'source' => $session['utm_source'],
                'medium' => $session['utm_medium'],
                'campaign' => $session['utm_campaign'],
                'term' => $session['utm_term'],
                'content' => $session['utm_content'],
            );
        }

        // Add device info
        $event['device'] = array(
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip_address' => $this->get_client_ip(),
            'language' => get_locale(),
        );

        // Validate against registry
        $registry = TrackSure_Registry::get_instance();
        if (!$registry->validate_event($event_name, $params)) {
            error_log("[TrackSure] Invalid event: {$event_name}");
        }

        return $event;
    }

    private function generate_event_id() {
        return 'evt_' . wp_generate_uuid4();
    }
}
```

**Output**:

```php
$event = array(
    'event_id' => 'evt_a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'event_name' => 'purchase',
    'event_time' => 1705564800, // 2026-01-17 10:00:00
    'params' => array(
        'transaction_id' => '12345',
        'value' => 99.99,
        'currency' => 'USD',
        'tax' => 5.00,
        'shipping' => 10.00,
        'items' => [...],
    ),
    'user_data' => array(
        'email' => 'customer@example.com',
        'phone' => '+1234567890',
        ...
    ),
    'session_id' => 'sess_xyz789',
    'visitor_id' => 'visitor_abc123',
    'page_url' => 'https://example.com/checkout/order-received/12345',
    'page_title' => 'Order Received',
    'page_referrer' => 'https://example.com/checkout',
    'utm' => array(
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'spring_sale',
        'term' => 'blue+shirt',
        'content' => 'ad_variant_a',
    ),
    'device' => array(
        'user_agent' => 'Mozilla/5.0...',
        'ip_address' => '192.168.1.1',
        'language' => 'en_US',
    ),
);
```

---

### Step 5: Consent Manager Checks Consent

**File**: `includes/core/services/class-tracksure-consent-manager.php`

**Code**:

```php
class TrackSure_Consent_Manager {

    public function is_tracking_allowed() {
        // Check if user has given consent
        $consent = $this->get_consent_status();

        // Apply filter to allow third-party consent plugins
        $allowed = apply_filters('tracksure_should_track_user', $consent);

        return $allowed;
    }

    public function get_consent_status() {
        // Check cookie
        if (isset($_COOKIE['_ts_consent'])) {
            return $_COOKIE['_ts_consent'] === 'granted';
        }

        // Check for third-party consent plugins
        // Cookiebot integration
        if (class_exists('Cookiebot_WP')) {
            return $this->check_cookiebot_consent();
        }

        // CookieYes integration
        if (function_exists('cky_get_consent_status')) {
            return cky_get_consent_status() === 'yes';
        }

        // Default: assume consent (can be changed in settings)
        $default = get_option('tracksure_default_consent', 'granted');
        return $default === 'granted';
    }

    public function anonymize_event($event_data) {
        // Remove personal data if consent not given
        if (isset($event_data['user_data'])) {
            $event_data['user_data'] = array(
                'email' => 'ANONYMIZED',
                'phone' => 'ANONYMIZED',
                'first_name' => 'ANONYMIZED',
                'last_name' => 'ANONYMIZED',
                // Keep non-personal data
                'city' => $event_data['user_data']['city'] ?? null,
                'state' => $event_data['user_data']['state'] ?? null,
                'country' => $event_data['user_data']['country'] ?? null,
            );
        }

        // Mark event as anonymized
        $event_data['anonymized'] = true;

        return $event_data;
    }
}
```

**Scenario 1: Consent Given**

```php
$consent_manager->is_tracking_allowed(); // true
// Event passes through unchanged
```

**Scenario 2: Consent Denied**

```php
$consent_manager->is_tracking_allowed(); // false
$event = $consent_manager->anonymize_event($event);

// Result:
$event['user_data'] = array(
    'email' => 'ANONYMIZED',
    'phone' => 'ANONYMIZED',
    'first_name' => 'ANONYMIZED',
    'last_name' => 'ANONYMIZED',
    'city' => 'New York',
    'state' => 'NY',
    'country' => 'US',
);
$event['anonymized'] = true;
```

---

### Step 6: Event Recorder Saves to Database

**File**: `includes/core/services/class-tracksure-event-recorder.php`

**Code**:

```php
class TrackSure_Event_Recorder {
    private $core;

    public function record($event_data, $session = null) {
        // Check consent
        $consent_manager = $this->core->get_service('consent_manager');
        if (!$consent_manager->is_tracking_allowed()) {
            $event_data = $consent_manager->anonymize_event($event_data);
        }

        // Get database instance
        $db = TrackSure_DB::get_instance();

        // Prepare data for storage
        $insert_data = array(
            'event_id' => $event_data['event_id'],
            'event_name' => $event_data['event_name'],
            'event_data' => wp_json_encode($event_data),
            'session_id' => $event_data['session_id'],
            'visitor_id' => $event_data['visitor_id'],
            'event_time' => date('Y-m-d H:i:s', $event_data['event_time']),
            'created_at' => current_time('mysql'),
        );

        // Insert into database
        global $wpdb;
        $result = $wpdb->insert(
            $db->tables->events,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log('[TrackSure] Failed to insert event: ' . $wpdb->last_error);
            return false;
        }

        // Get insert ID
        $event_id = $event_data['event_id'];

        // NOTE: In the current implementation, events are routed through
        // the Event Queue and inserted via insert_events_batch() for
        // performance. The queue collects events during the request and
        // flushes them as a single batched INSERT instead of individual
        // $wpdb->insert() calls. The code above is shown for clarity.

        // 🔹 STEP 7: Trigger hook for destination delivery
        do_action('tracksure_event_recorded', $event_id, $event_data, $session);

        // Log success
        if (defined('TRACKSURE_DEBUG') && TRACKSURE_DEBUG) {
            error_log("[TrackSure] Event recorded: {$event_data['event_name']} ({$event_id})");
        }

        return $event_id;
    }
}
```

**Database Insert**:

```sql
INSERT INTO wp_tracksure_events
(event_id, event_name, event_data, session_id, visitor_id, event_time, created_at)
VALUES
('evt_a1b2c3d4...', 'purchase', '{...JSON...}', 'sess_xyz789', 'visitor_abc123', '2026-01-17 10:00:00', '2026-01-17 10:00:00');
```

---

### Step 7: Destinations Manager Distributes

**File**: `includes/core/destinations/class-tracksure-destinations-manager.php`

**Code**:

```php
class TrackSure_Destinations_Manager {
    private $core;

    public function __construct($core) {
        $this->core = $core;

        // Listen for recorded events
        add_action('tracksure_event_recorded', array($this, 'distribute_event'), 10, 2);
    }

    public function distribute_event($event_id, $event_data) {
        // Get enabled destinations (each setting is an individual wp_option)
        $destinations_enabled = get_option('tracksure_enable_destinations', true);
        $enabled_destinations = $destinations_enabled ? array('ga4', 'meta') : array();

        // Apply filter to allow third-party modification
        $enabled_destinations = apply_filters('tracksure_enabled_destinations', $enabled_destinations, $event_data);

        foreach ($enabled_destinations as $destination) {
            // 🔹 STEP 8: Map event to destination format
            $this->queue_for_destination($event_id, $destination, $event_data);
        }
    }

    private function queue_for_destination($event_id, $destination, $event_data) {
        // Get event mapper service
        $event_mapper = $this->core->get_service('event_mapper');

        // Map event to destination-specific format
        $mapped_event = $event_mapper->map_event($event_data, $destination);

        if (!$mapped_event) {
            error_log("[TrackSure] Failed to map event {$event_id} for {$destination}");
            return;
        }

        // Add to outbox for background delivery
        global $wpdb;
        $db = TrackSure_DB::get_instance();

        $wpdb->insert(
            $db->tables->outbox,
            array(
                'event_id' => $event_id,
                'destination' => $destination,
                'event_data' => wp_json_encode($mapped_event),
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );

        if (defined('TRACKSURE_DEBUG') && TRACKSURE_DEBUG) {
            error_log("[TrackSure] Queued event {$event_id} for {$destination}");
        }
    }
}
```

---

### Step 8: Event Mapper Maps to GA4 Format

**File**: `includes/core/services/class-tracksure-event-mapper.php`

**Code**:

```php
class TrackSure_Event_Mapper {

    public function map_event($event_data, $destination) {
        // Route to destination-specific mapper
        $method = "map_to_{$destination}";

        if (!method_exists($this, $method)) {
            error_log("[TrackSure] No mapper for destination: {$destination}");
            return null;
        }

        return $this->$method($event_data);
    }

    private function map_to_ga4($event_data) {
        // Map to GA4 Measurement Protocol format
        $ga4_event = array(
            'client_id' => $event_data['visitor_id'],
            'timestamp_micros' => $event_data['event_time'] * 1000000,
            'non_personalized_ads' => $event_data['anonymized'] ?? false,
            'events' => array(
                array(
                    'name' => $this->map_event_name_ga4($event_data['event_name']),
                    'params' => $this->map_params_ga4($event_data['params']),
                ),
            ),
        );

        // Add user properties
        if (!empty($event_data['user_data']['email'])) {
            $ga4_event['user_properties'] = array(
                'email' => array('value' => $event_data['user_data']['email']),
            );
        }

        return $ga4_event;
    }

    private function map_event_name_ga4($event_name) {
        // Map TrackSure event names to GA4 event names
        $map = array(
            'purchase' => 'purchase',
            'add_to_cart' => 'add_to_cart',
            'page_view' => 'page_view',
            'form_submit' => 'generate_lead',
        );

        return $map[$event_name] ?? $event_name;
    }

    private function map_params_ga4($params) {
        // Map to GA4 parameter names
        return array(
            'transaction_id' => $params['transaction_id'] ?? null,
            'value' => $params['value'] ?? null,
            'currency' => $params['currency'] ?? 'USD',
            'tax' => $params['tax'] ?? null,
            'shipping' => $params['shipping'] ?? null,
            'items' => $this->map_items_ga4($params['items'] ?? array()),
        );
    }

    private function map_items_ga4($items) {
        return array_map(function($item) {
            return array(
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'item_category' => $item['item_category'] ?? null,
            );
        }, $items);
    }
}
```

**GA4 Output**:

```php
$ga4_event = array(
    'client_id' => 'visitor_abc123',
    'timestamp_micros' => 1705564800000000,
    'non_personalized_ads' => false,
    'events' => array(
        array(
            'name' => 'purchase',
            'params' => array(
                'transaction_id' => '12345',
                'value' => 99.99,
                'currency' => 'USD',
                'tax' => 5.00,
                'shipping' => 10.00,
                'items' => array(
                    array(
                        'item_id' => 'SKU-123',
                        'item_name' => 'Blue T-Shirt',
                        'price' => 29.99,
                        'quantity' => 2,
                        'item_category' => 'Apparel',
                    ),
                ),
            ),
        ),
    ),
    'user_properties' => array(
        'email' => array('value' => 'customer@example.com'),
    ),
);
```

---

### Step 9: Outbox Entry Created

**Database**:

```sql
INSERT INTO wp_tracksure_outbox
(event_id, destination, event_data, status, attempts, created_at)
VALUES
('evt_a1b2c3d4...', 'ga4', '{...GA4 JSON...}', 'pending', 0, '2026-01-17 10:00:00'),
('evt_a1b2c3d4...', 'meta', '{...Meta JSON...}', 'pending', 0, '2026-01-17 10:00:00');
```

---

### Step 10: Delivery Worker Sends (Background)

**File**: `includes/core/jobs/class-tracksure-delivery-worker.php`

**Runs via WP-Cron every 5 minutes**

**Code**:

```php
class TrackSure_Delivery_Worker {
    private $core;

    public function process() {
        global $wpdb;
        $db = TrackSure_DB::get_instance();

        // Get pending deliveries (limit 100 per run)
        $items = $wpdb->get_results("
            SELECT * FROM {$db->tables->outbox}
            WHERE status = 'pending'
            AND attempts < 3
            ORDER BY created_at ASC
            LIMIT 100
        ");

        foreach ($items as $item) {
            $this->deliver_item($item);
        }
    }

    private function deliver_item($item) {
        $destination = $item->destination;
        $event_data = json_decode($item->event_data, true);

        // Trigger destination-specific handler
        $result = apply_filters(
            'tracksure_deliver_mapped_event',
            array('success' => false),
            $destination,
            $event_data
        );

        global $wpdb;
        $db = TrackSure_DB::get_instance();

        if ($result['success']) {
            // Mark as delivered
            $wpdb->update(
                $db->tables->outbox,
                array(
                    'status' => 'delivered',
                    'delivered_at' => current_time('mysql'),
                ),
                array('id' => $item->id),
                array('%s', '%s'),
                array('%d')
            );

            error_log("[TrackSure] Delivered to {$destination}: {$item->event_id}");
        } else {
            // Increment attempts
            $wpdb->update(
                $db->tables->outbox,
                array('attempts' => $item->attempts + 1),
                array('id' => $item->id),
                array('%d'),
                array('%d')
            );

            error_log("[TrackSure] Failed delivery to {$destination}: {$item->event_id}");
        }
    }
}
```

---

### Step 11: GA4 Destination Sends to Google

**File**: `includes/free/destinations/class-tracksure-ga4-destination.php`

**Code**:

```php
class TrackSure_GA4_Destination {
    private $core;

    public function __construct($core) {
        $this->core = $core;

        // Hook into delivery system
        add_filter('tracksure_deliver_mapped_event', array($this, 'send'), 10, 3);
    }

    public function send($result, $destination, $mapped_event) {
        // Only handle GA4
        if ($destination !== 'ga4') {
            return $result;
        }

        // Get GA4 settings
        $measurement_id = get_option('tracksure_ga4_measurement_id');
        $api_secret = get_option('tracksure_ga4_api_secret');

        if (empty($measurement_id) || empty($api_secret)) {
            return array(
                'success' => false,
                'error' => 'GA4 not configured',
            );
        }

        // Send to GA4 Measurement Protocol
        $url = "https://www.google-analytics.com/mp/collect";
        $url .= "?measurement_id={$measurement_id}";
        $url .= "&api_secret={$api_secret}";

        $response = wp_remote_post($url, array(
            'body' => wp_json_encode($mapped_event),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        return array(
            'success' => ($status_code >= 200 && $status_code < 300),
            'status_code' => $status_code,
        );
    }
}
```

**HTTP Request**:

```http
POST https://www.google-analytics.com/mp/collect?measurement_id=G-XXXXXXXXXX&api_secret=abc123
Content-Type: application/json

{
  "client_id": "visitor_abc123",
  "timestamp_micros": 1705564800000000,
  "events": [
    {
      "name": "purchase",
      "params": {
        "transaction_id": "12345",
        "value": 99.99,
        "currency": "USD",
        ...
      }
    }
  ]
}
```

**Response**:

```
204 No Content
```

---

### Step 12: Database Updated

**Outbox Status Updated**:

```sql
UPDATE wp_tracksure_outbox
SET status = 'delivered', delivered_at = '2026-01-17 10:05:00'
WHERE id = 1;
```

---

## Summary: Complete Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Customer completes WooCommerce checkout                      │
│    → WooCommerce fires: do_action('woocommerce_thankyou')      │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. TrackSure_WooCommerce_V2 captures hook                       │
│    → Calls track_purchase($order_id)                            │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. TrackSure_WooCommerce_Adapter extracts order data            │
│    → Returns universal schema (transaction_id, value, items...) │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. TrackSure_Event_Builder builds standardized event            │
│    → Adds session, visitor, UTM, device info                    │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. TrackSure_Consent_Manager checks consent                     │
│    → If denied: anonymize personal data                         │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. TrackSure_Event_Recorder saves to database                   │
│    → Events queued and batch-inserted via insert_events_batch() │
│    → Fires: do_action('tracksure_event_recorded')              │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 7. TrackSure_Destinations_Manager distributes                   │
│    → Gets enabled destinations: ['ga4', 'meta']                 │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 8. TrackSure_Event_Mapper maps to destination formats           │
│    → GA4 format, Meta format                                    │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 9. Queue in outbox table                                        │
│    → INSERT INTO wp_tracksure_outbox (status='pending')         │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 10. TrackSure_Delivery_Worker processes queue (WP-Cron)         │
│     → Runs every 5 minutes                                      │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 11. TrackSure_GA4_Destination sends to Google                   │
│     → POST to GA4 Measurement Protocol                          │
│     → Response: 204 No Content (success)                        │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 12. Outbox updated                                              │
│     → UPDATE wp_tracksure_outbox SET status='delivered'         │
└─────────────────────────────────────────────────────────────────┘
```

**Total Time**:

- Steps 1-9: ~100ms (immediate, during page load)
- Steps 10-12: ~5 minutes later (background worker)

> **Note — Conversion Recording Deferral**: For conversion events (e.g., `purchase`), the actual database write is deferred to PHP's `shutdown` hook via `register_shutdown_function()`. This ensures the customer-facing response is not delayed by event recording. The event is queued in memory during the request and flushed to the database after the response is sent to the browser.

**Key Principles**:

- ✅ Never block page load (outbox pattern)
- ✅ Always record event (even if consent denied - anonymize instead)
- ✅ Retry on failure (up to 3 attempts)
- ✅ Universal schema (platform-agnostic)
- ✅ Extensible (hooks allow third-party customization)

---

**See Also**:

- [EVENT_SYSTEM.md](EVENT_SYSTEM.md) — Deep dive into the event pipeline (Builder → Recorder → Queue → Mapper)
- [PLUGIN_API.md](PLUGIN_API.md) — PHP & JavaScript public API reference
- [CUSTOM_EVENTS.md](CUSTOM_EVENTS.md) — Creating and tracking custom events

**Next**: Read [SESSION_MANAGEMENT.md](SESSION_MANAGEMENT.md) to understand session tracking, or [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md) to learn how to debug this flow.
