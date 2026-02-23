import React, { useState, useMemo } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { __ } from '@wordpress/i18n';
import { Icon, type IconName } from '../components/ui/Icon';
import { getChannelIcon, getChannelColor, groupSourcesByChannel, ChannelGroup } from '../utils/channelHelpers';
import { SkeletonKPI, SkeletonTable } from '../components/ui/Skeleton';
import { formatCurrency, formatLocalDate } from '../utils/parameterFormatters';
import '../styles/pages/TrafficSourcesPage.css';

type AttributionModel = 'first_touch' | 'last_touch' | 'linear' | 'time_decay' | 'position_based';

interface Source {
  source: string;
  medium: string;
  sessions: number;
  unique_visitors?: number;
  conversions: number;
  revenue?: number;
  conversion_rate?: number;
  aov?: number;
  first_touch_conversions?: number;
  first_touch_revenue?: number;
  last_touch_conversions?: number;
  last_touch_revenue?: number;
  [key: string]: string | number | boolean | undefined | null;
}

interface SourcesData {
  sources: Source[];
  unique_visitors?: number;
  total_conversions?: number;
  message?: string;
}

const TrafficSourcesPage: React.FC = () => {
  const { dateRange, segment } = useApp();
  const [sortBy, setSortBy] = useState<'sessions' | 'conversions' | 'revenue' | 'conversion_rate' | 'aov' | 'rps' | 'first_touch_conversions' | 'last_touch_conversions'>('sessions');
  const [sortOrder, setSortOrder] = useState<'desc' | 'asc'>('desc');
  const [displayMode, setDisplayMode] = useState<'channels' | 'sources'>('channels');
  const [attributionModel, setAttributionModel] = useState<AttributionModel>('last_touch');
  const [channelFilter, setChannelFilter] = useState<'all' | 'paid' | 'organic' | 'social' | 'email'>('all');

  // Use centralized API query hooks (SINGLE API call for performance)
  const { data, error, isLoading } = useApiQuery<SourcesData>(
    'getTrafficSources',
    {
      date_start: formatLocalDate(dateRange.start),
      date_end: formatLocalDate(dateRange.end),
      segment: segment,
    },
    {
      refetchInterval: 900000, // 15 minutes (performance optimized)
      staleTime: 600000, // 10 minutes
      retry: 1, // Reduced from 2 for faster failure
    }
  );

  const handleSort = (column: typeof sortBy) => {
    if (sortBy === column) {
      setSortOrder(sortOrder === 'desc' ? 'asc' : 'desc');
    } else {
      setSortBy(column);
      setSortOrder('desc');
    }
  };

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const sortedSources = data?.sources
    ? [...data.sources].sort((a, b) => {
        let aVal: number, bVal: number;
        
        switch (sortBy) {
          case 'sessions':
            aVal = a.sessions;
            bVal = b.sessions;
            break;
          case 'conversions':
            aVal = a.conversions;
            bVal = b.conversions;
            break;
          case 'revenue':
            aVal = a.revenue || 0;
            bVal = b.revenue || 0;
            break;
          case 'conversion_rate':
            aVal = a.conversion_rate || 0;
            bVal = b.conversion_rate || 0;
            break;
          case 'aov':
            aVal = a.aov || 0;
            bVal = b.aov || 0;
            break;
          case 'first_touch_conversions':
            aVal = a.first_touch_conversions || 0;
            bVal = b.first_touch_conversions || 0;
            break;
          case 'last_touch_conversions':
            aVal = a.last_touch_conversions || 0;
            bVal = b.last_touch_conversions || 0;
            break;
          default:
            aVal = a.sessions;
            bVal = b.sessions;
        }
        
        return sortOrder === 'desc' ? bVal - aVal : aVal - bVal;
      })
    : [];

  // Filter sources by channel type FIRST (before calculating totals)
  const filteredSources = useMemo(() => {
    if (channelFilter === 'all') {return sortedSources;}
    
    return sortedSources.filter(source => {
      const medium = source.medium.toLowerCase();
      switch (channelFilter) {
        case 'paid':
          return medium === 'cpc' || medium === 'paid';
        case 'organic':
          return medium === 'organic';
        case 'social':
          return medium === 'social';
        case 'email':
          return medium === 'email';
        default:
          return true;
      }
    });
  }, [sortedSources, channelFilter]);

  // Calculate totals from FILTERED sources
  const totalSessions = filteredSources.reduce((sum, s) => sum + s.sessions, 0) || 0;
  // Use total_conversions from API response (backend calculates accurately)
  const totalConversions = data?.total_conversions || 0;
  const totalRevenue = filteredSources.reduce((sum, s) => sum + (s.revenue || 0), 0) || 0;
  const overallConversionRate = totalSessions > 0 ? ((totalConversions / totalSessions) * 100).toFixed(1) : '0.0';

  // Group FILTERED sources by channel for channel view
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const channelGroups: ChannelGroup[] = displayMode === 'channels' && filteredSources.length > 0 
    ? Object.values(groupSourcesByChannel(filteredSources)).sort((a, b) => {
        // Sort by totalSessions descending
        return b.totalSessions - a.totalSessions;
      })
    : [];

  const _calculateConversionRate = (conversions: number, sessions: number): string => {
    if (sessions === 0) {return '0.0%';}
    return ((conversions / sessions) * 100).toFixed(1) + '%';
  };

  const getSourceIcon = (source: string): string => {
    const icons: Record<string, string> = {
      google: 'Search',
      facebook: 'Users',
      instagram: 'Instagram',
      twitter: 'Twitter',
      linkedin: 'Linkedin',
      youtube: 'Youtube',
      pinterest: 'Image',
      tiktok: 'Music',
      reddit: 'MessageSquare',
      direct: 'Link',
      newsletter: 'Mail',
      email: 'Mail',
      referral: 'ExternalLink',
      bing: 'Search',
      yahoo: 'Search',
    };
    return icons[source.toLowerCase()] || 'Globe';
  };

  // Calculate insights based on FILTERED data
  const insights = useMemo(() => {
    if (!filteredSources.length) {return [];}
    
    const results: string[] = [];
    const avgConvRate = totalSessions > 0 ? (totalConversions / totalSessions) * 100 : 0;
    
    // Insight 1: Dominant channel
    if (channelGroups.length > 0) {
      const topChannel = channelGroups[0];
      const percentage = totalSessions > 0 ? (topChannel.totalSessions / totalSessions * 100).toFixed(0) : 0;
      if (Number(percentage) > 50) {
        results.push(`${topChannel.channel} dominates with ${percentage}% of traffic - consider diversifying sources`);
      }
    }
    
    // Insight 2: High performers
    const highPerformers = filteredSources.filter(s => s.conversion_rate && s.conversion_rate > avgConvRate * 1.5);
    if (highPerformers.length > 0) {
      results.push(`${highPerformers[0].source}/${highPerformers[0].medium} has ${highPerformers[0].conversion_rate?.toFixed(1)}% conv. rate - replicate this success`);
    }
    
    // Insight 3: Untapped channels
    const hasOrganic = filteredSources.some(s => s.medium.toLowerCase() === 'organic');
    const hasPaid = filteredSources.some(s => s.medium.toLowerCase() === 'cpc');
    
    if (!hasOrganic && filteredSources.length > 1) {
      results.push('No organic traffic detected - improve SEO to reduce acquisition costs');
    }
    if (!hasPaid && totalRevenue > 1000) {
      results.push('Consider paid advertising to scale high-performing channels');
    }
    
    // Insight 4: UTM tracking
    const directTraffic = filteredSources.filter(s => s.source.toLowerCase() === '(direct)' || s.source.toLowerCase() === 'direct');
    if (directTraffic.length > 0 && directTraffic[0].sessions > totalSessions * 0.3) {
      results.push('Add UTM parameters to campaigns for better attribution tracking');
    }
    
    return results.slice(0, 3); // Max 3 insights
  }, [filteredSources, channelGroups, totalSessions, totalConversions, totalRevenue]);

  return (
    <div className="ts-page">
      <div className="ts-page-header">
        <div>
          <h1 className="ts-page-title">{__('Traffic Sources')}</h1>
          <p className="ts-page-description">
            {__('Analyze traffic performance with multi-touch attribution')}
          </p>
        </div>
        
        {/* Attribution Model Selector */}
        {!error && !isLoading && (
          <div className="ts-attribution-selector">
            <label className="ts-label">{__('Attribution Model:')}</label>
            <div className="ts-select-wrapper">
              <select 
                value={attributionModel} 
                onChange={(e) => setAttributionModel(e.target.value as AttributionModel)}
                className="ts-select"
              >
                <option value="last_touch">{__('Last-Touch')}</option>
                <option value="first_touch">{__('First-Touch')}</option>
                <option value="linear" disabled>{__('Linear (Pro)')}</option>
                <option value="time_decay" disabled>{__('Time-Decay (Pro)')}</option>
                <option value="position_based" disabled>{__('Position-Based (Pro)')}</option>
              </select>
            </div>
            <span className="ts-help-text">{__('Choose how to attribute conversions to traffic sources')}</span>
          </div>
        )}
      </div>

      {error ? (
        <div className="ts-error-state">
          <Icon name="AlertTriangle" size={48} color="danger" />
          <h2>{__('Error Loading Data')}</h2>
          <p>{error?.message || __('Failed to load traffic sources data')}</p>
        </div>
      ) : isLoading ? (
        <>
          <div className="ts-sources-summary">
            {[1, 2, 3].map((i) => (
              <SkeletonKPI key={i} />
            ))}
          </div>
          <div className="ts-sources-content">
            <SkeletonTable rows={8} columns={5} />
          </div>
        </>
      ) : !data || !data.sources || data.sources.length === 0 ? (
        <div className="ts-empty-state">
          <Icon name="BarChart2" size={48} color="muted" />
          <h2>{__('No traffic data yet')}</h2>
          <p>{__('Start tracking to see where your visitors come from')}</p>
        </div>
      ) : (
        <>
          {/* Summary Cards */}
          <div className="ts-sources-summary">
            <div className="ts-summary-card">
              <Icon name="Users" size={24} color="primary" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">
                  {(data.unique_visitors || totalSessions).toLocaleString()}
                  <span className="ts-summary-sub">
                    {totalSessions.toLocaleString()} sessions
                  </span>
                </div>
                <div className="ts-summary-label">{__('Unique Visitors')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="Link" size={24} color="primary" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{filteredSources.length}</div>
                <div className="ts-summary-label">{__('Traffic Sources')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="CheckCircle" size={24} color="success" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{totalConversions.toLocaleString()}</div>
                <div className="ts-summary-label">{__('Total Conversions')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="DollarSign" size={24} color="success" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{formatCurrency(totalRevenue)}</div>
                <div className="ts-summary-label">{__('Total Revenue')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="TrendingUp" size={24} color="primary" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{overallConversionRate}%</div>
                <div className="ts-summary-label">{__('Conversion Rate')}</div>
              </div>
            </div>
          </div>

          {/* Insights Widget */}
          {insights.length > 0 && (
            <div className="ts-insights-widget">
              <div className="ts-insights-header">
                <Icon name="Lightbulb" size={18} />
                <h3>{__('Insights & Recommendations')}</h3>
              </div>
              <ul className="ts-insights-list">
                {insights.map((insight, index) => (
                  <li key={index}>
                    <Icon name="TrendingUp" size={14} />
                    {insight}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {/* Quick Filters */}
          <div className="ts-quick-filters">
            <button
              className={`ts-filter-btn ${channelFilter === 'all' ? 'ts-filter-btn--active' : ''}`}
              onClick={() => setChannelFilter('all')}
            >
              {__('All Channels')}
            </button>
            <button
              className={`ts-filter-btn ${channelFilter === 'paid' ? 'ts-filter-btn--active' : ''}`}
              onClick={() => setChannelFilter('paid')}
            >
              💰 {__('Paid')}
            </button>
            <button
              className={`ts-filter-btn ${channelFilter === 'organic' ? 'ts-filter-btn--active' : ''}`}
              onClick={() => setChannelFilter('organic')}
            >
              🌱 {__('Organic')}
            </button>
            <button
              className={`ts-filter-btn ${channelFilter === 'social' ? 'ts-filter-btn--active' : ''}`}
              onClick={() => setChannelFilter('social')}
            >
              👥 {__('Social')}
            </button>
            <button
              className={`ts-filter-btn ${channelFilter === 'email' ? 'ts-filter-btn--active' : ''}`}
              onClick={() => setChannelFilter('email')}
            >
              📧 {__('Email')}
            </button>
          </div>

          {/* Display Mode Toggle */}
          <div className="ts-display-toggle">
            <button
              className={`ts-toggle-btn ${displayMode === 'channels' ? 'ts-toggle-btn--active' : ''}`}
              onClick={() => setDisplayMode('channels')}
            >
              <Icon name="Layers" size={16} /> {__('Group by Channels')}
            </button>
            <button
              className={`ts-toggle-btn ${displayMode === 'sources' ? 'ts-toggle-btn--active' : ''}`}
              onClick={() => setDisplayMode('sources')}
            >
              <Icon name="List" size={16} /> {__('View by Sources')}
            </button>
          </div>

          {/* Sources/Channels Table */}
          <div className="ts-sources-table-container">
            <table className="ts-sources-table">
                <thead>
                  <tr>
                    {displayMode === 'channels' ? (
                      <th>{__('Channel')}</th>
                    ) : (
                      <>
                        <th>{__('Source')}</th>
                        <th>{__('Medium')}</th>
                      </>
                    )}
                    <th
                      className={`ts-sortable ${sortBy === 'sessions' ? 'ts-sorted' : ''}`}
                      onClick={() => handleSort('sessions')}
                    >
                      <div className="ts-table-header">
                        {__('Sessions')}
                        {sortBy === 'sessions' && (
                          <Icon name={sortOrder === 'desc' ? 'ChevronDown' : 'ChevronUp'} size={16} className="ts-sort-icon" />
                        )}
                      </div>
                    </th>
                    <th
                      className={`ts-sortable ${sortBy === 'conversions' ? 'ts-sorted' : ''}`}
                      onClick={() => handleSort('conversions')}
                    >
                      <div className="ts-table-header">
                        {__('Conversions')}
                        {sortBy === 'conversions' && (
                          <Icon name={sortOrder === 'desc' ? 'ChevronDown' : 'ChevronUp'} size={16} className="ts-sort-icon" />
                        )}
                      </div>
                    </th>
                    <th
                      className={`ts-sortable ${sortBy === 'conversion_rate' ? 'ts-sorted' : ''}`}
                      onClick={() => handleSort('conversion_rate')}
                    >
                      <div className="ts-table-header">
                        {__('Conv. Rate')}
                        {sortBy === 'conversion_rate' && (
                          <Icon name={sortOrder === 'desc' ? 'ChevronDown' : 'ChevronUp'} size={16} className="ts-sort-icon" />
                        )}
                      </div>
                    </th>
                    <th
                      className={`ts-sortable ${sortBy === 'revenue' ? 'ts-sorted' : ''}`}
                      onClick={() => handleSort('revenue')}
                    >
                      <div className="ts-table-header">
                        {__('Revenue')}
                        {sortBy === 'revenue' && (
                          <Icon name={sortOrder === 'desc' ? 'ChevronDown' : 'ChevronUp'} size={16} className="ts-sort-icon" />
                        )}
                      </div>
                    </th>
                    <th
                      className={`ts-sortable ${sortBy === 'aov' ? 'ts-sorted' : ''}`}
                      onClick={() => handleSort('aov')}
                    >
                      <div className="ts-table-header">
                        {__('AOV')}
                        {sortBy === 'aov' && (
                          <Icon name={sortOrder === 'desc' ? 'ChevronDown' : 'ChevronUp'} size={16} className="ts-sort-icon" />
                        )}
                      </div>
                    </th>
                    <th>{__('% of Total')}</th>
                  </tr>
                </thead>
                <tbody>
                  {displayMode === 'channels' ? (
                    // Channel view
                    channelGroups.map((group, index) => {
                      const percentage = totalSessions > 0 ? ((group.totalSessions / totalSessions) * 100).toFixed(1) : '0.0';
                      const conversionRate = group.totalSessions > 0 ? ((group.totalConversions / group.totalSessions) * 100).toFixed(1) : '0.0';
                      const aov = group.totalConversions > 0 ? group.totalRevenue / group.totalConversions : 0;
                      
                      return (
                        <React.Fragment key={index}>
                          <tr className="ts-channel-row">
                            <td>
                              <span className="ts-channel-icon"><Icon name={getChannelIcon(group.channel) as IconName} size={20} /></span>
                              <strong>{group.channel}</strong>
                              <span className="ts-source-count"> ({group.sources.length} {group.sources.length === 1 ? __('source') : __('sources')})</span>
                            </td>
                            <td>{group.totalSessions.toLocaleString()}</td>
                            <td>
                              {group.totalConversions > 0 && <Icon name="CheckCircle" size={14} color="success" className="ts-conversion-icon" />}
                              {group.totalConversions.toLocaleString()}
                            </td>
                            <td>{conversionRate}%</td>
                            <td className="ts-revenue-cell">
                              {formatCurrency(group.totalRevenue)}
                            </td>
                            <td>{formatCurrency(aov)}</td>
                            <td>
                              <div className="ts-percentage-bar">
                                <div 
                                  className="ts-percentage-fill" 
                                  style={{
                                    width: `${percentage}%`,
                                    backgroundColor: getChannelColor(group.channel)
                                  }}
                                ></div>
                                <span className="ts-percentage-text">{percentage}%</span>
                              </div>
                            </td>
                          </tr>
                        </React.Fragment>
                      );
                    })
                  ) : (
                    // Source view - use FILTERED sources
                    filteredSources.map((source, index) => {
                      const percentage = totalSessions > 0 ? ((source.sessions / totalSessions) * 100).toFixed(1) : '0.0';
                      
                      return (
                        <tr key={index}>
                          <td>
                            <span className="ts-source-icon"><Icon name={getSourceIcon(source.source) as IconName} size={16} /></span>
                            {source.source}
                          </td>
                          <td>{source.medium}</td>
                          <td>{source.sessions.toLocaleString()}</td>
                          <td>
                            {source.conversions > 0 && <Icon name="CheckCircle" size={14} color="success" />}
                            {source.conversions.toLocaleString()}
                          </td>
                          <td>{source.conversion_rate ? source.conversion_rate.toFixed(1) + '%' : '0.0%'}</td>
                          <td className="ts-revenue-cell">
                            {formatCurrency(source.revenue || 0)}
                          </td>
                          <td>{formatCurrency(source.aov || 0)}</td>
                          <td>{percentage}%</td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
          </div>
        </>
      )}
    </div>
  );
};

export default TrafficSourcesPage;
