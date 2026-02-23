/**
 * Consent Settings Component
 * 
 * Main consent management UI for privacy settings tab.
 * Combines:
 * - Consent warning banner
 * - Consent plugin detector
 * - Google Consent Mode V2 visualizer
 * - Consent mode explanations
 * 
 * Integrates with existing settings system via DynamicField.
 * Supports dark/light mode.
 * 
 * @since 1.0.1
 */

import React from 'react';
import { Icon, type IconName } from '../ui/Icon';
import { Card } from '../ui/Card';
import { Skeleton } from '../ui/Skeleton';
import { useConsentStatus } from '../../hooks/useConsentStatus';
import { ConsentWarningBanner } from './ConsentWarningBanner';
import { ConsentPluginDetector } from './ConsentPluginDetector';
import { ConsentModeVisualizer } from './ConsentModeVisualizer';
import '../../styles/components/settings/ConsentSettings.css';
import '../../styles/components/settings/ConsentSettings.css';

interface ConsentSettingsProps {
	className?: string;
}

/**
 * Consent mode explanations for user education
 */
const consentModeInfo = {
	disabled: {
		title: 'Disabled - No Consent Required',
		description:
			'Tracking operates worldwide without consent restrictions. Use this only for websites that don\'t serve EU/EEA/UK visitors or have specific legal basis.',
		icon: 'Globe' as IconName,
		color: 'gray' as const,
		warning: null,
	},
	'opt-in': {
		title: 'Opt-in - Explicit Consent Required (GDPR)',
		description:
			'Users must explicitly accept tracking before any data is collected. Required for EU/EEA/UK/Switzerland visitors under GDPR and LGPD regulations.',
		icon: 'Shield' as IconName,
		color: 'green' as const,
		warning:
			'⚠️ Requires a consent management plugin (CookieYes, Complianz, etc.) to collect user consent.',
	},
	'opt-out': {
		title: 'Opt-out - Track by Default, Allow Opt-out (CCPA)',
		description:
			'Tracking operates by default. Users can opt-out via "Do Not Sell My Information" links. Required for California residents under CCPA.',
		icon: 'UserX' as IconName,
		color: 'blue' as const,
		warning:
			'💡 Users can opt-out via DNT header or consent plugin. TrackSure respects these preferences.',
	},
	auto: {
		title: 'Auto - Detect Based on Visitor Location (Recommended)',
		description:
			'Automatically applies: Opt-in for EU/UK/CH/BR (GDPR/LGPD), Opt-out for California (CCPA), Disabled for others. Best for global websites.',
		icon: 'Zap' as IconName,
		color: 'yellow' as const,
		warning: null,
	},
};

