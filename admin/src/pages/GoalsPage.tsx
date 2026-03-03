/**
 * Goals Page - Simplified Category-based Goal Management
 * 
 * Features:
 * - 4 core categories (engagement, leads, ecommerce, content)
 * - 28 curated goal templates
 * - Template customization
 * - Custom goal builder
 * - Real-time performance metrics
 * - Full i18n support
 * - Dark/Light theme compatible
 * 
 * @since 2.1.0 - Simplified from persona-based to category-based system
 * @package TrackSure
 */

import React, { useState, useMemo } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { TrackSureAPI } from '../utils/api';
import { validateGoal, formatValidationErrors } from '../utils/goalValidation';
import { KPICard } from '../components/ui/KPICard';
import { SkeletonKPI, SkeletonTable } from '../components/ui/Skeleton';
import { Icon } from '../components/ui/Icon';
import type { IconName } from '../components/ui/Icon';
import {
  Button,
  Card,
  CardBody,
  Modal,
  Input,
  Select,
  Badge,
  EmptyState,
} from '../components/ui';
import {
  GOAL_TEMPLATES,
  GOAL_CATEGORIES,
  getTemplatesByCategory,
  searchTemplates,
} from '../data/goalTemplates';
import type { GoalTemplate, GoalCategory, GoalFormData, Goal } from '@/types/goals';
import {
  GoalsOverview,
  GoalDetailsModal,
  GoalModal,
  CustomGoalBuilder,
  GoalBulkActions,
  GoalFilters,
  type GoalFilterState,
  GoalImport,
} from '../components/goals';
import { __ } from '../utils/i18n';
import { formatCurrency, formatLocalDate, getConfigCurrency } from '../utils/parameterFormatters';
import '../styles/pages/GoalsPage.css';

type ViewMode = 'list' | 'templates' | 'custom-builder';
type TabType = 'overview' | 'goals' | 'templates';

interface GoalPerformance {
  conversions: number;
  revenue: number;
  avg_value: number;
  conversion_rate: number;
}

