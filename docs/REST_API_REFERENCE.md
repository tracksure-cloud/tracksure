# 📡 TrackSure REST API Reference

Complete REST API documentation for TrackSure v1.

---

## 🎯 **Overview**

**Base URL**: `/wp-json/tracksure/v1`  
**Authentication**: WordPress nonce-based  
**Format**: JSON  
**Version**: 1.0.0

**Total Endpoints**: 50+ across 14 controllers

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
**Endpoint**: `/ingest`

### **POST /tracksure/v1/ingest**

Receive events from browser SDK.

**Request**:

```json
POST /wp-json/tracksure/v1/ingest
{
  "client_id": "550e8400-e29b-41d4-a716-446655440000",
  "events": [
    {
      "event_name": "page_view",
      "params": {
        "page_title": "Homepage",
        "page_location": "https://example.com/"
      }
    }
  ]
}
```

**Response** (200 OK):

```json
{
  "success": true,
  "events_recorded": 1,
  "session_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
}
```

**Error** (400 Bad Request):

```json
{
  "code": "invalid_event",
  "message": "Event validation failed",
  "data": {
    "status": 400,
    "errors": ["Missing required parameter: page_location"]
  }
}
```

---

## 📊 **Events API**

**Controller**: `TrackSure_REST_Events_Controller`  
**Endpoints**: CRUD for events

### **GET /tracksure/v1/events**

List events with pagination.

**Parameters**:

- `page` (int) - Page number (default: 1)
- `per_page` (int) - Items per page (default: 20, max: 100)
- `event_name` (string) - Filter by event name
- `start_date` (string) - Start date (Y-m-d format)
- `end_date` (string) - End date (Y-m-d format)
- `session_id` (string) - Filter by session
- `visitor_id` (int) - Filter by visitor

**Request**:

```
GET /wp-json/tracksure/v1/events?page=1&per_page=20&event_name=purchase
```

**Response**:

```json
{
  "events": [
    {
      "id": 12345,
      "event_id": "evt_abc123",
      "event_name": "purchase",
      "event_time": "2026-01-17T10:30:00",
      "session_id": "sess_xyz789",
      "params": {
        "transaction_id": "ORD-001",
        "value": 99.99,
        "currency": "USD"
      }
    }
  ],
  "total": 150,
  "total_pages": 8,
  "current_page": 1
}
```

### **GET /tracksure/v1/events/{event_id}**

Get single event.

**Response**:

```json
{
  "id": 12345,
  "event_id": "evt_abc123",
  "event_name": "purchase",
  "event_data": {...},
  "session_id": "sess_xyz789",
  "visitor_id": 100,
  "event_time": "2026-01-17T10:30:00"
}
```

### **DELETE /tracksure/v1/events/{event_id}**

Delete event (admin only).

**Response**:

```json
{
  "success": true,
  "message": "Event deleted"
}
```

---

## 📈 **Query API**

**Controller**: `TrackSure_REST_Query_Controller`  
**Endpoint**: `/query`

### **POST /tracksure/v1/query**

Advanced analytics queries.

**Request**:

```json
POST /wp-json/tracksure/v1/query
{
  "metrics": ["visitors", "sessions", "events", "revenue"],
  "dimensions": ["date", "source", "device"],
  "start_date": "2026-01-01",
  "end_date": "2026-01-31",
  "filters": [
    {
      "dimension": "event_name",
      "operator": "equals",
      "value": "purchase"
    }
  ],
  "order_by": [
    {"metric": "revenue", "direction": "desc"}
  ],
  "limit": 100
}
```

**Response**:

```json
{
  "data": [
    {
      "date": "2026-01-17",
      "source": "google",
      "device": "desktop",
      "visitors": 150,
      "sessions": 200,
      "events": 500,
      "revenue": 1250.5
    }
  ],
  "totals": {
    "visitors": 4500,
    "sessions": 6000,
    "events": 15000,
    "revenue": 37500.0
  },
  "rows": 31
}
```

**Available Metrics**:

- `visitors` - Unique visitors
- `sessions` - Total sessions
- `events` - Total events
- `page_views` - Page view count
- `revenue` - Total transaction value
- `conversions` - Goal conversions
- `avg_session_duration` - Average session time
- `bounce_rate` - Bounce percentage

**Available Dimensions**:

- `date` - Day grouping
- `hour` - Hour grouping
- `source` - Traffic source
- `medium` - Traffic medium
- `campaign` - Campaign name
- `device` - Device type
- `country` - Country code
- `event_name` - Event type
- `page_path` - Page URL

**Filter Operators**:

- `equals` - Exact match
- `not_equals` - Not equal
- `contains` - String contains
- `starts_with` - String starts
- `ends_with` - String ends
- `greater_than` - Numeric >
- `less_than` - Numeric <
- `in` - In array
- `not_in` - Not in array

---

## 🎯 **Goals API**

**Controller**: `TrackSure_REST_Goals_Controller`  
**Endpoints**: CRUD for goals

### **GET /tracksure/v1/goals**

List all goals.

**Response**:

