<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for Meta CAPI diagnostics, only fires when WP_DEBUG=true
// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Meta Pixel loaded from Facebook CDN, version managed by Facebook

/**
 *
 * Meta (Facebook & Instagram) Destination
 *
 * Simple API wrapper for Meta Conversions API.
 * Event mapping handled by Event Mapper (registry-based).
 * Browser pixel handled by Event Bridge.
 *
 * @package TrackSure
 * @since 1.2.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Meta Destination Class
 */
class TrackSure_Meta_Destination
{




	/**
	 * Core instance.
	 *
	 * @var TrackSure_Core
	 */
	private $core;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Meta API version.
	 *
	 * Meta maintains API versions for 2+ years.
	 * Current: v20.0 (stable, battle-tested)
	 * Latest: v22.0 (too new, use v20.0 for stability)
	 * Previous: v18.0 (deprecated Jan 2027)
	 *
	 * @see https://developers.facebook.com/docs/graph-api/changelog
	 * @var string
	 */
	private $meta_api_version = 'v20.0';

	/**
	 * Constructor.
	 *
	 * @param TrackSure_Core $core Core instance.
	 */
	public function __construct($core)
	{
		$this->core     = $core;
		$this->settings = $this->get_settings();

		// Only initialize if enabled and configured.
		if ($this->is_enabled()) {
			$this->init_hooks();
		}
	}

	/**
	 * Get destination settings.
	 *
	 * @return array Settings.
	 */
	private function get_settings()
	{
		return array(
			'enabled'         => get_option('tracksure_free_meta_enabled', false),
			'pixel_id'        => get_option('tracksure_free_meta_pixel_id', ''),
			'access_token'    => get_option('tracksure_free_meta_access_token', ''),
			'test_event_code' => get_option('tracksure_free_meta_test_event_code', ''),
		);
	}

	/**
	 * Check if destination is enabled.
	 *
	 * @return bool True if enabled and configured.
	 */
	private function is_enabled()
	{
		return ! empty($this->settings['enabled']) &&
			! empty($this->settings['pixel_id']);
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks()
	{
		// Register with Event Bridge (browser pixel).
		$this->register_browser_destination();

		// Register with Delivery Worker (server-side CAPI).
		add_filter('tracksure_deliver_mapped_event', array($this, 'send'), 10, 3);
	}

	/**
	 * Register browser-side pixel with Event Bridge.
	 */
	private function register_browser_destination()
	{
		$bridge = $this->core->get_service('event_bridge');

		if (! $bridge) {
			return;
		}

		$bridge->register_browser_destination(
			array(
				'id'           => 'meta',
				'enabled_key'  => 'tracksure_free_meta_enabled',
				'init_script'  => array($this, 'get_pixel_init_script'),
				'event_mapper' => array($this, 'get_browser_event_mapper'),
				'sdk_check'    => "function() { return typeof window.fbq === 'function'; }",
				'pixel_sender' => "function(mapped, trackSureEvent) {
					if (!mapped || !mapped.name) return;
					var method = mapped.isCustomEvent ? 'trackCustom' : 'track';
					window.fbq(method, mapped.name, mapped.params || {}, { eventID: trackSureEvent.event_id });
				}",
			)
		);
	}

