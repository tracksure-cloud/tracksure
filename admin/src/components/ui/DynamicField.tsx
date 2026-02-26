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
              value={typeof value === 'boolean' ? '' : (value || '')}
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
              value={typeof value === 'number' ? value : Number(field.defaultValue ?? 0)}
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
              value={typeof value === 'number' ? value : Number(field.defaultValue ?? 0)}
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
              value={String(typeof value !== 'boolean' ? (value || field.defaultValue || '') : (field.defaultValue ?? ''))}
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
              value={typeof value === 'boolean' ? '' : (value || '')}
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
