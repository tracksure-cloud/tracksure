import React from 'react';
import '../../styles/components/ui/Button.css';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  fullWidth?: boolean;
  icon?: React.ReactNode;
  iconPosition?: 'left' | 'right';
}

export const Button: React.FC<ButtonProps> = ({
  variant = 'primary',
  size = 'md',
  loading = false,
  fullWidth = false,
  icon,
  iconPosition = 'left',
  className = '',
  children,
  disabled,
  ...props
}) => {
  const classes = [
    'ts-button',
    `ts-button--${variant}`,
    `ts-button--${size}`,
    fullWidth && 'ts-button--full',
    loading && 'ts-button--loading',
    disabled && 'ts-button--disabled',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <button className={classes} disabled={disabled || loading} {...props}>
      {loading && (
        <span className="ts-button__spinner">
          <svg className="ts-spinner" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" fill="none" strokeWidth="3" />
          </svg>
        </span>
      )}
      {!loading && icon && iconPosition === 'left' && (
        <span className="ts-button__icon ts-button__icon--left">{icon}</span>
      )}
      <span className="ts-button__content">{children}</span>
      {!loading && icon && iconPosition === 'right' && (
        <span className="ts-button__icon ts-button__icon--right">{icon}</span>
      )}
    </button>
  );
};
