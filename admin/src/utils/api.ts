/**
 * TrackSure API Client
 * 
 * Centralized API utility for fetching data from REST endpoints.
 * Includes retry logic, timeout handling, and user-friendly error messages.
 */

import type { TrackSureConfig } from '../types';

/**
 * Common query parameters for analytics endpoints
 */
export interface QueryParams {
  date_start?: string;
  date_end?: string;
  segment?: 'all' | 'new' | 'returning' | 'converted';
  page?: number;
  per_page?: number;
  [key: string]: string | number | boolean | undefined;
}

/**
 * Custom API Error class
 */
class APIError extends Error {
  status: number;
  data?: unknown;

  constructor(message: string, status: number, data?: unknown) {
    super(message);
    this.name = 'APIError';
    this.status = status;
    this.data = data;
  }
}

export class TrackSureAPI {
  private config: TrackSureConfig;
  private readonly DEFAULT_TIMEOUT = 60000; // 60 seconds (increased for large datasets)
  private readonly MAX_RETRIES = 2;

  constructor(config: TrackSureConfig) {
    this.config = config;
  }

  /**
   * Check if error is retryable (5xx errors or network issues)
   */
  private isRetryable(error: unknown): boolean {
    if (error instanceof APIError) {
      return error.status >= 500 && error.status < 600;
    }
    // Network errors (TypeError from fetch)
    return error instanceof TypeError;
  }

  /**
   * Sleep utility for retry backoff
   */
  private sleep(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Make authenticated API request with retry logic and timeout
   */
  private async request<T>(
    endpoint: string,
    options: RequestInit = {},
    retries = this.MAX_RETRIES
  ): Promise<T> {
    const url = `${this.config.apiUrl}${endpoint}`;
    
    for (let attempt = 0; attempt <= retries; attempt++) {
      try {
        // Create abort controller for timeout
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.DEFAULT_TIMEOUT);

        const headers: HeadersInit = {
          'Content-Type': 'application/json',
          'X-WP-Nonce': this.config.nonce,
          ...options.headers,
        };

        const response = await fetch(url, {
          ...options,
          headers,
          credentials: 'same-origin',
          signal: controller.signal,
        });

        clearTimeout(timeout);

        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          throw new APIError(
            errorData.message || `HTTP ${response.status}: ${response.statusText}`,
            response.status,
            errorData
          );
        }

        const data = await response.json();
        return data as T;
      } catch (error: unknown) {
        // Handle timeout
        if (error instanceof Error && error.name === 'AbortError') {
          console.error('[TrackSure API] Request timeout:', endpoint);
          throw new APIError('Request timeout - please try again', 408);
        }

        // Retry logic
        if (attempt < retries && this.isRetryable(error)) {
          const backoffMs = 1000 * Math.pow(2, attempt); // Exponential backoff: 1s, 2s
          console.warn(`[TrackSure API] Retry ${attempt + 1}/${retries} after ${backoffMs}ms:`, endpoint);
          await this.sleep(backoffMs);
          continue;
        }

        // Final error
        console.error('[TrackSure API] Request failed:', endpoint, error);
        throw error;
      }
    }

