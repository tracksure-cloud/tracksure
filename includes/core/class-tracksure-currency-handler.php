<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for currency conversion diagnostics, only fires when WP_DEBUG=true

/**
 * TrackSure Currency Handler
 *
 * SINGLE SOURCE OF TRUTH for all currency normalization across TrackSure.
 *
 * Architecture:
 * - Core handles ALL currency normalization
 * - Platform-specific currency acceptance lists
 * - Symbol → ISO 4217 conversion
 * - Special regulations (VEF→USD, BGN→EUR, etc.)
 *
 * Used By:
 * - All Adapters (FluentCart, WooCommerce, EDD, SureCart, etc.)
 * - All Destinations (Meta, GA4, Google Ads, TikTok, LinkedIn, etc.)
 * - JavaScript tracking (via tracksure-currency.js)
 *
 * Benefits:
 * - DRY: No duplication of currency logic
 * - Single update point for currency changes
 * - Platform-specific validation
 * - Easy to extend for new platforms
 *
 * @package TrackSure\Core
 * @since 2.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Currency Handler Class (Singleton)
 */
class TrackSure_Currency_Handler
{

	/**
	 * Singleton instance.
	 *
	 * @var TrackSure_Currency_Handler
	 */
	private static $instance = null;

	/**
	 * Currency configuration.
	 *
	 * @var TrackSure_Currency_Config
	 */
	private $config;

	/**
	 * Get singleton instance.
	 *
	 * @return TrackSure_Currency_Handler
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor (private for singleton).
	 */
	private function __construct()
	{
		$this->config = TrackSure_Currency_Config::get_instance();
	}

	/**
	 * Normalize currency code to ISO 4217 standard.
	 *
	 * Handles:
	 * 1. Currency symbols (₺, €, £, ¥, etc.) → ISO codes
	 * 2. Non-standard formats (TL, EURO, US$, etc.) → ISO codes
	 * 3. Platform-specific regulations (VEF→USD, BGN→EUR)
	 *
	 * @param string $code        Currency code (BDT, USD, €, etc.)
	 * @param string $platform    Optional platform for validation (meta, ga4, google_ads, etc.)
	 * @return string ISO 4217 compliant code
	 */
	public function normalize($code, $platform = null)
	{
		$code = strtoupper(trim($code));

		// Apply symbol and format mappings
		$normalized = $this->apply_mappings($code);

		// Validate against platform requirements if specified
		if ($platform && ! $this->is_supported_by_platform($normalized, $platform)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log(
					sprintf(
						'[TrackSure Currency] %s not supported by %s - using fallback',
						$normalized,
						$platform
					)
				);
			}
			return $this->get_platform_fallback($platform);
		}

		// Validate ISO 4217 format
		if (! $this->is_valid_iso_code($normalized)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[TrackSure Currency] Invalid currency code: ' . $normalized . ' - using USD');
			}
			return 'USD';
		}

		return $normalized;
	}

	/**
	 * Apply currency mappings (symbols → ISO, regulations, etc.)
	 *
	 * @param string $code Currency code
	 * @return string Normalized code
	 */
	private function apply_mappings($code)
	{
		$mappings = $this->config->get_currency_mappings();

		if (isset($mappings[$code])) {
			return $mappings[$code];
		}

		return $code;
	}

	/**
	 * Check if currency is supported by platform.
	 *
	 * @param string $code     ISO currency code
	 * @param string $platform Platform name (meta, ga4, google_ads, etc.)
	 * @return bool
	 */
	public function is_supported_by_platform($code, $platform)
	{
		$supported = $this->config->get_platform_currencies($platform);

		// If platform not configured, assume all currencies supported
		if (empty($supported)) {
			return true;
		}

		return in_array($code, $supported, true);
	}

	/**
	 * Get fallback currency for platform.
	 *
	 * @param string $platform Platform name
	 * @return string Fallback currency code
	 */
	private function get_platform_fallback($platform)
	{
		$fallbacks = array(
			'meta'       => 'USD',
			'ga4'        => 'USD',
			'google_ads' => 'USD',
			'tiktok'     => 'USD',
			'linkedin'   => 'USD',
			'pinterest'  => 'USD',
			'snapchat'   => 'USD',
			'twitter'    => 'USD',
			'bing_ads'   => 'USD',
		);

		return isset($fallbacks[$platform]) ? $fallbacks[$platform] : 'USD';
	}

	/**
	 * Validate ISO 4217 currency code format.
	 *
	 * @param string $code Currency code
	 * @return bool
	 */
	private function is_valid_iso_code($code)
	{
		// ISO 4217: Must be 3 alphabetic characters
		return strlen($code) === 3 && ctype_alpha($code);
	}

	/**
	 * Get all supported currencies for a platform.
	 *
	 * @param string $platform Platform name
	 * @return array Array of currency codes
	 */
	public function get_supported_currencies($platform)
	{
		return $this->config->get_platform_currencies($platform);
	}

	/**
	 * Get currency symbol for ISO code.
	 *
	 * @param string $code ISO currency code
	 * @return string Currency symbol (or code if symbol not found)
	 */
	public function get_symbol($code)
	{
		$symbols = $this->config->get_currency_symbols();
		return isset($symbols[$code]) ? $symbols[$code] : $code;
	}

	/**
	 * Convert currency mappings to JavaScript object.
	 *
	 * Used to generate tracksure-currency.js automatically.
	 *
	 * @return string JavaScript object notation
	 */
	public function to_javascript()
	{
		$mappings = $this->config->get_currency_mappings();
		return wp_json_encode($mappings);
	}
}
