# 🏗️ TrackSure Code Architecture

## 🎯 **Overview**

TrackSure is a first-party analytics and attribution platform for WordPress that tracks visitor behavior, ecommerce events, and ad conversions while maintaining GDPR/CCPA compliance.

**Core Philosophy**:

- ✅ **Never block events** - Always track, anonymize when consent denied
- ✅ **Modular architecture** - Core + Free + Pro plugins
- ✅ **Universal schema** - Works with ANY ecommerce platform
- ✅ **First-party tracking** - Your data stays on your server

---

## 📁 **Directory Structure**

```
tracksure/
├── tracksure.php                    # Main plugin file - bootstrap
├── uninstall.php                    # Cleanup on plugin deletion
├── readme.txt                       # WordPress.org readme
├── README.md                        # GitHub readme
│
├── includes/                        # All PHP code
│   ├── core/                        # ⚡ CORE ENGINE (shared by all modules)
│   │   ├── class-tracksure-core.php          # Service container & module registry
│   │   ├── class-tracksure-db.php            # Database layer (CRUD for 15 tables)
│   │   ├── class-tracksure-installer.php     # Database schema creation
│   │   ├── class-tracksure-hooks.php         # Hook registry
│   │   ├── class-tracksure-event-bridge.php  # Browser ↔ Server coordination
│   │   ├── class-tracksure-settings-schema.php # Settings structure
│   │   │
│   │   ├── abstractions/             # Interfaces & base classes
│   │   │   ├── interface-tracksure-ecommerce-adapter.php  # Adapter contract
│   │   │   └── class-tracksure-data-normalizer.php        # Universal schema
│   │   │
│   │   ├── admin/                    # Admin UI
│   │   │   ├── class-tracksure-admin-ui.php        # React admin wrapper
│   │   │   └── class-tracksure-admin-extensions.php # Extension registry
│   │   │
│   │   ├── api/                      # REST API (12 controllers + 1 public API)
│   │   │   ├── class-tracksure-rest-api.php                    # Main API class
│   │   │   ├── class-tracksure-rest-controller.php             # Base controller
│   │   │   ├── class-tracksure-rest-ingest-controller.php      # /collect (browser events)
│   │   │   ├── class-tracksure-rest-events-controller.php      # /events CRUD
│   │   │   ├── class-tracksure-rest-query-controller.php       # /query (analytics)
│   │   │   ├── class-tracksure-rest-settings-controller.php    # /settings
│   │   │   ├── class-tracksure-rest-goals-controller.php       # /goals
│   │   │   ├── class-tracksure-rest-diagnostics-controller.php # /diagnostics
│   │   │   ├── class-tracksure-rest-consent-controller.php     # /consent (React admin)
│   │   │   ├── class-tracksure-rest-products-controller.php    # /products
│   │   │   ├── class-tracksure-rest-quality-controller.php     # /quality
│   │   │   ├── class-tracksure-rest-registry-controller.php    # /registry
│   │   │   ├── class-tracksure-rest-suggestions-controller.php # /suggestions
│   │   │   ├── class-tracksure-rest-pixel-callback-controller.php # Pixel callbacks
│   │   │   └── tracksure-consent-api.php                       # Public consent API
│   │   │
│   │   ├── destinations/             # Ad platform integrations manager
│   │   │   └── class-tracksure-destinations-manager.php
│   │   │
│   │   ├── integrations/             # Ecommerce platform integrations manager
│   │   │   └── class-tracksure-integrations-manager.php
│   │   │
│   │   ├── jobs/                     # Background workers (4 classes)
│   │   │   ├── class-tracksure-delivery-worker.php    # Send events to destinations
│   │   │   ├── class-tracksure-cleanup-worker.php     # Old data cleanup
│   │   │   ├── class-tracksure-hourly-aggregator.php  # Hourly aggregations
│   │   │   └── class-tracksure-daily-aggregator.php   # Daily aggregations
│   │   │
│   │   ├── modules/                  # Module system (Free/Pro/3rd party)
│   │   │   ├── interface-tracksure-module.php
│   │   │   └── class-tracksure-module-registry.php
│   │   │
│   │   ├── registry/                 # Event & parameter registry
│   │   │   ├── class-tracksure-registry.php        # Main registry
│   │   │   ├── class-tracksure-registry-loader.php  # JSON loader
│   │   │   └── class-tracksure-registry-cache.php   # Cache layer
│   │   │
│   │   ├── services/                 # Business logic services (22 classes)
│   │   │   ├── class-tracksure-session-manager.php       # Session tracking
│   │   │   ├── class-tracksure-event-recorder.php        # Event storage
│   │   │   ├── class-tracksure-event-builder.php         # Event construction
│   │   │   ├── class-tracksure-event-mapper.php          # Destination mapping
│   │   │   ├── class-tracksure-event-queue.php           # Event queue management
│   │   │   ├── class-tracksure-consent-manager.php       # GDPR compliance
│   │   │   ├── class-tracksure-attribution-resolver.php  # Attribution logic
│   │   │   ├── class-tracksure-attribution-hooks.php     # Attribution hook coordination
│   │   │   ├── class-tracksure-journey-engine.php        # Customer journey
│   │   │   ├── class-tracksure-touchpoint-recorder.php   # Touchpoint tracking
│   │   │   ├── class-tracksure-conversion-recorder.php   # Conversion tracking
│   │   │   ├── class-tracksure-funnel-analyzer.php       # Funnel analysis
│   │   │   ├── class-tracksure-goal-evaluator.php        # Goal evaluation
│   │   │   ├── class-tracksure-goal-validator.php        # Goal validation
│   │   │   ├── class-tracksure-logger.php                # Debug logging
│   │   │   ├── class-tracksure-rate-limiter.php          # Rate limiting
│   │   │   ├── class-tracksure-geolocation.php           # IP → Country
│   │   │   ├── class-tracksure-action-scheduler.php      # Cron scheduling
│   │   │   ├── class-tracksure-suggestion-engine.php        # AI suggestions
│   │   │   ├── class-tracksure-trusted-proxy-helper.php    # Proxy IP handling
│   │   │   ├── class-tracksure-url-normalizer.php          # URL normalization
│   │   │   └── class-tracksure-attribution-analytics.php   # Attribution analytics
│   │   │
│   │   ├── tracking/                 # Frontend tracking
│   │   │   ├── class-tracksure-tracker-assets.php   # Enqueue tracking script
│   │   │   └── class-tracksure-checkout-tracking.php
│   │   │
│   │   └── utils/                    # Utilities
│   │       ├── class-tracksure-utilities.php
│   │       └── countries.php
│   │
│   └── free/                         # 🆓 FREE MODULE (basic features)
│       ├── class-tracksure-free.php          # Module initialization
│       │
│       ├── adapters/                  # Ecommerce adapters
│       │   └── class-tracksure-woocommerce-adapter.php
│       │
│       ├── admin/                     # Free admin features
│       │   ├── free-admin-extensions.php
│       │   └── free-api-filters.php
│       │
│       ├── destinations/              # Ad platform integrations
│       │   ├── class-tracksure-ga4-destination.php    # Google Analytics 4
│       │   └── class-tracksure-meta-destination.php   # Meta Pixel + CAPI
│       │
│       └── integrations/              # Ecommerce integrations
│           └── class-tracksure-woocommerce-v2.php
│
├── admin/                            # React admin interface
│   ├── src/                          # TypeScript source (NOT in production zip)
│   │   ├── index.tsx                 # Entry point
│   │   ├── App.tsx                   # Main app component
│   │   ├── components/               # React components
│   │   ├── pages/                    # Page components
│   │   ├── services/                 # API services
│   │   ├── hooks/                    # React hooks
│   │   ├── types/                    # TypeScript types
│   │   └── styles/                   # CSS/SCSS
│   │
│   ├── dist/                         # Production build (IN production zip)
│   │   ├── runtime.js                # Webpack module loader (loaded first)
│   │   ├── vendors.js                # Core vendors (axios, react-query, etc.)
│   │   ├── react-router.js           # React Router chunk
│   │   ├── lucide.js                 # Lucide icons (tree-shaken)
│   │   ├── common.js                 # Shared code across pages
│   │   ├── recharts.js               # Charting library (lazy-loaded)
│   │   └── tracksure-admin.js        # Main app entry point
│   │
│   ├── package.json                  # Admin dependencies
│   └── webpack.config.js             # Build configuration
│
├── assets/                           # Public assets
│   ├── js/
│   │   ├── ts-web.js                 # Browser tracking SDK
│   │   ├── ts-currency.js            # Currency detection helper
│   │   ├── ts-minicart.js            # Mini-cart event tracking
│   │   └── consent-listeners.js      # Consent change listeners
│   ├── css/
│   └── images/
│
├── registry/                         # Event & parameter definitions
│   ├── events.json                   # All trackable events
│   └── params.json                   # All event parameters
│
├── languages/                        # Translations
├── scripts/                          # Build scripts
├── docs/                             # Documentation
└── build/                            # Build output (gitignored)
    └── tracksure.zip                 # Production ZIP

```

