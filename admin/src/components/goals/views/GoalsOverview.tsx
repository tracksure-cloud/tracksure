/**
 * Goals Overview Dashboard
 * 
 * Displays high-level goals performance metrics with:
 * - KPI summary cards (Total Conversions, Conversion Value, Conversion Rate, Active Goals)
 * - Conversions trend chart (last 30 days)
 * - Top 5 performing goals
 * 
 * Features:
 * - Real-time data fetching with useApiQuery
 * - Loading skeletons
 * - Error states with retry
 * - Full dark/light theme support
 * - Responsive design
 * - Translatable (i18n)
 * 
 * @package TrackSure
 * @since 2.1.0
 */

import React, { useMemo } from 'react';
import { useApp } from '../../../contexts/AppContext';
import { useApiQuery } from '../../../hooks/useApiQuery';
import { KPICard } from '../../ui/KPICard';
import { Card, CardHeader, CardBody } from '../../ui';
import { SkeletonKPI } from '../../ui/Skeleton';
import { Icon } from '../../ui/Icon';
import { __ } from '../../../utils/i18n';
import { formatLocalDate } from '../../../utils/parameterFormatters';
import type { GoalsOverview as GoalsOverviewType } from '../../../types/goals';
import './GoalsOverview.css';

/**
 * Goals Overview Dashboard Component
 * 
 * @since 2.1.0
 */
