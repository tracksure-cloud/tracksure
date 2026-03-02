/**
 * Attribution Page - Multi-Touch Attribution Analytics
 * 
 * Shows multi-session visitor journeys and attribution insights:
 * - Journey Insights: Aggregated metrics (avg sessions, time to convert)
 * - Attribution Models: Model comparison with all 5 models
 * - Individual Journeys: Single visitor journey search
 */

import React, { useState, useMemo, useCallback } from 'react';
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
 * Attribution Models Tab - Redesigned for Actionable Budget Insights
 *
 * Layout:
 * 1. Channel Performance Summary — aggregated across all models
 * 2. Smart Insights callout — explains what models reveal about the funnel
 * 3. Revenue chart with Conversions/Revenue toggle
 * 4. Cross-Model Comparison table — side-by-side model columns per source
 * 5. Per-Model Detail accordion — expandable with rank badges & bars
 */
function AttributionModelsTab({ dateRange }: { dateRange: { start: Date; end: Date } }): JSX.Element {
  const startDate = formatLocalDate(dateRange.start);
  const endDate = formatLocalDate(dateRange.end);
  const [chartMetric, setChartMetric] = useState<'revenue' | 'conversions'>('revenue');
  const [expandedModel, setExpandedModel] = useState<string | null>(null);

  const { data: modelsData, isLoading } = useApiQuery<AttributionModelsData>(
    'query',
    `/attribution/models?date_start=${startDate}&date_end=${endDate}`
  );

  // ── Derived data ──

  /** Aggregate channel-level performance (averaged across all 5 models). */
  const channelSummary = useMemo(() => {
    if (!modelsData?.models) return [];

    const channelMap: Record<string, { channel: string; totalConv: number; totalRev: number; modelCount: number }> = {};

    Object.values(modelsData.models).forEach((sources) => {
      const perModel: Record<string, { conv: number; rev: number }> = {};
      sources.forEach((s) => {
        const ch = s.channel || 'Unknown';
        if (!perModel[ch]) perModel[ch] = { conv: 0, rev: 0 };
        perModel[ch].conv += s.conversions;
        perModel[ch].rev += s.revenue;
      });

      Object.entries(perModel).forEach(([ch, vals]) => {
        if (!channelMap[ch]) channelMap[ch] = { channel: ch, totalConv: 0, totalRev: 0, modelCount: 0 };
        channelMap[ch].totalConv += vals.conv;
        channelMap[ch].totalRev += vals.rev;
        channelMap[ch].modelCount += 1;
      });
    });

    return Object.values(channelMap)
      .map((c) => ({
        channel: c.channel,
        conversions: Math.round(c.totalConv / Math.max(c.modelCount, 1)),
        revenue: +(c.totalRev / Math.max(c.modelCount, 1)).toFixed(2),
      }))
      .sort((a, b) => b.revenue - a.revenue);
  }, [modelsData]);

  const maxChannelRevenue = channelSummary.length > 0 ? channelSummary[0].revenue : 1;

  /** Chart data — supports both conversions and revenue. */
  const chartData = useMemo(() => {
    if (!modelsData?.models) return [];

    const allSources = new Set<string>();
    Object.values(modelsData.models).forEach((sources) => {
      sources.slice(0, 8).forEach((s) => allSources.add(s.source));
    });

    return Array.from(allSources)
      .map((source) => {
        const dp: Record<string, string | number> = { source };
        Object.entries(modelsData.models).forEach(([model, sources]) => {
          const match = sources.find((s) => s.source === source);
          dp[model] = match ? (chartMetric === 'revenue' ? match.revenue : match.conversions) : 0;
        });
        return dp;
      })
      .sort((a, b) => {
        const sumA = Object.values(a).reduce<number>((s, v) => s + (typeof v === 'number' ? v : 0), 0);
        const sumB = Object.values(b).reduce<number>((s, v) => s + (typeof v === 'number' ? v : 0), 0);
        return sumB - sumA;
      })
      .slice(0, 8);
  }, [modelsData, chartMetric]);

  /** Cross-model comparison rows: one row per source/medium, columns for each model. */
  const crossModelRows = useMemo(() => {
    if (!modelsData?.models) return [];

    const rowMap: Record<string, {
      source: string; medium: string; channel: string;
      models: Record<string, { conversions: number; revenue: number; avg_credit: number }>;
      totalRevenue: number;
    }> = {};

    Object.entries(modelsData.models).forEach(([model, sources]) => {
      sources.forEach((s) => {
        const key = `${s.source}|${s.medium}`;
        if (!rowMap[key]) {
          rowMap[key] = { source: s.source, medium: s.medium, channel: s.channel, models: {}, totalRevenue: 0 };
        }
        rowMap[key].models[model] = { conversions: s.conversions, revenue: s.revenue, avg_credit: s.avg_credit };
        rowMap[key].totalRevenue += s.revenue;
      });
    });

    return Object.values(rowMap)
      .sort((a, b) => b.totalRevenue - a.totalRevenue)
      .slice(0, 10);
  }, [modelsData]);

  /** Smart insights derived from model comparison. */
  const insights = useMemo(() => {
    if (!modelsData?.models) return [];

    const tips: Array<{ icon: string; text: string; type: 'success' | 'info' | 'warning' }> = [];
    const ft = modelsData.models.first_touch ?? [];
    const lt = modelsData.models.last_touch ?? [];

    if (ft.length > 0 && lt.length > 0) {
      // Initiator vs closer analysis
      const ftTop = ft[0];
      const ltTop = lt[0];
      if (ftTop.source !== ltTop.source) {
        tips.push({
          icon: 'Zap',
          text: `${ftTop.source} is your #1 channel for starting journeys (first touch), while ${ltTop.source} closes the most deals (last touch). Consider investing in both.`,
          type: 'info',
        });
      }

      // Find sources that rank much higher in first-touch than last-touch
      ft.slice(0, 5).forEach((src) => {
        const ltIdx = lt.findIndex((l) => l.source === src.source);
        if (ltIdx > 3 && ft.indexOf(src) < 2) {
          tips.push({
            icon: 'TrendingUp',
            text: `${src.source}/${src.medium} is great at bringing new visitors but rarely closes conversions. It's an awareness channel — pair it with retargeting.`,
            type: 'warning',
          });
        }
      });
    }

    // Budget signal from linear model (fairest split)
    const linear = modelsData.models.linear ?? [];
    if (linear.length >= 2) {
      const top = linear[0];
      const pct = ((top.revenue / linear.reduce((s, r) => s + r.revenue, 0)) * 100).toFixed(0);
      tips.push({
        icon: 'Target',
        text: `Under equal-credit (Linear) model, ${top.source}/${top.medium} captures ${pct}% of attributed revenue — your single biggest ROI driver.`,
        type: 'success',
      });
    }

    return tips.slice(0, 3);
  }, [modelsData]);

  const toggleModel = useCallback((model: string) => {
    setExpandedModel((prev) => (prev === model ? null : model));
  }, []);

  // ── Loading / Empty States ──

  if (isLoading) {
    return (
      <div className="attribution-models-tab">
        <div className="tracksure-grid tracksure-grid--3-cols"><SkeletonKPI /><SkeletonKPI /><SkeletonKPI /></div>
        <SkeletonChart />
        <SkeletonTable />
      </div>
    );
  }

  if (!modelsData?.models) {
    return (
      <EmptyState icon={<Icon name="GitBranch" size={64} />} title={__('No Attribution Data', 'tracksure')}
        message={__('Attribution model comparison will appear once conversions are tracked.', 'tracksure')} />
    );
  }

  const hasAnyModelData = Object.values(modelsData.models).some((s) => s.length > 0);
  if (!hasAnyModelData) {
    return (
      <EmptyState icon={<Icon name="GitBranch" size={64} />} title={__('No Attribution Data', 'tracksure')}
        message={__('Attribution model comparison will appear once conversions with linked touchpoints are tracked.', 'tracksure')} />
    );
  }

  const modelColors: Record<string, string> = {
    first_touch: '#3b82f6',
    last_touch: '#10b981',
    linear: '#8b5cf6',
    time_decay: '#f59e0b',
    position_based: '#ef4444',
  };

  const channelColors: Record<string, string> = {
    'Paid Search': '#ef4444',
    'Organic Search': '#10b981',
    'Paid Social': '#f59e0b',
    'Organic Social': '#8b5cf6',
    'Email': '#3b82f6',
    'Direct': '#6b7280',
    'Referral': '#ec4899',
    'Video': '#14b8a6',
    'unknown': '#9ca3af',
  };

  const channelIcons: Record<string, string> = {
    'Paid Search': 'DollarSign',
    'Organic Search': 'Search',
    'Paid Social': 'DollarSign',
    'Organic Social': 'Globe',
    'Email': 'Mail',
    'Direct': 'Globe',
    'Referral': 'Globe',
    'Video': 'Globe',
  };

  return (
    <div className="attribution-models-tab">

      {/* ─── 1. Channel Performance Summary ─── */}
      <Card>
        <CardHeader>
          <h3><Icon name="Target" size={20} /> {__('Channel Performance Summary', 'tracksure')}</h3>
          <p className="card-description">
            {__('See which marketing channels drive the most value — averaged across all attribution models for a balanced view.', 'tracksure')}
          </p>
        </CardHeader>
        <CardBody>
          <div className="channel-summary-grid">
            {channelSummary.map((ch, idx) => (
              <div key={ch.channel} className="channel-summary-card" style={{ '--channel-color': channelColors[ch.channel] ?? '#6b7280' } as React.CSSProperties}>
                <div className="channel-card-header">
                  <span className="channel-rank">{idx < 3 ? ['🥇', '🥈', '🥉'][idx] : `#${idx + 1}`}</span>
                  <Icon name={(channelIcons[ch.channel] ?? 'Globe') as any} size={18} />
                  <span className="channel-name">{ch.channel}</span>
                </div>
                <div className="channel-card-metrics">
                  <div className="channel-metric">
                    <span className="channel-metric-label">{__('Revenue', 'tracksure')}</span>
                    <span className="channel-metric-value">{formatCurrency(ch.revenue)}</span>
                  </div>
                  <div className="channel-metric">
                    <span className="channel-metric-label">{__('Conversions', 'tracksure')}</span>
                    <span className="channel-metric-value">{ch.conversions}</span>
                  </div>
                </div>
                <div className="channel-bar-track">
                  <div className="channel-bar-fill" style={{ width: `${Math.max((ch.revenue / maxChannelRevenue) * 100, 4)}%` }} />
                </div>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* ─── 2. Smart Insights ─── */}
      {insights.length > 0 && (
        <div className="attribution-insights">
          <h4 className="insights-heading"><Icon name="Zap" size={18} /> {__('Key Insights', 'tracksure')}</h4>
          {insights.map((tip, i) => (
            <div key={i} className={`insight-card insight-card--${tip.type}`}>
              <Icon name={tip.icon as any} size={18} />
              <p>{tip.text}</p>
            </div>
          ))}
        </div>
      )}

      {/* ─── 3. Revenue / Conversions Chart ─── */}
      <Card>
        <CardHeader>
          <div className="chart-header-row">
            <div>
              <h3>{__('Attribution Model Comparison', 'tracksure')}</h3>
              <p className="card-description">
                {__('See how each model credits your traffic sources differently', 'tracksure')}
              </p>
            </div>
            <div className="chart-metric-toggle">
              <button className={`toggle-btn ${chartMetric === 'revenue' ? 'is-active' : ''}`} onClick={() => setChartMetric('revenue')}>
                <Icon name="DollarSign" size={14} /> {__('Revenue', 'tracksure')}
              </button>
              <button className={`toggle-btn ${chartMetric === 'conversions' ? 'is-active' : ''}`} onClick={() => setChartMetric('conversions')}>
                <Icon name="Target" size={14} /> {__('Conversions', 'tracksure')}
              </button>
            </div>
          </div>
        </CardHeader>
        <CardBody>
          <ResponsiveContainer width="100%" height={400}>
            <BarChart data={chartData} barCategoryGap="20%">
              <CartesianGrid strokeDasharray="3 3" stroke="var(--ts-border)" />
              <XAxis dataKey="source" stroke="var(--ts-text-secondary)" tick={{ fontSize: 12 }} />
              <YAxis stroke="var(--ts-text-secondary)" tickFormatter={(v: number) => chartMetric === 'revenue' ? `${(v / 1).toLocaleString()}` : String(v)} />
              <Tooltip
                contentStyle={{
                  backgroundColor: '#1f2937', border: '1px solid #374151', borderRadius: '8px',
                  padding: '10px 14px', boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
                }}
                labelStyle={{ color: '#f3f4f6', fontWeight: 600, marginBottom: '4px' }}
                itemStyle={{ color: '#d1d5db', fontSize: '13px' }}
                formatter={(value: number) => chartMetric === 'revenue' ? formatCurrency(value) : value}
              />
              <Legend />
              {modelsData.available_models.map((model) => (
                <Bar key={model} dataKey={model} fill={modelColors[model] ?? '#6b7280'} name={formatModelName(model)} radius={[4, 4, 0, 0]} />
              ))}
            </BarChart>
          </ResponsiveContainer>
        </CardBody>
      </Card>

      {/* ─── 4. Cross-Model Comparison Table ─── */}
      <Card>
        <CardHeader>
          <h3><Icon name="BarChart3" size={20} /> {__('Revenue by Source — All Models Side by Side', 'tracksure')}</h3>
          <p className="card-description">
            {__('Compare how much revenue each model attributes to every source. Differences reveal whether a channel initiates or closes conversions.', 'tracksure')}
          </p>
        </CardHeader>
        <CardBody>
          <div className="cross-model-table-wrapper">
            <table className="tracksure-table cross-model-table">
              <thead>
                <tr>
                  <th>{__('Source / Medium', 'tracksure')}</th>
                  <th>{__('Channel', 'tracksure')}</th>
                  {modelsData.available_models.map((m) => (
                    <th key={m} className="text-right" style={{ borderBottom: `3px solid ${modelColors[m] ?? '#6b7280'}` }}>
                      {formatModelName(m)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {crossModelRows.map((row, idx) => {
                  const maxRev = Math.max(...Object.values(row.models).map((m) => m.revenue), 1);
                  return (
                    <tr key={idx}>
                      <td>
                        <div className="source-cell">
                          {idx < 3 && <span className="rank-badge rank-badge--gold">{idx + 1}</span>}
                          <span className="source-name">{row.source}</span>
                          <span className="source-medium">/ {row.medium}</span>
                        </div>
                      </td>
                      <td>
                        <span className="channel-tag" style={{ '--tag-color': channelColors[row.channel] ?? '#6b7280' } as React.CSSProperties}>
                          {row.channel}
                        </span>
                      </td>
                      {modelsData.available_models.map((m) => {
                        const data = row.models[m];
                        const rev = data?.revenue ?? 0;
                        const intensity = (rev / maxRev);
                        return (
                          <td key={m} className="text-right revenue-cell">
                            <div className="revenue-bar-bg">
                              <div className="revenue-bar-fill" style={{ width: `${intensity * 100}%`, backgroundColor: modelColors[m] ?? '#6b7280' }} />
                            </div>
                            <span className="revenue-value">{formatCurrency(rev)}</span>
                            {data && <span className="revenue-conv">{data.conversions} conv</span>}
                          </td>
                        );
                      })}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>

      {/* ─── 5. Per-Model Detail (Collapsible) ─── */}
      <Card>
        <CardHeader>
          <h3><Icon name="Layers" size={20} /> {__('Detailed Attribution by Model', 'tracksure')}</h3>
          <p className="card-description">
            {__('Expand a model to see its full source-level breakdown with revenue share indicators.', 'tracksure')}
          </p>
        </CardHeader>
        <CardBody>
          <div className="model-accordion">
            {modelsData.available_models.map((model) => {
              const sources = modelsData.models[model] ?? [];
              const totalModelRev = sources.reduce((s, r) => s + r.revenue, 0);
              const isOpen = expandedModel === model;
              return (
                <div key={model} className={`model-accordion-item ${isOpen ? 'is-open' : ''}`}>
                  <button className="model-accordion-trigger" onClick={() => toggleModel(model)}>
                    <span className="model-color-dot" style={{ backgroundColor: modelColors[model] ?? '#6b7280' }} />
                    <span className="model-trigger-name">{formatModelName(model)}</span>
                    <span className="model-trigger-meta">
                      {sources.length} {__('sources', 'tracksure')} &middot; {formatCurrency(totalModelRev)} {__('total', 'tracksure')}
                    </span>
                    <Icon name="ChevronDown" size={16} className={`accordion-chevron ${isOpen ? 'is-rotated' : ''}`} />
                  </button>
                  {isOpen && (
                    <div className="model-accordion-content">
                      <p className="model-description">{getModelDescription(model)}</p>
                      <table className="tracksure-table">
                        <thead>
                          <tr>
                            <th style={{ width: '40px' }}>#</th>
                            <th>{__('Source', 'tracksure')}</th>
                            <th>{__('Medium', 'tracksure')}</th>
                            <th>{__('Channel', 'tracksure')}</th>
                            <th className="text-right">{__('Conversions', 'tracksure')}</th>
                            <th className="text-right">{__('Revenue', 'tracksure')}</th>
                            <th style={{ width: '180px' }}>{__('Revenue Share', 'tracksure')}</th>
                            <th className="text-right">{__('Avg Credit', 'tracksure')}</th>
                          </tr>
                        </thead>
                        <tbody>
                          {sources.slice(0, 10).map((src, idx) => {
                            const share = totalModelRev > 0 ? (src.revenue / totalModelRev) * 100 : 0;
                            return (
                              <tr key={idx}>
                                <td>
                                  {idx < 3
                                    ? <span className={`rank-badge rank-badge--${['gold', 'silver', 'bronze'][idx]}`}>{idx + 1}</span>
                                    : <span className="rank-num">{idx + 1}</span>
                                  }
                                </td>
                                <td className="fw-600">{src.source}</td>
                                <td>{src.medium}</td>
                                <td>
                                  <span className="channel-tag" style={{ '--tag-color': channelColors[src.channel] ?? '#6b7280' } as React.CSSProperties}>
                                    {src.channel}
                                  </span>
                                </td>
                                <td className="text-right">{src.conversions}</td>
                                <td className="text-right fw-600">{formatCurrency(src.revenue)}</td>
                                <td>
                                  <div className="share-bar-track">
                                    <div className="share-bar-fill" style={{ width: `${share}%`, backgroundColor: modelColors[model] ?? '#6b7280' }} />
                                    <span className="share-bar-label">{share.toFixed(1)}%</span>
                                  </div>
                                </td>
                                <td className="text-right">{src.avg_credit.toFixed(1)}%</td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              );
            })}
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

/**
 * Helper: Plain-English description of each attribution model
 */
function getModelDescription(model: string): string {
  const desc: Record<string, string> = {
    first_touch: __('Gives 100% credit to the very first touchpoint that introduced the visitor. Best for measuring which channels drive initial awareness.', 'tracksure'),
    last_touch: __('Gives 100% credit to the last touchpoint before conversion. Best for measuring which channels close deals.', 'tracksure'),
    linear: __('Splits credit equally across all touchpoints in the journey. Gives a balanced view of every channel\'s contribution.', 'tracksure'),
    time_decay: __('Gives more credit to touchpoints closer to the conversion. Emphasizes recent interactions that likely influenced the decision.', 'tracksure'),
    position_based: __('Gives 40% credit to first touch, 40% to last touch, and splits 20% among middle touches. Balances awareness and closing.', 'tracksure'),
  };
  return desc[model] ?? '';
}

export default AttributionPage;
