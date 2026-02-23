/**
 * Extension Registry Context
 * 
 * Allows Free/Pro modules to register routes, nav items, and widgets.
 */

import React, { createContext, useContext, useState, useCallback, useEffect, ReactNode } from 'react';
import type { TracksureExtension, ExtensionRoute, ExtensionNavGroup, ExtensionWidget } from '../types';
import { __ } from '../utils/i18n';

interface ExtensionRegistryContextValue {
  extensions: TracksureExtension[];
  routes: ExtensionRoute[];
  navGroups: ExtensionNavGroup[];
  widgets: Record<string, ExtensionWidget[]>;
  registerExtension: (extension: TracksureExtension) => void;
}

const ExtensionRegistryContext = createContext<ExtensionRegistryContextValue | undefined>(undefined);

export const useExtensionRegistry = () => {
  const context = useContext(ExtensionRegistryContext);
  if (!context) {
    throw new Error('useExtensionRegistry must be used within ExtensionRegistryProvider');
  }
  return context;
};

interface ExtensionRegistryProviderProps {
  children: ReactNode;
}

export const ExtensionRegistryProvider: React.FC<ExtensionRegistryProviderProps> = ({ children }) => {
  const [extensions, setExtensions] = useState<TracksureExtension[]>([]);
  const [routes, setRoutes] = useState<ExtensionRoute[]>([]);
  const [navGroups, setNavGroups] = useState<ExtensionNavGroup[]>([]);
  const [widgets, setWidgets] = useState<Record<string, ExtensionWidget[]>>({});

  const registerExtension = useCallback((extension: TracksureExtension) => {
    // Prevent duplicate registration
    setExtensions((prev) => {
      if (prev.some((ext) => ext.id === extension.id)) {
        console.warn(`Extension ${extension.id} already registered`);
        return prev;
      }
      return [...prev, extension];
    });

    // Register routes
    if (extension.routes) {
      setRoutes((prev) => [...prev, ...extension.routes!]);
    }

    // Register nav groups
    if (extension.navGroups) {
      setNavGroups((prev) => {
        const merged = [...prev];
        extension.navGroups!.forEach((group) => {
          if (!merged.some((g) => g.id === group.id)) {
            merged.push(group);
          }
        });
        return merged.sort((a, b) => a.order - b.order);
      });
    }

    // Register widgets
    if (extension.widgets) {
      setWidgets((prev) => {
        const updated = { ...prev };
        extension.widgets!.forEach((widget) => {
          if (!updated[widget.slot]) {
            updated[widget.slot] = [];
          }
          updated[widget.slot].push(widget);
          updated[widget.slot].sort((a, b) => a.order - b.order);
        });
        return updated;
      });
    }
  }, []);

  // Auto-register extensions from window.trackSureExtensions on mount
  useEffect(() => {
    const globalExtensions = window.trackSureExtensions;
    
    if (!globalExtensions || !Array.isArray(globalExtensions)) {
      console.warn('[TrackSure] No extensions found in window.trackSureExtensions');
      return;
    }

    // Process each extension
    globalExtensions.forEach((ext: Record<string, unknown>) => {
      // Convert pages array to routes
      const extPages = ext.pages as Array<Record<string, unknown>> | undefined;
      if (extPages && Array.isArray(extPages)) {
        const pageRoutes: ExtensionRoute[] = extPages.map((page: Record<string, unknown>) => ({
          path: page.path as string,
          component: page.component as string, // Component name string (e.g., 'ExperiencesPage')
          nav: {
            group: (page.nav_group as string) || 'features',
            label: page.title as string,
            order: (page.order as number) || 100,
            icon: page.icon as string | undefined,
          },
        }));

        setRoutes((prev) => [...prev, ...pageRoutes]);
      }
    });
  }, []);

  return (
    <ExtensionRegistryContext.Provider value={{ extensions, routes, navGroups, widgets, registerExtension }}>
      {children}
    </ExtensionRegistryContext.Provider>
  );
};
