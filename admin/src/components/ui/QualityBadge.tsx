import React from 'react';
import { Icon } from './Icon';
import type { IconName } from './Icon';
import '../../styles/components/ui/QualityBadge.css';

export interface QualityBadgeProps {
  level: 'high' | 'medium' | 'low';
  metric?: string;
  tooltip?: string;
  compact?: boolean;
}

/**
 * Quality Badge Component
 * 
 * Displays data quality/confidence indicators for metrics
 * - High: Green - Strong signal, confident data
 * - Medium: Yellow - Moderate signal, some noise
 * - Low: Red - Weak signal, interpret with caution
 */
export const QualityBadge: React.FC<QualityBadgeProps> = ({
  level,
  metric,
  tooltip,
  compact = false,
}) => {
  const labels = {
    high: 'High Signal',
    medium: 'Medium Signal',
    low: 'Low Signal',
  };

  const icons: Record<'high' | 'medium' | 'low', IconName> = {
    high: 'CheckCircle',
    medium: 'AlertTriangle',
    low: 'AlertCircle',
  };

  const defaultTooltips = {
    high: 'Strong data quality. Metrics are reliable and actionable.',
    medium: 'Moderate data quality. Consider additional validation.',
    low: 'Limited data. Results may not be statistically significant.',
  };

  const displayTooltip = tooltip || defaultTooltips[level];

  return (
    <div
      className={`ts-quality-badge ts-quality-badge--${level} ${compact ? 'ts-quality-badge--compact' : ''}`}
      title={displayTooltip}
    >
      <Icon name={icons[level]} size={16} className="ts-quality-badge__icon" />
      {!compact && (
        <>
          <span className="ts-quality-badge__label">{labels[level]}</span>
          {metric && <span className="ts-quality-badge__metric">({metric})</span>}
        </>
      )}
    </div>
  );
};

/**
 * Calculate quality level based on sample size
 */
export const calculateQualityLevel = (
  sampleSize: number,
  minHighThreshold: number = 1000,
  minMediumThreshold: number = 100
): 'high' | 'medium' | 'low' => {
  if (sampleSize >= minHighThreshold) {return 'high';}
  if (sampleSize >= minMediumThreshold) {return 'medium';}
  return 'low';
};

/**
 * Calculate quality level based on conversion rate confidence
 */
export const calculateConversionQuality = (
  conversions: number,
  sessions: number
): 'high' | 'medium' | 'low' => {
  // For conversion rates, we need minimum conversions for statistical significance
  if (conversions >= 100 && sessions >= 1000) {return 'high';}
  if (conversions >= 30 && sessions >= 300) {return 'medium';}
  return 'low';
};
