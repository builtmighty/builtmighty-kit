<?php
/**
 * Request helpers.
 *
 * Client IP detection that can't be spoofed through forwarded headers, and a
 * cheap fixed-window rate limiter.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Utility;

if( ! defined( 'ABSPATH' ) ) { exit; }

class request {

    /** @var string|null */
    private static $ip = null;

    /**
     * The visitor's IP address.
     *
     * Uses REMOTE_ADDR unless the request came through a trusted proxy (set in
     * Kit settings or the kit_trusted_proxies filter). Cloudflare's header is
     * honored only when REMOTE_ADDR is a trusted proxy too.
     *
     * @return string
     */
    public static function client_ip(): string {

        if( self::$ip !== null ) return self::$ip;

        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
        $ip     = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';

        if( $ip !== '' && self::is_trusted_proxy( $ip ) ) {
            foreach( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ] as $header ) {
                if( empty( $_SERVER[ $header ] ) ) continue;
                // X-Forwarded-For: client, proxy1, proxy2. Walk from the right past trusted proxies.
                $chain = array_reverse( array_map( 'trim', explode( ',', (string) $_SERVER[ $header ] ) ) );
                foreach( $chain as $candidate ) {
                    if( ! filter_var( $candidate, FILTER_VALIDATE_IP ) ) continue;
                    if( self::is_trusted_proxy( $candidate ) ) continue;
                    $ip = $candidate;
                    break 2;
                }
            }
        }

        return self::$ip = (string) apply_filters( 'kit_client_ip', $ip );

    }

    /**
     * Is this IP a trusted proxy?
     */
    public static function is_trusted_proxy( string $ip ): bool {
        $proxies = array_merge( (array) settings::get( 'kit_trusted_proxies' ), (array) apply_filters( 'kit_trusted_proxies', [] ) );
        foreach( $proxies as $proxy ) {
            $proxy = trim( (string) $proxy );
            if( $proxy === '' ) continue;
            if( strpos( $proxy, '/' ) !== false ? self::in_cidr( $ip, $proxy ) : $proxy === $ip ) return true;
        }
        return false;
    }

    /**
     * CIDR match for IPv4 and IPv6.
     */
    public static function in_cidr( string $ip, string $cidr ): bool {
        [ $subnet, $bits ] = array_pad( explode( '/', $cidr, 2 ), 2, null );
        $ip_bin  = @inet_pton( $ip );
        $sub_bin = @inet_pton( (string) $subnet );
        if( $ip_bin === false || $sub_bin === false || strlen( $ip_bin ) !== strlen( $sub_bin ) ) return false;
        $max   = strlen( $ip_bin ) * 8;
        $bits  = $bits === null || $bits === '' ? $max : (int) $bits;
        if( $bits < 0 || $bits > $max ) return false;
        $bytes = intdiv( $bits, 8 );
        $rem   = $bits % 8;
        if( substr( $ip_bin, 0, $bytes ) !== substr( $sub_bin, 0, $bytes ) ) return false;
        if( $rem === 0 ) return true;
        $mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
        return ( $ip_bin[ $bytes ] & $mask ) === ( $sub_bin[ $bytes ] & $mask );
    }

    /**
     * Fixed-window rate limiter.
     *
     * Uses the object cache's atomic increment when a persistent cache exists,
     * and a transient otherwise.
     *
     * @param  string $bucket  Name, like 'rest' or '2fa_send'.
     * @param  string $id      Who is being limited (IP, user ID).
     * @param  int    $limit   Allowed hits per window.
     * @param  int    $window  Window length in seconds.
     * @return array  [ 'allowed' => bool, 'count' => int, 'retry_after' => int ]
     */
    public static function hit( string $bucket, string $id, int $limit, int $window ): array {

        $slot = (int) floor( time() / max( 1, $window ) );
        $key  = 'kit_rl_' . md5( $bucket . '|' . $id . '|' . $slot );
        $retry = ( $slot + 1 ) * $window - time();

        if( wp_using_ext_object_cache() ) {
            wp_cache_add( $key, 0, 'kit_rate', $window );
            $count = (int) wp_cache_incr( $key, 1, 'kit_rate' );
        } else {
            $count = (int) get_transient( $key ) + 1;
            set_transient( $key, $count, $window );
        }

        return [ 'allowed' => $count <= $limit, 'count' => $count, 'retry_after' => max( 1, $retry ) ];

    }

    /**
     * Current count without incrementing.
     */
    public static function peek( string $bucket, string $id, int $window ): int {
        $slot = (int) floor( time() / max( 1, $window ) );
        $key  = 'kit_rl_' . md5( $bucket . '|' . $id . '|' . $slot );
        return wp_using_ext_object_cache() ? (int) wp_cache_get( $key, 'kit_rate' ) : (int) get_transient( $key );
    }

}
