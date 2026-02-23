/**
 * Consent Warning Banner Component
 * 
 * Displays warning when:
 * - Consent mode set to 'opt-in' or 'opt-out'
 * - No consent plugin detected
 * - User hasn't dismissed the warning
 * 
 * Supports dark/light mode via Tailwind classes.
 * 
 * @since 1.0.1
 */

import React from 'react';
import { Icon } from '../ui/Icon';
import { Button } from '../ui/Button';
import type { ConsentWarning, SupportedPlugin } from '../../hooks/useConsentStatus';

interface ConsentWarningBannerProps {
	warning: ConsentWarning;
	supportedPlugins: SupportedPlugin[];
	onDismiss: () => void;
}

export const ConsentWarningBanner: React.FC<ConsentWarningBannerProps> = ({
	warning,
	supportedPlugins,
	onDismiss,
}) => {
	if (!warning || !warning.should_show) {
		return null;
	}

	const recommendedPlugins = supportedPlugins
		.filter((p) => p.recommended)
		.slice(0, 3);

	return (
		<div className="mb-6 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4">
			<div className="flex items-start gap-3">
				<div className="flex-shrink-0 mt-0.5">
					<Icon name="AlertTriangle" className="w-5 h-5 text-amber-600 dark:text-amber-400" />
				</div>

				<div className="flex-1 min-w-0">
					<h3 className="text-sm font-semibold text-amber-900 dark:text-amber-100 mb-1">
						Consent Plugin Recommended
					</h3>

					<p className="text-sm text-amber-800 dark:text-amber-200 mb-3">
						{warning.message}
					</p>

					<div className="space-y-2">
						<p className="text-xs font-medium text-amber-900 dark:text-amber-100 uppercase tracking-wide">
							Recommended Plugins:
						</p>

						<ul className="grid grid-cols-1 sm:grid-cols-3 gap-2">
							{recommendedPlugins.map((plugin) => (
								<li
									key={plugin.id}
									className="flex items-center gap-2 text-sm text-amber-800 dark:text-amber-200"
								>
									<Icon name="Check" className="w-4 h-4 flex-shrink-0" />
									<a
										href={plugin.url}
										target="_blank"
										rel="noopener noreferrer"
										className="font-medium hover:underline"
									>
										{plugin.name}
									</a>
								</li>
							))}
						</ul>

						<p className="text-xs text-amber-700 dark:text-amber-300 mt-3 p-3 bg-amber-100 dark:bg-amber-900/40 rounded border border-amber-200 dark:border-amber-800">
							<Icon name="Info" className="w-4 h-4 inline mr-1" />
							<strong>GDPR Protection:</strong> Without a consent plugin, TrackSure will
							anonymize all user data (remove email, phone, user ID, IP last octet) to
							ensure compliance.
						</p>
					</div>

					<div className="flex items-center gap-3 mt-4">
						<Button
							variant="outline"
							size="sm"
							onClick={onDismiss}
							className="border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-100 hover:bg-amber-100 dark:hover:bg-amber-900/40"
						>
							Dismiss
						</Button>

						<a
							href="https://wordpress.org/plugins/search/consent+cookie/"
							target="_blank"
							rel="noopener noreferrer"
							className="text-sm font-medium text-amber-700 dark:text-amber-300 hover:text-amber-900 dark:hover:text-amber-100 underline"
						>
							Browse Consent Plugins →
						</a>
					</div>
				</div>

				<button
					onClick={onDismiss}
					className="flex-shrink-0 text-amber-600 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-100 transition-colors"
					aria-label="Close warning"
				>
					<Icon name="X" className="w-5 h-5" />
				</button>
			</div>
		</div>
	);
};
