# 🧩 TrackSure Module Development Guide

**Complete guide to creating Free, Pro, and third-party modules for TrackSure**

---

## 📚 **Table of Contents**

1. [Overview](#overview)
2. [Module System Architecture](#module-system-architecture)
3. [Module Types](#module-types)
4. [Creating a Module](#creating-a-module)
5. [Module Interface](#module-interface)
6. [Capability System](#capability-system)
7. [Best Practices](#best-practices)
8. [Examples](#examples)

---

## 📖 **Overview**

TrackSure uses a **modular architecture** that allows extending functionality through modules:

- **Free Module** - Bundled with core, provides basic features
- **Pro Module** - Premium features (advanced analytics, destinations, integrations)
- **Third-Party Modules** - Community or partner extensions

**What Modules Can Do**:

✅ Register new destinations (ad platforms)  
✅ Register new integrations (ecommerce platforms)  
✅ Add custom dashboards to React admin  
✅ Register custom events and parameters  
✅ Extend settings schema  
✅ Add background jobs  
✅ Hook into core events

---

## 🏗️ **Module System Architecture**

### **How Modules Work**

```
WordPress Plugin Init
         ↓
TrackSure Core Boots
         ↓
tracksure_register_module Action Fires
         ↓
Modules Register Themselves
         ↓
Core Loads Module Files
         ↓
Module::init() Called
         ↓
Module::register_capabilities()
         ↓
Capabilities Available to Core
```

### **Module Lifecycle**

1. **Registration** (Priority 4): Module declares itself
2. **Loading** (Priority 10): Core loads module file
3. **Initialization**: Module `init()` method called
4. **Capability Registration**: Module registers features
5. **Runtime**: Module responds to hooks and filters

---

## 🎯 **Module Types**

### **1. Free Module**

**Location**: `includes/free/class-tracksure-free.php`

**Purpose**: Bundled features included with core (loaded directly by main plugin, not via module registration)

**Capabilities**:

- Meta Conversions API destination
- Google Analytics 4 destination
- WooCommerce integration
- Basic dashboards

**When to Use**:

- Core features everyone needs
- Features that work without license
- Community-contributed extensions

**Note**: The Free module is loaded directly by `tracksure.php` during initialization, not through the `tracksure_loaded` hook. It receives the Core instance and registers its capabilities immediately.

---

### **2. Pro Module**

**Location**: `includes/pro/class-tracksure-pro.php` (separate plugin)

**Purpose**: Premium features requiring license

**Capabilities**:

- Advanced destinations (TikTok, Pinterest, Snapchat)
- Premium integrations (Shopify, BigCommerce, Custom Cart)
- Advanced analytics dashboards
- A/B testing features
- Custom event funnels
- Advanced attribution models

**When to Use**:

- Features requiring external APIs with costs
- Advanced analytics requiring heavy processing
- Features requiring ongoing support

---

### **3. Third-Party Module**

**Location**: Separate WordPress plugin

**Purpose**: Community or partner extensions

**Capabilities**:

- Custom integrations (e.g., EDD, MemberPress)
- Regional ad platforms (e.g., Baidu, Yandex)
- Industry-specific tracking (e.g., real estate, bookings)
- Custom dashboards

**When to Use**:

- Building plugins that extend TrackSure
- Partner integrations
- Industry-specific solutions

---

## 🚀 **Creating a Module**

### **Step 1: Create Plugin Structure**

```
my-tracksure-extension/
├── my-tracksure-extension.php      (Main plugin file)
├── includes/
│   └── class-my-module.php         (Module class)
├── destinations/
│   └── class-my-destination.php    (Optional)
├── integrations/
│   └── class-my-integration.php    (Optional)
└── readme.txt
```

### **Step 2: Create Main Plugin File**

**File**: `my-tracksure-extension.php`

```php
<?php
/**
 * Plugin Name: My TrackSure Extension
 * Description: Adds custom tracking features to TrackSure
 * Version: 1.0.0
 * Author: Your Name
 * Requires Plugins: tracksure
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MY_TRACKSURE_EXT_VERSION', '1.0.0');
define('MY_TRACKSURE_EXT_DIR', plugin_dir_path(__FILE__));
define('MY_TRACKSURE_EXT_URL', plugin_dir_url(__FILE__));

/**
 * Register module with TrackSure Core
 */
add_action('init', function() {
    // Check if TrackSure is active
    if (!function_exists('tracksure')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo 'My TrackSure Extension requires TrackSure to be installed and activated.';
            echo '</p></div>';
        });
        return;
    }

    // Register module
    do_action('tracksure_register_module', 'my-extension', __FILE__, [
        'name' => 'My TrackSure Extension',
        'version' => MY_TRACKSURE_EXT_VERSION,
        'class_name' => 'My_TrackSure_Module',
        'file_path' => MY_TRACKSURE_EXT_DIR . 'includes/class-my-module.php',
        'capabilities' => [
            'destinations' => ['my-destination'],
            'integrations' => ['my-platform'],
            'dashboards' => ['my-analytics'],
        ],
    ]);
}, 4); // Priority 4 - before module initialization
```

### **Step 3: Create Module Class**

**File**: `includes/class-my-module.php`

```php
<?php

/**
 * My TrackSure Extension Module
 *
 * Implements TrackSure_Module_Interface to integrate with core.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class My_TrackSure_Module implements TrackSure_Module_Interface {

    /**
     * Core instance
     *
     * @var TrackSure_Core
     */
    private $core;

    /**
     * Constructor
     *
     * @param TrackSure_Core $core Core instance
     */
    public function __construct($core) {
        $this->core = $core;
    }

    /**
     * Get module ID
     *
     * @return string
     */
    public function get_id() {
        return 'my-extension';
    }

    /**
     * Get module name
     *
     * @return string
     */
    public function get_name() {
        return 'My TrackSure Extension';
    }

    /**
     * Get module version
     *
     * @return string
     */
    public function get_version() {
        return MY_TRACKSURE_EXT_VERSION;
    }

    /**
     * Get module configuration
     *
     * @return array
     */
    public function get_config() {
        return [
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'version' => $this->get_version(),
            'capabilities' => [
                'destinations' => ['my-destination'],
                'integrations' => ['my-platform'],
            ],
        ];
    }

    /**
     * Initialize module
     *
     * Called when module is loaded by core.
     */
    public function init() {
        // Register settings
        $this->register_settings();

        // Initialize features
        $this->init_admin_ui();
        $this->init_filters();
    }

    /**
     * Register module capabilities
     *
     * Called after init() to register destinations, integrations, etc.
     */
    public function register_capabilities() {
        // Register destinations
        $this->register_destinations();

        // Register integrations
        $this->register_integrations();

        // Register custom events
        $this->register_events();
    }

    /**
     * Register destinations
     */
    private function register_destinations() {
        add_action('tracksure_load_destination_handlers', [$this, 'load_destinations']);
    }

    /**
     * Load destination handlers
     *
     * @param TrackSure_Destinations_Manager $manager
     */
    public function load_destinations($manager) {
        $manager->register_destination([
            'id' => 'my-destination',
            'name' => 'My Custom Destination',
            'icon' => 'Target',
            'order' => 100,
            'enabled_key' => 'my_ext_destination_enabled',
            'class_name' => 'My_Destination_Handler',
            'file_path' => MY_TRACKSURE_EXT_DIR . 'destinations/class-my-destination.php',
            'settings_fields' => [
                'my_ext_api_key',
                'my_ext_pixel_id',
            ],
            'reconciliation_key' => 'my_destination',
        ]);
    }

    /**
     * Register integrations
     */
    private function register_integrations() {
        add_action('tracksure_load_integration_handlers', [$this, 'load_integrations']);
    }

    /**
     * Load integration handlers
     *
     * @param TrackSure_Integrations_Manager $manager
     */
    public function load_integrations($manager) {
        // Only register if platform is active
        if (class_exists('My_Ecommerce_Platform')) {
            require_once MY_TRACKSURE_EXT_DIR . 'integrations/class-my-integration.php';

            $manager->register_handler(
                'my-platform',
                'My_Platform_Integration'
            );
        }
    }

    /**
     * Register custom events
     */
    private function register_events() {
        add_filter('tracksure_loaded_events', [$this, 'add_custom_events']);
    }

    /**
     * Add custom events to registry
     *
     * @param array $events Existing events
     * @return array
     */
    public function add_custom_events($events) {
        $events['my_custom_event'] = [
            'name' => 'my_custom_event',
            'display_name' => 'My Custom Event',
            'category' => 'custom',
            'required_params' => ['custom_param'],
            'optional_params' => ['extra_data'],
        ];

        return $events;
    }

    /**
     * Register settings
     */
    private function register_settings() {
        add_filter('tracksure_settings_schema', [$this, 'extend_settings_schema']);
    }

    /**
     * Extend settings schema
     *
     * @param array $schema Existing schema
     * @return array
     */
    public function extend_settings_schema($schema) {
        $schema['my_ext_api_key'] = [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'description' => 'My Extension API Key',
        ];

        $schema['my_ext_destination_enabled'] = [
            'type' => 'boolean',
            'default' => false,
            'description' => 'Enable My Destination',
        ];

        return $schema;
    }

    /**
     * Initialize admin UI extensions
     */
    private function init_admin_ui() {
        add_action('tracksure_register_admin_extensions', [$this, 'register_admin_pages']);
    }

    /**
     * Register admin pages
     *
     * @param array $extensions Extensions registry
     */
    public function register_admin_pages(&$extensions) {
        $extensions['my-analytics'] = [
            'id' => 'my-analytics',
            'name' => 'My Analytics',
            'route' => '/my-analytics',
            'component' => 'MyAnalyticsPage',
            'menu_position' => 100,
        ];
    }

    /**
     * Initialize filters
     */
    private function init_filters() {
        // Add custom event enrichment
        add_filter('tracksure_enrich_event_data', [$this, 'enrich_events'], 10, 3);

        // Add to enabled destinations list
        add_filter('tracksure_enabled_destinations', [$this, 'add_enabled_destinations'], 10, 3);
    }

    /**
     * Enrich event data
     *
     * @param array $event_data Event data
     * @param array $session Session data
     * @param string $event_name Event name
     * @return array
     */
    public function enrich_events($event_data, $session, $event_name) {
        // Add custom data to all events
        $event_data['event_params']['my_custom_field'] = 'custom_value';

        return $event_data;
    }

    /**
     * Add enabled destinations
     *
     * @param array $enabled Current enabled destinations
     * @param array $event_data Event data
     * @param array $session Session data
     * @return array
     */
    public function add_enabled_destinations($enabled, $event_data, $session) {
        $is_enabled = get_option('my_ext_destination_enabled', false);
        $has_api_key = !empty(get_option('my_ext_api_key', ''));

        if ($is_enabled && $has_api_key) {
            $enabled[] = 'my-destination';
        }

        return $enabled;
    }
}
```

---

## 🎨 **Module Interface**

**Note**: The `TrackSure_Module_Interface` is used internally by the Free and Pro modules. **Third-party modules DO NOT need to implement this interface**. Third-party modules are simple PHP classes that receive the Core instance and register their capabilities via hooks.

**Internal Interface** (for reference only):

```php
interface TrackSure_Module_Interface {

    /**
     * Get module ID
     *
     * @return string Unique module identifier
     */
    public function get_id();

    /**
     * Get module name
     *
     * @return string Human-readable module name
     */
    public function get_name();

    /**
     * Get module version
     *
     * @return string Module version
     */
    public function get_version();

    /**
     * Get module configuration
     *
     * @return array Module configuration
     */
    public function get_config();

    /**
     * Initialize module
     *
     * Called when module is loaded by core.
     */
    public function init();

    /**
     * Register module capabilities
     *
     * Registers dashboards, destinations, integrations, features.
     */
    public function register_capabilities();
}
```

---

## 🎯 **Capability System**

Modules register capabilities to extend TrackSure:

### **1. Destinations**

Register ad platform destinations:

```php
public function register_capabilities() {
    add_action('tracksure_load_destination_handlers', function($manager) {
        $manager->register_destination([
            'id' => 'tiktok',
            'name' => 'TikTok Ads',
            'icon' => 'Music', // Lucide icon name
            'order' => 30,
            'enabled_key' => 'tracksure_pro_tiktok_enabled',
            'class_name' => 'TrackSure_TikTok_Destination',
            'file_path' => TRACKSURE_PRO_DIR . 'destinations/class-tracksure-tiktok-destination.php',
            'settings_fields' => [
                'tracksure_pro_tiktok_pixel_id',
                'tracksure_pro_tiktok_access_token',
            ],
            'reconciliation_key' => 'tiktok',
        ]);
    });
}
```

### **2. Integrations**

Register ecommerce platform integrations:

```php
public function register_capabilities() {
    add_action('tracksure_load_integration_handlers', function($manager) {
        // Only if Shopify plugin is active
        if (class_exists('WC_Shopify')) {
            require_once TRACKSURE_PRO_DIR . 'integrations/class-tracksure-shopify.php';

            $manager->register_handler(
                'shopify',
                'TrackSure_Shopify_Integration'
            );
        }
    });
}
```

### **3. Custom Events**

Register custom events in the registry:

```php
public function register_capabilities() {
    add_filter('tracksure_loaded_events', function($events) {
        $events['video_complete'] = [
            'name' => 'video_complete',
            'display_name' => 'Video Completed',
            'category' => 'engagement',
            'required_params' => ['video_url', 'video_duration'],
            'optional_params' => ['video_title', 'video_provider'],
        ];

        return $events;
    });
}
```

### **4. Custom Parameters**

Register custom event parameters:

```php
public function register_capabilities() {
    add_filter('tracksure_loaded_parameters', function($params) {
        $params['membership_level'] = [
            'name' => 'membership_level',
            'display_name' => 'Membership Level',
            'type' => 'string',
            'category' => 'user',
            'description' => 'User membership tier',
        ];

        return $params;
    });
}
```

### **5. Admin Dashboards**

Register React admin pages:

```php
public function register_capabilities() {
    add_action('tracksure_register_admin_extensions', function(&$extensions) {
        $extensions['ab-testing'] = [
            'id' => 'ab-testing',
            'name' => 'A/B Testing',
            'route' => '/ab-testing',
            'component' => 'ABTestingPage',
            'icon' => 'FlaskConical',
            'menu_position' => 50,
        ];
    });
}
```

### **6. Settings Schema**

Extend settings:

```php
public function register_capabilities() {
    add_filter('tracksure_settings_schema', function($schema) {
        $schema['pro_advanced_attribution'] = [
            'type' => 'boolean',
            'default' => false,
            'description' => 'Enable advanced attribution models',
        ];

        $schema['pro_attribution_window'] = [
            'type' => 'integer',
            'default' => 30,
            'minimum' => 1,
            'maximum' => 90,
            'description' => 'Attribution window in days',
        ];

        return $schema;
    });
}
```

---

## ✅ **Best Practices**

### **1. Check Dependencies**

Always verify TrackSure and other dependencies are active:

```php
add_action('init', function() {
    // Check TrackSure exists
    if (!function_exists('tracksure')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>My Extension requires TrackSure.</p></div>';
        });
        return;
    }

    // Check required plugins
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>My Extension requires WooCommerce.</p></div>';
        });
        return;
    }

    // All good - register module
    do_action('tracksure_register_module', 'my-ext', __FILE__, [...]);
}, 4);
```

### **2. Use Proper Hook Priorities**

```php
// Priority 4 - Module registration (BEFORE core loads modules)
add_action('init', 'register_module', 4);

// Priority 10 - Default hooks
add_filter('tracksure_enrich_event_data', 'enrich_events', 10, 3);

// Priority 99 - Late hooks (after others)
add_filter('tracksure_enabled_destinations', 'add_destinations', 99, 3);
```

### **3. Namespace Everything**

Avoid conflicts with unique prefixes:

```php
// ✅ GOOD
class MyCompany_TrackSure_Extension {}
function mycompany_tracksure_init() {}
define('MYCOMPANY_TRACKSURE_VERSION', '1.0.0');

// ❌ BAD
class Extension {}
function init() {}
define('VERSION', '1.0.0');
```

### **4. Validate Settings**

Always validate and sanitize:

```php
public function extend_settings_schema($schema) {
    $schema['my_api_key'] = [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
        'validate_callback' => function($value) {
            // Validate API key format
            if (!preg_match('/^[A-Za-z0-9]{32}$/', $value)) {
                return new WP_Error(
                    'invalid_api_key',
                    'API key must be 32 alphanumeric characters'
                );
            }
            return true;
        },
    ];

    return $schema;
}
```

### **5. Conditional Loading**

Only load what's needed:

```php
public function load_integrations($manager) {
    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {
        require_once MY_EXT_DIR . 'integrations/class-woocommerce.php';
        $manager->register_handler('woocommerce', 'My_WC_Integration');
    }

    // Check if EDD is active
    if (class_exists('Easy_Digital_Downloads')) {
        require_once MY_EXT_DIR . 'integrations/class-edd.php';
        $manager->register_handler('edd', 'My_EDD_Integration');
    }
}
```

### **6. Error Handling**

Use try-catch and logging:

```php
public function enrich_events($event_data, $session, $event_name) {
    try {
        // Get external data
        $api_response = wp_remote_get('https://api.example.com/data');

        if (is_wp_error($api_response)) {
            throw new Exception($api_response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($api_response), true);
        $event_data['event_params']['external_data'] = $data;

    } catch (Exception $e) {
        // Log error
        if (function_exists('tracksure')) {
            $logger = tracksure()->get_service('logger');
            $logger->error('External API failed: ' . $e->getMessage());
        }
    }

    return $event_data;
}
```

### **7. Documentation**

Document your module:

```php
/**
 * My TrackSure Extension
 *
 * Adds advanced video tracking capabilities to TrackSure.
 *
 * Features:
 * - YouTube video tracking
 * - Vimeo video tracking
 * - Custom video player support
 * - Video engagement events
 *
 * Requirements:
 * - TrackSure 1.0.0+
 * - WordPress 6.0+
 *
 * @package MyCompany\TrackSure\VideoTracking
 * @version 1.0.0
 */
class MyCompany_Video_Tracking_Module implements TrackSure_Module_Interface {
    // ...
}
```

---

## 📝 **Examples**

### **Example 1: Simple Destination Module**

```php
<?php
/**
 * Plugin Name: TrackSure - LinkedIn Ads
 * Description: Adds LinkedIn Ads destination to TrackSure
 * Version: 1.0.0
 * Requires Plugins: tracksure
 */

if (!defined('ABSPATH')) exit;

define('TRACKSURE_LINKEDIN_VERSION', '1.0.0');
define('TRACKSURE_LINKEDIN_DIR', plugin_dir_path(__FILE__));

// Register module
add_action('init', function() {
    if (!function_exists('tracksure')) {
        return;
    }

    do_action('tracksure_register_module', 'tracksure-linkedin', __FILE__, [
        'name' => 'TrackSure LinkedIn Ads',
        'version' => TRACKSURE_LINKEDIN_VERSION,
        'class_name' => 'TrackSure_LinkedIn_Module',
        'file_path' => TRACKSURE_LINKEDIN_DIR . 'includes/class-module.php',
        'capabilities' => [
            'destinations' => ['linkedin'],
        ],
    ]);
}, 4);
```

**File**: `includes/class-module.php`

```php
<?php

class TrackSure_LinkedIn_Module implements TrackSure_Module_Interface {

    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function get_id() {
        return 'tracksure-linkedin';
    }

    public function get_name() {
        return 'TrackSure LinkedIn Ads';
    }

    public function get_version() {
        return TRACKSURE_LINKEDIN_VERSION;
    }

    public function get_config() {
        return [
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'version' => $this->get_version(),
        ];
    }

    public function init() {
        add_filter('tracksure_settings_schema', [$this, 'add_settings']);
        add_filter('tracksure_enabled_destinations', [$this, 'add_to_enabled'], 10, 3);
    }

    public function register_capabilities() {
        add_action('tracksure_load_destination_handlers', [$this, 'register_destination']);
    }

    public function register_destination($manager) {
        $manager->register_destination([
            'id' => 'linkedin',
            'name' => 'LinkedIn Ads',
            'icon' => 'Linkedin',
            'order' => 40,
            'enabled_key' => 'tracksure_linkedin_enabled',
            'class_name' => 'TrackSure_LinkedIn_Destination',
            'file_path' => TRACKSURE_LINKEDIN_DIR . 'includes/class-destination.php',
            'settings_fields' => [
                'tracksure_linkedin_partner_id',
                'tracksure_linkedin_conversion_id',
            ],
        ]);
    }

    public function add_settings($schema) {
        $schema['tracksure_linkedin_enabled'] = [
            'type' => 'boolean',
            'default' => false,
        ];

        $schema['tracksure_linkedin_partner_id'] = [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ];

        $schema['tracksure_linkedin_conversion_id'] = [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ];

        return $schema;
    }

    public function add_to_enabled($enabled, $event_data, $session) {
        $is_enabled = get_option('tracksure_linkedin_enabled', false);
        $has_partner_id = !empty(get_option('tracksure_linkedin_partner_id', ''));

        if ($is_enabled && $has_partner_id) {
            $enabled[] = 'linkedin';
        }

        return $enabled;
    }
}
```

---

### **Example 2: Integration Module**

```php
<?php
/**
 * Plugin Name: TrackSure - Easy Digital Downloads
 * Description: Integrates Easy Digital Downloads with TrackSure
 * Version: 1.0.0
 * Requires Plugins: tracksure, easy-digital-downloads
 */

if (!defined('ABSPATH')) exit;

define('TRACKSURE_EDD_VERSION', '1.0.0');
define('TRACKSURE_EDD_DIR', plugin_dir_path(__FILE__));

// Register module
add_action('init', function() {
    if (!function_exists('tracksure') || !class_exists('Easy_Digital_Downloads')) {
        return;
    }

    do_action('tracksure_register_module', 'tracksure-edd', __FILE__, [
        'name' => 'TrackSure EDD Integration',
        'version' => TRACKSURE_EDD_VERSION,
        'class_name' => 'TrackSure_EDD_Module',
        'file_path' => TRACKSURE_EDD_DIR . 'includes/class-module.php',
        'capabilities' => [
            'integrations' => ['edd'],
        ],
    ]);
}, 4);
```

**File**: `includes/class-module.php`

```php
<?php

class TrackSure_EDD_Module implements TrackSure_Module_Interface {

    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function get_id() {
        return 'tracksure-edd';
    }

    public function get_name() {
        return 'TrackSure EDD Integration';
    }

    public function get_version() {
        return TRACKSURE_EDD_VERSION;
    }

    public function get_config() {
        return [
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'version' => $this->get_version(),
        ];
    }

    public function init() {
        // Nothing needed here
    }

    public function register_capabilities() {
        add_action('tracksure_load_integration_handlers', [$this, 'register_integration']);
    }

    public function register_integration($manager) {
        require_once TRACKSURE_EDD_DIR . 'includes/class-integration.php';
        require_once TRACKSURE_EDD_DIR . 'includes/class-adapter.php';

        $manager->register_handler('edd', 'TrackSure_EDD_Integration');
    }
}
```

**File**: `includes/class-integration.php`

```php
<?php

class TrackSure_EDD_Integration {

    private $core;
    private $adapter;

    public function __construct($core) {
        $this->core = $core;
        $this->adapter = new TrackSure_EDD_Adapter();

        $this->init_hooks();
    }

    private function init_hooks() {
        // Purchase complete
        add_action('edd_complete_purchase', [$this, 'track_purchase'], 10, 3);

        // Add to cart
        add_action('edd_post_add_to_cart', [$this, 'track_add_to_cart'], 10, 2);

        // View download
        add_action('edd_download_before', [$this, 'track_view_item']);
    }

    public function track_purchase($payment_id, $payment, $customer) {
        try {
            // Extract order data using adapter
            $order_data = $this->adapter->extract_order($payment_id);

            // Build purchase event
            $event_builder = $this->core->get_service('event_builder');
            $event_data = $event_builder->build_event('purchase', $order_data);

            // Record event
            $event_recorder = $this->core->get_service('event_recorder');
            $event_recorder->record($event_data);

        } catch (Exception $e) {
            $logger = $this->core->get_service('logger');
            $logger->error('EDD purchase tracking failed: ' . $e->getMessage());
        }
    }

    public function track_add_to_cart($download_id, $options) {
        try {
            $product_data = $this->adapter->extract_product($download_id, $options);

            $event_builder = $this->core->get_service('event_builder');
            $event_data = $event_builder->build_event('add_to_cart', [
                'currency' => edd_get_currency(),
                'value' => edd_get_download_price($download_id),
                'items' => [$product_data],
            ]);

            $event_recorder = $this->core->get_service('event_recorder');
            $event_recorder->record($event_data);

        } catch (Exception $e) {
            $logger = $this->core->get_service('logger');
            $logger->error('EDD add to cart tracking failed: ' . $e->getMessage());
        }
    }

    public function track_view_item($download_id) {
        try {
            $product_data = $this->adapter->extract_product($download_id);

            $event_builder = $this->core->get_service('event_builder');
            $event_data = $event_builder->build_event('view_item', [
                'currency' => edd_get_currency(),
                'value' => edd_get_download_price($download_id),
                'items' => [$product_data],
            ]);

            $event_recorder = $this->core->get_service('event_recorder');
            $event_recorder->record($event_data);

        } catch (Exception $e) {
            $logger = $this->core->get_service('logger');
            $logger->error('EDD view item tracking failed: ' . $e->getMessage());
        }
    }
}
```

---

### **Example 3: Custom Event Module**

```php
<?php
/**
 * Plugin Name: TrackSure - Form Tracking
 * Description: Track form submissions and abandonment
 * Version: 1.0.0
 * Requires Plugins: tracksure
 */

if (!defined('ABSPATH')) exit;

define('TRACKSURE_FORMS_VERSION', '1.0.0');
define('TRACKSURE_FORMS_DIR', plugin_dir_path(__FILE__));

add_action('init', function() {
    if (!function_exists('tracksure')) {
        return;
    }

    do_action('tracksure_register_module', 'tracksure-forms', __FILE__, [
        'name' => 'TrackSure Form Tracking',
        'version' => TRACKSURE_FORMS_VERSION,
        'class_name' => 'TrackSure_Forms_Module',
        'file_path' => TRACKSURE_FORMS_DIR . 'includes/class-module.php',
    ]);
}, 4);
```

**File**: `includes/class-module.php`

```php
<?php

class TrackSure_Forms_Module implements TrackSure_Module_Interface {

    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function get_id() {
        return 'tracksure-forms';
    }

    public function get_name() {
        return 'TrackSure Form Tracking';
    }

    public function get_version() {
        return TRACKSURE_FORMS_VERSION;
    }

    public function get_config() {
        return [
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'version' => $this->get_version(),
        ];
    }

    public function init() {
        // Enqueue frontend tracking script
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // REST API endpoint for form events
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }

    public function register_capabilities() {
        // Register custom events
        add_filter('tracksure_loaded_events', [$this, 'register_events']);

        // Register custom parameters
        add_filter('tracksure_loaded_parameters', [$this, 'register_parameters']);
    }

    public function register_events($events) {
        $events['form_start'] = [
            'name' => 'form_start',
            'display_name' => 'Form Started',
            'category' => 'engagement',
            'required_params' => ['form_id'],
            'optional_params' => ['form_name', 'form_type'],
        ];

        $events['form_submit'] = [
            'name' => 'form_submit',
            'display_name' => 'Form Submitted',
            'category' => 'conversion',
            'required_params' => ['form_id'],
            'optional_params' => ['form_name', 'form_fields', 'form_type'],
        ];

        $events['form_abandon'] = [
            'name' => 'form_abandon',
            'display_name' => 'Form Abandoned',
            'category' => 'engagement',
            'required_params' => ['form_id', 'abandon_field'],
            'optional_params' => ['form_name', 'fields_completed'],
        ];

        return $events;
    }

    public function register_parameters($params) {
        $params['form_id'] = [
            'name' => 'form_id',
            'display_name' => 'Form ID',
            'type' => 'string',
            'category' => 'form',
        ];

        $params['form_name'] = [
            'name' => 'form_name',
            'display_name' => 'Form Name',
            'type' => 'string',
            'category' => 'form',
        ];

        $params['form_fields'] = [
            'name' => 'form_fields',
            'display_name' => 'Form Fields Count',
            'type' => 'integer',
            'category' => 'form',
        ];

        return $params;
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'tracksure-forms',
            TRACKSURE_FORMS_URL . 'assets/js/form-tracking.js',
            ['ts-web'],
            TRACKSURE_FORMS_VERSION,
            true
        );
    }

    public function register_endpoints() {
        register_rest_route('tracksure-forms/v1', '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_form_event'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_form_event($request) {
        $params = $request->get_json_params();

        $event_builder = $this->core->get_service('event_builder');
        $event_data = $event_builder->build_event($params['event_name'], $params);

        $event_recorder = $this->core->get_service('event_recorder');
        $event_id = $event_recorder->record($event_data);

        return ['success' => true, 'event_id' => $event_id];
    }
}
```

**File**: `assets/js/form-tracking.js`

```javascript
(function () {
  // Track form start
  document.querySelectorAll("form").forEach((form) => {
    let started = false;

    form.addEventListener(
      "focus",
      () => {
        if (!started) {
          started = true;
          trackSure.track("form_start", {
            form_id: form.id || "unknown",
            form_name: form.name || form.id || "unknown",
          });
        }
      },
      true,
    );

    // Track form submit
    form.addEventListener("submit", () => {
      trackSure.track("form_submit", {
        form_id: form.id || "unknown",
        form_name: form.name || form.id || "unknown",
        form_fields: form.elements.length,
      });
    });
  });
})();
```

---

## 📖 **See Also**

- **[ADAPTER_DEVELOPMENT.md](ADAPTER_DEVELOPMENT.md)** - Creating ecommerce adapters
- **[DESTINATION_DEVELOPMENT.md](DESTINATION_DEVELOPMENT.md)** - Creating ad platform destinations
- **[HOOKS_AND_FILTERS.md](HOOKS_AND_FILTERS.md)** - All available hooks
- **[CLASS_REFERENCE.md](CLASS_REFERENCE.md)** - Core classes reference
- **[CODE_ARCHITECTURE.md](CODE_ARCHITECTURE.md)** - System architecture

---

**Need Help?** Module development questions? Check the [TrackSure community forum](#) or [GitHub discussions](#)!
