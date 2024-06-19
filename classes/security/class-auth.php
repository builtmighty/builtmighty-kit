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

        // Check if setup.
        if( empty( get_user_meta( $user_id, 'google_authenticator_confirmed', true ) ) ) return true;

        // Check for secret.
        if( empty( get_user_meta( $user_id, 'google_authenticator_secret', true ) ) ) return true;

        // Get secret.
        $secret = get_user_meta( $user_id, 'google_authenticator_secret', true );

        // Check for code.
        if( isset( $code ) ) {

            // Get Google Authenticator.
            $google = new GoogleAuthenticator();

            // Sanitize the code.
            $code = sanitize_text_field( $code );

            // Check code.
            if( $google->checkCode( $secret, $code ) ) {

                // Return true.
                return true;

            } else {

                // Return false.
                return false;

            }

        }

        // Return false.
        return false;

    }

}