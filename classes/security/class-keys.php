<?php
/**
 * Keys.
 * 
 * Encrypts important keys.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtKeys {

    /**
     * Variables.
     * 
     * @since   1.0.0
     */
    private $secret;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Set secret.
        $this->secret = AUTH_KEY;

    }

    /**
     * Encrypt key.
     * 
     * @since   1.0.0
     */
    public function encrypt( $key ) {

        // Set IV.
        $iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );

        // Encrypt.
        $encrypted = openssl_encrypt( $key, 'aes-256-cbc', $this->secret, 0, $iv );

        // Return.
        return base64_encode( $encrypted . '::' . $iv );

    }

    /**
     * Decrypt.
     * 
     * @since   1.0.0
     */
    public function decrypt( $encrypted_key ) {

        // List.
        list( $encrypted_data, $iv ) = explode( '::', base64_decode( $encrypted_key ), 2 );

        // Return.
        return openssl_decrypt( $encrypted_data, 'aes-256-cbc', $this->secret, 0, $iv );

    }

}