	/**
	 * Get Meta Pixel initialization JavaScript.
	 *
	 * @return string JavaScript code.
	 */
	public function get_pixel_init_script()
	{
		$pixel_id = sanitize_text_field($this->settings['pixel_id']);

		// Get user data for Advanced Matching.
		$user_data      = $this->get_advanced_matching_data();
		$user_data_json = ! empty($user_data) ? wp_json_encode($user_data, JSON_HEX_TAG | JSON_HEX_AMP) : '{}';

		// Enqueue Meta Pixel base library using WordPress enqueue system.
		wp_enqueue_script(
			'tracksure-meta-pixel',
			'https://connect.facebook.net/en_US/fbevents.js',
			array(),
			null,
			true
		);
		wp_script_add_data('tracksure-meta-pixel', 'async', true);

		// Build inline script for Meta Pixel initialization.
		$inline_script  = "!function(f,b,e,v,n,t,s){\n";
		$inline_script .= "  if(f.fbq)return;n=f.fbq=function(){n.callMethod?\n";
		$inline_script .= "  n.callMethod.apply(n,arguments):n.queue.push(arguments)};\n";
		$inline_script .= "  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';\n";
		$inline_script .= "  n.queue=[];t=b.createElement(e);t.async=!0;\n";
		$inline_script .= "  t.src=v;s=b.getElementsByTagName(e)[0];\n";
		$inline_script .= "  s.parentNode.insertBefore(t,s)\n";
		$inline_script .= "}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');\n";
		$inline_script .= "fbq('init', '" . esc_js($pixel_id) . "', {$user_data_json});\n";
		$inline_script .= "fbq('track', 'PageView');";

		// Add inline script using WordPress enqueue system.
		wp_add_inline_script('tracksure-meta-pixel', $inline_script, 'before');

		// Add noscript fallback.
		add_action(
			'wp_footer',
			function () use ($pixel_id) {
				echo '<noscript><img height="1" width="1" style="display:none" src="' . esc_url('https://www.facebook.com/tr?id=' . $pixel_id . '&ev=PageView&noscript=1') . '" /></noscript>';
			},
			99
		);

		// Return empty string since we're now using enqueue instead of echo.
		return '';
	}

	/**
	 * Get Advanced Matching data for Meta Pixel.
	 *
	 * @return array User data for Advanced Matching.
	 */
	private function get_advanced_matching_data()
	{
		$data = array();

		// Logged-in user.
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			if ($user->user_email) {
				$data['em'] = $user->user_email;
			}
			if ($user->first_name) {
				$data['fn'] = strtolower($user->first_name);
			}
			if ($user->last_name) {
				$data['ln'] = strtolower($user->last_name);
			}
		}

		// WooCommerce customer data (billing info from session/order).
		if (function_exists('WC') && WC()->customer) {
			$customer = WC()->customer;

			if (empty($data['em']) && $customer->get_billing_email()) {
				$data['em'] = $customer->get_billing_email();
			}
			if (empty($data['fn']) && $customer->get_billing_first_name()) {
				$data['fn'] = strtolower($customer->get_billing_first_name());
			}
			if (empty($data['ln']) && $customer->get_billing_last_name()) {
				$data['ln'] = strtolower($customer->get_billing_last_name());
			}
			if ($customer->get_billing_phone()) {
				$data['ph'] = preg_replace('/[^0-9]/', '', $customer->get_billing_phone());
			}
			if ($customer->get_billing_city()) {
				$data['ct'] = strtolower($customer->get_billing_city());
			}
			if ($customer->get_billing_state()) {
				$data['st'] = strtolower($customer->get_billing_state());
			}
			if ($customer->get_billing_postcode()) {
				$data['zp'] = strtolower($customer->get_billing_postcode());
			}
			if ($customer->get_billing_country()) {
				$data['country'] = strtolower($customer->get_billing_country());
			}
		}

		// External ID (user ID or session ID for guests).
		if (is_user_logged_in()) {
			$data['external_id'] = (string) get_current_user_id();
		}

