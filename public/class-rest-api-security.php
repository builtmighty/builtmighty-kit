<?php
/**
 * REST API security.
 *
 * Require sign-in for private routes, turn off routes, hide the user list,
 * rate-limit signed-out visitors and log requests that change data.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;
use BuiltMightyKit\Utility\request;

if( ! defined( 'ABSPATH' ) ) { exit; }

class rest_api_security {

    /**
     * Construct.
     */
    public function __construct() {

        if( ! settings::is_on( 'kit_rest_api_security' ) ) return;

        add_filter( 'rest_endpoints', [ $this, 'disable_endpoints' ] );

        if( settings::is_on( 'kit_rest_rate_limit' ) ) {
            add_filter( 'rest_pre_dispatch', [ $this, 'rate_limit' ], 1, 3 );
        }

        if( settings::is_on( 'kit_rest_require_auth' ) ) {
            add_filter( 'rest_pre_dispatch', [ $this, 'require_authentication' ], 2, 3 );
        }

        if( settings::is_on( 'kit_rest_logging' ) ) {
            add_filter( 'rest_post_dispatch', [ $this, 'log_request' ], 999, 3 );
        }

        if( settings::is_on( 'kit_rest_remove_link' ) ) {
            remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
            remove_action( 'template_redirect', 'rest_output_link_header', 11 );
        }

    }

    /**
     * Routes that stay public.
     */
    public static function public_prefixes(): array {
        $defaults = [
            '/wp/v2/posts',
            '/wp/v2/pages',
            '/wp/v2/categories',
            '/wp/v2/tags',
            '/wp/v2/media',
            '/wp/v2/types',
            '/wp/v2/statuses',
            '/wp/v2/taxonomies',
            '/wp/v2/comments',
            '/wp/v2/search',
            '/oembed/',
            '/wp-site-health/',
            '/wc/store/',
            '/wc/v3/',
            '/wc/v2/',
            '/wc-auth/',
            '/wc-blocks/',
            '/wc-analytics/',
            '/builtmighty-kit/v1/slack',
            '/builtmighty-kit/v1/passkeys/login', // Passkey sign-in runs before the person is signed in.
        ];
        $custom = array_map( function( $route ) {
            return '/' . ltrim( trim( (string) $route ), '/' );
        }, (array) settings::get( 'kit_rest_whitelist' ) );
        return array_values( array_filter( (array) apply_filters( 'kit_rest_whitelist', array_merge( $defaults, $custom ) ) ) );
    }

    /**
     * Does a route start with any of these prefixes?
     */
    public static function matches_prefix( string $route, array $prefixes ): bool {
        $route = '/' . ltrim( $route, '/' );
        foreach( $prefixes as $prefix ) {
            $prefix = (string) $prefix;
            if( $prefix === '' || $prefix === '/' ) continue;
            $base = rtrim( $prefix, '/' );
            // "/wp/v2/posts" matches "/wp/v2/posts" and "/wp/v2/posts/12", not "/wp/v2/postsx".
            if( rtrim( $route, '/' ) === $base || strpos( $route, $base . '/' ) === 0 ) return true;
        }
        return false;
    }

    /**
     * Require sign-in for private routes.
     */
    public function require_authentication( $result, $server, $request ) {
        if( $result !== null ) return $result;
        if( is_user_logged_in() ) return $result;
        $route = (string) $request->get_route();
        if( $route === '/' || $route === '' ) return $result;
        if( self::matches_prefix( $route, self::public_prefixes() ) ) return $result;
        return new \WP_Error( 'rest_not_authenticated', 'Sign in to use this part of the API.', [ 'status' => 401 ] );
    }

    /**
     * Rate-limit signed-out visitors.
     */
    public function rate_limit( $result, $server, $request ) {
        if( $result !== null || is_user_logged_in() ) return $result;
        $limit  = (int) settings::get( 'kit_rest_rate_limit_requests' );
        $window = (int) settings::get( 'kit_rest_rate_limit_window' );
        $hit = request::hit( 'rest', request::client_ip(), max( 1, $limit ), max( 1, $window ) );
        if( $hit['allowed'] ) return $result;
        if( $hit['count'] === $limit + 1 ) {
            activity::log( 'api', 'rate_limited', 'Rate-limited ' . request::client_ip() . ' on the REST API', [ 'level' => 'warning', 'user_id' => 0, 'context' => [ 'route' => $request->get_route() ] ] );
        }
        return new \WP_Error( 'rest_rate_limited', 'Too many requests. Try again in a moment.', [ 'status' => 429, 'retry_after' => $hit['retry_after'] ] );
    }

    /**
     * Turn off routes and hide the user list.
     */
    public function disable_endpoints( $endpoints ) {

        $remove = [];

        // Legacy array of /wp/v2/ routes.
        foreach( (array) get_option( 'kit_rest_disabled_endpoints', [] ) as $endpoint ) {
            $remove[] = '/wp/v2/' . ltrim( (string) $endpoint, '/' );
        }

        // Prefixes from settings.
        foreach( (array) settings::get( 'kit_rest_custom_disabled' ) as $route ) {
            $remove[] = '/' . ltrim( trim( (string) $route ), '/' );
        }

        // Hide the user list from people who can't edit others' posts (the block editor needs it).
        if( settings::is_on( 'kit_disable_user_enum' ) && ! current_user_can( 'edit_others_posts' ) ) {
            $remove[] = '/wp/v2/users';
        }

        $remove = array_filter( $remove, function( $r ) { return $r !== '/' && $r !== ''; } );
        if( ! $remove ) return $endpoints;

        foreach( array_keys( $endpoints ) as $route ) {
            foreach( $remove as $prefix ) {
                if( $route === $prefix || strpos( $route, rtrim( $prefix, '/' ) . '/' ) === 0 ) {
                    unset( $endpoints[ $route ] );
                    break;
                }
            }
        }

        return $endpoints;

    }

    /**
     * Log requests that change data, and failed requests.
     */
    public function log_request( $response, $server, $request ) {

        $method = strtoupper( (string) $request->get_method() );
        $status = $response instanceof \WP_HTTP_Response ? (int) $response->get_status() : 200;

        if( $method === 'GET' && $status < 400 ) return $response;
        if( $method === 'OPTIONS' || $method === 'HEAD' ) return $response;

        $route = (string) $request->get_route();
        $params = (array) $request->get_body_params() + (array) $request->get_query_params();

        activity::log( 'api', $status >= 400 ? 'request_failed' : 'request', sprintf( '%s %s returned %d', $method, $route, $status ), [
            'level'   => $status >= 500 ? 'error' : ( $status >= 400 ? 'warning' : 'info' ),
            'context' => [
                'route'  => $route,
                'method' => $method,
                'status' => $status,
                'params' => array_slice( $params, 0, 30, true ),
            ],
        ] );

        return $response;

    }

}