---

## 🏛️ **Architecture Patterns**

### **1. Service Container Pattern**

`TrackSure_Core` acts as a **service container** - all services are registered and accessed centrally:

```php
// Core bootstraps all services
$core = TrackSure_Core::get_instance();

// Services accessed via core
$session_manager = $core->get_service('session_manager');
$event_recorder = $core->get_service('event_recorder');
$consent_manager = $core->get_service('consent_manager');
```

**Why?**

- ✅ Single point of dependency management
- ✅ Easy to mock services for testing
- ✅ Prevents circular dependencies

---

### **2. Module System Pattern**

TrackSure uses a **modular architecture** where features are packaged as modules:

```
Core (engine) ← Free (basic features) ← Pro (advanced features) ← 3rd party
```

**Module Registration**:

```php
// Free module registers itself with core
do_action('tracksure_register_module', 'tracksure-free', __FILE__, array(
    'name' => 'TrackSure Free',
    'version' => '1.0.0',
    'capabilities' => array(
        'destinations' => array('ga4', 'meta'),
        'integrations' => array('woocommerce'),
    ),
));
```

**Why?**

- ✅ Core code shared by free/pro/3rd party
- ✅ Pro doesn't duplicate free code
- ✅ Easy to add new destinations/integrations

---

### **3. Adapter Pattern (Ecommerce Abstraction)**

