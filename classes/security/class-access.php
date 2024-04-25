<?php
/**
 * Access.
 * 
 * Disables access to the site, if the constant is set within wp-config, and redirects non-logged in users to Built Mighty.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtAccess {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Actions.
        add_action( 'template_redirect', [ $this, 'redirect' ] );

    }

    /**
     * Redirect.
     * 
     * Redirect the user to the home page if they are not logged in and the constant is set.
     * 
     * @since   1.0.0
     */
    public function redirect() {

        // If constant isn't set, later gator.
        if( ! defined( 'BUILT_ACCESS' ) ) return;

        // If user is logged in, take off.
        if( is_user_logged_in() ) return;

        // If user is trying to login, sayonara.
        if( isset( $_POST['builtmighty_login'] ) ) return;

        // If custom login page is set and user is trying to access it, off I go.
        if( defined( 'BUILT_ENDPOINT' ) && strpos( $_SERVER['REQUEST_URI'], BUILT_ENDPOINT ) !== false ) return;

        // Redirect to Built Mighty.
        wp_redirect( 'https://builtmighty.com' );
        exit;

    }

}