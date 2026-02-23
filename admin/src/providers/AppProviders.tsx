/**
 * App Providers - Combines all context providers
 * 
 * Optimized hierarchy using split contexts for better performance:
 * - ConfigContext: Rarely changes (config, viewMode)
 * - UIContext: Changes often (isLoading)
 * - DataContext: Changes moderately (dateRange, filters, segment)
 * - AppContext: Combines all for backward compatibility
 */

import React, { ReactNode, useState } from 'react';
import { ThemeProvider } from '../contexts/ThemeContext';
import { ExtensionRegistryProvider } from '../contexts/ExtensionRegistryContext';
import { SettingsExtensionProvider } from '../contexts/SettingsExtensionContext';
import { ConfigProvider } from '../contexts/ConfigContext';
import { UIProvider } from '../contexts/UIContext';
import { DataProvider } from '../contexts/DataContext';
import { AppProvider } from '../contexts/AppContext';
import type { TrackSureConfig } from '../types';

interface AppProvidersProps {
  config: TrackSureConfig;
  children: ReactNode;
}

export const AppProviders: React.FC<AppProvidersProps> = ({ config, children }) => {
  const [viewMode, setViewMode] = useState<'business' | 'debug'>('business');

  return (
    <ThemeProvider>
      <ExtensionRegistryProvider>
        <SettingsExtensionProvider>
          <ConfigProvider config={config} viewMode={viewMode} setViewMode={setViewMode}>
            <UIProvider>
              <DataProvider>
                <AppProvider config={config}>
                  {children}
                </AppProvider>
              </DataProvider>
            </UIProvider>
          </ConfigProvider>
        </SettingsExtensionProvider>
      </ExtensionRegistryProvider>
    </ThemeProvider>
  );
};
