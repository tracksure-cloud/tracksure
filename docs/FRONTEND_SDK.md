# 🌐 TrackSure Frontend SDK Documentation

**Complete guide to the TrackSure browser tracking SDK (ts-web.js)**

---

## 📚 **Table of Contents**

1. [Overview](#overview)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Core Features](#core-features)
5. [Automatic Tracking](#automatic-tracking)
6. [Manual Tracking](#manual-tracking)
7. [E-commerce Tracking](#e-commerce-tracking)
8. [Identity Management](#identity-management)
9. [Session Management](#session-management)
10. [Event Deduplication](#event-deduplication)
11. [Performance](#performance)
12. [Privacy & Consent](#privacy--consent)
13. [API Reference](#api-reference)
14. [Troubleshooting](#troubleshooting)

---

## 📖 **Overview**

TrackSure Frontend SDK (`ts-web.js`) is a lightweight (~5KB gzipped), production-grade browser tracking library that provides:

✅ **Complete Engagement Tracking**: Page views, clicks, scrolls, forms, video, downloads  
✅ **Performance & UX Metrics**: Page timing, rage clicks, dead clicks, bounce detection  
✅ **Advanced Attribution**: UTMs, referrers, social platforms, search engines, AI chatbots  
✅ **Identity Management**: Client ID, session ID, cross-device tracking  
✅ **Event Deduplication**: UUID-based for browser + server + destination sync  
✅ **Zero Page Speed Impact**: Non-blocking, batched delivery, lazy loading  
✅ **Universal Compatibility**: Safari ITP, ad blockers, private mode, mobile

**Version**: 2.0.0  
**File**: `assets/js/ts-web.js`  
**Size**: ~5KB (gzipped), ~15KB (uncompressed)  
**Dependencies**: None (vanilla JavaScript)

### **All Frontend Script Handles**

TrackSure enqueues multiple scripts on the frontend. `ts-web` is the core SDK; the others extend it:

| Handle                 | File                                | Dependencies                  | Purpose                                                 |
| ---------------------- | ----------------------------------- | ----------------------------- | ------------------------------------------------------- |
| `ts-web`               | `assets/js/ts-web.js`               | none                          | Core tracking SDK                                       |
| `ts-currency`          | `assets/js/ts-currency.js`          | none                          | Currency detection for e-commerce                       |
| `ts-minicart`          | `assets/js/ts-minicart.js`          | `ts-web`, `ts-currency`       | Mini-cart / add-to-cart tracking                        |
| `ts-consent-listeners` | `assets/js/consent-listeners.js`    | `ts-web`                      | CMP integration (listens for consent changes)           |
| `ts-goal-constants`    | `admin/tracksure-goal-constants.js` | none                          | Goal trigger type constants                             |
| `ts-goals`             | `admin/tracking-goals.js`           | `ts-goal-constants`, `ts-web` | Client-side goal evaluation                             |
| `ts-checkout`          | _(inline script)_                   | none                          | Injects hidden fields for server-side checkout tracking |

---

## 🚀 **Installation**

### **Automatic Installation (Recommended)**

TrackSure automatically loads the SDK when tracking is enabled:

```php
// In your theme or plugin
add_action('wp_enqueue_scripts', function() {
    // TrackSure SDK is automatically loaded
    // No action needed!
});
```

### **Manual Installation**

If you need manual control:

```html
<!-- Load SDK -->
<script src="/wp-content/plugins/tracksure/assets/js/ts-web.js"></script>

<!-- Configure -->
<script>
  window.trackSureConfig = {
    endpoint: "/wp-json/ts/v1/collect",
    trackingEnabled: true,
    sessionTimeout: 30, // minutes
    batchSize: 10,
    batchTimeout: 2000, // ms
  };
</script>
```

### **Custom Build**

For advanced users:

```bash
# Clone repository
git clone https://github.com/your-repo/tracksure.git

# Build custom SDK
cd tracksure/assets/js
# Modify ts-web.js
# Minify (optional)
npm run build
```

---

## ⚙️ **Configuration**

Configuration is passed via `window.trackSureConfig` object:

```javascript
window.trackSureConfig = {
  // Required
  endpoint: "/wp-json/ts/v1/collect",

  // Tracking control
  trackingEnabled: true, // Master switch
  respectDNT: false, // Honor Do Not Track header

  // Session settings
  sessionTimeout: 30, // Session timeout in minutes

  // Batching
  batchSize: 10, // Max events per batch
  batchTimeout: 2000, // Max time to wait before sending (ms)

  // Debug
  debug: false, // Enable console logging
};
```

### **Configuration Options**

| Option            | Type    | Default                  | Description                             |
| ----------------- | ------- | ------------------------ | --------------------------------------- |
| `endpoint`        | string  | `/wp-json/ts/v1/collect` | Event ingestion endpoint                |
| `trackingEnabled` | boolean | `true`                   | Master tracking switch                  |
| `respectDNT`      | boolean | `false`                  | Honor Do Not Track browser setting      |
| `sessionTimeout`  | number  | `30`                     | Session timeout in minutes              |
| `batchSize`       | number  | `10`                     | Maximum events per batch                |
| `batchTimeout`    | number  | `2000`                   | Max wait time before sending batch (ms) |
| `debug`           | boolean | `false`                  | Enable debug console logging            |

### **WordPress Integration**

WordPress uses `wp_localize_script()` to inject configuration:

```php
wp_localize_script('ts-web', 'trackSureConfig', [
    'endpoint' => rest_url('ts/v1/collect'),
    'trackingEnabled' => get_option('tracksure_tracking_enabled', true),
    'sessionTimeout' => get_option('tracksure_session_timeout', 30),
    'batchSize' => 10,
    'batchTimeout' => 2000,
    'respectDNT' => get_option('tracksure_respect_dnt', false)
]);
```

---

## 🎯 **Core Features**

### **1. Automatic Page View Tracking**

Page views are tracked automatically on load:

```javascript
// Automatically tracked on page load
{
    event_name: 'page_view',
    event_params: {
        page_path: '/products',
        page_title: 'Products',
        page_url: 'https://example.com/products',
        page_referrer: 'https://google.com',
        screen_resolution: '1920x1080',
        viewport_size: '1200x800',
        language: 'en-US',
        user_agent: 'Mozilla/5.0...'
    }
}
```

**Disable automatic tracking**:

```javascript
window.trackSureConfig = {
  autoTrack: false, // Disable automatic page views
};
```

### **2. Event Batching**

Events are automatically batched for optimal performance:

```javascript
// Events are queued
trackSure.track("button_click", { button: "CTA" });
trackSure.track("form_focus", { form_id: "contact" });
trackSure.track("scroll", { depth: 50 });

// Sent in single HTTP request after 2s or 10 events (whichever comes first)
```

**Manual flush**:

```javascript
trackSure.flush(); // Send immediately
```

### **3. Client ID Generation**

Unique client ID persists across sessions:

```javascript
// Automatically generated on first visit
const clientId = trackSure.getClientId();
// Example: "a1b2c3d4-e5f6-7890-abcd-ef1234567890"

// Stored in localStorage as '_ts_cid'
```

**Custom client ID**:

```javascript
trackSure.setClientId("custom-uuid-here");
```

### **4. Session Management**

Sessions are automatically managed:

```javascript
// Get current session ID
const sessionId = trackSure.getSessionId();

// Session expires after 30 minutes of inactivity (default)
// New session created on next activity

// First session
{
    is_new_visitor: true,
    session_number: 1
}

// Returning visitor
{
    is_new_visitor: false,
    session_number: 5  // 5th session
}
```

---

## 🔄 **Automatic Tracking**

TrackSure automatically tracks the following events:

### **Page Views**

```javascript
// Tracked on page load
Event: page_view
Parameters:
  - page_path: /products
  - page_title: Products
  - page_url: https://example.com/products
  - page_referrer: https://google.com
```

### **Click Tracking**

```javascript
// All clicks tracked
Event: click
Parameters:
  - element_type: button
  - element_id: cta-button
  - element_class: btn btn-primary
  - element_text: Buy Now
  - link_url: /checkout (if <a> tag)
```

**Track specific clicks**:

```html
<button data-track="purchase-cta">Buy Now</button>
<a href="/product" data-track="product-link">View Product</a>
```

### **Form Tracking**

```javascript
// Form interactions
Event: form_start
Parameters:
  - form_id: contact-form
  - form_name: Contact Us

Event: form_submit
Parameters:
  - form_id: contact-form
  - form_fields: 3
  - form_values: {name: 'John', email: 'john@example.com'} // if configured
```

### **Scroll Depth**

```javascript
// Tracked at 25%, 50%, 75%, 90% milestones
Event: scroll
Parameters:
  - scroll_depth: 50
  - page_path: /blog/article
```

### **Engagement Time**

```javascript
// Tracked on page unload
Event: engagement_time
Parameters:
  - engagement_duration: 45 // seconds
  - page_path: /products
```

### **Downloads**

```javascript
// PDF, ZIP, DOC, XLS, etc.
Event: file_download
Parameters:
  - file_name: product-catalog.pdf
  - file_extension: pdf
  - file_url: /downloads/product-catalog.pdf
  - file_size: 1024000 // bytes
```

### **Outbound Links**

```javascript
// External links
Event: outbound_click
Parameters:
  - link_url: https://partner-site.com
  - link_domain: partner-site.com
  - link_text: Visit Partner
```

### **Video Tracking**

```javascript
// HTML5 video events
Event: video_start
Event: video_progress (25%, 50%, 75%, 90%)
Event: video_complete
Parameters:
  - video_url: /videos/demo.mp4
  - video_title: Product Demo
  - video_duration: 120
  - video_current_time: 60
```

---

## 🎯 **Manual Tracking**

### **Track Custom Events**

```javascript
// Simple event
trackSure.track("button_click", {
  button_id: "cta-primary",
});

// E-commerce event
trackSure.track("add_to_cart", {
  currency: "USD",
  value: 99.99,
  items: [
    {
      item_id: "SKU123",
      item_name: "Premium Widget",
      price: 99.99,
      quantity: 1,
    },
  ],
});

// Custom event
trackSure.track("feature_used", {
  feature_name: "advanced_search",
  feature_category: "search",
  user_type: "premium",
});
```

### **Track Page Views (SPA)**

For single-page applications:

```javascript
// On route change
trackSure.trackPageView({
  page_path: "/products/widgets",
  page_title: "Widgets - Products",
  page_url: window.location.href,
});

// With custom parameters
trackSure.trackPageView({
  page_path: "/products/category/electronics",
  page_title: "Electronics",
  category: "electronics",
  subcategory: "laptops",
});
```

### **Set User Properties**

```javascript
// Set user data
trackSure.setUser({
  user_id: "12345",
  user_email: "john@example.com",
  user_role: "customer",
  membership_level: "premium",
});

// Set custom user properties
trackSure.setUserProperty("lifetime_value", 1250.0);
trackSure.setUserProperty("account_age_days", 365);
```

### **Set Event Properties**

Add properties to all future events:

```javascript
// Add to all events
trackSure.setEventProperty("app_version", "2.0.1");
trackSure.setEventProperty("experiment_variant", "A");

// All subsequent events will include these properties
```

---

## 🛒 **E-commerce Tracking**

TrackSure follows GA4 e-commerce event structure:

### **View Item**

```javascript
trackSure.track("view_item", {
  currency: "USD",
  value: 99.99,
  items: [
    {
      item_id: "SKU123",
      item_name: "Premium Widget",
      item_brand: "WidgetCo",
      item_category: "Widgets",
      item_variant: "Blue",
      price: 99.99,
    },
  ],
});
```

### **Add to Cart**

```javascript
trackSure.track("add_to_cart", {
  currency: "USD",
  value: 199.98,
  items: [
    {
      item_id: "SKU123",
      item_name: "Premium Widget",
      price: 99.99,
      quantity: 2,
    },
  ],
});
```

### **Remove from Cart**

```javascript
trackSure.track("remove_from_cart", {
  currency: "USD",
  value: 99.99,
  items: [
    {
      item_id: "SKU123",
      item_name: "Premium Widget",
      price: 99.99,
      quantity: 1,
    },
  ],
});
```

### **View Cart**

```javascript
trackSure.track("view_cart", {
  currency: "USD",
  value: 199.98,
  items: [
    { item_id: "SKU123", item_name: "Widget A", price: 99.99, quantity: 1 },
    { item_id: "SKU456", item_name: "Widget B", price: 99.99, quantity: 1 },
  ],
});
```

### **Begin Checkout**

```javascript
trackSure.track('begin_checkout', {
    currency: 'USD',
    value: 199.98,
    coupon: 'SUMMER10',
    items: [...]
});
```

### **Add Payment Info**

```javascript
trackSure.track('add_payment_info', {
    currency: 'USD',
    value: 199.98,
    payment_type: 'credit_card',
    items: [...]
});
```

### **Purchase**

```javascript
trackSure.track("purchase", {
  transaction_id: "T12345",
  affiliation: "Online Store",
  currency: "USD",
  value: 209.98, // Total
  tax: 10.0,
  shipping: 10.0,
  coupon: "SUMMER10",
  items: [
    { item_id: "SKU123", item_name: "Widget", price: 99.99, quantity: 2 },
  ],
});
```

### **Refund**

```javascript
trackSure.track('refund', {
    transaction_id: 'T12345',
    currency: 'USD',
    value: 209.98,
    items: [...]
});
```

---

## 👤 **Identity Management**

### **Client ID**

Unique identifier for each browser/device:

```javascript
// Get client ID
const clientId = trackSure.getClientId();
// Returns: "a1b2c3d4-e5f6-7890-abcd-ef1234567890"

// Set custom client ID
trackSure.setClientId("custom-uuid");

// Reset client ID (new visitor)
trackSure.resetClientId();
```

**Storage**: `localStorage` + cookie `_ts_cid` (400-day max-age)  
**Format**: UUID v4  
**Persistence**: Permanent (unless cleared)

### **Session ID**

Unique identifier for each session:

```javascript
// Get session ID
const sessionId = trackSure.getSessionId();
// Returns: "b2c3d4e5-f6a7-8901-bcde-f12345678901"

// Force new session
trackSure.newSession();
```

**Storage**: `sessionStorage` + cookie fallback `_ts_sid`  
**Format**: UUID v4  
**Persistence**: 30 minutes (default) of inactivity

> **Additional session storage keys**:
>
> - `_ts_ss` — session start timestamp (sessionStorage)
> - `_ts_la` — last activity timestamp (sessionStorage, used for timeout detection)

### **User ID**

Optional logged-in user identifier:

```javascript
// Set user ID
trackSure.setUserId("user_12345");

// Get user ID
const userId = trackSure.getUserId();

// Clear user ID (logout)
trackSure.clearUserId();
```

**When to use**:

- User logs in → `setUserId()`
- User logs out → `clearUserId()`
- Track across devices (same user ID)

---

## 🔁 **Session Management**

Sessions automatically start and expire:

### **Session Lifecycle**

```
Visit 1: New Session
  ↓
Activity (page views, clicks, etc.)
  ↓
30 min inactivity
  ↓
Visit 2: New Session (session_number: 2)
```

### **Session Data**

```javascript
// Get session data
const session = trackSure.getSession();

{
    session_id: "uuid",
    client_id: "uuid",
    session_start: "2026-01-17T10:00:00Z",
    last_activity: "2026-01-17T10:25:00Z",
    session_number: 3,
    is_new_visitor: false,

    // Attribution
    utm_source: "google",
    utm_medium: "cpc",
    utm_campaign: "summer_sale",
    utm_content: "ad_variant_a",
    utm_term: "widgets",

    // Referrer
    referrer: "https://google.com/search?q=widgets",
    referrer_domain: "google.com",

    // Landing page
    landing_page: "/products/widgets",
    landing_page_url: "https://example.com/products/widgets"
}
```

### **Manual Session Control**

```javascript
// Force new session
trackSure.newSession();

// Extend current session
trackSure.extendSession();

// Get session age (milliseconds)
const age = trackSure.getSessionAge();

// Check if session expired
const expired = trackSure.isSessionExpired();
```

---

## 🔐 **Event Deduplication**

TrackSure uses deterministic event IDs to prevent duplicates:

### **How It Works**

```
Browser generates event:
  event_id = MD5(session_id + event_name + content_identifier)

Server receives same event:
  event_id = MD5(session_id + event_name + content_identifier)

Result: SAME event_id → Deduplicated ✅
```

### **Content Identifiers**

**E-commerce** (product pages):

```javascript
{
  product_id: "SKU123"; // Used for event_id
}
```

**Blog/News** (WordPress posts):

```javascript
{
  post_id: 456; // Used for event_id
}
```

**Generic pages**:

```javascript
{
  page_url: "/about-us"; // Hashed for event_id
}
```

### **Why This Matters**

**Meta Pixel + CAPI**:

- Browser sends `event_id: "abc123"`
- Server sends `event_id: "abc123"`
- Meta deduplicates → **1 conversion** (not 2) ✅

**Google Analytics 4**:

- Browser sends `event_id: "abc123"`
- Server sends `event_id: "abc123"`
- GA4 deduplicates → **Accurate metrics** ✅

---

## ⚡ **Performance**

### **Zero Impact on Page Speed**

- **Non-blocking**: Loads asynchronously
- **Lazy evaluation**: Waits for page interactive
- **Batched requests**: 10 events = 1 HTTP request
- **No dependencies**: Pure JavaScript
- **Small size**: ~5KB gzipped

### **Benchmarks**

| Metric             | Value                     |
| ------------------ | ------------------------- |
| **File Size**      | 5KB (gzipped), 15KB (raw) |
| **Load Time**      | <50ms (async)             |
| **Memory Usage**   | <1MB                      |
| **CPU Impact**     | <0.1%                     |
| **Network Impact** | 1-2 requests/min          |

### **Optimization**

```javascript
// Increase batch size to reduce requests
window.trackSureConfig = {
  batchSize: 20, // Send every 20 events
  batchTimeout: 5000, // or every 5 seconds
};

// Disable specific auto-tracking
trackSure.disableAutoTracking("scroll"); // Disable scroll tracking
trackSure.disableAutoTracking("mousemove"); // Disable mouse tracking
```

---

## 🔒 **Privacy & Consent**

### **Respect Do Not Track**

```javascript
window.trackSureConfig = {
  respectDNT: true, // Honor browser DNT setting
};
```

### **GDPR Compliance**

```javascript
// Don't track until consent given
window.trackSureConfig = {
  trackingEnabled: false, // Start disabled
};

// User gives consent
function onConsentGranted() {
  trackSure.enable(); // Enable tracking
}

// User denies consent
function onConsentDenied() {
  trackSure.disable(); // Disable tracking
  trackSure.clearStorage(); // Clear all data
}
```

### **Dual-Storage Strategy**

TrackSure uses `localStorage`/`sessionStorage` as **primary** storage with **cookie fallbacks** for cross-page persistence:

```javascript
// Primary: localStorage / sessionStorage
localStorage.getItem("_ts_cid"); // Client ID (persistent)
sessionStorage.getItem("_ts_sid"); // Session ID (per-tab)

// Fallback: first-party cookies (same values)
// _ts_cid cookie (400-day max-age, SameSite=Lax)
// _ts_sid cookie (session-scoped, SameSite=Lax)
```

> **Note**: Because first-party cookies **are** set as fallbacks, a cookie consent banner
> may still be required depending on your jurisdiction (e.g., EU ePrivacy Directive).
> Use the Consent API (above) to defer tracking until consent is granted.

### **Data Anonymization**

```javascript
// Remove PII
trackSure.anonymizeIP(); // Masks last octet of IP

// Don't track user email
trackSure.setUser({
  user_id: "hashed_id_12345", // Use hashed ID, not email
});
```

---

## 📚 **API Reference**

### **Core Methods**

#### `trackSure.track(eventName, params)`

Track custom event.

```javascript
trackSure.track("button_click", {
  button_id: "cta",
  button_text: "Get Started",
});
```

**Parameters**:

- `eventName` (string) - Event name
- `params` (object) - Event parameters

**Returns**: `void`

---

#### `trackSure.trackPageView(params)`

Track page view (for SPAs).

```javascript
trackSure.trackPageView({
  page_path: "/products",
  page_title: "Products",
});
```

**Parameters**:

- `params` (object) - Page view parameters

**Returns**: `void`

---

#### `trackSure.getClientId()`

Get client ID.

```javascript
const clientId = trackSure.getClientId();
```

**Returns**: `string` - UUID

---

#### `trackSure.setClientId(id)`

Set custom client ID.

```javascript
trackSure.setClientId("custom-uuid");
```

**Parameters**:

- `id` (string) - Client UUID

**Returns**: `void`

---

#### `trackSure.getSessionId()`

Get session ID.

```javascript
const sessionId = trackSure.getSessionId();
```

**Returns**: `string` - UUID

---

#### `trackSure.setUserId(id)`

Set user ID.

```javascript
trackSure.setUserId("user_12345");
```

**Parameters**:

- `id` (string) - User identifier

**Returns**: `void`

---

#### `trackSure.getUserId()`

Get user ID.

```javascript
const userId = trackSure.getUserId();
```

**Returns**: `string|null` - User ID or null

---

#### `trackSure.setUser(properties)`

Set multiple user properties.

```javascript
trackSure.setUser({
  user_id: "12345",
  user_email: "john@example.com",
  user_role: "customer",
});
```

**Parameters**:

- `properties` (object) - User properties

**Returns**: `void`

---

#### `trackSure.setUserProperty(key, value)`

Set single user property.

```javascript
trackSure.setUserProperty("lifetime_value", 1250.0);
```

**Parameters**:

- `key` (string) - Property name
- `value` (any) - Property value

**Returns**: `void`

---

#### `trackSure.setEventProperty(key, value)`

Add property to all future events.

```javascript
trackSure.setEventProperty("app_version", "2.0.1");
```

**Parameters**:

- `key` (string) - Property name
- `value` (any) - Property value

**Returns**: `void`

---

#### `trackSure.flush()`

Send all queued events immediately.

```javascript
trackSure.flush();
```

**Returns**: `void`

---

#### `trackSure.enable()`

Enable tracking.

```javascript
trackSure.enable();
```

**Returns**: `void`

---

#### `trackSure.disable()`

Disable tracking.

```javascript
trackSure.disable();
```

**Returns**: `void`

---

#### `trackSure.newSession()`

Force new session.

```javascript
trackSure.newSession();
```

**Returns**: `void`

---

#### `trackSure.clearStorage()`

Clear all localStorage data.

```javascript
trackSure.clearStorage();
```

**Returns**: `void`

---

## 🐛 **Troubleshooting**

### **Events Not Tracking**

**Problem**: No events appearing in admin dashboard

**Solutions**:

1. **Check tracking enabled**:

```javascript
console.log(window.trackSureConfig.trackingEnabled);
// Should be true
```

2. **Check network tab**:

- Open DevTools → Network tab
- Look for POST to `/wp-json/ts/v1/collect`
- Check response status (should be 200)

3. **Check console for errors**:

```javascript
window.trackSureDebug = true; // Enable debug mode
```

4. **Verify endpoint**:

```javascript
console.log(window.trackSureConfig.endpoint);
// Should be '/wp-json/ts/v1/collect'
```

---

### **Duplicate Events**

**Problem**: Events appearing twice

**Causes**:

- Multiple SDK loads
- Browser + server duplicate (event_id mismatch)

**Solutions**:

1. **Check for duplicate script tags**:

```html
<!-- Should only appear once -->
<script src="/wp-content/plugins/tracksure/assets/js/ts-web.js"></script>
```

2. **Verify event_id generation**:

```javascript
// Enable debug mode
window.trackSureDebug = true;

// Check event_id in network tab
// Browser and server should have identical event_id
```

---

### **Session Not Persisting**

**Problem**: New session on every page

**Causes**:

- localStorage blocked
- Incognito/private mode
- Short session timeout

**Solutions**:

1. **Check localStorage**:

```javascript
console.log(localStorage.getItem("_ts_sid"));
// Should return UUID
```

2. **Increase session timeout**:

```javascript
window.trackSureConfig = {
  sessionTimeout: 60, // Increase to 60 minutes
};
```

3. **Test localStorage**:

```javascript
try {
  localStorage.setItem("test", "1");
  localStorage.removeItem("test");
  console.log("localStorage working");
} catch (e) {
  console.error("localStorage blocked:", e);
}
```

---

### **Performance Issues**

**Problem**: SDK slowing down page

**Solutions**:

1. **Reduce batch frequency**:

```javascript
window.trackSureConfig = {
  batchSize: 20, // Increase batch size
  batchTimeout: 5000, // Increase timeout
};
```

2. **Disable expensive tracking**:

```javascript
trackSure.disableAutoTracking("mousemove");
trackSure.disableAutoTracking("scroll");
```

3. **Lazy load SDK**:

```html
<script defer src="/wp-content/plugins/tracksure/assets/js/ts-web.js"></script>
```

---

## 🎓 **Examples**

### **Basic Page Tracking**

```html
<!DOCTYPE html>
<html>
  <head>
    <title>My Site</title>
  </head>
  <body>
    <!-- TrackSure automatically tracks page view -->
    <h1>Welcome</h1>

    <script src="/wp-content/plugins/tracksure/assets/js/ts-web.js"></script>
  </body>
</html>
```

### **E-commerce Product Page**

```html
<div class="product" data-product-id="SKU123">
  <h1>Premium Widget</h1>
  <p class="price">$99.99</p>
  <button id="add-to-cart">Add to Cart</button>
</div>

<script>
  // Track view_item (automatic if WooCommerce active)
  trackSure.track("view_item", {
    currency: "USD",
    value: 99.99,
    items: [
      {
        item_id: "SKU123",
        item_name: "Premium Widget",
        price: 99.99,
      },
    ],
  });

  // Track add to cart on click
  document.getElementById("add-to-cart").addEventListener("click", function () {
    trackSure.track("add_to_cart", {
      currency: "USD",
      value: 99.99,
      items: [
        {
          item_id: "SKU123",
          item_name: "Premium Widget",
          price: 99.99,
          quantity: 1,
        },
      ],
    });
  });
</script>
```

### **SPA (React/Vue) Integration**

```javascript
// React Router
import { useEffect } from "react";
import { useLocation } from "react-router-dom";

function App() {
  const location = useLocation();

  useEffect(() => {
    // Track page view on route change
    trackSure.trackPageView({
      page_path: location.pathname,
      page_title: document.title,
      page_url: window.location.href,
    });
  }, [location]);

  return <div>...</div>;
}
```

### **User Login/Logout**

```javascript
// On login
function onUserLogin(userId) {
  trackSure.setUserId(userId);
  trackSure.track("login", {
    method: "email",
  });
}

// On logout
function onUserLogout() {
  trackSure.track("logout");
  trackSure.clearUserId();
}
```

---

## 📖 **See Also**

- **[REST_API_REFERENCE.md](REST_API_REFERENCE.md)** - Backend API documentation
- **[EVENT_SYSTEM.md](EVENT_SYSTEM.md)** - Server-side event pipeline (where SDK events land)
- **[PLUGIN_API.md](PLUGIN_API.md)** - PHP API counterpart to the Browser SDK
- **[CUSTOM_EVENTS.md](CUSTOM_EVENTS.md)** - Registering & tracking custom events
- **[CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md)** - System architecture
- **[DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md)** - Debugging tracking issues
- **[ADAPTER_DEVELOPMENT.md](ADAPTER_DEVELOPMENT.md)** - Custom integrations

---

**Need Help?** Check the [Troubleshooting](#troubleshooting) section or review the code in `assets/js/ts-web.js`!
