<?php
/**
 * Authentication.
 *
 * Two-Factor Authentication System.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Utility;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
class authentication {

    /**
     * Authenticate.
     * 
     * @since   1.0.0
     */
    public function authenticate( $user_id, $code ) {

        // Check for code.
        if( ! isset( $code ) || empty( $code ) ) return false;

        // Get user.
        $user = get_user_by( 'id', $user_id );

        // Check if App is enabled.
        if( ! $this->is_enabled( $user ) ) {

            // Get code.
            $user_code = get_user_meta( $user_id, 'email_code', true );

            // Check if code is set.
            if( empty( $user_code ) ) return false;

            // Check code.
            if( $user_code['code'] !== $code ) return false;

            // Check time.
            if( ( time() - $user_code['time'] ) > 300 ) return false;

            // Delete code.
            delete_user_meta( $user_id, 'email_code' );

            // Return true.
            return true;

        } else {

            // Get secret.
            $secret = get_user_meta( $user_id, 'authentication_secret', true );

            // Get Google Authenticator.
            $google_auth = new GoogleAuthenticator();

            // Sanitize code.
            $code = sanitize_text_field( $code );

            // Check the code.
            if( ! $google_auth->checkCode( $secret, $code ) ) return false;

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
        Hello <?php echo $user->display_name; ?>,<br><br>
        Two-Factor Authentication is required for your account on <?php echo get_bloginfo( 'name' ); ?>.<br><br>
        Please click the link below to set up Two-Factor Authentication:<br><br>
        <a href="<?php echo site_url( '/security?key=' . $key ); ?>">Setup Two-Factor Authentication</a><br><br>
        If you did not trigger this email, please ignore it.<br><br>
        Thank you,<br>
        <?php echo get_bloginfo( 'name' );

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
     */
    public function send_code( $user ) {

        // Get code.
        $code = $this->generate_code( $user );

        // Set subject. 
        $subject = 'Authentication Code | ' . get_bloginfo( 'name' );

        // Start output buffering.
        ob_start();

        // Compose. ?>
        Hello <?php echo $user->display_name; ?>,<br><br>
        Your authentication code is:<br><br>
        <code style="font-size:20px;text-align:center"><?php echo $code['code']; ?></code><br><br>
        This code is valid for 5 minutes.<br><br>
        If you did not trigger this email, please ignore it.<br><br>
        Thank you,<br>
        <?php echo get_bloginfo( 'name' );

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
     * Create setup key.
     * 
     * @param   object  $user
     * @param   boolean $encode
     * 
     * @since   1.0.0
     */
    public function generate_key( $user, $encode = false ) {

        // Generate key.
        $key = wp_generate_password( 32, false );

        // Save to user meta.
        update_user_meta( $user->ID, 'authentication_setup', $key );

        // Return.
        return ( ! $encode ) ? $key : base64_encode( $user->ID . ':' . $key );

    }

    /**
     * Verify setup key.
     * 
     * @param   int     $user_id
     * @param   string  $key
     * 
     * @since   1.0.0
     */
    public function verify_key( $key ) {

        // Get key.
        $key = $this->get_key( $key );

        // Check key.
        if( $key['key'] !== get_user_meta( $key['user_id'], 'authentication_setup', true ) ) return false;

        // Return true.
        return true;

    }

    /**
     * Generate secret.
     * 
     * @param   object  $user
     * 
     * @since   1.0.0
     */
    public function generate_secret( $user ) {

        // Check if set.
        if( ! empty( get_user_meta( $user->ID, 'authentication_secret', true ) ) ) return get_user_meta( $user->ID, 'authentication_secret', true );

        // Google Authentication.
        $google_auth = new GoogleAuthenticator();

        // Create secret.
        $secret = $google_auth->generateSecret();

        // Save secret.
        update_user_meta( $user->ID, 'authentication_secret', $secret );

        // Return.
        return $secret;
    
    }

    /** 
     * Generate code.
     * 
     * Generate an authentication code for email.
     * 
     * @param   object  $user
     * 
     * @since   1.0.0
     */
    public function generate_code( $user ) {

        // Generate code.
        $code = [
            'code'  => str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT ),
            'time'  => time(),
        ];

        // Set.
        update_user_meta( $user->ID, 'email_code', $code );

        // Return.
        return $code;

    }

    /**
     * Check code.
     * 
     * @param   object  $user
     * @param   string  $code
     * 
     * @since   1.0.0
     */
    public function check_code( $user, $code ) {

        

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
            'key'       => $key[1]
        ];

    }

    /**
     * Get secret.
     * 
     * @param   object  $user
     * 
     * @since   1.0.0
     */
    public function get_secret( $user ) {
        return ( ! empty( get_user_meta( $user->ID, 'authentication_secret', true ) ) ) ? get_user_meta( $user->ID, 'authentication_secret', true ) : false;
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
     */
    public function confirm( $key, $code ) {

        // Get key.
        $key = $this->get_key( $key );

        // Confirm key is valid.
        if( $key['key'] !== get_user_meta( $key['user_id'], 'authentication_setup', true ) ) return false;

        // Authenticate.
        if( ! $this->authenticate( $key['user_id'], $code ) ) return false;

        // Update user meta.
        update_user_meta( $key['user_id'], 'authentication_confirmed', true );

        // Return.
        return true;

    }

    /**
     * Disable.
     * 
     * @param   object  $user
     * 
     * @since   1.0.0
     */
    public function disable( $user ) {

        // Keys.
        $keys = [ 'authentication_setup', 'authentication_secret', 'authentication_confirmed' ];

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
