/**
 * Theme Context - Dark/Light Mode with User Preference
 */

import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import type { Theme } from '../types';
import { __ } from '../utils/i18n';

interface ThemeContextValue {
  theme: Theme;
  effectiveTheme: 'light' | 'dark';
  setTheme: (theme: Theme) => void;
}

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within ThemeProvider');
  }
  return context;
};

interface ThemeProviderProps {
  children: ReactNode;
}

export const ThemeProvider: React.FC<ThemeProviderProps> = ({ children }) => {
  const [theme, setThemeState] = useState<Theme>(() => {
    try {
      const saved = localStorage.getItem('tracksure_theme');
      return (saved as Theme) || 'auto';
    } catch {
      return 'auto';
    }
  });

  const [effectiveTheme, setEffectiveTheme] = useState<'light' | 'dark'>('light');

  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const computeEffectiveTheme = () => {
      if (theme === 'auto') {
        return mediaQuery.matches ? 'dark' : 'light';
      }
      return theme;
    };

    const applyThemeToDom = (computed: 'light' | 'dark') => {
      // Prefer scoping to TrackSure root so we don't mess with WP admin globally
      const root = document.getElementById('tracksure-admin-root');
      const el = root || document.documentElement;

      el.setAttribute('data-theme', computed);
      el.setAttribute('data-theme-pref', theme); // "light" | "dark" | "auto"
    };

    const updateTheme = () => {
      const computed = computeEffectiveTheme();
      setEffectiveTheme(computed);
      applyThemeToDom(computed);
    };

    updateTheme();

    const handler = () => {
      if (theme === 'auto') {
        updateTheme();
      }
    };

    // Safari fallback
    try {
      mediaQuery.addEventListener('change', handler);
      return () => mediaQuery.removeEventListener('change', handler);
    } catch {
      mediaQuery.addListener(handler);
      return () => mediaQuery.removeListener(handler);
    }
  }, [theme]);

  const setTheme = (newTheme: Theme) => {
    setThemeState(newTheme);
    try {
      localStorage.setItem('tracksure_theme', newTheme);
    } catch {
      // ignore (private mode / blocked storage)
    }
  };

  return (
    <ThemeContext.Provider value={{ theme, effectiveTheme, setTheme }}>
      {children}
    </ThemeContext.Provider>
  );
};
