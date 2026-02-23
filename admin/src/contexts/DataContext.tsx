/**
 * Data Context - Frequently changing data state
 * 
 * Separated from AppContext for better performance.
 * Contains date ranges, filters, and segments that change often.
 */

import React, { createContext, useContext, useState, ReactNode } from 'react';
import type { DateRange } from '../types';

interface DataContextValue {
  dateRange: DateRange;
  setDateRange: (range: DateRange) => void;
  filters: Record<string, string | number | boolean>;
  setFilter: (key: string, value: string | number | boolean) => void;
  clearFilters: () => void;
  segment: 'all' | 'new' | 'returning' | 'converted';
  setSegment: (segment: 'all' | 'new' | 'returning' | 'converted') => void;
}

const DataContext = createContext<DataContextValue | undefined>(undefined);

export const useData = () => {
  const context = useContext(DataContext);
  if (!context) {
    throw new Error('useData must be used within DataProvider');
  }
  return context;
};

interface DataProviderProps {
  children: ReactNode;
}

export const DataProvider: React.FC<DataProviderProps> = ({ children }) => {
  const [dateRange, setDateRange] = useState<DateRange>({
    start: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000),
    end: new Date(),
  });

  const [filters, setFilters] = useState<Record<string, string | number | boolean>>({});
  const [segment, setSegment] = useState<'all' | 'new' | 'returning' | 'converted'>('all');

  const setFilter = (key: string, value: string | number | boolean) => {
    setFilters(prev => ({ ...prev, [key]: value }));
  };

  const clearFilters = () => {
    setFilters({});
  };

  return (
    <DataContext.Provider
      value={{
        dateRange,
        setDateRange,
        filters,
        setFilter,
        clearFilters,
        segment,
        setSegment,
      }}
    >
      {children}
    </DataContext.Provider>
  );
};
