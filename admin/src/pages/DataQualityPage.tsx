import React, { useMemo } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { __ } from '@wordpress/i18n';
import { Icon, type IconName } from '../components/ui/Icon';
import { ICON_REGISTRY } from '../config/iconRegistry';
import { SkeletonKPI, SkeletonChart } from '../components/ui/Skeleton';
import { getEnabledDestinationsMeta } from '../config/destinationRegistry';
import { formatLocalDate } from '../utils/parameterFormatters';
import '../styles/pages/DataQualityPage.css';

interface SignalQuality {
    destination: string;
    quality_score: number;
    dedup_rate: number;
    server_side_coverage: number;
    missing_params_rate: number;
    delivery_success_rate: number;
    match_quality: 'excellent' | 'good' | 'needs_improvement';
    last_7_days_events: number;
    recommendations: string[];
}

interface DeduplicationStats {
    total_events: number;
    unique_events: number;
    duplicate_events: number;
    dedup_rate: number;
    by_event_type: Array<{
        event_name: string;
        total: number;
        duplicates: number;
        dedup_rate: number;
    }>;
}

interface SchemaValidation {
    event_name: string;
    total_events: number;
    valid_events: number;
    invalid_events: number;
    validation_rate: number;
    missing_params: string[];
}

interface ReconciliationData {
    tracksure_count: number;
    meta_count: number;
    ga4_count: number;
    meta_diff: number;
    ga4_diff: number;
    meta_diff_percent: number;
    ga4_diff_percent: number;
    explanations: string[];
}

