/**
 * TrackSure Icon Registry
 * 
 * Centralized mapping of emoji icons to Lucide React icons.
 * This registry is used across the entire application for consistency.
 * 
 * Extensions can reference these icons or register custom ones.
 * 
 * @package TrackSure\Admin
 * @since 2.0.0
 */

import type { IconName } from '../components/ui/Icon';

/**
 * Core icon registry
 * Maps semantic names to Lucide icon names
 */
export const ICON_REGISTRY = {
  // Navigation & Dashboard
  dashboard: 'BarChart2' as IconName,
  overview: 'BarChart2' as IconName,
  realtime: 'Zap' as IconName,
  live: 'Zap' as IconName,
  journeys: 'Map' as IconName,
  sessions: 'Users' as IconName,
  users: 'Users' as IconName,
  visitors: 'Users' as IconName,
  traffic: 'Globe' as IconName,
  acquisition: 'Globe' as IconName,
  pages: 'FileText' as IconName,
  content: 'FileText' as IconName,
  products: 'Package' as IconName,
  goals: 'Target' as IconName,
  dataQuality: 'Shield' as IconName,
  quality: 'Shield' as IconName,
  attribution: 'GitBranch' as IconName,
  insights: 'Lightbulb' as IconName,
  conversion: 'Target' as IconName,
  conversions: 'Target' as IconName,
  
  // Tools & Settings
  diagnostics: 'Search' as IconName,
  settings: 'Settings' as IconName,
  destinations: 'Rocket' as IconName,
  integrations: 'Plug' as IconName,
  
  // Events (from events.json)
  pageView: 'FileText' as IconName,
  click: 'MousePointerClick' as IconName,
  scroll: 'ScrollText' as IconName,
  timeOnPage: 'Timer' as IconName,
  formView: 'Clipboard' as IconName,
  formStart: 'FileEdit' as IconName,
  formSubmit: 'CheckCircle' as IconName,
  elementView: 'Eye' as IconName,
  
  // E-commerce Events
  viewItem: 'Eye' as IconName,
  addToCart: 'ShoppingCart' as IconName,
  removeFromCart: 'Trash2' as IconName,
  viewCart: 'ShoppingBag' as IconName,
  beginCheckout: 'CreditCard' as IconName,
  purchase: 'CreditCard' as IconName,
  refund: 'RotateCcw' as IconName,
  
  // User Events
  login: 'LogIn' as IconName,
  signup: 'UserPlus' as IconName,
  logout: 'LogOut' as IconName,
  
  // Video Events
  videoStart: 'Play' as IconName,
  videoProgress: 'Film' as IconName,
  videoComplete: 'CheckCircle' as IconName,
  
  // Download Events
  download: 'Download' as IconName,
  
  // Outbound Events
  outboundClick: 'ExternalLink' as IconName,
  
  // File Events
  fileDownload: 'FileDown' as IconName,
  
  // Stats & Metrics
  revenue: 'DollarSign' as IconName,
  money: 'DollarSign' as IconName,
  cart: 'ShoppingCart' as IconName,
  trend: 'TrendingUp' as IconName,
  
  // Status Indicators
  success: 'CheckCircle' as IconName,
  error: 'XCircle' as IconName,
  warning: 'AlertTriangle' as IconName,
  info: 'Info' as IconName,
  
  // Channels (from channelHelpers.ts)
  direct: 'Target' as IconName,
  organicSearch: 'Search' as IconName,
  paidSearch: 'DollarSign' as IconName,
  social: 'Share2' as IconName,
  paidSocial: 'DollarSign' as IconName,
  email: 'Mail' as IconName,
  referral: 'Link' as IconName,
  display: 'Monitor' as IconName,
  affiliate: 'Users' as IconName,
  
  // Devices
  desktop: 'Monitor' as IconName,
  mobile: 'Smartphone' as IconName,
  tablet: 'Tablet' as IconName,
  
  // Browsers
  chrome: 'Globe' as IconName,
  firefox: 'Globe' as IconName,
  safari: 'Globe' as IconName,
  edge: 'Globe' as IconName,
  
  // Common UI
  delete: 'Trash2' as IconName,
  edit: 'Edit' as IconName,
  view: 'Eye' as IconName,
  hide: 'EyeOff' as IconName,
  save: 'Save' as IconName,
  cancel: 'X' as IconName,
  close: 'X' as IconName,
  add: 'Plus' as IconName,
  remove: 'Minus' as IconName,
  filter: 'Filter' as IconName,
  sort: 'ArrowUpDown' as IconName,
  refresh: 'RefreshCw' as IconName,
  export: 'Download' as IconName,
  import: 'Upload' as IconName,
  copy: 'Copy' as IconName,
  link: 'Link' as IconName,
  calendar: 'Calendar' as IconName,
  clock: 'Clock' as IconName,
  
  // Navigation
  chevronLeft: 'ChevronLeft' as IconName,
  chevronRight: 'ChevronRight' as IconName,
  chevronUp: 'ChevronUp' as IconName,
  chevronDown: 'ChevronDown' as IconName,
  arrowLeft: 'ArrowLeft' as IconName,
  arrowRight: 'ArrowRight' as IconName,
  arrowUp: 'ArrowUp' as IconName,
  arrowDown: 'ArrowDown' as IconName,
  
  // Extensions can add more icons via registerCustomIcon()
} as const;

