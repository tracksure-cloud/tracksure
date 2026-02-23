# 🐛 TrackSure Debugging Guide

Complete guide to debugging TrackSure issues - from basic troubleshooting to advanced techniques.

---

## Table of Contents

1. [Quick Diagnostics](#quick-diagnostics)
2. [Common Issues & Solutions](#common-issues--solutions)
3. [Debugging Tools](#debugging-tools)
4. [Debug Logging](#debug-logging)
5. [Database Inspection](#database-inspection)
6. [REST API Testing](#rest-api-testing)
7. [Browser Debugging](#browser-debugging)
8. [Performance Debugging](#performance-debugging)

---

## Quick Diagnostics

### Run Built-in Diagnostics

TrackSure includes a diagnostics endpoint:

```bash
# Via REST API
curl http://yoursite.com/wp-json/tracksure/v1/diagnostics/system

# Or visit in browser (must be admin)
http://yoursite.com/wp-admin/admin.php?page=tracksure-diagnostics
```

**What it checks**:

- ✅ Database tables exist
- ✅ Required PHP extensions loaded
- ✅ WP-Cron running
- ✅ Destinations configured
- ✅ Registry loaded
- ✅ Recent events recorded

---

### Quick Health Check

**1. Check if plugin is active**:

```bash
wp plugin list | grep tracksure
# Should show: tracksure | active
```

**2. Check database tables**:

```sql
SHOW TABLES LIKE 'wp_tracksure_%';
```

**Expected**: 14 tables

**3. Check recent events**:

```sql
SELECT event_name, COUNT(*) as count, MAX(created_at) as latest
FROM wp_tracksure_events
GROUP BY event_name
ORDER BY latest DESC;
```

**4. Check outbox status**:

```sql
SELECT destination, status, COUNT(*) as count
FROM wp_tracksure_outbox
GROUP BY destination, status;
```

---

## Common Issues & Solutions

### Issue 1: Events Not Recording

**Symptoms**:

- Events not appearing in database
- Dashboard shows zero events

**Debug Steps**:

**Step 1: Enable Debug Logging**

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('TRACKSURE_DEBUG', true);
```

**Step 2: Check Debug Log**

```bash
tail -f wp-content/debug.log | grep TrackSure
```

**Expected output when event fires**:

```
[TrackSure] Recording event: purchase
[TrackSure] Event recorded: purchase (evt_abc123)
```

**If no output**: Integration not capturing event

**Step 3: Verify Hook is Firing**

```php
// Add to mu-plugins/tracksure-debug.php
add_action('woocommerce_thankyou', function($order_id) {
    error_log('WooCommerce thankyou fired: ' . $order_id);
}, 5, 1); // Priority 5 (before TrackSure at 10)
```

**If hook fires but TrackSure doesn't log**: Integration hook priority issue

**Step 4: Check Consent Status**

```php
$core = TrackSure_Core::get_instance();
$consent_manager = $core->get_service('consent_manager');
error_log('Consent allowed: ' . ($consent_manager->is_tracking_allowed() ? 'YES' : 'NO'));
```

**Step 5: Check Database Permissions**

```php
global $wpdb;
$result = $wpdb->insert(
    $wpdb->prefix . 'tracksure_events',
    array('event_name' => 'test'),
    array('%s')
);
error_log('DB insert result: ' . print_r($result, true));
error_log('DB error: ' . $wpdb->last_error);
```

**Solutions**:

| Cause              | Solution                                                    |
| ------------------ | ----------------------------------------------------------- |
| Hook not firing    | Check if WooCommerce/integration is active                  |
| Consent denied     | Check consent cookie: `tracksure_consent`                   |
| DB permissions     | Grant INSERT permission on `wp_tracksure_*` tables          |
| Service not loaded | Check `$core->get_service('event_recorder')` returns object |

---

### Issue 2: Destinations Not Receiving Events

**Symptoms**:

- Events in database
- Outbox shows "pending" status
- GA4/Meta not receiving events

**Debug Steps**:

**Step 1: Check Outbox**

```sql
SELECT * FROM wp_tracksure_outbox
WHERE status = 'pending'
AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created_at DESC
LIMIT 10;
```

**If rows exist**: Delivery worker not running

**Step 2: Verify WP-Cron**

```bash
wp cron event list | grep tracksure
```

**Expected**:

```
tracksure_delivery_worker  2026-01-17 10:05:00  every_5_minutes
```

**If missing**: Schedule cron manually:

```php
if (!wp_next_scheduled('tracksure_delivery_worker')) {
    wp_schedule_event(time(), 'every_5_minutes', 'tracksure_delivery_worker');
}
```

**Step 3: Manually Trigger Delivery**

```php
// Add to test page
$core = TrackSure_Core::get_instance();
$worker = new TrackSure_Delivery_Worker($core);
$worker->process();
echo "Delivery worker executed";
```

**Step 4: Check Destination Configuration**

```php
$ga4_measurement_id = get_option('tracksure_ga4_measurement_id');
$ga4_api_secret = get_option('tracksure_ga4_api_secret');

if (empty($ga4_measurement_id) || empty($ga4_api_secret)) {
    echo "GA4 not configured!";
}
```

**Step 5: Test Destination Directly**

```php
// Test GA4 connection
$core = TrackSure_Core::get_instance();
$ga4_destination = new TrackSure_GA4_Destination($core);

$test_event = array(
    'client_id' => 'test-client',
    'events' => array(
        array(
            'name' => 'test_event',
            'params' => array('test' => 'value'),
        ),
    ),
);

$result = $ga4_destination->send(array(), 'ga4', $test_event);
print_r($result);
// Expected: array('success' => true, 'status_code' => 204)
```

**Solutions**:

| Cause                      | Solution                                                                                     |
| -------------------------- | -------------------------------------------------------------------------------------------- |
| WP-Cron not running        | Use system cron: `crontab -e` add `*/5 * * * * wget -q -O - http://yoursite.com/wp-cron.php` |
| Destination not configured | Add API keys in TrackSure settings                                                           |
| API request failing        | Check network connectivity, firewall rules                                                   |
| Max retries reached        | Check `attempts` column, may need to reset                                                   |

---

### Issue 3: Browser Tracking Not Working

**Symptoms**:

- Page views not tracked
- Browser SDK not loading
- JavaScript errors in console

**Debug Steps**:

**Step 1: Check if SDK Loaded**

Open browser console (F12) and check:

```javascript
console.log(window.trackSure);
// Expected: Object with track, init methods
```

**If undefined**: SDK not enqueued

**Step 2: Verify Script Enqueued**

View page source and search for:

```html
<script src="/wp-content/plugins/tracksure/assets/js/tracksure-web.js"></script>
<script>
  trackSure.init({
    apiUrl: "https://yoursite.com/wp-json/tracksure/v1",
    ...
  });
</script>
```

**If missing**: Asset not enqueued

**Step 3: Check Browser Console Errors**

```javascript
// Common errors:

// 1. CORS error
// Solution: Check REST API CORS headers

// 2. 403 Forbidden
// Solution: Check nonce generation

// 3. trackSure is not defined
// Solution: Script load order issue
```

**Step 4: Test Manual Event**

```javascript
// In browser console
trackSure.track("test_event", {
  test_param: "test_value",
});

// Check network tab for POST to /wp-json/tracksure/v1/ingest
```

**Solutions**:

| Cause               | Solution                                           |
| ------------------- | -------------------------------------------------- |
| Script not enqueued | Check `TrackSure_Tracker_Assets` class initialized |
| CORS error          | Add CORS headers to REST API                       |
| Nonce invalid       | Regenerate nonce, check cookie domain              |
| Ad blocker          | SDK blocked by browser extension                   |

---

### Issue 4: React Admin Not Loading

**Symptoms**:

- Blank admin page
- JavaScript errors
- "Loading..." never finishes

**Debug Steps**:

**Step 1: Check Browser Console**

Open DevTools (F12) → Console tab

**Common errors**:

```
Uncaught ReferenceError: React is not defined
→ Solution: React bundle not loaded

Uncaught SyntaxError: Unexpected token '<'
→ Solution: 404 on JS file, check build exists

Failed to fetch
→ Solution: REST API error
```

**Step 2: Verify Build Exists**

```bash
ls -lh admin/dist/tracksure-admin.js
# Should show file size (e.g., 500KB)
```

**If missing**: Rebuild admin

```bash
cd admin
npm run build
```

**Step 3: Check REST API**

```bash
curl http://yoursite.com/wp-json/tracksure/v1/
# Expected: JSON response with routes
```

**If error**: REST API not working

**Step 4: Check Nonce**

```javascript
// In browser console
console.log(trackSureAdmin.nonce);
// Should be a long alphanumeric string

// Test API call
fetch("/wp-json/tracksure/v1/query/overview", {
  headers: {
    "X-WP-Nonce": trackSureAdmin.nonce,
  },
})
  .then((r) => r.json())
  .then(console.log);
```

**Solutions**:

| Cause                  | Solution                                  |
| ---------------------- | ----------------------------------------- |
| Build missing          | Run `npm run build` in admin folder       |
| REST API 404           | Check permalink flush: `wp rewrite flush` |
| Nonce invalid          | Re-login to WordPress                     |
| React version conflict | Check no other React versions loaded      |

---

## Debugging Tools

### Tool 1: TrackSure Debug Plugin

Create `wp-content/mu-plugins/tracksure-debug.php`:

```php
<?php
/**
 * Plugin Name: TrackSure Debug Helper
 * Description: Must-use plugin for debugging TrackSure
 */

// Log all events
add_action('tracksure_event_recorded', function($event_id, $event_data, $session) {
    error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    error_log('📊 EVENT RECORDED');
    error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    error_log('Event ID: ' . $event_id);
    error_log('Event Name: ' . $event_data['event_name']);
    error_log('Session ID: ' . $event_data['session_id']);
    error_log('Visitor ID: ' . $event_data['visitor_id']);
    error_log('Params: ' . print_r($event_data['params'], true));
    error_log('Anonymized: ' . (isset($event_data['anonymized']) ? 'YES' : 'NO'));
}, 10, 3);

// Log destination delivery
add_filter('tracksure_deliver_mapped_event', function($result, $destination, $event) {
    error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    error_log('📤 DELIVERING TO: ' . strtoupper($destination));
    error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    error_log('Event: ' . print_r($event, true));

    // Log result after delivery
    add_action('shutdown', function() use ($result, $destination) {
        error_log('Result for ' . $destination . ': ' . print_r($result, true));
    });

    return $result;
}, 1, 3);

// Log consent checks
add_filter('tracksure_should_track_user', function($allowed) {
    error_log('🔐 Consent Check: ' . ($allowed ? 'ALLOWED' : 'DENIED'));
    return $allowed;
});

// Log service access
add_action('init', function() {
    $core = TrackSure_Core::get_instance();
    error_log('✅ TrackSure Core loaded');
    error_log('Services: ' . implode(', ', array_keys($core->get_all_services())));
});
```

---

### Tool 2: Query Monitor Plugin

Install **Query Monitor** plugin:

```bash
wp plugin install query-monitor --activate
```

**Features**:

- See all database queries
- Track slow queries
- Monitor hook execution
- View PHP errors
- Check REST API calls

**Usage**:

1. Install plugin
2. Load TrackSure page
3. Click "QM" in admin bar
4. Go to "Queries" tab → Filter by "tracksure"

---

### Tool 3: Custom Dashboard Widget

Add to `functions.php`:

```php
/**
 * TrackSure Debug Dashboard Widget
 */
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'tracksure_debug_widget',
        'TrackSure Debug Info',
        'tracksure_render_debug_widget'
    );
});

function tracksure_render_debug_widget() {
    global $wpdb;

    // Get stats
    $total_events = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_events");
    $total_sessions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_sessions");
    $pending_deliveries = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tracksure_outbox WHERE status = 'pending'");

    // Get recent events
    $recent_events = $wpdb->get_results("
        SELECT event_name, COUNT(*) as count, MAX(created_at) as latest
        FROM {$wpdb->prefix}tracksure_events
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY event_name
        ORDER BY count DESC
        LIMIT 5
    ");

    ?>
    <div class="tracksure-debug">
        <h3>📊 Statistics</h3>
        <ul>
            <li><strong>Total Events:</strong> <?php echo number_format($total_events); ?></li>
            <li><strong>Total Sessions:</strong> <?php echo number_format($total_sessions); ?></li>
            <li><strong>Pending Deliveries:</strong> <?php echo $pending_deliveries; ?></li>
        </ul>

        <h3>🔥 Last 24 Hours</h3>
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Count</th>
                    <th>Latest</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_events as $event) : ?>
                <tr>
                    <td><?php echo esc_html($event->event_name); ?></td>
                    <td><?php echo number_format($event->count); ?></td>
                    <td><?php echo esc_html($event->latest); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // Check for issues
        if ($pending_deliveries > 100) {
            echo '<p style="color: red;"><strong>⚠️ Warning:</strong> High pending deliveries. Check WP-Cron.</p>';
        }

        // Check WP-Cron
        $next_cron = wp_next_scheduled('tracksure_delivery_worker');
        if (!$next_cron) {
            echo '<p style="color: red;"><strong>⚠️ Error:</strong> Delivery worker not scheduled!</p>';
        } else {
            $time_until = human_time_diff(time(), $next_cron);
            echo '<p><strong>Next Delivery:</strong> ' . $time_until . '</p>';
        }
        ?>
    </div>
    <?php
}
```

---

## Debug Logging

### Enable Detailed Logging

**wp-config.php**:

```php
// WordPress debug
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

// TrackSure debug
define('TRACKSURE_DEBUG', true);
define('TRACKSURE_DEBUG_LEVEL', 'verbose'); // 'basic', 'verbose', 'all'
```

### Log Levels

**Basic** (default):

- Event recording
- Delivery success/failure
- Critical errors

**Verbose**:

- All basic logs
- Service initialization
- Hook execution
- Database queries

**All**:

- All verbose logs
- Every function call
- Parameter values
- Performance metrics

### Custom Logging

```php
// In your code
if (defined('TRACKSURE_DEBUG') && TRACKSURE_DEBUG) {
    error_log('[TrackSure] Custom message');
    error_log('[TrackSure] Variable: ' . print_r($var, true));
}

// Use TrackSure logger service
$logger = $core->get_service('logger');
$logger->info('Info message');
$logger->warning('Warning message');
$logger->error('Error message');
$logger->debug('Debug message (only when TRACKSURE_DEBUG=true)');
```

### View Logs

```bash
# Tail debug log
tail -f wp-content/debug.log

# Filter TrackSure logs only
tail -f wp-content/debug.log | grep TrackSure

# Last 100 TrackSure logs
grep TrackSure wp-content/debug.log | tail -100
```

---

## Database Inspection

### Useful SQL Queries

**1. Recent events by type**:

```sql
SELECT
    event_name,
    COUNT(*) as count,
    MAX(created_at) as latest,
    MIN(created_at) as earliest
FROM wp_tracksure_events
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY event_name
ORDER BY count DESC;
```

**2. Events per hour**:

```sql
SELECT
    DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour,
    COUNT(*) as events
FROM wp_tracksure_events
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY hour
ORDER BY hour DESC;
```

**3. Failed deliveries**:

```sql
SELECT
    destination,
    COUNT(*) as failed_count
FROM wp_tracksure_outbox
WHERE status = 'pending'
AND attempts >= 3
GROUP BY destination;
```

**4. Session details**:

```sql
SELECT
    s.session_id,
    s.started_at,
    s.utm_source,
    s.utm_campaign,
    COUNT(e.event_id) as event_count
FROM wp_tracksure_sessions s
LEFT JOIN wp_tracksure_events e ON s.session_id = e.session_id
WHERE s.started_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY s.session_id
ORDER BY s.started_at DESC;
```

**5. Top products (from events)**:

```sql
SELECT
    JSON_EXTRACT(event_data, '$.params.items[0].item_name') as product_name,
    COUNT(*) as purchases
FROM wp_tracksure_events
WHERE event_name = 'purchase'
AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY product_name
ORDER BY purchases DESC
LIMIT 10;
```

**6. Attribution source breakdown**:

```sql
SELECT
    utm_source,
    utm_medium,
    COUNT(DISTINCT visitor_id) as visitors,
    COUNT(DISTINCT session_id) as sessions
FROM wp_tracksure_sessions
WHERE started_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY utm_source, utm_medium
ORDER BY visitors DESC;
```

---

## REST API Testing

### Test with curl

**1. Get overview data**:

```bash
# Get nonce first (as admin)
NONCE=$(curl -c cookies.txt -b cookies.txt -X POST "http://yoursite.com/wp-login.php" \
  -d "log=admin&pwd=password" 2>/dev/null | grep -oP 'nonce=[0-9a-f]+' | cut -d= -f2)

# Call API
curl -b cookies.txt "http://yoursite.com/wp-json/tracksure/v1/query/overview?date_start=2026-01-01&date_end=2026-01-31" \
  -H "X-WP-Nonce: $NONCE"
```

**2. Ingest test event**:

```bash
curl -X POST "http://yoursite.com/wp-json/tracksure/v1/ingest" \
  -H "Content-Type: application/json" \
  -d '{
    "event_name": "page_view",
    "params": {
      "page_url": "https://example.com/test",
      "page_title": "Test Page"
    }
  }'
```

**3. Get specific event**:

```bash
curl "http://yoursite.com/wp-json/tracksure/v1/events/evt_abc123" \
  -H "X-WP-Nonce: $NONCE"
```

### Test with Postman

**Import Collection**:

1. Create new collection "TrackSure API"
2. Add requests:

**Request 1: Overview**

```
GET {{base_url}}/wp-json/tracksure/v1/query/overview
Headers:
  X-WP-Nonce: {{nonce}}
Params:
  date_start: 2026-01-01
  date_end: 2026-01-31
```

**Request 2: Ingest Event**

```
POST {{base_url}}/wp-json/tracksure/v1/ingest
Headers:
  Content-Type: application/json
Body (JSON):
{
  "event_name": "test_event",
  "params": {
    "test": "value"
  }
}
```

---

## Browser Debugging

### Enable Browser Console Logs

Add to TrackSure settings or use browser console:

```javascript
// Enable verbose browser SDK logging
localStorage.setItem("tracksure_debug", "true");

// Reload page
location.reload();

// Now all trackSure calls will log to console
```

### Monitor Network Requests

1. Open DevTools (F12)
2. Go to **Network** tab
3. Filter by "tracksure"
4. Look for:
   - `tracksure-web.js` (SDK load)
   - `POST /wp-json/tracksure/v1/ingest` (event tracking)
   - Status codes (200 = success, 4xx/5xx = error)

### Test SDK Manually

```javascript
// In browser console

// 1. Check SDK loaded
console.log(trackSure);

// 2. Get current session
console.log(trackSure.getSession());

// 3. Fire test event
trackSure.track("test_event", {
  test_param: "test_value",
});

// 4. Check if sent (Network tab)
// Should see POST to /wp-json/tracksure/v1/ingest

// 5. Force page view
trackSure.trackPageView();
```

---

## Performance Debugging

### Identify Slow Queries

**1. Enable Query Monitor**:

```bash
wp plugin install query-monitor --activate
```

**2. Load page and check**:

- Admin bar → QM → Queries
- Sort by "Time"
- Look for slow queries (>100ms)

**3. Common slow queries**:

```sql
-- Slow: Full table scan
SELECT * FROM wp_tracksure_events WHERE visitor_id = 'abc';

-- Fast: Use index
SELECT * FROM wp_tracksure_events WHERE visitor_id = 'abc' AND created_at > '2026-01-01';
```

### Profile PHP Execution

**Install Xdebug**:

```bash
# Install Xdebug
pecl install xdebug

# Enable profiler in php.ini
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug
xdebug.trigger_value=tracksure

# Load page with profiler
http://yoursite.com/?XDEBUG_PROFILE=tracksure

# Analyze with webgrind or KCachegrind
```

### Check Memory Usage

```php
// Add to beginning of file
$start_memory = memory_get_usage();

// Your code here

// At end of file
$end_memory = memory_get_usage();
$memory_used = ($end_memory - $start_memory) / 1024 / 1024; // MB
error_log("TrackSure memory used: {$memory_used} MB");
```

---

## Troubleshooting Checklist

Before asking for help, check:

- [ ] **Debug logs** - Any errors in `wp-content/debug.log`?
- [ ] **Database tables** - All 14 tables exist?
- [ ] **Recent events** - Any events in last hour?
- [ ] **Outbox** - Pending deliveries stuck?
- [ ] **WP-Cron** - Delivery worker scheduled?
- [ ] **REST API** - `/wp-json/tracksure/v1/` responds?
- [ ] **Browser console** - Any JavaScript errors?
- [ ] **Consent** - Tracking allowed?
- [ ] **Destinations** - API keys configured?
- [ ] **WordPress version** - 6.0+?
- [ ] **PHP version** - 7.4+?

---

## Getting Help

**When asking for help, provide**:

1. **What you're trying to do**
2. **What's happening** (screenshots, error messages)
3. **Debug logs** (paste relevant logs)
4. **Database check** (outbox status, recent events count)
5. **Environment** (WordPress version, PHP version, plugins)
6. **What you've tried** (from this guide)

**Example good question**:

```
**Issue**: Purchase events not sending to GA4

**What I checked**:
- ✅ Events appearing in wp_tracksure_events table
- ✅ Outbox shows 50 pending GA4 deliveries
- ✅ WP-Cron scheduled: tracksure_delivery_worker
- ❌ Outbox status still "pending" after 1 hour

**Debug logs**:
[TrackSure] Delivering to ga4: evt_abc123
[TrackSure] Failed delivery to ga4: cURL error 28: Connection timed out

**Environment**:
- WordPress 6.4
- PHP 8.1
- TrackSure 1.0.0
- Hosting: SiteGround

**What I've tried**:
- Manually triggered delivery worker
- Checked GA4 API keys (correct)
- Tested with curl to GA4 MP endpoint (works)

**Question**: Why is delivery failing from WordPress but curl works?
```

---

**Next**: [PERFORMANCE_OPTIMIZATION.md](PERFORMANCE_OPTIMIZATION.md) for optimization techniques.
