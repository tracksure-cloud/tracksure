/**
 * Destinations Page - External Platform Integrations
 * 
 * Dynamically loads destinations from extension registry.
 * Each destination: enable/disable toggle, configuration fields, test connection.
 */

import React, { useState, useEffect } from 'react';
import { useApp } from '../contexts/AppContext';
import { useSettingsExtension } from '../contexts/SettingsExtensionContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { useGA4SetupGuide } from '../hooks/useGA4SetupGuide';
import { DynamicField } from '../components/ui/DynamicField';
import { GA4SetupGuideModal } from '../components/GA4SetupGuideModal';
import { TrackSureAPI } from '../utils/api';
import { __ } from '../utils/i18n';
import { Icon, type IconName } from '../components/ui/Icon';
import '../styles/pages/DestinationsPage.css';

interface DestSetting {
  enabled: boolean;
  config: Record<string, string | number | boolean>;
}

const DestinationsPage: React.FC = () => {
  const { config } = useApp();
  const { destinations } = useSettingsExtension();
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState<string | null>(null);
  const [destinationSettings, setDestinationSettings] = useState<Record<string, DestSetting>>({});
  const [showGA4GuideModal, setShowGA4GuideModal] = useState(false);
  
  // Fetch GA4 setup guide data
  const { guideData, showGuide, dismissGuide } = useGA4SetupGuide();

  // Load settings using useApiQuery with 10min auto-refresh
  const { data: settingsData, isLoading: loading, refetch } = useApiQuery<Record<string, unknown>>(
    'getSettings',
    {},
    { refetchInterval: 600000 } // 10 minutes
  );

  // Parse settings data into destination-specific structure
  useEffect(() => {
    if (settingsData?.data) {
      const response = settingsData;
      const destSettings: Record<string, DestSetting> = {};
      destinations.forEach((dest) => {
        // Use enabledKey if provided, fallback to tracksure_dest.id_enabled
        const enabledKey = dest.enabledKey || `tracksure_${dest.id}_enabled`;
        destSettings[dest.id] = {
          enabled: response.data[enabledKey] === true || response.data[enabledKey] === 1,
          config: {},
        };
        // Safely access fields with optional chaining
        if (dest.fields && Array.isArray(dest.fields)) {
          dest.fields.forEach((field) => {
            destSettings[dest.id].config[field.id] = response.data[field.id] ?? field.defaultValue ?? '';
          });
        }
      });
      setDestinationSettings(destSettings);
    }
  }, [settingsData, destinations]);

  const handleToggleDestination = (destinationId: string, enabled: boolean) => {
    setDestinationSettings((prev) => ({
      ...prev,
      [destinationId]: {
        ...prev[destinationId],
        config: prev[destinationId]?.config || {},
        enabled,
      },
    }));
  };

  const handleFieldChange = (destinationId: string, fieldId: string, value: string | number | boolean) => {
    setDestinationSettings((prev) => ({
      ...prev,
      [destinationId]: {
        ...prev[destinationId],
        enabled: prev[destinationId]?.enabled ?? false,
        config: {
          ...(prev[destinationId]?.config || {}),
          [fieldId]: value,
        },
      },
    }));
  };

  const handleTestConnection = async (destinationId: string) => {
    const destination = destinations.find((d) => d.id === destinationId);
    if (!destination || !destination.testFunction) {
      alert(__('Test function not available for this destination.'));
      return;
    }

    try {
      setTesting(destinationId);
      const config = destinationSettings[destinationId]?.config || {};
      const result = await destination.testFunction(config);

      if (result.success) {
        alert(__('Connection successful! ') + (result.message || ''));
      } else {
        alert(__('Connection failed: ') + (result.message || __('Unknown error')));
      }
    } catch (error) {
      console.error('[TrackSure] Test connection failed:', error);
      alert(__('Connection test failed. Check console for details.'));
    } finally {
      setTesting(null);
    }
  };

  const handleSave = async () => {
    try {
      setSaving(true);

      // Transform destination settings to flat structure for WordPress options
      const flatSettings: Record<string, string | number | boolean> = {};
      Object.entries(destinationSettings).forEach(([destId, data]) => {
        // Find destination config to get correct enabledKey
        const dest = destinations.find((d) => d.id === destId);
        const enabledKey = dest?.enabledKey || `tracksure_${destId}_enabled`;
        flatSettings[enabledKey] = data.enabled;
        Object.entries(data.config).forEach(([key, value]) => {
          flatSettings[key] = value;
        });
      });

      const api = new TrackSureAPI(config);
      const response = (await api.updateSettings(flatSettings)) as { success: boolean; message?: string };

      if (response.success) {
        alert(__('Destinations saved successfully!'));
        // Force refetch settings to ensure UI is in sync
        await refetch();
      } else {
        alert(__('Save failed: ') + (response.message || 'Unknown error'));
      }
    } catch (error) {
      console.error('[TrackSure] Failed to save destinations:', error);
      alert(__('Failed to save destinations. Please try again.'));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="ts-page">
        <div className="ts-loading">{__('Loading destinations...')}</div>
      </div>
    );
  }

  return (
    <div className="ts-page">
      <div className="ts-page-header">
        <div>
          <h1 className="ts-page-title">{__('Destinations')}</h1>
          <p className="ts-page-description">
            {__('Send tracking data to external platforms (Meta, Google, etc.)')}
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

      <div className="ts-destinations-grid">
        {destinations.length === 0 ? (
          <div className="ts-empty-state">
            <p>{__('No destinations available. Install TrackSure Pro for more options.')}</p>
          </div>
        ) : (
          destinations.map((destination) => {
            const destData = destinationSettings[destination.id] || { enabled: false, config: {} };
            const isEnabled = destData.enabled;

            return (
              <div
                key={destination.id}
                className={`ts-destination-card ${isEnabled ? 'ts-enabled' : ''}`}
              >
                {/* Header */}
                <div className="ts-destination-header">
                  {destination.icon && (
                    <span className="ts-destination-icon">
                      <Icon name={destination.icon as IconName} size={24} />
                    </span>
                  )}
                  <div className="ts-destination-info">
                    <h3 className="ts-destination-title">{destination.name}</h3>
                    <p className="ts-destination-description">{destination.description}</p>
                  </div>
                  <label className="ts-toggle-label">
                    <input
                      type="checkbox"
                      checked={isEnabled}
                      onChange={(e) => handleToggleDestination(destination.id, e.target.checked)}
                      disabled={saving}
                      className="ts-toggle-input"
                    />
                    <span className="ts-toggle-slider"></span>
                  </label>
                </div>

                {/* Configuration Fields */}
                {isEnabled && (
                  <div className="ts-destination-config">
                    {/* Custom Configuration Component (for complex destinations like Google Ads) */}
                    {destination.custom_config ? (
                      (() => {
                        // Lookup custom component from window.trackSureProComponents
                        const typedWindow = window as Window & { trackSureProComponents?: Record<string, React.ComponentType<Record<string, unknown>>> };
                        const CustomConfigComponent = typedWindow.trackSureProComponents?.[destination.custom_config];
                        
                        if (CustomConfigComponent) {
                          return (
                            <CustomConfigComponent
                              settings={destData.config}
                              onSettingChange={(key: string, value: string | number | boolean) => handleFieldChange(destination.id, key, value)}
                              disabled={saving}
                            />
                          );
                        } else {
                          console.warn(`[DestinationsPage] Custom component "${destination.custom_config}" not found in window.trackSureProComponents`);
                          // Fallback to default field rendering
                          return destination.fields && destination.fields.length > 0 ? (
                            <>
                              {destination.fields.map((field) => (
                                <DynamicField
                                  key={field.id}
                                  field={field}
                                  value={destData.config[field.id] ?? field.defaultValue}
                                  onChange={(value) => handleFieldChange(destination.id, field.id, value)}
                                  disabled={saving}
                                />
                              ))}
                            </>
                          ) : null;
                        }
                      })()
                    ) : (
                      /* Default Field Rendering (for simple destinations like Meta, GA4) */
                      destination.fields && destination.fields.length > 0 && (
                        <>
                          {destination.fields.map((field) => (
                            <DynamicField
                              key={field.id}
                              field={field}
                              value={destData.config[field.id] ?? field.defaultValue}
                              onChange={(value) => handleFieldChange(destination.id, field.id, value)}
                              disabled={saving}
                            />
                          ))}
                        </>
                      )
                    )}

                    {/* GA4 Setup Guide Button */}
                    {destination.id === 'ga4' && showGuide && (
                      <div className="ts-ga4-setup-guide-banner">
                        <div className="ts-ga4-setup-guide-banner-content">
                          <Icon name="AlertCircle" size={20} color="warning" />
                          <div>
                            <strong>{__('Setup Required')}</strong>
                            <p>{__('Complete 5 quick steps in GA4 Admin to see your data in reports.')}</p>
                          </div>
                        </div>
                        <button
                          onClick={() => setShowGA4GuideModal(true)}
                          className="ts-button ts-button-primary"
                        >
                          <Icon name="ExternalLink" size={16} />
                          {__('View Setup Guide')}
                        </button>
                      </div>
                    )}

                    {/* Test Connection Button */}
                    {destination.testFunction && (
                      <button
                        onClick={() => handleTestConnection(destination.id)}
                        disabled={testing === destination.id || saving}
                        className="ts-button ts-button-secondary ts-test-button"
                      >
                        {testing === destination.id ? __('Testing...') : __('Test Connection')}
                      </button>
                    )}
                  </div>
                )}
              </div>
            );
          })
        )}
      </div>

      {/* GA4 Setup Guide Modal */}
      <GA4SetupGuideModal
        isOpen={showGA4GuideModal}
        onClose={() => setShowGA4GuideModal(false)}
        guideData={guideData}
        onDismiss={dismissGuide}
      />
    </div>
  );
};

export default DestinationsPage;
