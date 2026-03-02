<?php

/**
 * TrackSure Demo Data Seeder — MU-Plugin for InstaWP snapshots.
 *
 * Generates realistic analytics data so demo dashboards look populated.
 * Data is date-relative (last 30 days from today) and auto-refreshes
 * when stale, so InstaWP clones always show fresh-looking metrics.
 *
 * INSTALLATION:
 *   Copy this file to:  wp-content/mu-plugins/tracksure-demo-seeder.php
 *
 * WP-CLI COMMANDS:
 *   wp tracksure-demo seed    — Generate demo data (clears existing first)
 *   wp tracksure-demo clear   — Remove all demo data
 *   wp tracksure-demo status  — Check demo data status
 *
 * CONFIGURATION (wp-config.php):
 *   define( 'TRACKSURE_DEMO_VISITORS',     2000 );  // Visitor count
 *   define( 'TRACKSURE_DEMO_DAYS',         30 );    // Days of data
 *   define( 'TRACKSURE_DEMO_AUTO_REFRESH', true );   // Re-seed when stale
 *   define( 'TRACKSURE_DEMO_STALE_DAYS',   3 );     // Days before refresh
 *
 * @package TrackSure\Demo
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Demo data seeder for TrackSure analytics plugin.
 */
class TrackSure_Demo_Seeder
{

    /**
     * Option key that stores seeding metadata.
     *
     * @var string
     */
    const OPTION_KEY = 'tracksure_demo_seeded';

    /**
     * Number of visitors to generate.
     *
     * @var int
     */
    private $visitor_count;

    /**
     * Number of days of historical data.
     *
     * @var int
     */
    private $days;

    /**
     * Batch size for INSERT statements.
     *
     * @var int
     */
    private $batch_size = 200;

    /**
     * Site URL for generating page URLs.
     *
     * @var string
     */
    private $site_url;

    /**
     * wpdb instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Traffic sources with realistic distribution weights.
     *
     * @var array
     */
    private $sources = array(
        array(
            'source'   => '(direct)',
            'medium'   => '(none)',
            'campaign' => null,
            'weight'   => 28,
            'channel'  => 'Direct',
        ),
        array(
            'source'   => 'google',
            'medium'   => 'organic',
            'campaign' => null,
            'weight'   => 22,
            'channel'  => 'Organic Search',
        ),
        array(
            'source'   => 'google',
            'medium'   => 'cpc',
            'campaign' => 'brand-search',
            'weight'   => 7,
            'channel'  => 'Paid Search',
        ),
        array(
            'source'   => 'google',
            'medium'   => 'cpc',
            'campaign' => 'analytics-tools',
            'weight'   => 5,
            'channel'  => 'Paid Search',
        ),
        array(
            'source'   => 'facebook',
            'medium'   => 'paid',
            'campaign' => 'retargeting-q1',
            'weight'   => 5,
            'channel'  => 'Paid Social',
        ),
        array(
            'source'   => 'facebook',
            'medium'   => 'social',
            'campaign' => null,
            'weight'   => 4,
            'channel'  => 'Organic Social',
        ),
        array(
            'source'   => 'newsletter',
            'medium'   => 'email',
            'campaign' => 'weekly-digest',
            'weight'   => 5,
            'channel'  => 'Email',
        ),
        array(
            'source'   => 'newsletter',
            'medium'   => 'email',
            'campaign' => 'product-launch',
            'weight'   => 3,
            'channel'  => 'Email',
        ),
        array(
            'source'   => 'twitter',
            'medium'   => 'social',
            'campaign' => null,
            'weight'   => 3,
            'channel'  => 'Organic Social',
        ),
        array(
            'source'   => 'linkedin',
            'medium'   => 'social',
            'campaign' => null,
            'weight'   => 3,
            'channel'  => 'Organic Social',
        ),
        array(
            'source'   => 'bing',
            'medium'   => 'organic',
            'campaign' => null,
            'weight'   => 4,
            'channel'  => 'Organic Search',
        ),
        array(
            'source'   => 'producthunt',
            'medium'   => 'referral',
            'campaign' => null,
            'weight'   => 3,
            'channel'  => 'Referral',
        ),
        array(
            'source'   => 'wordpress.org',
            'medium'   => 'referral',
            'campaign' => null,
            'weight'   => 4,
            'channel'  => 'Referral',
        ),
        array(
            'source'   => 'github',
            'medium'   => 'referral',
            'campaign' => null,
            'weight'   => 2,
            'channel'  => 'Referral',
        ),
        array(
            'source'   => 'youtube',
            'medium'   => 'video',
            'campaign' => 'tutorials',
            'weight'   => 2,
            'channel'  => 'Video',
        ),
    );

    /**
     * Product catalog for eCommerce event generation.
     *
     * @var array
     */
    private $products = array(
        array(
            'item_id'       => 'PROD-001',
            'item_name'     => 'TrackSure Pro License',
            'item_category' => 'Software',
            'price'         => 99.00,
            'weight'        => 18,
        ),
        array(
            'item_id'       => 'PROD-002',
            'item_name'     => 'TrackSure Business License',
            'item_category' => 'Software',
            'price'         => 199.00,
            'weight'        => 12,
        ),
        array(
            'item_id'       => 'PROD-003',
            'item_name'     => 'TrackSure Enterprise License',
            'item_category' => 'Software',
            'price'         => 299.00,
            'weight'        => 6,
        ),
        array(
            'item_id'       => 'PROD-004',
            'item_name'     => 'TrackSure Starter Plan',
            'item_category' => 'Software',
            'price'         => 29.00,
            'weight'        => 20,
        ),
        array(
            'item_id'       => 'PROD-005',
            'item_name'     => 'Conversion Boost Add-on',
            'item_category' => 'Add-ons',
            'price'         => 49.00,
            'weight'        => 14,
        ),
        array(
            'item_id'       => 'PROD-006',
            'item_name'     => 'Multi-Touch Attribution Pack',
            'item_category' => 'Add-ons',
            'price'         => 79.00,
            'weight'        => 10,
        ),
        array(
            'item_id'       => 'PROD-007',
            'item_name'     => 'White-Label Agency Bundle',
            'item_category' => 'Bundles',
            'price'         => 499.00,
            'weight'        => 4,
        ),
        array(
            'item_id'       => 'PROD-008',
            'item_name'     => 'Analytics Training Course',
            'item_category' => 'Education',
            'price'         => 149.00,
            'weight'        => 8,
        ),
        array(
            'item_id'       => 'PROD-009',
            'item_name'     => 'Priority Support (1 Year)',
            'item_category' => 'Services',
            'price'         => 99.00,
            'weight'        => 10,
        ),
        array(
            'item_id'       => 'PROD-010',
            'item_name'     => 'Custom Integration Setup',
            'item_category' => 'Services',
            'price'         => 249.00,
            'weight'        => 5,
        ),
        array(
            'item_id'       => 'PROD-011',
            'item_name'     => 'Google Ads Connector',
            'item_category' => 'Add-ons',
            'price'         => 39.00,
            'weight'        => 12,
        ),
        array(
            'item_id'       => 'PROD-012',
            'item_name'     => 'Facebook CAPI Module',
            'item_category' => 'Add-ons',
            'price'         => 39.00,
            'weight'        => 10,
        ),
        array(
            'item_id'       => 'PROD-013',
            'item_name'     => 'Server-Side Tracking Kit',
            'item_category' => 'Bundles',
            'price'         => 349.00,
            'weight'        => 6,
        ),
        array(
            'item_id'       => 'PROD-014',
            'item_name'     => 'Privacy Compliance Pack',
            'item_category' => 'Add-ons',
            'price'         => 59.00,
            'weight'        => 9,
        ),
        array(
            'item_id'       => 'PROD-015',
            'item_name'     => 'eCommerce Analytics Dashboard',
            'item_category' => 'Software',
            'price'         => 129.00,
            'weight'        => 7,
        ),
    );

