import React, { forwardRef } from 'react';
import '../../styles/components/ui/Checkbox.css';

export interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string;
  error?: string;
  hint?: string;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  ({ label, error, hint, className = '', id, ...props }, ref) => {
    const checkboxId = id || label?.toLowerCase().replace(/\s+/g, '-');
    const hasError = !!error;

    const wrapperClasses = ['ts-checkbox-wrapper', className].filter(Boolean).join(' ');

    const checkboxClasses = ['ts-checkbox', hasError && 'ts-checkbox--error']
      .filter(Boolean)
      .join(' ');

    return (
      <div className={wrapperClasses}>
        <div className="ts-checkbox-container">
          <input
            ref={ref}
            type="checkbox"
            id={checkboxId}
            className={checkboxClasses}
            {...props}
          />
          {label && (
            <label htmlFor={checkboxId} className="ts-checkbox-label">
              {label}
            </label>
          )}
        </div>
        {error && <span className="ts-checkbox-error">{error}</span>}
        {!error && hint && <span className="ts-checkbox-hint">{hint}</span>}
      </div>
    );
  }
);

Checkbox.displayName = 'Checkbox';