All ecommerce platforms (WooCommerce, EDD, SureCart, etc.) implement the same interface:

```php
interface TrackSure_Ecommerce_Adapter {
    public function extract_order_data($order);
    public function extract_product_data($product);
    public function extract_cart_data();
    public function extract_user_data($user = null);
}
```

**Flow:**

1. **WooCommerce Hook** → Fires `woocommerce_thankyou`
2. **Adapter** → `WooCommerce_Adapter->extract_order_data($order)` → Universal schema
3. **Event Builder** → Builds standardized `purchase` event
4. **Destinations** → GA4, Meta, etc. receive same data structure

**Why?**

- ✅ Add new platform = 1 adapter class
- ✅ Core never touches platform-specific code
- ✅ All destinations work with all platforms

---

### **4. Destination Pattern (Ad Platforms)**

Ad platforms (GA4, Meta, TikTok, etc.) follow a consistent pattern:

```php
class TrackSure_GA4_Destination {
    public function __construct() {
        // Hook into delivery system
        add_filter('tracksure_deliver_mapped_event', array($this, 'send'), 10, 3);
    }

    public function send($result, $destination, $mapped_event) {
        if ($destination !== 'ga4') {
            return $result;
        }

        // Check consent
        if (!$consent_manager->is_tracking_allowed()) {
            return array('success' => true, 'delivered' => false);
        }

        // Send to GA4 Measurement Protocol
        // ...
    }
}
```