    /**
     * Pages with URLs, titles, and traffic weight.
     *
     * @var array
     */
    private $pages = array(
        array(
            'url'    => '/',
            'path'   => '/',
            'title'  => 'TrackSure — Privacy-First Analytics for WordPress',
            'weight' => 22,
        ),
        array(
            'url'    => '/pricing/',
            'path'   => '/pricing/',
            'title'  => 'Pricing Plans — TrackSure',
            'weight' => 14,
        ),
        array(
            'url'    => '/features/',
            'path'   => '/features/',
            'title'  => 'Features — TrackSure Analytics',
            'weight' => 10,
        ),
        array(
            'url'    => '/features/real-time-dashboard/',
            'path'   => '/features/real-time-dashboard/',
            'title'  => 'Real-Time Dashboard — TrackSure',
            'weight' => 4,
        ),
        array(
            'url'    => '/features/conversion-tracking/',
            'path'   => '/features/conversion-tracking/',
            'title'  => 'Conversion Tracking — TrackSure',
            'weight' => 4,
        ),
        array(
            'url'    => '/features/multi-touch-attribution/',
            'path'   => '/features/multi-touch-attribution/',
            'title'  => 'Multi-Touch Attribution — TrackSure',
            'weight' => 3,
        ),
        array(
            'url'    => '/features/google-ads-integration/',
            'path'   => '/features/google-ads-integration/',
            'title'  => 'Google Ads Integration — TrackSure',
            'weight' => 3,
        ),
        array(
            'url'    => '/blog/',
            'path'   => '/blog/',
            'title'  => 'Blog — TrackSure',
            'weight' => 6,
        ),
        array(
            'url'    => '/blog/best-google-analytics-alternative/',
            'path'   => '/blog/best-google-analytics-alternative/',
            'title'  => 'Best Google Analytics Alternative for WordPress',
            'weight' => 5,
        ),
        array(
            'url'    => '/blog/woocommerce-analytics-guide/',
            'path'   => '/blog/woocommerce-analytics-guide/',
            'title'  => 'WooCommerce Analytics: The Complete Guide',
            'weight' => 4,
        ),
        array(
            'url'    => '/blog/privacy-first-analytics/',
            'path'   => '/blog/privacy-first-analytics/',
            'title'  => 'Why Privacy-First Analytics Matter in 2026',
            'weight' => 3,
        ),
        array(
            'url'    => '/blog/server-side-tracking-explained/',
            'path'   => '/blog/server-side-tracking-explained/',
            'title'  => 'Server-Side Tracking Explained for WordPress',
            'weight' => 3,
        ),
        array(
            'url'    => '/docs/',
            'path'   => '/docs/',
            'title'  => 'Documentation — TrackSure',
            'weight' => 4,
        ),
        array(
            'url'    => '/docs/getting-started/',
            'path'   => '/docs/getting-started/',
            'title'  => 'Getting Started — TrackSure Docs',
            'weight' => 3,
        ),
        array(
            'url'    => '/docs/goal-setup/',
            'path'   => '/docs/goal-setup/',
            'title'  => 'Setting Up Goals — TrackSure Docs',
            'weight' => 2,
        ),
        array(
            'url'    => '/contact/',
            'path'   => '/contact/',
            'title'  => 'Contact Us — TrackSure',
            'weight' => 3,
        ),
        array(
            'url'    => '/demo/',
            'path'   => '/demo/',
            'title'  => 'Live Demo — TrackSure',
            'weight' => 4,
        ),
        array(
            'url'    => '/signup/',
            'path'   => '/signup/',
            'title'  => 'Create Your Account — TrackSure',
            'weight' => 2,
        ),
        array(
            'url'    => '/checkout/',
            'path'   => '/checkout/',
            'title'  => 'Checkout — TrackSure',
            'weight' => 1,
        ),
        // Product / shop pages — higher traffic for product browsing.
        array(
            'url'        => '/shop/',
            'path'       => '/shop/',
            'title'      => 'Shop — TrackSure',
            'weight'     => 6,
            'is_product' => false,
        ),
        array(
            'url'        => '/product/tracksure-pro-license/',
            'path'       => '/product/tracksure-pro-license/',
            'title'      => 'TrackSure Pro License — Shop',
            'weight'     => 5,
            'is_product' => true,
            'product_id' => 'PROD-001',
        ),
        array(
            'url'        => '/product/tracksure-business-license/',
            'path'       => '/product/tracksure-business-license/',
            'title'      => 'TrackSure Business License — Shop',
            'weight'     => 4,
            'is_product' => true,
            'product_id' => 'PROD-002',
        ),
        array(
            'url'        => '/product/tracksure-starter-plan/',
            'path'       => '/product/tracksure-starter-plan/',
            'title'      => 'TrackSure Starter Plan — Shop',
            'weight'     => 5,
            'is_product' => true,
            'product_id' => 'PROD-004',
        ),
        array(
            'url'        => '/product/conversion-boost-addon/',
            'path'       => '/product/conversion-boost-addon/',
            'title'      => 'Conversion Boost Add-on — Shop',
            'weight'     => 3,
            'is_product' => true,
            'product_id' => 'PROD-005',
        ),
        array(
            'url'        => '/product/multi-touch-attribution-pack/',
            'path'       => '/product/multi-touch-attribution-pack/',
            'title'      => 'Multi-Touch Attribution Pack — Shop',
            'weight'     => 3,
            'is_product' => true,
            'product_id' => 'PROD-006',
        ),
        array(
            'url'        => '/product/google-ads-connector/',
            'path'       => '/product/google-ads-connector/',
            'title'      => 'Google Ads Connector — Shop',
            'weight'     => 3,
            'is_product' => true,
            'product_id' => 'PROD-011',
        ),
        array(
            'url'        => '/product/analytics-training-course/',
            'path'       => '/product/analytics-training-course/',
            'title'      => 'Analytics Training Course — Shop',
            'weight'     => 2,
            'is_product' => true,
            'product_id' => 'PROD-008',
        ),
        array(
            'url'        => '/product/server-side-tracking-kit/',
            'path'       => '/product/server-side-tracking-kit/',
            'title'      => 'Server-Side Tracking Kit — Shop',
            'weight'     => 2,
            'is_product' => true,
            'product_id' => 'PROD-013',
        ),
    );

    /**
     * Device types with distribution.
     *
     * @var array
     */
    private $devices = array(
        array('type' => 'desktop', 'weight' => 55),
        array('type' => 'mobile', 'weight' => 35),
        array('type' => 'tablet', 'weight' => 10),
    );

    /**
     * Browsers with distribution.
     *
     * @var array
     */
    private $browsers = array(
        array('name' => 'Chrome', 'weight' => 62),
        array('name' => 'Safari', 'weight' => 20),
        array('name' => 'Firefox', 'weight' => 8),
        array('name' => 'Edge', 'weight' => 7),
        array('name' => 'Opera', 'weight' => 3),
    );

    /**
     * Operating systems with distribution.
     *
     * @var array
     */
    private $os_list = array(
        array('name' => 'Windows', 'weight' => 38),
        array('name' => 'macOS', 'weight' => 16),
        array('name' => 'iOS', 'weight' => 20),
        array('name' => 'Android', 'weight' => 20),
        array('name' => 'Linux', 'weight' => 6),
    );

    /**
     * Countries with distribution and city data.
     *
     * @var array
     */
    private $countries = array(
        array('code' => 'US', 'weight' => 32, 'cities' => array('New York', 'San Francisco', 'Los Angeles', 'Chicago', 'Austin', 'Seattle', 'Miami', 'Boston')),
        array('code' => 'GB', 'weight' => 12, 'cities' => array('London', 'Manchester', 'Birmingham', 'Leeds')),
        array('code' => 'DE', 'weight' => 9, 'cities'  => array('Berlin', 'Munich', 'Hamburg', 'Frankfurt')),
        array('code' => 'CA', 'weight' => 7, 'cities'  => array('Toronto', 'Vancouver', 'Montreal', 'Ottawa')),
        array('code' => 'AU', 'weight' => 5, 'cities'  => array('Sydney', 'Melbourne', 'Brisbane')),
        array('code' => 'IN', 'weight' => 8, 'cities'  => array('Mumbai', 'Bangalore', 'Delhi', 'Hyderabad')),
        array('code' => 'FR', 'weight' => 5, 'cities'  => array('Paris', 'Lyon', 'Marseille')),
        array('code' => 'NL', 'weight' => 4, 'cities'  => array('Amsterdam', 'Rotterdam', 'The Hague')),
        array('code' => 'BR', 'weight' => 4, 'cities'  => array('São Paulo', 'Rio de Janeiro')),
        array('code' => 'JP', 'weight' => 3, 'cities'  => array('Tokyo', 'Osaka')),
        array('code' => 'SE', 'weight' => 3, 'cities'  => array('Stockholm', 'Gothenburg')),
        array('code' => 'SG', 'weight' => 2, 'cities'  => array('Singapore')),
        array('code' => 'PL', 'weight' => 3, 'cities'  => array('Warsaw', 'Krakow')),
        array('code' => 'ES', 'weight' => 3, 'cities'  => array('Madrid', 'Barcelona')),
    );

    /**
     * Goals to create for the demo.
     *
     * @var array
     */
    private $goals = array(
        array(
            'name'         => 'Completed Purchase',
            'description'  => 'Customer completed a purchase',
            'event_name'   => 'purchase',
            'trigger_type' => 'event',
            'value_type'   => 'dynamic',
            'fixed_value'  => null,
        ),
        array(
            'name'         => 'Newsletter Signup',
            'description'  => 'Visitor subscribed to newsletter',
            'event_name'   => 'form_submit',
            'trigger_type' => 'event',
            'value_type'   => 'fixed',
            'fixed_value'  => 5.00,
        ),
        array(
            'name'         => 'Demo Request',
            'description'  => 'Visitor requested a live demo',
            'event_name'   => 'form_submit',
            'trigger_type' => 'event',
            'value_type'   => 'fixed',
            'fixed_value'  => 25.00,
        ),
        array(
            'name'         => 'Pricing Page Visit',
            'description'  => 'Visitor viewed the pricing page',
            'event_name'   => 'page_view',
            'trigger_type' => 'pageview',
            'value_type'   => 'fixed',
            'fixed_value'  => 2.00,
        ),
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb          = $wpdb;
        $this->visitor_count  = defined('TRACKSURE_DEMO_VISITORS') ? (int) TRACKSURE_DEMO_VISITORS : 2000;
        $this->days           = defined('TRACKSURE_DEMO_DAYS') ? (int) TRACKSURE_DEMO_DAYS : 30;
        $this->site_url       = home_url();
    }

