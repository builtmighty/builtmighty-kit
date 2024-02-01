<?php
/**
 * Login.
 * 
 * Sets up a custom login page, if the endpoint is set in wp-config.php. It also redirects the user to the home page if they try to access wp-login.php.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtLogin {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Actions.
        add_action( 'init', [ $this, 'redirect' ] );
        add_action( 'template_redirect', [ $this, 'login' ] );
        add_action( 'login_form', [ $this, 'login_form' ] );

    }

    /**
     * Redirect.
     * 
     * Redirect the user to the endpoint if they try to access wp-login.php.
     * 
     * @since   1.0.0
     */
    public function redirect() {

        // If constant isn't set, bail.
        if( ! defined( 'BUILT_ENDPOINT' ) ) return;

        // If user is logged in, peace out.
        if( is_user_logged_in() ) return;

        // If user is trying to login, buh-bye.
        if( isset( $_POST['builtmighty_login'] ) ) return;

        // If user is trying to access wp-login.php, turn them right around and send them home.
        if( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) {

            // Redirect to home page.
            wp_redirect( home_url( '/' ) );
            exit;

        }

    }

    /**
     * Login.
     * 
     * Load the login page at the endpoint.
     * 
     * @since   1.0.0
     */
    public function login() {

        // If constant isn't set, dip.
        if( ! defined( 'BUILT_ENDPOINT' ) ) return;

        // If constant is empty, farewell.
        if( empty( BUILT_ENDPOINT ) ) return;

        // If user is logged in, adios.
        if( is_user_logged_in() ) return;

        // Check request.
        if( $_SERVER['REQUEST_URI'] === '/' . BUILT_ENDPOINT || $_SERVER['REQUEST_URI'] === '/' . BUILT_ENDPOINT . '/' ) {


            // Set the necessary variables.
            $user_login = isset( $user_login ) ? $user_login : '';
            $error      = isset( $error ) ? $error : '';

            // Include the necessary WordPress files.
            require_once ABSPATH . WPINC . '/load.php';
            require_once ABSPATH . WPINC . '/default-constants.php';
            require_once ABSPATH . WPINC . '/version.php';
            require_once ABSPATH . WPINC . '/functions.php';
            require_once ABSPATH . WPINC . '/plugin.php';
            require_once ABSPATH . WPINC . '/user.php';
            require_once ABSPATH . WPINC . '/class-wpdb.php';
            require_once ABSPATH . WPINC . '/meta.php';
            require_once ABSPATH . WPINC . '/capabilities.php';
            require_once ABSPATH . WPINC . '/formatting.php';
            require_once ABSPATH . WPINC . '/capabilities.php';
            require_once ABSPATH . WPINC . '/pluggable.php';

            // Set the necessary global variables.
            global $pagenow, $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
            $wp_did_header = true;

            // Load wp-login.php to handle the login functionality.
            include ABSPATH . 'wp-login.php';
            exit;

        }

    }

    /**
     * Add a custom field to login form to ensure validation.
     * 
     * @since   1.0.0
     */
    public function login_form() {

        // If constant isn't set, catch you later.
        if( ! defined( 'BUILT_ENDPOINT' ) ) return;

        // If constant is empty, see you in a bit.
        if( empty( BUILT_ENDPOINT ) ) return;

        // If user is logged in, so long.
        if( is_user_logged_in() ) return;

        // If user is trying to login, time to bounce.
        if( isset( $_POST['builtmighty_login'] ) ) return;

        // If user is trying to access wp-login.php, ta-ta.
        if( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) return;

        // Add custom field.
        echo '<input type="hidden" name="builtmighty_login" value="true" />';

    }

}