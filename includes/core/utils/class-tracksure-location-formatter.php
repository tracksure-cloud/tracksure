<?php

/**
 * TrackSure Location Formatter Utility
 *
 * Single source of truth for location formatting across the plugin.
 * Companion to TypeScript's admin/src/utils/locationFormatters.ts
 *
 * Used for:
 * - Email notifications: "New visitor from Tuzla, Bosnia and Herzegovina"
 * - PDF/CSV exports: Full country names instead of codes
 * - Server-rendered admin widgets
 * - Debug logs (when WP_DEBUG is enabled)
 * - Webhook payloads to external services
 * - Pro/Free extensions that need consistent location display
 *
 * Usage Examples:
 * ```php
 * // Format location with full country name
 * $location = TrackSure_Location_Formatter::format('Tuzla', 'BA', 'Tuzla Canton');
 * // Returns: "Tuzla, Bosnia and Herzegovina"
 *
 * // Short format (city + code)
 * $short = TrackSure_Location_Formatter::format_short('Tuzla', 'BA');
 * // Returns: "Tuzla, BA"
 *
 * // Check if localhost
 * $is_local = TrackSure_Location_Formatter::is_local_network(null);
 * // Returns: true
 * ```
 *
 * Extensibility:
 * Free/Pro/3rd party plugins can extend functionality using filters:
 * - tracksure_location_format
 * - tracksure_location_format_short
 * - tracksure_location_separator
 *
 * @package TrackSure
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TrackSure Location Formatter Class
 *
 * @since 1.0.0
 */
class TrackSure_Location_Formatter {


	/**
	 * Format location for display (PHP version of TypeScript's formatLocation)
	 *
	 * Priority hierarchy:
	 * 1. City + Country Name (if both available)
	 * 2. Region + Country Name (if city missing)
	 * 3. Country Name only
	 * 4. "Local Network" (if no country data)
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $city    City name (e.g., 'Tuzla', 'New York').
	 * @param string|null $country ISO 3166-1 alpha-2 country code (e.g., 'BA', 'US').
	 * @param string|null $region  Region/state name (e.g., 'Tuzla Canton', 'California').
	 * @return string Formatted location string.
	 */
	public static function format( $city = null, $country = null, $region = null ) {
		// Priority 1: City + Country (most common and useful)
		if ( ! empty( $city ) && ! empty( $country ) ) {
			$separator = self::get_separator();
			$formatted = $city . $separator . TrackSure_Countries::get_name( $country );

			/**
			 * Filter formatted location with city.
			 *
			 * Allows Free/Pro/3rd party to customize location display.
			 *
			 * @since 1.0.0
			 *
			 * @param string      $formatted Formatted location string.
			 * @param string      $city      City name.
			 * @param string      $country   ISO country code.
			 * @param string|null $region    Region/state name.
			 */
			return apply_filters( 'tracksure_location_format', $formatted, $city, $country, $region );
		}

		// Priority 2: Region + Country (fallback when city unavailable)
		if ( ! empty( $region ) && ! empty( $country ) ) {
			$separator = self::get_separator();
			$formatted = $region . $separator . TrackSure_Countries::get_name( $country );

			return apply_filters( 'tracksure_location_format', $formatted, null, $country, $region );
		}

		// Priority 3: Country only
		if ( ! empty( $country ) ) {
			$formatted = TrackSure_Countries::get_name( $country );

			return apply_filters( 'tracksure_location_format', $formatted, null, $country, null );
		}

		// Priority 4: No location data (localhost/VPN/unknown)
		$fallback = __( 'Local Network', 'tracksure' );

		/**
		 * Filter local network fallback text.
		 *
		 * @since 1.0.0
		 *
		 * @param string $fallback Default fallback text.
		 */
		return apply_filters( 'tracksure_location_format_local', $fallback );
	}

	/**
	 * Format location in short format (compact display)
	 *
	 * Returns: "City, CODE" or "CODE" or "—"
	 * Useful for: Table cells, compact widgets, mobile displays
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $city    City name.
	 * @param string|null $country ISO country code.
	 * @return string Short formatted location.
	 */
	public static function format_short( $city = null, $country = null ) {
		$separator = self::get_separator();

		if ( ! empty( $city ) && ! empty( $country ) ) {
			$formatted = $city . $separator . strtoupper( $country );

			/**
			 * Filter short location format.
			 *
			 * @since 1.0.0
			 *
			 * @param string $formatted Short formatted location.
			 * @param string $city      City name.
			 * @param string $country   ISO country code.
			 */
			return apply_filters( 'tracksure_location_format_short', $formatted, $city, $country );
		}

		if ( ! empty( $country ) ) {
			$formatted = strtoupper( $country );
			return apply_filters( 'tracksure_location_format_short', $formatted, null, $country );
		}

		// No data available
		$empty = '—';

		/**
		 * Filter empty location placeholder.
		 *
		 * @since 1.0.0
		 *
		 * @param string $empty Default empty placeholder.
		 */
		return apply_filters( 'tracksure_location_format_empty', $empty );
	}

