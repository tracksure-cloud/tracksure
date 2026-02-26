/**
 * TopBar - Global Navigation Header
 */

import React from 'react';
// import { useTheme } from '../../contexts/ThemeContext';
import { DateRangePicker } from '../ui/DateRangePicker';
import { ThemeToggle } from '../ui/ThemeToggle';
import { ViewModeToggle } from '../ui/ViewModeToggle';
import { SegmentFilter } from '../ui/SegmentFilter';
import { __ } from '../../utils/i18n';
import '../../styles/components/layout/TopBar.css';

export const TopBar: React.FC = () => {
  return (
    <header className="ts-topbar">
      <div className="ts-topbar-left">
        <div className="ts-topbar-logo">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
            <rect width="28" height="28" rx="6" fill="currentColor" opacity="0.1"/>
            <path d="M14 7L20 11V17L14 21L8 17V11L14 7Z" stroke="currentColor" strokeWidth="2" fill="none"/>
            <circle cx="14" cy="14" r="3" fill="currentColor"/>
          </svg>
          <span className="ts-topbar-title">TrackSure</span>
        </div>
      </div>

      <div className="ts-topbar-center">
        <DateRangePicker />
        <SegmentFilter />
      </div>

      <div className="ts-topbar-right">
        <ViewModeToggle />
        <ThemeToggle />
        <button className="ts-topbar-btn" title={__("Documentation")}>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor">
            <path d="M10 18C14.4183 18 18 14.4183 18 10C18 5.58172 14.4183 2 10 2C5.58172 2 2 5.58172 2 10C2 14.4183 5.58172 18 10 18Z" strokeWidth="1.5"/>
            <path d="M10 14V10" strokeWidth="1.5" strokeLinecap="round"/>
            <circle cx="10" cy="7" r="0.5" fill="currentColor"/>
          </svg>
        </button>
      </div>
    </header>
  );
};
