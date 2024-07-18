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
        add_action( 'upgrader_process_complete', [ $this, 'code_updates' ], 10, 2 );
        add_action( 'activated_plugin', [ $this, 'plugin_activate' ], 10, 2 );
        add_action( 'deactivated_plugin', [ $this, 'plugin_deactivate' ], 10, 2 );
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

        // Get referer.
        $referer = parse_url( $_POST['_wp_http_referer'] );
        parse_str( $referer['query'], $query );

        // Message.
        $message = "🛒 WooCommerce " . ucwords( $query['tab'] ) . " settings were just updated <" . site_url( $_POST['_wp_http_referer'] ) . "|here>."; 

        // Send.
        $this->slack->message( $message );

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

        // Set message.
        $message = "";

        // Check type.
        if( $data['type'] == 'plugin' ) {

            // Check action.
            if( $data['action'] == 'update' ) {

                // Check count.
                if( count( $data['plugins'] ) > 1 ) {

                    // Set message.
                    $message = "🔄 Multiple plugins were just updated.\n";

                    // Loop through plugins.
                    foreach( $data['plugins'] as $plugin ) {

                        // Add to message.
                        $message .= "\n>`" . $plugin . "`";

                    }

                } else {

                    // Set message.
                    $message = "🔄 A plugin was just updated: `" . $data['plugins'][0] . "`.";

                }

            } elseif( $data['action'] == 'install' ) {

                // Set message.
                $message = "📦 A plugin was just installed: `" . $_POST['slug'] . "`.";

            }

        } elseif( $data['type'] == 'theme' ) {

            // Check action.
            if( $data['action'] == 'update' ) {

                // Check themes.
                if( count( $data['themes'] ) > 1 ) {

                    // Set message.
                    $message = "🔄 Multiple themes were just updated.\n";

                    // Loop through themes.
                    foreach( $data['themes'] as $theme ) {

                        // Add to message.
                        $message .= "\n>`" . $theme . "`";

                    }

                } else {

                    // Set message.
                    $message = "🔄 A theme was just updated: `" . $data['themes'][0] . "`.";

                }

            } elseif( $data['action'] == 'install' ) {

                // Set message.
                $message = "📦 A theme was just installed: `" . $_POST['slug'] . '`.';

            }

        }

        // Check for a message.
        if( empty( $message ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Plugin Activate.
     * 
     * @since   1.0.0
     * 
     * @param   string  $plugin
     * @return  void
     */
    public function plugin_activate( $plugin ) {

        // Set message.
        $message = "🔌 A plugin was just ✅ activated: `" . $plugin . "`.";

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Plugin Deactivate.
     * 
     * @since   1.0.0
     * 
     * @param   string  $plugin
     * @return  void
     */
    public function plugin_deactivate( $plugin ) {

        // Set message.
        $message = "🔌 A plugin was just 🔻 deactivated: `" . $plugin . "`.";

        // Send.
        $this->slack->message( $message );

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

        // Set message.
        $message = "🎨 The theme was just changed to `" . $stylesheet . "`.";

        // Send.
        $this->slack->message( $message );

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

        // Check if user is admin.
        if( ! in_array( 'administrator', $user->roles ) ) return;

        // Set message.
        $message = "👨‍💻 An admin user just logged into the site at " . site_url() . ".\n\n>User: `" . $user_login . "`\n>IP: `" . $_SERVER['REMOTE_ADDR'] . "`\n>User Agent: `" . $_SERVER['HTTP_USER_AGENT'] . "`";

        // Send.
        $this->slack->message( $message );

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