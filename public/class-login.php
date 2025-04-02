<?php
/**
 * Login.
 *
 * WordPress login methods.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Public;
class login {

    /**
     * Login URL.
     * 
     * @since   1.0.0
     */
    public $url;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check if standard permalinks are set.
        if( empty( get_option( 'permalink_structure' ) ) ) return;

        // Check if enabled.
        if( get_option( 'kit_enable_login' ) !== 'enable' && ! empty( get_option( 'kit_login_url' ) ) ) return;

        // Set login URL.
        $this->url = ( defined( 'BUILT_ENDPOINT' ) ) ? '/' . ltrim( BUILT_ENDPOINT, '/' ) . '/' : '/' . ltrim( (string)get_option( 'kit_login_url' ), '/' ) . '/';

        // Actions.
        add_action( 'init', [ $this, 'redirect' ] );
        add_action( 'template_redirect', [ $this, 'login' ] );
        add_action( 'login_form', [ $this, 'login_form' ] );
        add_action( 'wp_login_failed', [ $this, 'login_failed' ] );

    }

    /**
     * Redirect.
     * 
     * Redirect the user to the endpoint if they try to access wp-login.php.
     * 
     * @since   1.0.0
     */
    public function redirect() {

        // If user is logged in, peace out.
        if( is_user_logged_in() ) return;

        // If user is trying to login, buh-bye.
        if( isset( $_POST['builtmighty_login'] ) ) return;

        // Check if password protected page.
        if( ! empty( $_GET['action'] ) && $_GET['action'] === 'postpass' ) return;

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

        // Set request URI.
        $request_uri = trailingslashit( strtok( $_SERVER['REQUEST_URI'], '?' ) );

        // If user is logged in, adios.
        if( is_user_logged_in() && $request_uri == $this->url ) {

            // Redirect to home page.
            wp_redirect( home_url( '/' ) );
            exit;

        }

        // Set headers to 200.
        status_header( 200 );

        // Check request.
        if( $request_uri == $this->url ) {

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

        // If user is logged in, so long.
        if( is_user_logged_in() ) return;

        // If user is trying to login, time to bounce.
        if( isset( $_POST['builtmighty_login'] ) ) return;

        // If user is trying to access wp-login.php, ta-ta.
        if( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) return;

        // Check if password protected page.
        if( ! empty( $_GET['action'] ) && $_GET['action'] === 'postpass' ) return;

        // Add custom field.
        echo '<input type="hidden" name="builtmighty_login" value="true" />';

    }

    /**
     * On failed login.
     * 
     * Redirect the user back to the login page with an error message.
     * 
     * @since   1.2.0
     */
    public function login_failed( $username ) {

        // If user is logged in, so long.
        if( is_user_logged_in() ) return;

        // Check if WooCommerce login.
        if( isset( $_POST['_wp_http_referer'] ) && wc_get_page_permalink( 'myaccount' ) === home_url( $_POST['_wp_http_referer'] ) ) {

            // Redirect to My Account page with error message.
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) . '?login=failed' );
            exit;

        }

        // Set the necessary variables.
        $error = isset( $error ) ? $error : '';

        // Redirect to login page with error message.
        wp_redirect( home_url( $this->url . '?login=failed' ) );
        exit;

    }

}