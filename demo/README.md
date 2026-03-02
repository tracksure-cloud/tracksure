# TrackSure Demo Data Seeder

Generates realistic analytics data for InstaWP demo snapshots so dashboards look populated when customers preview the plugin.

## What It Generates

| Table       | Records | Notes                                                 |
| ----------- | ------- | ----------------------------------------------------- |
| Visitors    | 2,000   | Unique client IDs, spread over 30 days                |
| Sessions    | ~3,500  | 1–5 per visitor, realistic UTM/source data            |
| Events      | ~20,000 | page_view, click, scroll, form_submit, purchase, etc. |
| Goals       | 4       | Purchase, Newsletter, Demo Request, Pricing View      |
| Conversions | ~275    | ~8% conversion rate, $40 avg value                    |
| Touchpoints | ~3,500  | One per session, with attribution weights             |

### Realistic Distributions

- **Sources**: Direct 28%, Google Organic 22%, Google Ads 12%, Email 8%, Facebook 9%, Social 9%, Referral 9%, Other 3%
- **Devices**: Desktop 55%, Mobile 35%, Tablet 10%
- **Browsers**: Chrome 62%, Safari 20%, Firefox 8%, Edge 7%, Opera 3%
- **Countries**: US 32%, GB 12%, DE 9%, IN 8%, CA 7%, and 9 more
- **Traffic patterns**: Weekday bias, 8AM–8PM peak hours

## Installation

### For InstaWP Snapshots

1. Copy the seeder file to `mu-plugins/`:
   ```
   wp-content/mu-plugins/tracksure-demo-seeder.php
   ```
2. Ensure TrackSure plugin is activated with tables created
3. Visit any admin page — data auto-generates on first load
4. Create your InstaWP snapshot

The seeder auto-refreshes when data becomes stale (>3 days old), so cloned sites always show fresh-looking data.

### For Local Development

```bash
# Copy to mu-plugins
cp wp-content/plugins/tracksure/demo/tracksure-demo-seeder.php wp-content/mu-plugins/

# Or use WP-CLI directly
wp tracksure-demo seed
```

## WP-CLI Commands

```bash
# Generate demo data (clears existing first)
wp tracksure-demo seed

# Generate with custom settings
wp tracksure-demo seed --visitors=5000 --days=90

# Check current status
wp tracksure-demo status

# Remove all demo data
wp tracksure-demo clear
```

## Configuration

Add to `wp-config.php` to customize defaults:

```php
// Number of visitors to generate (default: 2000)
define( 'TRACKSURE_DEMO_VISITORS', 2000 );

// Days of historical data (default: 30)
define( 'TRACKSURE_DEMO_DAYS', 30 );

// Auto-refresh stale data on admin load (default: true)
define( 'TRACKSURE_DEMO_AUTO_REFRESH', true );

// Days before data is considered stale (default: 3)
define( 'TRACKSURE_DEMO_STALE_DAYS', 3 );
```

## InstaWP Workflow

1. Set up a clean WordPress site with TrackSure activated
2. Copy `tracksure-demo-seeder.php` to `wp-content/mu-plugins/`
3. Visit admin — seeder runs automatically (~13s for 2000 visitors)
4. Verify dashboards look good
5. Create InstaWP snapshot
6. Share snapshot URL with customers

When a customer launches a new site from the snapshot:

- If data is >3 days old, the seeder auto-regenerates fresh data
- All dates are relative to "today," so dashboards always look current

## Notes

- The seeder uses `TRUNCATE TABLE` when clearing — all existing TrackSure data is removed
- Runs in ~13 seconds for the default 2000 visitors
- Uses batch INSERTs (100–200 rows) for performance
- Does **not** populate aggregation tables (`agg_hourly`, `agg_daily`) — the dashboard reads from raw tables
- This file is excluded from the WordPress.org release ZIP
