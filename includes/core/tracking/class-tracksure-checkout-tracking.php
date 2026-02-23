<?php
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

defined('ABSPATH') || exit;

class TrackSure_Checkout_Tracking
{




  /**
   * Initialize checkout tracking
   */
  public function __construct()
  {
    // Inject fields on checkout pages.
    add_action('wp_footer', array($this, 'inject_checkout_tracking_fields'), 1);

    // Extract tracking data from POST on order creation.
    add_filter('tracksure_event_data', array($this, 'extract_tracking_from_post'), 10, 2);
  }

  /**
   * Check if current page is a checkout page (any e-commerce solution)
   *
   * @return bool
   */
  private function is_checkout_page()
  {
    // WooCommerce.
    if (function_exists('is_checkout') && is_checkout()) {
      return true;
    }

    // FluentCart (fluent-cart, fluent-checkout).
    if (class_exists('FluentCart\App\App')) {
      global $post;
      if ($post && has_shortcode($post->post_content, 'fluent_cart')) {
        return true;
      }
    }

    // Easy Digital Downloads.
    if (function_exists('edd_is_checkout') && edd_is_checkout()) {
      return true;
    }

    // SureCart
    if (function_exists('surecart') && is_page()) {
      global $post;
      if ($post && (
        has_block('surecart/checkout', $post) ||
        strpos($post->post_content, 'surecart') !== false
      )) {
        return true;
      }
    }

    return false;
  }

  /**
   * Inject hidden tracking fields into checkout forms
   */
  public function inject_checkout_tracking_fields()
  {
    // Only on checkout pages.
    if (! $this->is_checkout_page()) {
      return;
    }

    // Don't inject if tracking disabled.
    if (! get_option('tracksure_tracking_enabled', true)) {
      return;
    }

    // Generate nonce for secure tracking.
    $nonce = wp_create_nonce('tracksure_checkout_tracking');

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
        name: 'tracksure_event_id',
        value: eventId
      },
      {
        name: 'tracksure_client_id',
        value: clientId
      },
      {
        name: 'tracksure_session_id',
        value: sessionId
      },
      {
        name: 'tracksure_nonce',
        value: '" . esc_js($nonce) . "'
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

          // Re-inject on form submit to ensure fresh event_id
          form.addEventListener('submit', function() {
            injectTrackingFields(form);
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
    wp_register_script('tracksure-checkout-tracking', false, array(), TRACKSURE_VERSION, true);
    wp_enqueue_script('tracksure-checkout-tracking');
    wp_add_inline_script('tracksure-checkout-tracking', $checkout_script);
  }

  /**  * Validate UUID format (version 4).
   *
   * @param string $uuid UUID to validate.
   * @return bool True if valid UUID format.
   */
  private function is_valid_uuid($uuid)
  {
    return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $uuid);
  }

  /**  * Extract tracking data from POST and merge into event data
   *
   * @param array  $event_data Event data array.
   * @param string $event_name Event name.
   * @return array Modified event data.
   */
  public function extract_tracking_from_post($event_data, $event_name)
  {
    // Only for purchase/checkout events.
    $checkout_events = array('purchase', 'begin_checkout', 'add_payment_info');
    if (! in_array($event_name, $checkout_events, true)) {
      return $event_data;
    }

    // Verify nonce — required before reading any $_POST data.
    if (
      ! isset($_POST['tracksure_nonce'])
      || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tracksure_nonce'])), 'tracksure_checkout_tracking')
    ) {
      // Nonce missing or invalid — do not read $_POST, fall back to session-based tracking.
      return $event_data;
    }

    // Nonce verified — safe to extract tracking IDs from POST.
    if (isset($_POST['tracksure_event_id']) && ! empty($_POST['tracksure_event_id'])) {
      $sanitized = sanitize_text_field(wp_unslash($_POST['tracksure_event_id']));
      // Validate event_id format (UUID or ts_timestamp_randomstring format).
      if ($this->is_valid_uuid($sanitized) || preg_match('/^ts_[0-9]+_[a-z0-9]+$/i', $sanitized)) {
        $event_data['event_id'] = $sanitized;
      }
    }

    if (isset($_POST['tracksure_client_id']) && ! empty($_POST['tracksure_client_id'])) {
      $sanitized = sanitize_text_field(wp_unslash($_POST['tracksure_client_id']));
      if ($this->is_valid_uuid($sanitized)) {
        $event_data['client_id'] = $sanitized;
      }
    }

    if (isset($_POST['tracksure_session_id']) && ! empty($_POST['tracksure_session_id'])) {
      $sanitized = sanitize_text_field(wp_unslash($_POST['tracksure_session_id']));
      if ($this->is_valid_uuid($sanitized)) {
        $event_data['session_id'] = $sanitized;
      }
    }

    return $event_data;
  }
}

// Initialize.
new TrackSure_Checkout_Tracking();
