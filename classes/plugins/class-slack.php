<?php
/**
 * Slack.
 * 
 * Adds connectivity to Slack.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Plugins;
class builtSlack {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Routes.
        add_action( 'rest_api_init', [ $this, 'routes' ] );

    }

    /**
     * Set routes.
     * 
     * @since   1.0.0
     */
    public function routes() {

        // Register.
        register_rest_route( 'builtmighty-kit/v1', '/slack', [
            'methods'   => 'POST',
            'callback'  => [ $this, 'authorize' ],
            'permission_callback' => '__return_true'
        ] );

    }

    /**
     * Authorize.
     * 
     * @since   1.0.0
     */
    public function authorize( $request ) {

        // Get param key.
        $key = $request->get_param( 'key' );

        error_log( 'Key: ' . $key );

        // Check.
        if( ! $this->authorize_key( $key ) ) return;

        // Get token.
        $token = $request->get_param( 'token' );

        error_log( 'Token: ' . $token );

        // Save token.
        update_option( 'built_slack_token', $token );

    }

    /**
     * Authorize key.
     * 
     * @since   1.0.0
     */
    public function authorize_key( $key ) {

        // Set keys.
        $saved_key  = get_option( 'built_api_key' );
        $passed_key = base64_decode( $key );

        // Delete.
        delete_option( 'built_api_key' );

        // Return.
        return ( $passed_key === $saved_key ) ? true : false;

    }

}