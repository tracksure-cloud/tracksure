/**
 * TrackSure Skeleton Loader Component
 * 
 * Loading placeholder component with pulse animation.
 */

import React from 'react';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/Skeleton.css';

export interface SkeletonProps {
  width?: string | number;
  height?: string | number;
  borderRadius?: string | number;
  className?: string;
  style?: React.CSSProperties;
}

export const Skeleton: React.FC<SkeletonProps> = ({
  width = '100%',
  height = '20px',
  borderRadius = '4px',
  className = '',
  style,
}) => {
  return (
    <div
      className={`ts-skeleton ${className}`}
      style={{
        width: typeof width === 'number' ? `${width}px` : width,
        height: typeof height === 'number' ? `${height}px` : height,
        borderRadius: typeof borderRadius === 'number' ? `${borderRadius}px` : borderRadius,
        ...style,
      }}
    />
  );
};

/**
 * Skeleton for KPI Card
 */
export const SkeletonKPI: React.FC = () => {
  return (
    <div className="ts-skeleton-kpi">
      <Skeleton width="40%" height="16px" />
      <Skeleton width="60%" height="32px" style={{ marginTop: '12px' }} />
      <Skeleton width="30%" height="14px" style={{ marginTop: '8px' }} />
    </div>
  );
};

/**
 * Skeleton for Chart
 */
export const SkeletonChart: React.FC<{ height?: number }> = ({ height = 300 }) => {
  return (
    <div className="ts-skeleton-chart" style={{ height: `${height}px` }}>
      <div className="ts-skeleton-chart__bars">
        {[...Array(7)].map((_, i) => (
          <div
            key={i}
            className="ts-skeleton-chart__bar"
            style={{ height: `${Math.random() * 60 + 20}%` }}
          />
        ))}
      </div>
    </div>
  );
};

/**
 * Skeleton for Table Row
 */
export const SkeletonTable: React.FC<{ rows?: number; columns?: number }> = ({
  rows = 5,
  columns = 4,
}) => {
  return (
    <div className="ts-skeleton-table">
      {[...Array(rows)].map((_, i) => (
        <div key={i} className="ts-skeleton-table__row">
          {[...Array(columns)].map((_, j) => (
            <Skeleton key={j} height="16px" />
          ))}
        </div>
      ))}
    </div>
  );
};
