# TrackSure Cloud — First-Party and Server-Side Analytics & Conversion API for WordPress

> **Privacy-friendly, server-side tracking and analytics platform for WordPress.** Track visits, WooCommerce sales, funnels, and attribution — with or without ads.

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/tracksure-cloud/tracksure)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)

---

## What is TrackSure?

TrackSure is a production-grade, server-side tracking and analytics plugin built specifically for WordPress. It combines **first-party analytics** and **Conversion API (CAPI)** into a single dashboard:

- **First-Party Tracking** — Own your data, improve ad-blocker resilience, GDPR/CCPA ready
- **Server-Side Event Delivery** — Send events directly to ad platforms via Conversion APIs
- **Event Deduplication** — Smart browser + server tracking prevents double-counting
- **Ad Platform Integration** — Meta CAPI, Google Analytics 4 Measurement Protocol (Free); TikTok, Twitter, LinkedIn, Pinterest (Pro)
- **Attribution Analytics** — Multi-touch attribution to understand which channels drive conversions
- **E-commerce Tracking** — Deep WooCommerce, FluentCart, Easy Digital Downloads, SureCart integration
- **Real-time Dashboard** — Beautiful React 18 SPA admin interface with dark mode

---

## Key Features

### Complete Analytics

- Page views, sessions, visitors, bounce rate
- Traffic sources (UTMs, referrers, social, search, AI chatbots)
- Device, browser, OS, country analytics
- Visitor segmentation (all / new / returning / converted)
- Content performance analytics (pages and posts)
- Anomaly detection alerts
- Time intelligence analysis

### E-commerce Tracking

- Automatic event tracking: `view_item`, `add_to_cart`, `begin_checkout`, `purchase`
- Product performance analytics
- Revenue attribution by source/medium
- Funnel analysis and checkout flow visualization
- Cross-device journey mapping

### Goal Tracking

- Pre-built goal templates
- Custom goal builder with condition logic
- Bulk operations, filters, and import
- Goal completion tracking and stats

### Visitor Journeys

- Full visitor journey explorer (first touch → conversion)
- Touchpoint recording and visualization
- Journey drawer and modal views

### Ad Platform Integration (Destinations)

**Free:**

- **Meta Conversions API** — Server-side event delivery with Event Match Quality tracking
- **Google Analytics 4** — Measurement Protocol for server-side e-commerce and event tracking

**Pro (separate plugin):**

- **TikTok Events API** — Server-side conversion tracking
- **Twitter Conversions API** — Enhanced attribution
- **LinkedIn Conversions API** — B2B tracking
- **Pinterest API** — Visual platform tracking

### Privacy & Compliance

- Built-in consent manager integration (Cookiebot, CookieYes, OneTrust auto-detection)
- Consent mode visualizer
- GDPR/CCPA ready with configurable consent enforcement
- IP anonymization
- Cookieless tracking option (localStorage)
- Data retention policies (configurable)
- Data ownership — all data stays in your WordPress database

### Performance

- Lightweight frontend tracking script (`ts-web.js`)
- Batched event delivery via background jobs
- Action Scheduler-based cron processing (hourly/daily aggregation, delivery worker, cleanup)
- Indexed database queries
- Webpack code splitting (6 chunks) for fast admin loading

---

## System Requirements

### Minimum

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+

### Recommended

- WordPress 6.4+
- PHP 8.2+
- MySQL 8.0+ or MariaDB 10.11+
- 512 MB PHP memory limit
- HTTPS enabled

### Development

- Node.js 18+
- NPM 9+

---

## Installation

### From WordPress.org

1. WordPress Admin → Plugins → Add New
2. Search for "TrackSure Cloud"
3. Install and activate

### From Release ZIP

