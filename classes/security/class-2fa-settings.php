<?php
/**
 * 2FA Settings.
 * 
 * Requires 2FA for saving important settings.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
namespace BuiltMightyKit\Security;
class built2FASettings {

    /**
     * Construct.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // If 2FA is disabled, peace out.
        if( ! defined( 'BUILT_2FA' ) || BUILT_2FA === false ) return;

        // Check if 2FA settings is disabled.
        if( ! defined( 'BUILT_2FA_SETTINGS' ) && BUILT_2FA_SETTINGS === false ) return;

        // Check access.
        add_action( 'admin_init', [ $this, 'setting_access' ] );

        // Add settings.
        add_action( '\BuiltMightyKit\Core\add_settings', [ $this, 'add_settings' ] );

    }

    /**
     * Setting access.
     * 
     * @since   2.0.0
     */
    public function setting_access() {

        // Check if user is admin.
        if( ! current_user_can( 'manage_options' ) ) return;

        // Set page.
        $page = $_SERVER['REQUEST_URI'];
        
        // Generate a unique cookie based off of the current page/query.
        $cookie = 'builtmighty_2fa_' . md5( $page );

        // Check for cookie.
        if( isset( $_COOKIE[$cookie] ) && $_COOKIE[$cookie] == 'true' ) return;

        // Check for blocked setting.
        if( $this->block_settings( $page ) ) {

            // Check for authentication.
            if( isset( $_POST['google_authenticator_code'] ) ) {

                // Auth.
                $auth = new \BuiltMightyKit\Security\builtAuth();

                // Authenticate.
                if( $auth->authenticate( get_current_user_id(), $_POST['google_authenticator_code'] ) ) {

                    // Set a short-term cookie that allows access to this page.
                    setcookie( $cookie, 'true', time() + 300, COOKIEPATH, COOKIE_DOMAIN );

                    // Return.
                    return;

                }

            }

            // Start output buffering.
            ob_start(); 
            
            // Form. ?>
            <div class="builtmighty-lockdown-form">
                <h1>🔒Two Factor Authentication Required</h1>
                <p>Please enter your two factor authentication code to access this page, which contains sensitive information.</p>
                <form method="post">
                    <div class="built-panel-code">
                        <input type="text" name="google_authenticator_code" id="google_authenticator_code" class="regular-text" placeholder="Enter your code" />
                    </div>
                    <div class="built-panel-actions">
                        <button type="submit" class="button button-primary">Access Setting</button>
                    </div>
                </form>
            </div>
            <style>.built-panel-code{margin:0 0 15px}.built-panel-code input{padding:5px 10px;border-radius:4px;border:1px solid rgb(0 0 0 / 30%)}</style><?php

            // Get form.
            $form = ob_get_clean();

            // Output form.
            wp_die( $form );

        }

    }

    /**
     * Block settings.
     * 
     * @since   2.0.0
     */
    public function block_settings( $page ) {

        // Check if DOING_AJAX.
        if( defined( 'DOING_AJAX' ) && DOING_AJAX ) return false;

        // Get settings.
        $settings = unserialize( get_option( '2fa_settings' ) );

        // Merge default settings.
        $settings = array_merge( (array)$settings, (array)$this->default_settings() );

        // Set match.
        $match = false;

        // Loop through settings.
        foreach( $settings as $setting ) {

            // Check if page contains setting.
            if( strpos( $page, $setting ) !== false ) {

                // Set match.
                $match = true;
                break;

            }

        }

        // Return.
        return $match;

    }

    /**
     * Add settings.
     * 
     * @since   2.0.0
     */
    public function add_settings() {

        // Admin.
        $admin = new \BuiltMightyKit\Core\builtAdmin();

        // Field. 
        echo $admin->field( '2fa_settings', '2FA Settings', [
            'type'      => 'checkbox',
            'id'        => '2fa_settings',
            'label'     => '2FA for Settings',
            'options'   => (array)$this->dynamic_settings(),
            'default'   => false
        ] );
    
    }

    /**
     * Default settings.
     * 
     * Always set these pages to 2FA authentication.
     * 
     * @since   2.0.0
     */
    public function default_settings() {

        // Set and return default.
        return [
            'admin.php?page=wc-settings&tab=checkout',
            'user-new.php',
            'user-edit.php?user_id=',
            'theme-editor.php',
            'plugin-editor.php',
            'plugin-install.php',
            'widgets.php',
        ];

    }

    /** 
     * Dynamic settings.
     * 
     * @since   2.0.0
     */
    public function dynamic_settings() {

        // Globals.
        global $submenu, $menu;

        // Set menu items.
        $menu_items = [];

        // Loop.
        foreach( $menu as $item ) {

            // Check for title.
            if( empty( $item[0] ) ) continue;

            // Check label.
            if( $this->check_option( $item[0] ) ) continue;

            // Add to menu items.
            $menu_items[$item[2]] = $this->clean_label( $item[0] );

        }

        // Loop through submenu.
        foreach( $submenu as $parent => $items ) {

            // Loop through items.
            foreach( $items as $item ) {

                // Check for title.
                if( empty( $item[0] ) ) continue;

                // Check label.
                if( $this->check_option( $item[0] ) ) continue;

                // Add to menu items.
                $menu_items[$item[2]] = $this->clean_label( $item[0] );

            }

        }
        
        // Return.
        return $menu_items;

    }

    /** 
     * Clean label.
     * 
     * @since   2.0.0
     */
    public function clean_label( $label ) {

        // Remove trailing numbers.
        $label = preg_replace('/(.*?)([0-9*])/', '$1', $label );

        // Return.
        return $label;
        
    }

    /**
     * Check option.
     * 
     * @since   2.0.0
     */
    public function check_option( $label ) {

        // Set labels.
        $labels = [
            'home',
            'dashboard',
            'built mighty',
            'built 2fa'
        ];

        // Check for label.
        if( in_array( strtolower( $label ), (array)$labels ) ) return true;

        // Return.
        return false;

    }

}
