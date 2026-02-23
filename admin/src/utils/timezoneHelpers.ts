/**
 * Timezone Display Helpers for TrackSure
 * 
 * ARCHITECTURE OVERVIEW:
 * =====================
 * TrackSure uses a simple, reliable 3-step approach for global timezone support:
 * 
 * 1. STORAGE (Backend):
 *    - Browser SDK captures events with Unix timestamps (UTC) using Date.now() / 1000
 *    - PHP converts to MySQL DATETIME format using gmdate('Y-m-d H:i:s', timestamp)
 *    - Database stores all timestamps in UTC (universal standard)
 *    - MySQL session timezone set to UTC via SET time_zone = '+00:00' (see class-tracksure-db.php)
 * 
 * 2. RETRIEVAL (API):
 *    - SQL queries use UNIX_TIMESTAMP(occurred_at) to convert back to Unix seconds
 *    - Because MySQL session is UTC, conversion is accurate (no timezone interpretation issues)
 *    - API returns raw Unix timestamps to frontend (timezone-agnostic integers)
 * 
 * 3. DISPLAY (Frontend):
 *    - React components receive Unix timestamps from API
 *    - parseTimezoneOffset() converts WordPress timezone string to hours offset
 *    - Formatting functions add offset to UTC timestamp and display in user's local time
 * 
 * GLOBAL COMPATIBILITY:
 * ====================
 * Supports ALL WordPress timezone formats used worldwide:
 * 
 * Format                Example              WordPress Setting
 * ------------------    ------------------   ---------------------------------
 * Numeric offset        "+06:00", "-05:00"   Manual UTC offset selection
 * IANA timezone         "Asia/Dhaka"         City-based timezone selection
 * Etc/GMT format        "Etc/GMT-6"          Alternative UTC offset format
 * UTC variants          "UTC+6", "UTC-5"     UTC-based offset format
 * 
 * DAYLIGHT SAVING TIME (DST):
 * ===========================
 * - Numeric offsets: Fixed offset, NO automatic DST adjustment
 * - IANA timezones: Automatic DST handling via Intl.DateTimeFormat API
 * 
 * EXAMPLE DATA FLOW:
 * ==================
 * User in Bangladesh (UTC+6) visits site at 2:30 PM local time:
 * 
 * 1. Browser captures: 1771219800 (Unix timestamp = 08:30 UTC)
 * 2. Database stores: "2026-02-16 08:30:00" (UTC DATETIME)
 * 3. Query returns: 1771219800 (Unix timestamp via UNIX_TIMESTAMP())
 * 4. React receives: 1771219800
 * 5. WordPress timezone: "+06:00" (Bangladesh Standard Time)
 * 6. parseTimezoneOffset("+06:00") returns: 6 (hours)
 * 7. Display calculation: 08:30 UTC + 6 hours = 14:30 (2:30 PM local) ✅
 * 
 * @package TrackSure
 * @since 2.0.0
 */

import { useApp } from '../contexts/AppContext';

