/**
 * Tracking Status Banner
 * 
 * Shows a warning banner when tracking is disabled.
 * Displayed at the top of all admin pages.
 */

import React from 'react';
import { useApp } from '../../contexts/AppContext';
import type { TrackSureConfig } from '../../types';
import { Icon } from './Icon';
import { __ } from '../../utils/i18n';
import '../../styles/components/TrackingStatusBanner.css';

export const TrackingStatusBanner: React.FC = () => {
  const { config } = useApp();
  
  // Get tracking status from config (passed from PHP)
  const trackingEnabled = (config as TrackSureConfig & { trackingEnabled?: boolean }).trackingEnabled !== false;
  
  if (trackingEnabled) {
    return null; // Don't show banner if tracking is enabled
  }
  
  return (
    <div className="ts-tracking-status-banner ts-tracking-status-banner--disabled">
      <div className="ts-banner-icon">
        <Icon name="AlertCircle" size={24} />
      </div>
      <div className="ts-banner-content">
        <h3 className="ts-banner-title">{__('Tracking is Currently Disabled')}</h3>
        <p className="ts-banner-description">
          {__('No new data is being collected. Existing data from before tracking was disabled is still visible below. To resume tracking, go to')}{' '}
          <a href="#/settings" className="ts-banner-link">{__('Settings')}</a>{' '}
          {__('and enable the tracking option.')}
        </p>
      </div>
      <div className="ts-banner-actions">
        <button
          className="ts-button ts-button--primary"
          onClick={() => window.location.hash = '/settings'}
        >
          <Icon name="Settings" size={16} />
          <span>{__('Go to Settings')}</span>
        </button>
      </div>
    </div>
  );
};
