<?php
/**
 * Two Factor Authentication.
 *
 * Enables two-factor authentication for logging in.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 5.0.0
 */
namespace BuiltMightyKit\Public;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
class login_security {

    /**
     * Auth.
     *
     * @since   1.0.0
     */
    private $auth;

    /**
     * Construct.
     *
     * @since   1.0.0
     */
    public function __construct() {

        // Check if enabled.
        if( get_option( 'kit_enable_2fa' ) !== 'enable' ) return;

        // Get auth.
        $this->auth = new \BuiltMightyKit\Utility\authentication();

        // Verify 2FA code.
        add_filter( 'wp_authenticate_user', [ $this, 'verify_login' ], 10, 2 );

        // Check user.
        add_action( 'wp_ajax_check_user', [ $this, 'check_user' ] );
        add_action( 'wp_ajax_nopriv_check_user', [ $this, 'check_user' ] );

        // Add to login form.
        add_action( 'login_form', [ $this, 'login' ] );
        add_action( 'woocommerce_login_form', [ $this, 'login' ] );

        // Enqueue.
        add_action( 'login_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

        // Create setup page.
        add_action( 'template_redirect', [ $this, 'setup' ] );

        // Add user fields for administration.
        add_action( 'show_user_profile', [ $this, 'user_administration' ] );
        add_action( 'edit_user_profile', [ $this, 'user_administration' ] );
        add_action( 'personal_options_update', [ $this, 'user_save' ] );
        add_action( 'edit_user_profile_update', [ $this, 'user_save' ] );

        // Check if WooCommerce is active.
        if( ! in_array( 'woocommerce/woocommerce.php', (array)apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

        // Add tab and endpoint.
        add_filter( 'woocommerce_account_menu_items', [ $this, 'woocommerce_account' ] );
        add_action( 'woocommerce_account_security_endpoint', [ $this, 'security_endpoint' ] );
        add_action( 'init', [ $this, 'security_rewrite' ] );

    }

    /**
     * Verify login.
     *
     * On login, check if the user requires 2FA. If 2FA is required, authenticate the code.
     *
     * @param   object  $user
     * @param   string  $password
     *
     * @since   1.0.0
     * @since   4.4.0  Added rate limiting
     */
    public function verify_login( $user, $password ) {

        // Check if user requires 2FA.
        if( ! $this->auth->is_required( $user ) ) return $user;

        // Check rate limiting.
        if ( $this->auth->is_rate_limited( $user->ID, 'auth_code' ) ) {
            $remaining = $this->auth->get_lockout_remaining( $user->ID, 'auth_code' );
            $minutes = ceil( $remaining / 60 );
            /* translators: %d: Number of minutes until lockout expires */
            return new \WP_Error( 'rate_limited', sprintf( __( 'Too many failed attempts. Please try again in %d minutes.' ), $minutes ) );
        }

        // Check if 2FA code is set.
        if( ! $this->auth->get_code( $_POST ) ) {

            // Return error.
            return new \WP_Error( 'authentication_failed', __( 'Invalid authentication code. Please try again.' ) );

        }

        // Authenticate, and if valid, allow the user in.
        if( $this->auth->authenticate( $user->ID, $this->auth->get_code( $_POST ), 'login' ) ) return $user;

        // Return error.
        return new \WP_Error( 'authentication_failed', __( 'Invalid authentication code. Please try again.' ) );

    }

    /**
     * Check user.
     *
     * On login, check if user requires 2FA field.
     * Returns consistent responses to prevent user enumeration.
     *
     * @since   1.0.0
     * @since   4.4.0  Added rate limiting
     * @since   5.0.0  Fixed user enumeration
     */
    public function check_user() {

        // Get client IP for rate limiting.
        $client_ip = $this->auth->get_client_ip();

        // Check IP-based rate limiting to prevent enumeration.
        if ( $this->auth->is_rate_limited( $client_ip, 'login_check' ) ) {
            wp_send_json( [
                'status'  => 'error',
                'message' => '',
            ] );
        }

        // Check for nonce.
        if( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'built-twofactor' ) ) {
            wp_send_json( [
                'status'  => 'error',
                'message' => '',
            ] );
        }

        // Check for username.
        if( ! isset( $_POST['login'] ) || empty( $_POST['login'] ) ) {
            wp_send_json( [
                'status'  => 'error',
                'message' => '',
            ] );
        }

        // Get user.
        $login = sanitize_text_field( $_POST['login'] );
        $user  = get_user_by( 'login', $login );
        if ( ! $user ) {
            $user = get_user_by( 'email', $login );
        }

        // Check for user and 2FA requirement.
        // Use consistent timing to prevent enumeration via response time.
        if( ! $user || ! $this->auth->is_required( $user ) ) {

            // Add a small delay to match the timing of the success path.
            usleep( random_int( 50000, 150000 ) );

            // Record failed attempt for rate limiting.
            $this->auth->record_failed_attempt( $client_ip, 'login_check' );

            // Return generic error - same structure as success to prevent enumeration.
            wp_send_json( [
                'status'    => 'error',
                'message'   => '',
            ] );

        }

        // Check if app 2FA is setup.
        if( ! $this->auth->is_enabled( $user ) ) {

            // Send email code.
            $this->auth->send_code( $user );
            wp_send_json( [
                'status'    => 'success',
                'message'   => '🔒Authentication Code (Email)',
            ] );

        } else {

            // Respond.
            wp_send_json( [
                'status'    => 'success',
                'message'   => '🔒Authentication Code (App)',
            ] );

        }

    }

    /**
     * Login form.
     *
     * Add authentication code to login form.
     *
     * @since   1.0.0
     */
    public function login() {
        include KIT_PATH . 'public/views/login.php';
    }

    /**
     * Setup form.
     *
     * Add setup form with CSRF protection.
     *
     * @since   1.0.0
     * @since   5.0.0  Added CSRF nonce verification, replaced QR library
     */
    public function setup() {

        // Is 2FA required?
        if( ! get_option( 'kit_enable_2fa' ) ) return;

        // Set request URI.
        $request_uri = trailingslashit( strtok( $_SERVER['REQUEST_URI'], '?' ) );

        // Check request URI.
        if( $request_uri !== '/security/' ) return;

        // Check for required query string.
        if( ! isset( $_GET['key'] ) ) return;

        // Check key.
        if( ! $this->auth->verify_key( $_GET['key'] ) ) return;

        // Key.
        $key    = $this->auth->get_key( $_GET['key'] );
        $code   = $this->auth->get_code( $_POST );

        // Check for authentication code.
        if( $code ) {

            // Verify CSRF nonce.
            if ( ! isset( $_POST['_kit_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kit_2fa_nonce'] ) ), 'kit_2fa_confirm' ) ) {
                wp_redirect( site_url( '/security?key=' . rawurlencode( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) . '&confirm=true&status=error' ) );
                exit;
            }

            // Confirm setup.
            $status = $this->auth->confirm( $_GET['key'], $code );

            // Set redirect.
            $redirect = ( ! $status )
                ? site_url( '/security?key=' . rawurlencode( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) . '&confirm=true&status=error' )
                : site_url( '/security?key=' . rawurlencode( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) . '&status=confirmed' );

            // Redirect.
            wp_redirect( $redirect );
            exit;

        }

        // Get user.
        $user = get_user_by( 'ID', $key['user_id'] );

        // Get secret.
        $secret = $this->auth->generate_secret( $user );

        // Create QR code using BaconQrCode (SVG).
        $renderer = new ImageRenderer(
            new RendererStyle( 200 ),
            new SvgImageBackEnd()
        );
        $writer  = new Writer( $renderer );
        $qrData  = 'otpauth://totp/WordPress:' . rawurlencode( $user->user_login ) . '?secret=' . $secret . '&issuer=' . rawurlencode( 'WordPress ' . get_bloginfo( 'name' ) );
        $svg     = $writer->writeString( $qrData );
        $dataUri = 'data:image/svg+xml;base64,' . base64_encode( $svg );

        // Include.
        include KIT_PATH . 'public/views/security.php';
        exit;

    }

