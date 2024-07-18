<?php
/**
 * Notifications.
 * 
 * Send Slack notifications about certain touchy operations.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Security;
class builtNotifications {

    /**
     * Slack.
     * 
     * @since   1.0.0
     */
    private $slack;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check if Slack is connected.
        if( empty( get_option( 'slack-channel' ) ) ) return;

        // Initiate Slack.
        $this->slack = new \BuiltMightyKit\Plugins\builtSlack();

        // Actions.
        add_action( 'woocommerce_update_options', [ $this, 'woocommerce' ] );
        add_action( 'upgrader_process_complete', [ $this, 'code_updates' ] );
        add_action( 'switch_theme', [ $this, 'theme' ] );
        add_action( 'user_register', [ $this, 'admin_create' ], 10, 2 );
        add_action( 'delete_user', [ $this, 'admin_delete' ], 10, 3 );
        add_action( 'set_user_role', [ $this, 'admin_role' ], 10, 3 );
        add_action( 'profile_update', [ $this, 'admin_password' ], 10, 3 );
        add_action( 'profile_update', [ $this, 'admin_email' ], 10, 3 );
        add_action( 'wp_login', [ $this, 'admin_login' ], 10, 2 );

    }

    /**
     * WooCommerce.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function woocommerce( $options ) {

        // Check if settings were saved.
        if( ! isset( $_POST['save'] ) ) return;

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] OPTIONS: ' . print_r( $options, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    /**
     * Plugin/theme updates and installations.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function code_updates( $upgrader, $data ) {

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] DATA: ' . print_r( $data, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    /**
     * Theme.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function theme( $stylesheet ) {

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] STYLESHEET: ' . print_r( $stylesheet, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    /**
     * Admin Create.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_create( $user_id, $data ) {

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] USER ID: ' . print_r( $user_id, true ) );
        error_log( '[' . __FUNCTION__ . '] DATA: ' . print_r( $data, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    /**
     * Admin Delete.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_delete( $user_id, $reassign, $user ) {

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] USER ID: ' . print_r( $user_id, true ) );
        error_log( '[' . __FUNCTION__ . '] USER: ' . print_r( $user, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    /**
     * Admin Role.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_role( $user_id, $role, $old_roles ) {

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] USER ID: ' . print_r( $user_id, true ) );
        error_log( '[' . __FUNCTION__ . '] ROLE: ' . print_r( $role, true ) );
        error_log( '[' . __FUNCTION__ . '] OLD ROLES: ' . print_r( $old_roles, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    /**
     * Admin Password.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_password( $user_id, $old_data, $data ) {

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] USER ID: ' . print_r( $user_id, true ) );
        error_log( '[' . __FUNCTION__ . '] OLD DATA: ' . print_r( $old_data, true ) );
        error_log( '[' . __FUNCTION__ . '] DATA: ' . print_r( $data, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    /**
     * Admin Email.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_email( $user_id, $old_data, $data ) {

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] USER ID: ' . print_r( $user_id, true ) );
        error_log( '[' . __FUNCTION__ . '] OLD DATA: ' . print_r( $old_data, true ) );
        error_log( '[' . __FUNCTION__ . '] DATA: ' . print_r( $data, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    /**
     * Admin Login.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_login( $user_login, $user ) {

        error_log( '[' . __FUNCTION__ . '] Running.' );
        error_log( '[' . __FUNCTION__ . '] USER LOGIN: ' . print_r( $user_login, true ) );
        error_log( '[' . __FUNCTION__ . '] USER: ' . print_r( $user, true ) );
        error_log( '[' . __FUNCTION__ . '] POST: ' . print_r( $_POST, true ) );

        // Send.
        //$this->slack->message( $message );

    }

    // 'woocommerce'       => 'WooCommerce Settings',
    // 'plugin-install'    => 'Plugin Installation',
    // 'plugin-activate'   => 'Plugin Activation',
    // 'plugin-deactivate' => 'Plugin Deactivation',
    // 'theme-install'     => 'Theme Installation',
    // 'theme-activate'    => 'Theme Activation',
    // 'theme-deactivate'  => 'Theme Deactivation',
    // 'admin-create'      => 'Admin User Creation',
    // 'admin-delete'      => 'Admin User Deletion',
    // 'admin-role'        => 'Admin User Role Change',
    // 'admin-password'    => 'Admin User Password Change',
    // 'admin-email'       => 'Admin User Email Change',
    // 'admin-login'       => 'Admin User Login'

}