**Why?**

- ✅ Each destination is independent
- ✅ Easy to add new platforms
- ✅ Consent checked before every send

---

### **5. Event Registry Pattern**

All events and parameters are defined in JSON registry:

**registry/events.json:**

```json
{
  "events": [
    {
      "name": "purchase",
      "display_name": "Purchase",
      "category": "ecommerce",
      "required_params": ["transaction_id", "value", "currency"],
      "optional_params": ["items", "tax", "shipping"]
    }
  ]
}
```

**Why?**

- ✅ Single source of truth
- ✅ Validation against registry
- ✅ Easy to extend with custom events
- ✅ Documentation generated from registry

---

### **6. Event Bridge Pattern (Browser ↔ Server)**

Coordinates browser-side and server-side event tracking:

```
┌─────────────────────────────────────────────────────────────┐
│                      User's Browser                          │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  TrackSure Web SDK (ts-web.js)                  │ │
│  │  - Tracks page views, clicks, form submissions         │ │
│  │  - Sends to: POST /wp-json/ts/v1/collect         │ │
│  │  - Fires: fbq('track', 'PageView') [Meta Pixel]        │ │
│  │  - Fires: gtag('event', 'page_view') [GA4 gtag]        │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                           ↓ ↑
                    REST API (JSON)
                           ↓ ↑
┌─────────────────────────────────────────────────────────────┐
│                    WordPress Server                          │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Event Bridge                                          │ │
│  │  - Receives browser events via REST                    │ │
│  │  - Triggers server-side events (WooCommerce hooks)     │ │
│  │  - Stores in database                                  │ │
│  │  - Queues for delivery to GA4/Meta (CAPI)             │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Background Workers (WP-Cron)                          │ │
│  │  - Delivery Worker: Sends events to GA4 MP, Meta CAPI │ │
│  │  - Cleanup Worker: Removes old data                    │ │
│  │  - Aggregation Workers: Hourly/Daily stats             │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

**Why?**

- ✅ Browser events captured immediately (no data loss)
- ✅ Server-side events (checkout, purchase) tracked accurately
- ✅ Deduplication prevents double-counting
- ✅ Offline queue ensures delivery

---

## 🔄 **Data Flow: Order Placement Example**

Let's trace what happens when a customer places an order:

### **Step 1: WooCommerce Hook Fires**

```php
// WooCommerce triggers this after order creation
do_action('woocommerce_thankyou', $order_id);
```

### **Step 2: Integration Captures Hook**

```php
// includes/free/integrations/class-tracksure-woocommerce-v2.php
class TrackSure_WooCommerce_V2 {
    public function __construct() {
        add_action('woocommerce_thankyou', array($this, 'track_purchase'), 10, 1);
    }

