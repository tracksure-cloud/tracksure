/**
 * GA4 Setup Guide Modal - Premium Design
 * 
 * Shows required manual GA4 Admin setup steps with premium styling.
 * Supports dark/light theme, translations, and responsive design.
 */

import React, { useState } from 'react';
import { Modal } from './ui';
import { Icon } from './ui/Icon';
import { __ } from '@wordpress/i18n';
import '../styles/components/GA4SetupGuideModal.css';

export interface GA4SetupGuideData {
  show_guide: boolean;
  dismissed: boolean;
  configured: boolean;
  measurement_id?: string;
  ga4_admin_url?: string;
  title?: string;
  intro?: {
    good_news: string;
    important: string;
  };
  steps?: Array<{
    id: number;
    title: string;
    time: string;
    critical: boolean;
    description: string;
    why: string;
    events?: string[];
    dimensions?: Array<{
      name: string;
      scope: string;
      parameter: string;
      why: string;
    }>;
    audiences?: Array<{
      name: string;
      include?: string;
      exclude?: string;
      condition?: string;
    }>;
    integrations?: Array<{
      name: string;
      benefits: string[];
    }>;
  }>;
  tracksure_handles?: string[];
  summary?: string;
  note?: string;
  reason?: string;
}

interface GA4SetupGuideModalProps {
  isOpen: boolean;
  onClose: () => void;
  guideData: GA4SetupGuideData | null;
  onDismiss: () => void;
}

