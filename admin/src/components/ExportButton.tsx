/**
 * Export Button Component
 * Allows exporting overview dashboard data as PNG, PDF, or CSV
 */

import React, { useState, useEffect, useRef } from 'react';
import { Icon } from './ui/Icon';
import { __ } from '../utils/i18n';
import { formatLocalDate } from '../utils/parameterFormatters';
import type { OverviewData } from '../pages/OverviewPage';
import '../styles/components/ExportButton.css';

interface ExportButtonProps {
  data?: OverviewData;
  dateRange: { start: Date; end: Date };
}

export const ExportButton: React.FC<ExportButtonProps> = ({ data, dateRange }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);

  // Close menu when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  const downloadFile = (content: string, filename: string, mimeType: string) => {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  const exportCSV = () => {
    if (!data) {
      return;
    }

    setIsExporting(true);

    try {
      // Generate CSV content
      let csv = '';

      // Header
      csv += `TrackSure Overview Report\n`;
      csv += `Date Range: ${formatLocalDate(dateRange.start)} to ${formatLocalDate(dateRange.end)}\n`;
      csv += `Generated: ${new Date().toLocaleString()}\n\n`;

      // Metrics Section
      if (data.metrics) {
        csv += `Metrics\n`;
        csv += `Metric,Value\n`;
        Object.entries(data.metrics).forEach(([key, value]) => {
          const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
          csv += `${label},${value}\n`;
        });
        csv += `\n`;
      }

      // Devices Section
      if (data.devices && data.devices.length > 0) {
        csv += `Devices\n`;
        csv += `Device,Visitors,Sessions,Percentage\n`;
        data.devices.forEach((device) => {
          csv += `${device.device},${device.visitors},${device.sessions},${device.percentage}\n`;
        });
        csv += `\n`;
      }

      // Top Sources Section
      if (data.top_sources && data.top_sources.length > 0) {
        csv += `Top Traffic Sources\n`;
        csv += `Source,Medium,Visitors,Sessions,Conversions,Percentage\n`;
        data.top_sources.forEach((source) => {
          csv += `${source.source},${source.medium},${source.visitors},${source.sessions},${source.conversions},${source.percentage}\n`;
        });
        csv += `\n`;
      }

      // Top Countries Section
      if (data.top_countries && data.top_countries.length > 0) {
        csv += `Top Countries\n`;
        csv += `Country,Visitors,Sessions,Percentage\n`;
        data.top_countries.forEach((country) => {
          csv += `${country.country},${country.visitors},${country.sessions},${country.percentage}\n`;
        });
        csv += `\n`;
      }

      // Top Pages Section
      if (data.top_pages && data.top_pages.length > 0) {
        csv += `Top Pages\n`;
        csv += `Path,Title,Visitors,Sessions,Pageviews,Conversions\n`;
        data.top_pages.forEach((page) => {
          csv += `${page.path},"${page.title}",${page.visitors},${page.sessions},${page.pageviews},${page.conversions}\n`;
        });
      }

      const filename = `tracksure-overview-${formatLocalDate(dateRange.start)}-to-${formatLocalDate(dateRange.end)}.csv`;
      downloadFile(csv, filename, 'text/csv;charset=utf-8;');
    } catch (error) {
      console.error('Export CSV failed:', error);
    } finally {
      setIsExporting(false);
      setIsOpen(false);
    }
  };

  const exportImage = async () => {
    setIsExporting(true);
    
    try {
      // Dynamically import html2canvas only when needed
      const html2canvas = (await import('html2canvas')).default;
      
      const element = document.querySelector('.ts-page') as HTMLElement;
      if (!element) {
        throw new Error('Page element not found');
      }

      // Get current theme
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      const bgColor = isDark ? '#1F2937' : '#FFFFFF';
      const textColor = isDark ? '#F9FAFB' : '#111827';

      const canvas = await html2canvas(element, {
        scale: 2,
        backgroundColor: bgColor,
        logging: false,
        onclone: (clonedDoc) => {
          // Fix hero card text visibility
          const heroCards = clonedDoc.querySelectorAll('.ts-kpi-card--hero .ts-kpi-value');
          heroCards.forEach((card) => {
            (card as HTMLElement).style.webkitTextFillColor = textColor;
            (card as HTMLElement).style.color = textColor;
          });
        },
      });

      canvas.toBlob((blob) => {
        if (!blob) {
          return;
        }
        
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const filename = `tracksure-overview-${formatLocalDate(dateRange.start)}-to-${formatLocalDate(dateRange.end)}.png`;
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      }, 'image/png');
    } catch (error) {
      console.error('Export image failed:', error);
    } finally {
      setIsExporting(false);
      setIsOpen(false);
    }
  };

  const exportPDF = async () => {
    setIsExporting(true);
    
    try {
      // Dynamically import libraries only when needed
      const [html2canvas, { jsPDF }] = await Promise.all([
        import('html2canvas').then(m => m.default),
        import('jspdf'),
      ]);

      const element = document.querySelector('.ts-page') as HTMLElement;
      if (!element) {
        throw new Error('Page element not found');
      }

      // Get current theme
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      const bgColor = isDark ? '#1F2937' : '#FFFFFF';
      const textColor = isDark ? '#F9FAFB' : '#111827';

      const canvas = await html2canvas(element, {
        scale: 2,
        backgroundColor: bgColor,
        logging: false,
        onclone: (clonedDoc) => {
          // Fix hero card text visibility
          const heroCards = clonedDoc.querySelectorAll('.ts-kpi-card--hero .ts-kpi-value');
          heroCards.forEach((card) => {
            (card as HTMLElement).style.webkitTextFillColor = textColor;
            (card as HTMLElement).style.color = textColor;
          });
        },
      });

      const imgData = canvas.toDataURL('image/png');
      const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4',
      });

      const pdfWidth = pdf.internal.pageSize.getWidth();
      const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

      pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
      
      const filename = `tracksure-overview-${formatLocalDate(dateRange.start)}-to-${formatLocalDate(dateRange.end)}.pdf`;
      pdf.save(filename);
    } catch (error) {
      console.error('Export PDF failed:', error);
    } finally {
      setIsExporting(false);
      setIsOpen(false);
    }
  };

  return (
    <div className="ts-export-button-wrapper" ref={wrapperRef}>
      <button
        className="ts-export-button"
        onClick={() => setIsOpen(!isOpen)}
        disabled={isExporting}
      >
        {isExporting ? (
          <>
            <Icon name="RefreshCw" size={16} className="spin" />
            {__('Exporting...')}
          </>
        ) : (
          <>
            <Icon name="Download" size={16} />
            {__('Export')}
          </>
        )}
      </button>

      {isOpen && !isExporting && (
        <div className="ts-export-menu">
          <button onClick={exportCSV} className="ts-export-option">
            <Icon name="FileText" size={16} />
            <span>{__('Export as CSV')}</span>
          </button>
          <button onClick={exportImage} className="ts-export-option">
            <Icon name="Image" size={16} />
            <span>{__('Export as PNG')}</span>
          </button>
          <button onClick={exportPDF} className="ts-export-option">
            <Icon name="FileText" size={16} />
            <span>{__('Export as PDF')}</span>
          </button>
        </div>
      )}
    </div>
  );
};
