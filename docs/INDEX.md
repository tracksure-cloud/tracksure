# TrackSure Developer Documentation

> **Plugin Version**: Free (Pro-extensible architecture)  
> **REST Namespace**: `ts/v1`  
> **Admin UI**: React 18 SPA with Webpack code-splitting  
> **PHP Minimum**: 7.4 | **WordPress Minimum**: 6.0

---

## Quick Start — Reading Paths

### New / Junior Developer

1. [JUNIOR_DEVELOPER_GUIDE.md](JUNIOR_DEVELOPER_GUIDE.md) — Start here. Overview, key concepts, guided tasks
2. [CONCEPTS_EXPLAINED.md](CONCEPTS_EXPLAINED.md) — Attribution models, sessions, consent, event pipeline
3. [CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md) — Full directory tree, layer-by-layer walkthrough
4. [CODE_WALKTHROUGH.md](CODE_WALKTHROUGH.md) — Step-by-step flow of a real purchase event through the system
5. [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) — All 15 tables, column types, indexes, relationships

### Extending the Plugin (Pro / 3rd-Party)

1. [MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md) — How to build a self-contained integration module
2. [DESTINATION_DEVELOPMENT.md](DESTINATION_DEVELOPMENT.md) — Add a new analytics destination (e.g., TikTok, Bing)
3. [ADAPTER_DEVELOPMENT.md](ADAPTER_DEVELOPMENT.md) — Write a platform adapter + register an integration
4. [CUSTOM_EVENTS.md](CUSTOM_EVENTS.md) — Define, register, validate, and track custom events
5. [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md) — 30+ actions, 49+ filters — every hook documented
6. [REST_API_REFERENCE.md](REST_API_REFERENCE.md) — All endpoints across 12 controllers
7. [PLUGIN_API.md](PLUGIN_API.md) — PHP API, Consent API, Browser SDK API for third-party integration

### Frontend / JavaScript

1. [FRONTEND_SDK.md](FRONTEND_SDK.md) — `ts-web.js` SDK: tracking API, events, consent, client/session IDs
2. [REACT-CONSENT-API-INTEGRATION.md](REACT-CONSENT-API-INTEGRATION.md) — React admin consent UI + REST API integration

### Reference

1. [CLASS_REFERENCE.md](CLASS_REFERENCE.md) — All 22 services, 12 REST controllers, background jobs, class hierarchy
2. [EVENT_SYSTEM.md](EVENT_SYSTEM.md) — Event pipeline: builder, recorder, queue, mapper, bridge, registry, dedup
3. [TRACKSURE-DEDUPLICATION-EXPLANATION.md](TRACKSURE-DEDUPLICATION-EXPLANATION.md) — How event deduplication works

### Debugging

1. [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md) — Debug mode, logging, network inspection, common fixes

### Deployment

1. [deployment/DEPLOYMENT_GUIDE.md](deployment/DEPLOYMENT_GUIDE.md) — Build, package, and release workflow
2. [deployment/GITHUB_SETUP.md](deployment/GITHUB_SETUP.md) — CI/CD GitHub Actions setup
3. [deployment/VERIFICATION_CHECKLIST.md](deployment/VERIFICATION_CHECKLIST.md) — Pre-release QA checklist

---

## Architecture at a Glance

```
┌─────────────────────────────────────────────────────┐
│  Browser (ts-web.js)                                │
│  Events → POST /wp-json/ts/v1/collect               │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  REST API Layer (12 controllers)                    │
│  Ingest → Events → Sessions → Settings → Goals ...  │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  Service Layer (22 services)                        │
│  Event Recorder → Session Manager → Attribution     │
│  → Consent → Goals → Aggregation → Logger           │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  Database (15 custom tables via TrackSure_DB)       │
│  visitors, sessions, events, conversions, goals,    │
│  touchpoints, click_ids, outbox, aggregations ...   │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  Background Jobs (4 cron workers)                   │
│  Outbox Processor → Aggregator → Cleanup → Health   │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  Destinations (Meta CAPI, GA4, extensible)          │
│  Server-side delivery via outbox queue              │
└─────────────────────────────────────────────────────┘
```

