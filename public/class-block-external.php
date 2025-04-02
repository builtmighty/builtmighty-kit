<?php
/**
 * Block External.
 *
 * Block external API requests.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Public;
use function BuiltMightyKit\is_kit_mode;
class block_external {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check.
        if( ( empty( get_option( 'kit_block_external' ) ) && is_kit_mode() ) || get_option( 'kit_block_external' ) == 'disable' ) return;

        // Filter.
        add_filter( 'pre_http_request', [ $this, 'block' ], 10, 3 );

    }

    /**
     * Block.
     * 
     * @since   1.0.0
     */
    public function block( $pre, $args, $url ) {

        // Get the host of the URL being requested.
        $host = wp_parse_url( $url, PHP_URL_HOST );

        // Get allowed.
        $allowed = [
            'localhost',
            'api.wordpress.org',
            'downloads.wordpress.org',
            'github.com',
            'github.dev',
            'github.io',
            'githubusercontent.com',
            'slack.com',
            'builtmighty.com'
        ];

        // Check if kit_allowed_external is set.
        if( ! empty( get_option( 'kit_allowed_external' ) ) ) {

            // Get allowed.
            $allowed = array_merge( $allowed, explode( ",", str_replace( ' ', '', get_option( 'kit_allowed_external' ) ) ) );

        }

        // Check if not allowed.
        if( ! in_array( $host, (array)$allowed ) ) return new \WP_Error( 'http_request_blocked', __( 'External HTTP requests are disabled via the Built Mighty Kit. Blocked: ' . $host ) );

        // Return.
        return $pre;

    }

}