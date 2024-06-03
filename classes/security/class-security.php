<?php
/**
 * Security.
 * 
 * Improves security on dev environments and production sites.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Security;
class builtSecurity {

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

        // Disable login errors.
        add_filter( 'login_errors', [ $this, 'login_errors' ] );

        // Check if we're on admin.
        if( ! is_admin() ) {

            // Prevent user enumeration.
            add_filter( 'rest_endpoints', [ $this, 'rest_endpoints' ] );

        }

    }

    /**
     * Disable login errors.
     * 
     * @since   1.0.0
     */
    public function login_errors( $error ) {

        // Check for valid.
        $valid = [
            '<p><strong>Error:</strong> The username <strong>asdsadlk</strong> is not registered on this site. If you are unsure of your username, try your email address instead.</p>',
            '<p><strong>Error:</strong> The password you entered for the username <strong>admin</strong> is incorrect. Lost your password?</p>'
        ];

        // Check.
        if( in_array( $error, $valid ) ) return 'There was an error.';

        // Return.
        return $error;

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