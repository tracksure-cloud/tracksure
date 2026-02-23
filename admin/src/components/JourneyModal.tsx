/**
 * Journey Modal - Visitor Journey Detail View
 * Shows session-by-session timeline with events and funnel visualization
 */

import React, { useState } from 'react';
import { useApiQuery } from '../hooks/useApiQuery';
import { Icon } from '../components/ui/Icon';
import { __ } from '@wordpress/i18n';
import { classifyChannel, getChannelIcon } from '../utils/channelHelpers';
import { getEventIcon } from '../utils/eventDisplayHelpers';
import { formatUserTime, useUserTimezone } from '../utils/timezoneHelpers';
import { formatDuration as formatDurationSeconds } from '../utils/parameterFormatters';
import '../styles/components/JourneyModal.css';

interface JourneyModalProps {
  visitorId: number;
  onClose: () => void;
}

interface SessionData {
  session_id: string;
  started_at: string;
  last_activity_at: string;
  utm_source: string;
  utm_medium: string;
  device_type: string;
  events: EventData[];
}

interface EventData {
  event_id: number;
  event_name: string;
  occurred_at: string;
  page_url: string;
  page_title?: string;
  event_params?: Record<string, unknown>;
  is_conversion?: boolean;
  conversion_value?: number;
}

interface JourneyData {
  visitor_id: number;
  sessions: SessionData[];
  total_events: number;
  funnel_steps: FunnelStep[];
}

interface FunnelStep {
  step: string;
  label: string;
  count: number;
  percentage: number;
}

