=== TrackSure ===
Contributors: tracksure
Tags: analytics, tracking, server-side-tracking, facebook-pixel, conversion-api
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Server-side analytics & Conversion API for WordPress. Privacy-friendly tracking with WooCommerce funnels and first-party attribution.

== Description ==

TrackSure Cloud is a **privacy-friendly, server-side tracking and analytics plugin** for WordPress. It combines **first-party analytics** and **Conversion API (CAPI)** into a single dashboard. Track visits, WooCommerce sales, funnels, and attribution while staying **GDPR/CCPA-ready**.

**Why TrackSure?**
Many advertisers lose conversion data to iOS 14+, cookie blockers, and ad blockers. TrackSure helps fix this with **server-side tracking** that can deliver more complete conversion reporting to help improve ROAS and get visibility into customer touchpoints.

### 🚀 Key Features

**For Advertisers (Meta, Google, TikTok, Pinterest):**
*   **Server-Side Tracking (CAPI):** Helps bypass some client-side restrictions with direct server-to-server tracking.
*   **Improve ROAS Reporting:** Feed ad platforms more complete data to help optimize campaigns.
*   **Event Deduplication:** Combinines browser and server events.
*   **Supported Platforms:** Meta (Facebook/Instagram) CAPI, Google Analytics 4 Measurement Protocol, TikTok Events API (Pro), Pinterest API (Pro).

**For Everyone (Complete Analytics):**
*   **First-Party Analytics:** Own your data. Stored securely in your WordPress database.
*   **Traffic Source Detection:** Automatic tracking for organic search, social media, email, and referrals.
*   **Visitor Journeys:** See touchpoints from first visit to final conversion.
*   **Content Performance:** Track which posts and pages drive engagement.

**eCommerce & Attribution:**
*   **Auto-Tracking:** Works with WooCommerce, Easy Digital Downloads, FluentCart, and SureCart.
*   **Revenue Attribution:** See which blog post, ad, or email contributed to sales.
*   **Funnel Analysis:** Visualize checkout flows.

**Privacy & Compliance:**
*   **GDPR/CCPA Ready:** Built-in consent manager integration (Cookiebot, CookieYes, OneTrust).
*   **Cookieless Option:** Track without cookies using localStorage.
*   **Data Ownership:** No data sent to third parties unless you explicitly enable ad platforms.

### ⚡ Why Choose TrackSure?

**TrackSure vs. Pixel-Only Plugins:**
Pixel-only systems may lose data to browser blockers. TrackSure uses **server-side tracking** to help capture more events, so your ad platforms receive more complete data for optimization.

**TrackSure vs. Google Analytics:**
TrackSure is **first-party software**—your data stays on your server, so you retain control over it.

### 🔌 Integrations

*   **eCommerce:** WooCommerce, Easy Digital Downloads, FluentCart, SureCart, MemberPress (Pro).
*   **Forms:** Contact Form 7, Gravity Forms, WPForms, Fluent Forms, Elementor Forms.
*   **Builders:** Elementor, Divi, Beaver Builder.
*   **Ads:** Meta (Facebook/Instagram), Google Ads, TikTok, Pinterest, LinkedIn, Snapchat, Microsoft Ads.

### 🚀 Getting Started

1.  **Install** TrackSure Cloud from the plugin directory.
2.  **Activate** and visit TrackSure → Settings.
3.  **Config** your preferences.
4.  **(Optional)** Add Meta Pixel ID or GA4 Measurement ID for server-side syncing.
5.  **View** analytics instantly in your dashboard.

**Data Retention:**
* Raw events: 90 days (configurable)
* Aggregated metrics: Forever (no personally identifiable information)

**External Data Sharing:**
TrackSure only sends data to Meta, Google, TikTok, or other platforms **if you explicitly enable them** and provide API credentials. You control what data is shared.

== External services ==

This plugin connects to external third-party services to provide its functionality. Below is a complete list of all external services used, when they are called, what data is transmitted, and links to their terms of service and privacy policies.

**When You Enable Meta Pixel / Conversion API:**

* **Service:** Meta (Facebook) Graph API
* **Purpose:** Send conversion events (purchases, add-to-cart, page views) to Facebook for ad optimization
* **What data is sent:** Event name, timestamp, hashed user email/phone (if available), product SKU, revenue, IP address, user agent, pixel ID
* **When it's sent:** Automatically when a tracked event occurs (product view, purchase, etc.) and Meta destination is enabled in settings
* **Service provider:** Meta Platforms, Inc.
* **Terms of Service:** https://www.facebook.com/legal/terms
* **Privacy Policy:** https://www.facebook.com/privacy/policy
* **Data Processing Agreement:** https://www.facebook.com/legal/terms/dataprocessing

**When You Enable Google Analytics 4:**

