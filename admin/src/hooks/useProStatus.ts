/**
 * Pro Status Hook
 * 
 * Determines if Pro features are available.
 * Core provides the hook, Pro/Free extensions set the value.
 */

import { useApp } from '../contexts/AppContext';

export interface ProStatus {
  isPro: boolean;
  canUsePro: (feature: string) => boolean;
  getUpgradeUrl: () => string;
}

/**
 * Check if Pro features are enabled.
 * This is extensible - Pro plugin can override via filter.
 */
export const useProStatus = (): ProStatus => {
  const { config } = useApp();

  // Core: Check if Pro plugin is active
  // This can be set via wp_localize_script from PHP
  const isPro = config.isPro === true;

  const canUsePro = (_feature: string): boolean => {
    if (!isPro) {return false;}

    // Pro plugin can add feature-specific checks here via extension
    // e.g., check license status, plan tier, etc.
    // For now, if Pro is active, all features are available
    return true;
  };

  const getUpgradeUrl = (): string => {
    // Pro plugin can override this URL
    return config.upgradeUrl || 'https://tracksure.cloud/pricing';
  };

  return {
    isPro,
    canUsePro,
    getUpgradeUrl,
  };
};

/**
 * List of Pro-only features
 */
export const PRO_FEATURES = {
  // Goal operators
  OPERATORS_ADVANCED: 'operators_advanced', // starts_with, ends_with, regex, >=, <=
  
  // Goal triggers
  TRIGGERS_ADVANCED: 'triggers_advanced', // video_play, download, outbound_link
  
  // Goal frequency
  FREQUENCY_CONTROL: 'frequency_control', // once_per_session, once_per_day, cooldown
  
  // Attribution models
  ATTRIBUTION_LINEAR: 'attribution_linear',
  ATTRIBUTION_TIME_DECAY: 'attribution_time_decay',
  ATTRIBUTION_POSITION_BASED: 'attribution_position_based',
  
  // Templates
  TEMPLATES_PREMIUM: 'templates_premium', // Premium goal templates
} as const;

export type ProFeature = typeof PRO_FEATURES[keyof typeof PRO_FEATURES];
