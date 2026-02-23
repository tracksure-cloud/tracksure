/**
 * Theme Toggle Component
 */

import React from 'react';
import { useTheme } from '../../contexts/ThemeContext';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/ThemeToggle.css';

export const ThemeToggle: React.FC = () => {
  const { theme, setTheme } = useTheme();

  const handleToggle = () => {
    const themes: Array<'light' | 'dark' | 'auto'> = ['light', 'dark', 'auto'];
    const currentIndex = themes.indexOf(theme);
    const nextTheme = themes[(currentIndex + 1) % themes.length];
    setTheme(nextTheme);
  };

  const icons = {
    light: '☀️',
    dark: '🌙',
    auto: '🔄',
  };

  return (
    <button className="ts-theme-toggle" onClick={handleToggle} title={`${__("Theme")}: ${theme}`}>
      <span className="ts-theme-icon">{icons[theme]}</span>
    </button>
  );
};
