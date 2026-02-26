/**
 * Attribution Page - Multi-Touch Attribution Analytics
 * 
 * Shows multi-session visitor journeys and attribution insights:
 * - Journey Insights: Aggregated metrics (avg sessions, time to convert)
 * - Attribution Models: Model comparison with all 5 models
 * - Individual Journeys: Single visitor journey search
 */

import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardHeader, CardBody } from '../components/ui/Card';
import { KPICard } from '../components/ui/KPICard';
import { Button } from '../components/ui/Button';
import { Icon } from '../components/ui/Icon';
import { SkeletonKPI, SkeletonChart, SkeletonTable } from '../components/ui/Skeleton';
import { EmptyState } from '../components/ui/EmptyState';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { __ } from '../utils/i18n';
import { formatCurrency, formatLocalDate } from '../utils/parameterFormatters';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import '../styles/pages/AttributionPage.css';

interface JourneyInsights {
  total_conversions: number;
  total_revenue: number;
  avg_sessions_to_convert: number;
  avg_time_to_convert_days: number;
  multi_touch_percentage: number;
  single_touch_percentage: number;
  multi_touch_count: number;
  single_touch_count: number;
  unique_converters: number;
  conversion_rate_per_visitor: number;
}

interface ConversionPath {
  path: string;
  conversions: number;
  total_value: number;
  avg_time_to_convert: number;
  avg_sessions_to_convert: number;
}

interface DevicePattern {
  pattern: string;
  conversions: number;
  revenue: number;
  visitors: number;
  avg_sessions: number;
}

interface AttributionModelsData {
  models: {
    [key: string]: Array<{
      source: string;
      medium: string;
      channel: string;
      conversions: number;
      revenue: number;
      avg_credit: number;
    }>;
  };
  available_models: string[];
}

type TabType = 'insights' | 'models' | 'journeys';

export function AttributionPage(): JSX.Element {
  const { dateRange } = useApp();
  const [activeTab, setActiveTab] = useState<TabType>('insights');

  return (
    <div className="tracksure-page tracksure-attribution-page">
      <div className="tracksure-page__header">
        <div className="tracksure-page__title">
          <h1>{__('Attribution', 'tracksure')}</h1>
          <p className="tracksure-page__description">
            {__('Understand multi-session customer journeys and attribution across touchpoints', 'tracksure')}
          </p>
        </div>
      </div>

      {/* Tabs */}
      <div className="tracksure-tabs">
        <div className="tracksure-tabs__list">
          <button
            className={`tracksure-tabs__tab ${activeTab === 'insights' ? 'is-active' : ''}`}
            onClick={() => setActiveTab('insights')}
          >
            <Icon name="Lightbulb" size={18} />
            {__('Journey Insights', 'tracksure')}
          </button>
          <button
            className={`tracksure-tabs__tab ${activeTab === 'models' ? 'is-active' : ''}`}
            onClick={() => setActiveTab('models')}
          >
            <Icon name="GitBranch" size={18} />
            {__('Attribution Models', 'tracksure')}
          </button>
          <button
            className={`tracksure-tabs__tab ${activeTab === 'journeys' ? 'is-active' : ''}`}
            onClick={() => setActiveTab('journeys')}
          >
            <Icon name="Map" size={18} />
            {__('Individual Journeys', 'tracksure')}
          </button>
        </div>

        <div className="tracksure-tabs__content">
          {activeTab === 'insights' && <JourneyInsightsTab dateRange={dateRange} />}
          {activeTab === 'models' && <AttributionModelsTab dateRange={dateRange} />}
          {activeTab === 'journeys' && <IndividualJourneysTab />}
        </div>
      </div>
    </div>
  );
}

/**
 * Journey Insights Tab - Aggregated Metrics
 */
