/**
 * Conversions Page - Conversion Analytics & Breakdown
 * 
 * Enhanced conversion analytics showing:
 * - Conversion KPIs
 * - Single vs Multi-Touch breakdown
 * - Time to conversion histogram
 * - Conversions list
 */

import React from 'react';
import { Card, CardHeader, CardBody } from '../components/ui/Card';
import { KPICard } from '../components/ui/KPICard';
import { Button } from '../components/ui/Button';
import { Icon } from '../components/ui/Icon';
import { SkeletonKPI, SkeletonChart } from '../components/ui/Skeleton';
import { EmptyState } from '../components/ui/EmptyState';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { __ } from '../utils/i18n';
import { formatCurrency, formatLocalDate } from '../utils/parameterFormatters';
import { 
  BarChart, Bar, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend 
} from 'recharts';
import '../styles/pages/ConversionsPage.css';

interface ConversionBreakdown {
  single_touch: {
    count: number;
    percentage: number;
    revenue: number;
    avg_value: number;
  };
  multi_touch: {
    count: number;
    percentage: number;
    revenue: number;
    avg_value: number;
    avg_sessions: number;
    avg_days: number;
  };
  total: {
    conversions: number;
    revenue: number;
  };
}

interface TimeToConvertBucket {
  label: string;
  count: number;
  revenue: number;
  avg_value: number;
}

