/**
 * TrackSure Admin - Main Entry Point
 * 
 * React 18 SPA for TrackSure analytics admin interface.
 * Uses Context-based state management (no Redux).
 * 
 * @package TrackSure\Admin
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/global.css';

// Wait for DOM ready
document.addEventListener('DOMContentLoaded', () => {
  const rootElement = document.getElementById('tracksure-admin-root');
  
  if (!rootElement) {
    console.error('TrackSure: Admin root element not found');
    return;
  }

  // Get config passed from PHP
  const config = (window.trackSureAdmin || {}) as Parameters<typeof App>[0]['config'];
  
  const root = createRoot(rootElement);
  root.render(
    <React.StrictMode>
      <App config={config} />
    </React.StrictMode>
  );
});