    public function track_purchase($order_id) {
        $order = wc_get_order($order_id);

        // 1. Use adapter to extract data
        $adapter = new TrackSure_WooCommerce_Adapter();
        $order_data = $adapter->extract_order_data($order);
        $user_data = $adapter->extract_user_data($order);

        // 2. Build universal event
        $event_builder = $this->core->get_service('event_builder');
        $event = $event_builder->build_event('purchase', array(
            'transaction_id' => $order_data['transaction_id'],
            'value' => $order_data['value'],
            'currency' => $order_data['currency'],
            'items' => $order_data['items'],
        ), $user_data);

        // 3. Record event (server-side, immediate)
        $event_recorder = $this->core->get_service('event_recorder');
        $event_recorder->record($event);
    }
}
```

### **Step 3: Event Builder Constructs Event**

```php
// includes/core/services/class-tracksure-event-builder.php
class TrackSure_Event_Builder {
    public function build_event($event_name, $params, $user_data) {
        // Add standard fields
        $event = array(
            'event_id' => $this->generate_uuid(),
            'event_name' => $event_name,
            'event_time' => current_time('timestamp'),
            'params' => $params,
            'user_data' => $user_data,
        );

        // Add session data
        $session_manager = $this->core->get_service('session_manager');
        $event['session_id'] = $session_manager->get_session_id();

        // Add attribution data
        $attribution = $this->core->get_service('attribution');
        $event['attribution'] = $attribution->get_current_attribution();

        return $event;
    }
}
```

### **Step 4: Event Recorder Stores in Database**

```php
// includes/core/services/class-tracksure-event-recorder.php
class TrackSure_Event_Recorder {
    public function record($event_data) {
        global $wpdb;

        // 1. Check consent
        $consent_manager = $this->core->get_service('consent_manager');
        if (!$consent_manager->is_tracking_allowed()) {
            // Anonymize event
            $event_data = $consent_manager->anonymize_event($event_data);
        }

        // 2. Store in database
        $wpdb->insert(
            $wpdb->prefix . 'tracksure_events',
            array(
                'event_id' => $event_data['event_id'],
                'event_name' => $event_data['event_name'],
                'event_data' => wp_json_encode($event_data),
                'session_id' => $event_data['session_id'],
                'created_at' => current_time('mysql'),
            )
        );

        // 3. Trigger delivery to destinations
        do_action('tracksure_event_recorded', $event_id, $event_data);
    }
}
```

### **Step 5: Destinations Manager Distributes**

```php
// includes/core/destinations/class-tracksure-destinations-manager.php
class TrackSure_Destinations_Manager {
    public function __construct() {
        add_action('tracksure_event_recorded', array($this, 'distribute_event'), 10, 2);
    }

    public function distribute_event($event_id, $event_data) {
        // Get enabled destinations from settings
        $destinations = array('ga4', 'meta'); // from settings

        foreach ($destinations as $destination) {
            // Map event to destination format
            $event_mapper = $this->core->get_service('event_mapper');
            $mapped_event = $event_mapper->map_event($event_data, $destination);

            // Queue for background delivery
            $this->queue_for_delivery($event_id, $destination, $mapped_event);
        }
    }
}
```

### **Step 6: Delivery Worker Sends to Ad Platforms**

```php
// includes/core/jobs/class-tracksure-delivery-worker.php
class TrackSure_Delivery_Worker {
    public function process() {
        global $wpdb;

        // Get pending deliveries from outbox
        $items = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}tracksure_outbox
            WHERE status = 'pending'
            LIMIT 100
        ");

