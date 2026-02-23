/**
 * App Context - Global Application State
 * 
 * Lightweight Context + Hooks state management (no Redux).
 */

import React, { createContext, useContext, useState, useCallback, ReactNode } from 'react';
import type { DateRange, TrackSureConfig } from '../types';
import { subDays, startOfDay, endOfDay } from 'date-fns';
import { __ } from '../utils/i18n';

interface AppContextValue {
  config: TrackSureConfig;
  dateRange: DateRange;
  setDateRange: (range: DateRange) => void;
  filters: Record<string, string | number | boolean>;
  setFilter: (key: string, value: string | number | boolean) => void;
  clearFilters: () => void;
  isLoading: boolean;
  setLoading: (loading: boolean) => void;
  viewMode: 'business' | 'debug';
  setViewMode: (mode: 'business' | 'debug') => void;
  segment: 'all' | 'new' | 'returning' | 'converted';
  setSegment: (segment: 'all' | 'new' | 'returning' | 'converted') => void;
}

const AppContext = createContext<AppContextValue | undefined>(undefined);

export const useApp = () => {
  const context = useContext(AppContext);
  if (!context) {
    throw new Error('useApp must be used within AppProvider');
  }
  return context;
};

interface AppProviderProps {
  config: TrackSureConfig;
  children: ReactNode;
}

export const AppProvider: React.FC<AppProviderProps> = ({ config, children }) => {
  const [dateRange, setDateRangeState] = useState<DateRange>(() => {
    const end = endOfDay(new Date());
    const start = startOfDay(subDays(end, 6)); // Last 7 days (performance optimized)
    return { start, end };
  });

  const [filters, setFiltersState] = useState<Record<string, string | number | boolean>>({});
  const [isLoading, setLoading] = useState(false);
  const [viewMode, setViewMode] = useState<'business' | 'debug'>('business');
  const [segment, setSegment] = useState<'all' | 'new' | 'returning' | 'converted'>('all');

  const setDateRange = useCallback((range: DateRange) => {
    setDateRangeState(range);
  }, []);

  const setFilter = useCallback((key: string, value: string | number | boolean) => {
    setFiltersState((prev) => ({ ...prev, [key]: value }));
  }, []);

  const clearFilters = useCallback(() => {
    setFiltersState({});
  }, []);

  return (
    <AppContext.Provider
      value={{
        config,
        dateRange,
        setDateRange,
        filters,
        setFilter,
        clearFilters,
        isLoading,
        setLoading,
        viewMode,
        setViewMode,
        segment,
        setSegment,
      }}
    >
      {children}
    </AppContext.Provider>
  );
};