const DataQualityPage: React.FC = () => {
    const { dateRange, config } = useApp();

    // Get enabled destinations from config (set by server-side Destinations Manager)
    const enabledDestinations = useMemo(
        () => config?.enabledDestinations || [],
        [config]
    );
    
    // Get destination metadata (names, icons, order) - supports all registered destinations
    const enabledDestinationsMeta = useMemo(
        () => getEnabledDestinationsMeta(enabledDestinations),
        [enabledDestinations]
    );

    // Fetch quality data for all enabled destinations
    // Note: This uses a single API call instead of per-destination calls to avoid hooks violation
    const { data: _qualitySignals, isLoading: loadingSignals } = useApiQuery<{ signals: SignalQuality[] }>(
        'getQualitySignal',
        {
            destinations: enabledDestinations.join(','),
            date_start: formatLocalDate(dateRange.start),
            date_end: formatLocalDate(dateRange.end),
        },
        { refetchInterval: 300000 }
    );

    const { data: deduplication, isLoading: loadingDedup } = useApiQuery<DeduplicationStats>(
        'getQualityDeduplication',
        {
            date_start: formatLocalDate(dateRange.start),
            date_end: formatLocalDate(dateRange.end),
        },
        { refetchInterval: 300000 }
    );

    const { data: schemaValidation, isLoading: loadingSchema } = useApiQuery<SchemaValidation[]>(
        'getQualitySchema',
        {
            date_start: formatLocalDate(dateRange.start),
            date_end: formatLocalDate(dateRange.end),
        },
        { refetchInterval: 300000 }
    );

    const { data: reconciliation, isLoading: loadingRecon } = useApiQuery<ReconciliationData>(
        'getQualityReconciliation',
        {
            date_start: formatLocalDate(dateRange.start),
            date_end: formatLocalDate(dateRange.end),
        },
        { refetchInterval: 300000 }
    );

    // Create destination queries structure for rendering
    // Note: Since we now use a single API call, we create mock query objects
    const destinationQueries = useMemo(() => {
        return enabledDestinationsMeta.map(dest => ({
            dest,
            query: {
                data: null, // Quality signals would come from _qualitySignals if needed
                isLoading: loadingSignals,
            },
        }));
    }, [enabledDestinationsMeta, loadingSignals]);

    const loading = loadingSignals || loadingDedup || loadingSchema || loadingRecon;

    const getQualityColor = (score: number): string => {
        if (score >= 85) {
            return 'excellent';
        }
        if (score >= 70) {
            return 'good';
        }
        return 'needs-improvement';
    };

    const getQualityLabel = (quality: string): string => {
        const labels = {
            excellent: __('Excellent', 'tracksure'),
            good: __('Good', 'tracksure'),
            needs_improvement: __('Needs Improvement', 'tracksure'),
        };
        return labels[quality as keyof typeof labels] || quality;
    };

    const renderQualityGauge = (score: number) => {
        const colorClass = getQualityColor(score);
        const rotation = (score / 100) * 180;

        return (
            <div className="quality-gauge">
                <div className="gauge-container">
                    <div className="gauge-fill" style={{ transform: `rotate(${rotation}deg)` }}></div>
                    <div className="gauge-cover">
                        <div className={`gauge-score ${colorClass}`}>{score}</div>
                        <div className="gauge-label">{__('Quality Score', 'tracksure')}</div>
                    </div>
                </div>
            </div>
        );
    };

    const renderDestinationCard = (quality: SignalQuality | null, destinationName: string, iconName: IconName = 'Target') => {
        if (!quality) {
            return (
                <div className="destination-card" key={destinationName}>
                    <div className="destination-header">
                        <h3>
                            <Icon name={iconName} size={20} className="inline-icon" />
                            {destinationName}
                        </h3>
                    </div>
                    <p className="no-data">{__("No data available", "tracksure")}</p>
                </div>
            );
        }

        return (
            <div className="destination-card" key={destinationName}>
                <div className="destination-header">
                    <h3>
                        <Icon name={iconName} size={20} className="inline-icon" />
                        {destinationName}
                    </h3>
                    <span className={`quality-badge ${quality.match_quality}`}>
                        {getQualityLabel(quality.match_quality)}
                    </span>
                </div>

                {renderQualityGauge(quality.quality_score)}

                <div className="quality-metrics">
                    <div className="metric-row">
                        <span className="metric-label">{__('Deduplication Rate', 'tracksure')}</span>
                        <span className="metric-value">{(quality.dedup_rate || 0).toFixed(1)}%</span>
                    </div>
                    <div className="metric-row">
                        <span className="metric-label">{__('Server-Side Coverage', 'tracksure')}</span>
                        <span className="metric-value">{(quality.server_side_coverage || 0).toFixed(1)}%</span>
                    </div>
                    <div className="metric-row">
                        <span className="metric-label">{__('Missing Parameters', 'tracksure')}</span>
                        <span className="metric-value">{(quality.missing_params_rate || 0).toFixed(1)}%</span>
                    </div>
                    <div className="metric-row">
                        <span className="metric-label">{__('Delivery Success', 'tracksure')}</span>
                        <span className="metric-value">{(quality.delivery_success_rate || 0).toFixed(1)}%</span>
                    </div>
                    <div className="metric-row">
                        <span className="metric-label">{__('Events (7 days)', 'tracksure')}</span>
                        <span className="metric-value">{(quality.last_7_days_events || 0).toLocaleString()}</span>
                    </div>
                </div>

                {quality.recommendations && quality.recommendations.length > 0 && (
                    <div className="recommendations">
                        <h4><Icon name="Lightbulb" size={16} color="warning" /> {__('Recommendations', 'tracksure')}</h4>
                        <ul>
                            {quality.recommendations.map((rec, idx) => (
                                <li key={idx}>{rec}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        );
    };

    if (loading) {
        return (
            <div className="data-quality-page">
                <div className="page-header">
                    <h1>{__('Data Quality & Signal Health', 'tracksure')}</h1>
                </div>
                <div className="quality-grid">
                    {[1, 2, 3].map((i) => (
                        <SkeletonKPI key={i} />
                    ))}
                </div>
                <div className="quality-cards">
                    <SkeletonChart height={200} />
                    <SkeletonChart height={200} />
                </div>
            </div>
        );
    }

    return (
        <div className="data-quality-page">
            <div className="page-header">
                <h1><Icon name={ICON_REGISTRY.goals} size={28} className="inline-icon" /> {__('Tracking Health & Signal Quality', 'tracksure')}</h1>
                <p className="subtitle">
                    {__('Your data quality controls how much ad platforms trust your conversions', 'tracksure')}
                </p>
            </div>

            {/* Signal Quality Scores */}
            {enabledDestinationsMeta.length > 0 && (
                <div className="quality-section">
                    <h2>{__('Signal Quality by Destination', 'tracksure')}</h2>
                    <div className="destination-cards">
                        {destinationQueries.map(({ dest, query }) => 
                            renderDestinationCard(query.data, dest.name, dest.icon)
                        )}
                    </div>
                </div>
            )}

            {/* Deduplication Stats */}
            {deduplication && (
                <div className="quality-section">
                    <h2><Icon name="RefreshCw" size={20} className="inline-icon" /> {__('Event Deduplication', 'tracksure')}</h2>
                    <div className="dedup-summary">
                        <div className="summary-card">
                            <div className="summary-value">{(deduplication.total_events || 0).toLocaleString()}</div>
                            <div className="summary-label">{__('Total Events', 'tracksure')}</div>
                        </div>
                        <div className="summary-card">
                            <div className="summary-value">{(deduplication.unique_events || 0).toLocaleString()}</div>
                            <div className="summary-label">{__('Unique Events', 'tracksure')}</div>
                        </div>
                        <div className="summary-card">
                            <div className="summary-value">{(deduplication.duplicate_events || 0).toLocaleString()}</div>
                            <div className="summary-label">{__('Duplicates Removed', 'tracksure')}</div>
                        </div>
                        <div className="summary-card excellent">
                            <div className="summary-value">{(deduplication.dedup_rate || 0).toFixed(1)}%</div>
                            <div className="summary-label">{__('Dedup Rate', 'tracksure')}</div>
                        </div>
                    </div>

                    {deduplication.by_event_type.length > 0 && (
                        <div className="dedup-table">
                            <h3>{__('By Event Type', 'tracksure')}</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>{__('Event Name', 'tracksure')}</th>
                                        <th>{__('Total', 'tracksure')}</th>
                                        <th>{__('Duplicates', 'tracksure')}</th>
                                        <th>{__('Dedup Rate', 'tracksure')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {deduplication.by_event_type.map((event) => (
                                        <tr key={event.event_name}>
                                            <td>{event.event_name}</td>
                                            <td>{(event.total || 0).toLocaleString()}</td>
                                            <td>{(event.duplicates || 0).toLocaleString()}</td>
                                            <td>
                                                <span className={getQualityColor(event.dedup_rate || 0)}>
                                                    {(event.dedup_rate || 0).toFixed(1)}%
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {/* Schema Validation */}
            {schemaValidation && schemaValidation.length > 0 && (
                <div className="quality-section">
                    <h2><Icon name="Clipboard" size={20} className="inline-icon" /> {__('Event Schema Validation', 'tracksure')}</h2>
                    <p className="section-description">
                        {__('Checks if events contain all required parameters for accurate tracking', 'tracksure')}
                    </p>
                    <table className="schema-table">
                        <thead>
                            <tr>
                                <th>{__('Event Type', 'tracksure')}</th>
                                <th>{__('Total Events', 'tracksure')}</th>
                                <th>{__('Valid', 'tracksure')}</th>
                                <th>{__('Invalid', 'tracksure')}</th>
                                <th>{__('Validation Rate', 'tracksure')}</th>
                                <th>{__('Missing Params', 'tracksure')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {schemaValidation.map((schema) => (
                                <tr key={schema.event_name}>
                                    <td>{schema.event_name}</td>
                                    <td>{(schema.total_events || 0).toLocaleString()}</td>
                                    <td className="text-success">{(schema.valid_events || 0).toLocaleString()}</td>
                                    <td className="text-warning">{(schema.invalid_events || 0).toLocaleString()}</td>
                                    <td>
                                        <span className={getQualityColor(schema.validation_rate || 0)}>
                                            {(schema.validation_rate || 0).toFixed(1)}%
                                        </span>
                                    </td>
                                    <td>
                                        {schema.missing_params.length > 0 ? (
                                            <span className="missing-params">
                                                {schema.missing_params.join(', ')}
                                            </span>
                                        ) : (
                                            <span className="text-success"><Icon name="CheckCircle" size={14} /> {__('All present', 'tracksure')}</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Reconciliation - Why Numbers Don't Match */}
            {reconciliation && enabledDestinationsMeta.length > 0 && (
                <div className="quality-section reconciliation-section">
                    <h2><Icon name="HelpCircle" size={20} className="inline-icon" /> {__('Why Numbers Don\'t Match', 'tracksure')}</h2>
                    <p className="section-description">
                        {__('Compare TrackSure data with ad platforms to understand discrepancies', 'tracksure')}
                    </p>

                    <div className="reconciliation-grid">
                        <div className="platform-count">
                            <Icon name={ICON_REGISTRY.goals} size={40} className="platform-icon" />
                            <div className="platform-name">TrackSure</div>
                            <div className="platform-value">{(reconciliation.tracksure_count || 0).toLocaleString()}</div>
                            <div className="platform-label">{__('Events Tracked', 'tracksure')}</div>
                        </div>

                        {enabledDestinationsMeta.map(dest => {
                            const reconKey = dest.reconciliationKey || dest.id;
                            const countKey = `${reconKey}_count` as keyof ReconciliationData;
                            const diffKey = `${reconKey}_diff` as keyof ReconciliationData;
                            const diffPercentKey = `${reconKey}_diff_percent` as keyof ReconciliationData;
                            
                            // Safe property access with type checking
                            const count = (countKey in reconciliation && typeof reconciliation[countKey] === 'number')
                                ? reconciliation[countKey] as number
                                : 0;
                            const diff = (diffKey in reconciliation && typeof reconciliation[diffKey] === 'number')
                                ? reconciliation[diffKey] as number
                                : 0;
                            const diffPercent = (diffPercentKey in reconciliation && typeof reconciliation[diffPercentKey] === 'number')
                                ? reconciliation[diffPercentKey] as number
                                : 0;
                            
                            return (
                                <div className="platform-count" key={dest.id}>
                                    <Icon name={dest.icon} size={40} className="platform-icon" />
                                    <div className="platform-name">{dest.name}</div>
                                    <div className="platform-value">{count.toLocaleString()}</div>
                                    <div className="platform-label">
                                        {diff > 0 ? '↑' : '↓'}{' '}
                                        {Math.abs(diffPercent).toFixed(1)}% {__('difference', 'tracksure')}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <div className="reconciliation-explanations">
                        <h3>{__('Common Reasons for Discrepancies', 'tracksure')}</h3>
                        <ul>
                            {(reconciliation.explanations || []).map((explanation, idx) => (
                                <li key={idx}>{explanation}</li>
                            ))}
                        </ul>
                    </div>
                </div>
            )}

            {/* Overall Health Summary */}
            {(enabledDestinationsMeta.length > 0 || deduplication) && (
                <div className="quality-section health-summary">
                    <h2><Icon name="Activity" size={20} className="inline-icon" /> {__('Overall Tracking Health', 'tracksure')}</h2>
                    <div className="health-indicators">
                        {destinationQueries.map(({ dest, query }) => (
                            <div 
                                key={dest.id}
                                className={`health-indicator ${query.data && getQualityColor(query.data.quality_score)}`}
                            >
                                <Icon name={dest.icon} size={48} className="indicator-icon" />
                                <div className="indicator-title">{dest.name} Signal Quality</div>
                                <div className="indicator-value">{query.data?.quality_score || 0}/100</div>
                            </div>
                        ))}
                        {deduplication && (
                            <div className={`health-indicator ${getQualityColor(deduplication.dedup_rate || 0)}`}>
                                <Icon name="RefreshCw" size={48} className="indicator-icon" />
                                <div className="indicator-title">Deduplication</div>
                                <div className="indicator-value">{(deduplication?.dedup_rate || 0).toFixed(1)}%</div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};


export default DataQualityPage;