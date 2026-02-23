/**
 * Custom Hook - GA4 Setup Guide
 * 
 * Fetches GA4 setup guide data from REST API and provides dismiss functionality.
 */

import { useState, useEffect, useCallback } from 'react';
import { useApp } from '../contexts/AppContext';

export interface GA4SetupGuideData {
  show_guide: boolean;
  dismissed: boolean;
  configured: boolean;
  measurement_id?: string;
  ga4_admin_url?: string;
  title?: string;
  intro?: {
    good_news: string;
    important: string;
  };
  steps?: Array<{
    id: number;
    title: string;
    time: string;
    critical: boolean;
    description: string;
    why: string;
    events?: string[];
    dimensions?: Array<{
      name: string;
      scope: string;
      parameter: string;
      why: string;
    }>;
    audiences?: Array<{
      name: string;
      include?: string;
      exclude?: string;
      condition?: string;
    }>;
    integrations?: Array<{
      name: string;
      benefits: string[];
    }>;
  }>;
  tracksure_handles?: string[];
  summary?: string;
  note?: string;
  reason?: string;
}

export interface UseGA4SetupGuideReturn {
  guideData: GA4SetupGuideData | null;
  isLoading: boolean;
  error: Error | null;
  showGuide: boolean;
  dismissGuide: () => Promise<void>;
  refetch: () => Promise<void>;
}

export const useGA4SetupGuide = (): UseGA4SetupGuideReturn => {
  const { config } = useApp();
  const [guideData, setGuideData] = useState<GA4SetupGuideData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  const fetchGuideData = useCallback(async () => {
    // Guard: Wait for config to be loaded
    if (!config?.apiUrl || !config?.nonce) {
      setIsLoading(false);
      return;
    }

    try {
      setIsLoading(true);
      setError(null);

      const response = await fetch(`${config.apiUrl}/ga4-setup-guide`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data: GA4SetupGuideData = await response.json();
      setGuideData(data);
    } catch (err) {
      console.error('[TrackSure] Failed to fetch GA4 setup guide:', err);
      setError(err instanceof Error ? err : new Error('Unknown error'));
      setGuideData(null);
    } finally {
      setIsLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- Deps use config subproperties; full config object would cause unnecessary re-renders
  }, [config?.apiUrl, config?.nonce]);

  const dismissGuide = useCallback(async () => {
    // Guard: Ensure config is loaded
    if (!config?.apiUrl || !config?.nonce) {
      console.error('[TrackSure] Cannot dismiss guide: config not available');
      return;
    }

    try {
      const response = await fetch(`${config.apiUrl}/ga4-setup-guide/dismiss`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      
      if (result.success) {
        // Update local state to hide guide
        setGuideData((prev) => prev ? { ...prev, show_guide: false, dismissed: true } : null);
      }
    } catch (err) {
      console.error('[TrackSure] Failed to dismiss GA4 setup guide:', err);
      throw err;
    }
  }, [config?.apiUrl, config?.nonce]);

  useEffect(() => {
    // Only fetch when config is ready
    if (config?.apiUrl && config?.nonce) {
      fetchGuideData();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- Deps use config subproperties; full config object would cause unnecessary re-fetches
  }, [config?.apiUrl, config?.nonce, fetchGuideData]);

  return {
    guideData,
    isLoading,
    error,
    showGuide: guideData?.show_guide ?? false,
    dismissGuide,
    refetch: fetchGuideData,
  };
};
