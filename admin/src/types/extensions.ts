/**
 * Extension System Types
 * 
 * Allows extensions and 3rd party to extend the admin UI without modifying core.
 */

import { ComponentType } from 'react';

/**
 * Setting Field Type
 */
export interface SettingField {
  id: string;
  type: 'toggle' | 'text' | 'number' | 'select' | 'textarea' | 'password' | 'slider';
  label: string;
  description?: string;
  defaultValue: string | number | boolean;
  options?: Array<{ value: string | number; label: string }>;
  min?: number;
  max?: number;
  step?: number;
  unit?: string;
  sensitive?: boolean;
  readonly?: boolean;
  validation?: (value: string | number | boolean) => string | null;
}

/**
 * Settings Section (grouping of fields)
 */
export interface SettingsSection {
  id: string;
  category: 'tracking' | 'privacy' | 'performance' | 'attribution' | 'advanced';
  title: string;
  description?: string;
  icon?: string;
  fields: SettingField[];
  order?: number;
}

/**
 * Destination Configuration
 */
export interface DestinationConfig {
  id: string;
  name: string;
  description: string;
  icon?: string;
  enabled: boolean;
  enabledKey?: string; // Schema key for enable/disable toggle (e.g., 'meta_capi_enabled')
  custom_config?: string; // Name of custom React component (e.g., 'GoogleAdsDestinationConfig')
  fields: SettingField[];
  testFunction?: (config: Record<string, unknown>) => Promise<{ success: boolean; message: string }>;
  order?: number;
}

/**
 * Integration Configuration
 */
export interface IntegrationConfig {
  id: string;
  name: string;
  description: string;
  icon?: string;
  enabled: boolean;
  enabledKey?: string; // Schema key for enable/disable toggle (e.g., 'woo_integration_enabled')
  detected?: boolean; // Whether the auto-detected plugin is currently active (resolved server-side)
  fields: SettingField[];
  events?: string[]; // Events this integration tracks
  order?: number;
}

/**
 * Dashboard Widget
 */
export interface DashboardWidget {
  id: string;
  slot: 'overview' | 'realtime' | 'sidebar' | 'bottom';
  title: string;
  component: ComponentType<Record<string, unknown>>;
  order?: number;
}

/**
 * Custom Page Route
 */
export interface CustomPage {
  id: string;
  path: string;
  title: string;
  icon?: string;
  component: ComponentType<Record<string, unknown>>;
  navGroup?: string;
  order?: number;
}

/**
 * Main Extension Interface
 */
export interface TrackSureExtension {
  id: string;
  name: string;
  version: string;
  settings?: SettingsSection[];
  destinations?: DestinationConfig[];
  integrations?: IntegrationConfig[];
  widgets?: DashboardWidget[];
  pages?: CustomPage[];
}

/**
 * Extension Registry State
 */
export interface ExtensionRegistryState {
  extensions: TrackSureExtension[];
  settingsSections: SettingsSection[];
  destinations: DestinationConfig[];
  integrations: IntegrationConfig[];
  widgets: Record<string, DashboardWidget[]>;
  pages: CustomPage[];
}