/**
 * Custom icon registry for extensions
 */
const customIconRegistry = new Map<string, IconName>();

/**
 * Register a custom icon
 * Used by extensions to add their own icon mappings
 * 
 * @example
 * registerCustomIcon('myFeature', 'Rocket');
 */
export const registerCustomIcon = (key: string, iconName: IconName): void => {
  customIconRegistry.set(key, iconName);
};

/**
 * Get icon name from registry
 * Falls back to a default icon if not found
 * 
 * @example
 * const iconName = getIcon('dashboard'); // Returns 'BarChart2'
 * const customIcon = getIcon('myCustomFeature'); // Returns custom icon or 'HelpCircle'
 */
export const getIcon = (key: string, fallback: IconName = 'HelpCircle'): IconName => {
  // Check core registry
  if (key in ICON_REGISTRY) {
    return ICON_REGISTRY[key as keyof typeof ICON_REGISTRY];
  }
  
  // Check custom registry
  if (customIconRegistry.has(key)) {
    return customIconRegistry.get(key)!;
  }
  
  // Return fallback
  return fallback;
};

/**
 * Event icon mapping
 * Maps event names to icon names for display in journeys, tables, etc.
 */
export const EVENT_ICONS: Record<string, IconName> = {
  // Page events
  'page_view': 'Eye',
  'page_exit': 'LogOut',
  'click': 'MousePointerClick',
  'scroll': 'ArrowDown',
  'scroll_depth_final': 'ArrowDownCircle',
  'time_on_page': 'Clock',
  'time_on_page_threshold': 'Timer',
  
  // Session events
  'session_start': 'PlayCircle',
  'tab_visible': 'Eye',
  'tab_hidden': 'EyeOff',
  
  // Form events
  'form_view': 'FileText',
  'form_start': 'Edit',
  'form_submit': 'Send',
  
  // Element events
  'element_view': 'Eye',
  
  // E-commerce events
  'view_item': 'Package',
  'add_to_cart': 'ShoppingBag',
  'remove_from_cart': 'Trash2',
  'view_cart': 'ShoppingCart',
  'begin_checkout': 'CreditCard',
  'purchase': 'CheckCircle',
  'refund': 'RotateCcw',
  
  // User events
  'login': 'LogIn',
  'signup': 'UserPlus',
  'logout': 'LogOut',
  
  // Video events
  'video_start': 'Play',
  'video_progress': 'PlayCircle',
  'video_complete': 'CheckCircle',
  
  // Download events
  'download': 'Download',
  'file_download': 'Download',
  
  // Outbound events
  'outbound_click': 'ExternalLink',
  
  // Search events
  'search': 'Search',
  'view_search_results': 'List',
  
  // Social events
  'share': 'Share2',
  'like': 'Heart',
  
  // Performance events
  'page_performance': 'Gauge',
};

/**
 * Channel icon mapping
 * Maps channel names to icon names
 */
export const CHANNEL_ICONS: Record<string, IconName> = {
  'Direct': 'Target',
  'Organic Search': 'Search',
  'Paid Search': 'DollarSign',
  'Social': 'Share2',
  'Paid Social': 'DollarSign',
  'Email': 'Mail',
  'Referral': 'Link',
  'Display': 'Monitor',
  'Affiliate': 'Users',
  'Other': 'HelpCircle',
};

/**
 * Device icon mapping
 */
export const DEVICE_ICONS: Record<string, IconName> = {
  'desktop': 'Monitor',
  'mobile': 'Smartphone',
  'tablet': 'Tablet',
};

/**
 * Export all for convenience
 */
export type { IconName };
