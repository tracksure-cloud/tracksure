/**
 * TrackSure BarChart Component
 * 
 * Reusable bar chart wrapper around Recharts for categorical data.
 * Uses withChartWrapper HOC for consistent loading/empty state handling.
 */

import React from 'react';
import {
  BarChart as RechartsBarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
  Cell as _Cell,
} from 'recharts';
import { withChartWrapper } from './withChartWrapper';
import '../../styles/components/charts/BarChart.css';

export interface BarChartDataPoint {
  name: string;
  [key: string]: string | number;
}

export interface BarChartBar {
  dataKey: string;
  name: string;
  color: string;
}

export interface BarChartProps {
  data: BarChartDataPoint[];
  bars: BarChartBar[];
  height?: number;
  loading?: boolean;
  empty?: boolean;
  emptyMessage?: string;
  horizontal?: boolean;
  xAxisLabel?: string;
  yAxisLabel?: string;
  formatYAxis?: (value: number) => string;
  formatTooltip?: (value: number, name: string) => string;
}

const BarChartBase: React.FC<BarChartProps> = ({
  data,
  bars,
  height = 400,
  horizontal = false,
  xAxisLabel,
  yAxisLabel,
  formatYAxis,
  formatTooltip,
}) => {
  const layout = horizontal ? 'horizontal' : 'vertical';

  return (
    <div className="ts-bar-chart" style={{ height }}>
      <ResponsiveContainer width="100%" height="100%">
        <RechartsBarChart
          data={data}
          layout={layout}
          margin={{ top: 10, right: 30, left: 0, bottom: 20 }}
        >
          <CartesianGrid strokeDasharray="3 3" stroke="var(--ts-chart-grid)" />
          {horizontal ? (
            <>
              <XAxis
                type="number"
                stroke="var(--ts-text-muted)"
                tick={{ fill: 'var(--ts-text-muted)', fontSize: 12 }}
                tickFormatter={formatYAxis}
                label={xAxisLabel ? { value: xAxisLabel, position: 'insideBottom', offset: -10 } : undefined}
              />
              <YAxis
                type="category"
                dataKey="name"
                stroke="var(--ts-text-muted)"
                tick={{ fill: 'var(--ts-text-muted)', fontSize: 12 }}
                label={yAxisLabel ? { value: yAxisLabel, angle: -90, position: 'insideLeft' } : undefined}
              />
            </>
          ) : (
            <>
              <XAxis
                dataKey="name"
                stroke="var(--ts-text-muted)"
                tick={{ fill: 'var(--ts-text-muted)', fontSize: 12 }}
                label={xAxisLabel ? { value: xAxisLabel, position: 'insideBottom', offset: -10 } : undefined}
              />
              <YAxis
                stroke="var(--ts-text-muted)"
                tick={{ fill: 'var(--ts-text-muted)', fontSize: 12 }}
                tickFormatter={formatYAxis}
                label={yAxisLabel ? { value: yAxisLabel, angle: -90, position: 'insideLeft' } : undefined}
              />
            </>
          )}
          <Tooltip
            contentStyle={{
              backgroundColor: 'var(--ts-surface)',
              border: '1px solid var(--ts-border)',
              borderRadius: '8px',
              color: 'var(--ts-text)',
            }}
            formatter={formatTooltip}
          />
          <Legend
            wrapperStyle={{ paddingTop: '20px' }}
          />
          {bars.map((bar) => (
            <Bar
              key={bar.dataKey}
              dataKey={bar.dataKey}
              name={bar.name}
              fill={bar.color}
              radius={[4, 4, 0, 0]}
            />
          ))}
        </RechartsBarChart>
      </ResponsiveContainer>
    </div>
  );
};

// Export wrapped version with loading/empty state handling
export const BarChart = withChartWrapper(BarChartBase);
