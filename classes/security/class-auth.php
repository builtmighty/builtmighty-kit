<?php
/**
 * Authenticate 2FA.
 * 
 * Run authentication against 2FA codes.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
namespace BuiltMightyKit\Security;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
class builtAuth {

    /**
     * Authenticate.
     * 
     * Authenticate user.
     * 
     * @since   2.0.0
     */
    public function authenticate( $user_id, $code ) {

        // Get secret.
        $secret = get_user_meta( $user_id, 'google_authenticator_secret', true );

        // Check for code.
        if( ! isset( $code ) || empty( $code ) ) return false;

        // Get Google Authenticator.
        $google = new GoogleAuthenticator();

        // Sanitize the code.
        $code = sanitize_text_field( $code );

        // Check code.
        if( ! $google->checkCode( $secret, $code ) ) return false;

        // Return true.
        return true;

    }

}