/**
 * Overview Page - Main Dashboard
 */

import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { AreaChart, Area, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, ReferenceDot } from 'recharts';
import { KPICard } from '../components/ui/KPICard';
import { SuggestionsWidget } from '../components/SuggestionsWidget';
import { TimeIntelligencePanel } from '../components/TimeIntelligencePanel';
import { ExportButton } from '../components/ExportButton';
import { AnomalyAlert, detectAnomalies, type Anomaly } from '../components/AnomalyAlert';
import { SkeletonKPI, SkeletonChart, SkeletonTable } from '../components/ui/Skeleton';
import { TrackingStatusBanner } from '../components/ui/TrackingStatusBanner';
import { Icon, type IconName } from '../components/ui/Icon';
import { __ } from '../utils/i18n';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { getCountryName } from '../utils/countries';
import { formatLocation } from '../utils/locationFormatters';
import { formatCurrency, formatCurrencyCompact, formatLocalDate } from '../utils/parameterFormatters';
import type { KpiMetric } from '../types';
import '../styles/pages/OverviewPage.css';

export interface OverviewData {
  metrics: {
    unique_visitors: number;
    new_visitors: number;
    returning_visitors: number;
    total_sessions: number;
    sessions_per_visitor: number;
    total_pageviews: number;
    total_events: number;
    avg_session_duration_seconds: number;
    bounce_rate: number;
    events_per_session: number;
    converting_sessions: number;
    total_conversions: number;
    conversion_rate: number;
    total_revenue: number;
    revenue_per_visitor: number;
  };
  previous_period?: {
    unique_visitors: number;
    total_conversions: number;
    conversion_rate: number;
    total_revenue: number;
    total_sessions: number;
    avg_session_duration_seconds: number;
    bounce_rate: number;
    events_per_session: number;
  };
  chart_data: {
    labels: string[];
    visitors: number[];
    new_visitors: number[];
    sessions: number[];
    pageviews: number[];
    conversions: number[];
    revenue: number[];
  };
  devices: Array<{
    device: string;
    visitors: number;
    sessions: number;
    percentage: number;
  }>;
  top_sources: Array<{
    source: string;
    medium: string;
    visitors: number;
    sessions: number;
    conversions: number;
    percentage: number;
  }>;
  top_countries: Array<{
    country: string;
    visitors: number;
    sessions: number;
    percentage: number;
  }>;
  top_pages: Array<{
    path: string;
    title: string;
    visitors: number;
    sessions: number;
    pageviews: number;
    conversions: number;
    device?: string;
    country?: string;
  }>;
  time_intelligence?: {
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
  };
  data_updated_at: string;
}

// Helper function to get CSS variable colors (theme-compatible)
const getCSSColor = (variable: string): string => {
  if (typeof window === 'undefined') {
    return '#4F46E5'; // SSR fallback
  }
  const root = document.documentElement;
  const color = getComputedStyle(root).getPropertyValue(variable).trim();
  return color || '#4F46E5'; // Fallback color
};

// Chart colors - Theme-compatible via CSS variables
const getChartColors = (): string[] => [
  getCSSColor('--ts-chart-1'),
  getCSSColor('--ts-chart-2'),
  getCSSColor('--ts-chart-3'),
  getCSSColor('--ts-chart-4'),
  getCSSColor('--ts-chart-5'),
  getCSSColor('--ts-chart-6'),
  getCSSColor('--ts-chart-7'),
  getCSSColor('--ts-chart-8'),
];

// Metric configurations for dynamic styling (theme-compatible)
const getMetricConfig = () => {
  const chart1 = getCSSColor('--ts-chart-1');
  const chart2 = getCSSColor('--ts-chart-2');
  const chart3 = getCSSColor('--ts-chart-3');
  
  return {
    visitors: {
      color: chart1,
      gradientStart: `${chart1}80`,
      gradientEnd: `${chart1}10`,
      icon: 'Users' as IconName,
      label: 'Visitors',
      formatter: (value: number) => value.toLocaleString(),
    },
    sessions: {
      color: chart2,
      gradientStart: `${chart2}80`,
      gradientEnd: `${chart2}10`,
      icon: 'Activity' as IconName,
      label: 'Sessions',
      formatter: (value: number) => value.toLocaleString(),
    },
    conversions: {
      color: chart3,
      gradientStart: `${chart3}80`,
      gradientEnd: `${chart3}10`,
      icon: 'Target' as IconName,
      label: 'Conversions',
      formatter: (value: number) => value.toLocaleString(),
    },
    revenue: {
      color: chart2,
      gradientStart: `${chart2}80`,
      gradientEnd: `${chart2}10`,
      icon: 'DollarSign' as IconName,
      label: 'Revenue',
      formatter: (value: number) => formatCurrency(value),
    },
  };
};

