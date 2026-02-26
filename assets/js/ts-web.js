/**
 * TrackSure Browser SDK
 * 
 * Production-grade first-party tracking:
 * - Complete engagement tracking (views, clicks, scroll, forms, video, downloads)
 * - Performance & UX metrics (page timing, rage clicks, dead clicks)
 * - Advanced attribution (UTMs, referrers, social, search, AI chatbots)
 * - Identity management (client ID, session ID, cross-device)
 * - Event deduplication (UUID-based for browser + server + destinations)
 * - Zero page speed impact (<5KB, non-blocking, batched delivery)
 * - Universal compatibility (Safari ITP, ad blockers, private mode, mobile)
 * 
 * @version 2.0.0
 * @architecture Aligned with events.json and params.json registry
 */

/* eslint-disable no-console, curly, prefer-const, no-unused-vars, security/detect-object-injection, no-tabs, no-mixed-spaces-and-tabs, @typescript-eslint/no-this-alias, no-undef */
(function (window, document) {
	'use strict';

	// Configuration from wp_localize_script.
	const config = window.trackSureConfig || {};
	const endpoint = config.endpoint || '/wp-json/ts/v1/collect';
	const trackingEnabled = config.trackingEnabled === true || config.trackingEnabled === '1' || config.trackingEnabled === 1;
	const sessionTimeout = (config.sessionTimeout || 30) * 60 * 1000;
	const batchSize = config.batchSize || 10;
	const batchTimeout = config.batchTimeout || 2000;
	const respectDNT = config.respectDNT === true || config.respectDNT === 'true' || config.respectDNT === '1';

	// Check if tracking is enabled (master switch)
	if (!trackingEnabled) {
		if (window.trackSureDebug) {
			console.log('Tracking disabled');
		}
		return;
	}

	// Check Do Not Track.
	if (respectDNT && (navigator.doNotTrack === '1' || window.doNotTrack === '1')) {
		if (window.trackSureDebug) {
			console.log('DNT enabled, tracking disabled');
		}
		return;
	}

	// Event queue & state
	let eventQueue = [];
	let batchTimer = null;
	const _pageLoadTime = Date.now(); // Track initial page load time (unused but kept for future use)
	let isFirstVisit = false;
	let sessionStartTracked = false;
	let trackingCurrentlyEnabled = trackingEnabled; // Track runtime state
	let lastSettingsCheck = Date.now();
	const SETTINGS_CHECK_INTERVAL = 60000; // Check every 60 seconds

	/**
	 * Event Registry (loaded from backend)
	 * 
	 * Provides client-side validation and event metadata.
	 * Loaded asynchronously to avoid blocking page load.
	 * 
	 * @type {Object|null}
	 */
	let registry = null;
	let registryLoadAttempted = false;

	// Storage keys (neutral names to avoid ad-blocker keyword matching).
	const STORAGE_CLIENT_ID = '_ts_cid';
	const STORAGE_SESSION_ID = '_ts_sid';
	const STORAGE_SESSION_START = '_ts_ss';
	const STORAGE_LAST_ACTIVITY = '_ts_la';

	/**
	 * Check if tracking is still enabled server-side.
	 * Prevents collecting data after admin disables tracking.
	 */
	function checkTrackingEnabled() {
		if (!trackingCurrentlyEnabled) return; // Already disabled

		const now = Date.now();
		if (now - lastSettingsCheck < SETTINGS_CHECK_INTERVAL) return;

		lastSettingsCheck = now;

		// Lightweight HEAD request to check tracking status
		fetch(endpoint, {
			method: 'HEAD',
			credentials: 'same-origin'
		}).then(function(response) {
			if (response.status === 403) {
				// Tracking disabled server-side
				trackingCurrentlyEnabled = false;
				if (window.trackSureDebug) {
					console.log('Tracking disabled by administrator');
				}

				// Clear any pending batches
				eventQueue = [];
				clearTimeout(batchTimer);
				batchTimer = null;
			}
		}).catch(function() {
			// Ignore network errors - assume tracking still enabled
		});
	}

	// Check tracking status periodically
	setInterval(checkTrackingEnabled, SETTINGS_CHECK_INTERVAL);

	/**
	 * Load event and parameter registry from backend.
	 * 
	 * Enables client-side validation and provides event metadata to browser.
	 * Loads asynchronously without blocking tracking - events work even if registry fails.
	 * 
	 * @returns {Promise<void>}
	 */
	async function loadRegistry() {
		if (registryLoadAttempted) return; // Only attempt once
		registryLoadAttempted = true;

		try {
			const registryEndpoint = config.registryEndpoint || '/wp-json/ts/v1/registry';
			const response = await fetch(registryEndpoint, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'Accept': 'application/json'
				}
			});

			if (!response.ok) {
				throw new Error('Registry endpoint returned ' + response.status);
			}

			const data = await response.json();
			
			// Validate registry structure
			if (!data.success || !data.data || !data.data.events || !data.data.parameters) {
				throw new Error('Invalid registry structure');
			}

			registry = data.data;

			if (window.trackSureDebug) {
				console.log(
					'[TrackSure] Registry loaded:',
					Object.keys(registry.events).length + ' events,',
					Object.keys(registry.parameters).length + ' parameters'
				);
			}
		} catch (error) {
			if (window.trackSureDebug) {
				console.warn('[TrackSure] Failed to load registry:', error.message);
				console.warn('[TrackSure] Continuing without client-side validation (degraded mode)');
			}
			// Continue tracking without registry (server-side validation still works)
		}
	}

	/**
	 * Validate event against registry.
	 * 
	 * Checks if event exists and has required parameters.
	 * Only validates if registry is loaded (graceful degradation).
	 * 
	 * @param {string} eventName - Event name to validate
	 * @param {Object} eventParams - Event parameters
	 * @returns {Object} Validation result {valid: boolean, errors: Array<string>}
	 */
	function validateEvent(eventName, eventParams) {
		// Skip validation if registry not loaded (degraded mode)
		if (!registry || !registry.events) {
			return { valid: true, errors: [] };
		}

		const errors = [];
		const eventDef = registry.events[eventName];

		// Check if event exists in registry
		if (!eventDef) {
			errors.push('Event "' + eventName + '" not found in registry');
			return { valid: false, errors: errors };
		}

		// Check required parameters
		if (eventDef.required_params && eventDef.required_params.length > 0) {
			eventDef.required_params.forEach(function(param) {
				if (!(param in eventParams)) {
					errors.push('Missing required parameter: ' + param);
				}
			});
		}

		return {
			valid: errors.length === 0,
			errors: errors
		};
	}

	/**
	 * Generate UUID v4 using native crypto API (faster, more secure).
	 * Falls back to Math.random() for old browsers.
	 */
	function generateUUID() {
		// Use native crypto.randomUUID() if available (Chrome 92+, Safari 15.4+, Firefox 95+)
		if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
			try {
				return crypto.randomUUID();
			} catch (e) {
				// Fall through to fallback
			}
		}
		
		// Fallback for older browsers
		if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
			try {
				const bytes = new Uint8Array(16);
				crypto.getRandomValues(bytes);
				// Set version (4) and variant bits
				bytes[6] = (bytes[6] & 0x0f) | 0x40;
				bytes[8] = (bytes[8] & 0x3f) | 0x80;
				// Convert to hex string
				const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
				return [
					hex.slice(0, 8),
					hex.slice(8, 12),
					hex.slice(12, 16),
					hex.slice(16, 20),
					hex.slice(20, 32)
				].join('-');
			} catch (e) {
				// Fall through to Math.random fallback
			}
		}
		
		// Last resort: Math.random() based UUID (for very old browsers)
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			const r = (Math.random() * 16) | 0;
			const v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}

	/**
	 * Generate MD5 hash (for deterministic event_id).
	 * Simple implementation compatible with all browsers.
	 */
	function md5(str) {
		function rotateLeft(x, n) {
			return (x << n) | (x >>> (32 - n));
		}
		function addUnsigned(x, y) {
			const x8 = (x & 0x80000000);
			const y8 = (y & 0x80000000);
			const x4 = (x & 0x40000000);
			const y4 = (y & 0x40000000);
			const result = (x & 0x3FFFFFFF) + (y & 0x3FFFFFFF);
			if (x4 & y4) return (result ^ 0x80000000 ^ x8 ^ y8);
			if (x4 | y4) {
				if (result & 0x40000000) return (result ^ 0xC0000000 ^ x8 ^ y8);
				else return (result ^ 0x40000000 ^ x8 ^ y8);
			} else {
				return (result ^ x8 ^ y8);
			}
		}
		function F(x, y, z) { return (x & y) | ((~x) & z); }
		function G(x, y, z) { return (x & z) | (y & (~z)); }
		function H(x, y, z) { return (x ^ y ^ z); }
		function I(x, y, z) { return (y ^ (x | (~z))); }
		function FF(a, b, c, d, x, s, ac) {
			a = addUnsigned(a, addUnsigned(addUnsigned(F(b, c, d), x), ac));
			return addUnsigned(rotateLeft(a, s), b);
		}
		function GG(a, b, c, d, x, s, ac) {
			a = addUnsigned(a, addUnsigned(addUnsigned(G(b, c, d), x), ac));
			return addUnsigned(rotateLeft(a, s), b);
		}
		function HH(a, b, c, d, x, s, ac) {
			a = addUnsigned(a, addUnsigned(addUnsigned(H(b, c, d), x), ac));
			return addUnsigned(rotateLeft(a, s), b);
		}
		function II(a, b, c, d, x, s, ac) {
			a = addUnsigned(a, addUnsigned(addUnsigned(I(b, c, d), x), ac));
			return addUnsigned(rotateLeft(a, s), b);
		}
		function convertToWordArray(str) {
			const wordArray = [];
			for (let i = 0; i < str.length * 8; i += 8) {
				wordArray[i >> 5] |= (str.charCodeAt(i / 8) & 0xFF) << (i % 32);
			}
			return wordArray;
		}
		function wordToHex(x) {
			let hex = '';
			for (let i = 0; i < 4; i++) {
				const byte = (x >>> (i * 8)) & 0xFF;
				hex += ('0' + byte.toString(16)).slice(-2);
			}
			return hex;
		}

		const x = convertToWordArray(str);
		const len = str.length * 8;
		x[len >> 5] |= 0x80 << (len % 32);
		x[(((len + 64) >>> 9) << 4) + 14] = len;

		let a = 1732584193;
		let b = -271733879;
		let c = -1732584194;
		let d = 271733878;

		for (let i = 0; i < x.length; i += 16) {
			const olda = a, oldb = b, oldc = c, oldd = d;
			
			a = FF(a,b,c,d,x[i+0],7,-680876936); d = FF(d,a,b,c,x[i+1],12,-389564586);
			c = FF(c,d,a,b,x[i+2],17,606105819); b = FF(b,c,d,a,x[i+3],22,-1044525330);
			a = FF(a,b,c,d,x[i+4],7,-176418897); d = FF(d,a,b,c,x[i+5],12,1200080426);
			c = FF(c,d,a,b,x[i+6],17,-1473231341); b = FF(b,c,d,a,x[i+7],22,-45705983);
			a = FF(a,b,c,d,x[i+8],7,1770035416); d = FF(d,a,b,c,x[i+9],12,-1958414417);
			c = FF(c,d,a,b,x[i+10],17,-42063); b = FF(b,c,d,a,x[i+11],22,-1990404162);
			a = FF(a,b,c,d,x[i+12],7,1804603682); d = FF(d,a,b,c,x[i+13],12,-40341101);
			c = FF(c,d,a,b,x[i+14],17,-1502002290); b = FF(b,c,d,a,x[i+15],22,1236535329);
			
			a = GG(a,b,c,d,x[i+1],5,-165796510); d = GG(d,a,b,c,x[i+6],9,-1069501632);
			c = GG(c,d,a,b,x[i+11],14,643717713); b = GG(b,c,d,a,x[i+0],20,-373897302);
			a = GG(a,b,c,d,x[i+5],5,-701558691); d = GG(d,a,b,c,x[i+10],9,38016083);
			c = GG(c,d,a,b,x[i+15],14,-660478335); b = GG(b,c,d,a,x[i+4],20,-405537848);
			a = GG(a,b,c,d,x[i+9],5,568446438); d = GG(d,a,b,c,x[i+14],9,-1019803690);
			c = GG(c,d,a,b,x[i+3],14,-187363961); b = GG(b,c,d,a,x[i+8],20,1163531501);
			a = GG(a,b,c,d,x[i+13],5,-1444681467); d = GG(d,a,b,c,x[i+2],9,-51403784);
			c = GG(c,d,a,b,x[i+7],14,1735328473); b = GG(b,c,d,a,x[i+12],20,-1926607734);
			
			a = HH(a,b,c,d,x[i+5],4,-378558); d = HH(d,a,b,c,x[i+8],11,-2022574463);
			c = HH(c,d,a,b,x[i+11],16,1839030562); b = HH(b,c,d,a,x[i+14],23,-35309556);
			a = HH(a,b,c,d,x[i+1],4,-1530992060); d = HH(d,a,b,c,x[i+4],11,1272893353);
			c = HH(c,d,a,b,x[i+7],16,-155497632); b = HH(b,c,d,a,x[i+10],23,-1094730640);
			a = HH(a,b,c,d,x[i+13],4,681279174); d = HH(d,a,b,c,x[i+0],11,-358537222);
			c = HH(c,d,a,b,x[i+3],16,-722521979); b = HH(b,c,d,a,x[i+6],23,76029189);
			a = HH(a,b,c,d,x[i+9],4,-640364487); d = HH(d,a,b,c,x[i+12],11,-421815835);
			c = HH(c,d,a,b,x[i+15],16,530742520); b = HH(b,c,d,a,x[i+2],23,-995338651);
			
			a = II(a,b,c,d,x[i+0],6,-198630844); d = II(d,a,b,c,x[i+7],10,1126891415);
			c = II(c,d,a,b,x[i+14],15,-1416354905); b = II(b,c,d,a,x[i+5],21,-57434055);
			a = II(a,b,c,d,x[i+12],6,1700485571); d = II(d,a,b,c,x[i+3],10,-1894986606);
			c = II(c,d,a,b,x[i+10],15,-1051523); b = II(b,c,d,a,x[i+1],21,-2054922799);
			a = II(a,b,c,d,x[i+8],6,1873313359); d = II(d,a,b,c,x[i+15],10,-30611744);
			c = II(c,d,a,b,x[i+6],15,-1560198380); b = II(b,c,d,a,x[i+13],21,1309151649);
			a = II(a,b,c,d,x[i+4],6,-145523070); d = II(d,a,b,c,x[i+11],10,-1120210379);
			c = II(c,d,a,b,x[i+2],15,718787259); b = II(b,c,d,a,x[i+9],21,-343485551);

			a = addUnsigned(a, olda); b = addUnsigned(b, oldb);
			c = addUnsigned(c, oldc); d = addUnsigned(d, oldd);
		}

		return (wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d)).toLowerCase();
	}

	/**
	 * Generate deterministic event_id for browser+server deduplication.
	 * 
	 * CRITICAL for Meta CAPI and ad platforms:
	 * - Browser and server MUST use identical event_id for same action
	 * - Meta uses event_id for deduplication between Pixel and CAPI
	 * - Different event_ids = duplicate counting in ad platforms
	 * 
	 * UNIVERSAL DESIGN (works for ALL website types):
	 * - E-commerce: Uses product_id (WooCommerce, EDD, SureCart, etc.)
	 * - Blog/News: Uses post_id (WordPress posts/pages)
	 * - Custom pages: Uses page_url hash
	 * - Generic events: Uses session + event + time only
	 * 
	 * Strategy: Hash(session_id + event_name + timestamp + content_identifier)
	 * 
	 * @param {string} sessionId - Session UUID
	 * @param {string} eventName - Event name (e.g., "view_item", "page_view")
	 * @param {object} params - Event parameters (may contain product_id, post_id, page_url)
	 * @returns {string} UUID v4 formatted event_id
	 */
	function generateDeterministicEventId(sessionId, eventName, params = {}) {
		// Build deterministic string
		// CRITICAL: No timestamp! Browser and server must generate identical event_id
		// regardless of timing differences (milliseconds apart)
		
		// UNIVERSAL CONTENT IDENTIFIER (works for ALL website types)
		const contentIdentifier = extractContentIdentifier(params);
		
		// Create deterministic string: session + event + content (NO TIMESTAMP)
		// This ensures browser-server deduplication works even with timing differences
		const deterministicString = sessionId + '|' + eventName + '|' + contentIdentifier;
		
		// Generate MD5 hash (128 bits, perfect for UUID)
		const hash = md5(deterministicString);
		
		// Convert hash to UUID v4 format (8-4-4-4-12)
		// Set version bits (4) and variant bits (2) for valid UUID v4
		const uuid = [
			hash.substr(0, 8),
			hash.substr(8, 4),
			'4' + hash.substr(13, 3),
			(parseInt(hash.substr(16, 1), 16) & 0x3 | 0x8).toString(16) + hash.substr(17, 3),
			hash.substr(20, 12)
		].join('-');
		
		if (window.trackSureDebug) {
			console.log('[TrackSure] Generated deterministic event_id:', uuid, 'from:', deterministicString);
		}
		
		return uuid;
	}

	/**
	 * Extract universal content identifier from event parameters.
	 * 
	 * Works for ALL website types (e-commerce, blog, portfolio, agency, etc.)
	 * 
	 * Priority order:
	 * 1. E-commerce: product_id (WooCommerce, EDD, SureCart, CartFlows, etc.)
	 * 2. Blog/Content: post_id (WordPress posts, pages, custom post types)
	 * 3. Custom pages: page_url (for non-WordPress pages, landing pages)
	 * 4. Generic: Empty string (session + event + time is enough)
	 * 
	 * @param {object} params - Event parameters
	 * @returns {string} Content identifier (product_id, post_id, or page_url hash)
	 */
	function extractContentIdentifier(params) {
		// 1. E-commerce product ID (highest priority)
		if (params.product_id) {
			return 'product_' + params.product_id;
		}
		
		// 2. Multi-item e-commerce (cart, checkout)
		if (params.items && Array.isArray(params.items) && params.items.length > 0) {
			const itemIds = params.items.map(item => item.item_id || '').join(',');
			return 'items_' + md5(itemIds).substr(0, 16); // Hash for consistent length
		}
		
		// 3. WordPress post/page ID (blogs, news, content sites)
		if (params.post_id) {
			return 'post_' + params.post_id;
		}
		
		// 4. Page URL or page_location (custom pages, landing pages, page_view events)
		// Use short hash to keep deterministic string length reasonable
		// page_location is the GA4 standard name used by page_view events
		const pageUrl = params.page_url || params.page_location;
		if (pageUrl) {
			return 'page_' + md5(pageUrl).substr(0, 8);
		}
		
		// 5. Generic events (no content identifier needed)
		// Examples: session_start, form_submit, video_play, etc.
		// Session + event + time is sufficient for deduplication
		return '';
	}

	/**
	 * Note: Event validation happens server-side for performance.
	 * Browser just tracks events efficiently without validation overhead.
	 */

	/**
	 * Get or create client ID (persistent, 2 years).
	 * Works even if localStorage is blocked (Safari ITP, private mode, etc.)
	 */
	function getClientId() {
		let clientId = null;
		
		// Try localStorage first (preferred, survives browser restart)
		try {
			clientId = localStorage.getItem(STORAGE_CLIENT_ID);
		} catch (e) {
			// localStorage blocked or unavailable
		}
		
		// Fallback: check cookie if localStorage failed
		if (!clientId) {
			const match = document.cookie.match(/_ts_cid=([^;]+)/);
			if (match) {
				clientId = match[1];
				// Try to restore to localStorage
				try {
					localStorage.setItem(STORAGE_CLIENT_ID, clientId);
				} catch (e) {
					// Ignore if can't write to localStorage
				}
			}
		}
		
		// Generate new ID if both failed
		if (!clientId) {
			clientId = generateUUID();
			// Try to save to localStorage
			try {
				localStorage.setItem(STORAGE_CLIENT_ID, clientId);
			} catch (e) {
				// localStorage unavailable, cookie will be the only persistence
			}
		}
		
		// Always update cookie for PHP to read (refreshed every page load)
		try {
			const secure = window.location.protocol === 'https:' ? '; Secure' : '';
			// 400 days = Chrome's max-age cap. Safari ITP enforces 7 days regardless.
			// Cookie is refreshed on every page load, so the real expiry is "last visit + 400d".
			const maxAge = 400 * 24 * 60 * 60; // 400 days (Chrome maximum)
			document.cookie = '_ts_cid=' + clientId + '; path=/; max-age=' + maxAge + '; SameSite=Lax' + secure;
		} catch (e) {
			// Cookie blocked (ad blocker, privacy mode), still use in-memory ID for this session
			if (window.trackSureDebug) {
				console.warn('[TrackSure] Cookie storage blocked, using in-memory client ID');
			}
		}
		
		return clientId;
	}

	/**
	 * Get or create session ID (30-minute timeout).
	 * Gracefully handles sessionStorage unavailable (iOS Safari private mode, etc.)
	 */
	function getSessionId() {
		const now = Date.now();
		let sessionId = null;
		let lastActivity = 0;
		
		// Try sessionStorage first
		try {
			sessionId = sessionStorage.getItem(STORAGE_SESSION_ID);
			lastActivity = parseInt(sessionStorage.getItem(STORAGE_LAST_ACTIVITY) || '0', 10);
		} catch (e) {
			// sessionStorage blocked (private browsing, etc.)
		}
		
		// Fallback: check cookie if sessionStorage unavailable or empty
		if (!sessionId) {
			const match = document.cookie.match(/_ts_sid=([^;]+)/);
			if (match) {
				sessionId = match[1];
				
				// Try to restore to sessionStorage for faster access next time
				try {
					sessionStorage.setItem(STORAGE_SESSION_ID, sessionId);
				} catch (e) {
					// Still can't use sessionStorage, continue with cookie-only mode
				}
			}
		}

		// Check if session expired.
		if (!sessionId || (lastActivity > 0 && (now - lastActivity) > sessionTimeout)) {
			// Start new session.
			sessionId = generateUUID();
			isFirstVisit = !lastActivity; // First visit if no previous activity
			
			try {
				sessionStorage.setItem(STORAGE_SESSION_ID, sessionId);
				sessionStorage.setItem(STORAGE_SESSION_START, now.toString());
			} catch (e) {
				// Can't use sessionStorage, cookie-only mode
			}
		}

		// Update last activity.
		try {
			sessionStorage.setItem(STORAGE_LAST_ACTIVITY, now.toString());
		} catch (e) {
			// Ignore - will fallback to cookie
		}
		
		// ALWAYS set cookie for PHP to read (session cookie, expires when browser closes)
		// Also serves as backup if sessionStorage fails
		try {
			const secure = window.location.protocol === 'https:' ? '; Secure' : '';
			// Calculate expiry time for session timeout
			const maxAge = Math.floor(sessionTimeout / 1000); // Convert ms to seconds
			document.cookie = '_ts_sid=' + sessionId + '; path=/; max-age=' + maxAge + '; SameSite=Lax' + secure;
		} catch (e) {
			// Cookie blocked - tracking may be limited
			if (window.trackSureDebug) {
				console.warn('Cookies blocked - session persistence limited');
			}
		}
		
		return sessionId;
	}

	/**
	 * Detect device type.
	 */
	function getDeviceType() {
		const ua = navigator.userAgent;
		if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
			return 'tablet';
		}
		if (/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * Detect browser name and version.
	 */
	function getBrowserInfo() {
		const ua = navigator.userAgent;
		let browser = 'Unknown';
		let version = 'Unknown';

		if (ua.indexOf('Firefox') > -1) {
			browser = 'Firefox';
			version = ua.match(/Firefox\/(\d+\.\d+)/)?.[1] || 'Unknown';
		} else if (ua.indexOf('Edg') > -1) {
			browser = 'Edge';
			version = ua.match(/Edg\/(\d+\.\d+)/)?.[1] || 'Unknown';
		} else if (ua.indexOf('Chrome') > -1) {
			browser = 'Chrome';
			version = ua.match(/Chrome\/(\d+\.\d+)/)?.[1] || 'Unknown';
		} else if (ua.indexOf('Safari') > -1) {
			browser = 'Safari';
			version = ua.match(/Version\/(\d+\.\d+)/)?.[1] || 'Unknown';
		} else if (ua.indexOf('MSIE') > -1 || ua.indexOf('Trident') > -1) {
			browser = 'IE';
			version = ua.match(/(?:MSIE |rv:)(\d+\.\d+)/)?.[1] || 'Unknown';
		}

		return { browser, version };
	}

	/**
	 * Detect operating system.
	 */
	function getOSInfo() {
		const ua = navigator.userAgent;
		let os = 'Unknown';
		let version = 'Unknown';

		if (/Windows NT (\d+\.\d+)/.test(ua)) {
			os = 'Windows';
			version = ua.match(/Windows NT (\d+\.\d+)/)?.[1] || 'Unknown';
		} else if (/Mac OS X (\d+[._]\d+)/.test(ua)) {
			os = 'macOS';
			version = ua.match(/Mac OS X (\d+[._]\d+)/)?.[1]?.replace('_', '.') || 'Unknown';
		} else if (/Android (\d+\.\d+)/.test(ua)) {
			os = 'Android';
			version = ua.match(/Android (\d+\.\d+)/)?.[1] || 'Unknown';
		} else if (/iPhone OS (\d+_\d+)/.test(ua) || /iPad.*OS (\d+_\d+)/.test(ua)) {
			os = 'iOS';
			version = ua.match(/(?:iPhone|iPad).*OS (\d+_\d+)/)?.[1]?.replace('_', '.') || 'Unknown';
		} else if (/Linux/.test(ua)) {
			os = 'Linux';
		}

		return { os, version };
	}

	/**
	 * Get viewport dimensions.
	 */
	function getViewport() {
		return {
			width: window.innerWidth || document.documentElement.clientWidth,
			height: window.innerHeight || document.documentElement.clientHeight
		};
	}

	/**
	 * Get screen resolution.
	 */
	function getScreenResolution() {
		return {
			width: window.screen.width,
			height: window.screen.height,
			pixel_ratio: window.devicePixelRatio || 1
		};
	}

	/**
	 * Get connection quality (Network Information API).
	 */
	function getConnectionInfo() {
		const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
		if (!connection) {
			return null;
		}

		return {
			effective_type: connection.effectiveType || null, // 4g, 3g, 2g, slow-2g
			downlink: connection.downlink || null, // Mbps
			rtt: connection.rtt || null, // Round-trip time in ms
			save_data: connection.saveData || false
		};
	}

	/**
	 * Get cookie value by name.
	 * Used for extracting Facebook cookies (_fbp, _fbc) for Meta CAPI.
	 */
	function getCookie(name) {
		const value = `; ${document.cookie}`;
		const parts = value.split(`; ${name}=`);
		if (parts.length === 2) {
			return parts.pop().split(';').shift();
		}
		return null;
	}

	/**
	 * Get Facebook attribution cookies for Meta CAPI Event Match Quality.
	 * Returns fbp (browser ID) and fbc (click ID) for better server-side matching.
	 */
	function getFacebookCookies() {
		return {
			fbp: getCookie('_fbp'),
			fbc: getCookie('_fbc')
		};
	}

	/**
	 * Get Google Ads GCLID from cookie or URL parameter.
	 * Critical for Google Ads conversion attribution.
	 * 
	 * iOS Safari Private Mode & Ad Blocker Resilient
	 * 
	 * @return {string|null} GCLID or null
	 */
	function getGoogleClickId() {
		try {
			// Check _gcl_aw cookie (Google Ads Click ID storage).
			const gclCookie = getCookie('_gcl_aw');
			if (gclCookie) {
				// Format: GCL.1234567890.CjwKCAiA...
				const parts = gclCookie.split('.');
				if (parts.length >= 3 && parts[2]) {
					return parts[2];
				}
			}
		} catch (e) {
			// Cookie reading blocked (iOS private mode, ad blocker)
			if (window.trackSureDebug) {
				console.warn('[TrackSure] GCLID cookie read blocked:', e);
			}
		}
		
		// Fallback: Check URL parameter (in case cookie hasn't been set yet or was blocked).
		try {
			const urlParams = new URLSearchParams(window.location.search);
			const gclidParam = urlParams.get('gclid');
			if (gclidParam) {
				// Store in sessionStorage for persistence if cookie blocked
				try {
					sessionStorage.setItem('tracksure_gclid', gclidParam);
				} catch (e) {
					// Storage blocked, that's ok - we have it for this request
				}
				return gclidParam;
			}
		} catch (e) {
			// URL parsing blocked (should never happen, but handle anyway)
		}
		
		// Final fallback: Check sessionStorage (in case URL param was captured earlier)
		try {
			const storedGclid = sessionStorage.getItem('tracksure_gclid');
			if (storedGclid) {
				return storedGclid;
			}
		} catch (e) {
			// sessionStorage blocked
		}
		
		return null;
	}

	/**
	 * Classify referrer (search engine, social, AI chatbot, direct, referral).
	 * This function works WITHOUT UTM parameters - provides smart source attribution
	 * based on the referring website.
	 */
	function classifyReferrer(referrer) {
		if (!referrer || referrer === '') {
			return { type: 'direct', source: '(direct)', medium: '(none)' };
		}

		try {
			const hostname = new URL(referrer).hostname.toLowerCase();

			// Search engines.
			const searchEngines = {
				'google': 'google',
				'bing': 'bing',
				'yahoo': 'yahoo',
				'duckduckgo': 'duckduckgo',
				'baidu': 'baidu',
				'yandex': 'yandex',
				'ask.com': 'ask',
				'aol.com': 'aol',
				'ecosia.org': 'ecosia',
				'qwant.com': 'qwant'
			};
			for (const [key, name] of Object.entries(searchEngines)) {
				if (hostname.includes(key)) {
					return { type: 'search', source: name, medium: 'organic' };
				}
			}

			// Social platforms (HYROS/ClickMagick competitive list - 23 platforms).
			const socialPlatforms = {
				'facebook.com': 'facebook',
				'fb.com': 'facebook',
				'fb.me': 'facebook',
				'm.facebook.com': 'facebook',
				'instagram.com': 'instagram',
				'ig.me': 'instagram',
				'instagr.am': 'instagram',
				'linkedin.com': 'linkedin',
				'lnkd.in': 'linkedin',
				'twitter.com': 'twitter',
				'x.com': 'twitter',
				't.co': 'twitter',
				'pinterest.com': 'pinterest',
				'pin.it': 'pinterest',
				'reddit.com': 'reddit',
				'redd.it': 'reddit',
				'tiktok.com': 'tiktok',
				'vm.tiktok.com': 'tiktok',
				'snapchat.com': 'snapchat',
				'sc.com': 'snapchat',
				'whatsapp.com': 'whatsapp',
				'wa.me': 'whatsapp',
				'chat.whatsapp.com': 'whatsapp',
				'youtube.com': 'youtube',
				'youtu.be': 'youtube',
				'm.youtube.com': 'youtube',
				'vimeo.com': 'vimeo',
				'tumblr.com': 'tumblr',
				't.umblr.com': 'tumblr',
				'telegram.org': 'telegram',
				'telegram.me': 'telegram',
				't.me': 'telegram',
				'discord.com': 'discord',
				'discord.gg': 'discord',
				'threads.net': 'threads',
				'wechat.com': 'wechat',
				'weixin.qq.com': 'wechat',
				'weibo.com': 'weibo',
				'weibo.cn': 'weibo',
				'clubhouse.com': 'clubhouse',
				'joinclubhouse.com': 'clubhouse',
				'mastodon.social': 'mastodon',
				'mastodon.online': 'mastodon',
				'bsky.app': 'bluesky',
				'bluesky.app': 'bluesky',
				'line.me': 'line',
				'kakaotalk.com': 'kakao',
				'kakao.com': 'kakao',
				'viber.com': 'viber'
			};
			for (const [domain, name] of Object.entries(socialPlatforms)) {
				if (hostname.includes(domain)) {
					return { type: 'social', source: name, medium: 'social' };
				}
			}

			// AI Chatbots (expanded list).
			const aiChatbots = {
				'chat.openai.com': 'chatgpt',
				'openai.com': 'chatgpt',
				'claude.ai': 'claude',
				'anthropic.com': 'claude',
				'perplexity.ai': 'perplexity',
				'gemini.google.com': 'gemini',
				'bard.google.com': 'bard',
				'copilot.microsoft.com': 'copilot',
				'bing.com/chat': 'copilot',
				'you.com': 'you_chat',
				'character.ai': 'character_ai',
				'poe.com': 'poe'
			};
			for (const [domain, name] of Object.entries(aiChatbots)) {
				if (hostname.includes(domain)) {
					return { type: 'ai_chatbot', source: name, medium: 'ai' };
				}
			}

			// Referral (external site) - use hostname as source.
			return { type: 'referral', source: hostname, medium: 'referral' };
		} catch (e) {
			// Invalid URL
			return { type: 'direct', source: '(direct)', medium: '(none)' };
		}
	}

	/**
	 * Get UTM parameters and ad platform click IDs from URL.
	 * Supports all major ad platforms: Google, Facebook, Microsoft, TikTok, Twitter, LinkedIn, Snapchat, Impact.
	 */
	function getUTMParams() {
		const params = new URLSearchParams(window.location.search);
		return {
			// UTM parameters
			utm_source: params.get('utm_source') || null,
			utm_medium: params.get('utm_medium') || null,
			utm_campaign: params.get('utm_campaign') || null,
			utm_term: params.get('utm_term') || null,
			utm_content: params.get('utm_content') || null,
			// Ad platform click IDs (for attribution without UTMs)
			gclid: params.get('gclid') || null,           // Google Ads
			fbclid: params.get('fbclid') || null,         // Facebook Ads
			msclkid: params.get('msclkid') || null,       // Microsoft/Bing Ads
			ttclid: params.get('ttclid') || null,         // TikTok Ads
			twclid: params.get('twclid') || null,         // Twitter Ads
			li_fat_id: params.get('li_fat_id') || null,   // LinkedIn Ads
			irclickid: params.get('irclickid') || null,   // Impact Radius
			ScCid: params.get('ScCid') || null            // Snapchat Ads
		};
	}

	/**
	 * Check if user is logged in (WordPress body class).
	 */
	function isLoggedIn() {
		return document.body.classList.contains('logged-in');
	}

	/**
	 * ================================================================
	 * TrackSure User Data Extractor
	 * ================================================================
	 * Intelligent user data capture from ANY form (WooCommerce blocks,
	 * shortcodes, FluentCart, EDD, SureCart, Contact Form 7, Gravity,
	 * WPForms, Elementor, custom forms, etc.)
	 * 
	 * Strategy:
	 * - 50+ regex patterns for field name matching (fuzzy matching)
	 * - Progressive capture with sessionStorage caching
	 * - Works with blocks, shortcodes, page builders
	 * - Zero configuration needed
	 */
	const TrackSure_UserDataExtractor = {
		// Regex patterns for email field detection
		emailPatterns: [
			/email/i, /e-mail/i, /mail/i, /user_email/i, /your-email/i,
			/billing_email/i, /shipping_email/i, /contact_email/i,
			/field.*email/i, /input.*email/i, /wpforms.*email/i,
			/gform.*email/i, /forminator.*email/i, /ninja.*email/i,
			/elementor.*email/i, /fluent.*email/i
		],
		
		// Regex patterns for phone field detection
		phonePatterns: [
			/phone/i, /telephone/i, /mobile/i, /cell/i, /tel/i,
			/billing_phone/i, /shipping_phone/i, /contact.*phone/i,
			/field.*phone/i, /wpforms.*phone/i, /gform.*phone/i
		],
		
		// Regex patterns for first name
		firstNamePatterns: [
			/first.*name/i, /fname/i, /given.*name/i, /forename/i,
			/billing_first_name/i, /shipping_first_name/i,
			/field.*first/i, /wpforms.*first/i
		],
		
		// Regex patterns for last name
		lastNamePatterns: [
			/last.*name/i, /lname/i, /surname/i, /family.*name/i,
			/billing_last_name/i, /shipping_last_name/i,
			/field.*last/i, /wpforms.*last/i
		],
		
		// Regex patterns for address
		addressPatterns: [
			/address/i, /street/i, /billing_address/i, /shipping_address/i,
			/addr/i, /field.*address/i
		],
		
		// Regex patterns for city
		cityPatterns: [
			/city/i, /town/i, /billing_city/i, /shipping_city/i,
			/field.*city/i
		],
		
		// Regex patterns for state/region
		statePatterns: [
			/state/i, /province/i, /region/i, /billing_state/i,
			/shipping_state/i, /field.*state/i
		],
		
		// Regex patterns for zip/postal code
		zipPatterns: [
			/zip/i, /postal/i, /postcode/i, /billing_postcode/i,
			/shipping_postcode/i, /field.*zip/i, /field.*postal/i
		],
		
		// Regex patterns for country
		countryPatterns: [
			/country/i, /billing_country/i, /shipping_country/i,
			/field.*country/i
		],
		
		// Cached user data
		cachedData: {},
		
		/**
		 * Initialize - load cached data from sessionStorage
		 */
		init: function() {
			try {
				const cached = sessionStorage.getItem('tracksure_user_data');
				if (cached) {
					this.cachedData = JSON.parse(cached);
				}
			} catch (e) {
				// sessionStorage unavailable
			}
		},
		
		/**
		 * Match field name against pattern array
		 */
		matchesPattern: function(fieldName, patterns) {
			if (!fieldName) return false;
			return patterns.some(pattern => pattern.test(fieldName));
		},
		
		/**
		 * Extract user data from a form element
		 */
		extractFromForm: function(form) {
			const userData = {};
			const inputs = form.querySelectorAll('input, select, textarea');
			
			inputs.forEach(input => {
				const name = input.name || input.id || '';
				const value = input.value ? input.value.trim() : '';
				
				if (!value) return; // Skip empty fields
				
				// Email detection
				if (!userData.email && this.matchesPattern(name, this.emailPatterns)) {
					// Basic email validation
					if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
						userData.email = value;
					}
				}
				
				// Phone detection
				else if (!userData.phone && this.matchesPattern(name, this.phonePatterns)) {
					// Remove formatting, keep numbers and +
					const cleanPhone = value.replace(/[^\d+]/g, '');
					if (cleanPhone.length >= 10) {
						userData.phone = cleanPhone;
					}
				}
				
				// First name detection
				else if (!userData.first_name && this.matchesPattern(name, this.firstNamePatterns)) {
					userData.first_name = value;
				}
				
				// Last name detection
				else if (!userData.last_name && this.matchesPattern(name, this.lastNamePatterns)) {
					userData.last_name = value;
				}
				
				// Address detection
				else if (!userData.address && this.matchesPattern(name, this.addressPatterns)) {
					userData.address = value;
				}
				
				// City detection
				else if (!userData.city && this.matchesPattern(name, this.cityPatterns)) {
					userData.city = value;
				}
				
				// State detection
				else if (!userData.state && this.matchesPattern(name, this.statePatterns)) {
					userData.state = value;
				}
				
				// Zip detection
				else if (!userData.zip && this.matchesPattern(name, this.zipPatterns)) {
					userData.zip = value;
				}
				
				// Country detection
				else if (!userData.country && this.matchesPattern(name, this.countryPatterns)) {
					userData.country = value;
				}
			});
			
			return userData;
		},
		
		/**
		 * Monitor form fields for changes and cache data
		 */
		monitorFormFields: function() {
			const self = this;
			
			// Monitor all form submissions
			document.addEventListener('submit', function(e) {
				if (e.target.tagName === 'FORM') {
					const userData = self.extractFromForm(e.target);
					if (Object.keys(userData).length > 0) {
						// Merge with cached data (new data takes priority)
						self.cachedData = Object.assign({}, self.cachedData, userData);
						
						// Save to sessionStorage
						try {
							sessionStorage.setItem('tracksure_user_data', JSON.stringify(self.cachedData));
						} catch (e) {
							// Ignore if sessionStorage unavailable
						}
					}
				}
			}, true); // Use capture phase to catch all forms
			
			// Also monitor field changes for progressive capture
			document.addEventListener('blur', function(e) {
				if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
					const form = e.target.closest('form');
					if (form) {
						const userData = self.extractFromForm(form);
						if (Object.keys(userData).length > 0) {
							self.cachedData = Object.assign({}, self.cachedData, userData);
							try {
								sessionStorage.setItem('tracksure_user_data', JSON.stringify(self.cachedData));
							} catch (e) {
								// Ignore
							}
						}
					}
				}
			}, true);
		},
		
		/**
		 * Get cached user data
		 */
		getData: function() {
			return this.cachedData;
		}
	};
	
	// Initialize user data extractor
	TrackSure_UserDataExtractor.init();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			TrackSure_UserDataExtractor.monitorFormFields();
		});
	} else {
		TrackSure_UserDataExtractor.monitorFormFields();
	}

	/**
	 * Add temporal parameters to event for time-based analytics.
	 * 
	 * CRITICAL: This MUST match the PHP version in class-tracksure-event-builder.php
	 * to ensure browser and server events have identical temporal parameters.
	 * 
	 * Automatically adds date/time context to ALL events:
	 * - event_date: YYYY-MM-DD (local timezone)
	 * - event_time: HH:MM:SS (local timezone)
	 * - event_hour: 0-23 (local timezone)
	 * - day_of_week: Monday-Sunday (local timezone)
	 * - day_of_week_number: 0-6 (0=Sunday, 6=Saturday)
	 * - week_of_year: 1-53 (ISO week number)
	 * - month_name: January-December (for monthly trends)
	 * - month_number: 1-12 (numeric for sorting)
	 * - quarter: Q1-Q4 (seasonal campaigns)
	 * - year: 2026 (yearly trends)
	 * - is_weekend: true/false (weekend vs weekday)
	 * - timezone: Browser timezone name (e.g., "America/New_York")
	 * 
	 * Benefits:
	 * 1. Admin UI: Filter events by day/week/month in dashboard
	 * 2. Meta Custom Audiences: "Weekend shoppers in Q4"
	 * 3. Behavior Insights: "Products most viewed on Monday mornings"
	 * 4. Conversion Analysis: Weekday vs weekend purchase rates
	 * 
	 * @param {Object} params - Existing event parameters
	 * @returns {Object} - Parameters with temporal data added
	 */
	function addTemporalParams(params) {
		const now = new Date();
		
		// Date and time strings (local timezone)
		const year = now.getFullYear();
		const month = now.getMonth() + 1; // 0-11 → 1-12
		const date = now.getDate();
		const hours = now.getHours(); // 0-23
		const minutes = now.getMinutes();
		const seconds = now.getSeconds();
		
		// Format: YYYY-MM-DD
		params.event_date = year + '-' + 
			String(month).padStart(2, '0') + '-' + 
			String(date).padStart(2, '0');
		
		// Format: HH:MM:SS
		params.event_time = String(hours).padStart(2, '0') + ':' + 
			String(minutes).padStart(2, '0') + ':' + 
			String(seconds).padStart(2, '0');
		
		// Event hour (0-23)
		params.event_hour = hours;
		
		// Day of week
		const dayOfWeekNumber = now.getDay(); // 0 (Sunday) - 6 (Saturday)
		const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		params.day_of_week_number = dayOfWeekNumber;
		params.day_of_week = dayNames[dayOfWeekNumber];
		params.is_weekend = (dayOfWeekNumber === 0 || dayOfWeekNumber === 6);
		
		// Week of year (ISO 8601)
		params.week_of_year = getWeekNumber(now);
		
		// Month
		const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
			'July', 'August', 'September', 'October', 'November', 'December'];
		params.month_number = month;
		params.month_name = monthNames[month - 1];
		
		// Quarter (Q1-Q4)
		params.quarter = 'Q' + Math.ceil(month / 3);
		
		// Year
		params.year = year;
		
		// Browser timezone
		try {
			params.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
		} catch (e) {
			params.timezone = 'UTC'; // Fallback if Intl API not available
		}
		
		return params;
	}
	
	/**
	 * Get ISO week number for a date.
	 * 
	 * @param {Date} date - Date object
	 * @returns {number} - Week number (1-53)
	 */
	function getWeekNumber(date) {
		const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
		const dayNum = d.getUTCDay() || 7;
		d.setUTCDate(d.getUTCDate() + 4 - dayNum);
		const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
		return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
	}

	/**
	 * Track event with full context enrichment (aligned with events.json/params.json).
	 * 
	 * V2 Update: Now properly structures event_params as a separate object
	 * to align with server-side Event Recorder expectations and registry validation.
	 * 
	 * V3 Update: Added client-side registry validation for immediate feedback.
	 * 
	 * @param {string} eventName - Event name (must exist in registry)
	 * @param {Object} eventParams - Event parameters (key-value pairs)
	 */
	function track(eventName, eventParams = {}) {
		// Runtime check: ensure tracking still enabled
		if (!trackingCurrentlyEnabled) {
			if (window.trackSureDebug) {
				console.log('Tracking disabled, event ignored:', eventName);
			}
			return;
		}

		// Client-side validation (if registry loaded)
		const validation = validateEvent(eventName, eventParams);
		if (!validation.valid) {
			if (window.trackSureDebug) {
				console.warn('[TrackSure] Event validation failed:', eventName);
				validation.errors.forEach(function(error) {
					console.warn('  - ' + error);
				});
			}
			// Still send to server for server-side validation (don't block completely)
			// This allows custom events that might be registered via plugins
		}

		try {
			const browserInfo = getBrowserInfo();
			const osInfo = getOSInfo();
			const viewport = getViewport();
			const screen = getScreenResolution();
			const connection = getConnectionInfo();

			// Build event with complete context
			const sessionId = getSessionId();
			
			// Generate deterministic event_id for Meta CAPI compliance
			// Browser and server will generate the SAME ID for the same action
			const eventId = eventParams.event_id || generateDeterministicEventId(sessionId, eventName, eventParams);

			const event = {
				event_name: eventName,
				event_id: eventId,
				client_id: getClientId(),
				session_id: sessionId,
				occurred_at: Math.floor(Date.now() / 1000),
				page_url: window.location.href,
				page_path: window.location.pathname,
				page_title: document.title || 'Untitled Page',
				referrer: document.referrer || null,
				event_source: 'browser',
				browser_fired:1,
				browser_fired_at: Math.floor(Date.now() / 1000),
				device_type: getDeviceType(),
				browser: browserInfo.browser,
				os : osInfo.os,
	
				// Screen & viewport (CRITICAL for device analytics)
				screen_width: screen.width,
				screen_height: screen.height,
				viewport_width: viewport.width,
				viewport_height: viewport.height,
				pixel_ratio: screen.pixel_ratio,
		
				// User state (CRITICAL for logged-in vs guest tracking)
				user_logged_in: isLoggedIn(),
		
				// Language (CRITICAL for international attribution)
				language: navigator.language || navigator.userLanguage,
		
			// UTM parameters (CRITICAL for attribution)
			...getUTMParams(),
			
			// Network connection quality (CRITICAL for performance analysis & mobile optimization)
			connection_type: connection ? connection.effectiveType : null,
			connection_downlink: connection ? connection.downlink : null,
			connection_rtt: connection ? connection.rtt : null,
			save_data_mode: connection ? connection.saveData : null
		};
		
		// Properly structure event_params (separate from root-level tracking fields)
		// Remove event_id from eventParams if accidentally included (it belongs at root level)
		if (eventParams && Object.keys(eventParams).length > 0) {
			const cleanedParams = { ...eventParams };
			delete cleanedParams.event_id; // event_id must be at root, not in params
			event.event_params = cleanedParams;
		} else {
			// Initialize event_params if not provided
			event.event_params = {};
		}
		
		// CRITICAL: Add temporal parameters to ALL browser events
		// This ensures browser events have same temporal data as server events
		event.event_params = addTemporalParams(event.event_params);
		
		// Merge cached user data (progressive capture from forms)
		const cachedUserData = TrackSure_UserDataExtractor.getData();
		if (cachedUserData && Object.keys(cachedUserData).length > 0) {
			// Add as user_data object if not already provided
			if (!event.user_data) {
				event.user_data = cachedUserData;
			} else {
				// Merge with provided user_data (provided data takes priority)
				event.user_data = Object.assign({}, cachedUserData, event.user_data);
			}
		}
		
		// Also merge from config.user if logged in (from PHP)
		if (config.user && Object.keys(config.user).length > 0) {
			if (!event.user_data) {
				event.user_data = config.user;
			} else {
				// Merge (logged-in user data takes priority)
				event.user_data = Object.assign({}, event.user_data, config.user);
			}
		}

		// Add Facebook cookies (_fbp, _fbc) for Meta CAPI Event Match Quality
		// This dramatically improves attribution matching and Event Match Quality score
		const fbCookies = getFacebookCookies();
		if (fbCookies.fbp || fbCookies.fbc) {
			if (!event.user_data) {
				event.user_data = {};
			}
			if (fbCookies.fbp) event.user_data.fbp = fbCookies.fbp;
			if (fbCookies.fbc) event.user_data.fbc = fbCookies.fbc;
		}

		// CRITICAL: Add Google Ads Click ID (GCLID) for Google Ads attribution
		// This enables accurate conversion tracking and ROI measurement
		const gclid = getGoogleClickId();
		if (gclid) {
			// Store GCLID in event params for Google Ads destination
			if (!event.event_params) {
				event.event_params = {};
			}
			event.event_params.gclid = gclid;
		}

		// NEW: Send to browser pixels IMMEDIATELY (for real-time attribution)
		// This ensures ad platforms get instant browser-side tracking while
		// backend processing continues for data quality and deduplication
		if (window.TrackSure && window.TrackSure.sendToPixels) {
			try {
				window.TrackSure.sendToPixels(event);
			} catch (pixelError) {
				// Never break tracking if pixels fail
				if (config.debug) {
					console.error('Pixel delivery error:', pixelError);
				}
			}
		}

		eventQueue.push(event);			if (eventQueue.length >= batchSize) {
				sendBatch();
			} else {
				clearTimeout(batchTimer);
				batchTimer = setTimeout(sendBatch, batchTimeout);
			}
		} catch (error) {
			// Silently fail - never break user's site
			if (config.debug) {
				console.error('Track error:', error);
			}
		}
	}

	/**
	 * Confirm browser pixel fired by calling pixel callback endpoint.
	 * This updates browser_fired flag in database for transparent reporting.
	 */
	function confirmPixelFired(eventId, destination) {
		try {
			const callbackEndpoint = config.endpoint.replace('/collect', '/cb');
			const payload = {
				event_id: eventId,
				destination: destination,
				status: 'success'
			};
			
			// Use sendBeacon for reliability (works even on page unload)
			if (navigator.sendBeacon) {
				const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
				navigator.sendBeacon(callbackEndpoint, blob);
			} else {
				// Fallback to fetch with keepalive
				fetch(callbackEndpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(payload),
					keepalive: true,
					credentials: 'same-origin'
				}).catch(function(err) {
					// Silent fail - don't break tracking
					if (config.debug) {
						console.error('Pixel callback error:', err);
					}
				});
			}
		} catch (error) {
			// Silent fail - never break user's site
			if (config.debug) {
				console.error('confirmPixelFired error:', error);
			}
		}
	}

	/**
	 * Get list of pixel destinations that are actually active (mapper + SDK loaded).
	 * DYNAMIC: Iterates all registered destinations from Event Bridge.
	 * Pro and 3rd-party destinations are automatically detected.
	 * Single source of truth: pixelMappers (registered by PHP) + sdkChecks (SDK detection).
	 */
	function getActivePixelDestinations() {
		const destinations = [];
		if (window.TrackSure && window.TrackSure.pixelMappers) {
			const mappers = window.TrackSure.pixelMappers;
			const sdkChecks = window.TrackSure.sdkChecks || {};
			Object.keys(mappers).forEach(function(destId) {
				try {
					// Each destination registers its own SDK check function
					const checker = sdkChecks[destId];
					if (typeof checker === 'function' && checker()) {
						destinations.push(destId);
					} else if (!checker && typeof mappers[destId] === 'function') {
						// Fallback: if no sdkCheck registered, assume active if mapper exists
						// (backward compatible with 3rd-party destinations that don't register sdk_check)
						destinations.push(destId);
					}
				} catch (e) {
					// Silent fail per destination
				}
			});
		}
		return destinations;
	}

	/**
	 * Confirm browser pixels fired for a batch of events.
	 * Updates destinations_sent in DB for admin UI debugging transparency.
	 * Only confirms for destinations whose SDK is actually loaded on the page.
	 */
	function confirmBrowserPixels(queuedEvents) {
		try {
			const activeDestinations = getActivePixelDestinations();
			if (activeDestinations.length === 0) return;

			queuedEvents.forEach(function(event) {
				activeDestinations.forEach(function(dest) {
					confirmPixelFired(event.event_id, dest);
				});
			});
		} catch (e) {
			// Silent fail — never break tracking
		}
	}

	/**
	 * Send event batch to server.
	 */
	function sendBatch() {
		if (eventQueue.length === 0) {
			return;
		}

		try {
			const payload = {
				events: eventQueue.slice(), // Clone array.
				client_id: getClientId(),
				session_id: getSessionId()
			};

			// Clear queue immediately (prevents duplicates on rapid calls).
			const queuedEvents = eventQueue.slice();
			eventQueue = [];
			clearTimeout(batchTimer);

			// Send via sendBeacon (reliable even on page unload).
			if (navigator.sendBeacon) {
				try {
					const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
					const success = navigator.sendBeacon(endpoint, blob);
					
					if (success) {
						// Delay pixel confirmation to give server time to process the batch.
						// sendBeacon is fire-and-forget, so we wait for the server to flush
						// the Event Queue before confirming destinations_sent.
						setTimeout(function() {
							confirmBrowserPixels(queuedEvents);
						}, 2000);
					} else {
						// sendBeacon queue full — restore events for retry
						eventQueue = queuedEvents.concat(eventQueue);
						if (config.debug) {
							console.warn('sendBeacon queue full, will retry');
						}
					}
				} catch (e) {
					// Restore events on error
					eventQueue = queuedEvents.concat(eventQueue);
					if (config.debug) {
						console.error('sendBeacon error:', e);
					}
				}
			} else {
				// Fallback to fetch with keepalive (works in modern browsers).
				try {
					fetch(endpoint, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify(payload),
						keepalive: true, // Ensures delivery even on page unload
						credentials: 'same-origin'
					}).then(function(response) {
						if (response.ok) {
							// Server responded — events are in DB. Confirm pixel destinations.
							confirmBrowserPixels(queuedEvents);
						}
					}).catch(function(err) {
						// Restore events on network error
						eventQueue = queuedEvents.concat(eventQueue);
						if (config.debug) {
							console.error('Fetch error:', err);
						}
					});
				} catch (e) {
					// Restore events on error
					eventQueue = queuedEvents.concat(eventQueue);
					if (config.debug) {
						console.error('Fetch error:', e);
					}
				}
			}
		} catch (error) {
			// Never break user's site
			if (config.debug) {
				console.error('sendBatch error:', error);
			}
		}
	}

	/**
	 * Track page view with first_visit and session_start detection.
	 */
	function trackPageView() {
		const referrer = classifyReferrer(document.referrer);
		const utmParams = getUTMParams();
		
		// DEBUG: Log tracking data (REMOVE IN PRODUCTION)
		if (window.trackSureDebug || config.debug) {
			console.group('Page View Tracking');
			console.log('URL:', window.location.href);
			console.log('Page Title:', document.title);
			console.log('UTM Params:', utmParams);
			console.log('Referrer:', document.referrer);
			console.log('Classified Source:', referrer);
			console.log('Device Type:', getDeviceType());
			console.log('Browser:', getBrowserInfo());
			console.log('OS:', getOSInfo());
			console.groupEnd();
		}
		
		// Build attribution data - UTM takes priority, fallback to referrer classification
		const attribution = {
			utm_source: utmParams.utm_source || referrer.source,
			utm_medium: utmParams.utm_medium || referrer.medium,
			utm_campaign: utmParams.utm_campaign,
			utm_term: utmParams.utm_term,
			utm_content: utmParams.utm_content,
			referrer: document.referrer || null,
			referrer_type: referrer.type
		};
		
		// Check if this is first visit ever
		try {
			const hasVisited = localStorage.getItem('tracksure_has_visited');
			if (!hasVisited) {
				isFirstVisit = true;
				localStorage.setItem('tracksure_has_visited', 'true');
				track('first_visit', attribution);
			}
		} catch (e) {
			// localStorage unavailable
		}

		// Track session_start if new session (using sessionStorage to persist across page loads)
		try {
			const sessionId = getSessionId();
			const sessionStartKey = 'tracksure_session_' + sessionId + '_started';
			
			if (!sessionStorage.getItem(sessionStartKey)) {
				sessionStorage.setItem(sessionStartKey, 'true');
				track('session_start', Object.assign({}, attribution, {
					is_returning: !isFirstVisit
				}));
			}
		} catch (e) {
			// sessionStorage unavailable, fall back to in-memory check
			if (!sessionStartTracked) {
				sessionStartTracked = true;
				track('session_start', Object.assign({}, attribution, {
					is_returning: !isFirstVisit
				}));
			}
		}

		// Track page_view
		// CRITICAL: Include page_location in event_params so extractContentIdentifier()
		// generates a unique event_id per page. Without this, ALL page_views in the
		// same session produce the same deterministic event_id and get deduped.
		track('page_view', Object.assign({}, attribution, {
			page_location: window.location.href,
			page_title: document.title || 'Untitled Page'
		}));
	}

	/**
	 * Track clicks with rage click and dead click detection.
	 * Enhanced with CSS selector capture for custom goal matching.
	 */
	function trackClicks() {
		const clickHistory = [];
		const rageClickThreshold = 3; // 3 clicks in 1 second = rage
		const rageClickWindow = 1000;

		document.addEventListener('click', function (e) {
			const timestamp = Date.now();
			const target = e.target.closest('a, button, [role="button"], [onclick], input[type="submit"]');
			
			// Rage click detection (rapid clicks on same element)
			const targetPath = getElementPath(e.target);
			clickHistory.push({ path: targetPath, timestamp });
			
			// Clean old clicks
			const recentClicks = clickHistory.filter(c => timestamp - c.timestamp < rageClickWindow);
			clickHistory.length = 0;
			clickHistory.push(...recentClicks);
			
			// Count same-element clicks
			const sameElementClicks = recentClicks.filter(c => c.path === targetPath);
			if (sameElementClicks.length >= rageClickThreshold) {
				track('rage_click', {
					element_type: e.target.tagName.toLowerCase(),
					element_id: e.target.id || null,
					element_class: e.target.className || null,
					element_path: targetPath,
					click_count: sameElementClicks.length
				});
				clickHistory.length = 0; // Reset after reporting
			}

			if (!target) return;

			// Enhanced event data with CSS selector for goal matching
			const eventData = {
				element_type: target.tagName.toLowerCase(),
				element_id: target.id || null,
				element_class: target.className || null,
				element_text: target.textContent.trim().substring(0, 100),
				element_selector: getElementSelector(target), // NEW: CSS selector for goal matching
				element_path: targetPath
			};

			// Outbound link (params.json: link_url, link_domain)
			if (target.tagName === 'A' && target.hostname !== window.location.hostname) {
				track('outbound_click', {
					...eventData,
					link_url: target.href,
					link_domain: target.hostname
				});
			}
			// Phone click tracking (tel: links)
			else if (target.tagName === 'A' && target.href.startsWith('tel:')) {
				track('click_to_call', {
					...eventData,
					phone_number: target.href.replace('tel:', '').trim(),
					link_text: target.textContent.trim()
				});
			}
			// WhatsApp click tracking
			else if (target.tagName === 'A' && (target.href.includes('wa.me') || target.href.includes('whatsapp.com'))) {
				track('click_to_whatsapp', {
					...eventData,
					whatsapp_link: target.href,
					link_text: target.textContent.trim()
				});
			}
			// Email click tracking (mailto: links)
			else if (target.tagName === 'A' && target.href.startsWith('mailto:')) {
				track('click_to_email', {
					...eventData,
					email: target.href.replace('mailto:', '').split('?')[0].trim(),
					link_text: target.textContent.trim()
				});
			}
			else {
				track('click', eventData);
			}

			// File download (params.json: file_name, file_extension)
			if (target.tagName === 'A') {
				const href = target.href.toLowerCase();
				const fileExtensions = ['.pdf', '.zip', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.csv', '.txt', '.jpg', '.png', '.mp4', '.mp3'];
				for (const ext of fileExtensions) {
					if (href.endsWith(ext)) {
						const fileName = target.href.split('/').pop();
						track('file_download', {
							file_name: fileName,
							file_extension: ext,
							link_url: target.href
						});
						break;
					}
				}
			}
		});
	}

	/**
	 * Get CSS selector for an element (for goal matching).
	 * Returns the most specific unique selector possible.
	 */
	function getElementSelector(element) {
		if (!element) return '';
		
		// If has ID, use it (most specific)
		if (element.id) {
			return '#' + element.id;
		}
		
		// Build selector path
		const path = [];
		let current = element;
		
		while (current && current.nodeType === Node.ELEMENT_NODE) {
			let selector = current.tagName.toLowerCase();
			
			// Add classes if available
			if (current.className && typeof current.className === 'string') {
				const classes = current.className.trim().split(/\s+/).filter(Boolean);
				if (classes.length > 0) {
					selector += '.' + classes.join('.');
				}
			}
			
			// Add nth-child if needed for uniqueness
			if (current.parentElement) {
				const siblings = Array.from(current.parentElement.children);
				if (siblings.length > 1) {
					const index = siblings.indexOf(current) + 1;
					selector += ':nth-child(' + index + ')';
				}
			}
			
			path.unshift(selector);
			current = current.parentElement;
			
			// Limit depth to 5 for performance
			if (path.length >= 5) break;
		}
		
		return path.join(' > ');
	}

	/**
	 * Get unique element path (for rage click tracking).
	 */
	function getElementPath(element) {
		const path = [];
		while (element && element.nodeType === Node.ELEMENT_NODE) {
			let selector = element.nodeName.toLowerCase();
			if (element.id) {
				selector += '#' + element.id;
				path.unshift(selector);
				break;
			} else {
				let sibling = element;
				let nth = 1;
				while (sibling.previousElementSibling) {
					sibling = sibling.previousElementSibling;
					if (sibling.nodeName.toLowerCase() === selector) nth++;
				}
				if (nth > 1) selector += ':nth-of-type(' + nth + ')';
			}
			path.unshift(selector);
			element = element.parentNode;
		}
		return path.join(' > ');
	}

	/**
	 * Track time on page with engagement tracking and goal thresholds.
	 * Enhanced with time_on_page parameter for goal matching.
	 */
	function trackTimeOnPage() {
		const startTime = Date.now();
		let engagedTime = 0;
		let lastActivity = Date.now();
		let isEngaged = true;

		// Thresholds for goal matching
		const thresholds = [30, 60, 120, 180, 300]; // seconds: 30s, 1min, 2min, 3min, 5min
		const reached = {};

		// Track active engagement (mouse movement, scroll, keyboard)
		const activity = function() {
			if (!isEngaged) {
				isEngaged = true;
				lastActivity = Date.now();
			}
		};

		document.addEventListener('mousemove', activity, { passive: true });
		document.addEventListener('scroll', activity, { passive: true });
		document.addEventListener('keydown', activity, { passive: true });
		document.addEventListener('click', activity, { passive: true });

		// Check engagement every second
		setInterval(function() {
			if (isEngaged && Date.now() - lastActivity < 5000) {
				engagedTime++;
			} else {
				isEngaged = false;
			}

			// Check thresholds for goal triggers
			const timeOnPage = Math.floor((Date.now() - startTime) / 1000);
			for (const threshold of thresholds) {
				if (timeOnPage >= threshold && !reached[threshold]) {
					reached[threshold] = true;
					track('time_on_page_threshold', {
						time_on_page: timeOnPage, // For goal matching (in seconds)
						time_threshold: threshold,
						engaged_seconds: engagedTime
					});
				}
			}
		}, 1000);

		window.addEventListener('beforeunload', function () {
			const timeSpent = Math.round((Date.now() - startTime) / 1000);
			track('page_exit', { 
				time_on_page: timeSpent,
				engaged_seconds: engagedTime,
				exit_type: 'unload'
			});
			sendBatch(); // Force send.
		});
	}

	/**
	 * Track site search.
	 */
	function trackSearch() {
		const urlParams = new URLSearchParams(window.location.search);
		const searchTerm = urlParams.get('s');
		if (searchTerm) {
			track('search', { search_term: searchTerm });
		}
	}

	/**
	 * Track form interactions.
	 * Enhanced with comprehensive field-level tracking, abandonment detection,
	 * multi-step support, validation errors, and form builder detection.
	 */
	function trackForms() {
		/**
		 * Detect form builder from form structure and classes
		 */
		function detectFormBuilder(form) {
			if (form.classList.contains('gform_wrapper') || form.querySelector('.gform_wrapper')) return 'gravity_forms';
			if (form.classList.contains('wpforms-form') || form.querySelector('.wpforms-form')) return 'wpforms';
			if (form.classList.contains('elementor-form') || form.querySelector('.elementor-form')) return 'elementor';
			if (form.classList.contains('frm-show-form')) return 'formidable';
			if (form.classList.contains('nf-form-cont')) return 'ninja_forms';
			if (form.classList.contains('wpcf7-form')) return 'contact_form_7';
			if (form.classList.contains('mc4wp-form')) return 'mailchimp';
			if (form.classList.contains('fluentform')) return 'fluent_forms';
			if (form.classList.contains('woocommerce-checkout')) return 'woocommerce';
			if (form.classList.contains('forminator-ui')) return 'forminator';
			return 'custom';
		}

		/**
		 * Get all form fields (inputs, textareas, selects)
		 */
		function getFormFields(form) {
			return Array.from(form.elements).filter(el => 
				(el.tagName === 'INPUT' && !['submit', 'button', 'reset', 'hidden'].includes(el.type)) ||
				el.tagName === 'TEXTAREA' ||
				el.tagName === 'SELECT'
			);
		}

		/**
		 * Get field identifier (name, id, or placeholder)
		 */
		function getFieldIdentifier(field) {
			return field.name || field.id || field.placeholder || `field_${field.type}`;
		}

		/**
		 * Detect if form is a lead generation form.
		 * 
		 * Lead forms are contact/inquiry forms (not checkout, login, or search).
		 * Detection criteria:
		 * - Form builder is typically used for lead forms (Contact Form 7, WPForms, Gravity Forms, etc.)
		 * - Has email/phone fields but NOT payment fields
		 * - Not a login, registration, search, or checkout form
		 * 
		 * @param {HTMLFormElement} form - Form element
		 * @param {string} formBuilder - Detected form builder
		 * @param {Array<HTMLElement>} formFields - Form fields
		 * @returns {boolean} True if this is a lead form
		 */
		function detectLeadForm(form, formBuilder, formFields) {
			// Lead generation form builders (automatically classify as lead forms)
			const leadFormBuilders = [
				'contact_form_7',
				'wpforms',
				'gravity_forms',
				'ninja_forms',
				'formidable',
				'fluent_forms',
				'forminator',
				'mailchimp'
			];
			
			if (leadFormBuilders.includes(formBuilder)) {
				return true; // Known lead form builder
			}

			// Exclude non-lead forms
			const isLoginForm = form.querySelector('[name="log"], [name="pwd"], [type="password"]') && 
			                    (form.querySelector('[name="wp-submit"]') || form.action.includes('/wp-login'));
			const isSearchForm = form.querySelector('[name="s"], [type="search"]') || form.role === 'search';
			const isCheckoutForm = formBuilder === 'woocommerce' || 
			                       form.classList.contains('woocommerce-checkout') ||
			                       form.querySelector('[name="payment_method"]');
			const isRegistrationForm = form.querySelector('[name="user_login"], [name="user_email"]') && 
			                          form.action.includes('/wp-login.php?action=register');

			if (isLoginForm || isSearchForm || isCheckoutForm || isRegistrationForm) {
				return false; // Not a lead form
			}

			// Check for lead form indicators (email/phone fields)
			const hasEmailField = formFields.some(field => 
				field.type === 'email' || 
				field.name.toLowerCase().includes('email') ||
				field.placeholder?.toLowerCase().includes('email')
			);
			
			const hasPhoneField = formFields.some(field => 
				field.type === 'tel' || 
				field.name.toLowerCase().includes('phone') ||
				field.name.toLowerCase().includes('tel') ||
				field.placeholder?.toLowerCase().includes('phone')
			);

			const hasNameField = formFields.some(field => 
				field.name.toLowerCase().includes('name') ||
				field.placeholder?.toLowerCase().includes('name')
			);

			// Lead form if it has contact fields (email or phone + name)
			return (hasEmailField || hasPhoneField) && hasNameField;
		}

		const forms = document.querySelectorAll('form');
		
		forms.forEach(form => {
			// Form state tracking
			let formStarted = false;
			let formSubmitted = false;
			const fieldsCompleted = new Map(); // Track which fields are filled
			const fieldFocusTimes = new Map(); // Track time spent on each field
			const validationErrors = new Set(); // Track fields with validation errors

			// Ensure formId is always a string
			const formId = String(form.id || form.name || form.className || 'unnamed');
			const formBuilder = detectFormBuilder(form);
			const formFields = getFormFields(form);
			const totalFields = formFields.length;
			const requiredFields = form.querySelectorAll('[required]').length;

			// ========================================
			// SMART FORM CLASSIFIER
			// ========================================
			// GOAL: Track GENUINE forms (contact, newsletter, registration)
			// SKIP: E-commerce UI, sorting, filtering, product actions
			
			const formAction = form.action || '';
			const formMethod = (form.method || 'get').toLowerCase();
			const formClasses = form.className || '';
			
			// 1. E-COMMERCE FORMS (WooCommerce, EDD, SureCart, etc.)
			const isEcommerceForm = (
				// Add to cart forms
				form.querySelector('[name="add-to-cart"]') ||
				form.querySelector('.single_add_to_cart_button') ||
				form.querySelector('.ajax_add_to_cart') ||
				form.querySelector('.add_to_cart_button') ||
				formClasses.includes('cart') ||
				formClasses.includes('woocommerce-cart-form') ||
				formClasses.includes('woocommerce-checkout') ||
				formClasses.includes('variations_form') ||
				formClasses.includes('product') ||
				formId.includes('product') ||
				// Product quantity/variation forms
				formAction.includes('/product/') ||
				formAction.includes('/cart') ||
				formAction.includes('/checkout') ||
				// Cart update/coupon forms
				form.querySelector('[name="update_cart"]') ||
				form.querySelector('[name="apply_coupon"]') ||
				form.querySelector('[name="quantity"]') ||
				// Easy Digital Downloads
				formClasses.includes('edd_') ||
				form.querySelector('[name="edd_action"]') ||
				// FluentCart
				formClasses.includes('fluent-cart') ||
				formClasses.includes('fluent-checkout')
			);
			
			// 2. SORTING/FILTERING FORMS (shop sorting, product filters)
			const isSortingForm = (
				formClasses.includes('woocommerce-ordering') ||
				formClasses.includes('orderby') ||
				formClasses.includes('filter') ||
				formClasses.includes('search-filter') ||
				form.querySelector('select[name="orderby"]') ||
				form.querySelector('input[name="paged"]') ||
				formId.includes('filter') ||
				formId.includes('sort')
			);
			
			// 3. SEARCH FORMS (site search, product search)
			const isSearchForm = (
				formClasses.includes('search') ||
				formClasses.includes('searchform') ||
				formClasses.includes('wp-block-search') ||
				formClasses.includes('woocommerce-product-search') ||
				formId.includes('search') ||
				form.querySelector('input[name="s"]')
			);
			
			// 4. WORDPRESS CORE FORMS (login, comment - these have own events)
			const isWordPressCoreForm = (
				formClasses.includes('comment-form') ||
				formClasses.includes('commentform') ||
				formId === 'commentform' ||
				form.querySelector('[name="comment_post_ID"]') ||
				formAction.includes('wp-comments-post.php') ||
				formAction.includes('wp-login.php')
			);
			
			// 5. ADMIN/SYSTEM FORMS
			const isSystemForm = (
				formClasses.includes('adminbar') ||
				formClasses.includes('wc-block') ||
				formId.includes('admin')
			);
			
			// 6. SINGLE-BUTTON FORMS (just a submit button, no real fields)
			const isSingleButtonForm = totalFields === 0 && form.querySelector('[type="submit"]');
			
			// 7. GET METHOD FORMS (usually filtering/navigation, not data submission)
			// Exception: Some contact form plugins use GET, so check field count
			const isGetMethodForm = formMethod === 'get' && totalFields < 3;
			
			// VERDICT: Skip if ANY exclusion criteria match
			const shouldSkipForm = (
				isEcommerceForm ||
				isSortingForm ||
				isSearchForm ||
				isWordPressCoreForm ||
				isSystemForm ||
				isSingleButtonForm ||
				isGetMethodForm
			);
			
			if (shouldSkipForm) {
				// Skip all form tracking (view, start, complete, abandon, validation)
				// These are UI elements, not forms we want to track
				return;
			}

			// ============================================================
			// 1. FORM VIEW TRACKING
			// ============================================================
			const observer = new IntersectionObserver((entries) => {
				entries.forEach(entry => {
					if (entry.isIntersecting) {
						track('form_view', { 
							form_id: formId,
							form_name: form.name || null,
							form_action: form.action || null,
							form_builder: formBuilder,
							form_fields_count: totalFields,
							required_fields_count: requiredFields,
							has_required_fields: requiredFields > 0
						});
						observer.disconnect();
					}
				});
			}, { threshold: 0.5 });
			observer.observe(form);

			// ============================================================
			// 2. MULTI-STEP FORM DETECTION & TRACKING
			// ============================================================
			const isMultiStep = form.querySelector('.gform_page, .wpforms-page, .elementor-field-type-step, [data-step]');
			
			if (isMultiStep) {
				const steps = form.querySelectorAll('.gform_page, .wpforms-page, [data-step]');
				
				// Track step views
				steps.forEach((step, index) => {
					const stepObserver = new IntersectionObserver((entries) => {
						if (entries[0].isIntersecting) {
							const stepName = step.getAttribute('data-step-name') || 
											step.querySelector('h3, .gform_page_title, .wpforms-page-title')?.textContent || 
											`Step ${index + 1}`;
							
							track('form_step_view', {
								form_id: formId,
								form_builder: formBuilder,
								step_number: index + 1,
								total_steps: steps.length,
								step_name: stepName.trim()
							});
							stepObserver.disconnect();
						}
					}, { threshold: 0.5 });
					
					stepObserver.observe(step);
				});
				
				// Track step completions (when user clicks "Next")
				const nextButtons = form.querySelectorAll('.gform_next_button, .wpforms-page-next, [data-next-step], .elementor-field-type-next');
				nextButtons.forEach((btn, index) => {
					btn.addEventListener('click', function() {
						track('form_step_complete', {
							form_id: formId,
							form_builder: formBuilder,
							step_number: index + 1,
							total_steps: steps.length,
							fields_completed_in_step: fieldsCompleted.size
						});
					});
				});
			}

			// ============================================================
			// 3. FORM START TRACKING (First Interaction)
			// ============================================================
			form.addEventListener('input', function () {
				if (!formStarted) {
					formStarted = true;
					track('form_start', { 
						form_id: formId,
						form_name: form.name || null,
						form_builder: formBuilder,
						total_fields: totalFields,
						required_fields: requiredFields
					});
				}
			}, { once: true });

			// ============================================================
			// 4. FIELD-LEVEL TRACKING (Progressive Completion)
			// ============================================================
			form.addEventListener('input', function(e) {
				const field = e.target;
				if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(field.tagName)) return;
				if (field.type === 'submit' || field.type === 'button' || field.type === 'reset') return;
				
				const fieldName = getFieldIdentifier(field);
				
				// Track first completion of this field
				if (!fieldsCompleted.has(fieldName) && field.value.trim() !== '') {
					fieldsCompleted.set(fieldName, true);
					
					const fieldPosition = formFields.indexOf(field) + 1;
					const completionPercentage = Math.round((fieldsCompleted.size / totalFields) * 100);
					
					track('form_field_completed', {
						form_id: formId,
						form_builder: formBuilder,
						field_name: fieldName,
						field_type: field.type || field.tagName.toLowerCase(),
						field_position: fieldPosition,
						fields_completed: fieldsCompleted.size,
						total_fields: totalFields,
						completion_percentage: completionPercentage,
						is_required: field.hasAttribute('required')
					});
				}
			});

			// ============================================================
			// 5. FIELD FOCUS TIME TRACKING (Struggle Detection)
			// ============================================================
			form.addEventListener('focus', function(e) {
				const field = e.target;
				if (['INPUT', 'TEXTAREA', 'SELECT'].includes(field.tagName)) {
					fieldFocusTimes.set(field, Date.now());
				}
			}, true);
			
			form.addEventListener('blur', function(e) {
				const field = e.target;
				if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(field.tagName)) return;
				
				const focusTime = fieldFocusTimes.get(field);
				if (focusTime) {
					const timeSpent = Date.now() - focusTime;
					const timeSpentSeconds = Math.round(timeSpent / 1000);
					
					// Track if user spent >10 seconds (struggling with field)
					if (timeSpentSeconds > 10) {
						const fieldName = getFieldIdentifier(field);
						
						track('form_field_struggle', {
							form_id: formId,
							form_builder: formBuilder,
							field_name: fieldName,
							field_type: field.type || field.tagName.toLowerCase(),
							time_spent_seconds: timeSpentSeconds,
							is_required: field.hasAttribute('required')
						});
					}
					
					fieldFocusTimes.delete(field);
				}
			}, true);

			// ============================================================
			// 6. VALIDATION ERROR TRACKING
			// ============================================================
			
			// HTML5 validation errors (invalid event)
			form.addEventListener('invalid', function(e) {
				const field = e.target;
				const fieldName = getFieldIdentifier(field);
				
				if (!validationErrors.has(fieldName)) {
					validationErrors.add(fieldName);
					
					track('form_validation_error', {
						form_id: formId,
						form_builder: formBuilder,
						field_name: fieldName,
						field_type: field.type || field.tagName.toLowerCase(),
						error_type: field.validationMessage || 'validation_failed',
						is_required: field.hasAttribute('required')
					});
				}
			}, true);
			
			// Custom validation errors (blur validation)
			formFields.forEach(field => {
				field.addEventListener('blur', function() {
					if (field.value && !field.checkValidity()) {
						const fieldName = getFieldIdentifier(field);
						
						if (!validationErrors.has(fieldName)) {
							validationErrors.add(fieldName);
							
							track('form_field_error', {
								form_id: formId,
								form_builder: formBuilder,
								field_name: fieldName,
								field_type: field.type || field.tagName.toLowerCase(),
								error_message: field.validationMessage || 'invalid_input'
							});
						}
					}
				});
			});

			// ============================================================
			// 7. FORM SUBMIT TRACKING
			// ============================================================
			form.addEventListener('submit', function () {
				formSubmitted = true;
				
				track('form_submit', { 
					form_id: formId,
					form_name: form.name || null,
					form_destination: form.action || null,
					form_builder: formBuilder,
					fields_completed: fieldsCompleted.size,
					total_fields: totalFields,
					completion_percentage: Math.round((fieldsCompleted.size / totalFields) * 100),
					had_validation_errors: validationErrors.size > 0,
					validation_errors_count: validationErrors.size
				});

				// ============================================================
				// LEAD GENERATION DETECTION & TRACKING
				// ============================================================
				// Detect if this is a lead form (contact/inquiry form, not checkout/login)
				const isLeadForm = detectLeadForm(form, formBuilder, formFields);
				
				if (isLeadForm) {
					const leadData = {};
					
					// Check for value/currency (e.g., from hidden fields or data attributes)
					const valueField = form.querySelector('[name="lead_value"], [data-lead-value]');
					const currencyField = form.querySelector('[name="currency"], [data-currency]');
					
					if (valueField) {
						leadData.value = parseFloat(valueField.value || valueField.dataset.leadValue);
					}
					if (currencyField) {
						leadData.currency = currencyField.value || currencyField.dataset.currency;
					}
					
					track('generate_lead', leadData);
				}
			});

			// ============================================================
			// 8. FORM ABANDONMENT TRACKING
			// ============================================================
			window.addEventListener('beforeunload', function() {
				if (formStarted && !formSubmitted) {
					const lastField = document.activeElement;
					let lastFieldName = 'unknown';
					let lastFieldPosition = 0;
					
					if (lastField && formFields.includes(lastField)) {
						lastFieldName = getFieldIdentifier(lastField);
						lastFieldPosition = formFields.indexOf(lastField) + 1;
					}
					
					const completionPercentage = Math.round((fieldsCompleted.size / totalFields) * 100);
					
					track('form_abandon', {
						form_id: formId,
						form_builder: formBuilder,
						last_field: lastFieldName,
						last_field_position: lastFieldPosition,
						fields_completed: fieldsCompleted.size,
						total_fields: totalFields,
						completion_percentage: completionPercentage,
						had_validation_errors: validationErrors.size > 0,
						validation_errors_count: validationErrors.size
					});
				}
			});
		});
	}

	/**
	 * Track WooCommerce events (add_to_cart, begin_checkout, view_cart, view_item).
	 * Works with WooCommerce blocks, shortcodes, and custom implementations.
	 */
	function trackWooCommerce() {
		// Track Add to Cart button clicks
		document.addEventListener('click', function(e) {
			const target = e.target.closest('.add_to_cart_button, .single_add_to_cart_button, button[name="add-to-cart"], .wp-block-button__link');
			if (!target) return;

			// Check if it's an add to cart button
			if (target.classList.contains('add_to_cart_button') || 
				target.classList.contains('single_add_to_cart_button') ||
				target.name === 'add-to-cart' ||
				target.textContent.toLowerCase().includes('add to cart')) {
				
				const productId = target.getAttribute('data-product_id') || 
								  target.getAttribute('value') || 
								  target.closest('form')?.querySelector('[name="add-to-cart"]')?.value || 
								  'unknown';
				
				const productName = target.getAttribute('data-product_name') || 
									document.querySelector('.product_title')?.textContent ||
									document.querySelector('h1.entry-title')?.textContent ||
									document.title.split('|')[0].trim();

				// Get quantity from form
				const getQuantity = () => {
					const qtyInput = target.closest('form')?.querySelector('[name="quantity"]');
					return qtyInput ? parseInt(qtyInput.value) || 1 : 1;
				};

				track('add_to_cart', {
					item_id: productId,
					item_name: productName,
					quantity: getQuantity(),
					button_text: target.textContent.trim()
				});
			}
		});

		// REMOVED: BeginCheckout, ViewCart, ViewItem browser-side tracking
		// These events are now tracked SERVER-SIDE ONLY by class-tracksure-woocommerce-v2.php
		// Server-side has accurate cart data from WC()->cart and proper order-received page detection
		// Browser-side tracking was causing:
		// - Duplicate events (browser + server)
		// - Empty cart data (DOM scraping before WooCommerce loads)
		// - Wrong currency (symbol guessing vs WooCommerce settings)
		// - InitiateCheckout firing on thank you page (URL detection bug)

		// ONLY track dynamic browser events below (not available server-side):
		// - AddToCart button clicks (immediate user action feedback)
	}

	/**
	 * Track file downloads (PDF, docs, images, etc.).
	 * Supports: .pdf, .doc, .docx, .xls, .xlsx, .zip, .txt, .csv, .ppt, .pptx
	 */
	function trackFileDownloads() {
		document.addEventListener('click', function(e) {
			const link = e.target.closest('a');
			if (!link || !link.href) return;

			const fileExtensions = /\.(pdf|doc|docx|xls|xlsx|zip|rar|7z|txt|csv|ppt|pptx|mp3|mp4|avi|mov|jpg|jpeg|png|gif|svg)$/i;
			
			if (fileExtensions.test(link.href)) {
				const fileName = link.href.split('/').pop();
				const fileType = fileName.split('.').pop().toLowerCase();

				track('file_download', {
					file_name: fileName,
					file_type: fileType,
					file_url: link.href,
					link_text: link.textContent.trim()
				});
			}
		});
	}

	/**
	 * Track outbound link clicks (external links, affiliate links).
	 */
	function trackOutboundLinks() {
		document.addEventListener('click', function(e) {
			const link = e.target.closest('a');
			if (!link || !link.href) return;

			try {
				const linkUrl = new URL(link.href);
				const currentDomain = window.location.hostname;

				// Check if external link
				if (linkUrl.hostname !== currentDomain && 
					!linkUrl.hostname.includes('localhost') &&
					linkUrl.protocol.startsWith('http')) {
					
					track('outbound_click', {
						destination_url: link.href,
						destination_domain: linkUrl.hostname,
						link_text: link.textContent.trim(),
						link_position: getElementSelector(link)
					});
				}
			} catch (err) {
				// Invalid URL, skip
			}
		});
	}

	/**
	 * Track phone number clicks (tel: links).
	 */
	function trackPhoneClicks() {
		document.addEventListener('click', function(e) {
			const link = e.target.closest('a');
			if (!link || !link.href) return;

			if (link.href.startsWith('tel:')) {
				const phoneNumber = link.href.replace('tel:', '');
				
				track('click', {
					click_type: 'phone',
					phone_number: phoneNumber,
					link_text: link.textContent.trim(),
					button_text: link.textContent.trim()
				});
			}
		});
	}

	/**
	 * Track WhatsApp clicks (wa.me links).
	 */
	function trackWhatsAppClicks() {
		document.addEventListener('click', function(e) {
			const link = e.target.closest('a');
			if (!link || !link.href) return;

			if (link.href.includes('wa.me') || link.href.includes('whatsapp.com')) {
				track('click', {
					click_type: 'whatsapp',
					whatsapp_url: link.href,
					link_text: link.textContent.trim(),
					button_text: link.textContent.trim()
				});
			}
		});
	}

	/**
	 * Track video engagement (HTML5 videos, YouTube, Vimeo).
	 */
	function trackVideo() {
		// HTML5 video tracking
		const videos = document.querySelectorAll('video');
		videos.forEach(video => {
			const videoData = {
				video_url: video.currentSrc || video.src,
				video_title: video.title || null,
				video_duration: Math.round(video.duration) || null
			};

			const milestones = [25, 50, 75];
			const reached = {};

			video.addEventListener('play', function() {
				track('video_start', videoData);
			}, { once: true });

			video.addEventListener('timeupdate', function() {
				if (!video.duration) return;
				const percent = Math.round((video.currentTime / video.duration) * 100);
				
				for (const milestone of milestones) {
					if (percent >= milestone && !reached[milestone]) {
						reached[milestone] = true;
						track('video_progress', {
							...videoData,
							video_percent: milestone
						});
					}
				}
			});

			video.addEventListener('ended', function() {
				track('video_complete', videoData);
			});
		});

		// YouTube iframe tracking (if YouTube API available)
		if (window.YT && window.YT.Player) {
			const iframes = document.querySelectorAll('iframe[src*="youtube.com"]');
			iframes.forEach(iframe => {
				try {
					const player = new YT.Player(iframe);
					player.addEventListener('onStateChange', function(e) {
						const videoData = {
							video_url: player.getVideoUrl(),
							video_title: null,
							video_duration: Math.round(player.getDuration())
						};

						if (e.data === YT.PlayerState.PLAYING) {
							track('video_start', videoData);
						} else if (e.data === YT.PlayerState.ENDED) {
							track('video_complete', videoData);
						}
					});
				} catch (e) {
					// YouTube API not ready
				}
			});
		}
	}

	/**
	 * Track account page views for logged-in users.
	 * 
	 * Automatically detects and tracks when logged-in users view their account pages.
	 * Supports WooCommerce my-account, WordPress user profile, and custom account pages.
	 * 
	 * Detection methods:
	 * - WooCommerce: Body class 'woocommerce-account', my-account endpoint pages
	 * - WordPress: Body class 'wp-admin' or URLs containing /wp-admin/profile.php
	 * - Custom: Body class 'logged-in' + account page identifiers (dashboard, account, profile)
	 * 
	 * Compatible with:
	 * - WooCommerce my account pages (dashboard, orders, downloads, addresses, payment methods, edit account)
	 * - WordPress user profile pages
	 * - Membership plugins (MemberPress, Restrict Content Pro, Paid Memberships Pro)
	 * - Custom account page implementations
	 * 
	 * @since 1.1.0
	 */
	function trackAccountPages() {
		// Only track if user is logged in
		const body = document.body;
		const isLoggedIn = body.classList.contains('logged-in');
		
		if (!isLoggedIn) {
			return; // Not logged in, skip account tracking
		}

		const pathname = window.location.pathname;
		let pageType = null;
		let accountSection = null;

		// WooCommerce My Account pages
		if (body.classList.contains('woocommerce-account') || body.classList.contains('woocommerce-my-account')) {
			pageType = 'woocommerce';
			
			// Detect specific WooCommerce account sections
			if (pathname.includes('/my-account/orders') || body.classList.contains('woocommerce-orders')) {
				accountSection = 'orders';
			} else if (pathname.includes('/my-account/downloads') || body.classList.contains('woocommerce-downloads')) {
				accountSection = 'downloads';
			} else if (pathname.includes('/my-account/edit-address') || body.classList.contains('woocommerce-edit-address')) {
				accountSection = 'addresses';
			} else if (pathname.includes('/my-account/edit-account') || body.classList.contains('woocommerce-edit-account')) {
				accountSection = 'edit_account';
			} else if (pathname.includes('/my-account/payment-methods') || body.classList.contains('woocommerce-payment-methods')) {
				accountSection = 'payment_methods';
			} else if (pathname.includes('/my-account/subscriptions')) {
				accountSection = 'subscriptions';
			} else if (body.classList.contains('woocommerce-view-order')) {
				accountSection = 'view_order';
			} else {
				accountSection = 'dashboard';
			}
		}
		// WordPress admin/user profile pages
		else if (pathname.includes('/wp-admin/profile.php') || pathname.includes('/wp-admin/user-edit.php')) {
			pageType = 'wordpress';
			accountSection = 'profile';
		}
		// Membership plugin pages (MemberPress, Restrict Content Pro, etc.)
		else if (body.classList.contains('page-template-account') || 
		         pathname.includes('/account/') || 
		         pathname.includes('/my-account') ||
		         pathname.includes('/member/') ||
		         pathname.includes('/membership/') ||
		         pathname.includes('/dashboard/') ||
		         body.classList.contains('memberpress') ||
		         body.classList.contains('rcp_')) {
			pageType = 'membership';
			
			// Try to detect section from URL
			if (pathname.includes('/subscriptions') || pathname.includes('/subscription')) {
				accountSection = 'subscriptions';
			} else if (pathname.includes('/billing') || pathname.includes('/payments')) {
				accountSection = 'billing';
			} else if (pathname.includes('/profile') || pathname.includes('/edit-profile')) {
				accountSection = 'profile';
			} else {
				accountSection = 'dashboard';
			}
		}
		// Generic account page detection
		else if (pathname.includes('/account') || 
		         pathname.includes('/dashboard') || 
		         pathname.includes('/profile') ||
		         pathname.includes('/user/') ||
		         body.classList.contains('page-account') ||
		         body.classList.contains('user-account') ||
		         body.classList.contains('user-dashboard')) {
			pageType = 'custom';
			
			// Try to detect section from URL or body classes
			if (pathname.includes('/orders') || body.classList.contains('user-orders')) {
				accountSection = 'orders';
			} else if (pathname.includes('/profile') || body.classList.contains('user-profile')) {
				accountSection = 'profile';
			} else if (pathname.includes('/settings') || body.classList.contains('user-settings')) {
				accountSection = 'settings';
			} else if (pathname.includes('/dashboard') || body.classList.contains('user-dashboard')) {
				accountSection = 'dashboard';
			} else {
				accountSection = 'main';
			}
		}

		// Track if we detected an account page
		if (pageType) {
			const eventParams = {
				page_type: pageType
			};
			
			if (accountSection) {
				eventParams.account_section = accountSection;
			}
			
			track('account_page_view', eventParams);
		}
	}

	/**
	 * Promotion tracking is available via programmatic API only.
	 * 
	 * TrackSure Core does NOT automatically detect promotions because:
	 * - WordPress uses Gutenberg blocks (no predictable HTML structure)
	 * - Users have theme builders (Elementor, Divi, Beaver Builder, etc.)
	 * - No reliable way to detect "what is a promotion" across all themes/plugins
	 * 
	 * HOW TO TRACK PROMOTIONS:
	 * 
	 * 1. **Manual Tracking (Developers/Custom Code):**
	 * ```javascript
	 * // Track promotion view
	 * window.TrackSure.track('view_promotion', {
	 *     promotion_id: 'summer-sale-2024',
	 *     promotion_name: 'Summer Sale',
	 *     creative_name: 'Hero Banner',
	 *     creative_slot: 'homepage_hero',
	 *     location_id: 'home'
	 * });
	 * 
	 * // Track promotion click
	 * document.querySelector('.my-banner').addEventListener('click', function() {
	 *     window.TrackSure.track('select_promotion', {
	 *         promotion_id: 'summer-sale-2024',
	 *         promotion_name: 'Summer Sale'
	 *     });
	 * });
	 * ```
	 * 
	 * 2. **Automatic Tracking (TrackSure Pro Experiences Engine):**
	 * When Pro creates popups/modals/landing pages, it automatically injects tracking.
	 * Pro controls the content lifecycle, so it can track programmatically without
	 * relying on theme/builder HTML structure.
	 * 
	 * 3. **UTM Tracking (Already Working):**
	 * All clicks with UTM parameters are automatically tracked.
	 * Use UTM parameters in your promotion links to track campaign performance
	 * without any code changes.
	 * 
	 * EVENTS AVAILABLE:
	 * - view_promotion: User viewed promotion (impression)
	 * - select_promotion: User clicked promotion
	 * 
	 * @since 1.1.0
	 * @since 1.2.0 Changed to programmatic-only (removed automatic DOM detection)
	 */
	// No automatic promotion tracking function - use window.TrackSure.track() API

	/**
	 * Track tab visibility changes (user switches tabs).
	 * 
	 * Tracks when user:
	 * - Switches to another tab (tab_hidden)
	 * - Comes back to this tab (tab_visible with time_hidden duration)
	 * 
	 * Compatible with all browsers supporting Page Visibility API.
	 * 
	 * @since 1.0.0
	 */
	function trackVisibility() {
		let hiddenTime = null;

		document.addEventListener('visibilitychange', function() {
			if (document.hidden) {
				// Tab hidden - user switched away
				hiddenTime = Date.now();
				track('tab_hidden', {
					page_url: window.location.href,
					page_title: document.title
				});
			} else if (hiddenTime) {
				// Tab visible again - user came back
				const timeHidden = Math.round((Date.now() - hiddenTime) / 1000); // seconds
				track('tab_visible', {
					page_url: window.location.href,
					page_title: document.title,
					time_hidden: timeHidden
				});
				hiddenTime = null;
			}
		});
	}

	/**
	 * Track scroll depth with engagement zones.
	 * Enhanced with scroll_depth parameter for goal matching.
	 */
	function trackScrollDepth() {
		const milestones = [25, 50, 75, 90, 100];
		const reached = {};
		let maxScroll = 0;

		window.addEventListener('scroll', function () {
			const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
			const scrollPercent = Math.round((window.scrollY / scrollHeight) * 100);
			
			maxScroll = Math.max(maxScroll, scrollPercent);

			for (const milestone of milestones) {
				if (scrollPercent >= milestone && !reached[milestone]) {
					reached[milestone] = true;
					track('scroll', { 
						scroll_depth: milestone, // For goal matching
						max_scroll: maxScroll
					});
				}
			}
		});

		// Track max scroll on unload
		window.addEventListener('beforeunload', function() {
			if (maxScroll > 0 && maxScroll < 100) {
				track('scroll_depth_final', {
					scroll_depth: maxScroll
				});
			}
		});
	}

	/**
	 * Initialize tracking.
	 * 
	 * Loads registry asynchronously (non-blocking) then initializes all tracking.
	 */
	function init() {
		// Load registry asynchronously (non-blocking)
		// Events work even if registry fails to load (degraded mode)
		loadRegistry().catch(function(_error) {
			// Ignore errors - already logged in loadRegistry()
		});

		// Track page view with first_visit/session_start detection
		trackPageView();


		// Initialize all interaction tracking
		trackClicks();
		trackScrollDepth();
		trackTimeOnPage();
		trackSearch();
		trackForms();
		trackWooCommerce();
		trackFileDownloads();
		trackOutboundLinks();
		trackPhoneClicks();
		trackWhatsAppClicks();
		trackVideo();
		trackAccountPages(); // Account page tracking for logged-in users
		trackVisibility(); // Tab visibility tracking
		// Note: Promotion tracking (view_promotion, select_promotion) is manual-only.
		// Use window.TrackSure.track() API or let Pro Experiences Engine handle it.


		// Send batch before page unload
		window.addEventListener('beforeunload', sendBatch);
	}

	// Public API for external integrations
