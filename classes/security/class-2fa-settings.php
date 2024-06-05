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
        if( defined( 'BUILT_2FA_SETTINGS' ) && BUILT_2FA_SETTINGS === false ) return;

        // Check access.
        add_action( 'admin_init', [ $this, 'setting_access' ] );

    }

    /**
     * Setting access.
     * 
     * @since   2.0.0
     */
    public function setting_access() {

        // Check if user is admin.
        if( ! current_user_can( 'manage_options' ) ) return;

        // Get current page.
        global $pagenow;

        // Get query vars.
        parse_str( (string)$_SERVER['QUERY_STRING'], $query );
        
        // Generate a unique cookie based off of the current page/query.
        $cookie = 'builtmighty_2fa_' . md5( $pagenow . json_encode( (array)$query ) );

        // Check for cookie.
        if( isset( $_COOKIE[$cookie] ) && $_COOKIE[$cookie] == 'true' ) return;

        // Check for blocked setting.
        if( $this->block_settings( $pagenow, $query ) ) {

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
                <h1>🔒Authentication Required</h1>
                <p>Please enter your authentication code to access this page containing sensitive information.</p>
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
    public function block_settings( $pagenow, $query ) {

        // Check if DOING_AJAX.
        if( defined( 'DOING_AJAX' ) && DOING_AJAX ) return false;

        // Get settings.
        $settings = $this->get_settings();

        // Get pagenows.
        $pagenows = array_keys( (array)$settings );

        // Check for pagenow.
        if( ! in_array( $pagenow, (array)$pagenows ) ) return false;

        // Check if pagenow is empty.
        if( empty( $settings[$pagenow] ) ) return true;

        // Pages.
        $pages = array_keys( (array)$settings[$pagenow] );

        // Check for page.
        if( isset( $query['page'] ) && ! in_array( $query['page'], (array)$pages ) ) return false;

        // Set match.
        $match = false;

        // Loop through.
        foreach( $settings[$pagenow][$query['page']] as $location ) {

            // Get location
            $location = explode( ':', $location );

            // Check if location is set.
            if( $query[$location[0]] === $location[1] ) {

                // Set match and break.
                $match = true;
                break;

            }
            

        }

        // Check if location is set.
        if( $match ) return true;

        // Return.
        return false;

    }

    /**
     * Get settings.
     * 
     * @since   2.0.0
     */
    public function get_settings() {

        // Define settings.
        $settings = [
            'user-new.php'          => [],
            'user-edit.php'         => [],
            'theme-editor.php'      => [],
            'plugin-editor.php'     => [],
            'plugin-install.php'    => [],
            'admin.php'             => [
                'wc-settings'   => [
                    'tab:checkout',
                    'tab:square',
                    'tab:advanced',
                ],
                'wc-admin'      => [
                    'path:/payments/overview',
                ],
            ],
        ];

        // Return.
        return $settings;

    }

}
