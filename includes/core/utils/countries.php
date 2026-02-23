<?php

/**
 *
 * Countries List - Centralized country code to name mapping (PHP version)
 * ISO 3166-1 alpha-2 country codes (195+ countries)
 *
 * Companion file to: admin/src/utils/countries.ts
 *
 * Usage:
 * require_once 'countries.php';
 * $name = TrackSure_Countries::get_name('US'); // Returns "United States"
 *
 * @package TrackSure
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * TrackSure Countries Utility Class
 */
class TrackSure_Countries {



	/**
	 * ISO 3166-1 alpha-2 country codes to names mapping
	 *
	 * @var array<string, string>
	 */
	private static $countries = array(
		// A
		'AD' => 'Andorra',
		'AE' => 'United Arab Emirates',
		'AF' => 'Afghanistan',
		'AG' => 'Antigua and Barbuda',
		'AI' => 'Anguilla',
		'AL' => 'Albania',
		'AM' => 'Armenia',
		'AO' => 'Angola',
		'AQ' => 'Antarctica',
		'AR' => 'Argentina',
		'AS' => 'American Samoa',
		'AT' => 'Austria',
		'AU' => 'Australia',
		'AW' => 'Aruba',
		'AX' => 'Åland Islands',
		'AZ' => 'Azerbaijan',

		// B
		'BA' => 'Bosnia and Herzegovina',
		'BB' => 'Barbados',
		'BD' => 'Bangladesh',
		'BE' => 'Belgium',
		'BF' => 'Burkina Faso',
		'BG' => 'Bulgaria',
		'BH' => 'Bahrain',
		'BI' => 'Burundi',
		'BJ' => 'Benin',
		'BL' => 'Saint Barthélemy',
		'BM' => 'Bermuda',
		'BN' => 'Brunei',
		'BO' => 'Bolivia',
		'BQ' => 'Caribbean Netherlands',
		'BR' => 'Brazil',
		'BS' => 'Bahamas',
		'BT' => 'Bhutan',
		'BV' => 'Bouvet Island',
		'BW' => 'Botswana',
		'BY' => 'Belarus',
		'BZ' => 'Belize',

		// C
		'CA' => 'Canada',
		'CC' => 'Cocos (Keeling) Islands',
		'CD' => 'Congo (DRC)',
		'CF' => 'Central African Republic',
		'CG' => 'Congo (Republic)',
		'CH' => 'Switzerland',
		'CI' => 'Côte d\'Ivoire',
		'CK' => 'Cook Islands',
		'CL' => 'Chile',
		'CM' => 'Cameroon',
		'CN' => 'China',
		'CO' => 'Colombia',
		'CR' => 'Costa Rica',
		'CU' => 'Cuba',
		'CV' => 'Cape Verde',
		'CW' => 'Curaçao',
		'CX' => 'Christmas Island',
		'CY' => 'Cyprus',
		'CZ' => 'Czech Republic',

		// D
		'DE' => 'Germany',
		'DJ' => 'Djibouti',
		'DK' => 'Denmark',
		'DM' => 'Dominica',
		'DO' => 'Dominican Republic',
		'DZ' => 'Algeria',

		// E
		'EC' => 'Ecuador',
		'EE' => 'Estonia',
		'EG' => 'Egypt',
		'EH' => 'Western Sahara',
		'ER' => 'Eritrea',
		'ES' => 'Spain',
		'ET' => 'Ethiopia',

		// F
		'FI' => 'Finland',
		'FJ' => 'Fiji',
		'FK' => 'Falkland Islands',
		'FM' => 'Micronesia',
		'FO' => 'Faroe Islands',
		'FR' => 'France',

		// G
		'GA' => 'Gabon',
		'GB' => 'United Kingdom',
		'GD' => 'Grenada',
		'GE' => 'Georgia',
		'GF' => 'French Guiana',
		'GG' => 'Guernsey',
		'GH' => 'Ghana',
		'GI' => 'Gibraltar',
		'GL' => 'Greenland',
		'GM' => 'Gambia',
		'GN' => 'Guinea',
		'GP' => 'Guadeloupe',
		'GQ' => 'Equatorial Guinea',
		'GR' => 'Greece',
		'GS' => 'South Georgia',
		'GT' => 'Guatemala',
		'GU' => 'Guam',
		'GW' => 'Guinea-Bissau',
		'GY' => 'Guyana',

		// H
		'HK' => 'Hong Kong',
		'HM' => 'Heard Island',
		'HN' => 'Honduras',
		'HR' => 'Croatia',
		'HT' => 'Haiti',
		'HU' => 'Hungary',

		// I
		'ID' => 'Indonesia',
		'IE' => 'Ireland',
		'IL' => 'Israel',
		'IM' => 'Isle of Man',
		'IN' => 'India',
		'IO' => 'British Indian Ocean Territory',
		'IQ' => 'Iraq',
		'IR' => 'Iran',
		'IS' => 'Iceland',
		'IT' => 'Italy',

		// J
		'JE' => 'Jersey',
		'JM' => 'Jamaica',
		'JO' => 'Jordan',
		'JP' => 'Japan',

		// K
		'KE' => 'Kenya',
		'KG' => 'Kyrgyzstan',
		'KH' => 'Cambodia',
		'KI' => 'Kiribati',
		'KM' => 'Comoros',
		'KN' => 'Saint Kitts and Nevis',
		'KP' => 'North Korea',
		'KR' => 'South Korea',
		'KW' => 'Kuwait',
		'KY' => 'Cayman Islands',
		'KZ' => 'Kazakhstan',

		// L
		'LA' => 'Laos',
		'LB' => 'Lebanon',
		'LC' => 'Saint Lucia',
		'LI' => 'Liechtenstein',
		'LK' => 'Sri Lanka',
		'LR' => 'Liberia',
		'LS' => 'Lesotho',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'LV' => 'Latvia',
		'LY' => 'Libya',

		// M
		'MA' => 'Morocco',
		'MC' => 'Monaco',
		'MD' => 'Moldova',
		'ME' => 'Montenegro',
		'MF' => 'Saint Martin',
		'MG' => 'Madagascar',
		'MH' => 'Marshall Islands',
		'MK' => 'North Macedonia',
		'ML' => 'Mali',
		'MM' => 'Myanmar',
		'MN' => 'Mongolia',
		'MO' => 'Macao',
		'MP' => 'Northern Mariana Islands',
		'MQ' => 'Martinique',
		'MR' => 'Mauritania',
		'MS' => 'Montserrat',
		'MT' => 'Malta',
		'MU' => 'Mauritius',
		'MV' => 'Maldives',
		'MW' => 'Malawi',
		'MX' => 'Mexico',
		'MY' => 'Malaysia',
		'MZ' => 'Mozambique',

		// N
		'NA' => 'Namibia',
		'NC' => 'New Caledonia',
		'NE' => 'Niger',
		'NF' => 'Norfolk Island',
		'NG' => 'Nigeria',
		'NI' => 'Nicaragua',
		'NL' => 'Netherlands',
		'NO' => 'Norway',
		'NP' => 'Nepal',
		'NR' => 'Nauru',
		'NU' => 'Niue',
		'NZ' => 'New Zealand',

		// O
		'OM' => 'Oman',

		// P
		'PA' => 'Panama',
		'PE' => 'Peru',
		'PF' => 'French Polynesia',
		'PG' => 'Papua New Guinea',
		'PH' => 'Philippines',
		'PK' => 'Pakistan',
		'PL' => 'Poland',
		'PM' => 'Saint Pierre and Miquelon',
		'PN' => 'Pitcairn Islands',
		'PR' => 'Puerto Rico',
		'PS' => 'Palestine',
		'PT' => 'Portugal',
		'PW' => 'Palau',
		'PY' => 'Paraguay',

		// Q
		'QA' => 'Qatar',

		// R
		'RE' => 'Réunion',
		'RO' => 'Romania',
		'RS' => 'Serbia',
		'RU' => 'Russia',
		'RW' => 'Rwanda',

		// S
		'SA' => 'Saudi Arabia',
		'SB' => 'Solomon Islands',
		'SC' => 'Seychelles',
		'SD' => 'Sudan',
		'SE' => 'Sweden',
		'SG' => 'Singapore',
		'SH' => 'Saint Helena',
		'SI' => 'Slovenia',
		'SJ' => 'Svalbard and Jan Mayen',
		'SK' => 'Slovakia',
		'SL' => 'Sierra Leone',
		'SM' => 'San Marino',
		'SN' => 'Senegal',
		'SO' => 'Somalia',
		'SR' => 'Suriname',
		'SS' => 'South Sudan',
		'ST' => 'São Tomé and Príncipe',
		'SV' => 'El Salvador',
		'SX' => 'Sint Maarten',
		'SY' => 'Syria',
		'SZ' => 'Eswatini',

		// T
		'TC' => 'Turks and Caicos Islands',
		'TD' => 'Chad',
		'TF' => 'French Southern Territories',
		'TG' => 'Togo',
		'TH' => 'Thailand',
		'TJ' => 'Tajikistan',
		'TK' => 'Tokelau',
		'TL' => 'Timor-Leste',
		'TM' => 'Turkmenistan',
		'TN' => 'Tunisia',
		'TO' => 'Tonga',
		'TR' => 'Turkey',
		'TT' => 'Trinidad and Tobago',
		'TV' => 'Tuvalu',
		'TW' => 'Taiwan',
		'TZ' => 'Tanzania',

		// U
		'UA' => 'Ukraine',
		'UG' => 'Uganda',
		'UM' => 'U.S. Minor Outlying Islands',
		'US' => 'United States',
		'UY' => 'Uruguay',
		'UZ' => 'Uzbekistan',

		// V
		'VA' => 'Vatican City',
		'VC' => 'Saint Vincent and the Grenadines',
		'VE' => 'Venezuela',
		'VG' => 'British Virgin Islands',
		'VI' => 'U.S. Virgin Islands',
		'VN' => 'Vietnam',
		'VU' => 'Vanuatu',

		// W
		'WF' => 'Wallis and Futuna',
		'WS' => 'Samoa',

		// Y
		'YE' => 'Yemen',
		'YT' => 'Mayotte',

		// Z
		'ZA' => 'South Africa',
		'ZM' => 'Zambia',
		'ZW' => 'Zimbabwe',
	);

