<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for event mapping diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Event Mapper
 *
 * Maps TrackSure events to destination format using registry.
 * Eliminates duplicate mapping logic across destinations.
 *
 * @package TrackSure\Core
 * @since 1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Mapper Class
 */
class TrackSure_Event_Mapper {



	/**
	 * Instance.
	 *
	 * @var TrackSure_Event_Mapper
	 */
	private static $instance = null;

	/**
	 * Registry instance.
	 *
	 * @var TrackSure_Registry
	 */
	private $registry;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Event_Mapper
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
		$this->registry = TrackSure_Registry::get_instance();
	}

	/**
	 * Map event to destination format.
	 *
	 * Uses registry mappings to automatically transform TrackSure events
	 * into destination-specific format (Meta CAPI, GA4 MP, TikTok, etc).
	 *
	 * @param array  $event TrackSure event (from Event Builder).
	 * @param string $destination Destination ID (meta, ga4, tiktok, pinterest, etc).
	 * @return array|false Destination-formatted event or false if not mapped.
	 */
	public function map_to_destination( $event, $destination ) {
		// Get event schema from registry.
		$event_schema = $this->registry->get_event( $event['event_name'] );

		if ( ! $event_schema ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				error_log( "[TrackSure] Event Mapper: Event '{$event['event_name']}' not found in registry" );
			}
			return false;
		}

		// Check if destination mapping exists for this event.
		if ( ! isset( $event_schema['destination_mappings'][ $destination ] ) ) {
			// Event not supported by this destination (not an error - just skip).
			return false;
		}

		$mapping = $event_schema['destination_mappings'][ $destination ];

		// Convert occurred_at timestamp to Unix timestamp for destinations.
		$event_time = time(); // Default to now
		if ( isset( $event['occurred_at'] ) ) {
			$event_time = is_numeric( $event['occurred_at'] )
				? (int) $event['occurred_at']
				: strtotime( $event['occurred_at'] );
		}

		// Build destination event with standard structure.
		// CRITICAL: Include page_context and session_context for ALL destinations.
		// This ensures GA4, TikTok, Snapchat, Pinterest, Google Ads, etc. can access.
		// page titles, device info, browser data without special handling.
		$destination_event = array(
			'event_name'       => $mapping['event_name'],
			'event_time'       => $event_time, // Unix timestamp for Meta CAPI/TikTok
			'timestamp_micros' => $event_time * 1000000, // Microseconds for GA4 MP
			'event_id'         => $event['event_id'], // For deduplication
			'client_id'        => isset( $event['client_id'] ) ? $event['client_id'] : 'unknown', // For GA4 MP
			'session_id'       => isset( $event['session_id'] ) ? $event['session_id'] : '', // For GA4 MP
			'event_source_url' => isset( $event['page_context']['page_url'] ) ? $event['page_context']['page_url'] : home_url(),
			'user_data'        => isset( $event['user_data'] ) ? $event['user_data'] : array(),
			'custom_data'      => array(),
			'page_context'     => isset( $event['page_context'] ) ? $event['page_context'] : array(), // Page URL, title, path, referrer
			'session_context'  => isset( $event['session_context'] ) ? $event['session_context'] : array(), // Device, browser, OS
		);

		// CRITICAL: Pass GCLID if present (for Google Ads attribution)
		if ( isset( $event['event_params'] ) && is_array( $event['event_params'] ) && isset( $event['event_params']['gclid'] ) ) {
			$destination_event['custom_data']['gclid'] = $event['event_params']['gclid'];
		}

		// Map parameters according to registry.
		if ( isset( $mapping['param_mapping'] ) ) {
			foreach ( $mapping['param_mapping'] as $source_param => $dest_param ) {
				if ( isset( $event['event_params'][ $source_param ] ) ) {
					$destination_event['custom_data'][ $dest_param ] = $event['event_params'][ $source_param ];
				}
			}
		}

		// Apply transforms if specified in registry.
		if ( isset( $mapping['requires_transform'] ) && ! empty( $mapping['requires_transform'] ) ) {
			$destination_event['custom_data'] = $this->apply_transforms(
				$destination_event['custom_data'],
				$mapping['requires_transform'],
				$destination,
				$event
			);
		}

		// CORE RESPONSIBILITY: Map event structure using registry.
		// DESTINATION RESPONSIBILITY: Format user_data (hashing, etc.) in their own send() method.
		//
		// We pass user_data AS-IS. Each destination (Meta, GA4, TikTok, etc.) will:
		// 1. Receive complete user_data with fbp, fbc, email, etc.
		// 2. Apply their own formatting/hashing in their send() method.
		// 3. This keeps CORE destination-agnostic.
		// // Example: Meta Destination will hash email → em, phone → ph.
		// GA4 Destination will add sha256_email_address.
		// Custom destinations can do their own formatting.
		return $destination_event;
	}

	/**
	 * Transform items array for destination.
	 *
	 * Different destinations require different item formats.
	 *
	 * @param array  $items Items array.
	 * @param string $destination Destination ID.
	 * @return array Transformed items.
	 */
	private function transform_items( $items, $destination ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return array();
		}

		$transformed = array();

		foreach ( $items as $item ) {
			switch ( $destination ) {
				case 'meta':
					$transformed[] = array(
						'id'       => isset( $item['item_id'] ) ? $item['item_id'] : '',
						'quantity' => isset( $item['quantity'] ) ? (int) $item['quantity'] : 1,
					);
					break;

				case 'ga4':
					$transformed[] = array(
						'item_id'   => isset( $item['item_id'] ) ? $item['item_id'] : '',
						'item_name' => isset( $item['item_name'] ) ? $item['item_name'] : '',
						'quantity'  => isset( $item['quantity'] ) ? (int) $item['quantity'] : 1,
						'price'     => isset( $item['price'] ) ? (float) $item['price'] : 0,
					);
					break;

				default:
					// Use original format.
					$transformed[] = $item;
					break;
			}
		}

		return $transformed;
	}

	/**
	 * Apply transforms specified in registry.
	 *
	 * @param array  $mapped_data   Data after param mapping.
	 * @param array  $transform_rules Transform rules from registry.
	 * @param string $destination   Destination ID.
	 * @param array  $original_event Original TrackSure event.
	 * @return array Transformed data.
	 */
	private function apply_transforms( $mapped_data, $transform_rules, $destination, $original_event ) {
		if ( empty( $transform_rules ) ) {
			return $mapped_data;
		}

		foreach ( $transform_rules as $param => $transform_type ) {
			// Special transforms (prefixed with _) operate on entire dataset.
			if ( strpos( $param, '_' ) === 0 ) {
				$mapped_data = $this->apply_special_transform( $mapped_data, $transform_type, $destination, $original_event );
				continue;
			}

			// Regular param transforms.
			if ( isset( $mapped_data[ $param ] ) ) {
				$mapped_data[ $param ] = $this->transform_value( $mapped_data[ $param ], $transform_type, $destination );
			}
		}

		return $mapped_data;
	}

	/**
	 * Transform a single value.
	 *
	 * @param mixed  $value          Value to transform.
	 * @param string $transform_type Type of transform.
	 * @param string $destination    Destination ID.
	 * @return mixed Transformed value.
	 */
	private function transform_value( $value, $transform_type, $destination ) {
		switch ( $transform_type ) {
			case 'to_array':
				// Convert single value to array.
				return is_array( $value ) ? $value : array( $value );

			case 'to_id_array':
				// Extract item IDs from items array.
				return $this->extract_item_ids( $value );

			case 'to_float':
				// Convert to float.
				return (float) $value;

			case 'to_int':
				// Convert to integer.
				return (int) $value;

			case 'to_meta_contents_array':
				// Transform items to Meta contents format.
				return $this->transform_to_meta_contents( $value );

			default:
				// Allow extensions to add custom transforms.
				return apply_filters( 'tracksure_transform_value', $value, $transform_type, $destination );
		}
	}

	/**
	 * Apply special transforms (operate on entire dataset).
	 *
	 * @param array  $mapped_data    Mapped data.
	 * @param mixed  $transform_rule Transform rule (true or specific config).
	 * @param string $destination    Destination ID.
	 * @param array  $original_event Original TrackSure event.
	 * @return array Transformed data.
	 */
	private function apply_special_transform( $mapped_data, $transform_rule, $destination, $original_event ) {
		if ( $transform_rule === true || $transform_rule === '_build_items_array' ) {
			// Build GA4-style items array from event params.
			if ( $destination === 'ga4' && isset( $original_event['event_params']['items'] ) ) {
				$mapped_data['items'] = $original_event['event_params']['items'];
			} elseif ( $destination === 'ga4' && isset( $original_event['event_params']['item_id'] ) ) {
				// Build single-item array for GA4.
				$item = array(
					'item_id' => $original_event['event_params']['item_id'],
				);
				if ( isset( $original_event['event_params']['item_name'] ) ) {
					$item['item_name'] = $original_event['event_params']['item_name'];
				}
				if ( isset( $original_event['event_params']['item_category'] ) ) {
					$item['item_category'] = $original_event['event_params']['item_category'];
				}
				if ( isset( $original_event['event_params']['price'] ) ) {
					$item['price'] = (float) $original_event['event_params']['price'];
				}
				if ( isset( $original_event['event_params']['quantity'] ) ) {
					$item['quantity'] = (int) $original_event['event_params']['quantity'];
				}
				$mapped_data['items'] = array( $item );
			}
		}

		// Allow extensions to add custom special transforms.
		return apply_filters( 'tracksure_apply_special_transform', $mapped_data, $transform_rule, $destination, $original_event );
	}

	/**
	 * Extract item IDs from items array.
	 *
	 * @param mixed $items Items array or single ID.
	 * @return array Array of item IDs.
	 */
	private function extract_item_ids( $items ) {
		if ( ! is_array( $items ) ) {
			return array( $items );
		}

		$ids = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['item_id'] ) ) {
				$ids[] = $item['item_id'];
			} elseif ( is_array( $item ) && isset( $item['id'] ) ) {
				$ids[] = $item['id'];
			} else {
				$ids[] = $item;
			}
		}

		return $ids;
	}

	/**
	 * Transform items to Meta contents format.
	 *
	 * @param mixed $items Items array.
	 * @return array Meta contents array.
	 */
	private function transform_to_meta_contents( $items ) {
		if ( ! is_array( $items ) ) {
			return array(
				array(
					'id'       => $items,
					'quantity' => 1,
				),
			);
		}

		$contents = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$content = array(
					'id'       => isset( $item['item_id'] ) ? $item['item_id'] : ( isset( $item['id'] ) ? $item['id'] : '' ),
					'quantity' => isset( $item['quantity'] ) ? (int) $item['quantity'] : 1,
				);
				if ( isset( $item['price'] ) ) {
					$content['item_price'] = (float) $item['price'];
				}
				$contents[] = $content;
			} else {
				$contents[] = array(
					'id'       => $item,
					'quantity' => 1,
				);
			}
		}

		return $contents;
	}

	/**
	 * Map batch of events to destination.
	 *
	 * @param array  $events Array of TrackSure events.
	 * @param string $destination Destination ID.
	 * @return array Array of destination-formatted events.
	 */
	public function map_batch( $events, $destination ) {
		$mapped_events = array();

		foreach ( $events as $event ) {
			$mapped = $this->map_to_destination( $event, $destination );
			if ( $mapped ) {
				$mapped_events[] = $mapped;
			}
		}

		return $mapped_events;
	}

	/**
	 * Check if event is supported by destination.
	 *
	 * @param string $event_name Event name.
	 * @param string $destination Destination ID.
	 * @return bool True if supported.
	 */
	public function is_supported( $event_name, $destination ) {
		$event_schema = $this->registry->get_event( $event_name );

		if ( ! $event_schema ) {
			return false;
		}

		return isset( $event_schema['destination_mappings'][ $destination ] );
	}
}