---

## Key Technical Details

| Item                     | Value                                                     |
| ------------------------ | --------------------------------------------------------- |
| REST namespace           | `ts/v1`                                                   |
| Primary collect endpoint | `POST /wp-json/ts/v1/collect`                             |
| Client ID cookie         | `_ts_cid` (localStorage + cookie, 400-day)                |
| Session ID cookie        | `_ts_sid` (sessionStorage + cookie)                       |
| Session timing keys      | `_ts_ss` (start), `_ts_la` (last activity)                |
| Consent cookie           | `_ts_consent`                                             |
| Frontend script handle   | `ts-web`                                                  |
| Frontend JS file         | `assets/js/ts-web.js`                                     |
| Global config object     | `trackSureConfig` (via `wp_localize_script`)              |
| Hook prefix              | `tracksure_` (actions & filters)                          |
| Admin React app          | `admin/dist/` (7 Webpack chunks)                          |
| Settings storage         | Individual `tracksure_*` wp_options (via Settings Schema) |

---

## All Documentation Files

| File                                                                             | Description                                                          |
| -------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| [ADAPTER_DEVELOPMENT.md](ADAPTER_DEVELOPMENT.md)                                 | Building platform adapters + integration registration                |
| [CLASS_REFERENCE.md](CLASS_REFERENCE.md)                                         | Complete class-by-class reference: 22 services, 12 controllers, jobs |
| [CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md)                                     | Full directory tree with inline annotations for every file           |
| [CODE_WALKTHROUGH.md](CODE_WALKTHROUGH.md)                                       | End-to-end trace of a purchase event through all layers              |
| [CONCEPTS_EXPLAINED.md](CONCEPTS_EXPLAINED.md)                                   | Core concepts: attribution, sessions, consent, event pipeline        |
| [CUSTOM_EVENTS.md](CUSTOM_EVENTS.md)                                             | Define, register, validate, and track custom events (PHP + JS)       |
| [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)                                         | All 15 tables — columns, types, indexes, relationships               |
| [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md)                                         | Debug mode, WP_DEBUG logging, network inspection, curl tests         |
| [DESTINATION_DEVELOPMENT.md](DESTINATION_DEVELOPMENT.md)                         | Creating new analytics destinations (Meta CAPI, GA4 pattern)         |
| [EVENT_SYSTEM.md](EVENT_SYSTEM.md)                                               | Event pipeline: builder, recorder, queue, mapper, bridge, registry   |
| [FRONTEND_SDK.md](FRONTEND_SDK.md)                                               | JavaScript tracking SDK — events, consent, client/session IDs        |
| [HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md)                                     | Every action and filter hook with examples and parameters            |
| [JUNIOR_DEVELOPER_GUIDE.md](JUNIOR_DEVELOPER_GUIDE.md)                           | Onboarding guide with guided tasks and mental models                 |
| [MODULE_DEVELOPMENT.md](MODULE_DEVELOPMENT.md)                                   | Self-contained module architecture and registration                  |
| [PLUGIN_API.md](PLUGIN_API.md)                                                   | PHP API, Consent API, Browser SDK API for third-party devs           |
| [REACT-CONSENT-API-INTEGRATION.md](REACT-CONSENT-API-INTEGRATION.md)             | React admin consent settings + REST API integration                  |
| [REST_API_REFERENCE.md](REST_API_REFERENCE.md)                                   | All REST endpoints — routes, methods, parameters, responses          |
| [TRACKSURE-DEDUPLICATION-EXPLANATION.md](TRACKSURE-DEDUPLICATION-EXPLANATION.md) | Event deduplication logic and edge cases                             |
| [deployment/DEPLOYMENT_GUIDE.md](deployment/DEPLOYMENT_GUIDE.md)                 | Build, package, and release process                                  |
| [deployment/GITHUB_SETUP.md](deployment/GITHUB_SETUP.md)                         | GitHub Actions CI/CD configuration                                   |
| [deployment/VERIFICATION_CHECKLIST.md](deployment/VERIFICATION_CHECKLIST.md)     | Pre-release quality assurance checklist                              |
