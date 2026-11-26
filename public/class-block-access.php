<?php
/**
 * Visitor lock.
 *
 * Sends signed-out visitors away from a copy of the store. Someone without an
 * account can be let in with the preview link from Kit, Safeguards, which
 * sets a signed cookie for 12 hours. The REST API is locked too.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;

if( ! defined( 'ABSPATH' ) ) { exit; }

class block_access {

    const COOKIE = 'kit_preview';
    const OPTION = 'kit_preview_key';

    /**
     * Construct.
     */
    public function __construct() {

        if( ! settings::is_on( 'kit_block_access' ) ) return;

        add_action( 'template_redirect', [ $this, 'block' ], 0 );
        add_filter( 'rest_authentication_errors', [ $this, 'block_rest' ], 99 );

    }

    /**
     * Per-site secret for preview links.
     */
    public static function key(): string {
        $key = (string) get_option( self::OPTION, '' );
        if( strlen( $key ) < 32 ) {
            $key = wp_generate_password( 40, false, false );
            update_option( self::OPTION, $key, false );
        }
        return $key;
    }

    /**
     * New key: old preview links stop working.
     */
    public static function rotate(): void {
        delete_option( self::OPTION );
        self::key();
    }

    /**
     * The preview link to share.
     */
    public static function preview_url(): string {
        return add_query_arg( 'kit_preview', self::key(), home_url( '/' ) );
    }

    /**
     * Cookie value for a valid preview.
     */
    private static function token(): string {
        return hash_hmac( 'sha256', 'kit-preview', self::key() . wp_salt( 'auth' ) );
    }

    /**
     * Has this visitor been let in?
     */
    private function allowed(): bool {

        if( is_user_logged_in() ) return true;

        $cookie = isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : '';
        if( $cookie !== '' && hash_equals( self::token(), $cookie ) ) return true;

        $preview = isset( $_GET['kit_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['kit_preview'] ) ) : '';
        if( $preview !== '' && hash_equals( self::key(), $preview ) ) {
            setcookie( self::COOKIE, self::token(), [
                'expires'  => time() + 12 * HOUR_IN_SECONDS,
                'path'     => COOKIEPATH ?: '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );
            return true;
        }

        return false;

    }

    /**
     * Front end.
     */
    public function block() {

        if( $this->allowed() ) return;

        // Let the custom login page through.
        $login = trim( (string) settings::get( 'kit_login_url' ), '/' );
        if( settings::is_on( 'kit_enable_login' ) && $login !== '' ) {
            $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
            $home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
            if( $home !== '' && strpos( $path, $home ) === 0 ) $path = trim( substr( $path, strlen( $home ) ), '/' );
            if( $path === $login ) return;
        }

        nocache_headers();
        wp_redirect( (string) apply_filters( 'kit_block_access_redirect', 'https://builtmighty.com' ) );
        exit;

    }

    /**
     * REST API.
     */
    public function block_rest( $result ) {
        if( $result !== null && $result !== true ) return $result;
        if( $this->allowed() ) return $result;
        return new \WP_Error( 'kit_locked', 'This copy of the store is private.', [ 'status' => 401 ] );
    }

}
