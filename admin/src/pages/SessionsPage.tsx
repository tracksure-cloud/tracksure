/**
 * Sessions Page - Session List with Journey Drawer
 * Shows all visitor sessions with ability to view detailed journey timeline
 */

import React, { useState, useMemo } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { JourneyDrawer } from '../components/ui/JourneyDrawer';
import { TrackingStatusBanner } from '../components/ui/TrackingStatusBanner';
import { Icon } from '../components/ui/Icon';
import type { IconName } from '../components/ui/Icon';
import { formatSourceMediumDisplay, getVisitorLabel } from '../utils/eventDisplayHelpers';
import { formatDuration, formatCurrency, formatLocalDate } from '../utils/parameterFormatters';
import { formatUserTime, useUserTimezone } from '../utils/timezoneHelpers';
import { __ } from '@wordpress/i18n';
import '../styles/pages/SessionsPage.css';

interface Session {
  session_id: string;
  visitor_id: number;
  session_number: number;
  is_returning: boolean;
  started_at: number;
  last_seen_at: number;
  source?: string | null;
  medium?: string | null;
  campaign?: string | null;
  device?: string | null;
  browser?: string | null;
  os?: string | null;
  country?: string | null;
  city?: string | null;
  events_count: number | null;
  has_conversion: boolean;
  conversion_value?: number | null;
  entry_page?: string | null;
  exit_page?: string | null;
}

interface SessionsData {
  sessions: Session[];
  total: number;
  page: number;
  per_page: number;
}

/**
 * Calculate session quality score (0-100)
 */
const getSessionQuality = (session: Session) => {
  let score = 0;
  
  // Duration (max 30 points)
  const duration = session.last_seen_at - session.started_at;
  if (duration > 300) {score += 30;} // 5+ min
  else if (duration > 120) {score += 20;} // 2-5 min
  else if (duration > 60) {score += 10;} // 1-2 min
  
  // Events (max 30 points)
  const events = session.events_count || 0;
  if (events > 10) {score += 30;}
  else if (events > 5) {score += 20;}
  else if (events > 2) {score += 10;}
  
  // Conversion (40 points)
  if (session.has_conversion) {score += 40;}
  
  return {
    score,
    label: score >= 70 ? __('Hot') : score >= 40 ? __('Warm') : __('Cold'),
    icon: (score >= 70 ? 'Flame' : score >= 40 ? 'Zap' : 'Moon') as IconName,
    color: score >= 70 ? 'danger' : score >= 40 ? 'warning' : 'muted'
  };
};

