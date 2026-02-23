/**
 * Parameter Formatters for TrackSure Event Display
 * 
 * Converts raw technical parameter values into user-friendly display text.
 * Handles form IDs, element paths, prices, timestamps, etc.
 */

import type { IconName } from '../components/ui/Icon';

/**
 * Format a form ID into human-readable text
 */
export const formatFormId = (formId: string | null): string => {
  if (!formId) {return 'Unknown Form';}

  // WordPress block search forms
  if (formId.includes('wp-block-search')) {return 'Search Form';}
  
  // Elegant Themes / Divi forms
  if (formId.includes('et-search-form') || formId.includes('et_search_form')) {return 'Search Form';}
  
  // WooCommerce forms
  if (formId.includes('woocommerce-checkout')) {return 'Checkout Form';}
  if (formId.includes('woocommerce-login')) {return 'Login Form';}
  if (formId.includes('woocommerce-register')) {return 'Registration Form';}
  if (formId.includes('woocommerce-product-search')) {return 'Product Search';}
  if (formId.includes('cart')) {return 'Cart Form';}
  
  // Contact forms
  if (formId.includes('contact-form')) {return 'Contact Form';}
  if (formId.includes('wpcf7')) {return 'Contact Form 7';}
  if (formId.includes('gform')) {return 'Gravity Form';}
  if (formId.includes('wpforms')) {return 'WPForms';}
  if (formId.includes('ninja-forms')) {return 'Ninja Form';}
  if (formId.includes('fluentform')) {return 'Fluent Form';}
  
  // Newsletter/Email forms
  if (formId.includes('mc4wp')) {return 'Mailchimp Form';}
  if (formId.includes('newsletter')) {return 'Newsletter Signup';}
  if (formId.includes('subscribe')) {return 'Subscription Form';}
  
  // Comment forms
  if (formId.includes('comment')) {return 'Comment Form';}
  
  // Admin/System forms
  if (formId.includes('adminbar')) {return 'Admin Bar Form';}
  if (formId.includes('searchform')) {return 'Search Form';}
  
  // If no match, clean up the ID for display
  return formId
    .replace(/wp-block-/g, '')
    .replace(/woocommerce-/g, '')
    .replace(/et-search-form/gi, 'Search Form')
    .replace(/et_search_form/gi, 'Search Form')
    .replace(/__/g, ' ')
    .replace(/-/g, ' ')
    .replace(/\s+/g, ' ')
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
    .trim();
};

/**
 * Format element path into readable location
 */
export const formatElementPath = (path: string | null): string => {
  if (!path) {return 'Unknown Element';}

  // Extract meaningful parts
  if (path.includes('#')) {
    const id = path.split('#')[1].split('>')[0].split('.')[0].trim();
    return id.replace(/-/g, ' ').replace(/_/g, ' ')
      .split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
  }

  // Check for common patterns
  if (path.includes('left-area')) {return 'Product Image';}
  if (path.includes('button') || path.includes('btn')) {return 'Button';}
  if (path.includes('link') || path.includes('<a')) {return 'Link';}
  if (path.includes('img')) {return 'Image';}
  if (path.includes('nav')) {return 'Navigation';}
  if (path.includes('header')) {return 'Header';}
  if (path.includes('footer')) {return 'Footer';}
  if (path.includes('sidebar')) {return 'Sidebar';}

  return 'Page Element';
};

/**
 * Get the configured currency code from WordPress/WooCommerce.
 */
export const getConfigCurrency = (): string => {
  return window.trackSureAdmin?.currency || 'USD';
};

/**
 * Get the configured currency symbol from WordPress/WooCommerce.
 */
export const getConfigCurrencySymbol = (): string => {
  return window.trackSureAdmin?.currencySymbol || '$';
};

/**
 * Format a monetary value using the site's configured currency.
 * This is the SINGLE source of truth for currency formatting across all pages.
 */
export const formatCurrency = (value: number | string | null | undefined, decimals: number = 2): string => {
  const numValue = typeof value === 'string' ? parseFloat(value) : (value ?? 0);
  if (isNaN(numValue)) { return `${getConfigCurrencySymbol()}0.00`; }

  const currency = getConfigCurrency();
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: decimals,
    }).format(numValue);
  } catch {
    // Fallback if Intl doesn't recognise the currency code
    return `${getConfigCurrencySymbol()}${numValue.toFixed(decimals)}`;
  }
};

