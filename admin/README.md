# TrackSure Admin UI

React 18 SPA for TrackSure analytics admin interface.

## Architecture Decision: Why No Redux?

**We chose React Context + Hooks over Redux for these reasons:**

1. **WordPress Ecosystem Alignment**: Gutenberg (WordPress block editor) uses Context + Hooks pattern
2. **Simpler Extension Model**: Free/Pro modules can inject routes/components without Redux middleware complexity
3. **Smaller Bundle**: Context is built-in (0KB), Redux adds ~8KB + boilerplate
4. **Easier Maintenance**: Less abstraction, clearer data flow for small-medium apps
5. **Sufficient for Use Case**: We need global state for theme/date-range/filters, not complex async orchestration

## State Management Pattern

```typescript
// Three contexts handle different concerns:
AppContext; // Global: config, dateRange, filters, loading
ThemeContext; // UI: theme preference (light/dark/auto)
ExtensionRegistry; // Modules: Free/Pro register routes/nav/widgets
```

## Tech Stack

- **React 18** - UI framework
- **React Router v6** - Client-side routing
- **TypeScript** - Type safety
- **Recharts** - Charts/graphs
- **date-fns** - Date utilities
- **Webpack** - Build pipeline

## Project Structure

```
admin/
├── src/
│   ├── index.tsx                 # Entry point
│   ├── App.tsx                   # Main app component
│   ├── types/
│   │   └── index.ts              # TypeScript types
│   ├── contexts/
│   │   ├── AppContext.tsx        # Global app state
│   │   ├── ThemeContext.tsx      # Theme management
│   │   └── ExtensionRegistryContext.tsx  # Module extensions
│   ├── providers/
│   │   └── AppProviders.tsx      # Combined providers
│   ├── components/
│   │   ├── layout/
│   │   │   ├── AppShell.tsx      # Main layout
│   │   │   ├── TopBar.tsx        # Header
│   │   │   └── Sidebar.tsx       # Navigation
│   │   └── ui/
│   │       ├── KPICard.tsx       # Metric cards
│   │       ├── DateRangePicker.tsx
│   │       └── ThemeToggle.tsx
│   ├── pages/
│   │   ├── OverviewPage.tsx
│   │   ├── RealtimePage.tsx
│   │   ├── SettingsPage.tsx
│   │   └── NotFoundPage.tsx
│   └── styles/
│       └── global.css            # Design system tokens
├── dist/                         # Built assets (gitignored)
├── package.json
├── tsconfig.json
└── webpack.config.js
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

This watches for file changes and rebuilds automatically.

### 3. Production Build

```bash
npm run build
```

Creates optimized bundle in `dist/tracksure-admin.js`.

### 4. Type Checking

```bash
npm run type-check
```

## Extension API (for Free/Pro Modules)

Free and Pro plugins can extend the admin UI without modifying core:

### Register Extension (JavaScript)

```javascript
// In your Free/Pro admin enqueue:
wp_enqueue_script('tracksure-free-admin', ...);

// In your tracksure-free-admin.js:
window.trackSureExtensions = window.trackSureExtensions || [];
window.trackSureExtensions.push({
  id: 'tracksure-free',
  name: 'TrackSure Free',
  version: '1.0.0',

  // Add new routes
  routes: [
    {
      path: '/attribution',
      element: MyAttributionPage, // React component
      nav: {
        group: 'analytics',
        label: 'Attribution',
        order: 30,
        icon: '🎯'
      }
    }
  ],

  // Add nav groups
  navGroups: [
    {
      id: 'destinations',
      label: 'Destinations',
      order: 40
    }
  ],

  // Add dashboard widgets
  widgets: [
    {
      slot: 'overview.bottom',
      order: 10,
      element: MyCustomWidget
    }
  ]
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

## Theme System

Users can toggle between:

- ☀️ **Light** - Light theme
- 🌙 **Dark** - Dark theme
- 🔄 **Auto** - Follows system preference

Preference stored in `localStorage` as `tracksure_theme`.

## Design Tokens

All colors/spacing use CSS custom properties:

```css
--ts-primary        /* Brand color (teal/blue) */
--ts-surface        /* Card background */
--ts-text           /* Primary text */
--ts-text-muted     /* Secondary text */
--ts-border         /* Borders */
--ts-radius-lg      /* Border radius (12px) */
--ts-spacing-md     /* Spacing (16px) */
```

## API Integration

Admin app expects this global config (passed via `wp_localize_script`):

```javascript
window.trackSureAdmin = {
  apiUrl: "https://site.com/wp-json/tracksure/v1/",
  apiToken: "secret_token",
  nonce: "wp_rest_nonce",
  siteUrl: "https://site.com",
  timezone: "America/New_York",
  dateFormat: "F j, Y",
  isPro: false,
};
```

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+

(React 18 requires modern browsers)

## Production Checklist

- [ ] Run `npm run build` before release
- [ ] Commit `dist/tracksure-admin.js` to version control
- [ ] Verify theme toggle works
- [ ] Test extension registration with Free/Pro
- [ ] Check date range picker in all timezones
- [ ] Validate dark mode contrast ratios (WCAG AA)

## Troubleshooting

**"Assets not built" error in WordPress admin:**

```bash
cd admin
npm install
npm run build
```

**TypeScript errors:**
Dependencies not installed. Run `npm install`.

**Extension routes not appearing:**
Check browser console for registration errors. Ensure your script loads after `tracksure-admin`.
