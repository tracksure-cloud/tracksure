/**
 * TrackSure Goal Constants
 * 
 * Shared constants used across JavaScript, React, and PHP for goal tracking.
 * This ensures consistency between frontend and backend evaluation.
 * 
 * @since 2.0.0
 */

/* eslint-disable security/detect-object-injection */
(function(window) {
    'use strict';

    /**
     * Mapping between trigger types and event names.
     * Used by both JavaScript tracker and PHP evaluator for consistency.
     * 
     * Key: trigger_type (what users select in UI)
     * Value: array of event_name(s) that should trigger this goal
     */
    const TRIGGER_EVENT_MAP = {
        pageview: ['page_view'],
        click: ['click'],
        form_submit: ['form_submit', 'form_submission'],
        scroll_depth: ['scroll'], // Maps scroll_depth trigger → scroll event
        time_on_page: ['page_exit', 'time_on_page', 'time_on_page_threshold'],
        engagement: ['engagement', 'page_exit'], // engagement evaluates scroll+time from page_exit data
        video_play: ['video_play', 'video_start', 'video_complete'],
        download: ['file_download', 'download'],
        outbound_link: ['outbound_click', 'external_link'],
        custom_event: [] // Custom events match by exact event_name
    };

    /**
     * Supported condition operators.
     * Same list used in JavaScript and PHP for validation.
     */
    const OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'not_contains',
        'starts_with',
        'ends_with',
        'greater_than',
        'less_than',
        'greater_than_or_equal',
        'less_than_or_equal',
        'matches_regex' // Pro feature
    ];

    /**
     * Supported trigger types.
     */
    const TRIGGER_TYPES = [
        'pageview',
        'click',
        'form_submit',
        'scroll_depth',
        'time_on_page',
        'engagement', // ✅ Added: engagement trigger type
        'video_play',
        'download',
        'outbound_link',
        'custom_event'
    ];

    /**
     * Frequency options for goal firing.
     */
    const FREQUENCY_OPTIONS = [
        { value: 'once', label: 'Once per visitor (lifetime)' },
        { value: 'session', label: 'Once per session' },
        { value: 'unlimited', label: 'Unlimited (every time)' }
    ];

    /**
     * Check if trigger type should match event.
     * 
     * @param {string} triggerType - The trigger type from goal (e.g., 'click').
     * @param {string} eventName - The event name from tracker (e.g., 'click').
     * @return {boolean} True if they match.
     */
    function doesTriggerMatchEvent(triggerType, eventName) {
        if (!triggerType || !eventName) {
            return false;
        }

        // Custom events match by exact event_name (handled separately)
        if (triggerType === 'custom_event') {
            return true;
        }

        const eventNames = TRIGGER_EVENT_MAP[triggerType];
        if (!eventNames) {
            return false;
        }

        return eventNames.includes(eventName);
    }

    /**
     * Get event names for a trigger type.
     * 
     * @param {string} triggerType - The trigger type.
     * @return {string[]} Array of event names.
     */
    function getEventNamesForTrigger(triggerType) {
        return TRIGGER_EVENT_MAP[triggerType] || [];
    }

    // Export to global namespace
    window.TrackSureGoalConstants = {
        TRIGGER_EVENT_MAP: TRIGGER_EVENT_MAP,
        OPERATORS: OPERATORS,
        TRIGGER_TYPES: TRIGGER_TYPES,
        FREQUENCY_OPTIONS: FREQUENCY_OPTIONS,
        doesTriggerMatchEvent: doesTriggerMatchEvent,
        getEventNamesForTrigger: getEventNamesForTrigger
    };

})(window);
