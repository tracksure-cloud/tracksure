/**
 * AppShell - Main Layout Container
 */

import React, { ReactNode, useState } from 'react';
import { TopBar } from './TopBar';
import { Sidebar } from './Sidebar';
import { __ } from '../../utils/i18n';
import '../../styles/components/layout/AppShell.css';

interface AppShellProps {
  children: ReactNode;
}

export const AppShell: React.FC<AppShellProps> = ({ children }) => {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  return (
    <div className="ts-app-shell">
      <TopBar />
      <div className="ts-app-body">
        <Sidebar collapsed={sidebarCollapsed} onToggle={() => setSidebarCollapsed(!sidebarCollapsed)} />
        <main className={`ts-app-main ${sidebarCollapsed ? 'sidebar-collapsed' : ''}`}>
          {children}
        </main>
      </div>
    </div>
  );
};
