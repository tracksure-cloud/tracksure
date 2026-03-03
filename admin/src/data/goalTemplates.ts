/**
 * Goal Templates Library
 * 
 * Pre-built goal templates organized into 4 core categories.
 * Simplified from 15 personas to improve usability and maintainability.
 * 
 * @since 2.1.0 - Simplified to 4 categories, 28 core templates
 * @package TrackSure
 */

import type { 
  GoalTemplate, 
  GoalCategory, 
  CategoryMetadata 
} from '@/types/goals';

/**
 * Category metadata with labels, descriptions, and icons
 */
export const GOAL_CATEGORIES: Record<GoalCategory, CategoryMetadata> = {
  engagement: {
    id: 'engagement',
    label: 'Engagement',
    description: 'Track user interactions and content engagement',
    icon: 'MousePointer',
    color: 'var(--color-primary)',
  },
  leads: {
    id: 'leads',
    label: 'Lead Generation',
    description: 'Capture contact requests, forms, and inquiries',
    icon: 'Users',
    color: 'var(--color-success)',
  },
  ecommerce: {
    id: 'ecommerce',
    label: 'Ecommerce',
    description: 'Track purchases, cart actions, and revenue',
    icon: 'ShoppingCart',
    color: 'var(--color-warning)',
  },
  content: {
    id: 'content',
    label: 'Content',
    description: 'Monitor content consumption and downloads',
    icon: 'FileText',
    color: 'var(--color-info)',
  },
};

/**
 * Goal templates organized by category
 * 28 core templates (7 per category) covering most common use cases
 */
