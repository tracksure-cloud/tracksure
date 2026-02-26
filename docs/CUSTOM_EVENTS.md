# Custom Events

> **Scope**: How to define, register, validate, and track custom events from both PHP and JavaScript — including destination mappings so custom events are delivered to Meta, GA4, and other platforms.

---

## Table of Contents

1. [Overview](#-overview)
2. [Registering Custom Events (PHP)](#-registering-custom-events-php)
3. [Registering Custom Parameters (PHP)](#-registering-custom-parameters-php)
4. [Registering via Filters](#-registering-via-filters)
5. [Tracking from PHP](#-tracking-from-php)
6. [Tracking from JavaScript](#-tracking-from-javascript)
7. [Validation](#-validation)
8. [Destination Mappings](#-destination-mappings)
9. [Events.json Schema Reference](#-eventsjson-schema-reference)
10. [End-to-End Tutorial](#-end-to-end-tutorial)
11. [See Also](#-see-also)

---

## 🎯 **Overview**

TrackSure ships with built-in events (page_view, purchase, add_to_cart, etc.) defined in `registry/events.json`. You can add **custom events** for any user action your business needs to track.

**Three ways to register custom events:**

| Method                           | When to Use                           | Persistence                       |
| -------------------------------- | ------------------------------------- | --------------------------------- |
| `Registry::register_event()`     | Direct PHP call                       | Runtime only (until cache clears) |
| `tracksure_loaded_events` filter | From a module pack or plugin          | Runtime only                      |
| Edit `events.json`               | Core events that ship with the plugin | Permanent (in JSON file)          |

**Two ways to track custom events:**

| Surface        | Method                           | Example                                                    |
| -------------- | -------------------------------- | ---------------------------------------------------------- |
| **PHP**        | `Event Builder → Event Recorder` | Server-side form submissions, API callbacks                |
| **JavaScript** | `TrackSure.track()`              | Button clicks, scroll milestones, client-side interactions |

---

## 📝 **Registering Custom Events (PHP)**

Use `TrackSure_Registry::register_event()` to add a custom event at runtime:

```php
add_action('tracksure_core_booted', function($core) {
    $registry = $core->get_service('registry');

    $registry->register_event([
        'name'                   => 'webinar_signup',
        'display_name'           => 'Webinar Signup',
        'category'               => 'engagement',
        'description'            => 'Fired when a user signs up for a webinar',
        'automatically_collected' => false,
        'required_params'        => ['webinar_id', 'webinar_name'],
        'optional_params'        => ['scheduled_date', 'email'],
    ]);
});
```

### `register_event()` Fields

| Field                     | Required | Type   | Default | Description                                                         |
| ------------------------- | -------- | ------ | ------- | ------------------------------------------------------------------- |
| `name`                    | **Yes**  | string | —       | Internal event name (lowercase, underscored)                        |
| `display_name`            | **Yes**  | string | —       | Human-readable label for admin UI                                   |
| `category`                | **Yes**  | string | —       | Grouping: `"engagement"`, `"ecommerce"`, `"form"`, `"custom"`, etc. |
| `description`             | No       | string | `""`    | What triggers this event                                            |
| `automatically_collected` | No       | bool   | `false` | If `true`, the SDK tracks it without manual code                    |
| `required_params`         | No       | array  | `[]`    | Parameter names that must be present                                |
| `optional_params`         | No       | array  | `[]`    | Additional supported parameters                                     |

**Returns:** `true` on success, `false` if `name` or `display_name` or `category` is missing.

**Side effects:**

- Fires `tracksure_event_registered` action
- Invalidates registry cache (next request reloads fresh)

---

## 📝 **Registering Custom Parameters (PHP)**

If your custom event uses non-standard parameters, register them too:

```php
add_action('tracksure_core_booted', function($core) {
    $registry = $core->get_service('registry');

    $registry->register_parameter([
        'name'         => 'webinar_id',
        'display_name' => 'Webinar ID',
        'type'         => 'string',
        'description'  => 'Unique identifier for the webinar',
        'example'      => 'WEB-2026-001',
    ]);

    $registry->register_parameter([
        'name'         => 'webinar_name',
        'display_name' => 'Webinar Name',
        'type'         => 'string',
        'description'  => 'Title of the webinar',
        'example'      => 'How to Grow Your Business',
    ]);

    $registry->register_parameter([
        'name'         => 'scheduled_date',
        'display_name' => 'Scheduled Date',
        'type'         => 'string',
        'description'  => 'Date of the webinar',
        'example'      => '2026-03-15',
    ]);
});
```

### `register_parameter()` Fields

| Field          | Required | Type   | Default | Description                                                 |
| -------------- | -------- | ------ | ------- | ----------------------------------------------------------- |
| `name`         | **Yes**  | string | —       | Internal parameter name                                     |
| `display_name` | **Yes**  | string | —       | Human-readable label                                        |
| `type`         | **Yes**  | string | —       | `"string"`, `"number"`, `"integer"`, `"boolean"`, `"array"` |
| `description`  | No       | string | `""`    | What this parameter represents                              |
| `example`      | No       | mixed  | `null`  | Example value for documentation                             |

**Side effects:** Fires `tracksure_parameter_registered` action.

---

## 🔗 **Registering via Filters**

Alternative approach using WordPress filters. Ideal for module packs:

```php
// Add custom events
add_filter('tracksure_loaded_events', function($events) {
    $events[] = [
        'name'            => 'webinar_signup',
        'display_name'    => 'Webinar Signup',
        'category'        => 'engagement',
        'description'     => 'Fired when a user signs up for a webinar',
        'required_params' => ['webinar_id', 'webinar_name'],
        'optional_params' => ['scheduled_date'],
    ];
    return $events;
});

// Add custom parameters
add_filter('tracksure_loaded_parameters', function($params) {
    $params[] = [
        'name'         => 'webinar_id',
        'display_name' => 'Webinar ID',
        'type'         => 'string',
    ];
    $params[] = [
        'name'         => 'webinar_name',
        'display_name' => 'Webinar Name',
        'type'         => 'string',
    ];
    return $params;
});
```

**Difference from `register_event()`:**

- Filter runs during JSON loading (before cache is written)
- `register_event()` adds to already-loaded registry and invalidates cache

Both approaches are valid. The filter is better for module packs, `register_event()` is better for dynamic runtime registration.

---

## 🖥️ **Tracking from PHP**

Use Event Builder + Event Recorder to track custom events server-side:

```php
add_action('tracksure_core_booted', function($core) {
    $builder  = $core->get_service('event_builder');
    $recorder = $core->get_service('event_recorder');

    // Build the event
    $event = $builder->build_event('webinar_signup', [
        'webinar_id'     => 'WEB-2026-001',
        'webinar_name'   => 'How to Grow Your Business',
        'scheduled_date' => '2026-03-15',
    ], [
        'event_source' => 'server',
        'user_data'    => [
            'email'      => 'user@example.com',
            'first_name' => 'Jane',
        ],
    ]);

    // Record it
    if ($event) {
        $result = $recorder->record($event);

        if ($result['success']) {
            // Event recorded: $result['event_id']
        } else {
            // Validation failed: $result['errors']
        }
    }
});
```

### What `build_event()` does automatically:

- Validates `event_name` exists in registry
- Generates deterministic `event_id`
- Adds all temporal parameters (date, hour, quarter, etc.)
- Collects user data from WordPress if not provided
- Populates page context

### What `record()` does automatically:

- Validates required fields + UUID format
- Rate limiting + bot detection
- Deduplicates against existing events (deterministic ID)
- Enriches with geolocation, device detection, URL normalization
- Queues to database (batched INSERT)
- Queues to outbox for destination delivery
- Evaluates goals

---

## 🌐 **Tracking from JavaScript**

Use `TrackSure.track()` in the browser:

```javascript
// Simple custom event
TrackSure.track("webinar_signup", {
  webinar_id: "WEB-2026-001",
  webinar_name: "How to Grow Your Business",
  scheduled_date: "2026-03-15",
});
```

### What `track()` does automatically:

1. **Validates** against registry (if loaded, otherwise tracks anyway — graceful degradation)
2. **Generates** deterministic `event_id` (same algorithm as PHP)
3. **Fires browser pixels** immediately via `TrackSure.sendToPixels()`
4. **Queues** for batch delivery to server (`POST /ts/v1/collect`)
5. **Auto-sends** when batch is full or after `batchTimeout` (2 seconds default)

### Tracking on Page Navigation

When tracking before a link click or form submit, force-flush the queue:

```javascript
document.querySelector("#signup-form").addEventListener("submit", function () {
  TrackSure.track("webinar_signup", {
    webinar_id: "WEB-2026-001",
    webinar_name: "How to Grow Your Business",
  });
  TrackSure.sendBatch(); // Ensure delivery before navigation
});
```

### Conditional Tracking

```javascript
// Only track if user is on a specific page
if (window.location.pathname.includes("/webinars/")) {
  document.querySelector(".signup-btn").addEventListener("click", function () {
    TrackSure.track("webinar_signup", {
      webinar_id: this.dataset.webinarId,
      webinar_name: this.dataset.webinarName,
    });
  });
}
```

---

## ✅ **Validation**

Custom events are validated on both client and server.

### Server-Side Validation (PHP)

The Event Recorder runs registry validation:

```php
$result = $registry->validate_event('webinar_signup', [
    'webinar_id'   => 'WEB-2026-001',
    'webinar_name' => 'How to Grow Your Business',
]);
// ['valid' => true, 'errors' => []]

// Missing required param:
$result = $registry->validate_event('webinar_signup', [
    'webinar_id' => 'WEB-2026-001',
    // Missing webinar_name!
]);
// ['valid' => false, 'errors' => ['Missing required parameter: webinar_name']]
```

**Validation checks:**

1. Event exists in registry
2. All `required_params` are present
3. Parameter types match schema definition (`number` → `is_numeric()`, `integer` → `is_int()`, etc.)
4. Unknown parameters are **allowed** (forward-compatible)

**Override:** `apply_filters('tracksure_validate_event', $result, $event_name, $event_params)`

### Client-Side Validation (JavaScript)

```javascript
const result = TrackSure.validateEvent("webinar_signup", {
  webinar_id: "WEB-2026-001",
  webinar_name: "How to Grow Your Business",
});
// { valid: true, errors: [] }
```

**Important:** If the registry hasn't loaded yet (async fetch), `validateEvent()` returns `{ valid: true, errors: [] }` — graceful degradation ensures events are never lost.

---

## 🗺️ **Destination Mappings**

To send custom events to ad platforms (Meta CAPI, GA4 Measurement Protocol, etc.), add `destination_mappings`.

### Option 1: Via the `tracksure_loaded_events` Filter

```php
add_filter('tracksure_loaded_events', function($events) {
    $events[] = [
        'name'            => 'webinar_signup',
        'display_name'    => 'Webinar Signup',
        'category'        => 'engagement',
        'required_params' => ['webinar_id', 'webinar_name'],
        'optional_params' => ['scheduled_date', 'value', 'currency'],
        'destination_mappings' => [
            'meta' => [
                'event_name'    => 'Lead',           // Meta standard event
                'param_mapping' => [
                    'webinar_name' => 'content_name',
                    'value'        => 'value',
                    'currency'     => 'currency',
                ],
            ],
            'ga4' => [
                'event_name'    => 'generate_lead',  // GA4 recommended event
                'param_mapping' => [
                    'webinar_name' => 'item_name',
                    'value'        => 'value',
                    'currency'     => 'currency',
                ],
            ],
            'tiktok' => [
                'event_name'    => 'SubmitForm',
                'param_mapping' => [
                    'webinar_name' => 'content_name',
                ],
            ],
        ],
    ];
    return $events;
});
```

### Option 2: Edit `events.json` Directly

Add to `registry/events.json`:

```json
{
  "name": "webinar_signup",
  "display_name": "Webinar Signup",
  "icon": "Calendar",
  "description": "Fired when a user signs up for a webinar",
  "category": "engagement",
  "display_in_journey": true,
  "automatically_collected": false,
  "required_params": ["webinar_id", "webinar_name"],
  "optional_params": ["scheduled_date", "value", "currency"],
  "destination_mappings": {
    "meta": {
      "event_name": "Lead",
      "param_mapping": {
        "webinar_name": "content_name",
        "value": "value",
        "currency": "currency"
      }
    },
    "ga4": {
      "event_name": "generate_lead",
      "param_mapping": {
        "webinar_name": "item_name",
        "value": "value",
        "currency": "currency"
      }
    }
  }
}
```

### Destination Mapping Structure

```
destination_mappings: {
    "<destination_id>": {
        "event_name":    string,   // Destination's event name
        "param_mapping": {         // TrackSure param → destination param
            "source_param": "dest_param"
        },
        "requires_transform": {    // Optional type transforms
            "dest_param": "transform_type"
        }
    }
}
```

**Available transforms:** `to_array`, `to_id_array`, `to_float`, `to_int`, `to_meta_contents_array`, `_build_items_array`

**Custom transforms:** `apply_filters('tracksure_transform_value', $value, $transform, $param, $event)`

### Without Destination Mappings

If no `destination_mappings` are defined:

- The event is **still recorded** in the TrackSure database
- The event will **not** be sent to any ad platform
- Use this for internal analytics events where you only need the data in TrackSure's dashboard

---

## 📄 **Events.json Schema Reference**

Full schema for `registry/events.json`:

| Field                     | Type   | Required | Description                                         |
| ------------------------- | ------ | -------- | --------------------------------------------------- |
| `name`                    | string | Yes      | Internal event name (e.g., `"purchase"`)            |
| `display_name`            | string | Yes      | Admin UI label (e.g., `"Purchase"`)                 |
| `icon`                    | string | No       | Lucide React icon name (e.g., `"ShoppingCart"`)     |
| `description`             | string | No       | What fires this event                               |
| `category`                | string | Yes      | `"engagement"`, `"ecommerce"`, `"form"`, `"custom"` |
| `display_in_journey`      | bool   | No       | Show in user journey visualization                  |
| `automatically_collected` | bool   | No       | Auto-tracked by SDK (default: `false`)              |
| `required_params`         | array  | No       | Parameter names that must be present                |
| `optional_params`         | array  | No       | Additional supported parameters                     |
| `destination_mappings`    | object | No       | Per-destination event name + parameter mapping      |

Full schema for `registry/params.json`:

| Field          | Type   | Required | Description                                                 |
| -------------- | ------ | -------- | ----------------------------------------------------------- |
| `name`         | string | Yes      | Internal parameter name                                     |
| `display_name` | string | Yes      | Admin UI label                                              |
| `description`  | string | No       | What this parameter represents                              |
| `type`         | string | Yes      | `"string"`, `"number"`, `"integer"`, `"boolean"`, `"array"` |
| `example`      | mixed  | No       | Example value                                               |

---

## 🎓 **End-to-End Tutorial**

Complete example: Creating a "Webinar Signup" custom event, registering it, tracking from both PHP and JS, and sending to Meta + GA4.

### Step 1: Register the Event and Parameters

```php
// In your plugin or theme's functions.php
add_action('tracksure_core_booted', function($core) {
    $registry = $core->get_service('registry');

    // Register parameters
    $registry->register_parameter([
        'name'         => 'webinar_id',
        'display_name' => 'Webinar ID',
        'type'         => 'string',
        'example'      => 'WEB-2026-001',
    ]);

    $registry->register_parameter([
        'name'         => 'webinar_name',
        'display_name' => 'Webinar Name',
        'type'         => 'string',
        'example'      => 'How to Grow Your Business',
    ]);

    // Register event with destination mappings
    $registry->register_event([
        'name'            => 'webinar_signup',
        'display_name'    => 'Webinar Signup',
        'category'        => 'engagement',
        'description'     => 'User signed up for a webinar',
        'required_params' => ['webinar_id', 'webinar_name'],
        'optional_params' => ['scheduled_date', 'value', 'currency'],
    ]);
});
```

> **Note:** `destination_mappings` can only be set via the `tracksure_loaded_events` filter or by editing `events.json`. The `register_event()` method registers the event at runtime without destination mappings. For ad platform delivery, use the filter approach shown in the [Destination Mappings](#-destination-mappings) section.

### Step 2: Add Destination Mappings

```php
add_filter('tracksure_loaded_events', function($events) {
    // Find our event and add mappings
    foreach ($events as &$event) {
        if ($event['name'] === 'webinar_signup') {
            $event['destination_mappings'] = [
                'meta' => [
                    'event_name'    => 'Lead',
                    'param_mapping' => ['webinar_name' => 'content_name', 'value' => 'value', 'currency' => 'currency'],
                ],
                'ga4' => [
                    'event_name'    => 'generate_lead',
                    'param_mapping' => ['webinar_name' => 'item_name', 'value' => 'value', 'currency' => 'currency'],
                ],
            ];
            break;
        }
    }
    return $events;
});
```

### Step 3: Track from PHP (Server-Side)

```php
// In your form processing handler
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['webinar_signup_nonce'])) {
        return;
    }

    // Your form processing logic...
    $webinar_id   = sanitize_text_field($_POST['webinar_id']);
    $webinar_name = sanitize_text_field($_POST['webinar_name']);

    // Track with TrackSure
    $core     = TrackSure_Core::get_instance();
    $builder  = $core->get_service('event_builder');
    $recorder = $core->get_service('event_recorder');

    $event = $builder->build_event('webinar_signup', [
        'webinar_id'   => $webinar_id,
        'webinar_name' => $webinar_name,
        'value'        => 0,
        'currency'     => 'USD',
    ], [
        'event_source' => 'server',
        'user_data'    => [
            'email' => sanitize_email($_POST['email']),
        ],
    ]);

    if ($event) {
        $recorder->record($event);
    }
});
```

### Step 4: Track from JavaScript (Browser-Side)

```javascript
// On your webinar landing page
document
  .querySelector(".webinar-signup-btn")
  .addEventListener("click", function () {
    TrackSure.track("webinar_signup", {
      webinar_id: this.dataset.webinarId,
      webinar_name: this.dataset.webinarName,
      value: 0,
      currency: "USD",
    });
  });
```

### Step 5: Verify

1. **TrackSure Debug Mode**: Enable in settings → check browser console for event logs
2. **WordPress Debug Log**: Check `wp-content/debug.log` for server-side recording
3. **Database**: Query `wp_tracksure_events` for `event_name = 'webinar_signup'`
4. **Outbox**: Check `wp_tracksure_outbox` for destination delivery status
5. **Meta Events Manager**: Verify "Lead" events appear with `content_name` parameter
6. **GA4 DebugView**: Verify "generate_lead" events appear with `item_name` parameter

### What Happens End-to-End

```
User clicks "Sign Up" button
    │
    ├── Browser: TrackSure.track('webinar_signup', {...})
    │     ├── Deterministic event_id generated (MD5-UUID)
    │     ├── fbq('track', 'Lead', {content_name: '...'})     [immediate]
    │     ├── gtag('event', 'generate_lead', {item_name: '...'}) [immediate]
    │     └── Queued for POST /ts/v1/collect                    [batch]
    │
    ├── Server: form POST → $recorder->record(...)
    │     ├── Same deterministic event_id generated
    │     ├── Deduplication: merges with browser event (flags updated)
    │     ├── Enrichment: geolocation, device, URL normalization
    │     ├── Stored in tracksure_events table
    │     └── Queued in tracksure_outbox for CAPI/MP delivery
    │
    └── Background: Delivery Worker (WP-Cron)
          ├── Meta CAPI: POST to Facebook with 'Lead' event
          └── GA4 MP: POST to Google with 'generate_lead' event
```

---

## 📚 **See Also**

- [EVENT_SYSTEM.md](EVENT_SYSTEM.md) — Full event pipeline, builder, recorder, queue, mapper, bridge
- [PLUGIN_API.md](PLUGIN_API.md) — PHP API, Consent API, Browser SDK API
- [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md) — All hooks including event-related filters
- [MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md) — Building module packs with custom events
- [FRONTEND_SDK.md](FRONTEND_SDK.md) — Auto-tracked events and SDK configuration
- [DESTINATION_DEVELOPMENT.md](DESTINATION_DEVELOPMENT.md) — Creating new destination handlers

---

**Last Updated**: February 26, 2026
**Version**: 1.0.0
