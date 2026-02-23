/**
 * Event Display Helpers for TrackSure
 * 
 * Provides utilities for displaying events with icons, labels, and filtering.
 * Works with the enhanced events.json registry (v1.1.0+).
 */

import type { IconName } from '../config/iconRegistry';
import { EVENT_ICONS } from '../config/iconRegistry';

import eventsRegistry from '../../../registry/events.json';

export interface EventSchema {
  name: string;
  display_name: string;
  icon?: string;
  description: string;
  category: string;
  display_in_journey?: boolean;
  is_conversion?: boolean;
  automatically_collected: boolean;
  required_params: string[];
  optional_params: string[];
}

/**
 * Generic event record from tracking data
 */
export interface EventRecord {
  event_name: string;
  page_title?: string;
  page_url?: string;
  occurred_at?: string;
  conversion_value?: number;
  event_params?: Record<string, unknown>;
  [key: string]: string | number | boolean | Record<string, unknown> | undefined | null;
}

/**
 * Get event schema from registry
 */
export const getEventSchema = (eventName: string): EventSchema | null => {
  const event = eventsRegistry.events.find((e: { name: string }) => e.name === eventName);
  return event || null;
};

/**
 * Get user-friendly display name for event
 */
export const getEventDisplayName = (eventName: string): string => {
  const event = getEventSchema(eventName);
  
  if (event && event.display_name) {
    return event.display_name;
  }
  
  // Fallback: convert snake_case to Title Case
  return eventName
    .replace(/_/g, ' ')
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

/**
 * Get icon for event
 * Returns Lucide icon name instead of emoji
 */
export const getEventIcon = (eventName: string): IconName => {
  // Check EVENT_ICONS registry first
  if (EVENT_ICONS[eventName]) {
    return EVENT_ICONS[eventName];
  }
  
  const event = getEventSchema(eventName);
  
  // Fallback icons based on category
  const categoryIcons: Record<string, IconName> = {
    'engagement': 'MousePointerClick',
    'ecommerce': 'ShoppingCart',
    'conversion': 'CheckCircle',
    'promotion': 'Tag',
    'system': 'Settings',
  };
  
  return event?.category ? (categoryIcons[event.category] || 'MapPin') : 'MapPin';
};

/**
 * Check if event should be displayed in journey timeline
 * Business view: Only shows important business events
 * Debug view: Shows all events
 */
export const shouldDisplayInJourney = (eventName: string): boolean => {
  const event = getEventSchema(eventName);
  
  // If display_in_journey is explicitly set, use that
  if (event && event.display_in_journey !== undefined) {
    return event.display_in_journey;
  }
  
  // BUSINESS VIEW: Only show meaningful business events
  const businessEvents = [
    // Core business events
    'session_start',
    'page_view',
    
    // E-commerce
    'view_item',
    'add_to_cart',
    'remove_from_cart',
    'view_cart',
    'begin_checkout',
    'purchase',
    'refund',
    
    // Forms (important conversions)
    'form_submit',
    
    // Important engagement only
    'outbound_click',
    'file_download',
    'video_play',
    'video_complete',
    
    // Goals/Conversions
    'goal_achieved',
    'conversion',
  ];
  
  return businessEvents.includes(eventName);
};

/**
 * Get category display name
 */
export const getCategoryDisplayName = (category: string): string => {
  const categories: Record<string, string> = {
    'engagement': 'Engagement',
    'ecommerce': 'E-commerce',
    'conversion': 'Conversion',
    'promotion': 'Promotion',
    'system': 'System',
  };
  
  return categories[category] || category;
};

/**
 * Get category color for badges/tags
 */
export const getCategoryColor = (category: string): string => {
  const colors: Record<string, string> = {
    'engagement': '#3b82f6',    // Blue
    'ecommerce': '#10b981',      // Green
    'conversion': '#f59e0b',     // Amber
    'promotion': '#ec4899',      // Pink
    'system': '#6b7280',         // Gray
  };
  
  return colors[category] || '#6b7280';
};

/**
 * Check if event is a conversion
 */
export const isConversionEvent = (eventName: string): boolean => {
  const event = getEventSchema(eventName);
  return event?.is_conversion === true;
};

/**
 * Filter events for journey display
 */
export const filterEventsForJourney = (events: EventRecord[]): EventRecord[] => {
  return events.filter(event => shouldDisplayInJourney(event.event_name));
};

/**
 * Group events by page
 */
export const groupEventsByPage = (events: EventRecord[]): Record<string, EventRecord[]> => {
  const grouped: Record<string, EventRecord[]> = {};
  
  events.forEach(event => {
    const pageKey = event.page_title || event.page_url || 'Unknown Page';
    
    if (!grouped[pageKey]) {
      grouped[pageKey] = [];
    }
    
    grouped[pageKey].push(event);
  });
  
  return grouped;
};

/**
 * Format event for display in timeline
 */
export const formatEventForTimeline = (event: EventRecord): {
  icon: string;
  displayName: string;
  timestamp: string;
  isConversion: boolean;
  category: string;
  shouldDisplay: boolean;
} => {
  return {
    icon: getEventIcon(event.event_name),
    displayName: getEventDisplayName(event.event_name),
    timestamp: event.occurred_at,
    isConversion: isConversionEvent(event.event_name),
    category: getEventSchema(event.event_name)?.category || 'unknown',
    shouldDisplay: shouldDisplayInJourney(event.event_name),
  };
};

/**
 * Get visitor display label
 */
export const getVisitorLabel = (
  visitorId: number,
  isReturning: boolean = false,
  sessionNumber: number = 1,
  userName: string | null = null,
  userEmail: string | null = null
): string => {
  // If user is logged in and we have their info
  if (userName || userEmail) {
    return `${userName || 'User'} (${userEmail || 'Logged In'})`;
  }
  
  // Format visitor ID with leading zeros
  const formattedId = `#${String(visitorId).padStart(4, '0')}`;
  
  // Add context
  if (sessionNumber === 1) {
    return `Visitor ${formattedId} (New)`;
  } else if (isReturning) {
    return `Visitor ${formattedId} (Returning, Session ${sessionNumber})`;
  }
  
  return `Visitor ${formattedId}`;
};

/**
 * Format source/medium display with icon
 * Fixed to prevent confusing combinations like "direct / organic"
 */
export const formatSourceMediumDisplay = (
  source: string | null,
  medium: string | null,
  _referrerType: string | null = null
): string => {
  // Handle null/empty source (treat as direct)
  if (!source || source === '(direct)' || source === 'direct') {
    return 'Direct';
  }
  
  // Note: Icons are now handled by the Icon component in the UI layer
  // This function returns text-only labels
  const sourceLabels: Record<string, string> = {
    // Search engines
    'google': 'Google',
    'bing': 'Bing',
    'yahoo': 'Yahoo',
    'duckduckgo': 'DuckDuckGo',
    'baidu': 'Baidu',
    'yandex': 'Yandex',
    
    // Social platforms
    'facebook': 'Facebook',
    'instagram': 'Instagram',
    'twitter': 'Twitter',
    'linkedin': 'LinkedIn',
    'pinterest': 'Pinterest',
    'reddit': 'Reddit',
    'tiktok': 'TikTok',
    'youtube': 'YouTube',
    'vimeo': 'Vimeo',
    'snapchat': 'Snapchat',
    'whatsapp': 'WhatsApp',
    
    // AI chatbots
    'chatgpt': 'ChatGPT',
    'claude': 'Claude',
    'perplexity': 'Perplexity',
    'gemini': 'Gemini',
    'bard': 'Bard',
    'copilot': 'Copilot',
    
    // Email
    'email': 'Email',
    'newsletter': 'Newsletter',
  };
  
  const displaySource = sourceLabels[source.toLowerCase()] || 
    (source.charAt(0).toUpperCase() + source.slice(1));
  
  // Format medium
  let mediumLabel = '';
  if (medium && medium !== '(none)' && medium !== 'none') {
    const mediumDisplays: Record<string, string> = {
      'organic': 'Organic Search',
      'social': 'Social Media',
      'referral': 'Referral',
      'email': 'Email',
      'cpc': 'Paid Ad',
      'paid': 'Paid Ad',
      'ai': 'AI Chatbot',
    };
    
    mediumLabel = ` / ${mediumDisplays[medium.toLowerCase()] || medium}`;
  }
  // If medium is null/(none), just show source without " / none"
  
  return `${displaySource}${mediumLabel}`;
};

/**
 * Get event statistics
 */
export const getEventStatistics = (events: EventRecord[]): {
  totalEvents: number;
  conversions: number;
  conversionValue: number;
  pagesViewed: number;
  formsViewed: number;
  formsStarted: number;
  formsSubmitted: number;
} => {
  return {
    totalEvents: events.length,
    conversions: events.filter(e => isConversionEvent(e.event_name)).length,
    conversionValue: events
      .filter(e => isConversionEvent(e.event_name))
      .reduce((sum, e) => sum + (e.conversion_value || 0), 0),
    pagesViewed: events.filter(e => e.event_name === 'page_view').length,
    formsViewed: events.filter(e => e.event_name === 'form_view').length,
    formsStarted: events.filter(e => e.event_name === 'form_start').length,
    formsSubmitted: events.filter(e => e.event_name === 'form_submit').length,
  };
};
