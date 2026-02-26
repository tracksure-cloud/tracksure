/**
 * Settings Extension Context
 * 
 * Manages extensible settings, destinations, and integrations.
 * Free/Pro/3rd party can register via window.trackSureExtensions
 */

import React, { createContext, useContext, useState, useCallback, useEffect, ReactNode } from 'react';
import type {
  TrackSureExtension,
  SettingsSection,
  DestinationConfig,
  IntegrationConfig,
  DashboardWidget as _DashboardWidget,
  CustomPage as _CustomPage,
  ExtensionRegistryState,
} from '../types/extensions';

interface SettingsExtensionContextValue extends ExtensionRegistryState {
  registerExtension: (extension: TrackSureExtension) => void;
  getSettingsByCategory: (category: string) => SettingsSection[];
  getEnabledDestinations: () => DestinationConfig[];
  getEnabledIntegrations: () => IntegrationConfig[];
}

const SettingsExtensionContext = createContext<SettingsExtensionContextValue | undefined>(undefined);

export const useSettingsExtension = () => {
  const context = useContext(SettingsExtensionContext);
  if (!context) {
    throw new Error('useSettingsExtension must be used within SettingsExtensionProvider');
  }
  return context;
};

interface SettingsExtensionProviderProps {
  children: ReactNode;
}

export const SettingsExtensionProvider: React.FC<SettingsExtensionProviderProps> = ({ children }) => {
  const [state, setState] = useState<ExtensionRegistryState>({
    extensions: [],
    settingsSections: [],
    destinations: [],
    integrations: [],
    widgets: {},
    pages: [],
  });

  const registerExtension = useCallback((extension: TrackSureExtension) => {
    setState((prev) => {
      // Prevent duplicate registration
      if (prev.extensions.some((ext) => ext.id === extension.id)) {
        console.warn(`[TrackSure] Extension ${extension.id} already registered`);
        return prev;
      }

      const newState = { ...prev };

      // Register settings sections
      if (extension.settings) {
        newState.settingsSections = [
          ...prev.settingsSections,
          ...extension.settings,
        ].sort((a, b) => (a.order ?? 100) - (b.order ?? 100));
      }

      // Register destinations
      if (extension.destinations) {
        newState.destinations = [
          ...prev.destinations,
          ...extension.destinations,
        ].sort((a, b) => (a.order ?? 100) - (b.order ?? 100));
      }

      // Register integrations
      if (extension.integrations) {
        newState.integrations = [
          ...prev.integrations,
          ...extension.integrations,
        ].sort((a, b) => (a.order ?? 100) - (b.order ?? 100));
      }

      // Register widgets
      if (extension.widgets) {
        newState.widgets = { ...prev.widgets };
        extension.widgets.forEach((widget) => {
          if (!newState.widgets[widget.slot]) {
            newState.widgets[widget.slot] = [];
          }
          newState.widgets[widget.slot] = [
            ...newState.widgets[widget.slot],
            widget,
          ].sort((a, b) => (a.order ?? 100) - (b.order ?? 100));
        });
      }

      // Register pages
      if (extension.pages) {
        newState.pages = [
          ...prev.pages,
          ...extension.pages,
        ].sort((a, b) => (a.order ?? 100) - (b.order ?? 100));
      }

      newState.extensions = [...prev.extensions, extension];
      return newState;
    });
  }, []);

  const getSettingsByCategory = useCallback(
    (category: string) => {
      return state.settingsSections.filter((section) => section.category === category);
    },
    [state.settingsSections]
  );

  const getEnabledDestinations = useCallback(() => {
    return state.destinations.filter((dest) => dest.enabled);
  }, [state.destinations]);

  const getEnabledIntegrations = useCallback(() => {
    return state.integrations.filter((int) => int.enabled);
  }, [state.integrations]);

  // Auto-register extensions from window.trackSureExtensions
  useEffect(() => {
    const globalExtensions = window.trackSureExtensions;
    
    if (globalExtensions && Array.isArray(globalExtensions)) {
      globalExtensions.forEach((ext) => {
        registerExtension(ext as unknown as TrackSureExtension);
      });
    }
  }, [registerExtension]);

  return (
    <SettingsExtensionContext.Provider
      value={{
        ...state,
        registerExtension,
        getSettingsByCategory,
        getEnabledDestinations,
        getEnabledIntegrations,
      }}
    >
      {children}
    </SettingsExtensionContext.Provider>
  );
};
