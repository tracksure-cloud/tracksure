/**
 * Consent Mode Visualizer Component
 * 
 * Displays Google Consent Mode V2 state with visual indicators:
 * - All 7 consent categories (ad_storage, analytics_storage, etc.)
 * - Granted/Denied status with color-coded badges
 * - Real-time consent state from detected plugins
 * 
 * Supports dark/light mode.
 * 
 * @since 1.0.1
 */

import React from 'react';
import { Icon } from '../ui/Icon';
import { Card } from '../ui/Card';
import type { IconName } from '../ui/Icon';
import type { ConsentState } from '../../hooks/useConsentStatus';
import '../../styles/components/settings/ConsentModeVisualizer.css';

interface ConsentModeVisualizerProps {
	consentState: ConsentState;
	trackingAllowed: boolean;
}

const consentCategories = [
	{
		key: 'ad_storage' as keyof ConsentState,
		label: 'Ad Storage',
		description: 'Enables storage for advertising cookies',
		icon: 'Target' as IconName,
	},
	{
		key: 'analytics_storage' as keyof ConsentState,
		label: 'Analytics Storage',
		description: 'Enables storage for analytics cookies',
		icon: 'BarChart3' as IconName,
	},
	{
		key: 'ad_user_data' as keyof ConsentState,
		label: 'Ad User Data',
		description: 'User data sent to Google for advertising',
		icon: 'User' as IconName,
	},
	{
		key: 'ad_personalization' as keyof ConsentState,
		label: 'Ad Personalization',
		description: 'Personalized advertising based on user data',
		icon: 'Star' as IconName,
	},
	{
		key: 'functionality_storage' as keyof ConsentState,
		label: 'Functionality Storage',
		description: 'Essential cookies for site functionality',
		icon: 'Settings' as IconName,
	},
	{
		key: 'personalization_storage' as keyof ConsentState,
		label: 'Personalization Storage',
		description: 'Cookies for personalized content',
		icon: 'Palette' as IconName,
	},
	{
		key: 'security_storage' as keyof ConsentState,
		label: 'Security Storage',
		description: 'Security and fraud prevention cookies',
		icon: 'Lock' as IconName,
	},
];

export const ConsentModeVisualizer: React.FC<ConsentModeVisualizerProps> = ({
	consentState,
	trackingAllowed,
}) => {
	return (
		<Card padding="lg" className="ts-consent-visualizer">
			<div className="ts-consent-visualizer__header">
				<div>
					<h3 className="ts-consent-visualizer__title">
						Google Consent Mode V2 State (Your Preview)
					</h3>
					<p className="ts-consent-visualizer__subtitle">
						This shows YOUR current consent state for testing. Each website visitor has their own individual consent state.
					</p>
				</div>

				<div
					className={`ts-consent-visualizer__tracking-badge ${
						trackingAllowed
							? 'ts-consent-visualizer__tracking-badge--allowed'
							: 'ts-consent-visualizer__tracking-badge--denied'
					}`}
				>
					<Icon
						name={trackingAllowed ? 'CheckCircle2' : 'XCircle'}
						className="ts-consent-visualizer__tracking-icon"
					/>
					<span>
						{trackingAllowed ? 'Your Preview: Allowed' : 'Your Preview: Denied'}
					</span>
				</div>
			</div>

			<div className="ts-consent-visualizer__grid">
				{consentCategories.map((category) => {
					const status = consentState[category.key];
					const isGranted = status === 'granted';

					return (
						<div
							key={category.key}
							className={`ts-consent-category ${
								isGranted ? 'ts-consent-category--granted' : 'ts-consent-category--denied'
							}`}
						>
							<div className="ts-consent-category__icon-container">
								<Icon name={category.icon} className="ts-consent-category__icon" />
							</div>

							<div className="ts-consent-category__content">
								<p className="ts-consent-category__label">
									{category.label}
								</p>
								<p className="ts-consent-category__description">
									{category.description}
								</p>
							</div>

							<span className="ts-consent-category__badge">
								{status}
							</span>

							<Icon
								name={isGranted ? 'Check' : 'X'}
								className="ts-consent-category__check"
							/>
						</div>
					);
				})}
			</div>

			<div className="ts-consent-visualizer__info">
				<div className="ts-consent-visualizer__info-content">
					<Icon name="Info" className="ts-consent-visualizer__info-icon" />
					<p className="ts-consent-visualizer__info-text">
						<strong>How This Works:</strong> This preview shows the consent state from YOUR browser cookies (as the admin). 
						Each website visitor has their own individual consent state based on their choices in the consent banner. 
						When a visitor grants consent, TrackSure sends &quot;granted&quot; signals to Google Ads/GA4/Meta. 
						When denied, TrackSure sends &quot;denied&quot; signals and anonymizes all tracking data automatically.
					</p>
				</div>
			</div>

			<div className="ts-consent-visualizer__warning">
				<div className="ts-consent-visualizer__warning-content">
					<Icon name="AlertCircle" className="ts-consent-visualizer__warning-icon" />
					<p className="ts-consent-visualizer__warning-text">
						<strong>Important:</strong> This is NOT showing the consent state of your website visitors. 
						Each visitor&apos;s consent is tracked individually in their browser. To see aggregate visitor consent data, 
						use your consent plugin&apos;s dashboard or analytics.
					</p>
				</div>
			</div>
		</Card>
	);
};
