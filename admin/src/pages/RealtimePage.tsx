/**
 * Realtime Page - Live Activity Dashboard
 * Auto-refreshes every 10 seconds to show current visitor activity
 */

import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { getCountryName } from '../utils/countries';
import { formatTimeOnly, useUserTimezone } from '../utils/timezoneHelpers';
import { __ } from '@wordpress/i18n';
import { Icon, type IconName } from '../components/ui/Icon';
import { formatCurrency } from '../utils/parameterFormatters';
import '../styles/pages/RealtimePage.css';

interface ActivePage {
  path: string;
  users: number;
}

interface ActiveDevice {
  device: string;
  users: number;
}

interface ActiveCountry {
  country: string;
  users: number;
}

interface ActiveSource {
  source: string;
  medium: string;
  users: number;
}

interface RecentEvent {
  event: string;
  page: string;
  title?: string;
  time: number;
  is_conversion?: boolean;
  conversion_value?: number;
  params?: Record<string, string | number | boolean>;
}

interface RealTimeData {
  active_users: number;
  active_pages: ActivePage[];
  active_devices: ActiveDevice[];
  active_countries: ActiveCountry[];
  active_sources: ActiveSource[];
  recent_events: RecentEvent[];
  timestamp?: string;
  message?: string;
}

const RealtimePage: React.FC = () => {
  const { viewMode } = useApp();
  const timezone = useUserTimezone();
  const [lastUpdate, setLastUpdate] = useState<Date>(new Date());
  const [isAutoRefresh, setIsAutoRefresh] = useState(true);
  const [justRefreshed, setJustRefreshed] = useState(false);

  // Use centralized API hook with auto-refresh and cleanup
  const { data, error, isLoading, refetch } = useApiQuery<RealTimeData>(
    'getRealtime',
    undefined,
    {
      enabled: isAutoRefresh,
      refetchInterval: isAutoRefresh ? 15000 : undefined, // 15 seconds for better performance
      staleTime: 10000, // Keep data fresh for 10 seconds (reduces unnecessary fetches)
      retry: 1, // Retry only once for realtime data
      onSuccess: () => {
        setLastUpdate(new Date());
        setJustRefreshed(true);
      },
    }
  );

  // Clean up the "just refreshed" indicator after 1 second
  useEffect(() => {
    if (justRefreshed) {
      const timer = setTimeout(() => setJustRefreshed(false), 1000);
      return () => clearTimeout(timer);
    }
  }, [justRefreshed]);

  const handleManualRefresh = useCallback(async () => {
    await refetch();
  }, [refetch]);

  const formatTime = (date: Date): string => {
    return date.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  };

  // Filter events based on viewMode (memoized)
  const filteredEvents = useMemo(() => {
    if (!data?.recent_events) {return [];}
    
    if (viewMode === 'debug') {return data.recent_events;}
    
    // In business mode, hide low-signal events
    const lowSignalEvents = ['click', 'scroll', 'form_view', 'form_start'];
    return data.recent_events.filter(event => !lowSignalEvents.includes(event.event));
  }, [data?.recent_events, viewMode]);

  // Get event icon (memoized function) - Using Lucide icons instead of emoji
  const getEventIcon = useCallback((event: RecentEvent): IconName => {
    if (event.is_conversion) {
      return 'DollarSign';
    }
    
    const icons: Record<string, IconName> = {
      page_view: 'Eye',
      add_to_cart: 'ShoppingCart',
      begin_checkout: 'CreditCard',
      purchase: 'CheckCircle',
      view_item: 'Package',
      form_submit: 'FileText',
      sign_up: 'UserPlus',
      login: 'LogIn',
      click: 'MousePointer',
      scroll: 'ArrowDown',
      video_play: 'Play',
      video_complete: 'CheckSquare',
      file_download: 'Download',
    };
    return icons[event.event] || 'Activity';
  }, []);

  const getEventLabel = useCallback((eventName: string): string => {
    const labels: Record<string, string> = {
      page_view: __('Page View'),
      add_to_cart: __('Add to Cart'),
      begin_checkout: __('Begin Checkout'),
      purchase: __('Purchase'),
      view_item: __('View Item'),
      form_submit: __('Form Submit'),
      sign_up: __('Sign Up'),
      login: __('Login'),
      click: __('Click'),
      scroll: __('Scroll'),
      video_play: __('Video Play'),
      video_complete: __('Video Complete'),
      file_download: __('File Download'),
    };
    return labels[eventName] || eventName;
  }, []);

  return (
    <div className="ts-page ts-realtime-page">
      <div className="ts-page-header">
        <div>
          <h1 className="ts-page-title">{__('Real-Time')}</h1>
          <p className="ts-page-description">
            {__('Monitor visitor activity as it happens')}
          </p>
        </div>
        <div className="ts-page-actions">
          <button
            className={`ts-btn ts-btn-secondary ${isAutoRefresh ? 'ts-active' : ''}`}
            onClick={() => setIsAutoRefresh(!isAutoRefresh)}
            title={isAutoRefresh ? __('Auto-refresh enabled') : __('Auto-refresh disabled')}
          >
            <Icon name={isAutoRefresh ? 'Radio' : 'Pause'} size={16} />
            {isAutoRefresh ? __('Live') : __('Paused')}
          </button>
          <button
            className="ts-btn ts-btn-secondary"
            onClick={handleManualRefresh}
            disabled={isLoading}
          >
            <Icon name="RefreshCw" size={16} className={isLoading ? 'ts-spin' : ''} />
            {__('Refresh Now')}
          </button>
        </div>
      </div>

      <div className={`ts-realtime-status ${justRefreshed ? 'ts-pulse' : ''}`}>
        <span className="ts-realtime-indicator"></span>
        {__('Last updated')}: {formatTime(lastUpdate)}
      </div>

      {error ? (
        <div className="ts-error-state">
          <div className="ts-error-icon">⚠️</div>
          <h2>{__('Error Loading Data')}</h2>
          <p>{error.message}</p>
          <button className="ts-btn ts-btn-primary" onClick={handleManualRefresh}>
            {__('Try Again')}
          </button>
        </div>
      ) : isLoading && !data ? (
        <div className="ts-realtime-loading">
          <div className="ts-active-users-skeleton ts-skeleton">
            <div className="ts-skeleton-circle"></div>
            <div className="ts-skeleton-text"></div>
          </div>
          <div className="ts-realtime-grid">
            <div className="ts-skeleton ts-realtime-card-skeleton"></div>
            <div className="ts-skeleton ts-realtime-card-skeleton"></div>
          </div>
        </div>
      ) : data ? (
        <>
          {/* Active Users Hero - Prominent Display */}
          <div className="ts-hero-metric">
            <div className="ts-pulse-indicator"></div>
            <div className="ts-hero-value">{data.active_users}</div>
            <div className="ts-hero-label">{__('Active Visitors Right Now')}</div>
          </div>

          {data.message && (
            <div className="ts-api-status ts-realtime-message">
              <p><Icon name="CheckCircle" size={16} /> {data.message}</p>
            </div>
          )}

          {/* Main Grid - 4 Columns */}
          <div className="ts-realtime-grid-4">
            {/* Active Pages */}
            <div className="ts-realtime-card">
              <div className="ts-card-header">
                <h3><Icon name="FileText" size={18} /> {__('Active Pages')}</h3>
                <span className="ts-badge">{data.active_pages?.length || 0}</span>
              </div>
              <div className="ts-card-content ts-scrollable">
                {data.active_pages && data.active_pages.length > 0 ? (
                  <div className="ts-metric-list">
                    {data.active_pages.slice(0, 10).map((page, index) => (
                      <div key={index} className="ts-metric-item">
                        <div className="ts-metric-info">
                          <span className="ts-metric-name" title={page.path}>
                            {page.path}
                          </span>
                          <div className="ts-metric-bar">
                            <div
                              className="ts-metric-bar-fill"
                              style={{
                                width: `${(page.users / data.active_users) * 100}%`,
                              }}
                            ></div>
                          </div>
                        </div>
                        <span className="ts-metric-count">
                          {page.users}
                        </span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="ts-empty-state-mini">
                    <Icon name="FileText" size={32} color="muted" />
                    <p>{__('No active pages')}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Active Devices */}
            <div className="ts-realtime-card">
              <div className="ts-card-header">
                <h3><Icon name="Smartphone" size={18} /> {__('Devices')}</h3>
                <span className="ts-badge">{data.active_devices?.length || 0}</span>
              </div>
              <div className="ts-card-content">
                {data.active_devices && data.active_devices.length > 0 ? (
                  <div className="ts-metric-list">
                    {data.active_devices.map((device, index) => (
                      <div key={index} className="ts-metric-item">
                        <div className="ts-metric-info">
                          <div className="ts-metric-label">
                            <Icon 
                              name={device.device === 'mobile' ? 'Smartphone' : device.device === 'tablet' ? 'Tablet' : 'Monitor'} 
                              size={16} 
                            />
                            <span className="ts-metric-name">
                              {device.device.charAt(0).toUpperCase() + device.device.slice(1)}
                            </span>
                          </div>
                          <div className="ts-metric-bar">
                            <div
                              className="ts-metric-bar-fill"
                              style={{
                                width: `${(device.users / data.active_users) * 100}%`,
                              }}
                            ></div>
                          </div>
                        </div>
                        <span className="ts-metric-count">
                          {device.users}
                        </span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="ts-empty-state-mini">
                    <Icon name="Smartphone" size={32} color="muted" />
                    <p>{__('No device data')}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Active Countries */}
            <div className="ts-realtime-card">
              <div className="ts-card-header">
                <h3><Icon name="Globe" size={18} /> {__('Countries')}</h3>
                <span className="ts-badge">{data.active_countries?.length || 0}</span>
              </div>
              <div className="ts-card-content ts-scrollable">
                {data.active_countries && data.active_countries.length > 0 ? (
                  <div className="ts-metric-list">
                    {data.active_countries.slice(0, 10).map((country, index) => (
                      <div key={index} className="ts-metric-item">
                        <div className="ts-metric-info">
                          <div className="ts-metric-label">
                            <Icon name="MapPin" size={14} />
                            <span className="ts-metric-name">
                              {getCountryName(country.country)}
                            </span>
                          </div>
                          <div className="ts-metric-bar">
                            <div
                              className="ts-metric-bar-fill"
                              style={{
                                width: `${(country.users / data.active_users) * 100}%`,
                              }}
                            ></div>
                          </div>
                        </div>
                        <span className="ts-metric-count">
                          {country.users}
                        </span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="ts-empty-state-mini">
                    <Icon name="Globe" size={32} color="muted" />
                    <p>{__('No location data')}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Traffic Sources */}
            <div className="ts-realtime-card">
              <div className="ts-card-header">
                <h3><Icon name="TrendingUp" size={18} /> {__('Sources')}</h3>
                <span className="ts-badge">{data.active_sources?.length || 0}</span>
              </div>
              <div className="ts-card-content ts-scrollable">
                {data.active_sources && data.active_sources.length > 0 ? (
                  <div className="ts-metric-list">
                    {data.active_sources.slice(0, 10).map((source, index) => (
                      <div key={index} className="ts-metric-item">
                        <div className="ts-metric-info">
                          <div className="ts-metric-label">
                            <Icon name="Link" size={14} />
                            <span className="ts-metric-name">
                              {source.source}
                              <span className="ts-metric-secondary"> / {source.medium}</span>
                            </span>
                          </div>
                          <div className="ts-metric-bar">
                            <div
                              className="ts-metric-bar-fill"
                              style={{
                                width: `${(source.users / data.active_users) * 100}%`,
                              }}
                            ></div>
                          </div>
                        </div>
                        <span className="ts-metric-count">
                          {source.users}
                        </span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="ts-empty-state-mini">
                    <Icon name="TrendingUp" size={32} color="muted" />
                    <p>{__('No source data')}</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Recent Events - Full Width */}
          <div className="ts-realtime-card ts-realtime-events">
            <div className="ts-card-header">
              <h3><Icon name="Activity" size={18} /> {__('Recent Activity')}</h3>
              <span className="ts-badge">{filteredEvents.length}</span>
            </div>
            <div className="ts-card-content">
              {filteredEvents.length > 0 ? (
                <div className="ts-event-stream">
                  {filteredEvents.map((event, index) => (
                    <div 
                      key={index} 
                      className={`ts-event-item ts-slide-in ${event.is_conversion ? 'ts-conversion-event' : ''}`}
                      style={{ animationDelay: `${index * 30}ms` }}
                    >
                      <div className="ts-event-icon">
                        <Icon 
                          name={getEventIcon(event)} 
                          size={18} 
                          color={event.is_conversion ? 'success' : 'default'}
                        />
                      </div>
                      <div className="ts-event-details">
                        <div className="ts-event-name">
                          {getEventLabel(event.event)}
                          {event.is_conversion && (
                            <span className="ts-conversion-badge">
                              <Icon name="Zap" size={12} />
                            </span>
                          )}
                          {event.conversion_value && (
                            <span className="ts-conversion-value">
                              {formatCurrency(event.conversion_value)}
                            </span>
                          )}
                        </div>
                        <div className="ts-event-page" title={event.page}>
                          {event.title || event.page}
                        </div>
                      </div>
                      <div className="ts-event-time">{formatTimeOnly(event.time, timezone)}</div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="ts-empty-state-mini">
                  <Icon name="Activity" size={32} color="muted" />
                  <p>{__('No recent activity')}</p>
                </div>
              )}
            </div>
          </div>

          {/* Info Footer */}
          <div className="ts-realtime-footer">
            <div className="ts-info-box">
              <Icon name="Info" size={18} className="ts-info-icon" />
              <p>
                {__(
                  'Real-time data shows activity from the last 5 minutes. Updates automatically every 15 seconds.'
                )}
              </p>
            </div>
          </div>
        </>
      ) : null}
    </div>
  );
};

export default RealtimePage;
