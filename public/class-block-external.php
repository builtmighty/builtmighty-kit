<?php
/**
 * Outside services.
 *
 * On copies of the store, outgoing HTTP requests are blocked except to hosts
 * that are always safe (WordPress.org, GitHub, Slack, Built Mighty, this site)
 * and hosts added in settings. Subdomains of an allowed host are allowed.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;

if( ! defined( 'ABSPATH' ) ) { exit; }

class block_external {

    /**
     * Allowed hosts for this request, built once.
     *
     * @var array|null
     */
    private $allowed = null;

    /**
     * Construct.
     */
    public function __construct() {

        if( ! settings::is_on( 'kit_block_external' ) ) return;

        add_filter( 'pre_http_request', [ $this, 'block' ], 10, 3 );

    }

    /**
     * Hosts that are always allowed.
     */
    public static function always_allowed(): array {
        $self = wp_parse_url( home_url(), PHP_URL_HOST );
        $site = wp_parse_url( site_url(), PHP_URL_HOST );
        return array_values( array_unique( array_filter( (array) apply_filters( 'kit_always_allowed_hosts', [
            'localhost',
            '127.0.0.1',
            $self,
            $site,
            'wordpress.org',
            'github.com',
            'github.dev',
            'github.io',
            'githubusercontent.com',
            'slack.com',
            'builtmighty.com',
        ] ) ) ) );
    }

    /**
     * All allowed hosts.
     */
    public function allowed(): array {
        if( $this->allowed === null ) {
            $extra = array_map( function( $host ) {
                $host = strtolower( trim( (string) $host ) );
                $host = preg_replace( '#^[a-z]+://#', '', $host );
                $host = preg_replace( '#[/:].*$#', '', $host );
                return ltrim( $host, '*.' );
            }, (array) settings::get( 'kit_allowed_external' ) );
            $this->allowed = array_values( array_unique( array_filter( array_merge( self::always_allowed(), $extra ) ) ) );
        }
        return $this->allowed;
    }

    /**
     * Is a host allowed? Matches the host itself and any subdomain.
     */
    public static function host_allowed( string $host, array $allowed ): bool {
        $host = strtolower( rtrim( $host, '.' ) );
        foreach( $allowed as $item ) {
            $item = strtolower( (string) $item );
            if( $host === $item ) return true;
            if( substr( $host, -strlen( '.' . $item ) ) === '.' . $item ) return true;
        }
        return false;
    }

    /**
     * Block.
     */
    public function block( $pre, $args, $url ) {

        if( $pre !== false ) return $pre;

        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        if( $host === '' || self::host_allowed( $host, $this->allowed() ) ) return $pre;

        // Log the first block per host per hour so the log stays readable.
        $key = 'kit_blk_' . md5( $host );
        if( ! get_transient( $key ) ) {
            set_transient( $key, 1, HOUR_IN_SECONDS );
            activity::log( 'safeguard', 'request_blocked', 'Blocked a request to ' . $host, [
                'level'   => 'info',
                'user_id' => 0,
                'context' => [ 'host' => $host, 'method' => strtoupper( (string) ( $args['method'] ?? 'GET' ) ) ],
            ] );
        }

        return new \WP_Error( 'http_request_blocked', sprintf( 'Built Mighty Kit blocked a request to %s because this is a copy of the store. Allow it in Kit, Safeguards.', $host ) );

    }

}
