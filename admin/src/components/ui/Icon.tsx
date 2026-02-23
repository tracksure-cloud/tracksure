/**
 * TrackSure Icon Component
 * 
 * Unified icon system using Lucide React with dark/light theme support.
 * Provides abstraction layer for core, free, pro, and 3rd-party extensions.
 * 
 * Tree-shaken imports: Only loads icons actually used in the application.
 * Bundle size: ~35KB (vs ~200KB for all Lucide icons)
 * 
 * @package TrackSure\Admin
 * @since 2.0.0
 */

import React from 'react';
import { iconRegistry, type IconName } from './iconRegistry';

export type { IconName };

/**
 * Icon component props
 */
export interface IconProps {
  /**
   * Icon name from Lucide React library
   */
  name: IconName;
  
  /**
   * Icon size in pixels (default: 20)
   */
  size?: number;
  
  /**
   * Stroke width (default: 1.5)
   * Use 2 for bold icons, 1 for thin icons
   */
  strokeWidth?: number;
  
  /**
   * Additional CSS classes
   */
  className?: string;
  
  /**
   * Accessible label for screen readers
   */
  'aria-label'?: string;
  
  /**
   * Color override (uses CSS variables by default)
   * Can be: 'primary', 'success', 'warning', 'danger', 'muted', or any CSS color
   */
  color?: string;
  
  /**
   * Whether icon should adapt to dark/light theme
   * Default: true
   */
  themeAdaptive?: boolean;
}

/**
 * Color mapping for semantic colors
 */
const semanticColors: Record<string, string> = {
  primary: 'var(--ts-primary)',
  success: 'var(--ts-success)',
  warning: 'var(--ts-warning)',
  danger: 'var(--ts-danger)',
  muted: 'var(--ts-text-muted)',
};

/**
 * Icon Component
 * 
 * @example
 * // Basic usage
 * <Icon name="BarChart2" />
 * 
 * @example
 * // With custom size and color
 * <Icon name="Package" size={24} color="primary" />
 * 
 * @example
 * // With accessibility label
 * <Icon name="ShoppingCart" aria-label="Shopping Cart" />
 */
export const Icon: React.FC<IconProps> = ({
  name,
  size = 20,
  strokeWidth = 1.5,
  className = '',
  'aria-label': ariaLabel,
  color,
  themeAdaptive = true,
}) => {
  const LucideIcon = iconRegistry[name];
  
  if (!LucideIcon) {
    if (process.env.NODE_ENV !== 'production') {
      console.warn(`[TrackSure Icon] Icon "${name}" not found in icon registry`);
    }
    return null;
  }

  // Resolve color
  const iconColor = color ? (semanticColors[color] || color) : undefined;

  // Build class names
  const classes = [
    'ts-icon',
    themeAdaptive ? 'ts-icon--theme-adaptive' : '',
    className,
  ].filter(Boolean).join(' ');

  return React.createElement(LucideIcon, {
    size,
    strokeWidth,
    className: classes,
    'aria-label': ariaLabel,
    style: iconColor ? { color: iconColor } : undefined,
  });
};

/**
 * Icon with label wrapper
 * Useful for navigation items, stat cards, etc.
 */
export interface IconWithLabelProps extends IconProps {
  label: string;
  labelPosition?: 'right' | 'bottom';
}

export const IconWithLabel: React.FC<IconWithLabelProps> = ({
  label,
  labelPosition = 'right',
  ...iconProps
}) => {
  const flexDirection = labelPosition === 'bottom' ? 'column' : 'row';
  
  return (
    <span 
      className={`ts-icon-with-label ts-icon-with-label--${labelPosition}`}
      style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', flexDirection }}
    >
      <Icon {...iconProps} />
      <span>{label}</span>
    </span>
  );
};
