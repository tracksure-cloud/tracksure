/**
 * useAccessibility Hook
 * 
 * Provides keyboard navigation, focus management, and ARIA utilities.
 * 
 * @package TrackSure\Admin
 * @since 2.0.0
 */

import { useEffect, useRef, useCallback } from 'react';

export interface KeyboardShortcut {
  key: string;
  ctrl?: boolean;
  shift?: boolean;
  alt?: boolean;
  meta?: boolean;
  callback: () => void;
  description: string;
}

/**
 * Hook for managing keyboard shortcuts
 */
export function useKeyboardShortcuts(shortcuts: KeyboardShortcut[]) {
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      const shortcut = shortcuts.find(
        (s) =>
          s.key.toLowerCase() === e.key.toLowerCase() &&
          !!s.ctrl === e.ctrlKey &&
          !!s.shift === e.shiftKey &&
          !!s.alt === e.altKey &&
          !!s.meta === e.metaKey
      );

      if (shortcut) {
        e.preventDefault();
        shortcut.callback();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [shortcuts]);
}

/**
 * Hook for focus trap (useful in modals, drawers)
 */
export function useFocusTrap(isActive: boolean) {
  const containerRef = useRef<HTMLElement>(null);

  useEffect(() => {
    if (!isActive || !containerRef.current) {return;}

    const container = containerRef.current;
    const focusableElements = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    const firstElement = focusableElements[0] as HTMLElement;
    const lastElement = focusableElements[focusableElements.length - 1] as HTMLElement;

    const handleTabKey = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') {return;}

      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          lastElement?.focus();
          e.preventDefault();
        }
      } else {
        if (document.activeElement === lastElement) {
          firstElement?.focus();
          e.preventDefault();
        }
      }
    };

    // Focus first element when trap activates
    firstElement?.focus();

    document.addEventListener('keydown', handleTabKey);
    return () => document.removeEventListener('keydown', handleTabKey);
  }, [isActive]);

  return containerRef;
}

/**
 * Hook for announcing screen reader messages
 */
export function useAnnounce() {
  const announcerRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    // Create ARIA live region if it doesn't exist
    if (!document.getElementById('ts-screen-reader-announcer')) {
      const announcer = document.createElement('div');
      announcer.id = 'ts-screen-reader-announcer';
      announcer.setAttribute('role', 'status');
      announcer.setAttribute('aria-live', 'polite');
      announcer.setAttribute('aria-atomic', 'true');
      announcer.className = 'sr-only';
      announcer.style.cssText = `
        position: absolute;
        left: -10000px;
        width: 1px;
        height: 1px;
        overflow: hidden;
      `;
      document.body.appendChild(announcer);
      announcerRef.current = announcer;
    } else {
      announcerRef.current = document.getElementById('ts-screen-reader-announcer') as HTMLDivElement;
    }
  }, []);

  const announce = useCallback((message: string, priority: 'polite' | 'assertive' = 'polite') => {
    if (announcerRef.current) {
      announcerRef.current.setAttribute('aria-live', priority);
      announcerRef.current.textContent = message;
      
      // Clear after 1 second so repeated messages are announced
      setTimeout(() => {
        if (announcerRef.current) {
          announcerRef.current.textContent = '';
        }
      }, 1000);
    }
  }, []);

  return announce;
}

/**
 * Hook for skip links navigation
 */
export function useSkipLinks() {
  useEffect(() => {
    const skipLinks = document.querySelectorAll('a[href^="#"]');
    
    skipLinks.forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = (link as HTMLAnchorElement).getAttribute('href')?.slice(1);
        if (targetId) {
          const target = document.getElementById(targetId);
          if (target) {
            target.setAttribute('tabindex', '-1');
            target.focus();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      });
    });
  }, []);
}

/**
 * Generate unique IDs for ARIA attributes
 */
let idCounter = 0;
export function useAriaId(prefix: string = 'ts'): string {
  const id = useRef<string>('');
  
  if (!id.current) {
    idCounter += 1;
    id.current = `${prefix}-${idCounter}`;
  }
  
  return id.current;
}
