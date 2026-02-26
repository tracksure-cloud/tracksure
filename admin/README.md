# TrackSure Admin UI

React 18 SPA for the TrackSure analytics admin interface.

## Architecture Decision: Why No Redux?

**We chose React Context + Hooks over Redux for these reasons:**

1. **WordPress Ecosystem Alignment**: Gutenberg (WordPress block editor) uses the Context + Hooks pattern
2. **Simpler Extension Model**: Free/Pro modules can inject routes/components without Redux middleware complexity
3. **Smaller Bundle**: Context is built-in (0KB), Redux adds ~8KB + boilerplate
4. **Easier Maintenance**: Less abstraction, clearer data flow for small-medium apps
5. **Sufficient for Use Case**: We need global state for config/date-range/filters/theme, not complex async orchestration

## State Management Pattern

State is split across **six contexts** optimized to prevent unnecessary re-renders:

```typescript
// Split contexts handle different update frequencies:
ConfigContext; // Rarely changes: config object, viewMode (business/debug)
UIContext; // Changes often: isLoading state
DataContext; // Changes moderately: dateRange, filters, segment
ThemeContext; // UI: theme preference (light/dark/auto)
ExtensionRegistryContext; // Modules: Free/Pro register routes/nav/widgets
SettingsExtensionContext; // Settings: destinations, integrations, settings groups

// Legacy combined context (backward compatibility):
AppContext; // Combines Config + UI + Data for components using useApp()
```

## Tech Stack

| Category     | Technology          | Version         |
| ------------ | ------------------- | --------------- |
| UI Framework | React               | ^18.2.0         |
| Routing      | React Router DOM    | ^6.20.0         |
| Language     | TypeScript          | ^5.3.0          |
| Charts       | Recharts            | ^2.15.4         |
| Icons        | Lucide React        | ^0.562.0        |
| Dates        | date-fns            | ^3.0.0          |
| PDF Export   | jsPDF + html2canvas | ^2.5.2 / ^1.4.1 |
| CSS Utility  | clsx                | ^2.0.0          |
| Build        | Webpack             | ^5.89.0         |
| i18n         | @wordpress/i18n     | ^4.52.0         |

### Externals (Loaded from WordPress Core)

React, ReactDOM, and `wp.i18n` are **not bundled** — they are loaded as WordPress core dependencies via `wp_enqueue_script()`.

## Project Structure

