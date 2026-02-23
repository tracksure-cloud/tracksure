import React, { forwardRef } from 'react';
import '../../styles/components/ui/Input.css';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  hint?: string;
  icon?: React.ReactNode;
  iconPosition?: 'left' | 'right';
  fullWidth?: boolean;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  (
    {
      label,
      error,
      hint,
      icon,
      iconPosition = 'left',
      fullWidth = false,
      className = '',
      id,
      ...props
    },
    ref
  ) => {
    const inputId = id || label?.toLowerCase().replace(/\s+/g, '-');
    const hasError = !!error;

    const wrapperClasses = [
      'ts-input-wrapper',
      fullWidth && 'ts-input-wrapper--full',
      className,
    ]
      .filter(Boolean)
      .join(' ');

    const inputClasses = [
      'ts-input',
      hasError && 'ts-input--error',
      icon && `ts-input--icon-${iconPosition}`,
    ]
      .filter(Boolean)
      .join(' ');

    return (
      <div className={wrapperClasses}>
        {label && (
          <label htmlFor={inputId} className="ts-input-label">
            {label}
          </label>
        )}
        <div className="ts-input-container">
          {icon && iconPosition === 'left' && (
            <span className="ts-input-icon ts-input-icon--left">{icon}</span>
          )}
          <input ref={ref} id={inputId} className={inputClasses} {...props} />
          {icon && iconPosition === 'right' && (
            <span className="ts-input-icon ts-input-icon--right">{icon}</span>
          )}
        </div>
        {error && <span className="ts-input-error">{error}</span>}
        {!error && hint && <span className="ts-input-hint">{hint}</span>}
      </div>
    );
  }
);

Input.displayName = 'Input';
