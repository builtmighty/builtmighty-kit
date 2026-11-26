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
        // One instance: the loader and get_instance() share it.
        if ( self::$instance !== null ) {
            return;
        }
        self::$instance = $this;

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
        // Live store only: copies share its API key but not its visitors.
        return ! \BuiltMightyKit\is_kit_mode()
            && \BuiltMightyKit\Utility\settings::is_on( 'kit_crm_rum_enabled' )
            && \BuiltMightyKit\Utility\settings::is_on( 'kit_crm_enabled' )
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
     * Histogram bucket edges. Fixed-size storage, whatever the traffic.
     *
     * @since 6.0.0
     */
    private static function edges( string $metric ): array {
        if ( $metric === 'cls' ) {
            // CLS is sent multiplied by 1000.
            return [ 25, 50, 75, 100, 150, 200, 250, 300, 400, 500, 750, 1000, 1500, 2000 ];
        }
        return [ 100, 200, 300, 400, 500, 600, 800, 1000, 1200, 1500, 1800, 2000, 2500, 3000, 3500, 4000, 5000, 6000, 8000, 10000, 15000, 20000 ];
    }

    /**
     * Process incoming RUM beacon data.
     *
     * @since 5.0.0
     * @version 6.0.0
     */
    public function process_beacon(): void {

        if ( ! $this->is_enabled() ) {
            wp_die( '', '', 204 );
        }
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'kit_rum_beacon' ) ) {
            wp_die( 'Invalid nonce', 403 );
        }

        // A visitor sends at most one beacon a page view; cap per IP to keep bots out.
        $hit = \BuiltMightyKit\Utility\request::hit( 'rum', \BuiltMightyKit\Utility\request::client_ip(), 30, HOUR_IN_SECONDS );
        if ( ! $hit['allowed'] ) {
            wp_die( '', '', 429 );
        }

        $metrics = json_decode( wp_unslash( (string) ( $_POST['metrics'] ?? '' ) ), true );
        if ( ! is_array( $metrics ) ) {
            wp_die( 'Invalid metrics', 400 );
        }

        $this->store_metrics( $metrics );

        wp_die( '', '', 204 );

    }

    /**
     * Add one page view to today's histograms.
     *
     * @since 5.0.0
     * @version 6.0.0
     * @param array $metrics The metrics to store.
     */
    private function store_metrics( array $metrics ): void {

        $key  = 'kit_crm_rum_' . wp_date( 'Y-m-d' );
        $data = get_option( $key, null );
        if ( ! is_array( $data ) ) {
            $data = [ 'n' => 0, 'hist' => [], 'device' => [ 'mobile' => 0, 'desktop' => 0, 'tablet' => 0 ], 'connection' => [] ];
        }

        // Sane ranges only (ms, or CLS x 1000).
        $limits = [ 'lcp' => 60000, 'fid' => 10000, 'inp' => 10000, 'ttfb' => 30000, 'fcp' => 60000, 'cls' => 5000 ];
        $counted = false;
        foreach ( $limits as $metric => $max ) {
            if ( ! isset( $metrics[ $metric ] ) || ! is_numeric( $metrics[ $metric ] ) ) continue;
            $value = (int) $metrics[ $metric ];
            if ( $value < 0 || $value > $max ) continue;
            $bucket = count( self::edges( $metric ) );
            foreach ( self::edges( $metric ) as $b => $edge ) {
                if ( $value <= $edge ) { $bucket = $b; break; }
            }
            $data['hist'][ $metric ][ $bucket ] = ( $data['hist'][ $metric ][ $bucket ] ?? 0 ) + 1;
            $counted = true;
        }
        if ( ! $counted ) return;

        $data['n']++;
        $device = sanitize_key( (string) ( $metrics['device'] ?? 'desktop' ) );
        if ( isset( $data['device'][ $device ] ) ) $data['device'][ $device ]++;
        $connection = substr( sanitize_key( (string) ( $metrics['connection'] ?? 'unknown' ) ), 0, 12 );
        if ( count( $data['connection'] ) < 10 || isset( $data['connection'][ $connection ] ) ) {
            $data['connection'][ $connection ] = ( $data['connection'][ $connection ] ?? 0 ) + 1;
        }

        update_option( $key, $data, false );

    }

    /**
     * Aggregated metrics for a date (p50, p75, p95 from the histograms).
     *
     * @since 5.0.0
     * @version 6.0.0
     * @param string $date Date in Y-m-d format.
     * @return array|null
     */
    public function get_aggregated_metrics( string $date ): ?array {

        $data = get_option( 'kit_crm_rum_' . $date, null );
        if ( ! is_array( $data ) || empty( $data['n'] ) ) {
            return null;
        }

        $aggregated = [];
        foreach ( [ 'lcp', 'fid', 'cls', 'ttfb', 'fcp', 'inp' ] as $metric ) {
            $hist = (array) ( $data['hist'][ $metric ] ?? [] );
            if ( ! $hist ) { $aggregated[ $metric ] = null; continue; }
            $aggregated[ $metric ] = [
                'p50' => $this->percentile( $metric, $hist, 50 ),
                'p75' => $this->percentile( $metric, $hist, 75 ),
                'p95' => $this->percentile( $metric, $hist, 95 ),
            ];
        }

        $total = array_sum( $data['device'] );
        $device_pct = [];
        foreach ( $data['device'] as $type => $n ) {
            $device_pct[ $type ] = $total > 0 ? round( ( $n / $total ) * 100 ) : 0;
        }

        return [
            'date'             => $date,
            'sample_count'     => (int) $data['n'],
            'metrics'          => $aggregated,
            'device_breakdown' => $device_pct,
            'connection_types' => $data['connection'],
        ];

    }

    /**
     * Percentile from a histogram (upper edge of the bucket that holds it).
     *
     * @since 6.0.0
     */
    private function percentile( string $metric, array $hist, int $percentile ): int {
        ksort( $hist );
        $edges  = self::edges( $metric );
        $total  = array_sum( $hist );
        $target = max( 1, (int) ceil( $total * $percentile / 100 ) );
        $seen   = 0;
        foreach ( $hist as $bucket => $n ) {
            $seen += $n;
            if ( $seen >= $target ) {
                return (int) ( $edges[ $bucket ] ?? end( $edges ) );
            }
        }
        return (int) end( $edges );
    }

    /**
     * Send yesterday's numbers to the CRM, then clear them.
     *
     * @since 6.0.0
     */
    public function send_yesterday( crm_api $api ): void {
        $date = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
        $agg  = $this->get_aggregated_metrics( $date );
        if ( $agg ) {
            $result = $api->send_rum_beacon( $agg );
            if ( ! empty( $result['success'] ) ) $this->clear_metrics( $date );
        }
        // Remove anything older than a week that was never sent.
        $this->clear_metrics( wp_date( 'Y-m-d', time() - 8 * DAY_IN_SECONDS ) );
    }

    /**
     * Clear metrics for a date (after sending to CRM).
     *
     * @since 5.0.0
     * @param string $date Date in Y-m-d format.
     */
    public function clear_metrics( string $date ): void {
        delete_option( 'kit_crm_rum_' . $date );
        delete_transient( 'kit_crm_rum_' . $date );
    }

}