export const GOAL_TEMPLATES: GoalTemplate[] = [
  // ============================================================
  // ENGAGEMENT TEMPLATES (7)
  // ============================================================
  {
    id: 'eng_time_on_page',
    name: 'Deep Content Engagement',
    description: 'Visitors who spent 3+ minutes on a page',
    event_name: 'page_exit',
    trigger_type: 'time_on_page',
    category: 'engagement',
    priority: 1,
    recommended: true,
    icon: 'Timer',
    conditions: [
      { param: 'time_on_page', operator: 'greater_than_or_equal', value: 180 },
    ],
    trigger_config: {
      time_seconds: 180,
    },
  },
  {
    id: 'eng_scroll_depth',
    name: 'Read to Bottom',
    description: 'Visitors who scrolled to 80% of page',
    event_name: 'scroll',
    trigger_type: 'scroll_depth',
    category: 'engagement',
    priority: 2,
    recommended: true,
    icon: 'ArrowDown',
    conditions: [
      { param: 'scroll_depth', operator: 'greater_than_or_equal', value: 80 },
    ],
    trigger_config: {
      scroll_depth: 80,
    },
  },
  {
    id: 'eng_video_complete',
    name: 'Video Completed',
    description: 'Track video completion',
    event_name: 'video_complete',
    trigger_type: 'video_play',
    category: 'engagement',
    priority: 3,
    recommended: true,
    icon: 'Film',
    conditions: [],
  },
  {
    id: 'eng_outbound_click',
    name: 'Outbound Link Click',
    description: 'Track external link clicks (affiliate, partner sites)',
    event_name: 'outbound_click',
    trigger_type: 'outbound_link',
    category: 'engagement',
    priority: 4,
    recommended: false,
    icon: 'ExternalLink',
    conditions: [],
  },
  {
    id: 'eng_social_share',
    name: 'Social Share Click',
    description: 'Track content shares on social media',
    event_name: 'click',
    trigger_type: 'click',
    category: 'engagement',
    priority: 5,
    recommended: false,
    icon: 'Share2',
    conditions: [
      { param: 'element_class', operator: 'contains', value: 'share' },
    ],
    trigger_config: {
      css_selector: '.share-button, .social-share',
    },
  },
  {
    id: 'eng_search',
    name: 'Site Search Used',
    description: 'Track what visitors are searching for',
    event_name: 'search',
    trigger_type: 'custom_event',
    category: 'engagement',
    priority: 6,
    recommended: false,
    icon: 'Search',
    conditions: [],
  },
  {
    id: 'eng_pricing_view',
    name: 'Pricing Page Viewed',
    description: 'High-intent visitors checking pricing',
    event_name: 'page_view',
    trigger_type: 'pageview',
    category: 'engagement',
    priority: 7,
    recommended: true,
    icon: 'DollarSign',
    conditions: [
      { param: 'page_url', operator: 'contains', value: '/pricing' },
    ],
  },

  // ============================================================
  // LEAD GENERATION TEMPLATES (7)
  // ============================================================
  {
    id: 'lead_contact_form',
    name: 'Contact Form Submitted',
    description: 'Track contact form submissions',
    event_name: 'form_submit',
    trigger_type: 'form_submit',
    category: 'leads',
    priority: 1,
    recommended: true,
    icon: 'Mail',
    conditions: [
      { param: 'form_name', operator: 'contains', value: 'contact' },
    ],
    trigger_config: {
      form_id: 'contact-form',
    },
    value_type: 'fixed',
    typical_value: 50,
  },
  {
    id: 'lead_phone_click',
    name: 'Phone Number Clicked',
    description: 'Track when visitors click your phone number',
    event_name: 'click',
    trigger_type: 'click',
    category: 'leads',
    priority: 2,
    recommended: true,
    icon: 'Phone',
    conditions: [
      { param: 'element_type', operator: 'equals', value: 'tel' },
    ],
    trigger_config: {
      css_selector: 'a[href^="tel:"]',
    },
    value_type: 'fixed',
    typical_value: 60,
  },
  {
    id: 'lead_quote_request',
    name: 'Quote Requested',
    description: 'Track quote or estimate requests',
    event_name: 'form_submit',
    trigger_type: 'form_submit',
    category: 'leads',
    priority: 3,
    recommended: true,
    icon: 'FileText',
    conditions: [
      { param: 'form_name', operator: 'contains', value: 'quote' },
    ],
    value_type: 'fixed',
    typical_value: 75,
  },
  {
    id: 'lead_email_click',
    name: 'Email Link Clicked',
    description: 'Track when visitors click your email address',
    event_name: 'click',
    trigger_type: 'click',
    category: 'leads',
    priority: 4,
    recommended: false,
    icon: 'AtSign',
    conditions: [
      { param: 'element_type', operator: 'equals', value: 'mailto' },
    ],
    trigger_config: {
      css_selector: 'a[href^="mailto:"]',
    },
    value_type: 'fixed',
    typical_value: 40,
  },
  {
    id: 'lead_appointment',
    name: 'Appointment Booked',
    description: 'Track appointment/meeting bookings',
    event_name: 'form_submit',
    trigger_type: 'form_submit',
    category: 'leads',
    priority: 5,
    recommended: true,
    icon: 'Calendar',
    conditions: [
      { param: 'form_name', operator: 'contains', value: 'appointment' },
    ],
    value_type: 'fixed',
    typical_value: 100,
  },
  {
    id: 'lead_chat_started',
    name: 'Live Chat Started',
    description: 'Track when visitors initiate live chat',
    event_name: 'chat_started',
    trigger_type: 'custom_event',
    category: 'leads',
    priority: 6,
    recommended: false,
    icon: 'MessageCircle',
    conditions: [],
    value_type: 'fixed',
    typical_value: 45,
  },
  {
    id: 'lead_callback_request',
    name: 'Callback Requested',
    description: 'Track callback form submissions',
    event_name: 'form_submit',
    trigger_type: 'form_submit',
    category: 'leads',
    priority: 7,
    recommended: false,
    icon: 'PhoneCall',
    conditions: [
      { param: 'form_name', operator: 'contains', value: 'callback' },
    ],
    value_type: 'fixed',
    typical_value: 55,
  },

  // ============================================================
  // ECOMMERCE TEMPLATES (7)
  // ============================================================
  {
    id: 'ecom_purchase',
    name: 'Purchase Completed',
    description: 'Track completed transactions and revenue',
    event_name: 'purchase',
    trigger_type: 'custom_event',
    category: 'ecommerce',
    priority: 1,
    recommended: true,
    icon: 'DollarSign',
    conditions: [],
    value_type: 'dynamic',
  },
  {
    id: 'ecom_add_to_cart',
    name: 'Add to Cart',
    description: 'Track product additions to cart',
    event_name: 'add_to_cart',
    trigger_type: 'custom_event',
    category: 'ecommerce',
    priority: 2,
    recommended: true,
    icon: 'ShoppingCart',
    conditions: [],
  },
  {
    id: 'ecom_checkout_start',
    name: 'Checkout Started',
    description: 'Track when customers begin checkout',
    event_name: 'begin_checkout',
    trigger_type: 'custom_event',
    category: 'ecommerce',
    priority: 3,
    recommended: true,
    icon: 'CreditCard',
    conditions: [],
  },
  {
    id: 'ecom_product_view',
    name: 'Product Viewed',
    description: 'Track product page views',
    event_name: 'view_item',
    trigger_type: 'custom_event',
    category: 'ecommerce',
    priority: 4,
    recommended: false,
    icon: 'Package',
    conditions: [],
  },
  {
    id: 'ecom_view_cart',
    name: 'Cart Viewed',
    description: 'Track cart page views',
    event_name: 'view_cart',
    trigger_type: 'custom_event',
    category: 'ecommerce',
    priority: 5,
    recommended: false,
    icon: 'Eye',
    conditions: [],
  },
  {
    id: 'ecom_payment_info',
    name: 'Payment Info Added',
    description: 'Track when payment details are entered',
    event_name: 'add_payment_info',
    trigger_type: 'custom_event',
    category: 'ecommerce',
    priority: 6,
    recommended: false,
    icon: 'Lock',
    conditions: [],
  },
  {
    id: 'ecom_remove_from_cart',
    name: 'Remove from Cart',
    description: 'Track cart abandonment signals',
    event_name: 'remove_from_cart',
    trigger_type: 'custom_event',
    category: 'ecommerce',
    priority: 7,
    recommended: false,
    icon: 'XCircle',
    conditions: [],
  },

  // ============================================================
  // CONTENT TEMPLATES (7)
  // ============================================================
  {
    id: 'content_newsletter',
    name: 'Newsletter Signup',
    description: 'Track newsletter subscriptions',
    event_name: 'form_submit',
    trigger_type: 'form_submit',
    category: 'content',
    priority: 1,
    recommended: true,
    icon: 'Newspaper',
    conditions: [
      { param: 'form_name', operator: 'contains', value: 'newsletter' },
    ],
    trigger_config: {
      form_id: 'newsletter-form',
    },
    value_type: 'fixed',
    typical_value: 10,
  },
  {
    id: 'content_download',
    name: 'Resource Downloaded',
    description: 'Track PDF, ebook, or template downloads',
    event_name: 'file_download',
    trigger_type: 'download',
    category: 'content',
    priority: 2,
    recommended: true,
    icon: 'Download',
    conditions: [],
    value_type: 'fixed',
    typical_value: 15,
  },
  {
    id: 'content_ebook',
    name: 'Ebook Downloaded',
    description: 'Track ebook or guide downloads',
    event_name: 'file_download',
    trigger_type: 'download',
    category: 'content',
    priority: 3,
    recommended: true,
    icon: 'BookOpen',
    conditions: [
      { param: 'file_name', operator: 'contains', value: '.pdf' },
    ],
    value_type: 'fixed',
    typical_value: 20,
  },
  {
    id: 'content_case_study',
    name: 'Case Study Viewed',
    description: 'Track case study page views',
    event_name: 'page_view',
    trigger_type: 'pageview',
    category: 'content',
    priority: 4,
    recommended: false,
    icon: 'BarChart2',
    conditions: [
      { param: 'page_url', operator: 'contains', value: '/case-stud' },
    ],
  },
  {
    id: 'content_webinar',
    name: 'Webinar Registration',
    description: 'Track webinar signups',
    event_name: 'form_submit',
    trigger_type: 'form_submit',
    category: 'content',
    priority: 5,
    recommended: false,
    icon: 'Video',
    conditions: [
      { param: 'form_name', operator: 'contains', value: 'webinar' },
    ],
    value_type: 'fixed',
    typical_value: 30,
  },
  {
    id: 'content_whitepaper',
    name: 'Whitepaper Downloaded',
    description: 'Track whitepaper downloads',
    event_name: 'file_download',
    trigger_type: 'download',
    category: 'content',
    priority: 6,
    recommended: false,
    icon: 'FileDown',
    conditions: [
      { param: 'file_name', operator: 'contains', value: 'whitepaper' },
    ],
    value_type: 'fixed',
    typical_value: 25,
  },
  {
    id: 'content_portfolio',
    name: 'Portfolio Viewed',
    description: 'Track portfolio or work samples page views',
    event_name: 'page_view',
    trigger_type: 'pageview',
    category: 'content',
    priority: 7,
    recommended: false,
    icon: 'Folder',
    conditions: [
      { param: 'page_url', operator: 'contains', value: '/portfolio' },
    ],
  },
];

