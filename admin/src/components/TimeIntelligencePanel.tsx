/**
 * Time Intelligence Panel
 * Smart temporal insights: best converting day, peak hours, weekend vs weekday
 */

import React, { useMemo } from 'react';
import { Icon } from './ui/Icon';
import { __ } from '../utils/i18n';
import '../styles/components/TimeIntelligencePanel.css';

interface TimeIntelligenceData {
  best_converting_day?: {
    day: string;
    conversion_rate: number;
    conversions: number;
  };
  peak_hours?: Array<{
    hour: number;
    visitors: number;
    conversions: number;
  }>;
  weekend_vs_weekday?: {
    weekend: {
      visitors: number;
      conversions: number;
      conversion_rate: number;
    };
    weekday: {
      visitors: number;
      conversions: number;
      conversion_rate: number;
    };
  };
}

interface TimeIntelligencePanelProps {
  data?: TimeIntelligenceData;
  isLoading?: boolean;
}

const DAY_NAMES: Record<string, string> = {
  monday: __('Monday'),
  tuesday: __('Tuesday'),
  wednesday: __('Wednesday'),
  thursday: __('Thursday'),
  friday: __('Friday'),
  saturday: __('Saturday'),
  sunday: __('Sunday'),
};

export const TimeIntelligencePanel: React.FC<TimeIntelligencePanelProps> = ({ data, isLoading = false }) => {
  // Format hour to 12-hour format
  const formatHour = (hour: number): string => {
    if (hour === 0) { return '12 AM'; }
    if (hour === 12) { return '12 PM'; }
    if (hour < 12) { return `${hour} AM`; }
    return `${hour - 12} PM`;
  };

  // Get peak hours range (top 3 hours with most conversions)
  const peakHoursRange = useMemo(() => {
    if (!data?.peak_hours || data.peak_hours.length === 0) {
      return null;
    }

    // Sort by conversions
    const sorted = [...data.peak_hours].sort((a, b) => b.conversions - a.conversions);
    const topHours = sorted.slice(0, 3);

    if (topHours.length === 0) {
      return null;
    }

    // Get min and max hours for range display
    const hours = topHours.map(h => h.hour).sort((a, b) => a - b);
    const minHour = hours[0];
    const maxHour = hours[hours.length - 1];

    return {
      range: `${formatHour(minHour)} - ${formatHour(maxHour)}`,
      total_conversions: topHours.reduce((sum, h) => sum + h.conversions, 0),
      hours: topHours,
    };
  }, [data?.peak_hours]);

  // Calculate weekend vs weekday performance comparison
  const weekendComparison = useMemo(() => {
    if (!data?.weekend_vs_weekday) {
      return null;
    }

    const { weekend, weekday } = data.weekend_vs_weekday;
    const difference = weekend.conversion_rate - weekday.conversion_rate;
    const percentageDiff = weekday.conversion_rate > 0 
      ? (difference / weekday.conversion_rate) * 100
      : 0;

    return {
      winner: weekend.conversion_rate > weekday.conversion_rate ? 'weekend' : 'weekday',
      difference: Math.abs(percentageDiff),
      weekend_rate: weekend.conversion_rate,
      weekday_rate: weekday.conversion_rate,
    };
  }, [data?.weekend_vs_weekday]);

  if (isLoading) {
    return (
      <div className="ts-time-intelligence-panel ts-loading">
        <div className="ts-panel-header">
          <Icon name="Clock" size={20} />
          <h3>{__('Time Intelligence')}</h3>
        </div>
        <div className="ts-panel-content">
          <div className="ts-skeleton-insight"></div>
          <div className="ts-skeleton-insight"></div>
          <div className="ts-skeleton-insight"></div>
        </div>
      </div>
    );
  }

  if (!data || (!data.best_converting_day && !peakHoursRange && !weekendComparison)) {
    return null;
  }

  return (
    <div className="ts-time-intelligence-panel">
      <div className="ts-panel-header">
        <Icon name="Clock" size={20} />
        <h3>{__('Time Intelligence')}</h3>
        <span className="ts-panel-badge">{__('Insights')}</span>
      </div>

      <div className="ts-panel-content">
        {/* Best Converting Day */}
        {data.best_converting_day && (
          <div className="ts-insight-card ts-highlight">
            <div className="ts-insight-icon">
              <Icon name="Calendar" size={24} color="primary" />
            </div>
            <div className="ts-insight-content">
              <div className="ts-insight-label">{__('Best Converting Day')}</div>
              <div className="ts-insight-value">
                {DAY_NAMES[data.best_converting_day.day.toLowerCase()] || data.best_converting_day.day}
              </div>
              <div className="ts-insight-detail">
                {data.best_converting_day.conversion_rate.toFixed(1)}% {__('conversion rate')} 
                ({data.best_converting_day.conversions} {__('conversions')})
              </div>
            </div>
          </div>
        )}

        {/* Peak Hours */}
        {peakHoursRange && (
          <div className="ts-insight-card">
            <div className="ts-insight-icon">
              <Icon name="TrendingUp" size={24} color="success" />
            </div>
            <div className="ts-insight-content">
              <div className="ts-insight-label">{__('Peak Conversion Hours')}</div>
              <div className="ts-insight-value">{peakHoursRange.range}</div>
              <div className="ts-insight-detail">
                {peakHoursRange.total_conversions} {__('conversions during peak hours')}
              </div>
            </div>
          </div>
        )}

        {/* Weekend vs Weekday */}
        {weekendComparison && (
          <div className="ts-insight-card">
            <div className="ts-insight-icon">
              <Icon name="BarChart3" size={24} color="warning" />
            </div>
            <div className="ts-insight-content">
              <div className="ts-insight-label">{__('Weekend vs Weekday')}</div>
              <div className="ts-insight-value">
                {weekendComparison.winner === 'weekend' ? __('Weekend') : __('Weekday')} 
                {' '}{__('Performs Better')}
              </div>
              <div className="ts-insight-detail">
                {weekendComparison.difference.toFixed(1)}% {__('higher conversion rate')}
                {' '}({weekendComparison.winner === 'weekend' 
                  ? weekendComparison.weekend_rate.toFixed(1)
                  : weekendComparison.weekday_rate.toFixed(1)}%)
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="ts-panel-footer">
        <Icon name="Info" size={14} />
        <span>{__('Insights based on last 30 days of data')}</span>
      </div>
    </div>
  );
};
