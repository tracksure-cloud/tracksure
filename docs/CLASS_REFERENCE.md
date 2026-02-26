# 📖 TrackSure Class Reference

**Complete reference for all 50+ classes and interfaces in TrackSure**

---

## 📚 **Table of Contents**

1. [Overview](#overview)
2. [Core Classes](#core-classes)
3. [Services (22)](#services)
4. [REST API Controllers (12)](#rest-api-controllers)
5. [Background Jobs (4)](#background-jobs)
6. [Integrations & Destinations](#integrations--destinations)
7. [Registry & Modules](#registry--modules)
8. [Admin & UI](#admin--ui)
9. [Utilities](#utilities)
10. [Interfaces](#interfaces)
11. [Class Dependencies](#class-dependencies)
12. [Quick Lookup](#quick-lookup)

---

## 📖 **Overview**

TrackSure contains **50+ classes** organized into logical layers:

- **Core Layer**: Bootstrap, database, settings
- **Services Layer**: 22 core services (event handling, attribution, goals, etc.)
- **API Layer**: 12 REST controllers for admin communication
- **Background Layer**: 4 scheduled jobs for delivery and aggregation
- **Integration Layer**: Ecommerce platform integrations (WooCommerce, etc.)
- **Destination Layer**: Ad platform handlers (GA4, Meta, etc.)
- **Module Layer**: Module system for Free/Pro/3rd-party extensions

**File Organization**:

```
includes/
├── core/
│   ├── class-tracksure-core.php (Main container)
│   ├── class-tracksure-db.php (Database)
│   ├── services/ (22 service classes)
│   ├── api/ (12 REST controllers)
│   ├── jobs/ (4 background workers)
│   ├── modules/ (Module system)
│   ├── registry/ (Event/parameter registry)
│   ├── tracking/ (Frontend tracking)
│   └── admin/ (Admin UI)
└── free/
    ├── integrations/ (WooCommerce, etc.)
    ├── destinations/ (GA4, Meta)
    └── adapters/ (Ecommerce adapters)
```

---

## 🔧 **Core Classes**

### **TrackSure** (Main Plugin Class)

**File**: `tracksure.php`  
**Type**: Main bootstrap class  
**Pattern**: Singleton

**Purpose**: Plugin entry point, initializes Core + Free modules

**Key Properties**:

- `$core` - TrackSure_Core instance
- `$free` - TrackSure_Free instance

**Key Methods**:

- `get_instance()` - Get singleton instance
- `init()` - Initialize plugin
- `load_core()` - Load core module
- `load_free()` - Load free module

**Usage**:

```php
$tracksure = TrackSure::get_instance();
$core = $tracksure->core; // Access core
```

---

### **TrackSure_Core**

**File**: `includes/core/class-tracksure-core.php`  
**Type**: Service container and bootstrap  
**Pattern**: Singleton

**Purpose**: Central service container, manages dependency injection

**Key Properties**:

- `$services` (array) - Registered service instances
- `$modules` (array) - Registered modules
- `$module_registry` (TrackSure_Module_Registry) - Module registry instance

**Key Methods**:

- `get_instance()` - Get singleton instance
- `boot()` - Initialize core services
- `boot_services()` - Register all 22 services
- `get_service($name)` - Retrieve service by name
- `register_module($id, $path, $config)` - Register module
- `get_modules()` - Get all registered modules
- `init_modules()` - Initialize all modules

**Available Services**:

```php
$logger = $core->get_service('logger');
$db = $core->get_service('db');
$registry = $core->get_service('registry');
$event_builder = $core->get_service('event_builder');
$event_recorder = $core->get_service('event_recorder');
$session_manager = $core->get_service('session_manager');
$consent_manager = $core->get_service('consent_manager');
// ... 15 more services
```

**Usage**:

```php
$core = TrackSure_Core::get_instance();
$event_builder = $core->get_service('event_builder');
```

---

### **TrackSure_DB**

**File**: `includes/core/class-tracksure-db.php`  
**Type**: Database abstraction layer  
**Pattern**: Singleton

**Purpose**: Manages all database operations for 14 data tables (the 15th table `tracksure_logs` is managed by `TrackSure_Logger`)

**Key Properties**:

- `$wpdb` - WordPress database instance
- `$tables` (object) - Table name references
- `$table_prefix` - Plugin table prefix ('tracksure\_')

**Table References**:

```php
$db->tables->visitors
$db->tables->sessions
$db->tables->events
$db->tables->goals
$db->tables->conversions
$db->tables->touchpoints
$db->tables->conversion_attribution
$db->tables->outbox
$db->tables->click_ids
$db->tables->agg_hourly
$db->tables->agg_daily
$db->tables->agg_product_daily
$db->tables->funnels
$db->tables->funnel_steps
```

**Key Methods**:

**Visitor Methods**:

- `get_visitor_by_client_id($client_id)` - Find visitor by UUID
- `create_visitor($client_id, $data)` - Create new visitor
- `update_visitor($visitor_id, $data)` - Update visitor record

**Session Methods**:

- `get_session($session_id)` - Get session by ID
- `create_session($data)` - Create new session
- `update_session($session_id, $data)` - Update session

**Event Methods**:

- `insert_event($data)` - Insert event (returns event_id)
- `get_event($event_id)` - Get event by ID
- `get_events($where, $limit)` - Query events

**Goal Methods**:

- `insert_goal($data)` - Create goal
- `update_goal($goal_id, $data)` - Update goal
- `delete_goal($goal_id)` - Delete goal
- `get_active_goals()` - Get all active goals

**Conversion Methods**:

- `insert_conversion($data)` - Record conversion
- `get_conversion($conversion_id)` - Get conversion

**Outbox Methods**:

- `enqueue_to_outbox($event_id, $destination, $payload)` - Queue for delivery
- `get_pending_outbox_items($limit)` - Get items to deliver
- `mark_outbox_delivered($item_id)` - Mark as delivered
- `mark_outbox_failed($item_id, $error, $retry_count)` - Mark as failed

**Aggregation Methods**:

- `get_hourly_aggregate($hour, $metric)` - Get hourly stats
- `insert_hourly_aggregate($data)` - Insert hourly stats
- `get_daily_aggregate($date, $metric)` - Get daily stats

**Usage**:

```php
$db = TrackSure_Core::get_instance()->get_service('db');

// Create visitor
$visitor_id = $db->create_visitor('uuid-123', [
    'first_visit' => current_time('mysql')
]);

// Insert event
$event_id = $db->insert_event([
    'session_id' => 1,
    'event_name' => 'page_view',
    'event_data' => json_encode(['page' => '/home'])
]);

// Queue for delivery
$db->enqueue_to_outbox($event_id, 'ga4', $payload);
```

---

### **TrackSure_Installer**

**File**: `includes/core/class-tracksure-installer.php`  
**Type**: Installation/upgrade handler  
**Pattern**: Static class

**Purpose**: Creates/updates database tables on installation

**Key Methods**:

- `install()` - Create tables and default settings
- `create_tables()` - Create All 15 database tables
- `maybe_upgrade()` - Run upgrade routines
- `get_schema()` - Get CREATE TABLE statements

**Usage**:

```php
// Called automatically on activation
register_activation_hook(__FILE__, ['TrackSure_Installer', 'install']);
```

---

### **TrackSure_Settings_Schema**

**File**: `includes/core/class-tracksure-settings-schema.php`  
**Type**: Settings definition and validation  
**Pattern**: Singleton

**Purpose**: Defines all plugin settings and their schemas

**Key Methods**:

- `get_schema()` - Get complete settings schema
- `get_default($key)` - Get default value for setting
- `validate($key, $value)` - Validate setting value
- `get_group($group_name)` - Get settings in group

**Setting Groups**:

- `general` - Tracking enabled, data retention
- `privacy` - Consent mode, anonymization
- `destinations` - GA4, Meta configuration
- `advanced` - Debug mode, rate limits

**Usage**:

```php
$schema = TrackSure_Settings_Schema::get_instance();
$default_retention = $schema->get_default('data_retention_days'); // 90
```

---

### **TrackSure_Hooks**

**File**: `includes/core/class-tracksure-hooks.php`  
**Type**: WordPress hooks registration  
**Pattern**: Static class

**Purpose**: Registers all WordPress hooks in one place

**Key Methods**:

- `register()` - Register all hooks
- `register_frontend_hooks()` - Frontend tracking hooks
- `register_admin_hooks()` - Admin area hooks
- `register_ajax_hooks()` - AJAX handlers

---

### **TrackSure_Event_Bridge**

**File**: `includes/core/class-tracksure-event-bridge.php`  
**Type**: Event routing and enrichment  
**Pattern**: Singleton

**Purpose**: Routes events between services, coordinates event lifecycle

**Key Methods**:

- `route_event($event_data)` - Route event to appropriate handlers
- `enrich_event($event_data, $session)` - Add enrichment
- `validate_event($event_data)` - Validate event structure
- `dispatch_to_destinations($event_id, $event_data)` - Send to destinations

**Usage**:

```php
$bridge = $core->get_service('event_bridge');
$bridge->route_event($event_data);
```

---

### **TrackSure_Free**

**File**: `includes/free/class-tracksure-free.php`  
**Type**: Free module bootstrap  
**Pattern**: Module

**Purpose**: Initializes free features (WooCommerce, GA4, Meta)

**Key Methods**:

- `__construct($core)` - Initialize with core instance
- `init()` - Load free integrations and destinations
- `load_integrations()` - Load WooCommerce integration
- `load_destinations()` - Load GA4 and Meta destinations

**Provides**:

- WooCommerce integration
- GA4 destination
- Meta destination
- WooCommerce adapter

---

## ⚙️ **Services**

All services are accessed via `$core->get_service('service_name')` and follow singleton pattern.

---

### **1. TrackSure_Logger**

**File**: `includes/core/services/class-tracksure-logger.php`  
**Service Name**: `logger`  
**Purpose**: Centralized logging system

**Methods**:

- `log($message, $level, $context)` - Log message
- `error($message, $context)` - Log error
- `warning($message, $context)` - Log warning
- `info($message, $context)` - Log info
- `debug($message, $context)` - Log debug (only if debug mode enabled)
- `get_logs($level, $limit)` - Retrieve logs

**Usage**:

```php
$logger = $core->get_service('logger');
$logger->info('Event recorded', ['event_id' => 123]);
$logger->error('Delivery failed', ['destination' => 'ga4', 'error' => $error]);
```

---

### **2. TrackSure_Rate_Limiter**

**File**: `includes/core/services/class-tracksure-rate-limiter.php`  
**Service Name**: `rate_limiter`  
**Purpose**: Prevent abuse via rate limiting

**Methods**:

- `check_client_limit($client_id)` - Check if client exceeded limit
- `check_ip_limit($ip)` - Check if IP exceeded limit
- `increment_client($client_id)` - Increment client counter
- `increment_ip($ip)` - Increment IP counter
- `is_blocked($identifier, $type)` - Check if blocked
- `get_limits()` - Get current rate limits

**Default Limits**:

- 1000 events per client per hour
- 5000 events per IP per hour

**Usage**:

```php
$limiter = $core->get_service('rate_limiter');
if ($limiter->check_client_limit($client_id)) {
    // Reject - rate limit exceeded
}
```

---

### **3. TrackSure_Session_Manager**

**File**: `includes/core/services/class-tracksure-session-manager.php`  
**Service Name**: `session_manager`  
**Purpose**: Manages visitor sessions and attribution

**Key Methods**:

- `get_or_create_session($client_id, $data)` - Get existing or create new session
- `update_session($session_id, $data)` - Update session data
- `is_new_session($last_activity)` - Check if session expired (30 min timeout)
- `get_session_data($session_id)` - Get complete session data
- `end_session($session_id)` - End session
- `get_utm_params()` - Extract UTM parameters from URL
- `get_referrer_info()` - Parse referrer data

**Session Data Structure**:

```php
[
    'id' => 123,
    'visitor_id' => 456,
    'client_id' => 'uuid',
    'session_start' => '2026-01-17 10:00:00',
    'last_activity' => '2026-01-17 10:15:00',
    'utm_source' => 'google',
    'utm_medium' => 'cpc',
    'utm_campaign' => 'summer_sale',
    'referrer' => 'https://google.com',
    'landing_page' => '/products',
    'is_new_visitor' => false,
    'session_number' => 3
]
```

**Usage**:

```php
$session_mgr = $core->get_service('session_manager');
$session = $session_mgr->get_or_create_session($client_id, [
    'utm_source' => 'facebook',
    'utm_medium' => 'social'
]);
```

---

### **4. TrackSure_Consent_Manager**

**File**: `includes/core/services/class-tracksure-consent-manager.php`  
**Service Name**: `consent_manager`  
**Purpose**: Manages user consent and GDPR/CCPA compliance

**Methods**:

- `has_consent()` - Check if tracking consent given
- `has_analytics_consent()` - Check analytics consent
- `has_marketing_consent()` - Check marketing consent
- `should_track_user()` - Determine if user should be tracked
- `anonymize_event($event_data)` - Remove PII from event
- `get_user_country($ip)` - Get user's country
- `is_gdpr_country($country)` - Check if GDPR applies
- `is_california_user()` - Check if California user (CCPA)
- `set_consent($type, $granted)` - Set consent preference

**Consent Types**:

- `analytics` - Basic analytics tracking
- `marketing` - Marketing/advertising tracking
- `functional` - Functional cookies

**Usage**:

```php
$consent = $core->get_service('consent_manager');

if (!$consent->should_track_user()) {
    return; // Don't track
}

if (!$consent->has_marketing_consent()) {
    // Don't send to ad platforms
}

if ($consent->is_gdpr_country('FR')) {
    $event_data = $consent->anonymize_event($event_data);
}
```

---

### **5. TrackSure_Event_Builder**

**File**: `includes/core/services/class-tracksure-event-builder.php`  
**Service Name**: `event_builder`  
**Purpose**: Fluent interface for building events

**Methods**:

- `event($name)` - Start building event
- `param($key, $value)` - Add parameter
- `params($array)` - Add multiple parameters
- `user_data($data)` - Add user data
- `session($session_id)` - Associate with session
- `build()` - Build final event structure
- `validate()` - Validate event before building

**Usage**:

```php
$builder = $core->get_service('event_builder');

$event = $builder
    ->event('purchase')
    ->params([
        'value' => 99.99,
        'currency' => 'USD',
        'transaction_id' => 'order-123'
    ])
    ->param('items', $items)
    ->session($session_id)
    ->build();
```

---

### **6. TrackSure_Event_Recorder**

**File**: `includes/core/services/class-tracksure-event-recorder.php`  
**Service Name**: `event_recorder`  
**Purpose**: Validates and records events to database

**Methods**:

- `record($event_data, $session)` - Record event
- `enrich_event($event_data, $session)` - Add enrichment
- `validate_event($event_data)` - Validate structure
- `check_deduplication($event_hash)` - Prevent duplicates
- `detect_browser($user_agent)` - Detect browser
- `is_bot($user_agent)` - Detect bots
- `should_record($event_data)` - Check if should record

**Event Recording Flow**:

1. Validate event structure
2. Check for duplicates
3. Enrich with server-side data
4. Check consent
5. Check rate limits
6. Record to database
7. Evaluate goals
8. Queue for destinations

**Usage**:

```php
$recorder = $core->get_service('event_recorder');
$event_id = $recorder->record($event_data, $session);
```

---

### **7. TrackSure_Event_Queue**

**File**: `includes/core/services/class-tracksure-event-queue.php`  
**Service Name**: `event_queue`  
**Purpose**: In-memory event batching before database write

**Methods**:

- `enqueue($event_data)` - Add event to queue
- `flush()` - Write all queued events to database
- `get_queue()` - Get current queue
- `clear()` - Clear queue
- `size()` - Get queue size

**Usage**:

```php
$queue = $core->get_service('event_queue');
$queue->enqueue($event_data);

// Flush on shutdown
add_action('shutdown', function() use ($queue) {
    $queue->flush();
});
```

---

### **8. TrackSure_Event_Mapper**

**File**: `includes/core/services/class-tracksure-event-mapper.php`  
**Service Name**: `event_mapper`  
**Purpose**: Maps TrackSure events to destination formats

**Methods**:

- `map_event($event, $destination)` - Map event for destination
- `map_parameter($param, $mapping)` - Map single parameter
- `transform_value($value, $type)` - Transform value (currency, date, etc.)
- `apply_special_rules($data, $destination)` - Apply destination-specific rules

**Supported Destinations**:

- `ga4` - Google Analytics 4
- `meta` - Meta Pixel
- `google-ads` - Google Ads (Pro)
- `tiktok` - TikTok Ads (Pro)

**Usage**:

```php
$mapper = $core->get_service('event_mapper');
$ga4_event = $mapper->map_event($event, 'ga4');
$meta_event = $mapper->map_event($event, 'meta');
```

---

### **9. TrackSure_Attribution_Resolver**

**File**: `includes/core/services/class-tracksure-attribution-resolver.php`  
**Service Name**: `attribution`  
**Purpose**: Resolves multi-touch attribution for conversions

**Methods**:

- `resolve_conversion($conversion_id)` - Resolve attribution for conversion
- `get_touchpoints($session_id)` - Get all touchpoints for session
- `calculate_credit($touchpoints, $model)` - Calculate attribution credit
- `assign_attribution($conversion_id, $touchpoints)` - Record attribution

**Attribution Models**:

- `last_click` - 100% to last touchpoint (default)
- `first_click` - 100% to first touchpoint
- `linear` - Equal distribution
- `time_decay` - More recent weighted higher
- `position_based` - 40% first, 40% last, 20% middle

**Usage**:

```php
$attribution = $core->get_service('attribution');
$attribution->resolve_conversion($conversion_id);
```

---

### **10. TrackSure_Attribution_Hooks**

**File**: `includes/core/services/class-tracksure-attribution-hooks.php`  
**Service Name**: `attribution_hooks`  
**Purpose**: Tracks attribution click IDs (GCLID, FBCLID, etc.)

**Methods**:

- `capture_click_ids()` - Capture click IDs from URL
- `get_click_id($type)` - Get specific click ID
- `store_click_ids($client_id, $ids)` - Store in database
- `associate_with_conversion($conversion_id)` - Link to conversion

**Supported Click IDs**:

- `gclid` - Google Ads
- `fbclid` - Facebook/Meta
- `msclkid` - Microsoft Ads
- `ttclid` - TikTok (Pro)

**Usage**:

```php
$hooks = $core->get_service('attribution_hooks');
$hooks->capture_click_ids();
```

---

### **11. TrackSure_Journey_Engine**

**File**: `includes/core/services/class-tracksure-journey-engine.php`  
**Service Name**: `journey`  
**Purpose**: Tracks user journey and path analysis

**Methods**:

- `get_journey($session_id)` - Get session journey
- `get_visitor_journey($visitor_id)` - Get all sessions for visitor
- `analyze_path($journey)` - Analyze conversion path
- `get_common_paths($limit)` - Get most common paths

**Journey Structure**:

```php
[
    [
        'event_name' => 'page_view',
        'page_path' => '/home',
        'timestamp' => '2026-01-17 10:00:00',
        'event_id' => 1
    ],
    [
        'event_name' => 'add_to_cart',
        'product' => 'Widget',
        'timestamp' => '2026-01-17 10:05:00',
        'event_id' => 2
    ],
    [
        'event_name' => 'purchase',
        'value' => 99.99,
        'timestamp' => '2026-01-17 10:10:00',
        'event_id' => 3
    ]
]
```

---

### **12. TrackSure_Goal_Validator**

**File**: `includes/core/services/class-tracksure-goal-validator.php`  
**Service Name**: `goal_validator`  
**Purpose**: Validates goal configuration

**Methods**:

- `validate($goal_data)` - Validate complete goal
- `validate_trigger($trigger)` - Validate trigger type
- `validate_conditions($conditions)` - Validate conditions array
- `get_errors()` - Get validation errors

**Validation Rules**:

- Name required
- Trigger type must be valid
- Conditions must have valid operators
- Value must be numeric if present

---

### **13. TrackSure_Goal_Evaluator**

**File**: `includes/core/services/class-tracksure-goal-evaluator.php`  
**Service Name**: `goal_evaluator`  
**Purpose**: Evaluates if events trigger goal conversions

**Methods**:

- `evaluate($event_data, $session)` - Evaluate event against all goals
- `check_goal($goal, $event_data)` - Check single goal
- `evaluate_trigger($goal, $event_name)` - Check trigger match
- `evaluate_conditions($conditions, $event_params)` - Check conditions
- `record_conversion($goal, $event_id, $session)` - Record conversion

**Supported Triggers**:

- `page_view` - Page view events
- `event` - Specific event name
- `purchase` - Purchase events
- `form_submit` - Form submissions

**Supported Operators**:

- `equals`, `not_equals`
- `contains`, `not_contains`
- `greater_than`, `less_than`
- `starts_with`, `ends_with`

**Usage**:

```php
$evaluator = $core->get_service('goal_evaluator');
$evaluator->evaluate($event_data, $session);
```

---

### **14. TrackSure_Conversion_Recorder**

**File**: `includes/core/services/class-tracksure-conversion-recorder.php`  
**Service Name**: `conversion_recorder`  
**Purpose**: Records conversion events to database

**Methods**:

- `record($goal_id, $event_id, $session, $value)` - Record conversion
- `get_conversion($conversion_id)` - Get conversion
- `get_conversions_by_goal($goal_id, $date_start, $date_end)` - Query conversions
- `calculate_conversion_value($goal, $event_params)` - Calculate value

---

### **15. TrackSure_Touchpoint_Recorder**

**File**: `includes/core/services/class-tracksure-touchpoint-recorder.php`  
**Service Name**: `touchpoint_recorder`  
**Purpose**: Records attribution touchpoints

**Methods**:

- `record($session_id, $touchpoint_data)` - Record touchpoint
- `get_touchpoints($session_id)` - Get session touchpoints
- `get_visitor_touchpoints($visitor_id)` - Get all visitor touchpoints

**Touchpoint Data**:

```php
[
    'session_id' => 123,
    'source' => 'google',
    'medium' => 'cpc',
    'campaign' => 'summer_sale',
    'content' => 'ad_variant_a',
    'timestamp' => '2026-01-17 10:00:00'
]
```

---

### **16. TrackSure_Funnel_Analyzer**

**File**: `includes/core/services/class-tracksure-funnel-analyzer.php`  
**Service Name**: `funnel_analyzer`  
**Purpose**: Analyzes conversion funnels

**Methods**:

- `analyze_funnel($funnel_id, $date_start, $date_end)` - Analyze funnel
- `get_funnel_stats($funnel_id)` - Get funnel statistics
- `calculate_drop_off($funnel_id)` - Calculate drop-off rates

**Funnel Metrics**:

- Total entries
- Completion rate
- Drop-off at each step
- Average time between steps

---

### **17. TrackSure_Suggestion_Engine**

**File**: `includes/core/services/class-tracksure-suggestion-engine.php`  
**Service Name**: `suggestion_engine`  
**Purpose**: AI-powered suggestions for optimization

**Methods**:

- `get_suggestions($type)` - Get suggestions by type
- `analyze_data_quality()` - Analyze tracking quality
- `suggest_goals()` - Suggest new goals
- `suggest_optimizations()` - Suggest improvements

**Suggestion Types**:

- `data_quality` - Missing parameters, errors
- `goals` - Recommended goals
- `conversions` - Conversion optimization
- `attribution` - Attribution insights

---

### **18. TrackSure_Geolocation**

**File**: `includes/core/services/class-tracksure-geolocation.php`  
**Service Name**: `geolocation`  
**Purpose**: IP-based geolocation

**Methods**:

- `get_country($ip)` - Get country from IP
- `get_region($ip)` - Get region/state
- `get_city($ip)` - Get city
- `get_location_data($ip)` - Get complete location data

**Data Sources**:

- GeoLite2 database (free)
- MaxMind API (premium)

---

### **19. TrackSure_Action_Scheduler**

**File**: `includes/core/services/class-tracksure-action-scheduler.php`  
**Service Name**: `action_scheduler`  
**Purpose**: Manages scheduled background tasks

**Methods**:

- `schedule_single($hook, $time, $args)` - Schedule one-time action
- `schedule_recurring($hook, $interval, $args)` - Schedule recurring action
- `unschedule($hook, $args)` - Remove scheduled action
- `is_scheduled($hook, $args)` - Check if scheduled

**Used For**:

- Hourly aggregations
- Daily aggregations
- Delivery worker
- Cleanup worker

---

### **20. TrackSure_Trusted_Proxy_Helper**

**File**: `includes/core/services/class-tracksure-trusted-proxy-helper.php`  
**Service Name**: `trusted_proxy_helper`  
**Purpose**: Detects real IP behind proxies/CDNs

**Methods**:

- `get_client_ip()` - Get real client IP
- `is_trusted_proxy($ip)` - Check if IP is trusted proxy
- `add_trusted_proxy($ip)` - Add trusted proxy
- `get_proxy_headers()` - Get proxy header list

**Supported Proxies**:

- Cloudflare
- AWS ELB
- Google Load Balancer
- Custom proxies

---

### **21. TrackSure_URL_Normalizer**

**File**: `includes/core/services/class-tracksure-url-normalizer.php`  
**Service Name**: `url_normalizer`  
**Purpose**: Normalizes and cleans URLs for consistent tracking and reporting

**Methods**:

- `normalize($url)` - Normalize a URL (strip fragments, sort params, lowercase host)
- `strip_tracking_params($url)` - Remove known tracking query parameters (utm\_\*, gclid, fbclid, etc.)
- `get_path($url)` - Extract clean path from URL
- `is_same_page($url1, $url2)` - Compare two URLs ignoring irrelevant differences

---

### **22. TrackSure_Attribution_Analytics**

**File**: `includes/core/services/class-tracksure-attribution-analytics.php`  
**Service Name**: `attribution_analytics`  
**Purpose**: Provides analytics and reporting for attribution data

**Methods**:

- `get_attribution_report($args)` - Generate attribution summary report
- `get_channel_performance($date_range)` - Channel-level performance metrics
- `get_touchpoint_analysis($visitor_id)` - Analyze touchpoints for a visitor
- `get_conversion_paths($args)` - Common conversion path analysis

---

## 🌐 **REST API Controllers**

All controllers extend `TrackSure_REST_Controller` and are registered under `/wp-json/ts/v1/`.

---

### **TrackSure_REST_Controller** (Abstract Base)

**File**: `includes/core/api/class-tracksure-rest-controller.php`  
**Type**: Abstract base class  
**Extends**: `WP_REST_Controller`

**Purpose**: Base class for all REST controllers with common functionality

**Methods**:

- `check_permissions($request)` - Verify user permissions
- `validate_params($params, $schema)` - Validate request parameters
- `success_response($data)` - Format success response
- `error_response($message, $code)` - Format error response

---

### **1. TrackSure_REST_API**

**File**: `includes/core/api/class-tracksure-rest-api.php`  
**Service Name**: `rest_api`  
**Purpose**: Main REST API registration

**Methods**:

- `register_routes()` - Register all REST routes
- `load_controllers()` - Load all 12 controllers
- `get_namespace()` - Get API namespace ('ts/v1')

---

### **2. TrackSure_REST_Ingest_Controller**

**File**: `includes/core/api/class-tracksure-rest-ingest-controller.php`  
**Endpoint**: `/wp-json/ts/v1/collect`  
**Methods**: POST

**Purpose**: Receives events from browser SDK

**Endpoints**:

- `POST /collect` - Receive single or batch events

**Request**:

```json
{
  "client_id": "uuid",
  "events": [
    {
      "event_name": "page_view",
      "event_params": {
        "page_path": "/products",
        "page_title": "Products"
      }
    }
  ]
}
```

**Methods**:

- `ingest($request)` - Process incoming events
- `validate_event($event)` - Validate event structure
- `enrich_event($event)` - Add server-side data

---

### **3. TrackSure_REST_Events_Controller**

**File**: `includes/core/api/class-tracksure-rest-events-controller.php`  
**Endpoint**: `/wp-json/ts/v1/events`  
**Methods**: GET, POST, DELETE

**Purpose**: CRUD operations for events (admin only)

**Endpoints**:

- `GET /events` - List events
- `GET /events/{id}` - Get single event
- `POST /events` - Create event (server-side)
- `DELETE /events/{id}` - Delete event

---

### **4. TrackSure_REST_Query_Controller**

**File**: `includes/core/api/class-tracksure-rest-query-controller.php`  
**Endpoint**: `/wp-json/ts/v1/query`  
**Methods**: POST

**Purpose**: Advanced analytics queries for admin dashboard

**Endpoints**:

- `POST /query/overview` - Get overview metrics
- `POST /query/realtime` - Get real-time data
- `POST /query/custom` - Custom analytics query
- `POST /query/journey` - Get session journey
- `POST /query/funnel` - Get funnel analysis

**Query Parameters**:

```json
{
  "date_start": "2026-01-01",
  "date_end": "2026-01-31",
  "metrics": ["pageviews", "sessions", "conversions"],
  "dimensions": ["page_path", "source"],
  "filters": [
    { "dimension": "source", "operator": "equals", "value": "google" }
  ]
}
```

---

### **5. TrackSure_REST_Goals_Controller**

**File**: `includes/core/api/class-tracksure-rest-goals-controller.php`  
**Endpoint**: `/wp-json/ts/v1/goals`  
**Methods**: GET, POST, PUT, DELETE

**Purpose**: Manage goals

**Endpoints**:

- `GET /goals` - List all goals
- `GET /goals/{id}` - Get goal details
- `POST /goals` - Create goal
- `PUT /goals/{id}` - Update goal
- `DELETE /goals/{id}` - Delete goal

---

### **6. TrackSure_REST_Settings_Controller**

**File**: `includes/core/api/class-tracksure-rest-settings-controller.php`  
**Endpoint**: `/wp-json/ts/v1/settings`  
**Methods**: GET, POST

**Purpose**: Get/update plugin settings

**Endpoints**:

- `GET /settings` - Get all settings
- `POST /settings` - Update settings

---

### **7. TrackSure_REST_Diagnostics_Controller**

**File**: `includes/core/api/class-tracksure-rest-diagnostics-controller.php`  
**Endpoint**: `/wp-json/ts/v1/diagnostics`  
**Methods**: GET

**Purpose**: System diagnostics and health checks

**Endpoints**:

- `GET /diagnostics/system` - System info
- `GET /diagnostics/tracking` - Tracking status
- `GET /diagnostics/destinations` - Destination health
- `GET /diagnostics/database` - Database stats

---

### **8. TrackSure_REST_Consent_Controller**

**File**: `includes/core/api/class-tracksure-rest-consent-controller.php`  
**Endpoint**: `/wp-json/ts/v1/consent`  
**Methods**: GET, POST

**Purpose**: Manage consent preferences

**Endpoints**:

- `GET /consent` - Get consent status
- `POST /consent` - Update consent preferences

---

### **9. TrackSure_REST_Products_Controller**

**File**: `includes/core/api/class-tracksure-rest-products-controller.php`  
**Endpoint**: `/wp-json/ts/v1/products`  
**Methods**: GET

**Purpose**: Product analytics

**Endpoints**:

- `GET /products/top` - Top products by revenue
- `GET /products/{id}/stats` - Product statistics

---

### **10. TrackSure_REST_Quality_Controller**

**File**: `includes/core/api/class-tracksure-rest-quality-controller.php`  
**Endpoint**: `/wp-json/ts/v1/quality`  
**Methods**: GET

**Purpose**: Data quality monitoring

**Endpoints**:

- `GET /quality/score` - Overall quality score
- `GET /quality/issues` - Data quality issues

---

### **11. TrackSure_REST_Registry_Controller**

**File**: `includes/core/api/class-tracksure-rest-registry-controller.php`  
**Endpoint**: `/wp-json/ts/v1/registry`  
**Methods**: GET

**Purpose**: Access event/parameter registry

**Endpoints**:

- `GET /registry/events` - Get event registry
- `GET /registry/parameters` - Get parameter registry

---

### **12. TrackSure_REST_Suggestions_Controller**

**File**: `includes/core/api/class-tracksure-rest-suggestions-controller.php`  
**Endpoint**: `/wp-json/ts/v1/suggestions`  
**Methods**: GET

**Purpose**: AI-powered suggestions

**Endpoints**:

- `GET /suggestions` - Get all suggestions
- `GET /suggestions/goals` - Goal suggestions

---

### **13. TrackSure_REST_Pixel_Callback_Controller**

**File**: `includes/core/api/class-tracksure-rest-pixel-callback-controller.php`  
**Endpoint**: `/wp-json/ts/v1/pixel`  
**Methods**: GET

**Purpose**: Server-side pixel callbacks for GA4/Meta

**Endpoints**:

- `GET /pixel/ga4` - GA4 measurement protocol
- `GET /pixel/meta` - Meta CAPI

---

## ⏰ **Background Jobs**

All jobs use `TrackSure_Action_Scheduler` for cron-like scheduling.

---

### **1. TrackSure_Delivery_Worker**

**File**: `includes/core/jobs/class-tracksure-delivery-worker.php`  
**Schedule**: Every 1 minute  
**Purpose**: Delivers events to destinations (GA4, Meta)

**Methods**:

- `run()` - Process outbox queue
- `deliver_item($item)` - Deliver single item
- `handle_success($item_id)` - Mark delivered
- `handle_failure($item_id, $error)` - Handle retry

**Flow**:

1. Get pending items from outbox
2. For each item, deliver to destination
3. Mark as delivered or failed
4. Retry failed items (up to 5 times)

---

### **2. TrackSure_Cleanup_Worker**

**File**: `includes/core/jobs/class-tracksure-cleanup-worker.php`  
**Schedule**: Daily at 3 AM  
**Purpose**: Removes old data based on retention settings

**Methods**:

- `run()` - Execute cleanup
- `cleanup_events($days)` - Delete old events
- `cleanup_sessions($days)` - Delete old sessions
- `cleanup_logs($days)` - Delete old logs

**Default Retention**: 90 days

---

### **3. TrackSure_Hourly_Aggregator**

**File**: `includes/core/jobs/class-tracksure-hourly-aggregator.php`  
**Schedule**: Every hour  
**Purpose**: Aggregates hourly statistics

**Methods**:

- `run()` - Aggregate last hour
- `aggregate_pageviews($hour)` - Count pageviews
- `aggregate_sessions($hour)` - Count sessions
- `aggregate_conversions($hour)` - Count conversions
- `store_aggregates($hour, $data)` - Save to database

---

### **4. TrackSure_Daily_Aggregator**

**File**: `includes/core/jobs/class-tracksure-daily-aggregator.php`  
**Schedule**: Daily at 1 AM  
**Purpose**: Aggregates daily statistics

**Methods**:

- `run()` - Aggregate previous day
- `aggregate_by_source($date)` - Aggregate by traffic source
- `aggregate_by_page($date)` - Aggregate by page
- `aggregate_products($date)` - Aggregate product stats

---

## 🔌 **Integrations & Destinations**

---

### **TrackSure_Integrations_Manager**

**File**: `includes/core/integrations/class-tracksure-integrations-manager.php`  
**Service Name**: `integrations_manager`  
**Purpose**: Manages ecommerce integrations

**Methods**:

- `register_handler($id, $class)` - Register integration
- `get_handler($id)` - Get integration instance
- `get_available_integrations()` - List available integrations
- `detect_active_integrations()` - Auto-detect installed platforms

**Registered Integrations**:

- `woocommerce` - WooCommerce (Free)
- `shopify` - Shopify (Pro)
- `stripe` - Stripe (Pro)

---

### **TrackSure_Destinations_Manager**

**File**: `includes/core/destinations/class-tracksure-destinations-manager.php`  
**Service Name**: `destinations_manager`  
**Purpose**: Manages ad platform destinations

**Methods**:

- `register_handler($id, $class)` - Register destination
- `get_handler($id)` - Get destination instance
- `get_available_destinations()` - List available destinations

**Registered Destinations**:

- `ga4` - Google Analytics 4 (Free)
- `meta` - Meta Pixel (Free)
- `google-ads` - Google Ads (Pro)
- `tiktok` - TikTok Ads (Pro)

---

### **TrackSure_WooCommerce_V2**

**File**: `includes/free/integrations/class-tracksure-woocommerce-v2.php`  
**Type**: Integration handler  
**Purpose**: WooCommerce event tracking

**WordPress Hooks**:

- `woocommerce_thankyou` - Track purchases
- `woocommerce_add_to_cart` - Track add to cart
- `woocommerce_remove_cart_item` - Track cart removals
- `woocommerce_after_checkout_validation` - Track checkout

**Tracked Events**:

- `add_to_cart`
- `remove_from_cart`
- `begin_checkout`
- `purchase`
- `view_item`
- `view_cart`

**Usage**:

```php
// Automatically initialized when WooCommerce detected
```

---

### **TrackSure_WooCommerce_Adapter**

**File**: `includes/free/adapters/class-tracksure-woocommerce-adapter.php`  
**Type**: Ecommerce adapter  
**Implements**: `TrackSure_Ecommerce_Adapter`

**Purpose**: Extracts WooCommerce data into universal format

**Methods**:

- `extract_order($order)` - Extract order data
- `extract_product($product)` - Extract product data
- `extract_cart()` - Extract cart data
- `extract_customer($user_id)` - Extract customer data

**Universal Order Format**:

```php
[
    'order_id' => '123',
    'total' => 99.99,
    'currency' => 'USD',
    'items' => [...],
    'customer' => [...],
    'shipping' => 9.99,
    'tax' => 8.00
]
```

---

### **TrackSure_GA4_Destination**

**File**: `includes/free/destinations/class-tracksure-ga4-destination.php`  
**Type**: Destination handler  
**Purpose**: Sends events to Google Analytics 4

**Methods**:

- `deliver($event)` - Deliver event to GA4
- `format_event($event)` - Format for GA4 Measurement Protocol
- `send_to_ga4($payload)` - HTTP request to GA4

**Configuration**:

- Measurement ID (G-XXXXXXX)
- API Secret
- Debug mode toggle

---

### **TrackSure_Meta_Destination**

**File**: `includes/free/destinations/class-tracksure-meta-destination.php`  
**Type**: Destination handler  
**Purpose**: Sends events to Meta Pixel & CAPI

**Methods**:

- `deliver($event)` - Deliver to Meta
- `format_for_pixel($event)` - Format for browser pixel
- `format_for_capi($event)` - Format for Conversions API
- `send_to_capi($payload)` - HTTP request to CAPI

**Configuration**:

- Pixel ID
- Access Token (for CAPI)
- Test Event Code

---

## 📦 **Registry & Modules**

---

### **TrackSure_Registry**

**File**: `includes/core/registry/class-tracksure-registry.php`  
**Service Name**: `registry`  
**Purpose**: Central registry for events and parameters

**Methods**:

- `register_event($event_name, $schema)` - Register event type
- `register_parameter($param_name, $schema)` - Register parameter
- `get_event($event_name)` - Get event schema
- `get_parameter($param_name)` - Get parameter schema
- `get_all_events()` - Get all registered events
- `get_all_parameters()` - Get all registered parameters

**Event Schema**:

```php
[
    'name' => 'purchase',
    'description' => 'Purchase event',
    'parameters' => ['value', 'currency', 'transaction_id', 'items']
]
```

---

### **TrackSure_Registry_Loader**

**File**: `includes/core/registry/class-tracksure-registry-loader.php`  
**Purpose**: Loads registry from JSON files

**Methods**:

- `load_events()` - Load events from registry files
- `load_parameters()` - Load parameters from registry files

---

### **TrackSure_Registry_Cache**

**File**: `includes/core/registry/class-tracksure-registry-cache.php`  
**Purpose**: Caches registry for performance

**Methods**:

- `get($key)` - Get cached registry
- `set($key, $value)` - Cache registry
- `flush()` - Clear cache

---

### **TrackSure_Module_Registry**

**File**: `includes/core/modules/class-tracksure-module-registry.php`  
**Service Name**: `module_registry`  
**Purpose**: Manages module system

**Methods**:

- `register($id, $path, $config)` - Register module
- `load($id)` - Load module instance
- `get_module($id)` - Get module
- `get_all_modules()` - Get all modules
- `register_capability($type, $id, $config)` - Register capability

**Module Structure**:

```php
[
    'id' => 'tracksure-pro',
    'name' => 'TrackSure Pro',
    'version' => '1.0.0',
    'path' => '/path/to/module.php',
    'capabilities' => [
        'destinations' => ['google-ads', 'tiktok'],
        'integrations' => ['shopify', 'stripe']
    ]
]
```

---

## 🎨 **Admin & UI**

---

### **TrackSure_Admin_UI**

**File**: `includes/core/admin/class-tracksure-admin-ui.php`  
**Purpose**: Admin dashboard and menu

**Methods**:

- `register_menu()` - Add admin menu
- `render_page()` - Render React app container
- `enqueue_scripts()` - Load React admin assets

**Admin Pages**:

- Overview
- Real-time
- Events
- Sessions
- Journeys
- Goals
- Products
- Traffic Sources
- Pages
- Destinations
- Integrations
- Settings
- Diagnostics
- Data Quality

---

### **TrackSure_Admin_Extensions**

**File**: `includes/core/admin/class-tracksure-admin-extensions.php`  
**Purpose**: Extensibility for admin UI

**Methods**:

- `register_extension($id, $config)` - Register admin extension
- `get_extensions()` - Get all registered extensions

---

## 🛠 **Utilities**

---

### **TrackSure_Utilities**

**File**: `includes/core/utils/class-tracksure-utilities.php`  
**Purpose**: Common utility functions

**Methods**:

- `sanitize_event_name($name)` - Sanitize event name
- `generate_uuid()` - Generate UUID
- `get_client_ip()` - Get real client IP
- `parse_user_agent($ua)` - Parse user agent
- `format_currency($amount, $currency)` - Format money
- `get_timezone()` - Get WordPress timezone

---

### **TrackSure_Countries**

**File**: `includes/core/utils/countries.php`  
**Purpose**: Country codes and names

**Methods**:

- `get_countries()` - Get all countries
- `get_country_name($code)` - Get country name
- `get_eu_countries()` - Get EU country codes

---

### **TrackSure_Data_Normalizer**

**File**: `includes/core/abstractions/class-tracksure-data-normalizer.php`  
**Purpose**: Normalizes data from different sources

**Methods**:

- `normalize_order($order, $platform)` - Normalize order data
- `normalize_product($product, $platform)` - Normalize product data
- `normalize_customer($customer, $platform)` - Normalize customer data

---

## 🔄 **Interfaces**

---

### **TrackSure_Ecommerce_Adapter**

**File**: `includes/core/abstractions/interface-tracksure-ecommerce-adapter.php`  
**Purpose**: Interface for ecommerce platform adapters

**Required Methods**:

```php
interface TrackSure_Ecommerce_Adapter {
    public function extract_order($order);
    public function extract_product($product);
    public function extract_cart();
    public function extract_customer($user_id);
}
```

**Implementations**:

- `TrackSure_WooCommerce_Adapter`
- `TrackSure_Shopify_Adapter` (Pro)
- `TrackSure_Stripe_Adapter` (Pro)

---

### **TrackSure_Module_Interface**

**File**: `includes/core/modules/interface-tracksure-module.php`  
**Purpose**: Interface for TrackSure modules

**Required Methods**:

```php
interface TrackSure_Module_Interface {
    public function init();
    public function get_capabilities();
    public function get_version();
}
```

---

## 🔗 **Class Dependencies**

```
TrackSure (Main)
└── TrackSure_Core (Container)
    ├── TrackSure_DB
    ├── Services (22)
    │   ├── TrackSure_Logger
    │   ├── TrackSure_Event_Recorder
    │   │   └── TrackSure_Event_Builder
    │   ├── TrackSure_Session_Manager
    │   ├── TrackSure_Consent_Manager
    │   ├── TrackSure_Goal_Evaluator
    │   │   └── TrackSure_Goal_Validator
    │   └── ... 15 more services
    ├── REST API (12 controllers)
    │   └── TrackSure_REST_Controller (base)
    ├── Jobs (4 workers)
    ├── TrackSure_Registry
    ├── TrackSure_Module_Registry
    └── TrackSure_Integrations_Manager
        └── TrackSure_Destinations_Manager
```

---

## 🔍 **Quick Lookup**

### **By Function**

**Need to track events?**

- `TrackSure_Event_Builder` - Build events
- `TrackSure_Event_Recorder` - Record events
- `TrackSure_Event_Queue` - Batch events

**Need to manage sessions?**

- `TrackSure_Session_Manager` - Session lifecycle
- `TrackSure_Visitor` methods in `TrackSure_DB`

**Need to handle goals?**

- `TrackSure_Goal_Validator` - Validate goals
- `TrackSure_Goal_Evaluator` - Evaluate conversions
- `TrackSure_Conversion_Recorder` - Record conversions

**Need to send to destinations?**

- `TrackSure_Destinations_Manager` - Manage destinations
- `TrackSure_Event_Mapper` - Map events
- `TrackSure_Delivery_Worker` - Deliver events

**Need analytics data?**

- `TrackSure_REST_Query_Controller` - Query API
- `TrackSure_Hourly_Aggregator` - Hourly stats
- `TrackSure_Daily_Aggregator` - Daily stats

**Need consent handling?**

- `TrackSure_Consent_Manager` - All consent logic

---

### **By File Path**

**Core**:

- `includes/core/class-tracksure-core.php`
- `includes/core/class-tracksure-db.php`
- `includes/core/class-tracksure-installer.php`
- `includes/core/class-tracksure-settings-schema.php`

**Services** (all in `includes/core/services/`):

- Action Scheduler, Attribution Hooks, Attribution Resolver
- Consent Manager, Conversion Recorder, Event Builder
- Event Mapper, Event Queue, Event Recorder
- Funnel Analyzer, Geolocation, Goal Evaluator
- Goal Validator, Journey Engine, Logger
- Rate Limiter, Session Manager, Suggestion Engine
- Touchpoint Recorder, Trusted Proxy Helper

**API** (all in `includes/core/api/`):

- REST API, REST Controller (base)
- Consent, Diagnostics, Events, Goals
- Ingest, Pixel Callback, Products, Quality
- Query, Registry, Settings, Suggestions

**Jobs** (all in `includes/core/jobs/`):

- Cleanup Worker, Daily Aggregator
- Delivery Worker, Hourly Aggregator

---

## 🎓 **Learning Path**

**For Juniors - Start Here**:

1. `TrackSure_Core` - Understand service container
2. `TrackSure_DB` - Learn database layer
3. `TrackSure_Event_Builder` - Build events
4. `TrackSure_Event_Recorder` - Record events
5. `TrackSure_Session_Manager` - Session management

**For Intermediate**:

1. `TrackSure_REST_Ingest_Controller` - API integration
2. `TrackSure_Event_Mapper` - Event transformation
3. `TrackSure_Goal_Evaluator` - Goal logic
4. `TrackSure_Delivery_Worker` - Background jobs

**For Advanced**:

1. `TrackSure_Attribution_Resolver` - Attribution logic
2. `TrackSure_Module_Registry` - Module system
3. `TrackSure_Destinations_Manager` - Destination architecture
4. Custom controller/service development

---

## 📚 **See Also**

- **[CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md)** - System architecture
- **[HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md)** - All WordPress hooks
- **[REST_API_REFERENCE.md](REST_API_REFERENCE.md)** - API documentation
- **[DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)** - Database structure
- **[CODE_WALKTHROUGH.md](CODE_WALKTHROUGH.md)** - Code walkthroughs

---

**Questions?** All classes have inline documentation. Use `@see` tags to find related classes!
