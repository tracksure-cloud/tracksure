/**
 * TrackSure DonutChart Component
 * 
 * Reusable donut/pie chart wrapper around Recharts for percentage breakdowns.
 * Uses withChartWrapper HOC for consistent loading/empty state handling.
 */

import React from 'react';
import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';
import { withChartWrapper } from './withChartWrapper';
import '../../styles/components/charts/DonutChart.css';

export interface DonutChartDataPoint {
  name: string;
  value: number;
  color?: string;
}

export interface DonutChartProps {
  data: DonutChartDataPoint[];
  height?: number;
  loading?: boolean;
  empty?: boolean;
  emptyMessage?: string;
  innerRadius?: number;
  outerRadius?: number;
  showPercentage?: boolean;
  formatTooltip?: (value: number, name: string) => string;
}

// Direct hex values for Recharts compatibility (CSS variables don't work in JS context)
const DEFAULT_COLORS = [
  '#4F46E5', // Primary (Indigo)
  '#10B981', // Success (Green)
  '#F59E0B', // Warning (Amber)
  '#EF4444', // Danger (Red)
  '#8B5CF6', // Purple
  '#EC4899', // Pink
  '#14B8A6', // Teal
  '#F97316', // Orange
];

const DonutChartBase: React.FC<DonutChartProps> = ({
  data,
  height = 300,
  innerRadius = 60,
  outerRadius = 100,
  showPercentage = true,
  formatTooltip,
}) => {
  // Assign colors if not provided
  const dataWithColors = data.map((item, index) => ({
    ...item,
    color: item.color || DEFAULT_COLORS[index % DEFAULT_COLORS.length],
  }));

  // Calculate total for percentages
  const total = dataWithColors.reduce((sum, item) => sum + item.value, 0);

  // Custom label renderer
  const renderLabel = (entry: { value: number; name?: string }) => {
    if (!showPercentage) {return '';}
    const percent = ((entry.value / total) * 100).toFixed(1);
    return `${percent}%`;
  };

  return (
    <div className="ts-donut-chart" style={{ height }}>
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={dataWithColors}
            cx="50%"
            cy="50%"
            innerRadius={innerRadius}
            outerRadius={outerRadius}
            fill="#8884d8"
            paddingAngle={2}
            dataKey="value"
            label={renderLabel}
            labelLine={false}
          >
            {dataWithColors.map((entry, index) => (
              <Cell key={`cell-${index}`} fill={entry.color} />
            ))}
          </Pie>
          <Tooltip
            contentStyle={{
              backgroundColor: 'var(--ts-surface)',
              border: '1px solid var(--ts-border)',
              borderRadius: '8px',
              color: 'var(--ts-text)',
            }}
            formatter={formatTooltip || ((value: number, name: string) => {
              const percent = ((value / total) * 100).toFixed(1);
              return [`${value.toLocaleString()} (${percent}%)`, name];
            })}
          />
          <Legend
            verticalAlign="bottom"
            height={36}
            iconType="circle"
          />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
};

// Export wrapped version with loading/empty state handling
export const DonutChart = withChartWrapper(DonutChartBase);
