/**
 * TrackSure Goals - Front-End Goal Tracking
 * 
 * Monitors user interactions and triggers goal events when conditions match.
 * Handles pageviews, clicks, form submissions, scroll depth, and time on page.
 * 
 * @since 2.0.0 - Major refactor: Fixed data structure consistency
 */

/* eslint-disable no-console, no-unused-vars, security/detect-object-injection, curly, security/detect-non-literal-regexp, no-script-url */
(function() {
    'use strict';

    // Check if TrackSure main tracker is loaded
    if (typeof window.trackSure === 'undefined' || typeof window.tracksure_goals === 'undefined') {
        console.warn('TrackSure Goals: Tracker or goals data not loaded');
        return;
    }

    // Import constants (should be loaded via tracksure-goal-constants.js)
    const { TRIGGER_EVENT_MAP, doesTriggerMatchEvent: _doesTriggerMatchEvent } = window.TrackSureGoalConstants || {};

    if (!TRIGGER_EVENT_MAP) {
        console.error('TrackSure Goals: Goal constants not loaded. Make sure tracksure-goal-constants.js is enqueued first.');
        return;
    }

    const goals = window.tracksure_goals || [];
    const trackedGoals = new Set(); // Prevent duplicate tracking
    const trackedScrollGoals = new Map(); // Track scroll depth progress
    const goalLastFired = new Map(); // Track when goals last fired (for frequency limits)

    /**
     * Check if goal can be fired based on frequency limits
     */
    function canFireGoal(goal) {
        const goalKey = `goal_${goal.goal_id}`;
        
        // Check if already tracked (once per session by default)
        if (trackedGoals.has(goalKey)) {
            // If goal has frequency limit, check if enough time passed
            if (goal.frequency === 'unlimited') {
                return true; // Always allow
            }
            
            if (goal.cooldown_minutes) {
                const lastFired = goalLastFired.get(goalKey);
                if (lastFired) {
                    const minutesSince = (Date.now() - lastFired) / 1000 / 60;
                    return minutesSince >= goal.cooldown_minutes;
                }
            }
            
            return false; // Already tracked this session
        }
        
        return true; // Not tracked yet
    }

    /**
     * Fire a goal event
     */
    function fireGoal(goal, additionalData = {}) {
        const goalKey = `goal_${goal.goal_id}`;
        
        // Check if goal can be fired based on frequency limits
        if (!canFireGoal(goal)) {
            return;
        }

        // Mark as tracked
        trackedGoals.add(goalKey);
        goalLastFired.set(goalKey, Date.now());

        // Build event data
        const eventData = {
            goal_id: goal.goal_id,
            goal_name: goal.name,
            ...additionalData
        };

        // Add conversion value if configured
        if (goal.value_type === 'fixed' && goal.fixed_value) {
            eventData.value = parseFloat(goal.fixed_value);
        }

        // Fire TrackSure event
        window.trackSure.track(goal.event_name, eventData);

        console.log('TrackSure Goals: Fired goal', goal.name, eventData);
    }

    /**
     * Check if element matches selector
     */
    function matchesSelector(element, selector) {
        try {
            return element.matches(selector) || element.closest(selector);
        } catch (e) {
            return false;
        }
    }

    /**
     * Evaluate goal conditions against current page/event.
     * ✅ FIXED: Changed "field" to "param" for consistency with React/PHP
     */
    function evaluateConditions(goal, eventData = {}) {
        if (!goal.conditions || goal.conditions.length === 0) {
            return true; // No conditions = always matches
        }

        const conditions = goal.conditions;
        const matchLogic = goal.match_logic || 'all'; // 'all' or 'any'

        const results = conditions.map(condition => {
            const { param, operator, value } = condition; // ✅ Changed from "field" to "param"

            let actualValue;

            // Get actual value based on param
            switch (param) {
                case 'page_url':
                    actualValue = window.location.href;
                    break;
                case 'page_path':
                    actualValue = window.location.pathname;
                    break;
                case 'page_title':
                    actualValue = document.title;
                    break;
                case 'referrer':
                    actualValue = document.referrer;
                    break;
                case 'element_selector':
                    return eventData.element && matchesSelector(eventData.element, value);
                case 'element_text':
                    return eventData.element && eventData.element.textContent.includes(value);
                default:
                    // Look for param in eventData
                    actualValue = eventData[param];
            }

            // Evaluate operator
            return evaluateOperator(actualValue, operator, value);
        });

        // Apply match logic
        if (matchLogic === 'any') {
            return results.some(r => r === true);
        } else {
            return results.every(r => r === true);
        }
    }

    /**
     * Evaluate a single operator condition.
     * Extracted for reusability and clarity.
     */
    function evaluateOperator(actualValue, operator, expectedValue) {
        switch (operator) {
            case 'equals':
                return actualValue === expectedValue;
            case 'not_equals':
                return actualValue !== expectedValue;
            case 'contains':
                return actualValue && String(actualValue).includes(expectedValue);
            case 'not_contains':
                return actualValue && !String(actualValue).includes(expectedValue);
            case 'starts_with':
                return actualValue && String(actualValue).startsWith(expectedValue);
            case 'ends_with':
                return actualValue && String(actualValue).endsWith(expectedValue);
            case 'matches_regex':
                try {
                    const regex = new RegExp(expectedValue);
                    return regex.test(actualValue);
                } catch (e) {
                    console.warn('TrackSure Goals: Invalid regex pattern:', expectedValue);
                    return false;
                }
            case 'greater_than':
                return parseFloat(actualValue) > parseFloat(expectedValue);
            case 'less_than':
                return parseFloat(actualValue) < parseFloat(expectedValue);
            case 'greater_than_or_equal':
                return parseFloat(actualValue) >= parseFloat(expectedValue);
            case 'less_than_or_equal':
                return parseFloat(actualValue) <= parseFloat(expectedValue);
            default:
                console.warn('TrackSure Goals: Unknown operator:', operator);
                return false;
        }
    }

    /**
     * Initialize pageview goals
     */
    function initPageviewGoals() {
        const pageviewGoals = goals.filter(g => g.trigger_type === 'pageview' && g.is_active);

        pageviewGoals.forEach(goal => {
            if (evaluateConditions(goal)) {
                fireGoal(goal);
            }
        });
    }

    /**
     * Initialize click goals
     */
    function initClickGoals() {
        const clickGoals = goals.filter(g => g.trigger_type === 'click' && g.is_active);

        if (clickGoals.length === 0) return;

        // Listen for all clicks
        document.addEventListener('click', function(e) {
            clickGoals.forEach(goal => {
                if (evaluateConditions(goal, { element: e.target, event: e })) {
                    fireGoal(goal, {
                        clicked_element: e.target.tagName,
                        clicked_text: e.target.textContent.substring(0, 100)
                    });
                }
            });
        }, true);
    }

    /**
     * Initialize form submit goals
     */
    function initFormGoals() {
        const formGoals = goals.filter(g => g.trigger_type === 'form_submit' && g.is_active);

        if (formGoals.length === 0) return;

        // Listen for form submissions
        document.addEventListener('submit', function(e) {
            formGoals.forEach(goal => {
                if (evaluateConditions(goal, { element: e.target, event: e })) {
                    fireGoal(goal, {
                        form_action: e.target.action,
                        form_id: e.target.id,
                        form_name: e.target.name
                    });
                }
            });
        }, true);
    }

    /**
     * Initialize scroll depth goals
     */
    function initScrollGoals() {
        const scrollGoals = goals.filter(g => g.trigger_type === 'scroll_depth' && g.is_active);

        if (scrollGoals.length === 0) return;

        // Track scroll depth
        let ticking = false;

        function checkScrollDepth() {
            const scrollPercent = Math.round(
                (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
            );

            scrollGoals.forEach(goal => {
                // Find scroll_depth condition or use trigger_config
                const scrollCondition = goal.conditions?.find(c => c.param === 'scroll_depth');
                const targetDepth = scrollCondition ? parseInt(scrollCondition.value) 
                    : (goal.trigger_config?.scroll_depth || 75);

                // Check if reached target depth
                if (scrollPercent >= targetDepth) {
                    const goalKey = `scroll_${goal.goal_id}`;
                    if (!trackedScrollGoals.has(goalKey)) {
                        trackedScrollGoals.set(goalKey, true);
                        
                        if (evaluateConditions(goal, { scroll_depth: scrollPercent })) {
                            fireGoal(goal, {
                                scroll_depth: scrollPercent,
                                page_height: document.documentElement.scrollHeight
                            });
                        }
                    }
                }
            });

            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(checkScrollDepth);
                ticking = true;
            }
        }, { passive: true });

        // Check initial position (in case user lands mid-page)
        setTimeout(checkScrollDepth, 1000);
    }

    /**
     * Initialize time on page goals
     */
    function initTimeGoals() {
        const timeGoals = goals.filter(g => g.trigger_type === 'time_on_page' && g.is_active);

        if (timeGoals.length === 0) return;

        timeGoals.forEach(goal => {
            // Find time_on_page condition or use trigger_config
            const timeCondition = goal.conditions?.find(c => c.param === 'time_on_page');
            const targetSeconds = timeCondition ? parseInt(timeCondition.value) 
                : (goal.trigger_config?.time_seconds || 30);

            // Set timer
            setTimeout(() => {
                if (evaluateConditions(goal, { time_on_page: targetSeconds })) {
                    fireGoal(goal, {
                        time_on_page: targetSeconds
                    });
                }
            }, targetSeconds * 1000);
        });
    }

    /**
     * Initialize engagement rate goals (scroll + time combined)
     */
    function initEngagementGoals() {
        const engagementGoals = goals.filter(g => g.trigger_type === 'engagement' && g.is_active);

        if (engagementGoals.length === 0) return;

        const startTime = Date.now();
        let maxScrollDepth = 0;

        // Track max scroll
        function updateMaxScroll() {
            const scrollPercent = Math.round(
                (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
            );
            maxScrollDepth = Math.max(maxScrollDepth, scrollPercent);
        }

        window.addEventListener('scroll', updateMaxScroll, { passive: true });

        // Check engagement periodically
        function checkEngagement() {
            const timeOnPage = Math.round((Date.now() - startTime) / 1000);

            engagementGoals.forEach(goal => {
                const conditions = goal.conditions || [];
                const timeCondition = conditions.find(c => c.param === 'time_on_page');
                const scrollCondition = conditions.find(c => c.param === 'scroll_depth');

                const timeThreshold = timeCondition ? parseInt(timeCondition.value) : 30;
                const scrollThreshold = scrollCondition ? parseInt(scrollCondition.value) : 50;

                if (timeOnPage >= timeThreshold && maxScrollDepth >= scrollThreshold) {
                    fireGoal(goal, {
                        time_on_page: timeOnPage,
                        max_scroll_depth: maxScrollDepth
                    });
                }
            });
        }

        // Check every 5 seconds
        setInterval(checkEngagement, 5000);
    }

    /**
     * Initialize custom event goals
     */
    function initCustomEventGoals() {
        const customGoals = goals.filter(g => g.trigger_type === 'custom_event' && g.is_active);

        if (customGoals.length === 0) return;

        // Listen for custom TrackSure events
        document.addEventListener('trackSureCustomEvent', function(e) {
            const { eventName, eventData } = e.detail || {};

            customGoals.forEach(goal => {
                // Check if event name matches
                if (goal.event_name === eventName || goal.conditions.some(c => c.value === eventName)) {
                    if (evaluateConditions(goal, eventData)) {
                        fireGoal(goal, eventData);
                    }
                }
            });
        });
    }

    /**
     * Initialize video play goals
     */
    function initVideoGoals() {
        const videoGoals = goals.filter(g => g.trigger_type === 'video_play' && g.is_active);

        if (videoGoals.length === 0) return;

        // Track HTML5 videos
        document.querySelectorAll('video').forEach(video => {
            video.addEventListener('play', function() {
                const videoSrc = video.src || video.currentSrc;
                const videoTitle = video.title || video.getAttribute('data-title') || 
                                 video.getAttribute('aria-label') || 'Untitled Video';
                
                videoGoals.forEach(goal => {
                    if (evaluateConditions(goal, { 
                        video_src: videoSrc, 
                        video_title: videoTitle,
                        video_element: video 
                    })) {
                        fireGoal(goal, {
                            video_url: videoSrc,
                            video_title: videoTitle,
                            video_duration: video.duration || 0
                        });
                    }
                });
            });
        });

        // Track YouTube/Vimeo iframes (if title/src matches conditions)
        document.querySelectorAll('iframe[src*="youtube.com"], iframe[src*="vimeo.com"]').forEach(iframe => {
            const iframeSrc = iframe.src;
            const iframeTitle = iframe.title || 'Embedded Video';
            
            videoGoals.forEach(goal => {
                // Check conditions against iframe src/title
                if (evaluateConditions(goal, { 
                    video_src: iframeSrc,
                    video_title: iframeTitle 
                })) {
                    // Fire goal when iframe becomes visible (user likely to play)
                    const observer = new IntersectionObserver((entries) => {
                        if (entries[0].isIntersecting) {
                            fireGoal(goal, {
                                video_url: iframeSrc,
                                video_title: iframeTitle,
                                video_type: iframeSrc.includes('youtube') ? 'youtube' : 'vimeo'
                            });
                            observer.disconnect();
                        }
                    }, { threshold: 0.5 });
                    
                    observer.observe(iframe);
                }
            });
        });
    }

    /**
     * Initialize file download goals
     */
    function initDownloadGoals() {
        const downloadGoals = goals.filter(g => g.trigger_type === 'download' && g.is_active);

        if (downloadGoals.length === 0) return;

        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.href;
            if (!href) return;

            const fileName = href.split('/').pop().split('?')[0]; // Remove query params
            const fileExt = fileName.split('.').pop().toLowerCase();

            const downloadExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'csv', 'ppt', 'pptx', 'txt', 'rtf', 'png', 'jpg', 'jpeg', 'gif', 'svg'];

            if (downloadExts.includes(fileExt)) {
                downloadGoals.forEach(goal => {
                    if (evaluateConditions(goal, { 
                        file_url: href,
                        file_name: fileName,
                        file_type: fileExt,
                        link_text: link.textContent.trim()
                    })) {
                        fireGoal(goal, {
                            file_url: href,
                            file_name: fileName,
                            file_type: fileExt,
                            link_text: link.textContent.trim()
                        });
                    }
                });
            }
        });
    }

    /**
     * Initialize outbound link goals
     */
    function initOutboundLinkGoals() {
        const outboundGoals = goals.filter(g => g.trigger_type === 'outbound_link' && g.is_active);

        if (outboundGoals.length === 0) return;

        const currentDomain = window.location.hostname;

        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.href;
            if (!href || href.startsWith('javascript:') || href.startsWith('#')) return;

            try {
                const linkUrl = new URL(href);
                const isOutbound = linkUrl.hostname !== currentDomain && 
                                 !linkUrl.hostname.includes(currentDomain) &&
                                 !currentDomain.includes(linkUrl.hostname);

                if (isOutbound) {
                    outboundGoals.forEach(goal => {
                        if (evaluateConditions(goal, {
                            link_url: href,
                            link_domain: linkUrl.hostname,
                            link_text: link.textContent.trim()
                        })) {
                            fireGoal(goal, {
                                link_url: href,
                                link_domain: linkUrl.hostname,
                                link_text: link.textContent.trim()
                            });
                        }
                    });
                }
            } catch (err) {
                // Invalid URL, skip
            }
        });
    }

    /**
     * Initialize all goal trackers
     */
    function init() {
        if (goals.length === 0) {
            console.log('TrackSure Goals: No active goals to track');
            return;
        }

        console.log('TrackSure Goals: Initializing tracking for', goals.length, 'goals');

        // Wait for DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initPageviewGoals();
                initClickGoals();
                initFormGoals();
                initScrollGoals();
                initTimeGoals();
                initEngagementGoals();
                initCustomEventGoals();
                initVideoGoals();
                initDownloadGoals();
                initOutboundLinkGoals();
            });
        } else {
            initPageviewGoals();
            initClickGoals();
            initFormGoals();
            initScrollGoals();
            initTimeGoals();
            initEngagementGoals();
            initCustomEventGoals();
            initVideoGoals();
            initDownloadGoals();
            initOutboundLinkGoals();
        }
    }

    // Start tracking
    init();

    // Expose API for custom event triggering
    window.trackSureGoals = {
        fireCustomEvent: function(eventName, eventData) {
            const event = new CustomEvent('trackSureCustomEvent', {
                detail: { eventName, eventData }
            });
            document.dispatchEvent(event);
        }
    };

})();