1. Download latest release from [Releases](https://github.com/tracksure-cloud/tracksure/releases)
2. WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose `tracksure.zip` and activate

### From Source (Development)

```bash
# Clone repository
git clone https://github.com/tracksure-cloud/tracksure.git
cd tracksure

# Install root validation tools
npm install

# Install and build admin UI
cd admin
npm install
npm run build
cd ..
```

Copy the `tracksure` folder to `wp-content/plugins/` and activate.

---

## Quick Start

### 1. Activate & Configure

After activation, you'll be redirected to **TrackSure → Settings**. Enable tracking and configure your preferences.

### 2. Automatic Tracking

TrackSure immediately begins tracking:

- Page views and sessions
- Visitor information (device, browser, OS, country)
- Traffic sources (UTMs, referrers)
- New vs. returning visitors

### 3. E-commerce Setup

If WooCommerce, FluentCart, EDD, or SureCart is active, TrackSure auto-detects it and tracks:

- Product views (`view_item`)
- Add to cart (`add_to_cart`)
- Checkout initiated (`begin_checkout`)
- Purchases (`purchase`)

Currency is **auto-detected** from your e-commerce platform settings.

### 4. Destination Setup (e.g., Meta CAPI)

1. Go to **Destinations** → Enable **Meta Conversions API**
2. Enter your Pixel ID and Access Token from Facebook Events Manager
3. Test the connection
4. Save settings

---

## Architecture

### Plugin Bootstrap

```
tracksure.php (Main plugin file)
  → TrackSure class (Singleton)
    → Loads TrackSure_Core      (Core engine — includes/core/)
    → Loads TrackSure_Free      (Free module — includes/free/)
    → Fires 'tracksure_loaded'  (Pro/3rd-party extensions hook in here)
```

### Core Engine (`includes/core/`)

The core provides the service container, module registry, and all shared infrastructure:

| Directory       | Purpose                                                                                                                                                   |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `services/`     | Session manager, attribution resolver, event queue, consent manager, geolocation, goal evaluator, journey engine, suggestion engine, rate limiter, logger |
| `api/`          | REST API controllers (14 endpoints under `ts/v1` namespace)                                                                                               |
| `tracking/`     | Frontend asset enqueue, checkout tracking                                                                                                                 |
| `destinations/` | Destinations Manager (single source of truth for ad platforms)                                                                                            |
| `integrations/` | Integrations Manager (single source of truth for e-commerce/form plugins)                                                                                 |
| `modules/`      | Module registry and interface                                                                                                                             |
| `jobs/`         | Background workers: delivery, hourly/daily aggregation, cleanup                                                                                           |
| `admin/`        | Admin UI class, admin extensions registry                                                                                                                 |
| `registry/`     | Event/parameter schema cache and loader                                                                                                                   |
| `abstractions/` | Data normalizer, e-commerce adapter interface                                                                                                             |
| `utils/`        | Location formatter, country data, utilities                                                                                                               |

### Free Module (`includes/free/`)

Registers the free destinations, integrations, and admin extensions:

| Directory       | Purpose                                                                                     |
| --------------- | ------------------------------------------------------------------------------------------- |
| `destinations/` | Meta CAPI destination, GA4 destination, GA4 setup guide                                     |
| `integrations/` | WooCommerce integration, FluentCart integration                                             |
| `adapters/`     | WooCommerce adapter, FluentCart adapter (implement `interface-tracksure-ecommerce-adapter`) |
| `admin/`        | Free admin extensions registration, API filters                                             |

### Admin UI (`admin/`)

React 18 SPA with TypeScript. See [admin/README.md](admin/README.md) for full details.

### Frontend Tracking (`assets/js/`)

| File                   | Purpose                        |
| ---------------------- | ------------------------------ |
| `ts-web.js`            | Main browser tracking SDK      |
| `ts-currency.js`       | Currency detection helper      |
| `ts-minicart.js`       | Mini-cart event tracking       |
| `consent-listeners.js` | Consent change event listeners |

### Event & Parameter Registry (`registry/`)

| File          | Purpose                            |
| ------------- | ---------------------------------- |
| `events.json` | All trackable events with metadata |
| `params.json` | Event parameter definitions        |

---

## REST API

All endpoints are under the `ts/v1` namespace:

| Controller     | Endpoints                                                  |
| -------------- | ---------------------------------------------------------- |
| Ingest         | Event ingestion from browser SDK                           |
| Query          | Analytics data queries (overview, sessions, sources, etc.) |
| Events         | Event listing and details                                  |
| Settings       | Get/update plugin settings                                 |
| Goals          | CRUD operations for goals                                  |
| Products       | E-commerce product analytics                               |
| Quality        | Data quality metrics                                       |
| Diagnostics    | System health checks                                       |
| Suggestions    | AI-powered optimization suggestions                        |
| Registry       | Event/parameter schema lookups                             |
| Consent        | Consent status and management                              |
| Pixel Callback | Server-side pixel callbacks                                |

Full API documentation: [docs/REST_API_REFERENCE.md](docs/REST_API_REFERENCE.md)

---

## Project Structure

```
tracksure/
├── tracksure.php                           # Main plugin file (bootstrap + activation/deactivation)
├── uninstall.php                           # Clean uninstall (removes all data unless opted out)
├── readme.txt                              # WordPress.org readme
├── README.md                               # This file
├── CONTRIBUTING.md                         # Contribution guidelines
├── composer.json                           # PHP dependencies (PHPCS, WPCS)
├── package.json                            # Validation scripts, build orchestration
├── phpcs.xml                               # PHP CodeSniffer configuration
├── .eslintrc.js                            # ESLint configuration
├── .stylelintrc.json                       # Stylelint configuration
├── admin/                                  # React 18 admin SPA
│   ├── src/                               # TypeScript source
│   │   ├── index.tsx                      # Entry point
│   │   ├── App.tsx                        # Main app with routing
│   │   ├── types/                         # TypeScript type definitions
│   │   ├── contexts/                      # React contexts (6 split contexts)
│   │   ├── providers/                     # Combined provider hierarchy
│   │   ├── config/                        # Registries (destinations, icons)
│   │   ├── hooks/                         # Custom React hooks (10 hooks)
│   │   ├── utils/                         # Utility functions (13 modules)
│   │   ├── components/                    # UI components (layout, ui, charts, settings, goals)
│   │   ├── pages/                         # Page components (16 pages)
│   │   ├── styles/                        # CSS variables and global styles
│   │   └── data/                          # Static data (goal templates)
│   ├── dist/                              # Built assets (gitignored)
│   ├── package.json                       # Admin dependencies
│   ├── tsconfig.json                      # TypeScript config
│   └── webpack.config.js                  # Webpack build config
├── assets/
│   └── js/
│       ├── ts-web.js                      # Browser tracking SDK
│       ├── ts-currency.js                 # Currency detection
│       ├── ts-minicart.js                 # Mini-cart tracking
│       └── consent-listeners.js           # Consent event listeners
├── includes/
│   ├── core/                              # Core engine
│   │   ├── class-tracksure-core.php       # Main service container
│   │   ├── class-tracksure-db.php         # Database schema and queries
│   │   ├── class-tracksure-installer.php  # Table creation and setup
│   │   ├── class-tracksure-hooks.php      # WordPress hook registration
│   │   ├── class-tracksure-event-bridge.php # Browser ↔ server event bridge
│   │   ├── class-tracksure-settings-schema.php # Settings field definitions
│   │   ├── class-tracksure-currency-config.php # Currency config
│   │   ├── class-tracksure-currency-handler.php # Currency handling
│   │   ├── services/                      # 22 service classes
│   │   ├── api/                           # 14 REST API controllers + consent API
│   │   ├── tracking/                      # Tracker assets + checkout tracking
│   │   ├── destinations/                  # Destinations Manager
│   │   ├── integrations/                  # Integrations Manager
│   │   ├── modules/                       # Module registry + interface
│   │   ├── jobs/                          # 4 background workers
│   │   ├── admin/                         # Admin UI + extensions
│   │   ├── registry/                      # Schema cache + loader
│   │   ├── abstractions/                  # Data normalizer + e-commerce adapter interface
│   │   └── utils/                         # Location formatter, utilities, countries
│   └── free/                              # Free module
│       ├── class-tracksure-free.php       # Free module bootstrap
│       ├── destinations/                  # Meta CAPI, GA4 destinations
│       ├── integrations/                  # WooCommerce, FluentCart integrations
│       ├── adapters/                      # E-commerce platform adapters
│       └── admin/                         # Free admin extensions
├── registry/
│   ├── events.json                        # Event schema definitions
│   └── params.json                        # Parameter schema definitions
├── languages/
│   ├── tracksure.pot                      # Main translation template
│   └── tracksure-core.pot                 # Core translation template
├── docs/                                  # Documentation (15+ guides)
│   ├── REST_API_REFERENCE.md
│   ├── HOOKS_AND_FILTERS.md
│   ├── CODE_ARCHITECTURE.md
│   ├── DATABASE_SCHEMA.md
│   ├── FRONTEND_SDK.md
│   ├── DESTINATION_DEVELOPMENT.md
│   ├── MODULE_DEVELOPMENT.md
│   ├── ADAPTER_DEVELOPMENT.md
│   ├── DEBUGGING_GUIDE.md
│   └── ...
├── scripts/                               # Build and deployment scripts
│   ├── create-release-zip.js              # Release ZIP builder
│   ├── deploy-to-wordpress-org.js         # WordPress.org deployment
│   ├── bump-version.js                    # Version bumper
│   ├── check-versions.js                  # Version consistency checker
│   ├── validate-readme.js                 # readme.txt validator
│   └── ...                                # PHPCS auto-fix scripts
├── .github/
│   └── workflows/
│       ├── ci.yml                         # CI pipeline
│       └── deploy-to-wporg.yml            # WordPress.org deploy workflow
├── .wordpress-org/                        # WordPress.org SVN assets
└── vendor/                                # Composer dependencies (PHPCS)
```

---

## Development

### Build Admin UI

```bash
# Development (watch mode)
cd admin
npm run dev

# Production
cd admin
npm run build

# Type checking
cd admin
npm run type-check
```

### Code Quality

```bash
# From plugin root:

# PHP CodeSniffer (WordPress Coding Standards)
npm run phpcs

# Auto-fix PHP style
npm run phpcs:fix

# ESLint (TypeScript/React)
npm run eslint
npm run eslint:fix

# Stylelint (CSS)
npm run stylelint
npm run stylelint:fix

# Run all linters
npm run validate

# Fix all linters
npm run validate:fix

# Full PHPCS cleanup pipeline
npm run phpcs:perfect
```

### Build for Release

```bash
# Full production build (from plugin root)
npm run build:production

# Create release ZIP
npm run zip:plugin

# Validate readme.txt
npm run readme:validate

# Check version consistency across files
npm run version:check

# Deploy to WordPress.org
npm run deploy:wporg
```

### Version Management

```bash
# Bump version (updates tracksure.php, package.json, readme.txt)
npm run version:bump
```

### i18n

```bash
# Generate POT file
npm run i18n:pot
```

---

## Extension Architecture

TrackSure uses a modular **Core + Module** architecture:

1. **Core Engine** (`includes/core/`) — Provides all shared infrastructure: tracking, sessions, attribution, event queue, REST API, database, admin UI
2. **Free Module** (`includes/free/`) — Registers free destinations (Meta CAPI, GA4), integrations (WooCommerce, FluentCart), and admin settings
3. **Pro Module** (separate plugin) — Hooks into `tracksure_loaded` action to register pro destinations, integrations, and features

### Key Extension Hooks

| Hook                                  | Type   | Purpose                                  |
| ------------------------------------- | ------ | ---------------------------------------- |
| `tracksure_loaded`                    | Action | Pro/3rd-party register modules into Core |
| `tracksure_register_admin_extensions` | Action | Register admin UI settings groups        |
| `tracksure_admin_enqueue_scripts`     | Action | Enqueue additional admin scripts         |
| `tracksure_admin_currency`            | Filter | Override auto-detected currency          |

### Adding a New Destination

See [docs/DESTINATION_DEVELOPMENT.md](docs/DESTINATION_DEVELOPMENT.md).

### Adding a New Integration

See [docs/MODULE_DEVELOPMENT.md](docs/MODULE_DEVELOPMENT.md) and [docs/ADAPTER_DEVELOPMENT.md](docs/ADAPTER_DEVELOPMENT.md).

---

## Documentation

| Guide                                                      | Description                               |
| ---------------------------------------------------------- | ----------------------------------------- |
| [REST API Reference](docs/REST_API_REFERENCE.md)           | All REST endpoints, parameters, responses |
| [Hooks & Filters](docs/HOOKS_AND_FILTERS.md)               | WordPress actions and filters reference   |
| [Code Architecture](docs/CODE_ARCHITECTURE.md)             | System design and patterns                |
| [Database Schema](docs/DATABASE_SCHEMA.md)                 | Table structures and indexes              |
| [Frontend SDK](docs/FRONTEND_SDK.md)                       | Browser tracking JavaScript API           |
| [Destination Development](docs/DESTINATION_DEVELOPMENT.md) | Build custom ad platform destinations     |
| [Module Development](docs/MODULE_DEVELOPMENT.md)           | Create extension modules                  |
| [Adapter Development](docs/ADAPTER_DEVELOPMENT.md)         | Build e-commerce platform adapters        |
| [Debugging Guide](docs/DEBUGGING_GUIDE.md)                 | Troubleshooting and diagnostics           |
| [Admin UI](admin/README.md)                                | React admin interface architecture        |

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- **PHP**: WordPress Coding Standards via PHPCS + WPCS + VIP WPCS
- **TypeScript/React**: ESLint with `@typescript-eslint` + `eslint-plugin-react`
- **CSS**: Stylelint with `stylelint-config-standard`

---

## Bug Reports

Found a bug? Please [open an issue](https://github.com/tracksure-cloud/tracksure/issues) with:

- WordPress version
- PHP version
- TrackSure version
- Steps to reproduce
- Expected vs. actual behavior
- Error messages (if any)

---

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.

---

## Credits

**Built with:**

- [React 18](https://react.dev/) — UI framework
- [Recharts](https://recharts.org/) — Data visualization
- [Lucide React](https://lucide.dev/) — Icon system
- [React Router v6](https://reactrouter.com/) — Client-side routing
- [date-fns](https://date-fns.org/) — Date utilities
- [WordPress REST API](https://developer.wordpress.org/rest-api/) — Backend communication
- [@wordpress/i18n](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/) — Internationalization

---

## Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/tracksure-cloud/tracksure/issues)
- **WordPress.org Support**: [Support Forum](https://wordpress.org/support/plugin/tracksure)