export const GoalsOverview: React.FC = () => {
  const { dateRange } = useApp();

  // Fetch overview data
  const { data, isLoading, error, refetch } = useApiQuery<GoalsOverviewType>(
    'getGoalsOverview',
    {
      start_date: formatLocalDate(dateRange.start),
      end_date: formatLocalDate(dateRange.end),
    }
  );

  // Calculate trend badges
  const conversionsTrend = useMemo(() => {
    if (!data?.conversions_trend) {return null;}
    const current = data.total_conversions;
    const previous = data.conversions_trend.previous_period || 0;
    if (previous === 0) {return { direction: 'neutral' as const, percentage: 0 };}
    
    const change = ((current - previous) / previous) * 100;
    return {
      direction: change > 0 ? 'up' as const : change < 0 ? 'down' as const : 'neutral' as const,
      percentage: Math.abs(Math.round(change)),
    };
  }, [data]);

  const valueTrend = useMemo(() => {
    if (!data?.value_trend) {return null;}
    const current = data.total_value;
    const previous = data.value_trend.previous_period || 0;
    if (previous === 0) {return { direction: 'neutral' as const, percentage: 0 };}
    
    const change = ((current - previous) / previous) * 100;
    return {
      direction: change > 0 ? 'up' as const : change < 0 ? 'down' as const : 'neutral' as const,
      percentage: Math.abs(Math.round(change)),
    };
  }, [data]);

  const rateTrend = useMemo(() => {
    if (!data?.rate_trend) {return null;}
    const current = data.conversion_rate;
    const previous = data.rate_trend.previous_period || 0;
    if (previous === 0) {return { direction: 'neutral' as const, percentage: 0 };}
    
    const change = ((current - previous) / previous) * 100;
    return {
      direction: change > 0 ? 'up' as const : change < 0 ? 'down' as const : 'neutral' as const,
      percentage: Math.abs(Math.round(change)),
    };
  }, [data]);

  // Loading state
  if (isLoading) {
    return (
      <div className="ts-goals-overview">
        <div className="ts-goals-overview__kpis">
          <SkeletonKPI />
          <SkeletonKPI />
          <SkeletonKPI />
          <SkeletonKPI />
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <Card>
        <CardBody>
          <div className="ts-empty-state ts-empty-state--error">
            <Icon name="AlertCircle" size={48} color="var(--ts-error)" />
            <h3>{__('Failed to load goals overview', 'tracksure')}</h3>
            <p>{__('There was an error loading the dashboard data.', 'tracksure')}</p>
            <button onClick={refetch} className="ts-btn ts-btn--primary">
              {__('Retry', 'tracksure')}
            </button>
          </div>
        </CardBody>
      </Card>
    );
  }

  if (!data) {
    return null;
  }

  return (
    <div className="ts-goals-overview">
      {/* KPI Cards Row */}
      <div className="ts-goals-overview__kpis">
        <KPICard
          metric={{
            label: __('All Conversions', 'tracksure'),
            value: data.total_conversions,
            format: 'number',
            change: conversionsTrend?.percentage || 0,
          }}
        />
        <KPICard
          metric={{
            label: __('Conversion Value', 'tracksure'),
            value: data.total_value,
            format: 'currency',
            change: valueTrend?.percentage || 0,
          }}
        />
        <KPICard
          metric={{
            label: __('Avg Conversion Rate', 'tracksure'),
            value: data.conversion_rate,
            format: 'percent',
            change: rateTrend?.percentage || 0,
          }}
        />
        <KPICard
          metric={{
            label: __('Active Goals', 'tracksure'),
            value: data.active_goals,
            format: 'number',
          }}
        />
      </div>

      {/* Conversions Trend Chart */}
      {data.daily_conversions && data.daily_conversions.length > 0 && (
        <Card className="ts-goals-overview__chart">
          <CardHeader>
            <h3>{__('Conversions Trend', 'tracksure')}</h3>
            <span className="ts-card-header__subtitle">
              {__('Last 30 days', 'tracksure')}
            </span>
          </CardHeader>
          <CardBody>
            {/* Simple bar chart visualization */}
            <div className="ts-simple-chart">
              <div className="ts-simple-chart__bars">
                {data.daily_conversions.map((day, index) => {
                  const maxValue = Math.max(...data.daily_conversions.map(d => d.conversions));
                  const heightPercent = maxValue > 0 ? (day.conversions / maxValue) * 100 : 0;
                  
                  return (
                    <div key={index} className="ts-simple-chart__bar-wrapper">
                      <div 
                        className="ts-simple-chart__bar"
                        style={{ height: `${heightPercent}%` }}
                        title={`${day.date}: ${day.conversions} conversions`}
                      >
                        <span className="ts-simple-chart__bar-value">{day.conversions}</span>
                      </div>
                      <span className="ts-simple-chart__bar-label">
                        {new Date(day.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Top Performing Goals */}
      {data.top_goals && data.top_goals.length > 0 && (
        <Card className="ts-goals-overview__top-goals">
          <CardHeader>
            <h3>{__('Top Performing Goals', 'tracksure')}</h3>
            <span className="ts-card-header__subtitle">
              {__('By conversion count', 'tracksure')}
            </span>
          </CardHeader>
          <CardBody>
            <div className="ts-top-goals-list">
              {data.top_goals.slice(0, 5).map((goal, index) => (
                <div key={goal.goal_id} className="ts-top-goal">
                  <div className="ts-top-goal__rank">#{index + 1}</div>
                  <div className="ts-top-goal__info">
                    <div className="ts-top-goal__name">{goal.name}</div>
                    <div className="ts-top-goal__type">{goal.trigger_type}</div>
                  </div>
                  <div className="ts-top-goal__metrics">
                    <div className="ts-top-goal__conversions">
                      <Icon name="CheckCircle" size={16} />
                      {goal.conversions.toLocaleString()}
                    </div>
                    {goal.value > 0 && (
                      <div className="ts-top-goal__value">
                        ${goal.value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Empty state if no data */}
      {data.total_conversions === 0 && (
        <Card>
          <CardBody>
            <div className="ts-empty-state">
              <Icon name="Inbox" size={64} color="var(--ts-text-muted)" />
              <h3>{__('No conversions yet', 'tracksure')}</h3>
              <p>{__('Start tracking conversions by creating your first goal.', 'tracksure')}</p>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
};
