<?php
/**
 * 2FA.
 * 
 * Requires 2FA for admins.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
use Endroid\QrCode\QrCode;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
class built2FA {

    /**
     * Construct.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // Fields.
        add_action( 'show_user_profile', [ $this, 'authenticator_field' ] );
        add_action( 'edit_user_profile', [ $this, 'authenticator_field' ] );
        add_action( 'personal_options_update', [ $this, 'save_authenticator_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_authenticator_field' ] );
        
    }

    /**
     * Authenticator field.
     * 
     * Add authenticator field to user profile.
     * 
     * @since   2.0.0
     */
    public function authenticator_field( $user ) { ?>

        <h3>Google Authenticator</h3>
        <table class="form-table">
            <tr>
                <th><label for="google_authenticator_secret">Secret Key</label></th>
                <td>
                    <input type="text" name="google_authenticator_secret" id="google_authenticator_secret" value="<?php echo esc_attr(get_the_author_meta('google_authenticator_secret', $user->ID)); ?>" class="regular-text" /><br />
                    <span class="description">This is your Google Authenticator secret key.</span>
                </td>
            </tr>
        </table><?php

    }
    
    /**
     * Save Authenticator field.
     * 
     * Save authenticator field to user profile.
     * 
     * @since   2.0.0
     */
    public function save_authenticator_field( $user_id ) {

        // Check if current user can edit user.
        if( ! current_user_can( 'edit_user', $user_id ) ) return;

        // Update user meta.
        update_user_meta( $user_id, 'google_authenticator_secret', $_POST['google_authenticator_secret'] );

    }

}