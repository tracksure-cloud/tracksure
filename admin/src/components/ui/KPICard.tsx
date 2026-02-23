/**
 * KPI Card Component
 * Displays key performance indicators with trends, comparisons, and sparklines
 */

import React, { useMemo } from 'react';
import type { KpiMetric } from '../../types';
import { __ } from '../../utils/i18n';
import { getConfigCurrency } from '../../utils/parameterFormatters';
import '../../styles/components/ui/KPICard.css';

interface KPICardProps {
  metric: KpiMetric;
  isHero?: boolean;
}

export const KPICard: React.FC<KPICardProps> = ({ metric, isHero = false }) => {
  const formatValue = () => {
    const { value, format = 'number', currency = getConfigCurrency() } = metric;

    if (typeof value === 'string') {
      return value;
    }

    switch (format) {
      case 'currency':
        return new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency,
          minimumFractionDigits: 0,
          maximumFractionDigits: 2,
        }).format(value);
      case 'percent':
        return `${value.toFixed(1)}%`;
      case 'duration': {
        const minutes = Math.floor(value / 60);
        const seconds = Math.floor(value % 60);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
      }
      default:
        return new Intl.NumberFormat('en-US').format(value);
    }
  };

  const formatPreviousValue = () => {
    const { previousValue, format = 'number', currency = getConfigCurrency() } = metric;
    
    if (!previousValue) { return ''; }
    if (typeof previousValue === 'string') { return previousValue; }

    switch (format) {
      case 'currency':
        return new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency,
          minimumFractionDigits: 0,
          maximumFractionDigits: 2,
        }).format(previousValue);
      case 'percent':
        return `${previousValue.toFixed(1)}%`;
      case 'duration': {
        const minutes = Math.floor(previousValue / 60);
        const seconds = Math.floor(previousValue % 60);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
      }
      default:
        return previousValue.toLocaleString();
    }
  };

  const renderChange = () => {
    if (metric.change === undefined) {
      return null;
    }

    // For inverse metrics (like bounce rate), lower is better
    const isInverse = metric.inverseMetric || false;
    const actualChange = isInverse ? -metric.change : metric.change;
    
    const isPositive = actualChange > 0;
    const isNeutral = actualChange === 0;
    const formattedPrevious = formatPreviousValue();

    return (
      <div className={`ts-kpi-change ${isPositive ? 'positive' : isNeutral ? 'neutral' : 'negative'}`}>
        {!isNeutral && (isPositive ? '↑' : '↓')}
        {Math.abs(metric.change).toFixed(1)}%
        {formattedPrevious && (
          <span className="ts-kpi-previous">
            {' '}vs {formattedPrevious}
          </span>
        )}
      </div>
    );
  };

  // Generate SVG sparkline path
  const sparklinePath = useMemo(() => {
    if (!metric.sparklineData || metric.sparklineData.length === 0) {
      return null;
    }

    const data = metric.sparklineData;
    
    // Need at least 2 data points for a line
    if (data.length === 1) {
      return null;
    }

    const width = 100;
    const height = 32;
    const padding = 2;
    
    const max = Math.max(...data, 1);
    const min = Math.min(...data, 0);
    const range = max - min || 1;
    
    const points = data.map((value, index) => {
      const x = (index / (data.length - 1)) * (width - padding * 2) + padding;
      const y = height - ((value - min) / range) * (height - padding * 2) - padding;
      return `${x},${y}`;
    });

    return `M ${points.join(' L ')}`;
  }, [metric.sparklineData]);

  // Get trend color for sparkline
  const sparklineColor = useMemo(() => {
    if (!metric.trend) {
      return 'var(--ts-chart-1)';
    }
    
    switch (metric.trend) {
      case 'up':
        return 'var(--ts-success)';
      case 'down':
        return 'var(--ts-danger)';
      default:
        return 'var(--ts-text-muted)';
    }
  }, [metric.trend]);

  return (
    <div className={`ts-kpi-card ${isHero ? 'ts-kpi-card--hero' : ''}`}>
      <div className="ts-kpi-header">
        <div className="ts-kpi-label">{metric.label}</div>
        {renderChange()}
      </div>
      
      <div className="ts-kpi-value">{formatValue()}</div>
      
      {sparklinePath && (
        <div className="ts-kpi-sparkline-container">
          <svg
            className="ts-kpi-sparkline"
            viewBox="0 0 100 32"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <defs>
              <linearGradient id={`sparkline-gradient-${metric.label}`} x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor={sparklineColor} stopOpacity="0.2" />
                <stop offset="100%" stopColor={sparklineColor} stopOpacity="0.05" />
              </linearGradient>
            </defs>
            
            {/* Area fill */}
            <path
              d={`${sparklinePath} L 100,32 L 0,32 Z`}
              fill={`url(#sparkline-gradient-${metric.label})`}
              opacity="0.3"
            />
            
            {/* Line */}
            <path
              d={sparklinePath}
              fill="none"
              stroke={sparklineColor}
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              vectorEffect="non-scaling-stroke"
            />
          </svg>
        </div>
      )}
    </div>
  );
};
