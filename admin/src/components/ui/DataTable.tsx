/**
 * TrackSure DataTable Component
 * 
 * Reusable data table with sorting, pagination, and CSV export.
 */

import React, { useState, useMemo } from 'react';
import { __ } from '../../utils/i18n';
import { useVirtualized } from '../../hooks/useVirtualized';
import '../../styles/components/ui/DataTable.css';

export interface DataTableColumn<T = Record<string, unknown>> {
  key: string;
  label: string;
  width?: string;
  align?: 'left' | 'center' | 'right';
  sortable?: boolean;
  render?: (value: unknown, row: T, index: number) => React.ReactNode;
  format?: (value: unknown) => string;
}

export interface DataTableProps<T = Record<string, unknown>> {
  columns: DataTableColumn<T>[];
  data: T[];
  keyField?: string;
  loading?: boolean;
  empty?: boolean;
  emptyMessage?: string;
  pageSize?: number;
  showPagination?: boolean;
  showExport?: boolean;
  exportFilename?: string;
  stickyHeader?: boolean;
  compact?: boolean;
  className?: string;
  /** Enable virtualization for large datasets (default: true for >50 rows) */
  virtualized?: boolean;
  /** Row height in pixels for virtualization (default: 60) */
  rowHeight?: number;
}

type SortDirection = 'asc' | 'desc' | null;

