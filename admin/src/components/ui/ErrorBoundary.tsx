/**
 * TrackSure Error Boundary Component
 * 
 * Catches React errors and displays fallback UI.
 */

import React, { Component, ErrorInfo, ReactNode } from 'react';
import { __ } from '../../utils/i18n';
import '../../styles/components/ui/ErrorBoundary.css';

interface ErrorBoundaryProps {
  children: ReactNode;
  fallback?: ReactNode;
}

interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
    };
  }

  static getDerivedStateFromError(_error: Error): Partial<ErrorBoundaryState> {
    return { hasError: true };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('TrackSure Error:', error, errorInfo);
    this.setState({
      error,
      errorInfo,
    });
  }

  handleReset = () => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    });
  };

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) {
        return this.props.fallback;
      }

      return (
        <div className="ts-error-boundary">
          <div className="ts-error-boundary__content">
            <svg className="ts-error-boundary__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="12" cy="12" r="10" strokeWidth={2} />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01" />
            </svg>
            <h2 className="ts-error-boundary__title">{__("Something went wrong")}</h2>
            <p className="ts-error-boundary__message">
              {this.state.error?.message || __("An unexpected error occurred")}
            </p>
            {process.env.NODE_ENV === 'development' && this.state.errorInfo && (
              <details className="ts-error-boundary__details">
                <summary>{__("Error Details")}</summary>
                <pre className="ts-error-boundary__stack">
                  {this.state.error?.stack}
                  {this.state.errorInfo.componentStack}
                </pre>
              </details>
            )}
            <button
              className="ts-error-boundary__button"
              onClick={this.handleReset}
              type="button"
            >
              {__("Try Again")}
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