	/**
	 * Get full country name from ISO 3166-1 alpha-2 code
	 *
	 * @param string $country_code Two-letter ISO country code (e.g., "US", "GB").
	 * @return string Full country name or uppercase code if not found.
	 */
	public static function get_name( $country_code ) {
		if ( empty( $country_code ) ) {
			return 'Unknown';
		}

		$code = strtoupper( $country_code );
		return isset( self::$countries[ $code ] ) ? self::$countries[ $code ] : $code;
	}

	/**
	 * Check if a country code exists in the database
	 *
	 * @param string $country_code Two-letter ISO country code.
	 * @return bool True if country code is valid.
	 */
	public static function is_valid( $country_code ) {
		return ! empty( $country_code ) && isset( self::$countries[ strtoupper( $country_code ) ] );
	}

	/**
	 * Get all country codes
	 *
	 * @return array Array of all ISO country codes
	 */
	public static function get_all_codes() {
		return array_keys( self::$countries );
	}

	/**
	 * Get all country names
	 *
	 * @return array Array of all country names
	 */
	public static function get_all_names() {
		return array_values( self::$countries );
	}

	/**
	 * Get all countries as associative array
	 *
	 * @return array<string, string> Country code => name mapping
	 */
	public static function get_all() {
		return self::$countries;
	}
}
