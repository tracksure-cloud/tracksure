/**
 * Goal Details Modal - Enhanced with Analytics Tabs
 * 
 * Features:
 * - Overview tab: Key metrics and summary
 * - Timeline tab: Detailed conversion history
 * - Sources tab: Attribution breakdown by source/medium/campaign
 * - Devices tab: Device and browser distribution
 * 
 * @since 2.1.0 - Enhanced with tab navigation and analytics
 * @package TrackSure
 */
import React, { useState, useMemo, useEffect } from 'react';
import { Modal } from '../../ui/Modal';
import { Icon } from '../../ui/Icon';
import { Badge } from '../../ui/Badge';
import { Card, CardHeader, CardBody } from '../../ui';
import { TrackSureAPI } from '../../../utils/api';
import { useApp } from '../../../contexts/AppContext';
import { formatUserTime, useUserTimezone } from '../../../utils/timezoneHelpers';
import type { Goal } from '@/types/goals';
import { __ } from '../../../utils/i18n';
import { formatCurrency } from '../../../utils/parameterFormatters';
import './GoalDetailsModal.css';

interface Conversion {
  conversion_id: number;
  visitor_id: string;
  value: number;
  page_url: string;
  product_id?: string;
  product_name?: string;
  form_id?: string;
  element_selector?: string;
  source: string;
  medium: string;
  campaign: string;
  referrer: string;
  device: string;
  browser: string;
  converted_at: string;
}

interface SourceData {
  source: string;
  medium: string;
  conversions: number;
  revenue: number;
  percentage: number;
}

interface DeviceData {
  device: string;
  browser: string;
  conversions: number;
  percentage: number;
}

interface OverviewData {
  total_conversions: number;
  total_value: number;
  conversion_rate: number;
  avg_value: number;
  unique_visitors: number;
  top_pages: Array<{ page_url: string; conversions: number }>;
}

interface GoalDetailsModalProps {
  goal: Goal;
  onClose: () => void;
}

type TabType = 'overview' | 'timeline' | 'sources' | 'devices';