const GoalsPage: React.FC = () => {
  const { config, dateRange } = useApp();
  
  // State
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  const [viewMode, setViewMode] = useState<ViewMode>('list');
  const [selectedCategory, setSelectedCategory] = useState<GoalCategory | 'all'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deletingGoal, setDeletingGoal] = useState<Goal | null>(null);
  const [detailsGoal, setDetailsGoal] = useState<Goal | null>(null);
  const [selectedGoals, setSelectedGoals] = useState<number[]>([]);
  const [showImportModal, setShowImportModal] = useState(false);
  const [filters, setFilters] = useState<GoalFilterState>({
    search: '',
    category: 'all',
    triggerType: 'all',
    status: 'all',
    sortBy: 'name',
    sortOrder: 'asc'
  });
  
  // Unified editor modal state
  const [editorMode, setEditorMode] = useState<'create-from-template' | 'create-custom' | 'edit' | null>(null);
  const [editingTemplate, setEditingTemplate] = useState<GoalTemplate | null>(null);
  const [editingGoalData, setEditingGoalData] = useState<GoalFormData | null>(null);

  // Fetch goals using useApiQuery
  const {
    data: goalsData,
    isLoading,
    error: goalsError,
    refetch: refetchGoals,
  } = useApiQuery<{ goals: Goal[] }>(
    'getGoals',
    {},
    { 
      staleTime: 30000, // 30s — avoid refetch on every tab switch
      retry: 2,
      onError: (err) => console.error('[GoalsPage] Failed to fetch goals:', err)
    }
  );

  // Memoize goals array to prevent unnecessary re-renders
  const goals = useMemo(() => goalsData?.goals || [], [goalsData]);
  
  // Fetch performance data using useApiQuery (conditional on goals existing)
  const goalIds = goals.map((g) => g.goal_id).join(',');
  const params = useMemo(() => ({
    goal_ids: goalIds,
    date_start: formatLocalDate(dateRange.start),
    date_end: formatLocalDate(dateRange.end),
  }), [goalIds, dateRange]);

  const { data: perfData } = useApiQuery<{ performance: Record<number, GoalPerformance> }>(
    'getGoalsPerformance',
    params,
    {
      enabled: goals.length > 0 && goalIds.length > 0,
      staleTime: 30000, // 30s — performance data can be slightly stale
      retry: 2,
      onError: (err) => console.warn('[GoalsPage] Performance data unavailable:', err)
    }
  );

  // Memoize performance data to prevent unnecessary re-renders
  const performance = useMemo(() => perfData?.performance || {}, [perfData]);
  const error = goalsError ? goalsError.message : null;

  const handleSaveGoal = async (goalData: GoalFormData) => {
    try {
      // Validate goal data before API call
      const validation = validateGoal(goalData);
      
      if (!validation.valid) {
        const errorMessage = formatValidationErrors(validation.errors);
        console.error('[GoalsPage] Validation failed:', errorMessage);
        // Show user-friendly error message
        throw new Error('Goal validation failed:\n\n' + errorMessage);
      }

      const api = new TrackSureAPI(config);
      
      if (editorMode === 'edit' && editingGoalData) {
        // Update existing goal
        const goalId = (editingGoalData as Goal).goal_id;
        await api.put(`/goals/${goalId}`, goalData);
      } else {
        // Create new goal
        await api.post('/goals', goalData);
      }

      refetchGoals(); // Refresh goals list
      setEditorMode(null);
      setEditingTemplate(null);
      setEditingGoalData(null);
      setViewMode('list');
    } catch (err) {
      console.error('Failed to save goal:', err);
      throw err; // Re-throw for error handling
    }
  };



  const deleteGoal = async (goal: Goal) => {
    try {
      const api = new TrackSureAPI(config);
      await api.delete(`/goals/${goal.goal_id}`);

      refetchGoals(); // Refresh goals list
      setShowDeleteModal(false);
      setDeletingGoal(null);
    } catch (err) {
      console.error('Failed to delete goal:', err);
    }
  };

  const handleImportGoals = async (goals: GoalFormData[]) => {
    const api = new TrackSureAPI(config);
    
    // Import each goal sequentially
    for (const goalData of goals) {
      await api.post('/goals', goalData);
    }

    refetchGoals(); // Refresh goals list
  };

  const toggleGoalActive = async (goal: Goal) => {
    try {
      const api = new TrackSureAPI(config);
      await api.put(`/goals/${goal.goal_id}`, {
        is_active: !goal.is_active ? 1 : 0,
      });

      refetchGoals(); // Refresh goals list
    } catch (err) {
      console.error('Failed to update goal:', err);
      throw err; // Re-throw for error handling
    }
  };

  // Filter templates based on category and search
  const filteredTemplates = (): GoalTemplate[] => {
    let templates: GoalTemplate[] = [];

    if (selectedCategory === 'all') {
      // Show all templates across all categories
      templates = GOAL_TEMPLATES;
    } else {
      templates = getTemplatesByCategory(selectedCategory);
    }

    if (searchQuery) {
      return searchTemplates(searchQuery);
    }

    return templates;
  };

  // Filter and sort goals based on filters
  const filteredAndSortedGoals = useMemo(() => {
    let filtered = [...goals];

    // Apply search filter
    if (filters.search) {
      const searchLower = filters.search.toLowerCase();
      filtered = filtered.filter(goal =>
        (goal.name && goal.name.toLowerCase().includes(searchLower)) ||
        (goal.description && goal.description.toLowerCase().includes(searchLower))
      );
    }

    // Apply category filter
    if (filters.category !== 'all') {
      filtered = filtered.filter(goal => goal.category === filters.category);
    }

    // Apply trigger type filter
    if (filters.triggerType !== 'all') {
      filtered = filtered.filter(goal => goal.trigger_type === filters.triggerType);
    }

    // Apply status filter
    if (filters.status !== 'all') {
      filtered = filtered.filter(goal => 
        goal.is_active === (filters.status === 'active')
      );
    }

    // Apply sorting
    filtered.sort((a, b) => {
      let comparison = 0;

      switch (filters.sortBy) {
        case 'name':
          comparison = (a.name || '').localeCompare(b.name || '');
          break;
        case 'created_at':
          comparison = new Date(a.created_at.replace(' ', 'T') + 'Z').getTime() - new Date(b.created_at.replace(' ', 'T') + 'Z').getTime();
          break;
        case 'conversions': {
          const perfA = performance[a.goal_id]?.conversions || 0;
          const perfB = performance[b.goal_id]?.conversions || 0;
          comparison = perfA - perfB;
          break;
        }
      }

      return filters.sortOrder === 'asc' ? comparison : -comparison;
    });

    return filtered;
  }, [goals, filters, performance]);

  const resetFilters = () => {
    setFilters({
      search: '',
      category: 'all',
      triggerType: 'all',
      status: 'all',
      sortBy: 'name',
      sortOrder: 'asc'
    });
  };

  // Render functions
  const renderHeader = () => (
    <div className="ts-goals-header">
      <div>
        <h1 className="ts-page-title">{__('Goals', 'tracksure')}</h1>
        <p className="ts-page-subtitle">
          {__('Track conversions and measure what matters most', 'tracksure')}
        </p>
      </div>
      <div className="ts-goals-header-actions">
        {activeTab === 'goals' && viewMode === 'list' && (
          <>
            <Button
              variant="ghost"
              onClick={() => setShowImportModal(true)}
              icon={
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                  <path
                    d="M10 14V6M10 6L7 9M10 6L13 9M4 16h12"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              }
            >
              {__('Import Goals', 'tracksure')}
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                setActiveTab('templates');
                setViewMode('templates');
              }}
              icon={
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                  <path
                    d="M4 6h12M4 10h12M4 14h12"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                </svg>
              }
            >
              {__('Browse Templates', 'tracksure')}
            </Button>
            <Button
              variant="primary"
              onClick={() => setViewMode('custom-builder')}
              icon={
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                  <path
                    d="M10 5v10M5 10h10"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                </svg>
              }
            >
              {__('Create Custom Goal', 'tracksure')}
            </Button>
          </>
        )}
        {viewMode !== 'list' && (
          <Button variant="ghost" onClick={() => {
            setViewMode('list');
            setActiveTab('goals');
          }}>
            {__('← Back to Goals', 'tracksure')}
          </Button>
        )}
      </div>
    </div>
  );

  const renderTabs = () => (
    <div className="ts-goals-tabs">
      <button
        className={`ts-goals-tab ${activeTab === 'overview' ? 'ts-goals-tab--active' : ''}`}
        onClick={() => {
          setActiveTab('overview');
          setViewMode('list');
        }}
      >
        <Icon name="BarChart3" size={18} />
        {__('Overview', 'tracksure')}
      </button>
      <button
        className={`ts-goals-tab ${activeTab === 'goals' ? 'ts-goals-tab--active' : ''}`}
        onClick={() => {
          setActiveTab('goals');
          setViewMode('list');
        }}
      >
        <Icon name="Target" size={18} />
        {__('All Goals', 'tracksure')}
        {goals.length > 0 && (
          <Badge variant="default" size="sm">{goals.length}</Badge>
        )}
      </button>
      <button
        className={`ts-goals-tab ${activeTab === 'templates' ? 'ts-goals-tab--active' : ''}`}
        onClick={() => {
          setActiveTab('templates');
          setViewMode('templates');
        }}
      >
        <Icon name="LayoutTemplate" size={18} />
        {__('Templates', 'tracksure')}
      </button>
    </div>
  );

  const renderGoalsList = () => {
    if (isLoading) {
      return (
        <div className="ts-goals-loading">
          <div className="ts-goals-summary">
            {[1, 2, 3].map((i) => (
              <SkeletonKPI key={i} />
            ))}
          </div>
          <div className="ts-goals-table">
            <SkeletonTable rows={6} columns={5} />
          </div>
        </div>
      );
    }

    // Show empty state only if NO goals exist at all (not filtered)
    if (goals.length === 0) {
      return (
        <div className="ts-empty-state">
          <div className="ts-empty-icon"><Icon name="Target" size={64} color="muted" /></div>
          <h2>{__('No goals yet', 'tracksure')}</h2>
          <p>
            {__(
              'Start tracking conversions by creating your first goal from a template or building a custom one.',
              'tracksure'
            )}
          </p>
          <div style={{ display: 'flex', gap: '12px', justifyContent: 'center', marginTop: '16px' }}>
            <Button
              variant="primary"
              onClick={() => setViewMode('templates')}
            >
              {__('Browse Templates', 'tracksure')}
            </Button>
            <Button
              variant="outline"
              onClick={() => setViewMode('custom-builder')}
            >
              {__('Create Custom Goal', 'tracksure')}
            </Button>
          </div>
        </div>
      );
    }

    // Show filtered empty state if goals exist but filters hide them all
    if (filteredAndSortedGoals.length === 0 && goals.length > 0) {
      return (
        <div style={{ textAlign: 'center', padding: '40px 20px' }}>
          <h2>{__('No Goals Found', 'tracksure')}</h2>
          <p>{__('Try adjusting your filters or search query.', 'tracksure')}</p>
          <Button onClick={resetFilters}>
            {__('Clear Filters', 'tracksure')}
          </Button>
        </div>
      );
    }

    // Calculate summary metrics for filtered goals
    const activeGoals = filteredAndSortedGoals.filter(g => g.is_active);
    const totalAchievements = activeGoals.reduce((sum, goal) => {
      const perf = performance[goal.goal_id];
      return sum + (perf?.conversions || 0);
    }, 0);
    const totalRevenue = activeGoals.reduce((sum, goal) => {
      const perf = performance[goal.goal_id];
      return sum + (perf?.revenue || 0);
    }, 0);
    const goalsWithValue = activeGoals.filter(g => g.value_type !== 'none').length;
    const avgConversionRate = activeGoals.length > 0
      ? activeGoals.reduce((sum, goal) => {
          const perf = performance[goal.goal_id];
          return sum + (perf?.conversion_rate || 0);
        }, 0) / activeGoals.length
      : 0;

    return (
      <>
        {/* Goals Analytics Summary - Row Layout */}
        <div className="ts-goals-summary ts-goals-summary-row">
          <KPICard
            metric={{
              label: __('Total Achievements', 'tracksure'),
              value: totalAchievements,
              format: 'number',
            }}
          />
          <KPICard
            metric={{
              label: __('Active Goals', 'tracksure'),
              value: activeGoals.length,
              format: 'number',
            }}
          />
          <KPICard
            metric={{
              label: totalRevenue > 0 ? __('Total Value', 'tracksure') : __('Goals with Value', 'tracksure'),
              value: totalRevenue > 0 ? totalRevenue : goalsWithValue,
              format: totalRevenue > 0 ? 'currency' : 'number',
              currency: getConfigCurrency(),
            }}
          />
          <KPICard
            metric={{
              label: __('Avg Conversion Rate', 'tracksure'),
              value: avgConversionRate,
              format: 'percent',
            }}
          />
        </div>

        {/* Filters and Bulk Actions */}
        <GoalFilters
          filters={filters}
          onFiltersChange={setFilters}
          onReset={resetFilters}
        />

        <GoalBulkActions
          selectedGoals={selectedGoals}
          allGoals={filteredAndSortedGoals}
          onActionComplete={refetchGoals}
          onSelectionChange={setSelectedGoals}
        />

        {/* Goals Grid */}
        {filteredAndSortedGoals.length === 0 ? (
          <div className="ts-empty-state">
            <div className="ts-empty-icon"><Icon name="Search" size={64} color="muted" /></div>
            <h2>{__('No Goals Found', 'tracksure')}</h2>
            <p>{__('Try adjusting your filters or search query.', 'tracksure')}</p>
            <Button variant="outline" onClick={resetFilters}>
              {__('Clear Filters', 'tracksure')}
            </Button>
          </div>
        ) : (
          <div className="ts-goals-grid">
            {filteredAndSortedGoals.map((goal) => {
              const perf = performance[goal.goal_id];
              const isSelected = selectedGoals.includes(goal.goal_id);
              return (
                <div
                  key={goal.goal_id}
                  className={`ts-goal-card ${isSelected ? 'ts-goal-card-selected' : ''}`}
                >
                  <div className="ts-goal-checkbox">
                    <input
                      type="checkbox"
                      checked={isSelected}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedGoals([...selectedGoals, goal.goal_id]);
                        } else {
                          setSelectedGoals(selectedGoals.filter(id => id !== goal.goal_id));
                        }
                      }}
                    />
                  </div>
                  <div className="ts-goal-header">
                <div className="ts-goal-title-section">
                  <div className="ts-goal-title-row">
                    <h3 className="ts-goal-name">{goal.name}</h3>
                    <span className={`ts-goal-status ${goal.is_active ? 'ts-active' : 'ts-inactive'}`}>
                      {goal.is_active ? __('Active', 'tracksure') : __('Inactive', 'tracksure')}
                    </span>
                  </div>
                  <p className="ts-goal-description">{goal.description}</p>
                </div>
                <div className="ts-goal-actions">
                  <button
                    className="ts-btn-icon"
                    onClick={() => {
                      setEditorMode('edit');
                      setEditingGoalData(goal as GoalFormData);
                    }}
                    title={__('Edit goal', 'tracksure')}
                  >
                    <Icon name="Edit2" size={16} />
                  </button>
                  <button
                    className="ts-btn-icon"
                    onClick={() => toggleGoalActive(goal)}
                    title={goal.is_active ? __('Deactivate', 'tracksure') : __('Activate', 'tracksure')}
                  >
                    <Icon name={goal.is_active ? 'Pause' : 'Play'} size={16} />
                  </button>
                  <button
                    className="ts-btn-icon ts-btn-danger"
                    onClick={() => {
                      setDeletingGoal(goal);
                      setShowDeleteModal(true);
                    }}
                    title={__('Delete goal', 'tracksure')}
                  >
                    <Icon name="Trash2" size={16} color="danger" />
                  </button>
                </div>
              </div>

              <div className="ts-goal-details">
                <div className="ts-goal-meta">
                  <span className="ts-goal-event"><Icon name="BarChart2" size={16} /> {goal.event_name}</span>
                  {goal.conditions.length > 0 && (
                    <span className="ts-goal-conditions">
                      <Icon name="Target" size={16} /> {goal.conditions.length}{' '}
                      {goal.conditions.length === 1
                        ? __('condition', 'tracksure')
                        : __('conditions', 'tracksure')}
                    </span>
                  )}
                </div>
              </div>

              {perf && (
                <div className="ts-goal-performance">
                  <div className="ts-goal-stat">
                    <span className="ts-goal-stat-label">
                      {__('Conversions', 'tracksure')}
                    </span>
                    <span className="ts-goal-stat-value">
                      {perf.conversions.toLocaleString()}
                    </span>
                  </div>
                  {goal.value_type !== 'none' && (
                    <>
                      <div className="ts-goal-stat">
                        <span className="ts-goal-stat-label">
                          {__('Revenue', 'tracksure')}
                        </span>
                        <span className="ts-goal-stat-value">
                          {formatCurrency(perf.revenue)}
                        </span>
                      </div>
                      <div className="ts-goal-stat">
                        <span className="ts-goal-stat-label">
                          {__('Avg. Value', 'tracksure')}
                        </span>
                        <span className="ts-goal-stat-value">
                          {formatCurrency(perf.avg_value)}
                        </span>
                      </div>
                    </>
                  )}
                  <div className="ts-goal-stat">
                    <span className="ts-goal-stat-label">
                      {__('Conv. Rate', 'tracksure')}
                    </span>
                    <span className="ts-goal-stat-value">
                      {perf.conversion_rate.toFixed(2)}%
                    </span>
                  </div>
                </div>
              )}
              
              {/* Show recent conversions with page URLs */}
              {perf && perf.conversions > 0 && (
                <button 
                  className="ts-goal-view-details"
                  onClick={() => {
                    setDetailsGoal(goal);
                  }}
                >
                  <Icon name="Eye" size={14} />
                  {__('View Details', 'tracksure')}
                </button>
              )}
            </div>
          );
        })}
      </div>
        )}
      </>
    );
  };

  const renderTemplates = () => {
    const templates = filteredTemplates();

    return (
      <div className="ts-templates-view">
        <Card className="ts-templates-filters">
          <CardBody>
            <div className="ts-filters-row">
              <Select
                label={__('Goal Category', 'tracksure')}
                value={selectedCategory}
                onChange={(e) =>
                  setSelectedCategory(e.target.value as GoalCategory | 'all')
                }
                options={[
                  { value: 'all', label: __('All Categories', 'tracksure') },
                  ...Object.values(GOAL_CATEGORIES).map((category) => ({
                    value: category.id,
                    label: category.label,
                  })),
                ]}
                fullWidth
              />
              <Input
                placeholder={__('Search templates...', 'tracksure')}
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                icon={
                  <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                    <path
                      d="M9 17A8 8 0 109 1a8 8 0 000 16zM19 19l-4.35-4.35"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                    />
                  </svg>
                }
                fullWidth
              />
            </div>
          </CardBody>
        </Card>

        <div className="ts-templates-grid">
          {templates.map((template) => (
            <Card
              key={template.id}
              variant="elevated"
              hoverable
              className="ts-template-card"
            >
              <CardBody>
                <div className="ts-template-icon">
                  <Icon name={template.icon as IconName} size={32} color="primary" />
                </div>
                <h3 className="ts-template-name">{template.name}</h3>
                <p className="ts-template-description">{template.description}</p>
                <div className="ts-template-meta">
                  <Badge variant="default" size="sm">
                    {template.event_name}
                  </Badge>
                  <Badge
                    variant={
                      template.category === 'leads'
                        ? 'success'
                        : template.category === 'ecommerce'
                        ? 'info'
                        : 'default'
                    }
                    size="sm"
                  >
                    {template.category}
                  </Badge>
                </div>
                <Button
                  variant="primary"
                  fullWidth
                  onClick={() => {
                    setEditorMode('create-from-template');
                    setEditingTemplate(template);
                  }}
                  className="ts-template-add-btn"
                >
                  {__('Customize & Add', 'tracksure')}
                </Button>
              </CardBody>
            </Card>
          ))}
        </div>

        {templates.length === 0 && (
          <Card>
            <CardBody>
              <EmptyState
                icon="Search"
                title={__('No templates found', 'tracksure')}
                message={__('Try adjusting your filters or search query.', 'tracksure')}
              />
            </CardBody>
          </Card>
        )}
      </div>
    );
  };

  // Render custom goal builder
  const renderCustomBuilder = () => null; // Handled by modal below

  return (
    <div className="ts-goals-page">
      {renderHeader()}
      {renderTabs()}
      
      {error && (
        <div className="ts-error-banner">
          <Icon name="AlertTriangle" size={20} color="warning" />
          <p>{error}</p>
        </div>
      )}

      {activeTab === 'overview' && <GoalsOverview />}
      {activeTab === 'goals' && (
        <>
          {viewMode === 'list' && renderGoalsList()}
          {viewMode === 'custom-builder' && renderCustomBuilder()}
        </>
      )}
      {activeTab === 'templates' && renderTemplates()}

      {/* Goal Details Modal */}
      {detailsGoal && (
        <GoalDetailsModal
          goal={detailsGoal}
          onClose={() => {
            setDetailsGoal(null);
          }}
        />
      )}

      {/* Unified Goal Modal */}
      {editorMode && (
        <GoalModal
          isOpen={true}
          onClose={() => {
            setEditorMode(null);
            setEditingTemplate(null);
            setEditingGoalData(null);
          }}
          onSave={handleSaveGoal}
          mode={editorMode}
          template={editingTemplate || undefined}
          existingGoal={editingGoalData || undefined}
        />
      )}

      {/* Custom Goal Builder Modal */}
      {viewMode === 'custom-builder' && (
        <CustomGoalBuilder
          isOpen={true}
          onClose={() => setViewMode('list')}
          onSave={(goalData) => {
            handleSaveGoal(goalData);
            setViewMode('list');
          }}
          onSaveAsTemplate={(goalData) => {
            // TODO: Implement save as template functionality
            console.warn('[GoalsPage] Save as template not yet implemented:', goalData.name);
          }}
        />
      )}

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteModal}
        onClose={() => {
          setShowDeleteModal(false);
          setDeletingGoal(null);
        }}
        title={__('Delete Goal', 'tracksure')}
        size="sm"
        footer={
          <>
            <Button
              variant="ghost"
              onClick={() => {
                setShowDeleteModal(false);
                setDeletingGoal(null);
              }}
            >
              {__('Cancel', 'tracksure')}
            </Button>
            <Button
              variant="danger"
              onClick={() => deletingGoal && deleteGoal(deletingGoal)}
            >
              {__('Delete', 'tracksure')}
            </Button>
          </>
        }
      >
        <p>
          {__('Are you sure you want to delete', 'tracksure')}{' '}
          <strong>{deletingGoal?.name}</strong>?
        </p>
        <p style={{ marginTop: '12px', color: 'var(--ts-text-secondary)' }}>
          {__('This action cannot be undone.', 'tracksure')}
        </p>
      </Modal>

      {/* Import Modal */}
      {showImportModal && (
        <div className="ts-modal-overlay" onClick={() => setShowImportModal(false)}>
          <div onClick={(e) => e.stopPropagation()}>
            <GoalImport
              onImport={handleImportGoals}
              onClose={() => setShowImportModal(false)}
            />
          </div>
        </div>
      )}
    </div>
  );
};

export default GoalsPage;
