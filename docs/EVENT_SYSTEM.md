# Event System

> **Scope**: How events flow from browser/server through the full pipeline — builder, recorder, queue, mapper, bridge, registry, and deduplication.

---

## Table of Contents

1. [Pipeline Overview](#-pipeline-overview)
2. [Event Builder](#-event-builder)
3. [Event Recorder](#-event-recorder)
4. [Event Queue](#-event-queue)
5. [Event Mapper](#-event-mapper)
6. [Event Bridge](#-event-bridge)
7. [Event Registry](#-event-registry)
8. [Registry JSON Schema](#-registry-json-schema)
9. [Deduplication](#-deduplication)
10. [Hooks Reference](#-hooks-reference)
11. [See Also](#-see-also)

---

## 🔄 **Pipeline Overview**

Every event — whether originating in the browser or on the server — flows through the same pipeline:

```
┌──────────────────────────────────────────────────────┐
│  ORIGIN                                               │
│  Browser: ts-web.js track() → POST /ts/v1/collect     │
│  Server:  WooCommerce hook → Event Builder            │
└──────────────────┬───────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────┐
│  1. EVENT BUILDER                                     │
│  build_event($name, $params, $context)                │
│  • Validates against registry                         │
│  • Generates deterministic event_id (MD5-UUID)        │
│  • Adds temporal parameters (date, hour, quarter …)   │
│  • Collects user data & page context                  │
│  Output → complete event array                        │
└──────────────────┬───────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────┐
│  2. EVENT RECORDER                                    │
│  record($event_data)                                  │
│  Phase 0 — Validation                                 │
│    • Required fields, UUID format, rate limit, bot    │
│    • Registry validation (event + params)             │
│  Phase 1 — Deduplication                              │
│    • Lookup by deterministic event_id                 │
│    • If duplicate: merge flags, return early          │
│  Phase 2 — Enrichment                                 │
│    • URL normalization, geolocation, device/browser   │
│    • IP anonymization (consent), page context         │
│  Phase 3 — Persist                                    │
│    • Enqueue via Event Queue (batched INSERT)         │
│    • Queue to outbox for destination delivery         │
│    • Evaluate goals & record conversions              │
└──────────────────┬───────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────┐
│  3. EVENT QUEUE                                       │
│  In-memory buffer → single multi-row INSERT IGNORE    │
│  Batch size: 100 | Auto-flush on shutdown             │
└──────────────────┬───────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────┐
│  4. OUTBOX                                            │
│  One row per event, JSON destinations array           │
│  Conversion events: immediate delivery                │
│  Other events: next cron cycle                        │
└──────────────────┬───────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────┐
│  5. EVENT MAPPER + DELIVERY WORKER                    │
│  map_to_destination($event, 'meta') → Meta format     │
│  map_to_destination($event, 'ga4')  → GA4 format      │
│  Registry-based param transformation                  │
│  Delivery Worker sends via HTTP (CAPI, MP, etc.)      │
└──────────────────────────────────────────────────────┘
```

**Parallel browser path** (Event Bridge):

```
Browser track() → ts-web.js
  ├── Queue for batch POST /ts/v1/collect  (server path above)
  └── Immediately fire browser pixels:
        ├── fbq('track', 'Purchase', {...})    [Meta Pixel]
        ├── gtag('event', 'purchase', {...})   [GA4 gtag]
        └── ...registered destinations
```

---

## 🏗️ **Event Builder**

**File:** `includes/core/services/class-tracksure-event-builder.php`

The Event Builder is the **canonical** way to create events, both from PHP and from the REST ingest controller.

### Public Methods

| Method           | Signature                                                                  | Returns                   |
| ---------------- | -------------------------------------------------------------------------- | ------------------------- |
| `get_instance()` | `static`                                                                   | `TrackSure_Event_Builder` |
| `build_event()`  | `build_event(string $event_name, array $params = [], array $context = [])` | `array\|false`            |
| `build_batch()`  | `build_batch(array $events)`                                               | `array` of event arrays   |

### `build_event()` Parameters

| Argument      | Type     | Description                                                    |
| ------------- | -------- | -------------------------------------------------------------- |
| `$event_name` | `string` | Must exist in the registry (e.g., `'purchase'`, `'page_view'`) |
| `$params`     | `array`  | Key-value event parameters. Validated against registry schema. |
| `$context`    | `array`  | Optional overrides (see table below)                           |

**Context overrides:**

| Key              | Type     | Default                                                                  |
| ---------------- | -------- | ------------------------------------------------------------------------ |
| `event_id`       | `string` | Auto-generated deterministic UUID                                        |
| `client_id`      | `string` | `session_manager->get_client_id_from_browser()` → `wp_generate_uuid4()`  |
| `session_id`     | `string` | `session_manager->get_session_id_from_browser()` → `wp_generate_uuid4()` |
| `event_source`   | `string` | `'server'` (or `'browser'` from REST ingest)                             |
| `user_data`      | `array`  | WP user data or manual override                                          |
| `ecommerce_data` | `array`  | Item array for e-commerce events                                         |
| `page_url`       | `string` | Auto-detected                                                            |
| `page_title`     | `string` | Auto-detected                                                            |
| `page_path`      | `string` | Derived from page_url                                                    |
| `page_referrer`  | `string` | From `$_SERVER` or browser                                               |

### Built Event Structure

```php
[
    'event_id'       => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', // UUID v4
    'event_name'     => 'purchase',
    'event_source'   => 'server',       // or 'browser'
    'browser_fired'  => 0,              // 1 if browser origin
    'server_fired'   => 1,              // 1 if server origin
    'client_id'      => 'uuid-v4',
    'session_id'     => 'uuid-v4',
    'timestamp'      => '2026-02-26 14:30:00', // UTC
    'event_params'   => [               // Validated params + temporal params
        'transaction_id' => 'WC-1234',
        'value'          => 99.99,
        'currency'       => 'USD',
        'event_date'     => '2026-02-26',
        'event_hour'     => 14,
        'day_of_week'    => 'Thursday',
        'quarter'        => 'Q1',
        // ... all temporal params auto-added
    ],
    'user_data'      => ['email' => '...', 'first_name' => '...'],
    'ecommerce_data' => [/* items */],
    'page_context'   => [
        'page_url'      => 'https://example.com/checkout/order-received/123/',
        'page_title'    => 'Order Received',
        'page_path'     => '/checkout/order-received/123/',
        'page_referrer' => 'https://example.com/checkout/',
    ],
]
```

### Temporal Parameters

Auto-added to **every** event. Computed from the WordPress site timezone.

| Parameter            | Type   | Example              |
| -------------------- | ------ | -------------------- |
| `event_date`         | string | `"2026-02-26"`       |
| `event_time`         | string | `"14:30:00"`         |
| `event_hour`         | int    | `14`                 |
| `day_of_week`        | string | `"Thursday"`         |
| `day_of_week_number` | int    | `4` (0=Sun, 6=Sat)   |
| `is_weekend`         | bool   | `false`              |
| `week_of_year`       | int    | `9`                  |
| `month_number`       | int    | `2`                  |
| `month_name`         | string | `"February"`         |
| `quarter`            | string | `"Q1"`               |
| `year`               | int    | `2026`               |
| `timezone`           | string | `"America/New_York"` |

### User Data Collection Priority

1. **Manual** — `$context['user_data']` (from adapter or integration)
2. **WordPress** — `wp_get_current_user()` → `email`, `first_name`, `last_name`, `user_id`
3. **Empty** — progressive capture from browser (merged during deduplication)

---

## 📼 **Event Recorder**

**File:** `includes/core/services/class-tracksure-event-recorder.php` (1211 lines)

The Event Recorder is the single entry point for persisting events. It validates, deduplicates, enriches, and queues events.

### Public Methods

| Method           | Signature                   | Returns                                                              |
| ---------------- | --------------------------- | -------------------------------------------------------------------- |
| `get_instance()` | `static`                    | `TrackSure_Event_Recorder`                                           |
| `record()`       | `record(array $event_data)` | `['success' => bool, 'event_id' => string\|null, 'errors' => array]` |

### Phase 0 — Validation Pipeline

Each check runs in order. Failure halts the pipeline.

| Step | Check                                                                   | On Failure                                      |
| ---- | ----------------------------------------------------------------------- | ----------------------------------------------- |
| 1    | Required fields: `event_name`, `client_id`, `session_id`, `event_id`    | Error returned                                  |
| 2    | UUID v4 format for `event_id`                                           | Error returned                                  |
| 3    | Rate limit: `TrackSure_Rate_Limiter::check_rate_limit($client_id, $ip)` | **Silent reject** (no error — prevents probing) |
| 4    | Bot detection: regex against User-Agent (120+ patterns)                 | **Silent reject**                               |
| 5    | Registry validation: event existence + required params + param types    | Error returned                                  |

**Bot detection** uses a single compiled regex covering:

- Search engine bots (Google, Baidu, DuckDuckGo, etc.)
- Social crawlers (Facebook, Twitter, LinkedIn, WhatsApp, etc.)
- Monitoring tools (Pingdom, GTmetrix, Lighthouse, etc.)
- SEO tools (Ahrefs, Semrush, Screaming Frog, etc.)
- Security scanners, headless browsers (Puppeteer, Playwright)
- Dev tools (curl, wget, Python-requests, Postman)
- AI bots (GPTBot, ClaudeWeb, PerplexityBot)
- Short UA heuristic: < 20 chars (excluding "mobile"/"android")

Filterable: `apply_filters('tracksure_is_bot', false, $user_agent)`

### Phase 1 — Deduplication

See the dedicated [Deduplication](#-deduplication) section below for the full algorithm.

### Phase 2 — Enrichment

The `enrich_event_data()` method applies these enrichments in order:

| Step | Enrichment              | Details                                                                                                                                                          |
| ---- | ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1    | **URL extraction**      | From root `page_url`, then from `event_params.item_url`                                                                                                          |
| 2    | **URL normalization**   | `TrackSure_URL_Normalizer::normalize()` — strips visual builder params, AJAX endpoints, WC order keys. Keeps UTM & ad click IDs. Returns `null` → event skipped. |
| 3    | **page_title fallback** | `wp_get_document_title()` → global `$post` title                                                                                                                 |
| 4    | **User Agent**          | From `$_SERVER['HTTP_USER_AGENT']` if not present                                                                                                                |
| 5    | **IP address**          | `TrackSure_Utilities::get_client_ip()`                                                                                                                           |
| 6    | **IP anonymization**    | If consent denied → full anonymization. If consent granted + `tracksure_anonymize_ip` option → mask last octet.                                                  |
| 7    | **Geolocation**         | From IP → country, region, city (filterable: `tracksure_geolocation_data`)                                                                                       |
| 8    | **Device detection**    | From UA → `mobile`/`tablet`/`desktop`                                                                                                                            |
| 9    | **Browser detection**   | Edge, Opera, Brave, Vivaldi, UC Browser, Samsung, Chrome, Safari, Firefox, IE (filterable: `tracksure_detect_browser`)                                           |
| 10   | **OS detection**        | Windows 10/8.1, macOS, Linux, Android, iOS                                                                                                                       |
| 11   | **Session fallback**    | Server-side events with no UA → copy device/browser/OS from session                                                                                              |
| 12   | **Session update**      | Update `last_activity_at`, device/location fields                                                                                                                |

### Phase 3 — Persist & Distribute

1. **Event Queue** — `TrackSure_Event_Queue::enqueue($event_data)` (batched INSERT)
2. **Outbox queueing** — one row with all destination statuses as JSON
3. **Goal evaluation** — `TrackSure_Goal_Evaluator::evaluate_event()`
4. **Conversion recording** — if goal matched

### Consent Handling

- `consent_manager->is_tracking_allowed()` checked during enrichment
- `consent_granted` column stored on every event (`0` or `1`)
- If denied: IP and PII anonymized, but event **still recorded** with anonymized data
- The event never loses data entirely — anonymized traffic is still analyzable

### Outbox Queueing Details

```php
// Simplified flow
$destinations = apply_filters('tracksure_enabled_destinations', [], $event_data, $session);

$payload = [
    'event_params'    => $event_data['event_params'],
    'page_context'    => $event_data['page_context'],
    'session_context' => [...],  // UTM, referrer, landing page
    'user_data'       => [...],  // Merged browser + server data
];

// One outbox row per event, destinations JSON:
// {"meta": {"status":"pending","retry_count":0}, "ga4": {"status":"pending","retry_count":0}}
$db->insert_outbox($event_id, $event_name, $destinations, $payload);

// Conversion events trigger IMMEDIATE delivery (up to 10 events)
if ($is_conversion) {
    $delivery_worker->process_outbox(10);
}
```

---

## 📦 **Event Queue**

**File:** `includes/core/services/class-tracksure-event-queue.php`

In-memory buffer that batches events into single multi-row `INSERT IGNORE` statements for performance.

### Constants

| Constant     | Value | Description                 |
| ------------ | ----- | --------------------------- |
| `BATCH_SIZE` | `100` | Events per INSERT statement |

### Public Methods

| Method             | Signature                                 | Description                                                        |
| ------------------ | ----------------------------------------- | ------------------------------------------------------------------ |
| `enqueue()`        | `static enqueue(array $event_data): void` | Add event. Auto-flushes at batch size.                             |
| `flush()`          | `static flush(): void`                    | Writes all buffered events to DB. Fires `tracksure_queue_flushed`. |
| `get_queue_size()` | `static get_queue_size(): int`            | Current buffer length                                              |
| `clear()`          | `static clear(): void`                    | Empty buffer without flushing                                      |

### Behavior

- **Auto-flush** at 100 events
- **Shutdown flush** via `add_action('shutdown', ['TrackSure_Event_Queue', 'flush'])` — ensures all events are persisted even if the request ends early
- **INSERT strategy** — single `INSERT IGNORE INTO {prefix}tracksure_events (29 columns) VALUES (...), (...), ...`
- **NULL handling** — JSON columns use null sentinels with `$wpdb->prepare()`

---

## 🗺️ **Event Mapper**

**File:** `includes/core/services/class-tracksure-event-mapper.php`

Transforms TrackSure's universal event schema into destination-specific formats using the registry's `destination_mappings`.

### Public Methods

| Method                 | Signature                                               | Returns                                   |
| ---------------------- | ------------------------------------------------------- | ----------------------------------------- |
| `get_instance()`       | `static`                                                | `TrackSure_Event_Mapper`                  |
| `map_to_destination()` | `map_to_destination(array $event, string $destination)` | `array\|false`                            |
| `map_batch()`          | `map_batch(array $events, string $destination)`         | `array` (unsupported events filtered out) |
| `is_supported()`       | `is_supported(string $event_name, string $destination)` | `bool`                                    |

### How Mapping Works

1. Look up `registry->get_event($event_name)['destination_mappings'][$destination]`
2. If no mapping → return `false`
3. Build destination event:

```php
[
    'event_name'       => $mapping['event_name'],   // e.g., 'PageView' (Meta), 'page_view' (GA4)
    'event_time'       => $unix_timestamp,
    'timestamp_micros' => $microseconds,            // GA4 MP format
    'event_id'         => $event['event_id'],
    'client_id'        => $event['client_id'],
    'session_id'       => $event['session_id'],
    'event_source_url' => $event['page_context']['page_url'],
    'user_data'        => $event['user_data'],       // Destinations hash in their own send()
    'custom_data'      => [...],                     // Mapped parameters
    'page_context'     => $event['page_context'],
    'session_context'  => $event['session_context'],
]
```

### Parameter Transformation

The `param_mapping` maps source → destination parameter names. If `requires_transform` is set for a parameter:

| Transform                | Description                                                                      |
| ------------------------ | -------------------------------------------------------------------------------- |
| `to_array`               | Wraps single value in array                                                      |
| `to_id_array`            | Extracts `item_id` from items array                                              |
| `to_float`               | Type cast to float                                                               |
| `to_int`                 | Type cast to integer                                                             |
| `to_meta_contents_array` | Meta-specific `{id, quantity, item_price}` format                                |
| `_build_items_array`     | GA4-specific items array (prefixed `_` = operates on entire dataset)             |
| Custom                   | `apply_filters('tracksure_transform_value', $value, $transform, $param, $event)` |

### Supported Destinations

`meta`, `ga4`, `tiktok`, `pinterest`, `snapchat`, `reddit`, `twitter`, `microsoft_ads`, `google_ads`, `linkedin`

---

## 🌉 **Event Bridge**

**File:** `includes/core/class-tracksure-event-bridge.php`

Coordinates browser-side pixel tracking with server-side processing. Destinations register **once** and get both browser pixel injection and server-side delivery.

### Public Methods

| Method                           | Signature                                     | Description                                                               |
| -------------------------------- | --------------------------------------------- | ------------------------------------------------------------------------- |
| `register_browser_destination()` | `register_browser_destination(array $config)` | Register a browser pixel destination                                      |
| `inject_pixels()`                | `inject_pixels()`                             | `wp_head` priority 1 — calls each destination's `init_script` callback    |
| `enqueue_bridge_script()`        | `enqueue_bridge_script()`                     | `wp_enqueue_scripts` priority 20 — builds bridge JS inline after `ts-web` |
| `get_registered_destinations()`  | `get_registered_destinations()`               | All registered browser destinations                                       |

### Registration Config

```php
$event_bridge->register_browser_destination([
    'id'           => 'meta-pixel',         // Required – unique identifier
    'enabled_key'  => 'tracksure_meta_...',  // Required – wp_option key to check
    'init_script'  => callable,              // Required – returns JS initialization code
    'event_mapper' => callable,              // Required – returns JS mapper function string
    'sdk_check'    => 'typeof fbq !== ...',  // Optional – JS to detect if SDK loaded
    'pixel_sender' => 'fbq("track", ...)',   // Optional – JS to send events to SDK
]);
```

### How It Works

**Step 1 — Pixel Injection** (`wp_head`, priority 1):

For each registered destination where `get_option($enabled_key)` is truthy, call the `init_script` callback. This typically loads the platform's SDK (Meta Pixel base code, GA4 gtag snippet, etc.).

**Step 2 — Bridge Script** (`wp_enqueue_scripts`, priority 20):

Builds inline JS attached to the `ts-web` script handle via `wp_add_inline_script()`. The bridge exposes on `window.TrackSure`:

| Property              | Type       | Description                                                                  |
| --------------------- | ---------- | ---------------------------------------------------------------------------- |
| `pixelMappers`        | `object`   | `{destId: mapperFunction}` — converts TrackSure events to destination format |
| `sdkChecks`           | `object`   | `{destId: checkFunction}` — detects if destination SDK is loaded             |
| `pixelSenders`        | `object`   | `{destId: senderFunction}` — sends mapped events to destination SDK          |
| `sendToPixels(event)` | `function` | Routes events to all active browser pixels                                   |
| `testPixels()`        | `function` | Sends test `view_item` event to all pixels                                   |

**Step 3 — Runtime Flow**:

```
Browser: TrackSure.track('purchase', {...})
  │
  ├── 1. Queue for batch POST /ts/v1/collect     (server pipeline)
  │
  └── 2. TrackSure.sendToPixels(event)            (immediate)
         ├── sdkChecks['meta-pixel']() → true?
         │     → pixelMappers['meta-pixel'](event)  → Meta format
         │     → pixelSenders['meta-pixel'](mapped)  → fbq('track','Purchase',{...})
         │
         ├── sdkChecks['ga4']() → true?
         │     → pixelMappers['ga4'](event)          → GA4 format
         │     → pixelSenders['ga4'](mapped)          → gtag('event','purchase',{...})
         │
         └── ... other registered destinations
```

---

## 📋 **Event Registry**

**File:** `includes/core/registry/class-tracksure-registry.php`

Centralized event and parameter definitions loaded from JSON, with multi-tier caching and runtime registration support.

### Public Methods

| Method                     | Signature                                     | Returns                                                                   |
| -------------------------- | --------------------------------------------- | ------------------------------------------------------------------------- |
| `get_instance()`           | `static`                                      | `TrackSure_Registry`                                                      |
| `get_events()`             | `get_events()`                                | `array` — all registered events                                           |
| `get_event()`              | `get_event(string $name)`                     | `array\|null`                                                             |
| `event_exists()`           | `event_exists(string $name)`                  | `bool`                                                                    |
| `get_events_by_category()` | `get_events_by_category(string $cat)`         | `array`                                                                   |
| `get_auto_events()`        | `get_auto_events()`                           | `array` — events with `automatically_collected = true`                    |
| `get_parameters()`         | `get_parameters()`                            | `array` — all registered parameters                                       |
| `get_parameter()`          | `get_parameter(string $name)`                 | `array\|null`                                                             |
| `parameter_exists()`       | `parameter_exists(string $name)`              | `bool`                                                                    |
| `validate_event()`         | `validate_event(string $name, array $params)` | `['valid' => bool, 'errors' => array]`                                    |
| `register_event()`         | `register_event(array $data)`                 | `bool`                                                                    |
| `register_parameter()`     | `register_parameter(array $data)`             | `bool`                                                                    |
| `clear_cache()`            | `clear_cache()`                               | `void`                                                                    |
| `get_stats()`              | `get_stats()`                                 | `['total_events', 'auto_events', 'total_parameters', 'event_categories']` |

### `register_event()` Fields

| Field                     | Required | Default |
| ------------------------- | -------- | ------- |
| `name`                    | Yes      | —       |
| `display_name`            | Yes      | —       |
| `category`                | Yes      | —       |
| `description`             | No       | `''`    |
| `automatically_collected` | No       | `false` |
| `required_params`         | No       | `[]`    |
| `optional_params`         | No       | `[]`    |

### `register_parameter()` Fields

| Field          | Required | Default                                               |
| -------------- | -------- | ----------------------------------------------------- |
| `name`         | Yes      | —                                                     |
| `display_name` | Yes      | —                                                     |
| `type`         | Yes      | — (`string`, `number`, `integer`, `boolean`, `array`) |
| `description`  | No       | `''`                                                  |
| `example`      | No       | `null`                                                |

### Validation Logic (`validate_event()`)

1. Check event exists in registry → error if not
2. Check each required parameter is present → error per missing param
3. Type-check each parameter: `number` → `is_numeric()`, `integer` → `is_int()`, `boolean` → `is_bool()`, `array` → `is_array()`
4. Unknown parameters are **allowed** (forward-compatible)
5. Filterable: `apply_filters('tracksure_validate_event', $result, $event_name, $event_params)`

### 3-Tier Caching

```
┌─────────────────────────────────────────┐
│  Tier 1: Object Cache (wp_cache)        │
│  Redis / Memcached / in-memory          │
│  Key: tracksure_registry / events       │
│  Fastest, per-request                   │
└──────────────────┬──────────────────────┘
                   │ miss
┌──────────────────▼──────────────────────┐
│  Tier 2: Transient Cache                │
│  wp_options table, DAY_IN_SECONDS TTL   │
│  Key: tracksure_registry_events         │
└──────────────────┬──────────────────────┘
                   │ miss
┌──────────────────▼──────────────────────┐
│  Tier 3: JSON Files                     │
│  registry/events.json (1554 lines)      │
│  registry/params.json (749 lines)       │
│  Loaded by TrackSure_Registry_Loader    │
│  Filtered: tracksure_loaded_events      │
└─────────────────────────────────────────┘
```

On load from JSON, results are written back to both Tier 1 and Tier 2.

### Supporting Classes

| Class                       | File                                           | Purpose                                                                                                                                     |
| --------------------------- | ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `TrackSure_Registry_Loader` | `registry/class-tracksure-registry-loader.php` | Reads + parses JSON files. `load_events()` / `load_parameters()`. Filterable via `tracksure_loaded_events` / `tracksure_loaded_parameters`. |
| `TrackSure_Registry_Cache`  | `registry/class-tracksure-registry-cache.php`  | Transient wrapper. `get($key)` / `set($key, $data)` / `delete($key)` / `clear_all()`. Prefix: `tracksure_registry_`. TTL: `DAY_IN_SECONDS`. |

---

## 📄 **Registry JSON Schema**

### events.json

```json
{
  "$schema": "https://json-schema.org/draft-07/schema#",
  "version": "1.1.0",
  "events": [
    {
      "name": "purchase",
      "display_name": "Purchase",
      "icon": "ShoppingCart",
      "description": "Fired when a purchase is completed",
      "category": "ecommerce",
      "display_in_journey": true,
      "automatically_collected": false,
      "required_params": ["transaction_id", "value", "currency"],
      "optional_params": ["items", "tax", "shipping", "coupon"],
      "destination_mappings": {
        "meta": {
          "event_name": "Purchase",
          "param_mapping": {
            "value": "value",
            "currency": "currency",
            "items": "contents"
          },
          "requires_transform": {
            "contents": "to_meta_contents_array"
          }
        },
        "ga4": {
          "event_name": "purchase",
          "param_mapping": {
            "transaction_id": "transaction_id",
            "value": "value",
            "currency": "currency"
          },
          "requires_transform": {
            "items": "_build_items_array"
          }
        },
        "tiktok": { "event_name": "CompletePayment", "param_mapping": {...} },
        "pinterest": { "event_name": "Checkout", "param_mapping": {...} },
        "snapchat": { "event_name": "PURCHASE", "param_mapping": {...} }
      }
    }
  ]
}
```

**Event fields:**

| Field                     | Type   | Description                                    |
| ------------------------- | ------ | ---------------------------------------------- |
| `name`                    | string | Internal event name (e.g., `"purchase"`)       |
| `display_name`            | string | Human-readable label                           |
| `icon`                    | string | Lucide icon name for admin UI                  |
| `description`             | string | What fires this event                          |
| `category`                | string | `"engagement"`, `"ecommerce"`, `"form"`, etc.  |
| `display_in_journey`      | bool   | Show in user journey visualization             |
| `automatically_collected` | bool   | Auto-tracked by SDK vs manual                  |
| `required_params`         | array  | Parameter names that must be present           |
| `optional_params`         | array  | Additional supported parameters                |
| `destination_mappings`    | object | Per-destination event name + parameter mapping |

### params.json

```json
{
  "$schema": "https://json-schema.org/draft-07/schema#",
  "version": "1.0.0",
  "parameters": [
    {
      "name": "transaction_id",
      "display_name": "Transaction ID",
      "description": "Unique identifier for the transaction",
      "type": "string",
      "example": "WC-12345"
    },
    {
      "name": "value",
      "display_name": "Value",
      "description": "Monetary value of the event",
      "type": "number",
      "example": 99.99
    },
    {
      "name": "items",
      "display_name": "Items",
      "description": "Array of items in cart or purchase",
      "type": "array"
    }
  ]
}
```

**Parameter types:** `string`, `number`, `integer`, `boolean`, `array`

---

## 🔁 **Deduplication**

Browser and server often track the **same** user action (e.g., a purchase). TrackSure uses **deterministic event IDs** to merge both sides into a single event.

### The Deterministic Event ID Algorithm

Both browser (JS) and server (PHP) use the **identical** algorithm:

```
event_id = MD5( session_id + "|" + event_name + "|" + content_identifier )
           → formatted as UUID v4
```

**Content identifier priority:**

| Priority | Condition                         | Content ID                             |
| -------- | --------------------------------- | -------------------------------------- |
| 1        | Has `product_id`                  | `"product_{id}"`                       |
| 2        | Has `items` array                 | `"items_{md5(comma_sorted_item_ids)}"` |
| 3        | Has `post_id`                     | `"post_{id}"`                          |
| 4        | Has `page_url` or `page_location` | `"page_{md5_first_8_chars}"`           |
| 5        | None of above                     | `""` (session + event name alone)      |

**Critical design choice:** No timestamp in the hash. This ensures browser and server produce the **same ID** even with millisecond timing differences.

### Merge Behavior

When the Event Recorder finds an existing event with the same `event_id`:

1. **Flag update** — set `browser_fired = 1` if browser origin, `server_fired = 1` if server
2. **Timestamp update** — record `browser_fired_at` or `server_fired_at`
3. **Param merge** — `array_merge($existing_params, $new_params)` — server data wins for product/value fields
4. **Skip goal evaluation** — already evaluated on first insert
5. **Return** `['success' => true, 'duplicate' => true, 'merged' => true]`

### Why This Matters

Ad platforms (Meta CAPI, GA4 Measurement Protocol) require an `event_id` for server-side deduplication. By using deterministic IDs:

- **Meta**: Both `fbq('track', 'Purchase', {event_id: X})` and CAPI `POST {event_id: X}` use the same ID → Meta deduplicates
- **GA4**: Both `gtag('event', 'purchase')` and MP API `POST` use the same ID → GA4 deduplicates
- No duplicate conversions in ad reports

---

## 🎣 **Hooks Reference**

Event-system-specific hooks (see [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md) for the complete list):

### Actions

| Hook                             | When                          | Parameters                             |
| -------------------------------- | ----------------------------- | -------------------------------------- |
| `tracksure_event_recorded`       | After event persisted         | `$event_id`, `$event_data`, `$session` |
| `tracksure_queue_flushed`        | After batch INSERT            | `$count`                               |
| `tracksure_registry_loaded`      | After registry initialized    | `$registry`                            |
| `tracksure_event_registered`     | After custom event registered | `$event_data`                          |
| `tracksure_parameter_registered` | After custom param registered | `$param_data`                          |

### Filters

| Hook                             | Purpose                                  | Parameters                                 |
| -------------------------------- | ---------------------------------------- | ------------------------------------------ |
| `tracksure_enrich_event_data`    | Modify event during enrichment           | `$event_data`, `$session`                  |
| `tracksure_validate_event`       | Override validation result               | `$result`, `$event_name`, `$event_params`  |
| `tracksure_loaded_events`        | Modify events after JSON load            | `$events`                                  |
| `tracksure_loaded_parameters`    | Modify params after JSON load            | `$parameters`                              |
| `tracksure_enabled_destinations` | Control which destinations receive event | `$destinations`, `$event_data`, `$session` |
| `tracksure_is_bot`               | Override bot detection                   | `$is_bot`, `$user_agent`                   |
| `tracksure_geolocation_data`     | Modify geolocation enrichment            | `$geo_data`, `$ip`                         |
| `tracksure_detect_browser`       | Override browser detection               | `$browser`, `$user_agent`                  |
| `tracksure_transform_value`      | Custom parameter transform for mapper    | `$value`, `$transform`, `$param`, `$event` |

---

## 📚 **See Also**

- [CUSTOM_EVENTS.md](CUSTOM_EVENTS.md) — How to create and track custom events
- [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md) — Complete hook reference
- [CODE_WALKTHROUGH.md](CODE_WALKTHROUGH.md) — End-to-end purchase event trace
- [TRACKSURE-DEDUPLICATION-EXPLANATION.md](TRACKSURE-DEDUPLICATION-EXPLANATION.md) — Deduplication deep dive
- [FRONTEND_SDK.md](FRONTEND_SDK.md) — Browser SDK (`ts-web.js`) documentation
- [REST_API_REFERENCE.md](REST_API_REFERENCE.md) — REST API endpoints including `/collect`
- [PLUGIN_API.md](PLUGIN_API.md) — PHP API for interacting with the event system programmatically

---

**Last Updated**: February 26, 2026
**Version**: 1.0.0
