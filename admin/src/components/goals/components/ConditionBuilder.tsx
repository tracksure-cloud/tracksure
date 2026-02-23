/**
 * ConditionBuilder Component
 * 
 * Shared component for building goal conditions with dynamic parameters,
 * operators, and AND/OR logic. Used by both template customization and
 * custom goal builder.
 * 
 * Features:
 * - Add/remove unlimited conditions
 * - Dynamic parameter selection based on trigger type
 * - Multiple operator options (equals, contains, regex, etc.)
 * - AND/OR match logic toggle
 * - Parameter hints and examples
 * - No code duplication - used across all goal creation/editing
 */

import React from 'react';
import { GoalCondition } from '../../../types/goals';
import { __ } from '../../../utils/i18n';

interface ConditionBuilderProps {
  conditions: GoalCondition[];
  onChange: (conditions: GoalCondition[]) => void;
  triggerType: string;
  matchLogic?: 'all' | 'any';
  onMatchLogicChange?: (logic: 'all' | 'any') => void;
}

// Available parameters for each trigger type
const PARAMETER_OPTIONS: Record<string, Array<{ value: string; label: string; example: string; description: string }>> = {
  pageview: [
    { value: 'page_url', label: 'Page URL', example: '/pricing', description: 'Full page URL (e.g., https://site.com/pricing or /pricing)' },
    { value: 'page_path', label: 'Page Path', example: '/products', description: 'URL path only (without domain)' },
    { value: 'page_title', label: 'Page Title', example: 'Pricing', description: 'Browser page title' },
  ],
  form_submit: [
    { value: 'form_id', label: 'Form ID', example: 'gform_3', description: 'HTML form ID attribute (e.g., gform_3, wpforms-form-1245)' },
    { value: 'form_name', label: 'Form Name', example: 'contact', description: 'Form name or type (e.g., contact, inquiry, newsletter)' },
    { value: 'form_builder', label: 'Form Builder', example: 'gravity_forms', description: 'Form builder plugin (gravity_forms, wpforms, contact_form_7, etc.)' },
    { value: 'form_type', label: 'Form Type', example: 'contact', description: 'Form category (contact, newsletter, inquiry, etc.)' },
  ],
  click: [
    { value: 'element_text', label: 'Button/Link Text', example: 'Buy Now', description: 'Text inside the clicked element' },
    { value: 'element_id', label: 'Element ID', example: 'cta-button', description: 'HTML ID attribute of the element' },
    { value: 'element_class', label: 'Element Class', example: 'btn-primary', description: 'CSS class of the element' },
    { value: 'element_type', label: 'Element Type', example: 'tel', description: 'Link type (tel, mailto, etc.)' },
    { value: 'link_url', label: 'Link URL', example: 'https://example.com', description: 'Destination URL for links' },
  ],
  scroll_depth: [
    { value: 'scroll_depth', label: 'Scroll Depth %', example: '75', description: 'Scroll percentage (0-100)' },
    { value: 'page_url', label: 'Page URL', example: '/blog', description: 'Track scroll on specific pages' },
  ],
  time_on_page: [
    { value: 'time_seconds', label: 'Time (seconds)', example: '180', description: 'Time spent on page in seconds' },
    { value: 'page_url', label: 'Page URL', example: '/blog', description: 'Track time on specific pages' },
  ],
  engagement: [
    { value: 'scroll_depth', label: 'Scroll Depth %', example: '50', description: 'Minimum scroll percentage' },
    { value: 'time_seconds', label: 'Time (seconds)', example: '120', description: 'Minimum time on page' },
    { value: 'page_url', label: 'Page URL', example: '/blog', description: 'Track engagement on specific pages' },
  ],
  video_play: [
    { value: 'video_title', label: 'Video Title', example: 'product demo', description: 'Video title or name' },
    { value: 'video_url', label: 'Video URL', example: 'youtube.com/watch?v=', description: 'Video source URL' },
    { value: 'video_type', label: 'Video Type', example: 'youtube', description: 'Video platform (html5, youtube, vimeo)' },
  ],
  download: [
    { value: 'file_name', label: 'File Name', example: 'pricing-guide', description: 'Name of downloaded file' },
    { value: 'file_type', label: 'File Type', example: 'pdf', description: 'File extension (pdf, doc, jpg, etc.)' },
    { value: 'link_url', label: 'Download URL', example: '/downloads/', description: 'Download link URL' },
  ],
  outbound_link: [
    { value: 'link_domain', label: 'Link Domain', example: 'partner.com', description: 'External website domain' },
    { value: 'link_url', label: 'Link URL', example: 'https://partner.com', description: 'Full external URL' },
    { value: 'link_text', label: 'Link Text', example: 'Visit Partner', description: 'Text of the clicked link' },
  ],
  custom_event: [
    { value: 'event_name', label: 'Event Name', example: 'calculator_completed', description: 'Custom event identifier' },
  ],
};

