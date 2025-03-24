<?php
/**
 * Security.
 *
 * Default security hardening for WordPress sites.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Public;
class security {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Disable XML-RPC.
        add_filter( 'xmlrpc_enabled', '__return_false' );

        // Remove WordPress version.
        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_false' );

        // Check if we're on admin.
        if( ! is_admin() ) {

            // Prevent user enumeration.
            add_filter( 'rest_endpoints', [ $this, 'rest_endpoints' ] );

        }

    }

    /**
     * Disable REST API endpoints.
     * 
     * @since   1.0.0
     */
    public function rest_endpoints( $endpoints ) {

        // Unset, if set.
        if( isset( $endpoints['/wp/v2/users'] ) ) unset( $endpoints['/wp/v2/users'] );
        if( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

        // Return endpoints.
        return $endpoints;

    }

}