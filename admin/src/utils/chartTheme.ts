/**
 * Chart Theming Utilities
 * 
 * Provides theme-aware colors for charts to ensure they look good in both light and dark modes
 */

export interface ChartColors {
  primary: string;
  success: string;
  warning: string;
  danger: string;
  info: string;
  grid: string;
  text: string;
  background: string;
}

/**
 * Get chart colors based on current theme
 */
export const getChartColors = (): ChartColors => {
  const isDark = document.documentElement.dataset.theme === 'dark';

  return isDark
    ? {
        // Dark theme - lighter colors for better visibility
        primary: '#60a5fa',
        success: '#34d399',
        warning: '#fbbf24',
        danger: '#f87171',
        info: '#38bdf8',
        grid: 'rgba(148, 163, 184, 0.1)',
        text: 'rgba(248, 250, 252, 0.7)',
        background: 'rgba(15, 23, 42, 0.5)',
      }
    : {
        // Light theme - standard colors
        primary: '#3b82f6',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#0ea5e9',
        grid: 'rgba(15, 23, 42, 0.1)',
        text: 'rgba(15, 23, 42, 0.6)',
        background: 'rgba(255, 255, 255, 0.8)',
      };
};

/**
 * Get an array of colors for multi-series charts
 */
export const getChartColorPalette = (count: number = 5): string[] => {
  const colors = getChartColors();
  const palette = [
    colors.primary,
    colors.success,
    colors.warning,
    colors.danger,
    colors.info,
  ];

  // Extend palette if needed
  while (palette.length < count) {
    palette.push(`hsl(${(palette.length * 137.5) % 360}, 70%, 60%)`);
  }

  return palette.slice(0, count);
};