    /**
     * Check whether TrackSure tables exist.
     *
     * @return bool
     */
    public function tables_exist()
    {
        $table = $this->wpdb->prefix . 'tracksure_visitors';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );
        return ! empty($result);
    }

    /**
     * Check if demo data needs seeding or refreshing.
     *
     * @return bool True if seeding is needed.
     */
    public function needs_seeding()
    {
        $meta = get_option(self::OPTION_KEY);

        // Never seeded.
        if (empty($meta)) {
            return true;
        }

        // Auto-refresh if data is stale.
        $auto_refresh = defined('TRACKSURE_DEMO_AUTO_REFRESH') ? TRACKSURE_DEMO_AUTO_REFRESH : true;
        $stale_days   = defined('TRACKSURE_DEMO_STALE_DAYS') ? (int) TRACKSURE_DEMO_STALE_DAYS : 3;

        if ($auto_refresh && isset($meta['seeded_at'])) {
            $seeded_time = strtotime($meta['seeded_at']);
            $stale_after = $stale_days * DAY_IN_SECONDS;
            if ((time() - $seeded_time) > $stale_after) {
                return true;
            }
        }

        return false;
    }

    /**
     * Main seeding entry point.
     *
     * @param bool $verbose Whether to output progress messages (for WP-CLI).
     * @return void
     */
    public function seed($verbose = false)
    {
        $start = microtime(true);

        $this->log($verbose, 'Clearing existing demo data...');
        $this->clear();

        $this->log($verbose, 'Creating goals...');
        $goal_ids = $this->seed_goals();
        $this->log($verbose, sprintf('  Created %d goals.', count($goal_ids)));

        $this->log($verbose, sprintf('Generating %d visitors over %d days...', $this->visitor_count, $this->days));
        $visitor_ids = $this->seed_visitors();
        $this->log($verbose, sprintf('  Created %d visitors.', count($visitor_ids)));

        $this->log($verbose, 'Generating sessions, events, conversions, and touchpoints...');
        $stats = $this->seed_sessions_and_events($visitor_ids, $goal_ids, $verbose);

        $this->log($verbose, 'Calculating attribution models...');
        $attr_count = $this->seed_conversion_attribution();
        $this->log($verbose, sprintf('  Created %s attribution records across 5 models.', number_format($attr_count)));

        $this->log($verbose, 'Aggregating product analytics data...');
        $product_rows = $this->seed_product_aggregations();
        $this->log($verbose, sprintf('  Created %s product aggregation rows.', number_format($product_rows)));

        $elapsed = round(microtime(true) - $start, 1);

        // Store metadata.
        update_option(self::OPTION_KEY, array(
            'seeded_at'   => gmdate('Y-m-d H:i:s'),
            'visitors'    => count($visitor_ids),
            'sessions'    => $stats['sessions'],
            'events'      => $stats['events'],
            'conversions' => $stats['conversions'],
            'touchpoints' => $stats['touchpoints'],
            'elapsed'     => $elapsed,
        ), false);

        $this->log($verbose, '');
        $this->log($verbose, '=== Demo Seeding Complete ===');
        $this->log($verbose, sprintf('  Visitors:    %s', number_format(count($visitor_ids))));
        $this->log($verbose, sprintf('  Sessions:    %s', number_format($stats['sessions'])));
        $this->log($verbose, sprintf('  Events:      %s', number_format($stats['events'])));
        $this->log($verbose, sprintf('  Conversions: %s', number_format($stats['conversions'])));
        $this->log($verbose, sprintf('  Touchpoints: %s', number_format($stats['touchpoints'])));
        $this->log($verbose, sprintf('  Time:        %ss', $elapsed));
    }

    /**
     * Remove all demo data from TrackSure tables.
     *
     * @return void
     */
    public function clear()
    {
        $prefix = $this->wpdb->prefix . 'tracksure_';
        $tables = array(
            'touchpoints',
            'conversion_attribution',
            'conversions',
            'events',
            'click_ids',
            'sessions',
            'visitors',
            'goals',
            'agg_hourly',
            'agg_daily',
            'agg_product_daily',
            'outbox',
        );

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->query("TRUNCATE TABLE {$prefix}{$table}");
        }

        delete_option(self::OPTION_KEY);

        // Also clear metric caches.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->options} WHERE option_name LIKE %s",
                '_transient%tracksure_%'
            )
        );
    }

    /**
     * Get current demo data status.
     *
     * @return array|false Status data or false if not seeded.
     */
    public function get_status()
    {
        return get_option(self::OPTION_KEY);
    }

	// ============================================================
	// PRIVATE SEEDING METHODS
	// ============================================================

    /**
     * Create demo goals.
     *
     * @return array Array of goal_ids keyed by goal name.
     */
    private function seed_goals()
    {
        $goal_ids = array();
        $now      = gmdate('Y-m-d H:i:s');

        foreach ($this->goals as $goal) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'tracksure_goals',
                array(
                    'name'         => $goal['name'],
                    'description'  => $goal['description'],
                    'event_name'   => $goal['event_name'],
                    'conditions'   => '[]',
                    'trigger_type' => $goal['trigger_type'],
                    'match_logic'  => 'all',
                    'value_type'   => $goal['value_type'],
                    'fixed_value'  => $goal['fixed_value'],
                    'is_active'    => 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                )
            );
            $goal_ids[$goal['name']] = (int) $this->wpdb->insert_id;
        }

        return $goal_ids;
    }

    /**
     * Create visitors distributed across the date range.
     *
     * @return array Array of [ visitor_id => created_at_timestamp ].
     */
    private function seed_visitors()
    {
        $visitor_ids = array();
        $now_ts      = time();
        $start_ts    = $now_ts - ($this->days * DAY_IN_SECONDS);
        $batch       = array();

        for ($i = 0; $i < $this->visitor_count; $i++) {
            // Distribute creation dates across the range with weekday bias.
            $ts = $this->random_timestamp_weighted($start_ts, $now_ts);
            $dt = gmdate('Y-m-d H:i:s', $ts);

            $batch[] = array(
                'client_id'  => $this->uuid(),
                'created_at' => $dt,
                'updated_at' => $dt,
            );

            // Flush batch every $batch_size rows.
            if (count($batch) >= $this->batch_size) {
                $this->batch_insert_visitors($batch, $visitor_ids);
                $batch = array();
            }
        }

        // Flush remaining.
        if (! empty($batch)) {
            $this->batch_insert_visitors($batch, $visitor_ids);
        }

        return $visitor_ids;
    }

    /**
     * Batch insert visitors and collect their IDs.
     *
     * @param array $batch   Rows to insert.
     * @param array $visitor_ids Reference to collect inserted IDs.
     * @return void
     */
    private function batch_insert_visitors(array $batch, array &$visitor_ids)
    {
        $table        = $this->wpdb->prefix . 'tracksure_visitors';
        $placeholders = array();
        $values       = array();

        foreach ($batch as $row) {
            $placeholders[] = '(%s, %s, %s)';
            $values[]       = $row['client_id'];
            $values[]       = $row['created_at'];
            $values[]       = $row['updated_at'];
        }

        $sql = "INSERT INTO {$table} (client_id, created_at, updated_at) VALUES "
            . implode(', ', $placeholders);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query($this->wpdb->prepare($sql, $values));

        // Retrieve the auto-increment IDs.
        $first_id = $this->wpdb->insert_id;
        foreach ($batch as $idx => $row) {
            $visitor_ids[$first_id + $idx] = strtotime($row['created_at']);
        }
    }

    /**
     * Generate sessions, events, conversions, and touchpoints for all visitors.
     *
     * @param array $visitor_ids  Array of [ visitor_id => created_at_ts ].
     * @param array $goal_ids     Array of [ goal_name => goal_id ].
     * @param bool  $verbose      Output progress.
     * @return array Stats: sessions, events, conversions, touchpoints counts.
     */
    private function seed_sessions_and_events(array $visitor_ids, array $goal_ids, $verbose = false)
    {
        $now_ts = time();
        $stats  = array(
            'sessions'    => 0,
            'events'      => 0,
            'conversions' => 0,
            'touchpoints' => 0,
        );

        $session_batch     = array();
        $event_batch       = array();
        $conversion_batch  = array();
        $touchpoint_batch  = array();

        $visitor_count = count($visitor_ids);
        $processed     = 0;

        foreach ($visitor_ids as $visitor_id => $created_ts) {
            // How many sessions for this visitor.
            $session_count = $this->random_session_count();

            for ($sn = 1; $sn <= $session_count; $sn++) {
                // Session starts sometime after visitor creation and before now.
                $session_start_ts = $this->random_timestamp_weighted(
                    $created_ts + (($sn - 1) * 3600), // Spread sessions apart.
                    min($now_ts, $created_ts + ($sn * $this->days * DAY_IN_SECONDS / $session_count))
                );

                // Clamp to now.
                if ($session_start_ts > $now_ts) {
                    $session_start_ts = $now_ts - wp_rand(60, 7200);
                }

                $session_id   = $this->uuid();
                $source_data  = $this->weighted_pick($this->sources);
                $device_data  = $this->weighted_pick($this->devices);
                $browser_data = $this->weighted_pick($this->browsers);
                $os_data      = $this->weighted_pick($this->os_list);
                $country_data = $this->weighted_pick($this->countries);
                $city         = $country_data['cities'][array_rand($country_data['cities'])];
                $is_returning = ($sn > 1) ? 1 : 0;

                // Pick a landing page.
                $landing = $this->weighted_pick($this->pages);

                // Generate events for this session.
                $event_count = $this->random_event_count();
                $events      = $this->generate_session_events(
                    $visitor_id,
                    $session_id,
                    $session_start_ts,
                    $event_count,
                    $landing,
                    $device_data,
                    $browser_data,
                    $os_data,
                    $country_data,
                    $city
                );

                // Duration based on events.
                $last_event_ts = $session_start_ts;
                foreach ($events as $ev) {
                    $ev_ts = strtotime($ev['occurred_at']);
                    if ($ev_ts > $last_event_ts) {
                        $last_event_ts = $ev_ts;
                    }
                }

                // Build session row.
                $session_row = array(
                    'session_id'       => $session_id,
                    'visitor_id'       => $visitor_id,
                    'session_number'   => $sn,
                    'is_returning'     => $is_returning,
                    'started_at'       => gmdate('Y-m-d H:i:s', $session_start_ts),
                    'last_activity_at' => gmdate('Y-m-d H:i:s', $last_event_ts),
                    'referrer'         => $this->generate_referrer($source_data),
                    'landing_page'     => $this->site_url . $landing['url'],
                    'utm_source'       => $source_data['source'] !== '(direct)' ? $source_data['source'] : null,
                    'utm_medium'       => $source_data['medium'] !== '(none)' ? $source_data['medium'] : null,
                    'utm_campaign'     => $source_data['campaign'] ?? null,
                    'utm_term'         => null,
                    'utm_content'      => null,
                    'gclid'            => $source_data['medium'] === 'cpc' && $source_data['source'] === 'google' ? 'gclid_demo_' . substr(md5($session_id), 0, 12) : null,
                    'fbclid'           => $source_data['source'] === 'facebook' && $source_data['medium'] === 'paid' ? 'fbclid_demo_' . substr(md5($session_id), 0, 12) : null,
                    'device_type'      => $device_data['type'],
                    'browser'          => $browser_data['name'],
                    'os'               => $os_data['name'],
                    'country'          => $country_data['code'],
                    'region'           => null,
                    'city'             => $city,
                    'event_count'      => count($events),
                    'created_at'       => gmdate('Y-m-d H:i:s', $session_start_ts),
                    'updated_at'       => gmdate('Y-m-d H:i:s', $last_event_ts),
                );

                $session_batch[] = $session_row;
                $event_batch     = array_merge($event_batch, $events);

                // Decide if this session converts (~8% of sessions).
                $converts = wp_rand(1, 100) <= 8;
                if ($converts && ! empty($events)) {
                    $conv_data = $this->generate_conversion(
                        $visitor_id,
                        $session_id,
                        $events,
                        $goal_ids,
                        $source_data,
                        $sn,
                        $session_start_ts,
                        $created_ts
                    );
                    if ($conv_data) {
                        $conversion_batch[] = $conv_data['conversion'];
                        // Mark the event as conversion.
                        foreach ($event_batch as &$eb) {
                            if ($eb['event_id'] === $conv_data['event_id']) {
                                $eb['is_conversion']    = 1;
                                $eb['conversion_value'] = $conv_data['conversion']['conversion_value'];
                                break;
                            }
                        }
                        unset($eb);
                    }
                }

                // Generate touchpoint for first session event.
                $touchpoint_batch[] = array(
                    'visitor_id'               => $visitor_id,
                    'session_id'               => $session_id,
                    'event_id'                 => $events[0]['event_id'] ?? null,
                    'touchpoint_seq'           => $sn,
                    'touched_at'               => gmdate('Y-m-d H:i:s', $session_start_ts),
                    'utm_source'               => $session_row['utm_source'],
                    'utm_medium'               => $session_row['utm_medium'],
                    'utm_campaign'             => $session_row['utm_campaign'],
                    'channel'                  => $source_data['channel'],
                    'page_url'                 => $session_row['landing_page'],
                    'page_title'               => $landing['title'],
                    'page_path'                => $landing['path'],
                    'referrer'                 => $session_row['referrer'],
                    'is_conversion_touchpoint' => $converts ? 1 : 0,
                    'attribution_weight'       => 1.0000,
                    'created_at'               => gmdate('Y-m-d H:i:s', $session_start_ts),
                );

                ++$stats['sessions'];
            }

            ++$processed;

            // Flush batches periodically.
            if (count($session_batch) >= $this->batch_size) {
                $stats['events']      += count($event_batch);
                $stats['conversions'] += count($conversion_batch);
                $stats['touchpoints'] += count($touchpoint_batch);

                $this->flush_sessions($session_batch);
                $this->flush_events($event_batch);
                $this->flush_conversions($conversion_batch);
                $this->flush_touchpoints($touchpoint_batch);

                $session_batch    = array();
                $event_batch      = array();
                $conversion_batch = array();
                $touchpoint_batch = array();

                if ($verbose && $processed % 500 === 0) {
                    $this->log($verbose, sprintf('  Progress: %d / %d visitors...', $processed, $visitor_count));
                }
            }
        }

        // Flush remaining.
        if (! empty($session_batch)) {
            $stats['events']      += count($event_batch);
            $stats['conversions'] += count($conversion_batch);
            $stats['touchpoints'] += count($touchpoint_batch);

            $this->flush_sessions($session_batch);
            $this->flush_events($event_batch);
            $this->flush_conversions($conversion_batch);
            $this->flush_touchpoints($touchpoint_batch);
        }

        // Link touchpoints to their conversions via visitor_id + session_id.
        // conversion_id is auto-increment, so it's only available after flush.
        $this->link_touchpoint_conversions();

        return $stats;
    }

    /**
     * Link touchpoints to their conversions.
     *
     * After all data is flushed, conversion_id auto-increment values exist.
     * This UPDATE links each conversion touchpoint to its conversion
     * by matching on visitor_id + session_id.
     *
     * @return void
     */
    private function link_touchpoint_conversions()
    {
        $tp_table   = $this->wpdb->prefix . 'tracksure_touchpoints';
        $conv_table = $this->wpdb->prefix . 'tracksure_conversions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $this->wpdb->query(
            "UPDATE {$tp_table} t
            INNER JOIN {$conv_table} c
                ON t.visitor_id = c.visitor_id AND t.session_id = c.session_id
            SET t.conversion_id = c.conversion_id
            WHERE t.is_conversion_touchpoint = 1
            AND t.conversion_id IS NULL"
        );
    }

    /**
     * Calculate and seed attribution data for all 5 models.
     *
     * For each conversion, fetches its touchpoints (via visitor_id join)
     * and calculates first_touch, last_touch, linear, time_decay, and
     * position_based attribution credits.
     *
     * @return int Number of attribution records inserted.
     */
    private function seed_conversion_attribution()
    {
        $tp_table   = $this->wpdb->prefix . 'tracksure_touchpoints';
        $conv_table = $this->wpdb->prefix . 'tracksure_conversions';

        // Get all conversions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $conversions = $this->wpdb->get_results(
            "SELECT conversion_id, visitor_id, conversion_value, converted_at
            FROM {$conv_table}
            ORDER BY conversion_id ASC",
            ARRAY_A
        );

        if (empty($conversions)) {
            return 0;
        }

        $batch       = array();
        $total_count = 0;

        foreach ($conversions as $conv) {
            $conv_id    = (int) $conv['conversion_id'];
            $visitor_id = (int) $conv['visitor_id'];
            $conv_value = (float) $conv['conversion_value'];

            // Get touchpoints for this conversion.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $touchpoints = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT touchpoint_id, utm_source, utm_medium, utm_campaign, channel,
                            touched_at, touchpoint_seq
                    FROM {$tp_table}
                    WHERE visitor_id = %d AND touched_at <= %s
                    ORDER BY touchpoint_seq ASC",
                    $visitor_id,
                    $conv['converted_at']
                ),
                ARRAY_A
            );

            if (empty($touchpoints)) {
                continue;
            }

            $count   = count($touchpoints);
            $credits = array();

            // --- First Touch: 100% to first touchpoint ---
            $credits[] = $this->build_attribution_row(
                $conv_id,
                $touchpoints[0],
                'first_touch',
                $conv_value,
                100.00,
                1.0000,
                1
            );

            // --- Last Touch: 100% to last touchpoint ---
            $credits[] = $this->build_attribution_row(
                $conv_id,
                $touchpoints[$count - 1],
                'last_touch',
                $conv_value,
                100.00,
                1.0000,
                $count
            );

            // --- Linear: equal credit to all touchpoints ---
            $lin_weight  = 1.0 / $count;
            $lin_percent = 100.00 / $count;
            $lin_value   = $conv_value / $count;
            foreach ($touchpoints as $i => $tp) {
                $credits[] = $this->build_attribution_row(
                    $conv_id,
                    $tp,
                    'linear',
                    $lin_value,
                    $lin_percent,
                    $lin_weight,
                    $i + 1
                );
            }

            // --- Time Decay: half-life 7 days ---
            $td_weights = $this->calc_time_decay_weights($touchpoints);
            foreach ($touchpoints as $i => $tp) {
                $credits[] = $this->build_attribution_row(
                    $conv_id,
                    $tp,
                    'time_decay',
                    $conv_value * $td_weights[$i],
                    $td_weights[$i] * 100,
                    $td_weights[$i],
                    $i + 1
                );
            }

            // --- Position Based: 40/20/40 ---
            $pb_weights = $this->calc_position_based_weights($count);
            foreach ($touchpoints as $i => $tp) {
                $credits[] = $this->build_attribution_row(
                    $conv_id,
                    $tp,
                    'position_based',
                    $conv_value * $pb_weights[$i],
                    $pb_weights[$i] * 100,
                    $pb_weights[$i],
                    $i + 1
                );
            }

            $batch       = array_merge($batch, $credits);
            $total_count += count($credits);

            // Flush in batches of 500.
            if (count($batch) >= 500) {
                $this->flush_attribution_batch($batch);
                $batch = array();
            }
        }

        // Flush remaining.
        if (! empty($batch)) {
            $this->flush_attribution_batch($batch);
        }

        return $total_count;
    }

    /**
     * Build a single attribution row array.
     *
     * @param int    $conversion_id Conversion ID.
     * @param array  $touchpoint    Touchpoint data.
     * @param string $model         Attribution model name.
     * @param float  $credit_value  Credit value.
     * @param float  $credit_pct    Credit percentage.
     * @param float  $weight        Attribution weight.
     * @param int    $order         Touchpoint order.
     * @return array Row data.
     */
    private function build_attribution_row($conversion_id, $touchpoint, $model, $credit_value, $credit_pct, $weight, $order)
    {
        return array(
            'conversion_id'      => $conversion_id,
            'touchpoint_id'      => (int) $touchpoint['touchpoint_id'],
            'attribution_model'  => $model,
            'credit_value'       => round($credit_value, 2),
            'credit_percent'     => round($credit_pct, 2),
            'attribution_weight' => round($weight, 4),
            'utm_source'         => $touchpoint['utm_source'],
            'utm_medium'         => $touchpoint['utm_medium'],
            'utm_campaign'       => $touchpoint['utm_campaign'],
            'channel'            => $touchpoint['channel'],
            'touchpoint_order'   => $order,
            'created_at'         => gmdate('Y-m-d H:i:s'),
        );
    }

    /**
     * Calculate time-decay weights (half-life 7 days).
     *
     * @param array $touchpoints Touchpoints with touched_at.
     * @return array Normalized weights summing to 1.0.
     */
    private function calc_time_decay_weights(array $touchpoints)
    {
        $count = count($touchpoints);
        if ($count === 1) {
            return array(1.0);
        }

        $last_ts      = strtotime($touchpoints[$count - 1]['touched_at']);
        $weights      = array();
        $total_weight = 0;

        foreach ($touchpoints as $tp) {
            $days_before   = ($last_ts - strtotime($tp['touched_at'])) / 86400;
            $w             = pow(0.5, $days_before / 7);
            $weights[]     = $w;
            $total_weight += $w;
        }

        return array_map(
            function ($w) use ($total_weight) {
                return $w / $total_weight;
            },
            $weights
        );
    }

    /**
     * Calculate position-based weights (40/20/40).
     *
     * @param int $count Number of touchpoints.
     * @return array Weights summing to 1.0.
     */
    private function calc_position_based_weights($count)
    {
        if ($count === 1) {
            return array(1.0);
        }
        if ($count === 2) {
            return array(0.5, 0.5);
        }

        $weights       = array();
        $middle_weight = 0.20 / ($count - 2);

        for ($i = 0; $i < $count; $i++) {
            if ($i === 0) {
                $weights[] = 0.40;
            } elseif ($i === $count - 1) {
                $weights[] = 0.40;
            } else {
                $weights[] = $middle_weight;
            }
        }

        return $weights;
    }

    /**
     * Batch INSERT attribution rows.
     *
     * @param array $batch Array of attribution row arrays.
     * @return void
     */
    private function flush_attribution_batch(array $batch)
    {
        if (empty($batch)) {
            return;
        }

        $table = $this->wpdb->prefix . 'tracksure_conversion_attribution';
        $cols  = 'conversion_id, touchpoint_id, attribution_model, credit_value, credit_percent, '
            . 'attribution_weight, utm_source, utm_medium, utm_campaign, channel, touchpoint_order, created_at';
        $ph    = '(%d, %d, %s, %f, %f, %f, %s, %s, %s, %s, %d, %s)';

        $values = array();
        foreach ($batch as $row) {
            $values[] = $row['conversion_id'];
            $values[] = $row['touchpoint_id'];
            $values[] = $row['attribution_model'];
            $values[] = $row['credit_value'];
            $values[] = $row['credit_percent'];
            $values[] = $row['attribution_weight'];
            $values[] = $row['utm_source'];
            $values[] = $row['utm_medium'];
            $values[] = $row['utm_campaign'];
            $values[] = $row['channel'];
            $values[] = $row['touchpoint_order'];
            $values[] = $row['created_at'];
        }

        $phs = implode(', ', array_fill(0, count($batch), $ph));
        $sql = "INSERT INTO {$table} ({$cols}) VALUES {$phs}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query($this->wpdb->prepare($sql, $values));
    }

    /**
     * Aggregate product analytics from seeded events into agg_product_daily.
     *
     * Reads view_item/add_to_cart/begin_checkout events (from event_params.item_id)
     * and purchase events (from ecommerce_data.items array) to build per-product
     * daily aggregation rows, exactly matching the Hourly Aggregator logic.
     *
     * @return int Number of aggregation rows inserted.
     */
    private function seed_product_aggregations()
    {
        $events_table  = $this->wpdb->prefix . 'tracksure_events';
        $session_table = $this->wpdb->prefix . 'tracksure_sessions';
        $agg_table     = $this->wpdb->prefix . 'tracksure_agg_product_daily';

        // Step 1: Aggregate views, add_to_carts, checkouts from event_params.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $view_cart_events = $this->wpdb->get_results(
            "SELECT
                DATE(e.occurred_at) as event_date,
                e.event_name,
                e.event_params,
                COALESCE(s.utm_source, '') as utm_source,
                COALESCE(s.utm_medium, '') as utm_medium
            FROM {$events_table} e
            LEFT JOIN {$session_table} s ON e.session_id = s.session_id
            WHERE e.event_name IN ('view_item', 'add_to_cart', 'begin_checkout')
            AND e.event_params IS NOT NULL
            AND e.event_params != 'null'",
            ARRAY_A
        );

        // Build aggregation map: key = date|product_id|utm_source|utm_medium.
        $agg = array();

        if ($view_cart_events) {
            foreach ($view_cart_events as $event) {
                $params = json_decode($event['event_params'], true);
                if (! $params) {
                    continue;
                }

                $item_id = $params['item_id'] ?? ($params['product_id'] ?? '');
                if (! $item_id) {
                    // For begin_checkout, items may be in an array.
                    if (! empty($params['items'])) {
                        foreach ($params['items'] as $item) {
                            $iid = $item['item_id'] ?? '';
                            if (! $iid) {
                                continue;
                            }
                            $key = $event['event_date'] . '|' . $iid . '|' . $event['utm_source'] . '|' . $event['utm_medium'];
                            if (! isset($agg[$key])) {
                                $agg[$key] = array(
                                    'date'             => $event['event_date'],
                                    'product_id'       => $iid,
                                    'product_name'     => $item['item_name'] ?? '',
                                    'product_category' => $item['item_category'] ?? '',
                                    'utm_source'       => $event['utm_source'],
                                    'utm_medium'       => $event['utm_medium'],
                                    'views'            => 0,
                                    'add_to_carts'     => 0,
                                    'checkouts'        => 0,
                                    'purchases'        => 0,
                                    'items_sold'       => 0,
                                    'revenue'          => 0,
                                );
                            }
                            if ('begin_checkout' === $event['event_name']) {
                                ++$agg[$key]['checkouts'];
                            }
                        }
                        continue;
                    }
                    continue;
                }

                $key = $event['event_date'] . '|' . $item_id . '|' . $event['utm_source'] . '|' . $event['utm_medium'];
                if (! isset($agg[$key])) {
                    $agg[$key] = array(
                        'date'             => $event['event_date'],
                        'product_id'       => $item_id,
                        'product_name'     => $params['item_name'] ?? '',
                        'product_category' => $params['item_category'] ?? '',
                        'utm_source'       => $event['utm_source'],
                        'utm_medium'       => $event['utm_medium'],
                        'views'            => 0,
                        'add_to_carts'     => 0,
                        'checkouts'        => 0,
                        'purchases'        => 0,
                        'items_sold'       => 0,
                        'revenue'          => 0,
                    );
                }

                switch ($event['event_name']) {
                    case 'view_item':
                        ++$agg[$key]['views'];
                        break;
                    case 'add_to_cart':
                        ++$agg[$key]['add_to_carts'];
                        break;
                    case 'begin_checkout':
                        ++$agg[$key]['checkouts'];
                        break;
                }
            }
        }

        // Step 2: Aggregate purchases from ecommerce_data.items[].
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $purchase_events = $this->wpdb->get_results(
            "SELECT
                DATE(e.occurred_at) as event_date,
                e.ecommerce_data,
                COALESCE(s.utm_source, '') as utm_source,
                COALESCE(s.utm_medium, '') as utm_medium
            FROM {$events_table} e
            LEFT JOIN {$session_table} s ON e.session_id = s.session_id
            WHERE e.event_name = 'purchase'
            AND e.ecommerce_data IS NOT NULL",
            ARRAY_A
        );

        if ($purchase_events) {
            foreach ($purchase_events as $event) {
                $ecom = json_decode($event['ecommerce_data'], true);
                if (! $ecom || empty($ecom['items'])) {
                    continue;
                }
                foreach ($ecom['items'] as $item) {
                    $item_id = $item['item_id'] ?? '';
                    if (! $item_id) {
                        continue;
                    }
                    $quantity = intval($item['quantity'] ?? 1);
                    $revenue  = floatval($item['price'] ?? 0) * $quantity;

                    $key = $event['event_date'] . '|' . $item_id . '|' . $event['utm_source'] . '|' . $event['utm_medium'];
                    if (! isset($agg[$key])) {
                        $agg[$key] = array(
                            'date'             => $event['event_date'],
                            'product_id'       => $item_id,
                            'product_name'     => $item['item_name'] ?? '',
                            'product_category' => $item['item_category'] ?? '',
                            'utm_source'       => $event['utm_source'],
                            'utm_medium'       => $event['utm_medium'],
                            'views'            => 0,
                            'add_to_carts'     => 0,
                            'checkouts'        => 0,
                            'purchases'        => 0,
                            'items_sold'       => 0,
                            'revenue'          => 0,
                        );
                    }
                    ++$agg[$key]['purchases'];
                    $agg[$key]['items_sold'] += $quantity;
                    $agg[$key]['revenue']    += $revenue;
                }
            }
        }

        // Step 3: Insert aggregation rows.
        if (empty($agg)) {
            return 0;
        }

        $rows_inserted = 0;
        $chunks        = array_chunk(array_values($agg), 100);
        foreach ($chunks as $chunk) {
            $values = array();
            $phs    = array();
            foreach ($chunk as $row) {
                $conv_rate = $row['views'] > 0 ? ($row['purchases'] / $row['views']) * 100 : 0;
                $phs[]     = '(%s, %s, %s, %s, %s, %s, %d, %d, %d, %d, %d, %f, %f, NOW(), NOW())';
                $values[]  = $row['date'];
                $values[]  = $row['product_id'];
                $values[]  = $row['product_name'];
                $values[]  = $row['product_category'];
                $values[]  = $row['utm_source'];
                $values[]  = $row['utm_medium'];
                $values[]  = $row['views'];
                $values[]  = $row['add_to_carts'];
                $values[]  = $row['checkouts'];
                $values[]  = $row['purchases'];
                $values[]  = $row['items_sold'];
                $values[]  = $row['revenue'];
                $values[]  = $conv_rate;
            }

            $phs_str = implode(', ', $phs);
            $sql     = "INSERT INTO {$agg_table}
                (date, product_id, product_name, product_category, utm_source, utm_medium,
                 views, add_to_carts, checkouts, purchases, items_sold, revenue, conversion_rate,
                 created_at, updated_at)
                VALUES {$phs_str}
                ON DUPLICATE KEY UPDATE
                    views = VALUES(views),
                    add_to_carts = VALUES(add_to_carts),
                    checkouts = VALUES(checkouts),
                    purchases = VALUES(purchases),
                    items_sold = VALUES(items_sold),
                    revenue = VALUES(revenue),
                    conversion_rate = VALUES(conversion_rate),
                    updated_at = NOW()";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
            $this->wpdb->query($this->wpdb->prepare($sql, $values));
            $rows_inserted += count($chunk);
        }

        return $rows_inserted;
    }

    /**
     * Generate events for a single session.
     *
     * @param int    $visitor_id      Visitor ID.
     * @param string $session_id      Session UUID.
     * @param int    $start_ts        Session start timestamp.
     * @param int    $event_count     How many events to generate.
     * @param array  $landing_page    Landing page data.
     * @param array  $device_data     Device info.
     * @param array  $browser_data    Browser info.
     * @param array  $os_data         OS info.
     * @param array  $country_data    Country info.
     * @param string $city            City name.
     * @return array Array of event rows.
     */
    private function generate_session_events(
        $visitor_id,
        $session_id,
        $start_ts,
        $event_count,
        $landing_page,
        $device_data,
        $browser_data,
        $os_data,
        $country_data,
        $city
    ) {
        $events     = array();
        $current_ts = $start_ts;

        // First event is always a page_view of the landing page.
        $events[] = $this->make_event(
            $visitor_id,
            $session_id,
            'page_view',
            $landing_page,
            $current_ts,
            $device_data,
            $browser_data,
            $os_data,
            $country_data,
            $city
        );

        // If the landing page is a product page, auto-fire a view_item immediately.
        if (! empty($landing_page['is_product'])) {
            $current_ts += wp_rand(1, 5);
            $events[] = $this->make_event(
                $visitor_id,
                $session_id,
                'view_item',
                $landing_page,
                $current_ts,
                $device_data,
                $browser_data,
                $os_data,
                $country_data,
                $city
            );
        }

        $current_page = $landing_page;

        for ($e = count($events); $e < $event_count; $e++) {
            // Advance time by 3–90 seconds.
            $current_ts += wp_rand(3, 90);

            // Pick event type.
            $event_type = $this->random_event_type();

            if ('page_view' === $event_type) {
                // Navigate to a different page.
                $current_page = $this->weighted_pick($this->pages);

                // If navigated to a product page, auto-fire a view_item next.
                if (! empty($current_page['is_product']) && $e + 1 < $event_count) {
                    $events[] = $this->make_event(
                        $visitor_id,
                        $session_id,
                        'page_view',
                        $current_page,
                        $current_ts,
                        $device_data,
                        $browser_data,
                        $os_data,
                        $country_data,
                        $city
                    );
                    ++$e;
                    $current_ts += wp_rand(2, 8);
                    $event_type = 'view_item'; // Follow up with product detail view.
                }
            }

            $events[] = $this->make_event(
                $visitor_id,
                $session_id,
                $event_type,
                $current_page,
                $current_ts,
                $device_data,
                $browser_data,
                $os_data,
                $country_data,
                $city
            );
        }

        return $events;
    }

    /**
     * Build a single event row array.
     *
     * @param int    $visitor_id   Visitor ID.
     * @param string $session_id   Session UUID.
     * @param string $event_name   Event name.
     * @param array  $page         Page data.
     * @param int    $timestamp    Event timestamp.
     * @param array  $device_data  Device info.
     * @param array  $browser_data Browser info.
     * @param array  $os_data      OS info.
     * @param array  $country_data Country info.
     * @param string $city         City name.
     * @return array Event row.
     */
    private function make_event(
        $visitor_id,
        $session_id,
        $event_name,
        $page,
        $timestamp,
        $device_data,
        $browser_data,
        $os_data,
        $country_data,
        $city
    ) {
        $dt = gmdate('Y-m-d H:i:s', $timestamp);

        $event_params   = 'null';
        $ecommerce_data = null;

        if ('scroll_depth' === $event_name) {
            $event_params = wp_json_encode(array('depth' => wp_rand(25, 100)));
        } elseif ('click' === $event_name) {
            $event_params = wp_json_encode(array('element' => $this->random_click_element()));
        } elseif ('form_submit' === $event_name) {
            $forms = array('newsletter', 'contact', 'demo-request', 'signup');
            $event_params = wp_json_encode(array('form_id' => $forms[array_rand($forms)]));
        } elseif ('view_item' === $event_name) {
            // Pick a product — prefer the page's product if on a product page.
            $product = $this->pick_product_for_page($page);
            $event_params = wp_json_encode(array(
                'item_id'       => $product['item_id'],
                'item_name'     => $product['item_name'],
                'item_category' => $product['item_category'],
                'price'         => $product['price'],
                'currency'      => 'USD',
            ));
        } elseif ('add_to_cart' === $event_name) {
            $product  = $this->pick_product_for_page($page);
            $quantity = wp_rand(1, 100) <= 80 ? 1 : wp_rand(2, 3);
            $event_params = wp_json_encode(array(
                'item_id'       => $product['item_id'],
                'item_name'     => $product['item_name'],
                'item_category' => $product['item_category'],
                'price'         => $product['price'],
                'quantity'      => $quantity,
                'value'         => $product['price'] * $quantity,
                'currency'      => 'USD',
            ));
        } elseif ('begin_checkout' === $event_name) {
            // 1-3 items in cart at checkout start.
            $item_count = wp_rand(1, 100) <= 70 ? 1 : wp_rand(2, 3);
            $items      = array();
            $total      = 0;
            for ($i = 0; $i < $item_count; $i++) {
                $product  = $this->weighted_pick($this->products);
                $quantity = 1;
                $items[]  = array(
                    'item_id'       => $product['item_id'],
                    'item_name'     => $product['item_name'],
                    'item_category' => $product['item_category'],
                    'price'         => $product['price'],
                    'quantity'      => $quantity,
                );
                $total += $product['price'] * $quantity;
            }
            $event_params = wp_json_encode(array(
                'items'    => $items,
                'value'    => $total,
                'currency' => 'USD',
            ));
        } elseif ('purchase' === $event_name) {
            // 1-3 items purchased.
            $item_count     = wp_rand(1, 100) <= 65 ? 1 : wp_rand(2, 3);
            $items          = array();
            $total          = 0;
            $total_quantity = 0;
            for ($i = 0; $i < $item_count; $i++) {
                $product  = $this->weighted_pick($this->products);
                $quantity = wp_rand(1, 100) <= 85 ? 1 : wp_rand(2, 3);
                $items[]  = array(
                    'item_id'       => $product['item_id'],
                    'item_name'     => $product['item_name'],
                    'item_category' => $product['item_category'],
                    'price'         => $product['price'],
                    'quantity'      => $quantity,
                );
                $total          += $product['price'] * $quantity;
                $total_quantity += $quantity;
            }
            $txn_id = 'TXN-' . strtoupper(substr(md5($this->uuid()), 0, 8));
            $event_params = wp_json_encode(array(
                'transaction_id' => $txn_id,
                'value'          => $total,
                'currency'       => 'USD',
                'items'          => $items,
            ));
            $ecommerce_data = wp_json_encode(array(
                'transaction_id' => $txn_id,
                'value'          => $total,
                'currency'       => 'USD',
                'items'          => $items,
            ));
        }

        return array(
            'event_id'         => $this->uuid(),
            'visitor_id'       => $visitor_id,
            'session_id'       => $session_id,
            'event_name'       => $event_name,
            'event_source'     => 'browser',
            'browser_fired'    => 1,
            'server_fired'     => 0,
            'browser_fired_at' => $dt,
            'event_params'     => $event_params,
            'ecommerce_data'   => $ecommerce_data,
            'occurred_at'      => $dt,
            'created_at'       => $dt,
            'page_url'         => $this->site_url . $page['url'],
            'page_path'        => $page['path'],
            'page_title'       => $page['title'],
            'referrer'         => null,
            'device_type'      => $device_data['type'],
            'browser'          => $browser_data['name'],
            'os'               => $os_data['name'],
            'country'          => $country_data['code'],
            'region'           => null,
            'city'             => $city,
            'is_conversion'    => 0,
            'conversion_value' => null,
            'consent_granted'  => 1,
        );
    }

    /**
     * Pick a product contextually — use the page's linked product if available, else random.
     *
     * @param array $page Current page data.
     * @return array Product data from $this->products.
     */
    private function pick_product_for_page($page)
    {
        if (! empty($page['product_id'])) {
            foreach ($this->products as $product) {
                if ($product['item_id'] === $page['product_id']) {
                    return $product;
                }
            }
        }
        return $this->weighted_pick($this->products);
    }

    /**
     * Generate a conversion record for a converting session.
     *
     * @param int    $visitor_id         Visitor ID.
     * @param string $session_id         Session UUID.
     * @param array  $events             Session events.
     * @param array  $goal_ids           Goal ID map.
     * @param array  $source_data        Traffic source data.
     * @param int    $session_num        Session number.
     * @param int    $session_ts         Session start timestamp.
     * @param int    $visitor_created_ts  Visitor's first-visit timestamp.
     * @return array|null Conversion data or null.
     */
    private function generate_conversion($visitor_id, $session_id, $events, $goal_ids, $source_data, $session_num, $session_ts, $visitor_created_ts)
    {
        // Pick a goal to convert on (weighted towards purchase and signup).
        $goal_weights = array(
            'Completed Purchase'  => 30,
            'Newsletter Signup'   => 35,
            'Demo Request'        => 20,
            'Pricing Page Visit'  => 15,
        );

        $goal_name = $this->weighted_pick_simple($goal_weights);
        $goal_id   = $goal_ids[$goal_name] ?? null;
        if (! $goal_id) {
            return null;
        }

        // Pick the last event as the conversion event.
        $conv_event = end($events);

        // Conversion value.
        $value = 0;
        $type  = 'goal_completion';
        switch ($goal_name) {
            case 'Completed Purchase':
                $value = $this->random_purchase_value();
                $type  = 'purchase';
                break;
            case 'Newsletter Signup':
                $value = 5.00;
                $type  = 'lead';
                break;
            case 'Demo Request':
                $value = 25.00;
                $type  = 'lead';
                break;
            case 'Pricing Page Visit':
                $value = 2.00;
                $type  = 'pageview_goal';
                break;
        }

        $conv_ts = $conv_event['occurred_at'];
        // Time to convert = time from visitor's first visit to conversion moment.
        // This matches the production ConversionRecorder which uses the first touchpoint timestamp.
        $ttc     = max(0, strtotime($conv_ts) - $visitor_created_ts);

        $src = $source_data['source'] !== '(direct)' ? $source_data['source'] : null;
        $med = $source_data['medium'] !== '(none)' ? $source_data['medium'] : null;

        return array(
            'event_id'   => $conv_event['event_id'],
            'conversion' => array(
                'visitor_id'           => $visitor_id,
                'session_id'           => $session_id,
                'event_id'             => $conv_event['event_id'],
                'conversion_type'      => $type,
                'goal_id'              => $goal_id,
                'conversion_value'     => $value,
                'currency'             => 'USD',
                'transaction_id'       => 'purchase' === $type ? 'TXN-' . strtoupper(substr(md5($this->uuid()), 0, 8)) : null,
                'items_count'          => 'purchase' === $type ? wp_rand(1, 3) : 0,
                'converted_at'         => $conv_ts,
                'time_to_convert'      => $ttc,
                'sessions_to_convert'  => $session_num,
                'first_touch_source'   => $src,
                'first_touch_medium'   => $med,
                'first_touch_campaign' => $source_data['campaign'] ?? null,
                'last_touch_source'    => $src,
                'last_touch_medium'    => $med,
                'last_touch_campaign'  => $source_data['campaign'] ?? null,
                'created_at'           => $conv_ts,
            ),
        );
    }

	// ============================================================
	// BATCH FLUSH HELPERS
	// ============================================================

    /**
     * Flush sessions batch to DB.
     *
     * @param array $batch Session rows.
     * @return void
     */
    private function flush_sessions(array $batch)
    {
        if (empty($batch)) {
            return;
        }

        $table  = $this->wpdb->prefix . 'tracksure_sessions';
        $cols   = 'session_id, visitor_id, session_number, is_returning, started_at, last_activity_at, '
            . 'referrer, landing_page, utm_source, utm_medium, utm_campaign, utm_term, utm_content, '
            . 'gclid, fbclid, device_type, browser, os, country, region, city, event_count, created_at, updated_at';
        $ph     = '(%s, %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s)';
        $values = array();

        foreach ($batch as $row) {
            $values[] = $row['session_id'];
            $values[] = $row['visitor_id'];
            $values[] = $row['session_number'];
            $values[] = $row['is_returning'];
            $values[] = $row['started_at'];
            $values[] = $row['last_activity_at'];
            $values[] = $row['referrer'];
            $values[] = $row['landing_page'];
            $values[] = $row['utm_source'];
            $values[] = $row['utm_medium'];
            $values[] = $row['utm_campaign'];
            $values[] = $row['utm_term'];
            $values[] = $row['utm_content'];
            $values[] = $row['gclid'];
            $values[] = $row['fbclid'];
            $values[] = $row['device_type'];
            $values[] = $row['browser'];
            $values[] = $row['os'];
            $values[] = $row['country'];
            $values[] = $row['region'];
            $values[] = $row['city'];
            $values[] = $row['event_count'];
            $values[] = $row['created_at'];
            $values[] = $row['updated_at'];
        }

        $phs = implode(', ', array_fill(0, count($batch), $ph));
        $sql = "INSERT INTO {$table} ({$cols}) VALUES {$phs}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query($this->wpdb->prepare($sql, $values));
    }

    /**
     * Flush events batch to DB.
     *
     * @param array $batch Event rows.
     * @return void
     */
    private function flush_events(array $batch)
    {
        if (empty($batch)) {
            return;
        }

        $table = $this->wpdb->prefix . 'tracksure_events';
        $cols  = 'event_id, visitor_id, session_id, event_name, event_source, browser_fired, server_fired, '
            . 'browser_fired_at, event_params, ecommerce_data, occurred_at, created_at, page_url, page_path, page_title, '
            . 'referrer, device_type, browser, os, country, region, city, is_conversion, conversion_value, consent_granted';

        // Insert in sub-batches to avoid max_allowed_packet limits.
        $chunks = array_chunk($batch, 100);
        foreach ($chunks as $chunk) {
            $values  = array();
            $row_phs = array();
            foreach ($chunk as $row) {
                // Build per-row placeholder: use NULL literal for null ecommerce_data (JSON column).
                $has_ecom = ! empty($row['ecommerce_data']);
                $ecom_ph  = $has_ecom ? '%s' : 'NULL';
                $row_phs[] = '(%s, %d, %s, %s, %s, %d, %d, %s, %s, ' . $ecom_ph . ', %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %f, %d)';

                $values[] = $row['event_id'];
                $values[] = $row['visitor_id'];
                $values[] = $row['session_id'];
                $values[] = $row['event_name'];
                $values[] = $row['event_source'];
                $values[] = $row['browser_fired'];
                $values[] = $row['server_fired'];
                $values[] = $row['browser_fired_at'];
                $values[] = $row['event_params'];
                if ($has_ecom) {
                    $values[] = $row['ecommerce_data'];
                }
                $values[] = $row['occurred_at'];
                $values[] = $row['created_at'];
                $values[] = $row['page_url'];
                $values[] = $row['page_path'];
                $values[] = $row['page_title'];
                $values[] = $row['referrer'];
                $values[] = $row['device_type'];
                $values[] = $row['browser'];
                $values[] = $row['os'];
                $values[] = $row['country'];
                $values[] = $row['region'];
                $values[] = $row['city'];
                $values[] = $row['is_conversion'];
                $values[] = $row['conversion_value'] ?? 0;
                $values[] = $row['consent_granted'];
            }

            $phs = implode(', ', $row_phs);
            $sql = "INSERT INTO {$table} ({$cols}) VALUES {$phs}";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
            $this->wpdb->query($this->wpdb->prepare($sql, $values));
        }
    }

    /**
     * Flush conversions batch to DB.
     *
     * @param array $batch Conversion rows.
     * @return void
     */
    private function flush_conversions(array $batch)
    {
        if (empty($batch)) {
            return;
        }

        $table = $this->wpdb->prefix . 'tracksure_conversions';
        $cols  = 'visitor_id, session_id, event_id, conversion_type, goal_id, conversion_value, currency, '
            . 'transaction_id, items_count, converted_at, time_to_convert, sessions_to_convert, '
            . 'first_touch_source, first_touch_medium, first_touch_campaign, '
            . 'last_touch_source, last_touch_medium, last_touch_campaign, created_at';
        $ph = '(%d, %s, %s, %s, %d, %f, %s, %s, %d, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s)';

        $values = array();
        foreach ($batch as $row) {
            $values[] = $row['visitor_id'];
            $values[] = $row['session_id'];
            $values[] = $row['event_id'];
            $values[] = $row['conversion_type'];
            $values[] = $row['goal_id'];
            $values[] = $row['conversion_value'];
            $values[] = $row['currency'];
            $values[] = $row['transaction_id'];
            $values[] = $row['items_count'];
            $values[] = $row['converted_at'];
            $values[] = $row['time_to_convert'];
            $values[] = $row['sessions_to_convert'];
            $values[] = $row['first_touch_source'];
            $values[] = $row['first_touch_medium'];
            $values[] = $row['first_touch_campaign'];
            $values[] = $row['last_touch_source'];
            $values[] = $row['last_touch_medium'];
            $values[] = $row['last_touch_campaign'];
            $values[] = $row['created_at'];
        }

        $phs = implode(', ', array_fill(0, count($batch), $ph));
        $sql = "INSERT INTO {$table} ({$cols}) VALUES {$phs}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query($this->wpdb->prepare($sql, $values));
    }

    /**
     * Flush touchpoints batch to DB.
     *
     * @param array $batch Touchpoint rows.
     * @return void
     */
    private function flush_touchpoints(array $batch)
    {
        if (empty($batch)) {
            return;
        }

        $table = $this->wpdb->prefix . 'tracksure_touchpoints';
        $cols  = 'visitor_id, session_id, event_id, touchpoint_seq, touched_at, utm_source, utm_medium, '
            . 'utm_campaign, channel, page_url, page_title, page_path, referrer, '
            . 'is_conversion_touchpoint, attribution_weight, created_at';
        $ph = '(%d, %s, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %f, %s)';

        $values = array();
        foreach ($batch as $row) {
            $values[] = $row['visitor_id'];
            $values[] = $row['session_id'];
            $values[] = $row['event_id'];
            $values[] = $row['touchpoint_seq'];
            $values[] = $row['touched_at'];
            $values[] = $row['utm_source'];
            $values[] = $row['utm_medium'];
            $values[] = $row['utm_campaign'];
            $values[] = $row['channel'];
            $values[] = $row['page_url'];
            $values[] = $row['page_title'];
            $values[] = $row['page_path'];
            $values[] = $row['referrer'];
            $values[] = $row['is_conversion_touchpoint'];
            $values[] = $row['attribution_weight'];
            $values[] = $row['created_at'];
        }

        $phs = implode(', ', array_fill(0, count($batch), $ph));
        $sql = "INSERT INTO {$table} ({$cols}) VALUES {$phs}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query($this->wpdb->prepare($sql, $values));
    }

	// ============================================================
	// UTILITY / RANDOM HELPERS
	// ============================================================

    /**
     * Generate UUID v4.
     *
     * @return string UUID string.
     */
    private function uuid()
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Pick a random item from a weighted array.
     *
     * @param array $items Items with 'weight' key.
     * @return array The picked item.
     */
    private function weighted_pick(array $items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['weight'];
        }

        $rand    = wp_rand(1, $total);
        $running = 0;

        foreach ($items as $item) {
            $running += $item['weight'];
            if ($rand <= $running) {
                return $item;
            }
        }

        return end($items);
    }

    /**
     * Pick from a simple key => weight map.
     *
     * @param array $weights Key => weight map.
     * @return string The picked key.
     */
    private function weighted_pick_simple(array $weights)
    {
        $total   = array_sum($weights);
        $rand    = wp_rand(1, $total);
        $running = 0;

        foreach ($weights as $key => $weight) {
            $running += $weight;
            if ($rand <= $running) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * Generate a weighted random timestamp favouring weekdays and work hours.
     *
     * @param int $min_ts Minimum timestamp.
     * @param int $max_ts Maximum timestamp.
     * @return int Timestamp.
     */
    private function random_timestamp_weighted($min_ts, $max_ts)
    {
        if ($min_ts >= $max_ts) {
            return $max_ts;
        }

        // Try up to 10 times to get a weekday-biased result.
        for ($i = 0; $i < 10; $i++) {
            $ts  = wp_rand($min_ts, $max_ts);
            $dow = (int) gmdate('N', $ts); // 1=Mon, 7=Sun.

            // Accept weekdays always, weekends with 40% chance.
            if ($dow <= 5 || wp_rand(1, 100) <= 40) {
                // Bias toward work hours (8-20 UTC) — accept off-hours with 30% chance.
                $hour = (int) gmdate('G', $ts);
                if (($hour >= 8 && $hour <= 20) || wp_rand(1, 100) <= 30) {
                    return $ts;
                }
            }
        }

        // Fallback: just return a random timestamp.
        return wp_rand($min_ts, $max_ts);
    }

    /**
     * Random number of sessions per visitor.
     *
     * @return int Session count (1-5).
     */
    private function random_session_count()
    {
        $r = wp_rand(1, 100);
        if ($r <= 58) {
            return 1;
        }
        if ($r <= 80) {
            return 2;
        }
        if ($r <= 92) {
            return 3;
        }
        if ($r <= 97) {
            return 4;
        }
        return 5;
    }

    /**
     * Random number of events per session.
     *
     * @return int Event count (2-15).
     */
    private function random_event_count()
    {
        $r = wp_rand(1, 100);
        if ($r <= 20) {
            return wp_rand(1, 2);  // Bounce.
        }
        if ($r <= 60) {
            return wp_rand(3, 5);  // Light engagement.
        }
        if ($r <= 85) {
            return wp_rand(6, 9);  // Medium engagement.
        }
        return wp_rand(10, 15);     // Heavy engagement.
    }

    /**
     * Pick a random event type with realistic distribution.
     *
     * @return string Event name.
     */
    private function random_event_type()
    {
        $r = wp_rand(1, 100);
        if ($r <= 38) {
            return 'page_view';
        }
        if ($r <= 55) {
            return 'click';
        }
        if ($r <= 65) {
            return 'scroll_depth';
        }
        if ($r <= 72) {
            return 'view_item';        // Product detail view.
        }
        if ($r <= 78) {
            return 'form_submit';
        }
        if ($r <= 86) {
            return 'add_to_cart';
        }
        if ($r <= 90) {
            return 'begin_checkout';   // Started checkout flow.
        }
        if ($r <= 96) {
            return 'purchase';
        }
        return 'time_on_page';
    }

    /**
     * Random click element for click events.
     *
     * @return string CSS selector / element description.
     */
    private function random_click_element()
    {
        $elements = array(
            'button.cta-primary',
            'a.nav-link',
            'button.pricing-select',
            'a.read-more',
            'button.add-to-cart',
            'a.logo',
            'button.submit',
            'a.footer-link',
            'button.toggle-menu',
            'a.social-share',
        );
        return $elements[array_rand($elements)];
    }

    /**
     * Random purchase value.
     *
     * @return float Purchase value.
     */
    private function random_purchase_value()
    {
        $tiers = array(29.00, 49.00, 49.00, 99.00, 99.00, 99.00, 199.00, 199.00, 299.00);
        return $tiers[array_rand($tiers)];
    }

    /**
     * Generate a plausible referrer URL based on source data.
     *
     * @param array $source_data Traffic source.
     * @return string|null Referrer URL or null for direct.
     */
    private function generate_referrer($source_data)
    {
        $src = $source_data['source'];
        $map = array(
            'google'        => 'https://www.google.com/',
            'bing'          => 'https://www.bing.com/',
            'facebook'      => 'https://www.facebook.com/',
            'twitter'       => 'https://twitter.com/',
            'linkedin'      => 'https://www.linkedin.com/',
            'producthunt'   => 'https://www.producthunt.com/posts/tracksure',
            'wordpress.org' => 'https://wordpress.org/plugins/tracksure/',
            'github'        => 'https://github.com/tracksure-cloud/tracksure',
            'youtube'       => 'https://www.youtube.com/',
            'newsletter'    => null,
            '(direct)'      => null,
        );

        return $map[$src] ?? null;
    }

    /**
     * Output a log message.
     *
     * @param bool   $verbose Whether to output.
     * @param string $message Message text.
     * @return void
     */
    private function log($verbose, $message)
    {
        if (! $verbose) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::log($message);
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[TrackSure Demo] ' . $message);
        }
    }
}


