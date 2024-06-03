<?php
/**
 * Lockdown.
 * 
 * Locksdown the admin to allowed IPs.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
namespace BuiltMightyKit\Security;
class builtLockdown {

    /**
     * Construct.
     * 
     * Initialize the class.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // Check if enabled.
        if( ! defined( 'BUILT_LOCKDOWN' ) || ! BUILT_LOCKDOWN ) return;

        // Run on WP.
        add_action( 'admin_init', [ $this, 'lockdown' ] );

    }

    /**
     * Lockdown.
     * 
     * Lockdown the admin to allowed IPs.
     * 
     * @since   2.0.0
     */
    public function lockdown() {

        // Check if doing AJAX or CRON.
        if( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) ) return;

        // Get user.
        $user = wp_get_current_user();

        // Check if user is admin.
        if( ! in_array( 'administrator', (array) $user->roles ) ) return;

        // Check if IP is allowed.
        if( $this->check_ip( $this->get_ip() ) ) return;

        // Check if any admins have 2FA setup.
        if( ! $this->check_admins() ) return;

        // Start output buffering.
        ob_start();

        // Data.
        $ip = $this->get_ip();

        $data = $_POST;

        // Load the lockdown template.
        include BUILT_PATH . 'views/security/lockdown.php';

        // Output the buffer.
        echo ob_get_clean();

        // Exit.
        exit;
        
    }

    /**
     * Check for allowed IPs.
     * 
     * Check if the user's IP is allowed.
     * 
     * @since   2.0.0
     */
    public function check_ip( $ip ) {

        // Global.
        global $wpdb;

        // Get results.
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}built_lockdown WHERE ip = %s", $ip ), ARRAY_A );

        // Check if results.
        if( empty( $results ) ) return false;

        // Return true.
        return true;

    }

    /**
     * Check admins.
     * 
     * Check if any admins have 2FA setup.
     * 
     * @since   2.0.0
     */
    public function check_admins() {

        // Get admins.
        $admins = get_users( [ 'role' => 'administrator' ] );

        // Loop through admins.
        foreach( $admins as $admin ) {

            // Check if 2FA is setup.
            if( get_user_meta( $admin->ID, 'google_authenticator_confirmed', true ) ) return true;

        }

        // Return false.
        return false;

    }

    /**
     * Get IP.
     * 
     * Get the user's IP address.
     * 
     * @since   2.0.0
     */
    public function get_ip() {

        // Check if WC_Geolocation exists.
        if( class_exists( 'WC_Geolocation' ) ) return \WC_Geolocation::get_ip_address();

        // Check for Cloudflare.
        if( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) return $_SERVER['HTTP_CF_CONNECTING_IP'];

        // Check for forwarded.
        if( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) return $_SERVER['HTTP_X_FORWARDED_FOR'];

        // Return remote address.
        return $_SERVER['REMOTE_ADDR'];

    }

}