export const ConsentSettings: React.FC<ConsentSettingsProps> = ({ className = '' }) => {
	const { consentData, warning, isLoading, error, dismissWarning } = useConsentStatus();

	if (isLoading) {
		return (
			<div className={`ts-consent-loading ${className}`}>
				<Skeleton />
				<Skeleton />
				<Skeleton />
			</div>
		);
	}

	if (error) {
		return (
			<Card padding="lg">
				<div style={{ display: 'flex', alignItems: 'center', gap: 'var(--ts-spacing-md)', color: 'var(--ts-danger)' }}>
					<Icon name="AlertCircle" />
					<p style={{ fontSize: 'var(--ts-text-sm)' }}>Failed to load consent settings: {error.message}</p>
				</div>
			</Card>
		);
	}

	if (!consentData) {
		return null;
	}

	const modeInfo = consentModeInfo[consentData.consent_mode];

	return (
		<div className={`ts-consent-settings ${className}`}>
			{/* Section Header */}
			<Card padding="lg" variant="elevated">
				<div style={{ display: 'flex', alignItems: 'flex-start', gap: 'var(--ts-spacing-lg)' }}>
					<div style={{ 
						flexShrink: 0, 
						width: '3rem', 
						height: '3rem', 
						borderRadius: 'var(--ts-radius-xl)',
						background: 'var(--ts-primary)',
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						color: 'white'
					}}>
						<Icon name="Shield" style={{ width: '1.5rem', height: '1.5rem' }} />
					</div>
					<div style={{ flex: 1 }}>
						<h2 style={{ 
							fontSize: 'var(--ts-text-xl)', 
							fontWeight: 700, 
							color: 'var(--ts-text)',
							margin: '0 0 var(--ts-spacing-sm)'
						}}>
							Privacy & Compliance
						</h2>
						<p style={{ 
							fontSize: 'var(--ts-text-sm)', 
							color: 'var(--ts-text-secondary)',
							margin: 0,
							lineHeight: 1.6
						}}>
							GDPR/CCPA compliance management. TrackSure automatically detects consent plugins and adjusts tracking based on visitor location and consent preferences.
						</p>
					</div>
				</div>
			</Card>

			{/* Warning Banner */}
			{warning && warning.should_show && (
				<ConsentWarningBanner
					warning={warning}
					supportedPlugins={consentData.supported_plugins}
					onDismiss={dismissWarning}
				/>
			)}

			{/* Current Consent Mode Status */}
			<div className="ts-consent-mode-info">
				<div className={`ts-consent-mode-info__icon-container ts-consent-mode-info__icon-container--${modeInfo.color}`}>
					<Icon
						name={modeInfo.icon}
						className="ts-consent-mode-info__icon"
					/>
				</div>
				<div className="ts-consent-mode-info__content">
					<div style={{ display: 'flex', alignItems: 'center', gap: 'var(--ts-spacing-sm)', marginBottom: 'var(--ts-spacing-xs)' }}>
						<h3 className="ts-consent-mode-info__title" style={{ marginBottom: 0 }}>
							Current Mode: {modeInfo.title.split(' - ')[0]}
						</h3>
						<span style={{
							padding: '0.125rem 0.5rem',
							background: 'var(--ts-primary-light)',
							color: 'var(--ts-primary-dark)',
							borderRadius: 'var(--ts-radius-full)',
							fontSize: 'var(--ts-text-xs)',
							fontWeight: 600,
							textTransform: 'uppercase'
						}}>
							Active
						</span>
					</div>
					<p className="ts-consent-mode-info__description">
						{modeInfo.description}
					</p>

					{modeInfo.warning && (
						<div className="ts-consent-mode-info__warning">
							<span>{modeInfo.warning}</span>
						</div>
					)}
				</div>
			</div>

			{/* Consent Plugin Detector */}
			<ConsentPluginDetector
				detectedPlugin={consentData.detected_plugin}
				supportedPlugins={consentData.supported_plugins}
			/>

			{/* Google Consent Mode V2 Visualizer */}
			{consentData.consent_mode !== 'disabled' && (
				<ConsentModeVisualizer
					consentState={consentData.consent_state}
					trackingAllowed={consentData.tracking_allowed}
				/>
			)}

			{/* Quick Setup Guide */}
			<div className="ts-consent-help">
				<div className="ts-consent-help__header">
					<div className="ts-consent-help__icon-container">
						<Icon name="HelpCircle" className="ts-consent-help__icon" />
					</div>
					<h4 className="ts-consent-help__title">Quick Setup Guide</h4>
				</div>
				<div className="ts-consent-help__content">
					<ul>
						<li>
							<strong>Global site?</strong> Use <span className="ts-consent-mode-badge">Auto</span> mode for automatic compliance
						</li>
						<li>
							<strong>EU/UK only?</strong> Use <span className="ts-consent-mode-badge">Opt-in</span> with a consent plugin
						</li>
						<li>
							<strong>USA only?</strong> Use <span className="ts-consent-mode-badge">Opt-out</span> for CCPA or <span className="ts-consent-mode-badge">Disabled</span> for other states
						</li>
						<li>
							<strong>No consent plugin?</strong> Install CookieYes, Complianz, or Cookiebot
						</li>
					</ul>
				</div>
			</div>
		</div>
	);
};