function JourneyInsightsTab({ dateRange }: { dateRange: { start: Date; end: Date } }): JSX.Element {
  const startDate = formatLocalDate(dateRange.start);
  const endDate = formatLocalDate(dateRange.end);
  
  const { data: insights, isLoading: loadingInsights } = useApiQuery<JourneyInsights>(
    'query',
    `/attribution/insights?date_start=${startDate}&date_end=${endDate}`
  );

  const { data: pathsData, isLoading: loadingPaths } = useApiQuery<{ paths: ConversionPath[]; total: number }>(
    'query',
    `/attribution/paths?date_start=${startDate}&date_end=${endDate}&limit=10`
  );

  const { data: patternsData, isLoading: loadingPatterns } = useApiQuery<{ patterns: DevicePattern[]; total: number }>(
    'query',
    `/attribution/device-patterns?date_start=${startDate}&date_end=${endDate}`
  );

  if (loadingInsights) {
    return (
      <div className="journey-insights-tab">
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

  if (!insights || insights.total_conversions === 0) {
    return (
      <EmptyState
        icon={<Icon name="Lightbulb" size={64} />}
        title={__('No Conversion Data', 'tracksure')}
        message={__('Once visitors complete conversions, journey insights will appear here.', 'tracksure')}
      />
    );
  }

  return (
    <div className="journey-insights-tab">
      {/* KPI Cards */}
      <div className="tracksure-grid tracksure-grid--4-cols">
        <KPICard
          metric={{
            label: __('Total Conversions', 'tracksure'),
            value: insights.total_conversions,
            format: 'number',
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Total Revenue', 'tracksure'),
            value: insights.total_revenue,
            format: 'currency',
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Avg Sessions to Convert', 'tracksure'),
            value: insights.avg_sessions_to_convert,
            format: 'number',
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Avg Time to Convert', 'tracksure'),
            value: `${insights.avg_time_to_convert_days.toFixed(1)} ${__('days', 'tracksure')}`,
            trend: 'neutral',
          }}
        />
      </div>

      {/* Second Row of KPIs */}
      <div className="tracksure-grid tracksure-grid--4-cols">
        <KPICard
          metric={{
            label: __('Multi-Touch %', 'tracksure'),
            value: insights.multi_touch_percentage,
            format: 'percent',
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Multi-Touch Count', 'tracksure'),
            value: insights.multi_touch_count,
            format: 'number',
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Unique Converters', 'tracksure'),
            value: insights.unique_converters,
            format: 'number',
            trend: 'neutral',
          }}
        />
        <KPICard
          metric={{
            label: __('Conv Rate Per Visitor', 'tracksure'),
            value: insights.conversion_rate_per_visitor,
            format: 'number',
            trend: 'neutral',
          }}
        />
      </div>

      {/* Conversion Paths */}
      <Card>
        <CardHeader>
          <h3>{__('Top Conversion Paths', 'tracksure')}</h3>
          <p className="card-description">
            {__('See how visitors journey across multiple touchpoints before converting', 'tracksure')}
          </p>
        </CardHeader>
        <CardBody>
          {loadingPaths ? (
            <SkeletonTable />
          ) : pathsData && pathsData.paths && pathsData.paths.length > 0 ? (
            <div className="conversion-paths-table">
              <table className="tracksure-table">
                <thead>
                  <tr>
                    <th>{__('Conversion Path', 'tracksure')}</th>
                    <th className="text-right">{__('Conversions', 'tracksure')}</th>
                    <th className="text-right">{__('Revenue', 'tracksure')}</th>
                    <th className="text-right">{__('Avg Sessions', 'tracksure')}</th>
                    <th className="text-right">{__('Avg Days', 'tracksure')}</th>
                  </tr>
                </thead>
                <tbody>
                  {pathsData.paths.map((path, index) => (
                    <tr key={index}>
                      <td className="conversion-path-cell">
                        <code className="path-code">{path.path}</code>
                      </td>
                      <td className="text-right">{path.conversions}</td>
                      <td className="text-right">{formatCurrency(path.total_value)}</td>
                      <td className="text-right">{path.avg_sessions_to_convert.toFixed(1)}</td>
                      <td className="text-right">{path.avg_time_to_convert.toFixed(1)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <EmptyState
              icon={<Icon name="Map" size={48} />}
              title={__('No Path Data', 'tracksure')}
              message={__('Conversion paths will appear as visitors complete their journeys.', 'tracksure')}
            />
          )}
        </CardBody>
      </Card>

      {/* Device Journey Patterns */}
      <Card>
        <CardHeader>
          <h3>{__('Device Journey Patterns', 'tracksure')}</h3>
          <p className="card-description">
            {__('Common device switching patterns in conversion journeys', 'tracksure')}
          </p>
        </CardHeader>
        <CardBody>
          {loadingPatterns ? (
            <SkeletonChart />
          ) : patternsData && patternsData.patterns && patternsData.patterns.length > 0 ? (
            <div className="device-patterns-grid">
              {patternsData.patterns.map((pattern, index) => (
                <div key={index} className="device-pattern-card">
                  <div className="pattern-header">
                    <Icon name="Smartphone" size={24} />
                    <span className="pattern-label">{pattern.pattern}</span>
                  </div>
                  <div className="pattern-metrics">
                    <div className="metric">
                      <span className="metric-label">{__('Conversions', 'tracksure')}</span>
                      <span className="metric-value">{pattern.conversions}</span>
                    </div>
                    <div className="metric">
                      <span className="metric-label">{__('Revenue', 'tracksure')}</span>
                      <span className="metric-value">{formatCurrency(pattern.revenue)}</span>
                    </div>
                    <div className="metric">
                      <span className="metric-label">{__('Avg Sessions', 'tracksure')}</span>
                      <span className="metric-value">{pattern.avg_sessions.toFixed(1)}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState
              icon={<Icon name="Smartphone" size={48} />}
              title={__('No Device Patterns', 'tracksure')}
              message={__('Device patterns will appear as visitors use multiple devices.', 'tracksure')}
            />
          )}
        </CardBody>
      </Card>
    </div>
  );
}

/**
 * Attribution Models Tab - Model Comparison
 */
function AttributionModelsTab({ dateRange }: { dateRange: { start: Date; end: Date } }): JSX.Element {
  const startDate = formatLocalDate(dateRange.start);
  const endDate = formatLocalDate(dateRange.end);
  
  const { data: modelsData, isLoading } = useApiQuery<AttributionModelsData>(
    'query',
    `/attribution/models?date_start=${startDate}&date_end=${endDate}`
  );

  const chartData = useMemo(() => {
    if (!modelsData || !modelsData.models) {
      return [];
    }

    // Get all unique sources across all models
    const allSources = new Set<string>();
    Object.values(modelsData.models).forEach((sources) => {
      sources.slice(0, 10).forEach((source) => allSources.add(source.source)); // Top 10 sources
    });

    // Build chart data
    return Array.from(allSources).map((source) => {
      const dataPoint: Record<string, string | number> = { source };
      Object.entries(modelsData.models).forEach(([model, sources]) => {
        const sourceData = sources.find((s) => s.source === source);
        dataPoint[model] = sourceData ? sourceData.conversions : 0;
      });
      return dataPoint;
    });
  }, [modelsData]);

  if (isLoading) {
    return (
      <div>
        <SkeletonChart />
        <SkeletonTable />
      </div>
    );
  }

  if (!modelsData || !modelsData.models) {
    return (
      <EmptyState
        icon={<Icon name="GitBranch" size={64} />}
        title={__('No Attribution Data', 'tracksure')}
        message={__('Attribution model comparison will appear once conversions are tracked.', 'tracksure')}
      />
    );
  }

  // Check if ALL model arrays are empty (API returns models object with empty arrays when no data)
  const hasAnyModelData = Object.values(modelsData.models).some((sources) => sources.length > 0);
  if (!hasAnyModelData) {
    return (
      <EmptyState
        icon={<Icon name="GitBranch" size={64} />}
        title={__('No Attribution Data', 'tracksure')}
        message={__('Attribution model comparison will appear once conversions with linked touchpoints are tracked.', 'tracksure')}
      />
    );
  }

  return (
    <div className="attribution-models-tab">
      {/* Attribution Comparison Chart */}
      <Card>
        <CardHeader>
          <h3>{__('Attribution Model Comparison', 'tracksure')}</h3>
          <p className="card-description">
            {__('Compare how different attribution models credit your marketing channels', 'tracksure')}
          </p>
        </CardHeader>
        <CardBody>
          <ResponsiveContainer width="100%" height={400}>
            <BarChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--ts-border)" />
              <XAxis dataKey="source" stroke="var(--ts-text-secondary)" />
              <YAxis stroke="var(--ts-text-secondary)" />
              <Tooltip 
                contentStyle={{
                  backgroundColor: '#1f2937',
                  border: '1px solid #374151',
                  borderRadius: '8px',
                  padding: '10px 14px',
                  boxShadow: '0 4px 12px rgba(0, 0, 0, 0.3)',
                }}
                labelStyle={{
                  color: '#f3f4f6',
                  fontWeight: 600,
                  marginBottom: '4px',
                }}
                itemStyle={{
                  color: '#d1d5db',
                  fontSize: '13px',
                }}
              />
              <Legend />
              {modelsData.available_models.map((model, index) => {
                const modelColors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444'];
                return (
                  <Bar
                    key={model}
                    dataKey={model}
                    fill={modelColors[index % modelColors.length]}
                    name={formatModelName(model)}
                    radius={[4, 4, 0, 0]}
                  />
                );
              })}
            </BarChart>
          </ResponsiveContainer>
        </CardBody>
      </Card>

      {/* Attribution by Source Table */}
      <Card>
        <CardHeader>
          <h3>{__('Attribution by Source', 'tracksure')}</h3>
        </CardHeader>
        <CardBody>
          <div className="attribution-tables">
            {modelsData.available_models.map((model) => (
              <div key={model} className="attribution-model-section">
                <h4>{formatModelName(model)}</h4>
                <table className="tracksure-table">
                  <thead>
                    <tr>
                      <th>{__('Source', 'tracksure')}</th>
                      <th>{__('Medium', 'tracksure')}</th>
                      <th className="text-right">{__('Conversions', 'tracksure')}</th>
                      <th className="text-right">{__('Revenue', 'tracksure')}</th>
                      <th className="text-right">{__('Avg Credit %', 'tracksure')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {modelsData.models[model].slice(0, 10).map((source, index) => (
                      <tr key={index}>
                        <td>{source.source}</td>
                        <td>{source.medium}</td>
                        <td className="text-right">{source.conversions}</td>
                        <td className="text-right">{formatCurrency(source.revenue)}</td>
                        <td className="text-right">{source.avg_credit.toFixed(1)}%</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

/**
 * Individual Journeys Tab - Search Single Visitor
 */
function IndividualJourneysTab(): JSX.Element {
  const navigate = useNavigate();
  
  return (
    <div className="individual-journeys-tab">
      <Card>
        <CardHeader>
          <h3>{__('Search Individual Visitor Journeys', 'tracksure')}</h3>
          <p className="card-description">
            {__('Go to the Journeys page to search and view individual visitor journeys', 'tracksure')}
          </p>
        </CardHeader>
        <CardBody>
          <div className="redirect-notice">
            <Icon name="Info" size={48} />
            <p>{__('Individual visitor journey search is available on the Journeys page.', 'tracksure')}</p>
            <Button variant="primary" onClick={() => navigate('/journeys')}>
              {__('Go to Journeys Page', 'tracksure')}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

/**
 * Helper: Format attribution model name
 */
function formatModelName(model: string): string {
  const names: Record<string, string> = {
    first_touch: __('First Touch', 'tracksure'),
    last_touch: __('Last Touch', 'tracksure'),
    linear: __('Linear', 'tracksure'),
    time_decay: __('Time Decay', 'tracksure'),
    position_based: __('Position Based', 'tracksure'),
  };
  return names[model] ?? model;
}

export default AttributionPage;
