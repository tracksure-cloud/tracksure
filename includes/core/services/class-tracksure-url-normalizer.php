<?php

/**
 * TrackSure URL Normalizer
 *
 * SINGLE SOURCE OF TRUTH for URL normalization across the entire system.
 * Ensures clean, consistent URLs while preserving marketing attribution parameters.
 *
 * Core Philosophy:
 * - Remove noise (session IDs, builder params, unique keys)
 * - Keep signal (UTM params, ad platform IDs, meaningful query strings)
 * - Make it extensible (filters for free/pro/3rd party)
 *
 * @package TrackSure\Core\Services
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * URL Normalizer Service
 */
class TrackSure_URL_Normalizer
{

	/**
	 * Marketing/Attribution parameters to KEEP (case-insensitive).
	 * These are essential for proper attribution tracking.
	 *
	 * @var array
	 */
	private static $marketing_params = [
		// UTM Parameters (Google Analytics standard)
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'utm_id',

		// Google Ads
		'gclid',
		'gclsrc',
		'dclid',
		'wbraid',
		'gbraid',

		// Facebook/Meta
		'fbclid',
		'fb_action_ids',
		'fb_action_types',
		'fb_source',
		'fb_ref',

		// Microsoft/Bing Ads
		'msclkid',

		// TikTok
		'ttclid',

		// Twitter/X
		'twclid',

		// LinkedIn
		'li_fat_id',

		// Snapchat
		'ScCid',

		// Impact.com (Affiliate)
		'irclickid',
		'irgwc',

		// Email Marketing
		'mc_cid',
		'mc_eid', // Mailchimp
		'_ke', // Klaviyo

		// Referral/Affiliate
		'ref',
		'referral',
		'affiliate_id',
		'aff_id',
	];

	/**
	 * Patterns for URLs to exclude/filter entirely.
	 * These should never appear in analytics.
	 *
	 * @var array
	 */
	private static $excluded_url_patterns = [
		'/wp-admin/',
		'/wp-json/',
		'/wp-content/',
		'/wp-includes/',
		'admin-ajax.php',
		'xmlrpc.php',
		'wp-cron.php',
		'wp-login.php',
		'/feed/',
		'/trackback/',
		'/embed/',
		'elementor-preview', // Elementor preview
		'et_fb=1', // Divi builder
		'fl_builder', // Beaver Builder
		'brizy-edit', // Brizy builder
		'ct_builder', // Oxygen builder
		'tve=true', // Thrive Architect
	];

	/**
	 * WooCommerce/E-commerce URL normalization patterns.
	 *
	 * @var array
	 */
	private static $ecommerce_patterns = [
		// WooCommerce order received (normalize unique keys)
		[
			'pattern'     => '#^(.*?/checkout/order-received)/\d+/?\??.*$#i',
			'replacement' => '$1/',
		],
		// WooCommerce add-to-cart (normalize product IDs to category)
		[
			'pattern'     => '#^(.*?\??.*?add-to-cart)=\d+(.*)$#i',
			'replacement' => '$1$2',
		],
		// Easy Digital Downloads purchase confirmation
		[
			'pattern'     => '#^(.*?/purchase-confirmation)/?\??payment_key=.*$#i',
			'replacement' => '$1/',
		],
		// SureCart order confirmation
		[
			'pattern'     => '#^(.*?/order-confirmation)/?\??.*$#i',
			'replacement' => '$1/',
		],
	];

