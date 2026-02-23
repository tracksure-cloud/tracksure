/**
 * useVirtualized Hook
 * 
 * Lightweight virtualization for large lists without external dependencies.
 * Renders only visible items using IntersectionObserver.
 * 
 * @package TrackSure\Admin
 * @since 2.0.0
 */

import { useState, useEffect, useRef, useMemo } from 'react';

export interface VirtualizedOptions {
  /**
   * Total number of items in the list
   */
  itemCount: number;
  
  /**
   * Estimated height of each item in pixels
   */
  itemHeight: number;
  
  /**
   * Number of items to render outside the visible area (default: 5)
   */
  overscan?: number;
  
  /**
   * Enable virtualization only for lists larger than this threshold (default: 50)
   */
  threshold?: number;
}

export interface VirtualizedResult {
  /**
   * Array of item indices to render
   */
  virtualItems: number[];
  
  /**
   * Total height of the container
   */
  totalHeight: number;
  
  /**
   * Ref to attach to the scrollable container
   */
  containerRef: React.RefObject<HTMLDivElement>;
  
  /**
   * Offset for positioning the visible items
   */
  offsetY: number;
  
  /**
   * Whether virtualization is enabled
   */
  isVirtualized: boolean;
}

/**
 * Hook for virtualizing large lists
 */
export function useVirtualized({
  itemCount,
  itemHeight,
  overscan = 5,
  threshold = 50,
}: VirtualizedOptions): VirtualizedResult {
  const containerRef = useRef<HTMLDivElement>(null);
  const [scrollTop, setScrollTop] = useState(0);
  const [containerHeight, setContainerHeight] = useState(0);
  
  // Only enable virtualization for large lists
  const isVirtualized = itemCount > threshold;
  
  // Calculate visible range
  const { startIndex, endIndex, offsetY } = useMemo(() => {
    if (!isVirtualized) {
      return {
        startIndex: 0,
        endIndex: itemCount - 1,
        offsetY: 0,
      };
    }
    
    const start = Math.max(0, Math.floor(scrollTop / itemHeight) - overscan);
    const visibleCount = Math.ceil(containerHeight / itemHeight);
    const end = Math.min(itemCount - 1, start + visibleCount + overscan * 2);
    
    return {
      startIndex: start,
      endIndex: end,
      offsetY: start * itemHeight,
    };
  }, [scrollTop, containerHeight, itemCount, itemHeight, overscan, isVirtualized]);
  
  // Generate array of visible indices
  const virtualItems = useMemo(() => {
    const items: number[] = [];
    for (let i = startIndex; i <= endIndex; i++) {
      items.push(i);
    }
    return items;
  }, [startIndex, endIndex]);
  
  // Handle scroll events
  useEffect(() => {
    const container = containerRef.current;
    if (!container || !isVirtualized) {return;}
    
    const handleScroll = () => {
      setScrollTop(container.scrollTop);
    };
    
    // Set initial container height
    setContainerHeight(container.clientHeight);
    
    // Listen for scroll events
    container.addEventListener('scroll', handleScroll, { passive: true });
    
    // Update container height on resize
    const resizeObserver = new ResizeObserver((entries) => {
      const entry = entries[0];
      if (entry) {
        setContainerHeight(entry.contentRect.height);
      }
    });
    
    resizeObserver.observe(container);
    
    return () => {
      container.removeEventListener('scroll', handleScroll);
      resizeObserver.disconnect();
    };
  }, [isVirtualized]);
  
  return {
    virtualItems: isVirtualized ? virtualItems : Array.from({ length: itemCount }, (_, i) => i),
    totalHeight: isVirtualized ? itemCount * itemHeight : 0,
    containerRef,
    offsetY,
    isVirtualized,
  };
}
