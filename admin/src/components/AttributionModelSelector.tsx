/**
 * Attribution Model Selector
 * 
 * Allows users to switch between different attribution models:
 * - First-Touch (100% credit to first interaction)
 * - Last-Touch (100% credit to last interaction)
 * - Linear (Equal credit across all touchpoints)
 * - Time-Decay (More credit to recent touchpoints)
 * - Position-Based (40% first, 40% last, 20% middle)
 */

import React from 'react';
import { Icon } from './ui/Icon';
import { __ } from '@wordpress/i18n';
import '../styles/components/AttributionModelSelector.css';

export type AttributionModel = 'first_touch' | 'last_touch' | 'linear' | 'time_decay' | 'position_based';

interface AttributionModelSelectorProps {
  selectedModel: AttributionModel;
  onChange: (model: AttributionModel) => void;
}

interface ModelOption {
  value: AttributionModel;
  label: string;
  description: string;
  icon: 'PlayCircle' | 'CheckCircle' | 'BarChart2' | 'TrendingDown' | 'Target' | 'TrendingUp' | 'Clock' | 'Award';
  example: string;
}

const ATTRIBUTION_MODELS: ModelOption[] = [
  {
    value: 'first_touch',
    label: __('First-Touch'),
    description: __('100% credit to the first interaction'),
    icon: 'PlayCircle',
    example: __('Google Ad gets 100% credit for starting the journey'),
  },
  {
    value: 'last_touch',
    label: __('Last-Touch'),
    description: __('100% credit to the last interaction before conversion'),
    icon: 'Target',
    example: __('Facebook Ad gets 100% credit for final touchpoint'),
  },
  {
    value: 'linear',
    label: __('Linear'),
    description: __('Equal credit distributed across all touchpoints'),
    icon: 'TrendingUp',
    example: __('Google 33% + Email 33% + Facebook 33% = 100%'),
  },
  {
    value: 'time_decay',
    label: __('Time-Decay'),
    description: __('More credit to recent touchpoints'),
    icon: 'Clock',
    example: __('Google 10% + Email 30% + Facebook 60% = 100%'),
  },
  {
    value: 'position_based',
    label: __('Position-Based'),
    description: __('40% first + 40% last + 20% middle'),
    icon: 'Award',
    example: __('Google 40% + Email 20% + Facebook 40% = 100%'),
  },
];

export const AttributionModelSelector: React.FC<AttributionModelSelectorProps> = ({
  selectedModel,
  onChange,
}) => {
  const [showInfo, setShowInfo] = React.useState<AttributionModel | null>(null);

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

          return (
            <div
              key={model.value}
              className={`model-option ${isSelected ? 'selected' : ''}`}
              onClick={() => onChange(model.value)}
              onMouseEnter={() => setShowInfo(model.value)}
              onMouseLeave={() => setShowInfo(null)}
            >
              <div className="model-option-header">
                <div className="model-icon">
                  <Icon name={model.icon} size={24} />
                </div>
                <div className="model-label">
                  <h4>{model.label}</h4>
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
    </div>
  );
};
