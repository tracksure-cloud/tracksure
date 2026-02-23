/**
 * ViewModeToggle - Switch between Business and Debug modes
 */

import React from 'react';
import { useApp } from '../../contexts/AppContext';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/ViewModeToggle.css';

export const ViewModeToggle: React.FC = () => {
  const { viewMode, setViewMode } = useApp();

  return (
    <div className="ts-view-mode-toggle">
      <button
        className={`ts-view-mode-btn ${viewMode === 'business' ? 'ts-view-mode-btn--active' : ''}`}
        onClick={() => setViewMode('business')}
        title={__('Business Mode - High signal events only')}
      >
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path
            d="M2 3h12M2 7h12M2 11h12"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
          />
        </svg>
        {__('Business')}
      </button>
      <button
        className={`ts-view-mode-btn ${viewMode === 'debug' ? 'ts-view-mode-btn--active' : ''}`}
        onClick={() => setViewMode('debug')}
        title={__('Debug Mode - All events and raw data')}
      >
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path
            d="M8 3v10M3 8h10M5 5l6 6M11 5L5 11"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
          />
        </svg>
        {__('Debug')}
      </button>
    </div>
  );
};
