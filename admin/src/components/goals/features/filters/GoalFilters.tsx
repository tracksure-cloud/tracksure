import React, { useState, useEffect } from 'react';
import { __ } from '../../../../utils/i18n';
import type { GoalCategory, TriggerType } from '@/types/goals';
import './GoalFilters.css';

export interface GoalFilterState {
  search: string;
  category: GoalCategory | 'all';
  triggerType: TriggerType | 'all';
  status: 'active' | 'inactive' | 'all';
  sortBy: 'name' | 'created_at' | 'conversions';
  sortOrder: 'asc' | 'desc';
}

interface GoalFiltersProps {
  filters: GoalFilterState;
  onFiltersChange: (filters: GoalFilterState) => void;
  onReset: () => void;
}

export const GoalFilters: React.FC<GoalFiltersProps> = ({
  filters,
  onFiltersChange,
  onReset
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const [searchDebounce, setSearchDebounce] = useState(filters.search);

  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchDebounce !== filters.search) {
        onFiltersChange({ ...filters, search: searchDebounce });
      }
    }, 300);

    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchDebounce]);

  const handleFilterChange = (key: keyof GoalFilterState, value: string | boolean) => {
    onFiltersChange({ ...filters, [key]: value });
  };

  const hasActiveFilters = 
    filters.search !== '' ||
    filters.category !== 'all' ||
    filters.triggerType !== 'all' ||
    filters.status !== 'all' ||
    filters.sortBy !== 'name' ||
    filters.sortOrder !== 'asc';

  return (
    <div className="goal-filters" role="search" aria-label={__('Filter and search goals', 'tracksure')}>
      <div className="filters-header">
        <div className="filters-search">
          <span className="dashicons dashicons-search" aria-hidden="true"></span>
          <input
            type="text"
            placeholder={__('Search goals...', 'tracksure')}
            value={searchDebounce}
            onChange={(e) => setSearchDebounce(e.target.value)}
            className="search-input"
            aria-label={__('Search goals', 'tracksure')}
          />
          {searchDebounce && (
            <button
              className="clear-search"
              onClick={() => setSearchDebounce('')}
              title={__('Clear search', 'tracksure')}
              aria-label={__('Clear search', 'tracksure')}
            >
              <span className="dashicons dashicons-no-alt" aria-hidden="true"></span>
            </button>
          )}
        </div>

        <div className="filters-actions">
          <button
            className={`filters-toggle ${hasActiveFilters ? 'active' : ''}`}
            onClick={() => setIsExpanded(!isExpanded)}
            aria-expanded={isExpanded}
            aria-controls="filter-panel"
          >
            <span className="dashicons dashicons-filter" aria-hidden="true"></span>
            {__('Filters', 'tracksure')}
            {hasActiveFilters && <span className="filter-badge" aria-label={__('Active filters', 'tracksure')}></span>}
          </button>

          {hasActiveFilters && (
            <button
              className="button"
              onClick={onReset}
              aria-label={__('Reset all filters', 'tracksure')}
            >
              {__('Reset', 'tracksure')}
            </button>
          )}
        </div>
      </div>

      {isExpanded && (
        <div className="filters-panel" id="filter-panel">
          <div className="filter-group">
            <label htmlFor="filter-category">{__('Category', 'tracksure')}</label>
            <select
              id="filter-category"
              value={filters.category}
              onChange={(e) => handleFilterChange('category', e.target.value as GoalCategory | 'all')}
            >
              <option value="all">{__('All Categories', 'tracksure')}</option>
              <option value="engagement">{__('Engagement', 'tracksure')}</option>
              <option value="leads">{__('Leads', 'tracksure')}</option>
              <option value="ecommerce">{__('E-commerce', 'tracksure')}</option>
              <option value="content">{__('Content', 'tracksure')}</option>
            </select>
          </div>

          <div className="filter-group">
            <label htmlFor="filter-trigger">{__('Trigger Type', 'tracksure')}</label>
            <select
              id="filter-trigger"
              value={filters.triggerType}
              onChange={(e) => handleFilterChange('triggerType', e.target.value as TriggerType | 'all')}
            >
              <option value="all">{__('All Triggers', 'tracksure')}</option>
              <option value="page_visit">{__('Page Visit', 'tracksure')}</option>
              <option value="element_click">{__('Element Click', 'tracksure')}</option>
              <option value="form_submission">{__('Form Submission', 'tracksure')}</option>
              <option value="scroll_depth">{__('Scroll Depth', 'tracksure')}</option>
              <option value="time_on_page">{__('Time on Page', 'tracksure')}</option>
              <option value="custom_event">{__('Custom Event', 'tracksure')}</option>
            </select>
          </div>

          <div className="filter-group">
            <label htmlFor="filter-status">{__('Status', 'tracksure')}</label>
            <select
              id="filter-status"
              value={filters.status}
              onChange={(e) => handleFilterChange('status', e.target.value as 'active' | 'inactive' | 'all')}
            >
              <option value="all">{__('All Status', 'tracksure')}</option>
              <option value="active">{__('Active', 'tracksure')}</option>
              <option value="inactive">{__('Inactive', 'tracksure')}</option>
            </select>
          </div>

          <div className="filter-group">
            <label htmlFor="filter-sortby">{__('Sort By', 'tracksure')}</label>
            <select
              id="filter-sortby"
              value={filters.sortBy}
              onChange={(e) => handleFilterChange('sortBy', e.target.value as 'name' | 'created_at' | 'conversions')}
            >
              <option value="name">{__('Name', 'tracksure')}</option>
              <option value="created_at">{__('Date Created', 'tracksure')}</option>
              <option value="conversions">{__('Conversions', 'tracksure')}</option>
            </select>
          </div>

          <div className="filter-group">
            <label htmlFor="filter-order">{__('Order', 'tracksure')}</label>
            <select
              id="filter-order"
              value={filters.sortOrder}
              onChange={(e) => handleFilterChange('sortOrder', e.target.value as 'asc' | 'desc')}
            >
              <option value="asc">{__('Ascending', 'tracksure')}</option>
              <option value="desc">{__('Descending', 'tracksure')}</option>
            </select>
          </div>
        </div>
      )}
    </div>
  );
};
