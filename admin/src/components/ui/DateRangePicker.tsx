/**
 * Date Range Picker Component
 */

import React, { useState } from 'react';
import { useApp } from '../../contexts/AppContext';
import { format, subDays, startOfDay, endOfDay, startOfMonth, endOfMonth, subMonths } from 'date-fns';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/DateRangePicker.css';

type PresetType = 'today' | 'yesterday' | 'this_month' | 'last_month' | 'days';

interface Preset {
  label: string;
  type: PresetType;
  days?: number;
}

const presets: Preset[] = [
  { label: __("Today"), type: 'today' },
  { label: __("Yesterday"), type: 'yesterday' },
  { label: __("Last 7 days"), type: 'days', days: 6 },
  { label: __("Last 30 days"), type: 'days', days: 29 },
  { label: __("Last 90 days"), type: 'days', days: 89 },
  { label: __("This month"), type: 'this_month' },
  { label: __("Last month"), type: 'last_month' },
];

export const DateRangePicker: React.FC = () => {
  const { dateRange, setDateRange } = useApp();
  const [isOpen, setIsOpen] = useState(false);

  const handlePreset = (preset: Preset) => {
    const now = new Date();
    let start: Date;
    let end: Date;

    switch (preset.type) {
      case 'today':
        start = startOfDay(now);
        end = endOfDay(now);
        break;
      case 'yesterday': {
        const yesterday = subDays(now, 1);
        start = startOfDay(yesterday);
        end = endOfDay(yesterday);
        break;
      }
      case 'this_month':
        start = startOfMonth(now);
        end = endOfDay(now);
        break;
      case 'last_month': {
        const lastMonth = subMonths(now, 1);
        start = startOfMonth(lastMonth);
        end = endOfMonth(lastMonth);
        break;
      }
      case 'days':
      default:
        end = endOfDay(now);
        start = startOfDay(subDays(end, preset.days || 0));
        break;
    }

    setDateRange({ start, end });
    setIsOpen(false);
  };

  const formattedRange = `${format(dateRange.start, 'MMM d, yyyy')} - ${format(dateRange.end, 'MMM d, yyyy')}`;

  return (
    <div className="ts-date-picker">
      <button className="ts-date-picker-trigger" onClick={() => setIsOpen(!isOpen)}>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
          <rect x="2" y="3" width="12" height="11" rx="1.5" strokeWidth="1.5" />
          <path d="M2 6H14" strokeWidth="1.5" />
          <path d="M5 1.5V4.5M11 1.5V4.5" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
        <span className="ts-date-picker-range">
          {formattedRange}
        </span>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor">
          <path d="M3 4.5L6 7.5L9 4.5" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </button>

      {isOpen && (
        <div className="ts-date-picker-dropdown">
          <div className="ts-date-picker-presets">
            {presets.map((preset) => (
              <button key={preset.label} onClick={() => handlePreset(preset)} className="ts-date-picker-preset">
                {preset.label}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};