<?php
/**
 * CLI Security.
 * 
 * Create CLI commands for the Built Mighty Kit.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
namespace BuiltMightyKit\CLI;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
class builtSecurity {

    /**
     * Get Secret.
     * 
     * Get the 2FA authentication code.
     * 
     * wp kit security get_secret --username=username
     * 
     * @since   2.0.0
     */
    public function get_secret( $args, $assoc_args ) {

        // Check if user is set.
        if( ! isset( $assoc_args['username'] ) ) {

            // Error.
            \WP_CLI::error( 'Please provide a username. Use --username=value' );

        }
        
        // Get current user by username.
        $user = get_user_by( 'login', $assoc_args['username'] );

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
        \WP_CLI::success( 'Authenticator Code: ' . $secret );

    }

    /**
     * Enable 2FA.
     * 
     * Enable 2FA for a user.
     * 
     * wp kit security setup --username=username --code=value
     * 
     * @since   2.0.0
     */
    public function setup( $args, $assoc_args ) {

        // Check if user is set.
        if( ! isset( $assoc_args['username'] ) ) {

            // Error.
            \WP_CLI::error( 'Please provide a username. Use --username=value' );

        }

        // Get current user by username.
        $user = get_user_by( 'login', $assoc_args['username'] );

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
                \WP_CLI::success( '2FA successfully enabled for ' . $assoc_args['username'] . '.' );

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
     * wp kit security reset --username=username
     * 
     * @since   2.0.0
     */
    public function reset( $args, $assoc_args ) {

        // Get current user by username.
        $user = get_user_by( 'login', $assoc_args['username'] );

        // Check for valid user.
        if( ! $user ) {

            // Error.
            \WP_CLI::error( 'User not found.' );

        }

        // Reset.
        delete_user_meta( $user->ID, 'google_authenticator_secret' );
        delete_user_meta( $user->ID, 'google_authenticator_confirmed' );

        // Success.
        \WP_CLI::success( '2FA successfully reset for ' . $assoc_args['username'] . '.' );

    }

    /**
     * Approve IP.
     * 
     * wp kit security approve --username=username --ip=value 
     * 
     * @since   2.0.0
     */
    public function approve( $args, $assoc_args ) {

        // Check for user.
        if( ! isset( $assoc_args['username'] ) ) {

            // Error.
            \WP_CLI::error( 'Please provide a username. Use --username=value' );

        }

        // Check for IP.
        if( ! isset( $assoc_args['ip'] ) ) {

            // Error.
            \WP_CLI::error( 'Please provide an IP address. Use --ip=value' );

        }

        // Get user ID.
        $user = get_user_by( 'login', $assoc_args['username'] );

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
        \WP_CLI::success( 'IP added to approved for ' . $assoc_args['username'] . '.' );

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
        $results = $wpdb->get_results( "SELECT user_id, ip FROM {$wpdb->prefix}built_lockdown", ARRAY_A );

        // Check approved.
        if( ! empty( $results ) ) {

            // Output approved.
            \WP_CLI::line( 'Approved:' );
            foreach( $results as $result ) {

                // Get user by ID.
                $user = get_user_by( 'ID', $result['user_id'] );

                // Output.
                \WP_CLI::line( '• ' . $user->user_login . ' - ' . $result['ip'] );

            }

        } else {

            // No blocklist.
            \WP_CLI::line( 'Approved list is empty.' );

        }

    }

    /**
     * Remove.
     * 
     * Remove from approved list.
     * 
     * wp kit security remove --ip=value
     * 
     * @since   1.0.0
     */
    public function remove( $args, $assoc_args ) {

        // Check for IP.
        if( ! isset( $assoc_args['ip'] ) ) {

            // Error.
            \WP_CLI::error( 'Please provide an IP address to remove. Use --ip=value' );

        }

        // Global.
        global $wpdb;

        // Delete.
        $wpdb->delete( $wpdb->prefix . 'built_lockdown', [ 'ip' => $assoc_args['ip'] ] );

        // Success.
        \WP_CLI::success( $assoc_args['ip'] . ' removed from approved list.' );

    }

}