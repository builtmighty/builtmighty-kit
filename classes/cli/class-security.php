<?php
/**
 * CLI block.
 * 
 * Create CLI commands for protection.
 * 
 * @package Built Mighty Protection
 * @since   1.0.0
 */
namespace BuiltMightyKit\CLI;
class builtSecurity {

    /**
     * Get Secret.
     * 
     * Get the 2FA authentication code.
     * 
     * wp kit security get_secret --user=username
     * 
     * @since   2.0.0
     */
    public function get_secret( $args, $assoc_args ) {

        // Get current user by username.
        $user = get_user_by( 'login', $assoc_args['user'] );

        // Check for valid user.
        if( ! $user ) {

            // Error.
            \WP_CLI::error( 'User not found.' );

        }

        // Get secret.
        $secret = get_user_meta( $user->ID, 'google_authenticator_secret', true );

        // Check for secret.
        if( ! $secret ) {

            // Get new Google Authenticator.
            $google = new GoogleAuthenticator();
            $secret = $google->generateSecret();

            // Save secret.
            update_user_meta( $user->ID, 'google_authenticator_secret', $secret );

        }
        
        // Output secret.
        \WP_CLI::line( 'Authenticator Code: ' . $secret );

    }

    /**
     * Enable 2FA.
     * 
     * Enable 2FA for a user.
     * 
     * wp kit security setup --user=username --code=value
     * 
     * @since   2.0.0
     */
    public function setup( $args, $assoc_args ) {

        // Get current user by username.
        $user = get_user_by( 'login', $assoc_args['user'] );

        // Check for valid user.
        if( ! $user ) {

            // Error.
            \WP_CLI::error( 'User not found.' );

        }

        // Get secret.
        $secret = get_user_meta( $user->ID, 'google_authenticator_secret', true );

        // Check for secret.
        if( ! $secret ) {

            // Error.
            \WP_CLI::error( 'Secret not found. Please generate a secret first by running wp kit security get_secret --user=username.' );

        }

        // Get code.
        $code = $assoc_args['code'];

        // Check for code.
        if( isset( $code ) ) {

            // Auth.
            $auth = new \BuiltMightyKit\Security\builtAuth();

            // Authenticate.
            if( $auth->authenticate( $user->ID, $code ) ) {

                // Update user meta.
                update_user_meta( $user->ID, 'google_authenticator_confirmed', true );

                // Success.
                \WP_CLI::success( '2FA successfully enabled for ' . $assoc_args['user'] . '.' );

            } else {

                // Error.
                \WP_CLI::error( 'Code is invalid.' );

            }

        } else {

            // Error.
            \WP_CLI::error( 'Please provide a code. Use --code=value' );

        }

    }

    /** 
     * Reset.
     * 
     * Reset 2FA for a user.
     * 
     * wp kit security reset --user=username
     * 
     * @since   2.0.0
     */
    public function reset( $args, $assoc_args ) {

        // Get current user by username.
        $user = get_user_by( 'login', $assoc_args['user'] );

        // Check for valid user.
        if( ! $user ) {

            // Error.
            \WP_CLI::error( 'User not found.' );

        }

        // Reset.
        delete_user_meta( $user->ID, 'google_authenticator_secret' );
        delete_user_meta( $user->ID, 'google_authenticator_confirmed' );

        // Success.
        \WP_CLI::success( '2FA successfully reset for ' . $assoc_args['user'] . '.' );

    }

    /**
     * Approve IP.
     * 
     * wp kit security approve --ip=value --user=username
     * 
     * @since   2.0.0
     */
    public function approve( $args, $assoc_args ) {

        // Check for IP.
        if( ! isset( $assoc_args['ip'] ) ) {

            // Error.
            \WP_CLI::error( 'Please provide an IP address. Use --ip=value' );

        }

        // Check for user.
        if( ! isset( $assoc_args['user'] ) ) {

            // Error.
            \WP_CLI::error( 'Please provide a username. Use --user=value' );

        }

        // Get user ID.
        $user = get_user_by( 'login', $assoc_args['user'] );

        // Check if we have a user.
        if( ! $user ) {

            // Error.
            \WP_CLI::error( 'User not found.' );

        }

        // Get IP.
        $ip = $assoc_args['ip'];

        // Get security.
        $security = new \BuiltMightyKit\Security\builtLockdown();
        $security->add_ip( $ip, $user->ID );

        // Success.
        \WP_CLI::success( 'IP added to approved for ' . $assoc_args['user'] . '.' );

    }

    /**
     * Get approved list.
     * 
     * Get the approved list.
     * 
     * wp kit security approved
     * 
     * @since   1.0.0
     */
    public function approved( $args, $assoc_args ) {

        // Global.
        global $wpdb;

        // Get results.
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT ip FROM {$wpdb->prefix}built_lockdown" ), ARRAY_A );

        // Check approved.
        if( ! empty( $results ) ) {

            // Output approved.
            \WP_CLI::line( 'Approved:' );
            foreach( $results as $result ) {
                \WP_CLI::line( '• ' . $result['ip'] );
            }

        } else {

            // No blocklist.
            \WP_CLI::line( 'Approved list is empty.' );

        }

    }

}