/**
 * Compact currency format for chart axis labels (e.g. ৳1.2K, ৳3.5M).
 */
export const formatCurrencyCompact = (value: number): string => {
  const sym = getConfigCurrencySymbol();
  if (value >= 1_000_000) { return `${sym}${(value / 1_000_000).toFixed(1)}M`; }
  if (value >= 1_000) { return `${sym}${(value / 1_000).toFixed(1)}K`; }
  return `${sym}${value}`;
};

/**
 * Format a Date to local YYYY-MM-DD string (avoids UTC shift from .toISOString()).
 */
export const formatLocalDate = (date: Date): string => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

/**
 * Format price/monetary value
 * @deprecated Use formatCurrency() instead – this is kept for backward compat.
 */
export const formatPrice = (value: number | string | null, _currency?: string): string => {
  return formatCurrency(value);
};

/**
 * Format scroll depth percentage
 */
export const formatScrollDepth = (depth: number | string | null): string => {
  if (depth === null || depth === undefined) {return '0%';}
  
  const numDepth = typeof depth === 'string' ? parseInt(depth) : depth;
  
  if (isNaN(numDepth)) {return '0%';}
  
  return `${Math.round(numDepth)}%`;
};

/**
 * Format time duration in seconds to readable format
 */
export const formatDuration = (seconds: number | string | null): string => {
  if (seconds === null || seconds === undefined) {return '0s';}
  
  const numSeconds = typeof seconds === 'string' ? parseInt(seconds) : seconds;
  
  if (isNaN(numSeconds) || numSeconds < 0) {return '0s';}
  
  if (numSeconds < 60) {return `${numSeconds}s`;}
  
  const minutes = Math.floor(numSeconds / 60);
  const remainingSeconds = numSeconds % 60;
  
  if (minutes < 60) {
    return remainingSeconds > 0 ? `${minutes}m ${remainingSeconds}s` : `${minutes}m`;
  }
  
  const hours = Math.floor(minutes / 60);
  const remainingMinutes = minutes % 60;
  
  return `${hours}h ${remainingMinutes}m`;
};

/**
 * Format items array for display
 */
export const formatItems = (items: unknown): string => {
  if (!items) {return 'No items';}
  
  // Parse if string
  let itemArray: Array<Record<string, unknown>> = [];
  if (typeof items === 'string') {
    try {
      itemArray = JSON.parse(items);
    } catch {
      return items; // Return as-is if can't parse
    }
  } else if (Array.isArray(items)) {
    itemArray = items;
  } else {
    // Single item object
    itemArray = [items as Record<string, unknown>];
  }
  
  if (itemArray.length === 0) {return 'No items';}
  
  // Format each item
  const formattedItems = itemArray.map((item: Record<string, unknown>) => {
    const name = (item.name || item.item_name || `Product #${item.id || item.item_id || '?'}`) as string;
    const price = item.price !== undefined ? formatCurrency(item.price as number) : '';
    const qty = Number(item.quantity || item.qty || 1);
    
    if (qty > 1) {
      return price ? `${name} (${qty}× ${price})` : `${name} (${qty}×)`;
    }
    return price ? `${name} (${price})` : name;
  });
  
  if (formattedItems.length === 1) {
    return formattedItems[0];
  }
  
  return `${formattedItems.length} items: ${formattedItems.join(', ')}`;
};

/**
 * Format referrer type into user-friendly text
 */
export const formatReferrerType = (type: string | null): string => {
  if (!type) {return 'Direct';}
  
  const types: Record<string, string> = {
    'direct': 'Direct (Typed URL)',
    'search': 'Search Engine',
    'social': 'Social Media',
    'ai_chatbot': 'AI Chatbot',
    'referral': 'Referral',
    'email': 'Email',
  };
  
  return types[type.toLowerCase()] || type;
};

/**
 * Get icon name for source (for use with Icon component)
 */
