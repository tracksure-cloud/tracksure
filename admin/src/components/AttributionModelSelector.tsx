/**
 * Attribution Model Selector
 * 
 * Allows users to switch between different attribution models:
 * - First-Touch (100% credit to first interaction)
 * - Last-Touch (100% credit to last interaction)
 * - Linear (Equal credit across all touchpoints) - PRO
 * - Time-Decay (More credit to recent touchpoints) - PRO
 * - Position-Based (40% first, 40% last, 20% middle) - PRO
 */

import React from 'react';
import { Icon } from './ui/Icon';
import { ProUpgradeModal } from './ProUpgradeModal';
import { __ } from '@wordpress/i18n';
import '../styles/components/AttributionModelSelector.css';

export type AttributionModel = 'first_touch' | 'last_touch' | 'linear' | 'time_decay' | 'position_based';

interface AttributionModelSelectorProps {
  selectedModel: AttributionModel;
  onChange: (model: AttributionModel) => void;
  isPro?: boolean;
}

interface ModelOption {
  value: AttributionModel;
  label: string;
  description: string;
  icon: 'PlayCircle' | 'CheckCircle' | 'BarChart2' | 'TrendingDown' | 'Target' | 'TrendingUp' | 'Clock' | 'Award';
  isPro: boolean;
  example: string;
}

const ATTRIBUTION_MODELS: ModelOption[] = [
  {
    value: 'first_touch',
    label: __('First-Touch'),
    description: __('100% credit to the first interaction'),
    icon: 'PlayCircle',
    isPro: false,
    example: __('Google Ad gets 100% credit for starting the journey'),
  },
  {
    value: 'last_touch',
    label: __('Last-Touch'),
    description: __('100% credit to the last interaction before conversion'),
    icon: 'Target',
    isPro: false,
    example: __('Facebook Ad gets 100% credit for final touchpoint'),
  },
  {
    value: 'linear',
    label: __('Linear'),
    description: __('Equal credit distributed across all touchpoints'),
    icon: 'TrendingUp',
    isPro: true,
    example: __('Google 33% + Email 33% + Facebook 33% = 100%'),
  },
  {
    value: 'time_decay',
    label: __('Time-Decay'),
    description: __('More credit to recent touchpoints'),
    icon: 'Clock',
    isPro: true,
    example: __('Google 10% + Email 30% + Facebook 60% = 100%'),
  },
  {
    value: 'position_based',
    label: __('Position-Based'),
    description: __('40% first + 40% last + 20% middle'),
    icon: 'Award',
    isPro: true,
    example: __('Google 40% + Email 20% + Facebook 40% = 100%'),
  },
];

export const AttributionModelSelector: React.FC<AttributionModelSelectorProps> = ({
  selectedModel,
  onChange,
  isPro = false,
}) => {
  const [showInfo, setShowInfo] = React.useState<AttributionModel | null>(null);
  const [showUpgradeModal, setShowUpgradeModal] = React.useState(false);
  const [upgradeFeature, setUpgradeFeature] = React.useState('');

  const handleModelClick = (model: AttributionModel, modelIsPro: boolean) => {
    if (modelIsPro && !isPro) {
      // Show professional upgrade modal
      const modelLabel = ATTRIBUTION_MODELS.find(m => m.value === model)?.label || model;
      setUpgradeFeature(`${modelLabel} Attribution Model`);
      setShowUpgradeModal(true);
      return;
    }
    onChange(model);
  };

  return (
    <div className="attribution-model-selector">
      <div className="model-selector-header">
        <h3>
          <Icon name="GitBranch" size={20} />
          {__('Attribution Model')}
        </h3>
        <p className="model-selector-subtitle">
          {__('Choose how to distribute conversion credit across touchpoints')}
        </p>
      </div>

      <div className="model-options-grid">
        {ATTRIBUTION_MODELS.map((model) => {
          const isSelected = selectedModel === model.value;
          const isLocked = model.isPro && !isPro;
          const _isAvailable = !isLocked;

          return (
            <div
              key={model.value}
              className={`model-option ${isSelected ? 'selected' : ''} ${isLocked ? 'locked' : ''}`}
              onClick={() => handleModelClick(model.value, model.isPro)}
              onMouseEnter={() => setShowInfo(model.value)}
              onMouseLeave={() => setShowInfo(null)}
            >
              <div className="model-option-header">
                <div className="model-icon">
                  <Icon name={model.icon} size={24} />
                  {isLocked && (
                    <span className="lock-badge">
                      <Icon name="Lock" size={12} />
                    </span>
                  )}
                </div>
                <div className="model-label">
                  <h4>{model.label}</h4>
                  {model.isPro && <span className="pro-badge">PRO</span>}
                </div>
              </div>

              <p className="model-description">{model.description}</p>

              {showInfo === model.value && (
                <div className="model-tooltip">
                  <div className="tooltip-title">
                    <Icon name="Info" size={16} />
                    {__('Example:')}
                  </div>
                  <p className="tooltip-example">{model.example}</p>
                </div>
              )}

              {isSelected && (
                <div className="selected-indicator">
                  <Icon name="CheckCircle" size={20} color="success" />
                </div>
              )}
            </div>
          );
        })}
      </div>

      {selectedModel && (
        <div className="selected-model-info">
          <div className="info-card">
            <Icon name="Info" size={20} color="info" />
            <div className="info-content">
              <h4>{__('Using:')} {ATTRIBUTION_MODELS.find(m => m.value === selectedModel)?.label}</h4>
              <p>
                {selectedModel === 'first_touch' && __('Ideal for understanding what brings visitors to your site initially.')}
                {selectedModel === 'last_touch' && __('Best for seeing what final touchpoint drives conversions.')}
                {selectedModel === 'linear' && __('Gives equal weight to all marketing channels in the journey.')}
                {selectedModel === 'time_decay' && __('Prioritizes channels closer to conversion time.')}
                {selectedModel === 'position_based' && __('Balances first and last touch with middle touchpoints.')}
              </p>
            </div>
          </div>
        </div>
      )}

      {!isPro && (
        <div className="upgrade-cta">
          <div className="cta-content">
            <Icon name="Zap" size={24} color="warning" />
            <div>
              <h4>{__('Unlock Advanced Attribution Models')}</h4>
              <p>{__('Get Linear, Time-Decay, and Position-Based models with TrackSure Pro')}</p>
            </div>
            <button className="btn-upgrade">
              {__('Upgrade to Pro')}
              <Icon name="ArrowRight" size={16} />
            </button>
          </div>
        </div>
      )}

      {/* Pro Upgrade Modal */}
      <ProUpgradeModal
        isOpen={showUpgradeModal}
        onClose={() => setShowUpgradeModal(false)}
        feature={upgradeFeature}
        upgradeUrl={(typeof window !== 'undefined' && window.trackSureConfig?.proUpgradeUrl) || 'https://tracksure.io/pricing'}
      />
    </div>
  );
};
