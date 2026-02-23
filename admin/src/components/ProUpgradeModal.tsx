/**
 * Pro Upgrade Modal
 * 
 * Shown when users try to use Pro features without Pro license.
 */

import React from 'react';
import { Modal } from '../components/ui';

interface ProUpgradeModalProps {
  isOpen: boolean;
  onClose: () => void;
  feature: string;
  upgradeUrl: string;
}

export const ProUpgradeModal: React.FC<ProUpgradeModalProps> = ({
  isOpen,
  onClose,
  feature,
  upgradeUrl,
}) => {
  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="md"
      title="Upgrade to TrackSure Pro"
    >
      <div className="ts-pro-upgrade-modal">
        <div className="ts-pro-icon">
          <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
            <circle cx="32" cy="32" r="32" fill="#3B82F6" fillOpacity="0.1" />
            <path
              d="M32 16l8 16h-6v16l-8-16h6V16z"
              fill="#3B82F6"
              stroke="#3B82F6"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </div>

        <h3 className="ts-pro-title">Unlock {feature}</h3>

        <p className="ts-pro-description">
          This feature is available in TrackSure Pro. Upgrade now to access:
        </p>

        <ul className="ts-pro-features">
          <li>✨ Advanced goal operators (regex, starts_with, ends_with)</li>
          <li>📊 Multi-touch attribution models</li>
          <li>🎯 Premium goal templates</li>
          <li>⚡ Advanced triggers (video, downloads, outbound links)</li>
          <li>🔄 Frequency controls & cooldowns</li>
          <li>💼 Priority support</li>
        </ul>

        <div className="ts-pro-actions">
          <button
            className="ts-btn ts-btn-primary ts-btn-lg"
            onClick={() => window.open(upgradeUrl, '_blank')}
          >
            Upgrade to Pro →
          </button>
          <button
            className="ts-btn ts-btn-outline"
            onClick={onClose}
          >
            Maybe Later
          </button>
        </div>

        <p className="ts-pro-guarantee">
          30-day money-back guarantee • Cancel anytime
        </p>
      </div>

      <style>{`
        .ts-pro-upgrade-modal {
          text-align: center;
          padding: 24px;
        }

        .ts-pro-icon {
          margin: 0 auto 24px;
          width: 64px;
          height: 64px;
        }

        .ts-pro-title {
          font-size: 24px;
          font-weight: 700;
          margin: 0 0 12px;
          color: var(--ts-text);
        }

        .ts-pro-description {
          font-size: 16px;
          color: var(--ts-text-muted);
          margin: 0 0 24px;
          line-height: 1.6;
        }

        .ts-pro-features {
          list-style: none;
          padding: 0;
          margin: 0 0 32px;
          text-align: left;
          max-width: 400px;
          margin-left: auto;
          margin-right: auto;
        }

        .ts-pro-features li {
          padding: 12px 0;
          font-size: 15px;
          color: var(--ts-text);
          border-bottom: 1px solid var(--ts-border);
        }

        .ts-pro-features li:last-child {
          border-bottom: none;
        }

        .ts-pro-actions {
          display: flex;
          gap: 12px;
          justify-content: center;
          margin-bottom: 16px;
        }

        .ts-pro-actions .ts-btn {
          flex: 1;
          max-width: 200px;
        }

        .ts-pro-guarantee {
          font-size: 13px;
          color: var(--ts-text-muted);
          margin: 0;
        }

        [data-theme="dark"] .ts-pro-title {
          color: var(--ts-text-dark);
        }

        [data-theme="dark"] .ts-pro-description,
        [data-theme="dark"] .ts-pro-guarantee {
          color: var(--ts-text-muted-dark);
        }

        [data-theme="dark"] .ts-pro-features li {
          color: var(--ts-text-dark);
          border-color: var(--ts-border-dark);
        }
      `}</style>
    </Modal>
  );
};
