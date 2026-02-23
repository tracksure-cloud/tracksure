/**
 * Cache TTL Configuration
 * 
 * Centralized cache time-to-live settings for different data types.
 * Real-time data should have short TTLs, while static data can be cached longer.
 * 
 * @package TrackSure\Admin
 * @since 2.0.0
 */

/**
 * Cache TTL in milliseconds for different data types
 */
export const CACHE_TTL = {
  /**
   * Real-time data (active users, live sessions)
   * TTL: 5 seconds
   */
  REALTIME: 5 * 1000,

  /**
   * Dashboard overview metrics
   * TTL: 1 minute
   */
  OVERVIEW: 60 * 1000,

  /**
   * Session list data
   * TTL: 1 minute
   */
  SESSIONS: 60 * 1000,

  /**
   * Journey/funnel data
   * TTL: 2 minutes
   */
  JOURNEYS: 2 * 60 * 1000,

  /**
   * Traffic sources and attribution
   * TTL: 5 minutes
   */
  TRAFFIC_SOURCES: 5 * 60 * 1000,

  /**
   * Pages analytics
   * TTL: 5 minutes
   */
  PAGES: 5 * 60 * 1000,

  /**
   * Product analytics
   * TTL: 10 minutes
   */
  PRODUCTS: 10 * 60 * 1000,

  /**
   * Goals configuration
   * TTL: 10 minutes
   */
  GOALS: 10 * 60 * 1000,

  /**
   * Settings and configuration
   * TTL: 30 minutes
   */
  SETTINGS: 30 * 60 * 1000,

  /**
   * Destinations configuration
   * TTL: 15 minutes
   */
  DESTINATIONS: 15 * 60 * 1000,

  /**
   * Integrations list
   * TTL: 30 minutes
   */
  INTEGRATIONS: 30 * 60 * 1000,

  /**
   * Diagnostics data
   * TTL: 2 minutes
   */
  DIAGNOSTICS: 2 * 60 * 1000,

  /**
   * Data quality metrics
   * TTL: 5 minutes
   */
  DATA_QUALITY: 5 * 60 * 1000,
} as const;

/**
 * Cache invalidation triggers
 * Defines when caches should be cleared
 */
export const CACHE_INVALIDATION_EVENTS = {
  /** Invalidate sessions cache when new goal is created */
  GOAL_CREATED: ['sessions', 'journeys', 'overview'],
  
  /** Invalidate relevant caches when settings change */
  SETTINGS_UPDATED: ['settings', 'destinations', 'integrations'],
  
  /** Invalidate all caches when date range changes */
  DATE_RANGE_CHANGED: ['overview', 'sessions', 'journeys', 'traffic_sources', 'pages', 'products'],
  
  /** Invalidate all caches when filter changes */
  FILTER_CHANGED: ['sessions', 'journeys', 'traffic_sources', 'pages', 'products'],
} as const;

/**
 * Get cache TTL for a specific data type
 */
export function getCacheTTL(dataType: keyof typeof CACHE_TTL): number {
  return CACHE_TTL[dataType] || 60 * 1000; // Default 1 minute
}

/**
 * Check if cache should be invalidated based on event
 */
export function shouldInvalidateCache(
  cacheKey: string,
  event: keyof typeof CACHE_INVALIDATION_EVENTS
): boolean {
  const keysToInvalidate = CACHE_INVALIDATION_EVENTS[event];
  return keysToInvalidate.some(key => cacheKey.includes(key));
}
