<?php

/**
 *
 * TrackSure Admin Extensions
 *
 * Allows Free/Pro/3rd party to register settings, destinations, integrations, pages, and widgets.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin extensions class.
 */
class TrackSure_Admin_Extensions {


	/**
	 * Instance.
	 *
	 * @var TrackSure_Admin_Extensions
	 */
	private static $instance = null;

	/**
	 * Registered extensions.
	 *
	 * @var array
	 */
	private $extensions = array();

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Admin_Extensions
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// No hooks needed - extensions are triggered and output by Admin UI class.
	}

	/**
	 * Register an extension.
	 *
	 * @param array $extension Extension config.
	 */
	public function register_extension( $extension ) {
		if ( empty( $extension['id'] ) ) {
			return;
		}

		$this->extensions[ $extension['id'] ] = $extension;
	}

	/**
	 * Get all registered extensions.
	 *
	 * @return array
	 */
	public function get_extensions() {
		$extensions = array_values( $this->extensions );

		// Enrich field keys with schema data for React.
		foreach ( $extensions as &$extension ) {
			// Handle settings groups (array of setting sections).
			if ( ! empty( $extension['settings'] ) && is_array( $extension['settings'] ) ) {
				foreach ( $extension['settings'] as &$setting_group ) {
					if ( ! empty( $setting_group['fields'] ) && is_array( $setting_group['fields'] ) ) {
						$setting_group['fields'] = $this->enrich_fields( $setting_group['fields'] );
					}
				}
			}

			// Handle destinations (array of destination configs).
			if ( ! empty( $extension['destinations'] ) && is_array( $extension['destinations'] ) ) {
				foreach ( $extension['destinations'] as &$destination ) {
					// Convert PHP snake_case to JavaScript camelCase.
					if ( isset( $destination['enabled_key'] ) ) {
						$destination['enabledKey'] = $destination['enabled_key'];
						unset( $destination['enabled_key'] );
					}
					// Enrich fields.
					if ( ! empty( $destination['fields'] ) && is_array( $destination['fields'] ) ) {
						$destination['fields'] = $this->enrich_fields( $destination['fields'] );
					}
				}
			}

			// Handle integrations (array of integration configs).
			if ( ! empty( $extension['integrations'] ) && is_array( $extension['integrations'] ) ) {
				foreach ( $extension['integrations'] as &$integration ) {
					// Convert PHP snake_case to JavaScript camelCase.
					if ( isset( $integration['enabled_key'] ) ) {
						$integration['enabledKey'] = $integration['enabled_key'];
						unset( $integration['enabled_key'] );
					}
					// Enrich fields.
					if ( ! empty( $integration['fields'] ) && is_array( $integration['fields'] ) ) {
						$integration['fields'] = $this->enrich_fields( $integration['fields'] );
					}
				}
			}
		}

		return $extensions;
	}

	/**
	 * Enrich field keys with schema data.
	 *
	 * Converts field key strings to full field objects by reading from centralized schema.
	 * This ensures React receives complete field metadata (type, label, description, etc.).
	 *
	 * @param array $field_keys Array of field key strings.
	 * @return array Array of enriched field objects.
	 */
	private function enrich_fields( $field_keys ) {
		$schema          = TrackSure_Settings_Schema::get_all_settings();
		$enriched_fields = array();

		foreach ( $field_keys as $field_key ) {
			// Skip if not a string key (already an object).
			if ( ! is_string( $field_key ) ) {
				$enriched_fields[] = $field_key;
				continue;
			}

			// Skip if key doesn't exist in schema.
			if ( ! isset( $schema[ $field_key ] ) ) {
				continue;
			}

			$field_schema = $schema[ $field_key ];

			// Map PHP types to React input types.
			$field_type = $this->map_field_type( $field_schema['type'], $field_schema );

			// Build enriched field object.
			$enriched_field = array(
				'id'           => $field_key,
				'type'         => $field_type,
				'label'        => $field_schema['label'] ?? '',
				'description'  => $field_schema['description'] ?? '',
				'defaultValue' => $field_schema['default'] ?? null,
			);

			// Add conditional properties based on field type and schema.
			if ( $field_type === 'select' && isset( $field_schema['options'] ) ) {
				// Convert options object to array format React expects.
				// From: array('key' => 'Label').
				// To: array(array('value' => 'key', 'label' => 'Label')).
				$options_array = array();
				foreach ( $field_schema['options'] as $value => $label ) {
					$options_array[] = array(
						'value' => $value,
						'label' => $label,
					);
				}
				$enriched_field['options'] = $options_array;
			}

			if ( $field_type === 'number' ) {
				if ( isset( $field_schema['min'] ) ) {
					$enriched_field['min'] = $field_schema['min'];
				}
				if ( isset( $field_schema['max'] ) ) {
					$enriched_field['max'] = $field_schema['max'];
				}
				if ( isset( $field_schema['step'] ) ) {
					$enriched_field['step'] = $field_schema['step'];
				}
				if ( isset( $field_schema['unit'] ) ) {
					$enriched_field['unit'] = $field_schema['unit'];
				}
			}

			if ( isset( $field_schema['placeholder'] ) ) {
				$enriched_field['placeholder'] = $field_schema['placeholder'];
			}

			if ( isset( $field_schema['required_if'] ) ) {
				$enriched_field['required_if'] = $field_schema['required_if'];
			}

			if ( isset( $field_schema['sensitive'] ) && $field_schema['sensitive'] ) {
				$enriched_field['sensitive'] = true;
			}

			if ( isset( $field_schema['readonly'] ) && $field_schema['readonly'] ) {
				$enriched_field['readonly'] = true;
			}

			if ( isset( $field_schema['group'] ) ) {
				$enriched_field['group'] = $field_schema['group'];
			}

			$enriched_fields[] = $enriched_field;
		}

		return $enriched_fields;
	}

	/**
	 * Map schema types to React input types.
	 *
	 * @param string $schema_type Schema type (boolean, integer, string, etc.).
	 * @param array  $field_schema Full field schema with additional metadata.
	 * @return string React input type (toggle, number, text, password, etc.).
	 */
	private function map_field_type( $schema_type, $field_schema = array() ) {
		// Check for options first (select/dropdown).
		if ( isset( $field_schema['options'] ) && is_array( $field_schema['options'] ) ) {
			return 'select';
		}

		// Check for sensitive fields (password input).
		// Don't mask readonly tokens - users need to copy them.
		if ( isset( $field_schema['sensitive'] ) && $field_schema['sensitive'] ) {
			if ( isset( $field_schema['readonly'] ) && $field_schema['readonly'] ) {
				return 'text';
			}
			return 'password';
		}

		// Map by schema type.
		$type_map = array(
			'boolean' => 'toggle',
			'integer' => 'number',
			'string'  => 'text',
			'array'   => 'text', // Fallback for arrays without options
		);

		return $type_map[ $schema_type ] ?? 'text';
	}
}