```
admin/
├── src/
│   ├── index.tsx                          # Entry point (createRoot, DOMContentLoaded)
│   ├── App.tsx                            # Main app component with routing
│   ├── types/
│   │   ├── index.ts                       # Core TypeScript types (DateRange, TrackSureConfig, etc.)
│   │   ├── extensions.ts                  # Extension system types (DestinationConfig, IntegrationConfig)
│   │   ├── goals.ts                       # Goal tracking types
│   │   ├── global.d.ts                    # Global window type declarations
│   │   └── wordpress__i18n.d.ts           # WordPress i18n type shims
│   ├── contexts/
│   │   ├── AppContext.tsx                  # Combined global state (backward compat)
│   │   ├── ConfigContext.tsx              # Rarely-changing config (config, viewMode)
│   │   ├── UIContext.tsx                  # Frequently-changing UI state (isLoading)
│   │   ├── DataContext.tsx                # Moderately-changing data (dateRange, filters, segment)
│   │   ├── ThemeContext.tsx               # Theme management (light/dark/auto)
│   │   ├── ExtensionRegistryContext.tsx   # Route/nav/widget registration
│   │   └── SettingsExtensionContext.tsx   # Settings/destinations/integrations registry
│   ├── providers/
│   │   └── AppProviders.tsx               # Combined provider hierarchy
│   ├── config/
│   │   ├── destinationRegistry.ts         # Destination metadata registry
│   │   └── iconRegistry.ts               # Icon name → component mapping
│   ├── hooks/
│   │   ├── useApi.ts                      # REST API helper (fetch wrapper)
│   │   ├── useApiQuery.ts                # Cached API query hook
│   │   ├── useCache.ts                   # Client-side cache management
│   │   ├── useConsentStatus.ts           # Consent state detection
│   │   ├── useDebounce.ts                # Debounce hook
│   │   ├── useGA4SetupGuide.ts           # GA4 onboarding flow
│   │   ├── useMediaQuery.ts              # Responsive breakpoint hook
│   │   ├── useAccessibility.ts           # a11y helpers (focus trap, ARIA)
│   │   ├── useToast.tsx                  # Toast notification system
│   │   └── useVirtualized.ts             # Virtualized list rendering
│   ├── utils/
│   │   ├── api.ts                         # API client (base URL, nonce, error handling)
│   │   ├── cacheConfig.ts               # Cache TTL and invalidation config
│   │   ├── channelHelpers.ts            # Traffic channel classification
│   │   ├── chartTheme.ts                # Recharts theme tokens
│   │   ├── countries.ts                  # Country code → name mapping
│   │   ├── errorHandler.ts              # Global error handling utilities
│   │   ├── eventDisplayHelpers.ts       # Event name formatting
│   │   ├── extensionIconApi.ts          # Extension icon resolution
│   │   ├── goalValidation.ts            # Goal condition validation
│   │   ├── i18n.ts                       # WordPress i18n wrapper (__(), _n())
│   │   ├── locationFormatters.ts        # City/region/country formatting
│   │   ├── parameterFormatters.ts       # Event parameter display formatting
│   │   └── timezoneHelpers.ts           # Timezone conversion utilities
│   ├── data/
│   │   └── goalTemplates.ts              # Pre-built goal templates
│   ├── examples/
│   │   └── TimezoneHelperUsageExample.tsx # Timezone utility usage demo
│   ├── components/
│   │   ├── layout/
│   │   │   ├── AppShell.tsx               # Main layout wrapper
│   │   │   ├── TopBar.tsx                 # Header bar
│   │   │   └── Sidebar.tsx                # Navigation sidebar
│   │   ├── ui/
│   │   │   ├── Badge.tsx                  # Status badge
│   │   │   ├── Button.tsx                 # Button component
│   │   │   ├── Card.tsx                   # Card container
│   │   │   ├── Checkbox.tsx               # Checkbox input
│   │   │   ├── DataTable.tsx              # Sortable data table
│   │   │   ├── DateRangePicker.tsx        # Date range selector
│   │   │   ├── DynamicField.tsx           # Schema-driven form field
│   │   │   ├── EmptyState.tsx             # Empty state placeholder
│   │   │   ├── ErrorBoundary.tsx          # React error boundary
│   │   │   ├── Icon.tsx                   # Dynamic icon component
│   │   │   ├── iconRegistry.ts            # Icon name → Lucide mapping
│   │   │   ├── Input.tsx                  # Text input
│   │   │   ├── JourneyDrawer.tsx          # Visitor journey side drawer
│   │   │   ├── KPICard.tsx                # Metric KPI card
│   │   │   ├── MetricToggle.tsx           # Metric selection toggle
│   │   │   ├── Modal.tsx                  # Modal dialog
│   │   │   ├── QualityBadge.tsx           # Data quality indicator
│   │   │   ├── SegmentFilter.tsx          # Visitor segment filter (all/new/returning/converted)
│   │   │   ├── Select.tsx                 # Select dropdown
│   │   │   ├── Skeleton.tsx               # Loading skeleton
│   │   │   ├── ThemeToggle.tsx            # Light/dark/auto toggle
│   │   │   ├── TrackingStatusBanner.tsx   # Tracking status indicator
│   │   │   ├── ViewModeToggle.tsx         # Business/debug view toggle
│   │   │   └── index.ts                   # Barrel export
│   │   ├── charts/
│   │   │   ├── BarChart.tsx               # Bar chart component
│   │   │   ├── ChartContainer.tsx         # Chart wrapper with loading
│   │   │   ├── DonutChart.tsx             # Donut/pie chart
│   │   │   ├── LineChart.tsx              # Line/area chart
│   │   │   ├── withChartWrapper.tsx       # Chart HOC for shared logic
│   │   │   └── index.ts                   # Barrel export
│   │   ├── settings/
│   │   │   ├── ConsentModeVisualizer.tsx  # Consent mode visual diagram
│   │   │   ├── ConsentPluginDetector.tsx  # Auto-detect consent plugins
│   │   │   ├── ConsentSettings.tsx        # Consent configuration panel
│   │   │   ├── ConsentWarningBanner.tsx   # Consent misconfiguration warning
│   │   │   └── index.ts                   # Barrel export
│   │   ├── goals/
│   │   │   ├── components/
│   │   │   │   ├── ConditionBuilder.tsx   # Goal condition builder
│   │   │   │   ├── CustomGoalBuilder.tsx  # Custom goal creation form
│   │   │   │   ├── GoalDetailsModal.tsx   # Goal details/stats modal
│   │   │   │   └── GoalModal.tsx          # Goal create/edit modal
│   │   │   ├── features/
│   │   │   │   ├── bulk-actions/          # Bulk goal operations
│   │   │   │   ├── filters/              # Goal list filters
│   │   │   │   └── import/               # Goal import functionality
│   │   │   ├── views/
│   │   │   │   └── GoalsOverview.tsx      # Goals list view
│   │   │   └── index.ts                   # Barrel export
│   │   ├── AnomalyAlert.tsx               # Traffic anomaly detection alert
│   │   ├── AttributionModelSelector.tsx   # Attribution model picker
│   │   ├── ExportButton.tsx               # PDF/CSV export button
│   │   ├── GA4SetupGuideModal.tsx         # GA4 onboarding wizard
│   │   ├── JourneyModal.tsx               # Full journey view modal
│   │   ├── SuggestionsWidget.tsx          # AI-powered suggestions widget
│   │   ├── TimeIntelligencePanel.tsx      # Time-based analytics panel
│   │   └── index.ts                       # Top-level barrel export
│   ├── pages/
│   │   ├── OverviewPage.tsx               # Dashboard overview with KPIs
│   │   ├── RealtimePage.tsx               # Real-time visitor tracking
│   │   ├── SessionsPage.tsx               # Session analytics
│   │   ├── TrafficSourcesPage.tsx         # Traffic source breakdown
│   │   ├── ContentAnalytics.tsx           # Page/post performance
│   │   ├── AttributionPage.tsx            # Multi-touch attribution
│   │   ├── ConversionsPage.tsx            # Conversion tracking
│   │   ├── JourneysPage.tsx               # Visitor journey explorer
│   │   ├── ProductsPage.tsx               # E-commerce product analytics
│   │   ├── GoalsPage.tsx                  # Goals management
│   │   ├── DestinationsPage.tsx           # Ad platform destinations config
│   │   ├── IntegrationsPage.tsx           # E-commerce/form integrations config
│   │   ├── SettingsPage.tsx               # General settings
│   │   ├── DiagnosticsPage.tsx            # System diagnostics/health
│   │   ├── DataQualityPage.tsx            # Data quality monitoring
│   │   └── NotFoundPage.tsx               # 404 page
│   └── styles/
│       ├── variables.css                  # CSS custom properties (design tokens)
│       ├── global.css                     # Global styles and resets
│       ├── RESPONSIVE_DESIGN_SPEC.css     # Responsive breakpoint documentation
│       ├── components/                    # Component-scoped styles
│       └── pages/                         # Page-scoped styles
├── tracking-goals.js                      # Frontend goal tracking script
├── tracking-monitor.js                    # Frontend tracking monitor
├── tracksure-goal-constants.js            # Goal event constants
├── dist/                                  # Built assets (gitignored)
│   ├── tracksure-admin.js                 # Main app bundle
│   ├── runtime.js                         # Webpack runtime
│   ├── vendors.js                         # Core vendor libs (date-fns, clsx, etc.)
│   ├── react-router.js                    # React Router chunk
│   ├── recharts.js                        # Recharts chunk (lazy loaded)
│   ├── lucide.js                          # Lucide icons chunk
│   └── common.js                          # Shared code across routes
├── package.json
├── tsconfig.json
├── webpack.config.js
└── README.md                              # This file
```

