<?php
/**
 * 2FA.
 * 
 * Requires 2FA for admins.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
class built2FA {

    /**
     * Construct.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // Actions.
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'login_form', [ $this, 'login' ] );

        // Filters.
        add_filter( 'wp_authenticate_user', [ $this, 'verify_login' ], 10, 2 );
        
    }

    /**
     * Add menu page.
     * 
     * Add 2FA settings page.
     * 
     * @since   2.0.0
     */
    public function menu() {

        // Add menu page.
        add_menu_page( 'Built 2FA', 'Built 2FA', 'manage_options', 'builtmighty-2fa', [ $this, 'settings' ], 'dashicons-lock', 100 );

    }

    /**
     * Login form.
     * 
     * Add 2FA to login form.
     * 
     * @since   2.0.0
     */
    public function login() { 
            
        // Output. ?>
        <p>
            <label for="authenticator_code">Authenticator Code<br />
            <input type="text" name="authenticator_code" id="authenticator_code" class="input" value="" size="20" /></label>
        </p><?php

    }

    /**
     * Verify login.
     * 
     * Verify 2FA login.
     * 
     * @since   2.0.0
     */
    public function verify_login( $user, $password ) {

        // Check for a secret.
        if( empty( get_user_meta( $user->ID, 'google_authenticator_secret', true ) ) ) return $user;

        // Authenticate.
        if( $this->authenticate( $user->ID, $_POST['authenticator_code'] ) ) return $user;

        // Error.
        return new WP_Error( 'authentication_failed', __( 'Invalid Authenticator code.' ) );

    }

    /**
     * Settings.
     * 
     * 2FA settings page.
     * 
     * @since   2.0.0
     */
    public function settings() {

        // Check if user is admin.
        if( ! current_user_can( 'manage_options' ) ) {

            // Denied.
            echo $this->denied();

            // Return.
            return;

        }

        // Get current user.
        $user_id = get_current_user_id();

        // Check status.
        if( empty( get_user_meta( $user_id, 'google_authenticator_confirmed', true ) ) && ! isset( $_GET['confirm'] ) ) {

            // Still need to setup.
            echo $this->setup( $user_id );
            
        } elseif( empty( get_user_meta( $user_id, 'google_authenticator_confirmed', true ) ) && isset( $_GET['confirm'] ) ) {

            // Confirm setup.
            echo $this->confirm( $user_id );

        } else {

            // Finished.
            echo $this->finished( $user_id );

        }

    }

    /** 
     * Setup.
     * 
     * Setup 2FA.
     * 
     * @since   2.0.0
     */
    public function setup( $user_id ) {

        // Get current user.
        $user = get_user_by( 'ID', $user_id );

        // Get secret.
        $secret = get_user_meta( 'google_authenticator_secret', $user_id );

        // Check for secret.
        if( ! $secret ) {

            // Get new Google Authenticator.
            $google = new GoogleAuthenticator();
            $secret = $google->generateSecret();

            // Save secret.
            update_user_meta( $user_id, 'google_authenticator_secret', $secret );

        }
        
        // Create the QR code.
        $result = Builder::create()
        ->writer(new PngWriter())
        ->data('otpauth://totp/WordPress:' . $user->user_login . '?secret=' . $secret . '&issuer=WordPress ' . get_bloginfo('name') )
        ->build();

        // Get the data URI
        $dataUri = $result->getDataUri();

        // Panel. ?>
        <div class="built-panel built-2fa">
            <?php echo $this->header(); ?>
            <div class="built-panel-inner">
                <div class="built-panel-qr">
                    <img src="<?php echo $dataUri; ?>" alt="QR Code">
                </div>
                <div class="built-panel-code">
                    <input type="text" name="google_authenticator_secret" id="google_authenticator_secret" value="<?php echo esc_attr( $secret ); ?>" class="regular-text" readonly />
                </div>
                <div class="built-panel-actions">
                    <a href="<?php echo admin_url( '/admin.php?page=builtmighty-2fa&confirm=true' ); ?>" class="button button-primary">Confirm</a>
                </div>
            </div>
        </div><?php

    } 

    /**
     * Confirm.
     * 
     * Confirm 2FA.
     * 
     * @since   2.0.0
     */
    public function confirm( $user_id ) {

        // Check for reset.
        if( isset( $_POST['google_authenticator_reset'] ) ) {

            // Reset.
            delete_user_meta( $user_id, 'google_authenticator_secret' );
            delete_user_meta( $user_id, 'google_authenticator_confirmed' );

            // Redirect.
            wp_safe_redirect( admin_url( '/admin.php?page=builtmighty-2fa' ) );
            exit;

        }

        // Check for code.
        if( isset( $_POST['google_authenticator_code'] ) ) {

            // Authenticate.
            if( $this->authenticate( $user_id, $_POST['google_authenticator_code'] ) ) {

                // Update user meta.
                update_user_meta( $user_id, 'google_authenticator_confirmed', true );

                // Redirect.
                wp_safe_redirect( admin_url( '/admin.php?page=builtmighty-2fa' ) );
                exit;

            } else {

                // Error.
                echo $this->error();

            }

        }

        // Panel. ?>
        <div class="built-panel built-2fa">
            <?php echo $this->header(); ?>
            <div class="built-panel-inner">
                <form method="post">
                    <div class="built-panel-code">
                        <input type="text" name="google_authenticator_code" id="google_authenticator_code" class="regular-text" placeholder="Enter your code" />
                    </div>
                    <div class="built-panel-actions">
                        <button type="submit" class="button button-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div><?php

    }

    /**
     * Finished.
     * 
     * Finished 2FA.
     * 
     * @since   2.0.0
     */
    public function finished( $user_id ) {

        // Check for reset.
        if( isset( $_POST['google_authenticator_reset'] ) ) {

            // Reset.
            delete_user_meta( $user_id, 'google_authenticator_secret' );
            delete_user_meta( $user_id, 'google_authenticator_confirmed' );

            // Redirect.
            wp_safe_redirect( admin_url( '/admin.php?page=builtmighty-2fa' ) );
            exit;

        }

        // Panel. ?>
        <div class="built-panel built-2fa">
            <?php echo $this->header(); ?>
            <div class="built-panel-inner">
                <p>Two Factor Authentication has been setup and confirmed.<br>
                You're good to go, unless you need to reset and restart the process.</p>
                <form method="post">
                    <div class="built-panel-actions">
                        <input type="hidden" name="google_authenticator_reset" value="true" />
                        <button type="submit" class="button button-primary">Reset</button>
                    </div>
                </form>
            </div>
        </div><?php
        
    }

    /**
     * Denied.
     * 
     * Display denied message.
     * 
     * @since   2.0.0
     */
    public function denied() {

        // Start output buffering.
        ob_start();

        // Display error message. ?>
        <div class="built-panel built-2fa-error">
            <h2>ACCESS DENIED</h2>
            <p>You do not have permission to access this page.</p>
        </div>
        <style>.built-2fa-error{display:flex;flex-direction:column;height:70vh;align-items:center;justify-content:center;}.built-2fa-error h2{margin:0;color:#fff}</style><?php

        // Return.
        return ob_get_clean();

    }

    /**
     * Setup error.
     * 
     * Display setup error message.
     * 
     * @since   2.0.0
     */
    public function error() {

        // Start output buffering.
        ob_start();

        // Display error message. ?>
        <div class="notice notice-error is-dismissible built-2fa-error-message">
            <form method="post">
                <input type="hidden" name="google_authenticator_reset" value="true" />
                <p>Sorry, but the code entered was incorrect. Please try again. Still having issues? <button type="submit">Reset</button></p>
            </form>
        </div><?php

        // Return.
        return ob_get_clean();

    }

    /**
     * Header.
     * 
     * Display header.
     * 
     * @since   2.0.0
     */
    public function header() {

        // Start output buffering.
        ob_start();

        // Display header. ?>
        <div class="built-panel-header">
            <div class="built-panel-icon">
                <span>🔒</span>
            </div>
            <div class="built-panel-title">
                <h2>Two Factor Authentication</h2>
            </div>
        </div><?php

        // Return.
        return ob_get_clean();

    }

    /**
     * Authenticate.
     * 
     * Authenticate user.
     * 
     * @since   2.0.0
     */
    public function authenticate( $user_id, $code ) {

        // Check for secret.
        if( empty( get_user_meta( $user_id, 'google_authenticator_secret', true ) ) ) return false;

        // Get secret.
        $secret = get_user_meta( $user_id, 'google_authenticator_secret', true );

        // Check for code.
        if( isset( $code ) ) {

            // Get Google Authenticator.
            $google = new GoogleAuthenticator();

            // Sanitize the code.
            $code = sanitize_text_field( $code );

            // Check code.
            if( $google->checkCode( $secret, $code ) ) {

                // Return true.
                return true;

            }

        }

        // Return false.
        return false;

    }

}