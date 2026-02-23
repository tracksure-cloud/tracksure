/**
 * Config Context - Rarely changing configuration
 * 
 * Separated from AppContext to prevent unnecessary re-renders.
 * This context only updates when core config changes (almost never).
 */

import React, { createContext, useContext, ReactNode } from 'react';
import type { TrackSureConfig } from '../types';

interface ConfigContextValue {
  config: TrackSureConfig;
  viewMode: 'business' | 'debug';
  setViewMode: (mode: 'business' | 'debug') => void;
}

const ConfigContext = createContext<ConfigContextValue | undefined>(undefined);

export const useConfig = () => {
  const context = useContext(ConfigContext);
  if (!context) {
    throw new Error('useConfig must be used within ConfigProvider');
  }
  return context;
};

interface ConfigProviderProps {
  config: TrackSureConfig;
  viewMode: 'business' | 'debug';
  setViewMode: (mode: 'business' | 'debug') => void;
  children: ReactNode;
}

export const ConfigProvider: React.FC<ConfigProviderProps> = ({
  config,
  viewMode,
  setViewMode,
  children,
}) => {
  return (
    <ConfigContext.Provider value={{ config, viewMode, setViewMode }}>
      {children}
    </ConfigContext.Provider>
  );
};
