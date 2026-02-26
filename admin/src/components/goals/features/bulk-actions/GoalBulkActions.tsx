import React, { useState, useEffect } from 'react';
import { __ } from '../../../../utils/i18n';
import type { Goal } from '@/types/goals';
import './GoalBulkActions.css';

interface GoalBulkActionsProps {
  selectedGoals: number[];
  allGoals: Goal[];
  onActionComplete: () => void;
  onSelectionChange: (selectedIds: number[]) => void;
}

export const GoalBulkActions: React.FC<GoalBulkActionsProps> = ({
  selectedGoals,
  allGoals,
  onActionComplete,
  onSelectionChange
}) => {
  const [isProcessing, setIsProcessing] = useState(false);
  const [showConfirmDialog, setShowConfirmDialog] = useState<'delete' | 'disable' | null>(null);

  // Handle keyboard navigation (Escape key to close dialog)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && showConfirmDialog) {
        setShowConfirmDialog(null);
      }
    };

    if (showConfirmDialog) {
      document.addEventListener('keydown', handleKeyDown);
      return () => document.removeEventListener('keydown', handleKeyDown);
    }
  }, [showConfirmDialog]);

  const handleBulkAction = async (action: 'enable' | 'disable' | 'delete' | 'export') => {
    if (selectedGoals.length === 0) {
      alert(__('Please select at least one goal.', 'tracksure'));
      return;
    }

    // Show confirmation for destructive actions
    if (action === 'delete') {
      setShowConfirmDialog('delete');
      return;
    } else if (action === 'disable') {
      setShowConfirmDialog('disable');
      return;
    }

    setIsProcessing(true);

    try {
      if (action === 'export') {
        await handleBulkExport();
      } else {
        await handleBulkUpdate(action);
      }
    } catch (error) {
      console.error('Bulk action failed:', error);
      alert(__('Failed to perform bulk action. Please try again.', 'tracksure'));
    } finally {
      setIsProcessing(false);
    }
  };

  const handleBulkUpdate = async (action: 'enable' | 'disable') => {
    const is_active = action === 'enable' ? 1 : 0;
    
    const promises = selectedGoals.map(goalId =>
      window.wp.apiFetch({
        path: `/ts/v1/goals/${goalId}`,
        method: 'POST',
        data: { is_active }
      })
    );

    await Promise.all(promises);
    onSelectionChange([]);
    onActionComplete();
    
    alert(
      selectedGoals.length === 1
        ? __('Goal updated successfully.', 'tracksure')
        : __(`${selectedGoals.length} goals updated successfully.`, 'tracksure')
    );
  };

  const handleBulkDelete = async () => {
    setShowConfirmDialog(null);
    setIsProcessing(true);

    try {
      const promises = selectedGoals.map(goalId =>
        window.wp.apiFetch({
          path: `/ts/v1/goals/${goalId}`,
          method: 'DELETE'
        })
      );

      await Promise.all(promises);
      onSelectionChange([]);
      onActionComplete();
      
      alert(
        selectedGoals.length === 1
          ? __('Goal deleted successfully.', 'tracksure')
          : __(`${selectedGoals.length} goals deleted successfully.`, 'tracksure')
      );
    } catch (error) {
      console.error('Bulk delete failed:', error);
      alert(__('Failed to delete goals. Please try again.', 'tracksure'));
    } finally {
      setIsProcessing(false);
    }
  };

  const handleBulkExport = async () => {
    const selectedGoalObjects = allGoals.filter(goal => 
      selectedGoals.includes(goal.goal_id)
    );

    const exportData = {
      version: '2.1.0',
      exported_at: new Date().toISOString(),
      goals: selectedGoalObjects.map(goal => ({
        name: goal.name,
        description: goal.description,
        category: goal.category,
        trigger_type: goal.trigger_type,
        event_name: goal.event_name,
        conditions: goal.conditions,
        match_logic: goal.match_logic,
        value_type: goal.value_type,
        value: goal.value,
        attribution_window: goal.attribution_window,
        frequency: goal.frequency,
        is_active: goal.is_active
      }))
    };

    const blob = new Blob([JSON.stringify(exportData, null, 2)], {
      type: 'application/json'
    });
    
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `tracksure-goals-${Date.now()}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    alert(
      selectedGoals.length === 1
        ? __('Goal exported successfully.', 'tracksure')
        : __(`${selectedGoals.length} goals exported successfully.`, 'tracksure')
    );
    
    onSelectionChange([]);
  };

  const handleSelectAll = () => {
    if (selectedGoals.length === allGoals.length) {
      onSelectionChange([]);
    } else {
      onSelectionChange(allGoals.map(goal => goal.goal_id));
    }
  };

  const allSelected = selectedGoals.length === allGoals.length && allGoals.length > 0;
  const someSelected = selectedGoals.length > 0 && selectedGoals.length < allGoals.length;

  return (
    <>
      <div className="goal-bulk-actions" role="toolbar" aria-label={__('Bulk actions for goals', 'tracksure')}>
        <div className="bulk-actions-left">
          <label className="bulk-select-all">
            <input
              type="checkbox"
              checked={allSelected}
              ref={input => {
                if (input) {
                  input.indeterminate = someSelected;
                }
              }}
              onChange={handleSelectAll}
              disabled={allGoals.length === 0}
              aria-label={
                allSelected
                  ? __('Deselect all goals', 'tracksure')
                  : __('Select all goals', 'tracksure')
              }
            />
            <span>
              {selectedGoals.length > 0
                ? __(`${selectedGoals.length} selected`, 'tracksure')
                : __('Select all', 'tracksure')}
            </span>
          </label>
        </div>

        {selectedGoals.length > 0 && (
          <div className="bulk-actions-right" role="group" aria-label={__('Available bulk actions', 'tracksure')}>
            <button
              className="bulk-action-btn"
              onClick={() => handleBulkAction('enable')}
              disabled={isProcessing}
              aria-label={__('Enable selected goals', 'tracksure')}
            >
              <span className="dashicons dashicons-yes-alt" aria-hidden="true"></span>
              {__('Enable', 'tracksure')}
            </button>
            
            <button
              className="bulk-action-btn"
              onClick={() => handleBulkAction('disable')}
              disabled={isProcessing}
              aria-label={__('Disable selected goals', 'tracksure')}
            >
              <span className="dashicons dashicons-dismiss" aria-hidden="true"></span>
              {__('Disable', 'tracksure')}
            </button>
            
            <button
              className="bulk-action-btn"
              onClick={() => handleBulkAction('export')}
              disabled={isProcessing}
              aria-label={__('Export selected goals', 'tracksure')}
            >
              <span className="dashicons dashicons-download" aria-hidden="true"></span>
              {__('Export', 'tracksure')}
            </button>
            
            <button
              className="bulk-action-btn bulk-delete"
              onClick={() => handleBulkAction('delete')}
              disabled={isProcessing}
              aria-label={__('Delete selected goals', 'tracksure')}
            >
              <span className="dashicons dashicons-trash" aria-hidden="true"></span>
              {__('Delete', 'tracksure')}
            </button>
          </div>
        )}
      </div>

      {showConfirmDialog && (
        <div
          className="bulk-confirm-dialog-overlay"
          onClick={() => setShowConfirmDialog(null)}
          role="dialog"
          aria-modal="true"
          aria-labelledby="bulk-confirm-title"
        >
          <div className="bulk-confirm-dialog" onClick={e => e.stopPropagation()}>
            <h3 id="bulk-confirm-title">
              {showConfirmDialog === 'delete'
                ? __('Confirm Deletion', 'tracksure')
                : __('Confirm Disable', 'tracksure')}
            </h3>
            <p>
              {showConfirmDialog === 'delete'
                ? selectedGoals.length === 1
                  ? __('Are you sure you want to delete this goal? This action cannot be undone.', 'tracksure')
                  : `${__('Are you sure you want to delete', 'tracksure')} ${selectedGoals.length} ${__('goals? This action cannot be undone.', 'tracksure')}`
                : selectedGoals.length === 1
                  ? __('Are you sure you want to disable this goal?', 'tracksure')
                  : `${__('Are you sure you want to disable', 'tracksure')} ${selectedGoals.length} ${__('goals?', 'tracksure')}`}
            </p>
            <div className="bulk-confirm-actions">
              <button
                className="button"
                onClick={() => setShowConfirmDialog(null)}
              >
                {__('Cancel', 'tracksure')}
              </button>
              <button
                className="button button-primary"
                onClick={showConfirmDialog === 'delete' ? handleBulkDelete : () => {
                  setShowConfirmDialog(null);
                  handleBulkUpdate('disable');
                }}
                autoFocus
              >
                {__('Confirm', 'tracksure')}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};
