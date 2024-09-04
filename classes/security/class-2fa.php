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

        // Verify login.
        add_filter( 'wp_authenticate_user', [ $this, 'verify_login' ], 10, 2 );
        add_action( 'woocommerce_process_login_errors', [ $this, 'verify_woo_login' ], 10, 3 );

        // Check if 2FA is required.
        add_action( 'wp_ajax_check_2fa', [ $this, 'check_2fa' ] );
        add_action( 'wp_ajax_nopriv_check_2fa', [ $this, 'check_2fa' ] );

        // Enqueue.
        add_action( 'login_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

        // Add to login form.
        add_action( 'login_form', [ $this, 'login' ] );
        add_action( 'woocommerce_login_form', [ $this, 'login' ] );

        // Create setup page.
        add_action( 'template_redirect', [ $this, 'setup' ] );

        // Add user fields for administration.
        add_action( 'show_user_profile', [ $this, 'user_administration' ] );
        add_action( 'edit_user_profile', [ $this, 'user_administration' ] );
        add_action( 'personal_options_update', [ $this, 'user_save' ] );
        add_action( 'edit_user_profile_update', [ $this, 'user_save' ] );

        // Add WooCommerce account items.
        if( in_array( 'woocommerce/woocommerce.php', (array)apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

            // Add tab and endpoint.
            add_filter( 'woocommerce_account_menu_items', [ $this, 'woocommerce_account' ] );
            add_action( 'woocommerce_account_security_endpoint', [ $this, 'security_endpoint' ] );
            add_action( 'init', [ $this, 'security_rewrite' ] );

        }
        
    }

    /**
     * Verify login.
     * 
     * Check for 2FA, run setup flow, and/or confirm 2FA code.
     * 
     * @since   2.0.0
     */
    public function verify_login( $user, $password ) {

        // Check if user requires 2FA and if not, allow normal login.
        if( ! $this->check_user( $user ) ) return $user;

        // Check if user has 2FA setup on their account.
        if( ! $this->check_confirmed( $user ) ) {

            // Send setup email.
            $this->send_setup_email( $user );

            // Return.
            return new \WP_Error( 'authentication_failed', __( 'Two-Factor Authentication is required for your account. Please check your email to start setup.' ) );

        }

        // Get log.
        $log = new \BuiltMightyKit\Security\builtLockdownLog();

        // Set data.
        $data = [
            'ip'        => $_SERVER['REMOTE_ADDR'],
            'user'      => $user->ID,
            'agent'     => $_SERVER['HTTP_USER_AGENT'],
            'type'      => '2FA',
        ];

        // Check if set.
        if( ! isset( $_POST['authenticator_code'] ) ) {

            // Set data.
            $data['status'] = 'missing';

            // Log failure.
            $log->log( $data );

            // Return.
            return new WP_Error( 'authentication_failed', __( 'Invalid authentication code. Please try again.' ) );

        }

        // Get auth.
        $auth = new \BuiltMightyKit\Security\builtAuth();

        // Authenticate and if valid, allow the user to login.
        if( $auth->authenticate( $user->ID, $_POST['authenticator_code'] ) ) return $user;

        // Set data.
        $data['status'] = 'failed';

        // Log failure.
        $log->log( $data );

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
     * Verify WooCommerce login.
     * 
     * Check for 2FA, run setup flow, and/or confirm 2FA code.
     * 
     * @since   2.0.0
     * @param mixed $validation_error
     * @param mixed $username
     * @param mixed $password
     * @return mixed
     */
    public function verify_woo_login( $validation_error, $username, $password ) {

        // Get user by login.
        $user = ( ! get_user_by( 'login', $username ) ) ? get_user_by( 'email', $username ) : get_user_by( 'login', $username );

        // Check if user requires 2FA.
        if( ! $this->check_user( $user ) ) return $user;

        // Check for 2FA setup.
        if( ! $this->check_confirmed( $user ) ) {

            // Send email.
            $this->send_setup_email( $user );

            // Add validation error.
            $validation_error->add( 'authentication_failed', __( 'Two-Factor Authentication is required for your account. Please check your email to start setup.' ) );

            // Return.
            return $validation_error;

        }

        // Get log.
        $log = new \BuiltMightyKit\Security\builtLockdownLog();

        // Set data.
        $data = [
            'ip'        => $_SERVER['REMOTE_ADDR'],
            'user'      => $user->ID,
            'agent'     => $_SERVER['HTTP_USER_AGENT'],
            'type'      => '2FA',
        ];

        // Check if set.
        if( ! isset( $_POST['authenticator_code'] ) ) {

            // Set data.
            $data['status'] = 'missing';

            // Log failure.
            $log->log( $data );

            // Add validation error.
            $validation_error->add( 'authentication_failed', __( 'Authentication code missing. Please try again.' ) );

            // Return.
            return $validation_error;

        }

        // Get auth.
        $auth = new \BuiltMightyKit\Security\builtAuth();

        // Authenticate.
        if( ! $auth->authenticate( $user->ID, $_POST['authenticator_code'] ) ) {

            // Add validation error.
            $validation_error->add( 'authentication_failed', __( 'Invalid authentication code. Please try again.' ) );

        }

        // Return.
        return $validation_error;

    }

    /**
     * Create setup email.
     * 
     * Create email for 2FA setup.
     * 
     * @since   2.0.0
     * @param   int     $user_id
     * @param   string  $key
     * @return  void
     */
    public function send_setup_email( $user ) {

        // Generate keys.
        $key = $this->generate_setup( $user, true );
        $this->generate_secret( $user );

        // Set subject.
        $subject = 'Two-Factor Authentication Setup';

        // Start output buffering.
        ob_start();

        // Compose. ?>
        Hello <?php echo $user->display_name; ?>,<br><br>
        Two-Factor Authenication is required for your account on <?php echo get_bloginfo( 'name' ); ?>.<br><br>
        Please click the link below to setup Two-Factor Authentication:<br><br>
        <a href="<?php echo get_bloginfo( 'url' ); ?>/security?key=<?php echo $key; ?>">Setup Two-Factor Authentication</a><br><br>
        If you did not trigger this email, please ignore it.<br><br>
        Thank you,<br>
        <?php echo get_bloginfo( 'name' );

        // Set message.
        $message = ob_get_clean();

        // Headers.
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo( 'admin_email' ) . '>',
        ];

        // Check if WC Mailer is available.
        if( function_exists( 'WC' ) ) {

            // Get WooCommerce Mailer.
            $mailer = WC()->mailer();

            // Wrap message using WooCommerce HTML.
            $wrapped_message = $mailer->wrap_message( $subject, $message );

            // New email.
            $wc_email = new \WC_Email;

            // Style the wrapped message.
            $message = $wc_email->style_inline( $wrapped_message );

        }

        // Send email.
        wp_mail( $user->user_email, $subject, $message, $headers );

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
        $user = ( ! get_user_by( 'login', sanitize_text_field( $_POST['login'] ) ) ) ? get_user_by( 'email', sanitize_text_field( $_POST['login'] ) ) : get_user_by( 'login', sanitize_text_field( $_POST['login'] ) );

        // Check for user.
        if( ! $user ) {

            // Error, but continue and submit form to let WordPress handle.
            echo 'continue';
            wp_die();

        }

        // Check if user requires 2FA.
        if( ! $this->check_user( $user ) ) {

            // 2FA is not required, so continue with login.
            echo 'continue';
            wp_die();

        }

        // Check for 2FA.
        if( ! $this->check_confirmed( $user ) ) {

            // 2FA hasn't been setup yet, so continue.
            echo 'continue';
            wp_die();

        }

        // 2FA is required.
        echo 'confirm';
        wp_die();

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

        // Set request URI.
        $request_uri = trailingslashit( strtok( $_SERVER['REQUEST_URI'], '?' ) );

        // CSS.
        wp_enqueue_style( 'built-2fa', BUILT_URI . 'assets/security/2fa.css', [], BUILT_VERSION );

        // If security page.
        if( $request_uri == '/security/' ) {
            
            // Load.
            wp_enqueue_style( 'built-security', BUILT_URI . 'assets/security/security.css', [], BUILT_VERSION );

        }

        // JS.
        wp_enqueue_script( 'built-2fa', BUILT_URI . 'assets/security/2fa.js', [ 'jquery' ], BUILT_VERSION, true );

        // Localize.
        wp_localize_script( 'built-2fa', 'built2FA', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'built-2fa' )
        ] );

    }

    /**
     * Setup.
     * 
     * Setup 2FA.
     * 
     * @since   2.0.0
     */
    public function setup() {

        // If constant isn't set, dip.
        if( ! defined( 'BUILT_2FA' ) ) return;

        // Set request URI.
        $request_uri = trailingslashit( strtok( $_SERVER['REQUEST_URI'], '?' ) );

        // Check request URI.
        if( $request_uri !== '/security/' ) return;

        // Check for required query string.
        if( ! isset( $_GET['key'] ) ) return;

        // Check for valid key.
        if( ! $this->verify_key( $_GET['key'] ) ) return;

        // Get key.
        $get_key = explode( ':', base64_decode( $_GET['key'] ) );
        
        // Set variables.
        $user_id    = $get_key[0];
        $key        = $get_key[1];

        // Check for $code.
        if( isset( $_POST['google_authenticator_code'] ) ) {

            // Confirm.
            $status = $this->confirm( $_POST['key'], $_POST['google_authenticator_code'] );

            // Check status.
            if( ! $status ) {

                // Redirect, but with error.
                wp_redirect( home_url( '/security?key=' . $_POST['key'] . '&confirm=true&status=error' ) );
                exit;

            } else {

                // Redirect.
                wp_redirect( home_url( '/security?key=' . $_POST['key'] . '&status=confirmed' ) );
                exit;

            }

        }

        // Get current user.
        $user = get_user_by( 'ID', $user_id );

        // Get secret.
        $secret = $this->generate_secret( $user );
        
        // Create the QR code.
        $result = Builder::create()
        ->writer(new PngWriter())
        ->data('otpauth://totp/WordPress:' . $user->user_login . '?secret=' . $secret . '&issuer=WordPress ' . get_bloginfo('name') )
        ->build();

        // Get the data URI
        $dataUri = $result->getDataUri();

        // Include.
        include BUILT_PATH . 'views/security/security.php';
        exit;

    }

    /**
     * User administration.
     * 
     * Add user administration fields.
     * 
     * @since   2.0.0
     * 
     * @param   object  $user
     */
    public function user_administration( $user ) {

        // Check user. 
        if( current_user_can( 'administrator' ) ) {
            
            // Check if user requires 2FA. 
            if( ! $this->check_user( $user ) ) return;
            
            // Administration. ?>
            <h3>Two-Factor Authentication</h3>
            <table class="form-table">
                <tr>
                    <th>Status: <?php
                    
                    // Check status.
                    if( $this->check_confirmed( $user ) ) {

                        // Active.
                        echo ' <span style="color:green;">Active</span>';

                    } else {

                        // Not active.
                        echo ' <span style="color:red;">Inactive</span>';

                    } ?></th>
                    </th>
                    <td><?php

                        // Check status.
                        if( $this->check_confirmed( $user ) ) { 
                            
                            // Button. ?>
                            <button name="google_authenticator_reset" class="button button-secondary">Reset 2FA</button><?php

                        } else {

                            // Button. ?>
                            <button name="google_authenticator_setup" class="button button-primary">Send Setup Email</button><?php

                        } ?>

                    </td>
                </tr>
            </table><?php

        }

    }

    /**
     * User save.
     * 
     * Save user administration fields.
     * 
     * @since   2.0.0
     */
    public function user_save( $user_id ) {

        // Check user. 
        if( current_user_can( 'administrator' ) ) {

            // Check for reset.
            if( isset( $_POST['google_authenticator_reset'] ) ) {

                // Reset.
                $this->clear_auth( $user_id );

            } elseif( isset( $_POST['google_authenticator_setup'] ) ) {

                // Get user.
                $user = get_user_by( 'ID', $user_id );

                // Send email.
                $this->send_setup_email( $user );

            }

        }

    }

    /**
     * WooCommerce Account menu items.
     * 
     * Add 2FA to WooCommerce account menu items.
     * 
     * @since   2.0.0
     */
    public function woocommerce_account( $items ) {

        // Get current user.
        $user = get_user_by( 'ID', get_current_user_id() );

        // Check if user requires 2FA.
        if( ! $this->check_user( $user ) ) return $items;

        // Set new.
        $new_items = [];

        // Loop through items.
        foreach( $items as $key => $item ) {

            // Add item.
            $new_items[$key] = $item;

            // Check if edit account.
            if( $key == 'edit-account' ) {

                // Add 2FA.
                $new_items['security'] = ( $this->check_confirmed( $user ) ) ? 'Reset 2FA' : 'Setup 2FA';

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
        <p>Status: <?php

            // Check if setup.
            if( $this->check_confirmed( $user ) && ! isset( $_GET['setup'] ) ) {

                // Active.
                echo '<span style="color:white;display:inline-block;background:green;line-height:1;padding:5px 10px;border-radius:6px;">Active</span>';

            } else {

                // Inactive.
                echo '<span style="color:white;display:inline-block;background:red;line-height:1;padding:5px 10px;border-radius:6px;">Inactive</span>';

            } ?>
        </p><?php

        // Check for setup.
        if( $this->check_confirmed( $user ) && ! isset( $_GET['setup'] ) ) { 

            // Reset button. ?>
            <a href="<?php echo wc_get_account_endpoint_url( 'security' ); ?>?setup=reset" class="button">Reset</a><?php
            
        } elseif( isset( $_GET['setup'] ) && $_GET['setup'] == 'true' ) {

            // Send email.
            $this->send_setup_email( $user );

            // Output. ?>
            <p>A setup email has been sent to your email address.</p><?php

        } elseif( isset( $_GET['setup'] ) && $_GET['setup'] == 'reset' ) {

            // Reset.
            $this->clear_auth( $user->ID );

            // Output. ?>
            <p>Two-Factor Authentication has been reset.</p><?php

            // Button. ?>
            <a href="<?php echo wc_get_account_endpoint_url( 'security' ); ?>?setup=true" class="button">Setup</a><?php

        } else {

            // Button. ?>
            <a href="<?php echo wc_get_account_endpoint_url( 'security' ); ?>?setup=true" class="button">Setup</a><?php

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
     * Add security reset/setup button.
     * 
     * Add security reset/setup button.
     * 
     * @since   2.0.0
     */
    public function security_setup() {

        // Check if user is logged in.
        if( ! is_user_logged_in() ) return;

        // Check if admin.
        if( is_admin() ) return;

        // Check if logging out.
        if( isset( $_GET['action'] ) && $_GET['action'] == 'logout' ) return;

        // Get current user.
        $user = get_user_by( 'ID', get_current_user_id() );

        // Check if user requires 2FA.
        if( ! $this->check_user( $user ) ) return;

        // Container. ?>
        <div class="twofactor-setup-tab"><?php

            // Check if user has 2FA setup on their account.
            if( $this->check_confirmed( $user ) && ! isset( $_GET['setup'] ) ) {

                // Button. ?>
                <a href="<?php echo home_url( '/?setup=reset' ); ?>" class="button" style="background:green;color:white;">
                    <span class="button-init">🔒 2FA</span>   
                    <span class="button-hover">Reset 2FA</span>
                </a><?php

            } elseif( isset( $_GET['setup'] ) && $_GET['setup'] == 'reset' ) {

                // Reset.
                $this->clear_auth( $user->ID ); 
                
                // Button. ?>
                <a href="<?php echo home_url( '/?setup=true' ); ?>" class="button">🔓 Setup 2FA</a><?php

                // JavaScript redirect. ?>
                <script>
                    // Wait and redirect
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url( '/' ); ?>';
                    }, 1000);
                </script><?php

            } elseif( isset( $_GET['setup'] ) && $_GET['setup'] == 'true' ) {

                // Send email.
                $this->send_setup_email( $user );

                // JavaScript redirect. ?>
                <script>
                    // Wait and redirect
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url( '/' ); ?>';
                    }, 1000);
                </script><?php

            } else {

                // Button. ?>
                <a href="<?php echo home_url( '/?setup=true' ); ?>" class="button">🔓 Setup 2FA</a><?php

            } ?>

        </div>
        <style>
            .twofactor-setup-tab {
                position: fixed;
                bottom: 0;
                left: 15px;
                z-index: 99999;
            }

            .twofactor-setup-tab a {
                text-decoration: none;
                font-size: 12px;
                font-family: sans-serif;
                line-height: 1;
                padding: 12.5px 25px;
                background: #000;
                color: #fff;
                width: 150px;
                text-align: center;
                border: 1px solid rgb(0 0 0 / 10%);
                border-radius: 6px 6px 0 0;
                transition: all 0.3s ease;
                -webkit-transition: all 0.3s ease;
                -moz-transition: all 0.3s ease;
            }

            .twofactor-setup-tab a:hover {
                background: #fff !important;
                color: #000 !important;
            }

            .twofactor-setup-tab span.button-hover {
                display: none;
            }

            .twofactor-setup-tab a:hover .button-init {
                display: none;
            }

            .twofactor-setup-tab a:hover .button-hover {
                display: block;
            }
        </style><?php

    }

    /**
     * Confirm.
     * 
     * Confirm the 2FA setup.
     * 
     * @since   2.0.0
     */
    public function confirm( $key, $code ) {

        // Auth.
        $auth = new \BuiltMightyKit\Security\builtAuth();

        // Get key.
        $get_key = explode( ':', base64_decode( $key ) );

        // Set variables.
        $user_id    = $get_key[0];
        $key        = $get_key[1];

        // Confirm key. 
        if( $key !== get_user_meta( $user_id, 'google_authenticator_setup', true ) ) return false;

        // Authenticate.
        if( ! $auth->authenticate( $user_id, $code ) ) return false;

        // Update user meta.
        update_user_meta( $user_id, 'google_authenticator_confirmed', true );

        // Return.
        return true;

    }

    /**
     * Generate setup.
     * 
     * Generate setup key for 2FA.
     * 
     * @since   2.0.0
     * 
     * @param   object  $user
     * @return  string
     */
    public function generate_setup( $user, $encode = false ) {

        // Generate Google Authenticator setup key.
        $key = wp_generate_password( 32, false );

        // Save to user meta.
        update_user_meta( $user->ID, 'google_authenticator_setup', $key );

        // Return.
        return ( ! $encode ) ? $key : base64_encode( $user->ID . ':' . $key );

    }

    /**
     * Check setup.
     * 
     * Verify setup key for 2FA.
     * 
     * @since   2.0.0
     * 
     * @param   int     $user_id
     * @param   string  $key
     * @return  bool
     */
    public function verify_key( $key ) {

        // Get key.
        $get_key = explode( ':', base64_decode( $key ) );

        // Set variables.
        $user_id    = $get_key[0];
        $key        = $get_key[1];

        // Check key.
        if( $key !== get_user_meta( $user_id, 'google_authenticator_setup', true ) ) return false;

        // Return.
        return true;
        
    }

    /**
     * Generate secret.
     * 
     * Generate secret key for 2FA.
     * 
     * @since   2.0.0
     * 
     * @param   object  $user
     * @return  string
     */
    public function generate_secret( $user ) {

        // Get secret.
        $secret = get_user_meta( $user->ID, 'google_authenticator_secret', true );

        // Check for secret.
        if( ! $secret ) {

            // Get new Google Authenticator.
            $google = new GoogleAuthenticator();
            $secret = $google->generateSecret();

            // Save secret.
            update_user_meta( $user->ID, 'google_authenticator_secret', $secret );

        }

        // Return.
        return $secret;

    }

    /**
     * Check user.
     * 
     * Check if user requires 2FA.
     * 
     * @since   2.0.0
     * 
     * @param   object  $user
     * @return  bool
     */
    public function check_user( $user ) {

        // Check if user is admin.
        if( in_array( 'administrator', (array)$user->roles ) ) return true;

        // Check if 2fa-roles is set.
        if( empty( get_option( '2fa-roles' ) ) ) return false;

        // Get roles.
        $roles = unserialize( get_option( '2fa-roles' ) );

        // Check if user has role.
        if( array_intersect( (array)$user->roles, (array)$roles ) ) return true;

        // Return.
        return false;

    }

    /**
     * Check confirmed.
     * 
     * Check if user has 2FA confirmed.
     * 
     * @since   2.0.0
     * 
     * @param   object  $user
     * @return  bool
     */
    public function check_confirmed( $user ) {

        // Check if user has 2FA setup on their account.
        return ( empty( get_user_meta( $user->ID, 'google_authenticator_confirmed', true ) ) ) ? false : true;

    }

    /**
     * Clear 2FA.
     * 
     * Reset 2FA for user.
     * 
     * @since   2.0.0
     */
    public function clear_auth( $user_id ) {

        // Remove.
        delete_user_meta( $user_id, 'google_authenticator_setup' );
        delete_user_meta( $user_id, 'google_authenticator_secret' );
        delete_user_meta( $user_id, 'google_authenticator_confirmed' );

    }

    /**
     * Clear setup key.
     * 
     * Clear setup key for user.
     * 
     * @since   2.0.0
     */
    public function clear_setup( $user_id ) {

        // Remove.
        delete_user_meta( $user_id, 'google_authenticator_setup' );

    }

}