		return $data;
	}

	/**
	 * Get JavaScript event mapper function.
	 *
	 * Maps TrackSure events to Meta Pixel format.
	 * 
	 * CRITICAL: Meta custom events (prefixed with ts_) use trackCustom() instead of track().
	 * Standard events use track(), custom events use trackCustom().
	 *
	 * @return string JavaScript function definition.
	 */
	public function get_browser_event_mapper()
	{
		return "function(trackSureEvent) {
            // Meta Standard Events (9 total - ALL others must use trackCustom)
            var standardEventMap = {
                'page_view': 'PageView',
                'add_to_cart': 'AddToCart',
                'begin_checkout': 'InitiateCheckout',
                'purchase': 'Purchase',
                'view_item': 'ViewContent',
                'add_payment_info': 'AddPaymentInfo',
                'form_submit': 'Lead',
                'search': 'Search',
                'add_to_wishlist': 'AddToWishlist'
            };
            
            // Meta Custom Events (require ts_ prefix and trackCustom)
            // ONLY events with explicit Meta mappings in registry
            var customEventMap = {
                'view_cart': true,
                'login': true,
                'account_page_view': true
            };
            
            // Check if this is a standard Meta event
            var metaEventName = standardEventMap[trackSureEvent.event_name];
            var isCustomEvent = false;
            
            if (metaEventName) {
                // Standard event - use Meta's native name
                isCustomEvent = false;
            } else if (customEventMap[trackSureEvent.event_name]) {
                // Custom event with explicit Meta mapping
                metaEventName = 'ts_' + trackSureEvent.event_name;
                isCustomEvent = true;
            } else {
                // Event NOT mapped to Meta - return null to skip
                console.log('[TrackSure Meta] Event \"' + trackSureEvent.event_name + '\" not mapped to Meta, skipping');
                return null;
            }
            
            var metaParams = {};

            var params = trackSureEvent.event_params || {};
            
            // Value (use value if available, otherwise use price).
            if (params.value) metaParams.value = parseFloat(params.value);
            else if (params.price) metaParams.value = parseFloat(params.price);
            
			// Currency (use centralized TrackSureCurrency handler for consistent normalization).
			if (params.currency) {
				var cur = params.currency;
				
				// Use centralized currency handler if available.
				if (typeof window.TrackSureCurrency !== 'undefined' && window.TrackSureCurrency.normalize) {
					cur = window.TrackSureCurrency.normalize(cur, 'meta');
				}
				
				// Fallback to WooCommerce currency if available and normalization failed.
				if (typeof window !== 'undefined' && (!cur || cur.length !== 3 || /[^A-Z]/.test(cur))) {
					try {
						var wcSettings = (window.wcSettings) || (window.wp && window.wp.data && window.wp.data.select && window.wp.data.select('wc/store') && window.wp.data.select('wc/store').getSettings());
						var storeCurrency = wcSettings && wcSettings.currency && wcSettings.currency.code ? String(wcSettings.currency.code).toUpperCase() : null;
						if (storeCurrency) {
							cur = storeCurrency;
						}
					} catch (e) {}
				}
				
				metaParams.currency = cur || 'USD';
			}
			
			// Content IDs and contents array (critical for ecommerce events).
			if (params.items && Array.isArray(params.items) && params.items.length > 0) {
				// Build content_ids array.
				metaParams.content_ids = params.items.map(function(item) {
					return String(item.item_id || item.id || '');
				}).filter(function(id) { return id !== ''; });
                
                // Build contents array with id, quantity, item_price.
                metaParams.contents = params.items.map(function(item) {
                    return {
                        id: String(item.item_id || item.id || ''),
                        quantity: parseInt(item.quantity) || 1,
                        item_price: parseFloat(item.price || item.item_price || 0)
                    };
                });
                
                // Calculate total num_items (sum of all quantities).
                metaParams.num_items = params.items.reduce(function(total, item) {
                    return total + (parseInt(item.quantity) || 1);
                }, 0);
            } else if (params.item_id) {
                // Single item.
                metaParams.content_ids = [String(params.item_id)];
                metaParams.contents = [{
                    id: String(params.item_id),
                    quantity: parseInt(params.quantity) || 1,
                    item_price: parseFloat(params.price) || 0
                }];
                metaParams.num_items = parseInt(params.quantity) || 1;
            }
            
            // Content name.
            if (params.item_name) metaParams.content_name = params.item_name;
            
            // Transaction ID (for purchase).
            if (params.transaction_id) metaParams.transaction_id = params.transaction_id;
            
            // Quantity (for add_to_cart single item).
            if (params.quantity) metaParams.quantity = parseInt(params.quantity);
            
            // Content type.
            if (params.item_category) metaParams.content_type = params.item_category;
            else metaParams.content_type = 'product';
            
            // CRITICAL: Include event_id for deduplication between browser pixel and CAPI.
            // Meta uses event_id + event_name to deduplicate events within 48 hours.
            var eventId = trackSureEvent.event_id || null;
            
            return { 
                name: metaEventName, 
                params: metaParams,
                eventId: eventId,  // Facebook Pixel expects 'eventId' (camelCase)
                isCustomEvent: isCustomEvent  // Used by Event Bridge to call trackCustom() vs track()
            };
        }";
	}

	/**
	 * Send event to Meta Conversions API.
	 *
	 * Receives PRE-MAPPED event from Event Mapper.
	 * This is a simple API wrapper - NO mapping logic!
	 *
	 * @param array  $result Default result.
	 * @param string $destination Destination ID.
	 * @param array  $mapped_event Event already mapped by Event Mapper.
	 * @return array Result with success (bool) and error (string).
	 */
	public function send($result, $destination, $mapped_event)
	{
		if ($destination !== 'meta') {
			return $result;
		}

		// Skip if CAPI not configured.
		if (empty($this->settings['access_token'])) {
			return array(
				'success' => true, // Not an error - just not configured
				'error'   => 'Meta CAPI access token not configured',
			);
		}

		// Enrich user_data with fbp/fbc cookies and hash PII.
		$user_data = $this->enrich_user_data($mapped_event['user_data']);

		// Build custom_data with proper contents array for Meta.
		$custom_data = $this->build_custom_data($mapped_event['custom_data'], $mapped_event['event_name']);

		// Build CAPI payload.
		$payload = array(
			'data' => array(
				array(
					'event_name'       => $mapped_event['event_name'],
					'event_time'       => $mapped_event['event_time'],
					'event_id'         => $mapped_event['event_id'],
					'event_source_url' => isset($mapped_event['event_source_url']) ? $mapped_event['event_source_url'] : home_url(),
					'action_source'    => 'website',
					'user_data'        => $user_data,
					'custom_data'      => $custom_data,
				),
			),
		);

		// Add Data Processing Options (Limited Data Use) per Meta documentation.
		// https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/server-event#data-processing-options.
		// Applies to US states: CA, CO, CT, DE, FL, MT, NE, NH, NJ, OR, TX, MN, MD, RI.
		$consent_manager = $this->core->get_service('consent_manager');
		$consent_granted = $consent_manager ? $consent_manager->is_tracking_allowed() : true;

		if (! $consent_granted) {
			// Consent denied - enable Limited Data Use with geolocation.
			// Meta will automatically determine if user is in an applicable US state.
			// Country: 0 = Meta determines location, State: 0 = Meta determines location.
			$payload['data'][0]['data_processing_options']         = array('LDU');
			$payload['data'][0]['data_processing_options_country'] = 0;
			$payload['data'][0]['data_processing_options_state']   = 0;
		} else {
			// Consent granted - disable Limited Data Use (normal tracking).
			$payload['data'][0]['data_processing_options']         = array();
			$payload['data'][0]['data_processing_options_country'] = 0;
			$payload['data'][0]['data_processing_options_state']   = 0;
		}

		// Add test event code if configured.
		if (! empty($this->settings['test_event_code'])) {
			$payload['test_event_code'] = $this->settings['test_event_code'];
		}

		// Send to Meta CAPI.
		$response = $this->send_capi_request($payload);

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (isset($data['error'])) {
			return array(
				'success' => false,
				'error'   => $data['error']['message'] ?? 'Unknown Meta CAPI error',
			);
		}

		return array('success' => true);
	}

	/**
	 * Enrich and format user data for Meta CAPI.
	 *
	 * RESPONSIBILITY: Meta Destination handles ALL Meta-specific formatting
	 * - Hashes PII fields (email, phone, name, location) to SHA256
	 * - Adds fbp/fbc cookies if missing
	 * - Adds IP and user agent if missing
	 * - Filters out invalid fields (Meta CAPI only accepts specific fields)
	 *
	 * @param array $user_data Raw user data from Event Mapper (unhashed).
	 * @return array Meta CAPI formatted user data.
	 */
	private function enrich_user_data($user_data)
	{
		// BUILD Meta-compliant user_data object from scratch.
		// ONLY include fields Meta Conversions API accepts.
		$enriched = array();

		// COPY existing technical fields if already present.
		// These are passed through unchanged.
		if (! empty($user_data['fbp'])) {
			$enriched['fbp'] = $user_data['fbp'];
		}
		if (! empty($user_data['fbc'])) {
			$enriched['fbc'] = $user_data['fbc'];
		}
		if (! empty($user_data['client_ip_address'])) {
			$enriched['client_ip_address'] = $user_data['client_ip_address'];
		}
		if (! empty($user_data['client_user_agent'])) {
			$enriched['client_user_agent'] = $user_data['client_user_agent'];
		}
		if (! empty($user_data['external_id'])) {
			$enriched['external_id'] = (string) $user_data['external_id'];
		}

		// HASH PII FIELDS for Meta CAPI (SHA-256 lowercase).
		// Meta requires hashed email, phone, name, location.
		$hash_fields = array(
			'email'      => 'em',
			'phone'      => 'ph',
			'first_name' => 'fn',
			'last_name'  => 'ln',
			'city'       => 'ct',
			'state'      => 'st',
			'zip'        => 'zp',
			'country'    => 'country',
		);

		foreach ($hash_fields as $raw_field => $meta_field) {
			if (! empty($user_data[$raw_field])) {
				$value = trim($user_data[$raw_field]);

				// For email, phone, names, cities: lowercase before hashing.
				if (in_array($raw_field, array('email', 'first_name', 'last_name', 'city', 'state', 'country'))) {
					$value = strtolower($value);
				}

				// For phone: remove non-digits before hashing.
				if ($raw_field === 'phone') {
					$value = preg_replace('/[^0-9]/', '', $value);
				}

				// Hash and store in Meta format.
				$enriched[$meta_field] = hash('sha256', $value);
			}
		}

		// ADD MISSING TECHNICAL FIELDS (don't replace if already present).
		// Add fbp cookie if not already present (browser SDK should provide this).
		if (empty($enriched['fbp']) && isset($_COOKIE['_fbp'])) {
			$enriched['fbp'] = sanitize_text_field(wp_unslash($_COOKIE['_fbp']));
		}

		// Add fbc cookie if not already present (browser SDK should provide this).
		if (empty($enriched['fbc']) && isset($_COOKIE['_fbc'])) {
			$enriched['fbc'] = sanitize_text_field(wp_unslash($_COOKIE['_fbc']));
		}

		// Add client IP if not present.
		if (empty($enriched['client_ip_address'])) {
			$enriched['client_ip_address'] = TrackSure_Utilities::get_client_ip();
		}

		// Add user agent ONLY if not already present (browser SDK provides real UA).
		// CRITICAL: Don't replace browser's real user agent with server's PHP user agent.
		if (empty($enriched['client_user_agent'])) {
			// Fallback to server user agent only if browser didn't provide one.
			$enriched['client_user_agent'] = isset($_SERVER['HTTP_USER_AGENT'])
				? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
				: 'WordPress/' . get_bloginfo('version') . '; ' . home_url();
		}

		// Add external_id if not present and user is logged in.
		if (empty($enriched['external_id']) && is_user_logged_in()) {
			$enriched['external_id'] = (string) get_current_user_id();
		}

		return $enriched;
	}

	/**
	 * Build custom_data with proper contents array for Meta.
	 *
	 * @param array  $custom_data Custom data from mapped event.
	 * @param string $event_name Event name.
	 * @return array Custom data.
	 */
	private function build_custom_data($custom_data, $event_name)
	{
		$data = array();

		// Only include non-empty, valid parameters to avoid "Invalid parameter" errors.
		$allowed_params = array(
			'value',
			'currency',
			'content_name',
			'content_category',
			'content_ids',
			'content_type',
			'contents',
			'num_items',
			'predicted_ltv',
			'status',
			'search_string',
		);

		foreach ($allowed_params as $param) {
			if (isset($custom_data[$param]) && $custom_data[$param] !== '' && $custom_data[$param] !== null) {
				$data[$param] = $custom_data[$param];
			}
		}

		// For ecommerce events, ensure proper contents array structure.
		if (in_array($event_name, array('Purchase', 'AddToCart', 'InitiateCheckout', 'ViewContent'))) {
			// Build contents array from content_ids if present.
			if (! empty($data['content_ids']) && is_array($data['content_ids'])) {
				$contents       = array();
				$per_item_value = 0;

				// Calculate per-item value if total value is provided.
				if (! empty($data['value']) && count($data['content_ids']) > 0) {
					$per_item_value = (float) $data['value'] / count($data['content_ids']);
				}

				foreach ($data['content_ids'] as $index => $item_id) {
					$contents[] = array(
						'id'         => (string) $item_id,
						'quantity'   => isset($data['num_items']) ? (int) $data['num_items'] : 1,
						'item_price' => $per_item_value > 0 ? $per_item_value : 0,
					);
				}

				$data['contents'] = $contents;
			}

			// Calculate num_items if not provided (required for Meta Event Match Quality).
			// num_items should be total quantity across all products.
			if (! isset($data['num_items'])) {
				// Try to calculate from contents array first.
				if (! empty($data['contents']) && is_array($data['contents'])) {
					$total_quantity = 0;
					foreach ($data['contents'] as $content) {
						$total_quantity += isset($content['quantity']) ? (int) $content['quantity'] : 1;
					}
					$data['num_items'] = $total_quantity;
				} elseif (! empty($data['content_ids']) && is_array($data['content_ids'])) {
					// Fallback: use count of content_ids (assumes 1 qty each).
					$data['num_items'] = count($data['content_ids']);
				} else {
					// Last resort: default to 1.
					$data['num_items'] = 1;
				}
			}

			// Ensure content_type is set.
			if (empty($data['content_type'])) {
				$data['content_type'] = 'product';
			}

			// Ensure currency is set (Meta requirement for ecommerce events).
			// Use centralized currency handler for consistent normalization.
			if (empty($data['currency'])) {
				$currency_handler = TrackSure_Currency_Handler::get_instance();
				$data['currency'] = $currency_handler->normalize('USD', 'meta');
			} else {
				// Normalize existing currency using centralized handler.
				$currency_handler = TrackSure_Currency_Handler::get_instance();
				$data['currency'] = $currency_handler->normalize($data['currency'], 'meta');
			}

			// Convert value to float.
			if (isset($data['value'])) {
				$data['value'] = (float) $data['value'];
			}

			// Ensure num_items is integer.
			if (isset($data['num_items'])) {
				$data['num_items'] = (int) $data['num_items'];
			}
		}

		return $data;
	}

	/**

	 * Send request to Meta Conversions API.
	 *
	 * @param array $payload Payload.
	 * @return array|WP_Error Response.
	 */
	private function send_capi_request($payload)
	{
		$pixel_id     = sanitize_text_field($this->settings['pixel_id']);
		$access_token = sanitize_text_field($this->settings['access_token']);

		$url = add_query_arg('access_token', $access_token, "https://graph.facebook.com/{$this->meta_api_version}/{$pixel_id}/events");

		return wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array('Content-Type' => 'application/json'),
				'body'    => wp_json_encode($payload),
				'timeout' => 10,
			)
		);
	}
}
