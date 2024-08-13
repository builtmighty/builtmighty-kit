<?php
/**
 * Data API.
 * 
 * Deliver data via API to external sources.
 * 
 * @package Built Mighty Kit
 * @since   2.2.0
 */
namespace BuiltMightyKit\Data;
use function BuiltMightyKit\is_kit_mode;
class builtDataAPI {

    /**
     * Construct.
     * 
     * Initialize the class.
     * 
     * @since   2.2.0
     */
    public function __construct() {

        // Register API.
        add_action( 'rest_api_init', [ $this, 'routes' ] );

    }

    /**
     * Routes.
     * 
     * @since   2.2.0
     */
    public function routes() {

        // Register.
        register_rest_route( 'builtmighty-kit/v1', '/data', [
            'methods'   => 'GET',
            'callback'  => [ $this, 'data' ],
            'permission_callback' => '__return_true'
        ] );

    }

    /**
     * Data.
     * 
     * @since   2.2.0
     */
    public function data( $request ) {

        // Check if kit mode.
        if( is_kit_mode() ) return new \WP_Error( 'kit_mode', 'Kit mode is enabled. API access denied.', [ 'status' => 403 ] );

        // Check for a request.
        if( ! $request ) return new \WP_Error( 'no_request', 'No request found.', [ 'status' => 404 ] );

        // Get report.
        $report = $request->get_param( 'report' );

        // Check if report is set.
        if( ! isset( $report ) ) return new \WP_Error( 'no_report', 'Please provide a report. Valid reports are: ' . implode( ', ', (array)$this->get_reports() ), [ 'status' => 404 ] );

        // Check for a valid report.
        if( ! in_array( $report, (array)$this->get_reports() ) ) return new \WP_Error( 'invalid_report', 'Invalid report. Valid reports are: ' . implode( ', ', (array)$this->get_reports() ), [ 'status' => 404 ] );

        // Get type.
        $type = $request->get_param( 'type' );

        // Set response.
        $response = [];

        // Check type.
        if( $type == 'total' ) {

            // Get total.
            $response = $this->get_total( $request );
            
        } elseif( $type == 'average' ) {

            // Get average.
            $response = $this->get_average( $request );

        } elseif( $type == 'date' ) {

            // Check if date is set.
            if( ! $request->get_param( 'from' ) || ! $request->get_param( 'to' ) ) return new \WP_Error( 'no_date', 'Please provide a date range using from and to.', [ 'status' => 404 ] );

            // Get date.
            $response = $this->get_date( $request );

        } else {

            // Get current month.
            $response = $this->get_current( $request );

        }

        // Return data.
        return wp_send_json( (array)$response, 200 );

    }

    /**
     * Get total.
     * 
     * @since   2.2.0
     */
    public function get_total( $request ) {

        // Get wpdb.
        global $wpdb;

        // Get report.
        $report = $request->get_param( 'report' );

        // Query.
        $query = "SELECT SUM( value ) FROM {$wpdb->prefix}built_site_data WHERE name = '{$report}'";

        // Check if date is set.
        if( $request->get_param( 'from' ) && $request->get_param( 'to' ) ) {

            // Get date.
            $start  = $request->get_param( 'from' );
            $end    = $request->get_param( 'to' );

            // Add range.
            $query .= $this->get_range( $start, $end );

        }

        // Return total.
        return $wpdb->get_var( $query );

    }

    /**
     * Get average.
     * 
     * @since   2.2.0
     * 
     * @param   object  $request    The request object.
     */
    public function get_average( $request ) {

        // Get wpdb.
        global $wpdb;

        // Get report.
        $report = $request->get_param( 'report' );

        // Query.
        $query = "SELECT AVG( value ) FROM {$wpdb->prefix}built_site_data WHERE name = '{$report}'";

        // Check if date is set.
        if( $request->get_param( 'from' ) && $request->get_param( 'to' ) ) {

            // Get date.
            $start  = $request->get_param( 'from' );
            $end    = $request->get_param( 'to' );

            // Add range.
            $query .= $this->get_range( $start, $end );

        }

        // Return average.
        return $wpdb->get_var( $query );

    }

    /**
     * Get date.
     * 
     * @since   2.2.0
     * 
     * @param   object  $request    The request object.
     */
    public function get_date( $request ) {

        // Get wpdb.
        global $wpdb;

        // Get report.
        $report = $request->get_param( 'report' );

        // Get date.
        $start  = $request->get_param( 'from' );
        $end    = $request->get_param( 'to' );

        // Query.
        $query = "SELECT `value`, `date` FROM {$wpdb->prefix}built_site_data WHERE name = '{$report}' AND date BETWEEN '{$start}' AND '{$end}'";

        // Return date.
        return $wpdb->get_results( $query, ARRAY_A );

    }

    /**
     * Get current.
     * 
     * @since   2.2.0
     * 
     * @param   object  $request    The request object.
     */
    public function get_current( $request ) {

        // Get wpdb.
        global $wpdb;

        // Get report.
        $report = $request->get_param( 'report' );

        // Set current month start and end.
        $start  = date( 'Y-m-01' );
        $end    = date( 'Y-m-t' );

        // Query.
        $query = "SELECT `value`, `date` FROM {$wpdb->prefix}built_site_data WHERE name = '{$report}' AND date BETWEEN '{$start}' AND '{$end}'";

        // Return current.
        return $wpdb->get_results( $query, ARRAY_A );

    }

    /**
     * Get range.
     * 
     * @since   2.2.0
     */
    public function get_range( $start, $end ) {

        // Return.
        return " AND date BETWEEN '{$start}' AND '{$end}'";

    }

    /**
     * Get reports.
     * 
     * @since   2.2.0
     */
    public function get_reports() {

        // Reports.
        $reports = [
            'cumulative_layout_shift_score',
            'experimental_time_to_first_byte',
            'first_contentful_paint_ms',
            'interaction_to_next_paint',
            'largest_contentful_paint_ms',
            'overall_category'
        ];

        // Check if WooCommerce is active.
        if( class_exists( 'WooCommerce' ) ) {

            // Add WooCommerce reports.
            $reports = array_merge( $reports, [
                'revenue',
                'orders',
                'refunds',
                'refunded',
                'products',
                'customers'
            ] );

        }

        // Return reports.
        return $reports;

    }

}