/**
 * TrackSure MetricToggle Component
 * 
 * Toggle between different metrics (Sessions/Users/Pageviews/Revenue).
 */

import React from 'react';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/MetricToggle.css';

export type MetricType = 'sessions' | 'users' | 'pageviews' | 'revenue';

export interface MetricOption {
  value: MetricType;
  label: string;
  icon?: string;
}

export interface MetricToggleProps {
  value: MetricType;
  onChange: (value: MetricType) => void;
  options?: MetricOption[];
  className?: string;
}

const DEFAULT_OPTIONS: MetricOption[] = [
  { value: 'sessions', label: __("Sessions"), icon: '📊' },
  { value: 'users', label: __("Users"), icon: '👥' },
  { value: 'pageviews', label: __("Pageviews"), icon: '👁️' },
  { value: 'revenue', label: __("Revenue"), icon: '💰' },
];

export const MetricToggle: React.FC<MetricToggleProps> = ({
  value,
  onChange,
  options = DEFAULT_OPTIONS,
  className = '',
}) => {
  return (
    <div className={`ts-metric-toggle ${className}`}>
      {options.map((option) => (
        <button
          key={option.value}
          className={`ts-metric-toggle__button ${
            value === option.value ? 'ts-metric-toggle__button--active' : ''
          }`}
          onClick={() => onChange(option.value)}
          type="button"
        >
          {option.icon && (
            <span className="ts-metric-toggle__icon">{option.icon}</span>
          )}
          <span className="ts-metric-toggle__label">{option.label}</span>
        </button>
      ))}
    </div>
  );
};
