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
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_kit_crm_register', [ $this, 'ajax_register' ] );
        add_action( 'wp_ajax_kit_crm_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_kit_crm_run_backfill', [ $this, 'ajax_run_backfill' ] );
        add_action( 'wp_ajax_kit_crm_send_report_now', [ $this, 'ajax_send_report_now' ] );
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
            $time = strtotime( 'tomorrow 12:30 AM' );
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
        return get_option( 'kit_crm_enabled', '' ) === 'enable'
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
     * Register settings with the Built Mighty settings framework.
     *
     * @since 5.0.0
     */
    public function register_settings(): void {
        if ( ! class_exists( '\BuiltMighty\GlobalSettings\settings' ) ) {
            return;
        }

        $settings = \BuiltMighty\GlobalSettings\settings::get_instance();

        $settings->add_settings_section(
            'builtmighty_crm',
            'Performance Monitoring',
            function() {
                echo '<p>Track site performance metrics including Google Analytics traffic, Core Web Vitals, and WooCommerce sales (if active).</p>';
            }
        );

        $settings->radio_field(
            'kit_crm_enabled',
            'Performance Monitoring',
            'builtmighty_crm',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Enable performance monitoring and analytics reporting.',
            true
        );

        if ( empty( get_option( 'kit_crm_api_key' ) ) ) {
            $this->render_register_button( $settings );
        } else {
            $this->render_connection_status( $settings );
        }

        $settings->text_field(
            'kit_crm_ga_property',
            'Google Analytics Property ID',
            'builtmighty_crm',
            'Optional: Enter your GA4 property ID (e.g., 523123687). Grant Viewer access to developers@builtmighty.com in your GA property settings.',
            true
        );

        $settings->radio_field(
            'kit_crm_rum_enabled',
            'Real User Monitoring',
            'builtmighty_crm',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Capture Core Web Vitals from real user visits.',
            true
        );
    }

    /**
     * Render the register button.
     *
     * @since 5.0.0
     * @param object $settings Settings instance.
     */
    private function render_register_button( $settings ): void {
        $settings->add_settings_field( 'kit_crm_register', '', function() {
            $nonce = wp_create_nonce( 'kit_crm_register' );
            ?>
            <div class="builtmighty-field">
                <span class="builtmighty-field-label">Register Site</span>
                <div class="builtmighty-field_inner">
                    <button type="button" id="kit-crm-register" class="button button-primary" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        Connect Site
                    </button>
                    <span id="kit-crm-register-status" style="margin-left: 10px;"></span>
                </div>
                <p class="description">Click to connect this site for performance monitoring.</p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#kit-crm-register').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#kit-crm-register-status');
                    $btn.prop('disabled', true).text('Connecting...');
                    $status.text('');

                    $.post(ajaxurl, {
                        action: 'kit_crm_register',
                        nonce: $btn.data('nonce')
                    }, function(response) {
                        if (response.success) {
                            $status.html('<span style="color:green;">✓ Connected! Reloading...</span>');
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            $status.html('<span style="color:red;">✗ ' + response.data + '</span>');
                            $btn.prop('disabled', false).text('Connect Site');
                        }
                    }).fail(function() {
                        $status.html('<span style="color:red;">✗ Request failed</span>');
                        $btn.prop('disabled', false).text('Connect Site');
                    });
                });
            });
            </script>
            <?php
        }, 'builtmighty_crm' );
    }

    /**
     * Render connection status and controls.
     *
     * @since 5.0.0
     * @param object $settings Settings instance.
     */
    private function render_connection_status( $settings ): void {
        $settings->add_settings_field( 'kit_crm_status', '', function() {
            $api_key = get_option( 'kit_crm_api_key' );
            $last_report = get_option( 'kit_crm_last_report', 'Never' );
            $nonce = wp_create_nonce( 'kit_crm_actions' );
            $has_woo = $this->woo->is_woocommerce_active();
            ?>
            <div class="builtmighty-field">
                <span class="builtmighty-field-label">Connection Status</span>
                <div class="builtmighty-field_inner" style="display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <strong>API Key:</strong>
                        <code><?php echo esc_html( substr( $api_key, 0, 8 ) . '...' . substr( $api_key, -4 ) ); ?></code>
                        <span id="kit-crm-connection-status" style="margin-left: 10px;"></span>
                    </div>
                    <div>
                        <strong>Last Report:</strong> <?php echo esc_html( $last_report ); ?>
                    </div>
                    <?php if ( ! $has_woo ) : ?>
                    <div>
                        <em>WooCommerce not detected - only Google Analytics and Core Web Vitals will be tracked.</em>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; gap: 10px; margin-top: 5px;">
                        <button type="button" id="kit-crm-test" class="button" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                            Test Connection
                        </button>
                        <button type="button" id="kit-crm-send-now" class="button" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                            Send Report Now
                        </button>
                        <?php if ( $has_woo ) : ?>
                        <button type="button" id="kit-crm-backfill" class="button" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                            Run Historical Backfill
                        </button>
                        <?php endif; ?>
                    </div>
                    <div id="kit-crm-action-status"></div>
                </div>
            </div>
            <script>
            jQuery(document).ready(function($) {
                var $status = $('#kit-crm-action-status');

                $('#kit-crm-test').on('click', function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $status.html('<em>Testing connection...</em>');

                    $.post(ajaxurl, {
                        action: 'kit_crm_test_connection',
                        nonce: $btn.data('nonce')
                    }, function(response) {
                        $status.html(response.success
                            ? '<span style="color:green;">✓ Connection successful!</span>'
                            : '<span style="color:red;">✗ ' + response.data + '</span>');
                        $btn.prop('disabled', false);
                    });
                });

                $('#kit-crm-send-now').on('click', function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $status.html('<em>Sending report...</em>');

                    $.post(ajaxurl, {
                        action: 'kit_crm_send_report_now',
                        nonce: $btn.data('nonce')
                    }, function(response) {
                        $status.html(response.success
                            ? '<span style="color:green;">✓ Report sent!</span>'
                            : '<span style="color:red;">✗ ' + response.data + '</span>');
                        $btn.prop('disabled', false);
                    });
                });

                $('#kit-crm-backfill').on('click', function() {
                    if (!confirm('This will send historical order data (up to 2 years) for analysis. Continue?')) {
                        return;
                    }
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $status.html('<em>Starting backfill... This may take a while.</em>');

                    $.post(ajaxurl, {
                        action: 'kit_crm_run_backfill',
                        nonce: $btn.data('nonce')
                    }, function(response) {
                        $status.html(response.success
                            ? '<span style="color:green;">✓ ' + response.data + '</span>'
                            : '<span style="color:red;">✗ ' + response.data + '</span>');
                        $btn.prop('disabled', false);
                    });
                });
            });
            </script>
            <?php
        }, 'builtmighty_crm' );
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

        $start_date = gmdate( 'Y-m-d', strtotime( '-2 years' ) );
        $end_date   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

        $data = $this->woo->get_historical_data( $start_date, $end_date );

        $chunks = array_chunk( $data, 30, true );
        $total_chunks = count( $chunks );
        $success_count = 0;

        foreach ( $chunks as $index => $chunk ) {
            $result = $this->api->send_backfill( $chunk, $index + 1, $total_chunks );
            if ( $result['success'] ) {
                $success_count++;
            }
            usleep( 500000 );
        }

        if ( $success_count === $total_chunks ) {
            update_option( 'kit_crm_backfill_completed', current_time( 'mysql' ) );
            wp_send_json_success( "Backfill complete! Sent {$total_chunks} chunks of data." );
        } else {
            wp_send_json_error( "Partial success: {$success_count}/{$total_chunks} chunks sent." );
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
