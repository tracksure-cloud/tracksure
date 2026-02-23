/**
 * SegmentFilter - Global visitor segment selector
 * Filters: All Visitors / New / Returning / Converted
 */

import React from 'react';
import { useApp } from '../../contexts/AppContext';
import { Icon, type IconName } from './Icon';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/SegmentFilter.css';

export type SegmentType = 'all' | 'new' | 'returning' | 'converted';

export const SegmentFilter: React.FC = () => {
  const { segment, setSegment } = useApp();

  const segments: Array<{ value: SegmentType; label: string; icon: string }> = [
    { value: 'all', label: __('All Visitors'), icon: 'Users' },
    { value: 'new', label: __('New Visitors'), icon: 'Sparkles' },
    { value: 'returning', label: __('Returning'), icon: 'RefreshCw' },
    { value: 'converted', label: __('Converted'), icon: 'CheckCircle' },
  ];

  return (
    <div className="ts-segment-filter">
      <label className="ts-segment-label">{__('Segment')}:</label>
      <div className="ts-segment-buttons">
        {segments.map((seg) => (
          <button
            key={seg.value}
            className={`ts-segment-btn ${segment === seg.value ? 'ts-segment-btn--active' : ''}`}
            onClick={() => setSegment(seg.value)}
            title={seg.label}
          >
            <span className="ts-segment-icon"><Icon name={seg.icon as IconName} size={16} /></span>
            <span className="ts-segment-text">{seg.label}</span>
          </button>
        ))}
      </div>
    </div>
  );
};
