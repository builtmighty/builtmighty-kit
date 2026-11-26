<?php
/**
 * CRM WooCommerce.
 *
 * Collects WooCommerce analytics data.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\CRM;

if ( ! defined( 'WPINC' ) ) { die; }

class crm_woocommerce {

    /**
     * Instance.
     *
     * @since 5.0.0
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get instance.
     *
     * @since 5.0.0
     * @return self
     */
    public static function get_instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if WooCommerce is active.
     *
     * @since 5.0.0
     * @return bool
     */
    public function is_woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Collect all analytics data for a given date.
     *
     * @since 5.0.0
     * @param string $date Date in Y-m-d format. Defaults to yesterday.
     * @return array
     */
    public function collect_daily_data( string $date = '' ): array {
        if ( ! $this->is_woocommerce_active() ) {
            return [ 'error' => 'WooCommerce not active' ];
        }

        if ( empty( $date ) ) {
            $date = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ); // Yesterday in the site's time zone.
        }

        return [
            'sales'   => $this->get_sales_data( $date ),
            'catalog' => $this->get_catalog_data(),
        ];
    }

    /**
     * Get sales data for a specific date.
     *
     * @since 5.0.0
     * @param string $date Date in Y-m-d format.
     * @return array
     */
    public function get_sales_data( string $date ): array {
        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';

        // Fast path: WooCommerce's analytics tables answer this in two queries.
        $fast = $this->get_sales_data_from_lookup( $start, $end );
        if ( $fast !== null ) {
            return $fast;
        }

        $orders = wc_get_orders( [
            'date_created' => $start . '...' . $end,
            'status'       => [ 'completed', 'processing' ],
            'limit'        => -1,
            'return'       => 'ids',
        ] );

        $revenue          = 0;
        $order_count      = count( $orders );
        $new_customers    = 0;
        $return_customers = 0;
        $top_products     = [];

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) continue;

            $revenue += (float) $order->get_total();

            $customer_id = $order->get_customer_id();
            if ( $customer_id > 0 ) {
                $order_count_for_customer = wc_get_customer_order_count( $customer_id );
                if ( $order_count_for_customer === 1 ) {
                    $new_customers++;
                } else {
                    $return_customers++;
                }
            } else {
                $new_customers++;
            }

            foreach ( $order->get_items() as $item ) {
                $product_id = $item->get_product_id();
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();

                if ( ! isset( $top_products[ $product_id ] ) ) {
                    $top_products[ $product_id ] = [
                        'name'     => $product_name,
                        'quantity' => 0,
                        'revenue'  => 0,
                    ];
                }
                $top_products[ $product_id ]['quantity'] += $quantity;
                $top_products[ $product_id ]['revenue']  += (float) $item->get_total();
            }
        }

        $refunds = wc_get_orders( [
            'date_created' => $start . '...' . $end,
            'type'         => 'shop_order_refund',
            'limit'        => -1,
        ] );

        $refund_amount = 0;
        $refund_count  = count( $refunds );
        foreach ( $refunds as $refund ) {
            $refund_amount += abs( (float) $refund->get_total() );
        }

        uasort( $top_products, function( $a, $b ) {
            return $b['revenue'] <=> $a['revenue'];
        } );
        $top_products = array_slice( $top_products, 0, 10, true );

        return [
            'revenue'             => round( $revenue, 2 ),
            'order_count'         => $order_count,
            'average_order_value' => $order_count > 0 ? round( $revenue / $order_count, 2 ) : 0,
            'refund_amount'       => round( $refund_amount, 2 ),
            'refund_count'        => $refund_count,
            'new_customers'       => $new_customers,
            'returning_customers' => $return_customers,
            'top_products'        => array_values( $top_products ),
        ];
    }

    /**
     * Sales data from wc_order_stats and wc_order_product_lookup.
     *
     * Returns null when the tables are missing or not caught up (for example,
     * on a copy where scheduled actions are paused), so the caller can fall
     * back to loading orders.
     *
     * @since 6.0.0
     */
    private function get_sales_data_from_lookup( string $start, string $end ): ?array {

        global $wpdb;
        $stats   = $wpdb->prefix . 'wc_order_stats';
        $lookup  = $wpdb->prefix . 'wc_order_product_lookup';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $stats ) ) !== $stats ) {
            return null;
        }

        // Order dates in wc_order_stats are site-local, like wc_get_orders' date_created strings.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT order_id, total_sales, returning_customer FROM {$stats}
             WHERE parent_id = 0 AND status IN ('wc-completed','wc-processing') AND date_created BETWEEN %s AND %s",
            $start, $end
        ) ); // phpcs:ignore

        // Make sure the analytics import is caught up for this day.
        $expected = wc_get_orders( [ 'date_created' => $start . '...' . $end, 'status' => [ 'completed', 'processing' ], 'limit' => 1, 'paginate' => true, 'return' => 'ids' ] );
        if ( (int) $expected->total !== count( $rows ) ) {
            return null;
        }

        $revenue = 0; $new = 0; $returning = 0; $ids = [];
        foreach ( $rows as $row ) {
            $revenue += (float) $row->total_sales;
            (int) $row->returning_customer ? $returning++ : $new++;
            $ids[] = (int) $row->order_id;
        }

        $top_products = [];
        if ( $ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $products = $wpdb->get_results( $wpdb->prepare(
                "SELECT product_id, SUM(product_qty) AS qty, SUM(product_net_revenue) AS revenue FROM {$lookup}
                 WHERE order_id IN ({$placeholders}) GROUP BY product_id ORDER BY revenue DESC LIMIT 10",
                $ids
            ) ); // phpcs:ignore
            foreach ( $products as $p ) {
                $top_products[] = [ 'name' => get_the_title( (int) $p->product_id ), 'quantity' => (int) $p->qty, 'revenue' => round( (float) $p->revenue, 2 ) ];
            }
        }

        $refund_amount = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT ABS(SUM(total_sales)) FROM {$stats} WHERE parent_id > 0 AND total_sales < 0 AND date_created BETWEEN %s AND %s",
            $start, $end
        ) ); // phpcs:ignore
        $refund_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$stats} WHERE parent_id > 0 AND total_sales < 0 AND date_created BETWEEN %s AND %s",
            $start, $end
        ) ); // phpcs:ignore

        $count = count( $rows );
        return [
            'revenue'             => round( $revenue, 2 ),
            'order_count'         => $count,
            'average_order_value' => $count > 0 ? round( $revenue / $count, 2 ) : 0,
            'refund_amount'       => round( $refund_amount, 2 ),
            'refund_count'        => $refund_count,
            'new_customers'       => $new,
            'returning_customers' => $returning,
            'top_products'        => $top_products,
        ];

    }

    /**
     * Get catalog/inventory data.
     *
     * @since 5.0.0
     * @return array
     */
    public function get_catalog_data(): array {
        $total_products = wp_count_posts( 'product' );
        $active         = isset( $total_products->publish ) ? (int) $total_products->publish : 0;
        $draft          = isset( $total_products->draft ) ? (int) $total_products->draft : 0;

        global $wpdb;

        $out_of_stock = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_stock_status'
             AND pm.meta_value = 'outofstock'"
        );

        $low_stock_threshold = get_option( 'woocommerce_notify_low_stock_amount', 2 );
        $low_stock = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id
             INNER JOIN {$wpdb->postmeta} pm_manage ON p.ID = pm_manage.post_id
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             AND pm_stock.meta_key = '_stock'
             AND pm_manage.meta_key = '_manage_stock'
             AND pm_manage.meta_value = 'yes'
             AND CAST(pm_stock.meta_value AS SIGNED) <= %d
             AND CAST(pm_stock.meta_value AS SIGNED) > 0",
            $low_stock_threshold
        ) );

        $low_stock_products = [];
        if ( $low_stock > 0 ) {
            $low_stock_items = $wpdb->get_results( $wpdb->prepare(
                "SELECT p.ID, p.post_title, pm_stock.meta_value as stock
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id
                 INNER JOIN {$wpdb->postmeta} pm_manage ON p.ID = pm_manage.post_id
                 WHERE p.post_type = 'product'
                 AND p.post_status = 'publish'
                 AND pm_stock.meta_key = '_stock'
                 AND pm_manage.meta_key = '_manage_stock'
                 AND pm_manage.meta_value = 'yes'
                 AND CAST(pm_stock.meta_value AS SIGNED) <= %d
                 AND CAST(pm_stock.meta_value AS SIGNED) > 0
                 ORDER BY CAST(pm_stock.meta_value AS SIGNED) ASC
                 LIMIT 20",
                $low_stock_threshold
            ) );

            foreach ( $low_stock_items as $item ) {
                $low_stock_products[] = [
                    'id'    => (int) $item->ID,
                    'name'  => $item->post_title,
                    'stock' => (int) $item->stock,
                ];
            }
        }

        $categories_count = wp_count_terms( [ 'taxonomy' => 'product_cat' ] );
        if ( is_wp_error( $categories_count ) ) {
            $categories_count = 0;
        }

        return [
            'total_products'      => $active + $draft,
            'active_products'     => $active,
            'out_of_stock_count'  => $out_of_stock,
            'low_stock_count'     => $low_stock,
            'low_stock_products'  => $low_stock_products,
            'draft_products'      => $draft,
            'categories_count'    => (int) $categories_count,
        ];
    }

    /**
     * Get historical data for backfill.
     *
     * @since 5.0.0
     * @param string $start_date Start date Y-m-d.
     * @param string $end_date   End date Y-m-d.
     * @return array Array of daily data keyed by date.
     */
    public function get_historical_data( string $start_date, string $end_date ): array {
        $data    = [];
        $current = strtotime( $start_date );
        $end     = strtotime( $end_date );

        while ( $current <= $end ) {
            $date = gmdate( 'Y-m-d', $current );
            $data[ $date ] = [
                'sales' => $this->get_sales_data( $date ),
            ];
            $current = strtotime( '+1 day', $current );
        }

        return $data;
    }

    /**
     * Get total order count for estimating backfill size.
     *
     * @since 5.0.0
     * @param string $start_date Start date Y-m-d.
     * @return int
     */
    public function get_order_count_since( string $start_date ): int {
        $orders = wc_get_orders( [
            'date_created' => '>=' . $start_date,
            'status'       => [ 'completed', 'processing', 'refunded' ],
            'limit'        => -1,
            'return'       => 'ids',
        ] );

        return count( $orders );
    }

}
