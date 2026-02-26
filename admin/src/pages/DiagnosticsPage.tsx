/**
 * TrackSure Diagnostics Page
 * 
 * System health monitoring and testing tools.
 */

import React, { useState } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { TrackingStatusBanner } from '../components/ui/TrackingStatusBanner';
import { Icon } from '../components/ui/Icon';
import { formatUserTime, useUserTimezone } from '../utils/timezoneHelpers';
import { __ } from '../utils/i18n';
import '../styles/pages/DiagnosticsPage.css';

interface Log {
  log_id: number;
  level: string;
  message: string;
  occurred_at: string;
  context_json?: string;
}

interface HealthTile {
  title: string;
  status: 'healthy' | 'warning' | 'error';
  message: string;
  lastChecked?: string;
}

interface DeliveryStats {
  total: number;
  browser_count: number;
  server_count: number;
  both_count: number;
  browser_percent: number;
  server_percent: number;
  both_percent: number;
  server_only?: number;
  browser_only?: number;
  server_only_percent?: number;
  browser_only_percent?: number;
}

interface EventDeliveryStats {
  event_name: string;
  total: number;
  browser_count: number;
  server_count: number;
  both_count: number;
  browser_percent: number;
  server_percent: number;
  both_percent: number;
}

interface TestResult {
  status: 'success' | 'error' | 'idle' | 'running';
  message?: string;
  data?: unknown;
  details?: string | null;
}

/** Response shape from GET /diagnostics/health */
interface HealthCheck {
  status: 'healthy' | 'warning' | 'error';
  message: string;
  count?: number;
  period?: string;
}

interface HealthData {
  status: 'healthy' | 'warning' | 'error';
  checks: {
    database?: HealthCheck;
    tables?: HealthCheck;
    tracking?: HealthCheck;
    recent_events?: HealthCheck;
    delivery?: HealthCheck;
  };
  delivery_stats?: Record<string, number>;
  timestamp: number;
}

/** Response shapes for diagnostic test endpoints */
interface PageViewTestResult {
  success_count: number;
  results: { success: Array<{ event_id: number }> };
}

interface RegistryTestResult {
  events?: Array<unknown>;
}

interface CronTestResult {
  cron_enabled: boolean;
  tracksure_jobs?: Array<unknown>;
}