/**
 * Parse WordPress timezone string to numeric hours offset
 * 
 * CORE ALGORITHM:
 * ==============
 * This is the heart of timezone conversion. It handles 4 different timezone format families
 * and returns a consistent numeric offset in hours (with decimal minutes for partial offsets).
 * 
 * SUPPORTED FORMATS:
 * -----------------
 * 1. IANA Timezone Identifiers (e.g., "Asia/Dhaka", "America/New_York")
 *    - Uses Intl.DateTimeFormat API to detect current offset
 *    - Automatically handles DST transitions
 *    - Works for all 600+ IANA timezone identifiers worldwide
 * 
 * 2. Numeric UTC Offsets (e.g., "+06:00", "-05:00", "+05:30")
 *    - WordPress manual offset selection
 *    - Supports hours and minutes (for half-hour timezones like India +05:30)
 *    - No DST handling (fixed offset)
 * 
 * 3. Etc/GMT Format (e.g., "Etc/GMT-6", "Etc/GMT+5")
 *    - Alternative POSIX format (signs are REVERSED from intuition!)
 *    - Etc/GMT-6 means UTC+6, Etc/GMT+5 means UTC-5
 *    - Used by some WordPress configurations
 * 
 * 4. UTC Variants (e.g., "UTC+6", "UTC-5")
 *    - Another common offset format
 *    - Similar to numeric but with "UTC" prefix
 * 
 * WHY INTL.DATETIMEFORMAT FOR IANA TIMEZONES:
 * ===========================================
 * Browser's Intl API is the ONLY reliable way to:
 * - Detect current timezone offset (including DST)
 * - Work consistently across all browsers
 * - Handle all worldwide timezones automatically
 * 
 * We use formatToParts() instead of toLocaleString() because:
 * - formatToParts() returns structured data (no string parsing needed)
 * - toLocaleString() requires string parsing which varies by browser
 * - formatToParts() is more reliable and consistent
 * 
 * @param wpTimezone - WordPress timezone string (from wp_timezone_string())
 * @returns Hours offset as decimal (e.g., 6 for UTC+6, 5.5 for UTC+5:30, -5 for UTC-5)
 * 
 * @example
 * parseTimezoneOffset("+06:00")        // Returns: 6
 * parseTimezoneOffset("Asia/Dhaka")    // Returns: 6
 * parseTimezoneOffset("Etc/GMT-6")     // Returns: 6 (note: signs reversed!)
 * parseTimezoneOffset("UTC+6")         // Returns: 6
 * parseTimezoneOffset("+05:30")        // Returns: 5.5 (India Standard Time)
 * parseTimezoneOffset("America/New_York") // Returns: -5 (or -4 during DST)
 */
