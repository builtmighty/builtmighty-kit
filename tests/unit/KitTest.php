<?php
/**
 * Unit tests for Kit's pure logic.
 */

use PHPUnit\Framework\TestCase;
use BuiltMightyKit\Utility\authentication;
use BuiltMightyKit\Utility\environment;
use BuiltMightyKit\Utility\request;
use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Public\block_external;
use BuiltMightyKit\Public\rest_api_security;

class KitTest extends TestCase {

    // ------------------------------------------------------------------
    // Two-factor
    // ------------------------------------------------------------------

    /** RFC 6238 appendix B test vectors (SHA-1, 6 digits). */
    public function test_totp_matches_rfc_6238_vectors() {
        $secret = authentication::base32_encode( '12345678901234567890' );
        $this->assertSame( 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret );
        $vectors = [ 59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037', 20000000000 => '353130' ];
        foreach( $vectors as $time => $code ) {
            $this->assertSame( $code, authentication::totp( $secret, (int) floor( $time / 30 ) ), "t=$time" );
        }
    }

    public function test_totp_accepts_one_step_of_drift_only() {
        $secret = authentication::base32_encode( random_bytes( 20 ) );
        $now    = 1700000000;
        $step   = (int) floor( $now / 30 );
        $this->assertSame( $step - 1, authentication::totp_match( $secret, authentication::totp( $secret, $step - 1 ), $now ) );
        $this->assertSame( $step + 1, authentication::totp_match( $secret, authentication::totp( $secret, $step + 1 ), $now ) );
        $this->assertFalse( authentication::totp_match( $secret, authentication::totp( $secret, $step + 3 ), $now ) );
        $this->assertFalse( authentication::totp_match( $secret, '12345', $now ) );
        $this->assertFalse( authentication::totp_match( $secret, 'abcdef', $now ) );
    }

    public function test_base32_round_trip() {
        $bytes = random_bytes( 20 );
        $this->assertSame( $bytes, authentication::base32_decode( authentication::base32_encode( $bytes ) ) );
        $this->assertSame( 'foobar', authentication::base32_decode( 'MZXW6YTBOI' ) );
    }

    // ------------------------------------------------------------------
    // Outside services
    // ------------------------------------------------------------------

    public function test_allowed_hosts_include_subdomains_but_not_lookalikes() {
        $allowed = [ 'shipstation.com', 'wordpress.org' ];
        $this->assertTrue( block_external::host_allowed( 'ssapi.shipstation.com', $allowed ) );
        $this->assertTrue( block_external::host_allowed( 'SHIPSTATION.COM', $allowed ) );
        $this->assertTrue( block_external::host_allowed( 'downloads.wordpress.org', $allowed ) );
        $this->assertFalse( block_external::host_allowed( 'evilshipstation.com', $allowed ) );
        $this->assertFalse( block_external::host_allowed( 'wordpress.org.evil.com', $allowed ) );
    }

    // ------------------------------------------------------------------
    // REST API
    // ------------------------------------------------------------------

    public function test_rest_prefixes_match_whole_segments() {
        $prefixes = [ '/wp/v2/posts', '/wc/store/' ];
        $this->assertTrue( rest_api_security::matches_prefix( '/wp/v2/posts', $prefixes ) );
        $this->assertTrue( rest_api_security::matches_prefix( '/wp/v2/posts/12', $prefixes ) );
        $this->assertTrue( rest_api_security::matches_prefix( '/wc/store/v1/cart', $prefixes ) );
        $this->assertFalse( rest_api_security::matches_prefix( '/wp/v2/postsx', $prefixes ) );
        $this->assertFalse( rest_api_security::matches_prefix( '/wp/v2/users', $prefixes ) );
        // Kit 5.x matched anywhere in the URL, so this used to pass as public.
        $this->assertFalse( rest_api_security::matches_prefix( '/wp/v2/users?x=/wp/v2/posts', $prefixes ) );
    }

    // ------------------------------------------------------------------
    // Request helpers
    // ------------------------------------------------------------------

    public function test_cidr_matching() {
        $this->assertTrue( request::in_cidr( '10.1.2.3', '10.0.0.0/8' ) );
        $this->assertFalse( request::in_cidr( '11.1.2.3', '10.0.0.0/8' ) );
        $this->assertTrue( request::in_cidr( '192.168.1.130', '192.168.1.128/25' ) );
        $this->assertFalse( request::in_cidr( '192.168.1.127', '192.168.1.128/25' ) );
        $this->assertTrue( request::in_cidr( '2001:db8::1', '2001:db8::/32' ) );
        $this->assertFalse( request::in_cidr( '2001:db9::1', '2001:db8::/32' ) );
        $this->assertFalse( request::in_cidr( '10.0.0.1', '2001:db8::/32' ) );
    }

    // ------------------------------------------------------------------
    // Environment
    // ------------------------------------------------------------------

    public function test_copy_hosts() {
        $this->assertTrue( environment::is_copy_host( 'staging.mightyrhino.net' ) );
        $this->assertTrue( environment::is_copy_host( 'shop.kinsta.cloud' ) );
        $this->assertTrue( environment::is_copy_host( 'shop.local' ) );
        $this->assertFalse( environment::is_copy_host( 'shop.example.com' ) );
        $this->assertFalse( environment::is_copy_host( 'notkinsta.cloud.example.com' ) );
    }

    public function test_hosts_normalize_without_www_or_port() {
        $this->assertSame( 'shop.example.com', environment::normalize_host( 'https://www.Shop.example.com:8443/path' ) );
        $this->assertSame( 'shop.example.com', environment::normalize_host( 'shop.example.com' ) );
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    public function test_legacy_values_map_to_switches() {
        $field = [ 'type' => 'bool', 'legacy' => 'enable' ];
        // Every Kit 5.x radio stored 'enable' to mean "do the thing" (block, protect).
        $this->assertTrue( settings::decode_legacy( $field, 'enable' ) );
        $this->assertFalse( settings::decode_legacy( $field, 'disable' ) );
        $this->assertNull( settings::decode_legacy( $field, '' ), 'Unset keeps the environment default.' );
        $this->assertSame( 'enable', settings::encode_legacy( $field, true ) );
        $this->assertSame( 'disable', settings::encode_legacy( $field, false ) );
    }

    public function test_legacy_lists_and_urls() {
        $this->assertSame( [ 'api.stripe.com', 'shipstation.com' ], settings::decode_legacy( [ 'type' => 'list', 'legacy' => 'csv' ], 'api.stripe.com, shipstation.com' ) );
        $this->assertSame( 'api.stripe.com,shipstation.com', settings::encode_legacy( [ 'legacy' => 'csv' ], [ 'api.stripe.com', 'shipstation.com' ] ) );
        $b64 = base64_encode( 'https://shop.example.com/' );
        $this->assertSame( 'https://shop.example.com', settings::decode_legacy( [ 'type' => 'url', 'legacy' => 'b64' ], $b64 ) );
    }

    public function test_sanitizing_rejects_bad_values_with_plain_messages() {
        $this->assertTrue( settings::sanitize( [ 'type' => 'bool' ], 'on' ) );
        $this->assertInstanceOf( WP_Error::class, settings::sanitize( [ 'type' => 'enum', 'options' => [ '0' => 'No', '1' => 'Yes' ] ], '9' ) );
        $this->assertInstanceOf( WP_Error::class, settings::sanitize( [ 'type' => 'int', 'min' => 1, 'max' => 10 ], 50 ) );
        $this->assertSame( 5, settings::sanitize( [ 'type' => 'int', 'min' => 1, 'max' => 10 ], '5' ) );
        $login = [ 'type' => 'text', 'constant' => 'BUILT_ENDPOINT' ];
        $this->assertSame( 'team-entry', settings::sanitize( $login, '/team-entry/' ) );
        $this->assertInstanceOf( WP_Error::class, settings::sanitize( $login, 'wp-admin' ) );
        $this->assertInstanceOf( WP_Error::class, settings::sanitize( $login, 'has spaces' ) );
        $this->assertInstanceOf( WP_Error::class, settings::sanitize( [ 'type' => 'url' ], 'shop' ) );
        $this->assertSame( 'https://shop.example.com', settings::sanitize( [ 'type' => 'url' ], 'shop.example.com/' ) );
    }

}