        foreach ($items as $item) {
            $destination = $item->destination;
            $event_data = json_decode($item->event_data, true);

            // Trigger destination handler
            $result = apply_filters(
                'tracksure_deliver_mapped_event',
                array('success' => false),
                $destination,
                $event_data
            );

            // Update outbox status
            if ($result['success']) {
                $wpdb->update(
                    $wpdb->prefix . 'tracksure_outbox',
                    array('status' => 'delivered'),
                    array('id' => $item->id)
                );
            }
        }
    }
}
```

### **Step 7: GA4 Destination Sends to Measurement Protocol**

```php
// includes/free/destinations/class-tracksure-ga4-destination.php
class TrackSure_GA4_Destination {
    public function send($result, $destination, $mapped_event) {
        if ($destination !== 'ga4') {
            return $result;
        }

        // Get GA4 settings
        $measurement_id = get_option('tracksure_ga4_measurement_id');
        $api_secret = get_option('tracksure_ga4_api_secret');

        // Send to GA4 Measurement Protocol v2
        $response = wp_remote_post(
            "https://www.google-analytics.com/mp/collect?measurement_id={$measurement_id}&api_secret={$api_secret}",
            array(
                'body' => wp_json_encode(array(
                    'client_id' => $mapped_event['client_id'],
                    'events' => array($mapped_event['event']),
                )),
                'headers' => array('Content-Type' => 'application/json'),
            )
        );

        return array('success' => !is_wp_error($response));
    }
}
```

---

## 🗄️ **Database Architecture**

TrackSure uses **15 custom database tables** for analytics:

### **Core Tables**

| Table                     | Purpose          | Key Fields                                                  |
| ------------------------- | ---------------- | ----------------------------------------------------------- |
| **tracksure_visitors**    | Unique visitors  | `client_id` (UUID), `first_seen_at`, `user_id`              |
| **tracksure_sessions**    | User sessions    | `session_id`, `visitor_id`, `started_at`, `utm_source`      |
| **tracksure_events**      | All events       | `event_id`, `event_name`, `session_id`, `event_data` (JSON) |
| **tracksure_goals**       | Conversion goals | `goal_id`, `name`, `trigger_type`, `conditions` (JSON)      |
| **tracksure_conversions** | Goal conversions | `conversion_id`, `goal_id`, `event_id`, `value`             |

### **Attribution Tables**

| Table                                | Purpose                                                |
| ------------------------------------ | ------------------------------------------------------ |
| **tracksure_touchpoints**            | Marketing touchpoints (clicks, visits)                 |
| **tracksure_conversion_attribution** | Attribution modeling (first-click, last-click, linear) |
| **tracksure_click_ids**              | Ad platform click IDs (gclid, fbclid, etc.)            |

### **Delivery & Aggregation Tables**

| Table                           | Purpose                               |
| ------------------------------- | ------------------------------------- |
| **tracksure_outbox**            | Event delivery queue for destinations |
| **tracksure_agg_hourly**        | Hourly aggregated metrics             |
| **tracksure_agg_daily**         | Daily aggregated metrics              |
| **tracksure_agg_product_daily** | Product performance metrics           |

### **Funnel Tables**

| Table                      | Purpose                 |
| -------------------------- | ----------------------- |
| **tracksure_funnels**      | Funnel definitions      |
| **tracksure_funnel_steps** | Funnel step definitions |

**See [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) for complete schema documentation.**

---

## 🎣 **Hook System**

TrackSure provides **80+ WordPress hooks** for customization:

### **Action Hooks**

```php
// Core lifecycle
do_action('tracksure_core_booted', $core);
do_action('tracksure_modules_initialized');

// Event tracking
do_action('tracksure_event_recorded', $event_id, $event_data, $session);
do_action('tracksure_conversion_recorded', $conversion_id, $goal_id, $event_data);

// Session management
do_action('tracksure_session_started', $session_id, $visitor_id, $session_data);

// Delivery
do_action('tracksure_outbox_processed', $count);
```

### **Filter Hooks**

```php
// Consent
$allowed = apply_filters('tracksure_should_track_user', true);
$anonymized = apply_filters('tracksure_anonymize_event', $event_data);

// Event enrichment
$enriched = apply_filters('tracksure_enrich_event_data', $event_data, $session);

// Destination mapping
$mapped = apply_filters('tracksure_deliver_mapped_event', $result, $destination, $event);

// Settings
$settings = apply_filters('tracksure_settings_schema', $settings);
```

**See [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md) for complete hook reference.**

---

## ⚛️ **React Admin Interface**

The admin dashboard is built with **React + TypeScript + Tailwind CSS**:

### **Tech Stack**

- **React 18** - UI framework
- **TypeScript** - Type safety
- **Tailwind CSS** - Styling
- **React Query** - Data fetching
- **React Router** - Routing
- **Recharts** - Analytics charts
- **Webpack** - Bundling

### **Build Process**

```bash
# Development (with hot reload)
cd admin
npm run dev

# Production build
npm run build

