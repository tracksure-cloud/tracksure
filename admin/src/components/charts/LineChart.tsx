/**
 * TrackSure LineChart Component
 * 
 * Reusable line chart wrapper around Recharts for time-series data.
 * Uses withChartWrapper HOC for consistent loading/empty state handling.
 */

import React from 'react';
import {
  LineChart as RechartsLineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';
import { withChartWrapper } from './withChartWrapper';
import '../../styles/components/charts/LineChart.css';

export interface LineChartDataPoint {
  date: string;
  [key: string]: string | number;
}

export interface LineChartLine {
  dataKey: string;
  name: string;
  color: string;
  strokeWidth?: number;
}

export interface LineChartProps {
  data: LineChartDataPoint[];
  lines: LineChartLine[];
  height?: number;
  loading?: boolean;
  empty?: boolean;
  emptyMessage?: string;
  xAxisLabel?: string;
  yAxisLabel?: string;
  formatYAxis?: (value: number) => string;
  formatTooltip?: (value: number, name: string) => string;
}

const LineChartBase: React.FC<LineChartProps> = ({
  data,
  lines,
  height = 400,
  xAxisLabel,
  yAxisLabel,
  formatYAxis,
  formatTooltip,
}) => {
  return (
    <div className="ts-line-chart" style={{ height }}>
      <ResponsiveContainer width="100%" height="100%">
        <RechartsLineChart
          data={data}
          margin={{ top: 10, right: 30, left: 0, bottom: 20 }}
        >
          <CartesianGrid strokeDasharray="3 3" stroke="var(--ts-chart-grid)" />
          <XAxis
            dataKey="date"
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
            iconType="line"
          />
          {lines.map((line) => (
            <Line
              key={line.dataKey}
              type="monotone"
              dataKey={line.dataKey}
              name={line.name}
              stroke={line.color}
              strokeWidth={line.strokeWidth || 2}
              dot={false}
              activeDot={{ r: 6 }}
            />
          ))}
        </RechartsLineChart>
      </ResponsiveContainer>
    </div>
  );
};

// Export wrapped version with loading/empty state handling
export const LineChart = withChartWrapper(LineChartBase);
