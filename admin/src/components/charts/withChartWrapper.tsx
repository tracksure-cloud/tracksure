/**
 * TrackSure Chart Wrapper HOC
 * 
 * Higher-Order Component that wraps chart components with consistent loading and empty states.
 * Eliminates duplicate code across LineChart, BarChart, and DonutChart components.
 * 
 * @example
 * ```tsx
 * export const LineChart = withChartWrapper(LineChartBase);
 * ```
 */

import React from 'react';
import { __ } from '../../utils/i18n';
import { Skeleton } from '../ui/Skeleton';
import { EmptyState } from '../ui/EmptyState';

export interface ChartProps {
  loading?: boolean;
  empty?: boolean;
  emptyMessage?: string;
  data?: unknown[];
  height?: number;
}

/**
 * HOC that adds loading and empty state handling to chart components.
 * 
 * @param Component - The chart component to wrap
 * @returns Wrapped component with loading/empty state handling
 */
export function withChartWrapper<P extends ChartProps>(
  Component: React.ComponentType<P>
): React.FC<P> {
  const WrappedComponent: React.FC<P> = (props: P) => {
    const { loading, empty, emptyMessage, data, height = 300 } = props;

    // Show skeleton loader
    if (loading) {
      return (
        <div style={{ height }}>
          <Skeleton height={height} borderRadius="8px" />
        </div>
      );
    }

    // Show empty state
    if (empty || !data || data.length === 0) {
      return (
        <div style={{ height, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <EmptyState
            icon="BarChart2"
            title={__('No data available')}
            message={emptyMessage || __('Try adjusting your date range or filters')}
          />
        </div>
      );
    }

    // Render the actual chart component
    return <Component {...props} />;
  };
  WrappedComponent.displayName = `withChartWrapper(${Component.displayName || Component.name || 'Component'})`;
  return WrappedComponent;
}

/**
 * Example usage pattern:
 * 
 * 1. Separate chart logic from loading/empty handling:
 * 
 * ```tsx
 * const LineChartBase: React.FC<LineChartProps> = ({ data, lines, height }) => {
 *   return (
 *     <ResponsiveContainer width="100%" height={height}>
 *       <RechartsLineChart data={data}>
 *         {lines.map(line => <Line key={line.dataKey} {...line} />)}
 *       </RechartsLineChart>
 *     </ResponsiveContainer>
 *   );
 * };
 * 
 * export const LineChart = withChartWrapper(LineChartBase);
 * ```
 * 
 * 2. Component usage remains the same:
 * 
 * ```tsx
 * <LineChart
 *   data={timeseriesData}
 *   lines={[{ dataKey: 'sessions', name: 'Sessions', color: '#3B82F6' }]}
 *   loading={loading}
 *   empty={!data}
 *   height={400}
 * />
 * ```
 * 
 * Benefits:
 * - DRY principle: Eliminates 144 duplicate lines across 3 chart files
 * - Consistency: All charts have identical loading/empty UX
 * - Maintainability: Update loading/empty logic in one place
 * - Testability: Easier to test chart logic separate from UI states
 * - Extensibility: Pro/Free plugins can wrap with additional features
 */
