# 🎯 TrackSure Destination Development Guide

**Complete guide to creating ad platform destination handlers for TrackSure**

---

## 📚 **Table of Contents**

1. [Overview](#overview)
2. [Destination Architecture](#destination-architecture)
3. [Browser vs Server-Side](#browser-vs-server-side)
4. [Event Mapping](#event-mapping)
5. [Creating a Destination](#creating-a-destination)
6. [Best Practices](#best-practices)
7. [Examples](#examples)

---

## 📖 **Overview**

**Destinations** deliver tracking events to ad platforms (Meta, Google, TikTok, Pinterest, etc.). They handle:

- ✅ **Browser pixel delivery** (JavaScript tags)
- ✅ **Server-side API delivery** (Conversions API)
- ✅ **Event mapping** (TrackSure → platform format)
- ✅ **User data enrichment** (PII hashing, cookies, IP)
- ✅ **Deduplication** (browser + server events)
- ✅ **Consent management** (GDPR, CCPA)

---

## 🏗️ **Destination Architecture**

### **Dual Delivery System**

```
TrackSure Event
       ↓
┌──────┴──────┐
Browser       Server
  ↓             ↓
Event Bridge   Event Mapper
  ↓             ↓
Pixel Code     Conversion API
  ↓             ↓
Meta Pixel     Meta CAPI
```

### **Two Layers**

1. **Browser Layer** (Event Bridge):

   - JavaScript pixel initialization
   - Event mapping for client-side tracking
   - Deduplication IDs

2. **Server Layer** (Event Mapper + Delivery Worker):
   - Event mapping to platform format
   - API delivery
   - User data enrichment
   - Consent handling

---

## 🖥️ **Browser vs Server-Side**

### **Browser Pixel**

**Strengths**:

- ✅ Automatic session tracking
- ✅ Cookie access (fbp, fbc, \_ga)
- ✅ User behavior tracking (scroll, clicks)
- ✅ Real-time event firing

**Limitations**:

- ❌ Blocked by ad blockers
- ❌ Cookie limitations (ITP, ETP)
- ❌ No access to backend data
- ❌ GDPR consent required

**When to Use**:

- Page views
- Click tracking
- Engagement tracking
- Real-time events

### **Server-Side API**

**Strengths**:

- ✅ Ad blocker proof
- ✅ Access to order data
- ✅ PII hashing (privacy)
- ✅ Reliable delivery

**Limitations**:

- ❌ No automatic session tracking
- ❌ No cookie access (must forward from browser)
- ❌ Delayed firing (async processing)

**When to Use**:

- Purchase tracking
- Backend events (order status changes)
- Subscription renewals
- High-value conversions

### **Best Practice: Use BOTH**

For critical events (purchase), send BOTH:

- **Browser**: Fast tracking, cookie signals
- **Server**: Reliable delivery, backend data

Use **event_id** for deduplication:

```php
// Same event_id ensures platforms deduplicate
$event_id = wp_generate_uuid4();

// Browser pixel
fbq('track', 'Purchase', {
    value: 99.99,
    currency: 'USD'
}, { eventID: $event_id });

// Server CAPI (async)
$payload = [
    'event_name' => 'Purchase',
    'event_id' => $event_id,  // Same ID!
    'event_time' => time(),
    // ... user_data, custom_data
];
```

---

## 🗺️ **Event Mapping**

### **Event Mapping Registry**

TrackSure uses a **centralized mapping registry** to convert universal events → platform-specific events:

```
TrackSure Event   →   Meta Event   →   GA4 Event
───────────────────────────────────────────────────
add_to_cart       →   AddToCart    →   add_to_cart
begin_checkout    →   InitiateCheckout → begin_checkout
purchase          →   Purchase     →   purchase
view_item         →   ViewContent  →   view_item
form_submit       →   Lead         →   generate_lead
```

### **Mapping Responsibilities**

**Event Mapper** (Core):

- Maps TrackSure event names → platform event names
- Maps TrackSure parameters → platform parameters
- Returns **mapped event** ready for API delivery

**Destination Handler**:

- Receives **pre-mapped event** from Event Mapper
- Enriches user data (hashing, cookies, IP)
- Sends to platform API
- **NO mapping logic!** (Already done by Event Mapper)

### **Separation of Concerns**

```
❌ BAD (mapping in destination):
class TrackSure_Meta_Destination {
    public function send($event) {
        // ❌ Mapping logic in destination
        $meta_event = $this->map_event($event);
        $this->send_to_capi($meta_event);
    }
}

✅ GOOD (mapping in Event Mapper):
class TrackSure_Meta_Destination {
    public function send($result, $destination, $mapped_event) {
        // ✅ Receives pre-mapped event
        // Only handles API delivery
        $this->send_to_capi($mapped_event);
    }
}
```

---

## 🚀 **Creating a Destination**

### **Step 1: Register Destination Metadata**

**File**: `includes/modules/class-my-module.php`

```php
public function register_destinations($manager) {
    $manager->register_destination([
        'id' => 'linkedin',
        'name' => 'LinkedIn Ads',
        'icon' => 'Linkedin',
        'order' => 40,
        'enabled_key' => 'tracksure_mymodule_linkedin_enabled',
        'class_name' => 'TrackSure_LinkedIn_Destination',
        'file_path' => MY_MODULE_DIR . 'includes/destinations/class-tracksure-linkedin-destination.php',
        'settings_fields' => [
            [
                'id' => 'linkedin_partner_id',
                'label' => 'Partner ID',
                'type' => 'text',
                'required' => true,
                'description' => 'Your LinkedIn Insight Tag Partner ID',
            ],
            [
                'id' => 'linkedin_conversion_ids',
                'label' => 'Conversion IDs',
                'type' => 'textarea',
                'description' => 'One conversion ID per line',
            ],
        ],
    ]);
}
```

### **Step 2: Create Destination Handler**

**File**: `includes/destinations/class-tracksure-linkedin-destination.php`

```php
<?php

/**
 * LinkedIn Ads Destination
 *
 * Handles browser pixel and Conversions API delivery.
 *
 * @package TrackSure
 */

if (!defined('ABSPATH')) {
    exit;
}

class TrackSure_LinkedIn_Destination {

    /**
     * Core instance
     *
     * @var TrackSure_Core
     */
    private $core;

    /**
     * Settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     *
     * @param TrackSure_Core $core Core instance
     */
    public function __construct($core) {
        $this->core = $core;
        $this->settings = $this->get_settings();

        if ($this->is_enabled()) {
            $this->init_hooks();
        }
    }

    /**
     * Get destination settings
     *
     * @return array
     */
    private function get_settings() {
        return [
            'enabled' => get_option('tracksure_mymodule_linkedin_enabled', false),
            'partner_id' => get_option('tracksure_mymodule_linkedin_partner_id', ''),
            'conversion_ids' => get_option('tracksure_mymodule_linkedin_conversion_ids', ''),
        ];
    }

    /**
     * Check if destination is enabled
     *
     * @return bool
     */
    private function is_enabled() {
        return !empty($this->settings['enabled']) &&
               !empty($this->settings['partner_id']);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register browser pixel
        $this->register_browser_destination();

        // Register server-side delivery
        add_filter('tracksure_deliver_mapped_event', [$this, 'send'], 10, 3);
    }

    /**
     * Register browser pixel with Event Bridge
     */
    private function register_browser_destination() {
        $bridge = $this->core->get_service('event_bridge');

        if (!$bridge) {
            return;
        }

        $bridge->register_browser_destination([
            'id' => 'linkedin',
            'enabled_key' => 'tracksure_mymodule_linkedin_enabled',
            'init_script' => [$this, 'get_pixel_init_script'],
            'event_mapper' => [$this, 'get_browser_event_mapper'],
        ]);
    }

    /**
     * Get LinkedIn Insight Tag initialization script
     *
     * @return string JavaScript code
     */
    public function get_pixel_init_script() {
        $partner_id = sanitize_text_field($this->settings['partner_id']);

        return "
        <!-- LinkedIn Insight Tag -->
        <script type='text/javascript'>
        _linkedin_partner_id = '{$partner_id}';
        window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
        window._linkedin_data_partner_ids.push(_linkedin_partner_id);
        </script>
        <script type='text/javascript'>
        (function(l) {
            if (!l){window.lintrk = function(a,b){window.lintrk.q.push([a,b])};
            window.lintrk.q=[]}
            var s = document.getElementsByTagName('script')[0];
            var b = document.createElement('script');
            b.type = 'text/javascript';b.async = true;
            b.src = 'https://snap.licdn.com/li.lms-analytics/insight.min.js';
            s.parentNode.insertBefore(b, s);
        })(window.lintrk);
        </script>
        <noscript>
        <img height='1' width='1' style='display:none;' alt=''
            src='https://px.ads.linkedin.com/collect/?pid={$partner_id}&fmt=gif' />
        </noscript>
        <!-- End LinkedIn Insight Tag -->
        ";
    }

    /**
     * Get browser event mapper function
     *
     * @return string JavaScript function
     */
    public function get_browser_event_mapper() {
        $conversion_ids = $this->parse_conversion_ids();
        $conversion_map = wp_json_encode($conversion_ids);

        return "function(trackSureEvent) {
            // LinkedIn event mapping
            var eventMap = {
                'page_view': null,  // Auto-tracked by Insight Tag
                'purchase': 'Conversion',
                'add_to_cart': 'AddToCart',
                'form_submit': 'Lead',
                'sign_up': 'SignUp'
            };

            var linkedInEvent = eventMap[trackSureEvent.event_name];
            if (!linkedInEvent) {
                return null;  // Skip unmapped events
            }

            // Get conversion ID for this event
            var conversionMap = {$conversion_map};
            var conversionId = conversionMap[trackSureEvent.event_name] || null;

            // Build LinkedIn tracking call
            return {
                name: linkedInEvent,
                conversionId: conversionId,
                params: {}  // LinkedIn has limited parameter support
            };
        }";
    }

    /**
     * Send event to LinkedIn Conversions API
     *
     * @param array  $result Default result
     * @param string $destination Destination ID
     * @param array  $mapped_event Pre-mapped event from Event Mapper
     * @return array Result
     */
    public function send($result, $destination, $mapped_event) {
        if ($destination !== 'linkedin') {
            return $result;
        }

        // LinkedIn CAPI requires conversion ID
        $conversion_id = $this->get_conversion_id($mapped_event['original_event_name']);
        if (!$conversion_id) {
            return [
                'success' => true,
                'error' => 'No conversion ID configured for ' . $mapped_event['original_event_name'],
            ];
        }

        // Build payload
        $payload = [
            'conversion_id' => $conversion_id,
            'conversion_time' => $mapped_event['event_time'] * 1000,  // Milliseconds
            'conversion_value' => [
                'amount' => $mapped_event['custom_data']['value'] ?? 0,
                'currency_code' => $mapped_event['custom_data']['currency'] ?? 'USD',
            ],
            'user' => $this->build_user_data($mapped_event['user_data']),
        ];

        // Send to LinkedIn CAPI
        $response = $this->send_capi_request($payload);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        return ['success' => true];
    }

    /**
     * Build LinkedIn user data
     *
     * @param array $user_data Raw user data
     * @return array LinkedIn formatted user data
     */
    private function build_user_data($user_data) {
        $linkedin_user = [];

        // Hash PII fields (SHA256)
        $hash_fields = [
            'email' => 'sha256Email',
            'first_name' => 'sha256FirstName',
            'last_name' => 'sha256LastName',
            'country' => 'sha256Country',
        ];

        foreach ($hash_fields as $field => $linkedin_field) {
            if (!empty($user_data[$field])) {
                $linkedin_user[$linkedin_field] = hash('sha256', strtolower(trim($user_data[$field])));
            }
        }

        return $linkedin_user;
    }

    /**
     * Send request to LinkedIn Conversions API
     *
     * @param array $payload Request payload
     * @return array|WP_Error Response
     */
    private function send_capi_request($payload) {
        $url = 'https://api.linkedin.com/v2/conversions';

        return wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->settings['api_token'],
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ]);
    }

    /**
     * Parse conversion IDs from settings
     *
     * @return array Event name => Conversion ID map
     */
    private function parse_conversion_ids() {
        $ids = [];
        $lines = explode("\n", $this->settings['conversion_ids']);

        foreach ($lines as $line) {
            $parts = explode('=', trim($line), 2);
            if (count($parts) === 2) {
                $ids[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $ids;
    }

    /**
     * Get conversion ID for event
     *
     * @param string $event_name TrackSure event name
     * @return string|null Conversion ID
     */
    private function get_conversion_id($event_name) {
        $ids = $this->parse_conversion_ids();
        return $ids[$event_name] ?? null;
    }
}
```

### **Step 3: Register Event Mappings**

**File**: `includes/modules/class-my-module.php`

```php
/**
 * Register event mappings for LinkedIn
 */
public function register_event_mappings() {
    add_filter('tracksure_event_mapping', function($mappings) {
        $mappings['linkedin'] = [
            'purchase' => [
                'event_name' => 'Conversion',
                'parameter_map' => [
                    'value' => 'amount',
                    'currency' => 'currency_code',
                ],
            ],
            'form_submit' => [
                'event_name' => 'Lead',
                'parameter_map' => [],
            ],
            'add_to_cart' => [
                'event_name' => 'AddToCart',
                'parameter_map' => [],
            ],
        ];

        return $mappings;
    });
}
```

---

## ✅ **Best Practices**

### **1. Separation of Concerns**

**Event Mapper** does mapping, **Destination** does delivery:

```php
// ✅ GOOD
public function send($result, $destination, $mapped_event) {
    // Destination receives PRE-MAPPED event
    // Only handles enrichment and API delivery
    $user_data = $this->enrich_user_data($mapped_event['user_data']);
    $this->send_to_api($mapped_event['event_name'], $user_data);
}

// ❌ BAD
public function send($result, $destination, $event) {
    // ❌ Mapping in destination (should be in Event Mapper)
    $platform_event = $this->map_event_name($event['event_name']);
    $this->send_to_api($platform_event);
}
```

### **2. User Data Enrichment**

Destinations handle **platform-specific** user data formatting:

```php
private function enrich_user_data($user_data) {
    $enriched = [];

    // 1. Add cookies if missing
    if (empty($user_data['fbp']) && isset($_COOKIE['_fbp'])) {
        $enriched['fbp'] = sanitize_text_field($_COOKIE['_fbp']);
    }

    // 2. Hash PII fields
    $hash_fields = ['email', 'phone', 'first_name', 'last_name'];
    foreach ($hash_fields as $field) {
        if (!empty($user_data[$field])) {
            $enriched[$field] = hash('sha256', strtolower(trim($user_data[$field])));
        }
    }

    // 3. Add IP and user agent if missing
    if (empty($user_data['client_ip_address'])) {
        $enriched['client_ip_address'] = $this->get_client_ip();
    }

    return array_merge($user_data, $enriched);
}
```

### **3. Error Handling**

Always return structured results:

```php
public function send($result, $destination, $mapped_event) {
    if ($destination !== 'myplatform') {
        return $result;  // Pass through
    }

    try {
        $response = $this->send_to_api($mapped_event);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        return ['success' => true];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}
```

### **4. Deduplication**

Use event_id for browser + server deduplication:

```php
// Browser pixel
public function get_browser_event_mapper() {
    return "function(trackSureEvent) {
        return {
            name: 'Purchase',
            params: { value: 99.99 },
            eventId: trackSureEvent.event_id  // Critical for dedup!
        };
    }";
}

// Server CAPI
public function send($result, $destination, $mapped_event) {
    $payload = [
        'event_id' => $mapped_event['event_id'],  // Same ID!
        'event_name' => 'Purchase',
        // ...
    ];
}
```

### **5. Consent Management**

Check consent before sending:

```php
public function send($result, $destination, $mapped_event) {
    $consent_manager = $this->core->get_service('consent_manager');
    $consent_granted = $consent_manager ? $consent_manager->is_tracking_allowed() : true;

    if (!$consent_granted) {
        // Apply Limited Data Use or skip entirely
        $payload['data_processing_options'] = ['LDU'];
    }

    return $this->send_to_api($payload);
}
```

---

## 📝 **Examples**

### **Example 1: Meta (Facebook) Destination**

**File**: `includes/free/destinations/class-tracksure-meta-destination.php`

```php
<?php

class TrackSure_Meta_Destination {

    private $core;
    private $settings;

    public function __construct($core) {
        $this->core = $core;
        $this->settings = $this->get_settings();

        if ($this->is_enabled()) {
            $this->init_hooks();
        }
    }

    private function get_settings() {
        return [
            'enabled' => get_option('tracksure_free_meta_enabled', false),
            'pixel_id' => get_option('tracksure_free_meta_pixel_id', ''),
            'access_token' => get_option('tracksure_free_meta_access_token', ''),
            'test_event_code' => get_option('tracksure_free_meta_test_event_code', ''),
        ];
    }

    private function is_enabled() {
        return !empty($this->settings['enabled']) &&
               !empty($this->settings['pixel_id']);
    }

    private function init_hooks() {
        // Register browser pixel
        $this->register_browser_destination();

        // Register server-side CAPI
        add_filter('tracksure_deliver_mapped_event', [$this, 'send'], 10, 3);
    }

    private function register_browser_destination() {
        $bridge = $this->core->get_service('event_bridge');

        if (!$bridge) {
            return;
        }

        $bridge->register_browser_destination([
            'id' => 'meta',
            'enabled_key' => 'tracksure_free_meta_enabled',
            'init_script' => [$this, 'get_pixel_init_script'],
            'event_mapper' => [$this, 'get_browser_event_mapper'],
        ]);
    }

    public function get_pixel_init_script() {
        $pixel_id = sanitize_text_field($this->settings['pixel_id']);

        // Get Advanced Matching data
        $user_data = $this->get_advanced_matching_data();
        $user_data_json = !empty($user_data) ? wp_json_encode($user_data) : '{}';

        return "
        <!-- Meta Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s){
            if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)
        }(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{$pixel_id}', {$user_data_json});
        fbq('track', 'PageView');
        </script>
        <noscript><img height='1' width='1' style='display:none'
            src='https://www.facebook.com/tr?id={$pixel_id}&ev=PageView&noscript=1' /></noscript>
        <!-- End Meta Pixel Code -->
        ";
    }

    private function get_advanced_matching_data() {
        $data = [];

        // Logged-in user
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if ($user->user_email) {
                $data['em'] = $user->user_email;
            }
            if ($user->first_name) {
                $data['fn'] = strtolower($user->first_name);
            }
            if ($user->last_name) {
                $data['ln'] = strtolower($user->last_name);
            }
        }

        // WooCommerce customer data
        if (function_exists('WC') && WC()->customer) {
            $customer = WC()->customer;

            if (empty($data['em']) && $customer->get_billing_email()) {
                $data['em'] = $customer->get_billing_email();
            }
            if (empty($data['fn']) && $customer->get_billing_first_name()) {
                $data['fn'] = strtolower($customer->get_billing_first_name());
            }
            if (empty($data['ln']) && $customer->get_billing_last_name()) {
                $data['ln'] = strtolower($customer->get_billing_last_name());
            }
        }

        return $data;
    }

    public function get_browser_event_mapper() {
        return "function(trackSureEvent) {
            var eventMap = {
                'page_view': 'PageView',
                'add_to_cart': 'AddToCart',
                'begin_checkout': 'InitiateCheckout',
                'purchase': 'Purchase',
                'view_item': 'ViewContent',
                'add_payment_info': 'AddPaymentInfo',
                'form_submit': 'Lead'
            };

            var metaEventName = eventMap[trackSureEvent.event_name];
            if (!metaEventName) {
                return null;
            }

            var metaParams = {};
            var params = trackSureEvent.event_params || {};

            // Value
            if (params.value) metaParams.value = parseFloat(params.value);

            // Currency
            if (params.currency) metaParams.currency = String(params.currency).toUpperCase();

            // Content IDs
            if (params.items && params.items.length > 0) {
                metaParams.content_ids = params.items.map(function(item) {
                    return String(item.item_id || '');
                });

                metaParams.contents = params.items.map(function(item) {
                    return {
                        id: String(item.item_id),
                        quantity: parseInt(item.quantity) || 1,
                        item_price: parseFloat(item.price) || 0
                    };
                });
            }

            // Event ID for deduplication
            var eventId = trackSureEvent.event_id || null;

            return {
                name: metaEventName,
                params: metaParams,
                eventId: eventId
            };
        }";
    }

    public function send($result, $destination, $mapped_event) {
        if ($destination !== 'meta') {
            return $result;
        }

        // Skip if CAPI not configured
        if (empty($this->settings['access_token'])) {
            return [
                'success' => true,
                'error' => 'Meta CAPI access token not configured',
            ];
        }

        // Enrich user data
        $user_data = $this->enrich_user_data($mapped_event['user_data']);

        // Build payload
        $payload = [
            'data' => [
                [
                    'event_name' => $mapped_event['event_name'],
                    'event_time' => $mapped_event['event_time'],
                    'event_id' => $mapped_event['event_id'],
                    'event_source_url' => $mapped_event['event_source_url'] ?? home_url(),
                    'action_source' => 'website',
                    'user_data' => $user_data,
                    'custom_data' => $mapped_event['custom_data'],
                ],
            ],
        ];

        // Add Data Processing Options (LDU)
        $consent_manager = $this->core->get_service('consent_manager');
        $consent_granted = $consent_manager ? $consent_manager->is_tracking_allowed() : true;

        if (!$consent_granted) {
            $payload['data'][0]['data_processing_options'] = ['LDU'];
            $payload['data'][0]['data_processing_options_country'] = 0;
            $payload['data'][0]['data_processing_options_state'] = 0;
        } else {
            $payload['data'][0]['data_processing_options'] = [];
        }

        // Test event code
        if (!empty($this->settings['test_event_code'])) {
            $payload['test_event_code'] = $this->settings['test_event_code'];
        }

        // Send to CAPI
        $response = $this->send_capi_request($payload);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'Unknown Meta CAPI error',
            ];
        }

        return ['success' => true];
    }

    private function enrich_user_data($user_data) {
        $enriched = [];

        // Copy technical fields
        if (!empty($user_data['fbp'])) {
            $enriched['fbp'] = $user_data['fbp'];
        }
        if (!empty($user_data['fbc'])) {
            $enriched['fbc'] = $user_data['fbc'];
        }
        if (!empty($user_data['client_ip_address'])) {
            $enriched['client_ip_address'] = $user_data['client_ip_address'];
        }
        if (!empty($user_data['client_user_agent'])) {
            $enriched['client_user_agent'] = $user_data['client_user_agent'];
        }

        // Hash PII fields
        $hash_fields = [
            'email' => 'em',
            'phone' => 'ph',
            'first_name' => 'fn',
            'last_name' => 'ln',
            'city' => 'ct',
            'state' => 'st',
            'zip' => 'zp',
            'country' => 'country',
        ];

        foreach ($hash_fields as $field => $meta_field) {
            if (!empty($user_data[$field])) {
                $value = strtolower(trim($user_data[$field]));
                $enriched[$meta_field] = hash('sha256', $value);
            }
        }

        return $enriched;
    }

    private function send_capi_request($payload) {
        $pixel_id = $this->settings['pixel_id'];
        $access_token = $this->settings['access_token'];

        $url = "https://graph.facebook.com/v18.0/{$pixel_id}/events";

        return wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
            'sslverify' => true,
            'data_format' => 'body',
        ] + ['query' => ['access_token' => $access_token]]);
    }
}
```

---

### **Example 2: Google Analytics 4 Destination**

```php
<?php

class TrackSure_GA4_Destination {

    private $core;
    private $settings;

    public function __construct($core) {
        $this->core = $core;
        $this->settings = $this->get_settings();

        if ($this->is_enabled()) {
            $this->init_hooks();
        }
    }

    private function get_settings() {
        return [
            'enabled' => get_option('tracksure_free_ga4_enabled', false),
            'measurement_id' => get_option('tracksure_free_ga4_measurement_id', ''),
            'api_secret' => get_option('tracksure_free_ga4_api_secret', ''),
        ];
    }

    private function is_enabled() {
        return !empty($this->settings['enabled']) &&
               !empty($this->settings['measurement_id']);
    }

    private function init_hooks() {
        $this->register_browser_destination();
        add_filter('tracksure_deliver_mapped_event', [$this, 'send'], 10, 3);
    }

    private function register_browser_destination() {
        $bridge = $this->core->get_service('event_bridge');

        if (!$bridge) {
            return;
        }

        $bridge->register_browser_destination([
            'id' => 'ga4',
            'enabled_key' => 'tracksure_free_ga4_enabled',
            'init_script' => [$this, 'get_gtag_init_script'],
            'event_mapper' => [$this, 'get_browser_event_mapper'],
        ]);
    }

    public function get_gtag_init_script() {
        $measurement_id = sanitize_text_field($this->settings['measurement_id']);

        return "
        <!-- Google tag (gtag.js) -->
        <script async src='https://www.googletagmanager.com/gtag/js?id={$measurement_id}'></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '{$measurement_id}');
        </script>
        ";
    }

    public function get_browser_event_mapper() {
        return "function(trackSureEvent) {
            // GA4 uses same event names as TrackSure (mostly)
            var eventMap = {
                'page_view': 'page_view',
                'add_to_cart': 'add_to_cart',
                'begin_checkout': 'begin_checkout',
                'purchase': 'purchase',
                'view_item': 'view_item',
                'add_payment_info': 'add_payment_info',
                'form_submit': 'generate_lead'
            };

            var ga4EventName = eventMap[trackSureEvent.event_name];
            if (!ga4EventName) {
                return null;
            }

            var ga4Params = {};
            var params = trackSureEvent.event_params || {};

            // GA4 ecommerce parameters
            if (params.currency) ga4Params.currency = params.currency;
            if (params.value) ga4Params.value = parseFloat(params.value);
            if (params.transaction_id) ga4Params.transaction_id = params.transaction_id;
            if (params.tax) ga4Params.tax = parseFloat(params.tax);
            if (params.shipping) ga4Params.shipping = parseFloat(params.shipping);

            // Items array (GA4 native format)
            if (params.items) ga4Params.items = params.items;

            return {
                name: ga4EventName,
                params: ga4Params
            };
        }";
    }

    public function send($result, $destination, $mapped_event) {
        if ($destination !== 'ga4') {
            return $result;
        }

        // Skip if Measurement Protocol not configured
        if (empty($this->settings['api_secret'])) {
            return [
                'success' => true,
                'error' => 'GA4 API secret not configured',
            ];
        }

        // Build payload
        $payload = [
            'client_id' => $mapped_event['user_data']['client_id'] ?? wp_generate_uuid4(),
            'events' => [
                [
                    'name' => $mapped_event['event_name'],
                    'params' => $mapped_event['custom_data'],
                ],
            ],
        ];

        // Add user_id if available
        if (!empty($mapped_event['user_data']['user_id'])) {
            $payload['user_id'] = (string) $mapped_event['user_data']['user_id'];
        }

        // Send to Measurement Protocol
        $response = $this->send_measurement_protocol_request($payload);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        // GA4 Measurement Protocol returns 204 on success
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 204) {
            return [
                'success' => false,
                'error' => 'GA4 Measurement Protocol returned status ' . $code,
            ];
        }

        return ['success' => true];
    }

    private function send_measurement_protocol_request($payload) {
        $measurement_id = $this->settings['measurement_id'];
        $api_secret = $this->settings['api_secret'];

        $url = "https://www.google-analytics.com/mp/collect?measurement_id={$measurement_id}&api_secret={$api_secret}";

        return wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ]);
    }
}
```

---

## 📖 **See Also**

- **[MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md)** - Creating TrackSure modules
- **[ADAPTER_DEVELOPMENT.md](ADAPTER_DEVELOPMENT.md)** - Creating ecommerce adapters
- **[HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md)** - Available hooks
- **[CLASS_REFERENCE.md](CLASS_REFERENCE.md)** - Core classes reference
- **[EVENT_FLOW.md](EVENT_FLOW.md)** - Event processing flow

---

**Need Help?** Check the existing destinations in `includes/free/destinations/` for reference implementations!