function parseTimezoneOffset(wpTimezone: string): number {
  // Handle empty or UTC timezone (no offset needed)
  if (!wpTimezone || wpTimezone === 'UTC') { return 0; }
  
  // ========================================
  // METHOD 1: IANA TIMEZONE IDENTIFIER
  // ========================================
  // Detects: "Asia/Dhaka", "America/New_York", "Europe/London", etc.
  // IANA identifiers always contain a forward slash
  if (wpTimezone.includes('/')) {
    try {
      const date = new Date();
      
      // Step 1: Get current UTC time components
      // These will be our baseline for offset calculation
      const utcYear = date.getUTCFullYear();
      const utcMonth = date.getUTCMonth();
      const utcDay = date.getUTCDate();
      const utcHour = date.getUTCHours();
      const utcMinute = date.getUTCMinutes();
      
      // Step 2: Format the same moment in the target timezone
      // Intl.DateTimeFormat is a browser API that handles all timezone conversions
      // Including automatic DST detection for the given timezone
      const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: wpTimezone,  // Target timezone (e.g., "Asia/Dhaka")
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,  // Use 24-hour format for accurate calculation
      });
      
      // Step 3: Extract structured date/time components
      // formatToParts() returns array of {type, value} objects
      // Example: [{type: 'year', value: '2026'}, {type: 'month', value: '02'}, ...]
      const parts = formatter.formatToParts(date);
      const getValue = (type: string) => {
        const part = parts.find(p => p.type === type);
        return part ? parseInt(part.value, 10) : 0;
      };
      
      // Step 4: Parse the local time components
      const localYear = getValue('year');
      const localMonth = getValue('month') - 1;  // JavaScript months are 0-indexed
      const localDay = getValue('day');
      const localHour = getValue('hour');
      const localMinute = getValue('minute');
      
      // Step 5: Calculate offset by comparing UTC vs local timestamps
      // Both are converted to milliseconds since epoch for precise comparison
      const utcTimestamp = Date.UTC(utcYear, utcMonth, utcDay, utcHour, utcMinute);
      const localTimestamp = Date.UTC(localYear, localMonth, localDay, localHour, localMinute);
      
      // Step 6: Convert millisecond difference to hours
      // Offset = (local time - UTC time) / milliseconds per hour
      // Positive offset = ahead of UTC (e.g., +6 for Bangladesh)
      // Negative offset = behind UTC (e.g., -5 for New York)
      return (localTimestamp - utcTimestamp) / (1000 * 60 * 60);
    } catch (error) {
      // Invalid IANA timezone identifier (e.g., typo in timezone name)
      // Fall back to UTC (0 offset) to prevent display errors
      console.warn(`[TrackSure] Invalid IANA timezone: ${wpTimezone}`, error);
      return 0;
    }
  }
  
  // ========================================
  // METHOD 2: NUMERIC UTC OFFSET
  // ========================================
  // Detects: "+06:00", "-05:00", "+05:30", "-03:30"
  // WordPress manual offset format
  // Regex breakdown: ^([+-])(\d{1,2}):?(\d{2})?$
  //   ([+-])        - Required sign (+ or -)
  //   (\d{1,2})     - 1 or 2 digit hours (0-14)
  //   :?            - Optional colon separator
  //   (\d{2})?      - Optional 2 digit minutes (00-59)
  // eslint-disable-next-line security/detect-unsafe-regex -- Anchored regex with bounded quantifiers, no catastrophic backtracking risk
  const numericMatch = wpTimezone.match(/^([+-])(\d{1,2}):?(\d{2})?$/);
  if (numericMatch) {
    const sign = numericMatch[1] === '+' ? 1 : -1;
    const hours = parseInt(numericMatch[2], 10);
    const minutes = numericMatch[3] ? parseInt(numericMatch[3], 10) : 0;
    // Combine hours and minutes: 5 hours + 30 minutes = 5.5 hours
    return sign * (hours + minutes / 60);
  }
  
  // ========================================
  // METHOD 3: ETC/GMT FORMAT
  // ========================================
  // Detects: "Etc/GMT-6", "Etc/GMT+5"
  // WARNING: Signs are REVERSED in POSIX Etc/GMT format!
  // Etc/GMT-6 = 6 hours AHEAD of GMT (UTC+6)
  // Etc/GMT+5 = 5 hours BEHIND GMT (UTC-5)
  // This is counter-intuitive but part of POSIX standard
  const etcMatch = wpTimezone.match(/^Etc\/GMT([+-])(\d+)$/);
  if (etcMatch) {
    const sign = etcMatch[1] === '+' ? -1 : 1;  // Note: signs reversed!
    const hours = parseInt(etcMatch[2], 10);
    return sign * hours;
  }
  
  // ========================================
  // METHOD 4: UTC VARIANT FORMAT
  // ========================================
  // Detects: "UTC+6", "UTC-5", "UTC+05:30"
  // Alternative format with "UTC" prefix
  // eslint-disable-next-line security/detect-unsafe-regex -- Anchored regex with bounded quantifiers, no catastrophic backtracking risk
  const utcMatch = wpTimezone.match(/^UTC([+-])(\d{1,2})(?::(\d{2}))?$/);
  if (utcMatch) {
    const sign = utcMatch[1] === '+' ? 1 : -1;
    const hours = parseInt(utcMatch[2], 10);
    const minutes = utcMatch[3] ? parseInt(utcMatch[3], 10) : 0;
    return sign * (hours + minutes / 60);
  }
  
  // ========================================
  // FALLBACK: UNRECOGNIZED FORMAT
  // ========================================
  // If none of the above patterns match, log warning and default to UTC
  // This prevents display errors from malformed timezone strings
  console.warn(`[TrackSure] Unrecognized timezone: ${wpTimezone}`);
  return 0;
}

/**
 * Format Unix timestamp to user-friendly date and time string
 * 
 * PURPOSE:
 * ========
 * Primary formatting function for displaying event timestamps throughout the admin UI.
 * Converts UTC Unix timestamp to user's local timezone and formats in readable format.
 * 
 * Used in:
 * - Sessions page (session start times)
 * - Journeys page (journey timestamps)
 * - Goal details (conversion times)
 * - Event lists (event timestamps)
 * 
 * VALIDATION:
 * ===========
 * Validates timestamp range: Jan 1, 2000 (946684800) to Jan 1, 2100 (4102444800)
 * This prevents corrupted database values from showing "Jan 1, 1970" or invalid dates
 * 
 * WHY MANUAL FORMATTING:
 * ======================
 * We use manual date formatting instead of toLocaleString() because:
 * 1. Browser-independent: toLocaleString() format varies by browser
 * 2. Predictable: Same format across all browsers and locales
 * 3. Compact: Fits in table columns without wrapping
 * 
 * @param occurredAt - Unix timestamp in seconds (NOT milliseconds)
 * @param userTimezone - WordPress timezone string (default: 'UTC')
 * @returns Formatted string: "Feb 16, 2026, 11:33 AM" or "-" if invalid
 * 
 * @example
 * formatUserTime(1771219986, "+06:00")     // "Feb 16, 2026, 5:33 PM"
 * formatUserTime(1771219986, "Asia/Dhaka") // "Feb 16, 2026, 5:33 PM"
 * formatUserTime(1771219986, "UTC")        // "Feb 16, 2026, 11:33 AM"
 * formatUserTime(null)                      // "-"
 * formatUserTime(123)                       // "-" (invalid: before 2000)
 */
