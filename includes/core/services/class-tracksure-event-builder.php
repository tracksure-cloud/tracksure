<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for event building diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Event Builder
 *
 * SINGLE place to build ALL events (browser, server, integrations, API).
 * Ensures every event has: event_id, client_id, session_id, user_data, timestamps.
 * Uses registry for validation and enrichment.
 *
 * @package TrackSure\Core
 * @since 1.2.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Event Builder Class
 */
class TrackSure_Event_Builder
{



	/**
	 * Instance.
	 *
	 * @var TrackSure_Event_Builder
	 */
	private static $instance = null;

	/**
	 * Registry instance.
	 *
	 * @var TrackSure_Registry
	 */
	private $registry;

	/**
	 * Session manager instance.
	 *
	 * @var TrackSure_Session_Manager
	 */
	private $session_manager;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Event_Builder
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		$this->registry        = TrackSure_Registry::get_instance();
		$this->session_manager = TrackSure_Session_Manager::get_instance();
	}

	/**
	 * Build a complete, validated event.
	 *
	 * This is the ONLY way events should be created in TrackSure.
	 * Ensures consistency across browser, server, integrations, and API.
	 *
	 * @param string $event_name Event name from registry.
	 * @param array  $params Event parameters.
	 * @param array  $context Optional context (event_id, client_id, user_data, etc).
	 * @return array|false Complete event or false on validation error.
	 */
	public function build_event($event_name, $params = array(), $context = array())
	{
		// 1. Load event schema from registry.
		$event_schema = $this->registry->get_event($event_name);
		if (! $event_schema) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log("[TrackSure] Event Builder: Event '$event_name' not found in registry");
			}
			return false;
		}

		// 2. Generate/validate IDs (use context if provided, otherwise generate).
		// CRITICAL: Get session_id FIRST before generating event_id (needed for deterministic ID).
		$client_id = isset($context['client_id'])
			? $context['client_id']
			: $this->session_manager->get_client_id_from_browser();

		$session_id = isset($context['session_id'])
			? $context['session_id']
			: $this->session_manager->get_session_id_from_browser();

		// Fallback if session manager returns empty.
		if (empty($client_id)) {
			$client_id = wp_generate_uuid4();
		}
		if (empty($session_id)) {
			$session_id = wp_generate_uuid4();
		}

		// Generate event_id AFTER we have session_id.
		// Use deterministic ID for Meta CAPI compliance (browser + server = same ID).
		$event_id = isset($context['event_id']) && TrackSure_Utilities::is_valid_uuid_v4($context['event_id'])
			? $context['event_id']
			: $this->generate_deterministic_event_id($session_id, $event_name, $params);

		// 3. Build base event structure (ALWAYS includes these fields).
		$event_source = isset($context['event_source']) ? $context['event_source'] : 'server';

		// Determine browser_fired and server_fired flags based on event_source.
		// CRITICAL: Browser events should have browser_fired=1 and server_fired=0.
		// CRITICAL: Server events should have browser_fired=0 and server_fired=1.
		$browser_fired = 0;
		$server_fired  = 1;

		if ($event_source === 'browser') {
			$browser_fired = 1;
			$server_fired  = 0;
		}

		$event = array(
			'event_id'       => $event_id,
			'event_name'     => $event_name,
			'event_source'   => $event_source,
			'browser_fired'  => $browser_fired,
			'server_fired'   => $server_fired,
			'client_id'      => $client_id,
			'session_id'     => $session_id,
			'timestamp'      => current_time('mysql'),
			'event_params'   => array(),
			'user_data'      => array(),
			'ecommerce_data' => array(),
			'page_context'   => array(),
		);

		// 4. Add user data (ALWAYS try to enrich).
		$event['user_data'] = $this->get_user_data($context);

		// 5. Add ecommerce data (if provided).
		if (! empty($context['ecommerce_data']) && is_array($context['ecommerce_data'])) {
			$event['ecommerce_data'] = $context['ecommerce_data'];
		}

		// 6. Add page context (URL, title, referrer).
		$event['page_context'] = $this->get_page_context($context);

		// 7. Validate and add parameters.
		$validated_params = $this->validate_params($event_name, $params, $event_schema);
		if (false === $validated_params) {
			if (defined('WP_DEBUG') && WP_DEBUG) {

				error_log("[TrackSure] Event Builder: Parameter validation failed for '$event_name'");
			}
			return false;
		}
		$event['event_params'] = $validated_params;

		return $event;
	}

	/**
	 * Generate deterministic event_id for browser+server deduplication.
	 *
	 * CRITICAL for Meta CAPI and ad platforms:
	 * - Browser and server MUST use identical event_id for same action
	 * - Meta uses event_id for deduplication between Pixel and CAPI
	 * - Different event_ids = duplicate counting in ad platforms
	 *
	 * UNIVERSAL DESIGN (works for ALL website types):
	 * - E-commerce: Uses product_id (WooCommerce, EDD, SureCart, etc.)
	 * - Blog/News: Uses post_id (WordPress posts/pages)
	 * - Custom pages: Uses page_url hash
	 * - Generic events: Uses session + event + time only
	 *
	 * Strategy: Hash(session_id + event_name + timestamp + content_identifier)
	 *
	 * @param string $session_id Session UUID.
	 * @param string $event_name Event name (e.g., "view_item", "page_view").
	 * @param array  $params Event parameters (may contain product_id, post_id, page_url).
	 * @return string UUID v4 formatted event_id.
	 */
	private function generate_deterministic_event_id($session_id, $event_name, $params = array())
	{
		// Build deterministic string.
		// CRITICAL: No timestamp! Browser and server must generate identical event_id.
		// regardless of timing differences (milliseconds apart).
		// UNIVERSAL CONTENT IDENTIFIER (works for ALL website types).
		$content_identifier = $this->extract_content_identifier($params);

		// Create deterministic string: session + event + content (NO TIMESTAMP).
		// This ensures browser-server deduplication works even with timing differences.
		$deterministic_string = $session_id . '|' . $event_name . '|' . $content_identifier;

		// Generate MD5 hash (128 bits, perfect for UUID).
		$hash = md5($deterministic_string);

		// Convert hash to UUID v4 format (8-4-4-4-12).
		// Set version bits (4) and variant bits (2) for valid UUID v4.
		$uuid = sprintf(
			'%s-%s-4%s-%s%s-%s',
			substr($hash, 0, 8),
			substr($hash, 8, 4),
			substr($hash, 13, 3),
			dechex(hexdec(substr($hash, 16, 1)) & 0x3 | 0x8),
			substr($hash, 17, 3),
			substr($hash, 20, 12)
		);

		return $uuid;
	}

	/**
	 * Extract universal content identifier from event parameters.
	 *
	 * Works for ALL website types (e-commerce, blog, portfolio, agency, etc.)
	 *
	 * Priority order:
	 * 1. E-commerce: product_id (WooCommerce, EDD, SureCart, CartFlows, etc.)
	 * 2. Blog/Content: post_id (WordPress posts, pages, custom post types)
	 * 3. Custom pages: page_url (for non-WordPress pages, landing pages)
	 * 4. Generic: Empty string (session + event + time is enough)
	 *
	 * @param array $params Event parameters.
	 * @return string Content identifier (product_id, post_id, or page_url hash).
	 */
	private function extract_content_identifier($params)
	{
		// 1. E-commerce product ID (highest priority).
		if (isset($params['product_id']) && ! empty($params['product_id'])) {
			return 'product_' . $params['product_id'];
		}

		// 2. Multi-item e-commerce (cart, checkout).
		if (isset($params['items']) && is_array($params['items']) && ! empty($params['items'])) {
			$item_ids = array_map(
				function ($item) {
					return isset($item['item_id']) ? $item['item_id'] : '';
				},
				$params['items']
			);
			return 'items_' . md5(implode(',', $item_ids)); // Hash for consistent length
		}

		// 3. WordPress post/page ID (blogs, news, content sites).
		if (isset($params['post_id']) && ! empty($params['post_id'])) {
			return 'post_' . $params['post_id'];
		}

		// 4. Page URL or page_location (custom pages, landing pages, page_view events).
		// Use short hash to keep deterministic string length reasonable.
		// page_location is the GA4 standard name used by page_view events.
		$page_url = ! empty($params['page_url']) ? $params['page_url'] : (! empty($params['page_location']) ? $params['page_location'] : null);
		if ($page_url) {
			return 'page_' . substr(md5($page_url), 0, 8);
		}

		// 5. Generic events (no content identifier needed).
		// Examples: session_start, form_submit, video_play, etc.
		// Session + event + time is sufficient for deduplication.
		return '';
	}

	/**
	 * Get current user data (logged in or from context).
	 *
	 * Priority:
	 * 1. Context (manually provided user_data)
	 * 2. WordPress logged-in user
	 * 3. WooCommerce order data (for purchase events)
	 *
	 * @param array $context Context array.
	 * @return array User data.
	 */
	private function get_user_data($context)
	{
		// Priority 1: Context (manually provided).
		if (! empty($context['user_data']) && is_array($context['user_data'])) {
			return $context['user_data'];
		}

		// Priority 2: WordPress logged-in user.
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			return array(
				'email'      => $user->user_email,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
				'user_id'    => (string) $user->ID,
			);
		}

		// Priority 3: Progressive capture from browser (handled by tracksure-web.js).
		// Will be merged on API ingestion.
		return array();
	}

	/**
	 * Get page context (URL, title, path, referrer).
	 *
	 * CRITICAL: These 4 parameters are AUTOMATICALLY added to ALL events.
	 * This ensures Free, Pro, and 3rd-party extensions don't need to manually add them.
	 *
	 * Standard page context parameters (always included):
	 * - page_url: Full URL with query string
	 * - page_title: Document title
	 * - page_path: URL path without domain (for grouping)
	 * - page_referrer: Previous page URL
	 *
	 * @param array $context Context array.
	 * @return array Page context with all 4 standard parameters.
	 */
	private function get_page_context($context)
	{
		$page_context = array(
			'page_url'      => '',
			'page_title'    => '',
			'page_path'     => '',
			'page_referrer' => '',
		);

		// 1. Page URL (full URL with query string).
		if (! empty($context['page_url'])) {
			$page_context['page_url'] = $context['page_url'];
		} elseif (isset($_SERVER['REQUEST_URI'])) {
			$page_context['page_url'] = home_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])));
		}

		// 2. Page Title (document title).
		if (! empty($context['page_title'])) {
			$page_context['page_title'] = $context['page_title'];
		} elseif (function_exists('wp_get_document_title')) {
			$page_context['page_title'] = wp_get_document_title();
		}

		// 3. Page Path (URL path without domain - for analytics grouping).
		if (! empty($context['page_path'])) {
			$page_context['page_path'] = $context['page_path'];
		} elseif (isset($_SERVER['REQUEST_URI'])) {
			// Extract path from REQUEST_URI (e.g., "/checkout/order-received/248737/").
			$request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
			// Remove query string if present.
			$page_context['page_path'] = strtok($request_uri, '?');
		} elseif (! empty($page_context['page_url'])) {
			// Extract path from page_url as fallback.
			$parsed                    = wp_parse_url($page_context['page_url']);
			$page_context['page_path'] = isset($parsed['path']) ? $parsed['path'] : '/';
		}

		// 4. Page Referrer (previous page).
		if (! empty($context['page_referrer'])) {
			$page_context['page_referrer'] = $context['page_referrer'];
		} elseif (isset($_SERVER['HTTP_REFERER'])) {
			$page_context['page_referrer'] = sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER']));
		}

		return $page_context;
	}

	/**
	 * Validate parameters against registry schema.
	 *
	 * @param string $event_name Event name.
	 * @param array  $params Parameters to validate.
	 * @param array  $event_schema Event schema from registry.
	 * @return array|false Validated parameters or false on critical error.
	 */
	private function validate_params($event_name, $params, $event_schema)
	{
		$validated = array();

		// Get schema params.
		$required_params = isset($event_schema['required_params']) ? $event_schema['required_params'] : array();
		$optional_params = isset($event_schema['optional_params']) ? $event_schema['optional_params'] : array();

		// Add all required params.
		foreach ($required_params as $param_name) {
			if (! isset($params[$param_name])) {
				if (defined('WP_DEBUG') && WP_DEBUG) {

					error_log("[TrackSure] Event Builder: Missing required param '$param_name' for event '$event_name'");
				}
				// Don't fail - let Event Recorder handle gracefully.
			} else {
				$validated[$param_name] = $params[$param_name];
			}
		}

		// Add all optional params (if provided).
		foreach ($optional_params as $param_name) {
			if (isset($params[$param_name])) {
				$validated[$param_name] = $params[$param_name];
			}
		}

		// CRITICAL FIX: For ecommerce events, always pass through items array even if not in registry.
		// Registry uses generic 'items' but WooCommerce sends 'items' with product data.
		$ecommerce_events = array('view_cart', 'begin_checkout', 'purchase', 'add_to_cart', 'view_item');
		if (in_array($event_name, $ecommerce_events) && isset($params['items'])) {
			$validated['items'] = $params['items'];
		}

		// CRITICAL FIX: Add page_title and page_url to event_params for all events.
		// This ensures they show up in real-time page and are sent to destinations.
		if (isset($event['page_context']['page_title'])) {
			$validated['page_title'] = $event['page_context']['page_title'];
		}
		if (isset($event['page_context']['page_url'])) {
			$validated['page_url'] = $event['page_context']['page_url'];
		}
		if (isset($event['page_context']['page_referrer'])) {
			$validated['page_referrer'] = $event['page_context']['page_referrer'];
		}

		// AUTO-ADD TEMPORAL PARAMETERS (for admin UI filtering + Meta custom audiences).
		// These parameters enable powerful segmentation:
		// - Admin UI: "Show most visited products on Mondays in Q1"
		// - Meta Custom Audiences: "People who viewed products on weekends in December"
		// - Behavior Analysis: Weekday vs weekend conversion rates
		$validated = $this->add_temporal_params($validated);

		return $validated;
	}

	/**
	 * Add temporal parameters to event for time-based analytics.
	 *
	 * TIMEZONE STRATEGY:
	 * - Database timestamps: UTC (for global consistency)
	 * - Temporal parameters: WordPress site timezone (for user filtering)
	 * - Admin UI displays: WordPress site timezone (for user convenience)
	 *
	 * This allows:
	 * - Multi-timezone traffic tracking (stored in UTC)
	 * - Local time filtering ("Monday 9am-5pm in MY timezone")
	 * - Admin sees events in THEIR timezone
	 *
	 * Automatically adds date/time context to ALL events:
	 * - event_date: YYYY-MM-DD (site timezone)
	 * - event_time: HH:MM:SS (site timezone)
	 * - event_hour: 0-23 (site timezone)
	 * - day_of_week: Monday-Sunday (site timezone)
	 * - day_of_week_number: 0-6 (numeric for sorting)
	 * - week_of_year: 1-53 (ISO week number)
	 * - month_name: January-December (for monthly trends)
	 * - month_number: 1-12 (numeric for sorting)
	 * - quarter: Q1-Q4 (seasonal campaigns)
	 * - year: 2026 (yearly trends)
	 * - is_weekend: true/false (weekend vs weekday)
	 * - timezone: Site timezone name (e.g., "America/New_York")
	 *
	 * Benefits:
	 * 1. Admin UI: Filter events by day/week/month in dashboard
	 * 2. Meta Custom Audiences: "Weekend shoppers in Q4"
	 * 3. Behavior Insights: "Products most viewed on Monday mornings"
	 * 4. Conversion Analysis: Weekday vs weekend purchase rates
	 *
	 * @param array $params Existing event parameters.
	 * @return array Parameters with temporal data added.
	 */
	private function add_temporal_params($params)
	{
		// CRITICAL: Use WordPress site timezone for temporal parameters.
		// This ensures admin can filter "Monday 9am" in THEIR timezone, not UTC.
		// Database timestamps remain in UTC for global consistency.

		// Date and time strings (WordPress site timezone).
		$params['event_date'] = current_time('Y-m-d'); // YYYY-MM-DD (site timezone)
		$params['event_time'] = current_time('H:i:s'); // HH:MM:SS (site timezone)
		$params['event_hour'] = (int) current_time('G'); // 0-23 (site timezone)

		// Day of week (site timezone).
		$day_of_week_number           = (int) current_time('w'); // 0 (Sunday) - 6 (Saturday)
		$params['day_of_week_number'] = $day_of_week_number;
		$params['day_of_week']        = current_time('l'); // Monday, Tuesday, etc.
		$params['is_weekend']         = in_array($day_of_week_number, array(0, 6)); // Sunday or Saturday

		// Week of year (ISO 8601, site timezone).
		$params['week_of_year'] = (int) current_time('W'); // 1-53

		// Month (site timezone).
		$params['month_number'] = (int) current_time('n'); // 1-12
		$params['month_name']   = current_time('F'); // January, February, etc.

		// Quarter (Q1-Q4, site timezone).
		$month             = (int) current_time('n');
		$params['quarter'] = 'Q' . (int) ceil($month / 3);

		// Year (site timezone).
		$params['year'] = (int) current_time('Y');

		// Store site timezone name for reference.
		// Useful for debugging and multi-timezone analysis.
		$params['timezone'] = wp_timezone_string();

		return $params;
	}



	/**
	 * Build multiple events in batch (for performance).
	 *
	 * @param array $events Array of ['event_name' => string, 'params' => array, 'context' => array].
	 * @return array Array of built events.
	 */
	public function build_batch($events)
	{
		$built_events = array();

		foreach ($events as $event_data) {
			$event = $this->build_event(
				$event_data['event_name'],
				isset($event_data['params']) ? $event_data['params'] : array(),
				isset($event_data['context']) ? $event_data['context'] : array()
			);

			if ($event) {
				$built_events[] = $event;
			}
		}

		return $built_events;
	}
}