export const DataTable = <T extends Record<string, unknown>>({
  columns,
  data,
  keyField = 'id',
  loading = false,
  empty = false,
  emptyMessage = 'No data available',
  pageSize = 10,
  showPagination = true,
  showExport = true,
  exportFilename = 'tracksure-export.csv',
  stickyHeader = true,
  compact = false,
  className = '',
  virtualized = true,
  rowHeight = 60,
}: DataTableProps<T>) => {
  const [sortKey, setSortKey] = useState<string | null>(null);
  const [sortDirection, setSortDirection] = useState<SortDirection>(null);
  const [currentPage, setCurrentPage] = useState(1);

  // Sorting logic
  const sortedData = useMemo(() => {
    if (!sortKey || !sortDirection) {return data;}

    return [...data].sort((a, b) => {
      const aVal = a[sortKey];
      const bVal = b[sortKey];

      if (aVal === bVal) {return 0;}

      const comparison = aVal < bVal ? -1 : 1;
      return sortDirection === 'asc' ? comparison : -comparison;
    });
  }, [data, sortKey, sortDirection]);

  // Pagination logic
  const paginatedData = useMemo(() => {
    if (!showPagination) {return sortedData;}

    const start = (currentPage - 1) * pageSize;
    const end = start + pageSize;
    return sortedData.slice(start, end);
  }, [sortedData, currentPage, pageSize, showPagination]);

  // Virtualization (only applies when pagination is off)
  const {
    virtualItems,
    totalHeight,
    containerRef,
    offsetY,
    isVirtualized,
  } = useVirtualized({
    itemCount: showPagination ? paginatedData.length : sortedData.length,
    itemHeight: rowHeight,
    overscan: 5,
    threshold: 50, // Only virtualize if >50 rows
  });

  // Data to render (either paginated or virtualized)
  const displayData = useMemo(() => {
    if (showPagination) {
      return paginatedData;
    }
    
    if (!isVirtualized || !virtualized) {
      return sortedData;
    }
    
    return virtualItems.map(index => sortedData[index]);
  }, [showPagination, paginatedData, sortedData, isVirtualized, virtualized, virtualItems]);

  const totalPages = Math.ceil(sortedData.length / pageSize);

  // Handle sort
  const handleSort = (key: string) => {
    if (sortKey === key) {
      // Cycle through: asc -> desc -> null
      if (sortDirection === 'asc') {
        setSortDirection('desc');
      } else if (sortDirection === 'desc') {
        setSortKey(null);
        setSortDirection(null);
      }
    } else {
      setSortKey(key);
      setSortDirection('asc');
    }
  };

  // Export to CSV
  const handleExport = () => {
    const headers = columns.map((col) => col.label).join(',');
    const rows = sortedData
      .map((row) =>
        columns
          .map((col) => {
            const value = row[col.key];
            const formatted = col.format ? col.format(value) : value;
            // Escape CSV values
            return `"${String(formatted || '').replace(/"/g, '""')}"`;
          })
          .join(',')
      )
      .join('\n');

    const csv = `${headers}\n${rows}`;
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = exportFilename;
    link.click();
  };

  if (loading) {
    return (
      <div className={`ts-data-table ${className}`}>
        <div className="ts-data-table__loading">
          {[...Array(pageSize)].map((_, i) => (
            <div key={i} className="ts-data-table__skeleton-row">
              {columns.map((col, j) => (
                <div key={j} className="ts-data-table__skeleton-cell"></div>
              ))}
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (empty || !data || data.length === 0) {
    return (
      <div className={`ts-data-table ts-data-table--empty ${className}`}>
        <div className="ts-data-table__empty">
          <svg className="ts-data-table__empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          <p className="ts-data-table__empty-message">{emptyMessage}</p>
        </div>
      </div>
    );
  }

  return (
    <div className={`ts-data-table ${compact ? 'ts-data-table--compact' : ''} ${className}`}>
      {showExport && (
        <div className="ts-data-table__actions">
          <button
            className="ts-data-table__export-button"
            onClick={handleExport}
            type="button"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {__("Export CSV")}
          </button>
        </div>
      )}

      <div 
        ref={!showPagination && virtualized ? containerRef : null}
        className="ts-data-table__wrapper"
        style={!showPagination && isVirtualized && virtualized ? { maxHeight: '600px', overflow: 'auto' } : undefined}
      >
        <table className={`ts-data-table__table ${stickyHeader ? 'ts-data-table__table--sticky' : ''}`}>
          <thead className="ts-data-table__thead">
            <tr>
              {columns.map((column) => (
                <th
                  key={column.key}
                  className={`ts-data-table__th ts-data-table__th--${column.align || 'left'} ${
                    column.sortable !== false ? 'ts-data-table__th--sortable' : ''
                  } ${sortKey === column.key ? 'ts-data-table__th--sorted' : ''}`}
                  style={{ width: column.width }}
                  onClick={() => column.sortable !== false && handleSort(column.key)}
                >
                  <div className="ts-data-table__th-content">
                    <span>{column.label}</span>
                    {column.sortable !== false && (
                      <span className="ts-data-table__sort-icon">
                        {sortKey === column.key ? (
                          sortDirection === 'asc' ? '↑' : '↓'
                        ) : (
                          '↕'
                        )}
                      </span>
                    )}
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody 
            className="ts-data-table__tbody"
            style={!showPagination && isVirtualized && virtualized ? { 
              position: 'relative', 
              height: totalHeight 
            } : undefined}
          >
            {!showPagination && isVirtualized && virtualized && (
              <tr style={{ height: offsetY }} aria-hidden="true" />
            )}
            {displayData.map((row, index) => {
              const actualIndex = !showPagination && isVirtualized && virtualized 
                ? virtualItems[index] 
                : (showPagination ? (currentPage - 1) * pageSize + index : index);
              
              return (
                <tr 
                  key={row[keyField] || actualIndex} 
                  className="ts-data-table__tr"
                  style={!showPagination && isVirtualized && virtualized ? { 
                    height: rowHeight 
                  } : undefined}
                >
                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={`ts-data-table__td ts-data-table__td--${column.align || 'left'}`}
                    >
                      {column.render
                        ? column.render(row[column.key], row, actualIndex)
                        : column.format
                        ? column.format(row[column.key])
                        : row[column.key]}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {showPagination && totalPages > 1 && (
        <div className="ts-data-table__pagination">
          <button
            className="ts-data-table__pagination-button"
            onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
            disabled={currentPage === 1}
            type="button"
          >
            {__("Previous")}
          </button>
          <span className="ts-data-table__pagination-info">
            {__("Page")} {currentPage} {__("of")} {totalPages}
          </span>
          <button
            className="ts-data-table__pagination-button"
            onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
            disabled={currentPage === totalPages}
            type="button"
          >
            {__("Next")}
          </button>
        </div>
      )}
    </div>
  );
};
