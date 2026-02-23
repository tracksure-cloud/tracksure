/**
 * Error Handler Utilities
 * 
 * Provides standardized error messages for better user experience
 */

import { __ } from '@wordpress/i18n';

interface ApiErrorLike {
  response?: { status?: number };
  code?: string;
  message?: string;
}

const toApiError = (error: unknown): ApiErrorLike => {
  if (error instanceof Error) {
    return error as ApiErrorLike;
  }
  if (typeof error === 'object' && error !== null) {
    return error as ApiErrorLike;
  }
  return { message: String(error) };
};

export const handleApiError = (error: unknown): string => {
  // Network errors
  if (!navigator.onLine) {
    return __('No internet connection. Please check your network.', 'tracksure');
  }

  const err = toApiError(error);

  // HTTP status errors
  if (err.response) {
    switch (err.response.status) {
      case 400:
        return __('Invalid request. Please check your input.', 'tracksure');
      case 401:
        return __('Session expired. Please refresh the page.', 'tracksure');
      case 403:
        return __('You don\'t have permission to perform this action.', 'tracksure');
      case 404:
        return __('Data not found. Please try a different date range.', 'tracksure');
      case 429:
        return __('Too many requests. Please wait a moment.', 'tracksure');
      case 500:
        return __('Server error. Please try again later.', 'tracksure');
      case 503:
        return __('Service temporarily unavailable. Please try again.', 'tracksure');
      default:
        return __('An unexpected error occurred. Please try again.', 'tracksure');
    }
  }

  // Timeout errors
  if (err.code === 'ECONNABORTED') {
    return __('Request timed out. Please check your connection.', 'tracksure');
  }

  // Generic error
  return err.message || __('An unexpected error occurred. Please refresh the page.', 'tracksure');
};

export const getErrorSuggestion = (error: unknown): string | null => {
  if (!navigator.onLine) {
    return __('Try checking your WiFi or mobile data connection.', 'tracksure');
  }

  const err = toApiError(error);

  if (err.response?.status === 404) {
    return __('Try selecting a different date range with more data.', 'tracksure');
  }

  if (err.response?.status === 500) {
    return __('If this persists, please contact support.', 'tracksure');
  }

  return null;
};