## Build Instructions

### 1. Install Dependencies

```bash
cd admin
npm install
```

### 2. Development Build (with watch)

```bash
npm run dev
```

Runs `webpack --mode development --watch`. Auto-rebuilds on file changes with eval source maps.

### 3. Production Build

```bash
npm run build
```

Runs `webpack --mode production`. Creates optimized, minified bundles in `dist/` with:

- Tree shaking (`usedExports: true`)
- Console log removal (`drop_console: true`)
- Code splitting into 6+ chunks (runtime, vendors, react-router, recharts, lucide, common)
- Source maps for debugging

### 4. Type Checking

```bash
npm run type-check
```

Runs `tsc --noEmit` to validate TypeScript without emitting files.

### 5. i18n POT Generation

```bash
npm run i18n:make-pot
```

Generates translation template file for the admin interface.

## Webpack Code Splitting Strategy

The build produces multiple chunks for optimal loading:

| Chunk                | Contents                           | Loading                    |
| -------------------- | ---------------------------------- | -------------------------- |
| `runtime.js`         | Webpack module loader              | Immediate (required first) |
| `vendors.js`         | date-fns, clsx, jsPDF, html2canvas | Immediate                  |
| `react-router.js`    | React Router DOM                   | Immediate                  |
| `lucide.js`          | Lucide React icons (tree-shaken)   | Immediate                  |
| `recharts.js`        | Recharts charting library          | Lazy (per-page)            |
| `common.js`          | Shared code across routes          | Immediate                  |
| `tracksure-admin.js` | Main application code              | Immediate                  |