* **Service:** Google Analytics 4 Measurement Protocol
* **Purpose:** Send analytics events to Google Analytics for website traffic analysis
* **What data is sent:** Event name, page URL, referrer, session ID, client ID, IP address, user agent, device information
* **When it's sent:** Automatically when page views or custom events occur and GA4 destination is enabled in settings
* **Service provider:** Google LLC
* **Terms of Service:** https://marketingplatform.google.com/about/analytics/terms/us/
* **Privacy Policy:** https://policies.google.com/privacy

**When Loading Google Tag Manager Script (If Enabled):**

* **Service:** Google Tag Manager CDN
* **Purpose:** Load gtag.js library for browser-side Google Analytics tracking
* **What data is sent:** Standard HTTP request data (IP address, user agent, referrer) when loading the script
* **When it's sent:** On every page load when GA4 browser tracking is enabled
* **Service provider:** Google LLC
* **Script URL:** https://www.googletagmanager.com/gtag/js
* **Terms of Service:** https://marketingplatform.google.com/about/analytics/terms/us/
* **Privacy Policy:** https://policies.google.com/privacy

**When Loading Facebook Pixel Script (If Enabled):**

* **Service:** Facebook Connect CDN
* **Purpose:** Load fbevents.js library for browser-side Facebook Pixel tracking
* **What data is sent:** Standard HTTP request data (IP address, user agent, referrer) when loading the script
* **When it's sent:** On every page load when Meta Pixel browser tracking is enabled
* **Service provider:** Meta Platforms, Inc.
* **Script URL:** https://connect.facebook.net/en_US/fbevents.js
* **Terms of Service:** https://www.facebook.com/legal/terms
* **Privacy Policy:** https://www.facebook.com/privacy/policy

**Cloudflare IP Detection (Always Active):**

* **Service:** Cloudflare IP Ranges API
* **Purpose:** Fetch current list of Cloudflare proxy IP addresses to accurately detect real visitor IPs behind Cloudflare CDN. A bundled static list is included as fallback.
* **What data is sent:** Standard HTTP request headers only (no user data transmitted)
* **When it's sent:** Once per day (cached for 24 hours) to refresh the Cloudflare IP list. The plugin includes a bundled fallback list and works without this request.
* **Service provider:** Cloudflare, Inc.
* **API URLs:** https://www.cloudflare.com/ips-v4 and https://www.cloudflare.com/ips-v6
* **Terms of Service:** https://www.cloudflare.com/website-terms/
* **Privacy Policy:** https://www.cloudflare.com/privacypolicy/

**IP Geolocation (When Tracking Is Enabled):**

* **Service:** ipapi.co (primary), ip-api.com (secondary fallback), WordPress.com Geo API (tertiary fallback)
* **Purpose:** Determine the country, region, and city of visitors based on their IP address for geographic analytics reporting
* **What data is sent:** The visitor's IP address is sent to one of the geolocation providers. No other user data is transmitted.
* **When it's sent:** When a new visitor session is recorded and the IP has not been looked up recently. Results are cached for 24 hours per IP.
* **Service providers and policies:**
  * **ipapi.co** (primary) – https://ipapi.co/privacy/ and https://ipapi.co/terms/
  * **ip-api.com** (fallback) – https://ip-api.com/docs/legal
  * **WordPress.com Geo API** (fallback) – https://automattic.com/privacy/ and https://wordpress.com/tos/

**Important Notes:**

* **No automatic data sharing:** TrackSure does NOT send any data to third-party services unless you explicitly enable and configure them in TrackSure Settings → Destinations.
* **Consent-aware:** If you use a cookie consent plugin (Cookiebot, CookieYes, etc.), TrackSure will respect user consent choices and only fire pixels after consent is granted.
* **First-party analytics:** TrackSure's core analytics features store all data in your WordPress database. No external services are used for analytics unless you enable Google Analytics 4 or other destinations.
* **You control the data:** You choose which platforms to enable, what events to track, and what user data to include (emails, phones, etc.).

For more information about data privacy and compliance, see the **Privacy & GDPR Compliance** section below.

== Source Code & Build Instructions ==

The admin interface is built with React 18 and TypeScript, compiled with Webpack 5. The compiled files in `admin/dist/` are generated from the source code in `admin/src/`.

