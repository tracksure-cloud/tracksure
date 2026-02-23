/**
 * Location Formatters for TrackSure
 * 
 * Centralized utilities for displaying geographic location data.
 * Ensures consistent formatting across all components (DRY principle).
 * 
 * @package TrackSure\Utils
 * @since 1.3.0
 */

import { getCountryName } from './countries';

/**
 * Format full location with city, region, and country
 * 
 * Display priority:
 * 1. "City, Country Name" (if both available)
 * 2. "Region, Country Name" (if city missing but region available)
 * 3. "Country Name" (country code converted to full name)
 * 4. "Local Network" (fallback for localhost/development)
 * 
 * @param city - City name (optional)
 * @param country - Two-letter ISO country code (BA, US, etc.)
 * @param region - Region/state name (optional)
 * @returns Formatted location string
 * 
 * @example
 * formatLocation('Tuzla', 'BA') // "Tuzla, Bosnia and Herzegovina"
 * formatLocation(null, 'US', 'California') // "California, United States"
 * formatLocation(null, 'BA') // "Bosnia and Herzegovina"
 * formatLocation() // "Local Network"
 */
export function formatLocation(
  city?: string | null,
  country?: string | null,
  region?: string | null
): string {
  // If we have city and country, show "City, Country Name"
  if (city && country) {
    const countryName = getCountryName(country);
    return `${city}, ${countryName}`;
  }
  
  // If we have region and country (but no city), show "Region, Country Name"
  if (region && country) {
    const countryName = getCountryName(country);
    return `${region}, ${countryName}`;
  }
  
  // If we only have country, convert code to name
  if (country) {
    return getCountryName(country);
  }
  
  // Fallback for localhost/development (no geolocation data)
  return 'Local Network';
}

/**
 * Format short location (just city and country code)
 * 
 * Used in compact displays where full country names are too long.
 * 
 * @param city - City name (optional)
 * @param country - Two-letter ISO country code
 * @returns Formatted short location
 * 
 * @example
 * formatLocationShort('Tuzla', 'BA') // "Tuzla, BA"
 * formatLocationShort(null, 'BA') // "BA"
 * formatLocationShort() // "—"
 */
export function formatLocationShort(
  city?: string | null,
  country?: string | null
): string {
  if (city && country) {
    return `${city}, ${country}`;
  }
  
  if (country) {
    return country;
  }
  
  return '—';
}

/**
 * Check if location is local network (development environment)
 * 
 * @param country - Two-letter ISO country code
 * @returns True if local network, false otherwise
 */
export function isLocalNetwork(country?: string | null): boolean {
  return !country || country === '';
}

/**
 * Get location display with icon
 * 
 * Returns object with location text and appropriate icon name.
 * 
 * @param city - City name (optional)
 * @param country - Two-letter ISO country code
 * @param region - Region/state name (optional)
 * @returns Object with text and icon
 * 
 * @example
 * getLocationWithIcon('Tuzla', 'BA')
 * // { text: "Tuzla, Bosnia and Herzegovina", icon: "MapPin" }
 * 
 * getLocationWithIcon()
 * // { text: "Local Network", icon: "Wifi" }
 */
export function getLocationWithIcon(
  city?: string | null,
  country?: string | null,
  region?: string | null
): { text: string; icon: 'MapPin' | 'Wifi' } {
  const text = formatLocation(city, country, region);
  const icon = isLocalNetwork(country) ? 'Wifi' : 'MapPin';
  
  return { text, icon };
}

/**
 * Format location for API responses (PHP compatibility)
 * 
 * Used when converting location data from session/event records.
 * 
 * @param session - Session object with location data
 * @returns Formatted location string
 */
export function formatSessionLocation(session: {
  city?: string | null;
  country?: string | null;
  region?: string | null;
}): string {
  return formatLocation(session.city, session.country, session.region);
}
