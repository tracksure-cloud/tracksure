/**
 * TrackSure Universal Mini Cart Tracking
 * 
 * Tracks AJAX add-to-cart events across ALL e-commerce platforms:
 * - WooCommerce (standard + blocks)
 * - FluentCart
 * - Easy Digital Downloads (EDD)
 * - SureCart
 * - CartFlows
 * - WP eCommerce
 * - BigCommerce for WordPress
 * - Ecwid
 * - Shopify Buy Button
 * - Generic AJAX carts
 * 
 * Strategy:
 * 1. Listen to platform-specific custom events
 * 2. Intercept AJAX requests to cart endpoints
 * 3. Observe "Add to Cart" button clicks
 * 4. MutationObserver for dynamic cart updates
 * 
 * @version 1.0.0
 * @package TrackSure
 */

(function(window, document, $) {
    'use strict';

    // Wait for TrackSure to be ready
    if (!window.TrackSure || !window.TrackSure.trackEvent) {
        console.warn('[TrackSure MiniCart] TrackSure SDK not loaded');
        return;
    }

    // Debug mode (set to false in production)
    var DEBUG_MODE = (typeof window.tracksureDebug !== 'undefined') ? window.tracksureDebug : false;

    // Detected platform
    var detectedPlatform = null;

    // Deduplication: Track recent events to prevent duplicates
    var recentEvents = [];
    var DEDUP_WINDOW_MS = 2000; // 2 second window

    /**
     * Check if event is duplicate
     */
    function isDuplicateEvent(itemId, quantity, timestamp) {
        var now = timestamp || Date.now();
        var eventKey = itemId + '_' + quantity;
        
        // Clean old events
        recentEvents = recentEvents.filter(function(e) {
            return (now - e.timestamp) < DEDUP_WINDOW_MS;
        });
        
        // Check for duplicate
        var isDupe = recentEvents.some(function(e) {
            return e.key === eventKey;
        });
        
        if (!isDupe) {
            recentEvents.push({ key: eventKey, timestamp: now });
        }
        
        return isDupe;
    }

    /**
     * Debug log (only if DEBUG_MODE enabled)
     */
    function debugLog() {
        if (DEBUG_MODE && console.log) {
            console.log.apply(console, arguments);
        }
    }

    /**
     * Get default currency from detected platform
     * Normalizes non-standard codes (BDT → USD per TrackSure standards)
     */
    function getDefaultCurrency() {
        var currency = 'USD';
        
        // FluentCart - Check global config OR use REST API response
        if (detectedPlatform === 'fluentcart') {
            // Try window.fluentCartConfig first
            if (typeof window.fluentCartConfig !== 'undefined' && window.fluentCartConfig.currency) {
                currency = window.fluentCartConfig.currency;
            }
            // Fallback: Check WordPress PHP localized data
            else if (typeof window.fluentCartData !== 'undefined' && window.fluentCartData.currency) {
                currency = window.fluentCartData.currency;
            }
            // Last resort: Check meta tag
            else {
                var currencyMeta = document.querySelector('meta[name="fluent-cart:currency"]');
                if (currencyMeta && currencyMeta.content) {
                    currency = currencyMeta.content;
                }
            }
        }
        // WooCommerce - Use global config
        else if (detectedPlatform === 'woocommerce' && typeof woocommerce_params !== 'undefined' && woocommerce_params.currency) {
            currency = woocommerce_params.currency;
        }
        // Easy Digital Downloads - Use global config
        else if (detectedPlatform === 'edd' && typeof edd_scripts !== 'undefined' && edd_scripts.currency) {
            currency = edd_scripts.currency;
        }

        // Normalize currency (BDT → USD, as per TrackSure adapter pattern)
        return normalizeCurrency(currency);
    }

    /**
     * Normalize currency code using centralized currency handler.
     * 
     * Uses TrackSureCurrency for consistent currency normalization
     * (synchronized with PHP TrackSure_Currency_Handler).
     * 
     * Fallback to local normalization if TrackSureCurrency not loaded.
     */
    function normalizeCurrency(code) {
        // Use centralized currency handler if available
        if (typeof window.TrackSureCurrency !== 'undefined') {
            return window.TrackSureCurrency.normalize(code);
        }
        
        // Fallback: Basic normalization if TrackSureCurrency not loaded
        if (!code || typeof code !== 'string') {
            return 'USD';
        }
        
        code = code.toUpperCase().trim();
        
        // Basic mappings (should never be used if tracksure-currency.js loads properly)
        var currencyMap = {
            'VEF': 'USD',
            'BGN': 'EUR',
            'TL': 'TRY',
            'CNH': 'CNY',
            'EURO': 'EUR',
            '': 'USD'
        };
        
        return currencyMap[code] || code;
    }

    /**
     * Initialize universal mini cart tracking
     */
    function initMiniCartTracking() {
        console.log('[TrackSure MiniCart] Initializing for platform:', detectedPlatform || 'detecting...');

        // Detect e-commerce platform
        detectPlatform();

        // Platform-specific event listeners
        initPlatformListeners();

        // Universal AJAX interceptor (works for all platforms)
        interceptAjaxRequests();

        // Universal button click observer
        observeCartButtons();

        // MutationObserver for dynamic cart changes
        observeCartMutations();

        console.log('[TrackSure MiniCart] ✅ Initialized successfully for:', detectedPlatform || 'generic');
        if (detectedPlatform === 'fluentcart') {
            console.log('[TrackSure MiniCart] FluentCart: Server-side tracking ENABLED (client-side DISABLED)');
        }
    }

    /**
     * Detect active e-commerce platform
     */
    function detectPlatform() {
        // WooCommerce
        if (typeof wc_add_to_cart_params !== 'undefined' || document.querySelector('.woocommerce')) {
            detectedPlatform = 'woocommerce';
            return;
        }

        // FluentCart
        if (typeof fluentCartConfig !== 'undefined' || document.querySelector('.fluent-cart')) {
            detectedPlatform = 'fluentcart';
            return;
        }

        // Easy Digital Downloads
        if (typeof edd_scripts !== 'undefined' || document.querySelector('.edd_download')) {
            detectedPlatform = 'edd';
            return;
        }

        // SureCart
        if (typeof surecart !== 'undefined' || document.querySelector('surecart-cart')) {
            detectedPlatform = 'surecart';
            return;
        }

        // CartFlows
        if (typeof cartflows !== 'undefined' || document.querySelector('.cartflows-checkout')) {
            detectedPlatform = 'cartflows';
            return;
        }

        // BigCommerce
        if (typeof BCData !== 'undefined' || document.querySelector('.bc-product')) {
            detectedPlatform = 'bigcommerce';
            return;
        }

        // Ecwid
        if (typeof Ecwid !== 'undefined' || document.querySelector('.ec-store')) {
            detectedPlatform = 'ecwid';
            return;
        }

        // Generic fallback
        detectedPlatform = 'generic';
    }

    /**
     * Initialize platform-specific event listeners
     */
    function initPlatformListeners() {
        switch (detectedPlatform) {
            case 'woocommerce':
                initWooCommerceListeners();
                break;
            case 'fluentcart':
                initFluentCartListeners();
                break;
            case 'edd':
                initEDDListeners();
                break;
            case 'surecart':
                initSureCartListeners();
                break;
            case 'cartflows':
                initCartFlowsListeners();
                break;
            case 'bigcommerce':
                initBigCommerceListeners();
                break;
            case 'ecwid':
                initEcwidListeners();
                break;
        }
    }

    /**
     * WooCommerce event listeners
     */
    function initWooCommerceListeners() {
        // Standard WooCommerce AJAX add to cart
        $(document.body).on('added_to_cart', function(event, fragments, cart_hash, button) {
            debugLog('[TrackSure MiniCart] WooCommerce added_to_cart event');
            
            // Extract product data from button
            if (button && button.length) {
                var productId = button.data('product_id');
                var quantity = button.data('quantity') || 1;
                var productName = button.data('product_name') || button.attr('aria-label');
                
                trackAddToCartById(productId, quantity, {
                    item_name: productName,
                    source: 'woocommerce'
                });
            }
        });

        // WooCommerce blocks (new cart system)
        document.addEventListener('wc-blocks_added_to_cart', function(event) {
            debugLog('[TrackSure MiniCart] WooCommerce Blocks added_to_cart event', event.detail);
            
            if (event.detail && event.detail.product) {
                var product = event.detail.product;
                trackAddToCart({
                    item_id: String(product.id),
                    item_name: product.name,
                    price: parseFloat(product.prices.price) / 100, // WC uses cents
                    quantity: event.detail.quantity || 1,
                    currency: product.prices.currency_code || 'USD',
                    source: 'woocommerce-blocks'
                });
            }
        });
    }

    /**
     * FluentCart event listeners
     * 
     * CRITICAL: FluentCart tracking is COMPLETELY DISABLED on client-side.
     * 
     * WHY: FluentCart Integration (class-tracksure-fluentcart-integration.php) 
     * already handles ALL tracking via server-side hooks:
     * - fluent_cart/cart/item_added → track_add_to_cart()
     * - fluent_cart/before_checkout_page_start → track_view_cart()
     * - fluent_cart/before_checkout_form → track_begin_checkout()
     * - fluent_cart/before_payment_methods → track_add_payment_info()
     * - fluent_cart/order_paid_done → track_purchase()
     * 
     * Client-side tracking would create DUPLICATE events.
     * Server-side tracking provides accurate variation IDs, prices, and cart data.
     * 
     * DO NOT add any FluentCart event listeners here.
     */
    function initFluentCartListeners() {
        debugLog('[TrackSure MiniCart] FluentCart: Server-side tracking ONLY (client-side DISABLED)');
        // INTENTIONALLY EMPTY - No client-side FluentCart tracking
    }

    /**
     * Easy Digital Downloads listeners
     */
    function initEDDListeners() {
        // EDD AJAX cart updates
        $(document.body).on('edd_cart_item_added', function(event, response) {
            debugLog('[TrackSure MiniCart] EDD cart_item_added event', response);
            
            if (response && response.cart_item) {
                trackAddToCart({
                    item_id: String(response.cart_item.id),
                    item_name: response.cart_item.name,
                    price: parseFloat(response.cart_item.price || 0),
                    quantity: parseInt(response.cart_item.quantity || 1, 10),
                    currency: edd_scripts.currency || 'USD',
                    source: 'edd'
                });
            }
        });
    }

    /**
     * SureCart listeners
     */
    function initSureCartListeners() {
        // SureCart uses custom elements and events
        document.addEventListener('surecart/cart/item-added', function(event) {
            debugLog('[TrackSure MiniCart] SureCart item-added event', event.detail);
            
            if (event.detail && event.detail.item) {
                var item = event.detail.item;
                trackAddToCart({
                    item_id: String(item.id),
                    item_name: item.name,
                    price: parseFloat(item.price || 0) / 100, // SureCart uses cents
                    quantity: parseInt(item.quantity || 1, 10),
                    currency: item.currency || 'USD',
                    source: 'surecart'
                });
            }
        });
    }

    /**
     * CartFlows listeners
     */
    function initCartFlowsListeners() {
        // CartFlows is WooCommerce-based, so WooCommerce listeners handle it
        debugLog('[TrackSure MiniCart] CartFlows uses WooCommerce events');
    }

    /**
     * BigCommerce listeners
     */
    function initBigCommerceListeners() {
        // BigCommerce cart events
        document.addEventListener('bigcommerce/cart/item-added', function(event) {
            debugLog('[TrackSure MiniCart] BigCommerce item-added event', event.detail);
            
            if (event.detail && event.detail.product) {
                var product = event.detail.product;
                trackAddToCart({
                    item_id: String(product.id),
                    item_name: product.name,
                    price: parseFloat(product.price || 0),
                    quantity: parseInt(event.detail.quantity || 1, 10),
                    currency: BCData.currency_code || 'USD',
                    source: 'bigcommerce'
                });
            }
        });
    }

    /**
     * Ecwid listeners
     */
    function initEcwidListeners() {
        // Ecwid API events
        if (typeof Ecwid !== 'undefined' && Ecwid.OnAPILoaded) {
            Ecwid.OnAPILoaded.add(function() {
                Ecwid.OnCartChanged.add(function(cart) {
                    debugLog('[TrackSure MiniCart] Ecwid cart changed', cart);
                    
                    // Ecwid doesn't provide "item added" event, only cart state
                    // Track if cart count increased
                    if (cart && cart.items && cart.items.length > 0) {
                        // Track latest item
                        var latestItem = cart.items[cart.items.length - 1];
                        trackAddToCart({
                            item_id: String(latestItem.product.id),
                            item_name: latestItem.product.name,
                            price: parseFloat(latestItem.product.price || 0),
                            quantity: parseInt(latestItem.quantity || 1, 10),
                            currency: cart.currency || 'USD',
                            source: 'ecwid'
                        });
                    }
                });
            });
        }
    }

    /**
     * Universal AJAX interceptor for all platforms
     */
    function interceptAjaxRequests() {
        // jQuery AJAX interceptor (most e-commerce plugins use jQuery)
        if (typeof $ !== 'undefined' && $.ajaxPrefilter) {
            $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                // Listen for successful AJAX responses
                var originalSuccess = options.success;
                options.success = function(data, textStatus, jqXHR) {
                    handleAjaxResponse(data, options);
                    if (originalSuccess) {
                        originalSuccess.apply(this, arguments);
                    }
                };
            });
        }

        // Fetch API interceptor (modern implementations)
        var originalFetch = window.fetch;
        window.fetch = function() {
            return originalFetch.apply(this, arguments).then(function(response) {
                var clonedResponse = response.clone();
                clonedResponse.json().then(function(data) {
                    handleAjaxResponse(data, { url: response.url });
                }).catch(function() {
                    // Not JSON, ignore
                });
                return response;
            });
        };
    }

    /**
     * Handle AJAX response from cart endpoints
     */
    function handleAjaxResponse(data, options) {
        if (!data || !options.url) {
            return;
        }

        var url = options.url.toLowerCase();

        // WooCommerce AJAX endpoints
        if (url.indexOf('?wc-ajax=add_to_cart') !== -1 || url.indexOf('action=woocommerce_add_to_cart') !== -1) {
            debugLog('[TrackSure MiniCart] WooCommerce AJAX add_to_cart detected');
            // Handled by WooCommerce event listeners
        }

        // FluentCart REST API - COMPLETELY SKIP (server-side handles all tracking)
        if (url.indexOf('/wp-json/fluent-cart/v1/cart') !== -1 || 
            url.indexOf('action=fluent_cart') !== -1 ||
            url.indexOf('/fluent-cart/v1/') !== -1 ||
            url.indexOf('fluent_cart_place_order') !== -1) {
            debugLog('[TrackSure MiniCart] FluentCart API detected - SKIPPING (server-side tracking active)');
            return; // Exit early to prevent any tracking
        }

        // EDD AJAX
        if (url.indexOf('action=edd_add_to_cart') !== -1) {
            debugLog('[TrackSure MiniCart] EDD AJAX add_to_cart detected');
            // Handled by EDD event listeners
        }

        // Generic cart endpoints (fallback)
        if (url.indexOf('/cart/add') !== -1 || url.indexOf('add-to-cart') !== -1 || url.indexOf('addtocart') !== -1) {
            debugLog('[TrackSure MiniCart] Generic cart endpoint detected:', url);
            // Try to extract product data from response
            if (data.product_id || data.item_id) {
                trackAddToCartById(data.product_id || data.item_id, data.quantity || 1);
            }
        }
    }

    /**
     * Universal button click observer
     * 
     * Supports add-to-cart buttons from:
     * - WooCommerce (AJAX + non-AJAX)
     * - FluentCart
     * - Easy Digital Downloads
     * - Custom themes
     * - Page builders (Elementor, Divi, etc.)
     */
    function observeCartButtons() {
        // Use event delegation for dynamic buttons
        document.addEventListener('click', function(event) {
            var target = event.target;
            
            // Comprehensive button selector (supports nested elements like icons)
            var buttonSelectors = [
                // WooCommerce
                'button.add_to_cart_button',
                'button.ajax_add_to_cart',
                'a.add_to_cart_button',
                '.single_add_to_cart_button',
                
                // FluentCart
                'button.fluent-cart-add',
                '.fluent-product-add-to-cart',
                '[data-fluent-cart-action="add"]',
                
                // Easy Digital Downloads
                'button.edd-add-to-cart',
                '.edd-submit-button',
                
                // Generic/Custom
                'button[data-action="add-to-cart"]',
                'button[data-product-action="add-to-cart"]',
                '.add-to-cart-btn',
                '.product-add-to-cart',
                '.btn-add-to-cart',
                '.add-cart-button',
                '[data-add-to-cart]',
                
                // Page builders
                '.elementor-button[href*="add-to-cart"]',
                '.et_pb_button[href*="add-to-cart"]'
            ];
            
            // Find closest cart button (supports nested elements like icons inside buttons)
            var button = null;
            for (var i = 0; i < buttonSelectors.length; i++) {
                button = target.closest(buttonSelectors[i]);
                if (button) break;
            }
            
            if (!button) {
                return;
            }

            debugLog('[TrackSure MiniCart] Cart button clicked:', button.className || button.tagName);

            // CRITICAL: Skip FluentCart buttons (server-side handles tracking)
            var isFluentCartButton = button.classList.contains('fluent-cart-add') ||
                                    button.classList.contains('fluent-product-add-to-cart') ||
                                    button.hasAttribute('data-fluent-cart-action') ||
                                    button.closest('.fluent-cart') !== null ||
                                    button.closest('.fluent-product') !== null;
            
            if (isFluentCartButton && detectedPlatform === 'fluentcart') {
                debugLog('[TrackSure MiniCart] FluentCart button detected - SKIPPING (server-side tracking active)');
                return; // Exit early - server-side handles this
            }

            // Extract product data from button attributes (comprehensive list)
            var productId = button.getAttribute('data-product_id') || 
                           button.getAttribute('data-product-id') ||
                           button.getAttribute('data-product-sku') ||
                           button.getAttribute('data-item-id') ||
                           button.getAttribute('data-download-id') ||
                           button.getAttribute('data-id') ||
                           button.getAttribute('value');
            
            var quantity = parseInt(button.getAttribute('data-quantity') || button.getAttribute('data-qty'), 10) || 1;
            
            var productName = button.getAttribute('data-product-name') || 
                             button.getAttribute('data-product_name') ||
                             button.getAttribute('data-name') ||
                             button.getAttribute('aria-label') ||
                             button.getAttribute('title') ||
                             button.textContent?.trim();

            // Extract from URL for link-based buttons
            if (!productId && button.href) {
                var urlMatch = button.href.match(/[?&]add-to-cart=(\d+)/);
                if (urlMatch) {
                    productId = urlMatch[1];
                }
            }

            if (productId) {
                debugLog('[TrackSure MiniCart] Product ID extracted:', productId, 'Quantity:', quantity);
                
                // Slight delay to let AJAX complete and avoid race conditions
                setTimeout(function() {
                    trackAddToCartById(productId, quantity, {
                        item_name: productName,
                        source: detectedPlatform || 'generic',
                        button_type: button.tagName.toLowerCase(),
                        button_class: button.className
                    });
                }, 300); // Increased delay for slower servers
            } else {
                debugLog('[TrackSure MiniCart] No product ID found on button:', button);
            }
        }, true); // Use capture to catch before other handlers
    }

    /**
     * MutationObserver for dynamic cart changes
     * 
     * Watches for mini cart/slide cart/drawer cart updates across all themes and plugins.
     * Supports: WooCommerce, FluentCart, custom theme carts, block-based carts, etc.
     */
    function observeCartMutations() {
        // Comprehensive list of cart selectors (mini carts, slide carts, drawers, modals)
        var cartCountSelectors = [
            // Generic cart counters
            '.cart-count',
            '.cart-contents-count',
            '.header-cart-count',
            '.mini-cart-count',
            '.cart-items-count',
            '[data-cart-count]',
            
            // WooCommerce specific
            '.woocommerce-mini-cart',
            '.widget_shopping_cart_content',
            '.cart_list',
            '.woocommerce-mini-cart__total',
            '.cart-contents',
            
            // FluentCart specific
            '.fluent-cart-mini',
            '.fluent-cart-drawer',
            '.fluent-cart-count',
            '[data-fluent-cart]',
            
            // Theme-specific (popular themes)
            '.site-header-cart',
            '.header-cart',
            '.minicart',
            '.cart-drawer',
            '.slide-cart',
            '.cart-sidebar',
            '.offcanvas-cart',
            '.cart-panel',
            '.cart-popup',
            
            // Block-based (Gutenberg)
            '.wp-block-woocommerce-mini-cart',
            '.wc-block-mini-cart',
            
            // Custom attributes
            '[role="cart"]',
            '[data-cart]',
            '[data-minicart]'
        ];

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                // Check if cart count increased (item added)
                if (mutation.type === 'characterData' || mutation.type === 'childList') {
                    var target = mutation.target;
                    var targetElement = target.nodeType === Node.TEXT_NODE ? target.parentElement : target;
                    
                    // Log mutation for debugging
                    if (targetElement && targetElement.matches && cartCountSelectors.some(function(selector) {
                        return targetElement.matches(selector) || targetElement.closest(selector);
                    })) {
                        debugLog('[TrackSure MiniCart] Cart DOM mutation detected in:', targetElement.className || targetElement.tagName);
                        // Note: Actual tracking is handled by platform-specific event listeners
                        // This observer is for monitoring and debugging
                    }
                }
            });
        });

        // Observe each cart element (including dynamically added ones)
        cartCountSelectors.forEach(function(selector) {
            var elements = document.querySelectorAll(selector);
            elements.forEach(function(element) {
                observer.observe(element, {
                    childList: true,
                    characterData: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['data-count', 'data-quantity', 'data-items']
                });
            });
        });

        // Re-observe when new elements are added (for infinite scroll, AJAX loaded content)
        var bodyObserver = new MutationObserver(function() {
            cartCountSelectors.forEach(function(selector) {
                var elements = document.querySelectorAll(selector);
                elements.forEach(function(element) {
                    if (!element.hasAttribute('data-tracksure-observed')) {
                        element.setAttribute('data-tracksure-observed', 'true');
                        observer.observe(element, {
                            childList: true,
                            characterData: true,
                            subtree: true
                        });
                    }
                });
            });
        });

        bodyObserver.observe(document.body, {
            childList: true,
            subtree: true
        });

        debugLog('[TrackSure MiniCart] MutationObserver initialized for', cartCountSelectors.length, 'cart selectors');
    }

    /**
     * Track add_to_cart event (with full item data)
     */
    function trackAddToCart(item) {
        if (!item || !item.item_id) {
            console.warn('[TrackSure MiniCart] Invalid item data:', item);
            return;
        }

        // Check for duplicate event
        var quantity = parseInt(item.quantity || 1, 10);
        if (isDuplicateEvent(item.item_id, quantity)) {
            debugLog('[TrackSure MiniCart] Duplicate event detected - skipping:', item.item_id);
            return;
        }

        debugLog('[TrackSure MiniCart] Tracking add_to_cart:', item);

        // Normalize event data
        var eventData = {
            item_id: String(item.item_id),
            item_name: item.item_name || 'Unknown Product',
            price: parseFloat(item.price || 0),
            quantity: quantity,
            currency: normalizeCurrency(item.currency || getDefaultCurrency())
        };

        // Optional fields
        if (item.item_category) {
            eventData.item_category = item.item_category;
        }
        if (item.item_variant) {
            eventData.item_variant = item.item_variant;
        }
        if (item.sku || item.item_sku) {
            eventData.item_sku = item.sku || item.item_sku;
        }
        if (item.item_brand) {
            eventData.item_brand = item.item_brand;
        }
        if (item.source) {
            eventData.platform = item.source;
        }

        // Calculate value
        eventData.value = eventData.price * eventData.quantity;

        // Track via TrackSure SDK
        if (window.TrackSure && window.TrackSure.trackEvent) {
            try {
                window.TrackSure.trackEvent('add_to_cart', eventData);
                console.log('[TrackSure MiniCart] ✅ add_to_cart tracked:', eventData.item_id, eventData.item_name);
            } catch (error) {
                console.error('[TrackSure MiniCart] Error tracking event:', error);
            }
        } else {
            console.warn('[TrackSure MiniCart] TrackSure SDK not available');
        }
    }

    /**
     * Track add_to_cart by product ID (fetch data from API/DOM)
     */
    function trackAddToCartById(productId, quantity, extraData) {
        debugLog('[TrackSure MiniCart] Fetching product data for ID:', productId);

        quantity = parseInt(quantity, 10) || 1;
        extraData = extraData || {};

        // Try to get product data from DOM first
        var productData = getProductDataFromDOM(productId);
        
        if (productData) {
            productData.quantity = quantity;
            productData.item_id = String(productId);
            
            // Merge extra data
            for (var key in extraData) {
                if (extraData.hasOwnProperty(key)) {
                    productData[key] = extraData[key];
                }
            }
            
            trackAddToCart(productData);
        } else {
            // Fallback: Track with minimal data
            debugLog('[TrackSure MiniCart] Could not fetch product data for ID:', productId, '- using fallback');
            trackAddToCart({
                item_id: String(productId),
                item_name: extraData.item_name || 'Product ' + productId,
                price: 0,
                quantity: quantity,
                currency: getDefaultCurrency(),
                source: extraData.source || detectedPlatform || 'generic'
            });
        }
    }

    /**
     * Track FluentCart item (special format)
     */
    function trackFluentCartItem(item) {
        if (!item || !item.object_id) {
            return;
        }

        trackAddToCart({
            item_id: String(item.object_id || item.variation_id || item.product_id),
            item_name: item.title || item.name || 'Unknown Product',
            price: parseFloat(item.price || item.sale_price || item.regular_price || 0),
            quantity: parseInt(item.quantity || 1, 10),
            currency: item.currency || getDefaultCurrency(), // Use platform-specific currency
            item_category: item.category || item.product_category,
            sku: item.sku,
            source: 'fluentcart'
        });
    }

    /**
     * Get product data from DOM (product schema, WooCommerce data, variations)
     */
    function getProductDataFromDOM(productId) {
        // Try to find product schema (Schema.org JSON-LD)
        var scripts = document.querySelectorAll('script[type="application/ld+json"]');
        for (var i = 0; i < scripts.length; i++) {
            try {
                var schema = JSON.parse(scripts[i].textContent);
                if (schema['@type'] === 'Product' && String(schema.sku) === String(productId)) {
                    return {
                        item_name: schema.name,
                        price: parseFloat(schema.offers && schema.offers.price) || 0,
                        currency: normalizeCurrency((schema.offers && schema.offers.priceCurrency) || 'USD'),
                        item_sku: schema.sku,
                        item_brand: schema.brand && schema.brand.name
                    };
                }
            } catch (e) {
                // Not valid JSON or product schema
            }
        }

        // Try WooCommerce product data attributes
        var productElement = document.querySelector('[data-product_id="' + productId + '"]') ||
                            document.querySelector('[data-product-id="' + productId + '"]');
        
        if (productElement) {
            var data = {
                item_name: productElement.getAttribute('data-product_name') || 
                          productElement.getAttribute('data-product-name') ||
                          productElement.querySelector('.product_title, .woocommerce-loop-product__title, h1.product_title')?.textContent?.trim(),
                price: parseFloat(productElement.getAttribute('data-product_price') || 
                                 productElement.getAttribute('data-price')) || 0,
                currency: normalizeCurrency(getDefaultCurrency()),
                item_sku: productElement.getAttribute('data-product_sku') || 
                         productElement.getAttribute('data-sku')
            };
            
            // Check for variation data
            var variationId = productElement.getAttribute('data-variation_id') ||
                             productElement.getAttribute('data-variation-id');
            if (variationId) {
                data.item_id = variationId;
                data.item_variant = productElement.getAttribute('data-variation-name') ||
                                   productElement.querySelector('.variation')?.textContent?.trim();
            }
            
            return data;
        }

        // Try to get from current product page context
        var productTitle = document.querySelector('.product_title, h1.product_title, .entry-title');
        var productPrice = document.querySelector('.price .amount, .price ins .amount, .woocommerce-Price-amount');
        
        if (productTitle) {
            return {
                item_name: productTitle.textContent?.trim() || 'Product ' + productId,
                price: productPrice ? parseFloat(productPrice.textContent.replace(/[^0-9.]/g, '')) : 0,
                currency: normalizeCurrency(getDefaultCurrency()),
                item_id: String(productId)
            };
        }

        return null;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMiniCartTracking);
    } else {
        initMiniCartTracking();
    }

})(window, document, typeof jQuery !== 'undefined' ? jQuery : null);