export function formatUserTime(
  occurredAt: number | string | null | undefined,
  userTimezone: string = 'UTC'
): string {
  try {
    // Handle null/undefined values
    if (!occurredAt) { return '-'; }
    
    // Convert string timestamps to numbers
    const timestamp = typeof occurredAt === 'string' ? parseInt(occurredAt, 10) : occurredAt;
    
    // Validate timestamp range: 2000-01-01 to 2100-01-01
    // This prevents corrupted data from showing invalid dates
    if (isNaN(timestamp) || timestamp < 946684800 || timestamp > 4102444800) {
      console.warn('[TrackSure] Invalid timestamp:', occurredAt);
      return '-';
    }
    
    // Step 1: Convert Unix seconds to JavaScript Date (milliseconds)
    const date = new Date(timestamp * 1000);
    
    // Step 2: Calculate timezone offset and adjust date
    const offsetHours = parseTimezoneOffset(userTimezone);
    const adjustedDate = new Date(date.getTime() + offsetHours * 60 * 60 * 1000);
    
    // Step 3: Extract date components using UTC (since we already adjusted for timezone)
    const year = adjustedDate.getUTCFullYear();
    const month = adjustedDate.getUTCMonth();  // 0-indexed
    const day = adjustedDate.getUTCDate();
    const hours24 = adjustedDate.getUTCHours();
    const minutes = adjustedDate.getUTCMinutes();
    
    // Step 4: Convert 24-hour to 12-hour format
    // 0 → 12 AM, 13 → 1 PM, 23 → 11 PM
    const hours12 = hours24 % 12 || 12;
    const ampm = hours24 >= 12 ? 'PM' : 'AM';
    
    // Step 5: Format components into final string
    const monthNames: readonly string[] = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as const;
    const monthStr = monthNames[month] ?? 'Jan';
    const minutesStr = String(minutes).padStart(2, '0');
    
    // Final format: "Feb 16, 2026, 11:33 AM"
    return `${monthStr} ${day}, ${year}, ${hours12}:${minutesStr} ${ampm}`;
  } catch (error) {
    // Catch any unexpected errors (e.g., Date constructor failures)
    console.error('[TrackSure] Formatting error:', error);
    return '-';
  }
}

/**
 * Format Unix timestamp for chart display with separate hour value
 * 
 * PURPOSE:
 * ========
 * Specialized formatting for chart components that need both:
 * 1. Numeric hour (0-23) for chart positioning/grouping
 * 2. Formatted label for chart tooltips/legends
 * 
 * Used in:
 * - Traffic charts (hourly activity visualization)
 * - Analytics graphs (time-based metrics)
 * - Dashboard widgets (activity timeline)
 * 
 * WHY SEPARATE HOUR:
 * ==================
 * Chart libraries need numeric hour for:
 * - X-axis positioning
 * - Grouping data by hour
 * - Sorting chronologically
 * 
 * Meanwhile, tooltips/legends need human-readable labels
 * 
 * @param occurredAt - Unix timestamp in seconds
 * @param userTimezone - WordPress timezone string (default: 'UTC')
 * @returns Object with numeric hour (0-23) and formatted label
 * 
 * @example
 * formatForChart(1771219986, "+06:00")
 * // Returns: { hour: 17, label: "Feb 16, 5:33 PM" }
 * 
 * formatForChart(1771219986, "UTC")
 * // Returns: { hour: 11, label: "Feb 16, 11:33 AM" }
 */
