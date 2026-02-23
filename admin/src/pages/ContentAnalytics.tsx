import React, { useState } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { __ } from '@wordpress/i18n';
import { Icon, IconName } from '../components/ui/Icon';
import { SkeletonKPI, SkeletonTable } from '../components/ui/Skeleton';
import { formatCurrency, formatLocalDate } from '../utils/parameterFormatters';
import '../styles/pages/ContentAnalytics.css';

interface Page {
  path: string;
  title?: string;
  views: number;
  sessions?: number;
  conversions?: number;
  revenue?: number;
  conversion_rate?: number;
  aov?: number;
  time: string;
  bounce: string;
}

interface DeviceBreakdown {
  device: string;
  sessions: number;
  pageviews: number;
  conversions: number;
  revenue: number;
}

interface CountryBreakdown {
  country_code: string;
  country: string;
  sessions: number;
  pageviews: number;
  conversions: number;
  revenue: number;
}

interface SourceBreakdown {
  source: string;
  sessions: number;
  pageviews: number;
  conversions: number;
  revenue: number;
}

interface Breakdowns {
  devices: DeviceBreakdown[];
  countries: CountryBreakdown[];
  sources: SourceBreakdown[];
}

interface PagesData {
  pages: Page[];
  breakdowns?: Breakdowns;
  message?: string;
}

