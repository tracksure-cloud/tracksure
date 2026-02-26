/**
 * Settings Page - Extensible Dynamic Configuration
 * 
 * Reads settings from extension registry.
 * All fields rendered dynamically from SettingsExtensionContext.
 */

import React, { useState, useEffect } from 'react';
import { useApp } from '../contexts/AppContext';
import { useSettingsExtension } from '../contexts/SettingsExtensionContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { TrackSureAPI } from '../utils/api';
import { DynamicField } from '../components/ui/DynamicField';
import { Icon, type IconName } from '../components/ui/Icon';
import { ConsentSettings } from '../components/settings';
import { __ } from '../utils/i18n';
import '../styles/pages/SettingsPage.css';

type SettingsTab = 'tracking' | 'privacy' | 'performance' | 'attribution' | 'advanced';

const SettingsPage: React.FC = () => {
  const { config } = useApp();
  const { getSettingsByCategory } = useSettingsExtension();
  const [activeTab, setActiveTab] = useState<SettingsTab>('tracking');
  const [saving, setSaving] = useState(false);
  const [settings, setSettings] = useState<Record<string, string | number | boolean>>({});

  // Load settings using useApiQuery
  const {
    data: settingsData,
    isLoading: loading,
    refetch,
  } = useApiQuery<Record<string, unknown>>(
    'getSettings',
    {},
    { refetchInterval: 600000 } // 10 min auto-refresh
  );

  // Update local settings state when data loads
  useEffect(() => {
    if (settingsData?.data) {
      // Type coercion: Normalize all boolean/integer values
      const normalized: Record<string, string | number | boolean> = {};
      const sections = getSettingsByCategory(activeTab);
      const allFields = sections.flatMap(s => s.fields);
      
      Object.keys(settingsData.data).forEach(key => {
        const field = allFields.find(f => f.id === key);
        const rawValue = settingsData.data[key];
        
        if (field) {
          // Type coercion based on field type
          if (field.type === 'toggle') {
            // Handle various boolean representations for toggle fields
            normalized[key] = rawValue === true || rawValue === 'true' || rawValue === '1' || rawValue === 1;
          } else if (field.type === 'number' || field.type === 'slider') {
            // Handle numeric field types
            normalized[key] = parseInt(rawValue, 10) || field.defaultValue || 0;
          } else {
            normalized[key] = rawValue;
          }
        } else {
          normalized[key] = rawValue;
        }
      });
      
      setSettings(normalized);
    }
  }, [settingsData, activeTab, getSettingsByCategory]);

  const getDependentFields = (fieldId: string, value: string | number | boolean): string[] => {
    // When tracking is disabled, these fields become irrelevant
    if (fieldId === 'tracksure_tracking_enabled' && value === false) {
      return [
        'tracksure_track_admins',
        'tracksure_session_timeout',
        'tracksure_batch_size',
        'tracksure_batch_timeout',
        'tracksure_respect_dnt',
        'tracksure_anonymize_ip',
        'tracksure_exclude_ips',
      ];
    }
    return [];
  };

  const handleFieldChange = (fieldId: string, value: string | number | boolean) => {
    // Show confirmation for critical changes
    if (fieldId === 'tracksure_tracking_enabled' && value === false) {
      if (!confirm(__('Disabling tracking will stop all data collection. Are you sure you want to continue?'))) {
        return; // User cancelled
      }
    }
    
    const newSettings = { ...settings, [fieldId]: value };
    
    // Auto-disable dependent fields
    const dependentFields = getDependentFields(fieldId, value);
    dependentFields.forEach(depField => {
      // Reset to schema default when parent disabled
      const sections = getSettingsByCategory(activeTab);
      const field = sections.flatMap(s => s.fields).find(f => f.id === depField);
      if (field) {
        newSettings[depField] = field.defaultValue;
      }
    });
    
    setSettings(newSettings);
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      
      const api = new TrackSureAPI(config);
      const response = (await api.updateSettings(settings)) as { success: boolean; message?: string };

      if (response.success) {
        alert(__('Settings saved successfully!'));
        // Force refetch to reload from server
        await refetch();
      } else {
        alert(__('Save failed: ') + (response.message || 'Unknown error'));
      }
    } catch (error) {
      console.error('[TrackSure] Failed to save settings:', error);
      alert(__('Failed to save settings. Please try again.'));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="ts-page">
        <div className="ts-loading">{__('Loading settings...')}</div>
      </div>
    );
  }

  const tabs: Array<{ id: SettingsTab; label: string; icon: string }> = [
    { id: 'tracking', label: __('Tracking'), icon: 'BarChart3' },
    { id: 'privacy', label: __('Privacy'), icon: 'Lock' },
    { id: 'performance', label: __('Performance'), icon: 'Zap' },
    { id: 'attribution', label: __('Attribution'), icon: 'Target' },
    { id: 'advanced', label: __('Advanced'), icon: 'Settings' },
  ];

  // Get sections for active tab from extensions
  const sections = getSettingsByCategory(activeTab);

  return (
    <div className="ts-page">
      <div className="ts-page-header">
        <div>
          <h1 className="ts-page-title">{__('Settings')}</h1>
          <p className="ts-page-description">
            {__('Configure tracking, privacy, performance, and attribution')}
          </p>
        </div>
        <button
          onClick={handleSave}
          disabled={saving}
          className="ts-button ts-button-primary"
        >
          {saving ? __('Saving...') : __('Save Changes')}
        </button>
      </div>

      <div className="ts-settings-container">
        {/* Tabs */}
        <div className="ts-settings-tabs">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              className={`ts-settings-tab ${activeTab === tab.id ? 'ts-active' : ''}`}
              onClick={() => setActiveTab(tab.id)}
            >
              <span className="ts-tab-icon">
                <Icon name={tab.icon as IconName} size={20} />
              </span>
              <span className="ts-tab-label">{tab.label}</span>
            </button>
          ))}
        </div>

        {/* Content */}
        <div className="ts-settings-content">
          {/* Consent Management UI for Privacy Tab */}
          {activeTab === 'privacy' && (
            <ConsentSettings className="mb-6" />
          )}

          {sections.length === 0 ? (
            <div className="ts-empty-state">
              <p>{__('No settings available for this category.')}</p>
            </div>
          ) : (
            sections.map((section) => (
              <div key={section.id} className="ts-settings-section">
                <div className="ts-section-header">
                  {section.icon && <span className="ts-section-icon">{section.icon}</span>}
                  <div>
                    <h2 className="ts-section-title">{section.title}</h2>
                    {section.description && (
                      <p className="ts-section-description">{section.description}</p>
                    )}
                  </div>
                </div>

                <div className="ts-settings-fields">
                  {section.fields.map((field) => (
                    <DynamicField
                      key={field.id}
                      field={field}
                      value={settings[field.id] ?? field.defaultValue}
                      onChange={(value) => handleFieldChange(field.id, value)}
                      disabled={saving}
                    />
                  ))}
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
};

export default SettingsPage;
