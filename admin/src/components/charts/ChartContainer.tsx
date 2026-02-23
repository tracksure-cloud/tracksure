/**
 * ChartContainer Component
 * 
 * Wrapper for charts that provides progressive loading for better perceived performance
 */

import React, { useEffect, useState, ReactNode } from 'react';
import { SkeletonChart } from '../ui/Skeleton';

interface ChartContainerProps {
  title?: string;
  children: ReactNode;
  isLoading?: boolean;
  height?: number;
  description?: string;
}

export const ChartContainer: React.FC<ChartContainerProps> = ({
  title,
  children,
  isLoading = false,
  height = 300,
  description
}) => {
  const [showChart, setShowChart] = useState(false);

  useEffect(() => {
    if (!isLoading) {
      // Defer chart rendering to next paint cycle for better perceived performance
      requestAnimationFrame(() => {
        setTimeout(() => setShowChart(true), 100);
      });
    } else {
      setShowChart(false);
    }
  }, [isLoading]);

  return (
    <div className="ts-chart-card">
      {title && (
        <div className="ts-chart-header">
          <h3 className="ts-chart-title">{title}</h3>
          {description && <p className="ts-chart-description">{description}</p>}
        </div>
      )}
      <div className="ts-chart-body">
        {showChart && !isLoading ? (
          <div className="ts-chart-content ts-fade-in">{children}</div>
        ) : (
          <SkeletonChart height={height} />
        )}
      </div>
    </div>
  );
};
