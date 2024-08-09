<?php
/**
 * Data API.
 * 
 * Deliver data via API to external sources.
 * 
 * @package Built Mighty Kit
 * @since   2.2.0
 */
namespace BuiltMightyKit\Data;
use function BuiltMightyKit\is_kit_mode;
class builtDataAPI {

    /**
     * Construct.
     * 
     * Initialize the class.
     * 
     * @since   2.2.0
     */
    public function __construct() {

        // Register API.
        add_action( 'rest_api_init', [ $this, 'routes' ] );

    }

    /**
     * Routes.
     * 
     * @since   2.2.0
     */
    public function routes() {

        // Register.
        register_rest_route( 'builtmighty-kit/v1', '/data', [
            'methods'   => 'GET',
            'callback'  => [ $this, 'data' ],
            'permission_callback' => '__return_true'
        ] );

    }

    /**
     * Data.
     * 
     * @since   2.2.0
     */
    public function data( $request ) {

        // Check if kit mode.
        if( is_kit_mode() ) return new \WP_Error( 'kit_mode', 'Kit mode is enabled. API access denied.', [ 'status' => 403 ] );

        // Return data.
        return wp_send_json( [ 'data' => 'Hello, World!' ], 200 );

    }

}