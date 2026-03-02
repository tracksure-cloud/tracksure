/**
 * useApiQuery Hook
 * 
 * Centralized data fetching hook with:
 * - Automatic request cancellation (AbortController)
 * - Error handling
 * - Loading states
 * - Type safety
 * 
 * NOTE: Retry logic is handled by TrackSureAPI.request() (MAX_RETRIES=2).
 * Do NOT add retry here — it previously caused double-retry explosion
 * (up to 12 total attempts per failed request).
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { TrackSureAPI } from '../utils/api';
import { useApp } from '../contexts/AppContext';
import type { QueryParams } from '../utils/api';

interface UseApiQueryOptions {
  enabled?: boolean;
  refetchInterval?: number;
  staleTime?: number;
  retry?: number;
  onSuccess?: (data: unknown) => void;
  onError?: (error: Error) => void;
}

interface UseApiQueryResult<T> {
  data: T | null;
  error: Error | null;
  isLoading: boolean;
  isFetching: boolean;
  refetch: () => Promise<void>;
}

/**
 * Custom hook for API queries with automatic cleanup
 * 
 * @example
 * const { data, error, isLoading, refetch } = useApiQuery(
 *   'getSessions',
 *   { date_start: '2025-01-01', date_end: '2025-01-31' }
 * );
 */
export function useApiQuery<T = unknown>(
  endpoint: keyof TrackSureAPI,
  params?: QueryParams | string,
  options: UseApiQueryOptions = {}
): UseApiQueryResult<T> {
  const { config, setLoading: setGlobalLoading } = useApp();
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isFetching, setIsFetching] = useState(false);
  const abortControllerRef = useRef<AbortController | null>(null);
  const apiRef = useRef<TrackSureAPI | null>(null);
  const lastFetchTimeRef = useRef<number>(0);

  const {
    enabled = true,
    refetchInterval,
    staleTime = 0,
    // retry option is kept for interface compatibility but retries are
    // handled internally by TrackSureAPI.request() (MAX_RETRIES=2).
    retry: _retry,
    onSuccess,
    onError,
  } = options;

  // Create API instance once and reuse
  if (!apiRef.current) {
    apiRef.current = new TrackSureAPI(config);
  }
  const api = apiRef.current;

  // Serialize params to avoid object reference changes
  const paramsKey = JSON.stringify(params);

  const fetchData = useCallback(async (isRefetch = false) => {
    // Don't fetch if disabled
    if (!enabled) {return;}

    // Check if data is still fresh (stale-while-revalidate pattern)
    const now = Date.now();
    const timeSinceLastFetch = now - lastFetchTimeRef.current;
    const isDataStale = timeSinceLastFetch > staleTime;

    // If refetching and data is still fresh, don't show loading states
    const shouldShowLoading = !isRefetch || isDataStale;

    // Cancel any pending request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Create new abort controller
    abortControllerRef.current = new AbortController();
    const signal = abortControllerRef.current.signal;

    try {
      if (shouldShowLoading && !isRefetch) {
        setIsLoading(true);
        setGlobalLoading(true);
      }
      setIsFetching(true);
      setError(null);

      // Call the API method
      const method = api[endpoint] as (...args: unknown[]) => Promise<unknown>;
      const result = await method.call(api, params);

      // Check if request was aborted
      if (signal.aborted) {return;}

      setData(result);
      lastFetchTimeRef.current = Date.now();

      if (onSuccess) {
        onSuccess(result);
      }
    } catch (err: unknown) {
      // Ignore aborted requests
      const errObj = err instanceof Error ? err : new Error(String(err));
      if (errObj.name === 'AbortError' || signal.aborted) {return;}

      // NOTE: Retry logic is handled by TrackSureAPI.request() (MAX_RETRIES=2)
      // with exponential backoff. Do NOT add retry here to avoid double-retry
      // explosion (previously caused up to 12 total attempts).
      const error = new Error(errObj.message || 'Failed to fetch data');
      setError(error);

      if (onError) {
        onError(error);
      }
    } finally {
      if (!signal.aborted) {
        setIsLoading(false);
        setIsFetching(false);
        setGlobalLoading(false);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- Intentionally excludes callback refs and serialized params to prevent infinite re-fetching
  }, [enabled, endpoint, paramsKey, staleTime]);

  // Fetch on mount and when dependencies change
  useEffect(() => {
    fetchData();

    // Cleanup: abort request on unmount or dependency change
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [fetchData]);

  // Auto-refetch interval
  useEffect(() => {
    if (!refetchInterval || !enabled) {return;}

    const interval = setInterval(() => {
      fetchData(true);
    }, refetchInterval);

    return () => clearInterval(interval);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- Interval should only restart when these specific props change
  }, [refetchInterval, enabled]);

  const refetch = useCallback(async () => {
    await fetchData(true);
  }, [fetchData]);

  return {
    data,
    error,
    isLoading,
    isFetching,
    refetch,
  };
}