    /**
     * User Administration.
     *
     * Add user fields for administration.
     *
     * @param   object  $user
     *
     * @since   1.0.0
     */
    public function user_administration( $user ) {

        // Check user role.
        if( ! current_user_can( 'administrator' ) ) return;

        // Check if this user requires 2FA.
        if( ! $this->auth->is_required( $user ) ) return;

        // Administration. ?>
        <h2>Two-Factor Authentication</h2>
        <table class="form-table">
            <tr>
                <th><span style="width:40px;display:inline-block;">Email</span><span style="background:green;color:#fff;display:inline-block;padding:2.5px 5px;border-radius:8px;width:60px;text-align:center;">Active</span></th>
                <td>
                    <input type="text" value="<?php echo esc_attr( $user->user_email ); ?>" readonly/>
                </td>
            </tr>
            <tr>
                <th><span style="width:40px;display:inline-block;">App</span><?php

                    // Check status.
                    if( $this->auth->is_enabled( $user ) ) {

                        // Active.
                        echo '<span style="background:green;color:#fff;display:inline-block;padding:2.5px 5px;border-radius:8px;width:60px;text-align:center;">Active</span>';

                    } else {

                        // Not active.
                        echo '<span style="background:red;color:#fff;display:inline-block;padding:2.5px 5px;border-radius:8px;width:60px;text-align:center;">Inactive</span>';

                    } ?>
                </th>
                <td><?php

                    // Check status.
                    if( $this->auth->is_enabled( $user ) ) {

                        // Backup code count.
                        $backup_count = $this->auth->get_backup_code_count( $user->ID ); ?>
                        <button name="authentication_reset" class="button button-secondary">Reset 2FA</button>
                        <span style="margin-left:10px;">Backup codes remaining: <?php echo esc_html( $backup_count ); ?>/10</span><?php

                    } else {

                        // Check if profile is ours.
                        if( $user->ID == get_current_user_id() ) {

                            // Check if set.
                            $setup = get_user_meta( $user->ID, 'authentication_setup', true );
                            if( ! empty( $setup ) ) {

                                // Get key from setup data.
                                $setup_key = is_array( $setup ) ? $setup['key'] : $setup;
                                $key = base64_encode( $user->ID . ':' . $setup_key );

                            } else {

                                // Generate key.
                                $key = $this->auth->generate_key( $user, true );

                            }

                            // Generate secret.
                            $this->auth->generate_secret( $user );

                            // Button. ?>
                            <a href="<?php echo esc_url( site_url( '/security?key=' . $key ) ); ?>" class="button button-primary">Setup App</a><?php

                        } else {

                            // Button. ?>
                            <button name="authentication_setup" class="button button-primary">Send App Setup Email</button><?php

                        }

                    } ?>

                </td>
            </tr>
        </table><?php

    }

