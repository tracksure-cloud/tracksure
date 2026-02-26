/**
 * Custom Goal Builder - Step-by-Step Goal Creation Wizard
 * 
 * Advanced multi-step form for creating custom goals from scratch with
 * contextual help, live validation, and preview capabilities.
 * 
 * Features:
 * - Step-by-step wizard (Basic → Trigger → Conditions → Advanced → Preview)
 * - Live validation feedback
 * - Contextual help tooltips
 * - Condition logic preview
 * - Save as draft
 * - Create template from custom goal
 * - Keyboard navigation (WCAG 2.1 AA)
 * - Dark/light theme support
 * - Full internationalization
 * 
 * @since 2.1.0
 * @package TrackSure
 */

import React, { useState, useEffect } from 'react';
import { Modal } from '../../ui/Modal';
import { Card, CardHeader, CardBody } from '../../ui/Card';
import { Icon } from '../../ui/Icon';
import type { IconName } from '../../ui/Icon';
import { Button } from '../../ui/Button';
import { ConditionBuilder } from './ConditionBuilder';
import type { GoalFormData, GoalCategory, TriggerType } from '@/types/goals';
import { __ } from '../../../utils/i18n';
import './CustomGoalBuilder.css';

interface CustomGoalBuilderProps {
  /** Whether the modal is open */
  isOpen: boolean;
  /** Close handler */
  onClose: () => void;
  /** Save handler */
  onSave: (goalData: GoalFormData) => void;
  /** Save as template handler */
  onSaveAsTemplate?: (goalData: GoalFormData) => void;
}

type Step = 'basic' | 'trigger' | 'conditions' | 'advanced' | 'preview';

interface StepConfig {
  id: Step;
  title: string;
  description: string;
  icon: string;
}

const STEPS: StepConfig[] = [
  {
    id: 'basic',
    title: __('Basic Information', 'tracksure'),
    description: __('Name and describe your goal', 'tracksure'),
    icon: 'FileText',
  },
  {
    id: 'trigger',
    title: __('Trigger Configuration', 'tracksure'),
    description: __('Choose what triggers this goal', 'tracksure'),
    icon: 'Zap',
  },
  {
    id: 'conditions',
    title: __('Conditions', 'tracksure'),
    description: __('Define when the goal converts', 'tracksure'),
    icon: 'Filter',
  },
  {
    id: 'advanced',
    title: __('Advanced Settings', 'tracksure'),
    description: __('Value, frequency, and attribution', 'tracksure'),
    icon: 'Settings',
  },
  {
    id: 'preview',
    title: __('Review & Save', 'tracksure'),
    description: __('Preview and confirm your goal', 'tracksure'),
    icon: 'Eye',
  },
];

const TRIGGER_OPTIONS = [
  {
    value: 'pageview',
    label: __('Page View', 'tracksure'),
    description: __('Track visits to specific pages or sections', 'tracksure'),
    icon: 'FileText',
    eventName: 'page_view',
  },
  {
    value: 'click',
    label: __('Element Click', 'tracksure'),
    description: __('Track clicks on buttons, links, or elements', 'tracksure'),
    icon: 'MousePointer',
    eventName: 'click',
  },
  {
    value: 'form_submit',
    label: __('Form Submission', 'tracksure'),
    description: __('Track form completions and leads', 'tracksure'),
    icon: 'Send',
    eventName: 'form_submit',
  },
  {
    value: 'scroll_depth',
    label: __('Scroll Depth', 'tracksure'),
    description: __('Track how far users scroll on a page', 'tracksure'),
    icon: 'ArrowDown',
    eventName: 'scroll',
  },
  {
    value: 'time_on_page',
    label: __('Time on Page', 'tracksure'),
    description: __('Track engagement duration', 'tracksure'),
    icon: 'Clock',
    eventName: 'time_on_page',
  },
  {
    value: 'engagement',
    label: __('Engagement Rate', 'tracksure'),
    description: __('Track combined scroll + time engagement', 'tracksure'),
    icon: 'Heart',
    eventName: 'engagement',
  },
  {
    value: 'video_play',
    label: __('Video Play', 'tracksure'),
    description: __('Track video interactions', 'tracksure'),
    icon: 'Play',
    eventName: 'video_play',
  },
  {
    value: 'download',
    label: __('File Download', 'tracksure'),
    description: __('Track file and document downloads', 'tracksure'),
    icon: 'Download',
    eventName: 'file_download',
  },
  {
    value: 'outbound_link',
    label: __('Outbound Link', 'tracksure'),
    description: __('Track clicks to external websites', 'tracksure'),
    icon: 'ExternalLink',
    eventName: 'outbound_click',
  },
  {
    value: 'custom_event',
    label: __('Custom Event', 'tracksure'),
    description: __('Track custom JavaScript events', 'tracksure'),
    icon: 'Code',
    eventName: 'custom_event',
  },
];

