import React from 'react';
import '../../styles/components/ui/Card.css';

export interface CardProps {
  children: React.ReactNode;
  className?: string;
  padding?: 'none' | 'sm' | 'md' | 'lg';
  variant?: 'default' | 'bordered' | 'elevated';
  onClick?: () => void;
  hoverable?: boolean;
}

export const Card: React.FC<CardProps> = ({
  children,
  className = '',
  padding = 'md',
  variant = 'default',
  onClick,
  hoverable = false,
}) => {
  const classes = [
    'ts-card',
    `ts-card--${variant}`,
    `ts-card--padding-${padding}`,
    onClick && 'ts-card--clickable',
    hoverable && 'ts-card--hoverable',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={classes} onClick={onClick} role={onClick ? 'button' : undefined}>
      {children}
    </div>
  );
};

export interface CardHeaderProps {
  children: React.ReactNode;
  className?: string;
  action?: React.ReactNode;
}

export const CardHeader: React.FC<CardHeaderProps> = ({ children, className = '', action }) => {
  return (
    <div className={`ts-card__header ${className}`}>
      <div className="ts-card__header-content">{children}</div>
      {action && <div className="ts-card__header-action">{action}</div>}
    </div>
  );
};

export interface CardBodyProps {
  children: React.ReactNode;
  className?: string;
}

export const CardBody: React.FC<CardBodyProps> = ({ children, className = '' }) => {
  return <div className={`ts-card__body ${className}`}>{children}</div>;
};

export interface CardFooterProps {
  children: React.ReactNode;
  className?: string;
}

export const CardFooter: React.FC<CardFooterProps> = ({ children, className = '' }) => {
  return <div className={`ts-card__footer ${className}`}>{children}</div>;
};
