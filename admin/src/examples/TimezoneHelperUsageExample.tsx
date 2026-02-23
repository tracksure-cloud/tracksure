/**
 * TrackSure Timezone Helper Usage Examples
 * 
 * Shows how to use timezoneHelpers.ts in React components to display
 * UTC timestamps in user's WordPress timezone.
 */

import React from 'react';
import { 
  formatUserTime, 
  formatTimeOnly, 
  formatDateOnly, 
  getRelativeTime,
  useUserTimezone,
  useFormattedTime 
} from '@/utils/timezoneHelpers';

// ============================================
// EXAMPLE 1: Display Event Time in User Timezone
// ============================================
interface Event {
  event_id: string;
  event_name: string;
  occurred_at: string;  // UTC timestamp from database (e.g., "2026-02-11T18:30:45Z")
  page_url: string;
}

export function EventRow({ event }: { event: Event }) {
  const timezone = useUserTimezone(); // Gets WordPress timezone setting
  
  // Format the UTC timestamp to user's timezone
  const displayTime = formatUserTime(event.occurred_at, timezone);
  // UK user (Europe/London) sees: "Feb 11, 2026 6:30 PM"
  // US user (America/New_York) sees: "Feb 11, 2026 1:30 PM"
  // UTC user sees: "Feb 11, 2026 6:30 PM"
  
  return (
    <tr>
      <td>{event.event_name}</td>
      <td>{displayTime}</td>
      <td>{event.page_url}</td>
    </tr>
  );
}

// ============================================
// EXAMPLE 2: Using React Hook (Simpler)
// ============================================
export function EventRowSimple({ event }: { event: Event }) {
  // Hook automatically gets timezone and formats
  const displayTime = useFormattedTime(event.occurred_at);
  
  return (
    <tr>
      <td>{event.event_name}</td>
      <td>{displayTime}</td>
    </tr>
  );
}

// ============================================
// EXAMPLE 3: Display Relative Time ("2 hours ago")
// ============================================
export function RecentEventCard({ event }: { event: Event }) {
  const relativeTime = getRelativeTime(event.occurred_at);
  // Returns: "2 hours ago", "5 minutes ago", "3 days ago", etc.
  
  return (
    <div className="event-card">
      <h3>{event.event_name}</h3>
      <p className="timestamp">{relativeTime}</p>
    </div>
  );
}

// ============================================
// EXAMPLE 4: Separate Date and Time
// ============================================
export function EventDetailView({ event }: { event: Event }) {
  const timezone = useUserTimezone();
  
  const date = formatDateOnly(event.occurred_at, timezone);
  // Returns: "2026-02-11" (YYYY-MM-DD format)
  
  const time = formatTimeOnly(event.occurred_at, timezone);
  // Returns: "06:30:45 PM" (HH:MM:SS AM/PM format)
  
  return (
    <div>
      <div>Date: {date}</div>
      <div>Time: {time}</div>
    </div>
  );
}

// ============================================
// EXAMPLE 5: Journey Timeline (Multiple Events)
// ============================================
interface JourneyEvent {
  event_id: string;
  event_name: string;
  occurred_at: string;
  page_title: string;
}

export function JourneyTimeline({ events }: { events: JourneyEvent[] }) {
  const timezone = useUserTimezone();
  
  return (
    <div className="timeline">
      {events.map((event) => {
        const time = formatTimeOnly(event.occurred_at, timezone);
        const relative = getRelativeTime(event.occurred_at);
        
        return (
          <div key={event.event_id} className="timeline-item">
            <div className="time">
              {time}
              <span className="relative">({relative})</span>
            </div>
            <div className="details">
              <strong>{event.event_name}</strong>
              <p>{event.page_title}</p>
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ============================================
// EXAMPLE 6: Conversion Table with Timezone
// ============================================
interface Conversion {
  conversion_id: number;
  goal_id: number;
  converted_at: string;  // UTC timestamp from database
  conversion_value: number;
}

export function ConversionTable({ conversions }: { conversions: Conversion[] }) {
  const timezone = useUserTimezone();
  
  return (
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Converted At</th>
          <th>Value</th>
        </tr>
      </thead>
      <tbody>
        {conversions.map((conversion) => (
          <tr key={conversion.conversion_id}>
            <td>{conversion.conversion_id}</td>
            <td>{formatUserTime(conversion.converted_at, timezone)}</td>
            <td>${conversion.conversion_value}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

// ============================================
// EXAMPLE 7: Realtime Dashboard (Auto-updating)
// ============================================

// Placeholder type for example purposes — useRealtimeData would come from a custom hook
type RealtimeEvent = { event_id: string; event_name: string; occurred_at: string };
const useRealtimeData = (): { data?: { recent_events?: RealtimeEvent[] } } => ({ data: undefined });

export function RealtimeEventFeed() {
  const { data } = useRealtimeData();
  const timezone = useUserTimezone();
  
  return (
    <div className="realtime-feed">
      <h3>Recent Events</h3>
      {data?.recent_events?.map((event) => {
        const timeAgo = getRelativeTime(event.occurred_at);
        const exactTime = formatTimeOnly(event.occurred_at, timezone);
        
        return (
          <div key={event.event_id} className="event-item">
            <span className="event-name">{event.event_name}</span>
            <span className="time-ago">{timeAgo}</span>
            <span className="exact-time" title={exactTime}>
              at {exactTime}
            </span>
          </div>
        );
      })}
    </div>
  );
}

// ============================================
// EXAMPLE 8: Chart with Timezone Labels
// ============================================
import { formatForChart } from '@/utils/timezoneHelpers';

interface ChartDataPoint {
  occurred_at: string;
  value: number;
}

export function EventsChart({ dataPoints }: { dataPoints: ChartDataPoint[] }) {
  const timezone = useUserTimezone();
  
  // Prepare chart data with timezone-aware labels
  const _chartData = dataPoints.map((point) => {
    const { hour, label } = formatForChart(point.occurred_at, timezone);
    // hour: 0-23 (24-hour format for calculations)
    // label: "6:30 PM" (formatted for display)
    
    return {
      hour,
      label,
      value: point.value,
    };
  });
  
  return (
    <div className="chart">
      {/* Use your chart library here with chartData */}
      {/* Example: <LineChart data={chartData} xKey="label" yKey="value" /> */}
    </div>
  );
}

// ============================================
// EXAMPLE 9: Handling Null/Undefined Timestamps
// ============================================
export function EventRowSafe({ event }: { event: Event }) {
  const displayTime = useFormattedTime(event.occurred_at);
  // If occurred_at is null/undefined, returns '-'
  
  return (
    <tr>
      <td>{event.event_name}</td>
      <td>{displayTime}</td>  {/* Shows '-' if no timestamp */}
    </tr>
  );
}

// ============================================
// EXAMPLE 10: Custom Timezone Display  
// ============================================
export function EventWithTimezoneInfo({ event }: { event: Event }) {
  const timezone = useUserTimezone();
  const displayTime = formatUserTime(event.occurred_at, timezone);
  
  return (
    <div>
      <div>Event: {event.event_name}</div>
      <div>
        Time: {displayTime}
        <small> ({timezone})</small>
      </div>
    </div>
  );
}
