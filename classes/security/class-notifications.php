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

        // Get current user.
        $user = wp_get_current_user();

        // Message.
        $message = "🛒 WooCommerce " . ucwords( $query['tab'] ) . " settings were just updated <" . site_url( $_POST['_wp_http_referer'] ) . "|here>.\n>User: `" . $user->user_login . "`\n>IP: `" . $this->get_ip() . "`"; 

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'woocommerce' ) ) return;

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

        // Get current user.
        $user = wp_get_current_user();

        // Check type.
        if( $data['type'] == 'plugin' ) {

            // Check action.
            if( $data['action'] == 'update' && $this->is_enabled( 'plugin-update' ) ) {

                // Check count.
                if( count( (array)$data['plugins'] ) > 1 ) {

                    // Set message.
                    $message = "🔄 Multiple plugins were just updated.\n>User: `" . $user->user_login . "`\n>IP: " . $this->get_ip() . "`\n";

                    // Loop through plugins.
                    foreach( (array)$data['plugins'] as $plugin ) {

                        // Add to message.
                        $message .= "\n>`" . $plugin . "`";

                    }

                } else {

                    // Set message.
                    $message = "🔄 A plugin was just updated: `" . $data['plugins'][0] . "`.\nUser: `" . $user->user_login . "`\n>IP: " . $this->get_ip() . "`\n";

                }

            } elseif( $data['action'] == 'install' && $this->is_enabled( 'plugin-install' ) ) {

                // Set message.
                $message = "📦 A plugin was just installed: `" . $_POST['slug'] . "`.\nUser: `" . $user->user_login . "`\n>IP: " . $this->get_ip() . "`\n";

            }

        } elseif( $data['type'] == 'theme' ) {

            // Check action.
            if( $data['action'] == 'update' && $this->is_enabled( 'theme-update' ) ) {

                // Check themes.
                if( count( (array)$data['themes'] ) > 1 ) {

                    // Set message.
                    $message = "🔄 Multiple themes were just updated.\nUser: `" . $user->user_login . "`\n>IP: " . $this->get_ip() . "`\n";

                    // Loop through themes.
                    foreach( (array)$data['themes'] as $theme ) {

                        // Add to message.
                        $message .= "\n>`" . $theme . "`";

                    }

                } else {

                    // Set message.
                    $message = "🔄 A theme was just updated: `" . $data['themes'][0] . "`.\nUser: `" . $user->user_login . "`\n>IP: " . $this->get_ip() . "`\n";

                }

            } elseif( $data['action'] == 'install' && $this->is_enabled( 'theme-install' ) ) {

                // Set message.
                $message = "📦 A theme was just installed: `" . $_POST['slug'] . "`.\nUser: `" . $user->user_login . "`\n>IP: " . $this->get_ip() . "`\n";

            }

        }

        // Check for a message.
        if( empty( $message ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Plugin update.
     * 
     * @since   1.0.0
     * 
     * @param   array   $data
     * @param   object  $user
     * @return  void
     */
    public function plugin_update( $data, $user ) {



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

        // Get current user.
        $user = wp_get_current_user();

        // Set message.
        $message = "🔌 A plugin was just ✅ activated: `" . $plugin . "`.\n>User: `" . $user->user_login . "`\n>IP: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'plugin-activate' ) ) return;

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

        // Get current user.
        $user = wp_get_current_user();

        // Set message.
        $message = "🔌 A plugin was just 🔻 deactivated: `" . $plugin . "`.\n>User: `" . $user->user_login . "`\n>IP: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'plugin-deactivate' ) ) return;

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

        // Get current user.
        $user = wp_get_current_user();

        // Set message.
        $message = "🎨 The theme was just changed to `" . $stylesheet . "`.\n>User: `" . $user->user_login . "`\n>IP: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'theme-change' ) ) return;

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
        $message = "👨‍💻 An admin user was just created.\n\n>User: `" . $data['user_login'] . "`\n>Email: `" . $data['user_email'] . "`\n>IP: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'admin-create' ) ) return;

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

        // Check if user is admin.
        if( ! in_array( 'administrator', (array)$user->roles ) ) return;

        // Get current user.
        $current = wp_get_current_user();

        // Set message.
        $message = "👨‍💻 An admin user was just deleted.\n\n>User: `" . $user->user_login . "`\n>Email: `" . $user->user_email . "`\n\nUser was deleted by...\n>User: `" . $current->user_login . "`\n>IP: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'admin-delete' ) ) return;

        // Send.
        $this->slack->message( $message );

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
            $message = "👨‍💻 An admin user was just demoted.\n\n>User: `" . $data['user_login'] . "`\n>Email: `" . $data['user_email'] . "`\n>IP: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-role' ) ) $this->slack->message( $message );

        }

        // Check if user went from non-admin to admin.
        if( ! in_array( 'administrator', (array)$old_data->roles ) && $data['role'] == 'administrator' ) {

            // Set message.
            $message = "👨‍💻 A user was just promoted to admin.\n\n>User: `" . $data['user_login'] . "`\n>Email: `" . $data['user_email'] . "`\n>IP: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-role' ) ) $this->slack->message( $message );

        }

        // Check if user email was changed.
        if( $old_data->user_email !== $data['user_email'] ) {

            // Set message.
            $message = "👨‍💻 An admin user just changed their email.\n\n>User: `" . $data['user_login'] . "`\n>Old Email: `" . $old_data->user_email . "`\n>New Email: `" . $data['user_email'] . "`\n>IP: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-email' ) ) $this->slack->message( $message );

        }

        // Check if the password was changed.
        if( $old_data->data->user_pass !== $data['user_pass'] ) {

            // Set message.
            $message = "👨‍💻 An admin user just changed their password.\n\n>User: `" . $data['user_login'] . "`\n>IP: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-password' ) ) $this->slack->message( $message );

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

        // Check if it's run.
        if( did_action( 'wp_login' ) >= 2 ) return;

        // Check if user is admin.
        if( ! in_array( 'administrator', $user->roles ) ) return;

        // Set message.
        $message = "👨‍💻 An admin user just logged into the site at " . site_url() . ".\n\n>User: `" . $user_login . "`\n>IP: `" . $this->get_ip() . "`\n>User Agent: `" . $_SERVER['HTTP_USER_AGENT'] . "`";

        // Check if daily summary.
        if( $this->is_summary( 'admin-login' ) ) $this->log( $message );

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'admin-login' ) ) return;

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

        // Get current user.
        $user = wp_get_current_user();

        // Check type.
        if( isset( $_POST['theme'] ) ) {

            // Set message.
            $message = "📝 A theme file was edited.\n\n>File: `" . $_POST['file'] . "`\n>User: `" . $user->user_login . "`\n>IP: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'theme-editor' ) ) $this->slack->message( $message );

        } elseif( isset( $_POST['plugin'] ) ) {

            // Set message.
            $message = "📝 A plugin file was edited.\n\n>File: `" . $_POST['file'] . "`\nUser: `" . $user->user_login . "`\n>IP: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'plugin-editor' ) ) $this->slack->message( $message );

        }

    }

    /** 
     * Check if enabled.
     * 
     * @since   1.0.0
     */
    public function is_enabled( $setting ) {

        // Get notification settings.
        $notifications = unserialize( get_option( 'slack-notifications' ) );

        // Check if setting is enabled.
        return ( ! in_array( $setting, (array)$notifications ) ) ? false : true;

    }

    /**
     * Check if summary.
     * 
     * @since   1.0.0
     */
    public function is_summary( $setting ) {

        // Get notification settings.
        $notifications = unserialize( get_option( 'slack-summary-notifications' ) );

        // Check if setting is enabled.
        return ( ! in_array( $setting, (array)$notifications ) ) ? false : true;

    }

    /** 
     * Get IP.
     * 
     * @since   1.0.0
     */
    public function get_ip() {

        // Check if WooCommerce is installed.
        if( class_exists( '\WC_Geolocation' ) ) {

            // Get IP.
            $ip = \WC_Geolocation::get_ip_address();

        } else {

            // Get IP, check for Cloudflare headers.
            $ip = ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];

        }

        // Return.
        return $ip;

    }

    /**
     * Create log.
     * 
     * 
     */
    public function log( $message ) {

        // Create log file in wp-content/uploads.
        $file = WP_CONTENT_DIR . '/uploads/builtmighty-slack-summary.log';

        // Set timezone.
        date_default_timezone_set( get_option( 'timezone_string' ) );

        // Add date/time to message.
        $message = "[" . date( 'Y-m-d g:i:s A' ) . "]\n" . $message;

        // Check if file exists.
        if( ! file_exists( $file ) ) {

            // Create file.
            file_put_contents( $file, $message );

        } else {

            // Append to file.
            file_put_contents( $file, "\n\n" . $message, FILE_APPEND );

        }

        // Get log.
        $log = file_get_contents( $file );

    }

}