    // Should never reach here, but TypeScript needs it
    throw new APIError('Maximum retries exceeded', 500);
  }

  /**
   * GET request
   */
  async get<T>(endpoint: string, params?: Record<string, string | number | boolean>): Promise<T> {
    const queryString = params
      ? '?' + new URLSearchParams(
          Object.entries(params).reduce((acc, [key, value]) => {
            acc[key] = String(value);
            return acc;
          }, {} as Record<string, string>)
        ).toString()
      : '';

    return this.request<T>(`${endpoint}${queryString}`);
  }

  /**
   * POST request
   */
  async post<T>(endpoint: string, body?: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: JSON.stringify(body),
    });
  }

  /**
   * PUT request
   */
  async put<T>(endpoint: string, body?: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(body),
    });
  }

  /**
   * DELETE request
   */
  async delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'DELETE',
    });
  }

  // Settings API endpoints
  async getSettings() {
    // API returns settings directly, wrap in expected format
    const settings = await this.get('/settings');
    return { success: true, data: settings };
  }

  async updateSettings(settings: Record<string, unknown>) {
    // API returns {success: true, updated: [...]}
    return this.put('/settings', settings);
  }

  async getEnabledDestinations() {
    return this.get('/settings/destinations');
  }

  async getDetectedIntegrations() {
    return this.get('/settings/integrations');
  }

  // Query API endpoints (core database queries)
  
  /**
   * Generic query method for dynamic endpoints
   * Allows calling any /query/* endpoint with a path string
   * 
   * @example api.query('/attribution/insights?date_start=2025-01-01&date_end=2025-01-31')
   */
  async query<T = unknown>(path: string): Promise<T> {
    // Remove leading slash if present
    const cleanPath = path.startsWith('/') ? path : `/${path}`;
    return this.get(`/query${cleanPath}`);
  }
  
  async getOverview(params?: QueryParams) {
    return this.get('/query/overview', params);
  }

  async getRealtime(params?: QueryParams) {
    return this.get('/query/realtime', params);
  }

  async getSessions(params?: QueryParams) {
    return this.get('/query/sessions', params);
  }

  async getJourney(sessionId: string) {
    return this.get(`/query/journey/${sessionId}`);
  }

  async getVisitorJourney(visitorId: string | number) {
    return this.get(`/query/visitor/${visitorId}/journey`);
  }

  async getFunnel(params?: QueryParams) {
    return this.get('/query/funnel', params);
  }

  async getRegistry() {
    return this.get('/query/registry');
  }

  async getLogs(params?: QueryParams) {
    return this.get('/query/logs', params);
  }

  async getTrafficSources(params?: QueryParams) {
    return this.get('/query/traffic-sources', params);
  }

  async getPages(params?: QueryParams) {
    return this.get('/query/pages', params);
  }

  async getVisitors(params?: QueryParams) {
    return this.get('/query/visitors', params);
  }

  async getAttribution(params?: QueryParams) {
    return this.get('/query/attribution', params);
  }

  // Products API endpoints (WooCommerce analytics)
  async getProductsPerformance(params?: QueryParams) {
    return this.get('/products/performance', params);
  }

  async getProductsCategories(params?: QueryParams) {
    return this.get('/products/categories', params);
  }

  async getProductsFunnel(params?: QueryParams) {
    return this.get('/products/funnel', params);
  }

  // Goals API endpoints
  async getGoals(params?: QueryParams) {
    return this.get('/goals', params);
  }

  async getGoalsOverview(params?: QueryParams) {
    return this.get('/goals/overview', params);
  }

  async getGoalsPerformance(params?: QueryParams) {
    return this.get('/goals/performance', params);
  }

  async getGoalPerformance(goalId: number, params?: QueryParams) {
    return this.get(`/goals/${goalId}/performance`, params);
  }

  async getGoalTimeline(goalId: number, params?: QueryParams) {
    return this.get(`/goals/${goalId}/timeline`, params);
  }

  async getGoalSources(goalId: number, params?: QueryParams) {
    return this.get(`/goals/${goalId}/sources`, params);
  }

  /** Fetch aggregated device & browser distribution for a goal (server-side). */
  async getGoalDevices(goalId: number, params?: QueryParams) {
    return this.get(`/goals/${goalId}/devices`, params);
  }

  async createGoal(goalData: Record<string, unknown>) {
    return this.post('/goals', goalData);
  }

  async updateGoal(goalId: number, goalData: Record<string, unknown>) {
    return this.put(`/goals/${goalId}`, goalData);
  }

  async deleteGoal(goalId: number) {
    return this.delete(`/goals/${goalId}`);
  }

  // Diagnostics API endpoints
  async getDiagnosticsHealth() {
    return this.get('/diagnostics/health');
  }

  async getDiagnosticsDelivery(params?: QueryParams) {
    return this.get('/diagnostics/delivery', params);
  }

  async getDiagnosticsCron() {
    return this.get('/diagnostics/cron');
  }

  // Quality API endpoints
  async getQualitySignal(params?: QueryParams) {
    return this.get('/quality/signal', params);
  }

  async getQualityDeduplication(params?: QueryParams) {
    return this.get('/quality/deduplication', params);
  }

  async getQualitySchema(params?: QueryParams) {
    return this.get('/quality/schema', params);
  }

  async getQualityReconciliation(params?: QueryParams) {
    return this.get('/quality/reconciliation', params);
  }

}

/**
 * API hook for React components
 */
export const useAPI = (config: TrackSureConfig) => {
  return new TrackSureAPI(config);
};
