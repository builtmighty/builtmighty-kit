<?php
/**
 * Two-factor sign-in.
 *
 * The second step happens after WordPress accepts the password:
 *
 *  1. authenticate (priority 100): a correct password for someone who needs
 *     two-factor is noted, and auth cookies are held back.
 *  2. wp_login (first): the session WordPress just created is destroyed, a
 *     pending sign-in starts (a short-lived HttpOnly cookie), and the person
 *     goes to wp-login.php?action=kit_2fa.
 *  3. The code screen checks an app, emailed or backup code (or a passkey),
 *     then signs the person in and fires wp_login for everything else.
 *
 * Nobody can trigger a code email or a lockout without the password.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\authentication;
use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;

if( ! defined( 'ABSPATH' ) ) { exit; }

class login_security {

    /** @var authentication */
    private $auth;

    /** @var \WP_User|null User who passed the password step this request. */
    private $challenge = null;

    /** @var string Session token WordPress created for that user. */
    private $session_token = '';

    /** @var bool Completing a sign-in after the code step. */
    private $completing = false;

    /** @var string Method used to complete, for the sign-in log. */
    private $method_used = '';

    /**
     * Construct.
     */
    public function __construct() {

        $this->auth = new authentication();

        // Setup links and profile management work even if two-factor is later switched off.
        add_action( 'login_form_kit_2fa_setup', [ $this, 'setup_screen' ] );
        add_action( 'show_user_profile', [ $this, 'profile' ] );
        add_action( 'edit_user_profile', [ $this, 'profile' ] );
        add_action( 'personal_options_update', [ $this, 'profile_save' ] );
        add_action( 'edit_user_profile_update', [ $this, 'profile_save' ] );
        add_action( 'after_password_reset', [ $this, 'forget_devices_on_password' ] );
        add_action( 'profile_update', [ $this, 'forget_devices_on_profile' ], 10, 2 );

        if( ! settings::is_on( 'kit_enable_2fa' ) ) return;

        add_filter( 'authenticate', [ $this, 'after_password' ], 100, 3 );
        add_action( 'set_logged_in_cookie', [ $this, 'capture_token' ], 10, 6 );
        add_action( 'wp_login', [ $this, 'intercept' ], -1000, 2 );
        add_action( 'login_form_kit_2fa', [ $this, 'challenge_screen' ] );
        add_action( 'login_enqueue_scripts', [ $this, 'login_assets' ] );
        add_action( 'admin_init', [ $this, 'enforce_enrollment' ], 1 );
        add_action( 'template_redirect', [ $this, 'enforce_enrollment' ], 1 );
        add_filter( 'rest_authentication_errors', [ $this, 'enrollment_rest' ], 99 );
        add_filter( 'kit_login_method', [ $this, 'login_method' ] );

        if( class_exists( 'WooCommerce' ) ) {
            add_action( 'init', [ $this, 'account_endpoint' ] );
            add_filter( 'woocommerce_account_menu_items', [ $this, 'account_menu' ] );
            add_action( 'woocommerce_account_security_endpoint', [ $this, 'account_page' ] );
            add_action( 'template_redirect', [ $this, 'account_actions' ] );
        }

    }

    // ------------------------------------------------------------------
    // Sign-in flow
    // ------------------------------------------------------------------

    /**
     * After WordPress checks the password.
     */
    public function after_password( $user, $username, $password ) {

        if( ! $user instanceof \WP_User || $this->completing ) return $user;

        // API clients (application passwords, XML-RPC) can't answer a second step.
        if( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || did_action( 'application_password_did_authenticate' ) ) return $user;

        if( ! $this->auth->is_required( $user ) ) return $user;

        if( $this->auth->is_trusted( $user ) ) {
            $this->method_used = 'Trusted device';
            return $user;
        }

        // Not set up yet: sign in now, then enforce_enrollment() sends them to setup.
        if( $this->auth->needs_enrollment( $user ) ) {
            $this->method_used = 'Password (two-factor setup required)';
            return $user;
        }

        // Hold back cookies; the session is ended in intercept().
        $this->challenge = $user;
        add_filter( 'send_auth_cookies', '__return_false', PHP_INT_MAX );
        return $user;

    }

    /**
     * Remember the session token WordPress created, so it can be destroyed.
     */
    public function capture_token( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
        if( $this->challenge && (int) $user_id === (int) $this->challenge->ID ) $this->session_token = (string) $token;
    }

    /**
     * Stop the sign-in and send the person to the code screen.
     */
    public function intercept( $user_login, $user ) {

        if( ! $this->challenge || (int) $user->ID !== (int) $this->challenge->ID ) return;

        if( $this->session_token ) {
            \WP_Session_Tokens::get_instance( $user->ID )->destroy( $this->session_token );
        }
        wp_set_current_user( 0 );

        $methods  = $this->auth->methods( $user );
        $method   = (string) key( $methods );
        $redirect = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( (string) $_REQUEST['redirect_to'] ) : '';
        if( $redirect === '' && isset( $_POST['woocommerce-login-nonce'] ) ) {
            // WooCommerce sends its target (e.g. checkout) as "redirect".
            $redirect = isset( $_POST['redirect'] ) ? wp_unslash( (string) $_POST['redirect'] ) : '';
            if( $redirect === '' && function_exists( 'wc_get_page_permalink' ) ) $redirect = wc_get_page_permalink( 'myaccount' );
        }
        $interim = ! empty( $_REQUEST['interim-login'] );

        $token = $this->auth->start_pending( $user, ! empty( $_POST['rememberme'] ), $redirect, $method );
        if( $interim ) {
            $pending = get_transient( 'kit_2fa_p_' . hash( 'sha256', $token ) );
            if( is_array( $pending ) ) $this->auth->update_pending( $token, [ 'interim' => true ] + $pending );
        }
        $notice = '';
        if( $method === 'email' ) {
            $sent = $this->auth->send_code( $user );
            if( is_wp_error( $sent ) ) $notice = 'limit';
            elseif( ! $sent ) $notice = 'failed';
        }

        $args = [ 'action' => 'kit_2fa' ];
        if( $interim ) $args['interim-login'] = '1';
        if( $notice ) $args['kit_sent'] = $notice;
        wp_safe_redirect( add_query_arg( $args, wp_login_url() ) );
        exit;

    }

    /**
     * Send signed-in people who still need two-factor to setup.
     *
     * Runs on every wp-admin and front-end page view. Background requests
     * (AJAX, REST, cron, admin-post) are left alone so pages don't break.
     */
    public function enforce_enrollment() {

        if( ! is_user_logged_in() ) return;

        // Until setup is done, a password-only session can't use wp-admin AJAX either.
        if( wp_doing_ajax() ) {
            if( $this->auth->needs_enrollment( wp_get_current_user() ) ) wp_send_json_error( [ 'code' => 'kit_2fa_setup_required', 'message' => 'Set up two-factor sign-in first.' ], 403 );
            return;
        }

        if( wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) return;
        if( basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ) === 'admin-post.php' ) return;
        if( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) return;

        $user = wp_get_current_user();
        if( ! $this->auth->needs_enrollment( $user ) ) return;

        // Remember where they were going.
        $scheme = is_ssl() ? 'https://' : 'http://';
        $here   = $scheme . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' );
        update_user_meta( $user->ID, 'kit_2fa_return', esc_url_raw( $here ) );

        if( ! get_user_meta( $user->ID, 'kit_2fa_enroll_logged', true ) ) {
            update_user_meta( $user->ID, 'kit_2fa_enroll_logged', time() );
            activity::log( '2fa', 'setup_required', $user->user_login . ' signed in and was sent to set up two-factor', [ 'user_id' => $user->ID, 'level' => 'warning' ] );
        }

        nocache_headers();
        wp_safe_redirect( self::setup_url() );
        exit;

    }

    /**
     * Cookie-authenticated REST calls from someone who still has to set up two-factor are refused.
     */
    public function enrollment_rest( $result ) {
        if( ! empty( $result ) || ! is_user_logged_in() ) return $result;
        if( did_action( 'application_password_did_authenticate' ) ) return $result;
        if( $this->auth->needs_enrollment( wp_get_current_user() ) ) {
            return new \WP_Error( 'kit_2fa_setup_required', 'Set up two-factor sign-in first.', [ 'status' => 403 ] );
        }
        return $result;
    }

    /**
     * Method name for the sign-in log.
     */
    public function login_method( $method ) {
        return $this->method_used ?: $method;
    }

    /**
     * The code screen.
     */
    public function challenge_screen() {

        $pending = $this->auth->get_pending();
        if( ! $pending ) {
            wp_safe_redirect( add_query_arg( 'kit_2fa', 'expired', wp_login_url() ) );
            exit;
        }

        $user    = $pending['user'];
        $data    = $pending['data'];
        $token   = $pending['token'];
        $methods = $this->auth->methods( $user );
        $method  = isset( $methods[ $data['method'] ] ) ? $data['method'] : (string) key( $methods );
        $error   = '';
        $notice  = '';
        if( isset( $_GET['kit_sent'] ) && $method === 'email' ) {
            $notice = $_GET['kit_sent'] === 'limit' ? 'A code was sent a moment ago. Use that one, or ask for a new one in a few minutes.' : 'We couldn\'t send the email. Try "Send a new code", or use another way to sign in.';
        }

        // Switch method.
        if( isset( $_GET['method'] ) && isset( $methods[ sanitize_key( $_GET['method'] ) ] ) ) {
            $new = sanitize_key( $_GET['method'] );
            if( $new !== $method ) {
                $method = $new;
                $data['method'] = $method;
                $this->auth->update_pending( $token, $data );
                if( $method === 'email' ) {
                    $sent = $this->auth->send_code( $user );
                    if( is_wp_error( $sent ) ) $notice = $sent->get_error_message();
                    elseif( ! $sent ) $notice = 'We couldn\'t send the email. Try again, or use another way to sign in.';
                }
            }
        }

        if( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

            if( ! isset( $_POST['_kit_2fa'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kit_2fa'] ) ), 'kit_2fa_' . substr( $token, 0, 16 ) ) ) {
                $error = 'This page expired. Enter the newest code you have.';
            } elseif( isset( $_POST['kit_resend'] ) && $method === 'email' ) {
                $sent   = $this->auth->send_code( $user );
                $notice = is_wp_error( $sent ) ? $sent->get_error_message() : ( $sent ? 'A new code is on its way.' : 'We couldn\'t send the email. Try again, or use another way to sign in.' );
            } else {
                $code   = isset( $_POST['kit_code'] ) ? sanitize_text_field( wp_unslash( $_POST['kit_code'] ) ) : '';
                $result = $this->auth->verify( $user, $method, $code );
                if( $result === true ) {
                    $this->complete( $user, $data, $method, ! empty( $_POST['kit_trust'] ) );
                }
                $error = $result->get_error_message();
            }

        }

        $this->render_challenge( $user, $methods, $method, $error, $notice, $token );
        exit;

    }

    /**
     * Sign the person in.
     */
    private function complete( $user, array $data, string $method, bool $trust ): void {

        $this->auth->end_pending( $this->auth->get_pending()['token'] ?? '' );
        if( $trust ) $this->auth->trust_device( $user );

        $labels = [ 'app' => 'Authenticator app', 'email' => 'Email code', 'backup' => 'Backup code', 'passkey' => 'Passkey' ];
        $this->method_used = $labels[ $method ] ?? ucfirst( $method );
        $this->completing  = true;

        wp_set_auth_cookie( $user->ID, ! empty( $data['remember'] ) );
        wp_set_current_user( $user->ID );
        do_action( 'wp_login', $user->user_login, $user );

        if( ! empty( $data['interim'] ) ) {
            global $interim_login;
            $interim_login = 'success';
            login_header( '', '<p class="message">' . esc_html__( 'You have logged in successfully.' ) . '</p>' );
            echo '</div>';
            do_action( 'login_footer' );
            echo '</body></html>';
            exit;
        }

        $redirect = $data['redirect'] ?: admin_url();
        $redirect = apply_filters( 'login_redirect', $redirect, $data['redirect'], $user );
        if( ( empty( $redirect ) || $redirect === 'wp-admin/' || $redirect === admin_url() ) && ! $user->has_cap( 'edit_posts' ) ) {
            $redirect = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
        }
        wp_safe_redirect( $redirect );
        exit;

    }

    /**
     * Code screen markup.
     */
    private function render_challenge( $user, array $methods, string $method, string $error, string $notice, string $token ): void {

        $site   = get_bloginfo( 'name' );
        $masked = preg_replace_callback( '/^(.)(.*)(@.*)$/', function( $m ) { return $m[1] . str_repeat( '•', min( 6, strlen( $m[2] ) ) ) . $m[3]; }, $user->user_email );
        $copy   = [
            'app'     => [ 'Open your authenticator app', sprintf( 'Enter the 6-digit code shown for %s.', $site ), 6 ],
            'email'   => [ 'Check your email', sprintf( 'We sent an 8-digit code to %s. It expires in 5 minutes.', $masked ), 8 ],
            'backup'  => [ 'Use a backup code', 'Enter one of the codes you saved when you set up two-factor. Each works once.', 0 ],
            'passkey' => [ 'Use your passkey', 'Confirm with Face ID, Touch ID, Windows Hello or your security key.', -1 ],
        ];
        [ $title, $sub, $len ] = $copy[ $method ] ?? [ 'Enter your code', '', 0 ];
        $action = add_query_arg( array_filter( [ 'action' => 'kit_2fa', 'interim-login' => empty( $_REQUEST['interim-login'] ) ? null : '1' ] ), wp_login_url() );

        login_header( 'Two-factor sign-in' );

        echo '<form name="kit2fa" id="loginform" class="bmk-2fa" method="post" action="' . esc_url( $action ) . '" autocomplete="off">';
        echo '<div class="bmk-2fa-head"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $sub ) . '</p></div>';

        if( $notice ) echo '<p class="bmk-2fa-note" role="status">' . esc_html( $notice ) . '</p>';

        wp_nonce_field( 'kit_2fa_' . substr( $token, 0, 16 ), '_kit_2fa' );

        if( $len === -1 ) {
            /**
             * Render a custom method's controls (passkeys).
             *
             * @since 6.0.0
             */
            do_action( 'kit_2fa_render_' . $method, $user );
        } elseif( $len > 0 ) {
            echo '<label class="screen-reader-text" for="kit-code">' . esc_html( sprintf( '%d-digit code', $len ) ) . '</label>';
            echo '<div class="bmk-otp" data-len="' . (int) $len . '"><input type="text" id="kit-code" name="kit_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="' . (int) ( $len + 1 ) . '" required autofocus' . ( $error ? ' aria-invalid="true" aria-describedby="kit-code-err"' : '' ) . '></div>';
        } else {
            echo '<label class="screen-reader-text" for="kit-code">Backup code</label>';
            echo '<input type="text" class="input bmk-backup-input" id="kit-code" name="kit_code" placeholder="xxxx-xxxx" autocomplete="one-time-code" spellcheck="false" autocapitalize="off" required autofocus' . ( $error ? ' aria-invalid="true" aria-describedby="kit-code-err"' : '' ) . '>';
        }

        if( $error ) echo '<p class="bmk-2fa-err" id="kit-code-err" role="alert">' . esc_html( $error ) . '</p>';

        if( $len !== -1 ) {
            echo '<p class="bmk-2fa-trust"><label><input type="checkbox" name="kit_trust" value="1"> Trust this browser for 30 days</label></p>';
            echo '<p class="submit"><button type="submit" class="button button-primary button-large bmk-2fa-submit">Verify and sign in</button></p>';
        }

        if( $method === 'email' ) {
            echo '<p class="bmk-2fa-resend"><span>Didn\'t get it?</span><button type="submit" name="kit_resend" value="1" class="bmk-linkbtn" formnovalidate>Send a new code</button></p>';
        }

        $others = array_diff_key( $methods, [ $method => true ] );
        if( $others ) {
            $labels = [ 'app' => 'Use my authenticator app instead', 'email' => 'Email me a code instead', 'backup' => 'Use a backup code', 'passkey' => 'Use a passkey' ];
            echo '<div class="bmk-2fa-methods">';
            foreach( $others as $key => $label ) {
                echo '<a href="' . esc_url( add_query_arg( array_filter( [ 'action' => 'kit_2fa', 'method' => $key, 'interim-login' => empty( $_REQUEST['interim-login'] ) ? null : '1' ] ), wp_login_url() ) ) . '">' . esc_html( $labels[ $key ] ?? 'Use ' . strtolower( $label ) ) . '</a>';
            }
            echo '</div>';
        }

        echo '<p class="bmk-2fa-secured">Protected by Built Mighty Kit</p>';
        echo '</form>';
        echo '<p id="backtoblog" class="bmk-2fa-cancel"><a href="' . esc_url( wp_login_url() ) . '">Sign in as someone else</a></p>';

        login_footer();

    }

    /**
     * Styles and script for Kit's login screens.
     */
    public function login_assets() {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
        if( ! in_array( $action, [ 'kit_2fa', 'kit_2fa_setup' ], true ) && empty( $_GET['kit_2fa'] ) ) return;
        wp_enqueue_style( 'bmk-login', KIT_URI . 'public/css/kit-login.css', [ 'login' ], KIT_VERSION );
        wp_enqueue_script( 'bmk-login', KIT_URI . 'public/js/kit-login.js', [], KIT_VERSION, true );
    }

    // ------------------------------------------------------------------
    // Authenticator setup
    // ------------------------------------------------------------------

    /**
     * Three-step setup: scan, confirm, save backup codes.
     *
     * Opened from the profile (signed in) or from an emailed link (?key=).
     */
    public function setup_screen() {

        $user = null;
        $via  = '';
        if( isset( $_REQUEST['key'] ) ) {
            $user = $this->auth->user_for_key( sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) );
            $via  = 'key';
        } elseif( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $via  = 'session';
            if( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'kit_2fa_setup' ) ) $user = null;
        }

        if( ! $user ) {
            login_header( 'Two-factor setup', '', new \WP_Error( 'kit_setup', 'This setup link expired or was already used. Ask an administrator to send a new one, or start again from your profile.' ) );
            login_footer();
            exit;
        }

        $error = '';
        $codes = null;

        if( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['kit_setup_code'] ) ) {
            if( ! isset( $_POST['_kit_setup'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kit_setup'] ) ), 'kit_2fa_setup_confirm_' . $user->ID ) ) {
                $error = 'This page expired. Reload it and enter the newest code.';
            } else {
                $codes = $this->auth->confirm_app( $user, sanitize_text_field( wp_unslash( $_POST['kit_setup_code'] ) ) );
                if( $codes === false ) $error = 'That code didn\'t match. Use the code your app shows right now; it changes every 30 seconds.';
            }
        }

        $secret = $codes ? '' : $this->auth->pending_secret( $user );
        $qr     = $codes ? '' : $this->qr_svg( $this->auth->otpauth_uri( $user, $secret ) );
        $finish = $via === 'session' ? ( $user->has_cap( 'edit_posts' ) ? admin_url( 'profile.php#kit-2fa' ) : ( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'security' ) : home_url( '/' ) ) ) : wp_login_url();

        // Required setup (signed in without two-factor): explain why, and return them where they were going.
        $forced = $via === 'session' && ( $this->auth->needs_enrollment( $user ) || is_array( $codes ) && get_user_meta( $user->ID, 'kit_2fa_return', true ) );
        if( $forced ) {
            $return = (string) get_user_meta( $user->ID, 'kit_2fa_return', true );
            $finish = $return ?: ( $user->has_cap( 'edit_posts' ) ? admin_url() : home_url( '/' ) );
            if( is_array( $codes ) ) {
                delete_user_meta( $user->ID, 'kit_2fa_return' );
                delete_user_meta( $user->ID, 'kit_2fa_enroll_logged' );
            }
        }
        $logout = wp_logout_url( wp_login_url() );

        login_header( 'Two-factor setup' );
        include KIT_PATH . 'public/views/two-factor/setup.php';
        login_footer();
        exit;

    }

    /**
     * QR code as an inline SVG data URI.
     */
    private function qr_svg( string $data ): string {
        if( ! class_exists( '\BaconQrCode\Writer' ) ) require_once KIT_PATH . 'vendor/autoload.php';
        $renderer = new \BaconQrCode\Renderer\ImageRenderer( new \BaconQrCode\Renderer\RendererStyle\RendererStyle( 220, 1 ), new \BaconQrCode\Renderer\Image\SvgImageBackEnd() );
        $svg = ( new \BaconQrCode\Writer( $renderer ) )->writeString( $data );
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    /**
     * Link to start setup for the signed-in user.
     */
    public static function setup_url(): string {
        // Raw URL (wp_nonce_url() HTML-escapes the &, which breaks redirects).
        return add_query_arg( [ 'action' => 'kit_2fa_setup', '_wpnonce' => wp_create_nonce( 'kit_2fa_setup' ) ], wp_login_url() );
    }

    // ------------------------------------------------------------------
    // Profile
    // ------------------------------------------------------------------

    /**
     * Two-factor card on the profile screen.
     *
     * Each action is a submit button inside the profile form, so changes save
     * through WordPress's own profile update (and its nonce), plus ours.
     */
    public function profile( $user ) {

        $own   = (int) $user->ID === get_current_user_id();
        $admin = current_user_can( 'edit_users' );
        if( ! $own && ! $admin ) return;

        $ui       = '\BuiltMightyKit\Admin\ui';
        $on       = settings::is_on( 'kit_enable_2fa' );
        $required = $this->auth->is_required( $user );
        $app      = $this->auth->is_enabled( $user );
        $backups  = $this->auth->get_backup_code_count( $user->ID );
        $devices  = $this->auth->devices( $user->ID );
        $name     = $own ? 'you' : $user->display_name;

        if( ! $on ) {
            [ $pill, $summary ] = [ $ui::pill( 'Off for this site', 'off' ), 'Two-factor sign-in is turned off in Kit.' ];
        } elseif( $required ) {
            if( $app ) {
                $summary = 'Sign-in codes come from an authenticator app.';
            } elseif( $this->auth->is_enrolled( $user ) ) {
                $summary = 'Sign-in is confirmed with a passkey.';
            } elseif( settings::get( 'kit_2fa_unenrolled' ) === 'enroll' ) {
                $summary = ( $own ? 'You need' : 'This person needs' ) . ' to set up an authenticator app or passkey. Until then, every page leads to setup.';
            } else {
                $summary = 'Sign-in codes are emailed to ' . $user->user_email . ' until an authenticator app is set up.';
            }
            $pill = $ui::pill( 'Required', 'ok' );
        } else {
            [ $pill, $summary ] = [ $ui::pill( 'Optional', 'off' ), 'Not required for ' . ( $own ? 'your' : 'this' ) . ' role, but you can still add an app or passkey.' ];
        }

        echo $ui::sprite(); // phpcs:ignore
        wp_nonce_field( 'kit_2fa_profile_' . $user->ID, '_kit_2fa_profile' );
        // Enter in a profile field submits with the first submit button in the form. Make that a plain profile save.
        echo '<button type="submit" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;padding:0;border:0"></button>';
        echo '<section class="bmk-wp bmk-pcard" id="kit-2fa" aria-labelledby="kit-2fa-title">';
        echo '<header class="bmk-pcard-head"><div><h2 id="kit-2fa-title">Two-factor sign-in</h2><p>' . esc_html( $summary ) . '</p></div>' . $pill . '</header>'; // phpcs:ignore

        // New backup codes, shown once.
        if( ! empty( $_GET['kit_codes'] ) && $own ) {
            $codes = get_transient( 'kit_backup_codes_' . $user->ID );
            delete_transient( 'kit_backup_codes_' . $user->ID );
            if( is_array( $codes ) ) {
                echo '<div class="bmk-pcard-codes" role="status"><b>Save these backup codes now</b><p>Each works once, and they won\'t be shown again.</p><ol class="bmk-pcodes" id="kit-new-codes">';
                foreach( $codes as $c ) echo '<li>' . esc_html( $c ) . '</li>';
                echo '</ol><button type="button" class="bmk-btn bmk-btn-secondary bmk-btn-sm" data-bmk-copy-list="#kit-new-codes">Copy codes</button></div>';
            }
        }

        // Authenticator app.
        echo $this->profile_row( 'phone', 'Authenticator app',
            $app ? 'Set up ' . human_time_diff( (int) get_user_meta( $user->ID, 'authentication_confirmed', true ) ) . ' ago.' : 'Codes from 1Password, Google Authenticator, Microsoft Authenticator or Authy.',
            $app ? $ui::pill( 'On', 'ok' ) : ( $own
                ? '<a class="bmk-btn bmk-btn-primary bmk-btn-sm" href="' . esc_url( self::setup_url() ) . '">Set up</a>'
                : '<button type="submit" class="bmk-btn bmk-btn-secondary bmk-btn-sm" name="kit_2fa_action" value="send_setup">Email a setup link</button>' )
        ); // phpcs:ignore

        // Backup codes.
        if( $app || $backups ) {
            echo $this->profile_row( 'key', 'Backup codes',
                sprintf( _n( '%d unused code left. Each signs in once if a phone is lost.', '%d unused codes left. Each signs in once if a phone is lost.', $backups, 'builtmighty-kit' ), $backups ),
                $own ? '<button type="submit" class="bmk-btn bmk-btn-secondary bmk-btn-sm" name="kit_2fa_action" value="new_codes" data-bmk-confirm="Make 10 new backup codes? The old ones stop working.">Make new codes</button>' : ( $backups < 3 ? $ui::pill( 'Running low', 'warn' ) : '' )
            ); // phpcs:ignore
        }

        /**
         * Extra rows (passkeys).
         *
         * @since 6.0.0
         */
        do_action( 'kit_2fa_profile_rows', $user, $own );

        // Trusted browsers.
        $list = '';
        foreach( $devices as $hash => $d ) {
            $list .= '<li><span>' . esc_html( $d['label'] ) . '<small>' . esc_html( $d['ip'] . ', trusted until ' . wp_date( 'M j', (int) $d['expires'] ) ) . '</small></span><button type="submit" class="bmk-btn bmk-btn-ghost bmk-btn-sm" name="kit_2fa_action" value="' . esc_attr( 'revoke:' . $hash ) . '">Forget</button></li>';
        }
        echo $this->profile_row( 'shield', 'Trusted browsers',
            $devices ? sprintf( _n( '%d browser skips codes for 30 days.', '%d browsers skip codes for 30 days.', count( $devices ), 'builtmighty-kit' ), count( $devices ) ) : 'None. Tick "Trust this browser" when signing in to skip codes there for 30 days.',
            $devices ? '<button type="submit" class="bmk-btn bmk-btn-ghost bmk-btn-sm" name="kit_2fa_action" value="revoke:all">Forget all</button>' : '',
            $list ? '<ul class="bmk-plist2">' . $list . '</ul>' : ''
        ); // phpcs:ignore

        // Reset.
        if( $app || $backups || $devices ) {
            echo '<footer class="bmk-pcard-foot"><span>' . esc_html( $own ? 'Lost your phone? Reset to go back to emailed codes.' : 'If ' . $user->display_name . ' lost their phone, reset to go back to emailed codes.' ) . '</span><button type="submit" class="bmk-btn bmk-btn-danger-soft bmk-btn-sm" name="kit_2fa_action" value="reset" data-bmk-confirm="' . esc_attr( 'Reset two-factor for ' . $name . '? The authenticator app, backup codes and trusted browsers are removed.' ) . '">Reset two-factor</button></footer>';
        }

        echo '</section>';

    }

    /**
     * One row of the profile card.
     */
    public function profile_row( string $icon, string $title, string $desc, string $action = '', string $extra = '' ): string {
        $ui = '\BuiltMightyKit\Admin\ui';
        return '<div class="bmk-prow"><span class="bmk-pic">' . $ui::icon( $icon ) . '</span><div class="bmk-prow-text"><b>' . esc_html( $title ) . '</b><small>' . esc_html( $desc ) . '</small></div><div class="bmk-prow-act">' . $action . '</div>' . ( $extra ? '<div class="bmk-prow-extra">' . $extra . '</div>' : '' ) . '</div>';
    }

    /**
     * Save profile actions (WordPress has already checked the profile nonce).
     */
    public function profile_save( $user_id ) {

        if( empty( $_POST['kit_2fa_action'] ) ) return;
        if( ! isset( $_POST['_kit_2fa_profile'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kit_2fa_profile'] ) ), 'kit_2fa_profile_' . $user_id ) ) return;
        $own = (int) $user_id === get_current_user_id();
        if( ! $own && ! current_user_can( 'edit_user', $user_id ) ) return;
        $user   = get_userdata( $user_id );
        $action = sanitize_text_field( wp_unslash( $_POST['kit_2fa_action'] ) );

        if( $action === 'reset' ) {
            $this->auth->disable( $user );
        } elseif( $action === 'send_setup' && ! $own ) {
            $this->auth->send_setup( $user );
        } elseif( $action === 'new_codes' && $own ) {
            set_transient( 'kit_backup_codes_' . $user_id, $this->auth->generate_backup_codes( $user_id ), 10 * MINUTE_IN_SECONDS );
            add_filter( 'wp_redirect', function( $location ) { return add_query_arg( 'kit_codes', '1', $location ) . '#kit-2fa'; } );
            return;
        } elseif( $action === 'revoke:all' ) {
            $this->auth->revoke_devices( $user_id );
        } elseif( strpos( $action, 'revoke:' ) === 0 ) {
            $this->auth->revoke_devices( $user_id, preg_replace( '/[^a-f0-9]/', '', substr( $action, 7 ) ) );
        }

        add_filter( 'wp_redirect', function( $location ) { return $location . '#kit-2fa'; } );

    }

    /**
     * A new password ends trust in every remembered browser.
     */
    public function forget_devices_on_password( $user ) {
        $this->auth->revoke_devices( $user->ID );
    }

    /**
     * Same, when the password changes from the profile.
     */
    public function forget_devices_on_profile( $user_id, $old ) {
        $new = get_userdata( $user_id );
        if( $new && $old && $new->user_pass !== $old->user_pass ) $this->auth->revoke_devices( $user_id );
    }

    // ------------------------------------------------------------------
    // WooCommerce My Account
    // ------------------------------------------------------------------

    /**
     * Endpoint.
     */
    public function account_endpoint() {
        add_rewrite_endpoint( 'security', EP_ROOT | EP_PAGES );
    }

    /**
     * Menu item after Account details, for people who need two-factor.
     */
    public function account_menu( $items ) {
        if( ! $this->auth->is_required( wp_get_current_user() ) ) return $items;
        $out = [];
        foreach( $items as $key => $label ) {
            $out[ $key ] = $label;
            if( $key === 'edit-account' ) $out['security'] = 'Two-factor sign-in';
        }
        if( ! isset( $out['security'] ) ) $out['security'] = 'Two-factor sign-in';
        return $out;
    }

    /**
     * My Account page.
     */
    public function account_page() {

        $user = wp_get_current_user();
        if( ! $this->auth->is_required( $user ) ) {
            echo '<p>Two-factor sign-in isn\'t required for your account.</p>';
            return;
        }

        $app     = $this->auth->is_enabled( $user );
        $backups = $this->auth->get_backup_code_count( $user->ID );
        $devices = count( $this->auth->devices( $user->ID ) );

        echo '<h2>Two-factor sign-in</h2>';
        echo '<p>' . esc_html( $app ? 'You sign in with a code from your authenticator app.' : 'You get a code by email each time you sign in. Set up an authenticator app to skip the wait.' ) . '</p>';
        echo '<ul><li>' . esc_html( sprintf( _n( '%d unused backup code', '%d unused backup codes', $backups, 'builtmighty-kit' ), $backups ) ) . '</li><li>' . esc_html( sprintf( _n( '%d trusted browser', '%d trusted browsers', $devices, 'builtmighty-kit' ), $devices ) ) . '</li></ul>';

        if( ! $app ) {
            echo '<p><a class="button" href="' . esc_url( self::setup_url() ) . '">Set up an authenticator app</a></p>';
        } else {
            echo '<form method="post"><input type="hidden" name="kit_account_action" value="reset">';
            wp_nonce_field( 'kit_account_2fa', '_kit_account' );
            echo '<p><button type="submit" class="button" onclick="return confirm(\'Remove your authenticator app? You will get codes by email until you set it up again.\')">Remove authenticator app</button></p></form>';
        }

    }

    /**
     * My Account actions (POST with a nonce; GET no longer changes anything).
     */
    public function account_actions() {
        if( empty( $_POST['kit_account_action'] ) || ! is_user_logged_in() ) return;
        if( ! isset( $_POST['_kit_account'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kit_account'] ) ), 'kit_account_2fa' ) ) return;
        $user = wp_get_current_user();
        if( $_POST['kit_account_action'] === 'reset' ) {
            $this->auth->disable( $user );
            if( function_exists( 'wc_add_notice' ) ) wc_add_notice( 'Your authenticator app was removed. You\'ll get codes by email until you set one up again.' );
        }
        wp_safe_redirect( wc_get_account_endpoint_url( 'security' ) );
        exit;
    }

}
