/**
 * Type declarations for @wordpress/i18n
 * 
 * The official types only support single-argument __() but WordPress
 * standard is to use text domain as second argument.
 */
declare module '@wordpress/i18n' {
  /**
   * Translate a string.
   * 
   * @param text - Text to translate
   * @param domain - Text domain (optional, defaults to 'default')
   * @returns Translated text
   */
  export function __(text: string, domain?: string): string;

  /**
   * Translate a string with context.
   * 
   * @param text - Text to translate
   * @param context - Context for the translation
   * @param domain - Text domain (optional)
   * @returns Translated text
   */
  export function _x(text: string, context: string, domain?: string): string;

  /**
   * Translate and retrieve the singular or plural form.
   * 
   * @param single - Singular form
   * @param plural - Plural form
   * @param number - Number to determine singular/plural
   * @param domain - Text domain (optional)
   * @returns Translated text
   */
  export function _n(single: string, plural: string, number: number, domain?: string): string;

  /**
   * Translate and retrieve the singular or plural form with context.
   * 
   * @param single - Singular form
   * @param plural - Plural form
   * @param number - Number to determine singular/plural
   * @param context - Context for the translation
   * @param domain - Text domain (optional)
   * @returns Translated text
   */
  export function _nx(
    single: string,
    plural: string,
    number: number,
    context: string,
    domain?: string
  ): string;

  /**
   * Set current locale data.
   */
  export function setLocaleData(data: any, domain?: string): void;

  /**
   * Check if translations are loaded for domain.
   */
  export function hasTranslation(single: string, context?: string, domain?: string): boolean;

  /**
   * Format a string with placeholders.
   * 
   * @param format - Format string with %s, %d placeholders
   * @param args - Arguments to replace placeholders
   * @returns Formatted string
   */
  export function sprintf(format: string, ...args: any[]): string;
}
