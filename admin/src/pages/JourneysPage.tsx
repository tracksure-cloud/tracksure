/**
 * Journeys Page - Visitor-Level Analysis
 * Shows visitors across multiple sessions with attribution
 */

import React, { useState, useMemo } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { Icon } from '../components/ui/Icon';
import { __ } from '@wordpress/i18n';
import { getVisitorLabel } from '../utils/eventDisplayHelpers';
import { formatUserTime, useUserTimezone } from '../utils/timezoneHelpers';
import { classifyChannel, getChannelIcon } from '../utils/channelHelpers';
import { formatCurrency, formatLocalDate } from '../utils/parameterFormatters';
import JourneyModal from '../components/JourneyModal';
import '../styles/pages/JourneysPage.css';
import '../styles/components/JourneyModal.css';

interface Visitor {
  visitor_id: number;
  first_seen: string;
  last_seen: string;
  session_count: number;
  conversions: number;
  revenue: number;
  first_touch: string;
  last_touch: string;
  devices?: string;
}

interface VisitorsData {
  visitors: Visitor[];
  total: number;
  message?: string;
}

const JourneysPage: React.FC = () => {
  const { dateRange } = useApp();
  const timezone = useUserTimezone();
  const [filterType, setFilterType] = useState<'all' | 'converted' | 'returning'>('all');
  const [selectedVisitorId, setSelectedVisitorId] = useState<number | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 5;

  // Use centralized API query hook with automatic cleanup and optimized caching
  const { data, error, isLoading, refetch: _refetch } = useApiQuery<VisitorsData>(
    'getVisitors',
    {
      date_start: formatLocalDate(dateRange.start),
      date_end: formatLocalDate(dateRange.end),
      filter: filterType,
    },
    {
      refetchInterval: 600000, // Refetch every 10 minutes (performance optimized)
      staleTime: 300000, // Data stays fresh for 5 minutes
      retry: 2,
    }
  );

  const parseSourceMedium = (sourceMedium: string): [string, string] => {
    if (!sourceMedium || sourceMedium === 'null/null') {return ['Direct', 'none'];}
    const parts = sourceMedium.split('/');
    return [parts[0] || 'unknown', parts[1] || 'none'];
  };

  // Memoize calculations to prevent unnecessary re-renders
  const stats = useMemo(() => {
    if (!data?.visitors) {
      return { totalVisitors: 0, returningCount: 0, multiSessionCount: 0, avgSessions: '0' };
    }
    
    const total = data.visitors.length;
    const returning = data.visitors.filter(v => v.session_count > 1).length;
    const multiSession = data.visitors.filter(v => v.session_count > 2).length;
    const avgSess = total > 0 
      ? (data.visitors.reduce((sum, v) => sum + v.session_count, 0) / total).toFixed(1)
      : '0';
    
    return {
      totalVisitors: total,
      returningCount: returning,
      multiSessionCount: multiSession,
      avgSessions: avgSess,
    };
  }, [data?.visitors]);

  // Memoize filtered visitors to avoid re-filtering on every render
  const filteredVisitors = useMemo(() => {
    if (!data?.visitors) { return []; }
    
    return data.visitors.filter(visitor => {
      if (filterType === 'converted') { return visitor.conversions > 0; }
      if (filterType === 'returning') { return visitor.session_count > 1; }
      return true;
    });
  }, [data?.visitors, filterType]);

  // Pagination calculations
  const totalPages = Math.ceil(filteredVisitors.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentVisitors = filteredVisitors.slice(startIndex, endIndex);

  // Reset to page 1 when filter changes
  const handleFilterChange = (newFilter: 'all' | 'converted' | 'returning') => {
    setFilterType(newFilter);
    setCurrentPage(1);
  };

  const goToPage = (page: number) => {
    setCurrentPage(Math.max(1, Math.min(page, totalPages)));
  };

  // Skeleton loader for instant feedback
  if (isLoading) {
    return (
      <div className="ts-page">
        <div className="ts-page-header">
          <h1 className="ts-page-title">{__('Journeys')}</h1>
          <p className="ts-page-description">{__('Understand visitor behavior across multiple sessions')}</p>
        </div>
        
        {/* Summary Cards Skeleton */}
        <div className="ts-journeys-summary">
          {[1, 2, 3, 4].map(i => (
            <div key={i} className="ts-summary-card" style={{ animation: 'pulse 2s infinite' }}>
              <div style={{ background: 'var(--ts-surface-2)', width: '44px', height: '44px', borderRadius: '12px' }} />
              <div className="ts-summary-content">
                <div style={{ background: 'var(--ts-surface-2)', height: '28px', width: '80px', borderRadius: '6px', marginBottom: '8px' }} />
                <div style={{ background: 'var(--ts-surface-2)', height: '16px', width: '120px', borderRadius: '4px' }} />
              </div>
            </div>
          ))}
        </div>
        
        {/* Table Skeleton */}
        <div className="ts-visitors-table-container" style={{ marginTop: '24px' }}>
          <div style={{ background: 'var(--ts-surface)', borderRadius: '12px', padding: '24px' }}>
            {[1, 2, 3, 4, 5].map(i => (
              <div key={i} style={{ marginBottom: '16px', animation: `pulse 2s ${i * 0.1}s infinite` }}>
                <div style={{ background: 'var(--ts-surface-2)', height: '48px', borderRadius: '8px' }} />
              </div>
            ))}
          </div>
        </div>
        
        {/* Loading Spinner */}
        <div style={{ textAlign: 'center', marginTop: '24px', color: 'var(--ts-text-muted)' }}>
          <div style={{ display: 'inline-block', width: '32px', height: '32px', border: '3px solid var(--ts-border)', borderTop: '3px solid var(--ts-primary)', borderRadius: '50%', animation: 'spin 1s linear infinite' }} />
          <p style={{ marginTop: '12px', fontSize: '14px' }}>{__('Loading journeys...')}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="ts-page">
      <div className="ts-page-header">
        <h1 className="ts-page-title">{__('Journeys')}</h1>
        <p className="ts-page-description">{__('Understand visitor behavior across multiple sessions')}</p>
      </div>

      {error ? (
        <div className="ts-error-state">
          <div className="ts-error-icon"><Icon name="AlertTriangle" size={48} color="danger" /></div>
          <h2>{__('Error')}</h2>
          <p>{error?.message || __('Failed to load journeys data')}</p>
        </div>
      ) : data ? (
        <>
          {/* Summary Cards */}
          <div className="ts-journeys-summary">
            <div className="ts-summary-card">
              <span className="ts-summary-icon"><Icon name="Users" size={24} color="primary" /></span>
              <div className="ts-summary-content">
                <div className="ts-summary-value">{stats.totalVisitors.toLocaleString()}</div>
                <div className="ts-summary-label">{__('Total Visitors')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <span className="ts-summary-icon"><Icon name="RefreshCw" size={24} color="primary" /></span>
              <div className="ts-summary-content">
                <div className="ts-summary-value">{stats.returningCount.toLocaleString()}</div>
                <div className="ts-summary-label">{__('Returning Visitors')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <span className="ts-summary-icon"><Icon name="BarChart2" size={24} color="primary" /></span>
              <div className="ts-summary-content">
                <div className="ts-summary-value">{stats.multiSessionCount.toLocaleString()}</div>
                <div className="ts-summary-label">{__('Multi-Session Visitors')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <span className="ts-summary-icon"><Icon name="TrendingUp" size={24} color="primary" /></span>
              <div className="ts-summary-content">
                <div className="ts-summary-value">{stats.avgSessions}</div>
                <div className="ts-summary-label">{__('Avg Sessions/Visitor')}</div>
              </div>
            </div>
          </div>

          {/* Filters */}
          <div className="ts-journeys-filters">
            <button
              className={`ts-filter-btn ${filterType === 'all' ? 'ts-filter-btn--active' : ''}`}
              onClick={() => handleFilterChange('all')}
            >
              {__('All Visitors')} ({data?.visitors.length || 0})
            </button>
            <button
              className={`ts-filter-btn ${filterType === 'converted' ? 'ts-filter-btn--active' : ''}`}
              onClick={() => handleFilterChange('converted')}
            >
              {__('Converted Only')} ({data?.visitors.filter(v => v.conversions > 0).length || 0})
            </button>
            <button
              className={`ts-filter-btn ${filterType === 'returning' ? 'ts-filter-btn--active' : ''}`}
              onClick={() => handleFilterChange('returning')}
            >
              {__('Returning Only')} ({data?.visitors.filter(v => v.session_count > 1).length || 0})
            </button>
          </div>

          {/* Show message only if API returns one and no data */}
          {data.message && filteredVisitors.length === 0 && (
            <div className="ts-info-banner">
              <div className="ts-info-icon"><Icon name="Info" size={20} color="info" /></div>
              <div className="ts-info-content">
                <p>{data.message}</p>
              </div>
            </div>
          )}

          {/* Pagination Info */}
          {filteredVisitors.length > itemsPerPage && (
            <div className="ts-pagination-info" style={{ marginBottom: '16px' }}>
              {__('Showing')} {startIndex + 1}-{Math.min(endIndex, filteredVisitors.length)} {__('of')} {filteredVisitors.length} {__('visitors')}
            </div>
          )}

          {/* Visitors Table */}
          {filteredVisitors.length > 0 ? (
            <div className="ts-visitors-table-container">
              <table className="ts-visitors-table">
                <thead>
                  <tr>
                    <th>{__('Visitor')}</th>
                    <th>{__('First Seen')}</th>
                    <th>{__('Last Seen')}</th>
                    <th>{__('Sessions')}</th>
                    <th>{__('First Touch')}</th>
                    <th>{__('Last Touch')}</th>
                    <th>{__('Conversions')}</th>
                    <th>{__('Revenue')}</th>
                    <th>{__('Action')}</th>
                  </tr>
                </thead>
                <tbody>
                  {currentVisitors.map((visitor) => {
                    const [firstSource, firstMedium] = parseSourceMedium(visitor.first_touch);
                    const [lastSource, lastMedium] = parseSourceMedium(visitor.last_touch);
                    const firstChannel = classifyChannel(firstSource, firstMedium);
                    const lastChannel = classifyChannel(lastSource, lastMedium);

                    return (
                      <tr key={visitor.visitor_id}>
                        <td>
                          <div className="ts-visitor-cell">
                            <div className="ts-visitor-id">{getVisitorLabel(visitor.visitor_id, visitor.session_count > 1, 1)}</div>
                            {visitor.session_count > 1 && (
                              <span className="ts-visitor-badge">{__('Returning')}</span>
                            )}
                          </div>
                        </td>
                        <td>{formatUserTime(visitor.first_seen, timezone)}</td>
                        <td>{formatUserTime(visitor.last_seen, timezone)}</td>
                        <td>
                          <span className="ts-session-count">{visitor.session_count}</span>
                        </td>
                        <td>
                          <div className="ts-channel-cell">
                            <Icon name={getChannelIcon(firstChannel)} size={18} />
                            <span className="ts-channel-name">{firstChannel}</span>
                          </div>
                        </td>
                        <td>
                          <div className="ts-channel-cell">
                            <Icon name={getChannelIcon(lastChannel)} size={18} />
                            <span className="ts-channel-name">{lastChannel}</span>
                          </div>
                        </td>
                        <td>
                          {visitor.conversions > 0 ? (
                            <span className="ts-conversion-badge"><Icon name="CheckCircle" size={14} color="success" /> {visitor.conversions}</span>
                          ) : (
                            '—'
                          )}
                        </td>
                        <td className="ts-revenue-cell">
                          {visitor.revenue > 0 ? formatCurrency(visitor.revenue) : '—'}
                        </td>
                        <td>
                          <button
                            className="ts-action-btn"
                            onClick={() => setSelectedVisitorId(visitor.visitor_id)}
                          >
                            {__('View Journey')}
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>

              {/* Pagination Controls */}
              {totalPages > 1 && (
                <div className="ts-pagination" style={{ marginTop: '24px' }}>
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
            </div>
          ) : (
            <div className="ts-empty-state">
              <div className="ts-empty-icon"><Icon name="Users" size={64} color="muted" /></div>
              <h2>{__('No visitors found')}</h2>
              <p>{__('Try adjusting your filters or date range')}</p>
            </div>
          )}
        </>
      ) : null}

      {/* Journey Detail Modal */}
      {selectedVisitorId && (
        <JourneyModal
          visitorId={selectedVisitorId}
          onClose={() => setSelectedVisitorId(null)}
        />
      )}
    </div>
  );
};

export default JourneysPage;