const CATEGORY_OPTIONS = [
  { value: 'engagement', label: __('Engagement', 'tracksure'), icon: 'Heart' },
  { value: 'leads', label: __('Leads & Conversions', 'tracksure'), icon: 'Users' },
  { value: 'ecommerce', label: __('E-commerce', 'tracksure'), icon: 'ShoppingCart' },
  { value: 'content', label: __('Content & Media', 'tracksure'), icon: 'FileText' },
];

export const CustomGoalBuilder: React.FC<CustomGoalBuilderProps> = ({
  isOpen,
  onClose,
  onSave,
  onSaveAsTemplate,
}) => {
  const [currentStep, setCurrentStep] = useState<Step>('basic');
  const [formData, setFormData] = useState<GoalFormData>({
    name: '',
    description: '',
    category: 'engagement',
    trigger_type: 'pageview',
    event_name: 'page_view',
    conditions: [],
    match_logic: 'all',
    value_type: 'none',
    value: 0,
    attribution_window: 30,
    frequency: 'unlimited',
    is_active: true,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isDraft, setIsDraft] = useState(false);

  const currentStepIndex = STEPS.findIndex(s => s.id === currentStep);
  const isFirstStep = currentStepIndex === 0;
  const isLastStep = currentStepIndex === STEPS.length - 1;

  // Validate current step
  const validateStep = (step: Step): boolean => {
    const newErrors: Record<string, string> = {};

    if (step === 'basic') {
      const trimmedName = formData.name?.trim() || '';
      if (!trimmedName) {
        newErrors.name = __('Goal name is required', 'tracksure');
      } else if (trimmedName.length < 3) {
        newErrors.name = __('Goal name must be at least 3 characters', 'tracksure');
      } else if (trimmedName.length > 100) {
        newErrors.name = __('Goal name cannot exceed 100 characters', 'tracksure');
      }

      if (formData.description && formData.description.length > 500) {
        newErrors.description = __('Description cannot exceed 500 characters', 'tracksure');
      }

      if (!formData.category) {
        newErrors.category = __('Please select a category', 'tracksure');
      }
    }

    if (step === 'trigger') {
      if (!formData.trigger_type) {
        newErrors.trigger_type = __('Please select a trigger type', 'tracksure');
      }
      // Validate event_name (especially for custom_event where user types it).
      const eventName = formData.event_name?.trim() || '';
      if (!eventName) {
        newErrors.event_name = __('Event name is required', 'tracksure');
      } else if (eventName.length > 100) {
        newErrors.event_name = __('Event name cannot exceed 100 characters', 'tracksure');
      } else if (!/^[a-z0-9_]+$/.test(eventName)) {
        newErrors.event_name = __('Event name must contain only lowercase letters, numbers, and underscores', 'tracksure');
      }
    }

    if (step === 'conditions') {
      // Conditions are optional — some goals (e.g., ecommerce) work without conditions
    }

    if (step === 'advanced') {
      if (formData.value_type === 'fixed' && (!formData.value || formData.value <= 0)) {
        newErrors.value = __('Fixed value must be greater than 0', 'tracksure');
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  /**
   * Validate all steps before final save.
   * Returns true only if every step passes validation.
   */
  const validateAllSteps = (): boolean => {
    // Validate each step in order; stop on first failure.
    for (const step of STEPS) {
      if (!validateStep(step.id)) {
        setCurrentStep(step.id); // Navigate to the failing step.
        return false;
      }
    }
    return true;
  };

  const handleNext = () => {
    if (validateStep(currentStep)) {
      const nextIndex = currentStepIndex + 1;
      if (nextIndex < STEPS.length) {
        const nextStep = STEPS[nextIndex];
        if (nextStep) {
          setCurrentStep(nextStep.id);
        }
      }
    }
  };

  const handleBack = () => {
    const prevIndex = currentStepIndex - 1;
    if (prevIndex >= 0) {
      const prevStep = STEPS[prevIndex];
      if (prevStep) {
        setCurrentStep(prevStep.id);
        setErrors({});
      }
    }
  };

  const handleSave = () => {
    if (validateAllSteps()) {
      onSave(formData as GoalFormData);
      // Clear draft after successful save.
      localStorage.removeItem('tracksure_goal_draft');
      setIsDraft(false);
    }
  };

  const handleSaveDraft = () => {
    setIsDraft(true);
    localStorage.setItem('tracksure_goal_draft', JSON.stringify(formData));
    // Show notification
  };

  const handleSaveAsTemplate = () => {
    if (onSaveAsTemplate && validateAllSteps()) {
      onSaveAsTemplate(formData);
    }
  };

  // Load draft on mount (only if modal is open).
  useEffect(() => {
    if (!isOpen) return;
    const draft = localStorage.getItem('tracksure_goal_draft');
    if (draft) {
      try {
        const parsed = JSON.parse(draft);
        setFormData(parsed);
        setIsDraft(true);
      } catch (e) {
        console.error('[CustomGoalBuilder] Failed to load draft:', e);
      }
    }
  }, [isOpen]);

  // Update event_name when trigger_type changes — but skip for custom_event
  // (custom_event uses a user-defined event_name, not the trigger default).
  useEffect(() => {
    if (formData.trigger_type === 'custom_event') return;
    const trigger = TRIGGER_OPTIONS.find(t => t.value === formData.trigger_type);
    if (trigger) {
      setFormData(prev => ({ ...prev, event_name: trigger.eventName }));
    }
  }, [formData.trigger_type]);

  const renderStepIndicator = () => (
    <div className="ts-goal-builder-steps">
      {STEPS.map((step, index) => (
        <div
          key={step.id}
          className={`ts-step ${currentStep === step.id ? 'ts-step--active' : ''} ${
            index < currentStepIndex ? 'ts-step--completed' : ''
          }`}
        >
          <div className="ts-step-number">
            {index < currentStepIndex ? (
              <Icon name="Check" size={16} />
            ) : (
              <span>{index + 1}</span>
            )}
          </div>
          <div className="ts-step-info">
            <div className="ts-step-title">{step.title}</div>
            <div className="ts-step-description">{step.description}</div>
          </div>
        </div>
      ))}
    </div>
  );

  const renderBasicStep = () => (
    <div className="ts-step-content">
      <Card>
        <CardHeader>
          <Icon name="FileText" size={20} />
          {__('Basic Information', 'tracksure')}
        </CardHeader>
        <CardBody>
          <div className="ts-form-field">
            <label htmlFor="goal-name">
              {__('Goal Name', 'tracksure')} <span className="ts-required">*</span>
            </label>
            <input
              id="goal-name"
              type="text"
              value={formData.name || ''}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder={__('e.g., Newsletter Signup, Product Purchase', 'tracksure')}
              className={errors.name ? 'ts-input-error' : ''}
              autoFocus
            />
            {errors.name && <span className="ts-error-message">{errors.name}</span>}
            <p className="ts-field-help">
              {__('Choose a clear, descriptive name that identifies this goal', 'tracksure')}
            </p>
          </div>

          <div className="ts-form-field">
            <label htmlFor="goal-description">
              {__('Description', 'tracksure')} <span className="ts-optional">({__('optional', 'tracksure')})</span>
            </label>
            <textarea
              id="goal-description"
              value={formData.description || ''}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder={__('Describe the purpose of this goal and what success looks like', 'tracksure')}
              rows={3}
            />
            <p className="ts-field-help">
              {__('Help team members understand what this goal measures', 'tracksure')}
            </p>
          </div>

          <div className="ts-form-field">
            <label>
              {__('Category', 'tracksure')} <span className="ts-required">*</span>
            </label>
            <div className="ts-category-grid">
              {CATEGORY_OPTIONS.map((cat) => (
                <button
                  key={cat.value}
                  type="button"
                  className={`ts-category-card ${formData.category === cat.value ? 'ts-category-card--selected' : ''}`}
                  onClick={() => setFormData({ ...formData, category: cat.value as GoalCategory })}
                >
                  <Icon name={cat.icon as IconName} size={24} />
                  <span>{cat.label}</span>
                </button>
              ))}
            </div>
            {errors.category && <span className="ts-error-message">{errors.category}</span>}
          </div>
        </CardBody>
      </Card>
    </div>
  );

  const renderTriggerStep = () => (
    <div className="ts-step-content">
      <Card>
        <CardHeader>
          <Icon name="Zap" size={20} />
          {__('Choose Trigger Type', 'tracksure')}
        </CardHeader>
        <CardBody>
          <p className="ts-section-intro">
            {__('Select what user action will trigger this goal conversion', 'tracksure')}
          </p>

          <div className="ts-trigger-grid">
            {TRIGGER_OPTIONS.map((trigger) => (
              <button
                key={trigger.value}
                type="button"
                className={`ts-trigger-card ${formData.trigger_type === trigger.value ? 'ts-trigger-card--selected' : ''}`}
                onClick={() => setFormData({ ...formData, trigger_type: trigger.value as TriggerType })}
              >
                <div className="ts-trigger-icon">
                  <Icon name={trigger.icon as IconName} size={32} />
                </div>
                <div className="ts-trigger-info">
                  <div className="ts-trigger-label">{trigger.label}</div>
                  <div className="ts-trigger-description">{trigger.description}</div>
                </div>
                {formData.trigger_type === trigger.value && (
                  <div className="ts-trigger-check">
                    <Icon name="CheckCircle" size={20} />
                  </div>
                )}
              </button>
            ))}
          </div>
          {errors.trigger_type && <span className="ts-error-message">{errors.trigger_type}</span>}

          {/* Custom event name input */}
          {formData.trigger_type === 'custom_event' && (
            <div className="ts-form-field" style={{ marginTop: '16px' }}>
              <label htmlFor="custom-event-name">
                {__('Event Name', 'tracksure')} <span className="ts-required">*</span>
              </label>
              <input
                id="custom-event-name"
                type="text"
                value={formData.event_name || ''}
                onChange={(e) => setFormData({ ...formData, event_name: e.target.value })}
                placeholder={__('e.g., purchase, add_to_cart, calculator_completed', 'tracksure')}
                maxLength={100}
              />
              {errors.event_name && (
                <p className="ts-field-error">{errors.event_name}</p>
              )}
              <p className="ts-field-help">
                {__('The exact event name your custom code dispatches', 'tracksure')}
              </p>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );

  const renderConditionsStep = () => (
    <div className="ts-step-content">
      <Card>
        <CardHeader>
          <Icon name="Filter" size={20} />
          {__('Define Conditions', 'tracksure')}
        </CardHeader>
        <CardBody>
          <p className="ts-section-intro">
            {__('Add conditions to specify exactly when this goal should convert', 'tracksure')}
          </p>

          <ConditionBuilder
            conditions={formData.conditions || []}
            onChange={(conditions) => setFormData({ ...formData, conditions })}
            triggerType={formData.trigger_type || 'pageview'}
            matchLogic={formData.match_logic}
            onMatchLogicChange={(logic) => setFormData({ ...formData, match_logic: logic })}
          />

          {errors.conditions && <span className="ts-error-message">{errors.conditions}</span>}

          {formData.conditions && formData.conditions.length > 0 && (
            <div className="ts-conditions-preview">
              <h4>
                <Icon name="Eye" size={16} />
                {__('Condition Logic Preview', 'tracksure')}
              </h4>
              <div className="ts-logic-preview">
                {formData.match_logic === 'all' ? (
                  <p>
                    {__('Goal converts when', 'tracksure')} <strong>{__('ALL', 'tracksure')}</strong> {__('of the following are true:', 'tracksure')}
                  </p>
                ) : (
                  <p>
                    {__('Goal converts when', 'tracksure')} <strong>{__('ANY', 'tracksure')}</strong> {__('of the following are true:', 'tracksure')}
                  </p>
                )}
                <ul className="ts-condition-list">
                  {formData.conditions.map((cond, index) => (
                    <li key={index}>
                      <code>{cond.param}</code> <em>{cond.operator}</em> <code>&quot;{cond.value}&quot;</code>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );

  const renderAdvancedStep = () => (
    <div className="ts-step-content">
      <Card>
        <CardHeader>
          <Icon name="Settings" size={20} />
          {__('Advanced Settings', 'tracksure')}
        </CardHeader>
        <CardBody>
          {/* Value Configuration */}
          <div className="ts-form-field">
            <label>{__('Conversion Value', 'tracksure')}</label>
            <div className="ts-radio-group">
              <label className="ts-radio-option">
                <input
                  type="radio"
                  name="value_type"
                  value="none"
                  checked={formData.value_type === 'none'}
                  onChange={(_e) => setFormData({ ...formData, value_type: 'none' })}
                />
                <span>
                  <strong>{__('No Value', 'tracksure')}</strong>
                  <small>{__('Track conversions only (most common)', 'tracksure')}</small>
                </span>
              </label>
              <label className="ts-radio-option">
                <input
                  type="radio"
                  name="value_type"
                  value="fixed"
                  checked={formData.value_type === 'fixed'}
                  onChange={(_e) => setFormData({ ...formData, value_type: 'fixed' })}
                />
                <span>
                  <strong>{__('Fixed Value', 'tracksure')}</strong>
                  <small>{__('Assign a specific value to each conversion', 'tracksure')}</small>
                </span>
              </label>
              <label className="ts-radio-option">
                <input
                  type="radio"
                  name="value_type"
                  value="dynamic"
                  checked={formData.value_type === 'dynamic'}
                  onChange={(_e) => setFormData({ ...formData, value_type: 'dynamic' })}
                />
                <span>
                  <strong>{__('Dynamic Value', 'tracksure')}</strong>
                  <small>{__('Use actual transaction amounts (e.g., order totals)', 'tracksure')}</small>
                </span>
              </label>
            </div>

            {formData.value_type === 'fixed' && (
              <div className="ts-value-input">
                <input
                  type="number"
                  value={formData.value || 0}
                  onChange={(e) => setFormData({ ...formData, value: parseFloat(e.target.value) || 0 })}
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  className={errors.value ? 'ts-input-error' : ''}
                />
                {errors.value && <span className="ts-error-message">{errors.value}</span>}
                <p className="ts-field-help">
                  {__('Enter the monetary value for each conversion (e.g., average lead value)', 'tracksure')}
                </p>
              </div>
            )}
          </div>

          {/* Attribution Window */}
          <div className="ts-form-field">
            <label htmlFor="attribution-window">
              {__('Attribution Window', 'tracksure')}
            </label>
            <select
              id="attribution-window"
              value={formData.attribution_window || 30}
              onChange={(e) => setFormData({ ...formData, attribution_window: parseInt(e.target.value) })}
            >
              <option value="1">{__('1 day', 'tracksure')}</option>
              <option value="7">{__('7 days', 'tracksure')}</option>
              <option value="14">{__('14 days', 'tracksure')}</option>
              <option value="30">{__('30 days (recommended)', 'tracksure')}</option>
              <option value="60">{__('60 days', 'tracksure')}</option>
              <option value="90">{__('90 days', 'tracksure')}</option>
            </select>
            <p className="ts-field-help">
              {__('How long to connect conversions to original traffic sources', 'tracksure')}
            </p>
          </div>

          {/* Conversion Frequency */}
          <div className="ts-form-field">
            <label htmlFor="frequency">
              {__('Conversion Frequency', 'tracksure')}
            </label>
            <select
              id="frequency"
              value={formData.frequency || 'unlimited'}
              onChange={(e) => setFormData({ ...formData, frequency: e.target.value as GoalFormData['frequency'] })}
            >
              <option value="unlimited">{__('Unlimited (track every occurrence)', 'tracksure')}</option>
              <option value="session">{__('Once per session', 'tracksure')}</option>
              <option value="once">{__('Once per visitor (lifetime)', 'tracksure')}</option>
            </select>
            <p className="ts-field-help">
              {__('Control how often the same user can trigger this goal', 'tracksure')}
            </p>
          </div>
        </CardBody>
      </Card>
    </div>
  );

  const renderPreviewStep = () => (
    <div className="ts-step-content">
      <Card>
        <CardHeader>
          <Icon name="Eye" size={20} />
          {__('Review Your Goal', 'tracksure')}
        </CardHeader>
        <CardBody>
          <div className="ts-goal-preview">
            <div className="ts-preview-section">
              <h4>{__('Basic Information', 'tracksure')}</h4>
              <dl>
                <dt>{__('Name', 'tracksure')}</dt>
                <dd>{formData.name}</dd>
                
                {formData.description && (
                  <>
                    <dt>{__('Description', 'tracksure')}</dt>
                    <dd>{formData.description}</dd>
                  </>
                )}
                
                <dt>{__('Category', 'tracksure')}</dt>
                <dd>{CATEGORY_OPTIONS.find(c => c.value === formData.category)?.label}</dd>
              </dl>
            </div>

            <div className="ts-preview-section">
              <h4>{__('Trigger Configuration', 'tracksure')}</h4>
              <dl>
                <dt>{__('Trigger Type', 'tracksure')}</dt>
                <dd>{TRIGGER_OPTIONS.find(t => t.value === formData.trigger_type)?.label}</dd>
                
                <dt>{__('Event Name', 'tracksure')}</dt>
                <dd><code>{formData.event_name}</code></dd>
              </dl>
            </div>

            <div className="ts-preview-section">
              <h4>{__('Conditions', 'tracksure')}</h4>
              <p>
                {formData.match_logic === 'all'
                  ? __('Must match ALL conditions:', 'tracksure')
                  : __('Must match ANY condition:', 'tracksure')}
              </p>
              {formData.conditions && formData.conditions.length > 0 ? (
                <ul className="ts-condition-list">
                  {formData.conditions.map((cond, index) => (
                    <li key={index}>
                      <code>{cond.param}</code> <em>{cond.operator}</em> <code>&quot;{cond.value}&quot;</code>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="ts-no-conditions">{__('No conditions defined', 'tracksure')}</p>
              )}
            </div>

            <div className="ts-preview-section">
              <h4>{__('Advanced Settings', 'tracksure')}</h4>
              <dl>
                <dt>{__('Value Type', 'tracksure')}</dt>
                <dd>
                  {formData.value_type === 'none' && __('No value tracked', 'tracksure')}
                  {formData.value_type === 'fixed' && `${__('Fixed:', 'tracksure')} $${formData.value}`}
                  {formData.value_type === 'dynamic' && __('Dynamic (from transaction)', 'tracksure')}
                </dd>
                
                <dt>{__('Attribution Window', 'tracksure')}</dt>
                <dd>{formData.attribution_window} {__('days', 'tracksure')}</dd>
                
                <dt>{__('Frequency', 'tracksure')}</dt>
                <dd>
                  {formData.frequency === 'unlimited' && __('Unlimited', 'tracksure')}
                  {formData.frequency === 'session' && __('Once per session', 'tracksure')}
                  {formData.frequency === 'once' && __('Once per visitor', 'tracksure')}
                </dd>
              </dl>
            </div>
          </div>
        </CardBody>
      </Card>
    </div>
  );

  const renderStepContent = () => {
    switch (currentStep) {
      case 'basic':
        return renderBasicStep();
      case 'trigger':
        return renderTriggerStep();
      case 'conditions':
        return renderConditionsStep();
      case 'advanced':
        return renderAdvancedStep();
      case 'preview':
        return renderPreviewStep();
      default:
        return null;
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="xl"
      title={__('Create Custom Goal', 'tracksure')}
    >
      <div className="ts-custom-goal-builder">
        {renderStepIndicator()}
        
        <div className="ts-builder-content">
          {isDraft && (
            <div className="ts-draft-notice">
              <Icon name="Info" size={16} />
              {__('Draft loaded from previous session', 'tracksure')}
            </div>
          )}
          
          {renderStepContent()}
        </div>

        <div className="ts-builder-actions">
          <div className="ts-actions-left">
            {!isFirstStep && (
              <Button variant="outline" onClick={handleBack}>
                <Icon name="ChevronLeft" size={16} />
                {__('Back', 'tracksure')}
              </Button>
            )}
          </div>

          <div className="ts-actions-right">
            <Button variant="ghost" onClick={handleSaveDraft}>
              <Icon name="Save" size={16} />
              {__('Save Draft', 'tracksure')}
            </Button>

            {!isLastStep ? (
              <Button variant="primary" onClick={handleNext}>
                {__('Next', 'tracksure')}
                <Icon name="ChevronRight" size={16} />
              </Button>
            ) : (
              <>
                {onSaveAsTemplate && (
                  <Button variant="outline" onClick={handleSaveAsTemplate}>
                    <Icon name="Bookmark" size={16} />
                    {__('Save as Template', 'tracksure')}
                  </Button>
                )}
                <Button variant="primary" onClick={handleSave}>
                  <Icon name="Check" size={16} />
                  {__('Create Goal', 'tracksure')}
                </Button>
              </>
            )}
          </div>
        </div>
      </div>
    </Modal>
  );
};