const DiagnosticsPage: React.FC = () => {
  const { config } = useApp();
  const timezone = useUserTimezone();
  const [testing, setTesting] = useState<string | null>(null);
  const [testResults, setTestResults] = useState<Record<string, TestResult>>({});
  const [deliveryPeriod, setDeliveryPeriod] = useState<'1h' | '24h' | '7d' | '30d'>('7d');

  // Fetch system health with auto-refresh every 5 minutes
  const { 
    data: healthData, 
    error: healthError, 
    isLoading: loadingHealth 
  } = useApiQuery<HealthData>(
    'getDiagnosticsHealth',
    {},
    { refetchInterval: 300000 } // 5 minutes
  );

  // Fetch recent error logs with auto-refresh every 2 minutes
  const { 
    data: logsData, 
    error: _logsError, 
    isLoading: loadingLogs 
  } = useApiQuery<{ logs: Log[] }>(
    'getLogs',
    { limit: 20, level: 'error' },
    { refetchInterval: 120000 } // 2 minutes
  );

  // Fetch delivery stats based on selected period
  const { 
    data: deliveryStats, 
    error: _deliveryError, 
    isLoading: loadingDelivery 
  } = useApiQuery<{overall: DeliveryStats, by_event: EventDeliveryStats[]}>(
    'getDiagnosticsDelivery',
    { period: deliveryPeriod },
    { refetchInterval: 180000 } // 3 minutes
  );

  // Parse health data into tiles
  const healthTiles: HealthTile[] = React.useMemo(() => {
    if (!healthData || !healthData.checks) {
      if (healthError) {
        return [{
          title: __("System Health"),
          status: 'warning',
          message: __("Diagnostics API not yet implemented - Coming in v2.1"),
          lastChecked: formatUserTime(Math.floor(Date.now() / 1000), timezone),
        }];
      }
      return [];
    }

    const tiles: HealthTile[] = [];
    const data = healthData;

    // Database health
    if (data.checks.database) {
      tiles.push({
        title: __("Database Connection"),
        status: data.checks.database.status,
        message: data.checks.database.message,
        lastChecked: formatUserTime(data.timestamp, timezone),
      });
    }

    // Tables health
    if (data.checks.tables) {
      tiles.push({
        title: __("Database Tables"),
        status: data.checks.tables.status,
        message: data.checks.tables.message,
        lastChecked: formatUserTime(data.timestamp, timezone),
      });
    }

    // Tracking status
    if (data.checks.tracking) {
      tiles.push({
        title: __("Tracking Status"),
        status: data.checks.tracking.status,
        message: data.checks.tracking.message,
        lastChecked: formatUserTime(data.timestamp, timezone),
      });
    }

    // Recent events
    if (data.checks.recent_events) {
      tiles.push({
        title: __("Recent Events"),
        status: data.checks.recent_events.status,
        message: data.checks.recent_events.message,
        lastChecked: formatUserTime(data.timestamp, timezone),
      });
    }

    return tiles;
  }, [healthData, healthError, timezone]);

  const logs = logsData?.logs || [];

  const runTest = async (testName: string) => {
    const abortController = new AbortController();
    setTesting(testName);
    setTestResults((prev) => ({ ...prev, [testName]: { status: 'running' } }));

    try {
      let result;

      switch (testName) {
        case 'page_view':
          result = await fetch(`${config.apiUrl}/events`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': config.nonce,
            },
            body: JSON.stringify({
              events: [
                {
                  event_name: 'page_view',
                  page_path: '/diagnostics-test',
                  page_title: 'Diagnostics Test',
                  occurred_at: new Date().toISOString(),
                },
              ],
            }),
            signal: abortController.signal,
          });
          break;

        case 'rest_api':
          result = await fetch(`${config.apiUrl}/query/registry`, {
            headers: {
              'X-WP-Nonce': config.nonce,
            },
            signal: abortController.signal,
          });
          break;

        case 'cron':
          result = await fetch(`${config.apiUrl}/diagnostics/cron`, {
            headers: {
              'X-WP-Nonce': config.nonce,
            },
            signal: abortController.signal,
          });
          break;

        default:
          throw new Error(__("Unknown test"));
      }

      const data = await result.json();

      setTestResults((prev) => ({
        ...prev,
        [testName]: {
          status: result.ok ? 'success' : 'error',
          message: result.ok
            ? __("Test completed successfully")
            : data.message || __("Test failed"),
          data,
          details: result.ok ? generateTestDetails(testName, data) : null,
        },
      }));
    } catch (error: unknown) {
      // Ignore abort errors (user navigated away)
      if (error instanceof Error && error.name === 'AbortError') {
        return;
      }

      setTestResults((prev) => ({
        ...prev,
        [testName]: {
          status: 'error',
          message: error instanceof Error ? error.message : __("Test failed"),
        },
      }));
    } finally {
      setTesting(null);
    }
  };

  const generateTestDetails = (testName: string, data: unknown): string => {
    switch (testName) {
      case 'page_view': {
        const result = data as PageViewTestResult;
        if (result.success_count > 0) {
          return __(`Event recorded successfully (ID: ${result.results.success[0]?.event_id})`);
        }
        return __("Event failed to record");
      }
      
      case 'rest_api': {
        const result = data as RegistryTestResult;
        return __(`Registry loaded with ${result.events?.length || 0} events`);
      }
      
      case 'cron': {
        const result = data as CronTestResult;
        if (result.cron_enabled) {
          return __(`WP-Cron enabled, ${result.tracksure_jobs?.length || 0} TrackSure jobs scheduled`);
        }
        return __("WP-Cron is disabled");
      }
      
      default:
        return __("Test completed");
    }
  };

  return (
    <div className="ts-diagnostics-page">      <TrackingStatusBanner />
            <div className="ts-page-header">
        <h1 className="ts-page-title">{__("Diagnostics")}</h1>
        <p className="ts-page-description">
          {__("Monitor system health and run diagnostic tests")}
        </p>
      </div>

      {/* Health Tiles */}
      <div className="ts-diagnostics-grid">
        {loadingHealth ? (
          <p className="ts-text-muted">{__("Loading system health...")}</p>
        ) : healthTiles.length === 0 ? (
          <p className="ts-text-muted">{__("No health checks available")}</p>
        ) : (
          healthTiles.map((tile) => (
            <div key={tile.title} className={`ts-diagnostics-tile ts-diagnostics-tile--${tile.status}`}>
              <div className="ts-diagnostics-tile__header">
                <h3 className="ts-diagnostics-tile__title">{tile.title}</h3>
                <span className={`ts-diagnostics-tile__status ts-diagnostics-tile__status--${tile.status}`}>
                  {tile.status === 'healthy' && <Icon name="CheckCircle" size={20} color="success" />}
                  {tile.status === 'warning' && <Icon name="AlertTriangle" size={20} color="warning" />}
                  {tile.status === 'error' && <Icon name="XCircle" size={20} color="danger" />}
                </span>
              </div>
              <p className="ts-diagnostics-tile__message">{tile.message}</p>
              {tile.lastChecked && (
                <p className="ts-diagnostics-tile__timestamp">{__("Last checked")}: {tile.lastChecked}</p>
              )}
            </div>
          ))
        )}
      </div>

      {/* Delivery Statistics */}
      <div className="ts-diagnostics-delivery">
        <div className="ts-diagnostics-delivery__header">
          <h2 className="ts-diagnostics-delivery__title">{__("Event Delivery Tracking")}</h2>
          <select 
            value={deliveryPeriod} 
            onChange={(e) => setDeliveryPeriod(e.target.value as '1h' | '24h' | '7d' | '30d')}
            className="ts-diagnostics-delivery__period-select"
          >
            <option value="1h">{__("Last Hour")}</option>
            <option value="24h">{__("Last 24 Hours")}</option>
            <option value="7d">{__("Last 7 Days")}</option>
            <option value="30d">{__("Last 30 Days")}</option>
          </select>
        </div>
        
        {loadingDelivery ? (
          <p className="ts-text-muted">{__("Loading delivery stats...")}</p>
        ) : !deliveryStats || !deliveryStats.overall ? (
          <p className="ts-text-muted">{__("No delivery data available")}</p>
        ) : (
          <>
            {/* Overall Stats Cards */}
            <div className="ts-diagnostics-delivery__cards">
              <div className="ts-diagnostics-delivery__card">
                <div className="ts-diagnostics-delivery__card-icon"><Icon name="BarChart2" size={24} color="primary" /></div>
                <div className="ts-diagnostics-delivery__card-content">
                  <div className="ts-diagnostics-delivery__card-label">{__("Total Events")}</div>
                  <div className="ts-diagnostics-delivery__card-value">{(deliveryStats.overall.total || 0).toLocaleString()}</div>
                </div>
              </div>

              <div className="ts-diagnostics-delivery__card ts-diagnostics-delivery__card--success">
                <div className="ts-diagnostics-delivery__card-icon"><Icon name="CheckCircle" size={24} color="success" /></div>
                <div className="ts-diagnostics-delivery__card-content">
                  <div className="ts-diagnostics-delivery__card-label">{__("Browser + Server")}</div>
                  <div className="ts-diagnostics-delivery__card-value">
                    {(deliveryStats.overall.both_count || 0).toLocaleString()}
                    <span className="ts-diagnostics-delivery__card-percent">
                      ({(deliveryStats.overall.both_percent || 0)}%)
                    </span>
                  </div>
                  <div className="ts-diagnostics-delivery__card-sublabel">
                    {__("Both fired (ideal for advertisers)")}
                  </div>
                </div>
              </div>

              <div className="ts-diagnostics-delivery__card ts-diagnostics-delivery__card--warning">
                <div className="ts-diagnostics-delivery__card-icon"><Icon name="AlertTriangle" size={24} color="warning" /></div>
                <div className="ts-diagnostics-delivery__card-content">
                  <div className="ts-diagnostics-delivery__card-label">{__("Server Only")}</div>
                  <div className="ts-diagnostics-delivery__card-value">
                    {(deliveryStats.overall.server_only || 0).toLocaleString()}
                    <span className="ts-diagnostics-delivery__card-percent">
                      ({deliveryStats.overall.server_only_percent || 0}%)
                    </span>
                  </div>
                  <div className="ts-diagnostics-delivery__card-sublabel">
                    {__("Ad blockers may be active")}
                  </div>
                </div>
              </div>

              <div className="ts-diagnostics-delivery__card ts-diagnostics-delivery__card--info">
                <div className="ts-diagnostics-delivery__card-icon"><Icon name="Info" size={24} color="info" /></div>
                <div className="ts-diagnostics-delivery__card-content">
                  <div className="ts-diagnostics-delivery__card-label">{__("Browser Only")}</div>
                  <div className="ts-diagnostics-delivery__card-value">
                    {(deliveryStats.overall.browser_only || 0).toLocaleString()}
                    <span className="ts-diagnostics-delivery__card-percent">
                      ({deliveryStats.overall.browser_only_percent || 0}%)
                    </span>
                  </div>
                  <div className="ts-diagnostics-delivery__card-sublabel">
                    {__("Server delivery pending")}
                  </div>
                </div>
              </div>
            </div>

            {/* Per-Event Breakdown */}
            {deliveryStats.by_event && deliveryStats.by_event.length > 0 && (
              <div className="ts-diagnostics-delivery__events">
                <h3 className="ts-diagnostics-delivery__events-title">{__("Delivery by Event Type")}</h3>
                <div className="ts-diagnostics-delivery__table">
                  <table>
                    <thead>
                      <tr>
                        <th>{__("Event")}</th>
                        <th>{__("Total")}</th>
                        <th>{__("Browser")}</th>
                        <th>{__("Server")}</th>
                        <th>{__("Both")}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {deliveryStats.by_event.map((event) => (
                        <tr key={event.event_name}>
                          <td className="ts-diagnostics-delivery__event-name">{event.event_name}</td>
                          <td>{event.total.toLocaleString()}</td>
                          <td>
                            <span className="ts-diagnostics-delivery__percent">
                              {event.browser_count.toLocaleString()} ({event.browser_percent}%)
                            </span>
                          </td>
                          <td>
                            <span className="ts-diagnostics-delivery__percent">
                              {event.server_count.toLocaleString()} ({event.server_percent}%)
                            </span>
                          </td>
                          <td>
                            <span className="ts-diagnostics-delivery__percent ts-diagnostics-delivery__percent--success">
                              {event.both_count.toLocaleString()} ({event.both_percent}%)
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Explanation */}
            <div className="ts-diagnostics-delivery__explanation">
              <h4>{__("Understanding Delivery Tracking")}</h4>
              <ul>
                <li>
                  <strong>{__("Browser + Server (Both):")}</strong> {__("Ideal for advertisers. Events fired from both browser pixels and server API, giving ad platforms maximum signal quality.")}
                </li>
                <li>
                  <strong>{__("Server Only:")}</strong> {__("Common when users have ad blockers or strict privacy settings. Server tracking ensures no events are lost.")}
                </li>
                <li>
                  <strong>{__("Browser Only:")}</strong> {__("Rare. Usually means server delivery is pending or temporarily delayed.")}
                </li>
              </ul>
            </div>
          </>
        )}
      </div>

      {/* : healthTiles.length === 0 ? (
          <p className="ts-text-muted">{__("No health checks available")}</p>
        ) : (
          healthTiles.map((tile) => (
            <div key={tile.title} className={`ts-diagnostics-tile ts-diagnostics-tile--${tile.status}`}>
              <div className="ts-diagnostics-tile__header">
                <h3 className="ts-diagnostics-tile__title">{tile.title}</h3>
                <span className={`ts-diagnostics-tile__status ts-diagnostics-tile__status--${tile.status}`}>
                  {tile.status === 'healthy' && <Icon name="CheckCircle" size={20} color="success" />}
                  {tile.status === 'warning' && <Icon name="AlertTriangle" size={20} color="warning" />}
                  {tile.status === 'error' && <Icon name="XCircle" size={20} color="danger" />}
                </span>
              </div>
              <p className="ts-diagnostics-tile__message">{tile.message}</p>
              {tile.lastChecked && (
                <p className="ts-diagnostics-tile__timestamp">{__("Last checked")}: {tile.lastChecked}</p>
              )}
            </div>
          ))
        )}
      </div>

      {/* Test Buttons */}
      <div className="ts-diagnostics-tests">
        <h2 className="ts-diagnostics-tests__title">{__("Run Tests")}</h2>
        <div className="ts-diagnostics-tests__grid">
          <div className="ts-diagnostics-test">
            <h3 className="ts-diagnostics-test__title">{__("Send Test Page View")}</h3>
            <p className="ts-diagnostics-test__description">
              {__("Sends a test page_view event to verify ingestion pipeline")}
            </p>
            <button
              className="ts-diagnostics-test__button"
              onClick={() => runTest('page_view')}
              disabled={testing === 'page_view'}
              type="button"
            >
              {testing === 'page_view' ? __("Running...") : __("Run Test")}
            </button>
            {testResults.page_view && (
              <div className={`ts-diagnostics-test__result ts-diagnostics-test__result--${testResults.page_view.status}`}>
                <div>{testResults.page_view.message}</div>
                {testResults.page_view.details && (
                  <div className="ts-diagnostics-test__details">{testResults.page_view.details}</div>
                )}
              </div>
            )}
          </div>

          <div className="ts-diagnostics-test">
            <h3 className="ts-diagnostics-test__title">{__("Verify REST API")}</h3>
            <p className="ts-diagnostics-test__description">
              {__("Tests REST API reachability and authentication")}
            </p>
            <button
              className="ts-diagnostics-test__button"
              onClick={() => runTest('rest_api')}
              disabled={testing === 'rest_api'}
              type="button"
            >
              {testing === 'rest_api' ? __("Running...") : __("Run Test")}
            </button>
            {testResults.rest_api && (
              <div className={`ts-diagnostics-test__result ts-diagnostics-test__result--${testResults.rest_api.status}`}>
                <div>{testResults.rest_api.message}</div>
                {testResults.rest_api.details && (
                  <div className="ts-diagnostics-test__details">{testResults.rest_api.details}</div>
                )}
              </div>
            )}
          </div>

          <div className="ts-diagnostics-test">
            <h3 className="ts-diagnostics-test__title">{__("Verify WP Cron")}</h3>
            <p className="ts-diagnostics-test__description">
              {__("Checks if WP-Cron/Action Scheduler is working properly")}
            </p>
            <button
              className="ts-diagnostics-test__button"
              onClick={() => runTest('cron')}
              disabled={testing === 'cron'}
              type="button"
            >
              {testing === 'cron' ? __("Running...") : __("Run Test")}
            </button>
            {testResults.cron && (
              <div className={`ts-diagnostics-test__result ts-diagnostics-test__result--${testResults.cron.status}`}>
                <div>{testResults.cron.message}</div>
                {testResults.cron.details && (
                  <div className="ts-diagnostics-test__details">{testResults.cron.details}</div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* System Info */}
      <div className="ts-diagnostics-info">
        <h2 className="ts-diagnostics-info__title">{__("System Information")}</h2>
        <div className="ts-diagnostics-info__grid">
          <div className="ts-diagnostics-info__item">
            <span className="ts-diagnostics-info__label">{__("Site URL")}:</span>
            <span className="ts-diagnostics-info__value">{config.siteUrl}</span>
          </div>
          <div className="ts-diagnostics-info__item">
            <span className="ts-diagnostics-info__label">{__("API URL")}:</span>
            <span className="ts-diagnostics-info__value">{config.apiUrl}</span>
          </div>
          <div className="ts-diagnostics-info__item">
            <span className="ts-diagnostics-info__label">{__("Timezone")}:</span>
            <span className="ts-diagnostics-info__value">{config.timezone}</span>
          </div>
          <div className="ts-diagnostics-info__item">
            <span className="ts-diagnostics-info__label">{__("Date Format")}:</span>
            <span className="ts-diagnostics-info__value">{config.dateFormat}</span>
          </div>
        </div>
      </div>

      {/* Recent Errors */}
      <div className="ts-diagnostics-logs">
        <h2 className="ts-diagnostics-logs__title">{__("Recent Errors")}</h2>
        {loadingLogs ? (
          <p className="ts-text-muted">{__("Loading logs...")}</p>
        ) : logs.length === 0 ? (
          <p className="ts-text-muted">{__("No errors logged in the last 30 days")}</p>
        ) : (
          <div className="ts-diagnostics-logs__list">
            {logs.map((log) => (
              <div key={log.log_id} className="ts-diagnostics-logs__item">
                <span className={`ts-diagnostics-logs__level ts-diagnostics-logs__level--${log.level}`}>
                  {log.level}
                </span>
                <span className="ts-diagnostics-logs__message">{log.message}</span>
                <span className="ts-diagnostics-logs__time">
                  {formatUserTime(log.occurred_at, timezone)}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default DiagnosticsPage;
