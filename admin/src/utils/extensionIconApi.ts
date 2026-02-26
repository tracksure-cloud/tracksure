/**
 * Extension Icon API
 * 
 * Utilities for extensions to integrate with TrackSure's icon system.
 * Provides three integration methods:
 * 1. Use core Lucide icons from registry
 * 2. Add custom SVG icons
 * 3. Use WordPress Dashicons
 * 
 * @package TrackSure\Admin
 * @since 2.0.0
 */

import React, { ComponentType } from 'react';
import type { IconName } from '../components/ui/Icon';
import { registerCustomIcon } from '../config/iconRegistry';

/**
 * Custom SVG icon component registry
 */
const customSvgIcons = new Map<string, ComponentType<Record<string, unknown>>>();

/**
 * Register a custom SVG icon component
 * 
 * @param iconId - Unique identifier for the icon
 * @param IconComponent - React component that renders the SVG
 * 
 * @example
 * const MyCustomIcon = () => (
 *   <svg width="20" height="20" viewBox="0 0 20 20">
 *     <path d="..." />
 *   </svg>
 * );
 * 
 * registerSvgIcon('my-feature-icon', MyCustomIcon);
 */
export const registerSvgIcon = (iconId: string, IconComponent: ComponentType<Record<string, unknown>>): void => {
  customSvgIcons.set(iconId, IconComponent);
};

/**
 * Get a custom SVG icon component
 * 
 * @param iconId - Icon identifier
 * @returns Icon component or null if not found
 */
export const getSvgIcon = (iconId: string): ComponentType<Record<string, unknown>> | null => {
  return customSvgIcons.get(iconId) || null;
};

/**
 * Icon type discriminator
 */
export type IconType = 'lucide' | 'svg' | 'dashicon';

/**
 * Extension icon configuration
 */
export interface ExtensionIconConfig {
  /**
   * Icon type
   */
  type: IconType;
  
  /**
   * Icon identifier
   * - For 'lucide': IconName from Lucide React
   * - For 'svg': Custom icon ID registered via registerSvgIcon()
   * - For 'dashicon': WordPress Dashicon class (e.g., 'dashicons-chart-area')
   */
  icon: string;
  
  /**
   * Accessible label for screen readers
   */
  'aria-label'?: string;
  
  /**
   * Icon size (default: 20)
   */
  size?: number;
  
  /**
   * Icon color (uses theme colors by default)
   */
  color?: string;
}

/**
 * Render an extension icon
 * Handles all three icon types (Lucide, custom SVG, Dashicon)
 * 
 * @param config - Icon configuration
 * @returns React element or null
 * 
 * @example
 * // Use core Lucide icon
 * renderExtensionIcon({ type: 'lucide', icon: 'Rocket' });
 * 
 * @example
 * // Use custom SVG icon
 * renderExtensionIcon({ type: 'svg', icon: 'my-feature-icon' });
 * 
 * @example
 * // Use WordPress Dashicon
 * renderExtensionIcon({ type: 'dashicon', icon: 'dashicons-chart-area' });
 */
export const renderExtensionIcon = (config: ExtensionIconConfig): React.ReactElement | null => {
  const { type, icon, 'aria-label': ariaLabel, size = 20, color } = config;
  
  switch (type) {
    case 'lucide': {
      // Lazy import to avoid circular dependencies
      // eslint-disable-next-line @typescript-eslint/no-var-requires
      const { Icon } = require('../components/ui/Icon');
      return React.createElement(Icon, {
        name: icon as IconName,
        'aria-label': ariaLabel,
        size,
        color,
      });
    }
    
    case 'svg': {
      const SvgIcon = getSvgIcon(icon);
      if (!SvgIcon) {
        console.warn(`[TrackSure Extension API] Custom SVG icon "${icon}" not found`);
        return null;
      }
      return React.createElement(SvgIcon, {
        'aria-label': ariaLabel,
        width: size,
        height: size,
        style: color ? { color } : undefined,
      });
    }
    
    case 'dashicon': {
      return React.createElement('span', {
        className: `dashicons ${icon}`,
        'aria-label': ariaLabel,
        style: {
          fontSize: `${size}px`,
          width: `${size}px`,
          height: `${size}px`,
          color: color || 'currentColor',
        },
      });
    }
    
    default:
      console.warn(`[TrackSure Extension API] Unknown icon type "${type}"`);
      return null;
  }
};

/**
 * Helper to create a Lucide icon configuration
 * 
 * @example
 * const config = lucideIcon('Rocket', 'My Feature');
 */
export const lucideIcon = (
  icon: IconName,
  ariaLabel?: string,
  size?: number,
  color?: string
): ExtensionIconConfig => ({
  type: 'lucide',
  icon,
  'aria-label': ariaLabel,
  size,
  color,
});

/**
 * Helper to create a custom SVG icon configuration
 * 
 * @example
 * const config = svgIcon('my-feature-icon', 'My Feature');
 */
export const svgIcon = (
  icon: string,
  ariaLabel?: string,
  size?: number,
  color?: string
): ExtensionIconConfig => ({
  type: 'svg',
  icon,
  'aria-label': ariaLabel,
  size,
  color,
});

/**
 * Helper to create a Dashicon configuration
 * 
 * @example
 * const config = dashicon('dashicons-chart-area', 'Analytics');
 */
export const dashicon = (
  icon: string,
  ariaLabel?: string,
  size?: number,
  color?: string
): ExtensionIconConfig => ({
  type: 'dashicon',
  icon,
  'aria-label': ariaLabel,
  size,
  color,
});

/**
 * Export for extensions to use
 */
export {
  registerCustomIcon,
  type IconName,
};
