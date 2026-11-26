<?php
/**
 * Custom login address.
 *
 * Serves the WordPress login screen at a custom address and hides
 * wp-login.php from signed-out visitors. Every login, lost-password, reset,
 * register and logout link WordPress generates is rewritten to the custom
 * address, so password resets and the admin re-login popup keep working.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;

if( ! defined( 'ABSPATH' ) ) { exit; }

class login {

    /**
     * Custom slug, like "team-entry".
     *
     * @var string
     */
    private $slug = '';

    /**
     * Is this request for the custom login address?
     *
     * @var bool
     */
    private $is_custom = false;

    /**
     * Construct.
     */
    public function __construct() {

        // Pretty permalinks are required for a custom path.
        if( empty( get_option( 'permalink_structure' ) ) ) return;
        if( ! settings::is_on( 'kit_enable_login' ) ) return;

        $this->slug = trim( (string) settings::get( 'kit_login_url' ), '/' );
        if( $this->slug === '' ) return;

        $this->detect();

        add_action( 'wp_loaded', [ $this, 'route' ], 1 );
        add_filter( 'site_url', [ $this, 'filter_url' ], 10, 2 );
        add_filter( 'network_site_url', [ $this, 'filter_url' ], 10, 2 );
        add_filter( 'wp_redirect', [ $this, 'filter_redirect' ], 10, 1 );
        add_filter( 'login_url', [ $this, 'filter_redirect' ], 10, 1 );
        add_filter( 'lostpassword_url', [ $this, 'filter_redirect' ], 10, 1 );
        add_filter( 'register_url', [ $this, 'filter_redirect' ], 10, 1 );

    }

    /**
     * The custom login URL.
     */
    public function url( string $query = '' ): string {
        $url = home_url( '/' . $this->slug . '/' );
        return $query !== '' ? $url . '?' . $query : $url;
    }

    /**
     * Work out what this request is.
     */
    private function detect(): void {
        $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        $home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
        if( $home !== '' && strpos( $path, $home ) === 0 ) $path = trim( substr( $path, strlen( $home ) ), '/' );
        $this->is_custom = ( $path === $this->slug );
    }

    /**
     * Route the request.
     */
    public function route() {

        global $pagenow;

        // The custom address: show the login screen.
        if( $this->is_custom ) {

            if( is_user_logged_in() && empty( $_REQUEST['action'] ) && empty( $_REQUEST['interim-login'] ) ) {
                wp_safe_redirect( admin_url() );
                exit;
            }

            $pagenow = 'wp-login.php';
            $_SERVER['PHP_SELF'] = '/wp-login.php';

            // wp-login.php expects these globals.
            global $error, $interim_login, $action, $user_login, $user, $redirect_to;
            require_once ABSPATH . 'wp-login.php';
            exit;

        }

        // Signed-out visits to wp-admin would redirect to the login address and reveal it.
        $script = basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) );
        if( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! in_array( $script, [ 'admin-post.php', 'admin-ajax.php' ], true ) ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        // Direct requests to wp-login.php.
        if( $script !== 'wp-login.php' && $pagenow !== 'wp-login.php' ) return;

        // Actions that must keep working at wp-login.php.
        $action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
        $allowed = (array) apply_filters( 'kit_login_allowed_actions', [ 'postpass', 'logout', 'confirmaction', 'confirm_admin_email' ] );
        if( in_array( $action, $allowed, true ) ) return;
        if( is_user_logged_in() && ( $action !== '' || ! empty( $_REQUEST['interim-login'] ) ) ) return;

        wp_safe_redirect( home_url( '/' ) );
        exit;

    }

    /**
     * Replace wp-login.php in generated URLs.
     */
    public function filter_url( $url, $path = '' ) {
        return $this->replace( (string) $url );
    }

    /**
     * Replace wp-login.php in redirects and login links.
     */
    public function filter_redirect( $location ) {
        return $this->replace( (string) $location );
    }

    /**
     * Rewrite a URL that points at wp-login.php.
     */
    private function replace( string $url ): string {
        if( strpos( $url, 'wp-login.php' ) === false ) return $url;
        // Keep postpass on wp-login.php; WordPress posts the password form there.
        if( strpos( $url, 'action=postpass' ) !== false ) return $url;
        $parts = explode( '?', $url, 2 );
        return $this->url( $parts[1] ?? '' );
    }

}
