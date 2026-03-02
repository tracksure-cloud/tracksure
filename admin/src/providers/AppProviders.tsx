/**
 * App Providers - Combines all context providers
 * 
 * Provider hierarchy:
 * - ThemeProvider: Light/dark mode
 * - ExtensionRegistryProvider: Pro/3rd-party page routes & nav items
 * - SettingsExtensionProvider: Pro/3rd-party settings, destinations, integrations
 * - AppProvider: Global app state (config, dateRange, filters, viewMode, segment)
 */

import React, { ReactNode } from 'react';
import { ThemeProvider } from '../contexts/ThemeContext';
import { ExtensionRegistryProvider } from '../contexts/ExtensionRegistryContext';
import { SettingsExtensionProvider } from '../contexts/SettingsExtensionContext';
import { AppProvider } from '../contexts/AppContext';
import type { TrackSureConfig } from '../types';

interface AppProvidersProps {
  config: TrackSureConfig;
  children: ReactNode;
}

export const AppProviders: React.FC<AppProvidersProps> = ({ config, children }) => {
  return (
    <ThemeProvider>
      <ExtensionRegistryProvider>
        <SettingsExtensionProvider>
          <AppProvider config={config}>
            {children}
          </AppProvider>
        </SettingsExtensionProvider>
      </ExtensionRegistryProvider>
    </ThemeProvider>
  );
};