    /**
     * Save user fields.
     *
     * Save user fields.
     *
     * @param   int     $user_id
     *
     * @since   1.0.0
     */
   public function user_save( $user_id ) {

        // Check user role.
        if( ! current_user_can( 'administrator' ) ) return;

        // Get user.
        $user = get_user_by( 'ID', $user_id );

        // Check for reset.
        if( isset( $_POST['authentication_reset'] ) ) {

            // Reset.
            $this->auth->disable( $user );

        } elseif( isset( $_POST['authentication_setup'] ) ) {

            // Send setup.
            $this->auth->send_setup( $user );

        }

    }

    /**
     * WooCommerce Account items.
     *
     * Add 2FA to WooCommerce account items.
     *
     * @param   array   $items
     *
     * @since   1.0.0
     */
    public function woocommerce_account( $items ) {

       // Get current user.
       $user = get_user_by( 'ID', get_current_user_id() );

       // Check if 2FA is required.
       if( ! $this->auth->is_required( $user ) ) return $items;

       // Set new.
       $new_items = [];

       // Loop through items.
       foreach( $items as $key => $item ) {

            // Add item.
            $new_items[$key] = $item;

            // Check if edit account.
            if( $key == 'edit-account' ) {

                // Add 2FA.
                $new_items['security'] = 'Two-Factor Authentication';

            }

        }

       // Return.
       return $new_items;

    }

