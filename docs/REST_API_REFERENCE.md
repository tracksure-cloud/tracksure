# 📡 TrackSure REST API Reference

Complete REST API documentation for TrackSure v1.

---

## 🎯 **Overview**

**Base URL**: `/wp-json/ts/v1`  
**Authentication**: WordPress nonce-based  
**Format**: JSON  
**Version**: 1.0.0

**Total Endpoints**: 55+ across 12 controllers

---

## 📚 **Table of Contents**

1. [Authentication](#authentication)
2. [Event Ingestion](#event-ingestion)
3. [Events API](#events-api)
4. [Query API](#query-api)
5. [Goals API](#goals-api)
6. [Settings API](#settings-api)
7. [Diagnostics API](#diagnostics-api)
8. [Consent API](#consent-api)
9. [Registry API](#registry-api)
10. [Products API](#products-api)
11. [Quality API](#quality-api)
12. [Suggestions API](#suggestions-api)
13. [Pixel Callbacks](#pixel-callbacks)
14. [Error Codes](#error-codes)

---

## 🔐 **Authentication**

### **Nonce-Based Authentication**

All requests require a valid WordPress nonce:

```javascript
headers: {
  'X-WP-Nonce': trackSureConfig.nonce
}
```

**Getting Nonce** (localized in admin):

```javascript
const nonce = trackSureAdmin.nonce;
```

**Nonce Validity**: 24 hours

---

## 📥 **Event Ingestion**

**Controller**: `TrackSure_REST_Ingest_Controller`  
**File**: `includes/core/api/class-tracksure-rest-ingest-controller.php`

All ingest endpoints are **public** (`permission_callback: __return_true`) because they receive analytics events from the browser SDK (`ts-web.js`) running for anonymous, non-logged-in visitors. Rate limiting, origin checks, IP exclusion, DNT header respect, and admin exclusion are enforced in callbacks.

### **POST /ts/v1/collect**

Primary batch event ingestion endpoint. This is the main endpoint that `ts-web.js` calls.

**Parameters**:

- `events` (array, required) — Array of event objects to record

**Response** (201):

```json
{
  "success": true,
  "results": [
    { "success": true, "event_id": "550e8400-e29b-41d4-a716-446655440000" },
    { "success": false, "errors": ["..."] }
  ]
}
```

### **HEAD /ts/v1/collect**

Lightweight status check — returns `200` if tracking is enabled, `403` if disabled or admin-excluded.

**Response**: Empty body. Status code only.

### **POST /ts/v1/collect/event**

Record a single event (alternative to batch endpoint).

**Parameters**:

- `event_name` (string, required) — Event name from registry
- `client_id` (string/uuid, required) — Client UUID
- `session_id` (string/uuid, required) — Session UUID
- `event_params` (object) — Event parameters
- `event_id` (string/uuid) — Event UUID for deduplication
- `occurred_at` (string/date-time) — Client timestamp (ISO 8601)
- `referrer` (string) — Referrer URL
- `landing_page` (string) — Landing page URL
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` (string) — UTM parameters
- `gclid`, `fbclid` (string) — Click IDs
- `device_type`, `browser`, `os` (string) — Device context

**Response** (201):

```json
{
  "success": true,
  "event_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

### **POST /ts/v1/collect/batch**

Record multiple events (alternative batch endpoint, same handler as `POST /collect`).

**Parameters**:

- `events` (array, required) — Array of event objects to record

**Response** (201): Same as `POST /ts/v1/collect`.

### **POST /ts/v1/collect/conversion**

Record a conversion event.

**Parameters**:

- `goal_id` (integer, required) — Goal ID
- `visitor_id` (integer, required) — Visitor ID
- `session_id` (integer, required) — Session ID
- `event_id` (string) — Linked event UUID
- `value` (number) — Conversion value
- `currency` (string) — Currency code (ISO 4217)
- `snapshot_data` (object) — Snapshot data at conversion time

**Response** (201):

```json
{
  "success": true,
  "conversion_id": 42
}
```

---

## 📊 **Events API**

**Controller**: `TrackSure_REST_Events_Controller`  
**File**: `includes/core/api/class-tracksure-rest-events-controller.php`

Admin-only controller for manual event creation (diagnostics/testing). Separate from the public ingest endpoint — uses nonce-based authentication via `check_admin_permission`.

### **POST /ts/v1/events**

Create test event(s) from the admin diagnostics page.

**Permission**: `manage_options` + nonce

**Parameters**:

- `events` (array, required) — Array of event objects. Each must have at least `event_name`. Missing fields (`client_id`, `session_id`, `occurred_at`, etc.) are auto-generated with test metadata.

**Response** (200):

```json
{
  "success_count": 2,
  "failed_count": 0,
  "results": {
    "success": [
      { "event_name": "page_view", "event_id": "550e8400-..." },
      { "event_name": "purchase", "event_id": "7c9e6679-..." }
    ],
    "failed": []
  }
}
```

---

## 📈 **Query API**

**Controller**: `TrackSure_REST_Query_Controller`  
**File**: `includes/core/api/class-tracksure-rest-query-controller.php`

All query endpoints require `manage_options` capability (`check_admin_permission`). Most accept date range parameters `date_start` (string, YYYY-MM-DD) and `date_end` (string, YYYY-MM-DD) with defaults of last 30 days.

### Overview Queries

#### **GET /ts/v1/query/overview**

Dashboard overview metrics with chart data, comparisons, and time intelligence.

**Parameters**:

- `date_start` (string) — Start date (YYYY-MM-DD, default: 30 days ago)
- `date_end` (string) — End date (YYYY-MM-DD, default: today)

**Response** (200):

```json
{
  "metrics": {
    "unique_visitors": 1250,
    "total_sessions": 1800,
    "total_conversions": 45,
    "conversion_rate": 3.6,
    "total_revenue": 4500.0,
    "bounce_rate": 42.5,
    "avg_session_duration_seconds": 185
  },
  "previous_period": { "unique_visitors": 1100, "...": "..." },
  "devices": [{ "device_type": "desktop", "count": 950 }],
  "top_sources": [{ "source": "google", "medium": "organic", "sessions": 500 }],
  "top_countries": [{ "country": "US", "sessions": 700 }],
  "top_pages": [{ "path": "/", "views": 3000 }],
  "chart_data": {
    "labels": ["Jan 1", "Jan 2"],
    "visitors": [100, 120],
    "sessions": [150, 180],
    "pageviews": [400, 450],
    "conversions": [3, 5],
    "revenue": [300.0, 500.0]
  },
  "time_intelligence": {
    "best_converting_day": { "day": "tuesday", "conversion_rate": 5.2 },
    "peak_hours": [{ "hour": 14, "visitors": 80, "conversions": 5 }],
    "weekend_vs_weekday": { "weekend": {}, "weekday": {} }
  },
  "data_updated_at": "2026-02-25 12:00:00"
}
```

#### **GET /ts/v1/query/realtime**

Real-time active users, pages, events, devices, countries, and sources (last 5 minutes).

**Parameters**: None

**Response** (200):

```json
{
  "active_users": 12,
  "active_pages": [{ "path": "/shop", "users": 5 }],
  "active_devices": [{ "device": "desktop", "users": 8 }],
  "active_countries": [{ "country": "US", "users": 6 }],
  "active_sources": [{ "source": "google", "medium": "organic", "users": 4 }],
  "recent_events": [
    {
      "event": "page_view",
      "page": "/product/widget",
      "title": "Widget Pro",
      "time": 1740479400,
      "is_conversion": false
    }
  ],
  "timestamp": 1740479400
}
```

### Session & Journey Queries

#### **GET /ts/v1/query/sessions**

Paginated sessions list with date filtering.

**Parameters**:

- `date_start`, `date_end` (string) — Date range
- `page` (integer, default: 1) — Page number
- `per_page` (integer, default: 20, max: 100) — Items per page

**Response** (200):

```json
{
  "sessions": [
    { "session_id": "...", "visitor_id": 1, "started_at": "...", "...": "..." }
  ],
  "total": 500,
  "page": 1,
  "per_page": 20,
  "total_pages": 25
}
```

#### **GET /ts/v1/query/journey/{session_id}**

Full event timeline for a single session.

**Parameters**:

- `session_id` (string, required) — Session UUID (path parameter)

**Response** (200):

```json
{
  "session": { "session_id": "...", "started_at": "..." },
  "events": [
    { "event_name": "page_view", "occurred_at": "...", "event_params": {} }
  ],
  "touchpoints": []
}
```

#### **GET /ts/v1/query/visitor/{visitor_id}/journey**

Complete multi-session journey for a visitor.

**Parameters**:

- `visitor_id` (integer, required) — Visitor ID (path parameter, must be > 0)

**Response** (200):

```json
{
  "visitor_id": 42,
  "sessions": [
    {
      "session_id": "...",
      "events": [{ "event_name": "page_view", "...": "..." }]
    }
  ]
}
```

#### **GET /ts/v1/query/visitors**

Visitors list for the Journeys page with aggregated cross-session data.

**Parameters**:

- `date_start`, `date_end` (string) — Date range
- `filter` (string, default: `all`, enum: `all`, `converted`, `returning`) — Visitor filter

**Response** (200):

```json
{
  "visitors": [
    {
      "visitor_id": 42,
      "first_seen": 1740300000,
      "last_seen": 1740479400,
      "session_count": 3,
      "conversions": 1,
      "revenue": 99.99,
      "first_touch": "google/organic",
      "last_touch": "(direct)/(none)",
      "devices": "desktop,mobile"
    }
  ],
  "total": 150
}
```

#### **GET /ts/v1/query/funnel**

Custom funnel analysis for a sequence of events.

**Parameters**:

- `date_start`, `date_end` (string) — Date range
- `steps` (array, required) — Array of event names defining funnel steps

**Response** (200):

```json
{
  "steps": ["page_view", "add_to_cart", "purchase"],
  "data": []
}
```

### Page & Traffic Queries

#### **GET /ts/v1/query/pages**

Page performance metrics with time-on-page, bounce rates, and breakdowns.

**Parameters**:

- `date_start`, `date_end` (string) — Date range

**Response** (200):

```json
{
  "totals": {
    "total_views": 15000,
    "total_sessions": 5000,
    "total_conversions": 120,
    "total_revenue": 12000.0,
    "unique_pages": 85
  },
  "pages": [
    {
      "path": "/product/widget",
      "title": "Widget Pro",
      "views": 500,
      "sessions": 350,
      "conversions": 12,
      "revenue": 1200.0,
      "conversion_rate": 3.43,
      "aov": 100.0,
      "time": "2:35",
      "bounce": "38.5%"
    }
  ],
  "breakdowns": {
    "devices": [{ "device": "desktop", "sessions": 300, "pageviews": 800 }],
    "countries": [
      { "country_code": "US", "country": "United States", "sessions": 200 }
    ],
    "sources": [{ "source": "google / organic", "sessions": 150 }]
  }
}
```

#### **GET /ts/v1/query/active-pages**

Real-time active pages (currently being viewed).

**Parameters**:

- `minutes` (integer, default: 5, min: 1, max: 60) — Lookback window in minutes

**Response** (200):

```json
{
  "pages": [
    {
      "path": "/shop",
      "title": "Shop",
      "active_users": 5,
      "last_activity": "2026-02-25 12:00:00",
      "recent_conversions": 1
    }
  ],
  "total_active_users": 12,
  "timestamp": "2026-02-25 12:00:00"
}
```

#### **GET /ts/v1/query/traffic-sources**

Traffic source/medium breakdown with attribution data.

**Parameters**:

- `date_start`, `date_end` (string) — Date range

**Response** (200):

```json
{
  "sources": [
    {
      "source": "google",
      "medium": "organic",
      "sessions": 500,
      "unique_visitors": 420,
      "conversions": 15,
      "revenue": 1500.0,
      "conversion_rate": 3.0,
      "aov": 100.0,
      "first_touch_conversions": 10,
      "first_touch_revenue": 1000.0,
      "last_touch_conversions": 12,
      "last_touch_revenue": 1200.0
    }
  ],
  "total_conversions": 45,
  "unique_visitors": 1250
}
```

### Attribution Queries

#### **GET /ts/v1/query/attribution**

Multi-touch attribution analysis by source/medium with model selection.

**Parameters**:

- `date_start`, `date_end` (string) — Date range
- `model` (string, default: `last_touch`, enum: `first_touch`, `last_touch`, `linear`, `time_decay`, `position_based`) — Attribution model

**Response** (200):

```json
{
  "model": "last_touch",
  "sources": [
    {
      "source": "google",
      "medium": "cpc",
      "channel": "paid_search",
      "conversions": 25,
      "revenue": 2500.0,
      "avg_credit": 0.85,
      "visitors": 300
    }
  ],
  "total_conversions": 45,
  "total_revenue": 4500.0
}
```

#### **GET /ts/v1/query/attribution/insights**

Aggregated journey insights across all visitors.

**Parameters**:

- `date_start`, `date_end` (string) — Date range

**Response** (200): Journey insight data from the attribution analytics engine.

#### **GET /ts/v1/query/attribution/paths**

Top conversion paths (sequences of touchpoints leading to conversion).

**Parameters**:

- `date_start`, `date_end` (string) — Date range
- `limit` (integer, default: 20, min: 1, max: 100) — Max paths to return

**Response** (200):

```json
{
  "paths": [
    {
      "path": ["google/organic", "direct/(none)", "google/cpc"],
      "conversions": 5,
      "revenue": 500.0
    }
  ],
  "total": 15
}
```

#### **GET /ts/v1/query/attribution/device-patterns**

Device journey patterns — how users switch devices across touchpoints.

**Parameters**:

- `date_start`, `date_end` (string) — Date range

**Response** (200):

```json
{
  "patterns": [{ "pattern": "mobile → desktop", "conversions": 8 }],
  "total": 5
}
```

#### **GET /ts/v1/query/attribution/models**

Side-by-side comparison of all attribution models for the same data.

**Parameters**:

- `date_start`, `date_end` (string) — Date range

**Response** (200):

```json
{
  "models": {
    "first_touch": { "...": "..." },
    "last_touch": { "...": "..." },
    "linear": { "...": "..." },
    "time_decay": { "...": "..." },
    "position_based": { "...": "..." }
  },
  "available_models": [
    "first_touch",
    "last_touch",
    "linear",
    "time_decay",
    "position_based"
  ]
}
```

### Conversion Queries

#### **GET /ts/v1/query/conversions/breakdown**

Single-touch vs multi-touch conversion breakdown.

**Parameters**:

- `date_start`, `date_end` (string) — Date range

**Response** (200): Conversion breakdown data (single vs multi-touch counts and percentages).

#### **GET /ts/v1/query/conversions/time-to-convert**

Time-to-conversion histogram (bucketed distribution of how long visitors take to convert).

**Parameters**:

- `date_start`, `date_end` (string) — Date range

**Response** (200):

```json
{
  "buckets": [
    { "label": "< 1 hour", "count": 10 },
    { "label": "1-24 hours", "count": 25 }
  ],
  "total": 45
}
```

### Utility Queries

#### **GET /ts/v1/query/registry**

Events/parameters registry for admin UI (returns events, destinations, available attribution models).

**Parameters**: None

**Response** (200):

```json
{
  "version": 1,
  "events": [
    { "name": "page_view", "display_name": "Viewed Page", "...": "..." }
  ],
  "destinations": [],
  "models": [
    "first_touch",
    "last_touch",
    "linear",
    "time_decay",
    "position_based"
  ]
}
```

#### **GET /ts/v1/query/logs**

Recent error/warning logs from the TrackSure logger.

**Parameters**:

- `limit` (integer, default: 20, min: 1, max: 100) — Number of log entries
- `level` (string, enum: `error`, `warning`, `info`, `debug`) — Filter by log level

**Response** (200):

```json
{
  "logs": [
    { "level": "error", "message": "...", "context": {}, "created_at": "..." }
  ],
  "count": 5,
  "limit": 20,
  "level": "error"
}
```

---

## 🎯 **Goals API**

**Controller**: `TrackSure_REST_Goals_Controller`  
**File**: `includes/core/api/class-tracksure-rest-goals-controller.php`

All goals endpoints require `manage_options` capability.

### CRUD Operations

#### **GET /ts/v1/goals**

List all goals (ordered by creation date, newest first).

**Response** (200):

```json
{
  "goals": [
    {
      "goal_id": 1,
      "name": "Purchase Completion",
      "description": "User completes a purchase",
      "event_name": "purchase",
      "conditions": [
        { "param": "value", "operator": "greater_than", "value": "0" }
      ],
      "trigger_type": "event",
      "match_logic": "all",
      "trigger_config": null,
      "value_type": "fixed",
      "fixed_value": 25.0,
      "frequency": "every",
      "cooldown_minutes": 0,
      "is_active": true,
      "created_at": "2026-01-15 10:00:00",
      "updated_at": "2026-01-15 10:00:00"
    }
  ],
  "total": 5
}
```

#### **POST /ts/v1/goals**

Create a new goal. Input is validated via `TrackSure_Goal_Validator`.

**Parameters**:

- `name` (string, required) — Goal name
- `description` (string) — Goal description
- `event_name` (string, required) — Event name to track
- `trigger_type` (string) — Trigger type (e.g., `event`)
- `conditions` (array) — Array of condition objects
- `match_logic` (string) — `all` or `any`
- `trigger_config` (object) — Additional trigger configuration
- `value_type` (string) — Value type (e.g., `fixed`, `dynamic`)
- `fixed_value` (number) — Fixed conversion value
- `frequency` (string) — Frequency (`every`, `once`, etc.)
- `cooldown_minutes` (integer) — Cooldown between conversions
- `is_active` (boolean) — Whether goal is active

**Response** (200): The created goal object.

#### **PUT /ts/v1/goals/{id}**

Update an existing goal.

**Parameters**: Same as POST, plus:

- `id` (integer, required) — Goal ID (path parameter)

**Response** (200): The updated goal object.

#### **DELETE /ts/v1/goals/{id}**

Delete a goal.

**Parameters**:

- `id` (integer, required) — Goal ID (path parameter)

**Response** (200):

```json
{ "success": true }
```

### Performance & Analytics

#### **GET /ts/v1/goals/{id}/performance**

Performance metrics for a single goal.

**Parameters**:

- `id` (integer, required) — Goal ID (path parameter)
- `date_start` (string/date, required) — Start date
- `date_end` (string/date, required) — End date

**Response** (200): Goal conversion metrics for the date range.

#### **GET /ts/v1/goals/performance**

Batch performance for multiple goals in a single request.

**Parameters**:

- `goal_ids` (string, required) — Comma-separated list of goal IDs (e.g., `1,2,3`)
- `date_start` (string/date) — Start date
- `date_end` (string/date) — End date

**Response** (200): Performance data keyed by goal ID.

#### **GET /ts/v1/goals/{id}/timeline**

Paginated conversion timeline for a goal.

**Parameters**:

- `id` (integer, required) — Goal ID (path parameter)
- `page` (integer, default: 1) — Page number
- `per_page` (integer, default: 20, max: 100) — Items per page
- `date_start` (string/date) — Start date filter
- `date_end` (string/date) — End date filter

**Response** (200): Paginated list of conversion events.

#### **GET /ts/v1/goals/{id}/sources**

Source attribution for goal conversions.

**Parameters**:

- `id` (integer, required) — Goal ID (path parameter)
- `attribution_model` (string, default: `last_touch`, enum: `first_touch`, `last_touch`, `linear`, `time_decay`, `position_based`) — Attribution model
- `date_start` (string/date) — Start date filter
- `date_end` (string/date) — End date filter

**Response** (200): Source/medium breakdown of goal conversions.

#### **GET /ts/v1/goals/{id}/devices**

Device and browser distribution for goal conversions.

**Parameters**:

- `id` (integer, required) — Goal ID (path parameter)
- `date_start` (string/date) — Start date filter
- `date_end` (string/date) — End date filter

**Response** (200): Device type and browser breakdown.

#### **GET /ts/v1/goals/overview**

Goals overview dashboard — aggregated metrics across all goals.

**Parameters**:

- `start_date` (string/date) — Start date for reporting period (YYYY-MM-DD)
- `end_date` (string/date) — End date for reporting period (YYYY-MM-DD)

**Response** (200): Aggregated goal performance data.

---

## ⚙️ **Settings API**

**Controller**: `TrackSure_REST_Settings_Controller`  
**File**: `includes/core/api/class-tracksure-rest-settings-controller.php`

All settings endpoints require `manage_options` capability.

### **GET /ts/v1/settings**

Get all settings. Returns all REST-exposed settings from `TrackSure_Settings_Schema` with proper type casting. Cached for 5 minutes (auto-cleared on update).

**Response** (200):

```json
{
  "tracksure_tracking_enabled": true,
  "tracksure_track_admins": false,
  "tracksure_session_timeout": 30,
  "tracksure_data_retention_days": 90,
  "tracksure_consent_mode": "disabled",
  "tracksure_respect_dnt": false,
  "tracksure_exclude_ips": "",
  "_enabled_destinations": ["meta", "ga4"],
  "_detected_integrations": ["woocommerce"]
}
```

### **PUT /ts/v1/settings**

Update settings. Accepts a flat key-value JSON body. Each setting is validated against `TrackSure_Settings_Schema`. Type casting is applied automatically. Caches are cleared after save.

**Request**:

```json
{
  "tracksure_tracking_enabled": true,
  "tracksure_session_timeout": 45,
  "tracksure_respect_dnt": true
}
```

**Response** (200):

```json
{
  "success": true,
  "updated": ["tracksure_session_timeout", "tracksure_respect_dnt"]
}
```

---

## 🔍 **Diagnostics API**

**Controller**: `TrackSure_REST_Diagnostics_Controller`  
**File**: `includes/core/api/class-tracksure-rest-diagnostics-controller.php`

All diagnostics endpoints require `manage_options` capability.

### **GET /ts/v1/diagnostics/cron**

WordPress cron health check. Lists TrackSure scheduled jobs, custom schedules, and cron status.

**Response** (200):

```json
{
  "cron_enabled": true,
  "cron_disabled": false,
  "current_time": "2026-02-25 12:00:00",
  "tracksure_jobs": [
    {
      "hook": "tracksure_hourly_cleanup",
      "next_run": "2026-02-25 13:00:00",
      "timestamp": 1740484800,
      "schedule": "hourly",
      "args": []
    }
  ],
  "tracksure_schedules": {},
  "total_cron_jobs": 25,
  "status": "healthy"
}
```

### **GET /ts/v1/diagnostics/health**

Comprehensive system health check — database, tables, tracking status, recent events, delivery stats.

**Response** (200):

```json
{
  "status": "healthy",
  "checks": {
    "database": { "status": "healthy", "message": "Database connection OK" },
    "tables": { "status": "healthy", "message": "All tables exist" },
    "tracking": {
      "status": "healthy",
      "message": "Tracking is active (excluding administrators)"
    },
    "recent_events": {
      "count": 42,
      "period": "5 minutes",
      "status": "healthy",
      "message": "42 events received in last 5 minutes"
    },
    "delivery": {
      "status": "healthy",
      "message": "Browser: 95.0%, Server: 88.0%, Both: 85.0%"
    }
  },
  "delivery_stats": {
    "total": 1500,
    "browser_count": 1425,
    "server_count": 1320,
    "both_count": 1275,
    "browser_percent": 95.0,
    "server_percent": 88.0,
    "both_percent": 85.0
  },
  "timestamp": 1740479400
}
```

### **GET /ts/v1/diagnostics/delivery**

Detailed delivery statistics (browser-fired vs server-fired vs both), broken down by event type and over time.

**Parameters**:

- `period` (string, default: `7d`, enum: `1h`, `24h`, `7d`, `30d`) — Time period for stats

**Response** (200):

```json
{
  "period": "7d",
  "overall": {
    "total": 10000,
    "browser_count": 9500,
    "server_count": 8800,
    "both_count": 8500,
    "server_only": 300,
    "browser_only": 700,
    "browser_percent": 95.0,
    "server_percent": 88.0,
    "both_percent": 85.0
  },
  "by_event": [
    {
      "event_name": "page_view",
      "total": 5000,
      "browser_count": 4800,
      "server_count": 4500,
      "both_count": 4400
    }
  ],
  "timeline": [
    {
      "time_bucket": "2026-02-24",
      "total": 1500,
      "browser_count": 1425,
      "server_count": 1320,
      "both_count": 1275
    }
  ]
}
```

---

## 🍪 **Consent API**

**Controller**: `TrackSure_REST_Consent_Controller`  
**File**: `includes/core/api/class-tracksure-rest-consent-controller.php`

Mixed permissions: status/metadata/state use `check_read_permission`, warning uses `check_admin_permission`, and the consent update endpoint is public (browser-initiated).

### **GET /ts/v1/consent/status**

Get consent configuration and current status.

**Permission**: `check_read_permission`

**Response** (200):

```json
{
  "consent_mode": "disabled",
  "is_tracking_allowed": true,
  "has_consent_plugin": true,
  "consent_metadata": {}
}
```

### **GET /ts/v1/consent/warning**

Get consent warning for the React admin panel (shows if consent may be misconfigured).

**Permission**: `manage_options`

**Response** (200):

```json
{
  "show_warning": true,
  "type": "no_consent_plugin",
  "message": "No consent management plugin detected..."
}
```

### **POST /ts/v1/consent/warning/dismiss**

Dismiss the consent warning for the current admin user.

**Permission**: `manage_options` + nonce

**Response** (200):

```json
{
  "success": true,
  "message": "Consent warning dismissed successfully."
}
```

### **GET /ts/v1/consent/metadata**

Get consent metadata for events (which consent categories apply).

**Permission**: `check_read_permission`

**Response** (200): Consent metadata object from `TrackSure_Consent_Manager`.

### **GET /ts/v1/consent/state**

Get Google Consent Mode V2 state — current consent status for all categories, detected plugin, and supported plugins list.

**Permission**: `check_read_permission`

**Response** (200):

```json
{
  "consent_mode": "disabled",
  "tracking_allowed": true,
  "detected_plugin": "complianz",
  "consent_state": {
    "ad_storage": "denied",
    "analytics_storage": "granted",
    "ad_user_data": "denied",
    "ad_personalization": "denied"
  },
  "supported_plugins": [
    {
      "id": "complianz",
      "name": "Complianz GDPR/CCPA",
      "slug": "complianz-gdpr",
      "recommended": true
    }
  ]
}
```

### **POST /ts/v1/consent/update**

Update consent state from the browser (e.g., when user grants/denies consent in a cookie banner). Public endpoint for browser-initiated consent changes.

**Permission**: Public (`__return_true`)

**Parameters**:

- `consent_state` (object, required) — Must contain `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`, each set to `"granted"` or `"denied"`

**Response** (200):

```json
{
  "success": true,
  "message": "Consent state updated successfully.",
  "state": {
    "ad_storage": "granted",
    "analytics_storage": "granted",
    "ad_user_data": "denied",
    "ad_personalization": "denied"
  }
}
```

---

## 📋 **Registry API**

**Controller**: `TrackSure_REST_Registry_Controller`  
**File**: `includes/core/api/class-tracksure-rest-registry-controller.php`

All registry endpoints are **public** (`__return_true`) because the browser SDK needs to load event/parameter definitions for client-side validation. Data is read-only and non-sensitive.

### **GET /ts/v1/registry/events**

Get all event definitions as a keyed map.

**Response** (200):

```json
{
  "events": {
    "page_view": {
      "name": "page_view",
      "display_name": "Viewed Page",
      "category": "engagement"
    },
    "add_to_cart": {
      "name": "add_to_cart",
      "display_name": "Added to Cart",
      "category": "ecommerce"
    }
  },
  "count": 50
}
```

### **GET /ts/v1/registry/params**

Get all parameter definitions as a keyed map.

**Response** (200):

```json
{
  "parameters": {
    "page_url": {
      "name": "page_url",
      "type": "string",
      "description": "Full page URL"
    },
    "item_id": {
      "name": "item_id",
      "type": "string",
      "description": "Product ID"
    }
  },
  "count": 120
}
```

### **GET /ts/v1/registry**

Get the full registry (events + parameters + version) in a single request. Primary endpoint used by the browser SDK on initialization.

**Response** (200):

```json
{
  "events": { "page_view": { "...": "..." } },
  "parameters": { "page_url": { "...": "..." } },
  "version": "1.0.0"
}
```

### **GET /ts/v1/registry/validate/{event}**

Validate whether an event name exists in the registry.

**Parameters**:

- `event` (string, required) — Event name to validate (path parameter, alphanumeric + hyphens/underscores)

**Response** (200, valid event):

```json
{
  "valid": true,
  "event": { "name": "page_view", "display_name": "Viewed Page", "...": "..." }
}
```

**Response** (200, invalid event):

```json
{
  "valid": false,
  "message": "Event \"foo_bar\" is not registered"
}
```

---

## 🛍️ **Products API**

**Controller**: `TrackSure_REST_Products_Controller`  
**File**: `includes/core/api/class-tracksure-rest-products-controller.php`

WooCommerce product analytics. All endpoints require `manage_options` capability. Results are cached for 5 minutes via `wp_cache`.

### **GET /ts/v1/products/performance**

Product performance analytics — views, add-to-carts, purchases, revenue, conversion rates.

**Parameters**:

- `date_start`, `date_end` (string) — Date range
- `limit` (integer, default: 20, min: 1, max: 500) — Max products to return
- `order_by` (string, default: `revenue`, enum: `revenue`, `views`, `conversions`, `conversion_rate`, `product_name`) — Sort field
- `order` (string, default: `desc`, enum: `asc`, `desc`) — Sort direction

**Response** (200):

```json
[
  {
    "product_id": "123",
    "product_name": "Widget Pro",
    "views": 500,
    "add_to_carts": 50,
    "purchases": 25,
    "revenue": 1250.0,
    "conversion_rate": 5.0
  }
]
```

### **GET /ts/v1/products/categories**

Category-level performance (requires WooCommerce).

**Parameters**:

- `date_start`, `date_end` (string) — Date range

**Response** (200):

```json
[
  {
    "category_id": 15,
    "category_name": "Electronics",
    "views": 1200,
    "add_to_carts": 100,
    "purchases": 45,
    "revenue": 5400.0
  }
]
```

### **GET /ts/v1/products/funnel**

Product purchase funnel: view_item → add_to_cart → begin_checkout → purchase.

**Parameters**:

- `date_start`, `date_end` (string) — Date range
- `product_id` (integer) — Optional product ID filter

**Response** (200):

```json
{
  "funnel": [
    {
      "step": "view_item",
      "label": "Product Views",
      "count": 1000,
      "drop_off": 0
    },
    {
      "step": "add_to_cart",
      "label": "Add to Cart",
      "count": 200,
      "drop_off": 80.0
    },
    {
      "step": "begin_checkout",
      "label": "Begin Checkout",
      "count": 100,
      "drop_off": 50.0
    },
    { "step": "purchase", "label": "Purchase", "count": 60, "drop_off": 40.0 }
  ],
  "conversion_rate": 6.0
}
```

---

## ✅ **Quality API**

**Controller**: `TrackSure_REST_Quality_Controller`  
**File**: `includes/core/api/class-tracksure-rest-quality-controller.php`

Data quality and signal health monitoring. All endpoints require `manage_options` capability. Results are cached for 10 minutes.

### **GET /ts/v1/quality/signal**

Signal quality scores per destination. Calculates a 0-100 score based on deduplication rate (40%), server-side coverage (40%), missing params rate (10%), and delivery success rate (10%).

**Parameters**:

- `destination` (string, default: `all`) — Destination ID or `all` for all enabled destinations (dynamic enum from Destinations Manager)

**Response** (200, single destination):

```json
{
  "destination": "meta",
  "quality_score": 92,
  "dedup_rate": 99.5,
  "server_side_coverage": 88.0,
  "delivery_success_rate": 95.0,
  "missing_params_rate": 2.5,
  "match_quality": "excellent",
  "last_7_days_events": 10000,
  "server_events": 8800,
  "delivered_events": 8360,
  "last_failed_event": null,
  "recommendations": [
    "✅ Excellent signal quality! Your tracking setup is performing optimally."
  ]
}
```

**Response** (200, `destination=all`): Object keyed by destination ID, each containing the structure above.

### **GET /ts/v1/quality/deduplication**

Deduplication statistics — identifies duplicate events (same `event_id` appearing multiple times) over the last 7 days.

**Response** (200):

```json
{
  "total_events": 10000,
  "unique_events": 9800,
  "duplicate_events": 200,
  "dedup_rate": 2.0,
  "by_event_type": [
    {
      "event_name": "page_view",
      "total": 5000,
      "duplicates": 100,
      "dedup_rate": 2.0
    }
  ]
}
```

### **GET /ts/v1/quality/schema**

Event schema validation — checks required parameters for key events (purchase, view_item, add_to_cart) over the last 7 days.

**Response** (200):

```json
{
  "schemas": [
    {
      "event_name": "purchase",
      "total_events": 200,
      "valid_events": 195,
      "invalid_events": 5,
      "missing_params": [
        {
          "param": "currency",
          "missing_count": 5,
          "missing_rate": 2.5,
          "severity": "error"
        }
      ],
      "status": "needs_attention"
    }
  ],
  "summary": {
    "total_schemas": 3,
    "valid_schemas": 2,
    "invalid_schemas": 1
  }
}
```

### **GET /ts/v1/quality/reconciliation**

Cross-platform reconciliation — compares TrackSure event counts vs Meta and GA4 delivery counts (last 7 days). Includes explanations for differences.

**Response** (200):

```json
{
  "comparison": [
    {
      "event_name": "purchase",
      "tracksure": 200,
      "meta": 185,
      "ga4": 190,
      "meta_diff": 15,
      "ga4_diff": 10,
      "meta_coverage": 92.5,
      "ga4_coverage": 95.0
    }
  ],
  "explainer": {
    "why_different": [
      {
        "reason": "Browser-side only events",
        "description": "Ad blockers block 30-50% of browser pixel events."
      },
      {
        "reason": "Deduplication",
        "description": "TrackSure deduplicates by event_id. Destinations may double-count."
      },
      {
        "reason": "Consent blocking",
        "description": "Users who decline consent won't fire browser pixels."
      },
      {
        "reason": "Delayed processing",
        "description": "Platforms may take 24-48h to process server events."
      },
      {
        "reason": "Event filtering",
        "description": "Some events may not be mapped to certain destinations."
      }
    ]
  }
}
```

---

## 💡 **Suggestions API**

**Controller**: `TrackSure_REST_Suggestions_Controller`  
**File**: `includes/core/api/class-tracksure-rest-suggestions-controller.php`

Rule-based Smart Insights engine. Requires `manage_options` capability.

### **GET /ts/v1/suggestions**

Get actionable optimization suggestions based on 12 rule-based checks analyzing your tracking data.

**Parameters**:

- `limit` (integer, default: 5, min: 1, max: 20) — Maximum suggestions to return

**Response** (200):

```json
[
  {
    "type": "goal",
    "priority": "high",
    "title": "Create goal for cart abandonment",
    "description": "50% of users add to cart but don't complete purchase",
    "action": { "type": "create_goal" }
  }
]
```

---

## 📞 **Pixel Callbacks**

**Controller**: `TrackSure_REST_Pixel_Callback_Controller`  
**File**: `includes/core/api/class-tracksure-rest-pixel-callback-controller.php`

> **Note**: This controller extends `WP_REST_Controller` directly (not `TrackSure_REST_Controller`). Namespace is `ts/v1`, rest base is `cb`.

### **POST /ts/v1/cb**

Browser pixel confirmation callback. Called by the browser JS after a destination pixel (Meta, GA4, etc.) fires successfully. Updates the event record to set `browser_fired = 1` and appends the destination to `destinations_sent`.

**Permission**: Public (`__return_true`) — browser SDK confirms pixel from anonymous visitors.

**Parameters**:

- `event_id` (string, required) — Event UUID (validated as UUID v4 format)
- `destination` (string, required) — Destination name (e.g., `meta`, `ga4`)
- `status` (string, default: `success`, enum: `success`, `error`) — Pixel firing status

**Response** (200):

```json
{
  "success": true,
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "message": "Browser pixel confirmation recorded"
}
```

**Error** (404):

```json
{
  "success": false,
  "message": "Event not found"
}
```

---

## ❌ **Error Codes**

**HTTP Status Codes**:

- `200` - Success
- `201` - Created
- `400` - Bad Request (validation error)
- `401` - Unauthorized (invalid nonce)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `500` - Internal Server Error

**Custom Error Codes**:

```json
{
  "code": "tracksure_invalid_event",
  "message": "Event validation failed",
  "data": {
    "status": 400,
    "errors": ["Missing required parameter: event_name"]
  }
}
```

**Common Errors**:

- `tracksure_invalid_event` - Event validation failed
- `tracksure_invalid_nonce` - Invalid or expired nonce
- `tracksure_permission_denied` - Insufficient permissions
- `tracksure_not_found` - Resource not found
- `tracksure_database_error` - Database operation failed

---

## 📖 **See Also**

- [CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md) - REST API architecture
- [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md) - API testing guide
- [FRONTEND_SDK.md](FRONTEND_SDK.md) - Browser SDK integration

---

**Last Updated**: February 25, 2026  
**API Version**: 1.0.0  
**Total Endpoints**: 55+
