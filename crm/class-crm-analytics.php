<?php
/**
 * CRM Analytics.
 *
 * Main orchestrator for CRM analytics - handles settings, cron, and coordination.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\CRM;

if ( ! defined( 'WPINC' ) ) { die; }

class crm_analytics {

    /**
     * Cron hook name.
     *
     * @since 5.0.0
     * @var string
     */
    const CRON_HOOK = 'builtmightykit_crm_daily_report';

    /**
     * Instance.
     *
     * @since 5.0.0
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * API client.
     *
     * @since 5.0.0
     * @var crm_api
     */
    private crm_api $api;

    /**
     * WooCommerce collector.
     *
     * @since 5.0.0
     * @var crm_woocommerce
     */
    private crm_woocommerce $woo;

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
     * Constructor.
     *
     * @since 5.0.0
     */
    public function __construct() {
        $this->api = crm_api::get_instance();
        $this->woo = crm_woocommerce::get_instance();

        add_action( 'init', [ $this, 'schedule_cron' ] );
        add_action( self::CRON_HOOK, [ $this, 'send_daily_report' ] );
        add_action( self::CRON_HOOK, [ $this, 'send_inventory' ] );
        // Settings live on Kit's Monitoring page (6.0); no legacy settings section.
        add_action( 'wp_ajax_kit_crm_register', [ $this, 'ajax_register' ] );
        add_action( 'wp_ajax_kit_crm_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_kit_crm_run_backfill', [ $this, 'ajax_run_backfill' ] );
        add_action( 'kit_crm_backfill_chunk', [ $this, 'run_backfill_chunk' ] );
        add_action( 'wp_ajax_kit_crm_send_report_now', [ $this, 'ajax_send_report_now' ] );
        add_filter( 'pre_update_option_kit_crm_api_key', [ $this, 'preserve_api_key' ], 10, 2 );
    }

    /**
     * Keep the stored API key when the settings form posts an empty value —
     * the key field intentionally renders blank.
     *
     * @since 5.1.0
     * @param mixed $new New option value.
     * @param mixed $old Existing option value.
     * @return mixed
     */
    public function preserve_api_key( $new, $old ) {
        return empty( $new ) ? $old : sanitize_text_field( $new );
    }

    /**
     * Schedule the daily cron job.
     *
     * @since 5.0.0
     */
    public function schedule_cron(): void {
        if ( ! $this->is_enabled() ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            return;
        }

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // 12:30 AM in the site's time zone, not UTC.
            $time = ( new \DateTime( 'tomorrow 00:30', wp_timezone() ) )->getTimestamp();
            wp_schedule_event( $time, 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Check if CRM analytics is enabled.
     *
     * @since 5.0.0
     * @return bool
     */
    public function is_enabled(): bool {
        // Live store only: a staging copy carries the same API key and would report test orders.
        return ! \BuiltMightyKit\is_kit_mode()
            && get_option( 'kit_crm_enabled', '' ) === 'enable'
            && $this->api->is_configured();
    }

    /**
     * Send the daily analytics report.
     *
     * @since 5.0.0
     */
    public function send_daily_report(): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Yesterday's real-visitor page speed, if collected.
        if ( crm_rum::get_instance()->is_enabled() ) {
            crm_rum::get_instance()->send_yesterday( $this->api );
        }

        // Collect WooCommerce data if available, otherwise send basic report
        if ( $this->woo->is_woocommerce_active() ) {
            $data = $this->woo->collect_daily_data();

            if ( isset( $data['error'] ) ) {
                error_log( 'Built Mighty Kit Performance: Error collecting data - ' . $data['error'] );
                return;
            }
        } else {
            // Basic report for non-WooCommerce sites (GA tracking only)
            $data = $this->get_basic_report_data();
        }

        $result = $this->api->send_daily_report( $data );

        if ( ! $result['success'] ) {
            error_log( 'Built Mighty Kit Performance: Failed to send report - ' . ( $result['error'] ?? 'Unknown error' ) );
        } else {
            update_option( 'kit_crm_last_report', current_time( 'mysql' ) );
        }
    }

    /**
     * Send the installed-software inventory (core, plugins, themes).
     *
     * Hooked to the daily cron alongside the report, so inventory still goes
     * out even when report collection fails.
     *
     * @since 5.1.0
     */
    public function send_inventory(): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        $inventory = crm_inventory::get_instance()->collect();
        $result    = $this->api->send_inventory( $inventory );

        if ( ! $result['success'] ) {
            error_log( 'Built Mighty Kit Performance: Failed to send inventory - ' . ( $result['error'] ?? 'Unknown error' ) );
        } else {
            update_option( 'kit_crm_last_inventory', current_time( 'mysql' ) );
        }
    }

    /**
     * Get basic report data for non-WooCommerce sites.
     *
     * @since 5.0.0
     * @return array
     */
    private function get_basic_report_data(): array {
        return [
            'sales' => [
                'revenue'             => 0,
                'order_count'         => 0,
                'average_order_value' => 0,
            ],
            'catalog' => [
                'total_products'   => 0,
                'active_products'  => 0,
                'out_of_stock_count' => 0,
            ],
            'is_woocommerce' => false,
        ];
    }

    /**
     * AJAX: Register site with CRM.
     *
     * @since 5.0.0
     */
    public function ajax_register(): void {
        check_ajax_referer( 'kit_crm_register', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $result = $this->api->register_site();

        if ( $result['success'] && isset( $result['data']['api_key'] ) ) {
            update_option( 'kit_crm_api_key', sanitize_text_field( $result['data']['api_key'] ) );
            wp_send_json_success( 'Site registered successfully' );
        } else {
            wp_send_json_error( $result['error'] ?? 'Registration failed' );
        }
    }

    /**
     * AJAX: Test CRM connection.
     *
     * @since 5.0.0
     */
    public function ajax_test_connection(): void {
        check_ajax_referer( 'kit_crm_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $result = $this->api->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( $result['error'] ?? 'Connection failed' );
        }
    }

    /**
     * AJAX: Send report immediately.
     *
     * @since 5.0.0
     */
    public function ajax_send_report_now(): void {
        check_ajax_referer( 'kit_crm_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        // Collect WooCommerce data if available, otherwise send basic report
        if ( $this->woo->is_woocommerce_active() ) {
            $data = $this->woo->collect_daily_data();
        } else {
            $data = $this->get_basic_report_data();
        }

        $result = $this->api->send_daily_report( $data );

        if ( $result['success'] ) {
            update_option( 'kit_crm_last_report', current_time( 'mysql' ) );
            $this->send_inventory();
            wp_send_json_success();
        } else {
            wp_send_json_error( $result['error'] ?? 'Failed to send report' );
        }
    }

    /**
     * AJAX: Run historical backfill.
     *
     * @since 5.0.0
     */
    public function ajax_run_backfill(): void {
        check_ajax_referer( 'kit_crm_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        if ( ! $this->woo->is_woocommerce_active() ) {
            wp_send_json_error( 'WooCommerce not active' );
        }

        // Queue 30-day chunks in the background instead of 730 days in one request.
        $end   = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
        $start = wp_date( 'Y-m-d', strtotime( '-2 years' ) );
        $total = (int) ceil( ( strtotime( $end ) - strtotime( $start ) ) / ( 30 * DAY_IN_SECONDS ) );

        update_option( 'kit_crm_backfill', [ 'start' => $start, 'end' => $end, 'chunk' => 0, 'total' => $total, 'sent' => 0 ], false );
        $this->schedule_backfill_chunk();

        wp_send_json_success( sprintf( 'Sending %d months of sales in the background. This takes a few minutes.', $total ) );
    }

    /**
     * Schedule the next backfill chunk.
     *
     * @since 6.0.0
     */
    private function schedule_backfill_chunk(): void {
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'kit_crm_backfill_chunk', [], 'builtmighty-kit' );
        } else {
            wp_schedule_single_event( time() + 5, 'kit_crm_backfill_chunk' );
        }
    }

    /**
     * Send one 30-day chunk, then queue the next.
     *
     * @since 6.0.0
     */
    public function run_backfill_chunk(): void {

        $state = get_option( 'kit_crm_backfill' );
        if ( ! is_array( $state ) || $state['chunk'] >= $state['total'] ) return;

        // Pure calendar math on Y-m-d strings: strtotime() reads them as UTC midnight, so format with gmdate()
        // (wp_date() would shift them by the site's offset and skip a day per chunk west of UTC).
        $from = gmdate( 'Y-m-d', strtotime( $state['start'] . ' UTC' ) + $state['chunk'] * 30 * DAY_IN_SECONDS );
        $to   = gmdate( 'Y-m-d', min( strtotime( $state['end'] . ' UTC' ), strtotime( $from . ' UTC' ) + 29 * DAY_IN_SECONDS ) );

        $data   = $this->woo->get_historical_data( $from, $to );
        $result = $this->api->send_backfill( $data, $state['chunk'] + 1, $state['total'] );
        if ( ! empty( $result['success'] ) ) $state['sent']++;

        $state['chunk']++;
        update_option( 'kit_crm_backfill', $state, false );

        if ( $state['chunk'] < $state['total'] ) {
            $this->schedule_backfill_chunk();
        } else {
            update_option( 'kit_crm_backfill_completed', current_time( 'mysql' ) );
        }

    }

    /**
     * Deactivation cleanup.
     *
     * @since 5.0.0
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

}
