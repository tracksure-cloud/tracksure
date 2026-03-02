/**
 * TrackSure Journey Drawer Component
 * 
 * Modal/drawer showing full session timeline with events, attribution, and visitor info.
 */

import React, { useEffect } from 'react';
import { useApiQuery } from '../../hooks/useApiQuery';
import { useApp } from '../../contexts/AppContext';
import { Icon } from './Icon';
import { ICON_REGISTRY } from '../../config/iconRegistry';
import { Skeleton } from './Skeleton';
import { EmptyState } from './EmptyState';
import { __ } from '../../utils/i18n';
import { formatUserTime, useUserTimezone } from '../../utils/timezoneHelpers';
import type { EventRecord } from '../../utils/eventDisplayHelpers';
import {
  getEventDisplayName,
  getEventIcon,
  filterEventsForJourney,
  getVisitorLabel,
  formatSourceMediumDisplay,
} from '../../utils/eventDisplayHelpers';
import { formatLocation } from '../../utils/locationFormatters';
import {
  formatEventParameter,
  getParameterLabel,
  formatPrice,
  getSourceIcon,
} from '../../utils/parameterFormatters';
import '../../styles/components/ui/JourneyDrawer.css';

/** Journey API response types */
interface JourneySession {
  sessionId: string;
  visitorId: string;
  startedAt: string;
  lastSeenAt: string;
  source: string;
  medium: string;
  campaign: string;
  device: string;
  browser: string;
  os: string;
  country: string;
  city: string;
  region?: string;
  sessionNumber?: number;
  firstSource?: string;
  firstMedium?: string;
  firstCampaign?: string;
}

interface JourneyEvent {
  event_id: string;
  event_name: string;
  occurred_at: string;
  page_url: string;
  page_path?: string;
  page_title: string;
  event_params: Record<string, unknown>;
  is_conversion: boolean;
  conversion_value?: number;
  time_delta?: string;
}

interface JourneyTouchpoint {
  touchpoint_id: string;
  touchpoint_seq: number;
  touched_at: string;
  utm_source: string;
  utm_medium: string;
  utm_campaign: string;
  utm_term: string;
  utm_content: string;
  channel: string;
  referrer: string;
  page_url: string;
  page_title: string;
  is_conversion: boolean;
  attribution_weight: number;
}

interface JourneyData {
  session: JourneySession;
  events: JourneyEvent[];
  touchpoints: JourneyTouchpoint[];
  attribution: {
    first_touch: { source: string; medium: string; campaign: string | null; referrer: string | null; landing_page: string | null };
    last_touch: { source: string; medium: string; campaign: string | null; page_url: string | null };
  };
}

export interface JourneyDrawerProps {
  sessionId: string | null;
  onClose: () => void;
}

