<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging and error handler for goal validation diagnostics

/**
 *
 * TrackSure Goal Validator
 *
 * Production-ready validation service for goal data with comprehensive security,
 * type safety, i18n support, and structured error responses.
 *
 * Features:
 * - Type-safe validation with strict type hints
 * - Translatable error messages for i18n
 * - Structured validation results
 * - Sanitization and escaping
 * - Extensible via WordPress filters
 * - SQL injection prevention
 * - XSS protection
 *
 * @package TrackSure\Core\Services
 * @since 2.0.0
 * @version 2.1.0 - Enhanced security, i18n, and type safety
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Goal Validator class.
 *
 * Validates and sanitizes goal data before database operations.
 *
 * @since 2.0.0
 */
class TrackSure_Goal_Validator
{


	/**
	 * Supported trigger types.
	 *
	 * @since 2.0.0
	 * @var string[]
	 */
	private $trigger_types = array(
		'pageview',
		'click',
		'form_submit',
		'scroll_depth',
		'time_on_page',
		'engagement',
		'video_play',
		'download',
		'outbound_link',
		'custom_event',
	);

	/**
	 * Supported operators.
	 *
	 * @since 2.0.0
	 * @var string[]
	 */
	private $operators = array(
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
		'matches_regex',
	);

	/**
	 * Validate goal data.
	 *
	 * Performs comprehensive validation of goal data structure,
	 * trigger types, conditions, and configuration.
	 *
	 * @since 2.0.0
	 * @since 2.1.0 Added i18n support
	 *
	 * @param array $goal_data Goal data to validate.
	 * @return string[] Array of translatable error messages (empty if valid).
	 */
	public function validate(array $goal_data): array
	{
		$errors = array();

		// ========================================.
		// REQUIRED FIELDS.
		// ========================================.
		if (empty($goal_data['name']) || trim($goal_data['name']) === '') {
			$errors[] = __('Goal name is required.', 'tracksure');
		} elseif (strlen($goal_data['name']) > 255) {
			$errors[] = __('Goal name cannot exceed 255 characters.', 'tracksure');
		}

		if (empty($goal_data['event_name'])) {
			$errors[] = __('Event name is required.', 'tracksure');
		} elseif (strlen($goal_data['event_name']) > 100) {
			$errors[] = __('Event name cannot exceed 100 characters.', 'tracksure');
		} elseif (! preg_match('/^[a-z0-9_]+$/', $goal_data['event_name'])) {
			$errors[] = __('Event name must contain only lowercase letters, numbers, and underscores.', 'tracksure');
		}

		if (empty($goal_data['trigger_type'])) {
			$errors[] = __('Trigger type is required.', 'tracksure');
		} else {
			// Validate trigger_type is valid.
			$trigger_type = sanitize_text_field($goal_data['trigger_type']);

			/**
			 * Filter allowed trigger types.
			 *
			 * Allows Pro/3rd party extensions to register custom trigger types.
			 *
			 * @since 2.0.0
			 *
			 * @param string[] $trigger_types Array of allowed trigger type strings.
			 */
			$allowed_triggers = apply_filters('tracksure_allowed_trigger_types', $this->trigger_types);

			if (! in_array($trigger_type, $allowed_triggers, true)) {
				$errors[] = sprintf(
					/* translators: 1: Invalid trigger type, 2: List of allowed trigger types */
					__('Invalid trigger type: %1$s. Allowed types: %2$s', 'tracksure'),
					esc_html($trigger_type),
					esc_html(implode(', ', $allowed_triggers))
				);
			}
		}

		// ========================================.
		// VALIDATE CONDITIONS.
		// ========================================.
		if (isset($goal_data['conditions']) && ! empty($goal_data['conditions'])) {
			if (! is_array($goal_data['conditions'])) {
				// Try to decode if string.
				$conditions = json_decode($goal_data['conditions'], true);
				if (! is_array($conditions) || json_last_error() !== JSON_ERROR_NONE) {
					$errors[] = __('Conditions must be a valid JSON array.', 'tracksure');
					return $errors; // Can't proceed with invalid conditions
				}
				$goal_data['conditions'] = $conditions;
			}

			// Validate maximum conditions (prevent DoS).
			if (count($goal_data['conditions']) > 20) {
				$errors[] = __('Maximum 20 conditions allowed per goal.', 'tracksure');
			}

			foreach ($goal_data['conditions'] as $index => $condition) {
				$condition_num = $index + 1;

				// Validate structure.
				if (! is_array($condition)) {
					$errors[] = sprintf(
						/* translators: %d: Condition number */
						__('Condition #%d: Must be an array.', 'tracksure'),
						$condition_num
					);
					continue;
				}

				// Validate param.
				if (empty($condition['param'])) {
					$errors[] = sprintf(
						/* translators: %d: Condition number */
						__('Condition #%d: Parameter is required.', 'tracksure'),
						$condition_num
					);
				} elseif (strlen($condition['param']) > 100) {
					$errors[] = sprintf(
						/* translators: %d: Condition number */
						__('Condition #%d: Parameter cannot exceed 100 characters.', 'tracksure'),
						$condition_num
					);
				}

				// Validate operator.
				if (empty($condition['operator'])) {
					$errors[] = sprintf(
						/* translators: %d: Condition number */
						__('Condition #%d: Operator is required.', 'tracksure'),
						$condition_num
					);
				} else {
					$operator = sanitize_text_field($condition['operator']);

					/**
					 * Filter allowed operators.
					 *
					 * Allows Pro/3rd party extensions to register custom operators.
					 *
					 * @since 2.0.0
					 *
					 * @param string[] $operators Array of allowed operator strings.
					 */
					$allowed_operators = apply_filters('tracksure_allowed_operators', $this->operators);

					if (! in_array($operator, $allowed_operators, true)) {
						$errors[] = sprintf(
							/* translators: 1: Condition number, 2: Invalid operator, 3: List of allowed operators */
							__('Condition #%1$d: Invalid operator "%2$s". Allowed: %3$s', 'tracksure'),
							$condition_num,
							esc_html($operator),
							esc_html(implode(', ', $allowed_operators))
						);
					}
				}

				// Validate value.
				if (! isset($condition['value'])) {
					$errors[] = sprintf(
						/* translators: %d: Condition number */
						__('Condition #%d: Value is required.', 'tracksure'),
						$condition_num
					);
				} elseif (is_string($condition['value']) && strlen($condition['value']) > 1000) {
					$errors[] = sprintf(
						/* translators: %d: Condition number */
						__('Condition #%d: Value cannot exceed 1000 characters.', 'tracksure'),
						$condition_num
					);
				}

				// Validate regex if operator is matches_regex.
				if (isset($condition['operator']) && $condition['operator'] === 'matches_regex') {
					if (empty($condition['value'])) {
						$errors[] = sprintf(
							/* translators: %d: Condition number */
							__('Condition #%d: Regular expression pattern is required.', 'tracksure'),
							$condition_num
						);
					} else {
						// Test regex validity.
						set_error_handler(function () {}); // Suppress warnings
						$is_valid = @preg_match($condition['value'], '') !== false;
						restore_error_handler();

						if (! $is_valid) {
							$errors[] = sprintf(
								/* translators: %d: Condition number */
								__('Condition #%d: Invalid regular expression pattern.', 'tracksure'),
								$condition_num
							);
						}
					}
				}
			}
		}

		// ========================================.
		// VALIDATE MATCH LOGIC.
		// ========================================.
		if (isset($goal_data['match_logic']) && ! empty($goal_data['match_logic'])) {
			$match_logic = sanitize_text_field($goal_data['match_logic']);

			if (! in_array($match_logic, array('all', 'any'), true)) {
				$errors[] = sprintf(
					/* translators: %s: Invalid match logic value */
					__('Match logic must be "all" or "any", received: %s', 'tracksure'),
					esc_html($match_logic)
				);
			}
		}

		// ========================================.
		// VALIDATE TRIGGER CONFIG.
		// ========================================.
		if (isset($goal_data['trigger_config']) && ! empty($goal_data['trigger_config'])) {
			if (is_string($goal_data['trigger_config'])) {
				$trigger_config = json_decode($goal_data['trigger_config'], true);
				if (! is_array($trigger_config) || json_last_error() !== JSON_ERROR_NONE) {
					$errors[] = __('Trigger configuration must be a valid JSON object.', 'tracksure');
				}
			} elseif (! is_array($goal_data['trigger_config'])) {
				$errors[] = __('Trigger configuration must be an object.', 'tracksure');
			}
		}

		// ========================================.
		// VALIDATE VALUE TYPE.
		// ========================================.
		if (isset($goal_data['value_type']) && ! empty($goal_data['value_type'])) {
			$value_type = sanitize_text_field($goal_data['value_type']);

			if (! in_array($value_type, array('none', 'fixed', 'dynamic'), true)) {
				$errors[] = __('Invalid value type. Must be: none, fixed, or dynamic.', 'tracksure');
			}

			// If fixed value, require fixed_value field.
			if ($value_type === 'fixed') {
				if (! isset($goal_data['fixed_value']) || $goal_data['fixed_value'] === '') {
					$errors[] = __('Fixed value is required when value type is "fixed".', 'tracksure');
				} elseif (! is_numeric($goal_data['fixed_value'])) {
					$errors[] = __('Fixed value must be a number.', 'tracksure');
				} elseif ((float) $goal_data['fixed_value'] < 0) {
					$errors[] = __('Fixed value cannot be negative.', 'tracksure');
				} elseif ((float) $goal_data['fixed_value'] > 999999999.99) {
					$errors[] = __('Fixed value cannot exceed 999,999,999.99.', 'tracksure');
				}
			}
		}

		// ========================================.
		// VALIDATE FREQUENCY.
		// ========================================.
		if (isset($goal_data['frequency']) && ! empty($goal_data['frequency'])) {
			$frequency = sanitize_text_field($goal_data['frequency']);

			if (! in_array($frequency, array('once', 'session', 'unlimited'), true)) {
				$errors[] = __('Invalid frequency. Must be: once, session, or unlimited.', 'tracksure');
			}
		}

		// ========================================.
		// VALIDATE COOLDOWN.
		// ========================================.
		if (isset($goal_data['cooldown_minutes']) && $goal_data['cooldown_minutes'] !== '') {
			if (! is_numeric($goal_data['cooldown_minutes'])) {
				$errors[] = __('Cooldown minutes must be a number.', 'tracksure');
			} elseif ((int) $goal_data['cooldown_minutes'] < 0) {
				$errors[] = __('Cooldown minutes cannot be negative.', 'tracksure');
			} elseif ((int) $goal_data['cooldown_minutes'] > 525600) { // 1 year
				$errors[] = __('Cooldown cannot exceed 525,600 minutes (1 year).', 'tracksure');
			}
		}

		// ========================================.
		// ALLOW PRO/3RD PARTY VALIDATION.
		// ========================================.
		/**
		 * Filter goal validation errors.
		 *
		 * Allows Pro/3rd party extensions to add custom validation.
		 *
		 * @since 2.0.0
		 *
		 * @param string[] $custom_errors Array of translatable error messages.
		 * @param array    $goal_data     Goal data being validated.
		 */
		$custom_errors = apply_filters('tracksure_goal_validation_errors', array(), $goal_data);
		if (! empty($custom_errors) && is_array($custom_errors)) {
			$errors = array_merge($errors, $custom_errors);
		}

		return $errors;
	}

