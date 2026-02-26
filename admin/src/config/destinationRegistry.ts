/**
 * Destination Registry
 * 
 * 100% DYNAMIC - No hardcoded destinations!
 * All metadata comes from server-side PHP registration.
 * Works for all registered destinations automatically.
 */

import type { IconName } from '../components/ui/Icon';

export interface DestinationMetadata {
  id: string;
  name: string;
  icon: IconName;
  order: number;
  reconciliationKey?: string;
}

/**
 * Get all destinations from server-provided config.
 * 
 * PHP Destinations Manager registers destinations with full metadata,
 * Settings Schema injects into window.trackSureConfig.destinationsMetadata.
 * React consumes dynamically - NO HARDCODING!
 */
export const getAllDestinations = (): Record<string, DestinationMetadata> => {
  const config = window.trackSureConfig;
  return config?.destinationsMetadata || {};
};

/**
 * Get enabled destination IDs.
 * 
 * Returns simple array of IDs from server config.
 */
export const getEnabledDestinationIds = (): string[] => {
  const typedWindow = window as Window & { trackSureConfig?: Record<string, unknown> };
  const config = typedWindow.trackSureConfig || {};
  return (config as { enabledDestinations?: string[] }).enabledDestinations || [];
};

/**
 * Get destination metadata by ID.
 */
export const getDestinationMeta = (destId: string): DestinationMetadata | null => {
  const allDestinations = getAllDestinations();
  return allDestinations[destId] || null;
};

/**
 * Get enabled destinations with metadata, sorted by order.
 * 
 * Used by DataQualityPage, DiagnosticsPage, etc for dynamic rendering.
 */
export const getEnabledDestinationsMeta = (enabledIds?: string[]): DestinationMetadata[] => {
  const allDestinations = getAllDestinations();
  const ids = enabledIds || getEnabledDestinationIds();
  
  return ids
    .map(id => allDestinations[id])
    .filter(Boolean)
    .sort((a, b) => a.order - b.order);
};
