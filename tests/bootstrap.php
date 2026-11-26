<?php
/**
 * Unit test bootstrap.
 *
 * Tests cover Kit's pure logic (TOTP, matching, sanitizing, legacy value
 * mapping) without a WordPress install. The few WordPress functions that
 * logic touches are stubbed below with their documented behavior.
 *
 * Run: tests/vendor/bin/phpunit -c tests/phpunit.xml
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'KIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['kit_test_options'] = [];
$GLOBALS['kit_test_filters'] = [];

function apply_filters( $tag, $value, ...$args ) {
    return isset( $GLOBALS['kit_test_filters'][ $tag ] ) ? call_user_func( $GLOBALS['kit_test_filters'][ $tag ], $value, ...$args ) : $value;
}
function add_filter( $tag, $cb ) { $GLOBALS['kit_test_filters'][ $tag ] = $cb; return true; }
function add_action( $tag, $cb ) { return true; }
function do_action( $tag, ...$args ) {}
function get_option( $key, $default = false ) { return $GLOBALS['kit_test_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['kit_test_options'][ $key ] = $value; return true; }
function home_url( $path = '' ) { return 'https://shop.example.com' . $path; }
function site_url( $path = '' ) { return home_url( $path ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function esc_url_raw( $s ) { return (string) $s; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function trailingslashit( $s ) { return untrailingslashit( $s ) . '/'; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class WP_Error {
    private $message;
    public function __construct( $code = '', $message = '' ) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}

require KIT_PATH . 'utilities/class-environment.php';
require KIT_PATH . 'utilities/class-settings.php';
require KIT_PATH . 'utilities/class-request.php';
require KIT_PATH . 'utilities/class-auth.php';
require KIT_PATH . 'public/class-block-external.php';
require KIT_PATH . 'public/class-rest-api-security.php';