// Operator options
const OPERATOR_OPTIONS = [
  { value: 'equals', label: 'Equals', description: 'Exact match (case-sensitive)' },
  { value: 'contains', label: 'Contains', description: 'Partial match (most flexible)' },
  { value: 'starts_with', label: 'Starts with', description: 'Begins with the value' },
  { value: 'ends_with', label: 'Ends with', description: 'Finishes with the value' },
  { value: 'matches_regex', label: 'Matches regex', description: 'Advanced pattern matching' },
  { value: 'greater_than', label: 'Greater than', description: 'For numbers only' },
  { value: 'less_than', label: 'Less than', description: 'For numbers only' },
];

export const ConditionBuilder: React.FC<ConditionBuilderProps> = ({
  conditions,
  onChange,
  triggerType,
  matchLogic = 'all',
  onMatchLogicChange,
}) => {
  const availableParams = PARAMETER_OPTIONS[triggerType] || [];

  const addCondition = () => {
    onChange([
      ...conditions,
      { param: '', operator: 'contains', value: '' },
    ]);
  };

  const removeCondition = (index: number) => {
    onChange(conditions.filter((_, i) => i !== index));
  };

  const updateCondition = (index: number, field: keyof GoalCondition, value: string | number | boolean) => {
    const updated = [...conditions];
    updated[index] = { ...updated[index], [field]: value };
    onChange(updated);
  };

  const getParameterInfo = (paramValue: string) => {
    return availableParams.find(p => p.value === paramValue);
  };

  return (
    <div className="ts-condition-builder">
      <div className="ts-condition-builder-header">
        <h4 style={{ margin: 0 }}>Conditions</h4>
        {conditions.length === 0 && (
          <p style={{ margin: '8px 0', color: '#666', fontSize: '13px' }}>
            💡 No conditions = Goal will fire for <strong>ALL</strong> {triggerType} events
          </p>
        )}
      </div>

      <div className="ts-conditions-list">
        {conditions.map((condition, index) => {
          const paramInfo = getParameterInfo(condition.param);
          
          return (
            <div key={index} className="ts-condition-row" style={{
              display: 'grid',
              gridTemplateColumns: '1fr 1fr 1.5fr auto',
              gap: '12px',
              alignItems: 'start',
              padding: '16px',
              background: '#f9fafb',
              borderRadius: '8px',
              marginBottom: '12px',
              border: '1px solid #e5e7eb',
            }}>
              {/* Parameter Dropdown */}
              <div>
                <label style={{ display: 'block', marginBottom: '6px', fontSize: '13px', fontWeight: 500 }}>
                  Parameter
                </label>
                <select
                  value={condition.param}
                  onChange={(e) => updateCondition(index, 'param', e.target.value)}
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    borderRadius: '6px',
                    border: '1px solid #d1d5db',
                    fontSize: '14px',
                  }}
                >
                  <option value="">Select parameter...</option>
                  {availableParams.map(param => (
                    <option value={param.value} key={param.value}>
                      {param.label}
                    </option>
                  ))}
                </select>
                {paramInfo && (
                  <p style={{ margin: '4px 0 0', fontSize: '12px', color: '#6b7280' }}>
                    {paramInfo.description}
                  </p>
                )}
              </div>

              {/* Operator Dropdown */}
              <div>
                <label style={{ display: 'block', marginBottom: '6px', fontSize: '13px', fontWeight: 500 }}>
                  Operator
                </label>
                <select
                  value={condition.operator}
                  onChange={(e) => updateCondition(index, 'operator', e.target.value)}
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    borderRadius: '6px',
                    border: '1px solid #d1d5db',
                    fontSize: '14px',
                  }}
                >
                  {OPERATOR_OPTIONS.map(op => (
                    <option value={op.value} key={op.value} title={op.description}>
                      {op.label}
                    </option>
                  ))}
                </select>
              </div>

              {/* Value Input */}
              <div>
                <label style={{ display: 'block', marginBottom: '6px', fontSize: '13px', fontWeight: 500 }}>
                  Value
                </label>
                <input
                  type="text"
                  value={condition.value}
                  onChange={(e) => updateCondition(index, 'value', e.target.value)}
                  placeholder={paramInfo?.example || 'Enter value...'}
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    borderRadius: '6px',
                    border: '1px solid #d1d5db',
                    fontSize: '14px',
                  }}
                />
                {paramInfo && (
                  <p style={{ margin: '4px 0 0', fontSize: '12px', color: '#6b7280' }}>
                    Example: <code style={{ background: '#e5e7eb', padding: '2px 6px', borderRadius: '4px' }}>{paramInfo.example}</code>
                  </p>
                )}
              </div>

              {/* Remove Button */}
              <div style={{ paddingTop: '28px' }}>
                <button
                  onClick={() => removeCondition(index)}
                  style={{
                    padding: '8px 12px',
                    background: '#ef4444',
                    color: 'white',
                    border: 'none',
                    borderRadius: '6px',
                    cursor: 'pointer',
                    fontSize: '14px',
                    fontWeight: 500,
                  }}
                  title="Remove condition"
                >
                  🗑️
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {/* Add Condition Button */}
      <button
        onClick={addCondition}
        style={{
          padding: '10px 16px',
          background: '#3b82f6',
          color: 'white',
          border: 'none',
          borderRadius: '6px',
          cursor: 'pointer',
          fontSize: '14px',
          fontWeight: 500,
          marginBottom: '16px',
        }}
      >
        ➕ Add Condition
      </button>

      {/* Match Logic (AND/OR) */}
      {conditions.length > 1 && onMatchLogicChange && (
        <div style={{
          padding: '16px',
          background: '#eff6ff',
          borderRadius: '8px',
          border: '1px solid #bfdbfe',
        }}>
          <label style={{ display: 'block', marginBottom: '8px', fontSize: '13px', fontWeight: 500 }}>
            Match Logic
          </label>
          <div style={{ display: 'flex', gap: '12px' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>
              <input
                type="radio"
                name="match_logic"
                value="all"
                checked={matchLogic === 'all'}
                onChange={(e) => onMatchLogicChange(e.target.value as 'all' | 'any')}
              />
              <span style={{ fontSize: '14px' }}>
                <strong>ALL</strong> conditions must match (AND)
              </span>
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>
              <input
                type="radio"
                name="match_logic"
                value="any"
                checked={matchLogic === 'any'}
                onChange={(e) => onMatchLogicChange(e.target.value as 'all' | 'any')}
              />
              <span style={{ fontSize: '14px' }}>
                <strong>ANY</strong> condition can match (OR)
              </span>
            </label>
          </div>
          <p style={{ margin: '8px 0 0', fontSize: '12px', color: '#1e40af' }}>
            {matchLogic === 'all' 
              ? '✓ Goal fires only when ALL conditions are satisfied'
              : '✓ Goal fires when ANY ONE condition is satisfied'}
          </p>
        </div>
      )}

      {/* Help Text */}
      {availableParams.length > 0 && (
        <details style={{ marginTop: '16px', fontSize: '13px', color: '#6b7280' }}>
          <summary style={{ cursor: 'pointer', fontWeight: 500 }}>
            💡 Available Parameters for {triggerType}
          </summary>
          <ul style={{ marginTop: '8px', paddingLeft: '20px' }}>
            {availableParams.map(param => (
              <li key={param.value} style={{ marginBottom: '4px' }}>
                <strong>{param.label}:</strong> {param.description}
                <br />
                <code style={{ background: '#e5e7eb', padding: '2px 6px', borderRadius: '4px', fontSize: '12px' }}>
                  Example: {param.example}
                </code>
              </li>
            ))}
          </ul>
        </details>
      )}
    </div>
  );
};
