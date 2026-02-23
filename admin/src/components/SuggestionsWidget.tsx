import React, { useState, useEffect } from 'react';
import { useApp } from '../contexts/AppContext';
import { TrackSureAPI } from '../utils/api';
import { Icon } from './ui/Icon';
import { __ } from '@wordpress/i18n';
import type { Suggestion } from '../types';
import '../styles/components/SuggestionsWidget.css';

export const SuggestionsWidget: React.FC = () => {
    const { dateRange, config } = useApp();
    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
    const [loading, setLoading] = useState(true);
    const [expanded, setExpanded] = useState<string[]>([]);
    const [dismissed, setDismissed] = useState<string[]>([]);

    useEffect(() => {
        fetchSuggestions();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- Fetch on date range change only
    }, [dateRange]);

    const fetchSuggestions = async () => {
        setLoading(true);
        try {
            const api = new TrackSureAPI(config);
            const response = await api.get('/suggestions', {
                limit: 10,
            });
            
            // Backend returns array directly, not wrapped in {data: ...}
            const suggestionsArray = Array.isArray(response) ? response : ((response as Record<string, unknown>).data as unknown[]) || [];
            
            // Transform backend data to frontend format
            const transformedSuggestions: Suggestion[] = suggestionsArray.map((item: Record<string, unknown>, index: number) => ({
                id: `suggestion-${index}`,
                priority: (item.priority as Suggestion['priority']) || 'medium',
                title: String(item.title || ''),
                description: String(item.description || ''),
                action: String(item.action || ''),
                metric: (item.metric as Suggestion['metric']) || null,
            }));
            
            setSuggestions(transformedSuggestions);
        } catch (error) {
            console.error('Failed to fetch suggestions:', error);
            setSuggestions([]);
        } finally {
            setLoading(false);
        }
    };

    const toggleExpand = (id: string) => {
        setExpanded((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
        );
    };

    const dismissSuggestion = (id: string) => {
        setDismissed((prev) => [...prev, id]);
    };

    const visibleSuggestions = suggestions.filter(s => !dismissed.includes(s.id));
    const highPriority = visibleSuggestions.filter(s => s.priority === 'high').length;
    const mediumPriority = visibleSuggestions.filter(s => s.priority === 'medium').length;

    // Loading state
    if (loading) {
        return (
            <div className="ts-suggestions-widget ts-suggestions-widget--loading">
                <div className="ts-suggestions-header">
                    <h3 className="ts-suggestions-title">
                        <Icon name="Lightbulb" size={20} />
                        <span>{__('Smart Insights', 'tracksure')}</span>
                    </h3>
                </div>
                <div className="ts-suggestions-loading">
                    <div className="ts-loading-spinner">
                        <Icon name="RefreshCw" size={32} className="ts-spin" />
                    </div>
                    <p className="ts-loading-text">{__('Analyzing your data...', 'tracksure')}</p>
                </div>
            </div>
        );
    }

    // Empty state
    if (visibleSuggestions.length === 0) {
        return (
            <div className="ts-suggestions-widget ts-suggestions-widget--empty">
                <div className="ts-suggestions-header">
                    <h3 className="ts-suggestions-title">
                        <Icon name="Lightbulb" size={20} />
                        <span>{__('Smart Insights', 'tracksure')}</span>
                    </h3>
                </div>
                <div className="ts-suggestions-empty">
                    <div className="ts-empty-icon">
                        <Icon name="CheckCircle" size={64} color="success" />
                    </div>
                    <h4 className="ts-empty-title">{__('All Good!', 'tracksure')}</h4>
                    <p className="ts-empty-description">
                        {__('No actionable insights right now. Your tracking is performing well.', 'tracksure')}
                    </p>
                </div>
            </div>
        );
    }

    // Main widget with suggestions
    return (
        <div className="ts-suggestions-widget">
            <div className="ts-suggestions-header">
                <div className="ts-suggestions-header-content">
                    <h3 className="ts-suggestions-title">
                        <Icon name="Lightbulb" size={20} />
                        <span>{__('Smart Insights', 'tracksure')}</span>
                    </h3>
                    <div className="ts-suggestions-badges">
                        {highPriority > 0 && (
                            <span className="ts-badge ts-badge--danger">
                                <Icon name="AlertCircle" size={12} />
                                <span>{highPriority} {__('urgent', 'tracksure')}</span>
                            </span>
                        )}
                        {mediumPriority > 0 && (
                            <span className="ts-badge ts-badge--warning">
                                <Icon name="AlertTriangle" size={12} />
                                <span>{mediumPriority} {__('recommended', 'tracksure')}</span>
                            </span>
                        )}
                    </div>
                </div>
                <button
                    className="ts-suggestions-refresh"
                    onClick={fetchSuggestions}
                    title={__('Refresh suggestions', 'tracksure')}
                    aria-label={__('Refresh suggestions', 'tracksure')}
                >
                    <Icon name="RefreshCw" size={16} />
                </button>
            </div>

            <div className="ts-suggestions-list">
                {visibleSuggestions.map((suggestion) => {
                    const isExpanded = expanded.includes(suggestion.id);

                    return (
                        <div
                            key={suggestion.id}
                            className={`ts-suggestion-card ts-suggestion-card--${suggestion.priority} ${
                                isExpanded ? 'ts-suggestion-card--expanded' : ''
                            }`}
                        >
                            <div 
                                className="ts-suggestion-header" 
                                onClick={() => toggleExpand(suggestion.id)}
                                role="button"
                                tabIndex={0}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        toggleExpand(suggestion.id);
                                    }
                                }}
                            >
                                <div className="ts-suggestion-header-main">
                                    <div className="ts-suggestion-priority-icon">
                                        <Icon 
                                            name={suggestion.priority === 'high' ? 'AlertCircle' : 
                                                  suggestion.priority === 'medium' ? 'AlertTriangle' : 'Info'} 
                                            size={18} 
                                        />
                                    </div>
                                    <div className="ts-suggestion-content">
                                        <h4 className="ts-suggestion-title">{suggestion.title}</h4>
                                        {suggestion.metric && (
                                            <div className="ts-suggestion-metric">
                                                <Icon
                                                    name={suggestion.metric.trend === 'up' ? 'TrendingUp' : 
                                                          suggestion.metric.trend === 'down' ? 'TrendingDown' : 'Minus'}
                                                    size={14}
                                                />
                                                <span className="ts-metric-label">{suggestion.metric.label}:</span>
                                                <span className={`ts-metric-value ts-metric-value--${suggestion.metric.trend}`}>
                                                    {suggestion.metric.value}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <div className="ts-suggestion-actions">
                                    <button
                                        className="ts-suggestion-dismiss"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            dismissSuggestion(suggestion.id);
                                        }}
                                        title={__('Dismiss', 'tracksure')}
                                        aria-label={__('Dismiss suggestion', 'tracksure')}
                                    >
                                        <Icon name="X" size={14} />
                                    </button>
                                    <Icon
                                        name={isExpanded ? 'ChevronUp' : 'ChevronDown'}
                                        size={16}
                                        className="ts-suggestion-expand-icon"
                                    />
                                </div>
                            </div>

                            {isExpanded && (
                                <div className="ts-suggestion-body">
                                    <div className="ts-suggestion-description">
                                        <Icon name="Info" size={16} />
                                        <p>{suggestion.description}</p>
                                    </div>
                                    <div className="ts-suggestion-action">
                                        <div className="ts-action-icon">
                                            <Icon name="Zap" size={16} />
                                        </div>
                                        <div className="ts-action-content">
                                            <strong className="ts-action-label">
                                                {__('Recommended Action', 'tracksure')}
                                            </strong>
                                            <p className="ts-action-text">{suggestion.action}</p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {visibleSuggestions.length > 3 && (
                <div className="ts-suggestions-footer">
                    <Icon name="TrendingUp" size={14} />
                    <p className="ts-suggestions-footer-text">
                        {__('Insights refresh every 5 minutes based on your latest data', 'tracksure')}
                    </p>
                </div>
            )}
        </div>
    );
};
