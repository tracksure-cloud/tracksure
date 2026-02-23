/**
 * UI Context - Very frequently changing UI state
 * 
 * Separated from AppContext for optimal performance.
 * Only components that need loading state subscribe to this context.
 */

import React, { createContext, useContext, useState, ReactNode } from 'react';

interface UIContextValue {
  isLoading: boolean;
  setLoading: (loading: boolean) => void;
}

const UIContext = createContext<UIContextValue | undefined>(undefined);

export const useUI = () => {
  const context = useContext(UIContext);
  if (!context) {
    throw new Error('useUI must be used within UIProvider');
  }
  return context;
};

interface UIProviderProps {
  children: ReactNode;
}

export const UIProvider: React.FC<UIProviderProps> = ({ children }) => {
  const [isLoading, setLoading] = useState(false);

  return (
    <UIContext.Provider value={{ isLoading, setLoading }}>
      {children}
    </UIContext.Provider>
  );
};
