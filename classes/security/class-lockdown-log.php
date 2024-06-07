<?php
/**
 * Lockdown log.
 * 
 * Log all lockdown attempts.
 * 
 * @since   2.0.0
 */
namespace BuiltMightyKit\Security;
class builtLockdownLog {

    /**
     * Construct.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        return;

        // Log.
        add_action( 'wp_login_failed', [ $this, 'log' ] );

    }

    /**
     * Failed Login.
     * 
     * @since   2.0.0
     */
    public function failed_login() {

        // Global.
        global $wpdb;

        error_log( 'POST: ' . print_r( $_POST, true ) );

         // Get user IP.
        $ip = $_SERVER['REMOTE_ADDR'];

        // Get user agent.
        $agent = $_SERVER['HTTP_USER_AGENT'];

        // Get user login.
        $login = $_POST['log'];

        // Get user time.
        $time = date( 'Y-m-d H:i:s' );

        error_log( 'IP: ' . print_r( $ip, true ) );
        error_log( 'Agent: ' . print_r( $agent, true ) );
        error_log( 'Login: ' . print_r( $login, true ) );
        error_log( 'Time: ' . print_r( $time, true ) );

        // Redirect.
        wp_redirect( home_url( '/' . BUILT_ENDPOINT . '?login=failed' ) );
        exit;

    }

    /** 
     * Failed 2FA.
     * 
     * @since   2.0.0
     */
    public function failed_2fa() {



    }


}