/**
 * Anomaly Alert Component
 * Displays toast notifications when unusual patterns detected
 */

import React, { useEffect, useState, useCallback } from 'react';
import { Icon } from './ui/Icon';
import { __ } from '../utils/i18n';
import '../styles/components/AnomalyAlert.css';

export interface Anomaly {
  type: 'spike' | 'drop';
  metric: string;
  value: number;
  expected: number;
  deviation: number; // % above/below expected
  severity: 'low' | 'medium' | 'high';
  timestamp: string;
  message: string;
}

interface AnomalyAlertProps {
  anomalies: Anomaly[];
  onDismiss?: (anomaly: Anomaly) => void;
}

export const AnomalyAlert: React.FC<AnomalyAlertProps> = ({ anomalies, onDismiss }) => {
  const [visibleAlerts, setVisibleAlerts] = useState<Anomaly[]>([]);
  const [dismissed, setDismissed] = useState<Set<string>>(new Set());

  // Show alerts one at a time with delay
  useEffect(() => {
    if (anomalies.length === 0) {
      return;
    }

    // Filter out dismissed alerts
    const newAlerts = anomalies.filter(
      (a) => !dismissed.has(`${a.metric}-${a.timestamp}`)
    );

    if (newAlerts.length === 0) {
      return;
    }

    // Show first alert
    if (visibleAlerts.length === 0 && newAlerts.length > 0) {
      setVisibleAlerts([newAlerts[0]]);
    }

    // Auto-show next alert after delay
    const timer = setTimeout(() => {
      if (visibleAlerts.length < newAlerts.length) {
        setVisibleAlerts([...visibleAlerts, newAlerts[visibleAlerts.length]]);
      }
    }, 3000); // 3 seconds between alerts

    return () => clearTimeout(timer);
  }, [anomalies, dismissed, visibleAlerts]);

  const handleDismiss = useCallback((anomaly: Anomaly) => {
    const key = `${anomaly.metric}-${anomaly.timestamp}`;
    setDismissed(prev => new Set(prev).add(key));
    setVisibleAlerts(prev => prev.filter(a => 
      `${a.metric}-${a.timestamp}` !== key
    ));

    if (onDismiss) {
      onDismiss(anomaly);
    }

    // Auto-hide after 10 seconds
    setTimeout(() => {
      setVisibleAlerts(prev => prev.filter(a => 
        `${a.metric}-${a.timestamp}` !== key
      ));
    }, 10000);
  }, [onDismiss]);

  const getSeverityClass = (severity: string): string => {
    switch (severity) {
      case 'high':
        return 'ts-alert--high';
      case 'medium':
        return 'ts-alert--medium';
      default:
        return 'ts-alert--low';
    }
  };

  const getSeverityIcon = (type: string) => {
    return type === 'spike' ? 'TrendingUp' : 'TrendingDown';
  };

  const formatDeviation = (deviation: number): string => {
    const sign = deviation > 0 ? '+' : '';
    return `${sign}${deviation.toFixed(1)}%`;
  };

  if (visibleAlerts.length === 0) {
    return null;
  }

  return (
    <div className="ts-anomaly-alerts">
      {visibleAlerts.map((anomaly, index) => (
        <div
          key={`${anomaly.metric}-${anomaly.timestamp}-${index}`}
          className={`ts-anomaly-alert ${getSeverityClass(anomaly.severity)}`}
          role="alert"
          aria-live="polite"
        >
          <div className="ts-alert-icon">
            <Icon 
              name={getSeverityIcon(anomaly.type)} 
              size={24}
              color={anomaly.type === 'spike' ? 'success' : 'danger'}
            />
          </div>

          <div className="ts-alert-content">
            <div className="ts-alert-header">
              <span className="ts-alert-title">
                {anomaly.type === 'spike' ? __('Unusual Spike Detected') : __('Unusual Drop Detected')}
              </span>
              <span className={`ts-alert-badge ts-alert-badge--${anomaly.severity}`}>
                {anomaly.severity === 'high' && __('High')}
                {anomaly.severity === 'medium' && __('Medium')}
                {anomaly.severity === 'low' && __('Low')}
              </span>
            </div>

            <p className="ts-alert-message">{anomaly.message}</p>

            <div className="ts-alert-metrics">
              <span className="ts-alert-metric">
                <strong>{__('Current')}:</strong> {anomaly.value.toLocaleString()}
              </span>
              <span className="ts-alert-metric">
                <strong>{__('Expected')}:</strong> {anomaly.expected.toLocaleString()}
              </span>
              <span className={`ts-alert-deviation ${anomaly.type === 'spike' ? 'positive' : 'negative'}`}>
                {formatDeviation(anomaly.deviation)}
              </span>
            </div>
          </div>

          <button
            className="ts-alert-close"
            onClick={() => handleDismiss(anomaly)}
            aria-label={__('Dismiss alert')}
          >
            <Icon name="X" size={16} />
          </button>
        </div>
      ))}
    </div>
  );
};