PHP enqueues chunks in correct dependency order via `class-tracksure-admin-ui.php`.

## Provider Hierarchy

The app wraps components in a specific provider order (see `AppProviders.tsx`):

```
ThemeProvider
  └─ ExtensionRegistryProvider
       └─ SettingsExtensionProvider
            └─ ConfigProvider         (config, viewMode)
                 └─ UIProvider        (isLoading)
                      └─ DataProvider (dateRange, filters, segment)
                           └─ AppProvider (combined — backward compat)
```

## Extension API (for Free/Pro Modules)

Free and Pro plugins can extend the admin UI without modifying core.

### PHP: Register Extensions

Extensions data is passed from PHP to React via `window.trackSureExtensions`:

```php
// In free-admin-extensions.php or pro equivalent:
add_action('tracksure_register_admin_extensions', function($registry) {
  $registry->register_extension([
    'id'              => 'tracksure-free',
    'name'            => 'TrackSure Free',
    'version'         => '1.0.0',
    'settings_groups' => [ /* ... */ ],
  ]);
});
```

The core `class-tracksure-admin-ui.php` enriches each extension with:

- **Destinations** — auto-assigned from Destinations Manager by `enabled_key` prefix
- **Integrations** — auto-assigned from Integrations Manager by `enabled_key` prefix
- **Settings fields** — enriched with schema metadata (type, label, options, validation)

### JavaScript: Register Routes/Nav/Widgets

```javascript
// In your Free/Pro admin script (loads after tracksure-admin):
window.trackSureExtensions = window.trackSureExtensions || [];
window.trackSureExtensions.push({
  id: "tracksure-free",
  name: "TrackSure Free",
  version: "1.0.0",

  // Add new routes
  routes: [
    {
      path: "/attribution",
      element: MyAttributionPage,
      nav: {
        group: "analytics",
        label: "Attribution",
        order: 30,
        icon: "🎯",
      },
    },
  ],

  // Add nav groups
  navGroups: [{ id: "destinations", label: "Destinations", order: 40 }],

  // Add dashboard widgets
  widgets: [{ slot: "overview.bottom", order: 10, element: MyCustomWidget }],
});
```

### PHP Enqueue Hook

```php
add_action('tracksure_admin_enqueue_scripts', function($hook) {
  wp_enqueue_script(
    'tracksure-free-admin',
    plugins_url('build/admin.js', __FILE__),
    array('tracksure-admin'),
    '1.0.0',
    true
  );
});
```