	/**
	 * Query parameters to remove (noise, not signal).
	 *
	 * @var array
	 */
	private static $noise_params = [
		// Session/User identifiers
		'session_id',
		'sid',
		'sessionid',
		'sess',

		// Cache busting
		'ver',
		'version',
		'v',
		'_',
		'cache',
		'nocache',
		'timestamp',

		// WordPress/CMS
		'preview',
		'preview_id',
		'preview_nonce',
		'p',
		'page_id',
		'post_type',

		// WooCommerce unique keys
		'key',
		'order',
		'order_id',
		'payment_key',
		'order_key',

		// Tracking pixels (already captured separately)
		'_ga',
		'_gid',
		'_gac',
		// NOTE: gclid is intentionally NOT here — it's in $marketing_params for Google Ads attribution.

		// Social sharing
		'share',
		'shared',
		'sharesource',

		// Elementor/Builders
		'elementor-preview',
		'elementor_library',
		'ver',

		// Random/Debug
		'debug',
		'test',
		'random',
		'rand',
		'token',
		'nonce',
	];

	/**
	 * Normalize a URL for consistent tracking.
	 *
	 * MAIN ENTRY POINT - Use this method throughout the codebase.
	 *
	 * @param string $url Full URL to normalize.
	 * @param array  $options Optional configuration.
	 * @return string|null Normalized URL or null if should be excluded.
	 */
	public static function normalize($url, $options = [])
	{
		if (empty($url) || ! is_string($url)) {
			return null;
		}

		// Default options
		$options = wp_parse_args(
			$options,
			[
				'keep_query_string'     => true,
				'keep_marketing_params' => true,
				'apply_ecommerce_rules' => true,
				'lowercase'             => false,
				'remove_trailing_slash' => false,
				'remove_www'            => false,
			]
		);

		// 1. Check if URL should be excluded entirely
		if (self::should_exclude($url)) {
			return null;
		}

		// 2. Parse URL
		$parsed = wp_parse_url($url);
		if ($parsed === false || ! isset($parsed['path'])) {
			return null;
		}

		// 3. Start building normalized URL
		$normalized = '';

		// Scheme (optional)
		if (isset($parsed['scheme'])) {
			$normalized .= $parsed['scheme'] . '://';
		}

		// Host
		if (isset($parsed['host'])) {
			$host = $parsed['host'];

			// Remove www if requested
			if ($options['remove_www']) {
				$host = preg_replace('/^www\./i', '', $host);
			}

			// Lowercase domain
			$normalized .= strtolower($host);
		}

		// Path
		$path = $parsed['path'];

		// Apply e-commerce normalization patterns
		if ($options['apply_ecommerce_rules']) {
			$path = self::apply_ecommerce_rules($path);
		}

		// Remove trailing slash if requested
		if ($options['remove_trailing_slash'] && $path !== '/') {
			$path = rtrim($path, '/');
		}

		// Lowercase path if requested
		if ($options['lowercase']) {
			$path = strtolower($path);
		}

		$normalized .= $path;

		// 4. Query string processing
		if ($options['keep_query_string'] && isset($parsed['query'])) {
			$filtered_query = self::filter_query_string(
				$parsed['query'],
				$options['keep_marketing_params']
			);

			if (! empty($filtered_query)) {
				$normalized .= '?' . $filtered_query;
			}
		}

		/**
		 * Filter the normalized URL.
		 * Allows free/pro/3rd party extensions to customize normalization.
		 *
		 * @param string $normalized Normalized URL.
		 * @param string $original Original URL.
		 * @param array $options Normalization options.
		 */
		return apply_filters('tracksure_normalized_url', $normalized, $url, $options);
	}

	/**
	 * Check if URL should be completely excluded from tracking.
	 *
	 * @param string $url URL to check.
	 * @return bool True if should exclude.
	 */
	private static function should_exclude($url)
	{
		foreach (self::$excluded_url_patterns as $pattern) {
			if (stripos($url, $pattern) !== false) {
				return true;
			}
		}

		/**
		 * Filter URL exclusion check.
		 *
		 * @param bool $should_exclude Whether to exclude URL.
		 * @param string $url URL being checked.
		 */
		return apply_filters('tracksure_should_exclude_url', false, $url);
	}

