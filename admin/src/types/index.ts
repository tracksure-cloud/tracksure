/**
 * TrackSure Global Types
 */

import type { IconName } from '../components/ui/Icon';

export interface TrackSureConfig {
  apiUrl: string;
  rest_url: string;
  apiToken: string;
  nonce: string;
  siteUrl: string;
  timezone: string;
  dateFormat: string;
  isPro: boolean;
  upgradeUrl?: string;
  enabledDestinations?: string[];
  isEcommerce?: boolean;
  destinationsMetadata?: Record<string, {
    id: string;
    name: string;
    icon: IconName;
    order: number;
    reconciliationKey?: string;
  }>;
}

export interface DateRange {
  start: Date;
  end: Date;
}

export type Theme = 'light' | 'dark' | 'auto';

export interface KpiMetric {
  label: string;
  value: number | string;
  change?: number;
  previousValue?: number | string;
  trend?: 'up' | 'down' | 'neutral';
  sparklineData?: number[];
  format?: 'number' | 'currency' | 'percent' | 'duration';
  currency?: string;
  inverseMetric?: boolean; // For metrics where lower is better (bounce rate, etc.)
}

export interface ChartDataPoint {
  date: string;
  value: number;
  compareValue?: number;
  label?: string;
}

/**
 * Suggestion from backend (REST API response)
 */
export interface SuggestionData {
  priority: 'high' | 'medium' | 'low';
  title: string;
  description: string;
  action: string;
  metric?: {
    label: string;
    value: string;
    trend: 'up' | 'down' | 'neutral';
  };
}

/**
 * Suggestion with frontend additions (id for React keys)
 */
export interface Suggestion extends SuggestionData {
  id: string;
}

export interface ExtensionRoute {
  path: string;
  element?: React.ReactNode; // Direct element (legacy)
  component?: string; // Component name for dynamic resolution
  nav: {
    group: string;
    label: string;
    order: number;
    icon?: string;
  };
}

export interface ExtensionNavGroup {
  id: string;
  label: string;
  order: number;
  icon?: string;
}

export interface ExtensionWidget {
  slot: string;
  order: number;
  element: React.ReactNode;
}

export interface TracksureExtension {
  id: string;
  name: string;
  version: string;
  routes?: ExtensionRoute[];
  navGroups?: ExtensionNavGroup[];
  widgets?: ExtensionWidget[];
}

/**
 * Window interface extensions
 */
declare global {
  interface Window {
    trackSureConfig?: {
      apiUrl: string;
      nonce: string;
      isPro?: boolean;
      proUpgradeUrl?: string;
      trackingEnabled?: boolean;
      endpoint?: string;
      sessionTimeout?: number;
      batchSize?: number;
      batchTimeout?: number;
      respectDNT?: boolean | string | number;
      destinationsMetadata?: Record<string, import('../config/destinationRegistry').DestinationMetadata>;
      enabledDestinations?: string[];
    };
    trackSure?: Record<string, unknown>;
    tracksure_goals?: Array<Record<string, unknown>>;
    TrackSureGoalConstants?: Record<string, unknown>;
    TrackSureMonitor?: boolean;
    fbq?: (...args: unknown[]) => void;
    gtag?: (...args: unknown[]) => void;
    wc?: Record<string, unknown>;
    edd?: Record<string, unknown>;
    fluentcart?: Record<string, unknown>;
    surecart?: Record<string, unknown>;
    trackSureExtensions?: Array<Record<string, unknown>>;
    trackSureProComponents?: Record<string, React.ComponentType>;
    trackSureFreeComponents?: Record<string, React.ComponentType>;
    trackSureComponents?: Record<string, React.ComponentType>;
  }
}