	/**
	 * Validate and prepare goal data for database insert/update.
	 *
	 * Returns structured result with validation status, errors, and sanitized data.
	 *
	 * @since 2.0.0
	 * @since 2.1.0 Enhanced sanitization and type safety
	 *
	 * @param array $goal_data Raw goal data from request.
	 * @return array {
	 *     Structured validation result.
	 *
	 *     @type bool        $valid  Whether validation passed.
	 *     @type string[]    $errors Array of translatable error messages.
	 *     @type array|null  $data   Sanitized data ready for database (null if invalid).
	 * }
	 */
	public function validate_and_prepare(array $goal_data): array
	{
		$errors = $this->validate($goal_data);

		if (! empty($errors)) {
			return array(
				'valid'  => false,
				'errors' => $errors,
				'data'   => null,
			);
		}

		// Sanitize and prepare data.
		$prepared = array(
			'name'         => sanitize_text_field(trim($goal_data['name'])),
			'description'  => isset($goal_data['description']) ? sanitize_textarea_field(trim($goal_data['description'])) : '',
			'event_name'   => sanitize_text_field(strtolower(trim($goal_data['event_name']))),
			'trigger_type' => sanitize_text_field($goal_data['trigger_type']),
			'is_active'    => isset($goal_data['is_active']) ? (bool) $goal_data['is_active'] : true,
		);

		// Conditions (already validated, encode as JSON).
		if (isset($goal_data['conditions']) && is_array($goal_data['conditions'])) {
			$prepared['conditions'] = wp_json_encode($goal_data['conditions']);
		} elseif (isset($goal_data['conditions']) && is_string($goal_data['conditions'])) {
			$prepared['conditions'] = $goal_data['conditions'];
		}

		// Match logic.
		if (isset($goal_data['match_logic'])) {
			$prepared['match_logic'] = sanitize_text_field($goal_data['match_logic']);
		}

		// Trigger config (encode as JSON).
		if (isset($goal_data['trigger_config']) && ! empty($goal_data['trigger_config'])) {
			$prepared['trigger_config'] = is_string($goal_data['trigger_config'])
				? $goal_data['trigger_config']
				: wp_json_encode($goal_data['trigger_config']);
		}

		// Value type.
		if (isset($goal_data['value_type'])) {
			$prepared['value_type'] = sanitize_text_field($goal_data['value_type']);
		}

		// Fixed value (ensure 2 decimal places).
		if (isset($goal_data['fixed_value']) && $goal_data['fixed_value'] !== '') {
			$prepared['fixed_value'] = round((float) $goal_data['fixed_value'], 2);
		}

		// Frequency
		if (isset($goal_data['frequency'])) {
			$prepared['frequency'] = sanitize_text_field($goal_data['frequency']);
		}

		// Cooldown
		if (isset($goal_data['cooldown_minutes']) && $goal_data['cooldown_minutes'] !== '') {
			$prepared['cooldown_minutes'] = absint($goal_data['cooldown_minutes']);
		}

		return array(
			'valid'  => true,
			'errors' => array(),
			'data'   => $prepared,
		);
	}
}
