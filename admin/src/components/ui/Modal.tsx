import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import '../../styles/components/ui/Modal.css';

export interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title?: string | React.ReactNode;
  children: React.ReactNode;
  size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
  closeOnOverlayClick?: boolean;
  showCloseButton?: boolean;
  footer?: React.ReactNode;
}

export const Modal: React.FC<ModalProps> = ({
  isOpen,
  onClose,
  title,
  children,
  size = 'md',
  closeOnOverlayClick = true,
  showCloseButton = true,
  footer,
}) => {
  const overlayRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen, onClose]);

  if (!isOpen) {return null;}

  const handleOverlayClick = (e: React.MouseEvent) => {
    if (closeOnOverlayClick && e.target === overlayRef.current) {
      onClose();
    }
  };

  const modalClasses = ['ts-modal__content', `ts-modal__content--${size}`].join(' ');

  return createPortal(
    <div className="ts-modal" ref={overlayRef} onClick={handleOverlayClick}>
      <div className={modalClasses}>
        {(title || showCloseButton) && (
          <div className="ts-modal__header">
            {title && (
              typeof title === 'string' ? (
                <h2 className="ts-modal__title">{title}</h2>
              ) : (
                <div className="ts-modal__title">{title}</div>
              )
            )}
            {showCloseButton && (
              <button
                className="ts-modal__close"
                onClick={onClose}
                aria-label="Close modal"
              >
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                  <path
                    d="M15 5L5 15M5 5l10 10"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                </svg>
              </button>
            )}
          </div>
        )}

        <div className="ts-modal__body">{children}</div>

        {footer && <div className="ts-modal__footer">{footer}</div>}
      </div>
    </div>,
    document.body
  );
};
