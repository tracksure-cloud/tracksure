/**
 * TrackSure Goals - Front-End Goal Tracking
 *
 * Monitors user interactions and triggers goal events when conditions match.
 * Handles pageviews, clicks, form submissions, scroll depth, time on page,
 * engagement, custom events, video plays, downloads, and outbound links.
 *
 * Frequency persistence:
 * - 'once' (lifetime): Uses localStorage so goals won't re-fire across sessions.
 * - 'session': Uses sessionStorage so goals fire once per browser session.
 * - 'unlimited': Always fires (respects cooldown_minutes if set).
 *
 * @since   2.0.0 - Major refactor: Fixed data structure consistency
 * @since   2.2.0 - Added frequency persistence, regex consistency, JSDoc
 * @package TrackSure
 */

/* eslint-disable no-console, no-unused-vars, security/detect-object-injection, curly, security/detect-non-literal-regexp, no-script-url */
(function() {
    'use strict';

    // Check if TrackSure main tracker is loaded
    if (typeof window.TrackSure === 'undefined' || typeof window.tracksure_goals === 'undefined') {
        console.warn('TrackSure Goals: Tracker or goals data not loaded');
        return;
    }

    // Import constants (should be loaded via tracksure-goal-constants.js)
    const { TRIGGER_EVENT_MAP, doesTriggerMatchEvent: _doesTriggerMatchEvent } = window.TrackSureGoalConstants || {};

    if (!TRIGGER_EVENT_MAP) {
        console.error('TrackSure Goals: Goal constants not loaded. Make sure tracksure-goal-constants.js is enqueued first.');
        return;
    }

    /** @type {Array<Object>} Active goals loaded from server */
    const goals = window.tracksure_goals || [];

    /** @type {Set<string>} In-memory dedup set for current page load */
    const trackedGoals = new Set();

    /** @type {Map<string, boolean>} Scroll depth progress tracker */
    const trackedScrollGoals = new Map();

    /** @type {Map<string, number>} Timestamps when goals last fired (cooldown) */
    const goalLastFired = new Map();

    /** @type {string} Storage key prefix for frequency persistence */
    const STORAGE_PREFIX = 'ts_goal_';

    /**
     * Check if a goal has already been fired in persistent storage.
     *
     * - 'once' frequency: checks localStorage (survives browser restart).
     * - 'session' frequency: checks sessionStorage (cleared on tab close).
     *
     * @param {Object} goal - Goal object with goal_id and frequency.
     * @returns {boolean} True if the goal was already fired persistently.
     */
    function isGoalFiredPersistently(goal) {
        const key = STORAGE_PREFIX + goal.goal_id;

        try {
            if (goal.frequency === 'once') {
                return localStorage.getItem(key) !== null;
            }
            if (goal.frequency === 'session') {
                return sessionStorage.getItem(key) !== null;
            }
        } catch (e) {
            // Storage unavailable (private browsing, quota exceeded).
        }

        return false;
    }

    /**
     * Mark a goal as fired in persistent storage.
     *
     * @param {Object} goal - Goal object with goal_id and frequency.
     */
    function markGoalFiredPersistently(goal) {
        const key = STORAGE_PREFIX + goal.goal_id;
        const now = Date.now().toString();

        try {
            if (goal.frequency === 'once') {
                localStorage.setItem(key, now);
            } else if (goal.frequency === 'session') {
                sessionStorage.setItem(key, now);
            }
        } catch (e) {
            // Storage unavailable.
        }
    }

    /**
     * Check if a goal can be fired based on frequency limits.
     *
     * Evaluation order:
     * 1. Persistent check (localStorage for 'once', sessionStorage for 'session')
     * 2. In-memory dedup (prevents double-fire within page load)
     * 3. Cooldown check (unlimited frequency with cooldown_minutes)
     *
     * @param {Object} goal - Goal object with goal_id, frequency, cooldown_minutes.
     * @returns {boolean} True if the goal can fire now.
     */
    function canFireGoal(goal) {
        const goalKey = `goal_${goal.goal_id}`;

        // Persistent frequency check (survives page reload).
        if (isGoalFiredPersistently(goal)) {
            return false;
        }

        // In-memory dedup check.
        if (trackedGoals.has(goalKey)) {
            // If unlimited with cooldown, check elapsed time.
            if (goal.frequency === 'unlimited' && goal.cooldown_minutes) {
                const lastFired = goalLastFired.get(goalKey);
                if (lastFired) {
                    const minutesSince = (Date.now() - lastFired) / 1000 / 60;
                    return minutesSince >= goal.cooldown_minutes;
                }
                return true;
            }

            // If unlimited without cooldown, always allow.
            if (goal.frequency === 'unlimited') {
                return true;
            }

            return false; // Already tracked this page load.
        }

        return true; // Not tracked yet.
    }

    /**
     * Fire a goal event and send it to the TrackSure tracker.
     *
     * Checks frequency limits, marks the goal as tracked (in-memory + persistent),
     * attaches conversion value if configured, and calls TrackSure.track().
     *
     * @param {Object} goal - Goal object with goal_id, name, event_name, value_type, fixed_value.
     * @param {Object} [additionalData={}] - Extra event data to merge (e.g. clicked_element).
     */
    function fireGoal(goal, additionalData = {}) {
        const goalKey = `goal_${goal.goal_id}`;
        
        // Check if goal can be fired based on frequency limits.
        if (!canFireGoal(goal)) {
            return;
        }

        // Mark as tracked (in-memory + persistent storage).
        trackedGoals.add(goalKey);
        goalLastFired.set(goalKey, Date.now());
        markGoalFiredPersistently(goal);

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
        window.TrackSure.track(goal.event_name, eventData);

        console.log('TrackSure Goals: Fired goal', goal.name, eventData);
    }

    /**
     * Check if a DOM element matches a CSS selector (or is a descendant of one).
     *
     * @param {Element} element - DOM element to test.
     * @param {string}  selector - CSS selector string.
     * @returns {boolean|Element|null} Truthy if matches, falsy otherwise.
     */
    function matchesSelector(element, selector) {
        try {
            return element.matches(selector) || element.closest(selector);
        } catch (e) {
            return false;
        }
    }

    /**
     * Detect form builder plugin from a form element.
     *
     * Inspects the form's ID, classes, and inner HTML for known
     * builder signatures (Gravity Forms, WPForms, CF7, etc.).
     *
     * @param {HTMLFormElement} form - The form element.
     * @returns {string} Detected builder name or empty string.
     */
    function detectFormBuilder(form) {
        if (!form) return '';
        const id = form.id || '';
        const classes = form.className || '';

        if (id.startsWith('gform_') || classes.includes('gform_wrapper')) return 'gravity_forms';
        if (id.startsWith('wpforms-') || classes.includes('wpforms-form')) return 'wpforms';
        if (classes.includes('wpcf7-form')) return 'contact_form_7';
        if (classes.includes('formidable')) return 'formidable';
        if (classes.includes('ninja-forms-form')) return 'ninja_forms';
        if (classes.includes('fluentform')) return 'fluent_forms';
        if (classes.includes('elementor-form')) return 'elementor';

        return '';
    }

    /**
     * Evaluate goal conditions against the current page context and event data.
     *
     * Each condition specifies a param (e.g. 'page_url'), operator ('equals'),
     * and value. Supports match_logic: 'all' (AND) or 'any' (OR).
     *
     * @param {Object} goal - Goal object with conditions and match_logic.
     * @param {Object} [eventData={}] - Event-specific data (element, scroll_depth, etc.).
     * @returns {boolean} True if conditions are met per match_logic.
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

            // Get actual value based on param.
            // Each case resolves a ConditionBuilder parameter to a runtime value
            // from the page context or from the eventData passed by init* handlers.
            switch (param) {
                // ── Page context (always available) ──────────────────────
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

                // ── Element params (click triggers) ─────────────────────
                case 'element_selector':
                    // CSS selector match — returns boolean directly (operator N/A).
                    return eventData.element ? !!matchesSelector(eventData.element, value) : false;
                case 'element_text':
                    actualValue = eventData.element
                        ? (eventData.element.textContent || '').trim()
                        : (eventData.element_text || '');
                    break;
                case 'element_id':
                    actualValue = eventData.element
                        ? (eventData.element.id || '')
                        : (eventData.element_id || '');
                    break;
                case 'element_class':
                    actualValue = eventData.element
                        ? (eventData.element.className || '')
                        : (eventData.element_class || '');
                    break;
                case 'element_type': {
                    const el = eventData.element?.closest?.('a');
                    const href = el ? (el.getAttribute('href') || '') : '';
                    if (href.startsWith('tel:')) actualValue = 'tel';
                    else if (href.startsWith('mailto:')) actualValue = 'mailto';
                    else actualValue = el ? el.tagName.toLowerCase() : '';
                    break;
                }

                // ── Link params (click / download / outbound) ───────────
                case 'link_url':
                    actualValue = eventData.link_url
                        || eventData.file_url
                        || (eventData.element?.closest?.('a')?.href)
                        || '';
                    break;
                case 'link_domain':
                    actualValue = eventData.link_domain || '';
                    break;
                case 'link_text':
                    actualValue = eventData.link_text || '';
                    break;

                // ── Form params ─────────────────────────────────────────
                case 'form_id':
                    actualValue = eventData.form_id
                        || (eventData.element?.id)
                        || '';
                    break;
                case 'form_name':
                    actualValue = eventData.form_name
                        || (eventData.element?.name)
                        || (eventData.element?.getAttribute?.('name'))
                        || '';
                    break;
                case 'form_type':
                    actualValue = eventData.form_type || eventData.form_name || '';
                    break;
                case 'form_builder':
                    actualValue = eventData.form_builder || '';
                    break;

                // ── Timing / scroll params ──────────────────────────────
                case 'time_seconds':
                case 'time_on_page':
                    // ConditionBuilder stores 'time_seconds'; handlers pass 'time_on_page'.
                    // Accept both param names for forward/backward compatibility.
                    actualValue = eventData.time_on_page ?? eventData.time_seconds ?? '';
                    break;
                case 'scroll_depth':
                    // Engagement handler passes 'max_scroll_depth'; scroll handler passes 'scroll_depth'.
                    actualValue = eventData.scroll_depth ?? eventData.max_scroll_depth ?? '';
                    break;

                // ── Video params ────────────────────────────────────────
                case 'video_title':
                    actualValue = eventData.video_title || '';
                    break;
                case 'video_url':
                    actualValue = eventData.video_url || eventData.video_src || '';
                    break;
                case 'video_type':
                    actualValue = eventData.video_type || '';
                    break;

                // ── Download params ─────────────────────────────────────
                case 'file_name':
                    actualValue = eventData.file_name || '';
                    break;
                case 'file_type':
                    actualValue = eventData.file_type || '';
                    break;

                // ── Custom event ────────────────────────────────────────
                case 'event_name':
                    actualValue = eventData.event_name || '';
                    break;

                // ── Fallback ────────────────────────────────────────────
                default:
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
     *
     * Supports: equals, not_equals, contains, not_contains, starts_with,
     * ends_with, matches_regex, greater_than, less_than, greater_than_or_equal,
     * less_than_or_equal.
     *
     * Regex patterns: If the pattern has PHP-style delimiters (e.g. "/^foo$/i"),
     * they are stripped and flags extracted for JS RegExp compatibility.
     *
     * @param {*}      actualValue   - Actual value from page/event data.
     * @param {string}  operator      - Operator name.
     * @param {string}  expectedValue - Expected value to compare against.
     * @returns {boolean} True if condition is satisfied.
     */
    function evaluateOperator(actualValue, operator, expectedValue) {
        // Coerce both values to strings for consistent comparison.
        // wp_localize_script serialises all values to strings, and runtime
        // values from DOM/events might be numbers.  Normalising here avoids
        // subtle type-mismatch bugs (e.g. 180 !== "180").
        const a = actualValue != null ? String(actualValue) : '';
        const e = expectedValue != null ? String(expectedValue) : '';

        switch (operator) {
            case 'equals':
                return a === e;
            case 'not_equals':
                return a !== e;
            case 'contains':
                return a.includes(e);
            case 'not_contains':
                return !a.includes(e);
            case 'starts_with':
                return a.startsWith(e);
            case 'ends_with':
                return a.endsWith(e);
            case 'matches_regex':
            case 'regex':
                try {
                    let pattern = expectedValue;
                    let flags = '';
                    // Strip PHP-style delimiters if present (e.g. "/^foo$/i" -> "^foo$" with flags "i").
                    const delimiterMatch = /^([/~#])(.+)\1([gimsuy]*)$/.exec(pattern);
                    if (delimiterMatch) {
                        pattern = delimiterMatch[2];
                        flags = delimiterMatch[3];
                    }
                    const regex = new RegExp(pattern, flags);
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
     * Initialize pageview goals.
     *
     * Fires immediately for goals with trigger_type='pageview' if conditions match.
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
     * Initialize click goals.
     *
     * Attaches a single delegated click listener for all click-type goals.
     * Uses capture phase to catch clicks before they're stopped.
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
     * Initialize form submit goals.
     *
     * Attaches a delegated 'submit' listener for all form_submit goals.
     */
    function initFormGoals() {
        const formGoals = goals.filter(g => g.trigger_type === 'form_submit' && g.is_active);

        if (formGoals.length === 0) return;

        // Listen for form submissions
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const formData = {
                element: form,
                form_id: form.id || '',
                form_name: form.name || form.getAttribute('name') || '',
                form_action: form.action || '',
                form_builder: detectFormBuilder(form),
            };

            formGoals.forEach(goal => {
                if (evaluateConditions(goal, formData)) {
                    fireGoal(goal, {
                        form_action: formData.form_action,
                        form_id: formData.form_id,
                        form_name: formData.form_name,
                        form_builder: formData.form_builder,
                    });
                }
            });
        }, true);
    }

    /**
     * Initialize scroll depth goals.
     *
     * Uses requestAnimationFrame for scroll throttling.
     * Checks scroll % against each goal's threshold (from trigger_config or conditions).
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
     * Initialize time-on-page goals.
     *
     * Sets a setTimeout for each goal's time threshold.
     * Threshold comes from trigger_config.time_seconds or conditions.
     */
    function initTimeGoals() {
        const timeGoals = goals.filter(g => g.trigger_type === 'time_on_page' && g.is_active);

        if (timeGoals.length === 0) return;

        timeGoals.forEach(goal => {
            // Find time condition — ConditionBuilder stores param as 'time_seconds'.
            const timeCondition = goal.conditions?.find(c => c.param === 'time_seconds' || c.param === 'time_on_page');
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
     * Initialize engagement goals (scroll + time combined).
     *
     * Requires both scroll depth AND time-on-page thresholds to be met.
     * Checks every 5 seconds via setInterval.
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
                // ConditionBuilder stores 'time_seconds' (not 'time_on_page').
                const timeCondition = conditions.find(c => c.param === 'time_seconds' || c.param === 'time_on_page');
                const scrollCondition = conditions.find(c => c.param === 'scroll_depth');

                const timeThreshold = timeCondition ? parseInt(timeCondition.value) : (goal.trigger_config?.time_seconds || 30);
                const scrollThreshold = scrollCondition ? parseInt(scrollCondition.value) : (goal.trigger_config?.scroll_depth || 50);

                if (timeOnPage >= timeThreshold && maxScrollDepth >= scrollThreshold) {
                    // Pass full data so evaluateConditions can also check page_url, etc.
                    const engagementData = {
                        time_on_page: timeOnPage,
                        scroll_depth: maxScrollDepth,
                        max_scroll_depth: maxScrollDepth
                    };
                    if (evaluateConditions(goal, engagementData)) {
                        fireGoal(goal, {
                            time_on_page: timeOnPage,
                            scroll_depth: maxScrollDepth
                        });
                    }
                }
            });
        }

        // Check every 5 seconds
        setInterval(checkEngagement, 5000);
    }

    /**
     * Initialize custom event goals.
     *
     * Listens for 'trackSureCustomEvent' CustomEvent on document.
     * Developers can fire: window.trackSureGoals.fireCustomEvent(name, data).
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
     * Initialize video play goals.
     *
     * Tracks HTML5 <video> play events, YouTube iframe play via Iframe API,
     * Vimeo iframe play via postMessage, and falls back to IntersectionObserver
     * for unrecognized embed types.
     */
    function initVideoGoals() {
        const videoGoals = goals.filter(g => g.trigger_type === 'video_play' && g.is_active);

        if (videoGoals.length === 0) return;

        // Track which goals have already fired for a given iframe (prevent duplicates).
        const firedGoalIframes = new Set();

        /**
         * Attempt to fire matching goals for a video event.
         *
         * @param {Object}  eventData  Event data with video_src, video_title, etc.
         * @param {string}  dedupeKey  Unique key to prevent duplicate fires.
         */
        function fireVideoGoals(eventData, dedupeKey) {
            videoGoals.forEach(goal => {
                const key = dedupeKey + '_' + goal.goal_id;
                if (firedGoalIframes.has(key)) return;

                if (evaluateConditions(goal, eventData)) {
                    fireGoal(goal, {
                        video_url: eventData.video_src || eventData.video_url || '',
                        video_title: eventData.video_title || '',
                        video_type: eventData.video_type || 'html5',
                        video_duration: eventData.video_duration || 0
                    });
                    firedGoalIframes.add(key);
                }
            });
        }

        // ── 1. Track HTML5 <video> play events ─────────────────────
        document.querySelectorAll('video').forEach((video, idx) => {
            video.addEventListener('play', function() {
                const videoSrc = video.src || video.currentSrc;
                const videoTitle = video.title || video.getAttribute('data-title') || 
                                 video.getAttribute('aria-label') || 'Untitled Video';
                
                fireVideoGoals({
                    video_src: videoSrc,
                    video_title: videoTitle,
                    video_type: 'html5',
                    video_duration: video.duration || 0
                }, 'html5_' + idx);
            });
        });

        // ── 2. Track YouTube iframes via Iframe API ─────────────────
        const ytIframes = document.querySelectorAll('iframe[src*="youtube.com"], iframe[src*="youtube-nocookie.com"]');

        if (ytIframes.length > 0) {
            // Ensure enablejsapi=1 is in each iframe src for API access.
            ytIframes.forEach(iframe => {
                try {
                    const url = new URL(iframe.src);
                    if (!url.searchParams.has('enablejsapi')) {
                        url.searchParams.set('enablejsapi', '1');
                        iframe.src = url.toString();
                    }
                } catch (_) { /* invalid URL, skip */ }
            });

            // Load YouTube Iframe API if not already loaded.
            if (typeof window.YT === 'undefined' || typeof window.YT.Player === 'undefined') {
                const tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                document.head.appendChild(tag);
            }

            const onYTReady = function() {
                ytIframes.forEach((iframe, idx) => {
                    // YouTube Player requires an id on the iframe.
                    if (!iframe.id) {
                        iframe.id = 'ts-yt-player-' + idx;
                    }

                    try {
                        new window.YT.Player(iframe.id, {
                            events: {
                                onStateChange: function(event) {
                                    // YT.PlayerState.PLAYING === 1
                                    if (event.data === 1) {
                                        const iframeSrc = iframe.src || '';
                                        const iframeTitle = iframe.title || 'YouTube Video';

                                        fireVideoGoals({
                                            video_src: iframeSrc,
                                            video_title: iframeTitle,
                                            video_type: 'youtube'
                                        }, 'yt_' + idx);
                                    }
                                }
                            }
                        });
                    } catch (_) {
                        // Fallback: use IntersectionObserver if API init fails.
                        observeIframeVisibility(iframe, idx, 'youtube');
                    }
                });
            };

            // Queue callback for when YT API is ready.
            if (typeof window.YT !== 'undefined' && typeof window.YT.Player !== 'undefined') {
                onYTReady();
            } else {
                const prev = window.onYouTubeIframeAPIReady;
                window.onYouTubeIframeAPIReady = function() {
                    if (typeof prev === 'function') prev();
                    onYTReady();
                };
            }
        }

        // ── 3. Track Vimeo iframes via postMessage ──────────────────
        const vimeoIframes = document.querySelectorAll('iframe[src*="vimeo.com"]');

        if (vimeoIframes.length > 0) {
            // Build a map of iframe window → metadata for fast lookup.
            const vimeoMap = new Map();
            vimeoIframes.forEach((iframe, idx) => {
                vimeoMap.set(iframe.contentWindow, {
                    iframe: iframe,
                    idx: idx,
                    src: iframe.src,
                    title: iframe.title || 'Vimeo Video'
                });

                // Tell Vimeo player to send play events.
                try {
                    iframe.contentWindow.postMessage(JSON.stringify({
                        method: 'addEventListener',
                        value: 'play'
                    }), '*');
                } catch (_) { /* cross-origin, skip */ }
            });

            window.addEventListener('message', function(e) {
                try {
                    const data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
                    if (data && data.event === 'play') {
                        const meta = vimeoMap.get(e.source);
                        if (meta) {
                            fireVideoGoals({
                                video_src: meta.src,
                                video_title: meta.title,
                                video_type: 'vimeo'
                            }, 'vimeo_' + meta.idx);
                        }
                    }
                } catch (_) { /* not our message */ }
            });
        }

        /**
         * Fallback: observe iframe visibility with IntersectionObserver.
         * Used when API-based tracking is not available.
         */
        function observeIframeVisibility(iframe, idx, type) {
            const iframeSrc = iframe.src;
            const iframeTitle = iframe.title || 'Embedded Video';

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    fireVideoGoals({
                        video_src: iframeSrc,
                        video_title: iframeTitle,
                        video_type: type
                    }, type + '_vis_' + idx);
                    observer.disconnect();
                }
            }, { threshold: 0.5 });

            observer.observe(iframe);
        }
    }

    /**
     * Initialize file download goals.
     *
     * Tracks clicks on links to downloadable file extensions
     * (pdf, doc, docx, xls, xlsx, zip, csv, ppt, pptx, txt, rtf, png, etc.).
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
                        link_url: href,
                        file_name: fileName,
                        file_type: fileExt,
                        link_text: link.textContent.trim()
                    })) {
                        fireGoal(goal, {
                            file_url: href,
                            link_url: href,
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
     * Initialize outbound link goals.
     *
     * Tracks clicks on links pointing to external domains.
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
     * Initialize all goal trackers.
     *
     * Waits for DOMContentLoaded if necessary, then bootstraps
     * each trigger-type handler.
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
