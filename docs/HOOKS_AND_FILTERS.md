# 🪝 TrackSure Hooks & Filters Reference

**Complete guide to all WordPress actions and filters in TrackSure**

---

## 📚 **Table of Contents**

1. [Overview](#overview)
2. [Action Hooks](#action-hooks)
   - [Core Lifecycle](#core-lifecycle)
   - [Event Management](#event-management)
   - [Module System](#module-system)
   - [Goals & Conversions](#goals--conversions)
   - [Sessions & Visitors](#sessions--visitors)
   - [Background Jobs](#background-jobs)
   - [Settings](#settings)
   - [Admin UI](#admin-ui)
3. [Filter Hooks](#filter-hooks)
   - [Event Filters](#event-filters)
   - [Consent & Tracking](#consent--tracking)
   - [Data Transformation](#data-transformation)
   - [Goals & Conversions](#goals--conversions-filters)
   - [Query & Analytics](#query--analytics)
   - [Settings & Schema](#settings--schema)
   - [Registry & Detection](#registry--detection)
4. [Hook Priority Guidelines](#hook-priority-guidelines)
5. [Common Use Cases](#common-use-cases)
6. [Code Examples](#code-examples)

---

## 📖 **Overview**

TrackSure provides 80+ WordPress hooks for customization and extension. These hooks allow you to:

- ✅ Modify event data before recording
- ✅ Add custom enrichment to events
- ✅ Customize consent behavior
- ✅ Register custom modules and capabilities
- ✅ Intercept delivery to destinations
- ✅ Extend goals and conversion logic
- ✅ Modify settings and schemas

**Hook Naming Convention**:

- **Actions**: `tracksure_{action_name}` (e.g., `tracksure_event_recorded`)
- **Filters**: `tracksure_{filter_name}` (e.g., `tracksure_enrich_event_data`)

---

## 🎬 **Action Hooks**

### **Core Lifecycle**

#### `tracksure_loaded`

**Fires when TrackSure core is fully loaded.**

```php
do_action('tracksure_loaded', $core);
```

**Parameters**:

- `$core` (TrackSure_Core) - Core instance

**Use Cases**:

- Initialize custom integrations
- Register custom modules
- Hook into TrackSure services

**Example**:

```php
add_action('tracksure_loaded', function($core) {
    // Get service from container
    $event_builder = $core->get_service('event_builder');

    // Initialize custom module
    my_custom_module_init($core);
});
```

---

#### `tracksure_core_booted`

**Fires when all core services are booted.**

```php
do_action('tracksure_core_booted', $core);
```

**Parameters**:

- `$core` (TrackSure_Core) - Core instance

**Use Cases**:

- Access initialized services
- Register late-loading extensions
- Verify service availability

**Example**:

```php
add_action('tracksure_core_booted', function($core) {
    // All services now available
    $db = $core->get_service('db');
    $logger = $core->get_service('logger');
});
```

---

#### `tracksure_installed`

**Fires when TrackSure is first installed (database tables created).**

```php
do_action('tracksure_installed');
```

**Parameters**: None

**Use Cases**:

- Create custom database tables
- Initialize default settings
- Log installation event

**Example**:

```php
add_action('tracksure_installed', function() {
    // Create custom extension table
    global $wpdb;
    $table_name = $wpdb->prefix . 'tracksure_custom';

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        data text,
        PRIMARY KEY  (id)
    )";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});
```

---

### **Event Management**

#### `tracksure_event_recorded`

**Fires when an event is successfully recorded to the database.**

```php
do_action('tracksure_event_recorded', $event_id, $event_data, $session);
```

**Parameters**:

- `$event_id` (int) - Database event ID
- `$event_data` (array) - Complete event data
- `$session` (array) - Session data

**Use Cases**:

- Custom analytics processing
- External logging
- Real-time notifications
- Custom aggregations

**Example**:

```php
add_action('tracksure_event_recorded', function($event_id, $event_data, $session) {
    if ($event_data['event_name'] === 'purchase') {
        // Notify external system
        wp_remote_post('https://api.example.com/notify', [
            'body' => [
                'event_id' => $event_id,
                'value' => $event_data['event_params']['revenue'] ?? 0
            ]
        ]);
    }
}, 10, 3);
```

---

#### `tracksure_queue_flushed`

**Fires when event queue is flushed to database.**

```php
do_action('tracksure_queue_flushed', $count);
```

**Parameters**:

- `$count` (int) - Number of events flushed

**Use Cases**:

- Monitor queue performance
- Debug event batching
- Trigger post-flush cleanup

**Example**:

```php
add_action('tracksure_queue_flushed', function($count) {
    if ($count > 100) {
        error_log("TrackSure: Large queue flush of {$count} events");
    }
});
```

---

### **Module System**

#### `tracksure_register_module`

**Hook to register new TrackSure modules (Free/Pro/3rd-party).**

```php
do_action('tracksure_register_module', $module_id, $module_path, $config);
```

**Parameters**:

- `$module_id` (string) - Unique module identifier
- `$module_path` (string) - Path to module file
- `$config` (array) - Module configuration

**Use Cases**:

- Register Pro module
- Register 3rd-party extensions
- Declare module capabilities

**Example**:

```php
add_action('init', function() {
    do_action('tracksure_register_module', 'tracksure-pro', __FILE__, [
        'name' => 'TrackSure Pro',
        'version' => '1.0.0',
        'capabilities' => [
            'destinations' => ['google-ads', 'tiktok'],
            'integrations' => ['shopify', 'stripe']
        ]
    ]);
}, 4); // Priority 4 - before module initialization
```

---

#### `tracksure_module_registered`

**Fires when a module is successfully registered.**

```php
do_action('tracksure_module_registered', $module_id, $module_data);
```

**Parameters**:

- `$module_id` (string) - Module identifier
- `$module_data` (array) - Complete module configuration

**Use Cases**:

- Verify module registration
- Log module loading
- Trigger dependent modules

**Example**:

```php
add_action('tracksure_module_registered', function($module_id, $module_data) {
    error_log("TrackSure Module Registered: {$module_id}");
});
```

---

#### `tracksure_module_loaded`

**Fires when a module instance is created and loaded.**

```php
do_action('tracksure_module_loaded', $module_id, $instance);
```

**Parameters**:

- `$module_id` (string) - Module identifier
- `$instance` (object) - Module instance

**Use Cases**:

- Verify module initialization
- Access module methods
- Configure module settings

**Example**:

```php
add_action('tracksure_module_loaded', function($module_id, $instance) {
    if ($module_id === 'tracksure-pro') {
        // Configure Pro module
        $instance->set_option('advanced_attribution', true);
    }
}, 10, 2);
```

---

#### `tracksure_modules_initialized`

**Fires when all registered modules have been initialized.**

```php
do_action('tracksure_modules_initialized');
```

**Parameters**: None

**Use Cases**:

- Run code after all modules loaded
- Verify module dependencies
- Initialize cross-module features

**Example**:

```php
add_action('tracksure_modules_initialized', function() {
    // All modules now active
    $core = TrackSure_Core::get_instance();
    $modules = $core->get_modules();

    error_log('Active Modules: ' . implode(', ', array_keys($modules)));
});
```

---

#### `tracksure_capability_registered`

**Fires when a module registers a new capability (destination, integration, dashboard).**

```php
do_action('tracksure_capability_registered', $type, $id, $config);
```

**Parameters**:

- `$type` (string) - Capability type: 'destination', 'integration', 'dashboard', etc.
- `$id` (string) - Capability identifier
- `$config` (array) - Capability configuration

**Use Cases**:

- Track available capabilities
- Validate capability registration
- Update admin UI

**Example**:

```php
add_action('tracksure_capability_registered', function($type, $id, $config) {
    if ($type === 'destination') {
        // New destination registered
        update_option('tracksure_custom_destinations', [
            $id => $config
        ]);
    }
}, 10, 3);
```

---

### **Goals & Conversions**

#### `tracksure_goal_conversion`

**Fires when a goal conversion is recorded.**

```php
do_action('tracksure_goal_conversion', $conversion_id, $goal, $event_id, $session);
```

**Parameters**:

- `$conversion_id` (int) - Conversion database ID
- `$goal` (array) - Goal configuration
- `$event_id` (int) - Triggering event ID
- `$session` (array) - Session data

**Use Cases**:

- Send conversion notifications
- Trigger webhooks
- Custom conversion tracking
- External analytics

**Example**:

```php
add_action('tracksure_goal_conversion', function($conversion_id, $goal, $event_id, $session) {
    // Send Slack notification for high-value conversions
    if (isset($goal['value']) && $goal['value'] > 1000) {
        wp_remote_post('https://hooks.slack.com/services/YOUR/WEBHOOK/URL', [
            'body' => json_encode([
                'text' => "🎉 High-value conversion: {$goal['name']} - $" . $goal['value']
            ])
        ]);
    }
}, 10, 4);
```

---

#### `tracksure_conversion_recorded`

**Fires when a conversion is recorded (used by multiple systems).**

```php
do_action('tracksure_conversion_recorded', $conversion_id, $goal_id, $event_data, $session);
```

**Parameters**:

- `$conversion_id` (int) - Conversion ID
- `$goal_id` (int) - Goal ID
- `$event_data` (array) - Event data that triggered conversion
- `$session` (array|null) - Session data (may be null for server-side events)

**Use Cases**:

- Log conversions
- Trigger automations
- Update external systems

**Example**:

```php
add_action('tracksure_conversion_recorded', function($conversion_id, $goal_id, $event_data) {
    // Update CRM
    if ($event_data['event_name'] === 'purchase') {
        update_crm_conversion($conversion_id, $event_data);
    }
}, 10, 3);
```

---

#### `tracksure_before_create_goal`

**Fires before a goal is created via REST API.**

```php
do_action('tracksure_before_create_goal', $goal_data, $request);
```

**Parameters**:

- `$goal_data` (array) - Goal data to be created
- `$request` (WP_REST_Request) - REST API request object

**Use Cases**:

- Validate goal data
- Add custom validations
- Log goal creation attempts

**Example**:

```php
add_action('tracksure_before_create_goal', function($goal_data, $request) {
    error_log('Creating goal: ' . $goal_data['name']);
}, 10, 2);
```

---

#### `tracksure_after_create_goal`

**Fires after a goal is successfully created.**

```php
do_action('tracksure_after_create_goal', $goal_id, $goal_data, $request);
```

**Parameters**:

- `$goal_id` (int) - New goal ID
- `$goal_data` (array) - Complete goal data
- `$request` (WP_REST_Request) - REST API request

**Use Cases**:

- Sync to external systems
- Send notifications
- Clear caches

**Example**:

```php
add_action('tracksure_after_create_goal', function($goal_id, $goal_data, $request) {
    // Clear goal cache
    wp_cache_delete('tracksure_active_goals', 'tracksure');

    // Notify admins
    wp_mail(
        get_option('admin_email'),
        'New Goal Created',
        "Goal '{$goal_data['name']}' created with ID {$goal_id}"
    );
}, 10, 3);
```

---

#### `tracksure_before_update_goal`

**Fires before a goal is updated.**

```php
do_action('tracksure_before_update_goal', $goal_data, $goal_id, $request);
```

**Parameters**:

- `$goal_data` (array) - Updated goal data
- `$goal_id` (int) - Goal ID being updated
- `$request` (WP_REST_Request) - REST API request

---

#### `tracksure_after_update_goal`

**Fires after a goal is updated.**

```php
do_action('tracksure_after_update_goal', $goal_id, $goal_data, $request);
```

**Parameters**:

- `$goal_id` (int) - Updated goal ID
- `$goal_data` (array) - Complete goal data
- `$request` (WP_REST_Request) - REST API request

---

#### `tracksure_before_delete_goal`

**Fires before a goal is deleted.**

```php
do_action('tracksure_before_delete_goal', $goal_id);
```

**Parameters**:

- `$goal_id` (int) - Goal ID being deleted

---

#### `tracksure_after_delete_goal`

**Fires after a goal is deleted.**

```php
do_action('tracksure_after_delete_goal', $goal_id);
```

**Parameters**:

- `$goal_id` (int) - Deleted goal ID

---

### **Sessions & Visitors**

#### `tracksure_session_started`

**Fires when a new session is created.**

```php
do_action('tracksure_session_started', $db_session_id, $visitor_id, $session_data, $is_returning, $session_number);
```

**Parameters**:

- `$db_session_id` (int) - Database session ID
- `$visitor_id` (int) - Visitor ID
- `$session_data` (array) - Complete session data
- `$is_returning` (bool) - Whether visitor is returning
- `$session_number` (int) - Session count for this visitor

**Use Cases**:

- Track session starts
- Initialize session-specific features
- Log visitor behavior

**Example**:

```php
add_action('tracksure_session_started', function($db_session_id, $visitor_id, $session_data, $is_returning, $session_number) {
    if ($session_number === 1) {
        // First-time visitor
        error_log("New visitor {$visitor_id} started session");
    } elseif ($session_number > 10) {
        // Loyal visitor
        error_log("Loyal visitor {$visitor_id} on session #{$session_number}");
    }
}, 10, 5);
```

---

#### `tracksure_visitor_created`

**Fires when a new visitor record is created.**

```php
do_action('tracksure_visitor_created', $visitor_id, $client_id);
```

**Parameters**:

- `$visitor_id` (int) - Database visitor ID
- `$client_id` (string) - Client ID (UUID)

**Use Cases**:

- Track new visitors
- Initialize visitor profiles
- Send welcome emails

---

#### `tracksure_visitor_updated`

**Fires when a visitor record is updated.**

```php
do_action('tracksure_visitor_updated', $visitor_id, $client_id);
```

**Parameters**:

- `$visitor_id` (int) - Visitor ID
- `$client_id` (string) - Client ID

---

### **Background Jobs**

#### `tracksure_outbox_processed`

**Fires when delivery worker processes outbox items.**

```php
do_action('tracksure_outbox_processed', $count);
```

**Parameters**:

- `$count` (int) - Number of items processed

**Use Cases**:

- Monitor delivery performance
- Log delivery stats
- Trigger follow-up jobs

**Example**:

```php
add_action('tracksure_outbox_processed', function($count) {
    update_option('tracksure_last_delivery', [
        'time' => time(),
        'count' => $count
    ]);
});
```

---

#### `tracksure_delivery_failed`

**Fires when delivery to a destination fails.**

```php
do_action('tracksure_delivery_failed', $item_id, $error);
```

**Parameters**:

- `$item_id` (int) - Outbox item ID
- `$error` (string|WP_Error) - Error message or WP_Error object

**Use Cases**:

- Log delivery failures
- Send admin alerts
- Implement custom retry logic

**Example**:

```php
add_action('tracksure_delivery_failed', function($item_id, $error) {
    // Alert on repeated failures
    $failures = get_transient("tracksure_failures_{$item_id}") ?: 0;
    $failures++;
    set_transient("tracksure_failures_{$item_id}", $failures, HOUR_IN_SECONDS);

    if ($failures > 5) {
        wp_mail(
            get_option('admin_email'),
            'TrackSure Delivery Failures',
            "Item {$item_id} has failed {$failures} times: " . $error
        );
    }
}, 10, 2);
```

---

#### `tracksure_hourly_aggregation_complete`

**Fires when hourly aggregation job completes.**

```php
do_action('tracksure_hourly_aggregation_complete', $hour_start, $result);
```

**Parameters**:

- `$hour_start` (string) - Hour timestamp (Y-m-d H:00:00)
- `$result` (array) - Aggregation results

**Use Cases**:

- Trigger custom aggregations
- Monitor data processing
- Clear caches

---

#### `tracksure_daily_aggregation_complete`

**Fires when daily aggregation job completes.**

```php
do_action('tracksure_daily_aggregation_complete', $date, $result);
```

**Parameters**:

- `$date` (string) - Date (Y-m-d)
- `$result` (array) - Aggregation results

---

### **Settings**

#### `tracksure_setting_changed`

**Fires when any setting is updated.**

```php
do_action('tracksure_setting_changed', $key, $old_value, $new_value);
```

**Parameters**:

- `$key` (string) - Setting key
- `$old_value` (mixed) - Previous value
- `$new_value` (mixed) - New value

**Use Cases**:

- React to setting changes
- Clear caches
- Validate settings

**Example**:

```php
add_action('tracksure_setting_changed', function($key, $old_value, $new_value) {
    if ($key === 'tracking_enabled') {
        // Clear all caches when tracking toggled
        wp_cache_flush();

        // Log the change
        error_log("TrackSure tracking " . ($new_value ? 'enabled' : 'disabled'));
    }
}, 10, 3);
```

---

#### `tracksure_tracking_toggled`

**Fires specifically when tracking is enabled/disabled.**

```php
do_action('tracksure_tracking_toggled', $enabled);
```

**Parameters**:

- `$enabled` (bool) - Whether tracking is now enabled

**Use Cases**:

- React to tracking state changes
- Clear event queues
- Notify administrators

---

#### `tracksure_rest_update_settings`

**Fires after settings are updated via REST API.**

```php
do_action('tracksure_rest_update_settings', $updated_settings, $request);
```

**Parameters**:

- `$updated_settings` (array) - Array of updated settings
- `$request` (WP_REST_Request) - REST API request

---

#### `tracksure_settings_batch_updated`

**Fires after multiple settings are updated in batch.**

```php
do_action('tracksure_settings_batch_updated', $changed_settings);
```

**Parameters**:

- `$changed_settings` (array) - Array of changed settings with old/new values

---

### **Admin UI**

#### `tracksure_register_admin_extensions`

**Hook for registering custom admin dashboard extensions.**

```php
do_action('tracksure_register_admin_extensions', $extensions_registry);
```

**Parameters**:

- `$extensions_registry` (array) - Extensions registry array (passed by reference)

**Use Cases**:

- Add custom admin pages
- Register dashboard widgets
- Add menu items

> **JS Component Registry**: Each extension registered via this hook should also expose its React components on `window.trackSureExtensionComponents` (renamed from the earlier `window.trackSureProComponents`). The admin shell resolves component names from this global registry. Use the spread-merge pattern to avoid overwriting other extensions:
>
> ```javascript
> window.trackSureExtensionComponents = {
>   ...(window.trackSureExtensionComponents || {}),
>   MyDashboardWidget,
>   MySettingsPanel,
> };
> ```

---

#### `tracksure_admin_enqueue_scripts`

**Fires when admin scripts are enqueued.**

```php
do_action('tracksure_admin_enqueue_scripts', $hook);
```

**Parameters**:

- `$hook` (string) - Current admin page hook

**Use Cases**:

- Enqueue custom admin scripts
- Add admin CSS
- Inject admin-side JavaScript

---

### **Integrations & Destinations**

#### `tracksure_load_integration_handlers`

**Hook for loading custom integration handlers.**

```php
do_action('tracksure_load_integration_handlers', $integrations_manager);
```

**Parameters**:

- `$integrations_manager` (TrackSure_Integrations_Manager) - Integrations manager instance

**Use Cases**:

- Register custom ecommerce integrations
- Load 3rd-party integration handlers

**Example**:

```php
add_action('tracksure_load_integration_handlers', function($manager) {
    // Register custom Shopify integration
    require_once plugin_dir_path(__FILE__) . 'integrations/class-shopify.php';
    $manager->register_handler('shopify', 'TrackSure_Shopify_Integration');
});
```

---

#### `tracksure_integration_handler_loaded`

**Fires when an integration handler is loaded.**

```php
do_action('tracksure_integration_handler_loaded', $integration_id, $handler);
```

**Parameters**:

- `$integration_id` (string) - Integration identifier
- `$handler` (object) - Integration handler instance

---

#### `tracksure_load_destination_handlers`

**Hook for loading custom destination handlers.**

```php
do_action('tracksure_load_destination_handlers', $destinations_manager);
```

**Parameters**:

- `$destinations_manager` (TrackSure_Destinations_Manager) - Destinations manager instance

**Use Cases**:

- Register custom ad platform destinations
- Load Pro destination handlers

**Example**:

```php
add_action('tracksure_load_destination_handlers', function($manager) {
    // Register TikTok Ads destination
    require_once plugin_dir_path(__FILE__) . 'destinations/class-tiktok.php';
    $manager->register_handler('tiktok', 'TrackSure_TikTok_Destination');
});
```

---

#### `tracksure_destination_handler_loaded`

**Fires when a destination handler is loaded.**

```php
do_action('tracksure_destination_handler_loaded', $destination_id, $handler);
```

**Parameters**:

- `$destination_id` (string) - Destination identifier
- `$handler` (object) - Destination handler instance

---

### **REST API**

#### `tracksure_rest_api_init`

**Fires when REST API is initialized.**

```php
do_action('tracksure_rest_api_init', $namespace);
```

**Parameters**:

- `$namespace` (string) - REST API namespace ('ts/v1')

**Use Cases**:

- Register custom REST endpoints
- Extend REST API

---

### **Rate Limiting**

#### `tracksure_rate_limit_exceeded`

**Fires when rate limit is exceeded.**

```php
do_action('tracksure_rate_limit_exceeded', $type, $identifier, $count);
```

**Parameters**:

- `$type` (string) - Limit type: 'client' or 'ip'
- `$identifier` (string) - Client ID or IP address
- `$count` (int) - Request count that exceeded limit

**Use Cases**:

- Log rate limit violations
- Implement custom blocking
- Send abuse alerts

**Example**:

```php
add_action('tracksure_rate_limit_exceeded', function($type, $identifier, $count) {
    error_log("Rate limit exceeded: {$type} {$identifier} ({$count} requests)");

    // Block persistent abusers
    if ($count > 1000) {
        update_option('tracksure_blocked_' . $type, [
            $identifier => time()
        ]);
    }
}, 10, 3);
```

---

## 🔍 **Filter Hooks**

### **Event Filters**

#### `tracksure_enrich_event_data`

**Filter to enrich event data before recording.**

```php
$enriched = apply_filters('tracksure_enrich_event_data', $event_data, $session);
```

**Parameters**:

- `$event_data` (array) - Original event data
- `$session` (array) - Session data

**Returns**: `array` - Enriched event data

**Use Cases**:

- Add custom parameters to all events
- Inject user data
- Add server-side enrichment

**Example**:

```php
add_filter('tracksure_enrich_event_data', function($event_data, $session) {
    // Add custom user role to all events
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $event_data['user_role'] = $user->roles[0] ?? 'unknown';
    }

    // Add custom business data
    $event_data['custom_params'] = [
        'site_id' => get_current_blog_id(),
        'locale' => get_locale()
    ];

    return $event_data;
}, 10, 2);
```

---

#### `tracksure_detect_browser`

**Filter to customize browser detection.**

```php
$browser = apply_filters('tracksure_detect_browser', 'Unknown', $user_agent);
```

**Parameters**:

- `$browser` (string) - Default browser name
- `$user_agent` (string) - User agent string

**Returns**: `string` - Browser name

**Use Cases**:

- Improve browser detection
- Add custom browser names
- Override default detection

---

#### `tracksure_is_bot`

**Filter to detect bots/crawlers.**

```php
$is_bot = apply_filters('tracksure_is_bot', false, $user_agent);
```

**Parameters**:

- `$is_bot` (bool) - Default bot detection result
- `$user_agent` (string) - User agent string

**Returns**: `bool` - True if bot detected

**Use Cases**:

- Add custom bot detection
- Whitelist specific bots
- Blacklist user agents

**Example**:

```php
add_filter('tracksure_is_bot', function($is_bot, $user_agent) {
    // Detect custom crawler
    if (stripos($user_agent, 'MyCustomBot') !== false) {
        return true;
    }

    return $is_bot;
}, 10, 2);
```

---

#### `tracksure_enabled_destinations`

**Filter to control which destinations receive an event.**

```php
$destinations = apply_filters('tracksure_enabled_destinations', [], $event_data, $session);
```

**Parameters**:

- `$destinations` (array) - Array of destination IDs
- `$event_data` (array) - Event data
- `$session` (array) - Session data

**Returns**: `array` - Array of enabled destination IDs

**Use Cases**:

- Conditional destination routing
- Event-specific destinations
- A/B testing destinations

**Example**:

```php
add_filter('tracksure_enabled_destinations', function($destinations, $event_data, $session) {
    // Only send purchases to Meta
    if ($event_data['event_name'] === 'purchase') {
        $destinations[] = 'meta';
    }

    // Only send high-value events to Google Ads
    if (isset($event_data['event_params']['value']) && $event_data['event_params']['value'] > 100) {
        $destinations[] = 'google-ads';
    }

    return array_unique($destinations);
}, 10, 3);
```

---

### **Consent & Tracking**

#### `tracksure_should_track_user`

**Filter to control whether a user should be tracked.**

```php
$should_track = apply_filters('tracksure_should_track_user', true);
```

**Parameters**:

- `$should_track` (bool) - Default tracking permission

**Returns**: `bool` - True to allow tracking

**Use Cases**:

- Integrate with consent management platforms
- Implement custom tracking logic
- Exclude specific user roles

**Example**:

```php
add_filter('tracksure_should_track_user', function($should_track) {
    // Don't track administrators
    if (current_user_can('administrator')) {
        return false;
    }

    // Check custom consent cookie
    if (!isset($_COOKIE['my_consent']) || $_COOKIE['my_consent'] !== 'yes') {
        return false;
    }

    return $should_track;
});
```

---

#### `tracksure_consent_given`

**Filter for checking if user has given consent.**

```php
$consent_given = apply_filters('tracksure_consent_given', false);
```

**Parameters**:

- `$consent_given` (bool) - Default consent state

**Returns**: `bool` - True if consent given

**Use Cases**:

- Integrate with Cookiebot
- Integrate with OneTrust
- Custom consent platforms

**Example**:

```php
add_filter('tracksure_consent_given', function($consent_given) {
    // Check Cookiebot consent
    if (function_exists('cookiebot_is_category_accepted')) {
        return cookiebot_is_category_accepted('marketing');
    }

    return $consent_given;
});
```

---

#### `tracksure_consent_denied`

**Filter for checking if user has explicitly denied consent.**

```php
$consent_denied = apply_filters('tracksure_consent_denied', false);
```

**Parameters**:

- `$consent_denied` (bool) - Default denial state

**Returns**: `bool` - True if consent denied

---

#### `tracksure_anonymize_event`

**Filter to anonymize event data when consent is limited.**

```php
$anonymized = apply_filters('tracksure_anonymize_event', $event_data);
```

**Parameters**:

- `$event_data` (array) - Original event data

**Returns**: `array` - Anonymized event data

**Use Cases**:

- GDPR compliance
- Remove PII
- Mask sensitive data

**Example**:

```php
add_filter('tracksure_anonymize_event', function($event_data) {
    // Remove PII
    unset($event_data['user_email']);
    unset($event_data['user_name']);

    // Mask IP address
    if (isset($event_data['ip_address'])) {
        $event_data['ip_address'] = preg_replace('/\.\d+$/', '.0', $event_data['ip_address']);
    }

    return $event_data;
});
```

---

#### `tracksure_user_country`

**Filter to determine user's country.**

```php
$country = apply_filters('tracksure_user_country', 'XX', $client_ip);
```

**Parameters**:

- `$country` (string) - Default country code
- `$client_ip` (string) - Client IP address

**Returns**: `string` - ISO country code

**Use Cases**:

- Custom geo-location
- Integrate external geo services
- Override IP detection

---

#### `tracksure_is_california_user`

**Filter to detect California users (CCPA compliance).**

```php
$is_california = apply_filters('tracksure_is_california_user', false);
```

**Parameters**:

- `$is_california` (bool) - Default detection result

**Returns**: `bool` - True if California user

---

### **Data Transformation**

#### `tracksure_transform_value`

**Filter to transform event parameter values during mapping.**

```php
$transformed = apply_filters('tracksure_transform_value', $value, $transform_type, $destination);
```

**Parameters**:

- `$value` (mixed) - Original value
- `$transform_type` (string) - Transform type (e.g., 'currency', 'boolean')
- `$destination` (string) - Destination ID

**Returns**: `mixed` - Transformed value

**Use Cases**:

- Custom value transformations
- Currency conversion
- Data normalization

---

#### `tracksure_apply_special_transform`

**Filter for special transformation rules.**

```php
$mapped = apply_filters('tracksure_apply_special_transform', $mapped_data, $transform_rule, $destination, $original_event);
```

**Parameters**:

- `$mapped_data` (array) - Mapped data
- `$transform_rule` (array) - Transformation rule
- `$destination` (string) - Destination ID
- `$original_event` (array) - Original event data

**Returns**: `array` - Transformed data

---

#### `tracksure_deliver_mapped_event`

**Filter to modify mapped event before delivery.**

```php
$result = apply_filters('tracksure_deliver_mapped_event', $result, $destination, $event);
```

**Parameters**:

- `$result` (array|WP_Error) - Delivery result
- `$destination` (string) - Destination ID
- `$event` (array) - Mapped event data

**Returns**: `array|WP_Error` - Modified result

**Use Cases**:

- Intercept delivery
- Modify destination payloads
- Custom delivery logic

---

### **Goals & Conversions (Filters)**

#### `tracksure_allowed_trigger_types`

**Filter allowed goal trigger types.**

```php
$allowed = apply_filters('tracksure_allowed_trigger_types', $trigger_types);
```

**Parameters**:

- `$trigger_types` (array) - Default allowed trigger types

**Returns**: `array` - Allowed trigger types

**Use Cases**:

- Add custom trigger types
- Restrict trigger types
- Module-specific triggers

---

#### `tracksure_allowed_operators`

**Filter allowed goal condition operators.**

```php
$allowed = apply_filters('tracksure_allowed_operators', $operators);
```

**Parameters**:

- `$operators` (array) - Default operators

**Returns**: `array` - Allowed operators

---

#### `tracksure_goal_validation_errors`

**Filter to add custom goal validation errors.**

```php
$errors = apply_filters('tracksure_goal_validation_errors', [], $goal_data);
```

**Parameters**:

- `$errors` (array) - Existing errors
- `$goal_data` (array) - Goal data being validated

**Returns**: `array` - Validation errors

**Example**:

```php
add_filter('tracksure_goal_validation_errors', function($errors, $goal_data) {
    // Require description for high-value goals
    if (isset($goal_data['value']) && $goal_data['value'] > 1000) {
        if (empty($goal_data['description'])) {
            $errors[] = 'High-value goals must have a description';
        }
    }

    return $errors;
}, 10, 2);
```

---

#### `tracksure_active_goals`

**Filter active goals list.**

```php
$goals = apply_filters('tracksure_active_goals', $active_goals);
```

**Parameters**:

- `$active_goals` (array) - Active goals from database

**Returns**: `array` - Filtered active goals

**Use Cases**:

- Add programmatic goals
- Filter goals by context
- Override goal list

---

#### `tracksure_goal_custom_trigger_eval`

**Filter for custom goal trigger evaluation.**

```php
$result = apply_filters('tracksure_goal_custom_trigger_eval', null, $goal, $event_data, $event_params);
```

**Parameters**:

- `$result` (bool|null) - Evaluation result (null = not handled)
- `$goal` (array) - Goal configuration
- `$event_data` (array) - Event data
- `$event_params` (array) - Event parameters

**Returns**: `bool|null` - True if goal triggered, null to continue default evaluation

**Use Cases**:

- Implement custom trigger logic
- Complex trigger conditions
- Third-party goal integrations

---

#### `tracksure_goal_custom_operator`

**Filter for custom goal condition operators.**

```php
$result = apply_filters('tracksure_goal_custom_operator', null, $operator, $actual_value, $expected_value);
```

**Parameters**:

- `$result` (bool|null) - Operator result (null = not handled)
- `$operator` (string) - Operator name
- `$actual_value` (mixed) - Actual value
- `$expected_value` (mixed) - Expected value

**Returns**: `bool|null` - Comparison result

**Example**:

```php
add_filter('tracksure_goal_custom_operator', function($result, $operator, $actual, $expected) {
    if ($operator === 'in_array') {
        return in_array($actual, (array) $expected);
    }

    if ($operator === 'regex') {
        return (bool) preg_match($expected, $actual);
    }

    return $result; // Not handled, continue
}, 10, 4);
```

---

#### `tracksure_goal_conversion_value`

**Filter goal conversion value.**

```php
$value = apply_filters('tracksure_goal_conversion_value', $conversion_value, $goal, $event_data, $session);
```

**Parameters**:

- `$conversion_value` (float) - Calculated conversion value
- `$goal` (array) - Goal configuration
- `$event_data` (array) - Event data
- `$session` (array) - Session data

**Returns**: `float` - Modified conversion value

---

#### `tracksure_goal_conversion_data`

**Filter complete conversion data before recording.**

```php
$data = apply_filters('tracksure_goal_conversion_data', $conversion_data, $goal, $event_data, $session);
```

**Parameters**:

- `$conversion_data` (array) - Conversion data to record
- `$goal` (array) - Goal configuration
- `$event_data` (array) - Event data
- `$session` (array) - Session data

**Returns**: `array` - Modified conversion data

---

### **Query & Analytics**

#### `tracksure_query_overview`

**Filter overview query results.**

```php
$response = apply_filters('tracksure_query_overview', $response, $date_start, $date_end);
```

**Parameters**:

- `$response` (array) - Overview metrics
- `$date_start` (string) - Start date
- `$date_end` (string) - End date

**Returns**: `array` - Modified overview data

---

#### `tracksure_query_realtime`

**Filter real-time data query results.**

```php
$data = apply_filters('tracksure_query_realtime', $realtime_data);
```

**Parameters**:

- `$realtime_data` (array) - Real-time metrics

**Returns**: `array` - Modified real-time data

---

#### `tracksure_query_journey`

**Filter session journey data.**

```php
$journey = apply_filters('tracksure_query_journey', $journey, $session_id);
```

**Parameters**:

- `$journey` (array) - Session journey events
- `$session_id` (int) - Session ID

**Returns**: `array` - Modified journey data

---

#### `tracksure_query_visitor_journey`

**Filter visitor journey data (all sessions).**

```php
$journey = apply_filters('tracksure_query_visitor_journey', $journey, $visitor_id);
```

**Parameters**:

- `$journey` (array) - Complete visitor journey
- `$visitor_id` (int) - Visitor ID

**Returns**: `array` - Modified journey data

---

#### `tracksure_query_funnel_data`

**Filter funnel analysis data.**

```php
$funnel_data = apply_filters('tracksure_query_funnel_data', $data, $funnel_id, $date_start, $date_end);
```

**Parameters**:

- `$data` (array) - Funnel metrics
- `$funnel_id` (int) - Funnel ID
- `$date_start` (string) - Start date
- `$date_end` (string) - End date

**Returns**: `array` - Modified funnel data

---

#### `tracksure_query_registry_data`

**Filter registry query data.**

```php
$registry_data = apply_filters('tracksure_query_registry_data', $data, $type);
```

**Parameters**:

- `$data` (array) - Registry data (events/parameters)
- `$type` (string) - Registry type ('events' or 'parameters')

**Returns**: `array` - Modified registry data

---

### **Settings & Schema**

#### `tracksure_settings_schema`

**Filter complete settings schema.**

```php
$schema = apply_filters('tracksure_settings_schema', $settings);
```

**Parameters**:

- `$settings` (array) - Settings schema

**Returns**: `array` - Modified schema

**Use Cases**:

- Add custom settings
- Modify setting defaults
- Add setting validation

**Example**:

```php
add_filter('tracksure_settings_schema', function($settings) {
    // Add custom setting
    $settings['custom_setting'] = [
        'type' => 'boolean',
        'default' => false,
        'label' => 'Enable Custom Feature',
        'description' => 'Enables custom tracking feature'
    ];

    return $settings;
});
```

---

#### `tracksure_rest_get_settings`

**Filter settings retrieved via REST API.**

```php
$settings = apply_filters('tracksure_rest_get_settings', $settings);
```

**Parameters**:

- `$settings` (array) - Settings array

**Returns**: `array` - Filtered settings

---

#### `tracksure_rest_allow_setting`

**Filter to allow/deny specific setting updates via REST API.**

```php
$allowed = apply_filters('tracksure_rest_allow_setting', false, $key, $value);
```

**Parameters**:

- `$allowed` (bool) - Default permission
- `$key` (string) - Setting key
- `$value` (mixed) - New value

**Returns**: `bool` - True to allow update

**Example**:

```php
add_filter('tracksure_rest_allow_setting', function($allowed, $key, $value) {
    // Only admins can change critical settings
    if (in_array($key, ['api_key', 'secret_key'])) {
        return current_user_can('manage_options');
    }

    return $allowed;
}, 10, 3);
```

---

### **Registry & Detection**

#### `tracksure_loaded_events`

**Filter loaded event registry.**

```php
$events = apply_filters('tracksure_loaded_events', $events);
```

**Parameters**:

- `$events` (array) - Event registry

**Returns**: `array` - Modified event registry

**Use Cases**:

- Add custom events to registry
- Modify event definitions
- Remove events

---

#### `tracksure_loaded_parameters`

**Filter loaded parameter registry.**

```php
$parameters = apply_filters('tracksure_loaded_parameters', $parameters);
```

**Parameters**:

- `$parameters` (array) - Parameter registry

**Returns**: `array` - Modified parameter registry

---

#### `tracksure_integrations_detection_registry`

**Filter integrations detection registry (for auto-detection).**

```php
$integrations = apply_filters('tracksure_integrations_detection_registry', $integrations_to_detect);
```

**Parameters**:

- `$integrations_to_detect` (array) - Integration detection rules

**Returns**: `array` - Modified detection registry

**Example**:

```php
add_filter('tracksure_integrations_detection_registry', function($integrations) {
    // Add Shopify detection
    $integrations['shopify'] = [
        'name' => 'Shopify',
        'detect' => function() {
            return class_exists('ShopifyAPI');
        }
    ];

    return $integrations;
});
```

---

#### `tracksure_rest_controllers`

**Filter REST API controllers before registration.**

```php
$controllers = apply_filters('tracksure_rest_controllers', $controllers);
```

**Parameters**:

- `$controllers` (array) - Controller instances

**Returns**: `array` - Modified controllers

---

### **Rate Limiting**

#### `tracksure_rate_limits`

**Filter rate limit configuration.**

```php
$limits = apply_filters('tracksure_rate_limits', $default_limits);
```

**Parameters**:

- `$default_limits` (array) - Default rate limits

**Returns**: `array` - Modified rate limits

**Example**:

```php
add_filter('tracksure_rate_limits', function($limits) {
    // Increase limits for Pro users
    if (tracksure_is_pro_active()) {
        $limits['events_per_client'] = 10000;
        $limits['events_per_ip'] = 5000;
    }

    return $limits;
});
```

---

### **Asset Loading**

#### `tracksure_should_enqueue_tracker`

**Filter whether to enqueue tracking script.**

```php
$should_enqueue = apply_filters('tracksure_should_enqueue_tracker', true);
```

**Parameters**:

- `$should_enqueue` (bool) - Default enqueue state

**Returns**: `bool` - True to enqueue tracker

**Use Cases**:

- Disable tracking on specific pages
- Conditional script loading
- Performance optimization

**Example**:

```php
add_filter('tracksure_should_enqueue_tracker', function($should_enqueue) {
    // Don't load on login page
    if (is_page('login')) {
        return false;
    }

    // Don't load for logged-in admins
    if (current_user_can('administrator')) {
        return false;
    }

    return $should_enqueue;
});
```

---

#### `tracksure_auto_track`

**Filter whether automatic page view tracking is enabled.**

```php
$auto_track = apply_filters('tracksure_auto_track', true);
```

**Parameters**:

- `$auto_track` (bool) - Default auto-track state

**Returns**: `bool` - True to enable auto-tracking

---

### **Proxy & Security**

#### `tracksure_trusted_proxies`

**Filter trusted proxy list for IP detection.**

```php
$proxies = apply_filters('tracksure_trusted_proxies', $trusted_proxies);
```

**Parameters**:

- `$trusted_proxies` (array) - Default trusted proxy IPs

**Returns**: `array` - Modified proxy list

**Use Cases**:

- Add Cloudflare IPs
- Add load balancer IPs
- Custom proxy configuration

**Example**:

```php
add_filter('tracksure_trusted_proxies', function($proxies) {
    // Add Cloudflare IPs
    $proxies[] = '103.21.244.0/22';
    $proxies[] = '103.22.200.0/22';

    return $proxies;
});
```

---

## ⚡ **Hook Priority Guidelines**

**Standard Priorities**:

| Priority | Use Case                          |
| -------- | --------------------------------- |
| `4`      | Module registration (before init) |
| `5`      | Early initialization              |
| `10`     | **DEFAULT** - Most hooks          |
| `15`     | After default processing          |
| `20`     | Late processing                   |
| `99`     | Final modifications               |
| `999`    | Absolute last                     |

**Examples**:

```php
// Early - Run before TrackSure processes
add_filter('tracksure_enrich_event_data', 'my_function', 5);

// Default - Normal processing
add_action('tracksure_event_recorded', 'my_function', 10);

// Late - Run after TrackSure finishes
add_action('tracksure_event_recorded', 'my_function', 20);
```

---

## 💡 **Common Use Cases**

### **1. Integrate with Consent Management Platform**

```php
// Cookiebot Integration
add_filter('tracksure_consent_given', function($consent_given) {
    if (function_exists('cookiebot_is_category_accepted')) {
        return cookiebot_is_category_accepted('marketing');
    }
    return $consent_given;
});

add_filter('tracksure_should_track_user', function($should_track) {
    if (function_exists('cookiebot_is_category_accepted')) {
        return cookiebot_is_category_accepted('statistics');
    }
    return $should_track;
});
```

---

### **2. Add Custom Event Parameters**

```php
add_filter('tracksure_enrich_event_data', function($event_data, $session) {
    // Add membership tier
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $event_data['membership_tier'] = get_user_meta($user_id, 'membership_tier', true);
    }

    // Add A/B test variant
    if (isset($_COOKIE['ab_test_variant'])) {
        $event_data['ab_variant'] = $_COOKIE['ab_test_variant'];
    }

    return $event_data;
}, 10, 2);
```

---

### **3. Send High-Value Conversions to Slack**

```php
add_action('tracksure_goal_conversion', function($conversion_id, $goal, $event_id, $session) {
    // High-value conversions
    if (isset($goal['value']) && $goal['value'] >= 500) {
        $message = sprintf(
            "🎉 High-Value Conversion!\nGoal: %s\nValue: $%.2f",
            $goal['name'],
            $goal['value']
        );

        wp_remote_post('https://hooks.slack.com/services/YOUR/WEBHOOK', [
            'body' => json_encode(['text' => $message])
        ]);
    }
}, 10, 4);
```

---

### **4. Custom Bot Detection**

```php
add_filter('tracksure_is_bot', function($is_bot, $user_agent) {
    $custom_bots = [
        'MyCustomCrawler',
        'InternalMonitor',
        'QA-Bot'
    ];

    foreach ($custom_bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            return true;
        }
    }

    return $is_bot;
}, 10, 2);
```

---

### **5. Conditional Destination Routing**

```php
add_filter('tracksure_enabled_destinations', function($destinations, $event_data, $session) {
    // Only send purchases to Meta
    if ($event_data['event_name'] === 'purchase') {
        $destinations[] = 'meta';
    }

    // Send all events to GA4
    $destinations[] = 'ga4';

    // High-value events to Google Ads
    if (isset($event_data['event_params']['value'])) {
        $value = $event_data['event_params']['value'];
        if ($value > 100) {
            $destinations[] = 'google-ads';
        }
    }

    return array_unique($destinations);
}, 10, 3);
```

---

### **6. Custom Goal Trigger**

```php
add_filter('tracksure_goal_custom_trigger_eval', function($result, $goal, $event_data, $event_params) {
    // Custom trigger: "High Cart Value"
    if ($goal['trigger_type'] === 'custom_high_cart_value') {
        if ($event_data['event_name'] === 'add_to_cart') {
            $cart_total = WC()->cart->get_cart_contents_total();
            return $cart_total > 200;
        }
    }

    return $result; // Not handled
}, 10, 4);
```

---

### **7. Register Custom Module**

```php
add_action('init', function() {
    if (function_exists('tracksure_core')) {
        do_action('tracksure_register_module', 'my-custom-module', __FILE__, [
            'name' => 'My Custom Module',
            'version' => '1.0.0',
            'capabilities' => [
                'integrations' => ['custom-platform'],
                'destinations' => ['custom-ads']
            ]
        ]);
    }
}, 4); // Priority 4 - before module initialization
```

---

### **8. Log All Events (Debugging)**

```php
add_action('tracksure_event_recorded', function($event_id, $event_data, $session) {
    error_log(sprintf(
        'TrackSure Event #%d: %s - Session #%d',
        $event_id,
        $event_data['event_name'],
        $session['id'] ?? 0
    ));
}, 10, 3);
```

---

### **9. Modify Settings Schema**

```php
add_filter('tracksure_settings_schema', function($settings) {
    // Add custom setting
    $settings['enable_advanced_tracking'] = [
        'type' => 'boolean',
        'default' => false,
        'label' => 'Enable Advanced Tracking',
        'description' => 'Enables additional tracking features',
        'group' => 'advanced'
    ];

    return $settings;
});
```

---

### **10. Custom Geo-Location**

```php
add_filter('tracksure_user_country', function($country, $ip) {
    // Use MaxMind GeoIP
    if (class_exists('GeoIP')) {
        $gi = geoip_open('/path/to/GeoIP.dat', GEOIP_STANDARD);
        $country = geoip_country_code_by_addr($gi, $ip);
        geoip_close($gi);
    }

    return $country;
}, 10, 2);
```

---

## 📝 **Code Examples**

### **Complete Integration Example**

```php
<?php
/**
 * TrackSure Custom Integration
 */

// 1. Register Module
add_action('init', function() {
    do_action('tracksure_register_module', 'my-integration', __FILE__, [
        'name' => 'My Integration',
        'version' => '1.0.0'
    ]);
}, 4);

// 2. Load Integration Handler
add_action('tracksure_load_integration_handlers', function($manager) {
    require_once __DIR__ . '/class-my-integration.php';
    $manager->register_handler('my-platform', 'My_Platform_Integration');
});

// 3. Enrich Events
add_filter('tracksure_enrich_event_data', function($event_data, $session) {
    $event_data['custom_field'] = 'custom_value';
    return $event_data;
}, 10, 2);

// 4. Handle Conversions
add_action('tracksure_goal_conversion', function($conversion_id, $goal, $event_id, $session) {
    // Send to external system
    my_external_api_call($conversion_id, $goal);
}, 10, 4);

// 5. Custom Settings
add_filter('tracksure_settings_schema', function($settings) {
    $settings['my_api_key'] = [
        'type' => 'string',
        'default' => '',
        'label' => 'My API Key'
    ];
    return $settings;
});
```

---

## 🎯 **Best Practices**

1. **Always return values in filters** - Don't forget to return the modified value
2. **Use correct priority** - Default 10 is usually fine, use lower for early processing
3. **Check if parameters exist** - Use `isset()` before accessing array values
4. **Document your hooks** - Add comments explaining what your hook does
5. **Test thoroughly** - Hooks can affect core functionality
6. **Use proper namespacing** - Prefix your function names
7. **Remove hooks when deactivating** - Clean up after yourself

---

## 🚀 **Need More?**

- **See**: [CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md) - System architecture
- **See**: [MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md) - Creating modules
- **See**: [ADAPTER_DEVELOPMENT.md](ADAPTER_DEVELOPMENT.md) - Creating adapters
- **See**: [DESTINATION_DEVELOPMENT.md](DESTINATION_DEVELOPMENT.md) - Creating destinations
- **See**: [REST_API_REFERENCE.md](REST_API_REFERENCE.md) - REST API endpoints
- **See**: [CLASS_REFERENCE.md](CLASS_REFERENCE.md) - Class-by-class reference

---

**Questions?** Check other documentation files or review the code examples above!
