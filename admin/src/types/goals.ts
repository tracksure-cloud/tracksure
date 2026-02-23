/**
 * Goal System Type Definitions
 * 
 * Centralized type definitions for the TrackSure goals system.
 * Used across React components and data files.
 * 
 * @since 2.1.0
 * @package TrackSure
 */

/**
 * Goal category types - simplified from 15 personas to 4 core categories
 */
export type GoalCategory = 'engagement' | 'leads' | 'ecommerce' | 'content';

/**
 * Trigger types for goal evaluation
 */
export type TriggerType = 
  | 'pageview' 
  | 'click' 
  | 'form_submit' 
  | 'scroll_depth' 
  | 'time_on_page' 
  | 'custom_event' 
  | 'video_play' 
  | 'download' 
  | 'outbound_link';

/**
 * Condition operators for goal matching
 */
export type ConditionOperator = 
  | 'equals' 
  | 'not_equals' 
  | 'contains' 
  | 'not_contains' 
  | 'starts_with' 
  | 'ends_with' 
  | 'matches_regex' 
  | 'greater_than' 
  | 'less_than' 
  | 'greater_than_or_equal' 
  | 'less_than_or_equal';

/**
 * Value type for goal conversions
 */
export type ValueType = 'none' | 'fixed' | 'dynamic';

/**
 * Match logic for multiple conditions
 */
export type MatchLogic = 'all' | 'any';

/**
 * Goal condition interface
 * Defines a single condition that must be met for goal conversion
 */
export interface GoalCondition {
  /** Parameter name to check (e.g., 'page_url', 'form_name') */
  param: string;
  /** Comparison operator */
  operator: ConditionOperator;
  /** Value to compare against */
  value: string | number;
}

/**
 * Trigger configuration for specific trigger types
 */
export interface TriggerConfig {
  /** CSS selector for click/element triggers */
  css_selector?: string;
  /** URL pattern for pageview triggers */
  url_pattern?: string;
  /** Form ID for form_submit triggers */
  form_id?: string;
  /** Scroll depth percentage (0-100) */
  scroll_depth?: number;
  /** Time in seconds for time_on_page triggers */
  time_seconds?: number;
}

/**
 * Goal template interface
 * Pre-configured goal that users can quickly set up
 */
export interface GoalTemplate {
  /** Unique template identifier */
  id: string;
  /** Display name */
  name: string;
  /** Description of what this goal tracks */
  description: string;
  /** Event name that triggers this goal */
  event_name: string;
  /** Trigger type */
  trigger_type: TriggerType;
  /** Goal category */
  category: GoalCategory;
  /** Priority for sorting (1 = highest) */
  priority: number;
  /** Whether this is a recommended template */
  recommended: boolean;
  /** Icon name (Lucide React icon) */
  icon: string;
  /** Conditions that must be met */
  conditions: GoalCondition[];
  /** How to match conditions (default: 'all') */
  match_logic?: MatchLogic;
  /** Additional trigger-specific configuration */
  trigger_config?: TriggerConfig;
  /** How value is determined */
  value_type?: ValueType;
  /** Typical/suggested value for this goal type */
  typical_value?: number;
  /** Whether this is a Pro-only feature */
  is_pro?: boolean;
}

/**
 * Goal interface (matches database schema)
 * Represents a configured goal in the system
 */
export interface Goal {
  /** Unique goal ID (auto-generated) */
  goal_id: number;
  /** Goal name */
  name: string;
  /** Goal description */
  description: string;
  /** Event name that triggers this goal */
  event_name: string;
  /** Trigger type */
  trigger_type: TriggerType;
  /** Goal category (optional - not stored in database yet) */
  category?: GoalCategory;
  /** Conditions (stored as JSON) */
  conditions: GoalCondition[];
  /** Condition match logic */
  match_logic: MatchLogic;
  /** Value type */
  value_type: ValueType;
  /** Fixed value (if value_type is 'fixed') */
  value?: number;
  /** Attribution window in days (optional - not currently used) */
  attribution_window?: number;
  /** Conversion frequency: 'once' | 'unlimited' */
  frequency: string;
  /** Whether goal is active */
  is_active: boolean;
  /** Creation timestamp */
  created_at: string;
  /** Last update timestamp */
  updated_at: string;
}