# Output: admin/dist/tracksure-admin.js (minified)
```

### **Integration with WordPress**

```php
// includes/core/admin/class-tracksure-admin-ui.php
class TrackSure_Admin_UI {
    public function enqueue_assets($hook) {
        // Load React app
        wp_enqueue_script(
            'tracksure-admin',
            TRACKSURE_ADMIN_DIR . 'dist/tracksure-admin.js',
            array('wp-element'), // WordPress React
            TRACKSURE_VERSION,
            true
        );

        // Pass data to React
        wp_localize_script('tracksure-admin', 'trackSureAdmin', array(
            'apiUrl' => rest_url('ts/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUser' => wp_get_current_user(),
        ));
    }
}
```

### **API Communication**

React components use REST API:

```typescript
// admin/src/services/api.ts
export async function getOverview(dateStart: string, dateEnd: string) {
  const response = await fetch(
    `/wp-json/ts/v1/query/overview?date_start=${dateStart}&date_end=${dateEnd}`,
    {
      headers: {
        "X-WP-Nonce": trackSureAdmin.nonce,
      },
    },
  );
  return response.json();
}
```

---

## 🔧 **Extending TrackSure**

### **Add a New Ecommerce Integration**

1. **Create Adapter** (implements `TrackSure_Ecommerce_Adapter`):

```php
// includes/pro/adapters/class-tracksure-edd-adapter.php
class TrackSure_EDD_Adapter implements TrackSure_Ecommerce_Adapter {
    public function extract_order_data($payment) {
        // Extract EDD payment data
        // Return universal schema
    }
}
```

2. **Create Integration Handler**:

```php
// includes/pro/integrations/class-tracksure-edd.php
class TrackSure_EDD {
    public function __construct($core) {
        add_action('edd_complete_purchase', array($this, 'track_purchase'));
    }

    public function track_purchase($payment_id) {
        $adapter = new TrackSure_EDD_Adapter();
        $order_data = $adapter->extract_order_data(edd_get_payment($payment_id));

        $event_builder = $this->core->get_service('event_builder');
        $event = $event_builder->build_event('purchase', $order_data);

        $event_recorder = $this->core->get_service('event_recorder');
        $event_recorder->record($event);
    }
}
```

3. **Register Integration**:

```php
// includes/pro/class-tracksure-pro.php
add_action('tracksure_load_integration_handlers', function($integrations_manager) {
    $integrations_manager->register('edd', 'Easy Digital Downloads');
    $integrations_manager->load_handler('edd', 'TrackSure_EDD');
});
```

### **Add a New Ad Platform Destination**

1. **Create Destination Handler**:

```php
// includes/pro/destinations/class-tracksure-tiktok-destination.php
class TrackSure_TikTok_Destination {
    public function __construct($core) {
        add_filter('tracksure_deliver_mapped_event', array($this, 'send'), 10, 3);
    }

