/**
 * Products Page - eCommerce Product Analytics
 * Shows product performance, funnel analysis, and revenue metrics
 * Week 3-4 implementation
 */

import React, { useState, useMemo } from 'react';
import { useApp } from '../contexts/AppContext';
import { useApiQuery } from '../hooks/useApiQuery';
import { Icon } from '../components/ui/Icon';
import { ICON_REGISTRY } from '../config/iconRegistry';
import { __ } from '@wordpress/i18n';
import { formatCurrency, formatLocalDate } from '../utils/parameterFormatters';
import '../styles/pages/ProductsPage.css';

interface Product {
  product_id: number;
  product_name: string;
  views: number;
  add_to_carts: number;
  purchases: number;
  revenue: number;
  conversion_rate: number;
}

interface ProductCategory {
  category_id: number;
  category_name: string;
  views: number;
  add_to_carts: number;
  purchases: number;
  revenue: number;
}

interface FunnelStep {
  step: string;
  label: string;
  count: number;
  drop_off: number;
}

interface ProductFunnel {
  funnel: FunnelStep[];
  conversion_rate: number;
}

const ProductsPage: React.FC = () => {
  const { dateRange } = useApp();
  const [sortBy, setSortBy] = useState<'revenue' | 'views' | 'conversions' | 'conversion_rate'>('revenue');
  const [sortOrder, setSortOrder] = useState<'desc' | 'asc'>('desc');
  const [activeTab, setActiveTab] = useState<'products' | 'categories' | 'funnel'>('products');
  const [_selectedProduct, _setSelectedProduct] = useState<Product | null>(null);

  const params = useMemo(() => ({
    date_start: formatLocalDate(dateRange.start),
    date_end: formatLocalDate(dateRange.end),
  }), [dateRange]);

  // Lazy load data only when tab is active (improves initial page load)
  const { data: productsData, error: productsError, isLoading: productsLoading } = useApiQuery<Product[]>(
    'getProductsPerformance',
    params,
    { 
      refetchInterval: 300000, 
      retry: 0,
      staleTime: 60000, // Cache for 1 minute
      enabled: activeTab === 'products' // Only fetch when tab is active
    }
  );

  const { data: categoriesData, error: categoriesError, isLoading: categoriesLoading } = useApiQuery<ProductCategory[]>(
    'getProductsCategories',
    params,
    { 
      refetchInterval: 300000, 
      retry: 0,
      staleTime: 60000,
      enabled: activeTab === 'categories' // Only fetch when tab is active
    }
  );

  const { data: funnelData, error: funnelError, isLoading: funnelLoading } = useApiQuery<ProductFunnel>(
    'getProductsFunnel',
    params,
    { 
      refetchInterval: 300000, 
      retry: 0,
      staleTime: 60000,
      enabled: activeTab === 'funnel' // Only fetch when tab is active
    }
  );

  const products = productsData || [];
  const categories = categoriesData || [];
  const funnel = funnelData || null;
  const error = productsError || categoriesError || funnelError;
  
  // Show loading based on active tab
  const isLoading = (
    (activeTab === 'products' && productsLoading) ||
    (activeTab === 'categories' && categoriesLoading) ||
    (activeTab === 'funnel' && funnelLoading)
  );

  // Show error only for non-WooCommerce or server errors
  if (error && (error.message?.includes('WooCommerce') || (error as { status?: number }).status && (error as { status?: number }).status! >= 500)) {
    return (
      <div className="tracksure-page products-page">
        <div className="page-header">
          <h1><Icon name={ICON_REGISTRY.products} size={28} className="inline-icon" /> {__('Product Analytics')}</h1>
          <p className="subtitle">{__('eCommerce product performance and funnel analysis')}</p>
        </div>

        <div className="ts-empty-state" style={{ padding: '80px 20px', textAlign: 'center' }}>
          <div className="ts-empty-icon" style={{ marginBottom: '24px' }}>
            <Icon name="Package" size={64} color="muted" />
          </div>
          <h2 style={{ fontSize: '24px', fontWeight: '700', marginBottom: '12px', color: 'var(--ts-text)' }}>
            {error.message?.includes('WooCommerce') 
              ? __('WooCommerce Not Detected') 
              : __('Server Error')}
          </h2>
          <p style={{ fontSize: '15px', color: 'var(--ts-text-muted)', maxWidth: '500px', margin: '0 auto 24px' }}>
            {error.message?.includes('WooCommerce')
              ? __('Product analytics requires WooCommerce to be installed and active. Once activated, product views, add-to-cart events, and purchases will be tracked automatically.')
              : error.message || __('Unable to load product analytics. Please try again later.')}
          </p>
          {error.message?.includes('WooCommerce') && (
            <p style={{ fontSize: '13px', color: 'var(--ts-text-muted)', fontStyle: 'italic' }}>
              {__('Install WooCommerce to unlock product analytics')}
            </p>
          )}
        </div>
      </div>
    );
  }

  const handleSort = (column: 'revenue' | 'views' | 'conversions' | 'conversion_rate') => {
    if (sortBy === column) {
      setSortOrder(sortOrder === 'desc' ? 'asc' : 'desc');
    } else {
      setSortBy(column);
      setSortOrder('desc');
    }
  };

  const sortedProducts = [...products].sort((a, b) => {
    let aVal: number, bVal: number;

    switch (sortBy) {
      case 'revenue':
        aVal = a.revenue;
        bVal = b.revenue;
        break;
      case 'views':
        aVal = a.views;
        bVal = b.views;
        break;
      case 'conversions':
        aVal = a.purchases;
        bVal = b.purchases;
        break;
      case 'conversion_rate':
        aVal = a.conversion_rate;
        bVal = b.conversion_rate;
        break;
      default:
        aVal = a.revenue;
        bVal = b.revenue;
    }

    return sortOrder === 'desc' ? bVal - aVal : aVal - bVal;
  });

  const totalRevenue = products.reduce((sum, p) => sum + p.revenue, 0);
  const totalViews = products.reduce((sum, p) => sum + p.views, 0);
  const totalPurchases = products.reduce((sum, p) => sum + p.purchases, 0);
  const avgConversionRate = products.length > 0
    ? products.reduce((sum, p) => sum + p.conversion_rate, 0) / products.length
    : 0;

  // Show loading skeleton
  if (isLoading) {
    return (
      <div className="tracksure-page products-page">
        <div className="page-header">
          <h1><Icon name={ICON_REGISTRY.products} size={28} className="inline-icon" /> {__('Product Analytics')}</h1>
          <p className="subtitle">{__('Which products drive revenue and how customers shop')}</p>
        </div>

        <div className="metrics-grid">
          {[1, 2, 3, 4].map(i => (
            <div key={i} className="metric-card" style={{ minHeight: '100px', animation: 'pulse 2s infinite' }}>
              <div style={{ background: 'var(--ts-border)', height: '12px', width: '60%', borderRadius: '4px', marginBottom: '12px' }}></div>
              <div style={{ background: 'var(--ts-border)', height: '32px', width: '80%', borderRadius: '4px', marginBottom: '8px' }}></div>
              <div style={{ background: 'var(--ts-border)', height: '10px', width: '50%', borderRadius: '4px' }}></div>
            </div>
          ))}
        </div>

        <div className="tabs">
          <button className="tab active"><Icon name={ICON_REGISTRY.products} size={16} /> {__('Products')}</button>
          <button className="tab"><Icon name="FolderOpen" size={16} /> {__('Categories')}</button>
          <button className="tab"><Icon name={ICON_REGISTRY.goals} size={16} /> {__('Funnel')}</button>
        </div>

        <div className="table-container">
          <div className="table-header">
            <h2>{__('Loading...')}</h2>
          </div>
          <div style={{ padding: '40px', textAlign: 'center', color: 'var(--ts-text-muted)' }}>
            <Icon name="Loader" size={32} className="spinning" />
            <p style={{ marginTop: '16px' }}>{__('Loading product analytics...')}</p>
          </div>
        </div>
      </div>
    );
  }

  // Show error message for WooCommerce/server issues
  if (error && (error.message?.includes('WooCommerce') || (error as { status?: number }).status && (error as { status?: number }).status! >= 500)) {
    return (
      <div className="tracksure-page products-page">
        <div className="page-header">
          <h1><Icon name={ICON_REGISTRY.products} size={28} className="inline-icon" /> {__('Product Analytics')}</h1>
          <p className="subtitle">{__('eCommerce product performance and funnel analysis')}</p>
        </div>
        <div className="error-message">
          <Icon name="AlertTriangle" size={20} color="danger" />
          <span>{error?.message || __('Failed to load products data. Please check if WooCommerce is active.')}</span>
        </div>
      </div>
    );
  }

  return (
    <div className="tracksure-page products-page">
      <div className="page-header">
        <h1><Icon name={ICON_REGISTRY.products} size={28} className="inline-icon" /> {__('Product Analytics')}</h1>
        <p className="subtitle">{__('Which products drive revenue and how customers shop')}</p>
      </div>

      {/* Summary Cards */}
      <div className="metrics-grid">
        <div className="metric-card">
          <div className="metric-label">{__('Total Revenue')}</div>
          <div className="metric-value">{formatCurrency(totalRevenue)}</div>
          <div className="metric-subtitle">{__('from tracked products')}</div>
        </div>
        <div className="metric-card">
          <div className="metric-label">{__('Product Views')}</div>
          <div className="metric-value">{totalViews.toLocaleString()}</div>
          <div className="metric-subtitle">{__('total product page views')}</div>
        </div>
        <div className="metric-card">
          <div className="metric-label">{__('Purchases')}</div>
          <div className="metric-value">{totalPurchases.toLocaleString()}</div>
          <div className="metric-subtitle">{__('items purchased')}</div>
        </div>
        <div className="metric-card">
          <div className="metric-label">{__('Avg Conversion Rate')}</div>
          <div className="metric-value">{avgConversionRate.toFixed(2)}%</div>
          <div className="metric-subtitle">{__('views to purchases')}</div>
        </div>
      </div>

      {/* Tabs */}
      <div className="tabs">
        <button
          className={`tab ${activeTab === 'products' ? 'active' : ''}`}
          onClick={() => setActiveTab('products')}
        >
          <Icon name={ICON_REGISTRY.products} size={16} /> {__('Products')}
        </button>
        <button
          className={`tab ${activeTab === 'categories' ? 'active' : ''}`}
          onClick={() => setActiveTab('categories')}
        >
          <Icon name="FolderOpen" size={16} /> {__('Categories')}
        </button>
        <button
          className={`tab ${activeTab === 'funnel' ? 'active' : ''}`}
          onClick={() => setActiveTab('funnel')}
        >
          <Icon name={ICON_REGISTRY.goals} size={16} /> {__('Funnel')}
        </button>
      </div>

      {/* Products Tab */}
      {activeTab === 'products' && (
        <div className="table-container">
          <div className="table-header">
            <h2>{__('Top Products by Performance')}</h2>
            <p>{__('Products ranked by selected metric')}</p>
          </div>

          <table className="data-table">
            <thead>
              <tr>
                <th>{__('Product')}</th>
                <th 
                  className={`sortable ${sortBy === 'views' ? 'sorted-' + sortOrder : ''}`}
                  onClick={() => handleSort('views')}
                >
                  {__('Views')} {sortBy === 'views' && (sortOrder === 'desc' ? '↓' : '↑')}
                </th>
                <th>{__('Add to Cart')}</th>
                <th 
                  className={`sortable ${sortBy === 'conversions' ? 'sorted-' + sortOrder : ''}`}
                  onClick={() => handleSort('conversions')}
                >
                  {__('Purchases')} {sortBy === 'conversions' && (sortOrder === 'desc' ? '↓' : '↑')}
                </th>
                <th 
                  className={`sortable ${sortBy === 'revenue' ? 'sorted-' + sortOrder : ''}`}
                  onClick={() => handleSort('revenue')}
                >
                  {__('Revenue')} {sortBy === 'revenue' && (sortOrder === 'desc' ? '↓' : '↑')}
                </th>
                <th 
                  className={`sortable ${sortBy === 'conversion_rate' ? 'sorted-' + sortOrder : ''}`}
                  onClick={() => handleSort('conversion_rate')}
                >
                  {__('Conv. Rate')} {sortBy === 'conversion_rate' && (sortOrder === 'desc' ? '↓' : '↑')}
                </th>
              </tr>
            </thead>
            <tbody>
              {sortedProducts.length === 0 ? (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '40px' }}>
                    <span style={{ display: 'block', marginBottom: '16px' }}>
                      <Icon name={ICON_REGISTRY.products} size={48} color="muted" />
                    </span>
                    <p>{__('No product data available for the selected date range.')}</p>
                    <p style={{ fontSize: '14px', color: '#666', marginTop: '8px' }}>
                      {__('Make sure you have WooCommerce installed and have some product events tracked.')}
                    </p>
                  </td>
                </tr>
              ) : (
                sortedProducts.map((product) => (
                  <tr key={product.product_id}>
                    <td>
                      <div className="product-cell">
                        <span className="product-icon">
                          <Icon name={ICON_REGISTRY.products} size={20} />
                        </span>
                        <div>
                          <div className="product-name">{product.product_name || `Product #${product.product_id}`}</div>
                          <div className="product-id">ID: {product.product_id}</div>
                        </div>
                      </div>
                    </td>
                    <td>{product.views.toLocaleString()}</td>
                    <td>
                      {product.add_to_carts.toLocaleString()}
                      <span className="metric-secondary">
                        {product.views > 0 ? ` (${((product.add_to_carts / product.views) * 100).toFixed(1)}%)` : ''}
                      </span>
                    </td>
                    <td>{product.purchases.toLocaleString()}</td>
                    <td className="revenue-cell">
                      {formatCurrency(product.revenue)}
                    </td>
                    <td>
                      <span className={`conversion-badge ${product.conversion_rate > 5 ? 'high' : product.conversion_rate > 2 ? 'medium' : 'low'}`}>
                        {product.conversion_rate.toFixed(2)}%
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Categories Tab */}
      {activeTab === 'categories' && (
        <div className="table-container">
          <div className="table-header">
            <h2>{__('Performance by Category')}</h2>
            <p>{__('Product categories ranked by revenue')}</p>
          </div>

          <table className="data-table">
            <thead>
              <tr>
                <th>{__('Category')}</th>
                <th>{__('Views')}</th>
                <th>{__('Add to Cart')}</th>
                <th>{__('Purchases')}</th>
                <th>{__('Revenue')}</th>
              </tr>
            </thead>
            <tbody>
              {categories.length === 0 ? (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: '40px' }}>
                    <div style={{ marginBottom: '16px' }}>
                      <Icon name="FolderOpen" size={48} color="muted" />
                    </div>
                    <p>{__('No category data available.')}</p>
                  </td>
                </tr>
              ) : (
                categories.map((category) => (
                  <tr key={category.category_id}>
                    <td>
                      <div className="category-cell">
                        <Icon name="FolderOpen" size={20} className="category-icon" />
                        <span className="category-name">{category.category_name}</span>
                      </div>
                    </td>
                    <td>{category.views.toLocaleString()}</td>
                    <td>{category.add_to_carts.toLocaleString()}</td>
                    <td>{category.purchases.toLocaleString()}</td>
                    <td className="revenue-cell">
                      {formatCurrency(category.revenue)}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Funnel Tab */}
      {activeTab === 'funnel' && funnel && (
        <div className="funnel-container">
          <div className="table-header">
            <h2>{__('Product Purchase Funnel')}</h2>
            <p>{__('Journey from view to purchase')}</p>
          </div>

          <div className="funnel-visualization">
            {funnel.funnel.map((step, index) => {
              const maxCount = funnel.funnel[0].count;
              const completionPercent = maxCount > 0 ? (step.count / maxCount) * 100 : 0;

              return (
                <div key={step.step} className="funnel-step" data-step={index + 1}>
                  <div className="funnel-step-bar">
                    <div className="funnel-step-content">
                      <div className="funnel-step-label">{step.label}</div>
                      <div className="funnel-step-count">
                        {step.count.toLocaleString()}
                        <span style={{ fontSize: '14px', fontWeight: '500', marginLeft: '8px', opacity: 0.9 }}>
                          ({completionPercent.toFixed(1)}%)
                        </span>
                      </div>
                    </div>
                  </div>
                  {index < funnel.funnel.length - 1 && step.drop_off > 0 && (
                    <div className="funnel-drop-off">
                      <span className="drop-off-icon">↓</span>
                      <span className="drop-off-rate">{step.drop_off.toFixed(1)}% drop-off</span>
                    </div>
                  )}
                </div>
              );
            })}
          </div>

          <div className="funnel-summary">
            <div className="funnel-summary-card">
              <div className="summary-label">{__('Overall Conversion Rate')}</div>
              <div className="summary-value">{funnel.conversion_rate.toFixed(2)}%</div>
              <div className="summary-subtitle">{__('Views to purchases')}</div>
            </div>
            <div className="funnel-summary-card">
              <div className="summary-label">{__('Biggest Drop-off')}</div>
              <div className="summary-value">
                {funnel.funnel.reduce((max, step) => step.drop_off > max.drop_off ? step : max).label}
              </div>
              <div className="summary-subtitle">
                {funnel.funnel.reduce((max, step) => step.drop_off > max.drop_off ? step : max).drop_off.toFixed(1)}% abandon here
              </div>
            </div>
          </div>

          <div className="funnel-insights">
            <h3><Icon name="Lightbulb" size={20} color="warning" /> {__('Insights & Recommendations')}</h3>
            <ul className="insights-list">
              {funnel.funnel[1].drop_off > 70 && (
                <li className="insight-item high">
                  <Icon name="AlertCircle" size={20} color="danger" className="insight-icon" />
                  <div>
                    <strong>{__('High drop-off at Add to Cart')}</strong>
                    <p>{__('Most visitors view products but don\'t add them to cart. Consider improving product images, descriptions, reviews, or pricing clarity.')}</p>
                  </div>
                </li>
              )}
              {funnel.funnel[2].drop_off > 60 && (
                <li className="insight-item high">
                  <span className="insight-icon">
                    <Icon name="AlertTriangle" size={20} color="warning" />
                  </span>
                  <div>
                    <strong>{__('High cart abandonment')}</strong>
                    <p>{__('Many users add to cart but don\'t start checkout. Check for unexpected shipping costs or complicated cart UX.')}</p>
                  </div>
                </li>
              )}
              {funnel.funnel[3].drop_off > 50 && (
                <li className="insight-item medium">
                  <span className="insight-icon">
                    <Icon name="CreditCard" size={20} color="warning" />
                  </span>
                  <div>
                    <strong>{__('Checkout drop-off detected')}</strong>
                    <p>{__('Users abandon during checkout. Simplify forms, add guest checkout, and offer multiple payment methods.')}</p>
                  </div>
                </li>
              )}
              {funnel.conversion_rate > 3 && (
                <li className="insight-item positive">
                  <span className="insight-icon">
                    <Icon name="CheckCircle" size={20} color="success" />
                  </span>
                  <div>
                    <strong>{__('Excellent conversion rate!')}</strong>
                    <p>{__('Your product funnel is performing above average. Keep monitoring and testing improvements.')}</p>
                  </div>
                </li>
              )}
            </ul>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductsPage;
