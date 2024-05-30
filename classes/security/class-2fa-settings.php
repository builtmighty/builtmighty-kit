<?php
/**
 * 2FA Settings.
 * 
 * Requires 2FA for saving important settings.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
class built2FASettings {

    /**
     * Construct.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // If 2FA is disabled, peace out.
        if( ! defined( 'BUILT_2FA' ) || BUILT_2FA === false ) return;

        // Actions.
        add_action( 'update_option', [ $this, 'update_option' ], 10, 3 );
        add_action( 'woocommerce_update_options', [ $this, 'update_woocommerce' ], 10, 2 );
        add_action( 'admin_footer', [ $this, 'authentication_form' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

    }

    /**
     * On option update, check if user is good to go on update.
     * 
     * @since   2.0.0
     */
    public function update_option( $option, $value, $old_value ) {

        // Check user.
        if( ! $this->is_admin( get_current_user_id() ) ) return;

        // Check option.
        if( ! $this->is_checked( $option ) ) return;

        // Get current URL.
        $url = ( is_array( $_SERVER ) && isset( $_SERVER['HTTP_HOST'] ) ) ? 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] : 'localhost';

        // Get auth.
        $auth = new builtAuth();

        error_log( print_r( $_POST, true ) );

        // Check if code is set.
        if( ! isset( $_POST['authentication_code'] ) ) {

            // Stop the save.
            //wp_die( 'Authentication code required. <a href="' . $url . '" class="button button-primary">Retry</a>' );

        } elseif( ! $auth->authenticate( get_current_user_id(), $_POST['authentication_code'] ) ) {

            // Stop the save.
            //wp_die( 'Authentication code is incorrect. <a href="' . $url . '" class="button button-primary">Try Again</a>' );

        }

        // Save.
        return;

    }

    /**
     * TODO: Add 2FA when the following is set:
     * 
     * $_POST['action'] = woocommerce_toggle_gateway_enabled
     * $option = wc_square_settings
     * 
     */

    /**
     * On WooCommerce option update, check if user is good to go on update.
     * 
     * @since   2.0.0
     */
    public function update_woocommerce( $option, $value = NULL ) {

        // Check user.
        if( ! $this->is_admin( get_current_user_id() ) ) return;

        // Check option.
        if( ! $this->is_checked( $option ) ) return;

        // Log.
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );
        error_log( '[' . __FUNCTION__ . '] Option: ' . $option . ' | Value: ' . print_r( $value, true ) );

    }

    /**
     * Authentication form.
     * 
     * @since   2.0.0
     */
    public function authentication_form() {

        // Output. ?>
        <div id="builtmighty-setting-authentication" class="wrap builtmighty-setting-authentication" style="display:none">
            <div class="builtmighty-setting-form">
                <h1><?php _e( 'Authentication Required', 'builtmighty' ); ?></h1>
                <p><?php _e( 'Please enter your authentication code to save this value.', 'builtmighty' ); ?></p>
                <div class="builtmighty-fields">
                    <input type="text" id="builtmighty-setting-auth" name="builtmighty-setting-auth" placeholder="<?php _e( 'Authentication Code', 'builtmighty' ); ?>" />
                    <span id="builtmighty-submit-auth" class="button-primary woocommerce-save-button">Confirm + Save</span>
                </div>
            </div>
        </div><?php

    }

    /**
     * Enqueue.
     * 
     * @since   2.0.0
     */
    public function enqueue() {

        // CSS.
        wp_enqueue_style( 'builtmighty-2fa-settings', BUILT_URI . 'assets/2fa/2fa-settings.css', [], BUILT_VERSION );

        // JS.
        wp_enqueue_script( 'builtmighty-2fa-settings', BUILT_URI . 'assets/2fa/2fa-settings.js', [ 'jquery' ], BUILT_VERSION, true );
        wp_localize_script( 'builtmighty-2fa-settings', 'builtmighty', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'builtmighty-2fa-settings' )
        ] );

    }

    /**
     * Check if current user has 2FA enabled.
     * 
     * @since   2.0.0
     */
    public function is_admin( $user_id ) {

        // Check that user isn't empty.
        if( empty( $user_id ) ) return false;

        // Return.
        return ( ! empty( get_user_meta( $user_id, 'google_authenticator_confirmed', true ) ) ) ? true : false;

    }

    /**
     * Check if an option needs to be verified.
     * 
     * @since   2.0.0
     */
    public function is_checked( $option ) {

        // Equalize option.
        $option = strtolower( $option );

        // Set options.
        $options = [
            'gateway',
            'cheque',
            'bacs',
            'cod',
            'stripe',
            'square',
            'authorize.net',
            'paypal',
            'cybersource',
            'braintree',
            'mollie',
            'woocommerce_payments',
            'wc_payments',
            'amazon_pay',
            'apple_pay',
            'google_pay',
            'klarna',
            'afterpay',
            'affirm',
            'sezzle',
            'splitit',
            'laybuy',
            'clearpay',
            'zip',
            'paybright',
            'payu',
            'payfast',
            'paystack',
            'paytm',
            'razorpay',
            'mpesa',
            'flutterwave',
            'paygate',
            'peach_payments',
            'paytabs',
            'sagepay',
            'worldpay',
            '2checkout',
            'verifone'
        ];

        // Loop valid through options.
        foreach( $options as $o ) {

            // Check if string contains option.
            if( str_contains( $option, $o ) ) return true;

        }

        // Return.
        return false;

    }

}
