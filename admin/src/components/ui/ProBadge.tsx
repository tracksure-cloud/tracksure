/**
 * Pro Badge Component
 * 
 * Small badge to indicate Pro features.
 */

import React from 'react';

interface ProBadgeProps {
  size?: 'sm' | 'md' | 'lg';
  tooltip?: string;
}

export const ProBadge: React.FC<ProBadgeProps> = ({ 
  size = 'sm',
  tooltip = 'Pro Feature'
}) => {
  return (
    <span 
      className={`ts-pro-badge ts-pro-badge--${size}`}
      title={tooltip}
    >
      PRO
      <style>{`
        .ts-pro-badge {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          background: linear-gradient(135deg, #3B82F6, #8B5CF6);
          color: white;
          font-weight: 700;
          border-radius: 4px;
          padding: 2px 8px;
          font-size: 10px;
          letter-spacing: 0.05em;
          margin-left: 8px;
          vertical-align: middle;
          box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .ts-pro-badge--sm {
          font-size: 9px;
          padding: 2px 6px;
        }

        .ts-pro-badge--md {
          font-size: 11px;
          padding: 3px 10px;
        }

        .ts-pro-badge--lg {
          font-size: 12px;
          padding: 4px 12px;
        }

        .ts-pro-badge:hover {
          box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
          transform: scale(1.05);
          cursor: help;
        }
      `}</style>
    </span>
  );
};
