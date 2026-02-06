<?php
/**
 * CRM API.
 *
 * API client for communicating with the Built Mighty CRM.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\CRM;

use BuiltMightyKit\Utility\API;

if ( ! defined( 'WPINC' ) ) { die; }

class crm_api extends API {

    /**
     * Instance.
     *
     * @since 5.0.0
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Site API key.
     *
     * @since 5.0.0
     * @var string
     */
    private string $api_key;

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
     * API base URL.
     *
     * @since 5.0.0
     * @var string
     */
    private const API_URL = 'https://crm.builtmighty.com';

    /**
     * Constructor.
     *
     * @since 5.0.0
     */
    public function __construct() {
        $this->api_key = get_option( 'kit_crm_api_key', '' );

        parent::__construct( self::API_URL, [
            'X-API-Key' => $this->api_key,
            'X-Site-Domain' => $this->get_domain(),
        ] );
    }

    /**
     * Check if CRM is configured.
     *
     * @since 5.0.0
     * @return bool
     */
    public function is_configured(): bool {
        return ! empty( $this->api_key );
    }

    /**
     * Get the site domain.
     *
     * @since 5.0.0
     * @return string
     */
    private function get_domain(): string {
        return wp_parse_url( home_url(), PHP_URL_HOST );
    }

    /**
     * Register this site with the CRM.
     *
     * @since 5.0.0
     * @return array
     */
    public function register_site(): array {
        $body = [
            'domain'      => $this->get_domain(),
            'site_name'   => get_bloginfo( 'name' ),
            'site_url'    => home_url(),
            'admin_email' => get_option( 'admin_email' ),
            'site_info'   => $this->get_site_info(),
        ];

        return $this->post( 'api/woo/register', $body );
    }

    /**
     * Send daily analytics report.
     *
     * @since 5.0.0
     * @param array $data The analytics data.
     * @return array
     */
    public function send_daily_report( array $data ): array {
        $body = array_merge( $data, [
            'domain'       => $this->get_domain(),
            'report_date'  => current_time( 'Y-m-d' ),
            'site_info'    => $this->get_site_info(),
            'ga_property'  => get_option( 'kit_crm_ga_property', '' ),
            'is_woocommerce' => isset( $data['is_woocommerce'] ) ? $data['is_woocommerce'] : class_exists( 'WooCommerce' ),
        ] );

        return $this->post( 'api/woo/report', $body );
    }

    /**
     * Send RUM (Real User Monitoring) beacon data.
     *
     * @since 5.0.0
     * @param array $metrics Performance metrics.
     * @return array
     */
    public function send_rum_beacon( array $metrics ): array {
        $body = [
            'domain'  => $this->get_domain(),
            'date'    => current_time( 'Y-m-d' ),
            'metrics' => $metrics,
        ];

        return $this->post( 'api/woo/rum', $body );
    }

    /**
     * Send historical backfill data.
     *
     * @since 5.0.0
     * @param array $data Historical data by date.
     * @param int   $chunk Current chunk number.
     * @param int   $total_chunks Total chunks.
     * @return array
     */
    public function send_backfill( array $data, int $chunk, int $total_chunks ): array {
        $body = [
            'domain'       => $this->get_domain(),
            'data'         => $data,
            'chunk'        => $chunk,
            'total_chunks' => $total_chunks,
        ];

        return $this->post( 'api/woo/backfill', $body );
    }

    /**
     * Send heartbeat to confirm site is active.
     *
     * @since 5.0.0
     * @return array
     */
    public function heartbeat(): array {
        return $this->post( 'api/woo/heartbeat', [
            'domain'    => $this->get_domain(),
            'timestamp' => current_time( 'c' ),
        ] );
    }

    /**
     * Get site information.
     *
     * @since 5.0.0
     * @return array
     */
    private function get_site_info(): array {
        $info = [
            'wordpress_version' => get_bloginfo( 'version' ),
            'php_version'       => phpversion(),
            'theme'             => get_stylesheet(),
            'is_multisite'      => is_multisite(),
            'timezone'          => wp_timezone_string(),
        ];

        if ( class_exists( 'WooCommerce' ) ) {
            $info['woocommerce_version'] = WC()->version;
            $info['currency']            = get_woocommerce_currency();
        }

        return $info;
    }

    /**
     * Test the CRM connection.
     *
     * @since 5.0.0
     * @return array
     */
    public function test_connection(): array {
        return $this->heartbeat();
    }

}
