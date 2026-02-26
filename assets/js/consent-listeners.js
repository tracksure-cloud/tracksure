/**
 * Consent Change Listeners
 * 
 * Listens to consent plugin events and updates TrackSure consent state in real-time.
 * Prevents events from being anonymized after user accepts consent mid-session.
 * 
 * Supported Plugins:
 * - CookieYes
 * - Cookiebot
 * - OneTrust
 * - Complianz
 * - Cookie Notice
 * - GDPR Cookie Consent
 * - Borlabs Cookie
 * 
 * @since 1.0.1
 */

(function() {
	'use strict';

	// Wait for TrackSure SDK to load.
	if (!window.trackSureConfig || !window.trackSureConfig.restUrl) {
		console.warn('[TrackSure Consent] SDK not loaded, consent listeners disabled');
		return;
	}

	/**
	 * Update consent state via REST API.
	 *
	 * @param {Object} consentState Consent state object with ad_storage, analytics_storage, etc.
	 */
	function updateConsentState(consentState) {
		fetch(window.trackSureConfig.restUrl + 'ts/v1/consent/update', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.trackSureConfig.nonce || ''
			},
			credentials: 'same-origin',
			body: JSON.stringify({
				consent_state: consentState
			})
		})
		.then(function(response) {
			if (response.ok) {
				console.log('[TrackSure Consent] Updated:', consentState);
				
				// Update global consent object.
				if (window.trackSureConsent) {
					window.trackSureConsent.state = consentState;
					window.trackSureConsent.granted = true;
				}

				// Update Google Consent Mode V2.
				if (typeof gtag === 'function') {
					gtag('consent', 'update', consentState);
				}

				// Update Meta Pixel consent state.
				// fbq('consent', 'revoke') prevents the pixel from sending any events.
				// fbq('consent', 'grant') re-enables event sending after consent is granted.
				if (typeof fbq === 'function') {
					if (consentState.ad_storage === 'granted') {
						fbq('consent', 'grant');
					} else {
						fbq('consent', 'revoke');
					}
				}
			}
		})
		.catch(function(error) {
			console.error('[TrackSure Consent] Update failed:', error);
		});
	}

	/**
	 * CookieYes - Most Popular (500K+ installs)
	 */
	document.addEventListener('cookieyes-consent-update', function(event) {
		const consent = event.detail || {};

		updateConsentState({
			ad_storage: consent.advertisement === 'yes' ? 'granted' : 'denied',
			analytics_storage: consent.analytics === 'yes' ? 'granted' : 'denied',
			ad_user_data: consent.advertisement === 'yes' ? 'granted' : 'denied',
			ad_personalization: consent.advertisement === 'yes' ? 'granted' : 'denied',
			functionality_storage: 'granted', // Always granted (essential)
			personalization_storage: consent.functional === 'yes' ? 'granted' : 'denied',
			security_storage: 'granted' // Always granted (essential)
		});
	});

	/**
	 * Cookiebot - Most Popular Worldwide (400K+ installs)
	 */
	window.addEventListener('CookiebotOnAccept', function() {
		if (window.Cookiebot) {
			updateConsentState({
				ad_storage: window.Cookiebot.consent.marketing ? 'granted' : 'denied',
				analytics_storage: window.Cookiebot.consent.statistics ? 'granted' : 'denied',
				ad_user_data: window.Cookiebot.consent.marketing ? 'granted' : 'denied',
				ad_personalization: window.Cookiebot.consent.marketing ? 'granted' : 'denied',
				functionality_storage: 'granted',
				personalization_storage: window.Cookiebot.consent.preferences ? 'granted' : 'denied',
				security_storage: 'granted'
			});
		}
	});

	window.addEventListener('CookiebotOnDecline', function() {
		updateConsentState({
			ad_storage: 'denied',
			analytics_storage: 'denied',
			ad_user_data: 'denied',
			ad_personalization: 'denied',
			functionality_storage: 'granted',
			personalization_storage: 'denied',
			security_storage: 'granted'
		});
	});

	/**
	 * OneTrust - Enterprise Solution
	 */
	window.addEventListener('OneTrustGroupsUpdated', function() {
		if (window.OnetrustActiveGroups) {
			const groups = window.OnetrustActiveGroups.split(',');

			updateConsentState({
				ad_storage: groups.indexOf('C0004') !== -1 ? 'granted' : 'denied',
				analytics_storage: groups.indexOf('C0002') !== -1 ? 'granted' : 'denied',
				ad_user_data: groups.indexOf('C0004') !== -1 ? 'granted' : 'denied',
				ad_personalization: groups.indexOf('C0004') !== -1 ? 'granted' : 'denied',
				functionality_storage: 'granted',
				personalization_storage: groups.indexOf('C0003') !== -1 ? 'granted' : 'denied',
				security_storage: 'granted'
			});
		}
	});

	/**
	 * Complianz GDPR/CCPA (300K+ installs)
	 */
	document.addEventListener('cmplz_event_marketing', function(event) {
		const consent = event.detail || {};

		updateConsentState({
			ad_storage: consent.marketing ? 'granted' : 'denied',
			analytics_storage: consent.statistics ? 'granted' : 'denied',
			ad_user_data: consent.marketing ? 'granted' : 'denied',
			ad_personalization: consent.marketing ? 'granted' : 'denied',
			functionality_storage: 'granted',
			personalization_storage: consent.preferences ? 'granted' : 'denied',
			security_storage: 'granted'
		});
	});

	document.addEventListener('cmplz_event_statistics', function(event) {
		updateConsentState({
			ad_storage: 'denied',
			analytics_storage: event.detail ? 'granted' : 'denied',
			ad_user_data: 'denied',
			ad_personalization: 'denied',
			functionality_storage: 'granted',
			personalization_storage: 'granted',
			security_storage: 'granted'
		});
	});

	/**
	 * Cookie Notice (5M+ installs) - Simple Plugin
	 */
	document.addEventListener('cookie_notice_accepted', function() {
		updateConsentState({
			ad_storage: 'granted',
			analytics_storage: 'granted',
			ad_user_data: 'granted',
			ad_personalization: 'granted',
			functionality_storage: 'granted',
			personalization_storage: 'granted',
			security_storage: 'granted'
		});
	});

	document.addEventListener('cookie_notice_refused', function() {
		updateConsentState({
			ad_storage: 'denied',
			analytics_storage: 'denied',
			ad_user_data: 'denied',
			ad_personalization: 'denied',
			functionality_storage: 'granted',
			personalization_storage: 'denied',
			security_storage: 'granted'
		});
	});

	/**
	 * GDPR Cookie Consent (WebToffee) (800K+ installs)
	 */
	document.addEventListener('cli_user_preference_set', function(event) {
		const consent = event.detail || {};

		updateConsentState({
			ad_storage: consent.targeting ? 'granted' : 'denied',
			analytics_storage: consent.analytics ? 'granted' : 'denied',
			ad_user_data: consent.targeting ? 'granted' : 'denied',
			ad_personalization: consent.targeting ? 'granted' : 'denied',
			functionality_storage: 'granted',
			personalization_storage: consent.functional ? 'granted' : 'denied',
			security_storage: 'granted'
		});
	});

	/**
	 * Borlabs Cookie (200K+ installs) - Premium Plugin
	 */
	document.addEventListener('borlabs-cookie-consent-saved', function(event) {
		const consent = event.detail.consents || {};

		updateConsentState({
			ad_storage: consent.marketing ? 'granted' : 'denied',
			analytics_storage: consent.statistics ? 'granted' : 'denied',
			ad_user_data: consent.marketing ? 'granted' : 'denied',
			ad_personalization: consent.marketing ? 'granted' : 'denied',
			functionality_storage: 'granted',
			personalization_storage: consent.preferences ? 'granted' : 'denied',
			security_storage: 'granted'
		});
	});

	console.log('[TrackSure Consent] Listeners registered for 7 consent plugins');

	/**
	 * Debug Tools
	 * 
	 * Add debugging utilities to window.TrackSure.consent namespace.
	 * Accessible via browser console for testing and troubleshooting.
	 */
	window.TrackSure = window.TrackSure || {};
	window.TrackSure.consent = {
		/**
		 * Get current consent state.
		 */
		getState: function() {
			if (window.trackSureConsent) {
				console.table(window.trackSureConsent.state);
				return window.trackSureConsent;
			}
			console.warn('[TrackSure Consent] No consent state available');
			return null;
		},

		/**
		 * Simulate consent granted (for testing).
		 */
		simulateGranted: function() {
			updateConsentState({
				ad_storage: 'granted',
				analytics_storage: 'granted',
				ad_user_data: 'granted',
				ad_personalization: 'granted',
				functionality_storage: 'granted',
				personalization_storage: 'granted',
				security_storage: 'granted'
			});
			console.log('[TrackSure Consent Debug] Simulated consent GRANTED');
		},

		/**
		 * Simulate consent denied (for testing).
		 */
		simulateDenied: function() {
			updateConsentState({
				ad_storage: 'denied',
				analytics_storage: 'denied',
				ad_user_data: 'denied',
				ad_personalization: 'denied',
				functionality_storage: 'granted',
				personalization_storage: 'denied',
				security_storage: 'granted'
			});
			console.log('[TrackSure Consent Debug] Simulated consent DENIED');
		},

		/**
		 * Show detected consent plugin.
		 */
		getDetectedPlugin: function() {
			if (window.trackSureConsent && window.trackSureConsent.plugin) {
				console.log('[TrackSure Consent] Detected Plugin:', window.trackSureConsent.plugin);
				return window.trackSureConsent.plugin;
			}
			console.warn('[TrackSure Consent] No consent plugin detected');
			return null;
		},

		/**
		 * Show current consent mode.
		 */
		getMode: function() {
			if (window.trackSureConsent) {
				const modes = {
					disabled: 'Disabled - No consent required',
					'opt-in': 'Opt-in - Explicit consent required (GDPR)',
					'opt-out': 'Opt-out - Track by default, allow opt-out (CCPA)',
					auto: 'Auto - Detect based on visitor location'
				};

				console.log('[TrackSure Consent] Mode:', modes[window.trackSureConsent.mode] || window.trackSureConsent.mode);
				console.log('[TrackSure Consent] Tracking Allowed:', window.trackSureConsent.granted);

				return {
					mode: window.trackSureConsent.mode,
					granted: window.trackSureConsent.granted
				};
			}
			return null;
		},

		/**
		 * Show help message.
		 */
		help: function() {
			console.log('%c━━━ TrackSure Consent Debug Tools ━━━', 'font-weight: bold; font-size: 14px; color: #4F46E5;');
			console.log('');
			console.log('%cAvailable Commands:', 'font-weight: bold; color: #059669;');
			console.log('  TrackSure.consent.getState()           - Show current consent state');
			console.log('  TrackSure.consent.getMode()            - Show consent mode and status');
			console.log('  TrackSure.consent.getDetectedPlugin()  - Show detected consent plugin');
			console.log('  TrackSure.consent.simulateGranted()    - Test consent GRANTED');
			console.log('  TrackSure.consent.simulateDenied()     - Test consent DENIED');
			console.log('');
			console.log('%cExamples:', 'font-weight: bold; color: #DC2626;');
			console.log('  // Check if consent is granted');
			console.log('  TrackSure.consent.getMode()');
			console.log('');
			console.log('  // View Google Consent Mode V2 state');
			console.log('  TrackSure.consent.getState()');
			console.log('');
			console.log('  // Test anonymization (deny consent)');
			console.log('  TrackSure.consent.simulateDenied()');
		}
	};

	// Show help on load.
	console.log('%c[TrackSure Consent]%c Debug tools loaded. Type TrackSure.consent.help() for usage.', 
		'color: #4F46E5; font-weight: bold;', 
		'color: inherit;'
	);

})();
