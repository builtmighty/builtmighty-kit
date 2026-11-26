<?php
/**
 * Passkeys (WebAuthn) as a second factor.
 *
 * People add passkeys from their profile. At sign-in, after the password,
 * "Use your passkey" is offered first: Face ID, Touch ID, Windows Hello or a
 * security key confirms it's them. Uses lbuchs/WebAuthn (MIT), bundled in
 * vendor/lbuchs/webauthn.
 *
 * Credentials live in user meta kit_passkeys:
 *   [ base64url id => [ key (PEM), count, label, created, used ] ]
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;
use BuiltMightyKit\Utility\authentication;
use BuiltMightyKit\Utility\request;

if( ! defined( 'ABSPATH' ) ) { exit; }

class passkeys {

    const META = 'kit_passkeys';

    /**
     * Construct.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'routes' ] );
        add_action( 'kit_2fa_profile_rows', [ $this, 'profile_row' ], 10, 2 );
        add_action( 'personal_options_update', [ $this, 'profile_save' ] );
        add_action( 'edit_user_profile_update', [ $this, 'profile_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'profile_assets' ] );

        if( ! settings::is_on( 'kit_enable_2fa' ) ) return;
        add_filter( 'kit_2fa_methods', [ $this, 'methods' ], 5, 2 );
        add_filter( 'kit_2fa_verify_passkey', [ $this, 'verify' ], 10, 3 );
        add_action( 'kit_2fa_render_passkey', [ $this, 'render_login' ] );
        add_action( 'login_enqueue_scripts', [ $this, 'login_assets' ] );
    }

    /**
     * Can passkeys work here? (HTTPS, or localhost for development.)
     */
    public static function available(): bool {
        $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        return ( is_ssl() || strpos( home_url(), 'https://' ) === 0 || $host === 'localhost' ) && function_exists( 'openssl_verify' );
    }

    /**
     * WebAuthn server.
     */
    private function server() {
        if( ! class_exists( '\lbuchs\WebAuthn\WebAuthn' ) ) require_once KIT_PATH . 'vendor/lbuchs/webauthn/src/WebAuthn.php';
        $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        $name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ?: $host;
        return new \lbuchs\WebAuthn\WebAuthn( $name, $host, [ 'none', 'packed', 'apple', 'android-key', 'android-safetynet', 'fido-u2f', 'tpm' ], true );
    }

    /**
     * Stored credentials.
     */
    public static function all( int $user_id ): array {
        $keys = get_user_meta( $user_id, self::META, true );
        return is_array( $keys ) ? $keys : [];
    }

    private static function b64url( string $bin ): string { return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); }
    private static function unb64url( string $s ) { return base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true ); }

    // ------------------------------------------------------------------
    // REST
    // ------------------------------------------------------------------

    public function routes() {
        $ns = 'builtmighty-kit/v1';
        register_rest_route( $ns, '/passkeys/register/begin', [ 'methods' => 'POST', 'callback' => [ $this, 'register_begin' ], 'permission_callback' => 'is_user_logged_in' ] );
        register_rest_route( $ns, '/passkeys/register/finish', [ 'methods' => 'POST', 'callback' => [ $this, 'register_finish' ], 'permission_callback' => 'is_user_logged_in' ] );
        register_rest_route( $ns, '/passkeys/login/begin', [ 'methods' => 'POST', 'callback' => [ $this, 'login_begin' ], 'permission_callback' => '__return_true' ] );
    }

    /**
     * Options for navigator.credentials.create().
     */
    public function register_begin() {
        if( ! self::available() ) return new \WP_Error( 'kit_passkeys_https', 'Passkeys need HTTPS.', [ 'status' => 400 ] );
        $user    = wp_get_current_user();
        $server  = $this->server();
        $exclude = array_values( array_filter( array_map( [ __CLASS__, 'unb64url' ], array_keys( self::all( $user->ID ) ) ) ) );
        $args    = $server->getCreateArgs( (string) $user->ID, $user->user_login, $user->display_name, 120, false, 'preferred', null, $exclude );
        set_transient( 'kit_pk_reg_' . $user->ID, base64_encode( $server->getChallenge()->getBinaryString() ), 5 * MINUTE_IN_SECONDS );
        return rest_ensure_response( $args );
    }

    /**
     * Save a new passkey.
     */
    public function register_finish( \WP_REST_Request $request ) {

        $user      = wp_get_current_user();
        $challenge = base64_decode( (string) get_transient( 'kit_pk_reg_' . $user->ID ), true );
        delete_transient( 'kit_pk_reg_' . $user->ID );
        if( ! $challenge ) return new \WP_Error( 'kit_passkeys_expired', 'That took too long. Try adding the passkey again.', [ 'status' => 400 ] );

        $client = self::unb64url( (string) $request->get_param( 'clientDataJSON' ) );
        $att    = self::unb64url( (string) $request->get_param( 'attestationObject' ) );
        if( ! $client || ! $att ) return new \WP_Error( 'kit_passkeys_bad', 'The browser sent an incomplete passkey.', [ 'status' => 400 ] );

        try {
            $data = $this->server()->processCreate( $client, $att, $challenge, false, true, false );
        } catch( \Throwable $e ) {
            return new \WP_Error( 'kit_passkeys_failed', 'The passkey couldn\'t be verified. Try again.', [ 'status' => 400 ] );
        }

        $label = sanitize_text_field( (string) $request->get_param( 'label' ) ) ?: 'Passkey';
        $keys  = self::all( $user->ID );
        $keys[ self::b64url( $data->credentialId ) ] = [
            'key'     => $data->credentialPublicKey,
            'count'   => (int) ( $data->signatureCounter ?? 0 ),
            'label'   => substr( $label, 0, 60 ),
            'created' => time(),
            'used'    => 0,
        ];
        update_user_meta( $user->ID, self::META, $keys );
        activity::log( '2fa', 'passkey_added', $user->user_login . ' added a passkey (' . $label . ')', [ 'user_id' => $user->ID, 'level' => 'success' ] );

        return [ 'message' => 'Passkey added.', 'count' => count( $keys ) ];

    }

    /**
     * Options for navigator.credentials.get() during the code step.
     */
    public function login_begin() {
        $pending = ( new authentication() )->get_pending();
        if( ! $pending ) return new \WP_Error( 'kit_passkeys_pending', 'Your sign-in timed out. Enter your password again.', [ 'status' => 403 ] );
        $limit = request::hit( 'pk_begin', request::client_ip(), 20, 600 );
        if( ! $limit['allowed'] ) return new \WP_Error( 'kit_passkeys_limit', 'Too many attempts. Wait a few minutes.', [ 'status' => 429 ] );
        $user   = $pending['user'];
        $ids    = array_values( array_filter( array_map( [ __CLASS__, 'unb64url' ], array_keys( self::all( $user->ID ) ) ) ) );
        $server = $this->server();
        $args   = $server->getGetArgs( $ids, 120, true, true, true, true, true, 'preferred' );
        set_transient( 'kit_pk_auth_' . $user->ID, base64_encode( $server->getChallenge()->getBinaryString() ), 5 * MINUTE_IN_SECONDS );
        return rest_ensure_response( $args );
    }

    // ------------------------------------------------------------------
    // Sign-in
    // ------------------------------------------------------------------

    /**
     * Offer passkeys first when the user has one.
     */
    public function methods( $methods, $user ) {
        if( ! self::available() || ! self::all( $user->ID ) ) return $methods;
        return [ 'passkey' => 'Passkey' ] + $methods;
    }

    /**
     * Passkey button on the code screen.
     */
    public function render_login( $user ) {
        echo '<input type="hidden" name="kit_code" id="kit-passkey-response" value="">';
        echo '<p class="submit"><button type="button" class="button button-primary button-large bmk-2fa-submit" data-bmk-passkey-login data-rest="' . esc_url( rest_url( 'builtmighty-kit/v1/passkeys/login/begin' ) ) . '">Use passkey</button></p>';
        echo '<p class="bmk-2fa-err" id="kit-passkey-err" role="alert" hidden></p>';
        echo '<p class="bmk-2fa-trust"><label><input type="checkbox" name="kit_trust" value="1"> Trust this browser for 30 days</label></p>';
    }

    /**
     * Verify the assertion posted by the code screen.
     */
    public function verify( $ok, $user, $code ) {

        $assertion = json_decode( wp_unslash( (string) $code ), true );
        $challenge = base64_decode( (string) get_transient( 'kit_pk_auth_' . $user->ID ), true );
        delete_transient( 'kit_pk_auth_' . $user->ID );
        if( ! is_array( $assertion ) || ! $challenge ) return false;

        $keys = self::all( $user->ID );
        $id   = (string) ( $assertion['id'] ?? '' );
        if( ! isset( $keys[ $id ] ) ) return false;

        $client = self::unb64url( (string) ( $assertion['clientDataJSON'] ?? '' ) );
        $auth   = self::unb64url( (string) ( $assertion['authenticatorData'] ?? '' ) );
        $sig    = self::unb64url( (string) ( $assertion['signature'] ?? '' ) );
        if( ! $client || ! $auth || ! $sig ) return false;

        try {
            $server = $this->server();
            $server->processGet( $client, $auth, $sig, $keys[ $id ]['key'], $challenge, (int) $keys[ $id ]['count'] ?: null, false, true );
            $keys[ $id ]['count'] = (int) ( $server->getSignatureCounter() ?? $keys[ $id ]['count'] );
        } catch( \Throwable $e ) {
            return false;
        }

        $keys[ $id ]['used'] = time();
        update_user_meta( $user->ID, self::META, $keys );
        return true;

    }

    // ------------------------------------------------------------------
    // Profile
    // ------------------------------------------------------------------

    /**
     * Passkeys row in the two-factor profile card.
     */
    public function profile_row( $user, $own ) {

        $keys = self::all( $user->ID );
        $list = '';
        foreach( $keys as $id => $k ) {
            $meta  = 'Added ' . wp_date( 'M j, Y', (int) $k['created'] ) . ( $k['used'] ? ', last used ' . human_time_diff( (int) $k['used'] ) . ' ago' : '' );
            $list .= '<li><span>' . esc_html( $k['label'] ) . '<small>' . esc_html( $meta ) . '</small></span><button type="submit" class="bmk-btn bmk-btn-ghost bmk-btn-sm" name="kit_2fa_action" value="' . esc_attr( 'passkey_remove:' . $id ) . '" data-bmk-confirm="' . esc_attr( 'Remove the passkey "' . $k['label'] . '"?' ) . '">Remove</button></li>';
        }

        $add = '';
        if( $own ) {
            $add = self::available()
                ? '<div class="bmk-padd"><label class="screen-reader-text" for="kit-passkey-label">Passkey name</label><input type="text" class="bmk-pinput" id="kit-passkey-label" placeholder="Name it, like Work laptop" maxlength="60"><button type="button" class="bmk-btn bmk-btn-secondary bmk-btn-sm" data-bmk-passkey-add data-begin="' . esc_url( rest_url( 'builtmighty-kit/v1/passkeys/register/begin' ) ) . '" data-finish="' . esc_url( rest_url( 'builtmighty-kit/v1/passkeys/register/finish' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">Add a passkey</button></div><p class="bmk-pstatus" data-bmk-passkey-status role="status"></p>'
                : '<p class="bmk-pstatus">Passkeys need the site to use HTTPS.</p>';
        }

        $desc = $keys
            ? sprintf( _n( '%d passkey. Sign in with Face ID, Touch ID, Windows Hello or a security key.', '%d passkeys. Sign in with Face ID, Touch ID, Windows Hello or a security key.', count( $keys ), 'builtmighty-kit' ), count( $keys ) )
            : ( $own ? 'Sign in with Face ID, Touch ID, Windows Hello or a security key instead of typing a code.' : 'None added.' );

        $ls = new login_security();
        echo $ls->profile_row( 'fingerprint', 'Passkeys', $desc, $keys ? \BuiltMightyKit\Admin\ui::pill( 'On', 'ok' ) : '', ( $list ? '<ul class="bmk-plist2">' . $list . '</ul>' : '' ) . $add ); // phpcs:ignore

    }

    /**
     * Remove a passkey.
     */
    public function profile_save( $user_id ) {
        $action = isset( $_POST['kit_2fa_action'] ) ? sanitize_text_field( wp_unslash( $_POST['kit_2fa_action'] ) ) : '';
        if( strpos( $action, 'passkey_remove:' ) !== 0 ) return;
        if( ! isset( $_POST['_kit_2fa_profile'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kit_2fa_profile'] ) ), 'kit_2fa_profile_' . $user_id ) ) return;
        if( (int) $user_id !== get_current_user_id() && ! current_user_can( 'edit_user', $user_id ) ) return;
        $id   = preg_replace( '/[^A-Za-z0-9_-]/', '', substr( $action, 15 ) );
        $keys = self::all( $user_id );
        if( isset( $keys[ $id ] ) ) {
            activity::log( '2fa', 'passkey_removed', 'Removed passkey "' . $keys[ $id ]['label'] . '"', [ 'context' => [ 'user' => $user_id ] ] );
            unset( $keys[ $id ] );
            update_user_meta( $user_id, self::META, $keys );
        }
        add_filter( 'wp_redirect', function( $location ) { return strpos( $location, '#' ) === false ? $location . '#kit-2fa' : $location; } );
    }

    // ------------------------------------------------------------------
    // Assets
    // ------------------------------------------------------------------

    public function profile_assets( $hook ) {
        if( ! in_array( $hook, [ 'profile.php', 'user-edit.php' ], true ) ) return;
        wp_enqueue_style( 'bmk-fonts', 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700&family=Hanken+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap', [], null );
        wp_enqueue_script( 'bmk-passkeys', KIT_URI . 'public/js/kit-passkeys.js', [], KIT_VERSION, true );
    }

    public function login_assets() {
        if( ( $_REQUEST['action'] ?? '' ) !== 'kit_2fa' ) return;
        wp_enqueue_script( 'bmk-passkeys', KIT_URI . 'public/js/kit-passkeys.js', [], KIT_VERSION, true );
    }

}