**Full source code is available on GitHub:**
[https://github.com/rubait-hasan/tracksure](https://github.com/rubait-hasan/tracksure)

**To build from source:**

1. Navigate to the `admin/` directory
2. Run `npm install` to install dependencies
3. Run `npm run build` for a production build, or `npm run dev` for development mode with watch

**Build tools used:**

* Node.js (v18+)
* npm
* Webpack 5 (config: `admin/webpack.config.js`)
* TypeScript 5 (config: `admin/tsconfig.json`)
* ts-loader for TypeScript compilation

**Key source directories:**

* `admin/src/` — React/TypeScript source code (pages, components, contexts, hooks)
* `admin/dist/` — Compiled production JavaScript (generated by Webpack)
* `assets/js/` — Frontend tracking scripts (non-compiled, human-readable)
* `includes/` — PHP backend (non-compiled, human-readable)

== Installation ==

### Automatic Installation (Recommended)

1. Log in to your WordPress admin panel
2. Go to **Plugins → Add New**
3. Search for **"TrackSure Cloud"** or **"server-side tracking"**
4. Click **Install Now** next to "TrackSure Cloud – Server-Side Tracking & Analytics"
5. Click **Activate** after installation completes

### Manual Installation

1. Download the plugin ZIP file from WordPress.org
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate** after installation

### Configuration (Quick Setup)

1. After activation, go to **TrackSure → Settings**
2. **Tracking Settings:**
   * ✅ Enable tracking (Off by default)
   * Set session timeout (default: 30 minutes)
   * Set attribution window (default: 30 days)
   * Enable IP anonymization for GDPR compliance
   * Enable DNT (Do Not Track) respect
3. **Privacy Settings:**
   * Configure consent management integration
   * Set data retention period
4. **Destinations (Optional – only if you run ads):**
   * **Meta Pixel + CAPI:** Add Pixel ID and Access Token for server-side Facebook tracking
   * **Google Analytics 4:** Add Measurement ID and API Secret for server-side GA4 tracking
   * **TikTok / Pinterest / Others:** Upgrade to Pro for additional platforms
5. Click **Save Changes**

**That's it!** Visit **TrackSure → Overview** to see your first-party analytics dashboard.

### WooCommerce Auto-Detection

If WooCommerce is active, TrackSure **automatically tracks:**
* Product page views
* Add to cart events
* View cart / Begin checkout
* Purchase events with revenue attribution

No additional setup required.

== Frequently Asked Questions ==

= How does TrackSure increase ROAS and lower CPA for advertisers? =

**TrackSure helps improve ROAS (Return on Ad Spend)** by addressing a common problem advertisers face: **incomplete conversion tracking**.

**The Problem:**
Traditional browser-side tracking (Meta Pixel alone, Google Analytics alone) can miss conversion data due to:
* iOS 14+ privacy restrictions
* Ad blockers
* Cookie deletion
* Browser privacy settings

**What This Means:**
* Ad platforms may not see all your actual conversions
* Campaign optimization may be based on incomplete data
* Your cost-per-acquisition may appear higher than it actually is

**How TrackSure Fixes This:**

**1. Server-Side Conversion API (CAPI)**
* Sends conversion data directly from your WordPress server to Meta/Google/TikTok
* Bypasses iOS restrictions, ad blockers, and cookie deletion
* **Result: More complete conversion reporting**

**2. Browser + Server Data Enrichment**
* Combines browser-side pixel data with server-side data
* Deduplicates events (no double-counting)
* Sends enhanced user data (hashed emails, phones, IP addresses) for better targeting
* **Result: Ad platforms optimize to REAL conversions, not guessed ones**

**3. Revenue Attribution**
* Shows which ad, campaign, and platform drove each sale
* Helps you understand which channels contribute to conversions
* **Result: Better data for optimizing ad spend**

**Bottom Line:**
TrackSure helps ensure your ad platforms receive more complete conversion data, which can improve campaign optimization.

= Do I need to run ads to use TrackSure? =

**No! TrackSure works perfectly without any ads.** It's a complete analytics platform for ANY WordPress website.

**You get full analytics without ads:**
* Traffic source tracking (organic search, social media, email, referrals, direct)
* Visitor journey maps (see every page visited from entry to exit)
* Content performance (which posts/pages get the most engagement)
* Conversion tracking (form submissions, purchases, custom goals)
* Real-time visitor monitoring
* eCommerce revenue attribution (for WooCommerce, EDD, FluentCart, SureCart)

**Use cases WITHOUT ads:**
* Bloggers tracking which content performs best
* Business websites monitoring contact form submissions
* eCommerce stores understanding organic vs social traffic
* Content creators seeing where readers come from

**The ad platform integrations (Meta, Google, TikTok) are 100% optional.** Only enable them if you run paid advertising campaigns.

= Does TrackSure track traffic without UTM parameters? =

**Yes! TrackSure automatically detects traffic sources even without UTM tags.**

**Automatic Source Detection:**
* 🔍 **Organic Search** – Google, Bing, Yahoo, DuckDuckGo (detects search engines automatically)
* 📱 **Social Media** – Facebook, Instagram, LinkedIn, Twitter, Pinterest, Reddit, TikTok (auto-detected)
* 📧 **Email** – Gmail, Outlook, Yahoo Mail, Apple Mail (auto-detected as email traffic)
* 🔗 **Referral Sites** – Any website that links to you (captures referrer URL)
* 🎯 **Direct Traffic** – Visitors who type your URL or use bookmarks
* 🤖 **AI Chatbots** – ChatGPT, Claude, Perplexity, Gemini (auto-detected)

**UTM Parameters (Optional):**
If you DO use UTM parameters for campaigns (utm_source, utm_medium, utm_campaign), TrackSure captures them too:
* Email marketing campaigns (Mailchimp, ConvertKit, etc.)
* Social media posts (with UTM links)
* Paid ads (Facebook, Google, TikTok campaigns)
* Influencer partnerships (track specific referral links)

**Best of both worlds:** Automatic source detection + UTM campaign tracking when you need it.

= Does TrackSure support Meta Conversion API (CAPI) and server-side tracking? =

**Yes!** TrackSure includes **built-in Meta Conversion API (CAPI)** and **server-side tracking** for both Meta and Google Analytics 4. This means your conversion events are sent directly from your WordPress server to Meta/Google, bypassing browser blockers, iOS privacy restrictions, and cookie deletion.

**Benefits of server-side tracking:**
* Recover conversions that may be missed by browser-side tracking alone
* More complete attribution and better ad optimization
* Improved reporting accuracy
* Better audience building for retargeting

= Can I replace my existing pixel tracking plugins with TrackSure Cloud? =

**Yes.** TrackSure Cloud combines **browser-side pixel tracking** + **server-side Conversion API** + **first-party analytics** + **consent manager** in one plugin. You can replace plugins like PixelYourSite and similar tools with TrackSure as your single tracking solution.

= Is TrackSure compatible with GDPR, CCPA and cookie blockers? =

**Yes.** TrackSure includes built-in privacy compliance features:

* **Cookieless mode** – Track visitors without cookies (uses localStorage instead)
* **IP anonymization** – Mask IP addresses for GDPR compliance
* **Consent management integration** – Auto-detects Cookiebot, CookieYes, OneTrust, etc.
* **Do Not Track (DNT) respect** – Honors browser privacy settings
* **WordPress privacy tools** – Data export and deletion for GDPR "right to be forgotten"
* **Data retention controls** – Configure how long raw events are stored

**Server-side tracking bypasses cookie blockers** because events are sent directly from your server, not the user's browser.

= Does TrackSure work with WooCommerce and other eCommerce plugins? =

**Yes!** TrackSure automatically tracks the complete eCommerce funnel for:

**Supported Platforms (Free):**
* WooCommerce (full integration)
* Easy Digital Downloads
* FluentCart (physical & digital products)
* SureCart (products & subscriptions)

**Events Tracked:**
* Product page views
* Add to cart
* View cart
* Begin checkout
* Purchase (with revenue, items, order ID)
* Revenue attribution (which traffic source/campaign drove the sale)

**Pro Version Adds:**
* Cart abandonment recovery emails
* WooCommerce Subscriptions tracking
* Membership plugins (MemberPress, LearnDash)
* Booking plugins (Amelia, WooCommerce Bookings)
* Donation plugins (GiveWP, Charitable)

= How does TrackSure track complete visitor journeys and funnels? =

TrackSure uses **session-based tracking** with **first-party data storage** to build a complete picture of each visitor's journey:

**What's Tracked:**
* First visit (entry page, traffic source, UTM parameters)
* All pageviews during the session
* Actions taken (clicks, form views, add to cart, etc.)
* Time on each page, scroll depth, engagement
* Exit page and session duration
* Return visits over 30 days (attribution window)

**Journey Visualization:**
Go to **TrackSure → Visitor Journeys** to see:
* Complete path from first visit to conversion
* All touchpoints (organic search → social ad → email → direct purchase)
* Time between touchpoints
* Sessions needed to convert

**Funnel Tracking:**
Create custom funnels like:
1. Homepage → Product Page → Add to Cart → Checkout → Purchase

TrackSure shows drop-off rates at each stage so you can optimize conversions.

= Does TrackSure work without Meta Pixel or Google Analytics? =

**Yes, absolutely! That's the whole point.** TrackSure is a **standalone analytics platform** that doesn't require Meta, Google, or any external service.

**What you get WITHOUT any external integrations:**
* ✅ Complete traffic source tracking (organic, social, email, referral, direct)
* ✅ Real-time visitor monitoring (see who's on your site right now)
* ✅ Top pages and content performance analytics
* ✅ Conversion goal tracking (forms, purchases, custom events)
* ✅ Visitor journey maps (complete path from first visit to conversion)
* ✅ First-touch and last-touch attribution
* ✅ eCommerce revenue reports (WooCommerce, EDD, FluentCart, SureCart)
* ✅ All data stored in YOUR WordPress database (you own it)

**When to use Meta/Google integrations:**
Only if you run paid ads and want to send conversion signals back to ad platforms for optimization.

**Think of it this way:**
* **TrackSure = your personal analytics platform** (like having your own Google Analytics, but better)
* **Meta/GA4/TikTok integrations = optional ad platform connectors** (only for advertisers)

**TrackSure works great for:**
* Blogs with no ads (just track content performance)
* Business sites with no ads (just monitor contact forms and traffic)
* eCommerce stores relying on organic/social traffic (no paid ads needed)
* Anyone who wants privacy-friendly analytics without sending data to Google

= Will TrackSure slow down my website? =

**No.** TrackSure is optimized to minimize performance impact:

* **Async JavaScript loading** – Tracking script loads without blocking page render
* **Event batching** – Groups events to reduce HTTP requests (sends every 2 seconds or 10 events)
* **Database indexing** – All queries are indexed and optimized (under 100ms on most sites)
* **Pre-computed metrics** – Aggregation tables cache daily/hourly stats for instant dashboard loading
* **Cache-friendly** – Auto-configures exclusions for WP Rocket, W3 Total Cache, LiteSpeed, etc.
* **CDN compatible** – Works with Cloudflare, BunnyCDN, StackPath

**Typical performance impact:** Minimal added page load time.

TrackSure aims to be lightweight because core analytics data stays in your database.

= Can I track custom events and conversions? =

**Yes!** TrackSure provides both **JavaScript** and **PHP APIs** for custom event tracking.

**JavaScript API (browser-side):**

The `window.TrackSure` object is available on every frontend page after the tracking script loads.

```javascript
// Track a custom event with parameters
window.TrackSure.track('button_click', {
    button_name: 'Download PDF',
    file_type: 'pdf'
});

// Track promotion view
window.TrackSure.track('view_promotion', {
    promotion_id: 'summer-sale-2024',
    promotion_name: 'Summer Sale',
    creative_name: 'Hero Banner'
});

// Track promotion click
document.querySelector('.my-banner').addEventListener('click', function() {
    window.TrackSure.track('select_promotion', {
        promotion_id: 'summer-sale-2024',
        promotion_name: 'Summer Sale'
    });
});

// Utility: Get current visitor IDs (useful for custom integrations)
var clientId  = window.TrackSure.getClientId();
var sessionId = window.TrackSure.getSessionId();

// Utility: Validate an event against the registry before sending
var result = window.TrackSure.validateEvent('button_click', { button_name: 'Test' });
// result = { valid: true, errors: [] }
```

**PHP API (server-side):**

Use the `tracksure()` helper function to access core services.

```php
// Record a server-side event (e.g., from a form handler or webhook).
// Required fields: event_name, client_id, session_id, event_id.
$event_recorder = tracksure()->core->get_service( 'event_recorder' );

$result = $event_recorder->record( array(
    'event_name'   => 'membership_upgraded',
    'client_id'    => $client_id,      // UUID from cookie or session.
    'session_id'   => $session_id,     // UUID from cookie or session.
    'event_id'     => wp_generate_uuid4(), // Unique per event.
    'event_params' => array(
        'plan'    => 'pro',
        'revenue' => 99.00,
    ),
) );

if ( $result['success'] ) {
    // Event recorded — $result['event_id'] contains the stored ID.
}

// Get visitor IDs from the session manager (useful on server-side hooks).
$session_manager = tracksure()->core->get_service( 'session_manager' );
$client_id       = $session_manager->get_client_id_from_browser();
$session_id      = $session_manager->get_session_id_from_browser();
```

**Available PHP Hooks (for developers extending TrackSure):**

```php
// Filter event data before it is stored.
add_filter( 'tracksure_filter_event_data', function( $event_data ) {
    // Add custom parameter to every event.
    $event_data['event_params']['membership_level'] = get_user_meta(
        get_current_user_id(), 'membership_level', true
    );
    return $event_data;
} );

// Action: fires when a new session starts.
add_action( 'tracksure_session_started', function( $session_id, $visitor_id, $session_data, $is_returning, $session_number ) {
    // Custom logic when visitor starts a new session.
}, 10, 5 );

// Action: fires when a conversion is recorded.
add_action( 'tracksure_conversion_recorded', function( $conversion_id, $conversion_data ) {
    // Trigger a notification, sync to CRM, etc.
}, 10, 2 );
```

**Use Cases:**
* Button clicks
* Video plays
* PDF downloads
* Custom form submissions
* Membership upgrades
* Course completions
* Donation amounts

Then create **custom goals** in TrackSure → Goals to track conversions.

= What types of websites can use TrackSure? =

**ANY WordPress website!** TrackSure works for all website types:

**Content & Publishing:**
* 📝 Personal blogs (track which posts get the most reads)
* 📰 News & magazine sites (monitor article engagement)
* 🎓 Educational blogs (track tutorial views and resource downloads)
* 🎨 Portfolio sites (see which projects attract the most interest)

**eCommerce (any platform):**
* 🛒 WooCommerce stores (physical or digital products)
* 📦 Easy Digital Downloads (software, ebooks, courses)
* 🛍️ FluentCart stores (physical & digital products)
* 💳 SureCart stores (products, subscriptions, upsells)
* 🎁 Print-on-demand & dropshipping stores

**Business & Services:**
* 💼 Corporate websites (track contact forms, service inquiries)
* 🏢 B2B sites (monitor lead generation and downloads)
* 🏨 Hotels & restaurants (reservation forms, menu views)
* 🏥 Healthcare providers (appointment requests)
* 💇 Beauty salons & spas (booking inquiries)
* 🔧 Home services (contractor quotes, service requests)

**Membership & Learning:**
* 👥 Membership sites (MemberPress, LearnDash) – Pro
* 🎓 Online courses (LifterLMS, Sensei LMS) – Pro
* 📚 Learning management systems

**Booking & Events:**
* 📅 Appointment booking (Amelia, WooCommerce Bookings) – Pro
* 🎫 Event ticketing sites
* 🏋️ Fitness & gym booking

**Non-Profit:**
* ❤️ Donation sites (GiveWP, Charitable) – Pro
* 🌱 Fundraising campaigns
* 🤝 Community organizations

TrackSure works for content, eCommerce, lead generation, and many other WordPress use cases.

= How does attribution work in TrackSure? =

TrackSure tracks **first-touch** and **last-touch attribution** in the free version:

**First-Touch Attribution:**
* Tracks the very first traffic source that brought the visitor
* Example: User first arrives from Google organic search → credited to "google / organic"

**Last-Touch Attribution:**
* Tracks the final interaction before conversion
* Example: User returns via Facebook ad before purchasing → credited to "facebook / cpc"

**Attribution Window:** 30 days (configurable)

**Multi-Touch Attribution (Pro):**
Upgrade to Pro for advanced models:
* **Linear** – Equal credit to all touchpoints
* **Time-Decay** – More credit to recent interactions
* **Position-Based (U-Shaped)** – First and last touch get 40% each, middle gets 20%
* **Data-Driven (AI)** – Machine learning determines credit based on actual conversion patterns

**Assisted Conversions (Pro):**
See which channels helped but didn't get final credit (e.g., Facebook ad assisted, Google search converted).

= Can I export my analytics data? =

**Yes!** TrackSure offers multiple export options:

**CSV Export:**
* Go to any report (Traffic Sources, Top Pages, etc.)
* Click **Export CSV** button
* Downloads data with all metrics

**API Access (Pro):**
* REST API endpoints for programmatic data access
* Build custom dashboards or integrate with other tools

**WordPress Privacy Tools:**
* Users can request data export via WordPress → Settings → Privacy
* Includes all events and sessions for that user

**Database Access:**
All data is stored in your WordPress database (wp_tracksure_events table), so you can query directly if needed.

= Does TrackSure work with page builders (Elementor, Divi, etc.)? =

**Yes!** TrackSure automatically tracks events on pages built with:

* Elementor
* Divi Builder
* Beaver Builder
* Gutenberg (WordPress Block Editor)
* WPBakery
* Oxygen Builder
* Bricks Builder

**Tracked Events:**
* Page views
* Button clicks
* Form submissions (Elementor Forms, Divi Forms)
* Popup views
* Video plays (if using Elementor video widget)

No special configuration needed—just install and activate TrackSure.

= How do I set up Meta Conversion API (CAPI) server-side tracking? =

**Step-by-step setup:**

1. **Get your Meta Pixel ID:**
   * Go to Meta Events Manager (business.facebook.com/events_manager)
   * Copy your Pixel ID (15-digit number)

2. **Generate Conversion API Access Token:**
   * In Events Manager, click your Pixel → Settings → Conversions API
   * Click **Generate Access Token** → Copy it

3. **Add to TrackSure:**
   * Go to **TrackSure → Settings → Destinations → Meta**
   * Paste Pixel ID and Access Token
   * Enable "Server-Side Tracking (CAPI)"
   * Click **Save Changes**

4. **Test Events:**
   * Go to **TrackSure → Real-Time**
   * Browse your site, add a product to cart
   * Check Meta Events Manager → Test Events to see server events arriving

**That's it!** TrackSure now sends both browser-side (Meta Pixel) and server-side (CAPI) events with automatic deduplication.

= What's the difference between TrackSure Free and Pro? =

**TrackSure Free includes:**
* ✅ Complete first-party analytics dashboard
* ✅ Real-time visitor tracking
* ✅ Meta Pixel + Conversion API (server-side)
* ✅ Google Analytics 4 + Measurement Protocol (server-side)
* ✅ WooCommerce, FluentCart, EDD, SureCart tracking
* ✅ First-touch and last-touch attribution
* ✅ Consent management (GDPR/CCPA)
* ✅ Unlimited events and traffic

**TrackSure Pro adds:**
* 🚀 14+ additional ad platforms (TikTok, Pinterest, LinkedIn, Snapchat, Reddit, Twitter, Microsoft, Google Ads)
* 🚀 Multi-touch attribution models (linear, time-decay, position-based, AI-driven)
* 🚀 Assisted conversions and channel interaction analysis
* 🚀 Cart abandonment recovery emails
* 🚀 Session recording & heatmaps
* 🚀 Cohort analysis (retention, churn, LTV)
* 🚀 Predictive analytics (AI/ML churn prediction, conversion probability)
* 🚀 Advanced integrations (MemberPress, LearnDash, Amelia, etc.)
* 🚀 Email marketing sync (Mailchimp, ActiveCampaign, Klaviyo)
* 🚀 White label (rebrand for agencies)
* 🚀 Priority support (24/7 with SLA)

[Compare Plans](https://tracksure.cloud/pricing)

= How is my data stored and how long is it retained? =

**Data Storage:**
* All tracking data is stored **in your WordPress database** (not sent to TrackSure servers)
* Events table: `wp_tracksure_events`
* Sessions table: `wp_tracksure_sessions`
* Aggregated metrics: `wp_tracksure_analytics_hourly` and `wp_tracksure_analytics_daily`

**Data Retention:**
* **Raw events:** 90 days (default, configurable to 30/60/90/180 days)
* **Aggregated metrics:** Forever (no personally identifiable information)
* **Sessions:** 30 days after last activity

**Cleanup:**
* Automatic cleanup runs daily via WP-Cron
* Old events are permanently deleted based on retention settings
* You can manually trigger cleanup at **TrackSure → Settings → Privacy → Cleanup Data**

**GDPR Compliance:**
* Users can request deletion via WordPress privacy tools
* All events and sessions for a specific email/user are permanently deleted
* No personally identifiable information in aggregated metrics

= Does TrackSure track personally identifiable information (PII)? =

**TrackSure is designed to minimize PII collection:**

**What's tracked (NON-PII):**
* Page URLs visited
* Referrer URLs
* UTM parameters
* Device type (desktop/mobile/tablet)
* Browser name and version
* Operating system
* Session duration and engagement
* Events (page view, add to cart, purchase, etc.)

**What's optionally tracked (PII - can be disabled):**
* IP address (can be anonymized automatically)
* User ID (if visitor is logged into WordPress)
* Email (only for logged-in users, hashed when sent to Meta/GA4)
* Phone (only if provided in forms, hashed when sent to Meta/GA4)

**Privacy Controls:**
* **IP Anonymization** – Masks last octet (e.g., 192.168.1.XXX)
* **Hashing** – Email and phone are SHA-256 hashed before sending to ad platforms
* **Cookieless mode** – No cookies = no browser fingerprinting
* **DNT respect** – Completely stops tracking if user has Do Not Track enabled

**For GDPR compliance:** Enable IP anonymization + cookieless mode + consent management.

= Can I use TrackSure for client sites / white label? =

**Free Version:**
* ✅ You can install TrackSure on unlimited client sites
* ❌ Cannot rebrand (shows "TrackSure Cloud" in admin menu)
* ❌ No agency license

**Pro Version:**
* ✅ Install on unlimited client sites (Agency license)
* ✅ **White label** – Rebrand plugin name, logo, menu text, URLs
* ✅ Remove "Powered by TrackSure" footer links
* ✅ Custom support URL (direct to your agency)
* ✅ Client reporting dashboards with your branding

**Perfect for agencies** managing analytics/tracking for multiple clients.

[Get Agency License](https://tracksure.cloud/agency)

= What ad platforms does TrackSure integrate with? =

**Free Version:**
* ✅ Meta (Facebook/Instagram) – Pixel + Conversion API
* ✅ Google Analytics 4 – gtag.js + Measurement Protocol

**Pro Version Adds:**
* 🚀 TikTok – Pixel + Events API
* 🚀 Pinterest – Tag + Conversion API
* 🚀 LinkedIn – Insight Tag
* 🚀 Snapchat – Pixel + Conversions API
* 🚀 Reddit – Pixel
* 🚀 Twitter (X) – Pixel
* 🚀 Microsoft Advertising – UET Tag
* 🚀 Google Ads – Conversion Tracking
* 🚀 Taboola – Pixel
* 🚀 Outbrain – Pixel
* 🚀 Quora – Pixel
* 🚀 AdRoll – Pixel
* 🚀 And more...

All with **browser-side** and **server-side (Conversion API)** tracking where supported.

= How do I get support? =

**Free Version Support:**
* 📖 [Documentation](https://tracksure.cloud/docs) – Comprehensive guides and tutorials
* 💬 [WordPress.org Support Forum](https://wordpress.org/support/plugin/tracksure) – Community support
* 🐛 [GitHub Issues](https://github.com/tracksure/tracksure) – Bug reports and feature requests

**Pro Version Support:**
* 📧 **Priority Email Support** – responses within 24 hours (weekdays)
* 💬 **Live Chat** – Real-time support during business hours
* 📞 **Implementation Consulting** – Help with setup and configuration
* 🎯 **Feature Requests** – Priority consideration for Pro users

[Get Pro Support](https://tracksure.cloud/support)

== Screenshots ==

1. Overview Dashboard - KPIs, traffic trends, top sources
2. Real-Time Dashboard - Active users and live sessions
3. Traffic Sources - Source/medium breakdown with conversions
4. Top Pages - Most visited pages with engagement metrics
5. Goals & Conversions - Custom goals and funnel visualization
6. Settings - Configure tracking, destinations, and privacy

== Changelog ==

= 1.0.0 - 2026-02-05 =
* Initial release
* Browser tracking SDK with comprehensive event capture
* Server-side integrations (WooCommerce, FluentCart, EDD, SureCart)
* First-party analytics dashboards
* First-touch and last-touch attribution
* Meta Pixel + CAPI integration
* GA4 gtag.js + Measurement Protocol integration
* Privacy-first features (GDPR compliance, cookieless mode)
* Performance optimizations (caching, CDN compatibility)

== Upgrade Notice ==

= 1.0.0 =
Initial release of TrackSure - Complete first-party analytics and attribution platform for WordPress.

== Privacy Policy ==

TrackSure stores the following data in your WordPress database:

**Tracking Data (90-day retention):**
* Page URLs visited
* Referrer URLs
* UTM campaign parameters
* Device type (desktop/mobile/tablet)
* Browser and OS information (user agent)
* IP address (can be anonymized)
* Session duration and engagement metrics

**For E-commerce (if using WooCommerce/FluentCart/EDD/SureCart):**
* Product views
* Cart actions
* Order completion (order ID, total, items)
* Customer email and phone (hashed when sent to Meta/GA4)

**External Data Sharing (Optional):**

TrackSure is privacy-first by default. **All tracking is disabled upon installation** until you explicitly enable it.

**Note on Privacy Settings:**
*   **Tracking Disabled by Default:** No data is collected or processed until you manually enable tracking in Settings.
*   **IP Anonymization:** Default is `false` to ensure accurate location reporting for regions where anonymization is not legally required. Since tracking is opt-in, no IP addresses are processed until you enable the plugin. You can toggle "Anonymize IP" in Settings > Privacy at any time.

**Supported Third-Party Services:**

TrackSure connects to the following services **only when you enable them** and provide API credentials.

**1. Meta (Facebook/Instagram) - Available in Free & Pro**
*   **Method:** Server-to-Server via Meta Graph API (CAPI)
*   **Data Sent:** Event data (PageView, ViewContent, AddToCart, Checkout, Purchase), Hashed user data (email, phone, IP, User Agent)
*   **Purpose:** Ad optimization and attribution

**2. Google Analytics 4 (GA4) - Available in Free & Pro**
*   **Method:** Server-to-Server via Measurement Protocol
*   **Data Sent:** Event parameters, Client ID, User Agent, IP
*   **Purpose:** Analytics reporting

**3. Pro-Only Integrations (Add-ons)**
*   **Google Ads:** Sends offline conversion adjustments via Google Ads API.
*   **TikTok:** Sends web events via TikTok Events API.
*   **Pinterest:** Sends conversion events via Pinterest API.
*   **Snapchat:** Sends conversion events via Snapchat Conversions API.
*   **Microsoft Ads:** Sends offline conversions via Microsoft Ads API.
*   **LinkedIn:** Sends conversion events via LinkedIn CAPI.

You must obtain user consent before enabling these destinations (GDPR/CCPA requirement).

**Your Responsibilities:**

* Disclose TrackSure's tracking in your privacy policy
* Obtain consent before tracking (if required by law)
* Configure data retention periods appropriately
* Enable IP anonymization if required

**Data Deletion:**

Users can request data deletion via WordPress Privacy Tools or TrackSure Settings → Privacy.

== Support ==

**Free Support:**

* [Documentation](https://tracksure.cloud/docs)
* [Community Forum](https://wordpress.org/support/plugin/tracksure)
* [GitHub Issues](https://github.com/tracksure/tracksure)

**Pro Support:**

* Email support with 24-hour response time
* Priority bug fixes
* Feature requests
* Implementation consulting

[Get Pro Support](https://tracksure.cloud/support)

== Trademarks & Third-Party Services ==

TrackSure integrates with various third-party analytics and advertising platforms. All trademarks, service marks, and company names mentioned in this plugin are the property of their respective owners.

**Third-Party Platforms:**
* Meta, Facebook, Instagram, and Facebook Pixel are trademarks of Meta Platforms, Inc.
* Google, Google Analytics, Google Ads, and GA4 are trademarks of Google LLC.
* TikTok is a trademark of ByteDance Ltd.
* Pinterest is a trademark of Pinterest, Inc.
* Snapchat is a trademark of Snap Inc.
* LinkedIn is a trademark of Microsoft Corporation.
* Twitter (X) is a trademark of X Corp.
* Reddit is a trademark of Reddit Inc.
* Microsoft Ads is a trademark of Microsoft Corporation.

**Integration Requirements:**
* Users must have active accounts with third-party platforms to use their respective features
* API access requires platform-specific credentials and compliance with their terms of service
* Data transmission follows each platform's API specifications and privacy policies
* Users are responsible for compliance with each platform's terms of service

TrackSure is an independent plugin and is not officially affiliated with, endorsed by, or sponsored by any of the companies mentioned above. All product names, logos, brands, and trademarks are property of their respective owners.

**Data Privacy:** TrackSure sends conversion data to enabled platforms only with user consent. No data is transmitted to third parties unless explicitly configured by the site administrator.
