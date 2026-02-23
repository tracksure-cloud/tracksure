import React, { useState, useRef } from 'react';
import { __ } from '../../../../utils/i18n';
import type { GoalFormData } from '@/types/goals';
import './GoalImport.css';

interface GoalImportProps {
  onImport: (goals: GoalFormData[]) => void;
  onClose: () => void;
}

interface ImportResult {
  success: boolean;
  imported: number;
  skipped: number;
  errors: string[];
}

export const GoalImport: React.FC<GoalImportProps> = ({ onImport, onClose }) => {
  const [isDragging, setIsDragging] = useState(false);
  const [importing, setImporting] = useState(false);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [previewGoals, setPreviewGoals] = useState<GoalFormData[]>([]);
  const [showPreview, setShowPreview] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const validateImportData = (data: unknown): { valid: boolean; errors: string[] } => {
    const errors: string[] = [];

    if (!data || typeof data !== 'object') {
      errors.push(__('Invalid JSON format', 'tracksure'));
      return { valid: false, errors };
    }

    const importData = data as Record<string, unknown>;

    if (!importData.goals || !Array.isArray(importData.goals)) {
      errors.push(__('No goals array found in import file', 'tracksure'));
      return { valid: false, errors };
    }

    if (importData.goals.length === 0) {
      errors.push(__('Import file contains no goals', 'tracksure'));
      return { valid: false, errors };
    }

    // Validate each goal has required fields
    (importData.goals as Array<Record<string, unknown>>).forEach((goal: Record<string, unknown>, index: number) => {
      if (!goal.name) {errors.push(__(`Goal ${index + 1}: Missing name`, 'tracksure'));}
      if (!goal.category) {errors.push(__(`Goal ${index + 1}: Missing category`, 'tracksure'));}
      if (!goal.trigger_type) {errors.push(__(`Goal ${index + 1}: Missing trigger type`, 'tracksure'));}
    });

    return { valid: errors.length === 0, errors };
  };

  const parseFile = async (file: File): Promise<void> => {
    try {
      const text = await file.text();
      const data = JSON.parse(text);
      
      const validation = validateImportData(data);
      
      if (!validation.valid) {
        setResult({
          success: false,
          imported: 0,
          skipped: 0,
          errors: validation.errors
        });
        return;
      }

      // Map imported data to GoalFormData format
      const goals: GoalFormData[] = (data as { goals: Array<Record<string, unknown>> }).goals.map((goal: Record<string, unknown>) => ({
        name: String(goal.name || ''),
        description: String(goal.description || ''),
        event_name: String(goal.event_name || goal.trigger_type || ''),
        trigger_type: (goal.trigger_type || 'custom_event') as GoalFormData['trigger_type'],
        category: (goal.category || undefined) as GoalFormData['category'],
        conditions: (Array.isArray(goal.conditions) ? goal.conditions : []) as GoalFormData['conditions'],
        match_logic: (goal.match_logic as GoalFormData['match_logic']) || 'all',
        value_type: (goal.value_type as GoalFormData['value_type']) || 'none',
        value: typeof goal.conversion_value === 'number' ? goal.conversion_value : undefined,
        frequency: String(goal.frequency || 'once'),
        is_active: goal.is_active !== undefined ? Boolean(goal.is_active) : true,
      }));

      setPreviewGoals(goals);
      setShowPreview(true);

    } catch (error) {
      setResult({
        success: false,
        imported: 0,
        skipped: 0,
        errors: [__('Failed to parse JSON file. Please check the file format.', 'tracksure')]
      });
    }
  };

  const handleFileSelect = (file: File) => {
    if (!file.name.endsWith('.json')) {
      setResult({
        success: false,
        imported: 0,
        skipped: 0,
        errors: [__('Please select a JSON file', 'tracksure')]
      });
      return;
    }

    parseFile(file);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);

    const files = Array.from(e.dataTransfer.files);
    if (files.length > 0) {
      handleFileSelect(files[0]);
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      handleFileSelect(files[0]);
    }
  };

  const handleConfirmImport = async () => {
    setImporting(true);

    try {
      await onImport(previewGoals);
      
      setResult({
        success: true,
        imported: previewGoals.length,
        skipped: 0,
        errors: []
      });
      
      setShowPreview(false);
      
      // Close modal after brief delay
      setTimeout(() => {
        onClose();
      }, 2000);
      
    } catch (error) {
      setResult({
        success: false,
        imported: 0,
        skipped: previewGoals.length,
        errors: [__('Failed to import goals. Please try again.', 'tracksure')]
      });
    } finally {
      setImporting(false);
    }
  };

  return (
    <div className="goal-import-modal" role="dialog" aria-modal="true" aria-labelledby="import-title">
      <div className="goal-import-header">
        <h2 id="import-title">{__('Import Goals', 'tracksure')}</h2>
        <button
          className="goal-import-close"
          onClick={onClose}
          aria-label={__('Close import dialog', 'tracksure')}
        >
          <span className="dashicons dashicons-no-alt" aria-hidden="true"></span>
        </button>
      </div>

      <div className="goal-import-body">
        {!showPreview && !result && (
          <div
            className={`goal-import-dropzone ${isDragging ? 'dragging' : ''}`}
            onDrop={handleDrop}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onClick={() => fileInputRef.current?.click()}
          >
            <span className="dashicons dashicons-upload" aria-hidden="true"></span>
            <h3>{__('Drop JSON file here', 'tracksure')}</h3>
            <p>{__('or click to browse files', 'tracksure')}</p>
            <input
              ref={fileInputRef}
              type="file"
              accept=".json"
              onChange={handleFileInputChange}
              style={{ display: 'none' }}
              aria-label={__('Select JSON file', 'tracksure')}
            />
          </div>
        )}

        {showPreview && (
          <div className="goal-import-preview">
            <h3>{__('Preview Import', 'tracksure')}</h3>
            <p>
              {__(`${previewGoals.length} goal(s) will be imported:`, 'tracksure')}
            </p>
            <div className="preview-list">
              {previewGoals.map((goal, index) => (
                <div key={index} className="preview-item">
                  <div className="preview-item-header">
                    <strong>{goal.name}</strong>
                    <span className="preview-badge">{goal.category}</span>
                  </div>
                  <p className="preview-description">{goal.description}</p>
                  <div className="preview-meta">
                    <span>{goal.trigger_type.replace(/_/g, ' ')}</span>
                    {goal.conditions.length > 0 && (
                      <span>{goal.conditions.length} conditions</span>
                    )}
                  </div>
                </div>
              ))}
            </div>
            <div className="goal-import-actions">
              <button
                className="button"
                onClick={() => {
                  setShowPreview(false);
                  setPreviewGoals([]);
                }}
                disabled={importing}
              >
                {__('Cancel', 'tracksure')}
              </button>
              <button
                className="button button-primary"
                onClick={handleConfirmImport}
                disabled={importing}
              >
                {importing
                  ? __('Importing...', 'tracksure')
                  : __(`Import ${previewGoals.length} Goal(s)`, 'tracksure')}
              </button>
            </div>
          </div>
        )}

        {result && (
          <div className={`goal-import-result ${result.success ? 'success' : 'error'}`}>
            <span
              className={`dashicons ${
                result.success ? 'dashicons-yes-alt' : 'dashicons-warning'
              }`}
              aria-hidden="true"
            ></span>
            <h3>
              {result.success
                ? __('Import Successful!', 'tracksure')
                : __('Import Failed', 'tracksure')}
            </h3>
            {result.success ? (
              <p>{__(`Successfully imported ${result.imported} goal(s)`, 'tracksure')}</p>
            ) : (
              <div className="error-list">
                {result.errors.map((error, index) => (
                  <p key={index} className="error-message">
                    {error}
                  </p>
                ))}
              </div>
            )}
            <button className="button button-primary" onClick={onClose}>
              {__('Close', 'tracksure')}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};