	/**
	 * Check if location represents local network
	 *
	 * Local networks include:
	 * - Null/empty country codes
	 * - Special codes: 'XX', 'LOCAL'
	 * - Localhost IP addresses (127.0.0.1, ::1)
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $country Country code to check.
	 * @return bool True if local network, false otherwise.
	 */
	public static function is_local_network( $country = null ) {
		if ( empty( $country ) ) {
			return true;
		}

		// Special local/unknown codes
		$local_codes = array( 'XX', 'LOCAL', 'ZZ' );

		/**
		 * Filter local network country codes.
		 *
		 * Allows adding custom codes that should be treated as local.
		 *
		 * @since 1.0.0
		 *
		 * @param array $local_codes Array of country codes to treat as local.
		 */
		$local_codes = apply_filters( 'tracksure_location_local_codes', $local_codes );

		return in_array( strtoupper( $country ), $local_codes, true );
	}

	/**
	 * Get location separator (between city and country)
	 *
	 * Default: ", " (comma + space)
	 * Allows customization for different locales/formats
	 *
	 * @since 1.0.0
	 *
	 * @return string Location separator.
	 */
	public static function get_separator() {
		/**
		 * Filter location separator.
		 *
		 * Allows customization for different locales:
		 * - Western: ", " (comma space)
		 * - Some Asian locales: "、" (ideographic comma)
		 * - Minimal: " " (space only)
		 *
		 * @since 1.0.0
		 *
		 * @param string $separator Default separator.
		 */
		return apply_filters( 'tracksure_location_separator', ', ' );
	}

	/**
	 * Format location for email notifications
	 *
	 * Adds optional emoji flag for visual appeal
	 * Example: "🇧🇦 Tuzla, Bosnia and Herzegovina"
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $city         City name.
	 * @param string|null $country      ISO country code.
	 * @param string|null $region       Region/state name.
	 * @param bool        $include_flag Whether to include emoji flag (default: false).
	 * @return string Formatted location for email.
	 */
	public static function format_for_email( $city = null, $country = null, $region = null, $include_flag = false ) {
		$formatted = self::format( $city, $country, $region );

		if ( $include_flag && ! empty( $country ) ) {
			$flag = self::get_flag_emoji( $country );
			if ( ! empty( $flag ) ) {
				$formatted = $flag . ' ' . $formatted;
			}
		}

		/**
		 * Filter email location format.
		 *
		 * @since 1.0.0
		 *
		 * @param string      $formatted    Formatted location.
		 * @param string|null $city         City name.
		 * @param string|null $country      Country code.
		 * @param string|null $region       Region name.
		 * @param bool        $include_flag Include flag emoji.
		 */
		return apply_filters( 'tracksure_location_format_email', $formatted, $city, $country, $region, $include_flag );
	}

	/**
	 * Get emoji flag for country code
	 *
	 * Converts 2-letter ISO code to emoji flag
	 * Example: 'BA' → '🇧🇦', 'US' → '🇺🇸'
	 *
	 * @since 1.0.0
	 *
	 * @param string $country_code ISO 3166-1 alpha-2 code.
	 * @return string Emoji flag or empty string if invalid.
	 */
	public static function get_flag_emoji( $country_code ) {
		if ( empty( $country_code ) || strlen( $country_code ) !== 2 ) {
			return '';
		}

		// Regional indicator symbols (Unicode)
		// Convert A-Z to 🇦-🇿 (U+1F1E6 to U+1F1FF)
		$code   = strtoupper( $country_code );
		$first  = mb_chr( ord( $code[0] ) - ord( 'A' ) + 0x1F1E6 );
		$second = mb_chr( ord( $code[1] ) - ord( 'A' ) + 0x1F1E6 );

		if ( $first === false || $second === false ) {
			return '';
		}

		$flag = $first . $second;

		/**
		 * Filter country flag emoji.
		 *
		 * Allows customization or disabling flags.
		 *
		 * @since 1.0.0
		 *
		 * @param string $flag         Generated emoji flag.
		 * @param string $country_code ISO country code.
		 */
		return apply_filters( 'tracksure_location_flag_emoji', $flag, $country_code );
	}

	/**
	 * Format location for export (CSV, PDF, etc.)
	 *
	 * Always uses full country names, no codes
	 * No special characters that might break CSV parsing
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $city    City name.
	 * @param string|null $country Country code.
	 * @param string|null $region  Region name.
	 * @return string Export-safe location string.
	 */
	public static function format_for_export( $city = null, $country = null, $region = null ) {
		$formatted = self::format( $city, $country, $region );

		// Remove any special characters that might break CSV
		$formatted = str_replace( array( '"', "'", "\n", "\r", "\t" ), '', $formatted );

		/**
		 * Filter export location format.
		 *
		 * @since 1.0.0
		 *
		 * @param string      $formatted Formatted location.
		 * @param string|null $city      City name.
		 * @param string|null $country   Country code.
		 * @param string|null $region    Region name.
		 */
		return apply_filters( 'tracksure_location_format_export', $formatted, $city, $country, $region );
	}

	/**
	 * Format location array from session/event data
	 *
	 * Helper method for working with database rows
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Session or event data array with city/country/region keys.
	 * @return string Formatted location.
	 */
	public static function format_from_data( $data ) {
		if ( ! is_array( $data ) ) {
			return self::format();
		}

		$city    = isset( $data['city'] ) ? $data['city'] : null;
		$country = isset( $data['country'] ) ? $data['country'] : null;
		$region  = isset( $data['region'] ) ? $data['region'] : null;

		return self::format( $city, $country, $region );
	}
}
