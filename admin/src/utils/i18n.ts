/**
 * Internationalization (i18n) utilities for TrackSure Admin
 * 
 * This file provides TypeScript-friendly wrappers around WordPress i18n functions.
 * All user-facing strings must be wrapped in these functions for translation support.
 * 
 * @package TrackSure\Core
 * @since 1.0.0
 */

import { 
  __ as wp__,
  _n as wp_n,
  _x as wp_x,
  sprintf as wpSprintf 
} from '@wordpress/i18n';

/**
 * Text domain for TrackSure
 */
const TEXT_DOMAIN = 'tracksure';

/**
 * Translate a string
 * 
 * @param text - The string to translate
 * @param domain - Optional text domain (defaults to 'tracksure')
 * @returns Translated string (or original text if translation fails)
 * 
 * @example
 * __('Overview')
 * __('Sessions')
 * __('Custom text', 'my-domain') // With custom domain
 */
export function __(text: string, domain?: string): string {
  try {
    const translated = wp__(text, domain || TEXT_DOMAIN);
    return translated || text; // Fallback to original text if translation returns undefined
  } catch (error) {
    console.warn('TrackSure i18n: Translation failed for "' + text + '"', error);
    return text; // Fallback to original text
  }
}

/**
 * Translate a string with context
 * 
 * @param text - The string to translate
 * @param context - Context to help translators
 * @returns Translated string
 * 
 * @example
 * _x('Post', 'noun') // vs 'Post' as verb
 */
export function _x(text: string, context: string): string {
  return wp_x(text, context, TEXT_DOMAIN);
}

/**
 * Translate a string with singular/plural forms
 * 
 * @param singular - Singular form
 * @param plural - Plural form
 * @param count - Number to determine singular vs plural
 * @returns Translated string
 * 
 * @example
 * _n('1 session', '%d sessions', count)
 */
export function _n(singular: string, plural: string, count: number): string {
  return wp_n(singular, plural, count, TEXT_DOMAIN);
}

/**
 * Format a translated string with sprintf-style placeholders
 * 
 * @param text - Translated string with placeholders
 * @param args - Values to replace placeholders
 * @returns Formatted string
 * 
 * @example
 * sprintf(__('%s sessions in the last %d days'), 'Google', 30)
 */
export function sprintf(text: string, ...args: (string | number)[]): string {
  return wpSprintf(text, ...args);
}
