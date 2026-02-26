# Plugin API

> **Scope**: How other plugins, themes, and custom code can interact with TrackSure programmatically — PHP API, Consent API, Browser SDK API, and common integration patterns.

---

## Table of Contents

1. [Overview](#-overview)
2. [PHP API — Service Container](#-php-api--service-container)
3. [Recording Events from PHP](#-recording-events-from-php)
4. [Consent API (Public Functions)](#-consent-api-public-functions)
5. [Registry API](#-registry-api)
6. [Module & Capability API](#-module--capability-api)
7. [Browser SDK API](#-browser-sdk-api)
8. [Browser SDK Configuration](#-browser-sdk-configuration)
9. [Common PHP Recipes](#-common-php-recipes)
10. [Common JS Recipes](#-common-js-recipes)
11. [See Also](#-see-also)

---

## 🎯 **Overview**

TrackSure exposes three integration surfaces:

| Surface         | Access                                             | Use Case                                                     |
| --------------- | -------------------------------------------------- | ------------------------------------------------------------ |
| **PHP API**     | `TrackSure_Core::get_instance()->get_service(...)` | Server-side event recording, consent checks, registry access |
| **Consent API** | 5 global PHP functions                             | Register custom consent plugins, check consent status        |
| **Browser SDK** | `window.TrackSure.track(...)`                      | Client-side custom event tracking, client/session IDs        |

For REST API endpoints, see [REST_API_REFERENCE.md](REST_API_REFERENCE.md).
For WordPress hooks, see [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md).

---

## 🔌 **PHP API — Service Container**

`TrackSure_Core` is a **service container**. All functionality is accessed through named services:

```php
$core = TrackSure_Core::get_instance();
$service = $core->get_service('service_name');
```

### Available Services

| Service Key             | Class                             | Description                               |
| ----------------------- | --------------------------------- | ----------------------------------------- |
| `logger`                | `TrackSure_Logger`                | Debug logging to `tracksure_logs` table   |
| `db`                    | `TrackSure_DB`                    | Database layer (CRUD for all 15 tables)   |
| `registry`              | `TrackSure_Registry`              | Event & parameter definitions             |
| `rate_limiter`          | `TrackSure_Rate_Limiter`          | Rate limiting                             |
| `url_normalizer`        | `TrackSure_URL_Normalizer`        | URL cleanup & normalization               |
| `session_manager`       | `TrackSure_Session_Manager`       | Session & visitor tracking                |
| `attribution`           | `TrackSure_Attribution_Resolver`  | Attribution model resolution              |
| `journey`               | `TrackSure_Journey_Engine`        | Customer journey tracking                 |
| `attribution_analytics` | `TrackSure_Attribution_Analytics` | Attribution analytics queries             |
| `event_builder`         | `TrackSure_Event_Builder`         | Event construction                        |
| `event_mapper`          | `TrackSure_Event_Mapper`          | Destination format mapping                |
| `event_recorder`        | `TrackSure_Event_Recorder`        | Event validation, enrichment, persistence |
| `consent_manager`       | `TrackSure_Consent_Manager`       | GDPR/CCPA consent management              |
| `geolocation`           | `TrackSure_Geolocation`           | IP → country/region/city                  |
| `rest_api`              | `TrackSure_REST_API`              | REST API manager                          |
| `module_registry`       | `TrackSure_Module_Registry`       | Module registration & loading             |
| `attribution_hooks`     | `TrackSure_Attribution_Hooks`     | Attribution hook coordination             |
| `event_bridge`          | `TrackSure_Event_Bridge`          | Browser ↔ server coordination             |
| `destinations_manager`  | `TrackSure_Destinations_Manager`  | Ad platform destination management        |
| `integrations_manager`  | `TrackSure_Integrations_Manager`  | Ecommerce integration management          |
| `delivery_worker`       | `TrackSure_Delivery_Worker`       | Outbox processing & HTTP delivery         |
| `cleanup_worker`        | `TrackSure_Cleanup_Worker`        | Old data cleanup                          |

**Admin-only services** (available when `is_admin()` is true):

| Service Key        | Class                        |
| ------------------ | ---------------------------- |
| `admin_ui`         | `TrackSure_Admin_UI`         |
| `admin_extensions` | `TrackSure_Admin_Extensions` |

**Frontend-only service** (available when `!is_admin()` is true):

| Service Key      | Class                      |
| ---------------- | -------------------------- |
| `tracker_assets` | `TrackSure_Tracker_Assets` |

### Core Lifecycle Methods

```php
$core = TrackSure_Core::get_instance();

// Check if core has finished booting
if ($core->is_loaded()) {
    // Safe to use services
}

// Get all registered modules
$modules = $core->get_modules();
// Returns: ['tracksure-free' => ['id' => ..., 'path' => ..., 'config' => ..., 'loaded' => true]]
```

**Important**: Always check `is_loaded()` or hook into `tracksure_core_booted` before accessing services:

```php
add_action('tracksure_core_booted', function($core) {
    $recorder = $core->get_service('event_recorder');
    // Safe to use
});
```

---

## 📼 **Recording Events from PHP**

The most common integration task — record a custom event from your plugin or theme:

```php
add_action('tracksure_core_booted', function($core) {
    // 1. Get the services
    $event_builder  = $core->get_service('event_builder');
    $event_recorder = $core->get_service('event_recorder');

    // 2. Build the event
    $event = $event_builder->build_event('purchase', [
        'transaction_id' => 'ORD-12345',
        'value'          => 99.99,
        'currency'       => 'USD',
        'items'          => [
            ['item_id' => 'PROD-1', 'item_name' => 'Widget', 'price' => 49.99, 'quantity' => 2]
        ],
    ], [
        'event_source' => 'server',
        'user_data'    => [
            'email'      => 'customer@example.com',
            'first_name' => 'Jane',
        ],
    ]);

    // 3. Record it (validates, deduplicates, enriches, queues)
    if ($event) {
        $result = $event_recorder->record($event);
        // $result = ['success' => true, 'event_id' => 'uuid-v4', 'errors' => []]
    }
});
```

### `build_event()` Reference

```php
$event = $event_builder->build_event(
    string $event_name,   // Registry event name (e.g., 'purchase', 'page_view')
    array  $params = [],  // Event parameters (validated against registry)
    array  $context = []  // Optional overrides: event_id, client_id, session_id, event_source, user_data, etc.
);
// Returns: array (complete event) or false (validation failure)
```

### `record()` Reference

```php
$result = $event_recorder->record(array $event_data);
// Returns:
// ['success' => true,  'event_id' => 'uuid', 'errors' => []]
// ['success' => false, 'event_id' => null,    'errors' => ['Missing required field: event_name']]
// ['success' => true,  'duplicate' => true, 'merged' => true]  // Dedup merge
```

See [EVENT_SYSTEM.md](EVENT_SYSTEM.md) for the full pipeline details.

---

## 🛡️ **Consent API (Public Functions)**

**File:** `includes/core/api/tracksure-consent-api.php`

Five global functions available after plugin loads. No class instantiation needed.

### `tracksure_register_consent_plugin()`

Register a custom consent management plugin with TrackSure.

```php
/**
 * @param string   $plugin_id  Unique identifier (e.g., 'my_cookie_plugin')
 * @param callable $callback   Must return true if tracking consent is granted
 * @return bool    Whether registration succeeded
 */
tracksure_register_consent_plugin(string $plugin_id, callable $callback): bool
```

**Example:**

```php
add_action('init', function() {
    tracksure_register_consent_plugin('my_consent_plugin', function() {
        // Check your cookie/option/etc.
        return isset($_COOKIE['my_consent']) && $_COOKIE['my_consent'] === 'granted';
    });
});
```

### `tracksure_is_tracking_allowed()`

Check if the current visitor has granted tracking consent.

```php
/**
 * @return bool  true if tracking is allowed
 */
tracksure_is_tracking_allowed(): bool
```

**Example:**

```php
if (tracksure_is_tracking_allowed()) {
    // Full tracking
} else {
    // Anonymized tracking (TrackSure handles this automatically)
}
```

### `tracksure_get_consent_mode()`

Get the current consent mode configuration.

```php
/**
 * @return string  'disabled' | 'opt-in' | 'opt-out' | 'auto'
 */
tracksure_get_consent_mode(): string
```

| Mode       | Behavior                                 |
| ---------- | ---------------------------------------- |
| `disabled` | No consent management — track everything |
| `opt-in`   | Block tracking until explicit consent    |
| `opt-out`  | Track by default, stop on opt-out        |
| `auto`     | Auto-detect consent plugin behavior      |

### `tracksure_has_consent_plugin()`

Check if a consent management platform (CMP) is detected.

```php
/**
 * @return bool  true if a CMP is registered/detected
 */
tracksure_has_consent_plugin(): bool
```

### `tracksure_get_consent_warning_status()`

Get warning data for the admin UI when consent mode is active but no CMP is detected.

```php
/**
 * @return array|null  Warning data array, or null if no warning needed
 */
tracksure_get_consent_warning_status(): array|null
```

---

## 📋 **Registry API**

Access the event and parameter registry:

```php
$core     = TrackSure_Core::get_instance();
$registry = $core->get_service('registry');

// Browse events
$all_events    = $registry->get_events();
$purchase      = $registry->get_event('purchase');
$ecom_events   = $registry->get_events_by_category('ecommerce');
$auto_events   = $registry->get_auto_events();  // automatically_collected = true

// Browse parameters
$all_params    = $registry->get_parameters();
$value_param   = $registry->get_parameter('value');

// Check existence
$registry->event_exists('purchase');       // true
$registry->parameter_exists('value');      // true

// Validate event data
$result = $registry->validate_event('purchase', [
    'transaction_id' => 'WC-123',
    'value'          => 99.99,
    'currency'       => 'USD',
]);
// ['valid' => true, 'errors' => []]

// Register custom event (see CUSTOM_EVENTS.md for full guide)
$registry->register_event([
    'name'         => 'webinar_signup',
    'display_name' => 'Webinar Signup',
    'category'     => 'engagement',
]);

// Registry stats
$stats = $registry->get_stats();
// ['total_events' => 25, 'auto_events' => 12, 'total_parameters' => 40, 'event_categories' => [...]]
```

---

## 📦 **Module & Capability API**

Register modules and capabilities from your plugin:

```php
$core = TrackSure_Core::get_instance();

// Register a module pack
$core->register_module('my-addon', __DIR__, [
    'name'    => 'My Addon',
    'version' => '1.0.0',
]);

// Register capabilities
$core->register_capability('destinations', 'tiktok', [
    'name'    => 'TikTok Pixel',
    'enabled' => true,
]);

$core->register_capability('integrations', 'edd', [
    'name'    => 'Easy Digital Downloads',
    'enabled' => true,
]);

// Query capabilities
$destinations  = $core->get_capabilities('destinations');
$integrations  = $core->get_capabilities('integrations');
$all_modules   = $core->get_modules();
```

**Capability types**: `dashboards`, `destinations`, `integrations`, `features`

See [MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md) for the full module creation guide.

---

## 🌐 **Browser SDK API**

**File:** `assets/js/ts-web.js` — exposed on `window.TrackSure`

### `TrackSure.track(eventName, eventParams?)`

Track a custom event from JavaScript.

```javascript
// Basic tracking
TrackSure.track("button_click", {
  button_id: "cta-hero",
  button_text: "Get Started",
});

// E-commerce tracking
TrackSure.track("add_to_cart", {
  item_id: "PROD-123",
  item_name: "Premium Widget",
  price: 49.99,
  quantity: 1,
  currency: "USD",
});
```

**Behavior:**

1. Validates against registry (if loaded) — graceful degradation if not
2. Generates deterministic `event_id` (same algorithm as PHP)
3. Immediately fires browser pixels via `sendToPixels()`
4. Queues for batch delivery to server (`POST /ts/v1/collect`)

### `TrackSure.sendBatch()`

Flush the event queue to the server immediately.

```javascript
// Force send all queued events
TrackSure.sendBatch();
```

Uses `navigator.sendBeacon()` with fallback to `fetch({ keepalive: true })`.

### `TrackSure.getClientId()`

Get the persistent client UUID.

```javascript
const clientId = TrackSure.getClientId();
// "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
```

**Storage priority:** localStorage (`_ts_cid`) → cookie → generate new.
Cookie: 400-day max-age, `SameSite=Lax`.

### `TrackSure.getSessionId()`

Get the current session UUID.

```javascript
const sessionId = TrackSure.getSessionId();
// "11223344-5566-7788-9900-aabbccddeeff"
```

**Storage priority:** sessionStorage (`_ts_sid`) → cookie → generate new.
Expires after 30 minutes of inactivity (configurable via `sessionTimeout`).

### `TrackSure.generateUUID()`

Generate a UUID v4.

```javascript
const uuid = TrackSure.generateUUID();
```

Uses `crypto.randomUUID()` → `crypto.getRandomValues()` → `Math.random()` fallback.

### `TrackSure.getRegistry()`

Get the loaded event registry.

```javascript
const registry = TrackSure.getRegistry();
// Object with events and parameters, or null if not yet loaded
```

### `TrackSure.validateEvent(eventName, eventParams)`

Client-side event validation against the registry.

```javascript
const result = TrackSure.validateEvent("purchase", {
  transaction_id: "WC-123",
  value: 99.99,
  currency: "USD",
});
// { valid: true, errors: [] }

// If registry not loaded yet → graceful degradation:
// { valid: true, errors: [] }
```

### Event Bridge Properties

Set by the Event Bridge after page load (see [EVENT_SYSTEM.md](EVENT_SYSTEM.md)):

| Property                        | Type       | Description                                                 |
| ------------------------------- | ---------- | ----------------------------------------------------------- |
| `TrackSure.pixelMappers`        | `object`   | `{destId: mapperFn}` — convert events to destination format |
| `TrackSure.sdkChecks`           | `object`   | `{destId: checkFn}` — detect if SDK is loaded               |
| `TrackSure.pixelSenders`        | `object`   | `{destId: senderFn}` — send to SDK                          |
| `TrackSure.sendToPixels(event)` | `function` | Route event to all active browser pixels                    |
| `TrackSure.testPixels()`        | `function` | Send test event to all pixels                               |

---

## ⚙️ **Browser SDK Configuration**

Configured via `window.trackSureConfig` (set by `wp_localize_script` in PHP):

| Key                | Type   | Default                   | Description                  |
| ------------------ | ------ | ------------------------- | ---------------------------- |
| `endpoint`         | string | `/wp-json/ts/v1/collect`  | REST API endpoint            |
| `trackingEnabled`  | bool   | `true`                    | Master kill switch           |
| `sessionTimeout`   | int    | `30`                      | Inactivity timeout (minutes) |
| `batchSize`        | int    | `10`                      | Events per batch request     |
| `batchTimeout`     | int    | `2000`                    | Auto-send delay (ms)         |
| `respectDNT`       | bool   | —                         | Honor Do Not Track header    |
| `registryEndpoint` | string | `/wp-json/ts/v1/registry` | Registry API URL             |
| `debug`            | bool   | —                         | Enable console logging       |
| `user`             | object | —                         | Logged-in user data from PHP |

---

## 🍳 **Common PHP Recipes**

### Record a Custom Event

```php
add_action('tracksure_core_booted', function($core) {
    $builder  = $core->get_service('event_builder');
    $recorder = $core->get_service('event_recorder');

    $event = $builder->build_event('form_submit', [
        'form_id'   => 'contact-form-1',
        'form_name' => 'Contact Us',
    ]);

    if ($event) {
        $recorder->record($event);
    }
});
```

### Check Consent Before Custom Logic

```php
if (tracksure_is_tracking_allowed()) {
    // User has consented — run full personalization
    show_personalized_recommendations();
} else {
    // No consent — show generic content
    show_generic_recommendations();
}
```

### Register a Consent Plugin

```php
add_action('init', function() {
    tracksure_register_consent_plugin('cookiebot', function() {
        return isset($_COOKIE['CookieConsent'])
            && strpos($_COOKIE['CookieConsent'], 'statistics:true') !== false;
    });
});
```

### Access Event Data from the Database

```php
add_action('tracksure_core_booted', function($core) {
    $db = $core->get_service('db');

    // Get recent events for a visitor
    $events = $db->get_events_by_visitor($visitor_id, [
        'limit'    => 50,
        'order_by' => 'created_at DESC',
    ]);

    // Get session data
    $session = $db->get_session($session_id);
});
```

### Get Attribution Data

```php
add_action('tracksure_core_booted', function($core) {
    $attribution = $core->get_service('attribution');

    // Resolve attribution for a conversion
    $touchpoints = $attribution->get_touchpoints($visitor_id);
    $model_result = $attribution->resolve($visitor_id, 'last_click');
});
```

### Extend Settings Schema

```php
add_filter('tracksure_settings_schema', function($schema) {
    $schema['my_custom_setting'] = [
        'type'    => 'string',
        'default' => 'hello',
        'label'   => 'My Custom Setting',
    ];
    return $schema;
});
```

---

## 🍳 **Common JS Recipes**

### Track a Custom Button Click

```javascript
document.querySelector("#my-cta").addEventListener("click", function () {
  TrackSure.track("button_click", {
    button_id: "my-cta",
    button_text: this.textContent,
    page_url: window.location.href,
  });
});
```

### Track a Form Submission

```javascript
document.querySelector("#signup-form").addEventListener("submit", function (e) {
  TrackSure.track("form_submit", {
    form_id: "signup-form",
    form_name: "Newsletter Signup",
  });
  // sendBatch() ensures the event is sent before navigation
  TrackSure.sendBatch();
});
```

### Get Client/Session IDs for External Use

```javascript
// Pass to your own analytics or backend
const payload = {
  tracksure_client_id: TrackSure.getClientId(),
  tracksure_session_id: TrackSure.getSessionId(),
  // ...your data
};
```

### Validate Before Tracking

```javascript
const validation = TrackSure.validateEvent("purchase", {
  transaction_id: "WC-123",
  value: 99.99,
  currency: "USD",
});

if (validation.valid) {
  TrackSure.track("purchase", {
    transaction_id: "WC-123",
    value: 99.99,
    currency: "USD",
  });
} else {
  console.warn("Invalid event:", validation.errors);
}
```

### Check if a Destination Pixel is Active

```javascript
// After page load, check if Meta Pixel is active
if (TrackSure.sdkChecks && TrackSure.sdkChecks["meta-pixel"]) {
  const isLoaded = TrackSure.sdkChecks["meta-pixel"]();
  console.log("Meta Pixel loaded:", isLoaded);
}
```

---

## 📚 **See Also**

- [EVENT_SYSTEM.md](EVENT_SYSTEM.md) — Full event pipeline architecture
- [CUSTOM_EVENTS.md](CUSTOM_EVENTS.md) — Creating and tracking custom events
- [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md) — All 80+ WordPress hooks
- [REST_API_REFERENCE.md](REST_API_REFERENCE.md) — All 55+ REST API endpoints
- [FRONTEND_SDK.md](FRONTEND_SDK.md) — Browser SDK auto-tracking features
- [MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md) — Building module packs
- [REACT-CONSENT-API-INTEGRATION.md](REACT-CONSENT-API-INTEGRATION.md) — React admin consent UI

---

**Last Updated**: February 26, 2026
**Version**: 1.0.0
