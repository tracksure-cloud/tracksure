/**
 * Integrations Page - WordPress Plugin Integrations
 * 
 * Dynamically loads integrations from extension registry.
 * Shows auto-detection status, enable/disable toggles, configuration.
 */

import React, { useState, useEffect } from 'react';
import { useApp } from '../contexts/AppContext';
import { useSettingsExtension } from '../contexts/SettingsExtensionContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { TrackSureAPI } from '../utils/api';
import { DynamicField } from '../components/ui/DynamicField';
import { Icon, type IconName } from '../components/ui/Icon';
import { __ } from '../utils/i18n';
import '../styles/pages/IntegrationsPage.css';

const IntegrationsPage: React.FC = () => {
  const { config } = useApp();
  const settingsExtension = useSettingsExtension();
  const [saving, setSaving] = useState(false);
  interface IntegrationSetting {
    enabled: boolean;
    config: Record<string, string | number | boolean>;
    autoDetected: boolean;
  }

  const [integrationSettings, setIntegrationSettings] = useState<Record<string, IntegrationSetting>>({});

  // Use centralized API query hook
  interface IntegrationsData {
    [key: string]: boolean | Record<string, unknown>;
  }

  const { data: settingsData, isLoading: loading } = useApiQuery<IntegrationsData>(
    'getSettings',
    undefined,
    {
      refetchInterval: 300000, // 5 minutes
      retry: 2,
    }
  );

  // Load integration settings when data is available
  useEffect(() => {
    if (settingsData?.success && settingsData?.data && settingsExtension.integrations.length > 0) {
      const response = settingsData;
      
      {
        // Filter integration-specific settings
        const intSettings: Record<string, IntegrationSetting> = {};
        settingsExtension.integrations.forEach((integration) => {
          // Use enabledKey if provided, fallback to tracksure_integration.id_enabled
          const enabledKey = integration.enabledKey || `tracksure_${integration.id}_enabled`;
          
          // Detection status is resolved server-side and passed in the integration metadata
          const autoDetected = Boolean(integration.detected);
          
          intSettings[integration.id] = {
            enabled: (typeof response.data === 'object' && response.data !== null && enabledKey in response.data)
              ? (response.data[enabledKey] === true || response.data[enabledKey] === 1)
              : false,
            autoDetected: autoDetected,
            config: {},
          };
          // Safely access fields with optional chaining
          if (integration.fields && Array.isArray(integration.fields)) {
            integration.fields.forEach((field) => {
              const fieldValue = (typeof response.data === 'object' && response.data !== null && field.id in response.data)
                ? response.data[field.id] as string | number | boolean
                : (field.defaultValue ?? '');
              intSettings[integration.id].config[field.id] = fieldValue;
            });
          }
        });
        
        setIntegrationSettings(intSettings);
      }
    }
  }, [settingsData, settingsExtension.integrations]);

  const handleToggleIntegration = (integrationId: string, enabled: boolean) => {
    setIntegrationSettings((prev) => ({
      ...prev,
      [integrationId]: {
        ...prev[integrationId],
        config: prev[integrationId]?.config || {},
        autoDetected: prev[integrationId]?.autoDetected ?? false,
        enabled,
      },
    }));
  };

  const handleFieldChange = (integrationId: string, fieldId: string, value: string | number | boolean) => {
    setIntegrationSettings((prev) => ({
      ...prev,
      [integrationId]: {
        ...prev[integrationId],
        enabled: prev[integrationId]?.enabled ?? false,
        autoDetected: prev[integrationId]?.autoDetected ?? false,
        config: {
          ...(prev[integrationId]?.config || {}),
          [fieldId]: value,
        },
      },
    }));
  };

  const handleSave = async () => {
    try {
      setSaving(true);

      // Transform integration settings to flat structure for WordPress options
      const flatSettings: Record<string, string | number | boolean> = {};
      Object.entries(integrationSettings).forEach(([intId, data]) => {
        // Find integration config to get correct enabledKey
        const integration = settingsExtension.integrations.find(i => i.id === intId);
        const enabledKey = integration?.enabledKey || `tracksure_${intId}_enabled`;
        flatSettings[enabledKey] = data.enabled;
        Object.entries(data.config).forEach(([key, value]) => {
          flatSettings[key] = value;
        });
      });

      const api = new TrackSureAPI(config);
      const response = (await api.updateSettings(flatSettings)) as { success: boolean; message?: string };

      if (response.success) {
        alert(__('Integrations saved successfully!'));
      }
    } catch (error) {
      console.error('[TrackSure] Failed to save integrations:', error);
      alert(__('Failed to save integrations. Please try again.'));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="ts-page">
        <div className="ts-loading">{__('Loading integrations...')}</div>
      </div>
    );
  }

  return (
    <div className="ts-page">
      <div className="ts-page-header">
        <div>
          <h1 className="ts-page-title">{__('Integrations')}</h1>
          <p className="ts-page-description">
            {__('Connect with WooCommerce, EDD, and other WordPress plugins')}
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

      <div className="ts-integrations-grid">
        {settingsExtension.integrations.length === 0 ? (
          <div className="ts-empty-state">
            <p>{__('No integrations configured. Integrations allow TrackSure to track events from your installed plugins.')}</p>
          </div>
        ) : (
          settingsExtension.integrations.map((integration) => {
            const intData = integrationSettings[integration.id] || { enabled: false, autoDetected: false, config: {} };
            const isEnabled = intData.enabled;
            const isAutoDetected = intData.autoDetected;

            return (
              <div
                key={integration.id}
                className={`ts-integration-card ${isEnabled ? 'ts-enabled' : ''} ${isAutoDetected ? 'ts-auto-detected' : ''}`}
              >
                {/* Header */}
                <div className="ts-integration-header">
                  {integration.icon && (
                    <span className="ts-integration-icon">
                      <Icon name={integration.icon as IconName} size={24} />
                    </span>
                  )}
                  <div className="ts-integration-info">
                    <h3 className="ts-integration-title">{integration.name}</h3>
                    <p className="ts-integration-description">{integration.description}</p>
                    
                    {/* Auto-detection status */}
                    {isAutoDetected ? (
                      <span className="ts-badge ts-badge-success">
                        <Icon name="CheckCircle" size={14} /> {__('Detected')}
                      </span>
                    ) : (
                      <span className="ts-badge ts-badge-warning">
                        <Icon name="AlertTriangle" size={14} /> {__('Not Installed')}
                      </span>
                    )}
                  </div>
                  <label className="ts-toggle-label">
                    <input
                      type="checkbox"
                      checked={isEnabled}
                      onChange={(e) => handleToggleIntegration(integration.id, e.target.checked)}
                      disabled={saving || !isAutoDetected}
                      className="ts-toggle-input"
                    />
                    <span className="ts-toggle-slider"></span>
                  </label>
                </div>

                {/* Tracked Events */}
                {isEnabled && integration.events && integration.events.length > 0 && (
                  <div className="ts-integration-events">
                    <h4 className="ts-events-title">{__('Tracked Events:')}</h4>
                    <div className="ts-event-tags">
                      {integration.events.map((event) => (
                        <span key={event} className="ts-event-tag">
                          {event}
                        </span>
                      ))}
                    </div>
                  </div>
                )}

                {/* Configuration Fields */}
                {isEnabled && integration.fields && integration.fields.length > 0 && (
                  <div className="ts-integration-config">
                    {integration.fields.map((field) => (
                      <DynamicField
                        key={field.id}
                        field={field}
                        value={intData.config[field.id] ?? field.defaultValue}
                        onChange={(value) => handleFieldChange(integration.id, field.id, value)}
                        disabled={saving}
                      />
                    ))}
                  </div>
                )}
              </div>
            );
          })
        )}
      </div>
    </div>
  );
};

export default IntegrationsPage;
