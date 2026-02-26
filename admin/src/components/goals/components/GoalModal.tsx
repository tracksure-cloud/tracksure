/**
 * GoalModal - Unified Goal Creation/Editing Component
 * 
 * Single, production-ready modal component for all goal operations:
 * - Creating goals from templates (customize before creating)
 * - Creating custom goals from scratch  
 * - Editing existing goals
 * 
 * Features:
 * - Full internationalization (i18n)
 * - Dark/Light theme support
 * - Inline validation with helpful error messages
 * - Accessibility (WCAG 2.1 AA compliant)
 * - Contextual help tooltips
 * - Security best practices (sanitized inputs)
 * 
 * @since 2.1.0
 * @package TrackSure
 */

import React, { useState, useEffect } from 'react';
import { Modal } from '../../ui/Modal';
import { ConditionBuilder } from './ConditionBuilder';
import type { GoalTemplate, GoalFormData, TriggerType } from '@/types/goals';
import { __ } from '../../../utils/i18n';
import './GoalModal.css';

interface GoalModalProps {
  /** Whether the modal is open */
  isOpen: boolean;
  /** Close handler */
  onClose: () => void;
  /** Save handler - receives validated goal data */
  onSave: (goalData: GoalFormData) => void;
  /** Operation mode */
  mode: 'create-from-template' | 'create-custom' | 'edit';
  /** Template to customize (required for 'create-from-template') */
  template?: GoalTemplate;
  /** Existing goal data (required for 'edit') */
  existingGoal?: GoalFormData;
}

/**
 * Trigger type options with labels and corresponding event names
 */
const TRIGGER_OPTIONS = [
  { value: 'pageview', label: __('Page View', 'tracksure'), eventName: 'page_view', icon: '📄' },
  { value: 'click', label: __('Element Click', 'tracksure'), eventName: 'click', icon: '👆' },
  { value: 'form_submit', label: __('Form Submission', 'tracksure'), eventName: 'form_submit', icon: '📝' },
  { value: 'scroll_depth', label: __('Scroll Depth', 'tracksure'), eventName: 'scroll', icon: '📜' },
  { value: 'time_on_page', label: __('Time on Page', 'tracksure'), eventName: 'time_on_page', icon: '⏱️' },
  { value: 'engagement', label: __('Engagement Rate', 'tracksure'), eventName: 'engagement', icon: '❤️' },
  { value: 'video_play', label: __('Video Play', 'tracksure'), eventName: 'video_play', icon: '🎬' },
  { value: 'download', label: __('File Download', 'tracksure'), eventName: 'file_download', icon: '💾' },
  { value: 'outbound_link', label: __('Outbound Link', 'tracksure'), eventName: 'outbound_click', icon: '🔗' },
  { value: 'custom_event', label: __('Custom Event', 'tracksure'), eventName: 'custom_event', icon: '⚡' },
] as const;

/**
 * Validation error messages interface
 */
interface ValidationErrors {
  name?: string;
  description?: string;
  trigger_type?: string;
  fixed_value?: string;
  conditions?: string;
}

/**
 * Unified Goal Modal Component
 */