// IMPORTANT: Extend existing TrackSure object (Event Bridge may have already added sendToPixels/pixelMappers)
if (!window.TrackSure) {
	window.TrackSure = {};
}
Object.assign(window.TrackSure, {
	track: track,
	sendBatch: sendBatch,
	getClientId: getClientId,
	getSessionId: getSessionId,
	generateUUID: generateUUID,
	
	/**
	 * Registry API (read-only)
	 * 
	 * Exposes loaded registry for external integrations.
	 * Returns null if registry hasn't loaded yet.
	 */
	getRegistry: function() {
		return registry;
	},
	
	/**
	 * Validate event against registry.
	 * 
	 * @param {string} eventName - Event name
	 * @param {Object} eventParams - Event parameters
	 * @returns {Object} {valid: boolean, errors: Array<string>}
	 */
	validateEvent: validateEvent
});

// Auto-initialize with non-blocking strategy
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', function() {
		if ('requestIdleCallback' in window) {
			requestIdleCallback(init, { timeout: 500 });
		} else {
			setTimeout(init, 100);
		}
	});
} else {
	// Page already loaded
	if ('requestIdleCallback' in window) {
		requestIdleCallback(init, { timeout: 500 });
	} else {
		setTimeout(init, 100);
	}
}

})(window, document);
