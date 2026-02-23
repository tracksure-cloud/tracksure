/**
 * TrackSure EmptyState Component
 * 
 * Display when no data is available with helpful message and action.
 */

import React from 'react';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/EmptyState.css';

export interface EmptyStateProps {
  icon?: React.ReactNode;
  title: string;
  message?: string;
  action?: {
    label: string;
    onClick: () => void;
  };
  className?: string;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  icon,
  title,
  message,
  action,
  className = '',
}) => {
  const defaultIcon = (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
      />
    </svg>
  );

  return (
    <div className={`ts-empty-state ${className}`}>
      <div className="ts-empty-state__icon">{icon || defaultIcon}</div>
      <h3 className="ts-empty-state__title">{title}</h3>
      {message && <p className="ts-empty-state__message">{message}</p>}
      {action && (
        <button
          className="ts-empty-state__button"
          onClick={action.onClick}
          type="button"
        >
          {action.label}
        </button>
      )}
    </div>
  );
};
