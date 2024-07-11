<?php
/**
 * Lockdown log.
 * 
 * Log all lockdown attempts.
 * 
 * @since   2.0.0
 */
namespace BuiltMightyKit\Security;
class builtLockdownLog {

    /**
     * Construct.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // Log.
        add_action( 'wp_login_failed', [ $this, 'failed_login' ] );

    }

    /**
     * Failed Login.
     * 
     * @since   2.0.0
     */
    public function failed_login() {

        // Get user ID from login.
        $user = get_user_by( 'login', $_POST['log'] );

        // Set data.
        $data = [
            'ip'        => (string)$_SERVER['REMOTE_ADDR'],
            'user'      => (int)$user->ID,
            'agent'     => (string)$_SERVER['HTTP_USER_AGENT'],
            'type'      => (string)'login',
            'status'    => (string)'failed'
        ];

        // Log.
        $this->log( $data );

    }

    /**
     * Log.
     * 
     * @since   2.0.0
     */
    public function log( $data ) {

        // Global.
        global $wpdb;

        // Insert.
        $wpdb->insert( $wpdb->prefix . 'built_lockdown_log', [
            'ip'            => $data['ip'],
            'user_id'       => $data['user'],
            'user_agent'    => $data['agent'],
            'type'          => $data['type'],
            'status'        => $data['status'],
            'date'          => date( 'Y-m-d H:i:s' )
        ] );

    }


}