// ================================================================
// HOOKS: Auto-seed on admin load if needed.
// ================================================================

add_action('admin_init', function () {
    // Only run for administrators.
    if (! current_user_can('manage_options')) {
        return;
    }

    // Only run if TrackSure is active.
    if (! class_exists('TrackSure_DB')) {
        return;
    }

    $seeder = new TrackSure_Demo_Seeder();

    if (! $seeder->tables_exist()) {
        return;
    }

    if ($seeder->needs_seeding()) {
        // Increase limits for seeding.
        // phpcs:ignore WordPress.PHP.IniSet.Risky
        @ini_set('max_execution_time', 120);
        // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Risky
        @ini_set('memory_limit', '512M');

        $seeder->seed(false);

        // Show admin notice.
        add_action('admin_notices', function () {
            $status = get_option(TrackSure_Demo_Seeder::OPTION_KEY);
            if ($status) {
                printf(
                    '<div class="notice notice-success is-dismissible"><p><strong>TrackSure Demo:</strong> Generated %s visitors, %s sessions, %s events, and %s conversions in %ss.</p></div>',
                    esc_html(number_format($status['visitors'])),
                    esc_html(number_format($status['sessions'])),
                    esc_html(number_format($status['events'])),
                    esc_html(number_format($status['conversions'])),
                    esc_html($status['elapsed'])
                );
            }
        });
    }
}, 99); // Late priority so TrackSure is loaded.


