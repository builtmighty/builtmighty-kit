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
        add_action( 'profile_update', [ $this, 'admin_update' ], 10, 3 );
        add_action( 'wp_login', [ $this, 'admin_login' ], 10, 2 );
        add_action( 'admin_init', [ $this, 'file_editor' ] );

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

        // Check role.
        if( $data['role'] !== 'administrator' ) return;

        // Set message.
        $message = "👨‍💻 An admin user was just created.\n\n>User: `" . $data['user_login'] . "`\n>Email: `" . $data['user_email'] . "`";

        // Send.
        $this->slack->message( $message );

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
     * Admin Update.
     * 
     * @since   1.0.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_update( $user_id, $old_data, $data ) {

        // Check role.
        if( ! in_array( 'administrator', (array)$old_data->roles ) && $data['role'] !== 'administrator' ) return;

        // Check if user went from admin to non-admin.
        if( in_array( 'administrator', (array)$old_data->roles ) && $data['role'] !== 'administrator' ) {

            // Set message.
            $message = "👨‍💻 An admin user was just demoted.\n\n>User: `" . $data['user_login'] . "`\n>Email: `" . $data['user_email'] . "`";

            // Send.
            $this->slack->message( $message );

        }

        // Check if user went from non-admin to admin.
        if( ! in_array( 'administrator', (array)$old_data->roles ) && $data['role'] == 'administrator' ) {

            // Set message.
            $message = "👨‍💻 A user was just promoted to admin.\n\n>User: `" . $data['user_login'] . "`\n>Email: `" . $data['user_email'] . "`";

            // Send.
            $this->slack->message( $message );

        }

        // Check if user email was changed.
        if( $old_data->user_email !== $data['user_email'] ) {

            // Set message.
            $message = "👨‍💻 An admin user just changed their email.\n\n>User: `" . $data['user_login'] . "`\n>Old Email: `" . $old_data->user_email . "`\n>New Email: `" . $data['user_email'] . "`";

            // Send.
            $this->slack->message( $message );

        }

        // Check if the password was changed.
        if( $old_data->data->user_pass !== $data['user_pass'] ) {

            // Set message.
            $message = "👨‍💻 An admin user just changed their password.\n\n>User: `" . $data['user_login'] . "`";

            // Send.
            $this->slack->message( $message );

        }

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

    /**
     * File editor.
     * 
     * @since   1.0.0
     */
    public function file_editor() {

        // Check for post.
        if( ! isset( $_POST ) || empty( $_POST ) ) return;

        // Check action.
        if( ! isset( $_POST['action'] ) && $_POST['action'] !== 'edit-theme-plugin-file' ) return;

        // Check for file.
        if( ! isset( $_POST['file'] ) ) return;

        // Set type.
        $type = ( isset( $_POST['theme'] ) ) ? 'theme' : 'plugin';

        // Set message.
        $message = "📝 A " . $type . " file was edited: `" . $_POST['file'] . "`";

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