/**
 * Detect anomalies in time series data
 * Uses statistical analysis (mean + standard deviation)
 */
export const detectAnomalies = (
  data: Array<{ name: string; value: number }>,
  metricName: string
): Anomaly[] => {
  if (!data || data.length < 3) {
    return [];
  }

  const values = data.map(d => d.value);
  
  // Calculate mean and standard deviation
  const mean = values.reduce((sum, val) => sum + val, 0) / values.length;
  const variance = values.reduce((sum, val) => sum + Math.pow(val - mean, 2), 0) / values.length;
  const stdDev = Math.sqrt(variance);
  
  // Thresholds
  const highThreshold = mean + (3 * stdDev); // 3σ for high severity
  const lowThreshold = mean - (3 * stdDev);
  const mediumHighThreshold = mean + (2 * stdDev); // 2σ for medium severity
  const mediumLowThreshold = mean - (2 * stdDev);
  
  const anomalies: Anomaly[] = [];
  const now = new Date().toISOString();
  
  data.forEach((point) => {
    const value = point.value;
    
    // Skip if no significant deviation
    if (value >= mediumLowThreshold && value <= mediumHighThreshold) {
      return;
    }
    
    // High spike
    if (value > highThreshold) {
      const percent = (((value - mean) / mean) * 100).toFixed(1);
      anomalies.push({
        type: 'spike',
        metric: metricName,
        value,
        expected: mean,
        deviation: ((value - mean) / mean) * 100,
        severity: 'high',
        timestamp: now,
        message: `${metricName} ${__('reached')} ${value.toLocaleString()}, ${__('which is')} ${percent}% ${__('above the expected average')}.`,
      });
    }
    // Medium spike
    else if (value > mediumHighThreshold) {
      anomalies.push({
        type: 'spike',
        metric: metricName,
        value,
        expected: mean,
        deviation: ((value - mean) / mean) * 100,
        severity: 'medium',
        timestamp: now,
        message: `${metricName} ${__('is trending higher than usual at')} ${value.toLocaleString()}.`,
      });
    }
    // High drop
    else if (value < lowThreshold) {
      const percent = Math.abs(((value - mean) / mean) * 100).toFixed(1);
      anomalies.push({
        type: 'drop',
        metric: metricName,
        value,
        expected: mean,
        deviation: ((value - mean) / mean) * 100,
        severity: 'high',
        timestamp: now,
        message: `${metricName} ${__('dropped to')} ${value.toLocaleString()}, ${__('which is')} ${percent}% ${__('below the expected average')}.`,
      });
    }
    // Medium drop
    else if (value < mediumLowThreshold) {
      anomalies.push({
        type: 'drop',
        metric: metricName,
        value,
        expected: mean,
        deviation: ((value - mean) / mean) * 100,
        severity: 'medium',
        timestamp: now,
        message: `${metricName} ${__('is trending lower than usual at')} ${value.toLocaleString()}.`,
      });
    }
  });
  
  return anomalies;
};