export const JourneyDrawer: React.FC<JourneyDrawerProps> = ({ sessionId, onClose }) => {
  const { data, isLoading: loading, error } = useApiQuery<JourneyData>('getJourney', sessionId || '', { enabled: !!sessionId });
  const { viewMode } = useApp();
  const timezone = useUserTimezone();

  // Calculate funnel steps
  const funnelSteps = React.useMemo(() => {
    if (!data || !data.events) {return [];}
    
    const steps = [];
    const hasViewedProduct = data.events.some(e => 
      e.event_name === 'view_item' || 
      e.event_name === 'page_view' && e.page_url?.includes('/product')
    );
    const hasAddedToCart = data.events.some(e => 
      e.event_name === 'add_to_cart'
    );
    const hasStartedCheckout = data.events.some(e => 
      e.event_name === 'begin_checkout' || e.event_name === 'checkout_started'
    );
    const hasPurchased = data.events.some(e => 
      e.is_conversion || e.event_name === 'purchase'
    );
    
    steps.push({ name: 'Session Started', completed: true, percentage: 100 });
    if (hasViewedProduct) {steps.push({ name: 'Viewed Product', completed: true, percentage: 80 });}
    if (hasAddedToCart) {steps.push({ name: 'Added to Cart', completed: true, percentage: 60 });}
    if (hasStartedCheckout) {steps.push({ name: 'Started Checkout', completed: true, percentage: 40 });}
    if (hasPurchased) {steps.push({ name: 'Completed Purchase', completed: true, percentage: 30 });}
    
    return steps;
  }, [data]);

  // Stable ref for onClose to avoid re-attaching event listener on every parent render
  const onCloseRef = React.useRef(onClose);
  onCloseRef.current = onClose;

  useEffect(() => {
    if (!sessionId) {return;}

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {onCloseRef.current();}
    };

    document.addEventListener('keydown', handleEscape);
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', handleEscape);
      document.body.style.overflow = '';
    };
  }, [sessionId]);

  if (!sessionId) {return null;}

  const handleExport = () => {
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `tracksure-journey-${sessionId}.json`;
    link.click();
  };

  return (
    <>
      <div className="ts-journey-drawer__overlay" onClick={onClose} />
      <div className="ts-journey-drawer">
        {/* Header */}
        <div className="ts-journey-drawer__header">
          <div>
            <h2 className="ts-journey-drawer__title">{__("Session Journey")}</h2>
            {data && (
              <p className="ts-journey-drawer__subtitle">
                {getVisitorLabel(
                  Number(data.session.visitorId),
                  (data.session.sessionNumber || 1) > 1,
                  data.session.sessionNumber || 1
                )}
              </p>
            )}
          </div>
          <button
            className="ts-journey-drawer__close"
            onClick={onClose}
            type="button"
            aria-label={__("Close")}
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        {/* Content */}
        <div className="ts-journey-drawer__content">
          {loading && (
            <div className="ts-journey-drawer__loading">
              {[...Array(5)].map((_, i) => (
                <Skeleton key={i} height="60px" style={{ marginBottom: '12px' }} />
              ))}
            </div>
          )}

          {error && (
            <EmptyState
              title="Failed to load journey"
              message={error.message}
              action={{ label: 'Try Again', onClick: () => window.location.reload() }}
            />
          )}

          {data && (
            <>
              {/* Attribution Summary */}
              <div className="ts-journey-drawer__section">
                <h3 className="ts-journey-drawer__section-title">{__("Attribution")} ({__("Last-Touch Model")})</h3>
                <div className="ts-journey-drawer__attribution">
                  <div className="ts-journey-drawer__attribution-item">
                    <span className="ts-journey-drawer__attribution-label">{__("First Touch")}</span>
                    <span className="ts-journey-drawer__attribution-value">
                      {formatSourceMediumDisplay(
                        data.attribution?.first_touch?.source || 'direct',
                        data.attribution?.first_touch?.medium || 'none'
                      )}
                      {data.attribution?.first_touch?.campaign && ` / ${data.attribution.first_touch.campaign}`}
                    </span>
                  </div>
                  <div className="ts-journey-drawer__attribution-item">
                    <span className="ts-journey-drawer__attribution-label">{__("Last Touch")}</span>
                    <span className="ts-journey-drawer__attribution-value">
                      {formatSourceMediumDisplay(
                        data.attribution?.last_touch?.source || 'direct',
                        data.attribution?.last_touch?.medium || 'none'
                      )}
                      {data.attribution?.last_touch?.campaign && ` / ${data.attribution.last_touch.campaign}`}
                    </span>
                  </div>
                </div>
              </div>

              {/* Device & Location */}
              <div className="ts-journey-drawer__section">
                <h3 className="ts-journey-drawer__section-title">{__("Device & Location")}</h3>
                <div className="ts-journey-drawer__meta">
                  <div className="ts-journey-drawer__meta-item">
                    <span className="ts-journey-drawer__meta-label">{__("Device")}:</span>
                    <span>{data.session.device || data.session.browser || 'Desktop'}</span>
                  </div>
                  <div className="ts-journey-drawer__meta-item">
                    <span className="ts-journey-drawer__meta-label">{__("Browser")}:</span>
                    <span>{data.session.browser || 'Not detected'}</span>
                  </div>
                  <div className="ts-journey-drawer__meta-item">
                    <span className="ts-journey-drawer__meta-label">{__("OS")}:</span>
                    <span>{data.session.os || 'Not detected'}</span>
                  </div>
                  <div className="ts-journey-drawer__meta-item">
                    <span className="ts-journey-drawer__meta-label">{__("Location")}:</span>
                    <span>
                      {formatLocation(data.session.city, data.session.country, data.session.region)}
                    </span>
                  </div>
                </div>
              </div>

              {/* Touchpoints */}
              {data.touchpoints && data.touchpoints.length > 0 && (
                <div className="ts-journey-drawer__section">
                  <h3 className="ts-journey-drawer__section-title">{__("Touchpoints Journey")}</h3>
                  <div className="ts-journey-drawer__timeline">
                    {data.touchpoints.map((touchpoint, index) => (
                      <div
                        key={touchpoint.touchpoint_id}
                        className={`ts-journey-drawer__event ${
                          touchpoint.is_conversion ? 'ts-journey-drawer__event--conversion' : ''
                        }`}
                      >
                        <div className="ts-journey-drawer__event-dot" />
                        <div className="ts-journey-drawer__event-content">
                          <div className="ts-journey-drawer__event-name">
                            {index === 0 && <Icon name="DoorOpen" size={16} />}
                            <Icon name={getSourceIcon(touchpoint.utm_source || touchpoint.channel, touchpoint.utm_medium)} size={18} />
                            <span>{touchpoint.channel || formatSourceMediumDisplay(touchpoint.utm_source, touchpoint.utm_medium)}</span>
                            {touchpoint.is_conversion && <Icon name={ICON_REGISTRY.goals} size={16} color="success" />}
                          </div>
                          <div className="ts-journey-drawer__event-details">
                            <div className="ts-detail-row">
                              <strong>{__("Page")}:</strong> {touchpoint.page_title || touchpoint.page_url || __("Unknown page")}
                            </div>
                            <div className="ts-detail-row">
                              <strong>{__("Source")}:</strong> {touchpoint.utm_source || 'direct'} / {touchpoint.utm_medium || 'none'}
                            </div>
                            {touchpoint.utm_campaign && (
                              <div className="ts-detail-row">
                                <strong>{__("Campaign")}:</strong> {touchpoint.utm_campaign}
                              </div>
                            )}
                          </div>
                          {touchpoint.attribution_weight > 0 && (
                            <div className="ts-journey-drawer__event-meta">
                              {__("Attribution Weight")}: {(touchpoint.attribution_weight * 100).toFixed(0)}%
                            </div>
                          )}
                          <div className="ts-journey-drawer__event-time">
                            {formatUserTime(touchpoint.touched_at, timezone)}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Goal Achievements */}
              {data.events && data.events.some(e => e.is_conversion) && (
                <div className="ts-journey-drawer__section">
                  <h3 className="ts-journey-drawer__section-title">
                    <Icon name="Target" size={16} />
                    {__("Goal Achievements")}
                  </h3>
                  <div className="ts-journey-drawer__goals">
                    {data.events
                      .filter(e => e.is_conversion)
                      .map((event, idx) => (
                        <div key={idx} className="ts-journey-drawer__goal-item">
                          <div className="ts-goal-icon">
                            <Icon name="CheckCircle" size={20} color="success" />
                          </div>
                          <div className="ts-goal-content">
                            <div className="ts-goal-name">{getEventDisplayName(event.event_name)}</div>
                            <div className="ts-goal-meta">
                              {event.conversion_value && event.conversion_value > 0 && (
                                <span className="ts-goal-value">{formatPrice(event.conversion_value)}</span>
                              )}
                              <span className="ts-goal-time">{formatUserTime(event.occurred_at, timezone)}</span>
                            </div>
                          </div>
                        </div>
                      ))}
                  </div>
                </div>
              )}

              {/* Visual Funnel */}
              {funnelSteps.length > 0 && (
                <div className="ts-journey-drawer__section">
                  <h3 className="ts-journey-drawer__section-title">{__("Conversion Funnel")}</h3>
                  <div className="ts-funnel-visualization">
                    {funnelSteps.map((step, index) => (
                      <div key={index} className="ts-funnel-step">
                        <div 
                          className="ts-funnel-bar" 
                          style={{
                            width: `${step.percentage}%`,
                            backgroundColor: step.completed 
                              ? (step.name.includes('Purchase') ? 'var(--ts-color-success)' : 'var(--ts-color-primary)')
                              : 'var(--ts-color-muted)'
                          }}
                        >
                          <span className="ts-funnel-label">{step.name}</span>
                          <span className="ts-funnel-percentage">{step.percentage}%</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Event Timeline */}
              <div className="ts-journey-drawer__section">
                <h3 className="ts-journey-drawer__section-title">
                  {__("Event Timeline")}
                  <span className="ts-mode-badge">
                    {viewMode === 'business' ? (
                      <><Icon name="BarChart2" size={16} /> Business</>
                    ) : (
                      <><Icon name="Settings" size={16} /> Debug</>
                    )}
                  </span>
                </h3>
                <div className="ts-journey-drawer__timeline">
                  {(viewMode === 'business' ? filterEventsForJourney(data.events as unknown as EventRecord[]) : data.events).map((event, _index) => {
                    const isConversion = event.is_conversion;
                    const eventIcon = getEventIcon(event.event_name);
                    const eventDisplayName = getEventDisplayName(event.event_name);

                    return (
                      <div
                        key={event.event_id}
                        className={`ts-journey-drawer__event ${
                          isConversion ? 'ts-journey-drawer__event--conversion' : ''
                        }`}
                      >
                        <div className="ts-journey-drawer__event-dot" />
                        <div className="ts-journey-drawer__event-content">
                          {event.time_delta && (
                            <span className="ts-journey-drawer__event-delta">{event.time_delta}</span>
                          )}
                          <div className="ts-journey-drawer__event-name">
                            <Icon name={eventIcon} size={18} />
                            <span>{eventDisplayName}</span>
                            {isConversion && <span className="ts-conversion-amount">{formatPrice(event.conversion_value || 0)}</span>}
                          </div>
                          <div className="ts-journey-drawer__event-details">
                            <span className="ts-journey-drawer__page-title">
                              {event.page_title || event.page_url || __("Unknown page")}
                            </span>
                            {event.page_path && (
                              <span className="ts-journey-drawer__page-path"> {event.page_path}</span>
                            )}
                            {!event.page_path && event.page_url && (
                              <span className="ts-journey-drawer__page-url"> {event.page_url}</span>
                            )}
                          </div>
                          {event.event_params && Object.keys(event.event_params).length > 0 && (
                            <div className="ts-journey-drawer__event-meta">
                              {Object.entries(event.event_params)
                                .filter(([_key, value]) => {
                                  // Hide empty, null, undefined, or "-" values
                                  return value !== null && value !== undefined && value !== '' && value !== '-';
                                })
                                .slice(0, 5)
                                .map(([key, value], idx) => (
                                  <React.Fragment key={key}>
                                    {idx > 0 && <span className="ts-param-separator">•</span>}
                                    <span className="ts-event-param-inline">
                                      <strong>{getParameterLabel(key)}:</strong> {formatEventParameter(key, value)}
                                    </span>
                                  </React.Fragment>
                                ))}
                            </div>
                          )}
                          <div className="ts-journey-drawer__event-time">
                            {formatUserTime(event.occurred_at, timezone)}
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer Actions */}
        {data && (
          <div className="ts-journey-drawer__footer">
            <button
              className="ts-journey-drawer__button ts-journey-drawer__button--secondary"
              onClick={handleExport}
              type="button"
            >
              {__("Export JSON")}
            </button>
          </div>
        )}
      </div>
    </>
  );
};
