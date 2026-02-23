# 💡 TrackSure Concepts Explained (For Beginners)

This guide explains TrackSure's key concepts in simple, non-technical language. Perfect for junior developers learning the codebase.

---

## Table of Contents

1. [What is TrackSure?](#what-is-tracksure)
2. [The Big Picture](#the-big-picture)
3. [Core Concepts](#core-concepts)
4. [How Everything Works Together](#how-everything-works-together)

---

## What is TrackSure?

**Simple Answer**: TrackSure helps website owners understand what visitors do on their site and how well their ads are working.

**Technical Answer**: TrackSure is a first-party analytics and attribution platform that:

- Tracks visitor behavior (page views, clicks, purchases)
- Sends data to ad platforms (Google Analytics, Meta/Facebook)
- Measures ad campaign performance
- Maintains GDPR/CCPA compliance

**Think of it like this**:

- **WordPress** = Your house
- **TrackSure** = Security cameras + smart home system
- **Events** = Things happening in your house (door opened, light turned on)
- **Destinations** = Cloud storage where recordings go (Google Drive, Dropbox)

---

## The Big Picture

### How TrackSure Fits in Your WordPress Site

```
┌────────────────────────────────────────────────────────────┐
│                    Customer's Browser                       │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Your Website (WordPress)                            │  │
│  │  ↓ Click "Add to Cart"                               │  │
│  │  ↓ TrackSure SDK sees this                           │  │
│  │  ↓ Sends event to server                             │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────┐
│                    WordPress Server                         │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  TrackSure Plugin                                    │  │
│  │  1. Receives "add_to_cart" event                     │  │
│  │  2. Stores in database                               │  │
│  │  3. Queues for Google Analytics                      │  │
│  │  4. Queues for Facebook Pixel                        │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────┐
│             Ad Platforms (Google, Meta, etc.)               │
│  ✅ Google Analytics: Records "add_to_cart"                │
│  ✅ Meta Pixel: Records "add_to_cart"                      │
│  ✅ Used for ad optimization                               │
└────────────────────────────────────────────────────────────┘
```

---

## Core Concepts

### 1. 🎯 **Events** (Things That Happen)

**What is an Event?**
An "event" is something a visitor does on your website.

**Examples**:

- `page_view` - Someone views a page
- `add_to_cart` - Someone adds product to cart
- `purchase` - Someone completes a purchase
- `form_submit` - Someone submits a form

**Real-World Analogy**:
Think of events like entries in a diary:

- "8:00 AM - Woke up" = `page_view`
- "8:30 AM - Made breakfast" = `add_to_cart`
- "9:00 AM - Left for work" = `purchase`

**In Code**:

```php
// Event structure
$event = array(
    'event_name' => 'purchase',           // What happened
    'event_time' => '2026-01-17 10:30:00', // When it happened
    'params' => array(                     // Additional details
        'transaction_id' => '12345',
        'value' => 99.99,
        'currency' => 'USD',
    ),
);
```

**Why Events Matter**:

- ✅ You know what visitors are doing
- ✅ You can measure conversions
- ✅ Ad platforms use this data to optimize ads

---

### 2. 📦 **Sessions** (Visits to Your Website)

**What is a Session?**
A session is a single visit to your website. It includes all the pages viewed and actions taken during that visit.

**Real-World Analogy**:

- **Session** = Your trip to the grocery store
- **Events** = Things you do there (enter store, pick up milk, checkout)

**Session Example**:

```
Session ID: abc123
Started: 10:00 AM
Ended: 10:15 AM

Events in this session:
- 10:00 AM: page_view (homepage)
- 10:02 AM: page_view (product page)
- 10:05 AM: add_to_cart
- 10:10 AM: page_view (checkout)
- 10:15 AM: purchase
```

**Key Facts**:

- Sessions expire after **30 minutes of inactivity**
- One visitor can have many sessions (multiple visits)
- All events in a session are linked together

**Why Sessions Matter**:

- ✅ You can see the path visitors take
- ✅ You can measure session duration
- ✅ You can analyze behavior patterns

---

### 3. 👤 **Visitors** (Unique People)

**What is a Visitor?**
A visitor is a unique person visiting your website, identified by a `client_id` (stored in a cookie).

**Visitor vs. Session**:

- **1 Visitor** = Same person
- **Multiple Sessions** = Multiple visits by that person

**Example**:

```
Visitor ID: visitor-xyz

Session 1 (Monday 10 AM):
- page_view: Homepage
- page_view: Product Page
- add_to_cart

Session 2 (Tuesday 3 PM):
- page_view: Homepage
- page_view: Cart
- purchase ✅
```

**Real-World Analogy**:

- **Visitor** = You (as a person)
- **Sessions** = Different times you visit the same coffee shop

**Why Visitors Matter**:

- ✅ Measure unique reach (how many people)
- ✅ Track customer journey across visits
- ✅ Analyze returning vs. new visitors

---

### 4. 🏷️ **Parameters** (Event Details)

**What is a Parameter?**
Parameters are extra details about an event.

**Example Event: Purchase**

```php
'event_name' => 'purchase',
'params' => array(
    'transaction_id' => '12345',   // Order number
    'value' => 99.99,              // Total amount
    'currency' => 'USD',           // Currency
    'tax' => 5.00,                 // Tax amount
    'shipping' => 10.00,           // Shipping cost
    'items' => array(              // Products purchased
        array(
            'item_name' => 'Blue T-Shirt',
            'item_id' => 'SKU-123',
            'price' => 29.99,
            'quantity' => 2,
        ),
    ),
),
```

**Real-World Analogy**:

- **Event** = "I bought groceries"
- **Parameters** = The receipt (what you bought, how much it cost)

**Why Parameters Matter**:

- ✅ Provide context to events
- ✅ Enable detailed analysis
- ✅ Allow ad platforms to optimize

---

### 5. 🎨 **The Registry** (Event Dictionary)

**What is the Registry?**
The Registry is a JSON file that defines all possible events and their parameters.

**Think of it like**:

- A dictionary of allowed words
- A menu at a restaurant (you can only order what's on the menu)

**Registry File (`registry/events.json`)**:

```json
{
  "events": [
    {
      "name": "purchase",
      "display_name": "Purchase",
      "category": "ecommerce",
      "required_params": ["transaction_id", "value", "currency"],
      "optional_params": ["tax", "shipping", "items"]
    },
    {
      "name": "page_view",
      "display_name": "Page View",
      "category": "engagement",
      "required_params": ["page_url"],
      "optional_params": ["page_title", "page_referrer"]
    }
  ]
}
```

**Why We Use a Registry**:

- ✅ **Validation**: Only valid events are tracked
- ✅ **Documentation**: Automatically know what events exist
- ✅ **Consistency**: Everyone uses the same event names

**Real-World Analogy**:
McDonald's menu = Registry

- You can order "Big Mac" ✅
- You can't order "Dragon Roll" ❌ (that's at a sushi place)

---

### 6. 🔌 **Adapters** (Platform Translators)

**What is an Adapter?**
An adapter translates data from a specific platform (like WooCommerce) into TrackSure's universal format.

**Problem**: Every ecommerce platform stores data differently:

- **WooCommerce** calls it `_order_total`
- **Easy Digital Downloads** calls it `cart_amount`
- **SureCart** calls it `total`

**Solution**: Adapters translate everything to a common language:

```php
// WooCommerce Adapter
class TrackSure_WooCommerce_Adapter {
    public function extract_order_data($order) {
        return array(
            'transaction_id' => $order->get_id(),          // WooCommerce way
            'value' => $order->get_total(),                 // WooCommerce way
            'currency' => $order->get_currency(),           // WooCommerce way
        );
    }
}

// EDD Adapter (different platform, same output)
class TrackSure_EDD_Adapter {
    public function extract_order_data($payment) {
        return array(
            'transaction_id' => $payment->ID,              // EDD way
            'value' => $payment->total,                     // EDD way
            'currency' => $payment->currency,               // EDD way
        );
    }
}
```

**Output is always the same**:

```php
array(
    'transaction_id' => '12345',
    'value' => 99.99,
    'currency' => 'USD',
)
```

**Real-World Analogy**:

- **Adapters** = Language translators
- **Input** = French, Spanish, German
- **Output** = Always English

**Why Adapters Matter**:

- ✅ Add new platform = Write 1 adapter
- ✅ TrackSure core doesn't care about platform differences
- ✅ Easy to support ANY ecommerce platform

---

### 7. 🎯 **Destinations** (Where Data Goes)

**What is a Destination?**
A destination is an ad platform where TrackSure sends event data.

**Common Destinations**:

- **Google Analytics (GA4)** - Website analytics
- **Meta Pixel** - Facebook/Instagram ads
- **TikTok Pixel** - TikTok ads
- **Pinterest Tag** - Pinterest ads

**How Destinations Work**:

```php
// 1. Event happens
$event = array('event_name' => 'purchase', 'value' => 99.99);

// 2. TrackSure sends to EACH enabled destination
$destinations = array('ga4', 'meta', 'tiktok');

foreach ($destinations as $destination) {
    // Map event to destination's format
    $mapped_event = map_event($event, $destination);

    // Send to destination
    send_to_destination($destination, $mapped_event);
}
```

**Real-World Analogy**:

- **Event** = You take a photo
- **Destinations** = Where you post it (Instagram, Facebook, Twitter)
- TrackSure posts to ALL your selected platforms automatically

**Why Destinations Matter**:

- ✅ Your ads get conversion data
- ✅ Ad platforms optimize better
- ✅ Better return on ad spend (ROAS)

---

### 8. 🔗 **Integrations** (Ecommerce Platforms)

**What is an Integration?**
An integration connects TrackSure to an ecommerce platform (WooCommerce, Easy Digital Downloads, etc.).

**How Integrations Work**:

```php
// WooCommerce fires a hook after purchase
do_action('woocommerce_thankyou', $order_id);

// TrackSure integration listens to this hook
class TrackSure_WooCommerce {
    public function __construct() {
        // Listen for WooCommerce events
        add_action('woocommerce_thankyou', array($this, 'track_purchase'));
    }

    public function track_purchase($order_id) {
        // 1. Get order from WooCommerce
        $order = wc_get_order($order_id);

        // 2. Use adapter to translate to universal format
        $adapter = new TrackSure_WooCommerce_Adapter();
        $order_data = $adapter->extract_order_data($order);

        // 3. Track the purchase event
        tracksure_track_event('purchase', $order_data);
    }
}
```

**Real-World Analogy**:

- **Integration** = Security camera installed in your house
- **Hooks** = Motion sensors that trigger the camera
- **Events** = Recordings when motion detected

**Why Integrations Matter**:

- ✅ Automatic tracking (no manual code needed)
- ✅ Accurate ecommerce data
- ✅ Works with ANY ecommerce platform

---

### 9. 🎭 **Service Container** (The Toolbox)

**What is a Service Container?**
The service container is a central place where all TrackSure's tools (services) are stored and accessed.

**Think of it like**:

- A toolbox where all your tools live
- Instead of carrying every tool, you go to the toolbox when you need one

**Example**:

```php
// Without Service Container (❌ Bad)
$session_manager = new TrackSure_Session_Manager();
$event_recorder = new TrackSure_Event_Recorder();
$consent_manager = new TrackSure_Consent_Manager();
// Every file creates its own instances = messy!

// With Service Container (✅ Good)
$core = TrackSure_Core::get_instance();

// Get services from central container
$session_manager = $core->get_service('session_manager');
$event_recorder = $core->get_service('event_recorder');
$consent_manager = $core->get_service('consent_manager');
// One instance shared everywhere = clean!
```

**Services Available**:

- `db` - Database operations
- `session_manager` - Session tracking
- `event_recorder` - Event storage
- `consent_manager` - GDPR compliance
- `attribution` - Attribution logic
- `event_builder` - Event construction
- And 15+ more...

**Why Service Container Matters**:

- ✅ One instance of each service (memory efficient)
- ✅ Easy to swap services for testing
- ✅ Clear dependencies

---

### 10. 🧩 **Modules** (Plugin Extensions)

**What is a Module?**
A module is a package of features that extends TrackSure.

**Module Hierarchy**:

```
TrackSure Core (engine)
    ↓
TrackSure Free (basic features)
    ↓
TrackSure Pro (advanced features)
    ↓
TrackSure 3rd Party (custom extensions)
```

**How Modules Work**:

```php
// Free module registers itself
do_action('tracksure_register_module', 'tracksure-free', __FILE__, array(
    'name' => 'TrackSure Free',
    'version' => '1.0.0',
    'capabilities' => array(
        'destinations' => array('ga4', 'meta'),      // Ad platforms
        'integrations' => array('woocommerce'),      // Ecommerce platforms
        'dashboards' => array('overview', 'events'), // Admin pages
    ),
));
```

**Real-World Analogy**:

- **Core** = iPhone (the phone itself)
- **Free Module** = Pre-installed apps (Camera, Safari)
- **Pro Module** = App Store apps you buy (Photoshop, Games)
- **3rd Party Module** = Apps from other developers

**Why Modules Matter**:

- ✅ Keep core code clean
- ✅ Easy to add features
- ✅ Pro doesn't duplicate free code
- ✅ 3rd parties can extend functionality

---

### 11. 🎣 **Hooks** (Event Listeners)

**What is a Hook?**
Hooks let you run your own code when something happens in TrackSure.

**Two Types of Hooks**:

**1. Action Hooks** (Do something when X happens)

```php
// When an event is recorded, send notification
add_action('tracksure_event_recorded', function($event_id, $event_data) {
    if ($event_data['event_name'] === 'purchase') {
        send_slack_notification("New purchase: $" . $event_data['params']['value']);
    }
}, 10, 2);
```

**2. Filter Hooks** (Modify data)

```php
// Add custom parameter to all events
add_filter('tracksure_enrich_event_data', function($event_data) {
    $event_data['params']['my_custom_param'] = 'custom_value';
    return $event_data;
});
```

**Real-World Analogy**:

- **Hooks** = If This Then That (IFTTT)
- **Action Hook** = "When motion detected, send alert"
- **Filter Hook** = "Before sending alert, add location info"

**Common Hooks**:

- `tracksure_event_recorded` - After event saved
- `tracksure_session_started` - After session created
- `tracksure_conversion_recorded` - After conversion tracked
- `tracksure_should_track_user` - Filter: Should we track this user?

---

### 12. 🔐 **Consent Manager** (GDPR/CCPA Compliance)

**What is the Consent Manager?**
The Consent Manager ensures TrackSure follows privacy laws (GDPR, CCPA).

**How It Works**:

```php
// Before recording event
$consent_manager = $core->get_service('consent_manager');

if ($consent_manager->is_tracking_allowed()) {
    // User gave consent - track normally
    record_event($event);
} else {
    // User denied consent - anonymize and track
    $anonymized_event = $consent_manager->anonymize_event($event);
    record_event($anonymized_event);
}
```

**Anonymization Example**:

```php
// Original event
$event = array(
    'event_name' => 'purchase',
    'params' => array(
        'transaction_id' => '12345',
        'email' => 'john@example.com',  // Personal data
        'value' => 99.99,
    ),
);

// After anonymization
$anonymized = array(
    'event_name' => 'purchase',
    'params' => array(
        'transaction_id' => 'ANONYMIZED',  // Removed
        'email' => 'ANONYMIZED',            // Removed
        'value' => 99.99,                   // Kept (not personal)
    ),
);
```

**Key Principle: Never Block Events**

- ✅ Consent given = Full tracking
- ✅ Consent denied = Anonymized tracking
- ❌ Never = "Don't track at all" (you lose data)

**Why This Matters**:

- ✅ Legal compliance
- ✅ User privacy respected
- ✅ You still get analytics (anonymized)

---

### 13. 🎯 **Attribution** (Which Ad Worked?)

**What is Attribution?**
Attribution answers: "Which marketing channel gets credit for the sale?"

**Scenario**:

```
Monday: Customer clicks Google Ad → visits site → leaves
Tuesday: Customer clicks Facebook Ad → visits site → leaves
Wednesday: Customer types URL directly → visits site → BUYS
```

**Question**: Who gets credit? Google, Facebook, or Direct?

**Attribution Models**:

**1. First-Click Attribution**

- **Rule**: First touchpoint gets 100% credit
- **Winner**: Google Ad ✅
- **Use**: Find what attracts new customers

**2. Last-Click Attribution**

- **Rule**: Last touchpoint gets 100% credit
- **Winner**: Direct ✅
- **Use**: Find what closes sales

**3. Linear Attribution**

- **Rule**: All touchpoints share credit equally
- **Winners**: Google 33%, Facebook 33%, Direct 33%
- **Use**: Fair credit distribution

**How TrackSure Tracks This**:

```php
// Table: tracksure_touchpoints
// Stores every marketing interaction

┌────────────┬──────────┬────────────┬─────────────┐
│ visitor_id │ source   │ medium     │ clicked_at  │
├────────────┼──────────┼────────────┼─────────────┤
│ visitor123 │ google   │ cpc        │ Mon 10am    │
│ visitor123 │ facebook │ cpc        │ Tue 2pm     │
│ visitor123 │ direct   │ none       │ Wed 9am     │
└────────────┴──────────┴────────────┴─────────────┘

// When purchase happens
$attribution_resolver->resolve('visitor123', 'first-click');
// Result: Google gets credit

$attribution_resolver->resolve('visitor123', 'last-click');
// Result: Direct gets credit
```

**Real-World Analogy**:

- **Touchpoint** = Each ad you saw before buying a car
- **Attribution** = Which ad deserves credit for the sale?

**Why Attribution Matters**:

- ✅ Know which ads actually work
- ✅ Stop wasting money on bad channels
- ✅ Invest more in channels that convert

---

### 14. 📨 **Outbox** (Delivery Queue)

**What is the Outbox?**
The outbox is a queue of events waiting to be sent to destinations.

**Problem**:
Sending to Google Analytics during checkout would slow down the page (customer waits).

**Solution**:

1. **Immediately**: Save event to database ✅ (fast)
2. **Queue**: Add to outbox for later delivery 📦
3. **Background**: Worker sends to destinations ⏰ (doesn't block page)

**Outbox Table**:

```
┌─────────┬──────────┬────────────┬──────────┬────────────┐
│ id      │ event_id │ destination│ status   │ created_at │
├─────────┼──────────┼────────────┼──────────┼────────────┤
│ 1       │ evt-123  │ ga4        │ pending  │ 10:00:00   │
│ 2       │ evt-123  │ meta       │ pending  │ 10:00:00   │
│ 3       │ evt-124  │ ga4        │ delivered│ 10:01:00   │
└─────────┴──────────┴────────────┴──────────┴────────────┘
```

**Delivery Worker Process**:

```php
// Runs every 5 minutes (WP-Cron)
1. Get pending items from outbox
2. For each item:
   - Send to destination (GA4, Meta, etc.)
   - If successful: Mark as "delivered"
   - If failed: Retry later (max 3 attempts)
3. Clean up old delivered items
```

**Real-World Analogy**:

- **Outbox** = Mailbox
- **Events** = Letters
- **Delivery Worker** = Mail carrier (picks up every 5 minutes)

**Why Outbox Matters**:

- ✅ Fast page loads (no waiting)
- ✅ Reliable delivery (retry on failure)
- ✅ No data loss if destination is down

---

### 15. 🏃 **Journey Engine** (Customer Path Tracking)

**What is the Journey Engine?**
The Journey Engine tracks the complete path a customer takes from first visit to purchase.

**Example Journey**:

```
Day 1: Google Ad Click
   ↓
Day 1: Homepage View
   ↓
Day 1: Product Page View
   ↓
Day 1: Add to Cart
   ↓
Day 1: Leave Site (abandoned)
   ↓
Day 3: Email Click (cart reminder)
   ↓
Day 3: Checkout
   ↓
Day 3: Purchase ✅
```

**Journey Data Stored**:

```php
array(
    'visitor_id' => 'visitor-123',
    'journey' => array(
        array('step' => 1, 'event' => 'ad_click', 'source' => 'google', 'day' => 1),
        array('step' => 2, 'event' => 'page_view', 'page' => 'homepage', 'day' => 1),
        array('step' => 3, 'event' => 'page_view', 'page' => 'product', 'day' => 1),
        array('step' => 4, 'event' => 'add_to_cart', 'product' => 'Blue Shirt', 'day' => 1),
        array('step' => 5, 'event' => 'email_click', 'campaign' => 'cart_reminder', 'day' => 3),
        array('step' => 6, 'event' => 'purchase', 'value' => 99.99, 'day' => 3),
    ),
)
```

**Why Journey Engine Matters**:

- ✅ See drop-off points (where people leave)
- ✅ Understand customer behavior
- ✅ Optimize conversion funnels

---

## How Everything Works Together

### Complete Purchase Flow

Let's trace a complete purchase from start to finish:

**Step 1: Customer Clicks Google Ad**

```
Browser → TrackSure SDK → Tracks "click" event
         → Saves: source=google, medium=cpc, gclid=abc123
         → Creates session_id
```

**Step 2: Customer Views Product Page**

```
Browser → TrackSure SDK → Tracks "page_view" event
         → Links to same session_id
         → Journey Engine records step
```

**Step 3: Customer Adds to Cart**

```
Browser → TrackSure SDK → Tracks "add_to_cart" event
WooCommerce → Fires hook: woocommerce_add_to_cart
Integration → Captures hook
            → Uses Adapter to extract product data
            → Event Builder builds standardized event
            → Event Recorder saves to database
```

**Step 4: Customer Completes Purchase**

```
WooCommerce → Fires hook: woocommerce_thankyou
Integration → Captures hook
            → Uses Adapter to extract order data
            → Event Builder builds "purchase" event
            → Event Recorder saves to database
            → Triggers: do_action('tracksure_event_recorded')
```

**Step 5: Consent Check**

```
Consent Manager → Checks: Is tracking allowed?
                → Yes: Keep full data
                → No: Anonymize personal data
```

**Step 6: Destinations Queue**

```
Destinations Manager → Enabled destinations: ['ga4', 'meta']
                    → Event Mapper maps to GA4 format
                    → Event Mapper maps to Meta format
                    → Adds to outbox (2 items)
```

**Step 7: Attribution**

```
Attribution Resolver → Finds all touchpoints for visitor
                    → Applies attribution model
                    → Records: Google Ad gets credit (first-click)
```

**Step 8: Background Delivery**

```
Delivery Worker (WP-Cron) → Gets pending items from outbox
                           → Sends to GA4 Measurement Protocol ✅
                           → Sends to Meta Conversions API ✅
                           → Marks items as "delivered"
```

**Step 9: Admin Dashboard**

```
React Admin → Calls REST API: /wp-json/tracksure/v1/query/overview
           → Gets: Total sales, conversion rate, attribution data
           → Displays charts and tables
```

---

## Summary

**Key Takeaways**:

1. **Events** = Things that happen (page views, clicks, purchases)
2. **Sessions** = Single visits (grouping of events)
3. **Visitors** = Unique people (across multiple sessions)
4. **Registry** = Dictionary of allowed events
5. **Adapters** = Translators (platform-specific → universal format)
6. **Integrations** = Connections to ecommerce platforms
7. **Destinations** = Ad platforms where data goes
8. **Service Container** = Toolbox of shared services
9. **Modules** = Feature packages (Free, Pro, 3rd party)
10. **Hooks** = Extension points (run your own code)
11. **Consent Manager** = GDPR/CCPA compliance
12. **Attribution** = Which ad gets credit
13. **Outbox** = Delivery queue (reliable sending)
14. **Journey Engine** = Customer path tracking

**Everything works together to**:

- ✅ Track visitor behavior accurately
- ✅ Maintain privacy compliance
- ✅ Send data to ad platforms
- ✅ Measure ad performance
- ✅ Optimize conversions

---

**Next Steps**:

- Read [JUNIOR_DEVELOPER_GUIDE.md](JUNIOR_DEVELOPER_GUIDE.md) for hands-on tutorials
- Read [CODE_WALKTHROUGH.md](CODE_WALKTHROUGH.md) for code examples
- Read [CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md) for technical details

---

**Questions?** Ask in #tracksure-dev on Slack!
