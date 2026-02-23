/**
 * useConsentStatus Hook
 * 
 * Fetches and manages consent management system status:
 * - Current consent mode (disabled/opt-in/opt-out/auto)
 * - Detected consent plugin
 * - Google Consent Mode V2 state
 * - Supported consent plugins list
 * - Consent warnings
 * 
 * @since 1.0.1
 */

import { useState, useEffect, useCallback } from 'react';
import { useApp } from '../contexts/AppContext';

export interface ConsentState {
	ad_storage: 'granted' | 'denied';
	analytics_storage: 'granted' | 'denied';
	functionality_storage: 'granted' | 'denied';
	personalization_storage: 'granted' | 'denied';
	security_storage: 'granted' | 'denied';
	ad_user_data: 'granted' | 'denied';
	ad_personalization: 'granted' | 'denied';
}

export interface SupportedPlugin {
	id: string;
	name: string;
	slug: string;
	url: string;
	recommended: boolean;
}

export interface ConsentStatusData {
	consent_mode: 'disabled' | 'opt-in' | 'opt-out' | 'auto';
	tracking_allowed: boolean;
	detected_plugin: string | null;
	consent_state: ConsentState;
	supported_plugins: SupportedPlugin[];
}

export interface ConsentWarning {
	should_show: boolean;
	message: string;
	detected_plugins: string[];
}

interface UseConsentStatusResult {
	consentData: ConsentStatusData | null;
	warning: ConsentWarning | null;
	isLoading: boolean;
	error: Error | null;
	refetch: () => Promise<void>;
	dismissWarning: () => Promise<void>;
}

/**
 * Fetch consent status from REST API
 */
export function useConsentStatus(): UseConsentStatusResult {
	const { config } = useApp();
	const [consentData, setConsentData] = useState<ConsentStatusData | null>(null);
	const [warning, setWarning] = useState<ConsentWarning | null>(null);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState<Error | null>(null);

	const fetchConsentStatus = useCallback(async () => {
		try {
			setIsLoading(true);
			setError(null);

			// Fetch consent state
			const stateResponse = await fetch(
				`${config.apiUrl}/consent/state`,
				{
					headers: {
						'X-WP-Nonce': config.nonce,
						'Content-Type': 'application/json',
					},
					credentials: 'same-origin',
				}
			);

			if (!stateResponse.ok) {
				throw new Error('Failed to fetch consent state');
			}

			const stateData = await stateResponse.json();
			setConsentData(stateData);

			// Fetch consent warning
			const warningResponse = await fetch(
				`${config.apiUrl}/consent/warning`,
				{
					headers: {
						'X-WP-Nonce': config.nonce,
						'Content-Type': 'application/json',
					},
					credentials: 'same-origin',
				}
			);

			if (warningResponse.ok) {
				const warningData = await warningResponse.json();
				setWarning(warningData);
			}
		} catch (err) {
			setError(err instanceof Error ? err : new Error('Unknown error'));
		} finally {
			setIsLoading(false);
		}
	}, [config.apiUrl, config.nonce]);

	const dismissWarning = useCallback(async () => {
		try {
			const response = await fetch(
				`${config.apiUrl}/consent/warning/dismiss`,
				{
					method: 'POST',
					headers: {
						'X-WP-Nonce': config.nonce,
						'Content-Type': 'application/json',
					},
					credentials: 'same-origin',
				}
			);

			if (response.ok) {
				setWarning(null);
			}
		} catch (err) {
			console.error('Failed to dismiss consent warning:', err);
		}
	}, [config.apiUrl, config.nonce]);

	useEffect(() => {
		fetchConsentStatus();
	}, [fetchConsentStatus]);

	return {
		consentData,
		warning,
		isLoading,
		error,
		refetch: fetchConsentStatus,
		dismissWarning,
	};
}