    public function send($result, $destination, $mapped_event) {
        if ($destination !== 'tiktok') {
            return $result;
        }

        // Send to TikTok Events API
        // ...

        return array('success' => true);
    }
}
```

2. **Register Destination**:

```php
// includes/pro/class-tracksure-pro.php
add_action('tracksure_load_destination_handlers', function($destinations_manager) {
    $destinations_manager->register('tiktok', 'TikTok Pixel');
    $destinations_manager->load_handler('tiktok', 'TrackSure_TikTok_Destination');
});
```

### **Add Custom Event**

```php
// Register custom event
add_action('init', function() {
    $registry = TrackSure_Registry::get_instance();
    $registry->register_event(array(
        'name' => 'webinar_signup',
        'display_name' => 'Webinar Signup',
        'category' => 'lead',
        'required_params' => array('webinar_id', 'webinar_name'),
        'optional_params' => array('scheduled_date'),
    ));
});

// Track custom event
$event_builder = $core->get_service('event_builder');
$event = $event_builder->build_event('webinar_signup', array(
    'webinar_id' => 'webinar-123',
    'webinar_name' => 'How to Grow Your Business',
    'scheduled_date' => '2026-02-15',
));

$event_recorder = $core->get_service('event_recorder');
$event_recorder->record($event);
```

---

## 📦 **Build & Deployment**

### **Development Workflow**

```bash
# Install dependencies
npm install

# Start dev server (React hot reload)
cd admin
npm run dev

# Validate PHP code
npm run lint:php

# Run all validation
npm run validate
```

### **Production Build**

```bash
# Build everything
npm run build:production

# Create ZIP file
npm run zip:plugin

# Output: build/tracksure.zip
```

### **What's Included in ZIP**

✅ Included:

- `tracksure.php` (main file)
- `includes/` (all PHP)
- `admin/dist/` (compiled React)
- `assets/` (public assets)
- `registry/` (event definitions)
- `languages/` (translations)

❌ Excluded:

- `admin/src/` (TypeScript source)
- `node_modules/`
- `build/`
- `.git/`
- `tests/`
- Development files

---

## 🔑 **Key Design Decisions**

### **1. Why Separate Core from Free?**

**Problem**: Free and Pro plugins would duplicate code.

**Solution**:

- **Core** = Shared engine (tracking, database, API)
- **Free** = Basic features (GA4, Meta, WooCommerce)
- **Pro** = Advanced features (TikTok, Pinterest, EDD, SureCart)

**Benefit**: Pro is just additional modules, not a complete rewrite.

---

### **2. Why Universal Schema?**

**Problem**: Each ecommerce platform has different data structures.

**Solution**: Normalize all platforms to universal schema:

```php
$order = array(
    'transaction_id' => '12345',
    'value' => 99.99,
    'currency' => 'USD',
    'items' => array(/* ... */),
);
```

**Benefit**:

- ✅ Add new platform = 1 adapter
- ✅ Destinations don't care about platform
- ✅ Easy to switch platforms

---

### **3. Why Event Registry?**

**Problem**: Hard-coded event names scattered across codebase.

**Solution**: Centralized JSON registry:

```json
{
  "events": [
    {"name": "purchase", "display_name": "Purchase", ...}
  ]
}
```

**Benefit**:

- ✅ Single source of truth
- ✅ Validation
- ✅ Documentation auto-generated
- ✅ Easy to add custom events

---

### **4. Why Outbox Pattern?**

**Problem**: Sending to GA4/Meta during checkout would slow down page load.

**Solution**:

1. Store event in database immediately
2. Queue delivery in `outbox` table
3. Background worker sends asynchronously

**Benefit**:

- ✅ Fast page loads
- ✅ Retry on failure
- ✅ No data loss if destination is down

---

### **5. Why Consent-Never-Block?**

**Problem**: Traditional GDPR plugins block tracking completely.

**Solution**:

- ✅ **Always track** events
- ✅ **Anonymize** when consent denied
- ✅ **Mark consent status** in event data

**Benefit**:

- ✅ Never lose data
- ✅ Compliance maintained
- ✅ Can analyze anonymized traffic

---

## 🎓 **Learning Path**

### **For Junior Developers**

1. Start here: [JUNIOR_DEVELOPER_GUIDE.md](JUNIOR_DEVELOPER_GUIDE.md)
2. Understand concepts: [CONCEPTS_EXPLAINED.md](CONCEPTS_EXPLAINED.md)
3. Follow example: [CODE_WALKTHROUGH.md](CODE_WALKTHROUGH.md)
4. Learn debugging: [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md)

### **For Senior Developers**

1. Architecture (this file) ✅
2. Event system: [EVENT_SYSTEM.md](EVENT_SYSTEM.md)
3. Database schema: [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)
4. REST API: [REST_API_REFERENCE.md](REST_API_REFERENCE.md)
5. Hooks: [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md)
6. Plugin API: [PLUGIN_API.md](PLUGIN_API.md)

### **For Third-Party Developers**

1. Module development: [MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md)
2. Adapter / integration development: [ADAPTER_DEVELOPMENT.md](ADAPTER_DEVELOPMENT.md)
3. Custom events: [CUSTOM_EVENTS.md](CUSTOM_EVENTS.md)
4. Plugin API: [PLUGIN_API.md](PLUGIN_API.md)
5. Destination development: [DESTINATION_DEVELOPMENT.md](DESTINATION_DEVELOPMENT.md)

---

## 📞 **Questions?**

- **Slack**: #tracksure-dev
- **GitHub**: https://github.com/tracksure/tracksure
- **Docs**: https://docs.tracksure.cloud

---

**Last Updated**: February 26, 2026  
**Version**: 1.0.0
