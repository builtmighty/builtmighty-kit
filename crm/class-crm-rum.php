<?php
/**
 * CRM Real User Monitoring.
 *
 * Handles RUM script injection and beacon processing.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\CRM;

if ( ! defined( 'WPINC' ) ) { die; }

class crm_rum {

    /**
     * Instance.
     *
     * @since 5.0.0
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Collected metrics for the day.
     *
     * @since 5.0.0
     * @var array
     */
    private array $daily_metrics = [];

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
        if ( $this->is_enabled() ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_rum_script' ] );
        }

        add_action( 'wp_ajax_kit_crm_rum_beacon', [ $this, 'process_beacon' ] );
        add_action( 'wp_ajax_nopriv_kit_crm_rum_beacon', [ $this, 'process_beacon' ] );
    }

    /**
     * Check if RUM is enabled.
     *
     * @since 5.0.0
     * @return bool
     */
    public function is_enabled(): bool {
        return get_option( 'kit_crm_rum_enabled', '' ) === 'enable'
            && get_option( 'kit_crm_enabled', '' ) === 'enable'
            && ! empty( get_option( 'kit_crm_api_key' ) );
    }

    /**
     * Enqueue the RUM script on frontend.
     *
     * @since 5.0.0
     */
    public function enqueue_rum_script(): void {
        if ( is_admin() || $this->is_bot() ) {
            return;
        }

        wp_enqueue_script(
            'builtmighty-kit-rum',
            KIT_URI . 'assets/js/rum.js',
            [],
            KIT_VERSION,
            [
                'strategy' => 'defer',
                'in_footer' => true,
            ]
        );

        wp_localize_script( 'builtmighty-kit-rum', 'kitRumConfig', [
            'endpoint' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'kit_rum_beacon' ),
        ] );
    }

    /**
     * Check if the current request is from a bot.
     *
     * @since 5.0.0
     * @return bool
     */
    private function is_bot(): bool {
        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return true;
        }

        $user_agent = strtolower( $_SERVER['HTTP_USER_AGENT'] );
        $bot_patterns = [
            'bot', 'crawl', 'spider', 'slurp', 'googlebot', 'bingbot',
            'yandex', 'baidu', 'duckduck', 'facebookexternalhit',
            'linkedinbot', 'twitterbot', 'pingdom', 'pagespeed',
            'lighthouse', 'gtmetrix', 'headless',
        ];

        foreach ( $bot_patterns as $pattern ) {
            if ( strpos( $user_agent, $pattern ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process incoming RUM beacon data.
     *
     * @since 5.0.0
     */
    public function process_beacon(): void {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'kit_rum_beacon' ) ) {
            wp_die( 'Invalid nonce', 403 );
        }

        $metrics_json = sanitize_text_field( $_POST['metrics'] ?? '' );
        if ( empty( $metrics_json ) ) {
            wp_die( 'No metrics', 400 );
        }

        $metrics = json_decode( $metrics_json, true );
        if ( ! is_array( $metrics ) ) {
            wp_die( 'Invalid metrics', 400 );
        }

        $this->store_metrics( $metrics );

        wp_die( 'OK', 200 );
    }

    /**
     * Store metrics for aggregation.
     *
     * @since 5.0.0
     * @param array $metrics The metrics to store.
     */
    private function store_metrics( array $metrics ): void {
        $date = current_time( 'Y-m-d' );
        $key  = 'kit_crm_rum_' . $date;

        $existing = get_transient( $key );
        if ( ! is_array( $existing ) ) {
            $existing = [
                'samples'    => [],
                'device'     => [ 'mobile' => 0, 'desktop' => 0, 'tablet' => 0 ],
                'connection' => [],
            ];
        }

        $sample = [
            'lcp'  => isset( $metrics['lcp'] ) ? (int) $metrics['lcp'] : null,
            'fid'  => isset( $metrics['fid'] ) ? (int) $metrics['fid'] : null,
            'cls'  => isset( $metrics['cls'] ) ? (int) $metrics['cls'] : null,
            'ttfb' => isset( $metrics['ttfb'] ) ? (int) $metrics['ttfb'] : null,
            'fcp'  => isset( $metrics['fcp'] ) ? (int) $metrics['fcp'] : null,
            'inp'  => isset( $metrics['inp'] ) ? (int) $metrics['inp'] : null,
        ];

        $existing['samples'][] = $sample;

        $device = sanitize_text_field( $metrics['device'] ?? 'desktop' );
        if ( isset( $existing['device'][ $device ] ) ) {
            $existing['device'][ $device ]++;
        }

        $connection = sanitize_text_field( $metrics['connection'] ?? 'unknown' );
        if ( ! isset( $existing['connection'][ $connection ] ) ) {
            $existing['connection'][ $connection ] = 0;
        }
        $existing['connection'][ $connection ]++;

        set_transient( $key, $existing, DAY_IN_SECONDS * 2 );
    }

    /**
     * Get aggregated metrics for a date.
     *
     * @since 5.0.0
     * @param string $date Date in Y-m-d format.
     * @return array|null
     */
    public function get_aggregated_metrics( string $date ): ?array {
        $key = 'kit_crm_rum_' . $date;
        $data = get_transient( $key );

        if ( ! is_array( $data ) || empty( $data['samples'] ) ) {
            return null;
        }

        $samples = $data['samples'];
        $count = count( $samples );

        $metrics_to_aggregate = [ 'lcp', 'fid', 'cls', 'ttfb', 'fcp', 'inp' ];
        $aggregated = [];

        foreach ( $metrics_to_aggregate as $metric ) {
            $values = array_filter( array_column( $samples, $metric ), function( $v ) {
                return $v !== null;
            } );

            if ( empty( $values ) ) {
                $aggregated[ $metric ] = null;
                continue;
            }

            sort( $values );
            $aggregated[ $metric ] = [
                'p50' => $this->percentile( $values, 50 ),
                'p75' => $this->percentile( $values, 75 ),
                'p95' => $this->percentile( $values, 95 ),
            ];
        }

        $total_devices = array_sum( $data['device'] );
        $device_pct = [];
        foreach ( $data['device'] as $type => $count_type ) {
            $device_pct[ $type ] = $total_devices > 0 ? round( ( $count_type / $total_devices ) * 100 ) : 0;
        }

        return [
            'sample_count'    => $count,
            'metrics'         => $aggregated,
            'device_breakdown' => $device_pct,
            'connection_types' => $data['connection'],
        ];
    }

    /**
     * Calculate percentile from sorted array.
     *
     * @since 5.0.0
     * @param array $sorted Sorted array of values.
     * @param int   $percentile Percentile to calculate (0-100).
     * @return int
     */
    private function percentile( array $sorted, int $percentile ): int {
        $count = count( $sorted );
        $index = ( $percentile / 100 ) * ( $count - 1 );
        $lower = (int) floor( $index );
        $upper = (int) ceil( $index );

        if ( $lower === $upper ) {
            return (int) $sorted[ $lower ];
        }

        $fraction = $index - $lower;
        return (int) round( $sorted[ $lower ] + ( $sorted[ $upper ] - $sorted[ $lower ] ) * $fraction );
    }

    /**
     * Clear metrics for a date (after sending to CRM).
     *
     * @since 5.0.0
     * @param string $date Date in Y-m-d format.
     */
    public function clear_metrics( string $date ): void {
        delete_transient( 'kit_crm_rum_' . $date );
    }

}
