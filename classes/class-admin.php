<?php
/**
 * Admin.
 * 
 * Adds a settings panel for admins.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtAdmin {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Add admin menu.
        add_action( 'admin_menu', [ $this, 'menu' ] );

    }

    /**
     * Add admin menu.
     * 
     * @since   1.0.0
     */
    public function menu() {

        // Get current user.
        $user = wp_get_current_user();

        // If user isn't an admin, later gator.
        if( ! in_array( 'administrator', $user->roles ) ) return;

        // Check if user email is @builtmighty.com.
        if( strpos( $user->user_email, '@builtmighty.com' ) === false ) return;

        // Add admin menu.
        add_menu_page( 
            'Built Mighty', 
            'Built Mighty', 
            'manage_options', 
            'builtmighty', 
            [ $this, 'page' ], 
            'dashicons-admin-tools', 
            99 
        );

    }

    /**
     * Admin page.
     * 
     * @since   1.0.0
     */
    public function page() {

        echo 'Hello';

    }

}