/**
 * Get all templates (flat array)
 */
export const getAllTemplates = (): GoalTemplate[] => {
  return GOAL_TEMPLATES;
};

/**
 * Get templates by category
 * 
 * @param category - The category to filter by
 * @returns Array of templates in the specified category
 */
export const getTemplatesByCategory = (category: GoalCategory): GoalTemplate[] => {
  return GOAL_TEMPLATES.filter((t) => t.category === category);
};

/**
 * Get recommended templates
 * 
 * @param category - Optional category to filter by
 * @returns Array of recommended templates
 */
export const getRecommendedTemplates = (category?: GoalCategory): GoalTemplate[] => {
  const templates = category 
    ? getTemplatesByCategory(category) 
    : GOAL_TEMPLATES;
  return templates.filter((t) => t.recommended);
};

/**
 * Search templates by name or description
 * 
 * @param query - Search query string
 * @returns Array of matching templates
 */
export const searchTemplates = (query: string): GoalTemplate[] => {
  const lowercaseQuery = query.toLowerCase();
  return GOAL_TEMPLATES.filter(
    (template) =>
      template.name.toLowerCase().includes(lowercaseQuery) ||
      template.description.toLowerCase().includes(lowercaseQuery)
  );
};

/**
 * Get template by ID
 * 
 * @param id - Template ID
 * @returns Template or undefined if not found
 */
export const getTemplateById = (id: string): GoalTemplate | undefined => {
  return GOAL_TEMPLATES.find((t) => t.id === id);
};

/**
 * Get category metadata with template count
 * 
 * @returns Array of category metadata with counts
 */
export const getCategoriesWithCounts = (): CategoryMetadata[] => {
  return Object.values(GOAL_CATEGORIES).map((category) => ({
    ...category,
    templateCount: getTemplatesByCategory(category.id).length,
  }));
};

