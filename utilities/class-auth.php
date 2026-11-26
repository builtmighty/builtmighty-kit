<?php
/**
 * Two-factor authentication.
 *
 * Authenticator app codes (TOTP, RFC 6238) with replay protection, emailed
 * 8-digit codes stored as keyed hashes, one-time backup codes, trusted
 * devices, lockouts, and the short-lived "pending sign-in" that carries a
 * user from the password step to the code step.
 *
 * Secrets are encrypted at rest with libsodium using a dedicated key, so
 * rotating WordPress salts no longer breaks everyone's two-factor.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Utility;

if( ! defined( 'ABSPATH' ) ) { exit; }

class authentication {

    const MAX_FAILURES      = 5;              // Wrong codes before a lockout.
    const FAILURE_WINDOW    = 900;            // 15 minutes.
    const LOCKOUT           = 1800;           // 30 minutes.
    const EMAIL_CODE_TTL    = 300;            // 5 minutes.
    const SETUP_KEY_TTL     = DAY_IN_SECONDS; // Setup links last a day.
    const PENDING_TTL       = 600;            // 10 minutes to enter a code.
    const TRUST_DAYS        = 30;
    const BACKUP_CODE_COUNT = 10;
    const PENDING_COOKIE    = 'kit_2fa_pending';
    const TRUST_COOKIE      = 'kit_2fa_trust';

    // ------------------------------------------------------------------
    // Who needs it
    // ------------------------------------------------------------------

    /**
     * Does this user need two-factor?
     */
    public function is_required( $user ): bool {
        if( ! $user instanceof \WP_User ) return false;
        if( ! settings::is_on( 'kit_enable_2fa' ) ) return false;
        if( in_array( 'administrator', (array) $user->roles, true ) ) return true;
        if( is_multisite() && is_super_admin( $user->ID ) ) return true;
        return (bool) array_intersect( (array) $user->roles, (array) settings::get( 'kit_2fa_users' ) );
    }

    /**
     * Has this user set up an authenticator app?
     */
    public function is_enabled( $user ): bool {
        return $user instanceof \WP_User && ! empty( get_user_meta( $user->ID, 'authentication_confirmed', true ) ) && $this->get_secret( $user ) !== false;
    }

    /**
     * Has this user set up a second factor (authenticator app or passkey)?
     */
    public function is_enrolled( $user ): bool {
        if( $this->is_enabled( $user ) ) return true;
        $passkeys = get_user_meta( $user->ID, 'kit_passkeys', true );
        return is_array( $passkeys ) && ! empty( $passkeys );
    }

    /**
     * Must this user set up two-factor before using the site?
     */
    public function needs_enrollment( $user ): bool {
        return $this->is_required( $user ) && ! $this->is_enrolled( $user ) && settings::get( 'kit_2fa_unenrolled' ) === 'enroll';
    }

    /**
     * Methods available to this user, in the order to offer them.
     *
     * @return array [ method => label ]
     */
    public function methods( $user ): array {
        $methods = [];
        if( $this->is_enabled( $user ) ) {
            $methods['app'] = 'Authenticator app';
        } else {
            $methods['email'] = 'Email code';
        }
        if( $this->get_backup_code_count( $user->ID ) > 0 ) {
            $methods['backup'] = 'Backup code';
        }
        /**
         * Filter two-factor methods for a user (passkeys add themselves here).
         *
         * @since 6.0.0
         */
        return (array) apply_filters( 'kit_2fa_methods', $methods, $user );
    }

    // ------------------------------------------------------------------
    // Encryption
    // ------------------------------------------------------------------

    /**
     * Encryption key: KIT_2FA_KEY constant, or a random key stored once.
     */
    private function key(): string {
        if( defined( 'KIT_2FA_KEY' ) && KIT_2FA_KEY ) {
            return hash( 'sha256', (string) KIT_2FA_KEY, true );
        }
        $stored = (string) get_option( 'kit_2fa_key', '' );
        $raw    = $stored !== '' ? base64_decode( $stored, true ) : false;
        if( $raw === false || strlen( $raw ) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
            $raw = sodium_crypto_secretbox_keygen();
            update_option( 'kit_2fa_key', base64_encode( $raw ), false );
        }
        return $raw;
    }

    /**
     * Encrypt.
     */
    public function encrypt( string $value ): string {
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $value, $nonce, $this->key() ) );
    }

    /**
     * Decrypt (current format, then the 5.x AES format).
     *
     * @return string|false
     */
    public function decrypt( string $value ) {

        if( strpos( $value, 's1:' ) === 0 ) {
            $data = base64_decode( substr( $value, 3 ), true );
            if( $data === false || strlen( $data ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) return false;
            $plain = sodium_crypto_secretbox_open( substr( $data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $this->key() );
            return $plain === false ? false : $plain;
        }

        // Kit 5.x: AES-256-CBC keyed from salts.
        if( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) return false;
        $data = base64_decode( $value, true );
        if( $data === false || strlen( $data ) < 17 ) return false;
        $plain = openssl_decrypt( substr( $data, 16 ), 'aes-256-cbc', hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true ), OPENSSL_RAW_DATA, substr( $data, 0, 16 ) );
        return $plain === false ? false : $plain;

    }

    // ------------------------------------------------------------------
    // Authenticator app (TOTP)
    // ------------------------------------------------------------------

    /**
     * New base32 secret (160 bits).
     */
    public function new_secret(): string {
        return self::base32_encode( random_bytes( 20 ) );
    }

    /**
     * The user's secret, decrypted. Migrates older storage formats.
     *
     * @return string|false
     */
    public function get_secret( $user ) {

        $stored = (string) get_user_meta( $user->ID, 'authentication_secret', true );
        if( $stored === '' ) return false;

        $plain = $this->decrypt( $stored );
        if( $plain !== false && preg_match( '/^[A-Z2-7]+=*$/', $plain ) ) {
            if( strpos( $stored, 's1:' ) !== 0 ) {
                update_user_meta( $user->ID, 'authentication_secret', $this->encrypt( $plain ) );
            }
            return $plain;
        }

        // Very old plain-text secrets.
        if( preg_match( '/^[A-Z2-7]+=*$/', $stored ) ) {
            update_user_meta( $user->ID, 'authentication_secret', $this->encrypt( $stored ) );
            return $stored;
        }

        return false;

    }

    /**
     * Secret waiting to be confirmed during setup (not active yet).
     */
    public function pending_secret( $user, bool $create = true ) {
        $stored = (string) get_user_meta( $user->ID, 'kit_2fa_pending_secret', true );
        $plain  = $stored !== '' ? $this->decrypt( $stored ) : false;
        if( $plain === false && $create ) {
            $plain = $this->new_secret();
            update_user_meta( $user->ID, 'kit_2fa_pending_secret', $this->encrypt( $plain ) );
        }
        return $plain;
    }

    /**
     * otpauth:// URI for the QR code.
     */
    public function otpauth_uri( $user, string $secret ): string {
        $issuer = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ?: wp_parse_url( home_url(), PHP_URL_HOST );
        return 'otpauth://totp/' . rawurlencode( $issuer . ':' . $user->user_login ) . '?secret=' . $secret . '&issuer=' . rawurlencode( $issuer ) . '&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * TOTP code for a time step.
     */
    public static function totp( string $secret, int $step ): string {
        $key  = self::base32_decode( $secret );
        $hash = hash_hmac( 'sha1', pack( 'N*', 0, $step ), $key, true );
        $off  = ord( $hash[19] ) & 0x0f;
        $bin  = ( ( ord( $hash[ $off ] ) & 0x7f ) << 24 ) | ( ( ord( $hash[ $off + 1 ] ) & 0xff ) << 16 ) | ( ( ord( $hash[ $off + 2 ] ) & 0xff ) << 8 ) | ( ord( $hash[ $off + 3 ] ) & 0xff );
        return str_pad( (string) ( $bin % 1000000 ), 6, '0', STR_PAD_LEFT );
    }

    /**
     * Matching time step for a code (one step either side), or false.
     *
     * @return int|false
     */
    public static function totp_match( string $secret, string $code, ?int $now = null ) {
        if( ! preg_match( '/^\d{6}$/', $code ) ) return false;
        $step = (int) floor( ( $now ?? time() ) / 30 );
        foreach( [ 0, -1, 1 ] as $drift ) {
            if( hash_equals( self::totp( $secret, $step + $drift ), $code ) ) return $step + $drift;
        }
        return false;
    }

    /**
     * Check an app code, refusing a code that was already used.
     */
    public function verify_totp( $user, string $code, ?string $secret = null ): bool {
        $secret = $secret ?? $this->get_secret( $user );
        if( ! $secret ) return false;
        $step = self::totp_match( $secret, preg_replace( '/\s+/', '', $code ) );
        if( $step === false ) return false;
        $last = (int) get_user_meta( $user->ID, 'kit_totp_last_step', true );
        if( $step <= $last ) return false;
        update_user_meta( $user->ID, 'kit_totp_last_step', $step );
        return true;
    }

    /**
     * Confirm app setup with a code from the pending secret.
     *
     * @return array|false Backup codes on success.
     */
    public function confirm_app( $user, string $code ) {
        $secret = $this->pending_secret( $user, false );
        if( ! $secret || ! $this->verify_totp( $user, $code, $secret ) ) return false;
        update_user_meta( $user->ID, 'authentication_secret', $this->encrypt( $secret ) );
        update_user_meta( $user->ID, 'authentication_confirmed', time() );
        delete_user_meta( $user->ID, 'kit_2fa_pending_secret' );
        delete_user_meta( $user->ID, 'authentication_setup' );
        activity::log( '2fa', 'app_added', $user->user_login . ' set up an authenticator app', [ 'user_id' => $user->ID, 'level' => 'success' ] );
        return $this->generate_backup_codes( $user->ID );
    }

    // ------------------------------------------------------------------
    // Email codes
    // ------------------------------------------------------------------

    /**
     * Email a fresh 8-digit code. Limited to 3 a user every 10 minutes.
     *
     * @return bool|\WP_Error
     */
    public function send_code( $user ) {

        $limit = request::hit( 'otp_send', (string) $user->ID, 3, 600 );
        if( ! $limit['allowed'] ) {
            return new \WP_Error( 'kit_2fa_send_limit', sprintf( 'A code was just sent. You can ask for another in %d minutes.', (int) ceil( $limit['retry_after'] / 60 ) ) );
        }

        $code = str_pad( (string) random_int( 0, 99999999 ), 8, '0', STR_PAD_LEFT );
        update_user_meta( $user->ID, 'kit_email_code', [ 'hash' => $this->hash_code( $code ), 'time' => time() ] );
        delete_user_meta( $user->ID, 'email_code' ); // Kit 5.x stored it in plain text.

        $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $subject = sprintf( '%s is your %s sign-in code', implode( ' ', str_split( $code, 4 ) ), $site );
        $body    = sprintf(
            '<p>Hi %1$s,</p><p>Your sign-in code is:</p><p style="font-size:28px;font-weight:700;letter-spacing:4px;font-family:monospace">%2$s</p><p>It expires in 5 minutes. If you didn\'t try to sign in, change your password, because someone else knows it.</p><p>%3$s</p>',
            esc_html( $user->display_name ),
            esc_html( implode( ' ', str_split( $code, 4 ) ) ),
            esc_html( $site )
        );

        $sent = wp_mail( $user->user_email, $subject, $this->wrap_email( $subject, $body ), [ 'Content-Type: text/html; charset=UTF-8' ] );
        activity::log( '2fa', 'code_sent', 'Sign-in code emailed to ' . $user->user_login, [ 'user_id' => $user->ID ] );
        return (bool) $sent;

    }

    /**
     * Check an emailed code (single use, 5 minutes).
     */
    public function verify_email_code( $user, string $code ): bool {
        $code   = preg_replace( '/\D/', '', $code );
        $stored = get_user_meta( $user->ID, 'kit_email_code', true );
        if( ! is_array( $stored ) || strlen( $code ) !== 8 ) return false;
        if( ( time() - (int) $stored['time'] ) > self::EMAIL_CODE_TTL ) {
            delete_user_meta( $user->ID, 'kit_email_code' );
            return false;
        }
        if( ! hash_equals( (string) $stored['hash'], $this->hash_code( $code ) ) ) return false;
        delete_user_meta( $user->ID, 'kit_email_code' );
        return true;
    }

    /**
     * Keyed hash for short codes.
     */
    private function hash_code( string $code ): string {
        return hash_hmac( 'sha256', $code, wp_salt( 'auth' ) . $this->key() );
    }

    // ------------------------------------------------------------------
    // Backup codes
    // ------------------------------------------------------------------

    /**
     * Ten new one-time codes, like 8f3k-2qwm. Replaces any old ones.
     *
     * @return array Plain codes, shown once.
     */
    public function generate_backup_codes( int $user_id ): array {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $codes = []; $hashes = [];
        for( $i = 0; $i < self::BACKUP_CODE_COUNT; $i++ ) {
            $c = '';
            for( $j = 0; $j < 8; $j++ ) $c .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
            $codes[]  = substr( $c, 0, 4 ) . '-' . substr( $c, 4 );
            $hashes[] = 'h1:' . $this->hash_code( $c );
        }
        update_user_meta( $user_id, 'authentication_backup_codes', $hashes );
        return $codes;
    }

    /**
     * Check and use up a backup code.
     */
    public function verify_backup_code( int $user_id, string $code ): bool {
        $clean = strtolower( preg_replace( '/[^a-z0-9]/i', '', $code ) );
        if( ! preg_match( '/^[a-z0-9]{8}$/', $clean ) ) return false;
        $hashes = get_user_meta( $user_id, 'authentication_backup_codes', true );
        if( ! is_array( $hashes ) || ! $hashes ) return false;
        $mine = 'h1:' . $this->hash_code( $clean );
        foreach( $hashes as $i => $hash ) {
            $ok = strpos( (string) $hash, 'h1:' ) === 0 ? hash_equals( (string) $hash, $mine ) : wp_check_password( $clean, (string) $hash ); // 5.x used password hashes.
            if( $ok ) {
                unset( $hashes[ $i ] );
                update_user_meta( $user_id, 'authentication_backup_codes', array_values( $hashes ) );
                return true;
            }
        }
        return false;
    }

    /**
     * Unused backup codes left.
     */
    public function get_backup_code_count( int $user_id ): int {
        $codes = get_user_meta( $user_id, 'authentication_backup_codes', true );
        return is_array( $codes ) ? count( $codes ) : 0;
    }

    // ------------------------------------------------------------------
    // Verify any method, with lockout
    // ------------------------------------------------------------------

    /**
     * Check a code for a method. Records failures and locks out after five.
     *
     * @return true|\WP_Error
     */
    public function verify( $user, string $method, string $code ) {

        if( $remaining = $this->lockout_remaining( $user->ID ) ) {
            return new \WP_Error( 'kit_2fa_locked', sprintf( 'Too many wrong codes. Try again in %d minutes.', (int) ceil( $remaining / 60 ) ) );
        }

        $code = trim( $code );
        if( $code === '' ) return new \WP_Error( 'kit_2fa_empty', 'Enter your code.' );

        $ok = false;
        switch( $method ) {
            case 'app':    $ok = $this->verify_totp( $user, $code ); break;
            case 'email':  $ok = $this->verify_email_code( $user, $code ); break;
            case 'backup': $ok = $this->verify_backup_code( $user->ID, $code ); break;
            default:
                /**
                 * Verify a custom two-factor method (passkeys).
                 *
                 * @since 6.0.0
                 */
                $ok = (bool) apply_filters( 'kit_2fa_verify_' . $method, false, $user, $code );
        }

        if( $ok ) {
            delete_transient( 'kit_2fa_fail_' . $user->ID );
            if( $method === 'backup' ) {
                activity::log( '2fa', 'backup_used', $user->user_login . ' used a backup code (' . $this->get_backup_code_count( $user->ID ) . ' left)', [ 'user_id' => $user->ID, 'level' => 'warning' ] );
            }
            return true;
        }

        $fails = (int) get_transient( 'kit_2fa_fail_' . $user->ID ) + 1;
        set_transient( 'kit_2fa_fail_' . $user->ID, $fails, self::FAILURE_WINDOW );
        activity::log( '2fa', 'wrong_code', $user->user_login . ' entered a wrong code', [ 'user_id' => $user->ID, 'level' => 'warning', 'context' => [ 'method' => $method ] ] );

        if( $fails >= self::MAX_FAILURES ) {
            set_transient( 'kit_2fa_lock_' . $user->ID, time() + self::LOCKOUT, self::LOCKOUT );
            delete_transient( 'kit_2fa_fail_' . $user->ID );
            activity::log( '2fa', 'locked', $user->user_login . ' is locked out for 30 minutes after ' . self::MAX_FAILURES . ' wrong codes', [ 'user_id' => $user->ID, 'level' => 'error' ] );
            return new \WP_Error( 'kit_2fa_locked', 'Too many wrong codes. Try again in 30 minutes.' );
        }

        $left = self::MAX_FAILURES - $fails;
        return new \WP_Error( 'kit_2fa_wrong', sprintf( _n( 'That code didn\'t match. %d try left before a 30-minute lockout.', 'That code didn\'t match. %d tries left before a 30-minute lockout.', $left, 'builtmighty-kit' ), $left ) );

    }

    /**
     * Seconds left on a lockout (0 if none). Stored as an expiry time, so
     * it works with persistent object caches too.
     */
    public function lockout_remaining( int $user_id ): int {
        $until = (int) get_transient( 'kit_2fa_lock_' . $user_id );
        return $until > time() ? $until - time() : 0;
    }

    // ------------------------------------------------------------------
    // Pending sign-in (between password and code)
    // ------------------------------------------------------------------

    /**
     * Start a pending sign-in and set its cookie.
     */
    public function start_pending( $user, bool $remember, string $redirect, string $method ): string {
        $token = bin2hex( random_bytes( 32 ) );
        set_transient( 'kit_2fa_p_' . hash( 'sha256', $token ), [
            'user_id'  => $user->ID,
            'remember' => $remember,
            'redirect' => $redirect,
            'method'   => $method,
            'created'  => time(),
        ], self::PENDING_TTL );
        $this->set_cookie( self::PENDING_COOKIE, $token, time() + self::PENDING_TTL );
        return $token;
    }

    /**
     * The pending sign-in for this browser.
     *
     * @return array|null [ token, user, data ]
     */
    public function get_pending(): ?array {
        $token = isset( $_COOKIE[ self::PENDING_COOKIE ] ) ? preg_replace( '/[^a-f0-9]/', '', (string) $_COOKIE[ self::PENDING_COOKIE ] ) : '';
        if( strlen( $token ) !== 64 ) return null;
        $data = get_transient( 'kit_2fa_p_' . hash( 'sha256', $token ) );
        if( ! is_array( $data ) ) return null;
        $user = get_userdata( (int) $data['user_id'] );
        return $user ? [ 'token' => $token, 'user' => $user, 'data' => $data ] : null;
    }

    /**
     * Update the pending sign-in (method switch).
     */
    public function update_pending( string $token, array $data ): void {
        set_transient( 'kit_2fa_p_' . hash( 'sha256', $token ), $data, max( 60, self::PENDING_TTL - ( time() - (int) $data['created'] ) ) );
    }

    /**
     * End the pending sign-in.
     */
    public function end_pending( string $token ): void {
        delete_transient( 'kit_2fa_p_' . hash( 'sha256', $token ) );
        $this->set_cookie( self::PENDING_COOKIE, '', time() - YEAR_IN_SECONDS );
    }

    // ------------------------------------------------------------------
    // Trusted devices
    // ------------------------------------------------------------------

    /**
     * Remember this browser for 30 days.
     */
    public function trust_device( $user ): void {
        $token   = bin2hex( random_bytes( 32 ) );
        $devices = $this->devices( $user->ID );
        $ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $devices[ hash( 'sha256', $token ) ] = [
            'label'   => \BuiltMightyKit\Public\login_logging::browser( $ua ),
            'ip'      => request::client_ip(),
            'created' => time(),
            'expires' => time() + self::TRUST_DAYS * DAY_IN_SECONDS,
        ];
        // Newest ten only.
        uasort( $devices, function( $a, $b ) { return $b['created'] <=> $a['created']; } );
        update_user_meta( $user->ID, 'kit_trusted_devices', array_slice( $devices, 0, 10, true ) );
        $this->set_cookie( self::TRUST_COOKIE, $user->ID . ':' . $token, time() + self::TRUST_DAYS * DAY_IN_SECONDS );
        activity::log( '2fa', 'device_trusted', $user->user_login . ' trusted a device for 30 days', [ 'user_id' => $user->ID ] );
    }

    /**
     * Is this browser trusted for this user?
     */
    public function is_trusted( $user ): bool {
        $cookie = isset( $_COOKIE[ self::TRUST_COOKIE ] ) ? (string) $_COOKIE[ self::TRUST_COOKIE ] : '';
        if( ! preg_match( '/^(\d+):([a-f0-9]{64})$/', $cookie, $m ) || (int) $m[1] !== (int) $user->ID ) return false;
        $devices = $this->devices( $user->ID );
        $hash    = hash( 'sha256', $m[2] );
        foreach( $devices as $key => $d ) {
            if( hash_equals( (string) $key, $hash ) ) return ( (int) $d['expires'] ) > time();
        }
        return false;
    }

    /**
     * Unexpired trusted devices.
     */
    public function devices( int $user_id ): array {
        $devices = get_user_meta( $user_id, 'kit_trusted_devices', true );
        if( ! is_array( $devices ) ) return [];
        return array_filter( $devices, function( $d ) { return (int) ( $d['expires'] ?? 0 ) > time(); } );
    }

    /**
     * Forget one device, or all of them.
     */
    public function revoke_devices( int $user_id, string $hash = '' ): void {
        if( $hash === '' ) {
            delete_user_meta( $user_id, 'kit_trusted_devices' );
            return;
        }
        $devices = $this->devices( $user_id );
        unset( $devices[ $hash ] );
        update_user_meta( $user_id, 'kit_trusted_devices', $devices );
    }

    // ------------------------------------------------------------------
    // Setup links (emailed to people who must set up an app)
    // ------------------------------------------------------------------

    /**
     * New setup key. Returns the token for the link.
     */
    public function generate_key( $user, $encode = false ) {
        $key = wp_generate_password( 32, false );
        update_user_meta( $user->ID, 'authentication_setup', [ 'hash' => hash( 'sha256', $key ), 'created' => time() ] );
        return $encode ? rtrim( strtr( base64_encode( $user->ID . ':' . $key ), '+/', '-_' ), '=' ) : $key;
    }

    /**
     * User for a setup token, or null.
     */
    public function user_for_key( string $token ) {
        $decoded = base64_decode( strtr( $token, '-_', '+/' ) );
        if( ! $decoded || strpos( $decoded, ':' ) === false ) return null;
        [ $user_id, $key ] = explode( ':', $decoded, 2 );
        $setup = get_user_meta( (int) $user_id, 'authentication_setup', true );
        if( ! is_array( $setup ) ) return null;
        if( time() - (int) ( $setup['created'] ?? 0 ) > self::SETUP_KEY_TTL ) return null;
        $hash = $setup['hash'] ?? ( isset( $setup['key'] ) ? hash( 'sha256', (string) $setup['key'] ) : '' );
        if( $hash === '' || ! hash_equals( $hash, hash( 'sha256', $key ) ) ) return null;
        return get_userdata( (int) $user_id ) ?: null;
    }

    /**
     * Email a setup link.
     */
    public function send_setup( $user ): bool {
        $token = $this->generate_key( $user, true );
        $url   = add_query_arg( [ 'action' => 'kit_2fa_setup', 'key' => $token ], wp_login_url() );
        $site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $subject = sprintf( 'Set up two-factor sign-in for %s', $site );
        $body = sprintf(
            '<p>Hi %1$s,</p><p>%2$s asks for a second step when you sign in. Set up an authenticator app so you don\'t have to wait for emailed codes.</p><p><a href="%3$s" style="display:inline-block;background:#0E1726;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:600">Set up two-factor</a></p><p>The link works for 24 hours. If you didn\'t expect this, you can ignore it.</p>',
            esc_html( $user->display_name ),
            esc_html( $site ),
            esc_url( $url )
        );
        return (bool) wp_mail( $user->user_email, $subject, $this->wrap_email( $subject, $body ), [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    /**
     * Remove a user's authenticator app, backup codes and trusted devices.
     */
    public function disable( $user ): void {
        foreach( [ 'authentication_setup', 'authentication_secret', 'authentication_confirmed', 'authentication_backup_codes', 'kit_2fa_pending_secret', 'kit_totp_last_step', 'kit_email_code', 'email_code' ] as $key ) {
            delete_user_meta( $user->ID, $key );
        }
        $this->revoke_devices( $user->ID );
        activity::log( '2fa', 'reset', 'Two-factor reset for ' . $user->user_login, [ 'context' => [ 'user' => $user->ID ], 'level' => 'warning' ] );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Set a secure cookie on the site path.
     */
    public function set_cookie( string $name, string $value, int $expires ): void {
        if( headers_sent() ) return;
        $options = [ 'expires' => $expires, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ];
        if( COOKIE_DOMAIN ) $options['domain'] = COOKIE_DOMAIN;
        setcookie( $name, $value, $options );
        if( SITECOOKIEPATH && SITECOOKIEPATH !== $options['path'] ) {
            $options['path'] = SITECOOKIEPATH;
            setcookie( $name, $value, $options );
        }
        if( $value === '' ) unset( $_COOKIE[ $name ] ); else $_COOKIE[ $name ] = $value;
    }

    /**
     * Wrap an email in WooCommerce's template when available.
     */
    private function wrap_email( string $heading, string $body ): string {
        if( function_exists( 'WC' ) && class_exists( '\WC_Email' ) ) {
            $mailer = WC()->mailer();
            return ( new \WC_Email() )->style_inline( $mailer->wrap_message( $heading, $body ) );
        }
        return '<div style="font-family:-apple-system,Segoe UI,sans-serif;font-size:15px;line-height:1.5;color:#2A3445">' . $body . '</div>';
    }

    /**
     * Base32 (RFC 4648) encode.
     */
    public static function base32_encode( string $data ): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach( str_split( $data ) as $c ) $bits .= str_pad( decbin( ord( $c ) ), 8, '0', STR_PAD_LEFT );
        $out = '';
        foreach( str_split( $bits, 5 ) as $chunk ) $out .= $alphabet[ bindec( str_pad( $chunk, 5, '0' ) ) ];
        return $out;
    }

    /**
     * Base32 (RFC 4648) decode.
     */
    public static function base32_decode( string $data ): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper( preg_replace( '/[^A-Za-z2-7]/', '', $data ) );
        $bits = '';
        foreach( str_split( $data ) as $c ) {
            $i = strpos( $alphabet, $c );
            if( $i === false ) continue;
            $bits .= str_pad( decbin( $i ), 5, '0', STR_PAD_LEFT );
        }
        $out = '';
        foreach( str_split( $bits, 8 ) as $byte ) {
            if( strlen( $byte ) === 8 ) $out .= chr( bindec( $byte ) );
        }
        return $out;
    }

}