const PagesPage: React.FC = () => {
  const { dateRange } = useApp();
  const [sortBy, setSortBy] = useState<'views' | 'conversions' | 'revenue' | 'time' | 'bounce'>('views');
  const [sortOrder, setSortOrder] = useState<'desc' | 'asc'>('desc');
  const [showAllPages, setShowAllPages] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [activeBreakdownTab, setActiveBreakdownTab] = useState<'devices' | 'countries' | 'sources'>('devices');
  const itemsPerPage = 50;

  // Get pages data
  const { data, error, isLoading } = useApiQuery<PagesData>(
    'getPages',
    {
      date_start: formatLocalDate(dateRange.start),
      date_end: formatLocalDate(dateRange.end),
    },
    {
      refetchInterval: 900000, // Refetch every 15 minutes (performance optimized)
      staleTime: 600000, // Data stays fresh for 10 minutes
      retry: 2,
    }
  );

  // Get overview data for accurate total conversions
  const { data: _overviewData } = useApiQuery<{ metrics: { total_conversions: number } }>(
    'getOverview',
    {
      date_start: formatLocalDate(dateRange.start),
      date_end: formatLocalDate(dateRange.end),
    },
    {
      refetchInterval: 900000, // 15 minutes
      staleTime: 600000, // 10 minutes
      retry: 2,
    }
  );

  const handleSort = (column: 'views' | 'conversions' | 'revenue' | 'time' | 'bounce') => {
    if (sortBy === column) {
      setSortOrder(sortOrder === 'desc' ? 'asc' : 'desc');
    } else {
      setSortBy(column);
      setSortOrder(column === 'bounce' ? 'asc' : 'desc'); // Lower bounce rate is better
    }
    setCurrentPage(1); // Reset to first page when sorting changes
  };

  const convertTimeToSeconds = (timeStr: string): number => {
    const [minutes, seconds] = timeStr.split(':').map(Number);
    return minutes * 60 + seconds;
  };

  const convertBounceToNumber = (bounceStr: string): number => {
    return parseFloat(bounceStr.replace('%', ''));
  };

  // Filter pages: exclude backend URLs (0 pageviews) unless user wants to see all
  const filteredPages = data?.pages
    ? data.pages.filter((page) => {
        if (showAllPages) { return true; }
        // Hide pages with 0 views (backend endpoints like admin-ajax, order-received)
        return page.views > 0;
      })
    : [];

  const sortedPages = filteredPages.length > 0
    ? [...filteredPages].sort((a, b) => {
        let aVal: number, bVal: number;

        switch (sortBy) {
          case 'views':
            aVal = a.views;
            bVal = b.views;
            break;
          case 'conversions':
            aVal = a.conversions || 0;
            bVal = b.conversions || 0;
            break;
          case 'revenue':
            aVal = a.revenue || 0;
            bVal = b.revenue || 0;
            break;
          case 'time':
            aVal = convertTimeToSeconds(a.time);
            bVal = convertTimeToSeconds(b.time);
            break;
          case 'bounce':
            aVal = convertBounceToNumber(a.bounce);
            bVal = convertBounceToNumber(b.bounce);
            break;
          default:
            aVal = a.views;
            bVal = b.views;
        }

        return sortOrder === 'desc' ? bVal - aVal : aVal - bVal;
      })
    : [];

  const totalViews = sortedPages.reduce((sum, p) => sum + p.views, 0);
  const totalConversions = sortedPages.reduce((sum, p) => sum + (p.conversions || 0), 0);
  const totalRevenue = sortedPages.reduce((sum, p) => sum + (p.revenue || 0), 0);
  const totalSessions = sortedPages.reduce((sum, p) => sum + (p.sessions || 0), 0);
  
  // Calculate actual conversion rate (conversions / sessions)
  const actualConversionRate = totalSessions > 0 ? (totalConversions / totalSessions) * 100 : 0;
  
  // Calculate weighted average time (based on pageviews)
  const avgTimeSeconds = sortedPages.length > 0 && totalViews > 0
    ? sortedPages.reduce((sum, p) => sum + (convertTimeToSeconds(p.time) * p.views), 0) / totalViews
    : 0;
  
  // Calculate weighted average bounce rate (based on pageviews)
  const avgBounce = sortedPages.length > 0 && totalViews > 0
    ? sortedPages.reduce((sum, p) => sum + (convertBounceToNumber(p.bounce) * p.views), 0) / totalViews
    : 0;

  // Pagination
  const totalPages = Math.ceil(sortedPages.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentPages = sortedPages.slice(startIndex, endIndex);

  const goToPage = (page: number) => {
    setCurrentPage(Math.max(1, Math.min(page, totalPages)));
  };

  const formatTime = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const getPageIcon = (path: string): IconName => {
    if (path === '/' || path === '/home') {
      return 'Home';
    }
    if (path.includes('/product')) {
      return 'Package';
    }
    if (path.includes('/blog') || path.includes('/post')) {
      return 'FileText';
    }
    if (path.includes('/cart')) {
      return 'ShoppingCart';
    }
    if (path.includes('/checkout')) {
      return 'CreditCard';
    }
    if (path.includes('/contact')) {
      return 'Mail';
    }
    if (path.includes('/about')) {
      return 'Info';
    }
    return 'FileText';
  };

  const getBounceRateColor = (bounce: string): string => {
    const rate = convertBounceToNumber(bounce);
    if (rate < 40) {
      return 'ts-bounce-excellent';
    }
    if (rate < 55) {
      return 'ts-bounce-good';
    }
    if (rate < 70) {
      return 'ts-bounce-average';
    }
    return 'ts-bounce-poor';
  };

  const getCountryFlag = (countryCode: string): string => {
    if (!countryCode || countryCode.length !== 2) {
      return '🌐';
    }
    const code = countryCode.toUpperCase();
    // Convert country code to flag emoji using regional indicator symbols
    const codePoints = [...code].map(char => 127397 + char.charCodeAt(0));
    return String.fromCodePoint(...codePoints);
  };

  const getSourceIcon = (source: string): IconName => {
    const lowerSource = source.toLowerCase();
    
    if (lowerSource.includes('google') || lowerSource.includes('search')) {
      return 'Search';
    }
    if (lowerSource.includes('facebook') || lowerSource.includes('instagram')) {
      return 'Users';
    }
    if (lowerSource.includes('email') || lowerSource.includes('newsletter')) {
      return 'Mail';
    }
    if (lowerSource.includes('direct')) {
      return 'ExternalLink';
    }
    if (lowerSource.includes('referral')) {
      return 'Link';
    }
    if (lowerSource.includes('social') || lowerSource.includes('twitter') || lowerSource.includes('linkedin')) {
      return 'Share2';
    }
    if (lowerSource.includes('cpc') || lowerSource.includes('paid')) {
      return 'DollarSign';
    }
    
    return 'TrendingUp';
  };

  return (
    <div className="ts-page ts-pages-page">
      <div className="ts-page-header">
        <div>
          <h1 className="ts-page-title">{__('Pages & Content')}</h1>
          <p className="ts-page-description">
            {__('Analyze page performance, engagement, and visitor behavior')}
          </p>
        </div>
      </div>

      {error ? (
        <div className="ts-error-state">
          <Icon name="AlertTriangle" size={48} color="danger" />
          <h2>{__('Error Loading Data')}</h2>
          <p>{error?.message || __('Failed to load pages data')}</p>
        </div>
      ) : isLoading ? (
        <>
          <div className="ts-pages-summary">
            {[1, 2, 3].map((i) => (
              <SkeletonKPI key={i} />
            ))}
          </div>
          <div className="ts-pages-content">
            <SkeletonTable rows={10} columns={6} />
          </div>
        </>
      ) : data && sortedPages.length > 0 ? (
        <>
          {/* Summary Cards */}
          <div className="ts-pages-summary">
            <div className="ts-summary-card">
              <Icon name="Eye" size={24} color="primary" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{totalViews.toLocaleString()}</div>
                <div className="ts-summary-label">{__('Total Pageviews')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="Users" size={24} color="primary" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{totalSessions.toLocaleString()}</div>
                <div className="ts-summary-label">{__('Total Sessions')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="Clock" size={24} color="primary" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{formatTime(avgTimeSeconds)}</div>
                <div className="ts-summary-label">{__('Avg Time on Page')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="BarChart2" size={24} color="primary" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{avgBounce.toFixed(1)}%</div>
                <div className="ts-summary-label">{__('Avg Bounce Rate')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="Target" size={24} color="success" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{actualConversionRate.toFixed(1)}%</div>
                <div className="ts-summary-label">{__('Conversion Rate')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <Icon name="FileText" size={24} color="primary" className="ts-summary-icon" />
              <div className="ts-summary-content">
                <div className="ts-summary-value">{sortedPages.length}</div>
                <div className="ts-summary-label">{__('Unique Pages')}</div>
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
          </div>

          {data.message && (
            <div className="ts-api-status">
              <Icon name="CheckCircle" size={16} color="success" /> {data.message}
            </div>
          )}

          {/* Breakdowns Section */}
          {data.breakdowns && (data.breakdowns.devices.length > 0 || data.breakdowns.countries.length > 0 || data.breakdowns.sources.length > 0) && (
            <div className="ts-pages-breakdowns">
              <h2 className="ts-breakdowns-title">{__('Traffic Breakdowns')}</h2>
              
              {/* Tabs Navigation */}
              <div className="ts-breakdown-tabs">
                {data.breakdowns.devices.length > 0 && (
                  <button
                    className={`ts-breakdown-tab ${activeBreakdownTab === 'devices' ? 'active' : ''}`}
                    onClick={() => setActiveBreakdownTab('devices')}
                  >
                    <Icon name="Monitor" size={18} />
                    <span>{__('Top Devices')}</span>
                  </button>
                )}
                {data.breakdowns.countries.length > 0 && (
                  <button
                    className={`ts-breakdown-tab ${activeBreakdownTab === 'countries' ? 'active' : ''}`}
                    onClick={() => setActiveBreakdownTab('countries')}
                  >
                    <Icon name="Globe" size={18} />
                    <span>{__('Top Countries')}</span>
                  </button>
                )}
                {data.breakdowns.sources.length > 0 && (
                  <button
                    className={`ts-breakdown-tab ${activeBreakdownTab === 'sources' ? 'active' : ''}`}
                    onClick={() => setActiveBreakdownTab('sources')}
                  >
                    <Icon name="TrendingUp" size={18} />
                    <span>{__('Top Traffic Sources')}</span>
                  </button>
                )}
              </div>

              {/* Tab Content */}
              <div className="ts-breakdown-content">
                {/* Device Breakdown */}
                {activeBreakdownTab === 'devices' && data.breakdowns.devices.length > 0 && (
                  <div className="ts-breakdown-table">
                    <table>
                      <thead>
                        <tr>
                          <th>{__('Device')}</th>
                          <th>{__('Sessions')}</th>
                          <th>{__('Pageviews')}</th>
                          <th>{__('Conversions')}</th>
                          <th>{__('Revenue')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {data.breakdowns.devices.map((item, index) => (
                          <tr key={index}>
                            <td>
                              <div className="ts-breakdown-name">
                                <Icon 
                                  name={item.device === 'desktop' ? 'Monitor' : item.device === 'mobile' ? 'Smartphone' : 'Tablet'} 
                                  size={16} 
                                />
                                <span className="ts-capitalize">{item.device}</span>
                              </div>
                            </td>
                            <td>{item.sessions.toLocaleString()}</td>
                            <td>{item.pageviews.toLocaleString()}</td>
                            <td>{item.conversions.toLocaleString()}</td>
                            <td>{formatCurrency(item.revenue)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}

                {/* Country Breakdown */}
                {activeBreakdownTab === 'countries' && data.breakdowns.countries.length > 0 && (
                  <div className="ts-breakdown-table">
                    <table>
                      <thead>
                        <tr>
                          <th>{__('Country')}</th>
                          <th>{__('Sessions')}</th>
                          <th>{__('Pageviews')}</th>
                          <th>{__('Conversions')}</th>
                          <th>{__('Revenue')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {data.breakdowns.countries.map((item, index) => (
                          <tr key={index}>
                            <td>
                              <div className="ts-breakdown-name">
                                <span className="ts-country-flag">{getCountryFlag(item.country_code)}</span>
                                <span>{item.country}</span>
                              </div>
                            </td>
                            <td>{item.sessions.toLocaleString()}</td>
                            <td>{item.pageviews.toLocaleString()}</td>
                            <td>{item.conversions.toLocaleString()}</td>
                            <td>{formatCurrency(item.revenue)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}

                {/* Source Breakdown */}
                {activeBreakdownTab === 'sources' && data.breakdowns.sources.length > 0 && (
                  <div className="ts-breakdown-table">
                    <table>
                      <thead>
                        <tr>
                          <th>{__('Source / Medium')}</th>
                          <th>{__('Sessions')}</th>
                          <th>{__('Pageviews')}</th>
                          <th>{__('Conversions')}</th>
                          <th>{__('Revenue')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {data.breakdowns.sources.map((item, index) => (
                          <tr key={index}>
                            <td>
                              <div className="ts-breakdown-name">
                                <Icon name={getSourceIcon(item.source)} size={16} />
                                <span>{item.source}</span>
                              </div>
                            </td>
                            <td>{item.sessions.toLocaleString()}</td>
                            <td>{item.pageviews.toLocaleString()}</td>
                            <td>{item.conversions.toLocaleString()}</td>
                            <td>{formatCurrency(item.revenue)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Filter Toggle */}
          <div className="ts-pages-filters">
            <label className="ts-filter-toggle">
              <input
                type="checkbox"
                checked={showAllPages}
                onChange={(e) => setShowAllPages(e.target.checked)}
              />
              <span>{__('Show backend pages (admin-ajax, order-received, etc.)')}</span>
            </label>
            <span className="ts-filter-info">
              {showAllPages 
                ? `${__('Showing all')} ${data.pages.length} ${__('pages')}`
                : `${__('Showing')} ${sortedPages.length} ${__('user-facing pages')}`
              }
            </span>
          </div>

          {/* Pagination Info */}
          {sortedPages.length > itemsPerPage && (
            <div className="ts-pagination-info">
              {__('Showing')} {startIndex + 1}-{Math.min(endIndex, sortedPages.length)} {__('of')} {sortedPages.length} {__('items')}
            </div>
          )}

          {/* Pages Table */}
          <div className="ts-pages-table-container">
            <table className="ts-pages-table">
              <thead>
                <tr>
                  <th className="ts-col-page">{__('Page')}</th>
                  <th
                    className={`ts-sortable ts-col-views ${sortBy === 'views' ? 'ts-active' : ''}`}
                    onClick={() => handleSort('views')}
                  >
                    {__('Views')}
                    {sortBy === 'views' && (
                      <span className="ts-sort-icon">
                        {sortOrder === 'desc' ? '▼' : '▲'}
                      </span>
                    )}
                  </th>
                  <th
                    className={`ts-sortable ts-col-conversions ${sortBy === 'conversions' ? 'ts-active' : ''}`}
                    onClick={() => handleSort('conversions')}
                  >
                    {__('Conversions')}
                    {sortBy === 'conversions' && (
                      <span className="ts-sort-icon">
                        {sortOrder === 'desc' ? '▼' : '▲'}
                      </span>
                    )}
                  </th>
                  <th
                    className={`ts-sortable ts-col-revenue ${sortBy === 'revenue' ? 'ts-active' : ''}`}
                    onClick={() => handleSort('revenue')}
                  >
                    {__('Revenue')}
                    {sortBy === 'revenue' && (
                      <span className="ts-sort-icon">
                        {sortOrder === 'desc' ? '▼' : '▲'}
                      </span>
                    )}
                  </th>
                  <th
                    className={`ts-sortable ts-col-time ${sortBy === 'time' ? 'ts-active' : ''}`}
                    onClick={() => handleSort('time')}
                  >
                    {__('Avg Time')}
                    {sortBy === 'time' && (
                      <span className="ts-sort-icon">
                        {sortOrder === 'desc' ? '▼' : '▲'}
                      </span>
                    )}
                  </th>
                  <th
                    className={`ts-sortable ts-col-bounce ${sortBy === 'bounce' ? 'ts-active' : ''}`}
                    onClick={() => handleSort('bounce')}
                  >
                    {__('Bounce')}
                    {sortBy === 'bounce' && (
                      <span className="ts-sort-icon">
                        {sortOrder === 'desc' ? '▼' : '▲'}
                      </span>
                    )}
                  </th>
                </tr>
              </thead>
              <tbody>
                {currentPages.map((page, index) => {
                  return (
                    <tr key={index}>
                      <td className="ts-page-cell">
                        <div className="ts-page-info">
                          <span className="ts-page-icon"><Icon name={getPageIcon(page.path)} size={18} /></span>
                          <div className="ts-page-text">
                            <div className="ts-page-path" title={page.path}>
                              {page.path}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="ts-number-cell">
                        <strong>{page.views.toLocaleString()}</strong>
                      </td>
                      <td className="ts-number-cell">
                        {page.conversions && page.conversions > 0 ? (
                          <span className="ts-has-conversions">
                            <Icon name="CheckCircle" size={14} color="success" className="ts-conversion-icon" />
                            {page.conversions.toLocaleString()}
                          </span>
                        ) : (
                          <span className="ts-muted">—</span>
                        )}
                      </td>
                      <td className="ts-number-cell ts-revenue-cell">
                        {page.revenue && page.revenue > 0 ? (
                          formatCurrency(page.revenue)
                        ) : (
                          <span className="ts-muted">—</span>
                        )}
                      </td>
                      <td className="ts-number-cell">
                        <span className="ts-time-value">{page.time}</span>
                      </td>
                      <td className="ts-number-cell">
                        <span className={`ts-bounce-rate ${getBounceRateColor(page.bounce)}`}>
                          {page.bounce}
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
              <tfoot>
                <tr className="ts-total-row">
                  <td><strong>{__('Total / Average')}</strong></td>
                  <td className="ts-number-cell">
                    <strong>{totalViews.toLocaleString()}</strong>
                  </td>
                  <td className="ts-number-cell">
                    <strong>{totalConversions.toLocaleString()}</strong>
                  </td>
                  <td className="ts-number-cell">
                    <strong>{formatCurrency(totalRevenue)}</strong>
                  </td>
                  <td className="ts-number-cell">
                    <strong>{formatTime(avgTimeSeconds)}</strong>
                  </td>
                  <td className="ts-number-cell">
                    <strong>{avgBounce.toFixed(1)}%</strong>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          {/* Pagination Controls */}
          {totalPages > 1 && (
            <div className="ts-pagination">
              <button
                className="ts-pagination-btn"
                onClick={() => goToPage(1)}
                disabled={currentPage === 1}
                title={__('First page')}
              >
                «
              </button>
              <button
                className="ts-pagination-btn"
                onClick={() => goToPage(currentPage - 1)}
                disabled={currentPage === 1}
                title={__('Previous page')}
              >
                ‹
              </button>
              
              <div className="ts-pagination-pages">
                {Array.from({ length: Math.min(7, totalPages) }, (_, i) => {
                  let pageNum: number;
                  
                  if (totalPages <= 7) {
                    pageNum = i + 1;
                  } else if (currentPage <= 4) {
                    pageNum = i + 1;
                  } else if (currentPage >= totalPages - 3) {
                    pageNum = totalPages - 6 + i;
                  } else {
                    pageNum = currentPage - 3 + i;
                  }
                  
                  return (
                    <button
                      key={pageNum}
                      className={`ts-pagination-btn ${currentPage === pageNum ? 'ts-active' : ''}`}
                      onClick={() => goToPage(pageNum)}
                    >
                      {pageNum}
                    </button>
                  );
                })}
              </div>
              
              <button
                className="ts-pagination-btn"
                onClick={() => goToPage(currentPage + 1)}
                disabled={currentPage === totalPages}
                title={__('Next page')}
              >
                ›
              </button>
              <button
                className="ts-pagination-btn"
                onClick={() => goToPage(totalPages)}
                disabled={currentPage === totalPages}
                title={__('Last page')}
              >
                »
              </button>
              
              <span className="ts-pagination-summary">
                {__('Page')} {currentPage} {__('of')} {totalPages}
              </span>
            </div>
          )}

          {/* Page Insights */}
          <div className="ts-pages-footer">
            <div className="ts-info-box">
              <Icon name="Lightbulb" size={24} color="warning" className="ts-info-icon" />
              <div>
                <h4>{__('Page Performance Insights')}</h4>
                <ul>
                  <li>
                    <strong>{__('Bounce Rate')}</strong>: {__('Lower is better. Under 40% is excellent, 40-55% is good, 55-70% is average, above 70% needs improvement.')}
                  </li>
                  <li>
                    <strong>{__('Time on Page')}</strong>: {__('Higher indicates better engagement. Consider improving content on pages with low time.')}
                  </li>
                  <li>
                    <strong>{__('High Traffic + High Bounce')}</strong>: {__('Review content relevance and page experience.')}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </>
      ) : (
        <div className="ts-empty-state">
          <Icon name="FileText" size={48} color="muted" />
          <h2>{__('No page data yet')}</h2>
          <p>{__('Start tracking to see which pages visitors are viewing')}</p>
        </div>
      )}
    </div>
  );
};

export default PagesPage;