export const GoalDetailsModal: React.FC<GoalDetailsModalProps> = ({ goal, onClose }) => {
  const { config, dateRange } = useApp();
  const timezone = useUserTimezone();
  
  // Tab state
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  
  // Timeline tab state
  const [currentPage, setCurrentPage] = useState(1);
  const [sortBy, setSortBy] = useState<'date' | 'value' | 'page'>('date');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');
  const [filterSource, setFilterSource] = useState<string>('all');
  const [conversions, setConversions] = useState<Conversion[]>([]);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const perPage = 20;
  
  // Analytics data state
  const [overviewData, setOverviewData] = useState<OverviewData | null>(null);
  const [sourcesData, setSourcesData] = useState<SourceData[]>([]);
  const [devicesData, setDevicesData] = useState<DeviceData[]>([]);
  const [analyticsLoading, setAnalyticsLoading] = useState(true);
  const [devicesLoading, setDevicesLoading] = useState(false);

  // Fetch overview data
  useEffect(() => {
    const fetchOverview = async () => {
      setAnalyticsLoading(true);
      
      try {
        const api = new TrackSureAPI(config);
        
        // Fetch sources data
        const sourcesResponse = await api.getGoalSources(goal.goal_id, {
          date_start: dateRange.start.toLocaleDateString('en-CA'),
          date_end: dateRange.end.toLocaleDateString('en-CA'),
          attribution_model: 'last_touch',
        }) as { sources: SourceData[] };
        
        setSourcesData(sourcesResponse.sources || []);
        
        // Calculate overview metrics from existing performance data
        const perfResponse = await api.getGoalPerformance(goal.goal_id, {
          date_start: dateRange.start.toLocaleDateString('en-CA'),
          date_end: dateRange.end.toLocaleDateString('en-CA'),
        }) as {
          conversions?: number;
          revenue?: number;
          conversion_rate?: number;
          avg_value?: number;
          unique_visitors?: number;
          top_pages?: Array<{ page: string; count: number }>;
        };
        
        if (perfResponse) {
          setOverviewData({
            total_conversions: perfResponse.conversions || 0,
            total_value: perfResponse.revenue || 0,
            conversion_rate: perfResponse.conversion_rate || 0,
            avg_value: perfResponse.avg_value || 0,
            unique_visitors: perfResponse.unique_visitors || 0,
            top_pages: (perfResponse.top_pages || []).map(p => ({
              page_url: p.page,
              conversions: p.count,
            })),
          });
        }
        
      } catch (err: unknown) {
        console.error('[GoalDetailsModal] Failed to fetch analytics:', err);
      } finally {
        setAnalyticsLoading(false);
      }
    };
    
    fetchOverview();
  }, [goal.goal_id, dateRange, config]);

  // Fetch conversion timeline (only when timeline tab is active)
  useEffect(() => {
    if (activeTab !== 'timeline') {return;}
    
    const fetchTimeline = async () => {
      setIsLoading(true);
      setError(null);
      
      try {
        const api = new TrackSureAPI(config);
        const response = await api.getGoalTimeline(goal.goal_id, {
          date_start: dateRange.start.toLocaleDateString('en-CA'),
          date_end: dateRange.end.toLocaleDateString('en-CA'),
          page: currentPage,
          per_page: perPage,
        }) as { conversions: Conversion[]; total: number };
        
        setConversions(response.conversions || []);
        setTotal(response.total || 0);
        
      } catch (err: unknown) {
        const message = err instanceof Error ? err.message : __('Failed to load conversions', 'tracksure');
        setError(message);
      } finally {
        setIsLoading(false);
      }
    };
    
    fetchTimeline();
  }, [goal.goal_id, dateRange, currentPage, config, activeTab]);

  // Fetch device/browser stats from server (only when devices tab is active).
  // Uses the dedicated /devices endpoint for full-dataset aggregation.
  useEffect(() => {
    if (activeTab !== 'devices') {return;}
    
    const fetchDevices = async () => {
      setDevicesLoading(true);
      
      try {
        const api = new TrackSureAPI(config);
        const response = await api.getGoalDevices(goal.goal_id, {
          date_start: dateRange.start.toLocaleDateString('en-CA'),
          date_end: dateRange.end.toLocaleDateString('en-CA'),
        }) as { devices: DeviceData[]; total: number };
        
        setDevicesData((response.devices || []).map(d => ({
          device: d.device || 'desktop',
          browser: d.browser || 'unknown',
          conversions: d.conversions || 0,
          percentage: d.percentage || 0,
        })));
        
      } catch (err: unknown) {
        console.error('[GoalDetailsModal] Failed to fetch devices:', err);
      } finally {
        setDevicesLoading(false);
      }
    };
    
    fetchDevices();
  }, [goal.goal_id, dateRange, config, activeTab]);

  // Get unique sources for filter
  const sources = useMemo(() => {
    const sourceSet = new Set(conversions.map(c => c.source || 'direct'));
    return ['all', ...Array.from(sourceSet)];
  }, [conversions]);

  // Filter and sort conversions
  const filteredConversions = useMemo(() => {
    let filtered = [...conversions];

    // Filter by source
    if (filterSource !== 'all') {
      filtered = filtered.filter(c => (c.source || 'direct') === filterSource);
    }

    // Sort
    filtered.sort((a, b) => {
      let comparison = 0;
      
      if (sortBy === 'date') {
        comparison = new Date(a.converted_at.replace(' ', 'T') + 'Z').getTime() - new Date(b.converted_at.replace(' ', 'T') + 'Z').getTime();
      } else if (sortBy === 'value') {
        comparison = (a.value || 0) - (b.value || 0);
      } else if (sortBy === 'page') {
        comparison = (a.page_url || '').localeCompare(b.page_url || '');
      }

      return sortOrder === 'asc' ? comparison : -comparison;
    });

    return filtered;
  }, [conversions, filterSource, sortBy, sortOrder]);

  const handleSort = (column: 'date' | 'value' | 'page') => {
    if (sortBy === column) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortBy(column);
      setSortOrder('desc');
    }
  };

  const formatDate = (timestamp: number | string) => {
    return formatUserTime(timestamp, timezone);
  };

  const getContextLabel = (conversion: Conversion): string | null => {
    if (conversion.product_name) {
      return conversion.product_name;
    }
    if (conversion.product_id) {
      return `Product #${conversion.product_id}`;
    }
    if (conversion.form_id) {
      return `Form: ${conversion.form_id}`;
    }
    if (conversion.element_selector) {
      return conversion.element_selector;
    }
    return null;
  };

  const totalPages = Math.ceil(total / perPage);

  const exportToCSV = () => {
    const headers = [
      __('Date', 'tracksure'),
      __('Page URL', 'tracksure'),
      goal.value_type !== 'none' ? __('Value', 'tracksure') : null,
      __('Context', 'tracksure'),
      __('Source', 'tracksure'),
      __('Medium', 'tracksure'),
      __('Campaign', 'tracksure'),
      __('Device', 'tracksure'),
      __('Browser', 'tracksure'),
    ].filter(Boolean);

    const rows = filteredConversions.map(c => [
      c.converted_at,
      c.page_url,
      goal.value_type !== 'none' ? c.value : null,
      getContextLabel(c) || '-',
      c.source || 'direct',
      c.medium || '-',
      c.campaign || '-',
      c.device || '-',
      c.browser || '-',
    ].filter((_, i) => headers[i] !== null));

    const csv = [
      headers.join(','),
      ...rows.map(row => row.map(cell => `"${cell}"`).join(',')),
    ].join('\n');

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${goal.name.replace(/[^a-z0-9]/gi, '_')}_conversions.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  // Render tab content
  const renderTabContent = () => {
    switch (activeTab) {
      case 'overview':
        return renderOverviewTab();
      case 'timeline':
        return renderTimelineTab();
      case 'sources':
        return renderSourcesTab();
      case 'devices':
        return renderDevicesTab();
      default:
        return null;
    }
  };

  const renderOverviewTab = () => {
    if (analyticsLoading) {
      return (
        <div className="ts-loading">
          <Icon name="Loader" size={24} />
          <span>{__('Loading overview...', 'tracksure')}</span>
        </div>
      );
    }

    if (!overviewData) {
      return (
        <div className="ts-empty">
          <Icon name="BarChart3" size={48} />
          <p>{__('No data available for this period.', 'tracksure')}</p>
        </div>
      );
    }

    return (
      <div className="ts-goal-overview-tab">
        {/* Key Metrics Row */}
        <div className="ts-overview-metrics">
          <Card>
            <CardBody>
              <div className="ts-metric">
                <div className="ts-metric-label">{__('Total Conversions', 'tracksure')}</div>
                <div className="ts-metric-value">{overviewData.total_conversions.toLocaleString()}</div>
              </div>
            </CardBody>
          </Card>
          
          {goal.value_type !== 'none' && (
            <>
              <Card>
                <CardBody>
                  <div className="ts-metric">
                    <div className="ts-metric-label">{__('Total Value', 'tracksure')}</div>
                    <div className="ts-metric-value">{formatCurrency(overviewData.total_value)}</div>
                  </div>
                </CardBody>
              </Card>
              
              <Card>
                <CardBody>
                  <div className="ts-metric">
                    <div className="ts-metric-label">{__('Average Value', 'tracksure')}</div>
                    <div className="ts-metric-value">{formatCurrency(overviewData.avg_value)}</div>
                  </div>
                </CardBody>
              </Card>
            </>
          )}
          
          <Card>
            <CardBody>
              <div className="ts-metric">
                <div className="ts-metric-label">{__('Conversion Rate', 'tracksure')}</div>
                <div className="ts-metric-value">{overviewData.conversion_rate.toFixed(2)}%</div>
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Top Pages */}
        {overviewData.top_pages && overviewData.top_pages.length > 0 && (
          <Card>
            <CardHeader>{__('Top Converting Pages', 'tracksure')}</CardHeader>
            <CardBody>
              <div className="ts-top-pages">
                {overviewData.top_pages.map((page, index) => (
                  <div key={index} className="ts-top-page">
                    <div className="ts-top-page-rank">#{index + 1}</div>
                    <div className="ts-top-page-url">
                      <a href={page.page_url} target="_blank" rel="noopener noreferrer">
                        {page.page_url}
                      </a>
                    </div>
                    <div className="ts-top-page-conversions">
                      <Badge variant="success">{page.conversions} {__('conversions', 'tracksure')}</Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        )}
      </div>
    );
  };

  const renderTimelineTab = () => {
    return (
      <>
        {/* Filters & Actions */}
        <div className="ts-modal-filters">
          <div className="ts-filter-group">
            <label>{__('Filter by Source:', 'tracksure')}</label>
            <select
              value={filterSource}
              onChange={(e) => setFilterSource(e.target.value)}
              className="ts-filter-select"
            >
              {sources.map(source => (
                <option key={source} value={source}>
                  {source === 'all' ? __('All Sources', 'tracksure') : source}
                </option>
              ))}
            </select>
          </div>

          <button className="ts-export-btn" onClick={exportToCSV}>
            <Icon name="Download" size={14} />
            {__('Export CSV', 'tracksure')}
          </button>
        </div>

        {/* Table */}
        <div className="ts-modal-content">
          {isLoading ? (
            <div className="ts-loading">
              <Icon name="Loader" size={24} />
              <span>{__('Loading conversions...', 'tracksure')}</span>
            </div>
          ) : error ? (
            <div className="ts-error">
              <Icon name="AlertCircle" size={24} />
              <span>{error}</span>
            </div>
          ) : filteredConversions.length === 0 ? (
            <div className="ts-empty">
              <Icon name="FileText" size={48} />
              <p>{__('No conversions found for this date range.', 'tracksure')}</p>
            </div>
          ) : (
            <div className="ts-table-scroll">
              <table className="ts-conversions-table">
                <thead>
                  <tr>
                    <th
                      onClick={() => handleSort('date')}
                      className={sortBy === 'date' ? 'ts-sorted' : ''}
                    >
                      {__('Date', 'tracksure')} {sortBy === 'date' && (sortOrder === 'asc' ? '↑' : '↓')}
                    </th>
                    <th
                      onClick={() => handleSort('page')}
                      className={sortBy === 'page' ? 'ts-sorted' : ''}
                    >
                      {__('Page URL', 'tracksure')} {sortBy === 'page' && (sortOrder === 'asc' ? '↑' : '↓')}
                    </th>
                    <th>{__('Context', 'tracksure')}</th>
                    <th>{__('Source', 'tracksure')}</th>
                    <th>{__('Device', 'tracksure')}</th>
                    {goal.value_type !== 'none' && (
                      <th
                        onClick={() => handleSort('value')}
                        className={sortBy === 'value' ? 'ts-sorted' : ''}
                      >
                        {__('Value', 'tracksure')} {sortBy === 'value' && (sortOrder === 'asc' ? '↑' : '↓')}
                      </th>
                    )}
                  </tr>
                </thead>
                <tbody>
                  {filteredConversions.map((conversion) => (
                    <tr key={conversion.conversion_id}>
                      <td className="ts-date-cell">
                        {formatDate(conversion.converted_at)}
                      </td>
                      <td className="ts-url-cell">
                        {conversion.page_url ? (
                          <a
                            href={conversion.page_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="ts-url-link"
                          >
                            {conversion.page_url}
                            <Icon name="ExternalLink" size={12} />
                          </a>
                        ) : (
                          <span className="ts-url-empty">-</span>
                        )}
                      </td>
                      <td className="ts-context-cell">
                        {getContextLabel(conversion) ? (
                          <span className="ts-context-badge">
                            <Icon name="Tag" size={12} />
                            {getContextLabel(conversion)}
                          </span>
                        ) : (
                          <span className="ts-context-empty">-</span>
                        )}
                      </td>
                      <td className="ts-source-cell">
                        <div className="ts-source-info">
                          <strong>{conversion.source || 'direct'}</strong>
                          {conversion.medium && (
                            <span className="ts-medium"> / {conversion.medium}</span>
                          )}
                          {conversion.campaign && (
                            <div className="ts-campaign">{conversion.campaign}</div>
                          )}
                        </div>
                      </td>
                      <td className="ts-device-cell">
                        <span className="ts-device-badge">
                          <Icon
                            name={
                              conversion.device === 'mobile'
                                ? 'Smartphone'
                                : conversion.device === 'tablet'
                                ? 'Tablet'
                                : 'Monitor'
                            }
                            size={12}
                          />
                          {conversion.device || 'desktop'}
                        </span>
                      </td>
                      {goal.value_type !== 'none' && (
                        <td className="ts-value-cell">
                          {formatCurrency(conversion.value || 0)}
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
        
        {/* Pagination */}
        {!isLoading && totalPages > 1 && (
          <div className="ts-modal-pagination">
            <button
              onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
              disabled={currentPage === 1}
              className="ts-page-btn"
            >
              <Icon name="ChevronLeft" size={16} />
              {__('Previous', 'tracksure')}
            </button>
            <span className="ts-page-info">
              {__('Page', 'tracksure')} {currentPage} {__('of', 'tracksure')} {totalPages} ({total} {__('total conversions', 'tracksure')})
            </span>
            <button
              onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
              disabled={currentPage === totalPages}
              className="ts-page-btn"
            >
              {__('Next', 'tracksure')}
              <Icon name="ChevronRight" size={16} />
            </button>
          </div>
        )}
      </>
    );
  };

  const renderSourcesTab = () => {
    if (analyticsLoading) {
      return (
        <div className="ts-loading">
          <Icon name="Loader" size={24} />
          <span>{__('Loading sources...', 'tracksure')}</span>
        </div>
      );
    }

    if (sourcesData.length === 0) {
      return (
        <div className="ts-empty">
          <Icon name="Globe" size={48} />
          <p>{__('No source data available for this period.', 'tracksure')}</p>
        </div>
      );
    }

    return (
      <div className="ts-sources-tab">
        <table className="ts-sources-table">
          <thead>
            <tr>
              <th>{__('Source', 'tracksure')}</th>
              <th>{__('Medium', 'tracksure')}</th>
              <th>{__('Conversions', 'tracksure')}</th>
              <th>{__('Percentage', 'tracksure')}</th>
              {goal.value_type !== 'none' && <th>{__('Revenue', 'tracksure')}</th>}
            </tr>
          </thead>
          <tbody>
            {sourcesData.map((source, index) => (
              <tr key={index}>
                <td className="ts-source-name">{source.source}</td>
                <td className="ts-source-medium">{source.medium || '-'}</td>
                <td className="ts-source-conversions">{source.conversions}</td>
                <td className="ts-source-percentage">
                  <div className="ts-progress-bar">
                    <div 
                      className="ts-progress-fill" 
                      style={{ width: `${source.percentage}%` }}
                    />
                  </div>
                  <span>{source.percentage.toFixed(1)}%</span>
                </td>
                {goal.value_type !== 'none' && (
                  <td className="ts-source-revenue">{formatCurrency(source.revenue)}</td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  const renderDevicesTab = () => {
    if (devicesLoading) {
      return (
        <div className="ts-loading">
          <Icon name="Loader" size={24} />
          <span>{__('Loading devices...', 'tracksure')}</span>
        </div>
      );
    }

    if (devicesData.length === 0) {
      return (
        <div className="ts-empty">
          <Icon name="Monitor" size={48} />
          <p>{__('No device data available for this period.', 'tracksure')}</p>
        </div>
      );
    }

    return (
      <div className="ts-devices-tab">
        <table className="ts-devices-table">
          <thead>
            <tr>
              <th>{__('Device', 'tracksure')}</th>
              <th>{__('Browser', 'tracksure')}</th>
              <th>{__('Conversions', 'tracksure')}</th>
              <th>{__('Percentage', 'tracksure')}</th>
            </tr>
          </thead>
          <tbody>
            {devicesData.map((device, index) => (
              <tr key={index}>
                <td className="ts-device-name">
                  <Icon
                    name={
                      device.device === 'mobile'
                        ? 'Smartphone'
                        : device.device === 'tablet'
                        ? 'Tablet'
                        : 'Monitor'
                    }
                    size={16}
                  />
                  {device.device}
                </td>
                <td className="ts-device-browser">{device.browser}</td>
                <td className="ts-device-conversions">{device.conversions}</td>
                <td className="ts-device-percentage">
                  <div className="ts-progress-bar">
                    <div 
                      className="ts-progress-fill" 
                      style={{ width: `${device.percentage}%` }}
                    />
                  </div>
                  <span>{device.percentage.toFixed(1)}%</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  return (
    <Modal 
      isOpen={true} 
      onClose={onClose} 
      size="xl"
      title={
        <div>
          <div>{goal.name}</div>
          <p className="ts-modal-subtitle">{goal.description}</p>
        </div>
      }
    >
      <div className="ts-goal-details-modal">
        {/* Tab Navigation */}
        <div className="ts-modal-tabs">
          <button
            className={`ts-modal-tab ${activeTab === 'overview' ? 'ts-modal-tab--active' : ''}`}
            onClick={() => setActiveTab('overview')}
          >
            <Icon name="BarChart3" size={18} />
            {__('Overview', 'tracksure')}
          </button>
          <button
            className={`ts-modal-tab ${activeTab === 'timeline' ? 'ts-modal-tab--active' : ''}`}
            onClick={() => setActiveTab('timeline')}
          >
            <Icon name="Clock" size={18} />
            {__('Timeline', 'tracksure')}
          </button>
          <button
            className={`ts-modal-tab ${activeTab === 'sources' ? 'ts-modal-tab--active' : ''}`}
            onClick={() => setActiveTab('sources')}
          >
            <Icon name="Globe" size={18} />
            {__('Sources', 'tracksure')}
          </button>
          <button
            className={`ts-modal-tab ${activeTab === 'devices' ? 'ts-modal-tab--active' : ''}`}
            onClick={() => setActiveTab('devices')}
          >
            <Icon name="Monitor" size={18} />
            {__('Devices', 'tracksure')}
          </button>
        </div>

        {/* Tab Content */}
        <div className="ts-modal-tab-content">
          {renderTabContent()}
        </div>
      </div>
    </Modal>
  );
};