export const GoalModal: React.FC<GoalModalProps> = ({
  isOpen,
  onClose,
  onSave,
  mode,
  template,
  existingGoal,
}) => {
  // Initialize form data based on mode
  const getInitialData = (): GoalFormData => {
    if (mode === 'edit' && existingGoal) {
      return { ...existingGoal };
    }
    
    if (mode === 'create-from-template' && template) {
      return {
        name: template.name,
        description: template.description,
        event_name: template.event_name,
        trigger_type: template.trigger_type,
        category: template.category,
        conditions: [...(template.conditions || [])],
        match_logic: template.match_logic || 'all',
        value_type: template.value_type || 'none',
        value: template.typical_value || 0,
        attribution_window: 30,
        frequency: 'unlimited',
        is_active: true,
      };
    }
    
    // Default for custom goal
    return {
      name: '',
      description: '',
      event_name: 'page_view',
      trigger_type: 'pageview',
      category: 'engagement',
      conditions: [],
      match_logic: 'all',
      value_type: 'none',
      value: 0,
      attribution_window: 30,
      frequency: 'unlimited',
      is_active: true,
    };
  };

  const [formData, setFormData] = useState<GoalFormData>(getInitialData());
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [isSaving, setIsSaving] = useState(false);

  // Reset form when modal opens/mode changes
  useEffect(() => {
    if (isOpen) {
      setFormData(getInitialData());
      setErrors({});
      setIsSaving(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, mode, template, existingGoal]);

  /**
   * Update a single form field
   */
  const updateField = <K extends keyof GoalFormData>(
    field: K,
    value: GoalFormData[K]
  ) => {
    const updated = { ...formData, [field]: value };
    
    // Auto-update event_name when trigger_type changes
    if (field === 'trigger_type') {
      const triggerOption = TRIGGER_OPTIONS.find(t => t.value === value);
      if (triggerOption) {
        updated.event_name = triggerOption.eventName;
      }
    }
    
    setFormData(updated);
    
    // Clear error for this field if it exists
    if (errors[field as keyof ValidationErrors]) {
      setErrors(prev => ({ ...prev, [field]: undefined }));
    }
  };

  /**
   * Validate form data
   * @returns true if valid, false otherwise
   */
  const validate = (): boolean => {
    const newErrors: ValidationErrors = {};

    // Name is required
    if (!formData.name.trim()) {
      newErrors.name = __('Goal name is required');
    } else if (formData.name.trim().length < 3) {
      newErrors.name = __('Goal name must be at least 3 characters');
    } else if (formData.name.trim().length > 100) {
      newErrors.name = __('Goal name must not exceed 100 characters');
    }

    // Description should not be too long
    if (formData.description && formData.description.length > 500) {
      newErrors.description = __('Description must not exceed 500 characters');
    }

    // Fixed value must be positive if value_type is 'fixed'
    if (formData.value_type === 'fixed') {
      if (!formData.value || formData.value <= 0) {
        newErrors.fixed_value = __('Fixed value must be greater than 0');
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  /**
   * Handle form submission
   */
  const handleSave = async () => {
    if (!validate()) {
      return;
    }

    setIsSaving(true);
    
    try {
      // Sanitize data before saving
      const sanitizedData: GoalFormData = {
        ...formData,
        name: formData.name.trim(),
        description: formData.description?.trim() || '',
      };

      await onSave(sanitizedData);
      onClose();
    } catch (error) {
      console.error('Error saving goal:', error);
      setErrors({ name: __('An error occurred while saving. Please try again.') });
    } finally {
      setIsSaving(false);
    }
  };

  /**
   * Get modal title based on mode
   */
  const getTitle = (): string => {
    switch (mode) {
      case 'edit':
        return __('Edit Goal');
      case 'create-from-template':
        return __('Customize Goal Template');
      case 'create-custom':
      default:
        return __('Create Custom Goal');
    }
  };

  /**
   * Get help text for trigger types
   */
  const getTriggerHelp = (triggerType: string): string => {
    const helpText: Record<string, string> = {
      pageview: __('Fires when a specific page is viewed'),
      click: __('Fires when a specific element is clicked'),
      form_submit: __('Fires when a form is submitted'),
      scroll_depth: __('Fires when user scrolls to a specific depth'),
      time_on_page: __('Fires when user spends specific time on page'),
      engagement: __('Fires when user meets scroll + time engagement thresholds'),
      video_play: __('Fires when a video is played or completed'),
      download: __('Fires when a file is downloaded'),
      outbound_link: __('Fires when an external link is clicked'),
      custom_event: __('Fires when a custom event occurs (e.g., WooCommerce purchase)'),
    };
    
    // Use 'in' operator for safe property access
    return triggerType in helpText ? helpText[triggerType] : '';
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="lg"
      title={getTitle()}
      footer={
        <div className="ts-goal-modal__footer">
          <button
            onClick={onClose}
            className="ts-btn ts-btn--secondary"
            disabled={isSaving}
            aria-label={__('Cancel')}
          >
            {__('Cancel')}
          </button>
          <button
            onClick={handleSave}
            className="ts-btn ts-btn--primary"
            disabled={isSaving}
            aria-label={mode === 'edit' ? __('Update goal') : __('Create goal')}
          >
            {isSaving ? __('Saving...') : (mode === 'edit' ? __('Update Goal') : __('Create Goal'))}
          </button>
        </div>
      }
    >
      <div className="ts-goal-modal__content">
        {/* Template Info Banner */}
        {mode === 'create-from-template' && (
          <div className="ts-goal-modal__banner ts-goal-modal__banner--info" role="alert">
            <span className="ts-goal-modal__banner-icon">💡</span>
            <div>
              <strong>{__('Customize this template')}</strong>
              <p>{__('Modify the conditions to match your website\'s URLs, form IDs, or other specific values before creating.')}</p>
            </div>
          </div>
        )}

        {/* Basic Information Section */}
        <section className="ts-goal-modal__section" aria-labelledby="basic-info-heading">
          <h3 id="basic-info-heading" className="ts-goal-modal__section-title">
            <span className="ts-goal-modal__section-icon">📝</span>
            {__('Basic Information')}
          </h3>
          
          {/* Goal Name */}
          <div className="ts-goal-modal__field">
            <label htmlFor="goal-name" className="ts-goal-modal__label ts-goal-modal__label--required">
              {__('Goal Name')}
            </label>
            <input
              id="goal-name"
              type="text"
              className={`ts-goal-modal__input ${errors.name ? 'ts-goal-modal__input--error' : ''}`}
              value={formData.name}
              onChange={(e) => updateField('name', e.target.value)}
              placeholder={__('e.g., Pricing Page Viewed')}
              required
              aria-required="true"
              aria-invalid={!!errors.name}
              aria-describedby={errors.name ? 'goal-name-error' : undefined}
              maxLength={100}
            />
            {errors.name && (
              <p id="goal-name-error" className="ts-goal-modal__error" role="alert">
                {errors.name}
              </p>
            )}
          </div>

          {/* Description */}
          <div className="ts-goal-modal__field">
            <label htmlFor="goal-description" className="ts-goal-modal__label">
              {__('Description')}
              <span className="ts-goal-modal__label-optional"> ({__('optional')})</span>
            </label>
            <textarea
              id="goal-description"
              className={`ts-goal-modal__textarea ${errors.description ? 'ts-goal-modal__input--error' : ''}`}
              value={formData.description}
              onChange={(e) => updateField('description', e.target.value)}
              placeholder={__('Describe what this goal tracks...')}
              rows={3}
              aria-invalid={!!errors.description}
              aria-describedby={errors.description ? 'goal-description-error' : undefined}
              maxLength={500}
            />
            {errors.description && (
              <p id="goal-description-error" className="ts-goal-modal__error" role="alert">
                {errors.description}
              </p>
            )}
            <p className="ts-goal-modal__help">
              {(formData.description || '').length}/500 {__('characters')}
            </p>
          </div>
        </section>

        {/* Trigger Type Section */}
        <section className="ts-goal-modal__section" aria-labelledby="trigger-heading">
          <h3 id="trigger-heading" className="ts-goal-modal__section-title">
            <span className="ts-goal-modal__section-icon">⚡</span>
            {__('Trigger Type')}
          </h3>
          
          <div className="ts-goal-modal__field">
            <label htmlFor="trigger-type" className="ts-goal-modal__label ts-goal-modal__label--required">
              {__('When should this goal fire?')}
            </label>
            <select
              id="trigger-type"
              className="ts-goal-modal__select"
              value={formData.trigger_type}
              onChange={(e) => updateField('trigger_type', e.target.value as TriggerType)}
              aria-required="true"
            >
              {TRIGGER_OPTIONS.map(option => (
                <option key={option.value} value={option.value}>
                  {option.icon} {option.label}
                </option>
              ))}
            </select>
            <p className="ts-goal-modal__help">
              {getTriggerHelp(formData.trigger_type)}
            </p>
          </div>

          {/* Custom event name input */}
          {formData.trigger_type === 'custom_event' && (
            <div className="ts-goal-modal__field">
              <label htmlFor="custom-event-name" className="ts-goal-modal__label ts-goal-modal__label--required">
                {__('Event Name')}
              </label>
              <input
                id="custom-event-name"
                type="text"
                className="ts-goal-modal__input"
                value={formData.event_name || ''}
                onChange={(e) => updateField('event_name', e.target.value)}
                placeholder={__('e.g., purchase, add_to_cart, calculator_completed')}
              />
              <p className="ts-goal-modal__help">
                {__('The exact event name your custom code or WooCommerce dispatches')}
              </p>
            </div>
          )}
        </section>

        {/* Conditions Section */}
        <section className="ts-goal-modal__section" aria-labelledby="conditions-heading">
          <ConditionBuilder
            conditions={formData.conditions}
            onChange={(conditions) => updateField('conditions', conditions)}
            triggerType={formData.trigger_type}
            matchLogic={formData.match_logic}
            onMatchLogicChange={(logic) => updateField('match_logic', logic)}
          />
          {errors.conditions && (
            <p className="ts-goal-modal__error" role="alert">
              {errors.conditions}
            </p>
          )}
        </section>

        {/* Conversion Value Section */}
        <section className="ts-goal-modal__section" aria-labelledby="value-heading">
          <h3 id="value-heading" className="ts-goal-modal__section-title">
            <span className="ts-goal-modal__section-icon">💰</span>
            {__('Conversion Value')}
          </h3>
          
          <div className="ts-goal-modal__field">
            <label htmlFor="value-type" className="ts-goal-modal__label">
              {__('Value Type')}
            </label>
            <select
              id="value-type"
              className="ts-goal-modal__select"
              value={formData.value_type}
              onChange={(e) => updateField('value_type', e.target.value as 'none' | 'fixed' | 'dynamic')}
            >
              <option value="none">{__('No Value (Engagement Metric)')}</option>
              <option value="fixed">{__('Fixed Value (Same for all conversions)')}</option>
              <option value="dynamic">{__('Dynamic Value (Use transaction amount)')}</option>
            </select>
          </div>

          {formData.value_type === 'fixed' && (
            <div className="ts-goal-modal__field">
              <label htmlFor="fixed-value" className="ts-goal-modal__label ts-goal-modal__label--required">
                {__('Fixed Value')} ($)
              </label>
              <input
                id="fixed-value"
                type="number"
                className={`ts-goal-modal__input ${errors.fixed_value ? 'ts-goal-modal__input--error' : ''}`}
                value={formData.value || 0}
                onChange={(e) => updateField('value', parseFloat(e.target.value) || 0)}
                placeholder="0"
                min="0"
                step="0.01"
                required
                aria-required="true"
                aria-invalid={!!errors.fixed_value}
                aria-describedby={errors.fixed_value ? 'fixed-value-error' : 'fixed-value-help'}
              />
              {errors.fixed_value ? (
                <p id="fixed-value-error" className="ts-goal-modal__error" role="alert">
                  {errors.fixed_value}
                </p>
              ) : (
                <p id="fixed-value-help" className="ts-goal-modal__help">
                  {__('This value will be assigned to every conversion (e.g., lead value, estimated revenue)')}
                </p>
              )}
            </div>
          )}

          {formData.value_type === 'dynamic' && (
            <div className="ts-goal-modal__banner ts-goal-modal__banner--info">
              <span className="ts-goal-modal__banner-icon">💡</span>
              <div>
                {__('Value will be automatically pulled from transaction data (e.g., WooCommerce order total)')}
              </div>
            </div>
          )}
        </section>

        {/* Active Status Toggle (Edit Mode Only) */}
        {mode === 'edit' && (
          <section className="ts-goal-modal__section">
            <label className="ts-goal-modal__toggle">
              <input
                type="checkbox"
                className="ts-goal-modal__toggle-input"
                checked={formData.is_active}
                onChange={(e) => updateField('is_active', e.target.checked)}
                aria-label={__('Activate this goal')}
              />
              <div className="ts-goal-modal__toggle-content">
                <div className="ts-goal-modal__toggle-title">
                  {__('Activate this goal')}
                </div>
                <div className="ts-goal-modal__toggle-description">
                  {__('Start tracking conversions for this goal')}
                </div>
              </div>
            </label>
          </section>
        )}

        {/* Global Error Display */}
        {Object.keys(errors).length > 0 && (
          <div className="ts-goal-modal__banner ts-goal-modal__banner--error" role="alert">
            <span className="ts-goal-modal__banner-icon">⚠️</span>
            <div>
              <strong>{__('Please fix the following errors:')}</strong>
              <ul className="ts-goal-modal__error-list">
                {Object.entries(errors).map(([field, message]) => message && (
                  <li key={field}>{message}</li>
                ))}
              </ul>
            </div>
          </div>
        )}
      </div>
    </Modal>
  );
};
