<?php
/**
 * 2FA.
 * 
 * Requires 2FA for admins.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
class built2FA {

    /**
     * Construct.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // Show.
        add_action( 'show_user_profile', [ $this, 'authenticator_field' ] );
        add_action( 'edit_user_profile', [ $this, 'authenticator_field' ] );
        
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

}