const OverviewPage: React.FC = () => {
  const { dateRange, segment, config } = useApp();
  
  // Detect e-commerce context (WooCommerce, EDD, FluentCart, SureCart, etc.)
  const isEcommerce = useMemo(() => {
    // Check config first
    if (config.isEcommerce !== undefined) {
      return config.isEcommerce;
    }
    
    // Auto-detect from window objects
    if (typeof window !== 'undefined') {
      return !!(window.wc || window.edd || window.fluentcart || window.surecart);
    }
    
    return false;
  }, [config]);
  const [chartMetric, setChartMetric] = useState<'visitors' | 'sessions' | 'conversions' | 'revenue'>('visitors');
  const [showCharts, setShowCharts] = useState(false);
  const [anomalies, setAnomalies] = useState<Anomaly[]>([]);
  const [topPagesPage, setTopPagesPage] = useState(1);
  const PAGES_PER_PAGE = 10;

  // Get theme-compatible colors and config
  const CHART_COLORS = useMemo(() => getChartColors(), []);
  const METRIC_CONFIG = useMemo(() => getMetricConfig(), []);

  // Type-safe metric config accessor
  const metricConfig = METRIC_CONFIG[chartMetric];


  // Use centralized API query hook with automatic cleanup
  const { data, error, isLoading } = useApiQuery<OverviewData>(
    'getOverview',
    {
      date_start: formatLocalDate(dateRange.start),
      date_end: formatLocalDate(dateRange.end),
      segment: segment,
    },
    {
      refetchInterval: 600000, // Refetch every 10 minutes (performance optimized)
      staleTime: 300000, // Data stays fresh for 5 minutes
      retry: 3,
    }
  );

  // Progressive chart loading for better perceived performance
  useEffect(() => {
    if (!isLoading && data) {
      // Defer chart rendering to allow critical content to paint first
      const timer = setTimeout(() => setShowCharts(true), 100);
      return () => clearTimeout(timer);
    } else {
      setShowCharts(false);
    }
  }, [isLoading, data]);

  // Helper to calculate percentage change
  const calculateChange = useCallback((current: number, previous: number): number => {
    if (!previous || previous === 0) {
      return 0;
    }
    return ((current - previous) / previous) * 100;
  }, []);

  // Safe number conversion helper
  const toNum = useCallback((val: string | number): number => {
    return typeof val === 'number' ? val : parseFloat(String(val)) || 0;
  }, []);

  // Calculate trend based on first vs last value in sparkline
  const getTrend = useCallback((sparkline: number[]): 'up' | 'down' | 'neutral' => {
    if (!sparkline || sparkline.length < 2) {
      return 'neutral';
    }
    const first = sparkline[0];
    const last = sparkline[sparkline.length - 1];
    if (last > first) {
      return 'up';
    }
    if (last < first) {
      return 'down';
    }
    return 'neutral';
  }, []);

  // Transform API data to HERO metrics - MEMOIZED to prevent recalculation
  const heroMetrics: KpiMetric[] = useMemo(() => {
    if (!data?.metrics) {
      return [];
    }
    
    const m = data.metrics;
    const p = data.previous_period;
    const chartData = data.chart_data;

    const metrics: KpiMetric[] = [
      {
        label: __('Unique Visitors'),
        value: toNum(m.unique_visitors),
        format: 'number' as const,
        sparklineData: chartData?.visitors || [],
        trend: getTrend(chartData?.visitors || []),
        ...(p && {
          previousValue: toNum(p.unique_visitors),
          change: calculateChange(toNum(m.unique_visitors), toNum(p.unique_visitors)),
        }),
      },
      {
        label: __('Total Conversions'),
        value: toNum(m.total_conversions),
        format: 'number' as const,
        sparklineData: chartData?.conversions || [],
        trend: getTrend(chartData?.conversions || []),
        ...(p && {
          previousValue: toNum(p.total_conversions),
          change: calculateChange(toNum(m.total_conversions), toNum(p.total_conversions)),
        }),
      },
      {
        label: __('Conversion Rate'),
        value: toNum(m.conversion_rate),
        format: 'percent' as const,
        ...(p && {
          previousValue: toNum(p.conversion_rate),
          change: calculateChange(toNum(m.conversion_rate), toNum(p.conversion_rate)),
        }),
      },
    ];

    // Add revenue only if e-commerce
    if (isEcommerce) {
      metrics.push({
        label: __('Total Revenue'),
        value: toNum(m.total_revenue),
        format: 'currency' as const,
        sparklineData: chartData?.revenue || [],
        trend: getTrend(chartData?.revenue || []),
        ...(p && {
          previousValue: toNum(p.total_revenue),
          change: calculateChange(toNum(m.total_revenue), toNum(p.total_revenue)),
        }),
      });
    }

    return metrics;
  }, [data?.metrics, data?.previous_period, data?.chart_data, isEcommerce, calculateChange, toNum, getTrend]);

  // Transform API data to DETAILED metrics
  const detailedMetrics: KpiMetric[] = useMemo(() => {
    if (!data?.metrics) {
      return [];
    }
    
    const m = data.metrics;
    const p = data.previous_period;
    const chartData = data.chart_data;

    const metrics: KpiMetric[] = [
      {
        label: __('Total Sessions'),
        value: toNum(m.total_sessions),
        format: 'number' as const,
        sparklineData: chartData?.sessions || [],
        trend: getTrend(chartData?.sessions || []),
        ...(p && {
          previousValue: toNum(p.total_sessions),
          change: calculateChange(toNum(m.total_sessions), toNum(p.total_sessions)),
        }),
      },
      {
        label: __('Avg. Session Duration'),
        value: toNum(m.avg_session_duration_seconds),
        format: 'duration' as const,
        ...(p && {
          previousValue: toNum(p.avg_session_duration_seconds),
          change: calculateChange(toNum(m.avg_session_duration_seconds), toNum(p.avg_session_duration_seconds)),
        }),
      },
      {
        label: __('Bounce Rate'),
        value: toNum(m.bounce_rate),
        format: 'percent' as const,
        inverseMetric: true, // Lower is better
        ...(p && {
          previousValue: toNum(p.bounce_rate),
          change: calculateChange(toNum(m.bounce_rate), toNum(p.bounce_rate)),
        }),
      },
    ];

    // Add conversion value for e-commerce or events per session for others
    if (isEcommerce) {
      metrics.push({
        label: __('Avg. Conversion Value'),
        value: toNum(m.total_revenue) / Math.max(toNum(m.total_conversions), 1),
        format: 'currency' as const,
        ...(p && {
          previousValue: toNum(p.total_revenue) / Math.max(toNum(p.total_conversions), 1),
          change: calculateChange(
            toNum(m.total_revenue) / Math.max(toNum(m.total_conversions), 1),
            toNum(p.total_revenue) / Math.max(toNum(p.total_conversions), 1)
          ),
        }),
      });
    } else {
      // Show events per session for non-e-commerce sites
      metrics.push({
        label: __('Events per Session'),
        value: toNum(m.events_per_session),
        format: 'number' as const,
        ...(p && {
          previousValue: toNum(p.events_per_session),
          change: calculateChange(toNum(m.events_per_session), toNum(p.events_per_session)),
        }),
      });
    }

    // Add revenue per visitor only if e-commerce
    if (isEcommerce) {
      metrics.push({
        label: __('Revenue/Visitor'),
        value: toNum(m.revenue_per_visitor),
        format: 'currency' as const,
      });
    }

    return metrics;
  }, [data?.metrics, data?.previous_period, data?.chart_data, isEcommerce, calculateChange, toNum, getTrend]);

  // Combine for legacy compatibility (commented out - using heroMetrics/detailedMetrics separately)
  // const kpis = useMemo(() => [...heroMetrics, ...detailedMetrics], [heroMetrics, detailedMetrics]);

  // Transform chart data for recharts - MEMOIZED to prevent recalculation
  const chartData = useMemo(() => {
    if (!data?.chart_data) {
      return [];
    }
    
    // Safety check: ensure all arrays exist and have matching lengths
    const { labels, visitors, new_visitors, sessions, pageviews, conversions, revenue } = data.chart_data;
    if (!labels || !visitors || !new_visitors || !sessions || !pageviews || !conversions || !revenue) {
      return [];
    }
    
    return labels.map((label, index) => ({
      name: label,
      visitors: Array.isArray(visitors) ? (visitors[index] ?? 0) : 0,
      new_visitors: Array.isArray(new_visitors) ? (new_visitors[index] ?? 0) : 0,
      sessions: Array.isArray(sessions) ? (sessions[index] ?? 0) : 0,
      pageviews: Array.isArray(pageviews) ? (pageviews[index] ?? 0) : 0,
      conversions: Array.isArray(conversions) ? (conversions[index] ?? 0) : 0,
      revenue: Array.isArray(revenue) ? (revenue[index] ?? 0) : 0,
    }));
  }, [data?.chart_data]);

  // Detect anomalies (peaks and drops) based on statistical analysis
  const chartAnomalies = useMemo(() => {
    if (!chartData || chartData.length < 3 || !chartMetric) {
      return { peaks: [], drops: [] };
    }

    const values = chartData.map(point => point[chartMetric] as number);
    
    // Calculate mean and standard deviation
    const mean = values.reduce((sum, val) => sum + val, 0) / values.length;
    const variance = values.reduce((sum, val) => sum + Math.pow(val - mean, 2), 0) / values.length;
    const stdDev = Math.sqrt(variance);
    
    // Define threshold (2 standard deviations)
    const threshold = 2;
    const upperBound = mean + (threshold * stdDev);
    const lowerBound = mean - (threshold * stdDev);
    
    const peaks: Array<{ name: string; value: number; index: number }> = [];
    const drops: Array<{ name: string; value: number; index: number }> = [];
    
    chartData.forEach((point, index) => {
      const value = point[chartMetric] as number;
      
      // Only mark anomalies if they differ significantly from mean
      if (stdDev > 0 && value > upperBound && value > mean * 1.2) {
        peaks.push({ name: point.name, value, index });
      } else if (stdDev > 0 && value < lowerBound && value < mean * 0.8) {
        drops.push({ name: point.name, value, index });
      }
    });
    
    // Limit to top 3 most significant anomalies
    const sortedPeaks = peaks.sort((a, b) => b.value - a.value).slice(0, 3);
    const sortedDrops = drops.sort((a, b) => a.value - b.value).slice(0, 3);
    
    return { peaks: sortedPeaks, drops: sortedDrops };
  }, [chartData, chartMetric]);

  // Detect anomalies and show alerts
  useEffect(() => {
    if (!chartData || chartData.length === 0 || !chartMetric) {
      return;
    }

    // Only check for anomalies on initial load and when metric changes
    const data = chartData.map(point => ({
      name: point.name,
      value: point[chartMetric] as number,
    }));

    const metricConfig = getMetricConfig();
    const config = metricConfig[chartMetric];
    const detected = detectAnomalies(data, config.label);

    // Only show high/medium severity alerts
    const significantAlerts = detected.filter(a => a.severity !== 'low');
    
    if (significantAlerts.length > 0) {
      setAnomalies(significantAlerts);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chartMetric]); // Only run when metric changes, not on every data update

  // Format large numbers for Y-axis (1K, 1M notation)
  const formatYAxis = useCallback((value: number, metric: string): string => {
    if (metric === 'revenue') {
      return formatCurrencyCompact(value);
    }
    
    if (value >= 1000000) {
      return `${(value / 1000000).toFixed(1)}M`;
    }
    if (value >= 1000) {
      return `${(value / 1000).toFixed(1)}K`;
    }
    return value.toString();
  }, []);

  // Memoize helper function to prevent recreation
  const getSourceIcon = useCallback((source: string): IconName => {
    const icons: Record<string, IconName> = {
      google: 'Search',
      facebook: 'Users',
      instagram: 'Users', // Note: Instagram icon not in registry
      twitter: 'Users', // Note: Twitter icon not in registry
      linkedin: 'Users', // Note: LinkedIn icon not in registry
      direct: 'Link',
      newsletter: 'Globe', // Note: Mail icon not in registry
      email: 'Globe', // Note: Mail icon not in registry
    };
    return icons[source.toLowerCase()] || 'Globe';
  }, []);



  // Transform device data - convert string numbers to actual numbers
  const deviceData = useMemo(() => {
    if (!data?.devices) {
      return [];
    }
    
    return data.devices.map(device => ({
      device: device.device,
      visitors: typeof device.visitors === 'string' ? parseInt(device.visitors, 10) : device.visitors,
      sessions: typeof device.sessions === 'string' ? parseInt(device.sessions, 10) : device.sessions,
      percentage: device.percentage,
    }));
  }, [data?.devices]);

  // Transform country data - convert string numbers and calculate percentages
  const countryData = useMemo(() => {
    if (!data?.top_countries) {
      return [];
    }
    
    return data.top_countries.map(country => ({
      country: country.country,
      visitors: typeof country.visitors === 'string' ? parseInt(country.visitors, 10) : country.visitors,
      sessions: typeof country.sessions === 'string' ? parseInt(country.sessions, 10) : country.sessions,
      percentage: country.percentage,
    }));
  }, [data?.top_countries]);

  return (
    <div className="ts-page">
      {/* Anomaly Alerts */}
      <AnomalyAlert anomalies={anomalies} onDismiss={() => {}} />

      <TrackingStatusBanner />
      
      <div className="ts-page-header">
        <div>
          <h1 className="ts-page-title">{__('Overview')}</h1>
          <p className="ts-page-description">{__('Complete performance overview of your website')}</p>
        </div>
        <ExportButton 
          data={data}
          dateRange={dateRange}
        />
      </div>

      {error ? (
        <div className="ts-error-state">
          <div className="ts-error-icon"><Icon name="AlertTriangle" size={48} color="danger" /></div>
          <h2>{__('Error Loading Data')}</h2>
          <p>{error?.message || __('Failed to load overview data')}</p>
        </div>
      ) : isLoading ? (
        <>
          {/* Hero Metrics Skeleton */}
          <div className="ts-hero-metrics-grid">
            {[1, 2, 3, 4].map((i) => (
              <SkeletonKPI key={i} />
            ))}
          </div>
          
          {/* Detailed Metrics Skeleton */}
          <div className="ts-detailed-metrics-grid">
            {[1, 2, 3, 4].map((i) => (
              <SkeletonKPI key={`detail-${i}`} />
            ))}
          </div>

          <div className="ts-chart-grid">
            <div className="ts-chart-card">
              <SkeletonChart height={300} />
            </div>
            <div className="ts-chart-card">
              <SkeletonChart height={300} />
            </div>
          </div>
          <div className="ts-tables-grid">
            <div className="ts-table-card">
              <SkeletonTable rows={5} columns={3} />
            </div>
            <div className="ts-table-card">
              <SkeletonTable rows={5} columns={3} />
            </div>
          </div>
        </>
      ) : heroMetrics.length > 0 ? (
        <>
          {/* Hero Metrics - Large, Prominent Cards */}
          <div className="ts-hero-metrics-grid">
            {heroMetrics.map((metric) => (
              <KPICard key={metric.label} metric={metric} isHero={true} />
            ))}
          </div>

          {/* Detailed Metrics - Smaller Cards */}
          {detailedMetrics.length > 0 && (
            <div className="ts-detailed-metrics-section">
              <h3 className="ts-section-title">{__('Detailed Metrics')}</h3>
              <div className="ts-detailed-metrics-grid">
                {detailedMetrics.map((metric) => (
                  <KPICard key={metric.label} metric={metric} isHero={false} />
                ))}
              </div>
            </div>
          )}

          {/* Top Pages - Full Width Section */}
          {data?.top_pages && data.top_pages.length > 0 && (() => {
            const startIdx = (topPagesPage - 1) * PAGES_PER_PAGE;
            const endIdx = startIdx + PAGES_PER_PAGE;
            const paginatedPages = data.top_pages.slice(startIdx, endIdx);
            const totalPages = Math.ceil(data.top_pages.length / PAGES_PER_PAGE);
            
            return (
              <div className="ts-detailed-metrics-section" style={{ marginTop: 'var(--ts-spacing-lg)' }}>
                <h3 className="ts-section-title">{__('Top Pages')}</h3>
                <div className="ts-overview-card ts-overview-card--no-hover" style={{ marginTop: 'var(--ts-spacing-md)' }}>
                  <div className="ts-table-container">
                    <table className="ts-simple-table ts-simple-table--no-hover">
                      <thead>
                        <tr>
                          <th>{__('Page Title')}</th>
                          <th>{__('Page Path')}</th>
                          <th>{__('Pageviews')}</th>
                          <th>{__('Unique Visitors')}</th>
                          <th>{__('Conversions')}</th>
                          <th>{__('Device')}</th>
                          <th>{__('Country')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {paginatedPages.map((page, index) => (
                          <tr key={index}>
                            <td className="ts-page-title-cell">
                              {page.title || __('(No title)')}
                            </td>
                            <td className="ts-page-path" title={page.path}>
                              {page.path.length > 50 ? page.path.substring(0, 50) + '...' : page.path}
                            </td>
                            <td>{page.pageviews?.toLocaleString() || 0}</td>
                            <td>{page.visitors.toLocaleString()}</td>
                            <td>{page.conversions || 0}</td>
                            <td className="ts-device-badge">
                              <Icon name={page.device === 'mobile' ? 'Smartphone' : page.device === 'tablet' ? 'Tablet' : 'Monitor'} size={14} />
                              {page.device ? page.device.charAt(0).toUpperCase() + page.device.slice(1) : __('Unknown')}
                            </td>
                            <td className="ts-country-cell">
                              {formatLocation(null, page.country)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  {totalPages > 1 && (
                    <div className="ts-pagination">
                      <button
                        className="ts-btn ts-btn-secondary ts-btn-sm"
                        disabled={topPagesPage === 1}
                        onClick={() => setTopPagesPage(p => Math.max(1, p - 1))}
                      >
                        <Icon name="ChevronLeft" size={16} />
                        {__('Previous')}
                      </button>
                      <span className="ts-pagination-info">
                        {__('Page')} {topPagesPage} {__('of')} {totalPages}
                      </span>
                      <button
                        className="ts-btn ts-btn-secondary ts-btn-sm"
                        disabled={topPagesPage === totalPages}
                        onClick={() => setTopPagesPage(p => Math.min(totalPages, p + 1))}
                      >
                        {__('Next')}
                        <Icon name="ChevronRight" size={16} />
                      </button>
                    </div>
                  )}
                </div>
              </div>
            );
          })()}

          {/* Smart Suggestions Widget */}
          <SuggestionsWidget />

          {/* Time Intelligence Panel */}
          {data.time_intelligence &&  (
            <TimeIntelligencePanel 
              data={data.time_intelligence}
              isLoading={false}
            />
          )}

          {/* Traffic Trend Chart - Progressive Loading + Optimized Metric Toggle */}
          {chartData.length > 0 && (
            <div className="ts-chart-section">
              <div className="ts-chart-header">
                <h2>{__('Visitor Trend')}</h2>
                <div className="ts-metric-toggle">
                  <button
                    className={chartMetric === 'visitors' ? 'ts-active' : ''}
                    onClick={() => setChartMetric('visitors')}
                  >
                    <Icon name="Users" size={14} /> {__('Visitors')}
                  </button>
                  <button
                    className={chartMetric === 'sessions' ? 'ts-active' : ''}
                    onClick={() => setChartMetric('sessions')}
                  >
                    <Icon name="Activity" size={14} /> {__('Sessions')}
                  </button>
                  <button
                    className={chartMetric === 'conversions' ? 'ts-active' : ''}
                    onClick={() => setChartMetric('conversions')}
                  >
                    <Icon name="Target" size={14} /> {__('Conversions')}
                  </button>
                  <button
                    className={chartMetric === 'revenue' ? 'ts-active' : ''}
                    onClick={() => setChartMetric('revenue')}
                  >
                    <Icon name="DollarSign" size={14} /> {__('Revenue')}
                  </button>
                </div>
              </div>
              <div className="ts-chart-container">
                {showCharts ? (
                  <ResponsiveContainer width="100%" height={360}>
                    <AreaChart 
                      data={chartData}
                      margin={{ top: 10, right: 30, left: 0, bottom: 5 }}
                    >
                      <defs>
                        <linearGradient id={`gradient-${chartMetric}`} x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor={metricConfig.color} stopOpacity={0.3} />
                          <stop offset="95%" stopColor={metricConfig.color} stopOpacity={0.05} />
                        </linearGradient>
                        <filter id="glow">
                          <feGaussianBlur stdDeviation="2" result="coloredBlur" />
                          <feMerge>
                            <feMergeNode in="coloredBlur" />
                            <feMergeNode in="SourceGraphic" />
                          </feMerge>
                        </filter>
                      </defs>
                      <CartesianGrid 
                        strokeDasharray="3 3" 
                        stroke="var(--ts-border)" 
                        opacity={0.3}
                        vertical={false}
                      />
                      <XAxis 
                        dataKey="name" 
                        stroke="var(--ts-text-muted)" 
                        tick={{ fill: 'var(--ts-text-muted)', fontSize: 12 }}
                        tickLine={false}
                        axisLine={{ stroke: 'var(--ts-border)' }}
                      />
                      <YAxis 
                        stroke="var(--ts-text-muted)" 
                        tick={{ fill: 'var(--ts-text-muted)', fontSize: 12 }}
                        tickLine={false}
                        axisLine={{ stroke: 'var(--ts-border)' }}
                        tickFormatter={(value) => formatYAxis(value, chartMetric)}
                      />
                      <Tooltip
                        contentStyle={{
                          backgroundColor: 'var(--ts-surface)',
                          border: '1px solid var(--ts-border)',
                          borderRadius: 'var(--ts-radius-lg)',
                          boxShadow: '0 8px 24px rgba(0, 0, 0, 0.12)',
                          padding: 'var(--ts-spacing-md)',
                        }}
                        labelStyle={{
                          color: 'var(--ts-text)',
                          fontWeight: '600',
                          fontSize: '13px',
                          marginBottom: '8px',
                        }}
                        itemStyle={{
                          color: 'var(--ts-text)',
                          fontSize: '14px',
                          fontWeight: '500',
                        }}
                        formatter={(value: number) => [
                          metricConfig.formatter(value),
                          metricConfig.label
                        ]}
                        cursor={{
                          stroke: metricConfig.color,
                          strokeWidth: 1,
                          strokeDasharray: '4 4',
                          opacity: 0.3,
                        }}
                      />
                      <Area
                        type="natural"
                        dataKey={chartMetric}
                        stroke={metricConfig.color}
                        strokeWidth={3}
                        fill={`url(#gradient-${chartMetric})`}
                        fillOpacity={1}
                        animationDuration={1200}
                        animationEasing="ease-out"
                        dot={{
                          fill: metricConfig.color,
                          stroke: 'var(--ts-surface)',
                          strokeWidth: 2,
                          r: 4,
                        }}
                        activeDot={{
                          r: 6,
                          fill: metricConfig.color,
                          stroke: 'var(--ts-surface)',
                          strokeWidth: 3,
                          filter: 'url(#glow)',
                        }}
                      />
                      {/* Peak Annotations */}
                      {chartAnomalies.peaks.map((peak, i) => (
                        <ReferenceDot
                          key={`peak-${i}`}
                          x={peak.name}
                          y={peak.value}
                          r={8}
                          fill="var(--ts-success)"
                          stroke="var(--ts-surface)"
                          strokeWidth={3}
                          label={{
                            value: '↑',
                            position: 'top',
                            fill: 'var(--ts-success)',
                            fontSize: 16,
                            fontWeight: 'bold',
                          }}
                        />
                      ))}
                      {/* Drop Annotations */}
                      {chartAnomalies.drops.map((drop, i) => (
                        <ReferenceDot
                          key={`drop-${i}`}
                          x={drop.name}
                          y={drop.value}
                          r={8}
                          fill="var(--ts-danger)"
                          stroke="var(--ts-surface)"
                          strokeWidth={3}
                          label={{
                            value: '↓',
                            position: 'bottom',
                            fill: 'var(--ts-danger)',
                            fontSize: 16,
                            fontWeight: 'bold',
                          }}
                        />
                      ))}
                    </AreaChart>
                  </ResponsiveContainer>
                ) : (
                  <SkeletonChart height={360} />
                )}
              </div>
            </div>
          )}

          {/* Second Row: Top Sources, Devices, Countries (3-column grid) */}
          <div className="ts-overview-grid">
            {/* Top Sources */}
            {data?.top_sources && data.top_sources.length > 0 && (
              <div className="ts-overview-card">
                <h3><Icon name="Globe" size={18} /> {__('Top Sources')}</h3>
                <div className="ts-table-container">
                  <table className="ts-simple-table">
                    <thead>
                      <tr>
                        <th>{__('Source / Medium')}</th>
                        <th>{__('Visitors')}</th>
                        <th>{__('Sessions')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.top_sources.map((source, index) => (
                        <tr key={index}>
                          <td className="ts-source-name">
                            <span className="ts-source-icon">
                              <Icon name={getSourceIcon(source.source)} size={16} />
                            </span>
                            {source.source}
                            <span className="ts-source-medium"> / {source.medium}</span>
                          </td>
                          <td>
                            {source.visitors.toLocaleString()}
                            <span className="ts-percentage"> ({source.percentage}%)</span>
                          </td>
                          <td>{source.sessions.toLocaleString()}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Device Breakdown */}
            {deviceData.length > 0 && (
              <div className="ts-overview-card">
                <div className="ts-card-header">
                  <h3><Icon name="Smartphone" size={18} /> {__('Device Breakdown')}</h3>
                  <span className="ts-card-subtitle">{__('Visitors by device type')}</span>
                </div>
                <div className="ts-chart-container ts-device-chart">
                  <ResponsiveContainer width="100%" height={240}>
                    <PieChart>
                      <Pie
                        data={deviceData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={(entry) => {
                          const percentage = entry.percentage || (entry.percent ? (entry.percent * 100).toFixed(1) : '0');
                          return `${percentage}%`;
                        }}
                        outerRadius={90}
                        innerRadius={45}
                        fill="#8884d8"
                        dataKey="visitors"
                        nameKey="device"
                        paddingAngle={2}
                        animationBegin={0}
                        animationDuration={800}
                      >
                        {deviceData.map((entry, index) => (
                          <Cell 
                            key={`cell-${index}`} 
                            fill={CHART_COLORS[index % CHART_COLORS.length]}
                            stroke="var(--ts-surface)"
                            strokeWidth={2}
                          />
                        ))}
                      </Pie>
                      <Tooltip 
                        contentStyle={{
                          backgroundColor: 'var(--ts-surface)',
                          border: '1px solid var(--ts-border)',
                          borderRadius: 'var(--ts-radius-md)',
                          boxShadow: '0 4px 12px rgba(0, 0, 0, 0.1)',
                          padding: 'var(--ts-spacing-sm)',
                          color: 'var(--ts-text)',
                        }}
                        itemStyle={{
                          color: 'var(--ts-text)',
                          fontSize: '13px',
                          fontWeight: '500',
                        }}
                        formatter={(value: number, name: string) => [
                          `${value.toLocaleString()} visitors`,
                          name.charAt(0).toUpperCase() + name.slice(1)
                        ]}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
                <div className="ts-device-legend">
                  {deviceData.map((device, index) => (
                    <div key={index} className="ts-device-item">
                      <span 
                        className="ts-device-dot" 
                        style={{ 
                          backgroundColor: CHART_COLORS[index % CHART_COLORS.length],
                          boxShadow: `0 0 0 3px ${CHART_COLORS[index % CHART_COLORS.length]}15`
                        }}
                      />
                      <span className="ts-device-label">
                        {device.device.charAt(0).toUpperCase() + device.device.slice(1)}
                      </span>
                      <div className="ts-device-stats">
                        <span className="ts-device-value">{device.visitors.toLocaleString()}</span>
                        <span className="ts-device-percentage">{device.percentage}%</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Top Countries */}
            {countryData.length > 0 && (
              <div className="ts-overview-card">
                <div className="ts-card-header">
                  <h3><Icon name="Globe" size={18} /> {__('Geographic Breakdown')}</h3>
                  <span className="ts-card-subtitle">{__('Visitors by country')}</span>
                </div>
                <div className="ts-chart-container ts-device-chart">
                  <ResponsiveContainer width="100%" height={240}>
                    <PieChart>
                      <Pie
                        data={countryData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={(entry) => {
                          const percentage = entry.percentage || (entry.percent ? (entry.percent * 100).toFixed(1) : '0');
                          return `${percentage}%`;
                        }}
                        outerRadius={90}
                        innerRadius={45}
                        fill="#8884d8"
                        dataKey="visitors"
                        nameKey="country"
                        paddingAngle={2}
                        animationBegin={0}
                        animationDuration={800}
                      >
                        {countryData.map((entry, index) => (
                          <Cell 
                            key={`cell-${index}`} 
                            fill={CHART_COLORS[index % CHART_COLORS.length]}
                            stroke="var(--ts-surface)"
                            strokeWidth={2}
                          />
                        ))}
                      </Pie>
                      <Tooltip 
                        contentStyle={{
                          backgroundColor: 'var(--ts-surface)',
                          border: '1px solid var(--ts-border)',
                          borderRadius: 'var(--ts-radius-md)',
                          boxShadow: '0 4px 12px rgba(0, 0, 0, 0.1)',
                          padding: 'var(--ts-spacing-sm)',
                          color: 'var(--ts-text)',
                        }}
                        itemStyle={{
                          color: 'var(--ts-text)',
                          fontSize: '13px',
                          fontWeight: '500',
                        }}
                        formatter={(value: number, name: string) => [
                          `${value.toLocaleString()} visitors`,
                          getCountryName(name)
                        ]}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
                <div className="ts-device-legend">
                  {countryData.map((country, index) => (
                    <div key={index} className="ts-device-item">
                      <span 
                        className="ts-device-dot" 
                        style={{ 
                          backgroundColor: CHART_COLORS[index % CHART_COLORS.length],
                          boxShadow: `0 0 0 3px ${CHART_COLORS[index % CHART_COLORS.length]}15`
                        }}
                      />
                      <span className="ts-device-label">
                        <span style={{ marginRight: '6px', display: 'inline-flex' }}>
                          <Icon name="MapPin" size={14} />
                        </span>
                        {getCountryName(country.country)}
                      </span>
                      <div className="ts-device-stats">
                        <span className="ts-device-value">{country.visitors.toLocaleString()}</span>
                        <span className="ts-device-percentage">{country.percentage}%</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {data?.data_updated_at && (
            <div className="ts-api-status">
              <p><Icon name="CheckCircle" size={16} color="success" /> {__('Last updated')}: {new Date(data.data_updated_at.replace(' ', 'T') + 'Z').toLocaleString()}</p>
            </div>
          )}
        </>
      ) : (
        <div className="ts-empty-state">
          <div className="ts-empty-icon"><Icon name="BarChart2" size={64} color="muted" /></div>
          <h2>{__('No data available')}</h2>
          <p>{__('Start tracking to see your analytics')}</p>
        </div>
      )}
    </div>
  );
};

export default OverviewPage;
