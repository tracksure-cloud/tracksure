/**
 * Goal Validation Utilities
 * 
 * Client-side validation for goal data before API submission.
 * Matches server-side validation in class-tracksure-goal-validator.php
 * 
 * @since 2.0.0
 */

import { GoalFormData, GoalCondition } from '../types/goals';

/**
 * Supported trigger types.
 */
const TRIGGER_TYPES = [
  'pageview',
  'click',
  'form_submit',
  'scroll_depth',
  'time_on_page',
  'engagement', // Added: engagement trigger (scroll + time)
  'video_play',
  'download',
  'outbound_link',
  'custom_event',
] as const;

/**
 * Supported operators.
 */
const OPERATORS = [
  'equals',
  'not_equals',
  'contains',
  'not_contains',
  'starts_with',
  'ends_with',
  'greater_than',
  'less_than',
  'greater_than_or_equal',
  'less_than_or_equal',
  'matches_regex', // Pro feature
] as const;

/**
 * Supported value types.
 */
const VALUE_TYPES = ['none', 'fixed', 'dynamic'] as const;

/**
 * Supported frequency options.
 */
const FREQUENCIES = ['once', 'session', 'unlimited'] as const;

/**
 * Validation result interface.
 */
export interface ValidationResult {
  valid: boolean;
  errors: string[];
}

/**
 * Validate goal data.
 * 
 * @param goalData - Goal data to validate
 * @returns Validation result with errors array
 */
export function validateGoal(goalData: Partial<GoalFormData>): ValidationResult {
  const errors: string[] = [];

  // ========================================
  // REQUIRED FIELDS
  // ========================================

  if (!goalData.name || goalData.name.trim() === '') {
    errors.push('Goal name is required');
  }

  if (!goalData.event_name || goalData.event_name.trim() === '') {
    errors.push('Event name is required');
  }

  if (!goalData.trigger_type) {
    errors.push('Trigger type is required');
  } else if (!(TRIGGER_TYPES as readonly string[]).includes(goalData.trigger_type)) {
    errors.push(`Invalid trigger type: ${goalData.trigger_type}. Allowed: ${TRIGGER_TYPES.join(', ')}`);
  }

  // ========================================
  // VALIDATE CONDITIONS
  // ========================================

  if (goalData.conditions && Array.isArray(goalData.conditions)) {
    goalData.conditions.forEach((condition: GoalCondition, index: number) => {
      const num = index + 1;

      if (!condition.param || condition.param.trim() === '') {
        errors.push(`Condition #${num}: Parameter (param) is required`);
      }

      if (!condition.operator) {
        errors.push(`Condition #${num}: Operator is required`);
      } else if (!(OPERATORS as readonly string[]).includes(condition.operator)) {
        errors.push(
          `Condition #${num}: Invalid operator '${condition.operator}'. Allowed: ${OPERATORS.join(', ')}`
        );
      }

      if (condition.value === undefined || condition.value === '') {
        errors.push(`Condition #${num}: Value is required`);
      }

      // Validate regex pattern if operator is matches_regex
      if (condition.operator === 'matches_regex') {
        try {
          // eslint-disable-next-line security/detect-non-literal-regexp
          new RegExp(String(condition.value));
        } catch (e) {
          errors.push(`Condition #${num}: Invalid regex pattern: ${condition.value}`);
        }
      }
    });
  }

  // ========================================
  // VALIDATE MATCH LOGIC
  // ========================================

  if (goalData.match_logic && !['all', 'any'].includes(goalData.match_logic)) {
    errors.push(`Match logic must be 'all' or 'any', got: ${goalData.match_logic}`);
  }

  // ========================================
  // VALIDATE VALUE TYPE
  // ========================================

  if (goalData.value_type && !(VALUE_TYPES as readonly string[]).includes(goalData.value_type)) {
    errors.push(`Invalid value_type. Must be: ${VALUE_TYPES.join(', ')}`);
  }

  if (goalData.value_type === 'fixed') {
    if (goalData.value === undefined || goalData.value === null) {
      errors.push('Fixed value is required when value_type is "fixed"');
    } else if (isNaN(Number(goalData.value))) {
      errors.push('Fixed value must be a number');
    }
  }

  // ========================================
  // VALIDATE FREQUENCY
  // ========================================

  if (goalData.frequency && !(FREQUENCIES as readonly string[]).includes(goalData.frequency)) {
    errors.push(`Invalid frequency. Must be: ${FREQUENCIES.join(', ')}`);
  }

  // Note: cooldown_minutes and trigger_config are no longer used
  // The new schema uses attribution_window and conditions instead

  return {
    valid: errors.length === 0,
    errors,
  };
}

/**
 * Validate a single condition.
 * 
 * @param condition - Condition to validate
 * @returns True if valid, error string if invalid
 */
export function validateCondition(condition: Partial<GoalCondition>): string | true {
  if (!condition.param || condition.param.trim() === '') {
    return 'Parameter is required';
  }

  if (!condition.operator) {
    return 'Operator is required';
  }

  if (!(OPERATORS as readonly string[]).includes(condition.operator)) {
    return `Invalid operator: ${condition.operator}`;
  }

  if (condition.value === undefined || condition.value === '') {
    return 'Value is required';
  }

  return true;
}

/**
 * Get user-friendly error message.
 * 
 * @param errors - Array of error messages
 * @returns Formatted error message
 */
export function formatValidationErrors(errors: string[]): string {
  if (errors.length === 0) {
    return '';
  }

  if (errors.length === 1) {
    return errors[0];
  }

  return `Please fix the following errors:\n• ${errors.join('\n• ')}`;
}

/**
 * Sanitize goal data before submission.
 * 
 * @param goalData - Raw goal data
 * @returns Sanitized goal data
 */
export function sanitizeGoalData(goalData: Partial<GoalFormData>): Partial<GoalFormData> {
  const sanitized: Partial<GoalFormData> = {
    ...goalData,
    name: goalData.name?.trim(),
    event_name: goalData.event_name?.trim(),
    trigger_type: goalData.trigger_type,
    match_logic: goalData.match_logic || 'all',
    is_active: goalData.is_active !== false, // Default to true
  };

  // Remove empty/null values
  Object.keys(sanitized).forEach((key) => {
    const value = sanitized[key as keyof GoalFormData];
    if (value === null || value === undefined || value === '') {
      delete sanitized[key as keyof GoalFormData];
    }
  });

  return sanitized;
}

/**
 * Export constants for use in components.
 */
export const VALIDATION_CONSTANTS = {
  TRIGGER_TYPES,
  OPERATORS,
  VALUE_TYPES,
  FREQUENCIES,
} as const;
