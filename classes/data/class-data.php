<?php
/**
 * Data.
 * 
 * Handles data tracking for the plugin.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Data;
use function BuiltMightyKit\is_kit_mode;
class builtData {

    /**
     * Database.
     * 
     * @since   2.0.0
     */
    private $db;

    /**
     * Prefix.
     * 
     * @since   2.0.0
     */
    private $prefix;

    /**
     * Construct.
     * 
     * Initialize the class.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // Only run on production sites.
        if( is_kit_mode() ) return;

        // Globals.
        global $wpdb, $table_prefix;

        // Set database.
        $this->db = $wpdb;

        // Set prefix.
        $this->prefix = $table_prefix;

        // On initialize.
        add_action( 'wp', [ $this, 'schedule_data' ] );
        add_action( 'data_ingest', [ $this, 'execute_data_ingest' ] );
        
    }

    /**
     * Schedule data event.
     * 
     * @since   2.0.0
     */
    public function schedule_data() {

        // Check if event is scheduled.
        if( ! wp_next_scheduled( 'data_ingest' ) ) {

            // Schedule the event.
            wp_schedule_event( time(), 'daily', 'data_ingest' );

        }

        // Get pagespeed.
        $this->execute_data_ingest();

    }

    /**
     * Execute data ingest.
     * 
     * @since   2.0.0
     */
    public function execute_data_ingest() {

        // Process Pagespeed Insights.
        $this->process_pagespeed();

        // Check for WooCommerce.
        if( ! class_exists( 'WooCommerce' ) ) return;

        // Process WooCommerce.
        $this->process_woocommerce();

    }

    /**
     * Process Pagespeed data.
     * 
     * @since   2.0.0
     */
    public function process_pagespeed() {

        // Set pagespeed.
        $pagespeed = [];

        // Loop through keys.
        foreach( $this->get_pagespeed_keys() as $key ) {

            // Check if key exists.
            if( $this->check_data( $key, date( 'Y-m-d' ) ) ) continue;

            // Get Pagespeed if not set.
            if( empty( $pagespeed ) ) $pagespeed = $this->get_pagespeed();

            // Check if key exists.
            if( ! isset( $pagespeed['originLoadingExperience']['metrics'][$key]['percentile'] ) ) continue;

            // Compose data.
            $data = [
                'name'  => strtolower( $key ),
                'value' => strtolower( $pagespeed['originLoadingExperience']['metrics'][$key]['percentile'] ),
                'date'  => date( 'Y-m-d' )
            ];

            // Insert data.
            $this->insert_data( $data );

        }

        // Check if Pagespeed is set.
        if( empty( $pagespeed ) ) return;

        // Check for overall data.
        if( $this->check_data( 'overall_category', date( 'Y-m-d' ) ) ) return;

        // Compose data.
        $data = [
            'name'  => 'overall_category',
            'value' => strtolower( $pagespeed['originLoadingExperience']['overall_category'] ),
            'date'  => date( 'Y-m-d' )
        ];

        // Insert data.
        $this->insert_data( $data );

    }

    /**
     * Process WooCommerce data.
     * 
     * @since   2.0.0
     */
    public function process_woocommerce() {

        // Loop through keys.
        foreach( $this->get_woocommerce_keys() as $key ) {

            // Get previous day.
            $day = date( 'Y-m-d', strtotime( '-1 day' ) );

            // Check if key exists.
            if( $this->check_data( $key, $day ) ) continue;

            // Get WooCommerce.
            $woocommerce = $this->get_woocommerce( $key );

            // Check if key exists.
            if( ! isset( $woocommerce ) ) continue;

            // Compose data.
            $data = [
                'name'  => strtolower( $key ),
                'value' => strtolower( $woocommerce ),
                'date'  => $day
            ];

            // Insert data.
            $this->insert_data( $data );

        }

    }

    /**
     * Get PageSpeed Insights data.
     * 
     * @since   2.0.0
     */
    public function get_pagespeed() {

        // Set args.
        $args = [
            'headers' => [
                'Accept' => 'application/json'
            ],
            'timeout' => 60
        ];

        // WP Remote GET.
        $response = wp_remote_get( 'https://builtmighty.com/wp-json/builtmighty-kit/v1/pagespeed?site=https://builtmighty.com', $args );

        // Return body.
        return json_decode( wp_remote_retrieve_body( $response ), true );

    }

    /**
     * Get WooCommerce data.
     * 
     * @since   2.0.0
     */
    public function get_woocommerce( $key, $day = NULL ) {

        // Get day.
        $day = ( empty( $day ) ) ? date( 'Y-m-d', strtotime( '-1 day' ) ) : $day;

        // Switch key.
        switch( $key ) {
            case 'revenue':
                return $this->get_revenue( $day );
            case 'orders':
                return $this->get_orders( $day );
            case 'refunds':
                return $this->get_refunds( $day );
            case 'refunded':
                return $this->get_refunded( $day );
            case 'products':
                return $this->get_products();
            case 'customers':
                return $this->get_customers();
        }

        // Return.
        return false;

    }

    /**
     * Get total sales.
     * 
     * @since   2.0.0
     * 
     * @param   string  $day    Day.
     * @return  float
     */
    public function get_revenue( $day ) {

        // Set start/end.
        $start  = $day . ' 00:00:00';
        $end    = $day . ' 23:59:59';

        // Set args.
        $args = [
            'status'        => [ 'processing', 'completed' ],
            'limit'         => -1,
            'date_created'  => $start . '...' . $end,
            'return'        => 'ids'
        ];

        // Set revenue.
        $revenue = 0;

        // Get orders.
        $orders = wc_get_orders( $args );

        // Loop through orders.
        foreach( $orders as $order_id ) {

            // Get order.
            $order = wc_get_order( $order_id );

            // Add to revenue.
            $revenue += $order->get_total();

        }

        // Return.
        return (float)$revenue;

    }

    /**
     * Get orders.
     * 
     * @since   2.0.0
     * 
     * @param   string  $day    Day.
     * @return  int
     */
    public function get_orders( $day ) {

        // Set start/end.
        $start  = $day . ' 00:00:00';
        $end    = $day . ' 23:59:59';

        // Set args.
        $args = [
            'status'        => [ 'processing', 'completed' ],
            'limit'         => -1,
            'date_created'  => $start . '...' . $end,
            'return'        => 'ids'
        ];

        // Get and return order count.
        return (int)count( wc_get_orders( $args ) );

    }

    /**
     * Get refunds.
     * 
     * @since   2.0.0
     * 
     * @param   string  $day    Day.
     * @return  int
     */
    public function get_refunds( $day ) {

        // Set start/end.
        $start  = $day . ' 00:00:00';
        $end    = $day . ' 23:59:59';

        // Set args.
        $args = [
            'status'        => 'refunded',
            'limit'         => -1,
            'date_created'  => $start . '...' . $end,
            'return'        => 'ids'
        ];

        // Set revenue.
        $revenue = 0;

        // Get orders.
        $orders = wc_get_orders( $args );

        // Loop through orders.
        foreach( $orders as $order_id ) {

            // Get order.
            $order = wc_get_order( $order_id );

            // Add to revenue.
            $revenue += $order->get_total();

        }

        // Return.
        return (float)$revenue;

    }

    /**
     * Get refunded.
     * 
     * @since   2.0.0
     * 
     * @param   string  $day    Day.
     * @return  int
     */
    public function get_refunded( $day ) {

        // Set start/end.
        $start  = $day . ' 00:00:00';
        $end    = $day . ' 23:59:59';

        // Set args.
        $args = [
            'status'        => 'refunded',
            'limit'         => -1,
            'date_created'  => $start . '...' . $end,
            'return'        => 'ids'
        ];

        // Get and return refund count.
        return (int)count( wc_get_orders( $args ) );

    }

    /**
     * Get products.
     * 
     * @since   2.0.0
     * 
     * @param   string  $day    Day.
     * @return  int
     */
    public function get_products() {

        // Set args.
        $args = [
            'limit'         => -1,
            'return'        => 'ids'
        ];

        // Get and return product count.
        return (int)count( wc_get_products( $args ) );

    }

    /**
     * Get customers.
     * 
     * @since   2.0.0
     * 
     * @return  int
     */
    public function get_customers() {

        // Set args.
        $args = [
            'role__in'  => [ 'subscriber', 'customer' ],
            'fields'    => 'ID',
            'number'    => -1
        ];

        // Query.
        $users = new \WP_User_query( $args );

        // Return.
        return (int)$users->get_total();

    }

    /**
     * Check if data exists.
     * 
     * @since   2.0.0
     */
    public function check_data( $name, $date ) {

        // Get data.
        $data = $this->db->get_row( "SELECT * FROM " . $this->prefix . "built_site_data WHERE name = '" . $name . "' AND date = '" . $date . "'" );

        // Return.
        return ( empty( $data ) ) ? false : true;

    }

    /**
     * Insert data.
     * 
     * @since   2.0.0
     */
    public function insert_data( $data ) {

        // Insert data.
        $this->db->insert( $this->prefix . 'built_site_data', $data );

    }

    /**
     * Pagespeed keys.
     * 
     * @since   2.0.0
     */
    public function get_pagespeed_keys() {

        // Return.
        return [
            'CUMULATIVE_LAYOUT_SHIFT_SCORE',
            'EXPERIMENTAL_TIME_TO_FIRST_BYTE',
            'FIRST_CONTENTFUL_PAINT_MS',
            'INTERACTION_TO_NEXT_PAINT',
            'LARGEST_CONTENTFUL_PAINT_MS',
        ];

    }

    /**
     * WooCommerce keys.
     * 
     * @since   2.0.0
     */
    public function get_woocommerce_keys() {

        // Return.
        return [
            'revenue',
            'orders',
            'refunds',
            'refunded',
            'products',
            'customers',
        ];

    }

}