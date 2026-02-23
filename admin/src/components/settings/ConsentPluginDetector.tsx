/**
 * Consent Plugin Detector Component
 * 
 * Displays detected consent plugin status with:
 * - Plugin name and detection status
 * - Visual indicator (green checkmark or gray dash)
 * - List of supported plugins
 * 
 * Supports dark/light mode.
 * 
 * @since 1.0.1
 */

import React from 'react';
import { Icon } from '../ui/Icon';
import { Card } from '../ui/Card';
import type { SupportedPlugin } from '../../hooks/useConsentStatus';
import '../../styles/components/settings/ConsentPluginDetector.css';

interface ConsentPluginDetectorProps {
	detectedPlugin: string | null;
	supportedPlugins: SupportedPlugin[];
}

export const ConsentPluginDetector: React.FC<ConsentPluginDetectorProps> = ({
	detectedPlugin,
	supportedPlugins,
}) => {
	const detectedPluginInfo = supportedPlugins.find((p) => p.id === detectedPlugin);

	return (
		<Card padding="md" className="ts-consent-detector">
			<div className="ts-consent-detector__header">
				<div>
					<h3 className="ts-consent-detector__title">
						Consent Plugin Detection
					</h3>
					<p style={{ fontSize: 'var(--ts-text-xs)', color: 'var(--ts-text-secondary)' }}>
						Automatically detects active consent management plugins
					</p>
				</div>

				{detectedPlugin ? (
					<div className="ts-consent-detector__status" style={{ background: 'var(--ts-success-bg)', borderColor: 'var(--ts-success-border)', color: 'var(--ts-success-text)' }}>
						<Icon name="Check" className="ts-consent-detector__status-icon" />
						<span className="ts-consent-detector__status-text">Detected</span>
					</div>
				) : (
					<div className="ts-consent-detector__status">
						<Icon name="Minus" className="ts-consent-detector__status-icon" />
						<span className="ts-consent-detector__status-text">None</span>
					</div>
				)}
			</div>

			{detectedPluginInfo ? (
				<div className="ts-consent-detected">
					<div className="ts-consent-detected__inner">
						<div className="ts-consent-detected__icon-container">
							<Icon name="Shield" className="ts-consent-detected__icon" />
						</div>
						<div className="ts-consent-detected__content">
							<p className="ts-consent-detected__name">
								{detectedPluginInfo.name}
							</p>
							<p className="ts-consent-detected__description">
								{detectedPluginInfo.recommended ? '★ Recommended' : 'Fully compatible'} • Enterprise-grade consent management
							</p>
						</div>
						<Icon name="CheckCircle2" className="ts-consent-detected__check-icon" />
					</div>
				</div>
			) : (
				<div className="ts-consent-no-plugin">
					<p className="ts-consent-no-plugin__text">
						No consent plugin detected. TrackSure will anonymize data when consent mode is enabled.
					</p>
				</div>
			)}

			<div>
				<h4 className="ts-consent-supported__title">
					Supported Plugins ({supportedPlugins.length})
				</h4>

				<div className="ts-consent-supported__grid">
					{supportedPlugins.map((plugin) => (
						<div
							key={plugin.id}
							className={`ts-consent-plugin-card ${
								plugin.id === detectedPlugin ? 'ts-consent-plugin-card--detected' : ''
							}`}
						>
							<div className="ts-consent-plugin-card__check">
								{plugin.id === detectedPlugin ? (
									<Icon name="Check" className="ts-consent-plugin-card__check-icon" />
								) : (
									<Icon name="Circle" className="ts-consent-plugin-card__check-icon" />
								)}
							</div>

							<div className="ts-consent-plugin-card__content">
							<a
								href={plugin.url}
								target="_blank"
								rel="noopener noreferrer"
								className="ts-consent-plugin-card__name"
							>
								{plugin.name}
								<Icon name="ExternalLink" className="ts-consent-plugin-card__link-icon" />
							</a>
							</div>

							{plugin.recommended && plugin.id !== detectedPlugin && (
								<span className="ts-consent-plugin-card__badge">
									★ Recommended
								</span>
							)}
						</div>
					))}
				</div>
			</div>
		</Card>
	);
};
