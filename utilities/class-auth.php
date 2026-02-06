<?php
/**
 * Authentication.
 *
 * Two-Factor Authentication System.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 5.0.0
 */
namespace BuiltMightyKit\Utility;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
class authentication {

    /**
     * Rate limit settings.
     *
     * @since   4.4.0
     */
    private const RATE_LIMIT_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW   = 900; // 15 minutes in seconds
    private const RATE_LIMIT_LOCKOUT  = 1800; // 30 minutes lockout

    /**
     * Setup key expiration in seconds (24 hours).
     *
     * @since   5.0.0
     */
    private const SETUP_KEY_EXPIRATION = 86400;

    /**
     * Number of backup codes to generate.
     *
     * @since   5.0.0
     */
    private const BACKUP_CODE_COUNT = 10;

    /**
     * Check if rate limited.
     *
     * @param   string  $identifier  Unique identifier (user_id or IP)
     * @param   string  $type        Type of rate limit (auth, login, etc.)
     * @return  bool    True if rate limited, false otherwise
     *
     * @since   4.4.0
     */
    public function is_rate_limited( $identifier, $type = 'auth' ) {
        $lockout_key = 'kit_lockout_' . $type . '_' . md5( $identifier );

        // Check if currently locked out.
        if ( get_transient( $lockout_key ) ) {
            return true;
        }

        return false;
    }

    /**
     * Record a failed attempt for rate limiting.
     *
     * @param   string  $identifier  Unique identifier (user_id or IP)
     * @param   string  $type        Type of rate limit (auth, login, etc.)
     * @return  bool    True if now locked out, false otherwise
     *
     * @since   4.4.0
     */
    public function record_failed_attempt( $identifier, $type = 'auth' ) {
        $key = 'kit_rate_limit_' . $type . '_' . md5( $identifier );
        $lockout_key = 'kit_lockout_' . $type . '_' . md5( $identifier );

        // Get current attempts.
        $attempts = get_transient( $key );
        if ( false === $attempts ) {
            $attempts = 0;
        }

        // Increment attempts.
        $attempts++;
        set_transient( $key, $attempts, self::RATE_LIMIT_WINDOW );

        // Check if exceeded limit.
        if ( $attempts >= self::RATE_LIMIT_ATTEMPTS ) {
            set_transient( $lockout_key, true, self::RATE_LIMIT_LOCKOUT );
            delete_transient( $key );
            return true;
        }

        return false;
    }

    /**
     * Clear rate limit for an identifier.
     *
     * @param   string  $identifier  Unique identifier (user_id or IP)
     * @param   string  $type        Type of rate limit (auth, login, etc.)
     *
     * @since   4.4.0
     */
    public function clear_rate_limit( $identifier, $type = 'auth' ) {
        $key = 'kit_rate_limit_' . $type . '_' . md5( $identifier );
        delete_transient( $key );
    }

    /**
     * Get remaining lockout time.
     *
     * @param   string  $identifier  Unique identifier (user_id or IP)
     * @param   string  $type        Type of rate limit (auth, login, etc.)
     * @return  int     Seconds remaining, or 0 if not locked out
     *
     * @since   4.4.0
     */
    public function get_lockout_remaining( $identifier, $type = 'auth' ) {
        $lockout_key = 'kit_lockout_' . $type . '_' . md5( $identifier );
        $timeout = get_option( '_transient_timeout_' . $lockout_key );

        if ( $timeout ) {
            $remaining = $timeout - time();
            return max( 0, $remaining );
        }

        return 0;
    }

    /**
     * Get client IP address.
     *
     * @return  string  Client IP address
     *
     * @since   4.4.0
     */
    public function get_client_ip() {
        $ip = '';

        // Check for forwarded IP (behind proxy/load balancer).
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Validate IP format.
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }

