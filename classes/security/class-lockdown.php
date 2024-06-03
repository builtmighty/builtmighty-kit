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

        // Get user.
        $user = wp_get_current_user();

        // Check if user is admin.
        if( ! in_array( 'administrator', (array) $user->roles ) ) return;
        
    }

    /**
     * Check for allowed IPs.
     * 
     * Check if the user's IP is allowed.
     * 
     * @since   2.0.0
     */
    public function check_ip() {

        // Global.
        global $wpdb;

        // Query.
        $query = "SELECT * FROM {$wpdb->prefix}built_lockdown WHERE ip = %s";

        // Prepare.
        $prepare = $wpdb->prepare( $query, $this->get_ip() );

        // Get results.
        $results = $wpdb->get_results( $prepare );

        // Check if results.
        if( ! $results ) return false;

        // Return true.
        return true;

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
        if( class_exists( 'WC_Geolocation' ) ) return WC_Geolocation::get_ip_address();

        // Check for Cloudflare.
        if( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) return $_SERVER['HTTP_CF_CONNECTING_IP'];

        // Check for forwarded.
        if( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) return $_SERVER['HTTP_X_FORWARDED_FOR'];

        // Return remote address.
        return $_SERVER['REMOTE_ADDR'];

    }

}