	/**
	 * Apply e-commerce URL normalization rules.
	 *
	 * @param string $path URL path.
	 * @return string Normalized path.
	 */
	private static function apply_ecommerce_rules($path)
	{
		foreach (self::$ecommerce_patterns as $rule) {
			$path = preg_replace($rule['pattern'], $rule['replacement'], $path);
		}

		/**
		 * Filter e-commerce normalization rules.
		 *
		 * @param string $path Normalized path.
		 * @param array $patterns E-commerce patterns applied.
		 */
		return apply_filters('tracksure_ecommerce_normalized_path', $path, self::$ecommerce_patterns);
	}

	/**
	 * Filter query string to keep only marketing parameters.
	 *
	 * @param string $query_string Raw query string.
	 * @param bool   $keep_marketing Keep marketing parameters.
	 * @return string Filtered query string.
	 */
	private static function filter_query_string($query_string, $keep_marketing = true)
	{
		parse_str($query_string, $params);

		if (! $keep_marketing) {
			return ''; // Remove all query params
		}

		$filtered = [];

		foreach ($params as $key => $value) {
			$key_lower = strtolower($key);

			// Keep if it's a marketing parameter
			if (in_array($key_lower, array_map('strtolower', self::$marketing_params), true)) {
				$filtered[$key] = $value;
				continue;
			}

			// Skip if it's a noise parameter
			if (in_array($key_lower, array_map('strtolower', self::$noise_params), true)) {
				continue;
			}

			// Keep other meaningful parameters (search terms, filters, etc.)
			// This is a whitelist approach - only keep non-noise params
			$filtered[$key] = $value;
		}

		/**
		 * Filter query parameters.
		 *
		 * @param array $filtered Filtered parameters.
		 * @param array $params Original parameters.
		 */
		$filtered = apply_filters('tracksure_filtered_query_params', $filtered, $params);

		return http_build_query($filtered);
	}

	/**
	 * Get clean page path (no query string).
	 *
	 * @param string $url URL to process.
	 * @return string Clean path.
	 */
	public static function get_clean_path($url)
	{
		if (empty($url)) {
			return '/';
		}

		$parsed = wp_parse_url($url);
		return $parsed['path'] ?? '/';
	}

	/**
	 * Extract marketing parameters from URL.
	 *
	 * @param string $url URL to process.
	 * @return array Marketing parameters.
	 */
	public static function extract_marketing_params($url)
	{
		if (empty($url)) {
			return [];
		}

		$parsed = wp_parse_url($url);
		if (! isset($parsed['query'])) {
			return [];
		}

		parse_str($parsed['query'], $params);
		$marketing = [];

		foreach ($params as $key => $value) {
			if (in_array(strtolower($key), array_map('strtolower', self::$marketing_params), true)) {
				$marketing[$key] = $value;
			}
		}

		return $marketing;
	}

	/**
	 * Add a marketing parameter to the whitelist.
	 * Useful for extensions adding custom tracking parameters.
	 *
	 * @param string|array $param Parameter name(s) to add.
	 */
	public static function add_marketing_param($param)
	{
		$params                 = (array) $param;
		self::$marketing_params = array_unique(array_merge(self::$marketing_params, $params));
	}

	/**
	 * Add a URL exclusion pattern.
	 * Useful for extensions adding custom exclusions.
	 *
	 * @param string|array $pattern Pattern(s) to add.
	 */
	// public static function add_exclusion_pattern( $pattern ) {
	// 	$patterns	= (array) $pattern;
	// 	self::$excluded_url_patterns	= array_unique( array_merge( self::$excluded_url_patterns, $patterns ) );

	// 	self::$marketing_params = array_unique(array_merge(self::$marketing_params, $params));
	// }

	/**
	 * Add a URL exclusion pattern.
	 * Useful for extensions adding custom exclusions.
	 *
	 * @param string|array $pattern Pattern(s) to add.
	 */
	public static function add_exclusion_pattern($pattern)
	{
		$patterns = (array) $pattern;
		self::$excluded_url_patterns = array_unique(array_merge(self::$excluded_url_patterns, $patterns));
	}
}
