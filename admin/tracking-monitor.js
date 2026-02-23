/**
 * TrackSure Event Tracking Monitor - Bookmarklet Version
 * 
 * Copy this entire code, minify it, and create a bookmarklet.
 * Or run directly in console on any page with TrackSure.
 */

/* eslint-disable no-console, curly, no-alert */
(function() {
    'use strict';
    
    // Check if already loaded
    if (window.TrackSureMonitor) {
        console.log('[TrackSure Monitor] Already loaded');
        return;
    }
    
    // Create monitor container
    const container = document.createElement('div');
    container.id = 'tracksure-monitor';
    container.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 400px;
        max-height: 600px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        z-index: 999999;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    `;
    
    // Monitor state
    const state = {
        browserEvents: [],
        serverEvents: [],
        browserCount: 0,
        serverCount: 0,
        metaCount: 0,
        ga4Count: 0,
        errorCount: 0,
        minimized: false
    };
    
    // Build UI
    function buildUI() {
        container.innerHTML = `
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; color: white; cursor: move;" id="monitor-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; font-size: 14px;">🎯 TrackSure Monitor</div>
                        <div style="font-size: 11px; opacity: 0.9;">Real-time tracking</div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button id="minimize-btn" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">_</button>
                        <button id="close-btn" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">✕</button>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 12px; font-size: 11px;" id="stats">
                    <div style="text-align: center;">
                        <div style="opacity: 0.8;">Browser</div>
                        <div style="font-size: 18px; font-weight: 700;" id="stat-browser">0</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="opacity: 0.8;">Server</div>
                        <div style="font-size: 18px; font-weight: 700;" id="stat-server">0</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="opacity: 0.8;">Meta</div>
                        <div style="font-size: 18px; font-weight: 700;" id="stat-meta">0</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="opacity: 0.8;">GA4</div>
                        <div style="font-size: 18px; font-weight: 700;" id="stat-ga4">0</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="opacity: 0.8;">Errors</div>
                        <div style="font-size: 18px; font-weight: 700;" id="stat-errors">0</div>
                    </div>
                </div>
            </div>
            <div id="monitor-content" style="flex: 1; overflow-y: auto; padding: 12px; background: #f5f5f5;">
                <div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">
                    <div style="margin-bottom: 8px;">👀</div>
                    <div>Monitoring events...</div>
                </div>
            </div>
            <div style="padding: 10px; background: white; border-top: 1px solid #e0e0e0; display: flex; gap: 8px;">
                <button id="test-btn" style="flex: 1; background: #4CAF50; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500;">🧪 Test Event</button>
                <button id="clear-btn" style="flex: 1; background: #f44336; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500;">🗑️ Clear</button>
                <button id="export-btn" style="flex: 1; background: #2196F3; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500;">💾 Export</button>
            </div>
        `;
    }
    
    buildUI();
    document.body.appendChild(container);
    
    // Make draggable
    const header = document.getElementById('monitor-header');
    let isDragging = false;
    let currentX, currentY, initialX, initialY;
    
    header.addEventListener('mousedown', (e) => {
        if (e.target.tagName === 'BUTTON') return;
        isDragging = true;
        initialX = e.clientX - container.offsetLeft;
        initialY = e.clientY - container.offsetTop;
    });
    
    document.addEventListener('mousemove', (e) => {
        if (isDragging) {
            e.preventDefault();
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            container.style.left = currentX + 'px';
            container.style.top = currentY + 'px';
            container.style.right = 'auto';
            container.style.bottom = 'auto';
        }
    });
    
    document.addEventListener('mouseup', () => {
        isDragging = false;
    });
    
    // Update stats
    function updateStats() {
        document.getElementById('stat-browser').textContent = state.browserCount;
        document.getElementById('stat-server').textContent = state.serverCount;
        document.getElementById('stat-meta').textContent = state.metaCount;
        document.getElementById('stat-ga4').textContent = state.ga4Count;
        document.getElementById('stat-errors').textContent = state.errorCount;
    }
    
    // Add event to UI
    function addEventToUI(type, data) {
        const content = document.getElementById('monitor-content');
        
        // Remove empty state
        if (content.querySelector('div[style*="text-align: center"]')) {
            content.innerHTML = '';
        }
        
        const eventEl = document.createElement('div');
        eventEl.style.cssText = `
            background: white;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 6px;
            font-size: 12px;
            border-left: 3px solid ${type === 'browser' ? '#4CAF50' : '#2196F3'};
            animation: slideIn 0.3s;
        `;
        
        const time = new Date().toLocaleTimeString('en-US', { hour12: false });
        const icon = type === 'browser' ? '🌐' : '🖥️';
        
        eventEl.innerHTML = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                <strong>${icon} ${data.name || data.url?.split('/').pop() || 'Event'}</strong>
                <span style="color: #999; font-size: 11px;">${time}</span>
            </div>
            <div style="background: #f5f5f5; padding: 6px; border-radius: 4px; font-family: monospace; font-size: 10px; max-height: 100px; overflow-y: auto;">
                ${JSON.stringify(data.params || data.payload || {}, null, 2)}
            </div>
            ${data.response ? `<div style="margin-top: 6px; padding: 6px; background: #E8F5E9; border-radius: 4px; font-size: 10px;">✓ Response: ${typeof data.response === 'string' ? data.response : JSON.stringify(data.response)}</div>` : ''}
            ${data.error ? `<div style="margin-top: 6px; padding: 6px; background: #FFEBEE; border-radius: 4px; font-size: 10px; color: #c62828;">✗ Error: ${data.error}</div>` : ''}
        `;
        
        content.insertBefore(eventEl, content.firstChild);
        
        // Limit to 20 events
        while (content.children.length > 20) {
            content.removeChild(content.lastChild);
        }
    }
    
    // Intercept Meta Pixel
    if (window.fbq) {
        const originalFbq = window.fbq;
        window.fbq = function(...args) {
            const [action, eventName, params] = args;
            
            if (action === 'track' || action === 'trackCustom') {
                console.log('[TrackSure Monitor] Meta Pixel:', eventName, params);
                state.browserCount++;
                state.metaCount++;
                updateStats();
                addEventToUI('browser', { name: `Meta: ${eventName}`, params });
            }
            
            return originalFbq.apply(this, args);
        };
        window.fbq.queue = originalFbq.queue;
    }
    
    // Intercept GA4
    if (window.gtag) {
        const originalGtag = window.gtag;
        window.gtag = function(...args) {
            const [command, action, params] = args;
            
            if (command === 'event') {
                console.log('[TrackSure Monitor] GA4:', action, params);
                state.browserCount++;
                state.ga4Count++;
                updateStats();
                addEventToUI('browser', { name: `GA4: ${action}`, params });
            }
            
            return originalGtag.apply(this, args);
        };
    }
    
    // Intercept fetch
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        const [url, options] = args;
        const urlStr = typeof url === 'string' ? url : url.toString();
        const isTracking = urlStr.includes('tracksure/v1/ingest') || 
                          urlStr.includes('facebook.com') || 
                          urlStr.includes('google-analytics.com');
        
        if (isTracking && options && options.body) {
            let payload;
            try {
                payload = JSON.parse(options.body);
            } catch (e) {
                payload = options.body;
            }
            
            console.log('[TrackSure Monitor] Server Request:', urlStr, payload);
            
            return originalFetch.apply(this, args).then(response => {
                const clonedResponse = response.clone();
                
                clonedResponse.json().then(data => {
                    console.log('[TrackSure Monitor] Server Response:', data);
                    state.serverCount++;
                    updateStats();
                    addEventToUI('server', { url: urlStr, payload, response: data });
                }).catch(() => {
                    state.serverCount++;
                    updateStats();
                    addEventToUI('server', { url: urlStr, payload, response: response.statusText });
                });
                
                return response;
            }).catch(error => {
                console.error('[TrackSure Monitor] Server Error:', error);
                state.serverCount++;
                state.errorCount++;
                updateStats();
                addEventToUI('server', { url: urlStr, payload, error: error.message });
                throw error;
            });
        }
        
        return originalFetch.apply(this, args);
    };
    
    // Button handlers
    document.getElementById('close-btn').addEventListener('click', () => {
        container.remove();
        window.TrackSureMonitor = null;
    });
    
    document.getElementById('minimize-btn').addEventListener('click', () => {
        const content = document.getElementById('monitor-content');
        state.minimized = !state.minimized;
        content.style.display = state.minimized ? 'none' : 'block';
        container.style.height = state.minimized ? 'auto' : '600px';
    });
    
    document.getElementById('test-btn').addEventListener('click', () => {
        if (window.track) {
            window.track('add_to_cart', {
                item_id: 'TEST-' + Date.now(),
                item_name: 'Test Product',
                value: 29.99,
                currency: 'USD'
            });
        } else if (window.TrackSure && window.TrackSure.testPixels) {
            window.TrackSure.testPixels('add_to_cart', {
                item_id: 'TEST-' + Date.now(),
                value: 29.99,
                currency: 'USD'
            });
        } else {
            alert('TrackSure not found');
        }
    });
    
    document.getElementById('clear-btn').addEventListener('click', () => {
        state.browserEvents = [];
        state.serverEvents = [];
        state.browserCount = 0;
        state.serverCount = 0;
        state.metaCount = 0;
        state.ga4Count = 0;
        state.errorCount = 0;
        updateStats();
        document.getElementById('monitor-content').innerHTML = '<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;"><div style="margin-bottom: 8px;">👀</div><div>Monitoring events...</div></div>';
    });
    
    document.getElementById('export-btn').addEventListener('click', () => {
        const data = {
            browser: state.browserEvents,
            server: state.serverEvents,
            stats: {
                browserCount: state.browserCount,
                serverCount: state.serverCount,
                metaCount: state.metaCount,
                ga4Count: state.ga4Count,
                errorCount: state.errorCount
            },
            exportedAt: new Date().toISOString()
        };
        
        console.log('[TrackSure Monitor] Export:', data);
        
        // Copy to clipboard
        navigator.clipboard.writeText(JSON.stringify(data, null, 2)).then(() => {
            alert('Events copied to clipboard!');
        }).catch(() => {
            // Download as file
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tracksure-events-${Date.now()}.json`;
            a.click();
            URL.revokeObjectURL(url);
        });
    });
    
    // Mark as loaded
    window.TrackSureMonitor = true;
    
    console.log('[TrackSure Monitor] Loaded successfully');
    console.log('📊 Use window.TrackSureMonitor to check status');
})();
