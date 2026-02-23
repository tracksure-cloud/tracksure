<?php

/**
 * TrackSure Currency Configuration
 *
 * CENTRALIZED CONFIGURATION for all currency data.
 *
 * Contains:
 * - Currency symbols → ISO 4217 mappings
 * - Platform-specific accepted currencies (Meta, GA4, Google Ads, etc.)
 * - Special regulations (VEF→USD, BGN→EUR, etc.)
 * - Non-standard format mappings (TL→TRY, EURO→EUR, etc.)
 *
 * Last Updated: February 2026 (Based on Meta, GA4, Google Ads documentation)
 *
 * @package TrackSure\Core
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currency Configuration Class (Singleton)
 */
class TrackSure_Currency_Config {

	/**
	 * Singleton instance.
	 *
	 * @var TrackSure_Currency_Config
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return TrackSure_Currency_Config
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get currency symbol/format → ISO 4217 mappings.
	 *
	 * This is the SINGLE SOURCE OF TRUTH for all currency normalization.
	 *
	 * @return array Currency mappings
	 */
	public function get_currency_mappings() {
		return array(
			// ========== SPECIAL PLATFORM REGULATIONS ==========
			'VEF'     => 'USD',  // Venezuelan Bolivar → USD (Meta regulation)
			'BGN'     => 'EUR',  // Bulgarian Lev → Euro (Bulgaria adopted EUR Jan 2026)

			// ========== CURRENCY SYMBOLS → ISO CODES ==========
			'₺'       => 'TRY',  // Turkish Lira
			'€'       => 'EUR',  // Euro
			'£'       => 'GBP',  // British Pound
			'¥'       => 'JPY',  // Japanese Yen (also CNY, but JPY more common)
			'₹'       => 'INR',  // Indian Rupee
			'₦'       => 'NGN',  // Nigerian Naira
			'₪'       => 'ILS',  // Israeli Shekel
			'₩'       => 'KRW',  // South Korean Won
			'฿'       => 'THB',  // Thai Baht
			'₱'       => 'PHP',  // Philippine Peso
			'₽'       => 'RUB',  // Russian Ruble
			'₴'       => 'UAH',  // Ukrainian Hryvnia
			'₫'       => 'VND',  // Vietnamese Dong
			'R'       => 'ZAR',  // South African Rand
			'R$'      => 'BRL',  // Brazilian Real
			'$'       => 'USD',  // Generic dollar (ambiguous - default USD)
			'A$'      => 'AUD',  // Australian Dollar
			'C$'      => 'CAD',  // Canadian Dollar
			'NZ$'     => 'NZD',  // New Zealand Dollar
			'HK$'     => 'HKD',  // Hong Kong Dollar
			'S$'      => 'SGD',  // Singapore Dollar
			'NT$'     => 'TWD',  // New Taiwan Dollar
			'kr'      => 'SEK',  // Swedish Krona (also NOK, DKK, ISK - default SEK)
			'zł'      => 'PLN',  // Polish Zloty
			'Kč'      => 'CZK',  // Czech Koruna
			'Ft'      => 'HUF',  // Hungarian Forint
			'lei'     => 'RON',  // Romanian Leu
			'RM'      => 'MYR',  // Malaysian Ringgit
			'Rp'      => 'IDR',  // Indonesian Rupiah
			'SR'      => 'SAR',  // Saudi Riyal
			'QR'      => 'QAR',  // Qatari Riyal
			'DA'      => 'DZD',  // Algerian Dinar
			'E£'      => 'EGP',  // Egyptian Pound
			'KSh'     => 'KES',  // Kenyan Shilling

			// ========== NON-STANDARD TEXT FORMATS → ISO CODES ==========
			'TL'      => 'TRY',  // Old Turkish Lira code
			'CNH'     => 'CNY',  // Chinese Yuan Offshore → Onshore
			'EURO'    => 'EUR',  // Common variation
			'US$'     => 'USD',
			'USD$'    => 'USD',
			'CA$'     => 'CAD',
			'AU$'     => 'AUD',
			'NZ$'     => 'NZD',
			'HK$'     => 'HKD',
			'SG$'     => 'SGD',
			'DOLLAR'  => 'USD',
			'DOLLARS' => 'USD',
			'POUND'   => 'GBP',
			'POUNDS'  => 'GBP',
			''        => 'USD',   // Empty → USD
		);
	}

