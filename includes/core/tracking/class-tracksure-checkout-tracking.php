<?php
/**
 * Checkout event tracking handler.
 *
 * @package TrackSure
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging for checkout tracking diagnostics

/**
 *
 * TrackSure Checkout Tracking - Hidden Field Injection
 *
 * Injects tracking fields into checkout forms for server-side event deduplication.
 * Works with WooCommerce (blocks & shortcode), FluentCart, EDD, SureCart, etc.
 *
 * @package TrackSure
 * @subpackage Core\Tracking
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles checkout tracking for WooCommerce and other e-commerce platforms.
 *
 * @since 2.0.0
 */
class TrackSure_Checkout_Tracking {





	/**
	 * Initialize checkout tracking
	 */
	public function __construct() {
		// Inject fields on checkout pages.
		add_action( 'wp_footer', array( $this, 'inject_checkout_tracking_fields' ), 1 );

		// Extract tracking data from POST on order creation.
		add_filter( 'tracksure_event_data', array( $this, 'extract_tracking_from_post' ), 10, 2 );
	}

	/**
	 * Check if current page is a checkout page (any e-commerce solution)
	 *
	 * @return bool
	 */
	private function is_checkout_page() {
		// WooCommerce.
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		// FluentCart (fluent-cart, fluent-checkout).
		if ( class_exists( 'FluentCart\App\App' ) ) {
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'fluent_cart' ) ) {
				return true;
			}
		}

		// Easy Digital Downloads.
		if ( function_exists( 'edd_is_checkout' ) && edd_is_checkout() ) {
			return true;
		}

		// SureCart
		if ( function_exists( 'surecart' ) && is_page() ) {
			global $post;
			if ( $post && (
			has_block( 'surecart/checkout', $post ) ||
			strpos( $post->post_content, 'surecart' ) !== false
			) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Inject hidden tracking fields into checkout forms
	 */
	public function inject_checkout_tracking_fields() {
		// Only on checkout pages.
		if ( ! $this->is_checkout_page() ) {
			return;
		}

		// Don't inject if tracking disabled.
		if ( ! get_option( 'tracksure_tracking_enabled', true ) ) {
			return;
		}

		// Generate nonce for secure tracking.
		$nonce = wp_create_nonce( 'tracksure_checkout_tracking' );

		// Build the entire JavaScript as a PHP string.
		$checkout_script = "(function() {
  'use strict';

  /**
   * Generate UUID v4 (RFC 4122 compliant)
   */
  function generateUUID() {
    if (typeof window.TrackSure !== 'undefined' && window.TrackSure.generateUUID) {
      return window.TrackSure.generateUUID();
    }
    // Fallback: RFC 4122 compliant UUID v4
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      var r = (Math.random() * 16) | 0;
      var v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  /**
   * Billing field selectors for all supported ecommerce platforms.
   * Maps TrackSure field names to form selectors (checked in order).
   * These capture guest user data for EMQ and cross-device matching.
   */
  var billingFieldMap = {
    _ts_b_email: [
      '#billing_email',                 // WooCommerce Classic
      'input[name=\"billing_email\"]',  // WooCommerce
      'input[id*=\"email\"][type=\"email\"]', // WooCommerce Blocks / SureCart / FluentCart
      'input[name=\"email\"]',          // EDD / Generic
      'input[name*=\"email\"][type=\"email\"]' // Fallback
    ],
    _ts_b_phone: [
      '#billing_phone',                 // WooCommerce Classic
      'input[name=\"billing_phone\"]',  // WooCommerce
      'input[id*=\"phone\"]',           // WooCommerce Blocks / SureCart
      'input[name=\"phone\"]',          // EDD / Generic
      'input[name*=\"phone\"]'          // Fallback
    ],
    _ts_b_fname: [
      '#billing_first_name',            // WooCommerce Classic
      'input[name=\"billing_first_name\"]', // WooCommerce
      'input[id*=\"first-name\"]',      // WooCommerce Blocks
      'input[name=\"first_name\"]',     // EDD / Generic
      'input[name=\"edd_first\"]'       // EDD specific
    ],
    _ts_b_lname: [
      '#billing_last_name',             // WooCommerce Classic
      'input[name=\"billing_last_name\"]', // WooCommerce
      'input[id*=\"last-name\"]',       // WooCommerce Blocks
      'input[name=\"last_name\"]',      // EDD / Generic
      'input[name=\"edd_last\"]'        // EDD specific
    ],
    _ts_b_city: [
      '#billing_city',                  // WooCommerce Classic
      'input[name=\"billing_city\"]',   // WooCommerce
      'input[id*=\"city\"]'             // WooCommerce Blocks
    ],
    _ts_b_state: [
      '#billing_state',                 // WooCommerce Classic
      'select[name=\"billing_state\"]', // WooCommerce dropdown
      'input[name=\"billing_state\"]'   // WooCommerce text
    ],
    _ts_b_zip: [
      '#billing_postcode',              // WooCommerce Classic
      'input[name=\"billing_postcode\"]', // WooCommerce
      'input[id*=\"postcode\"]',        // WooCommerce Blocks
      'input[name=\"card_zip\"]',       // EDD
      'input[name*=\"postcode\"]',      // Fallback
      'input[name*=\"zip\"]'            // Fallback
    ],
    _ts_b_country: [
      '#billing_country',               // WooCommerce Classic
      'select[name=\"billing_country\"]', // WooCommerce dropdown
      'input[id*=\"country\"]'          // WooCommerce Blocks
    ]
  };

  /**
   * Capture billing field values from the checkout form.
   * Searches through selectors for each billing field and injects as hidden inputs.
   */
  function captureBillingFields(form) {
    Object.keys(billingFieldMap).forEach(function(fieldName) {
      var selectors = billingFieldMap[fieldName];
      var value = '';

      for (var i = 0; i < selectors.length; i++) {
        try {
          // Search within the form first, then in the document (for split layouts).
          var el = form.querySelector(selectors[i]) || document.querySelector(selectors[i]);
          if (el && el.value && el.value.trim() !== '') {
            value = el.value.trim();
            break;
          }
        } catch (e) {} // Silently catch invalid selectors
      }

      if (value) {
        var existing = form.querySelector('input[name=\"' + fieldName + '\"]');
        if (existing) {
          existing.value = value;
        } else {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = fieldName;
          input.value = value;
          input.className = 'tracksure-tracking-field';
          form.appendChild(input);
        }
      }
    });
  }

  /**
   * Inject or update hidden fields in a form
   */
  function injectTrackingFields(form) {
    // Get current tracking IDs from TrackSure SDK.
    var clientId = '';
    var sessionId = '';
    var eventId = '';

    if (typeof window.TrackSure !== 'undefined') {
      clientId = window.TrackSure.getClientId ? window.TrackSure.getClientId() : '';
      sessionId = window.TrackSure.getSessionId ? window.TrackSure.getSessionId() : '';
    }

    // Always generate new event ID for each form interaction
    eventId = generateUUID();

    // Create or update hidden fields.
    var fields = [
      {
        name: '_ts_eid',
        value: eventId
      },
      {
        name: '_ts_cid',
        value: clientId
      },
      {
        name: '_ts_sid',
        value: sessionId
      },
      {
        name: '_ts_nonce',
        value: '" . esc_js( $nonce ) . "'
      }
    ];

    // Inject or update each field
    fields.forEach(function(field) {
      var existingField = form.querySelector('input[name=\"' + field.name + '\"]');
      if (existingField) {
        // Update existing field value (important for AJAX checkouts)
        existingField.value = field.value;
      } else {
        // Create new field
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = field.name;
        input.value = field.value;
        input.className = 'tracksure-tracking-field';
        form.appendChild(input);
      }
    });
  }

  /**
   * Monitor and process checkout forms
   */
  function monitorCheckoutForms() {
    // Comprehensive list of checkout form selectors (all major ecommerce platforms)
    var selectors = [
      // WooCommerce (most popular)
      'form.checkout',
      'form.woocommerce-checkout',
      '.wp-block-woocommerce-checkout form',
      'form#order_review',

      // Easy Digital Downloads
      'form#edd_purchase_form',
      'form.edd_form',
      'form#edd_checkout_cart_form',

      // SureCart
      'form.surecart-form',
      'form[class*=\"surecart\"]',

      // FluentCart
      'form[class*=\"fluent\"]',
      'form.fluent-checkout-form',

      // WP Simple Pay
      'form.simpay-checkout-form',

      // MemberPress
      'form.mepr-checkout-form',
      'form#mepr_checkout_form',

      // Paid Memberships Pro
      'form.pmpro_form',
      'form#pmpro_form',

      // Restrict Content Pro
      'form#rcp_registration_form',

      // WooCommerce Subscriptions
      'form.woocommerce-form-register',

      // Gravity Forms (with payment)
      'form.gform_wrapper[action*=\"checkout\"]',

      // Formidable Forms (with payment)
      'form.frm-show-form[action*=\"checkout\"]',

      // Generic fallbacks
      'form[name=\"checkout\"]',
      'form[id*=\"checkout\"]',
      'form[class*=\"checkout\"]',
      'form[action*=\"checkout\"]',
      'form[action*=\"order\"]'
    ];

    // Process existing forms immediately
    selectors.forEach(function(selector) {
      try {
        var forms = document.querySelectorAll(selector);
        forms.forEach(function(form) {
          injectTrackingFields(form);

          // Re-inject on form submit to ensure fresh event_id + capture billing data.
          form.addEventListener('submit', function() {
            injectTrackingFields(form);
            captureBillingFields(form);
          }, false);
        });
      } catch (e) {
        // Silently catch selector errors for better compatibility
      }
    });

    // Monitor for dynamically added forms (React/Vue checkouts, AJAX updates)
    if (typeof MutationObserver !== 'undefined') {
      var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.addedNodes && mutation.addedNodes.length) {
            mutation.addedNodes.forEach(function(node) {
              if (node.nodeType === 1) { // Element node
                // Check if the node itself is a form
                if (node.tagName === 'FORM') {
                  selectors.forEach(function(selector) {
                    try {
                      if (node.matches && node.matches(selector)) {
                        injectTrackingFields(node);
                        node.addEventListener('submit', function() {
                          injectTrackingFields(node);
                          captureBillingFields(node);
                        }, false);
                      }
                    } catch (e) {
                      // Silently catch selector errors
                    }
                  });
                }
                // Check for forms within the added node
                if (node.querySelectorAll) {
                  selectors.forEach(function(selector) {
                    try {
                      var forms = node.querySelectorAll(selector);
                      forms.forEach(function(form) {
                        injectTrackingFields(form);
                        form.addEventListener('submit', function() {
                          injectTrackingFields(form);
                          captureBillingFields(form);
                        }, false);
                      });
                    } catch (e) {
                      // Silently catch selector errors
                    }
                  });
                }
              }
            });
          }
        });
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    }
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', monitorCheckoutForms);
  } else {
    monitorCheckoutForms();
  }

  // WooCommerce specific: Re-inject on checkout update (AJAX)
  if (typeof jQuery !== 'undefined') {
    jQuery(document.body).on('updated_checkout', function() {
      setTimeout(monitorCheckoutForms, 100);
    });

    // WooCommerce blocks compatibility
    jQuery(document.body).on('checkout_error', function() {
      setTimeout(monitorCheckoutForms, 100);
    });
  }

  // EDD specific: Re-inject on AJAX cart updates
  if (typeof window.edd_scripts !== 'undefined') {
    document.addEventListener('edd_cart_billing_updated', monitorCheckoutForms);
    document.addEventListener('edd_quantity_updated', monitorCheckoutForms);
  }
})();";

		// Register a dummy script handle and add the inline script.
		// Use false for source to indicate inline-only script.
		wp_register_script( 'ts-checkout', false, array(), TRACKSURE_VERSION, true );
		wp_enqueue_script( 'ts-checkout' );
		wp_add_inline_script( 'ts-checkout', $checkout_script );
	}

	/**  * Validate UUID format (version 4).
	 *
	 * @param string $uuid UUID to validate.
	 * @return bool True if valid UUID format.
	 */
	private function is_valid_uuid( $uuid ) {
		return (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $uuid );
	}

	/**  * Extract tracking data from POST and merge into event data
	 *
	 * @param array  $event_data Event data array.
	 * @param string $event_name Event name.
	 * @return array Modified event data.
	 */
	public function extract_tracking_from_post( $event_data, $event_name ) {
		// Only for purchase/checkout events.
		$checkout_events = array( 'purchase', 'begin_checkout', 'add_payment_info' );
		if ( ! in_array( $event_name, $checkout_events, true ) ) {
			return $event_data;
		}

		// Verify nonce — required before reading any $_POST data.
		if (
		! isset( $_POST['_ts_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ts_nonce'] ) ), 'tracksure_checkout_tracking' )
		) {
			// Nonce missing or invalid — do not read $_POST, fall back to session-based tracking.
			return $event_data;
		}

		// Nonce verified — safe to extract tracking IDs from POST.
		if ( isset( $_POST['_ts_eid'] ) && ! empty( $_POST['_ts_eid'] ) ) {
			$sanitized = sanitize_text_field( wp_unslash( $_POST['_ts_eid'] ) );
			// Validate event_id format (UUID or ts_timestamp_randomstring format).
			if ( $this->is_valid_uuid( $sanitized ) || preg_match( '/^ts_[0-9]+_[a-z0-9]+$/i', $sanitized ) ) {
				$event_data['event_id'] = $sanitized;
			}
		}

		if ( isset( $_POST['_ts_cid'] ) && ! empty( $_POST['_ts_cid'] ) ) {
			$sanitized = sanitize_text_field( wp_unslash( $_POST['_ts_cid'] ) );
			if ( $this->is_valid_uuid( $sanitized ) ) {
				$event_data['client_id'] = $sanitized;
			}
		}

		if ( isset( $_POST['_ts_sid'] ) && ! empty( $_POST['_ts_sid'] ) ) {
			$sanitized = sanitize_text_field( wp_unslash( $_POST['_ts_sid'] ) );
			if ( $this->is_valid_uuid( $sanitized ) ) {
				$event_data['session_id'] = $sanitized;
			}
		}

		// BILLING FIELDS: Extract checkout form data for EMQ enrichment.
		// For guest users (not logged in), this is the ONLY source of email/phone/address.
		// These dramatically improve Meta CAPI Event Match Quality and cross-device matching.
		$billing_fields = array(
			'_ts_b_email'   => 'email',
			'_ts_b_phone'   => 'phone',
			'_ts_b_fname'   => 'first_name',
			'_ts_b_lname'   => 'last_name',
			'_ts_b_city'    => 'city',
			'_ts_b_state'   => 'state',
			'_ts_b_zip'     => 'zip',
			'_ts_b_country' => 'country',
		);

		$user_data   = isset( $event_data['user_data'] ) && is_array( $event_data['user_data'] ) ? $event_data['user_data'] : array();
		$has_billing = false;

		foreach ( $billing_fields as $post_key => $data_key ) {
		  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above
			if ( isset( $_POST[ $post_key ] ) && ! empty( $_POST[ $post_key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
				if ( ! empty( $value ) ) {
					// Only override if not already set (server data may be more authoritative).
					if ( empty( $user_data[ $data_key ] ) ) {
						$user_data[ $data_key ] = $value;
						$has_billing            = true;
					}
				}
			}
		}

		// Validate email format.
		if ( ! empty( $user_data['email'] ) && ! is_email( $user_data['email'] ) ) {
			unset( $user_data['email'] );
		}

		if ( $has_billing ) {
			$event_data['user_data'] = $user_data;
		}

		return $event_data;
	}
}

// Initialize.
new TrackSure_Checkout_Tracking();