export function formatForChart(
  occurredAt: number | string | null | undefined,
  userTimezone: string = 'UTC'
): { hour: number; label: string } {
  try {
    if (!occurredAt) { return { hour: 0, label: '-' }; }
    
    const timestamp = typeof occurredAt === 'string' ? parseInt(occurredAt, 10) : occurredAt;
    
    // Validate timestamp range
    if (isNaN(timestamp) || timestamp < 946684800 || timestamp > 4102444800) {
      console.warn('[TrackSure] Invalid timestamp:', occurredAt);
      return { hour: 0, label: '-' };
    }
    
    // Calculate timezone-adjusted date
    const date = new Date(timestamp * 1000);
    const offsetHours = parseTimezoneOffset(userTimezone);
    const adjustedDate = new Date(date.getTime() + offsetHours * 60 * 60 * 1000);
    
    // Extract components
    const hour = adjustedDate.getUTCHours();  // 24-hour format for chart (0-23)
    const month = adjustedDate.getUTCMonth();
    const day = adjustedDate.getUTCDate();
    const minutes = adjustedDate.getUTCMinutes();
    
    // Format label with 12-hour time
    const hours12 = hour % 12 || 12;
    const ampm = hour >= 12 ? 'PM' : 'AM';
    
    const monthNames: readonly string[] = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as const;
    const label = `${monthNames[month] ?? 'Jan'} ${day}, ${hours12}:${String(minutes).padStart(2, '0')} ${ampm}`;
    
    // Return both numeric hour (for chart logic) and label (for display)
    return { hour, label };
  } catch (error) {
    console.error('[TrackSure] Formatting error:', error);
    return { hour: 0, label: '-' };
  }
}

/**
 * Format Unix timestamp to time-only string (no date)
 * 
 * PURPOSE:
 * ========
 * Compact time display when date is shown elsewhere or implied.
 * Includes seconds for precise event timing (e.g., real-time activity feeds).
 * 
 * Used in:
 * - Real-Time page Recent Activity ("Event at 11:33:06 AM")
 * - Activity logs ("Last seen: 11:33:06 AM")
 * - Live visitor tracking
 * 
 * FORMAT:
 * =======
 * 12-hour format with AM/PM and seconds: "11:33:06 AM"
 * Seconds included for real-time precision (unlike formatUserTime)
 * 
 * @param occurredAt - Unix timestamp in seconds
 * @param userTimezone - WordPress timezone string (default: 'UTC')
 * @returns Time string: "11:33:06 AM" or "-" if invalid
 * 
 * @example
 * formatTimeOnly(1771219986, "+06:00")     // "5:33:06 PM"
 * formatTimeOnly(1771219986, "Asia/Dhaka") // "5:33:06 PM"
 * formatTimeOnly(1771219986, "UTC")        // "11:33:06 AM"
 */
