<?php
/**
 * Lockdown.
 * 
 * Locksdown the admin to allowed IPs.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
namespace BuiltMightyKit\Security;
class builtLockdown {

    /**
     * Construct.
     * 
     * Initialize the class.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // Check if enabled.
        if( ! defined( 'BUILT_LOCKDOWN' ) || ! BUILT_LOCKDOWN ) return;

        // Run on WP.
        add_action( 'admin_init', [ $this, 'lockdown' ] );

        // Add fields to backend user.
        add_action( 'edit_user_profile', [ $this, 'profile_fields' ] );
        add_action( 'show_user_profile', [ $this, 'profile_fields' ] );
        add_action( 'personal_options_update', [ $this, 'save_profile_fields' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_profile_fields' ] );


    }

    /**
     * Lockdown.
     * 
     * Lockdown the admin to allowed IPs.
     * 
     * @since   2.0.0
     */
    public function lockdown() {

        // Check if doing AJAX or CRON.
        if( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) ) return;

        // Get user.
        $user = wp_get_current_user();

        // Check if user is admin.
        if( ! in_array( 'administrator', (array) $user->roles ) ) return;

        // Check if action is set.
        if( isset( $_GET['action'] ) && $_GET['action'] === 'builtmighty-approve-ip' && isset( $_GET['param'] ) ) {

            // Link approve.
            $this->approve_ip( $_GET['param'] );

        }

        // Check if IP is allowed.
        if( $this->check_ip( $this->get_ip() ) ) return;

        // Check if any admins have 2FA setup.
        if( ! $this->check_admins() ) return;

        // Data.
        $ip = $this->get_ip();

        // Check form.
        $data = $this->check_form( $_POST );

        // Start output buffering.
        ob_start();
 
        // Load the lockdown template.
        include BUILT_PATH . 'views/security/lockdown.php';

        // Output the buffer.
        echo ob_get_clean();

        // Exit.
        exit;
        
    }

    /**
     * Profile fields.
     * 
     * Add fields to the user profile.
     * 
     * @since   2.0.0
     */
    public function profile_fields( $user ) {

        // Check if user is admin.
        if( ! in_array( 'administrator', (array) $user->roles ) ) return;

        // Start output buffering.
        ob_start();

        // Get IPs.
        $ips = $this->get_ips( $user->ID );

        // Load the 2FA template.
        include BUILT_PATH . 'views/security/lockdown-user.php';

        // Output the buffer.
        echo ob_get_clean();

    }

    /** 
     * Save profile fields.
     * 
     * Save the profile fields.
     * 
     * @since   2.0.0
     */
    public function save_profile_fields( $user_id ) {

        // Check nonce.
        if( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) return;

        // Check if user is admin.
        if( ! in_array( 'administrator', (array) get_userdata( $user_id )->roles ) ) return;

        // Check if remove/add are set.
        if( isset( $_POST['remove_ip'] ) ) {

            // Sanitize.
            $id = absint( $_POST['remove_ip'] );

            // Global.
            global $wpdb;

            // Delete.
            $wpdb->delete( $wpdb->prefix . 'built_lockdown', [ 'id' => $id ] );

        } elseif( isset( $_POST['add_ip'] ) && ! empty( $_POST['user_ip'] ) ) {

            // Sanitize.
            $ip = sanitize_text_field( $_POST['user_ip'] );

            // Add IP.
            $this->add_ip( $ip, $user_id );

        }

    }

    /**
     * Check for allowed IPs.
     * 
     * Check if the user's IP is allowed.
     * 
     * @since   2.0.0
     */
    public function check_ip( $ip ) {

        // Global.
        global $wpdb;

        // Get results.
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}built_lockdown WHERE ip = %s", $ip ), ARRAY_A );

        // Check if results.
        if( empty( $results ) ) return false;

        // Return true.
        return true;

    }

    /**
     * Get IPs.
     * 
     * Get the user's allowed IPs.
     * 
     * @since   2.0.0
     */
    public function get_ips( $user_id ) {

        // Global.
        global $wpdb;

        // Get results.
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}built_lockdown WHERE user_id = %d", $user_id ), ARRAY_A );

        // Check if results.
        if( empty( $results ) ) return [];

        // Set IPs.
        $ips = [];

        // Loop through results.
        foreach( $results as $result ) {

            // Add IP.
            $ips[$result['id']] = $result['ip'];

        }

        // Return IPs.
        return $ips;

    }

    /**
     * Check form.
     * 
     * Check the form submission.
     * 
     * @param   array       $post       The POST data.
     * 
     * @since   2.0.0
     */
    public function check_form( $post ) {

        // Check for user ID.
        if( ! isset( $post['user_id'] ) ) return;

        // Check for IP.
        if( ! isset( $post['user_ip'] ) ) return;

        // Set data.
        $data = [
            'status'    => 'success',
            'message'   => '',
            'redirect'  => false,
        ];
        
        // Sanitize.
        $post = array_map( 'sanitize_text_field', $post );

        // Check for authenticator submission.
        if( isset( $post['google_authenticator_code'] ) ) {

            // Get auth.
            $auth = new \BuiltMightyKit\Security\builtAuth();

            // Authenticate.
            if( $auth->authenticate( $post['user_id'], $post['google_authenticator_code'] ) ) {

                // Add IP to approved.
                $added = $this->add_ip( $post['user_ip'], $post['user_id'] );

                // Check if IP was added.
                if( $added ) {

                    // Redirect.
                    wp_redirect( admin_url() );
                    exit;

                } else {

                    // Set data.
                    $data['status'] = 'error';
                    $data['message'] = 'There was an error adding your IP to the approved list.';

                }

            } else {

                // Set data.
                $data['status'] = 'error';
                $data['message'] = 'Invalid authentication code.';

            }

        } elseif( isset( $post['google_authenticator_request'] ) ) {

            // Get admins.
            $admins = get_users( [ 'role' => 'administrator' ] );

            // Set sent.
            $sent = false;
            
            // Loop through admins.
            foreach( $admins as $admin ) {

                // Check if 2FA is setup.
                if( get_user_meta( $admin->ID, 'google_authenticator_confirmed', true ) ) {

                    // Create a unique token.
                    $token = wp_generate_password( 32, false );

                    // Add token to post.
                    $post['token'] = $token;

                    // Send request.
                    $this->send_request( $admin->ID, $post );

                    // Set data.
                    $data['status'] = 'success';
                    $data['message'] = 'Request sent to an admin.';

                    // Set meta.
                    update_user_meta( $post['user_id'], 'google_authenticator_request', $token );

                    // Set sent.
                    $sent = true;

                    // Break.
                    break;

                }

            }

            // Check if sent.
            if( ! $sent ) {

                // Update data.
                $data['status']     = 'error';
                $data['message']    = 'Please use the WP CLI to approve your IP.';

            }

        }

        // Return.
        return $data;

    }

    /**
     * Approve IP.
     * 
     * Approve the user's IP.
     * 
     * @param   string      $param      The parameter.
     * 
     * @since   2.0.0
     */
    public function approve_ip( $data ) {

        // Check for data.
        if( ! isset( $_GET['param'] ) ) return;

        // Get data.
        $data = json_decode( base64_decode( $_GET['param'] ), true );

        // Check for data.
        if( empty( $data['user_id'] ) || empty( $data['user_ip'] ) || empty( $data['token'] ) ) return;

        // Check if user exists.
        $user = get_user_by( 'ID', $data['user_id'] );

        // Check if user exists.
        if( ! $user ) return;

        // Get token.
        $token = get_user_meta( $data['user_id'], 'google_authenticator_request', true );

        // Check if token matches.
        if( $token !== $data['token'] ) return;

        // Add IP to approved.
        $added = $this->add_ip( $data['user_ip'], $data['user_id'] );

        // Check if IP was added.
        if( $added ) {

            // Delete token.
            delete_user_meta( $data['user_id'], 'google_authenticator_request' );

            // Redirect.
            wp_redirect( admin_url() );
            exit;

        }

    }

    /**
     * Add IP.
     * 
     * Add the user's IP to the allowed list.
     * 
     * @param   string      $ip     The IP address.
     *  
     * @since   2.0.0
     */
    public function add_ip( $ip, $user_id ) {

        // Global.
        global $wpdb;

        // Insert.
        $wpdb->insert( $wpdb->prefix . 'built_lockdown', [ 'ip' => $ip, 'user_id' => $user_id ] );

        // Check for error.
        if( $wpdb->last_error ) return false;

        // Return true.
        return true;

    }

    /**
     * Check admins.
     * 
     * Check if any admins have 2FA setup.
     * 
     * @since   2.0.0
     */
    public function check_admins() {

        // Get admins.
        $admins = get_users( [ 'role' => 'administrator' ] );

        // Loop through admins.
        foreach( $admins as $admin ) {

            // Check if 2FA is setup.
            if( get_user_meta( $admin->ID, 'google_authenticator_confirmed', true ) ) return true;

        }

        // Return false.
        return false;

    }

    /**
     * Get IP.
     * 
     * Get the user's IP address.
     * 
     * @since   2.0.0
     */
    public function get_ip() {

        // Check if WC_Geolocation exists.
        if( class_exists( 'WC_Geolocation' ) ) return \WC_Geolocation::get_ip_address();

        // Check for Cloudflare.
        if( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) return $_SERVER['HTTP_CF_CONNECTING_IP'];

        // Check for forwarded.
        if( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) return $_SERVER['HTTP_X_FORWARDED_FOR'];

        // Return remote address.
        return $_SERVER['REMOTE_ADDR'];

    }

    /**
     * Send request.
     * 
     * Send a request to another admin.
     * 
     * @param   int         $user_id    The user ID.
     * @param   array       $post       The POST data.
     * 
     * @since   2.0.0
     */
    public function send_request( $user_id, $post ) {

        // Get admin.
        $admin = get_user_by( 'ID', $user_id );
        $user  = get_user_by( 'ID', $post['user_id'] );

        // Get email.
        $email = $admin->user_email;

        // Create data.
        $param = base64_encode( json_encode( [
            'user_id'   => $post['user_id'],
            'user_ip'   => $post['user_ip'],
            'token'     => $post['token'],
        ] ) );

        // Get subject.
        $subject = 'IP Approval Request';

        // Get message.
        $message = 'Hello ' . $admin->display_name . ',<br /><br />';
        $message .= 'User ' . $user->display_name . ' has requested approval for their IP address. Please click the link below to approve.<br /><br />';
        $message .= 'IP Address: ' . $post['user_ip'] . '<br /><br />';
        $message .= '<a href="' . admin_url( 'admin.php?action=builtmighty-approve-ip&param=' . $param ) . '" class="button button-primary">Approve IP</a><br /><br />';
        $message .= 'Thank you,<br />';
        $message .= get_bloginfo( 'name' );

        // Set headers.
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo( 'admin_email' ) . '>',
        ];

        // Send email.
        wp_mail( $email, $subject, $message, $headers );

    }

}