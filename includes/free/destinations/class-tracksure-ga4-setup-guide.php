<?php
/**
 * GA4 setup guide configuration helper.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging for GA4 setup guide diagnostics

/**
 * GA4 Setup Guide - Admin Notice
 *
 * Shows users the 5 REQUIRED manual steps in GA4 Admin UI.
 * TrackSure handles all tracking automatically, but GA4 Admin requires manual configuration.
 *
 * @package TrackSure
 * @since 2.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GA4 Setup Guide Class
 */
class TrackSure_GA4_Setup_Guide {




	/**
	 * User meta key for dismissing notice.
	 */
	const DISMISS_META_KEY = 'tracksure_ga4_setup_guide_dismissed';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Show admin notice for GA4 setup guide (WordPress admin).
		add_action( 'admin_notices', array( $this, 'show_setup_guide' ) );

		// Handle dismiss action (WordPress admin).
		add_action( 'admin_init', array( $this, 'handle_dismiss' ) );

		// Register REST API endpoint for React admin.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Show GA4 setup guide admin notice.
	 */
	public function show_setup_guide() {
		// Only show on TrackSure settings page.
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'toplevel_page_tracksure' ) {
			return;
		}

		// Check if user dismissed the notice.
		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, self::DISMISS_META_KEY, true ) ) {
			return;
		}

		// Check if GA4 is configured.
		$ga4_settings = get_option( 'tracksure_destinations', array() );
		if ( empty( $ga4_settings['ga4']['enabled'] ) || empty( $ga4_settings['ga4']['measurement_id'] ) ) {
			return; // GA4 not configured yet.
		}

		// Get measurement ID for guide.
		$measurement_id = sanitize_text_field( $ga4_settings['ga4']['measurement_id'] );

		?>
		<div class="notice notice-info is-dismissible tracksure-ga4-setup-guide">
			<h2 style="margin-top: 10px;">🚀 TrackSure + GA4: 5 Required Setup Steps</h2>
			<p><strong>Good news:</strong> TrackSure is already tracking <strong>all events automatically</strong>
			(purchase, begin_checkout, view_item, add_to_cart, page_view, etc.). No code needed! ✅</p>
			<p><strong>Important:</strong> You must complete these <strong>5 manual steps in Google Analytics Admin UI</strong> to see data in reports:</p>

			<ol style="margin-left: 20px; line-height: 1.8;">
				<li>
					<strong>Mark Events as Conversions</strong> (CRITICAL for tracking revenue)<br>
					→ GA4 Admin → Events → Toggle "Mark as conversion" for:
					<ul style="margin-left: 20px;">
						<li><code>purchase</code> ✅ (Already tracked by TrackSure)</li>
						<li><code>begin_checkout</code> ✅ (Already tracked)</li>
						<li><code>add_to_cart</code> ✅ (Already tracked)</li>
					</ul>
					<em>Why: Without marking as conversion, GA4 won't show revenue in reports.</em>
				</li>

				<li>
					<strong>Set Data Retention to 14 Months</strong> (default is only 2 months!)<br>
					→ GA4 Admin → Data Settings → Data Retention → Select <strong>14 months (maximum)</strong><br>
					<em>Why: TrackSure attribution needs longer data retention for accurate campaign tracking.</em>
				</li>

				<li>
					<strong>Create Custom Dimensions</strong> (Optional but recommended)<br>
					→ GA4 Admin → Custom Definitions → Create custom dimensions:<br>
					TrackSure already sends these parameters - you just need to create the dimensions:
					<ul style="margin-left: 20px;">
						<li><code>page_url</code> (Event-scoped, parameter: <code>page_url</code>) → Shows full URL in reports</li>
						<li><code>product_sku</code> (Event-scoped, parameter: <code>item_sku</code>) → Product SKU tracking</li>
						<li><code>customer_type</code> (User-scoped, user property: <code>customer_type</code>) → New vs returning</li>
						<li><code>utm_source</code> (Event-scoped, parameter: <code>source</code>) → Traffic source</li>
					</ul>
					<em>Why: Enables detailed reporting by product, customer segment, traffic source, etc.</em>
				</li>

				<li>
					<strong>Create Audiences for Remarketing</strong> (Optional but powerful)<br>
					→ GA4 Admin → Audiences → Create custom audience:<br>
					<ul style="margin-left: 20px;">
						<li>"Cart Abandoners" → Include: add_to_cart (last 7 days), Exclude: purchase (last 7 days)</li>
						<li>"Purchasers (30 days)" → Include: purchase (last 30 days)</li>
						<li>"High Value Customers" → Condition: total revenue > 1000 BDT</li>
					</ul>
					<em>Why: Export to Google Ads for retargeting campaigns.</em>
				</li>

				<li>
					<strong>Link Product Integrations</strong> (If applicable)<br>
					→ GA4 Admin → Product Links:<br>
					<ul style="margin-left: 20px;">
						<li><strong>Google Ads</strong> → Import conversions, enable remarketing (CRITICAL for ROI tracking)</li>
						<li><strong>Search Console</strong> → See which Google searches drive traffic</li>
						<li><strong>Merchant Center</strong> → Required for Google Shopping ads</li>
					</ul>
					<em>Why: Connects GA4 data to your advertising platforms for better targeting.</em>
				</li>
			</ol>

			<h3 style="margin-top: 15px;">✅ What TrackSure Already Handles (Zero Configuration Needed):</h3>
			<ul style="margin-left: 20px; line-height: 1.6;">
				<li>✅ All GA4 recommended events (purchase, begin_checkout, view_item, add_to_cart, page_view, etc.)</li>
				<li>✅ Complete eCommerce data (transaction_id, value, currency, items array with SKU/price/quantity)</li>
				<li>✅ Enhanced measurement parameters (page_url, page_title, page_path, page_location)</li>
				<li>✅ User properties (customer_type, lifetime_value, first_purchase_date)</li>
				<li>✅ Session tracking (session_id, engagement_time_msec)</li>
				<li>✅ Device/browser context (device_category, browser, os, screen_resolution)</li>
				<li>✅ UTM parameters & attribution (campaign, source, medium, term, content)</li>
				<li>✅ Ad platform click IDs (gclid, fbclid, msclkid, ttclid)</li>
				<li>✅ Deduplication (browser + server use same event_id)</li>
				<li>✅ Debug mode (auto-enabled for .local domains)</li>
				<li>✅ Consent Mode V2 (GDPR/CCPA compliance with Complianz, CookieYes, etc.)</li>
			</ul>

			<p><strong>Summary:</strong> TrackSure handles <strong>100% of tracking code</strong> automatically.
			You just need to configure <strong>GA4 Admin UI</strong>
			(conversions, data retention, custom dimensions) to see data in reports.
			The 5 steps above take ~15 minutes total.</p>

			<p style="margin-top: 15px;">
				<a href="<?php echo esc_url( 'https://analytics.google.com/analytics/web/#/p' . str_replace( 'G-', '', $measurement_id ) . '/admin' ); ?>"
					target="_blank" class="button button-primary">
					Open GA4 Admin → Complete Setup (15 min)
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=tracksure_dismiss_ga4_guide' ), 'dismiss_ga4_guide' ) ); ?>" class="button">
					I've Completed Setup → Dismiss
				</a>
			</p>

			<p style="font-size: 12px; color: #666;">
				<strong>Note:</strong> Wait 24-48 hours after completing setup for data to appear in GA4 standard reports (Realtime shows data immediately).
			</p>
		</div>

		<?php
		// Enqueue inline CSS using WordPress enqueue system.
		$guide_css  = '.tracksure-ga4-setup-guide code {';
		$guide_css .= '  background: #f0f0f0;';
		$guide_css .= '  padding: 2px 6px;';
		$guide_css .= '  border-radius: 3px;';
		$guide_css .= '  font-size: 13px;';
		$guide_css .= '}';
		$guide_css .= '.tracksure-ga4-setup-guide ul,';
		$guide_css .= '.tracksure-ga4-setup-guide ol {';
		$guide_css .= '  margin-bottom: 10px;';
		$guide_css .= '}';
		$guide_css .= '.tracksure-ga4-setup-guide em {';
		$guide_css .= '  color: #666;';
		$guide_css .= '  font-size: 13px;';
		$guide_css .= '}';
		wp_add_inline_style( 'wp-admin', $guide_css );
	}

	/**
	 * Handle dismiss action.
	 */
	public function handle_dismiss() {
		if ( ! isset( $_GET['action'] ) || 'tracksure_dismiss_ga4_guide' !== sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dismiss_ga4_guide' ) ) {
			wp_die( 'Security check failed' );
		}

		// Verify user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to perform this action.' );
		}

		// Mark as dismissed.
		$user_id = get_current_user_id();
		update_user_meta( $user_id, self::DISMISS_META_KEY, true );

		// Redirect back.
		wp_safe_redirect( admin_url( 'admin.php?page=tracksure' ) );
		exit;
	}

	/**
	 * Register REST API routes for React admin.
	 */
	public function register_rest_routes() {
		// GET /wp-json/ts/v1/ga4-setup-guide
		register_rest_route(
			'ts/v1',
			'/ga4-setup-guide',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_setup_guide_status' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// POST /wp-json/ts/v1/ga4-setup-guide/dismiss
		register_rest_route(
			'ts/v1',
			'/ga4-setup-guide/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'dismiss_setup_guide' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Get setup guide status for React admin.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function get_setup_guide_status( $request ) {
		// Check if user dismissed the notice.
		$user_id   = get_current_user_id();
		$dismissed = get_user_meta( $user_id, self::DISMISS_META_KEY, true );

		if ( $dismissed ) {
			return new WP_REST_Response(
				array(
					'show_guide' => false,
					'dismissed'  => true,
					'reason'     => 'User dismissed the guide',
				),
				200
			);
		}

		// Check if GA4 is configured.
		$ga4_measurement_id = get_option( 'tracksure_free_ga4_measurement_id', '' );
		$ga4_enabled        = get_option( 'tracksure_free_ga4_enabled', false );

		if ( empty( $ga4_measurement_id ) || ! $ga4_enabled ) {
			return new WP_REST_Response(
				array(
					'show_guide' => false,
					'configured' => false,
					'reason'     => 'GA4 not configured yet',
				),
				200
			);
		}

		// Return setup guide data for React admin.
		return new WP_REST_Response(
			array(
				'show_guide'        => true,
				'dismissed'         => false,
				'configured'        => true,
				'measurement_id'    => sanitize_text_field( $ga4_measurement_id ),
				'ga4_admin_url'     => 'https://analytics.google.com/analytics/web/#/p' . str_replace( 'G-', '', $ga4_measurement_id ) . '/admin',
				'title'             => '🚀 TrackSure + GA4: 5 Required Setup Steps',
				'intro'             => array(
					'good_news' => 'TrackSure is already tracking all events automatically (purchase, begin_checkout, view_item, add_to_cart, page_view, etc.). No code needed!',
					'important' => 'You must complete these 5 manual steps in Google Analytics Admin UI to see data in reports:',
				),
				'steps'             => array(
					array(
						'id'          => 1,
						'title'       => 'Mark Events as Conversions',
						'time'        => '2 minutes',
						'critical'    => true,
						'description' => 'GA4 Admin → Events → Toggle "Mark as conversion"',
						'events'      => array( 'purchase', 'begin_checkout', 'add_to_cart', 'generate_lead' ),
						'why'         => 'Without marking as conversion, GA4 won\'t show revenue in reports.',
					),
					array(
						'id'          => 2,
						'title'       => 'Set Data Retention to 14 Months',
						'time'        => '1 minute',
						'critical'    => true,
						'description' => 'GA4 Admin → Data Settings → Data Retention → Select 14 months (maximum)',
						'why'         => 'TrackSure attribution needs longer data retention for accurate campaign tracking.',
					),
					array(
						'id'          => 3,
						'title'       => 'Create Custom Dimensions',
						'time'        => '5 minutes',
						'critical'    => false,
						'description' => 'GA4 Admin → Custom Definitions → Create custom dimensions',
						'dimensions'  => array(
							array(
								'name'      => 'page_url',
								'scope'     => 'Event',
								'parameter' => 'page_url',
								'why'       => 'Shows full URL in "Views by Page" reports',
							),
							array(
								'name'      => 'product_sku',
								'scope'     => 'Event',
								'parameter' => 'item_sku',
								'why'       => 'Track by SKU instead of product name',
							),
							array(
								'name'      => 'customer_type',
								'scope'     => 'User',
								'parameter' => 'customer_type',
								'why'       => 'Segment by New vs Returning',
							),
							array(
								'name'      => 'utm_source',
								'scope'     => 'Event',
								'parameter' => 'source',
								'why'       => 'Traffic source custom reports',
							),
						),
						'why'         => 'Enables detailed reporting by product, customer segment, traffic source, etc.',
					),
					array(
						'id'          => 4,
						'title'       => 'Create Audiences for Remarketing',
						'time'        => '5 minutes',
						'critical'    => false,
						'description' => 'GA4 Admin → Audiences → Create custom audience', // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
						'audiences'   => array(
							array(
								'name'    => 'Cart Abandoners',
								'include' => 'add_to_cart (last 7 days)',
								'exclude' => 'purchase (last 7 days)', // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
							),
							array(
								'name'    => 'Purchasers (30 days)',
								'include' => 'purchase (last 30 days)',
							),
							array(
								'name'      => 'High Value Customers',
								'condition' => 'total_revenue > 1000 BDT',
							),
						),
						'why'         => 'Export to Google Ads for retargeting campaigns.',
					),
					array(
						'id'           => 5,
						'title'        => 'Link Product Integrations',
						'time'         => '2 minutes',
						'critical'     => false,
						'description'  => 'GA4 Admin → Product Links',
						'integrations' => array(
							array(
								'name'     => 'Google Ads',
								'benefits' => array(
									'Import conversions to Google Ads',
									'Enable remarketing audiences',
									'Track ROAS (Return on Ad Spend)',
									'Auto-tagging (gclid parameter)',
								),
							),
							array(
								'name'     => 'Search Console',
								'benefits' => array(
									'See which Google searches drive traffic',
									'Landing page performance',
									'Click-through rates',
								),
							),
							array(
								'name'     => 'Merchant Center',
								'benefits' => array(
									'Required for Google Shopping campaigns',
									'Performance Max campaigns',
									'Free product listings',
								),
							),
						),
						'why'          => 'Connects GA4 data to your advertising platforms for better targeting.',
					),
				),
				'tracksure_handles' => array(
					'All GA4 recommended events (purchase, begin_checkout, view_item, add_to_cart, page_view, etc.)',
					'Complete eCommerce data (transaction_id, value, currency, items array with SKU/price/quantity)',
					'Enhanced measurement parameters (page_url, page_title, page_path, page_location)',
					'User properties (customer_type, lifetime_value, first_purchase_date)',
					'Session tracking (session_id, engagement_time_msec)',
					'Device/browser context (device_category, browser, os, screen_resolution)',
					'UTM parameters & attribution (campaign, source, medium, term, content)',
					'Ad platform click IDs (gclid, fbclid, msclkid, ttclid)',
					'Deduplication (browser + server use same event_id)',
					'Debug mode (auto-enabled for .local domains)',
					'Consent Mode V2 (GDPR/CCPA compliance with Complianz, CookieYes, etc.)',
				),
				'summary'           => 'TrackSure handles 100% of tracking code automatically. '
					. 'You just need to configure GA4 Admin UI (conversions, data retention, custom dimensions) '
					. 'to see data in reports. The 5 steps above take ~15 minutes total.',
				'note'              => 'Wait 24-48 hours after completing setup for data to appear in GA4 standard reports (Realtime shows data immediately).',
			),
			200
		);
	}

	/**
	 * Dismiss setup guide (REST API endpoint).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function dismiss_setup_guide( $request ) {
		$user_id = get_current_user_id();
		update_user_meta( $user_id, self::DISMISS_META_KEY, true );

		return new WP_REST_Response(
			array(
				'success'   => true,
				'message'   => 'Setup guide dismissed successfully',
				'dismissed' => true,
			),
			200
		);
	}
}

// Initialize.
new TrackSure_GA4_Setup_Guide();