    /**
     * Security endpoint.
     *
     * Add 2FA to WooCommerce account security endpoint.
     *
     * @since   2.0.0
     */
    public function security_endpoint() {

        // Get current user.
        $user = get_user_by( 'ID', get_current_user_id() );

        // Header. ?>
        <h2>Two-Factor Authentication</h2>
        <p>For account security, we recommend two-factor authentication.</p>
        <p>Email Status: <span style="color:white;display:inline-block;background:green;line-height:1;padding:5px 10px;border-radius:6px;">Active</span></p>
        <p>App Status: <?php

            // Check if setup.
            if( $this->auth->is_enabled( $user ) && ! isset( $_GET['setup'] ) ) {

                // Active.
                echo '<span style="color:white;display:inline-block;background:green;line-height:1;padding:5px 10px;border-radius:6px;">Active</span>';

            } else {

                // Inactive.
                echo '<span style="color:white;display:inline-block;background:red;line-height:1;padding:5px 10px;border-radius:6px;">Inactive</span>';

            } ?>

        </p><?php

        // Check for setup.
        if( $this->auth->is_enabled( $user ) && ! isset( $_GET['setup'] ) ) {

            // Reset button. ?>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'security' ) . '?setup=reset' ); ?>" class="button">Reset</a><?php

        } elseif( isset( $_GET['setup'] ) && $_GET['setup'] == 'true' ) {

            // Send email.
            $this->auth->send_setup( $user );

            // Output. ?>
            <p>A setup email has been sent to your email address.</p><?php

        } elseif( isset( $_GET['setup'] ) && $_GET['setup'] == 'reset' ) {

            // Reset.
            $this->auth->disable( $user );

            // Output. ?>
            <p>Two-Factor Authentication has been reset.</p><?php

            // Button. ?>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'security' ) . '?setup=true' ); ?>" class="button">Setup App</a><?php

        } else {

            // Button. ?>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'security' ) . '?setup=true' ); ?>" class="button">Setup App</a><?php

        }

    }

    /**
     * Load security endpoint.
     *
     * Load security endpoint.
     *
     * @since   2.0.0
     */
    public function security_rewrite() {

        // Add.
        add_rewrite_endpoint( 'security', EP_ROOT | EP_PAGES );

    }

    /**
     * Enqueue.
     *
     * @since   1.0.0
     */
    public function enqueue() {

        // Set request URI.
        $request_uri = trailingslashit( strtok( $_SERVER['REQUEST_URI'], '?' ) );

        // CSS.
        wp_enqueue_style( 'built-login-security', KIT_URI . 'public/css/login-security.css', [], KIT_VERSION );

        // If security page.
        if( $request_uri == '/security/' ) {

            // Load.
            wp_enqueue_style( 'built-security', KIT_URI . 'public/css/security.css', [], KIT_VERSION );

        }

        // JS.
        wp_enqueue_script( 'built-login-security', KIT_URI . 'public/js/login-security.js', [ 'jquery' ], KIT_VERSION, true );

        // Localize.
        wp_localize_script( 'built-login-security', 'builttwofactor', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'built-twofactor' )
        ] );

    }

}
