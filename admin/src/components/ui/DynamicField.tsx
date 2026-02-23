/**
 * Dynamic Form Field Component
 * 
 * Renders different field types based on SettingField type.
 * Used by Settings, Destinations, and Integrations pages.
 */

import React from 'react';
import type { SettingField } from '../../types/extensions';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/DynamicField.css';

interface FieldProps {
  field: SettingField;
  value: string | number | boolean;
  onChange: (value: string | number | boolean) => void;
  disabled?: boolean;
}

export const DynamicField: React.FC<FieldProps> = ({ field, value, onChange, disabled }) => {
  const [showToken, setShowToken] = React.useState(false);
  const [justRegenerated, setJustRegenerated] = React.useState(false);

  const handleRegenerateToken = async () => {
    if (!window.confirm(
      __('Regenerating the API token will invalidate the current token. Any external integrations using the old token will stop working. Continue?')
    )) {
      return;
    }

    try {
      const response = await fetch(`${window.trackSureAdmin?.apiUrl || '/wp-json/tracksure/v1'}/settings/regenerate-token`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.trackSureAdmin?.nonce || '',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to regenerate token');
      }

      const data = await response.json();
      onChange(data.api_token);
      setShowToken(true); // Automatically show new token once
      setJustRegenerated(true); // Mark as just regenerated
      alert(__('✅ API token regenerated successfully!\n\n⚠️ IMPORTANT: Copy this token now! It will be hidden when you navigate away or refresh the page.'));
    } catch (error) {
      console.error('Error regenerating token:', error);
      alert(__('Failed to regenerate API token. Please try again.'));
    }
  };

  const copyToClipboard = (text: string) => {
    // Modern Clipboard API (HTTPS/localhost only)
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => {
        alert(__('Token copied to clipboard!'));
      }).catch(() => {
        fallbackCopyToClipboard(text);
      });
    } else {
      // Fallback for HTTP or older browsers
      fallbackCopyToClipboard(text);
    }
  };

  const fallbackCopyToClipboard = (text: string) => {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
      const successful = document.execCommand('copy');
      if (successful) {
        alert(__('Token copied to clipboard!'));
      } else {
        alert(__('Failed to copy. Please copy manually:') + ' ' + text);
      }
    } catch (err) {
      alert(__('Failed to copy. Please copy manually:') + ' ' + text);
    }
    
    document.body.removeChild(textArea);
  };

  // Special handling for API token field
  if (field.id === 'tracksure_api_token') {
    const tokenValue = value || '';
    const maskedValue = tokenValue ? '•'.repeat(Math.min(32, tokenValue.length)) : '';
    
    return (
      <div className="ts-field ts-field-token">
        <label className="ts-field-label">
          {field.label}
          {field.description && (
            <span className="ts-field-description">{field.description}</span>
          )}
        </label>
        
        {justRegenerated && showToken && (
          <div className="ts-token-warning">
            <strong>{__('⚠️ New Token Generated')}</strong>
            <p>
              {__('This is your only chance to copy this token! It will be masked when you:')}
              <br/>{__('• Navigate away from this page')}
              <br/>{__('• Refresh the page')}
              <br/>{__('• Click "Hide Token"')}
            </p>
          </div>
        )}
        
        <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
          <input
            type="text"
            value={showToken ? tokenValue : maskedValue}
            readOnly
            className="ts-input"
            style={{ flex: 1, fontFamily: 'monospace', fontSize: '13px', minWidth: '200px' }}
          />
          <button
            type="button"
            onClick={() => {
              setShowToken(!showToken);
              if (!showToken) {setJustRegenerated(false);} // Clear warning when manually showing
            }}
            disabled={disabled || !tokenValue}
            className="ts-button ts-button-secondary"
            style={{ whiteSpace: 'nowrap' }}
          >
            {showToken ? __('Hide Token') : __('Show Token')}
          </button>
          <button
            type="button"
            onClick={() => copyToClipboard(tokenValue)}
            disabled={disabled || !tokenValue}
            className="ts-button ts-button-secondary"
            style={{ whiteSpace: 'nowrap' }}
          >
            {__('Copy')}
          </button>
          <button
            type="button"
            onClick={handleRegenerateToken}
            disabled={disabled}
            className="ts-button ts-button-secondary"
            style={{ whiteSpace: 'nowrap' }}
          >
            {__('Regenerate')}
          </button>
        </div>
        
        {showToken && tokenValue && !justRegenerated && (
          <p className="ts-token-security-note">
            ⚠️ <strong>{__('Keep this token secure!')}</strong> {__('Anyone with this token can access your TrackSure data via the REST API.')}
          </p>
        )}
      </div>
    );
  }

  const renderField = () => {
    switch (field.type) {
      case 'toggle':
        return (
          <label className="ts-toggle-field">
            <input
              type="checkbox"
              checked={!!value}
              onChange={(e) => onChange(e.target.checked)}
              disabled={disabled || field.readonly}
            />
            <div className="ts-toggle-switch"></div>
            <div className="ts-toggle-label">
              <strong>{field.label}</strong>
              {field.description && <p>{field.description}</p>}
            </div>
          </label>
        );

      case 'text':
      case 'password':
        return (
          <div className="ts-field">
            <label className="ts-field-label">
              {field.label}
              {field.description && (
                <span className="ts-field-description">{field.description}</span>
              )}
            </label>
            <input
              type={field.type}
              value={value || ''}
              onChange={(e) => onChange(e.target.value)}
              disabled={disabled || field.readonly}
              className="ts-input"
              placeholder={field.sensitive ? '••••••••' : ''}
            />
          </div>
        );

      case 'number':
        return (
          <div className="ts-field">
            <label className="ts-field-label">
              {field.label}
              {field.description && (
                <span className="ts-field-description">{field.description}</span>
              )}
            </label>
            <input
              type="number"
              value={value || field.defaultValue}
              onChange={(e) => onChange(parseInt(e.target.value, 10))}
              disabled={disabled || field.readonly}
              min={field.min}
              max={field.max}
              step={field.step || 1}
              className="ts-input ts-input-number"
            />
            {field.unit && <span className="ts-field-unit">{field.unit}</span>}
          </div>
        );

      case 'slider':
        return (
          <div className="ts-field">
            <label className="ts-field-label">
              {field.label}
              <span className="ts-field-value">
                {value || field.defaultValue} {field.unit}
              </span>
              {field.description && (
                <span className="ts-field-description">{field.description}</span>
              )}
            </label>
            <input
              type="range"
              value={value || field.defaultValue}
              onChange={(e) => onChange(parseInt(e.target.value, 10))}
              disabled={disabled || field.readonly}
              min={field.min}
              max={field.max}
              step={field.step || 1}
              className="ts-slider"
            />
            <div className="ts-slider-labels">
              <span>{field.min} {field.unit}</span>
              <span>{field.max} {field.unit}</span>
            </div>
          </div>
        );

      case 'select':
        return (
          <div className="ts-field">
            <label className="ts-field-label">
              {field.label}
              {field.description && (
                <span className="ts-field-description">{field.description}</span>
              )}
            </label>
            <select
              value={value || field.defaultValue}
              onChange={(e) => onChange(e.target.value)}
              disabled={disabled || field.readonly}
              className="ts-select"
            >
              {field.options?.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        );

      case 'textarea':
        return (
          <div className="ts-field">
            <label className="ts-field-label">
              {field.label}
              {field.description && (
                <span className="ts-field-description">{field.description}</span>
              )}
            </label>
            <textarea
              value={value || ''}
              onChange={(e) => onChange(e.target.value)}
              disabled={disabled || field.readonly}
              className="ts-textarea"
              rows={4}
            />
          </div>
        );

      default:
        return <p>Unsupported field type: {field.type}</p>;
    }
  };

  return <div className="ts-dynamic-field">{renderField()}</div>;
};
