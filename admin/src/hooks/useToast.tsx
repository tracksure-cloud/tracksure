/**
 * useToast Hook
 * 
 * Provides toast notification functionality for user feedback
 */

import { useCallback } from 'react';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastOptions {
  duration?: number;
  position?: 'top' | 'bottom';
}

// Simple toast implementation - can be replaced with a more sophisticated library
export const useToast = () => {
  const show = useCallback((message: string, type: ToastType = 'success', options: ToastOptions = {}) => {
    const { duration = 3000, position = 'bottom' } = options;

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `ts-toast ts-toast--${type} ts-toast--${position}`;
    toast.textContent = message;
    
    // Add to DOM
    document.body.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => {
      toast.classList.add('ts-toast--visible');
    });

    // Remove after duration
    setTimeout(() => {
      toast.classList.remove('ts-toast--visible');
      setTimeout(() => {
        document.body.removeChild(toast);
      }, 300);
    }, duration);
  }, []);

  const success = useCallback((message: string, options?: ToastOptions) => {
    show(message, 'success', options);
  }, [show]);

  const error = useCallback((message: string, options?: ToastOptions) => {
    show(message, 'error', options);
  }, [show]);

  const warning = useCallback((message: string, options?: ToastOptions) => {
    show(message, 'warning', options);
  }, [show]);

  const info = useCallback((message: string, options?: ToastOptions) => {
    show(message, 'info', options);
  }, [show]);

  return { show, success, error, warning, info };
};
