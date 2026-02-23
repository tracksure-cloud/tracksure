# 🗄️ TrackSure Database Schema

Complete database schema reference for TrackSure analytics platform.

---

## 📊 **Overview**

TrackSure uses **14 custom database tables** to store visitor tracking, events, conversions, and analytics data.

**Tables**:

- 3 Core tracking tables (visitors, sessions, events)
- 3 Goal/conversion tables (goals, conversions, conversion_attribution)
- 2 Attribution tables (touchpoints, click_ids)
- 1 Delivery table (outbox)
- 3 Aggregation tables (agg_hourly, agg_daily, agg_product_daily)
- 2 Funnel tables (funnels, funnel_steps)

**Total**: 14 tables

---

## 📁 **Table of Contents**

1. [Core Tracking Tables](#core-tracking-tables)
2. [Goal & Conversion Tables](#goal--conversion-tables)
3. [Attribution Tables](#attribution-tables)
4. [Delivery Table](#delivery-table)
5. [Aggregation Tables](#aggregation-tables)
6. [Funnel Tables](#funnel-tables)
7. [Indexes & Performance](#indexes--performance)
8. [Entity Relationship Diagram](#entity-relationship-diagram)

---

## 🎯 **Core Tracking Tables**

### **1. wp_tracksure_visitors**

Stores unique visitors (identified by client_id cookie).

```sql
CREATE TABLE wp_tracksure_visitors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BINARY(16) NOT NULL,           -- UUID stored as binary
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    session_count INT UNSIGNED DEFAULT 0,
    event_count INT UNSIGNED DEFAULT 0,
    first_referrer TEXT,
    first_utm_source VARCHAR(255),
    first_utm_medium VARCHAR(255),
    first_utm_campaign VARCHAR(255),
    first_landing_page TEXT,
    country_code CHAR(2),                    -- ISO 3166-1 alpha-2
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    PRIMARY KEY (id),
    UNIQUE KEY client_id (client_id),
    KEY first_seen_at (first_seen_at),
    KEY last_seen_at (last_seen_at),
    KEY country_code (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Columns**:

- `id` - Auto-increment primary key
- `client_id` - UUID (binary format for performance)
- `first_seen_at` - First visit timestamp
- `last_seen_at` - Most recent visit timestamp
- `session_count` - Total number of sessions
- `event_count` - Total number of events
- `first_referrer` - Initial referrer URL
- `first_utm_source` - First UTM source
- `first_utm_medium` - First UTM medium
- `first_utm_campaign` - First UTM campaign
- `first_landing_page` - Initial landing page
- `country_code` - Visitor country (from IP)

**Relationships**:

- One visitor → Many sessions
- One visitor → Many events

---

### **2. wp_tracksure_sessions**

Stores visitor sessions (30-minute timeout).

```sql
CREATE TABLE wp_tracksure_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BINARY(16) NOT NULL,          -- UUID stored as binary
    visitor_id BIGINT UNSIGNED NOT NULL,
    client_id BINARY(16) NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME,
    event_count INT UNSIGNED DEFAULT 0,
    page_view_count INT UNSIGNED DEFAULT 0,
    referrer TEXT,
    utm_source VARCHAR(255),
    utm_medium VARCHAR(255),
    utm_campaign VARCHAR(255),
    utm_content VARCHAR(255),
    utm_term VARCHAR(255),
    landing_page TEXT,
    exit_page TEXT,
    device_type VARCHAR(50),                 -- desktop, mobile, tablet
    browser VARCHAR(100),
    os VARCHAR(100),
    country_code CHAR(2),
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    PRIMARY KEY (id),
    UNIQUE KEY session_id (session_id),
    KEY visitor_id (visitor_id),
    KEY client_id (client_id),
    KEY started_at (started_at),
    KEY device_type (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Columns**:

- `session_id` - Unique session identifier (UUID)
- `visitor_id` - Foreign key to visitors table
- `client_id` - Denormalized for faster queries
- `started_at` - Session start time
- `ended_at` - Session end time (updated on last event)
- `event_count` - Total events in session
- `page_view_count` - Page views in session
- `referrer` - Session referrer
- `utm_*` - Campaign tracking parameters
- `landing_page` - First page in session
- `exit_page` - Last page in session
- `device_type` - Device category
- `browser` - Browser name
- `os` - Operating system

**Relationships**:

- Many sessions → One visitor
- One session → Many events

---

### **3. wp_tracksure_events**

Stores all tracked events.

```sql
CREATE TABLE wp_tracksure_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(191) NOT NULL,          -- UUID string format
    event_name VARCHAR(100) NOT NULL,
    event_data LONGTEXT,                     -- JSON blob
    session_id BINARY(16),
    visitor_id BIGINT UNSIGNED,
    client_id BINARY(16),
    user_id BIGINT UNSIGNED,                 -- WordPress user ID (if logged in)
    event_time DATETIME NOT NULL,
    anonymized TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY event_id (event_id),
    KEY event_name (event_name),
    KEY session_id (session_id),
    KEY visitor_id (visitor_id),
    KEY client_id (client_id),
    KEY event_time (event_time),
    KEY created_at (created_at),
    KEY event_name_time (event_name, event_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Columns**:

- `event_id` - Unique event identifier
- `event_name` - Event type (page_view, purchase, etc.)
- `event_data` - Full event JSON (params, user_data, etc.)
- `session_id` - Associated session
- `visitor_id` - Associated visitor
- `client_id` - Denormalized client_id
- `user_id` - WordPress user (if logged in)
- `event_time` - When event occurred
- `anonymized` - Whether personal data was anonymized
- `created_at` - Database insertion time

**JSON Structure (`event_data`)**:

```json
{
  "event_name": "purchase",
  "params": {
    "transaction_id": "12345",
    "value": 99.99,
    "currency": "USD",
    "items": [...]
  },
  "user_data": {
    "email": "user@example.com",
    "phone": "+1234567890",
    "address": {...}
  },
  "session_data": {...},
  "device": {...},
  "utm": {...}
}
```

**Relationships**:

- Many events → One session
- Many events → One visitor

---

## 🎯 **Goal & Conversion Tables**

### **4. wp_tracksure_goals**

Stores goal definitions.

```sql
CREATE TABLE wp_tracksure_goals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    goal_type VARCHAR(50) NOT NULL,          -- event, url, duration
    conditions LONGTEXT,                     -- JSON conditions
    value DECIMAL(10,2) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    PRIMARY KEY (id),
    KEY active (active),
    KEY goal_type (goal_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Goal Types**:

- `event` - Triggered by specific event (e.g., purchase)
- `url` - Triggered by page visit
- `duration` - Triggered by session duration

**Example Conditions**:

```json
{
  "event_name": "purchase",
  "params": {
    "value": { "operator": ">=", "value": 100 }
  }
}
```

---

### **5. wp_tracksure_conversions**

Stores goal conversions.

```sql
CREATE TABLE wp_tracksure_conversions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversion_id VARCHAR(191) NOT NULL,
    goal_id BIGINT UNSIGNED NOT NULL,
    event_id VARCHAR(191),
    session_id BINARY(16),
    visitor_id BIGINT UNSIGNED,
    client_id BINARY(16),
    conversion_value DECIMAL(10,2) DEFAULT 0,
    converted_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY conversion_id (conversion_id),
    KEY goal_id (goal_id),
    KEY event_id (event_id),
    KEY session_id (session_id),
    KEY visitor_id (visitor_id),
    KEY converted_at (converted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships**:

- Many conversions → One goal
- One conversion → One event
- One conversion → One session

---

### **6. wp_tracksure_conversion_attribution**

Multi-touch attribution for conversions.

```sql
CREATE TABLE wp_tracksure_conversion_attribution (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversion_id VARCHAR(191) NOT NULL,
    touchpoint_id BIGINT UNSIGNED NOT NULL,
    attribution_model VARCHAR(50) NOT NULL,   -- last_click, first_click, linear
    attribution_credit DECIMAL(5,4) DEFAULT 0, -- 0.0000 to 1.0000
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY conversion_id (conversion_id),
    KEY touchpoint_id (touchpoint_id),
    KEY attribution_model (attribution_model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Attribution Models**:

- `last_click` - 100% credit to last touchpoint
- `first_click` - 100% credit to first touchpoint
- `linear` - Equal credit distributed
- `time_decay` - More recent gets more credit
- `position_based` - 40% first, 40% last, 20% middle

---

## 🎯 **Attribution Tables**

### **7. wp_tracksure_touchpoints**

Marketing touchpoints in customer journey.

```sql
CREATE TABLE wp_tracksure_touchpoints (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_id BIGINT UNSIGNED NOT NULL,
    client_id BINARY(16) NOT NULL,
    session_id BINARY(16),
    touchpoint_type VARCHAR(50) NOT NULL,    -- click, impression, visit
    source VARCHAR(255),                     -- google, facebook, direct
    medium VARCHAR(255),                     -- cpc, organic, referral
    campaign VARCHAR(255),
    content VARCHAR(255),
    term VARCHAR(255),
    gclid VARCHAR(255),                      -- Google Click ID
    fbclid VARCHAR(255),                     -- Facebook Click ID
    referrer TEXT,
    landing_page TEXT,
    touched_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY visitor_id (visitor_id),
    KEY client_id (client_id),
    KEY session_id (session_id),
    KEY touched_at (touched_at),
    KEY source_medium (source, medium)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### **8. wp_tracksure_click_ids**

Stores click IDs (GCLID, FBCLID, etc.) with expiration.

```sql
CREATE TABLE wp_tracksure_click_ids (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_id BIGINT UNSIGNED NOT NULL,
    client_id BINARY(16) NOT NULL,
    click_id_type VARCHAR(50) NOT NULL,      -- gclid, fbclid, msclkid
    click_id_value VARCHAR(500) NOT NULL,
    expires_at DATETIME,                     -- 90 days default
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY visitor_id (visitor_id),
    KEY client_id (client_id),
    KEY click_id_type (click_id_type),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Click ID Types**:

- `gclid` - Google Ads Click ID
- `fbclid` - Facebook Click ID
- `msclkid` - Microsoft Ads Click ID
- `ttclid` - TikTok Click ID

---

## 📤 **Delivery Table**

### **9. wp_tracksure_outbox**

Event delivery queue for destinations (GA4, Meta, etc.).

```sql
CREATE TABLE wp_tracksure_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(191) NOT NULL,
    destination VARCHAR(50) NOT NULL,        -- ga4, meta, tiktok
    event_data LONGTEXT NOT NULL,            -- Destination-specific JSON
    status VARCHAR(20) DEFAULT 'pending',    -- pending, delivered, failed
    attempts INT UNSIGNED DEFAULT 0,
    max_attempts INT UNSIGNED DEFAULT 3,
    error_message TEXT,
    created_at DATETIME NOT NULL,
    delivered_at DATETIME,
    PRIMARY KEY (id),
    KEY event_id (event_id),
    KEY destination (destination),
    KEY status (status),
    KEY created_at (created_at),
    KEY status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Status Values**:

- `pending` - Awaiting delivery
- `delivered` - Successfully sent
- `failed` - Max attempts exceeded

---

## 📊 **Aggregation Tables**

### **10. wp_tracksure_agg_hourly**

Hourly aggregated metrics.

```sql
CREATE TABLE wp_tracksure_agg_hourly (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hour_start DATETIME NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    dimension_name VARCHAR(100),
    dimension_value VARCHAR(255),
    metric_value BIGINT DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY hour_metric_dimension (hour_start, metric_name, dimension_name, dimension_value),
    KEY hour_start (hour_start),
    KEY metric_name (metric_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Example Metrics**:

- `visitors` - Unique visitors
- `sessions` - Total sessions
- `events` - Total events
- `revenue` - Total revenue

**Example Dimensions**:

- `source` - Traffic source
- `device` - Device type
- `country` - Country code

---

### **11. wp_tracksure_agg_daily**

Daily aggregated metrics.

```sql
CREATE TABLE wp_tracksure_agg_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    date DATE NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    dimension_name VARCHAR(100),
    dimension_value VARCHAR(255),
    metric_value BIGINT DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY date_metric_dimension (date, metric_name, dimension_name, dimension_value),
    KEY date (date),
    KEY metric_name (metric_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### **12. wp_tracksure_agg_product_daily**

Product-level daily aggregations.

```sql
CREATE TABLE wp_tracksure_agg_product_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    date DATE NOT NULL,
    product_id VARCHAR(191) NOT NULL,
    product_name VARCHAR(500),
    views INT UNSIGNED DEFAULT 0,
    add_to_carts INT UNSIGNED DEFAULT 0,
    purchases INT UNSIGNED DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY date_product (date, product_id),
    KEY date (date),
    KEY product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔀 **Funnel Tables**

### **13. wp_tracksure_funnels**

Funnel definitions.

```sql
CREATE TABLE wp_tracksure_funnels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    PRIMARY KEY (id),
    KEY active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### **14. wp_tracksure_funnel_steps**

Funnel step definitions.

```sql
CREATE TABLE wp_tracksure_funnel_steps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    funnel_id BIGINT UNSIGNED NOT NULL,
    step_order INT UNSIGNED NOT NULL,
    step_name VARCHAR(255) NOT NULL,
    step_type VARCHAR(50) NOT NULL,          -- event, url
    step_conditions LONGTEXT,                -- JSON
    required TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY funnel_id (funnel_id),
    KEY step_order (step_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## ⚡ **Indexes & Performance**

### **Primary Indexes**

All tables have:

- ✅ Auto-increment primary key
- ✅ Unique constraints on UUIDs
- ✅ Foreign key relationships (indexed)

### **Query Optimization Indexes**

**Time-based queries**:

```sql
KEY event_time (event_time)              -- Events by time
KEY started_at (started_at)              -- Sessions by start
KEY touched_at (touched_at)              -- Touchpoints by time
```

**Composite indexes**:

```sql
KEY event_name_time (event_name, event_time)  -- Event type + time
KEY status_created (status, created_at)        -- Outbox processing
KEY source_medium (source, medium)             -- Attribution queries
```

**UUID Performance**:

- UUIDs stored as BINARY(16) for 50% size reduction
- Conversion functions: `UUID_TO_BIN()`, `BIN_TO_UUID()`

---

## 🗺️ **Entity Relationship Diagram**

```
┌─────────────────┐
│   VISITOR       │
│  (client_id)    │
└────────┬────────┘
         │ 1
         │
         │ N
┌────────▼────────┐
│   SESSIONS      │
│  (session_id)   │
└────────┬────────┘
         │ 1
         │
         │ N
┌────────▼────────┐       ┌─────────────┐
│   EVENTS        │──────▶│  OUTBOX     │
│  (event_id)     │   N:N │ (delivery)  │
└────────┬────────┘       └─────────────┘
         │ 1
         │
         │ N
┌────────▼────────┐
│  CONVERSIONS    │
│(conversion_id)  │
└────────┬────────┘
         │ 1
         │
         │ N
┌────────▼────────┐       ┌──────────────┐
│   ATTRIBUTION   │──────▶│ TOUCHPOINTS  │
│  (multi-touch)  │   N:1 │  (journey)   │
└─────────────────┘       └──────────────┘
```

**Relationships**:

- 1 Visitor → Many Sessions → Many Events
- 1 Event → Many Outbox entries (one per destination)
- 1 Event → 1 Conversion (if goal met)
- 1 Conversion → Many Attribution records (multi-touch)
- 1 Visitor → Many Touchpoints (journey)

---

## 📝 **Common Queries**

### **Get visitor journey**:

```sql
SELECT * FROM wp_tracksure_events
WHERE client_id = UUID_TO_BIN('...')
ORDER BY event_time ASC;
```

### **Get session events**:

```sql
SELECT * FROM wp_tracksure_events
WHERE session_id = UUID_TO_BIN('...')
ORDER BY event_time ASC;
```

### **Get conversion attribution**:

```sql
SELECT c.*, ca.*, t.*
FROM wp_tracksure_conversions c
JOIN wp_tracksure_conversion_attribution ca ON c.conversion_id = ca.conversion_id
JOIN wp_tracksure_touchpoints t ON ca.touchpoint_id = t.id
WHERE c.conversion_id = '...'
ORDER BY t.touched_at ASC;
```

### **Get pending deliveries**:

```sql
SELECT * FROM wp_tracksure_outbox
WHERE status = 'pending'
AND attempts < max_attempts
ORDER BY created_at ASC
LIMIT 100;
```

---

## 🛠️ **Maintenance**

### **Cleanup Old Data**

Runs via `TrackSure_Cleanup_Worker`:

```sql
DELETE FROM wp_tracksure_events
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

DELETE FROM wp_tracksure_sessions
WHERE started_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### **Optimize Tables**

```sql
OPTIMIZE TABLE wp_tracksure_events;
OPTIMIZE TABLE wp_tracksure_sessions;
OPTIMIZE TABLE wp_tracksure_visitors;
```

---

## 📖 **See Also**

- [CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md) - System architecture
- [CLASS_REFERENCE.md](CLASS_REFERENCE.md) - TrackSure_DB class
- [REST_API_REFERENCE.md](REST_API_REFERENCE.md) - Query API endpoints

---

**Last Updated**: January 17, 2026  
**Schema Version**: 1.0.0  
**Total Tables**: 14
