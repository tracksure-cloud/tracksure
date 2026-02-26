/**
 * TrackSure Currency Handler (JavaScript)
 *
 * CLIENT-SIDE MIRROR of class-tracksure-currency-handler.php
 *
 * SINGLE SOURCE OF TRUTH for browser-based currency normalization.
 * Keep synchronized with PHP currency configuration.
 *
 * Architecture:
 * - Generated from TrackSure_Currency_Config PHP class
 * - Used by all client-side tracking (Universal MiniCart, etc.)
 * - Handles symbols → ISO codes, special regulations
 *
 * @package TrackSure
 * @version 1.0.0
 */

(function(window) {
    'use strict';

    /**
     * Currency Handler Singleton
     */
    var TrackSureCurrency = {
        
        /**
         * Currency mappings (symbols, formats → ISO 4217)
         * 
         * AUTO-GENERATED from TrackSure_Currency_Config::get_currency_mappings()
         * Last Updated: February 2026
         */
        mappings: {
            // ========== SPECIAL PLATFORM REGULATIONS ==========
            'VEF': 'USD',    // Venezuelan Bolivar → USD (Meta regulation)
            'BGN': 'EUR',    // Bulgarian Lev → Euro (Bulgaria adopted EUR Jan 2026)
            
            // ========== CURRENCY SYMBOLS → ISO CODES ==========
            '₺': 'TRY',      // Turkish Lira
            '€': 'EUR',      // Euro
            '£': 'GBP',      // British Pound
            '¥': 'JPY',      // Japanese Yen
            '₹': 'INR',      // Indian Rupee
            '₦': 'NGN',      // Nigerian Naira
            '₪': 'ILS',      // Israeli Shekel
            '₩': 'KRW',      // South Korean Won
            '฿': 'THB',      // Thai Baht
            '₱': 'PHP',      // Philippine Peso
            '₽': 'RUB',      // Russian Ruble
            '₴': 'UAH',      // Ukrainian Hryvnia
            '₫': 'VND',      // Vietnamese Dong
            'R': 'ZAR',      // South African Rand
            'R$': 'BRL',     // Brazilian Real
            '$': 'USD',      // Generic dollar
            'A$': 'AUD',     // Australian Dollar
            'C$': 'CAD',     // Canadian Dollar
            'NZ$': 'NZD',    // New Zealand Dollar
            'HK$': 'HKD',    // Hong Kong Dollar
            'S$': 'SGD',     // Singapore Dollar
            'NT$': 'TWD',    // New Taiwan Dollar
            'kr': 'SEK',     // Swedish Krona
            'zł': 'PLN',     // Polish Zloty
            'Kč': 'CZK',     // Czech Koruna
            'Ft': 'HUF',     // Hungarian Forint
            'lei': 'RON',    // Romanian Leu
            'RM': 'MYR',     // Malaysian Ringgit
            'Rp': 'IDR',     // Indonesian Rupiah
            'SR': 'SAR',     // Saudi Riyal
            'QR': 'QAR',     // Qatari Riyal
            'DA': 'DZD',     // Algerian Dinar
            'E£': 'EGP',     // Egyptian Pound
            'KSh': 'KES',    // Kenyan Shilling
            
            // ========== NON-STANDARD TEXT FORMATS → ISO CODES ==========
            'TL': 'TRY',     // Old Turkish Lira code
            'CNH': 'CNY',    // Chinese Yuan Offshore
            'EURO': 'EUR',   // Common variation
            'US$': 'USD',
            'USD$': 'USD',
            'CA$': 'CAD',
            'AU$': 'AUD',
            'NZ$': 'NZD',
            'HK$': 'HKD',
            'SG$': 'SGD',
            'DOLLAR': 'USD',
            'DOLLARS': 'USD',
            'POUND': 'GBP',
            'POUNDS': 'GBP',
            '': 'USD'        // Empty → USD
        },

        /**
         * Normalize currency code to ISO 4217 standard.
         * 
         * @param {string} code - Currency code (BDT, €, US$, etc.)
         * @param {string} platform - Optional platform for validation (meta, ga4, etc.)
         * @returns {string} ISO 4217 currency code
         */
        normalize: function(code, platform) {
            if (!code || typeof code !== 'string') {
                return 'USD';
            }
            
            code = code.toUpperCase().trim();
            
            // Apply mappings
            var normalized = this.mappings[code] || code;
            
            // Validate ISO 4217 format (3 letters)
            if (normalized.length !== 3 || !/^[A-Z]+$/.test(normalized)) {
                console.warn('[TrackSure Currency] Invalid currency code:', normalized, '- using USD');
                return 'USD';
            }
            
            return normalized;
        },

        /**
         * Check if currency code is valid ISO 4217 format.
         * 
         * @param {string} code - Currency code
         * @returns {boolean}
         */
        isValid: function(code) {
            return code && code.length === 3 && /^[A-Z]+$/.test(code);
        },

        /**
         * Get currency symbol for ISO code.
         * 
         * @param {string} code - ISO currency code
         * @returns {string} Currency symbol (or code if not found)
         */
        getSymbol: function(code) {
            var symbols = {
                'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CNY': '¥',
                'INR': '₹', 'NGN': '₦', 'ILS': '₪', 'KRW': '₩', 'THB': '฿',
                'PHP': '₱', 'RUB': '₽', 'UAH': '₴', 'VND': '₫', 'TRY': '₺',
                'BRL': 'R$', 'ZAR': 'R', 'AUD': 'A$', 'CAD': 'C$', 'NZD': 'NZ$',
                'HKD': 'HK$', 'SGD': 'S$', 'TWD': 'NT$', 'SEK': 'kr', 'NOK': 'kr',
                'DKK': 'kr', 'PLN': 'zł', 'CZK': 'Kč', 'HUF': 'Ft', 'RON': 'lei',
                'MYR': 'RM', 'IDR': 'Rp', 'SAR': 'SR', 'QAR': 'QR', 'DZD': 'DA',
                'EGP': 'E£', 'KES': 'KSh'
            };
            
            return symbols[code] || code;
        }
    };

    // Export to global scope
    window.TrackSureCurrency = TrackSureCurrency;

})(window);