	/**
	 * Get ISO code → Symbol mappings (reverse lookup).
	 *
	 * @return array Symbol mappings
	 */
	public function get_currency_symbols() {
		return array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
			'CNY' => '¥',
			'INR' => '₹',
			'NGN' => '₦',
			'ILS' => '₪',
			'KRW' => '₩',
			'THB' => '฿',
			'PHP' => '₱',
			'RUB' => '₽',
			'UAH' => '₴',
			'VND' => '₫',
			'TRY' => '₺',
			'BRL' => 'R$',
			'ZAR' => 'R',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'NZD' => 'NZ$',
			'HKD' => 'HK$',
			'SGD' => 'S$',
			'TWD' => 'NT$',
			'SEK' => 'kr',
			'NOK' => 'kr',
			'DKK' => 'kr',
			'PLN' => 'zł',
			'CZK' => 'Kč',
			'HUF' => 'Ft',
			'RON' => 'lei',
			'MYR' => 'RM',
			'IDR' => 'Rp',
			'SAR' => 'SR',
			'QAR' => 'QR',
			'DZD' => 'DA',
			'EGP' => 'E£',
			'KES' => 'KSh',
		);
	}

	/**
	 * Get supported currencies by platform.
	 *
	 * Platform Currency Requirements (as of February 2026):
	 * - Meta: 60 currencies
	 * - GA4: ~135 currencies (almost all ISO 4217)
	 * - Google Ads: ~50 currencies
	 * - TikTok: ~40 currencies
	 * - LinkedIn: ~20 currencies
	 * - Pinterest: ~30 currencies
	 *
	 * @param string $platform Platform name
	 * @return array Supported currency codes
	 */
	public function get_platform_currencies( $platform ) {
		$currencies = array(
			// Meta Ads (Facebook, Instagram, WhatsApp) - 60 currencies
			'meta'       => array(
				'DZD',
				'ARS',
				'AUD',
				'BDT',
				'BOB',
				'GBP',
				'BRL',
				'CAD',
				'CLP',
				'CNY',
				'COP',
				'CRC',
				'CZK',
				'DKK',
				'EGP',
				'EUR',
				'GTQ',
				'HNL',
				'HKD',
				'HUF',
				'ISK',
				'INR',
				'IDR',
				'ILS',
				'JPY',
				'KES',
				'MOP',
				'MYR',
				'MXN',
				'TWD',
				'NZD',
				'NIO',
				'NGN',
				'NOK',
				'PKR',
				'PYG',
				'PEN',
				'PHP',
				'PLN',
				'QAR',
				'RON',
				'RUB',
				'SAR',
				'SGD',
				'ZAR',
				'KRW',
				'LKR',
				'SEK',
				'CHF',
				'THB',
				'TRY',
				'AED',
				'UAH',
				'UYU',
				'USD',
				'VND',
			),

			// Google Analytics 4 - Accepts almost all ISO 4217 codes (~135 currencies)
			// Using null to indicate "accept all valid ISO 4217 codes"
			'ga4'        => null,

			// Google Ads - ~50 currencies (subset of Meta)
			'google_ads' => array(
				'ARS',
				'AUD',
				'BDT',
				'GBP',
				'BRL',
				'CAD',
				'CLP',
				'CNY',
				'COP',
				'CZK',
				'DKK',
				'EGP',
				'EUR',
				'HKD',
				'HUF',
				'INR',
				'IDR',
				'ILS',
				'JPY',
				'KES',
				'MYR',
				'MXN',
				'TWD',
				'NZD',
				'NGN',
				'NOK',
				'PKR',
				'PEN',
				'PHP',
				'PLN',
				'RON',
				'RUB',
				'SAR',
				'SGD',
				'ZAR',
				'KRW',
				'SEK',
				'CHF',
				'THB',
				'TRY',
				'AED',
				'UAH',
				'USD',
				'VND',
			),

			// TikTok Ads - ~40 currencies
			'tiktok'     => array(
				'ARS',
				'AUD',
				'BRL',
				'CAD',
				'CLP',
				'CNY',
				'COP',
				'CZK',
				'DKK',
				'EUR',
				'HKD',
				'HUF',
				'INR',
				'IDR',
				'ILS',
				'JPY',
				'MYR',
				'MXN',
				'TWD',
				'NZD',
				'NOK',
				'PHP',
				'PLN',
				'GBP',
				'RON',
				'RUB',
				'SAR',
				'SGD',
				'ZAR',
				'KRW',
				'SEK',
				'CHF',
				'THB',
				'TRY',
				'AED',
				'USD',
				'VND',
			),

			// LinkedIn Ads - ~20 currencies
			'linkedin'   => array(
				'AUD',
				'BRL',
				'CAD',
				'CNY',
				'DKK',
				'EUR',
				'HKD',
				'INR',
				'JPY',
				'MXN',
				'NZD',
				'NOK',
				'GBP',
				'SEK',
				'CHF',
				'TWD',
				'TRY',
				'USD',
				'ZAR',
			),

			// Pinterest Ads - ~30 currencies
			'pinterest'  => array(
				'ARS',
				'AUD',
				'BRL',
				'CAD',
				'CLP',
				'CNY',
				'COP',
				'CZK',
				'DKK',
				'EUR',
				'HKD',
				'HUF',
				'INR',
				'JPY',
				'MXN',
				'NZD',
				'NOK',
				'PLN',
				'GBP',
				'RON',
				'RUB',
				'SEK',
				'SGD',
				'CHF',
				'TWD',
				'THB',
				'TRY',
				'USD',
				'ZAR',
			),

			// Snapchat Ads - Similar to Meta
			'snapchat'   => array(
				'AUD',
				'BRL',
				'CAD',
				'CNY',
				'DKK',
				'EUR',
				'HKD',
				'INR',
				'JPY',
				'MXN',
				'NZD',
				'NOK',
				'GBP',
				'SEK',
				'SGD',
				'CHF',
				'TWD',
				'TRY',
				'USD',
				'ZAR',
			),

			// Twitter (X) Ads - ~25 currencies
			'twitter'    => array(
				'AUD',
				'BRL',
				'CAD',
				'CNY',
				'DKK',
				'EUR',
				'HKD',
				'INR',
				'JPY',
				'MXN',
				'NZD',
				'NOK',
				'GBP',
				'SEK',
				'SGD',
				'CHF',
				'TWD',
				'TRY',
				'USD',
				'ZAR',
			),

			// Microsoft Bing Ads - Similar to Google Ads
			'bing_ads'   => array(
				'ARS',
				'AUD',
				'BRL',
				'CAD',
				'CLP',
				'CNY',
				'COP',
				'CZK',
				'DKK',
				'EGP',
				'EUR',
				'HKD',
				'HUF',
				'INR',
				'IDR',
				'ILS',
				'JPY',
				'MYR',
				'MXN',
				'NZD',
				'NOK',
				'PHP',
				'PLN',
				'GBP',
				'RON',
				'RUB',
				'SAR',
				'SGD',
				'ZAR',
				'KRW',
				'SEK',
				'CHF',
				'THB',
				'TRY',
				'AED',
				'USD',
				'VND',
			),
		);

		$platform = strtolower( $platform );
		return isset( $currencies[ $platform ] ) ? $currencies[ $platform ] : null;
	}

	/**
	 * Get all platforms that support a specific currency.
	 *
	 * @param string $currency_code ISO currency code
	 * @return array Platform names that support this currency
	 */
	public function get_platforms_for_currency( $currency_code ) {
		$currency_code = strtoupper( $currency_code );
		$platforms     = array();

		$all_platforms = array( 'meta', 'ga4', 'google_ads', 'tiktok', 'linkedin', 'pinterest', 'snapchat', 'twitter', 'bing_ads' );

		foreach ( $all_platforms as $platform ) {
			$supported = $this->get_platform_currencies( $platform );

			// null means accepts all ISO 4217 (like GA4)
			if ( null === $supported || in_array( $currency_code, $supported, true ) ) {
				$platforms[] = $platform;
			}
		}

		return $platforms;
	}
}