        return '0.0.0.0';
    }

    /**
     * Encrypt a value using OpenSSL.
     *
     * @param   string  $value  The value to encrypt.
     * @return  string  The encrypted value (base64-encoded).
     *
     * @since   5.0.0
     */
    public function encrypt( $value ) {

        // Use AUTH_KEY as encryption key.
        $key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
        $iv  = openssl_random_pseudo_bytes( 16 );

        // Encrypt.
        $encrypted = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

        // Return IV + encrypted data, base64-encoded.
        return base64_encode( $iv . $encrypted );

    }

    /**
     * Decrypt a value using OpenSSL.
     *
     * @param   string  $value  The encrypted value (base64-encoded).
     * @return  string|false    The decrypted value, or false on failure.
     *
     * @since   5.0.0
     */
    public function decrypt( $value ) {

        // Use AUTH_KEY as encryption key.
        $key  = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
        $data = base64_decode( $value );

        if ( $data === false || strlen( $data ) < 17 ) {
            return false;
        }

        // Extract IV and encrypted data.
        $iv        = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );

        // Decrypt.
        return openssl_decrypt( $encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

    }

    /**
     * Authenticate.
     *
     * @since   1.0.0
     * @since   4.4.0  Added rate limiting
     * @since   5.0.0  Added backup code support, increased TOTP discrepancy
     */
    public function authenticate( $user_id, $code, $type = null ) {

        // Check for code.
        if( ! isset( $code ) || empty( $code ) ) return false;

        // Check rate limiting for this user.
        if ( $this->is_rate_limited( $user_id, 'auth_code' ) ) {
            return false;
        }

        // Get user.
        $user = get_user_by( 'id', $user_id );

        // Check if this is a backup code.
        if ( $this->verify_backup_code( $user_id, $code ) ) {
            $this->clear_rate_limit( $user_id, 'auth_code' );
            return true;
        }

        // Check if App is enabled.
        if( ! $this->is_enabled( $user ) && $type == 'login' ) {

            // Get code.
            $user_code = get_user_meta( $user_id, 'email_code', true );

            // Check if code is set.
            if( empty( $user_code ) ) {
                $this->record_failed_attempt( $user_id, 'auth_code' );
                return false;
            }

            // Check code.
            if( $user_code['code'] !== $code ) {
                $this->record_failed_attempt( $user_id, 'auth_code' );
                return false;
            }

            // Check time.
            if( ( time() - $user_code['time'] ) > 300 ) {
                $this->record_failed_attempt( $user_id, 'auth_code' );
                return false;
            }

            // Delete code.
            delete_user_meta( $user_id, 'email_code' );

            // Clear rate limit on successful authentication.
            $this->clear_rate_limit( $user_id, 'auth_code' );

            // Return true.
            return true;

        } else {

            // Get secret (decrypt).
            $secret = $this->get_secret( $user );

            if ( ! $secret ) {
                $this->record_failed_attempt( $user_id, 'auth_code' );
                return false;
            }

            // Get Google Authenticator.
            $google_auth = new GoogleAuthenticator();

            // Sanitize code.
            $code = sanitize_text_field( $code );

            // Check the code with discrepancy of 2 (±60 seconds tolerance).
            if( ! $google_auth->checkCode( $secret, $code, 2 ) ) {
                $this->record_failed_attempt( $user_id, 'auth_code' );
                return false;
            }

            // Clear rate limit on successful authentication.
            $this->clear_rate_limit( $user_id, 'auth_code' );

            // Return true.
            return true;

        }

    }

    /**
     * Send setup.
     *
     * @param   object  $user
     *
     * @since   1.0.0
     */
    public function send_setup( $user ) {

        // Generate key.
        $key = $this->generate_key( $user, true );

        // Generate secret.
        $this->generate_secret( $user );

        // Set subject.
        $subject = 'Two-Factor Authentication Setup | ' . get_bloginfo( 'name' );

        // Start output buffering.
        ob_start();

        // Compose. ?>
        Hello <?php echo esc_html( $user->display_name ); ?>,<br><br>
        Two-Factor Authentication is required for your account on <?php echo esc_html( get_bloginfo( 'name' ) ); ?>.<br><br>
        Please click the link below to set up Two-Factor Authentication:<br><br>
        <a href="<?php echo esc_url( site_url( '/security?key=' . $key ) ); ?>">Setup Two-Factor Authentication</a><br><br>
        This link expires in 24 hours.<br><br>
        If you did not trigger this email, please ignore it.<br><br>
        Thank you,<br>
        <?php echo esc_html( get_bloginfo( 'name' ) );

        // Set message.
        $message = ob_get_clean();

        // Set headers.
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . '<' . get_bloginfo( 'admin_email' ) . '>',
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

        // Send.
        wp_mail( $user->user_email, $subject, $message, $headers );

    }

    /**
     * Send code.
     *
     * Send an email with the authentication code.
     *
     * @param   object  $user
     *
     * @since   1.0.0
     * @since   5.0.0  Increased to 8-digit codes
     */
    public function send_code( $user ) {

        // Get code.
        $code = $this->generate_code( $user );

        // Set subject.
        $subject = 'Authentication Code | ' . get_bloginfo( 'name' );

        // Start output buffering.
        ob_start();

        // Compose. ?>
        Hello <?php echo esc_html( $user->display_name ); ?>,<br><br>
        Your authentication code is:<br><br>
        <code style="font-size:20px;text-align:center"><?php echo esc_html( $code['code'] ); ?></code><br><br>
        This code is valid for 5 minutes.<br><br>
        If you did not trigger this email, please ignore it.<br><br>
        Thank you,<br>
        <?php echo esc_html( get_bloginfo( 'name' ) );

        // Set message.
        $message = ob_get_clean();

        // Set headers.
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . '<' . get_bloginfo( 'admin_email' ) . '>',
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

        // Send.
        wp_mail( $user->user_email, $subject, $message, $headers );

    }

    /**
     * Create setup key with expiration.
     *
     * @param   object  $user
     * @param   boolean $encode
     *
     * @since   1.0.0
     * @since   5.0.0  Added expiration timestamp
     */
    public function generate_key( $user, $encode = false ) {

        // Generate key.
        $key = wp_generate_password( 32, false );

        // Save to user meta with timestamp for expiration.
        update_user_meta( $user->ID, 'authentication_setup', [
            'key'     => $key,
            'created' => time(),
        ] );

        // Return.
        return ( ! $encode ) ? $key : base64_encode( $user->ID . ':' . $key );

    }

    /**
     * Verify setup key with expiration check.
     *
     * @param   string  $key
     * @return  bool
     *
     * @since   1.0.0
     * @since   5.0.0  Added expiration check
     */
    public function verify_key( $key ) {

        // Get key.
        $key = $this->get_key( $key );

        // Get stored setup data.
        $setup = get_user_meta( $key['user_id'], 'authentication_setup', true );

        // Support both old format (string) and new format (array with expiration).
        if ( is_array( $setup ) ) {

            // Check expiration.
            if ( ( time() - $setup['created'] ) > self::SETUP_KEY_EXPIRATION ) {
                delete_user_meta( $key['user_id'], 'authentication_setup' );
                return false;
            }

            // Check key.
            if ( $key['key'] !== $setup['key'] ) return false;

        } else {

            // Legacy: plain string key (no expiration).
            if ( $key['key'] !== $setup ) return false;

        }

        // Return true.
        return true;

    }

    /**
     * Generate secret and encrypt before storing.
     *
     * @param   object  $user
     *
     * @since   1.0.0
     * @since   5.0.0  Encrypts secret at rest
     */
    public function generate_secret( $user ) {

        // Check if already set.
        $existing = $this->get_secret( $user );
        if ( $existing ) return $existing;

        // Google Authentication.
        $google_auth = new GoogleAuthenticator();

        // Create secret.
        $secret = $google_auth->generateSecret();

        // Encrypt and save secret.
        update_user_meta( $user->ID, 'authentication_secret', $this->encrypt( $secret ) );

        // Return plain secret for QR code generation.
        return $secret;

    }

    /**
     * Generate code.
     *
     * Generate an 8-digit authentication code for email.
     *
     * @param   object  $user
     *
     * @since   1.0.0
     * @since   5.0.0  Increased to 8 digits for better entropy
     */
    public function generate_code( $user ) {

        // Generate 8-digit code.
        $code = [
            'code'  => str_pad( random_int( 0, 99999999 ), 8, '0', STR_PAD_LEFT ),
            'time'  => time(),
        ];

        // Set.
        update_user_meta( $user->ID, 'email_code', $code );

        // Return.
        return $code;

    }

    /**
     * Generate backup codes.
     *
     * Generates one-time backup codes and stores them hashed.
     *
     * @param   int   $user_id
     * @return  array Plain text backup codes (only shown once).
     *
     * @since   5.0.0
     */
    public function generate_backup_codes( $user_id ) {

        $codes  = [];
        $hashed = [];

        for ( $i = 0; $i < self::BACKUP_CODE_COUNT; $i++ ) {
            // Generate 8-character alphanumeric code.
            $code     = strtolower( wp_generate_password( 8, false ) );
            $codes[]  = $code;
            $hashed[] = wp_hash_password( $code );
        }

        // Store hashed codes.
        update_user_meta( $user_id, 'authentication_backup_codes', $hashed );

        // Return plain codes (shown to user once).
        return $codes;

    }

    /**
     * Verify a backup code and consume it.
     *
     * @param   int     $user_id
     * @param   string  $code
     * @return  bool
     *
     * @since   5.0.0
     */
    public function verify_backup_code( $user_id, $code ) {

        $hashed_codes = get_user_meta( $user_id, 'authentication_backup_codes', true );

        if ( empty( $hashed_codes ) || ! is_array( $hashed_codes ) ) {
            return false;
        }

        $code = strtolower( sanitize_text_field( $code ) );

        foreach ( $hashed_codes as $index => $hashed ) {
            if ( wp_check_password( $code, $hashed ) ) {
                // Remove used code.
                unset( $hashed_codes[ $index ] );
                update_user_meta( $user_id, 'authentication_backup_codes', array_values( $hashed_codes ) );
                return true;
            }
        }

        return false;

    }

    /**
     * Get remaining backup code count.
     *
     * @param   int  $user_id
     * @return  int
     *
     * @since   5.0.0
     */
    public function get_backup_code_count( $user_id ) {

        $codes = get_user_meta( $user_id, 'authentication_backup_codes', true );
        return is_array( $codes ) ? count( $codes ) : 0;

    }

    /**
     * Get key.
     *
     * @param   string  $key
     *
     * @since   1.0.0
     */
    public function get_key( $key ) {

        // Get key.
        $key = explode( ':', base64_decode( sanitize_text_field( $key ) ) );

        // Return.
        return [
            'user_id'   => $key[0],
            'key'       => $key[1] ?? '',
        ];

    }

    /**
     * Get secret (decrypted).
     *
     * @param   object  $user
     * @return  string|false
     *
     * @since   1.0.0
     * @since   5.0.0  Decrypts encrypted secrets, supports legacy plain text
     */
    public function get_secret( $user ) {

        $stored = get_user_meta( $user->ID, 'authentication_secret', true );

        if ( empty( $stored ) ) return false;

        // Try to decrypt (new encrypted format).
        $decrypted = $this->decrypt( $stored );

        if ( $decrypted !== false && ! empty( $decrypted ) ) {
            return $decrypted;
        }

        // Fallback: legacy unencrypted secret. Re-encrypt it.
        if ( preg_match( '/^[A-Z2-7]+=*$/', $stored ) ) {
            update_user_meta( $user->ID, 'authentication_secret', $this->encrypt( $stored ) );
            return $stored;
        }

        return false;

    }

    /**
     * Get code.
     *
     * @param mixed $data
     *
     * @since   1.0.0
     */
    public function get_code( $data ) {

        // Check for authentication code.
        if( ! isset( $data['authentication_code'] ) ) return false;

        // Check if empty.
        if( empty( $data['authentication_code'] ) ) return false;

        // Return.
        return sanitize_text_field( $data['authentication_code'] );

    }

    /**
     * Confirm.
     *
     * @param   string  $key
     * @param   string  $code
     *
     * @since   1.0.0
     * @since   5.0.0  Generates backup codes on confirmation
     */
    public function confirm( $key, $code ) {

        // Get key.
        $parsed = $this->get_key( $key );

        // Verify key is valid (with expiration check).
        if ( ! $this->verify_key( $key ) ) return false;

        // Authenticate.
        if( ! $this->authenticate( $parsed['user_id'], $code ) ) return false;

        // Update user meta.
        update_user_meta( $parsed['user_id'], 'authentication_confirmed', true );

        // Delete setup key.
        delete_user_meta( $parsed['user_id'], 'authentication_setup' );

        // Generate backup codes.
        $backup_codes = $this->generate_backup_codes( $parsed['user_id'] );

        // Store temporarily for display (cleared after viewing).
        set_transient( 'kit_backup_codes_' . $parsed['user_id'], $backup_codes, 300 );

        // Return.
        return true;

    }

    /**
     * Disable.
     *
     * @param   object  $user
     *
     * @since   1.0.0
     * @since   5.0.0  Also clears backup codes
     */
    public function disable( $user ) {

        // Keys.
        $keys = [
            'authentication_setup',
            'authentication_secret',
            'authentication_confirmed',
            'authentication_backup_codes',
        ];

        // Loop.
        foreach( $keys as $key ) {

            // Delete.
            delete_user_meta( $user->ID, $key );

        }

    }

    /**
     * Requires 2FA.
     *
     * @param   object  $user
     *
     * @since   1.0.0
     */
    public function is_required( $user ) {

        // Check is user is admin.
        if( in_array( 'administrator', (array)$user->roles ) ) return true;

        // Check if 2FA required roles are set.
        if( empty( get_option( 'kit_2fa_users' ) ) ) return false;

        // Check if user has role.
        if( array_intersect( (array)$user->roles, (array)get_option( 'kit_2fa_users' ) ) ) return true;

        // Return false.
        return false;

    }

    /**
     * Check if enabled.
     *
     * @param   object  $user
     *
     * @since   1.0.0
     */
    public function is_enabled( $user ) {
        return ( empty( get_user_meta( $user->ID, 'authentication_confirmed', true ) ) ) ? false : true;
    }

}
