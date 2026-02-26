# 🎓 Junior Developer Guide

Welcome to TrackSure! This guide will help you get up and running as a junior developer on the TrackSure project.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Your First Day](#your-first-day)
3. [Understanding the Codebase](#understanding-the-codebase)
4. [Making Your First Change](#making-your-first-change)
5. [Common Tasks](#common-tasks)
6. [Testing Your Changes](#testing-your-changes)
7. [Debugging](#debugging)
8. [Getting Help](#getting-help)

---

## Getting Started

### Prerequisites

Before you start, make sure you have:

- ✅ **Local WordPress Environment** (Local by Flywheel, XAMPP, or similar)
- ✅ **PHP 7.4+** installed
- ✅ **Node.js 16+** and npm installed
- ✅ **Code Editor** (VS Code recommended)
- ✅ **Git** for version control

### Recommended VS Code Extensions

Install these extensions to make your life easier:

```
1. PHP Intelephense - PHP autocomplete
2. ESLint - JavaScript linting
3. Prettier - Code formatting
4. WordPress Snippets - WordPress code snippets
5. GitLens - Git visualization
```

---

## Your First Day

### Step 1: Clone the Repository

```bash
# Navigate to your WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Clone TrackSure
git clone https://github.com/your-company/tracksure.git
cd tracksure
```

### Step 2: Install Dependencies

```bash
# Install Node.js dependencies (for React admin)
npm install

# Navigate to admin folder
cd admin
npm install
cd ..
```

### Step 3: Activate the Plugin

1. Open your WordPress admin panel
2. Go to **Plugins** → **Installed Plugins**
3. Find **TrackSure** and click **Activate**

### Step 4: Verify Installation

1. Check for a new **TrackSure** menu in the WordPress admin sidebar
2. Click it - you should see the React admin dashboard
3. Check **WordPress** → **Tools** → **Site Health** → **Info** → look for TrackSure database tables

**Expected Database Tables** (15 total):

```
wp_tracksure_visitors
wp_tracksure_sessions
wp_tracksure_events
wp_tracksure_goals
wp_tracksure_conversions
wp_tracksure_touchpoints
wp_tracksure_conversion_attribution
wp_tracksure_outbox
wp_tracksure_click_ids
wp_tracksure_agg_hourly
wp_tracksure_agg_daily
wp_tracksure_agg_product_daily
wp_tracksure_funnels
wp_tracksure_funnel_steps
wp_tracksure_logs
```

### Step 5: Enable Debug Mode

Add this to your `wp-config.php`:

```php
// Enable WordPress debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// TrackSure-specific debug
define('TRACKSURE_DEBUG', true);
```

Debug logs will go to: `wp-content/debug.log`

---

## Understanding the Codebase

### Directory Structure Quick Reference

```
tracksure/
├── tracksure.php              ← START HERE (main plugin file)
├── includes/
│   ├── core/                  ← Core engine (read this first)
│   │   ├── class-tracksure-core.php          ← Service container
│   │   ├── class-tracksure-db.php            ← Database layer
│   │   ├── services/          ← Business logic (22 services)
│   │   ├── api/               ← REST API controllers
│   │   └── ...
│   └── free/                  ← Free module features
│       ├── destinations/      ← GA4, Meta destinations
│       └── integrations/      ← WooCommerce integration
├── admin/                     ← React admin interface
│   ├── src/                   ← TypeScript source (development)
│   └── dist/                  ← Compiled bundle (production)
├── assets/                    ← Public assets
│   └── js/
│       ├── ts-web.js          ← Browser tracking SDK
│       ├── ts-currency.js     ← Currency detection
│       ├── ts-minicart.js     ← Mini-cart tracking
│       └── consent-listeners.js ← Consent change listeners
└── registry/                  ← Event definitions (JSON)
```

### Reading Order (Start Here)

**Day 1**: Understand the bootstrap

1. Read `tracksure.php` (main file) - 200 lines
2. Read `includes/core/class-tracksure-core.php` (lines 1-300) - Service container
3. Read [CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md) - Architecture overview

**Day 2**: Understand data flow 4. Read `includes/core/class-tracksure-db.php` (lines 1-200) - Database operations 5. Read `includes/core/services/class-tracksure-event-recorder.php` - Event recording 6. Read [CODE_WALKTHROUGH.md](CODE_WALKTHROUGH.md) - Complete flow example

**Day 3**: Understand integrations 7. Read `includes/free/integrations/class-tracksure-woocommerce-v2.php` - WooCommerce integration 8. Read `includes/free/adapters/class-tracksure-woocommerce-adapter.php` - Data extraction 9. Test: Place a test order and watch the debug log

**Day 4**: Understand destinations 10. Read `includes/free/destinations/class-tracksure-ga4-destination.php` - GA4 integration 11. Read `includes/core/destinations/class-tracksure-destinations-manager.php` - Manager 12. Test: Fire a test event and check the outbox table

**Day 5**: Understand admin 13. Read `admin/src/App.tsx` - React app entry 14. Read `admin/src/pages/Overview.tsx` - Dashboard page 15. Test: Make a small UI change

---

## Making Your First Change

### Task: Add a Debug Log Statement

Let's add a simple debug log to see events being recorded.

**Step 1: Find the File**

```bash
# Open this file
includes/core/services/class-tracksure-event-recorder.php
```

**Step 2: Find the `record()` Method**

Look for this method (around line 50):

```php
public function record($event_data, $session = null) {
    // existing code...
}
```

**Step 3: Add Your Debug Log**

Add this at the beginning of the method:

```php
public function record($event_data, $session = null) {
    // 🆕 YOUR DEBUG LOG (add this)
    if (defined('TRACKSURE_DEBUG') && TRACKSURE_DEBUG) {
        error_log('[TrackSure] Recording event: ' . $event_data['event_name']);
        error_log('[TrackSure] Event data: ' . print_r($event_data, true));
    }

    // existing code continues...
}
```

**Step 4: Test Your Change**

1. Clear your debug log: `wp-content/debug.log`
2. Place a test order on your site
3. Check the debug log - you should see:

```
[TrackSure] Recording event: purchase
[TrackSure] Event data: Array
(
    [event_name] => purchase
    [event_id] => evt-12345
    [params] => Array
    (
        [transaction_id] => 123
        [value] => 99.99
        ...
    )
)
```

**✅ Congratulations!** You just made your first code change!

---

### Task: Change Admin Dashboard Title

Let's make a small change to the React admin interface.

**Step 1: Navigate to Admin Source**

```bash
cd admin/src/pages
```

**Step 2: Open Overview Page**

```typescript
// admin/src/pages/Overview.tsx

export function Overview() {
  return (
    <div>
      <h1>Dashboard Overview</h1> {/* ← Change this */}
      ...
    </div>
  );
}
```

**Step 3: Make Your Change**

```typescript
export function Overview() {
  return (
    <div>
      <h1>🎯 Analytics Dashboard</h1> {/* ← Changed! */}
      ...
    </div>
  );
}
```

**Step 4: Rebuild React Admin**

```bash
# From the admin folder
cd admin
npm run build

# This creates: admin/dist/tracksure-admin.js
```

**Step 5: Test Your Change**

1. Refresh the TrackSure admin page in WordPress
2. You should see "🎯 Analytics Dashboard" instead of "Dashboard Overview"

**✅ Success!** You just modified the React admin!

---

## Common Tasks

### Task 1: Add a New Custom Event

**Scenario**: Track when users download a PDF file.

**Step 1: Define Event in Registry**

Edit `registry/events.json`:

```json
{
  "events": [
    // ... existing events ...
    {
      "name": "pdf_download",
      "display_name": "PDF Download",
      "category": "engagement",
      "description": "User downloaded a PDF file",
      "required_params": ["file_name", "file_url"],
      "optional_params": ["file_size"]
    }
  ]
}
```

**Step 2: Track the Event**

Create a new file: `includes/free/integrations/class-tracksure-pdf-downloads.php`

```php
<?php
/**
 * Track PDF downloads
 */
class TrackSure_PDF_Downloads {
    private $core;

    public function __construct($core) {
        $this->core = $core;

        // Hook into WordPress download tracking
        add_action('wp_ajax_download_pdf', array($this, 'track_download'));
        add_action('wp_ajax_nopriv_download_pdf', array($this, 'track_download'));
    }

    public function track_download() {
        // Get file info from request
        $file_name = sanitize_text_field($_POST['file_name']);
        $file_url = esc_url_raw($_POST['file_url']);

        // Build event
        $event_builder = $this->core->get_service('event_builder');
        $event = $event_builder->build_event('pdf_download', array(
            'file_name' => $file_name,
            'file_url' => $file_url,
        ));

        // Record event
        $event_recorder = $this->core->get_service('event_recorder');
        $event_recorder->record($event);

        wp_send_json_success();
    }
}
```

**Step 3: Initialize Your Class**

Add to `includes/free/class-tracksure-free.php`:

```php
public function init() {
    // ... existing code ...

    // 🆕 Add PDF downloads tracking
    require_once TRACKSURE_FREE_DIR . 'integrations/class-tracksure-pdf-downloads.php';
    new TrackSure_PDF_Downloads($this->core);
}
```

**Step 4: Test**

1. Clear registry cache: `wp transient delete tracksure_registry_cache`
2. Trigger a PDF download
3. Check database: `SELECT * FROM wp_tracksure_events WHERE event_name = 'pdf_download'`

---

### Task 2: Add a New REST API Endpoint

**Scenario**: Create an endpoint to get total events count.

**Step 1: Create Controller**

Create file: `includes/core/api/class-tracksure-rest-stats-controller.php`

```php
<?php
/**
 * REST API: Stats Controller
 */
class TrackSure_REST_Stats_Controller extends TrackSure_REST_Controller {

    public function register_routes() {
        register_rest_route($this->namespace, '/stats/total-events', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_total_events'),
            'permission_callback' => array($this, 'admin_permissions_check'),
        ));
    }

    public function get_total_events($request) {
        global $wpdb;

        $table = $wpdb->prefix . 'tracksure_events';
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return $this->success(array(
            'total_events' => (int) $total,
        ));
    }
}
```

**Step 2: Register Controller**

Add to `includes/core/api/class-tracksure-rest-api.php`:

```php
public function register_controllers() {
    // ... existing controllers ...

    // 🆕 Add stats controller
    require_once TRACKSURE_CORE_DIR . 'api/class-tracksure-rest-stats-controller.php';
    $stats_controller = new TrackSure_REST_Stats_Controller();
    $stats_controller->register_routes();
}
```

**Step 3: Test**

```bash
# In browser or Postman
GET /wp-json/ts/v1/stats/total-events

# Response:
{
  "success": true,
  "data": {
    "total_events": 1234
  }
}
```

---

### Task 3: Add a Custom Destination

**Scenario**: Send events to a custom webhook.

**Step 1: Create Destination Handler**

Create file: `includes/pro/destinations/class-tracksure-webhook-destination.php`

```php
<?php
/**
 * Webhook Destination
 * Sends events to custom webhook URL
 */
class TrackSure_Webhook_Destination {
    private $core;

    public function __construct($core) {
        $this->core = $core;

        // Hook into delivery system
        add_filter('tracksure_deliver_mapped_event', array($this, 'send'), 10, 3);
    }

    public function send($result, $destination, $mapped_event) {
        // Only handle webhook destination
        if ($destination !== 'webhook') {
            return $result;
        }

        // Get webhook URL from settings
        $webhook_url = get_option('tracksure_webhook_url');
        if (empty($webhook_url)) {
            return array('success' => false, 'error' => 'Webhook URL not configured');
        }

        // Send event to webhook
        $response = wp_remote_post($webhook_url, array(
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

**Step 2: Register Destination**

```php
// In your module's init method
add_action('tracksure_load_destination_handlers', function($destinations_manager) {
    $destinations_manager->register('webhook', 'Custom Webhook');

    require_once TRACKSURE_PRO_DIR . 'destinations/class-tracksure-webhook-destination.php';
    new TrackSure_Webhook_Destination($this->core);
});
```

**Step 3: Add Settings**

```php
// Add to settings schema
add_filter('tracksure_settings_schema', function($schema) {
    $schema['destinations']['webhook'] = array(
        'webhook_url' => array(
            'type' => 'string',
            'label' => 'Webhook URL',
            'default' => '',
        ),
    );
    return $schema;
});
```

**Step 4: Test**

1. Go to TrackSure settings
2. Enable "Custom Webhook" destination
3. Enter webhook URL: `https://webhook.site/your-unique-url`
4. Trigger an event
5. Check webhook.site - you should see the event data

---

## Testing Your Changes

### Manual Testing Checklist

**Before Submitting Code**:

- [ ] ✅ PHP syntax check: `php -l includes/your-file.php`
- [ ] ✅ WordPress debug log clean (no errors)
- [ ] ✅ Test in different browsers (Chrome, Firefox, Safari)
- [ ] ✅ Test with consent enabled and disabled
- [ ] ✅ Check database for expected data
- [ ] ✅ Verify REST API responses

### Test Scenarios

**1. Test Event Recording**

```php
// Add this to a test page template or use WP-CLI
$core = TrackSure_Core::get_instance();
$event_builder = $core->get_service('event_builder');
$event_recorder = $core->get_service('event_recorder');

$event = $event_builder->build_event('test_event', array(
    'test_param' => 'test_value',
));

$event_id = $event_recorder->record($event);

// Check result
if ($event_id) {
    echo "✅ Event recorded! ID: {$event_id}";
} else {
    echo "❌ Failed to record event";
}
```

**2. Test Destination Delivery**

```sql
-- Check outbox table
SELECT * FROM wp_tracksure_outbox
WHERE destination = 'ga4'
ORDER BY created_at DESC
LIMIT 10;
```

**3. Test REST API**

```bash
# Using curl
curl -X GET "http://localhost/wp-json/ts/v1/events" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

---

## Debugging

### Common Issues

**Issue 1: Events Not Recording**

**Symptoms**: Events not appearing in database

**Debug Steps**:

1. Check debug log: `wp-content/debug.log`
2. Verify hooks are firing:
   ```php
   add_action('tracksure_event_recorded', function($event_id) {
       error_log("Event recorded: {$event_id}");
   });
   ```
3. Check consent status:
   ```php
   $consent_manager = $core->get_service('consent_manager');
   error_log('Tracking allowed: ' . ($consent_manager->is_tracking_allowed() ? 'YES' : 'NO'));
   ```

---

**Issue 2: Destinations Not Receiving Events**

**Symptoms**: Events in database but not in GA4/Meta

**Debug Steps**:

1. Check outbox table:
   ```sql
   SELECT * FROM wp_tracksure_outbox WHERE status = 'pending';
   ```
2. Manually trigger delivery worker:
   ```php
   $worker = new TrackSure_Delivery_Worker($core);
   $worker->process();
   ```
3. Check destination settings:
   ```php
   // Settings are individual wp_options, not a single array
   $tracking_enabled = get_option('tracksure_tracking_enabled', false);
   $session_timeout = get_option('tracksure_session_timeout', 30);
   ```

---

**Issue 3: React Admin Not Loading**

**Symptoms**: Blank page or JavaScript errors

**Debug Steps**:

1. Check browser console (F12)
2. Verify build exists: `admin/dist/tracksure-admin.js`
3. Rebuild admin:
   ```bash
   cd admin
   npm run build
   ```
4. Clear browser cache (Ctrl+Shift+R)

---

### Debug Helper Functions

**Add these to `wp-content/mu-plugins/tracksure-debug.php`**:

```php
<?php
/**
 * TrackSure Debug Helpers
 * Must-use plugin for debugging
 */

// Log all events
add_action('tracksure_event_recorded', function($event_id, $event_data) {
    error_log('=== EVENT RECORDED ===');
    error_log('Event ID: ' . $event_id);
    error_log('Event Name: ' . $event_data['event_name']);
    error_log('Event Data: ' . print_r($event_data, true));
}, 10, 2);

// Log all conversions
add_action('tracksure_conversion_recorded', function($conversion_id, $goal_id) {
    error_log('=== CONVERSION RECORDED ===');
    error_log('Conversion ID: ' . $conversion_id);
    error_log('Goal ID: ' . $goal_id);
}, 10, 2);

// Log destination delivery
add_filter('tracksure_deliver_mapped_event', function($result, $destination, $event) {
    error_log("=== DELIVERING TO {$destination} ===");
    error_log('Event: ' . print_r($event, true));
    return $result;
}, 5, 3);
```

---

## Getting Help

### Resources

**Documentation**:

- [CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md) - Architecture overview
- [CONCEPTS_EXPLAINED.md](CONCEPTS_EXPLAINED.md) - Key concepts
- [CODE_WALKTHROUGH.md](CODE_WALKTHROUGH.md) - Code examples
- [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md) - Debugging techniques
- [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md) - All hooks
- [REST_API_REFERENCE.md](REST_API_REFERENCE.md) - API docs
- [FRONTEND_SDK.md](FRONTEND_SDK.md) - Browser tracking SDK
- [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) - All 15 database tables
- [CLASS_REFERENCE.md](CLASS_REFERENCE.md) - Class-by-class reference
- [EVENT_SYSTEM.md](EVENT_SYSTEM.md) - Event pipeline deep dive
- [PLUGIN_API.md](PLUGIN_API.md) - PHP & JavaScript public API
- [CUSTOM_EVENTS.md](CUSTOM_EVENTS.md) - Creating custom events

**Code Examples**:

```php
// Get service
$session_manager = $core->get_service('session_manager');

// Build event
$event = $event_builder->build_event('event_name', $params);

// Record event
$event_id = $event_recorder->record($event);

// Check consent
$allowed = $consent_manager->is_tracking_allowed();

// Get database instance
$db = TrackSure_DB::get_instance();
```

**WordPress Resources**:

- [WordPress Developer Handbook](https://developer.wordpress.org/)
- [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/)
- [WP REST API Handbook](https://developer.wordpress.org/rest-api/)

### Asking Questions

**Good Question Format**:

```
**What I'm trying to do:**
Add a custom event to track video plays

**What I've tried:**
1. Added event to registry/events.json
2. Created tracking code in class-tracksure-video-tracking.php
3. Initialized class in tracksure-free.php

**What's happening:**
Events are not appearing in the database

**Debug info:**
- WordPress debug log: [paste relevant logs]
- Database query: SELECT * FROM wp_tracksure_events WHERE event_name = 'video_play' (0 results)
- Consent status: Tracking allowed = YES

**Question:**
Am I missing a step in the event recording flow?
```

**Where to Ask**:

- **Slack**: #tracksure-dev (fastest response)
- **GitHub Issues**: For bugs and feature requests
- **Email**: dev@tracksure.com (for private questions)

---

## Next Steps

**Week 1**: Foundation

- [ ] Complete all "Your First Day" steps
- [ ] Read CODE_ARCHITECTURE.md
- [ ] Read CONCEPTS_EXPLAINED.md
- [ ] Make your first code change (add debug log)

**Week 2**: Hands-on Practice

- [ ] Add a custom event
- [ ] Add a REST API endpoint
- [ ] Modify React admin UI
- [ ] Test event recording end-to-end

**Week 3**: Advanced Topics

- [ ] Add a custom destination
- [ ] Create an adapter for a new platform
- [ ] Implement attribution logic changes
- [ ] Write unit tests

**Week 4**: Real Work

- [ ] Pick a GitHub issue labeled "good first issue"
- [ ] Implement the feature
- [ ] Submit pull request
- [ ] Celebrate! 🎉

---

## Checklist: Am I Ready?

Before your first pull request, make sure you can answer YES to all:

- [ ] I understand what TrackSure does
- [ ] I can explain what an "event" is
- [ ] I know where to find the main plugin file
- [ ] I can locate the service container (TrackSure_Core)
- [ ] I understand the difference between Core and Free modules
- [ ] I know what an adapter does
- [ ] I can add a debug log statement
- [ ] I can query the database for events
- [ ] I know how to rebuild the React admin
- [ ] I know where to ask for help

**If YES to all**: You're ready! Pick a task and start coding! 🚀

**If NO to some**: Re-read the relevant docs, ask questions in Slack, pair with a senior developer.

---

**Welcome to the team! 🎉**

You're going to do great things with TrackSure!