```json
{
  "goals": [
    {
      "id": 1,
      "name": "Purchase Completion",
      "description": "User completes a purchase",
      "goal_type": "event",
      "conditions": {
        "event_name": "purchase"
      },
      "value": 25.0,
      "active": true
    }
  ],
  "total": 5
}
```

### **POST /tracksure/v1/goals**

Create new goal.

**Request**:

```json
{
  "name": "Newsletter Signup",
  "description": "User subscribes to newsletter",
  "goal_type": "event",
  "conditions": {
    "event_name": "subscribe",
    "params": {
      "subscription_type": "newsletter"
    }
  },
  "value": 5.0,
  "active": true
}
```

### **PUT /tracksure/v1/goals/{goal_id}**

Update goal.

### **DELETE /tracksure/v1/goals/{goal_id}**

Delete goal.

---

## ⚙️ **Settings API**

**Controller**: `TrackSure_REST_Settings_Controller`

### **GET /tracksure/v1/settings**

Get all settings.

**Response**:

```json
{
  "general": {
    "tracking_enabled": true,
    "session_timeout": 30,
    "data_retention_days": 90
  },
  "consent": {
    "mode": "anonymize",
    "cookie_name": "tracksure_consent"
  },
  "destinations": {
    "ga4": {
      "enabled": true,
      "measurement_id": "G-XXXXXXXXX",
      "api_secret": "***"
    },
    "meta": {
      "enabled": true,
      "pixel_id": "1234567890",
      "access_token": "***"
    }
  }
}
```

### **POST /tracksure/v1/settings**

Update settings (admin only).

**Request**:

```json
{
  "general": {
    "session_timeout": 45
  }
}
```

---

## 🔍 **Diagnostics API**

**Controller**: `TrackSure_REST_Diagnostics_Controller`

### **GET /tracksure/v1/diagnostics/system**

System health check.

**Response**:

```json
{
  "status": "healthy",
  "checks": {
    "database": {
      "status": "ok",
      "tables": 14,
      "message": "All tables exist"
    },
    "wp_cron": {
      "status": "ok",
      "message": "Scheduled jobs running"
    },
    "destinations": {
      "status": "ok",
      "configured": ["ga4", "meta"]
    },
    "registry": {
      "status": "ok",
      "events": 25,
      "params": 100
    }
  }
}
```

### **GET /tracksure/v1/diagnostics/events**

Recent events status.

**Response**:

```json
{
  "last_24h": 1500,
  "last_hour": 62,
  "recent_events": [...]
}
```

---

## 🍪 **Consent API**

**Controller**: `TrackSure_REST_Consent_Controller`

### **GET /tracksure/v1/consent/status**

Get current consent status.

**Response**:

```json
{
  "tracking_allowed": true,
  "consent_given": true,
  "consent_date": "2026-01-17T10:00:00"
}
```

### **POST /tracksure/v1/consent**

Update consent preferences.

**Request**:

```json
{
  "tracking_allowed": true,
  "analytics": true,
  "marketing": false
}
```

---

## 📋 **Registry API**

**Controller**: `TrackSure_REST_Registry_Controller`

### **GET /tracksure/v1/registry/events**

Get event registry.

**Response**:

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
  ],
  "total": 25
}
```

### **GET /tracksure/v1/registry/params**

Get parameter registry.

---

## 🛍️ **Products API**

**Controller**: `TrackSure_REST_Products_Controller`

### **GET /tracksure/v1/products/top**

Top performing products.

**Parameters**:

- `start_date` - Start date
- `end_date` - End date
- `metric` - Sort by (revenue, views, purchases)
- `limit` - Results limit (default: 10)

**Response**:

```json
{
  "products": [
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
}
```

---

## ✅ **Quality API**

**Controller**: `TrackSure_REST_Quality_Controller`

### **GET /tracksure/v1/quality/score**

Data quality score.

**Response**:

```json
{
  "overall_score": 92,
  "checks": {
    "event_completeness": 95,
    "session_tracking": 98,
    "destination_delivery": 85,
    "consent_compliance": 100
  },
  "issues": [
    {
      "severity": "warning",
      "message": "15% of events missing UTM parameters"
    }
  ]
}
```

---

## 💡 **Suggestions API**

**Controller**: `TrackSure_REST_Suggestions_Controller`

### **GET /tracksure/v1/suggestions**

AI-powered optimization suggestions.

**Response**:

```json
{
  "suggestions": [
    {
      "type": "goal",
      "priority": "high",
      "title": "Create goal for cart abandonment",
      "description": "50% of users add to cart but don't complete purchase",
      "action": {
        "type": "create_goal",
        "config": {...}
      }
    }
  ]
}
```

---

## 📞 **Pixel Callbacks**

**Controller**: `TrackSure_REST_Pixel_Callback_Controller`

### **GET /tracksure/v1/pixel/ga4**

GA4 Measurement Protocol callback.

### **GET /tracksure/v1/pixel/meta**

Meta CAPI callback.

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

**Last Updated**: January 17, 2026  
**API Version**: 1.0.0  
**Total Endpoints**: 50+