export function formatTimeOnly(
  occurredAt: number | string | null | undefined,
  userTimezone: string = 'UTC'
): string {
  try {
    if (!occurredAt) { return '-'; }
    
    const timestamp = typeof occurredAt === 'string' ? parseInt(occurredAt, 10) : occurredAt;
    
    // Validate timestamp range
    if (isNaN(timestamp) || timestamp < 946684800 || timestamp > 4102444800) {
      console.warn('[TrackSure] Invalid timestamp:', occurredAt);
      return '-';
    }
    
    const date = new Date(timestamp * 1000);
    const offsetHours = parseTimezoneOffset(userTimezone);
    const adjustedDate = new Date(date.getTime() + offsetHours * 60 * 60 * 1000);
    
    const hours24 = adjustedDate.getUTCHours();
    const minutes = adjustedDate.getUTCMinutes();
    const seconds = adjustedDate.getUTCSeconds();
    
    const hours12 = hours24 % 12 || 12;
    const ampm = hours24 >= 12 ? 'PM' : 'AM';
    
    const hoursStr = String(hours12).padStart(2, '0');
    const minutesStr = String(minutes).padStart(2, '0');
    const secondsStr = String(seconds).padStart(2, '0');
    
    return `${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;
  } catch (error) {
    console.error('[TrackSure] Formatting error:', error);
    return '-';
  }
}

/**
 * Format Unix timestamp to date-only string (no time)
 * 
 * PURPOSE:
 * ========
 * Compact date display when time is shown elsewhere or not needed.
 * US date format (MM/DD/YYYY) for consistency across the admin interface.
 * 
 * Used in:
 * - Table date columns
 * - Date filters
 * - Report headers
 * - CSV exports
 * 
 * FORMAT CHOICE:
 * ==============
 * MM/DD/YYYY format chosen for:
 * 1. Consistency: Matches WordPress US default format
 * 2. Compactness: Fits in narrow table columns
 * 3. Sortability: Year at end allows visual scanning
 * 
 * Note: Could be made configurable for international users in future versions
 * 
 * @param occurredAt - Unix timestamp in seconds
 * @param userTimezone - WordPress timezone string (default: 'UTC')
 * @returns Date string: "02/16/2026" or "-" if invalid
 * 
 * @example
 * formatDateOnly(1771219986, "+06:00")     // "02/16/2026"
 * formatDateOnly(1771219986, "Asia/Dhaka") // "02/16/2026"
 * formatDateOnly(1771219986, "UTC")        // "02/16/2026"
 */
export function formatDateOnly(
  occurredAt: number | string | null | undefined,
  userTimezone: string = 'UTC'
): string {
  try {
    if (!occurredAt) { return '-'; }
    
    const timestamp = typeof occurredAt === 'string' ? parseInt(occurredAt, 10) : occurredAt;
    
    // Validate timestamp range
    if (isNaN(timestamp) || timestamp < 946684800 || timestamp > 4102444800) {
      console.warn('[TrackSure] Invalid timestamp:', occurredAt);
      return '-';
    }
    
    // Calculate timezone-adjusted date
    const date = new Date(timestamp * 1000);
    const offsetHours = parseTimezoneOffset(userTimezone);
    const adjustedDate = new Date(date.getTime() + offsetHours * 60 * 60 * 1000);
    
    // Extract and format date components
    const year = adjustedDate.getUTCFullYear();
    const month = String(adjustedDate.getUTCMonth() + 1).padStart(2, '0');  // +1 because months are 0-indexed
    const day = String(adjustedDate.getUTCDate()).padStart(2, '0');
    
    // Format: MM/DD/YYYY
    return `${month}/${day}/${year}`;
  } catch (error) {
    console.error('[TrackSure] Formatting error:', error);
    return '-';
  }
}

/**
 * Get human-readable relative time from Unix timestamp
 * 
 * PURPOSE:
 * ========
 * Convert absolute timestamps to user-friendly relative descriptions.
 * Shows how long ago an event occurred in natural language.
 * 
 * Used in:
 * - Activity feeds ("User visited 5 minutes ago")
 * - Real-time page ("Last seen 2 hours ago")
 * - Notification timestamps
 * - Live visitor tracking
 * 
 * TIME THRESHOLDS:
 * ================
 * - Under 60 seconds: "Just now"
 * - Under 60 minutes: "5 minutes ago", "30 minutes ago"
 * - Under 24 hours: "2 hours ago", "10 hours ago"
 * - Under 7 days: "3 days ago", "5 days ago"
 * - 7+ days: "2 weeks ago", "4 weeks ago"
 * 
 * PLURAL HANDLING:
 * ================
 * Automatically handles singular/plural:
 * - "1 minute ago" (singular)
 * - "5 minutes ago" (plural)
 * 
 * NOTE: This function is timezone-independent (relative to current time)
 * 
 * @param occurredAt - Unix timestamp in seconds
 * @returns Relative time string: "5 minutes ago" or "-" if invalid
 * 
 * @example
 * // If current time is 1771220286:
 * getRelativeTime(1771220226)  // "Just now" (60 seconds ago)
 * getRelativeTime(1771219986)  // "5 minutes ago" (300 seconds ago)
 * getRelativeTime(1771216386)  // "1 hour ago" (3600 seconds ago)
 * getRelativeTime(1771133886)  // "1 day ago" (86400 seconds ago)
 */
export function getRelativeTime(occurredAt: number | string | null | undefined): string {
  if (!occurredAt) { return '-'; }
  
  const timestamp = typeof occurredAt === 'string' ? parseInt(occurredAt, 10) : occurredAt;
  
  // Validate timestamp range
  if (isNaN(timestamp) || timestamp < 946684800 || timestamp > 4102444800) {
    console.warn('[TrackSure] Invalid timestamp:', occurredAt);
    return '-';
  }
  
  // Calculate difference from current time
  const now = Math.floor(Date.now() / 1000);  // Current Unix timestamp
  const diff = now - timestamp;  // Seconds elapsed
  
  // Handle edge cases
  if (diff < 0 || diff < 60) { return 'Just now'; }  // Future timestamps or < 1 minute
  
  // Minutes (60 seconds to 59 minutes)
  if (diff < 3600) {
    const minutes = Math.floor(diff / 60);
    return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
  }
  
  // Hours (1 hour to 23 hours)
  if (diff < 86400) {
    const hours = Math.floor(diff / 3600);
    return `${hours} hour${hours > 1 ? 's' : ''} ago`;
  }
  
  // Days (1 day to 6 days)
  if (diff < 604800) {
    const days = Math.floor(diff / 86400);
    return `${days} day${days > 1 ? 's' : ''} ago`;
  }
  
  // Weeks (7+ days)
  const weeks = Math.floor(diff / 604800);
  return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
}

/**
 * React hook to access the WordPress timezone setting
 * 
 * PURPOSE:
 * ========
 * Provides React components with access to the WordPress timezone configuration.
 * Pulls timezone from the global app config (set in wp-admin Settings → General).
 * 
 * Used in:
 * - Components that need timezone for formatting
 * - Custom timestamp displays
 * - Date/time pickers that need default timezone
 * 
 * IMPLEMENTATION:
 * ==============
 * Uses useApp() hook to access global config object.
 * Falls back to 'UTC' if timezone not configured (defensive programming).
 * 
 * @returns WordPress timezone string (e.g., "+06:00", "Asia/Dhaka", "UTC")
 * 
 * @example
 * function MyComponent() {
 *   const timezone = useUserTimezone();
 *   // timezone = "+06:00" or "Asia/Dhaka" etc.
 *   
 *   const formatted = formatUserTime(timestamp, timezone);
 *   return <div>{formatted}</div>;
 * }
 */
export function useUserTimezone(): string {
  const { config } = useApp();
  return config.timezone || 'UTC';  // Fallback to UTC if not set
}

/**
 * React hook to format timestamp with automatic timezone detection
 * 
 * PURPOSE:
 * ========
 * Convenience wrapper combining formatUserTime() + useUserTimezone().
 * Simplifies common pattern: "Format this timestamp in user's timezone".
 * 
 * Used in:
 * - Diagnostics page (quick timestamp display)
 * - Simple components without manual timezone handling
 * - Anywhere formatUserTime() is needed with automatic timezone
 * 
 * WHEN TO USE:
 * ============
 * Use this hook when:
 * - Component only displays ONE timestamp
 * - Don't need to cache timezone for multiple calls
 * - Want simplest possible API
 * 
 * DON'T use when:
 * - Formatting MANY timestamps (cache timezone with useUserTimezone() instead)
 * - Need different format (use formatTimeOnly, formatDateOnly, etc.)
 * - Need custom timezone (call formatUserTime() directly)
 * 
 * @param occurredAt - Unix timestamp in seconds
 * @returns Formatted string: "Feb 16, 2026, 11:33 AM" or "-" if invalid
 * 
 * @example
 * function EventDisplay({ timestamp }) {
 *   const formattedTime = useFormattedTime(timestamp);
 *   return <div>Event occurred: {formattedTime}</div>;
 * }
 * 
 * // BETTER for multiple timestamps:
 * function EventList({ timestamps }) {
 *   const timezone = useUserTimezone();  // Cache timezone once
 *   return timestamps.map(ts => (
 *     <div key={ts}>{formatUserTime(ts, timezone)}</div>
 *   ));
 * }
 */
export function useFormattedTime(occurredAt: number | string | null | undefined): string {
  const { config } = useApp();
  const timezone = config.timezone || 'UTC';
  return occurredAt ? formatUserTime(occurredAt, timezone) : '-';
}
