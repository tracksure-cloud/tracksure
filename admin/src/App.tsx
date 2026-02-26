/**
 * TrackSure Admin App Component
 * 
 * Main app shell with routing and providers.
 * Implements code splitting for optimal performance.
 */

import React, { lazy, Suspense } from 'react';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AppProviders } from './providers/AppProviders';
import { AppShell } from './components/layout/AppShell';
import { ErrorBoundary } from './components/ui/ErrorBoundary';
import { useExtensionRegistry } from './contexts/ExtensionRegistryContext';

// Lazy load all pages for code splitting (80% bundle reduction)
const OverviewPage = lazy(() => import('./pages/OverviewPage'));
const RealtimePage = lazy(() => import('./pages/RealtimePage'));
const JourneysPage = lazy(() => import('./pages/JourneysPage'));
const SessionsPage = lazy(() => import('./pages/SessionsPage'));
const TrafficSourcesPage = lazy(() => import('./pages/TrafficSourcesPage'));
const ContentAnalytics = lazy(() => import('./pages/ContentAnalytics'));
const ProductsPage = lazy(() => import('./pages/ProductsPage'));
const DataQualityPage = lazy(() => import('./pages/DataQualityPage'));
const AttributionPage = lazy(() => import('./pages/AttributionPage')); // NEW: Attribution analytics
const ConversionsPage = lazy(() => import('./pages/ConversionsPage')); // NEW: Conversions analytics
const GoalsPage = lazy(() => import('./pages/GoalsPage'));
const SettingsPage = lazy(() => import('./pages/SettingsPage'));
const DestinationsPage = lazy(() => import('./pages/DestinationsPage'));
const IntegrationsPage = lazy(() => import('./pages/IntegrationsPage'));
const DiagnosticsPage = lazy(() => import('./pages/DiagnosticsPage'));
const NotFoundPage = lazy(() => import('./pages/NotFoundPage'));

// Loading fallback component
const PageLoader: React.FC = () => (
  <div className="ts-page-loader" style={{
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: '400px',
    fontSize: '14px',
    color: 'var(--ts-text-muted)'
  }}>
    <div className="ts-spinner" />
  </div>
);

interface AppProps {
  config: {
    apiUrl: string;
    nonce: string;
    siteUrl: string;
    timezone: string;
    dateFormat: string;
  };
}

const AppRoutes: React.FC = () => {
  const { routes } = useExtensionRegistry();

  // Dynamic component resolution for extensions
  // Extensions register components via window.trackSureExtensionComponents, etc.
  const getExtensionComponent = (componentName: string) => {
    // Check extension components (registered by add-on plugins)
    const extensionComponents = window.trackSureExtensionComponents || {};
    if (extensionComponents[componentName]) {
      return extensionComponents[componentName];
    }

    // Check free module components
    const freeComponents = window.trackSureFreeComponents || {};
    if (freeComponents[componentName]) {
      return freeComponents[componentName];
    }

    // Check 3rd party components
    const thirdPartyComponents = window.trackSureComponents || {};
    if (thirdPartyComponents[componentName]) {
      return thirdPartyComponents[componentName];
    }

    console.warn(`[TrackSure] Component not found: ${componentName}`);
    return null;
  };

  return (
    <Suspense fallback={<PageLoader />}>
      <Routes>
        <Route path="/" element={<Navigate to="/overview" replace />} />
        <Route path="/overview" element={<OverviewPage />} />
        <Route path="/realtime" element={<RealtimePage />} />
        <Route path="/journeys" element={<JourneysPage />} />
        <Route path="/sessions" element={<SessionsPage />} />
        <Route path="/traffic-sources" element={<TrafficSourcesPage />} />
        <Route path="/pages" element={<ContentAnalytics />} />
        <Route path="/products" element={<ProductsPage />} />
        <Route path="/data-quality" element={<DataQualityPage />} />
        <Route path="/attribution" element={<AttributionPage />} />
        <Route path="/conversions" element={<ConversionsPage />} />
        <Route path="/goals" element={<GoalsPage />} />
        <Route path="/settings" element={<SettingsPage />} />
        <Route path="/destinations" element={<DestinationsPage />} />
        <Route path="/integrations" element={<IntegrationsPage />} />
        <Route path="/diagnostics" element={<DiagnosticsPage />} />
        
        {/* Extension routes - dynamically resolved */}
        {routes.map((route) => {
          // If route already has element (from direct registration), use it
          if (route.element) {
            return <Route key={route.path} path={route.path} element={route.element} />;
          }

          // Otherwise, resolve component by name
          if (route.component) {
            const Component = getExtensionComponent(route.component);
            if (Component) {
              return (
                <Route 
                  key={route.path} 
                  path={route.path} 
                  element={<Component />} 
                />
              );
            }
          }

          return null;
        })}
        
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </Suspense>
  );
};

const App: React.FC<AppProps> = ({ config }) => {
  // Transform config to include rest_url
  const appConfig = {
    ...config,
    rest_url: config.apiUrl, // apiUrl is the REST API base URL
  };

  return (
    <ErrorBoundary>
      <HashRouter>
        <AppProviders config={appConfig}>
          <AppShell>
            <AppRoutes />
          </AppShell>
        </AppProviders>
      </HashRouter>
    </ErrorBoundary>
  );
};

export default App;
