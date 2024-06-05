<?php
/**
 * 2FA.
 * 
 * Requires 2FA for admins.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
namespace BuiltMightyKit\Security;
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

        // Check if turned on.
        if( ! defined( 'BUILT_2FA' ) || ! BUILT_2FA ) return;

        // Actions.
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'login_form', [ $this, 'login' ] );
        add_action( 'woocommerce_login_form', [ $this, 'login' ] );
        add_action( 'login_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_check_2fa', [ $this, 'check_2fa' ] );
        add_action( 'wp_ajax_nopriv_check_2fa', [ $this, 'check_2fa' ] );

        // Filters.
        add_filter( 'wp_authenticate_user', [ $this, 'verify_login' ], 10, 2 );
        
    }

    /**
     * Force admins to use 2FA.
     * 
     * @since   2.0.0
     */
    public function init() {

        // Check for error message.
        if( ! is_user_logged_in() && isset( $_GET['login'] ) && $_GET['login'] == 'failed' ) {

            // Check if WooCommerce notices are available.
            if( function_exists( 'wc_add_notice' ) ) {

                // Add notice.
                wc_add_notice( __( 'Login failed: Please check your username and password and try again. If you are an administrator and have setup 2FA, please login to the admin portal, so that you can supply your 2FA code.' ), 'error' );

            }

        }

        // Check if user is logged in.
        if( ! is_user_logged_in() ) return;

        // Check if user is admin.
        if( ! current_user_can( 'manage_options' ) ) return;

        // Get current user.
        $user_id = get_current_user_id();

        // Check if user is trying to logout.
        if( isset( $_GET['action'] ) && $_GET['action'] == 'logout' ) return;

        // Check if we are on the 2FA page.
        if( isset( $_GET['page'] ) && $_GET['page'] == 'builtmighty-2fa' ) return;

        // Check if user has 2FA setup.
        if( empty( get_user_meta( $user_id, 'google_authenticator_confirmed', true ) ) ) {

            // Redirect.
            wp_safe_redirect( admin_url( '/admin.php?page=builtmighty-2fa' ) );
            exit;

        }

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
            
        // Output.
        include BUILT_PATH . 'views/security/2fa-login.php';

    }

    /**
     * Enqueue.
     * 
     * Enqueue scripts and styles.
     * 
     * @since   2.0.0
     */
    public function enqueue() {

        // CSS.
        wp_enqueue_style( 'built-2fa', BUILT_URI . 'assets/security/2fa.css', [], BUILT_VERSION );

        // JS.
        wp_enqueue_script( 'built-2fa', BUILT_URI . 'assets/security/2fa.js', [ 'jquery' ], BUILT_VERSION, true );

        // Localize.
        wp_localize_script( 'built-2fa', 'built2FA', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'built-2fa' )
        ] );

    }

    /**
     * Check if 2FA is needed.
     * 
     * @since   2.0.0
     */
    public function check_2fa() {
        
        // Verify nonce.
        if( ! wp_verify_nonce( $_POST['nonce'], 'built-2fa' ) ) wp_die();

        // Check for user.
        if( ! isset( $_POST['login'] ) ) {

            // Error, but continue and submit form to let WordPress handle.
            echo 'continue';
            wp_die();

        }

        // Get user.
        $user = get_user_by( 'login', sanitize_text_field( $_POST['login'] ) );

        // Check for user.
        if( ! $user ) {

            // Error, but continue and submit form to let WordPress handle.
            echo 'continue';
            wp_die();

        }

        // Check if user is admin.
        if( ! in_array( 'administrator', (array)$user->roles ) ) {

            // 2FA is not required, so continue with login.
            echo 'continue';
            wp_die();

        }

        // Check for 2FA.
        if( empty( get_user_meta( $user->ID, 'google_authenticator_confirmed', true ) ) ) {

            // 2FA hasn't been setup yet, so continue.
            echo 'continue';
            wp_die();

        }

        // 2FA is required.
        echo 'confirm';
        wp_die();

    }

    /**
     * Verify login.
     * 
     * Verify 2FA login.
     * 
     * @since   2.0.0
     */
    public function verify_login( $user, $password ) {

        // Check if user is admin.
        if( ! in_array( 'administrator', (array)$user->roles ) ) return $user;

        // Check for 2FA setup.
        if( empty( get_user_meta( $user->ID, 'google_authenticator_confirmed', true ) ) ) return $user;

        // Check if set.
        if( ! isset( $_POST['authenticator_code'] ) ) return new WP_Error( 'authentication_failed', __( 'Invalid authentication code. Please try again.' ) );

        // Get auth.
        $auth = new \BuiltMightyKit\Security\builtAuth();

        // Authenticate.
        if( $auth->authenticate( $user->ID, $_POST['authenticator_code'] ) ) return $user;

        // Set error message dynamically, based on the page.
        if( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false || defined( 'BUILT_ENDPOINT' ) && ( $_SERVER['REQUEST_URI'] === '/' . BUILT_ENDPOINT || $_SERVER['REQUEST_URI'] === '/' . BUILT_ENDPOINT . '/' ) ) {

            // Check if endpoint is set.
            if( defined( 'BUILT_ENDPOINT' ) ) {

                // Redirect.
                wp_redirect( home_url( '/' . BUILT_ENDPOINT . '/?login=failed' ) );
                exit;

            } else {

                // Error and redirect.
                wp_redirect( wp_login_url( add_query_arg( [ 'login' => 'failed' ], $_SERVER['REQUEST_URI'] ) ) );
                exit;

            }

        } else {

            // Check if WooCommerce login.
            if( isset( $_POST['woocommerce-login-nonce'] ) && function_exists( 'wc_add_notice' ) ) {

                // Add notice.
                wp_die( 'Invalid authentication code.' );

            } else {

                // Error.
                return new WP_Error( 'authentication_failed', __( 'Invalid authentication code. If you are an admin, please visit the WordPress login page.' ) );

            }

        }

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
        $secret = get_user_meta( $user_id, 'google_authenticator_secret', true );

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

        // Panel.
        include BUILT_PATH . 'views/security/2fa-setup.php';

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

            // Auth.
            $auth = new \BuiltMightyKit\Security\builtAuth();

            // Authenticate.
            if( $auth->authenticate( $user_id, $_POST['google_authenticator_code'] ) ) {

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

        // Panel.
        include BUILT_PATH . 'views/security/2fa-confirm.php';

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

        // Panel.
        include BUILT_PATH . 'views/security/2fa-finished.php';
        
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

        // Display error message.
        include BUILT_PATH . 'views/security/2fa-denied.php';
        
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

        // Display error message.
        include BUILT_PATH . 'views/security/2fa-error.php';

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

        // Header.
        include BUILT_PATH . 'views/security/2fa-header.php';

        // Return.
        return ob_get_clean();

    }

}