## API Integration

The admin app receives config from PHP via `wp_localize_script()`:

```javascript
// window.trackSureAdmin (passed by class-tracksure-admin-ui.php)
{
  apiUrl: "https://site.com/wp-json/ts/v1",    // REST API namespace
  nonce: "wp_rest_nonce",                        // WordPress REST nonce
  siteUrl: "https://site.com",                   // Site URL
  timezone: "America/New_York",                  // WordPress timezone
  dateFormat: "F j, Y",                          // WordPress date format
  isEcommerce: true,                             // E-commerce platform detected
  currency: "USD",                               // Auto-detected currency code
  currencySymbol: "$"                             // Auto-detected currency symbol
}
```

**Currency auto-detection** supports: WooCommerce, FluentCart, Easy Digital Downloads, SureCart, and CartFlows. Filterable via `tracksure_admin_currency` PHP filter.

## Theme System

Users can toggle between three modes:

- **Light** — Light theme
- **Dark** — Dark theme
- **Auto** — Follows system preference (`prefers-color-scheme`)

Preference stored in `localStorage` as `tracksure_theme`.

## Design Tokens

All colors, spacing, and typography use CSS custom properties defined in `variables.css`:

```css
/* Primary */
--ts-primary: #4F46E5;         /* Brand indigo */
--ts-primary-dark: #4338CA;
--ts-primary-light: #818CF8;

/* Semantic */
--ts-success: #10B981;         /* Green */
--ts-warning: #F59E0B;         /* Amber */
--ts-danger: #EF4444;          /* Red */

/* Neutrals */
--ts-gray-50 through --ts-gray-900

/* Chart Palette (8 colors) */
--ts-chart-1 through --ts-chart-8

/* Surfaces */
--ts-surface                   /* Card background */
--ts-text                      /* Primary text */
--ts-text-muted                /* Secondary text */
--ts-border                    /* Borders */

/* Layout */
--ts-radius-lg: 12px;         /* Border radius */
--ts-spacing-md: 16px;        /* Spacing */
```

## Admin Pages

| Page            | Route              | Description                                               |
| --------------- | ------------------ | --------------------------------------------------------- |
| Overview        | `/`                | Dashboard with KPI cards, trend charts, traffic breakdown |
| Realtime        | `/realtime`        | Live visitor activity                                     |
| Sessions        | `/sessions`        | Session analytics and details                             |
| Traffic Sources | `/traffic-sources` | UTM, referrer, channel analysis                           |
| Content         | `/content`         | Page/post performance metrics                             |
| Attribution     | `/attribution`     | Multi-touch attribution models                            |
| Conversions     | `/conversions`     | Conversion event tracking                                 |
| Journeys        | `/journeys`        | Visitor journey explorer                                  |
| Products        | `/products`        | E-commerce product analytics                              |
| Goals           | `/goals`           | Goal creation, tracking, and management                   |
| Destinations    | `/destinations`    | Ad platform configuration (Meta, GA4, etc.)               |
| Integrations    | `/integrations`    | E-commerce/form integrations config                       |
| Settings        | `/settings`        | General tracking and privacy settings                     |
| Diagnostics     | `/diagnostics`     | System diagnostics and debugging                          |
| Data Quality    | `/data-quality`    | Data quality monitoring                                   |

## Browser Support

- Chrome / Edge 90+
- Firefox 88+
- Safari 14+

(React 18 requires modern browsers)

## Troubleshooting

**"Assets not built" error in WordPress admin:**

```bash
cd admin
npm install
npm run build
```

**TypeScript errors:**

```bash
npm install   # Install dependencies first
npm run type-check   # Then check types
```

**Extension routes not appearing:**
Check browser console for registration errors. Ensure your script loads after `tracksure-admin` by declaring it as a dependency in `wp_enqueue_script()`.

**Chunks not loading:**
Verify all `dist/*.js` files exist. Re-run `npm run build`. The PHP enqueue code in `class-tracksure-admin-ui.php` conditionally loads each chunk only if the file exists.