export function ConversionsPage(): JSX.Element {
  const { dateRange } = useApp();
  
  // Convert dates to ISO format for API
  const startDate = formatLocalDate(dateRange.start);
  const endDate = formatLocalDate(dateRange.end);

  // Fetch conversion breakdown
  const { data: breakdown, isLoading: loadingBreakdown } = useApiQuery<ConversionBreakdown>(
    'query',
    `/conversions/breakdown?date_start=${startDate}&date_end=${endDate}`
  );

  // Fetch time to convert histogram
  const { data: histogram, isLoading: loadingHistogram } = useApiQuery<{ buckets: TimeToConvertBucket[]; total: number }>(
    'query',
    `/conversions/time-to-convert?date_start=${startDate}&date_end=${endDate}`
  );

  // Fetch overview metrics for conversion context
  const { data: overview } = useApiQuery<{ metrics: { total_conversions: number; total_revenue: number; conversion_rate: number } }>(
    'getOverview',
    {
      date_start: startDate,
      date_end: endDate,
    }
  );

  const pieData = breakdown ? [
    { name: __('Single-Touch', 'tracksure') || 'Single-Touch', value: breakdown.single_touch.count, color: '#3b82f6' },
    { name: __('Multi-Touch', 'tracksure') || 'Multi-Touch', value: breakdown.multi_touch.count, color: '#10b981' },
  ] : [];

  // Custom label renderer for pie chart - positions labels outside the pie
  const renderPieLabel = ({ cx, cy, midAngle, outerRadius, name, percent }: {
    cx: number; cy: number; midAngle: number; outerRadius: number;
    name: string; percent: number;
  }) => {
    const RADIAN = Math.PI / 180;
    const radius = outerRadius + 30;
    const x = cx + radius * Math.cos(-midAngle * RADIAN);
    const y = cy + radius * Math.sin(-midAngle * RADIAN);
    return (
      <text
        x={x}
        y={y}
        fill="var(--ts-text-primary, #374151)"
        textAnchor={x > cx ? 'start' : 'end'}
        dominantBaseline="central"
        fontSize={13}
        fontWeight={600}
      >
        {`${name} ${(percent * 100).toFixed(0)}%`}
      </text>
    );
  };

  // Custom tooltip for charts
  const chartTooltipStyle = {
    backgroundColor: '#1f2937',
    border: '1px solid #374151',
    borderRadius: '8px',
    padding: '10px 14px',
    boxShadow: '0 4px 12px rgba(0, 0, 0, 0.3)',
  };
  const chartTooltipLabelStyle = {
    color: '#f3f4f6',
    fontWeight: 600 as const,
    marginBottom: '4px',
  };
  const chartTooltipItemStyle = {
    color: '#d1d5db',
    fontSize: '13px',
  };

  if (loadingBreakdown && loadingHistogram) {
    return (
      <div className="tracksure-page tracksure-conversions-page">
        <div className="tracksure-page__header">
          <h1>{__('Conversions', 'tracksure') || 'Conversions'}</h1>
          <p className="tracksure-page__description">
            {__('Analyze conversion performance and journey patterns', 'tracksure') || 'Analyze conversion performance and journey patterns'}
          </p>
        </div>
        <div className="tracksure-grid tracksure-grid--3-cols">
          <SkeletonKPI />
          <SkeletonKPI />
          <SkeletonKPI />
        </div>
        <SkeletonChart />
        <SkeletonChart />
      </div>
    );
  }

  if (!breakdown || breakdown.total.conversions === 0) {
    return (
      <div className="tracksure-page tracksure-conversions-page">
        <div className="tracksure-page__header">
          <h1>{__('Conversions', 'tracksure') || 'Conversions'}</h1>
          <p className="tracksure-page__description">
            {__('Analyze conversion performance and journey patterns', 'tracksure') || 'Analyze conversion performance and journey patterns'}
          </p>
        </div>
        <EmptyState
          icon={<Icon name="Target" size={64} />}
          title={__('No Conversions Yet', 'tracksure') || 'No Conversions Yet'}
          message={__('Once visitors complete goals, conversion analytics will appear here.', 'tracksure') || 'Once visitors complete goals, conversion analytics will appear here.'}
          action={{
            label: __('Set Up Goals', 'tracksure') || 'Set Up Goals',
            onClick: () => (window.location.href = '#/goals')
          }}
        />
      </div>
    );
  }

  return (
    <div className="tracksure-page tracksure-conversions-page">
      <div className="tracksure-page__header">
        <div className="tracksure-page__title">
          <h1>{__('Conversions', 'tracksure') || 'Conversions'}</h1>
          <p className="tracksure-page__description">
            {__('Dive deep into conversion performance and customer journey patterns', 'tracksure') || 'Dive deep into conversion performance and customer journey patterns'}
          </p>
        </div>
        <Button variant="primary" onClick={() => (window.location.href = '#/attribution')}>
          <Icon name="GitBranch" size={16} />
          {__('View Attribution', 'tracksure') || 'View Attribution'}
        </Button>
      </div>

      {/* KPI Cards */}
      <div className="tracksure-grid tracksure-grid--4-cols">
        <KPICard
          metric={{            label: __('Total Conversions', 'tracksure') || 'Total Conversions',
            value: breakdown.total.conversions,
            format: 'number',
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Total Revenue', 'tracksure') || 'Total Revenue',
            value: formatCurrency(breakdown.total.revenue),
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Conversion Rate', 'tracksure') || 'Conversion Rate',
            value: overview?.metrics.conversion_rate || 0,
            format: 'percent',
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Avg Order Value', 'tracksure') || 'Avg Order Value',
            value: formatCurrency(breakdown.total.revenue / breakdown.total.conversions),
            trend: 'neutral',
          }}
        />
      </div>

      {/* Conversion Type Breakdown */}
      <Card>
        <CardHeader>
          <h3>{__('Conversion Type Breakdown', 'tracksure') || 'Conversion Type Breakdown'}</h3>
          <p className="card-description">
            {__('Compare single-touch vs multi-touch conversion patterns', 'tracksure') || 'Compare single-touch vs multi-touch conversion patterns'}
          </p>
        </CardHeader>
        <CardBody>
          <div className="conversion-breakdown-grid">
            {/* Pie Chart */}
            <div className="breakdown-chart">
              <ResponsiveContainer width="100%" height={320}>
                <PieChart>
                  <Pie
                    data={pieData}
                    cx="50%"
                    cy="50%"
                    labelLine={true}
                    label={renderPieLabel}
                    outerRadius={85}
                    innerRadius={40}
                    dataKey="value"
                    stroke="none"
                    paddingAngle={2}
                  >
                    {pieData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip
                    contentStyle={chartTooltipStyle}
                    labelStyle={chartTooltipLabelStyle}
                    itemStyle={chartTooltipItemStyle}
                    formatter={(value: number, name: string) => [
                      `${value} conversion${value !== 1 ? 's' : ''}`,
                      name
                    ]}
                  />
                </PieChart>
              </ResponsiveContainer>
            </div>

            {/* Breakdown Details */}
            <div className="breakdown-details">
              <div className="breakdown-section">
                <div className="breakdown-header">
                  <div className="breakdown-indicator" style={{ backgroundColor: '#3b82f6' }} />
                  <h4>{__('Single-Touch Conversions', 'tracksure') || 'Single-Touch Conversions'}</h4>
                </div>
                <div className="breakdown-metrics">
                  <div className="metric-row">
                    <span className="metric-label">{__('Conversions', 'tracksure') || 'Conversions'}</span>
                    <span className="metric-value">{breakdown.single_touch.count}</span>
                  </div>
                  <div className="metric-row">
                    <span className="metric-label">{__('Revenue', 'tracksure') || 'Revenue'}</span>
                    <span className="metric-value">{formatCurrency(breakdown.single_touch.revenue)}</span>
                  </div>
                  <div className="metric-row">
                    <span className="metric-label">{__('Avg Value', 'tracksure') || 'Avg Value'}</span>
                    <span className="metric-value">{formatCurrency(breakdown.single_touch.avg_value)}</span>
                  </div>
                  <div className="metric-row">
                    <span className="metric-label">{__('Percentage', 'tracksure') || 'Percentage'}</span>
                    <span className="metric-value">{breakdown.single_touch.percentage}%</span>
                  </div>
                </div>
              </div>

              <div className="breakdown-section">
                <div className="breakdown-header">
                  <div className="breakdown-indicator" style={{ backgroundColor: '#10b981' }} />
                  <h4>{__('Multi-Touch Conversions', 'tracksure') || 'Multi-Touch Conversions'}</h4>
                </div>
                <div className="breakdown-metrics">
                  <div className="metric-row">
                    <span className="metric-label">{__('Conversions', 'tracksure') || 'Conversions'}</span>
                    <span className="metric-value">{breakdown.multi_touch.count}</span>
                  </div>
                  <div className="metric-row">
                    <span className="metric-label">{__('Revenue', 'tracksure') || 'Revenue'}</span>
                    <span className="metric-value">{formatCurrency(breakdown.multi_touch.revenue)}</span>
                  </div>
                  <div className="metric-row">
                    <span className="metric-label">{__('Avg Value', 'tracksure') || 'Avg Value'}</span>
                    <span className="metric-value">{formatCurrency(breakdown.multi_touch.avg_value)}</span>
                  </div>
                  <div className="metric-row">
                    <span className="metric-label">{__('Avg Sessions', 'tracksure') || 'Avg Sessions'}</span>
                    <span className="metric-value">{breakdown.multi_touch.avg_sessions.toFixed(1)}</span>
                  </div>
                  <div className="metric-row">
                    <span className="metric-label">{__('Avg Days', 'tracksure') || 'Avg Days'}</span>
                    <span className="metric-value">{breakdown.multi_touch.avg_days.toFixed(1)}</span>
                  </div>
                  <div className="metric-row">
                    <span className="metric-label">{__('Percentage', 'tracksure') || 'Percentage'}</span>
                    <span className="metric-value">{breakdown.multi_touch.percentage}%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Time to Conversion Histogram */}
      <Card>
        <CardHeader>
          <h3>{__('Time to Conversion', 'tracksure') || 'Time to Conversion'}</h3>
          <p className="card-description">
            {__('Distribution of time between first visit and conversion', 'tracksure') || 'Distribution of time between first visit and conversion'}
          </p>
        </CardHeader>
        <CardBody>
          {loadingHistogram ? (
            <SkeletonChart />
          ) : histogram && histogram.buckets.length > 0 ? (
            <ResponsiveContainer width="100%" height={400}>
              <BarChart data={histogram.buckets}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--ts-border)" />
                <XAxis dataKey="label" stroke="var(--ts-text-secondary)" />
                <YAxis stroke="var(--ts-text-secondary)" />
                <Tooltip
                  contentStyle={chartTooltipStyle}
                  labelStyle={chartTooltipLabelStyle}
                  itemStyle={chartTooltipItemStyle}
                  formatter={(value: number, name: string) => {
                    if (name === 'count') {
                      return [value, __('Conversions', 'tracksure') || 'Conversions'];
                    }
                    if (name === 'revenue') {
                      return [formatCurrency(value), __('Revenue', 'tracksure') || 'Revenue'];
                    }
                    return [value, name];
                  }}
                />
                <Legend />
                <Bar dataKey="count" fill="#3b82f6" name={__('Conversions', 'tracksure') || 'Conversions'} />
                <Bar dataKey="revenue" fill="#10b981" name={__('Revenue', 'tracksure') || 'Revenue'} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <EmptyState
              icon={<Icon name="Clock" size={48} />}
              title={__('No Time Data', 'tracksure') || 'No Time Data'}
              message={__('Time to conversion data will appear as visitors complete their journeys.', 'tracksure') || 'Time to conversion data will appear as visitors complete their journeys.'}
            />
          )}
        </CardBody>
      </Card>

      {/* Insights Cards */}
      <Card>
        <CardHeader>
          <h3>{__('Key Insights', 'tracksure') || 'Key Insights'}</h3>
        </CardHeader>
        <CardBody>
          <div className="insights-grid">
            <div className="insight-card">
              <Icon name="Info" size={24} />
              <div>
                <strong>{__('Multi-Touch Majority', 'tracksure') || 'Multi-Touch Majority'}</strong>
                <p>
                  {breakdown.multi_touch.percentage > 50
                    ? (__('Most conversions involve multiple touchpoints. Consider multi-touch attribution models.', 'tracksure') || 'Most conversions involve multiple touchpoints. Consider multi-touch attribution models.')
                    : (__('Most conversions happen in a single session. Single-touch attribution may be sufficient.', 'tracksure') || 'Most conversions happen in a single session. Single-touch attribution may be sufficient.')}
                </p>
              </div>
            </div>

            {breakdown.multi_touch.avg_days > 7 && (
              <div className="insight-card">
                <Icon name="Clock" size={24} />
                <div>
                  <strong>{__('Long Conversion Cycle', 'tracksure') || 'Long Conversion Cycle'}</strong>
                  <p>
                    {(__('Average conversion takes', 'tracksure') || 'Average conversion takes') + ' ' + breakdown.multi_touch.avg_days.toFixed(0) + ' ' + (__('days. Consider remarketing campaigns to nurture leads.', 'tracksure') || 'days. Consider remarketing campaigns to nurture leads.')}
                  </p>
                </div>
              </div>
            )}

            <div className="insight-card">
              <Icon name="TrendingUp" size={24} />
              <div>
                <strong>{__('Average Order Value', 'tracksure') || 'Average Order Value'}</strong>
                <p>
                  {breakdown.multi_touch.avg_value > breakdown.single_touch.avg_value
                    ? (__('Multi-touch conversions have higher order values. Focus on nurturing multi-session visitors.', 'tracksure') || 'Multi-touch conversions have higher order values. Focus on nurturing multi-session visitors.')
                    : (__('Single-touch conversions have higher order values. Focus on converting first-time visitors.', 'tracksure') || 'Single-touch conversions have higher order values. Focus on converting first-time visitors.')}
                </p>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default ConversionsPage;