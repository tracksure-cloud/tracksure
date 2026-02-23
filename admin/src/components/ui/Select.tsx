import React, { forwardRef } from 'react';
import '../../styles/components/ui/Select.css';

export interface SelectOption {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
  hint?: string;
  options: SelectOption[];
  placeholder?: string;
  fullWidth?: boolean;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  (
    {
      label,
      error,
      hint,
      options,
      placeholder,
      fullWidth = false,
      className = '',
      id,
      ...props
    },
    ref
  ) => {
    const selectId = id || label?.toLowerCase().replace(/\s+/g, '-');
    const hasError = !!error;

    const wrapperClasses = [
      'ts-select-wrapper',
      fullWidth && 'ts-select-wrapper--full',
      className,
    ]
      .filter(Boolean)
      .join(' ');

    const selectClasses = ['ts-select', hasError && 'ts-select--error']
      .filter(Boolean)
      .join(' ');

    return (
      <div className={wrapperClasses}>
        {label && (
          <label htmlFor={selectId} className="ts-select-label">
            {label}
          </label>
        )}
        <div className="ts-select-container">
          <select ref={ref} id={selectId} className={selectClasses} {...props}>
            {placeholder && (
              <option value="" disabled>
                {placeholder}
              </option>
            )}
            {options.map((option) => (
              <option
                key={option.value}
                value={option.value}
                disabled={option.disabled}
              >
                {option.label}
              </option>
            ))}
          </select>
          <svg
            className="ts-select-arrow"
            width="20"
            height="20"
            viewBox="0 0 20 20"
            fill="none"
          >
            <path
              d="M6 8l4 4 4-4"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </div>
        {error && <span className="ts-select-error">{error}</span>}
        {!error && hint && <span className="ts-select-hint">{hint}</span>}
      </div>
    );
  }
);

Select.displayName = 'Select';