export const getSourceIcon = (source: string | null, _medium: string | null = null): IconName => {
  if (!source || source === '(direct)') {return 'Link';}
  
  const icons: Record<string, IconName> = {
    // Search engines
    'google': 'Search',
    'bing': 'Search',
    'yahoo': 'Search',
    'duckduckgo': 'Search',
    'baidu': 'Search',
    
    // Social platforms
    'facebook': 'Facebook',
    'instagram': 'Instagram',
    'twitter': 'Twitter',
    'linkedin': 'Linkedin',
    'pinterest': 'Pin',
    'reddit': 'MessageCircle',
    'tiktok': 'Music',
    'youtube': 'Youtube',
    'snapchat': 'Camera',
    'whatsapp': 'MessageCircle',
    
    // AI chatbots
    'chatgpt': 'Bot',
    'claude': 'Bot',
    'perplexity': 'Bot',
    'gemini': 'Bot',
    'copilot': 'Bot',
    
    // Email
    'email': 'Mail',
    'newsletter': 'Mail',
  };
  
  return icons[source.toLowerCase()] || 'Globe';
};

/**
 * Format source with icon (deprecated - use getSourceIcon with Icon component instead)
 * @deprecated
 */
export const formatSourceWithIcon = (source: string | null, medium: string | null = null): string => {
  if (!source || source === '(direct)') {return 'Direct';}
  
  const mediumText = medium && medium !== '(none)' && medium !== 'none' ? ` / ${medium}` : '';
  
  return `${source.charAt(0).toUpperCase() + source.slice(1)}${mediumText}`;
};

/**
 * Format any parameter value based on parameter name
 */
export const formatEventParameter = (paramName: string, paramValue: unknown): string => {
  if (paramValue === null || paramValue === undefined) {return '-';}
  
  const name = paramName.toLowerCase();
  
  // Items array (e-commerce products)
  if (name === 'items') {
    return formatItems(paramValue);
  }
  
  // Form-related
  if (name === 'form_id') {return formatFormId(String(paramValue));}
  if (name === 'form_name' && !paramValue) {return formatFormId(String(paramValue));}
  
  // Element/Path
  if (name === 'element_path') {return formatElementPath(String(paramValue));}
  
  // Price/Money
  if (name === 'value' || name === 'price' || name === 'conversion_value') {
    return formatCurrency(paramValue as string | number);
  }
  if (name === 'tax' || name === 'shipping') {
    return formatCurrency(paramValue as string | number);
  }
  
  // Scroll
  if (name === 'scroll_depth') {return formatScrollDepth(paramValue as string | number);}
  
  // Time
  if (name === 'time_on_page' || name === 'time_seconds' || name === 'engaged_seconds') {
    return formatDuration(paramValue as number);
  }
  if (name === 'time_threshold') {
    return `${String(paramValue)}s threshold`;
  }
  
  // Referrer
  if (name === 'referrer_type') {return formatReferrerType(String(paramValue));}
  if (name === 'referrer_source') {
    const source = String(paramValue);
    return source.charAt(0).toUpperCase() + source.slice(1);
  }
  
  // Boolean
  if (typeof paramValue === 'boolean') {
    return paramValue ? 'Yes' : 'No';
  }
  
  // Default: return as string
  return String(paramValue);
};

/**
 * Get user-friendly parameter label
 */
export const getParameterLabel = (paramName: string): string => {
  const labels: Record<string, string> = {
    'form_id': 'Form',
    'form_name': 'Form Name',
    'form_action': 'Form Action',
    'form_destination': 'Destination',
    'element_path': 'Location',
    'element_type': 'Element',
    'element_id': 'Element ID',
    'element_text': 'Text',
    'link_url': 'Link',
    'scroll_depth': 'Scroll Depth',
    'time_on_page': 'Time on Page',
    'time_seconds': 'Duration',
    'time_threshold': 'Threshold',
    'engaged_seconds': 'Engaged Time',
    'value': 'Value',
    'price': 'Price',
    'conversion_value': 'Value',
    'currency': 'Currency',
    'tax': 'Tax',
    'shipping': 'Shipping',
    'transaction_id': 'Order #',
    'item_id': 'Item ID',
    'item_name': 'Product',
    'item_category': 'Category',
    'quantity': 'Quantity',
    'search_term': 'Search Query',
    'video_url': 'Video',
    'video_percent': 'Progress',
    'file_name': 'File',
    'referrer_type': 'Referrer Type',
    'referrer_source': 'Source',
    'referrer_medium': 'Medium',
    'utm_source': 'UTM Source',
    'utm_medium': 'UTM Medium',
    'utm_campaign': 'Campaign',
    'is_returning': 'Returning Visitor',
  };
  
  return labels[paramName] || paramName.replace(/_/g, ' ')
    .split(' ')
    .map(w => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ');
};