const SessionsPage: React.FC = () => {
  const { dateRange, segment } = useApp();
  const timezone = useUserTimezone();
  const [selectedSessionId, setSelectedSessionId] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [filterDevice, setFilterDevice] = useState<string>('all');
  const [filterSource, setFilterSource] = useState<string>('all');
  const [filterConversion, setFilterConversion] = useState<'all' | 'converted' | 'non-converted'>('all');

  // Use centralized API query hook with automatic cleanup
  const { data, error, isLoading, refetch: _refetch } = useApiQuery<SessionsData>(
    'getSessions',
    {
      date_start: formatLocalDate(dateRange.start),
      date_end: formatLocalDate(dateRange.end),
      segment: segment,
      page,
      per_page: 25,
    },
    {
      refetchInterval: 300000, // 5 minutes (sessions data changes slowly)
      retry: 1, // Retry only once
      staleTime: 60000, // 1-minute cache (prevents unnecessary refetches)
    }
  );

  const handleSessionClick = (sessionId: string) => {
    setSelectedSessionId(sessionId);
  };

  const handleCloseDrawer = () => {
    setSelectedSessionId(null);
  };

  // Filter sessions based on filters (memoized for performance)
  const filteredSessions = useMemo(() => {
    return data?.sessions?.filter((session) => {
      if (filterDevice !== 'all' && session.device !== filterDevice) {return false;}
      if (filterSource !== 'all' && session.source !== filterSource) {return false;}
      if (filterConversion === 'converted' && !session.has_conversion) {return false;}
      if (filterConversion === 'non-converted' && session.has_conversion) {return false;}
      return true;
    }) || [];
  }, [data?.sessions, filterDevice, filterSource, filterConversion]);

  // Derived metrics (memoized)
  const metrics = useMemo(() => {
    const totalSessions = filteredSessions.length;
    const convertedSessions = filteredSessions.filter(s => s.has_conversion).length;
    const totalRevenue = filteredSessions.reduce((sum, s) => sum + (s.conversion_value || 0), 0);
    const avgEventsPerSession = filteredSessions.length > 0
      ? filteredSessions.reduce((sum, s) => sum + (s.events_count || 0), 0) / filteredSessions.length
      : 0;
    
    return { totalSessions, convertedSessions, totalRevenue, avgEventsPerSession };
  }, [filteredSessions]);

  const { totalSessions, convertedSessions, totalRevenue, avgEventsPerSession } = metrics;

  const getDeviceIcon = (device: string | null | undefined): IconName => {
    if (!device) {return 'Monitor';}
    const icons: Record<string, IconName> = {
      desktop: 'Monitor',
      mobile: 'Smartphone',
      tablet: 'Tablet',
    };
    return icons[device.toLowerCase()] || 'Monitor';
  };

  // Get unique devices and sources for filters (memoized)
  const uniqueDevices = useMemo(() => 
    [...new Set(data?.sessions?.map(s => s.device).filter((d): d is string => Boolean(d)) || [])],
    [data?.sessions]
  );
  
  const uniqueSources = useMemo(() =>
    [...new Set(data?.sessions?.map(s => s.source).filter((s): s is string => Boolean(s)) || [])],
    [data?.sessions]
  );

  return (
    <div className="ts-page">
      <TrackingStatusBanner />
      
      <div className="ts-page-header">
        <h1 className="ts-page-title">{__('Sessions')}</h1>
        <p className="ts-page-description">
          {__('View all visitor sessions and explore their journey')}
        </p>
      </div>

      {error ? (
        <div className="ts-error-state">
          <div className="ts-error-icon"><Icon name="AlertTriangle" size={48} color="danger" /></div>
          <h2>{__('Error Loading Data')}</h2>
          <p>{error?.message || __('Failed to load sessions data')}</p>
        </div>
      ) : isLoading ? (
        <div className="ts-sessions-summary">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="ts-summary-card ts-skeleton">
              <div className="ts-skeleton-text"></div>
              <div className="ts-skeleton-text"></div>
            </div>
          ))}
        </div>
      ) : !data || !data.sessions || data.sessions.length === 0 ? (
        <div className="ts-empty-state">
          <div className="ts-empty-icon"><Icon name="Users" size={64} color="muted" /></div>
          <h2>{__('No sessions yet')}</h2>
          <p>{__('Start tracking to see visitor sessions')}</p>
        </div>
      ) : (
        <>
          {/* Summary Cards */}
          <div className="ts-sessions-summary">
            <div className="ts-summary-card">
              <span className="ts-summary-icon"><Icon name="Users" size={24} color="primary" /></span>
              <div className="ts-summary-content">
                <div className="ts-summary-value">{totalSessions.toLocaleString()}</div>
                <div className="ts-summary-label">{__('Total Sessions')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <span className="ts-summary-icon"><Icon name="CheckCircle" size={24} color="success" /></span>
              <div className="ts-summary-content">
                <div className="ts-summary-value">{convertedSessions.toLocaleString()}</div>
                <div className="ts-summary-label">{__('Conversions')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <span className="ts-summary-icon"><Icon name="DollarSign" size={24} color="success" /></span>
              <div className="ts-summary-content">
                <div className="ts-summary-value">{formatCurrency(totalRevenue)}</div>
                <div className="ts-summary-label">{__('Total Revenue')}</div>
              </div>
            </div>
            <div className="ts-summary-card">
              <span className="ts-summary-icon"><Icon name="BarChart2" size={24} color="primary" /></span>
              <div className="ts-summary-content">
                <div className="ts-summary-value">{avgEventsPerSession.toFixed(1)}</div>
                <div className="ts-summary-label">{__('Avg Events/Session')}</div>
              </div>
            </div>
          </div>

          {/* Filters */}
          <div className="ts-sessions-filters">
            <div className="ts-filter-group">
              <label>{__('Device')}:</label>
              <select value={filterDevice} onChange={(e) => setFilterDevice(e.target.value)}>
                <option value="all">{__('All Devices')}</option>
                {uniqueDevices.map((device) => (
                  <option key={device} value={device}>{device}</option>
                ))}
              </select>
            </div>
            <div className="ts-filter-group">
              <label>{__('Source')}:</label>
              <select value={filterSource} onChange={(e) => setFilterSource(e.target.value)}>
                <option value="all">{__('All Sources')}</option>
                {uniqueSources.map((source) => (
                  <option key={source} value={source}>{source}</option>
                ))}
              </select>
            </div>
            <div className="ts-filter-group">
              <label>{__('Conversions')}:</label>
              <select value={filterConversion} onChange={(e) => setFilterConversion(e.target.value as 'all' | 'converted' | 'non-converted')}>
                <option value="all">{__('All Sessions')}</option>
                <option value="converted">{__('Converted Only')}</option>
                <option value="non-converted">{__('Non-Converted')}</option>
              </select>
            </div>
          </div>

          {/* Sessions Table */}
          <div className="ts-sessions-table-container">
            <table className="ts-sessions-table">
              <thead>
                <tr>
                  <th>{__('Session')}</th>
                  <th>{__('Started')}</th>
                  <th>{__('Duration')}</th>
                  <th>{__('Entry Page')}</th>
                  <th>{__('Source')}</th>
                  <th>{__('Device')}</th>
                  <th>{__('Location')}</th>
                  <th>{__('Events')}</th>
                  <th>{__('Quality')}</th>
                  <th>{__('Conversion')}</th>
                  <th>{__('Action')}</th>
                </tr>
              </thead>
              <tbody>
                {filteredSessions.map((session) => {
                  const quality = getSessionQuality(session);
                  return (
                  <tr key={session.session_id}>
                    <td>
                      <div className="ts-session-id">
                        {session.session_id ? session.session_id.substring(0, 8) + '...' : 'N/A'}
                      </div>
                      <div className="ts-visitor-id">
                        {getVisitorLabel(
                          session.visitor_id, 
                          session.session_number > 1, 
                          session.session_number || 1
                        )}
                      </div>
                    </td>
                    <td>
                      {formatUserTime(session.started_at, timezone)}
                    </td>
                    <td>
                      {formatDuration(session.last_seen_at - session.started_at)}
                    </td>
                    <td className="ts-entry-page">
                      {session.entry_page ? (
                        <span title={session.entry_page}>
                          {session.entry_page.length > 40 ? session.entry_page.substring(0, 40) + '...' : session.entry_page}
                        </span>
                      ) : (
                        <span className="ts-direct-entry">Direct Entry</span>
                      )}
                    </td>
                    <td>
                      {formatSourceMediumDisplay(session.source || null, session.medium || null)}
                    </td>
                    <td>
                      <span className="ts-device-icon"><Icon name={getDeviceIcon(session.device)} size={16} /></span>
                      {session.device || session.browser || 'Desktop'}
                    </td>
                    <td>
                      {session.city && session.country 
                        ? `${session.city}, ${session.country}`
                        : session.country || 'Local Network'
                      }
                    </td>
                    <td>
                      <span className="ts-events-badge">{session.events_count || 0}</span>
                    </td>
                    <td>
                      <div className="ts-quality-badge" title={quality.label}>
                        <Icon name={quality.icon} size={14} color={quality.color} />
                        <span className={`ts-quality-score ts-quality-score--${quality.color}`}>{quality.score}</span>
                      </div>
                    </td>
                    <td>
                      {session.has_conversion ? (
                        <div className="ts-conversion-info">
                          <span className="ts-conversion-badge"><Icon name="CheckCircle" size={14} color="success" /></span>
                          {session.conversion_value && session.conversion_value > 0 && (
                            <span className="ts-conversion-value">
                              {formatCurrency(session.conversion_value)}
                            </span>
                          )}
                        </div>
                      ) : (
                        <span className="ts-no-conversion">—</span>
                      )}
                    </td>
                    <td>
                      <button
                        className="ts-btn-view-journey"
                        onClick={() => handleSessionClick(session.session_id)}
                        title={__('View Journey')}
                      >
                        {__('View Journey')}
                      </button>
                    </td>
                  </tr>
                )})}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {data.total > data.per_page && (
            <div className="ts-pagination">
              <button
                className="ts-btn ts-btn-secondary"
                onClick={() => setPage(page - 1)}
                disabled={page === 1}
              >
                {__('Previous')}
              </button>
              <span className="ts-pagination-info">
                {__('Page')} {page} {__('of')} {Math.ceil(data.total / data.per_page)}
              </span>
              <button
                className="ts-btn ts-btn-secondary"
                onClick={() => setPage(page + 1)}
                disabled={page >= Math.ceil(data.total / data.per_page)}
              >
                {__('Next')}
              </button>
            </div>
          )}
        </>
      )}

      {/* Journey Drawer */}
      <JourneyDrawer sessionId={selectedSessionId} onClose={handleCloseDrawer} />
    </div>
  );
};

export default SessionsPage;