export const GA4SetupGuideModal: React.FC<GA4SetupGuideModalProps> = ({
  isOpen,
  onClose,
  guideData,
  onDismiss,
}) => {
  const [expandedSteps, setExpandedSteps] = useState<number[]>([]);
  const [showAutomationDetails, setShowAutomationDetails] = useState(false);

  if (!guideData || !guideData.show_guide) {
    return null;
  }

  const toggleStep = (stepId: number) => {
    setExpandedSteps((prev) =>
      prev.includes(stepId)
        ? prev.filter((id) => id !== stepId)
        : [...prev, stepId]
    );
  };

  const handleOpenGA4Admin = () => {
    if (guideData.ga4_admin_url) {
      window.open(guideData.ga4_admin_url, '_blank', 'noopener,noreferrer');
    }
  };

  const handleDismiss = async () => {
    await onDismiss();
    onClose();
  };

  const totalTime = guideData.steps?.reduce((acc, step) => {
    const minutes = parseInt(step.time.match(/\d+/)?.[0] || '0');
    return acc + minutes;
  }, 0) || 0;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={
        <div className="ts-ga4-modal-title">
          <div className="ts-ga4-modal-title-icon">🚀</div>
          <div>
            <div className="ts-ga4-modal-title-text">
              {guideData.title || __('GA4 Setup Guide')}
            </div>
            <div className="ts-ga4-modal-subtitle">
              {__('5 quick steps to complete your setup')} • ~{totalTime} {__('minutes')}
            </div>
          </div>
        </div>
      }
      size="lg"
      closeOnOverlayClick={false}
    >
      <div className="ts-ga4-setup-guide">
        
        {/* Hero Banner */}
        <div className="ts-ga4-hero">
          <div className="ts-ga4-hero-content">
            <div className="ts-ga4-hero-badge">
              <Icon name="CheckCircle" size={16} color="success" />
              <span>{__('Tracking Active')}</span>
            </div>
            <h3 className="ts-ga4-hero-title">{guideData.intro?.good_news}</h3>
            <div className="ts-ga4-hero-alert">
              <Icon name="AlertCircle" size={20} color="warning" />
              <p>{guideData.intro?.important}</p>
            </div>
          </div>
        </div>

        {/* Setup Steps */}
        <div className="ts-ga4-steps-container">
          <div className="ts-ga4-steps-header">
            <h4>{__('Required Setup Steps')}</h4>
            <div className="ts-ga4-progress">
              <span className="ts-ga4-progress-text">0/5 {__('completed')}</span>
            </div>
          </div>

          <div className="ts-ga4-steps">
            {guideData.steps?.map((step, index) => {
              const isExpanded = expandedSteps.includes(step.id);

              return (
                <div
                  key={step.id}
                  className={`ts-ga4-step ${step.critical ? 'ts-ga4-step--critical' : ''} ${
                    isExpanded ? 'ts-ga4-step--expanded' : ''
                  }`}
                >
                  {/* Step Header */}
                  <div
                    className="ts-ga4-step-header"
                    onClick={() => toggleStep(step.id)}
                    role="button"
                    tabIndex={0}
                    onKeyPress={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleStep(step.id);
                      }
                    }}
                  >
                    <div className="ts-ga4-step-header-left">
                      <div className="ts-ga4-step-number">
                        <span>{index + 1}</span>
                      </div>
                      <div className="ts-ga4-step-info">
                        <div className="ts-ga4-step-title">
                          {step.title}
                          {step.critical && (
                            <span className="ts-ga4-critical-badge">
                              {__('Critical')}
                            </span>
                          )}
                        </div>
                        <div className="ts-ga4-step-meta">
                          <Icon name="Clock" size={14} />
                          <span>{step.time}</span>
                        </div>
                      </div>
                    </div>
                    <Icon
                      name={isExpanded ? 'ChevronUp' : 'ChevronDown'}
                      size={20}
                      className="ts-ga4-step-chevron"
                    />
                  </div>

                  {/* Step Content */}
                  {isExpanded && (
                    <div className="ts-ga4-step-content">
                      <div className="ts-ga4-step-description">
                        <p>{step.description}</p>
                      </div>

                      {/* Events List (Step 1) */}
                      {step.events && step.events.length > 0 && (
                        <div className="ts-ga4-step-list">
                          <div className="ts-ga4-step-list-title">
                            {__('Mark these events as conversions:')}
                          </div>
                          <div className="ts-ga4-events-grid">
                            {step.events.map((event) => (
                              <div key={event} className="ts-ga4-event-tag">
                                <Icon name="Zap" size={14} />
                                <code>{event}</code>
                                <span className="ts-ga4-event-status">✅ {__('Tracked')}</span>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Custom Dimensions (Step 3) */}
                      {step.dimensions && step.dimensions.length > 0 && (
                        <div className="ts-ga4-step-list">
                          <div className="ts-ga4-step-list-title">
                            {__('Create these custom dimensions:')}
                          </div>
                          <div className="ts-ga4-dimensions-list">
                            {step.dimensions.map((dim, idx) => (
                              <div key={idx} className="ts-ga4-dimension-card">
                                <div className="ts-ga4-dimension-header">
                                  <code className="ts-ga4-dimension-name">{dim.name}</code>
                                  <span className="ts-ga4-dimension-scope">
                                    {dim.scope}
                                  </span>
                                </div>
                                <div className="ts-ga4-dimension-detail">
                                  <span className="ts-ga4-dimension-label">{__('Parameter:')}</span>
                                  <code>{dim.parameter}</code>
                                </div>
                                <div className="ts-ga4-dimension-why">{dim.why}</div>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Audiences (Step 4) */}
                      {step.audiences && step.audiences.length > 0 && (
                        <div className="ts-ga4-step-list">
                          <div className="ts-ga4-step-list-title">
                            {__('Recommended audiences:')}
                          </div>
                          <div className="ts-ga4-audiences-list">
                            {step.audiences.map((audience, idx) => (
                              <div key={idx} className="ts-ga4-audience-card">
                                <div className="ts-ga4-audience-name">
                                  <Icon name="Users" size={16} />
                                  {audience.name}
                                </div>
                                {audience.include && (
                                  <div className="ts-ga4-audience-rule">
                                    <span className="ts-ga4-audience-rule-label">
                                      {__('Include:')}
                                    </span>
                                    <span>{audience.include}</span>
                                  </div>
                                )}
                                {audience.exclude && (
                                  <div className="ts-ga4-audience-rule">
                                    <span className="ts-ga4-audience-rule-label">
                                      {__('Exclude:')}
                                    </span>
                                    <span>{audience.exclude}</span>
                                  </div>
                                )}
                                {audience.condition && (
                                  <div className="ts-ga4-audience-rule">
                                    <span className="ts-ga4-audience-rule-label">
                                      {__('Condition:')}
                                    </span>
                                    <span>{audience.condition}</span>
                                  </div>
                                )}
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Integrations (Step 5) */}
                      {step.integrations && step.integrations.length > 0 && (
                        <div className="ts-ga4-step-list">
                          <div className="ts-ga4-step-list-title">
                            {__('Link these integrations:')}
                          </div>
                          <div className="ts-ga4-integrations-grid">
                            {step.integrations.map((integration, idx) => (
                              <div key={idx} className="ts-ga4-integration-card">
                                <div className="ts-ga4-integration-name">
                                  <Icon name="Link" size={16} />
                                  {integration.name}
                                </div>
                                <ul className="ts-ga4-integration-benefits">
                                  {integration.benefits.map((benefit, bidx) => (
                                    <li key={bidx}>{benefit}</li>
                                  ))}
                                </ul>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Why Section */}
                      <div className="ts-ga4-step-why">
                        <Icon name="Lightbulb" size={16} color="warning" />
                        <span>
                          <strong>{__('Why this matters:')}</strong> {step.why}
                        </span>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        {/* What TrackSure Handles */}
        <div className="ts-ga4-automation">
          <button
            className="ts-ga4-automation-toggle"
            onClick={() => setShowAutomationDetails(!showAutomationDetails)}
          >
            <div className="ts-ga4-automation-toggle-left">
              <Icon name="CheckCircle" size={20} color="success" />
              <span className="ts-ga4-automation-toggle-title">
                {__('What TrackSure Already Handles')}
              </span>
              <span className="ts-ga4-automation-badge">
                {guideData.tracksure_handles?.length || 0} {__('features')}
              </span>
            </div>
            <Icon
              name={showAutomationDetails ? 'ChevronUp' : 'ChevronDown'}
              size={20}
            />
          </button>

          {showAutomationDetails && (
            <div className="ts-ga4-automation-content">
              <div className="ts-ga4-automation-grid">
                {guideData.tracksure_handles?.map((item, idx) => (
                  <div key={idx} className="ts-ga4-automation-item">
                    <Icon name="Check" size={16} color="success" />
                    <span>{item}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Summary */}
        <div className="ts-ga4-summary">
          <Icon name="Info" size={20} color="primary" />
          <div className="ts-ga4-summary-content">
            <p><strong>{__('Summary:')}</strong> {guideData.summary}</p>
            {guideData.note && (
              <p className="ts-ga4-summary-note">
                <Icon name="Clock" size={16} />
                {guideData.note}
              </p>
            )}
          </div>
        </div>

        {/* Action Buttons */}
        <div className="ts-ga4-actions">
          <button
            className="ts-button ts-button-primary ts-ga4-primary-action"
            onClick={handleOpenGA4Admin}
          >
            <Icon name="ExternalLink" size={18} />
            {__('Open GA4 Admin & Complete Setup')}
          </button>
          <button
            className="ts-button ts-button-secondary"
            onClick={handleDismiss}
          >
            <Icon name="CheckCircle" size={18} />
            {__("I've Completed Setup")}
          </button>
        </div>
      </div>
    </Modal>
  );
};