const JourneyModal: React.FC<JourneyModalProps> = ({ visitorId, onClose }) => {
  const [selectedSessionId, setSelectedSessionId] = useState<string | null>(null);
  const timezone = useUserTimezone();

  // Fetch journey data for this visitor (all sessions)
  const { data, error, isLoading } = useApiQuery<JourneyData>(
    'getVisitorJourney',
    visitorId.toString(),
    {
      enabled: !!visitorId,
      retry: 1,
    }
  );

  const formatDate = (timestamp: number | string) => {
    return formatUserTime(timestamp, timezone);
  };

  const formatDuration = (start: number | string, lastActivity: number | string) => {
    const startTime = typeof start === 'string' ? parseInt(start, 10) : start;
    const endTime = typeof lastActivity === 'string' ? parseInt(lastActivity, 10) : lastActivity;
    const diff = endTime - startTime; // diff is in seconds (Unix timestamps)
    return formatDurationSeconds(diff);
  };

  return (
    <div className="ts-modal-overlay" onClick={onClose}>
      <div className="ts-modal-content ts-journey-modal" onClick={(e) => e.stopPropagation()}>
        <div className="ts-modal-header">
          <h2>
            <Icon name="TrendingUp" size={24} /> {__('Visitor Journey')}
          </h2>
          <button className="ts-modal-close" onClick={onClose}>
            <Icon name="X" size={20} />
          </button>
        </div>

        <div className="ts-modal-body">
          {isLoading ? (
            <div className="ts-loading-state">
              <Icon name="Loader" size={48} color="primary" />
              <p>{__('Loading journey data...')}</p>
            </div>
          ) : error ? (
            <div className="ts-error-state">
              <Icon name="AlertTriangle" size={48} color="danger" />
              <h3>{__('Error Loading Journey')}</h3>
              <p>{error?.message || __('Failed to load journey data')}</p>
            </div>
          ) : data ? (
            <>
              {/* Journey Summary */}
              <div className="ts-journey-summary">
                <div className="ts-journey-stat">
                  <Icon name="Calendar" size={20} color="muted" />
                  <div>
                    <div className="ts-stat-label">{__('Sessions')}</div>
                    <div className="ts-stat-value">{data.sessions?.length || 0}</div>
                  </div>
                </div>
                <div className="ts-journey-stat">
                  <Icon name="Activity" size={20} color="muted" />
                  <div>
                    <div className="ts-stat-label">{__('Total Events')}</div>
                    <div className="ts-stat-value">{data.total_events || 0}</div>
                  </div>
                </div>
                <div className="ts-journey-stat">
                  <Icon name="Zap" size={20} color="muted" />
                  <div>
                    <div className="ts-stat-label">{__('Avg Events/Session')}</div>
                    <div className="ts-stat-value">
                      {data.sessions?.length > 0
                        ? ((data.total_events || 0) / data.sessions.length).toFixed(1)
                        : '0'}
                    </div>
                  </div>
                </div>
              </div>

              {/* Funnel Visualization */}
              {data.funnel_steps && data.funnel_steps.length > 0 && (
                <div className="ts-journey-funnel">
                  <h3>
                    <Icon name="TrendingDown" size={18} /> {__('Conversion Funnel')}
                  </h3>
                  <div className="ts-funnel-steps">
                    {data.funnel_steps.map((step, index) => (
                      <div key={step.step} className="ts-funnel-step">
                        <div className="ts-funnel-step-header">
                          <span className="ts-funnel-step-number">{index + 1}</span>
                          <span className="ts-funnel-step-label">{step.label}</span>
                          <span className="ts-funnel-step-count">{step.count}</span>
                        </div>
                        <div className="ts-funnel-step-bar">
                          <div
                            className="ts-funnel-step-fill"
                            style={{ width: `${step.percentage}%` }}
                          />
                        </div>
                        <div className="ts-funnel-step-percentage">{step.percentage.toFixed(1)}%</div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Session Timeline */}
              <div className="ts-journey-timeline">
                <h3>
                  <Icon name="Clock" size={18} /> {__('Session Timeline')}
                </h3>

                {data.sessions && data.sessions.length > 0 ? (
                  <div className="ts-sessions-list">
                    {data.sessions.map((session, sessionIndex) => {
                      const channel = classifyChannel(session.utm_source, session.utm_medium);
                      const isExpanded = selectedSessionId === session.session_id;

                      return (
                        <div key={session.session_id} className="ts-session-card">
                          <div
                            className="ts-session-header"
                            onClick={() =>
                              setSelectedSessionId(
                                isExpanded ? null : session.session_id
                              )
                            }
                          >
                            <div className="ts-session-info">
                              <span className="ts-session-number">#{sessionIndex + 1}</span>
                              <div className="ts-session-details">
                                <div className="ts-session-date">{formatDate(session.started_at)}</div>
                                <div className="ts-session-meta">
                                  <Icon name={getChannelIcon(channel)} size={14} />
                                  <span>{channel}</span>
                                  <span className="ts-session-separator">•</span>
                                  <Icon name="Monitor" size={14} />
                                  <span>{session.device_type}</span>
                                  <span className="ts-session-separator">•</span>
                                  <Icon name="Clock" size={14} />
                                  <span>{formatDuration(session.started_at, session.last_activity_at)}</span>
                                </div>
                              </div>
                            </div>
                            <div className="ts-session-actions">
                              <span className="ts-session-event-count">
                                {session.events?.length || 0} {__('events')}
                              </span>
                              <Icon
                                name={isExpanded ? 'ChevronUp' : 'ChevronDown'}
                                size={20}
                              />
                            </div>
                          </div>

                          {isExpanded && session.events && session.events.length > 0 && (
                            <div className="ts-session-events">
                              {session.events.map((event) => (
                                  <div key={event.event_id} className="ts-event-row">
                                    <div className="ts-event-icon">
                                      <Icon name={getEventIcon(event.event_name)} size={16} />
                                    </div>
                                    <div className="ts-event-details">
                                      <div className="ts-event-name">{event.event_name}</div>
                                      <div className="ts-event-meta">
                                        {event.page_url && (
                                          <>
                                            <Icon name="FileText" size={12} />
                                            <span>{event.page_url}</span>
                                          </>
                                        )}
                                      </div>
                                    </div>
                                    <div className="ts-event-time">
                                      {formatUserTime(event.occurred_at, timezone)}
                                    </div>
                                  </div>
                              ))}
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <div className="ts-empty-state">
                    <Icon name="Inbox" size={48} color="muted" />
                    <p>{__('No sessions found')}</p>
                  </div>
                )}
              </div>
            </>
          ) : null}
        </div>

        <div className="ts-modal-footer">
          <button className="ts-btn ts-btn-secondary" onClick={onClose}>
            {__('Close')}
          </button>
        </div>
      </div>
    </div>
  );
};

export default JourneyModal;
