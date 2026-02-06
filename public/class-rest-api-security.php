<?php
/**
 * REST API Security.
 *
 * Hardens the WordPress REST API.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\Public;

class rest_api_security {

    /**
     * Option name for API logs.
     *
     * @var string
     */
    private const LOG_OPTION = 'kit_api_logs';

    /**
     * Maximum logs to keep.
     *
     * @var int
     */
    private const MAX_LOGS = 200;

    /**
     * Construct.
     *
     * @since   5.0.0
     */
    public function __construct() {

        // Check if enabled.
        if ( get_option( 'kit_rest_api_security' ) !== 'enable' ) {
            return;
        }

        // Require authentication for REST API.
        if ( get_option( 'kit_rest_require_auth' ) === 'enable' ) {
            add_filter( 'rest_authentication_errors', [ $this, 'require_authentication' ], 99 );
        }

        // Disable specific REST endpoints.
        add_filter( 'rest_endpoints', [ $this, 'disable_endpoints' ] );

        // Log API requests.
        if ( get_option( 'kit_rest_logging' ) === 'enable' ) {
            add_filter( 'rest_pre_dispatch', [ $this, 'log_request' ], 10, 3 );
        }

        // Rate limit REST API.
        if ( get_option( 'kit_rest_rate_limit' ) === 'enable' ) {
            add_filter( 'rest_pre_dispatch', [ $this, 'check_rate_limit' ], 5, 3 );
        }

        // Remove REST API link from head.
        if ( get_option( 'kit_rest_remove_link' ) === 'enable' ) {
            remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
            remove_action( 'template_redirect', 'rest_output_link_header', 11 );
        }

    }

    /**
     * Require authentication for REST API requests.
     *
     * @param   \WP_Error|null|true $result  Authentication result.
     *
     * @return  \WP_Error|null|true  Authentication result.
     *
     * @since   5.0.0
     */
    public function require_authentication( $result ) {

        // If there's already an error, return it.
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Allow if already authenticated.
        if ( is_user_logged_in() ) {
            return $result;
        }

        // Get current REST route.
        $rest_route = $this->get_current_rest_route();

        // Check if route is in whitelist.
        if ( $this->is_route_whitelisted( $rest_route ) ) {
            return $result;
        }

        // Require authentication.
        return new \WP_Error(
            'rest_not_authenticated',
            __( 'Authentication is required to access this endpoint.', 'builtmighty-kit' ),
            [ 'status' => 401 ]
        );

    }

    /**
     * Check if route is whitelisted.
     *
     * @param   string $route  REST route.
     *
     * @return  bool  Whether route is whitelisted.
     *
     * @since   5.0.0
     */
    private function is_route_whitelisted( $route ) {

        // Default whitelisted routes.
        $whitelist = [
            '/wp/v2/posts',           // Public posts.
            '/wp/v2/pages',           // Public pages.
            '/wp/v2/categories',      // Public categories.
            '/wp/v2/tags',            // Public tags.
            '/wp/v2/media',           // Public media.
            '/wp/v2/types',           // Post types.
            '/wp/v2/statuses',        // Post statuses.
            '/wp/v2/taxonomies',      // Taxonomies.
            '/wp/v2/comments',        // Comments (if open).
            '/oembed/',               // oEmbed.
            '/wp-site-health/',       // Site health (needed for updates).
            '/wc/',                   // WooCommerce.
            '/wc-auth/',              // WooCommerce auth.
            '/wc-blocks/',            // WooCommerce blocks.
        ];

        // Get custom whitelist.
        $custom_whitelist = get_option( 'kit_rest_whitelist', '' );
        if ( ! empty( $custom_whitelist ) ) {
            $custom_routes = array_map( 'trim', explode( "\n", $custom_whitelist ) );
            $whitelist = array_merge( $whitelist, $custom_routes );
        }

        // Allow filtering.
        $whitelist = apply_filters( 'kit_rest_whitelist', $whitelist );

        // Check each whitelist pattern.
        foreach ( $whitelist as $pattern ) {
            if ( empty( $pattern ) ) {
                continue;
            }
            if ( strpos( $route, $pattern ) === 0 || strpos( $route, $pattern ) !== false ) {
                return true;
            }
        }

        return false;

    }

    /**
     * Disable specific REST endpoints.
     *
     * @param   array $endpoints  REST endpoints.
     *
     * @return  array  Modified endpoints.
     *
     * @since   5.0.0
     */
    public function disable_endpoints( $endpoints ) {

        // Get disabled endpoints option.
        $disabled = get_option( 'kit_rest_disabled_endpoints', [] );

        if ( ! is_array( $disabled ) ) {
            $disabled = [];
        }

        // Always disable user enumeration if security class is active.
        if ( get_option( 'kit_disable_user_enum', 'enable' ) === 'enable' ) {
            $disabled = array_merge( $disabled, [ 'users', 'users/(?P<id>[\\d]+)' ] );
        }

        // Remove disabled endpoints.
        foreach ( $disabled as $endpoint ) {
            // Handle both formats.
            $route = '/wp/v2/' . ltrim( $endpoint, '/' );
            if ( isset( $endpoints[ $route ] ) ) {
                unset( $endpoints[ $route ] );
            }
        }

        // Get custom disabled endpoints.
        $custom_disabled = get_option( 'kit_rest_custom_disabled', '' );
        if ( ! empty( $custom_disabled ) ) {
            $custom_routes = array_map( 'trim', explode( "\n", $custom_disabled ) );
            foreach ( $custom_routes as $route ) {
                if ( isset( $endpoints[ $route ] ) ) {
                    unset( $endpoints[ $route ] );
                }
            }
        }

        return $endpoints;

    }

    /**
     * Log REST API requests.
     *
     * @param   mixed            $result   Response.
     * @param   \WP_REST_Server  $server   REST server.
     * @param   \WP_REST_Request $request  Request object.
     *
     * @return  mixed  Unmodified result.
     *
     * @since   5.0.0
     */
    public function log_request( $result, $server, $request ) {

        $log = [
            'route'     => $request->get_route(),
            'method'    => $request->get_method(),
            'user_id'   => get_current_user_id(),
            'ip'        => $this->get_client_ip(),
            'timestamp' => current_time( 'timestamp' ),
            'params'    => $this->sanitize_params( $request->get_params() ),
        ];

        $this->add_log( $log );

        return $result;

    }

    /**
     * Check rate limit for REST API.
     *
     * @param   mixed            $result   Response.
     * @param   \WP_REST_Server  $server   REST server.
     * @param   \WP_REST_Request $request  Request object.
     *
     * @return  mixed|\WP_Error  Result or error if rate limited.
     *
     * @since   5.0.0
     */
    public function check_rate_limit( $result, $server, $request ) {

        // Skip rate limiting for authenticated users.
        if ( is_user_logged_in() ) {
            return $result;
        }

        $ip = $this->get_client_ip();
        $limit = (int) get_option( 'kit_rest_rate_limit_requests', 60 );
        $window = (int) get_option( 'kit_rest_rate_limit_window', 60 );

        $transient_key = 'kit_api_rate_' . md5( $ip );
        $requests = get_transient( $transient_key );

        if ( false === $requests ) {
            $requests = 0;
        }

        $requests++;

        if ( $requests > $limit ) {
            return new \WP_Error(
                'rest_rate_limited',
                __( 'Too many requests. Please try again later.', 'builtmighty-kit' ),
                [ 'status' => 429 ]
            );
        }

        set_transient( $transient_key, $requests, $window );

        return $result;

    }

    /**
     * Add a log entry.
     *
     * @param   array $log  Log data.
     *
     * @since   5.0.0
     */
    private function add_log( $log ) {

        $logs = get_option( self::LOG_OPTION, [] );

        if ( ! is_array( $logs ) ) {
            $logs = [];
        }

        array_unshift( $logs, $log );
        $logs = array_slice( $logs, 0, self::MAX_LOGS );

        update_option( self::LOG_OPTION, $logs, false );

    }

    /**
     * Get API logs.
     *
     * @param   int $limit  Number of logs.
     *
     * @return  array  API logs.
     *
     * @since   5.0.0
     */
    public static function get_logs( $limit = 50 ) {

        $logs = get_option( self::LOG_OPTION, [] );

        if ( ! is_array( $logs ) ) {
            return [];
        }

        return array_slice( $logs, 0, $limit );

    }

    /**
     * Clear API logs.
     *
     * @since   5.0.0
     */
    public static function clear_logs() {
        delete_option( self::LOG_OPTION );
    }

    /**
     * Get current REST route.
     *
     * @return  string  Current REST route.
     *
     * @since   5.0.0
     */
    private function get_current_rest_route() {

        $rest_route = '';

        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
            $rest_prefix = rest_get_url_prefix();

            if ( strpos( $request_uri, '/' . $rest_prefix . '/' ) !== false ) {
                $rest_route = substr( $request_uri, strpos( $request_uri, '/' . $rest_prefix ) + strlen( $rest_prefix ) + 1 );
                $rest_route = '/' . strtok( $rest_route, '?' );
            }
        }

        return $rest_route;

    }

    /**
     * Sanitize params for logging.
     *
     * @param   array $params  Request parameters.
     *
     * @return  array  Sanitized parameters.
     *
     * @since   5.0.0
     */
    private function sanitize_params( $params ) {

        // Remove sensitive data.
        $sensitive_keys = [ 'password', 'pass', 'pwd', 'secret', 'token', 'key', 'api_key' ];

        foreach ( $params as $key => $value ) {
            if ( in_array( strtolower( $key ), $sensitive_keys, true ) ) {
                $params[ $key ] = '[REDACTED]';
            }
        }

        return $params;

    }

    /**
     * Get client IP.
     *
     * @return  string  Client IP.
     *
     * @since   5.0.0
     */
    private function get_client_ip() {

        $ip = '';

        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip  = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }

        return '0.0.0.0';

    }

}
