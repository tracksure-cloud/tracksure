/**
 * Sidebar - Navigation Menu
 */

import React from 'react';
import { NavLink } from 'react-router-dom';
import { useExtensionRegistry } from '../../contexts/ExtensionRegistryContext';
import { Icon } from '../ui/Icon';
import { ICON_REGISTRY } from '../../config/iconRegistry';
import type { IconName } from '../../config/iconRegistry';
import { __ } from '../../utils/i18n';
import '../../styles/components/layout/Sidebar.css';

interface SidebarProps {
  collapsed: boolean;
  onToggle: () => void;
}

const coreNavItems = [
  {
    group: 'analytics',
    groupLabel: __('Analytics'),
    items: [
      { path: '/overview', label: __('Dashboard'), icon: ICON_REGISTRY.dashboard },
      { path: '/realtime', label: __('Live'), icon: ICON_REGISTRY.realtime },
      { path: '/journeys', label: __('Journeys'), icon: ICON_REGISTRY.journeys },
      { path: '/sessions', label: __('Sessions'), icon: ICON_REGISTRY.sessions },
      { path: '/traffic-sources', label: __('Acquisition'), icon: ICON_REGISTRY.acquisition },
      { path: '/pages', label: __('Content'), icon: ICON_REGISTRY.content },
      { path: '/products', label: __('Products'), icon: ICON_REGISTRY.products },
      { path: '/data-quality', label: __('Data Quality'), icon: ICON_REGISTRY.dataQuality },
      { path: '/attribution', label: __('Attribution'), icon: ICON_REGISTRY.attribution || ICON_REGISTRY.insights },
      { path: '/conversions', label: __('Conversions'), icon: ICON_REGISTRY.conversion || ICON_REGISTRY.goals },
      { path: '/goals', label: __('Goals'), icon: ICON_REGISTRY.goals },
    ],
  },
  {
    group: 'tools',
    groupLabel: __('Tools'),
    items: [
      { path: '/diagnostics', label: __('Diagnostics'), icon: ICON_REGISTRY.diagnostics },
    ],
  },
  {
    group: 'settings',
    groupLabel: __('Settings'),
    items: [
      { path: '/settings', label: __('Settings'), icon: ICON_REGISTRY.settings },
      { path: '/destinations', label: __('Destinations'), icon: ICON_REGISTRY.destinations },
      { path: '/integrations', label: __('Integrations'), icon: ICON_REGISTRY.integrations },
    ],
  },
];

export const Sidebar: React.FC<SidebarProps> = ({ collapsed, onToggle }) => {
  const { routes, navGroups } = useExtensionRegistry();

  // Merge core + extension routes
  const allNavItems = [...coreNavItems];

  // Add extension routes to their groups
  routes.forEach((route) => {
    const groupIndex = allNavItems.findIndex((g) => g.group === route.nav.group);
    // Extension icons can be IconName (string) or already rendered React element
    const extensionIcon = route.nav.icon || ICON_REGISTRY.pages;
    
    if (groupIndex >= 0) {
      allNavItems[groupIndex].items.push({
        path: route.path,
        label: route.nav.label,
        icon: extensionIcon as IconName,
      });
    } else {
      // Create new group
      const groupDef = navGroups.find((g) => g.id === route.nav.group);
      allNavItems.push({
        group: route.nav.group,
        groupLabel: groupDef?.label || route.nav.group,
        items: [
          {
            path: route.path,
            label: route.nav.label,
            icon: extensionIcon as IconName,
          },
        ],
      });
    }
  });

  const expandLabel = __('Expand sidebar');
  const collapseLabel = __('Collapse sidebar');

  return (
    <aside className={`ts-sidebar ${collapsed ? 'collapsed' : ''}`}>
      <button 
        className="ts-sidebar-toggle" 
        onClick={onToggle} 
        title={collapsed ? expandLabel : collapseLabel}
        aria-label={collapsed ? expandLabel : collapseLabel}
      >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor">
          <path d="M3 10H17M10 3L17 10L10 17" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </button>

      <nav className="ts-sidebar-nav">
        {allNavItems.map((group) => (
          <div key={group.group} className="ts-sidebar-group">
            {!collapsed && <div className="ts-sidebar-group-label">{group.groupLabel || ''}</div>}
            {group.items.map((item) => (
              <NavLink
                key={item.path}
                to={item.path}
                className={({ isActive }) => `ts-sidebar-item ${isActive ? 'active' : ''}`}
                title={collapsed ? (item.label || '') : undefined}
              >
                <span className="ts-sidebar-icon">
                  <Icon name={item.icon} size={20} aria-label={item.label || ''} />
                </span>
                {!collapsed && <span className="ts-sidebar-label">{item.label || ''}</span>}
              </NavLink>
            ))}
          </div>
        ))}
      </nav>
    </aside>
  );
};
