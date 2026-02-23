/**
 * TrackSure API Custom Hooks
 * 
 * Custom hooks for fetching data from TrackSure REST API.
 */

import { useState, useEffect, useCallback } from 'react';
import { useApp } from '../contexts/AppContext';
import { __ } from '../utils/i18n';

interface UseApiOptions {
  immediate?: boolean;
  refetchInterval?: number;
}

interface ApiState<T> {
  data: T | null;
  loading: boolean;
  error: Error | null;
}

function useApi<T>(
  endpoint: string,
  options: UseApiOptions = {}
): ApiState<T> & { refetch: () => Promise<void> } {
  const { config } = useApp();
  const { immediate = true, refetchInterval } = options;

  const [state, setState] = useState<ApiState<T>>({
    data: null,
    loading: immediate,
    error: null,
  });

  const fetchData = useCallback(async () => {
    setState((prev) => ({ ...prev, loading: true, error: null }));

    try {
      const response = await fetch(`${config.apiUrl}${endpoint}`, {
        headers: {
          'X-WP-Nonce': config.nonce,
        },
      });

      if (!response.ok) {
        let errorMessage = __('API request failed');
        
        if (response.status === 401) {
          errorMessage = __('Authentication failed. Please refresh the page.');
        } else if (response.status === 403) {
          errorMessage = __('You do not have permission to access this data.');
        } else if (response.status === 404) {
          errorMessage = __('The requested data was not found.');
        } else if (response.status >= 500) {
          errorMessage = __('Server error. Please try again later.');
        }

        throw new Error(`${errorMessage} (${response.status})`);
      }

      const data = await response.json();
      setState({ data, loading: false, error: null });
    } catch (error) {
      console.error('[TrackSure] API Error:', endpoint, error);
      
      const errorMessage = error instanceof Error 
        ? error.message 
        : __('An unexpected error occurred while fetching data.');
      
      setState({ 
        data: null, 
        loading: false, 
        error: new Error(errorMessage)
      });
    }
  }, [config.apiUrl, config.nonce, endpoint]);

  useEffect(() => {
    if (immediate) {
      fetchData();
    }
  }, [immediate, fetchData]);

  useEffect(() => {
    if (refetchInterval) {
      const interval = setInterval(fetchData, refetchInterval);
      return () => clearInterval(interval);
    }
  }, [refetchInterval, fetchData]);

  return {
    ...state,
    refetch: fetchData,
  };
}

/**
 * Fetch overview dashboard data
 */
export function useOverviewData(startDate: string, endDate: string) {
  const endpoint = `/query/overview?start=${startDate}&end=${endDate}`;
  return useApi<{
    metrics: {
      sessions: number;
      users: number;
      pageviews: number;
      bounceRate: number;
      avgSessionDuration: number;
      conversions: number;
      revenue: number;
    };
    timeseries: Array<{
      date: string;
      sessions: number;
      users: number;
      pageviews: number;
      revenue: number;
    }>;
    topPages: Array<{
      path: string;
      pageviews: number;
      users: number;
      bounceRate: number;
    }>;
    topSources: Array<{
      source: string;
      medium: string;
      sessions: number;
      users: number;
      conversions: number;
    }>;
    devices: Array<{
      device: string;
      sessions: number;
      percentage: number;
    }>;
  }>(endpoint);
}

/**
 * Fetch realtime dashboard data
 */
export function useRealtimeData() {
  const endpoint = `/query/realtime`;
  return useApi<{
    activeUsers: number;
    activePages: Array<{
      path: string;
      title: string;
      activeUsers: number;
    }>;
    activeSources: Array<{
      source: string;
      activeUsers: number;
    }>;
    recentEvents: Array<{
      eventId: string;
      eventName: string;
      pagePath: string;
      pageTitle: string;
      occurredAt: string;
      sessionId: string;
    }>;
  }>(endpoint, { refetchInterval: 5000 }); // Refresh every 5 seconds
}

/**
 * Fetch session list
 */
export function useSessionsData(startDate: string, endDate: string, page: number = 1) {
  const endpoint = `/query/sessions?start=${startDate}&end=${endDate}&page=${page}`;
  return useApi<{
    sessions: Array<{
      sessionId: string;
      visitorId: string;
      startedAt: string;
      lastSeenAt: string;
      pageviews: number;
      events: number;
      source: string;
      medium: string;
      device: string;
      country: string;
    }>;
    total: number;
    page: number;
    perPage: number;
  }>(endpoint);
}

/**
 * Fetch single session journey
 */
export function useJourneyData(sessionId: string) {
  const endpoint = `/query/journey/${sessionId}`;
  return useApi<{
    session: {
      sessionId: string;
      visitorId: string;
      startedAt: string;
      lastSeenAt: string;
      source: string;
      medium: string;
      campaign: string;
      device: string;
      browser: string;
      os: string;
      country: string;
      city: string;
      region?: string;
      sessionNumber?: number;
      firstSource?: string;
      firstMedium?: string;
      firstCampaign?: string;
    };
    events: Array<{
      event_id: string;
      event_name: string;
      occurred_at: string;  // UTC timestamp string
      page_url: string;
      page_title: string;
      event_params: Record<string, unknown>;
      is_conversion: boolean;
      conversion_value?: number;
      time_delta?: string;
    }>;
    touchpoints: Array<{
      touchpoint_id: string;
      touchpoint_seq: number;
      touched_at: string;
      utm_source: string;
      utm_medium: string;
      utm_campaign: string;
      utm_term: string;
      utm_content: string;
      channel: string;
      referrer: string;
      page_url: string;
      page_title: string;
      is_conversion: boolean;
      attribution_weight: number;
    }>;
    attribution: {
      first_touch: {
        source: string;
        medium: string;
        campaign: string | null;
        referrer: string | null;
        landing_page: string | null;
      };
      last_touch: {
        source: string;
        medium: string;
        campaign: string | null;
        page_url: string | null;
      };
    };
  }>(endpoint, { immediate: !!sessionId });
}

/**
 * Fetch funnel data
 */
export function useFunnelData(startDate: string, endDate: string) {
  const endpoint = `/query/funnel?start=${startDate}&end=${endDate}`;
  return useApi<{
    steps: Array<{
      step: string;
      eventName: string;
      sessions: number;
      dropoffRate: number;
    }>;
  }>(endpoint);
}

/**
 * Fetch registry (events/destinations/models)
 */
export function useRegistryData() {
  const endpoint = `/query/registry`;
  return useApi<{
    version: number;
    events: Array<{
      name: string;
      label: string;
      category: string;
      requiredParams: string[];
    }>;
    destinations: Array<{
      id: string;
      name: string;
      enabled: boolean;
    }>;
    models: string[];
  }>(endpoint);
}
