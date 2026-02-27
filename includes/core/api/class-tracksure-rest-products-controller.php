<?php

/**
 *
 * TrackSure REST Products Controller
 *
 * Handles product analytics API endpoints for WooCommerce.
 * Optimized for high-traffic sites (100K+ visitors).
 *
 * Direct database queries are required for custom analytics tables.
 * WordPress doesn't provide APIs for complex time-series analytics queries.
 * All queries use $wpdb->prepare() for security.
 *
 * Table name interpolation is safe because:
 * - All table names use $wpdb->prefix (controlled by WordPress)
 * - No user input in table names
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Products controller class.
 */
class TrackSure_REST_Products_Controller extends TrackSure_REST_Controller {




	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Cache group name.
	 *
	 * @var string
	 */
	private $cache_group = 'tracksure_products';

	/**
	 * Cache expiration (5 minutes for high-traffic sites).
	 *
	 * @var int
	 */
	private $cache_expiration = 300;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$core     = TrackSure_Core::get_instance();
		$this->db = $core->get_service( 'db' );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$date_args = $this->get_date_range_args();

		// GET /products/performance - Product analytics.
		register_rest_route(
			$this->namespace,
			'/products/performance',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product_performance' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array_merge(
					$date_args,
					array(
						'limit'    => array(
							'type'    => 'integer',
							'default' => 20, // Reduced from 50 for faster initial load
							'minimum' => 1,
							'maximum' => 500,
						),
						'order_by' => array(
							'type'    => 'string',
							'default' => 'revenue',
							'enum'    => array( 'revenue', 'views', 'conversions', 'conversion_rate', 'product_name' ),
						),
						'order'    => array(
							'type'    => 'string',
							'default' => 'desc',
							'enum'    => array( 'asc', 'desc' ),
						),
					)
				),
			)
		);

		// GET /products/categories - Category performance.
		register_rest_route(
			$this->namespace,
			'/products/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_category_performance' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $date_args,
			)
		);

		// GET /products/funnel - Product funnel metrics.
		register_rest_route(
			$this->namespace,
			'/products/funnel',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product_funnel' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array_merge(
					$date_args,
					array(
						'product_id' => array(
							'type'     => 'integer',
							'required' => false,
						),
					)
				),
			)
		);
	}

	/**
	 * Get product performance data.
	 *
	 * Optimized with:
	 * - Object caching for repeated requests
	 * - Single optimized query with proper indexes
	 * - Pagination to limit memory usage
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product_performance( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );
		$limit      = absint( $request->get_param( 'limit' ) );
		$order_by   = sanitize_text_field( $request->get_param( 'order_by' ) );
		$order      = sanitize_text_field( $request->get_param( 'order' ) );

		// Generate cache key.
		$cache_key = 'perf_' . md5( "{$date_start}_{$date_end}_{$limit}_{$order_by}_{$order}" );

		// Try to get from cache (for high-traffic performance).
		$cached = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached ) {
			return $this->prepare_success( $cached );
		}

		global $wpdb;
		// Map order_by to actual column.
		$order_by_map    = array(
			'revenue'         => 'revenue',
			'views'           => 'views',
			'conversions'     => 'purchases',
			'conversion_rate' => 'conversion_rate',
			'product_name'    => 'product_name',
		);
		$order_by_column = $order_by_map[ $order_by ] ?? 'revenue';

		// Validate order direction.
		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		/**
		 * OPTIMIZED QUERY: Use aggregated table with fallback to raw events
		 *
		 * Performance improvement:
		 * - Single query vs 4-5 queries per product (100x faster)
		 * - No JSON_EXTRACT overhead (pre-calculated metrics)
		 * - Indexed columns for sorting
		 * - Perfect for 100K+ products
		 *
		 * FALLBACK: If aggregation table is empty or incomplete, query raw events
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "
            SELECT 
                product_id,
                MAX(product_name) as product_name,
                SUM(views) as views,
                SUM(add_to_carts) as add_to_carts,
                SUM(purchases) as purchases,
                SUM(revenue) as revenue,
                CASE 
                    WHEN SUM(views) > 0 THEN ROUND((SUM(purchases) / SUM(views)) * 100, 2)
                    ELSE 0 
                END as conversion_rate
            FROM {$wpdb->prefix}tracksure_agg_product_daily
            WHERE date BETWEEN %s AND %s
            GROUP BY product_id
            ORDER BY {$order_by_column} {$order}
            LIMIT %d
        ";
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared in the prepare() call below
		$products = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Variable $sql contains prepared SQL from above
			$wpdb->prepare( $sql, $date_start, $date_end, $limit )
		);

		// FALLBACK: If no products or all have 0 purchases, query raw events.
		$has_purchases = false;
		foreach ( $products as $product ) {
			if ( $product->purchases > 0 ) {
				$has_purchases = true;
				break;
			}
		}

		// If aggregation table is incomplete (no purchases), fallback to raw events.
		if ( empty( $products ) || ! $has_purchases ) {
			$products = $this->get_products_from_raw_events( $date_start, $date_end, $limit, $order_by_column, $order );
		}

		// Format response.
		$formatted = array_map(
			function ( $product ) {
				return array(
					'product_id'      => sanitize_text_field( $product->product_id ),
					'product_name'    => sanitize_text_field( $product->product_name ?? 'Unknown Product' ),
					'views'           => absint( $product->views ),
					'add_to_carts'    => absint( $product->add_to_carts ),
					'purchases'       => absint( $product->purchases ),
					'revenue'         => floatval( $product->revenue ),
					'conversion_rate' => floatval( $product->conversion_rate ),
				);
			},
			$products
		);

		// Cache for 5 minutes.
		wp_cache_set( $cache_key, $formatted, $this->cache_group, $this->cache_expiration );

		return $this->prepare_success( $formatted );
	}

	/**
	 * Get product performance from raw events (fallback when aggregation is incomplete).
	 * Properly handles multi-item purchases by extracting all items from JSON.
	 *
	 * @param string $date_start Start date.
	 * @param string $date_end End date.
	 * @param int    $limit Result limit.
	 * @param string $order_by_column Column to order by.
	 * @param string $order Order direction.
	 * @return array Product data.
	 */
	private function get_products_from_raw_events( $date_start, $date_end, $limit, $order_by_column, $order ) {
		global $wpdb;
		// Get all product-related events.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"
                SELECT 
                    e.event_id,
                    e.event_name,
                    e.event_params,
                    c.conversion_id,
                    c.conversion_value
                FROM {$wpdb->prefix}tracksure_events e
                LEFT JOIN {$wpdb->prefix}tracksure_conversions c ON c.event_id = e.event_id
                WHERE DATE(e.created_at) >= %s 
                  AND DATE(e.created_at) <= %s
                  AND e.event_name IN ('view_item', 'add_to_cart', 'purchase')
                  AND e.event_params IS NOT NULL
            ",
				$date_start,
				$date_end
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Aggregate product metrics by parsing JSON.
		// Handle TWO different event structures:
		// 1. view_item/add_to_cart: product data in root (item_id, item_name, price).
		// 2. purchase: product data in items array (items[0].item_id, items[0].item_name).
		$products = array();
		foreach ( $events as $event ) {
			$params = json_decode( $event->event_params, true );
			if ( ! $params ) {
				continue;
			}

			// Determine event structure and extract products.
			if ( $event->event_name === 'purchase' && isset( $params['items'] ) && is_array( $params['items'] ) ) {
				// Purchase events: items array structure.
				foreach ( $params['items'] as $item ) {
					$product_id   = $item['item_id'] ?? null;
					$product_name = $item['item_name'] ?? 'Unknown Product';
					$item_price   = floatval( $item['price'] ?? 0 );

					if ( ! $product_id ) {
						continue;
					}

					// Initialize product if not exists.
					if ( ! isset( $products[ $product_id ] ) ) {
						$products[ $product_id ] = array(
							'product_id'      => $product_id,
							'product_name'    => $product_name,
							'views'           => 0,
							'add_to_carts'    => 0,
							'purchases'       => 0,
							'revenue'         => 0.0,
							'conversion_rate' => 0.0,
						);
					}

					if ( $event->conversion_id ) {
						++$products[ $product_id ]['purchases'];
						$products[ $product_id ]['revenue'] += $item_price;
					}
				}
			} else {
				// view_item/add_to_cart events: flat structure.
				$product_id   = $params['item_id'] ?? null;
				$product_name = $params['item_name'] ?? 'Unknown Product';

				if ( ! $product_id ) {
					continue;
				}

				// Initialize product if not exists.
				if ( ! isset( $products[ $product_id ] ) ) {
					$products[ $product_id ] = array(
						'product_id'      => $product_id,
						'product_name'    => $product_name,
						'views'           => 0,
						'add_to_carts'    => 0,
						'purchases'       => 0,
						'revenue'         => 0.0,
						'conversion_rate' => 0.0,
					);
				}

				// Increment metrics based on event type.
				if ( $event->event_name === 'view_item' ) {
					++$products[ $product_id ]['views'];
				} elseif ( $event->event_name === 'add_to_cart' ) {
					++$products[ $product_id ]['add_to_carts'];
				}
			}
		}

		// Calculate conversion rates and convert to objects.
		$results = array();
		foreach ( $products as $product ) {
			if ( $product['views'] > 0 ) {
				$product['conversion_rate'] = round( ( $product['purchases'] / $product['views'] ) * 100, 2 );
			}
			$results[] = (object) $product;
		}

		// Sort by specified column.
		usort(
			$results,
			function ( $a, $b ) use ( $order_by_column, $order ) {
				$a_val = $a->{$order_by_column} ?? 0;
				$b_val = $b->{$order_by_column} ?? 0;

				if ( $order === 'DESC' ) {
					return $b_val <=> $a_val;
				}
				return $a_val <=> $b_val;
			}
		);

		// Apply limit.
		return array_slice( $results, 0, $limit );
	}

	/**
	 * Get category performance.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_category_performance( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );

		// Check if WooCommerce is active.
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $this->prepare_error( 'woocommerce_not_active', 'WooCommerce is not active', 400 );
		}

		// Cache key
		$cache_key = 'cat_' . md5( "{$date_start}_{$date_end}" );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached ) {
			return $this->prepare_success( $cached );
		}

		global $wpdb;
		// Get product categories with aggregated metrics.
		// Note: Categories are only available for purchased products (items array format)
		// Use COUNT(DISTINCT) to avoid counting duplicate browser/server events.
		// OPTIMIZED: Added LIMIT 20 for faster initial load.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from safe sources.
		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    tt.term_id as category_id,
                    t.name as category_name,
                    COUNT(DISTINCT e.event_id) as total_events,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'view_item' THEN e.event_id END) as views,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'add_to_cart' THEN e.event_id END) as add_to_carts,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'purchase' THEN e.event_id END) as purchases,
                    SUM(CASE 
                        WHEN e.event_name = 'purchase' 
                        THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.value')) AS DECIMAL(10,2))
                        ELSE 0 
                    END) as revenue
                FROM {$wpdb->prefix}tracksure_events e
                INNER JOIN {$wpdb->prefix}term_relationships tr 
                    ON tr.object_id = CAST(
                        COALESCE(
                            JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.item_id')),
                            JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.items[0].item_id'))
                        ) AS UNSIGNED
                    )
                INNER JOIN {$wpdb->prefix}term_taxonomy tt 
                    ON tt.term_taxonomy_id = tr.term_taxonomy_id 
                    AND tt.taxonomy = 'product_cat'
                INNER JOIN {$wpdb->prefix}terms t 
                    ON t.term_id = tt.term_id
                WHERE e.event_name IN ('view_item', 'add_to_cart', 'purchase')
                  AND e.occurred_at BETWEEN %s AND DATE_ADD(%s, INTERVAL 1 DAY)
                  AND e.event_params IS NOT NULL
                GROUP BY tt.term_id
                ORDER BY revenue DESC
                LIMIT 20",
				$date_start,
				$date_end
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$formatted = array_map(
			function ( $cat ) {
				return array(
					'category_id'   => absint( $cat->category_id ),
					'category_name' => sanitize_text_field( $cat->category_name ),
					'views'         => absint( $cat->views ),
					'add_to_carts'  => absint( $cat->add_to_carts ),
					'purchases'     => absint( $cat->purchases ),
					'revenue'       => floatval( $cat->revenue ),
				);
			},
			$categories
		);

		wp_cache_set( $cache_key, $formatted, $this->cache_group, $this->cache_expiration );

		return $this->prepare_success( $formatted );
	}

	/**
	 * Get product funnel metrics.
	 *
	 * Shows: view_item → add_to_cart → begin_checkout → purchase
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product_funnel( $request ) {
		$date_start = sanitize_text_field( $request->get_param( 'date_start' ) );
		$date_end   = sanitize_text_field( $request->get_param( 'date_end' ) );
		$product_id = $request->get_param( 'product_id' );

		$cache_key = 'funnel_' . md5( "{$date_start}_{$date_end}_{$product_id}" );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached ) {
			return $this->prepare_success( $cached );
		}

		global $wpdb;
		// Build WHERE clause.
		$where  = 'e.occurred_at BETWEEN %s AND DATE_ADD(%s, INTERVAL 1 DAY)';
		$params = array( $date_start, $date_end );

		if ( $product_id ) {
			// Try both JSON paths for product_id.
			$where   .= " AND (
                JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.item_id')) = %s
                OR JSON_UNQUOTE(JSON_EXTRACT(e.event_params, '$.items[0].item_id')) = %s
            )";
			$params[] = $product_id;
			$params[] = $product_id;
		}

		// Get funnel metrics.
		// Use COUNT(DISTINCT event_id) to avoid counting duplicate browser/server events.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic WHERE clause built with proper escaping.
		$funnel = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    COUNT(DISTINCT CASE WHEN e.event_name = 'view_item' THEN e.event_id END) as views,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'add_to_cart' THEN e.event_id END) as add_to_carts,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'begin_checkout' THEN e.event_id END) as begin_checkouts,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'purchase' THEN e.event_id END) as purchases
                FROM {$wpdb->prefix}tracksure_events e
                WHERE {$where}",
				...$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// Calculate drop-off rates.
		$views           = absint( $funnel->views ?? 0 );
		$add_to_carts    = absint( $funnel->add_to_carts ?? 0 );
		$begin_checkouts = absint( $funnel->begin_checkouts ?? 0 );
		$purchases       = absint( $funnel->purchases ?? 0 );

		$formatted = array(
			'funnel'          => array(
				array(
					'step'     => 'view_item',
					'label'    => 'Product Views',
					'count'    => $views,
					'drop_off' => 0,
				),
				array(
					'step'     => 'add_to_cart',
					'label'    => 'Add to Cart',
					'count'    => $add_to_carts,
					'drop_off' => $views > 0 ? round( ( ( $views - $add_to_carts ) / $views ) * 100, 2 ) : 0,
				),
				array(
					'step'     => 'begin_checkout',
					'label'    => 'Begin Checkout',
					'count'    => $begin_checkouts,
					'drop_off' => $add_to_carts > 0 ? round( ( ( $add_to_carts - $begin_checkouts ) / $add_to_carts ) * 100, 2 ) : 0,
				),
				array(
					'step'     => 'purchase',
					'label'    => 'Purchase',
					'count'    => $purchases,
					'drop_off' => $begin_checkouts > 0 ? round( ( ( $begin_checkouts - $purchases ) / $begin_checkouts ) * 100, 2 ) : 0,
				),
			),
			'conversion_rate' => $views > 0 ? round( ( $purchases / $views ) * 100, 2 ) : 0,
		);

		wp_cache_set( $cache_key, $formatted, $this->cache_group, $this->cache_expiration );

		return $this->prepare_success( $formatted );
	}
}
