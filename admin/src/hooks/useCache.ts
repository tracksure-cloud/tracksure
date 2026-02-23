/**
 * useCache Hook
 * 
 * Provides client-side caching for API requests to improve perceived performance
 * and reduce unnecessary network calls.
 */

import { useEffect, useRef, useState } from 'react';

interface CacheOptions {
  ttl?: number; // Time to live in milliseconds (default: 60000 = 1 minute)
}

interface CacheEntry<T> {
  data: T;
  timestamp: number;
}

export function useCache<T>(
  key: string,
  fetcher: () => Promise<T>,
  options: CacheOptions = {}
) {
  const { ttl = 60000 } = options;
  const cache = useRef<Map<string, CacheEntry<T>>>(new Map());
  const [data, setData] = useState<T | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        // Check cache first
        const cached = cache.current.get(key);
        if (cached && Date.now() - cached.timestamp < ttl) {
          setData(cached.data);
          setIsLoading(false);
          return;
        }

        // Fetch fresh data
        setIsLoading(true);
        const result = await fetcher();
        
        // Store in cache
        cache.current.set(key, {
          data: result,
          timestamp: Date.now()
        });
        
        setData(result);
        setError(null);
      } catch (err) {
        setError(err instanceof Error ? err : new Error('An error occurred'));
      } finally {
        setIsLoading(false);
      }
    };

    fetchData();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- Intentionally excludes fetcher to prevent re-fetch on function reference changes
  }, [key, ttl]);

  const invalidate = () => {
    cache.current.delete(key);
  };

  const refresh = async () => {
    cache.current.delete(key);
    setIsLoading(true);
    try {
      const result = await fetcher();
      cache.current.set(key, {
        data: result,
        timestamp: Date.now()
      });
      setData(result);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err : new Error('An error occurred'));
    } finally {
      setIsLoading(false);
    }
  };

  return { data, isLoading, error, invalidate, refresh };
}