// ================================================================
// WP-CLI COMMANDS
// ================================================================

if (defined('WP_CLI') && WP_CLI) {

    /**
     * Manage TrackSure demo data for InstaWP snapshots.
     */
    class TrackSure_Demo_CLI
    {

        /**
         * Generate demo data (clears existing data first).
         *
         * ## OPTIONS
         *
         * [--visitors=<count>]
         * : Number of visitors to generate.
         * ---
         * default: 2000
         * ---
         *
         * [--days=<count>]
         * : Number of days of historical data.
         * ---
         * default: 30
         * ---
         *
         * ## EXAMPLES
         *
         *     wp tracksure-demo seed
         *     wp tracksure-demo seed --visitors=5000 --days=90
         *
         * @param array $args       Positional args.
         * @param array $assoc_args Named args.
         */
        public function seed($args, $assoc_args)
        {
            $visitors = (int) ($assoc_args['visitors'] ?? 2000);
            $days     = (int) ($assoc_args['days'] ?? 30);

            if (! defined('TRACKSURE_DEMO_VISITORS')) {
                define('TRACKSURE_DEMO_VISITORS', $visitors);
            }
            if (! defined('TRACKSURE_DEMO_DAYS')) {
                define('TRACKSURE_DEMO_DAYS', $days);
            }

            $seeder = new TrackSure_Demo_Seeder();

            if (! $seeder->tables_exist()) {
                WP_CLI::error('TrackSure tables not found. Is the plugin activated?');
            }

            $seeder->seed(true);
            WP_CLI::success('Demo data seeded successfully!');
        }

        /**
         * Remove all demo data from TrackSure tables.
         *
         * ## EXAMPLES
         *
         *     wp tracksure-demo clear
         *
         * @param array $args       Positional args.
         * @param array $assoc_args Named args.
         */
        public function clear($args, $assoc_args)
        {
            $seeder = new TrackSure_Demo_Seeder();

            if (! $seeder->tables_exist()) {
                WP_CLI::error('TrackSure tables not found.');
            }

            $seeder->clear();
            WP_CLI::success('All TrackSure demo data cleared.');
        }

        /**
         * Show demo data status.
         *
         * ## EXAMPLES
         *
         *     wp tracksure-demo status
         *
         * @param array $args       Positional args.
         * @param array $assoc_args Named args.
         */
        public function status($args, $assoc_args)
        {
            $seeder = new TrackSure_Demo_Seeder();
            $status = $seeder->get_status();

            if (! $status) {
                WP_CLI::warning('No demo data has been seeded.');
                return;
            }

            WP_CLI::log('TrackSure Demo Data Status:');
            WP_CLI::log(sprintf('  Seeded at:    %s', $status['seeded_at']));
            WP_CLI::log(sprintf('  Visitors:     %s', number_format($status['visitors'])));
            WP_CLI::log(sprintf('  Sessions:     %s', number_format($status['sessions'])));
            WP_CLI::log(sprintf('  Events:       %s', number_format($status['events'])));
            WP_CLI::log(sprintf('  Conversions:  %s', number_format($status['conversions'])));
            WP_CLI::log(sprintf('  Touchpoints:  %s', number_format($status['touchpoints'])));
            WP_CLI::log(sprintf('  Generation:   %ss', $status['elapsed']));

            // Check staleness.
            $stale_days = defined('TRACKSURE_DEMO_STALE_DAYS') ? (int) TRACKSURE_DEMO_STALE_DAYS : 3;
            $age_days   = round((time() - strtotime($status['seeded_at'])) / DAY_IN_SECONDS, 1);
            WP_CLI::log(sprintf('  Age:          %s days (stale after %d)', $age_days, $stale_days));

            if ($seeder->needs_seeding()) {
                WP_CLI::warning('Data is stale — will auto-refresh on next admin load.');
            } else {
                WP_CLI::success('Data is fresh.');
            }
        }
    }

    WP_CLI::add_command('tracksure-demo', 'TrackSure_Demo_CLI');
}