/**
 * Goal form data type
 * Used for creating/editing goals (omits database-generated fields)
 */
export type GoalFormData = Omit<Goal, 'goal_id' | 'created_at' | 'updated_at'>;

/**
 * Goal with runtime statistics
 * Extends Goal with performance metrics
 */
export interface GoalWithStats extends Goal {
  /** Total conversions */
  conversions?: number;
  /** Conversion rate (conversions / sessions) */
  conversion_rate?: number;
  /** Total value generated */
  total_value?: number;
  /** Trend indicator (percentage change) */
  trend?: number;
}

/**
 * Category metadata interface
 */
export interface CategoryMetadata {
  /** Category identifier */
  id: GoalCategory;
  /** Display label */
  label: string;
  /** Description */
  description: string;
  /** Icon name */
  icon: string;
  /** Color for UI (hex or CSS variable) */
  color: string;
  /** Number of templates in this category */
  templateCount?: number;
}

/**
 * Goal filter options
 */
export interface GoalFilters {
  /** Search query */
  search?: string;
  /** Filter by trigger type */
  trigger_type?: TriggerType;
  /** Filter by status */
  is_active?: boolean;
  /** Filter by category */
  category?: GoalCategory;
  /** Sort field */
  sort?: 'name' | 'created_at' | 'conversions' | 'conversion_rate';
  /** Sort order */
  sortOrder?: 'asc' | 'desc';
}

/**
 * Goal analytics data
 */
export interface GoalAnalytics {
  /** Goal ID */
  goal_id: number;
  /** Total conversions */
  total_conversions: number;
  /** Conversion rate */
  conversion_rate: number;
  /** Total value */
  total_value: number;
  /** Daily conversion trend (last 30 days) */
  daily_trend: Array<{ date: string; conversions: number }>;
  /** Top traffic sources */
  top_sources: Array<{ source: string; conversions: number; percentage: number }>;
  /** Device breakdown */
  devices: Array<{ device: string; conversions: number; percentage: number }>;
  /** Top converting pages */
  top_pages: Array<{ page_url: string; conversions: number; percentage: number }>;
}

/**
 * Goals overview dashboard data
 */
export interface GoalsOverview {
  /** Total conversions across all goals */
  total_conversions: number;
  /** Overall conversion rate */
  conversion_rate: number;
  /** Total value across all goals */
  total_value: number;
  /** Number of active goals */
  active_goals: number;
  /** Trend data for conversions */
  conversions_trend: {
    previous_period: number;
  };
  /** Trend data for value */
  value_trend: {
    previous_period: number;
  };
  /** Trend data for conversion rate */
  rate_trend: {
    previous_period: number;
  };
  /** Daily conversions (last 30 days) */
  daily_conversions: Array<{ date: string; conversions: number }>;
  /** Top 5 performing goals */
  top_goals: Array<{
    goal_id: number;
    name: string;
    trigger_type: string;
    conversions: number;
    value: number;
  }>;
}

/**
 * Conversion record interface
 */
export interface Conversion {
  /** Conversion ID */
  conversion_id: number;
  /** Goal ID */
  goal_id: number;
  /** Visitor ID */
  visitor_id: string;
  /** Session ID */
  session_id: string;
  /** Event ID that triggered conversion */
  event_id: number;
  /** Conversion value */
  value: number;
  /** Attribution source */
  attribution_source?: string;
  /** Attribution medium */
  attribution_medium?: string;
  /** Attribution campaign */
  attribution_campaign?: string;
  /** Conversion timestamp (UTC string) */
  converted_at: string;
}
