<?php
/**
 * Notifications.
 *
 * System notifications for admins.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Private;
class notifications {

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
        $this->slack = new \BuiltMightyKit\Utility\slack();

        // Actions.
        add_action( 'updated_option', [ $this, 'woocommerce' ], 10, 3);
        add_action( 'upgrader_process_complete', [ $this, 'code_updates' ], 10, 2 );
        add_action( 'activated_plugin', [ $this, 'plugin_activate' ], 10, 2 );
        add_action( 'deactivated_plugin', [ $this, 'plugin_deactivate' ], 10, 2 );
        add_action( 'switch_theme', [ $this, 'theme' ] );
        add_action( 'user_register', [ $this, 'admin_create' ], 10, 2 );
        add_action( 'delete_user', [ $this, 'admin_delete' ], 10, 3 );
        add_action( 'profile_update', [ $this, 'admin_update' ], 10, 3 );
        add_action( 'wp_login', [ $this, 'admin_login' ], 10, 2 );
        add_action( 'admin_init', [ $this, 'file_editor' ] );

        // Check if daily summary is enabled.
        if( get_option( 'slack-summary' ) == 'enable' ) {

            // Schedule.
            add_action( 'wp_loaded', [ $this, 'schedule' ] );
            add_action( 'builtmighty_slack_summary', [ $this, 'sync' ] );

        }

    }

    /**
     * WooCommerce.
     * 
     * @since  2.2.0
     * 
     * @param mixed $name
     * @param mixed $old
     * @param mixed $new
     * @return void
     */
    public function woocommerce( $name, $old, $new ) {

        // Check if name contains woocommerce or wc.
        if( strpos( $name, 'woocommerce' ) === false && strpos( $name, 'wc' ) === false ) return;

        // Check if name contains transient.
        if( strpos( $name, 'transient' ) !== false ) return;

        // Check if name contains queue.
        if( strpos( $name, 'queue' ) !== false ) return;

        // Set message.
        $message = "🛒 WooCommerce settings were just updated.\n\n>*Name*: `" . $name . "`\n>*Settings*: ```\n" . print_r( $new, true ) . "\n```";

        // Check if daily summary.
        if( $this->is_summary( 'woocommerce' ) ) $this->log( $message );

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'woocommerce' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Plugin/theme updates and installations.
     * 
     * @since   2.2.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function code_updates( $upgrader, $data ) {

        // Get current user.
        $user = wp_get_current_user();

        // Check if set.
        if( ! isset( $data['type'] ) || ! isset( $data['action'] ) ) return;

        // Core update.
        $this->core_update( $data, $_POST, $user );

        // Plugin update.
        $this->plugin_update( $data, $user );

        // Plugin install.
        $this->plugin_install( $_POST, $user );
        
        // Manual plugin install.
        $this->plugin_manual_install( $data, $_POST, $user );

        // Theme update.
        $this->theme_update( $data, $user );

        // Theme install.
        $this->theme_install( $_POST, $user );

    }

    /**
     * Core update.
     * 
     * @since   2.2.0
     * 
     * @param   array   $data
     * @param   array   $post
     * @param   object  $user
     */
    public function core_update( $data, $post, $user ) {

        // Check type.
        if( ! isset( $data['type'] ) || $data['type'] !== 'core' ) return;

        // Check action.
        if( ! isset( $data['action'] ) || $data['action'] !== 'update' ) return;

        // Check if version is set.
        if( ! isset( $post['version'] ) ) return;

        // Set message.
        $message = "🔄 WordPress core was just updated to version `" . $post['version'] . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";
        
        // Check if summary.
        if( $this->is_summary( 'core-update' ) ) $this->log( $message );

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'core-update' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Plugin update.
     * 
     * @since   2.2.0
     * 
     * @param   array   $data
     * @param   object  $user
     * @return  void
     */
    public function plugin_update( $data, $user ) {

        // Check type.
        if( ! isset( $data['type'] ) || $data['type'] !== 'plugin' ) return;

        // Check action.
        if( ! isset( $data['action'] ) || $data['action'] !== 'update' ) return;

        // Check if bulk.
        if( $data['bulk'] ) {

            // Loop through plugins.
            foreach( $data['plugins'] as $plugin ) {

                // Set message.
                $message = "🔄 A plugin was just updated: `" . $plugin . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

                // Check if summary.
                if( $this->is_summary( 'plugin-update' ) ) $this->log( $message );

                // Check if setting is enabled.
                if( ! $this->is_enabled( 'plugin-update' ) ) return;

                // Send.
                $this->slack->message( $message );

            }

        } else {

            // Set message.
            $message = "🔄 A plugin was just updated: `" . $data['plugin'] . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Check if summary.
            if( $this->is_summary( 'plugin-update' ) ) $this->log( $message );

            // Check if setting is enabled.
            if( ! $this->is_enabled( 'plugin-update' ) ) return;

            // Send.
            $this->slack->message( $message );

        }

    }

    /**
     * Plugin install.
     * 
     * @since   2.2.0
     * 
     * @param   array   $data
     * @param   object  $user
     * @return  void
     */
    public function plugin_install( $post, $user ) {

        // Check.
        if( ! isset( $post['action'] ) || $post['action'] !== 'install-plugin' ) return;

        // Check if slug is set.
        if( ! isset( $post['slug'] ) ) return;

        // Set message.
        $message = "📦 A plugin was just installed: `" . $post['slug'] . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if summary.
        if( $this->is_summary( 'plugin-install' ) ) $this->log( $message );

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'plugin-install' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Plugin manual install.
     * 
     * @since   2.2.0
     * 
     * @param   array   $data
     * @param   array   $post
     * @param   object  $user
     * @return  void
     */
    public function plugin_manual_install( $data, $post, $user ) {

        // Check.
        if( ! isset( $data['type'] ) || $data['type'] !== 'plugin' ) return;

        // Check.
        if( ! isset( $data['action'] ) || $data['action'] !== 'install' ) return;

        // Check if manually installation is submitted.
        if( ! isset( $post['install-plugin-submit'] ) ) return;

        // Set message.
        $message = "📦 A plugin was just manually installed.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if summary.
        if( $this->is_summary( 'plugin-install' ) ) $this->log( $message );

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'plugin-install' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Theme update.
     * 
     * @since   2.2.0
     * 
     * @param   array   $data
     * @param   object  $user
     * @return  void
     */
    public function theme_update( $data, $user ) {

        // Check type.
        if( ! isset( $data['type'] ) || $data['type'] !== 'theme' ) return;

        // Check action.
        if( ! isset( $data['action'] ) || $data['action'] !== 'update' ) return;

        // Check if bulk is set.
        if( ! isset( $data['bulk'] ) ) return;

        // Check if bulk.
        if( $data['bulk'] ) {

            // Loop through themes.
            foreach( $data['themes'] as $theme ) {

                // Set message.
                $message = "🔄 A theme was just updated: `" . $theme . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

                // Check if summary.
                if( $this->is_summary( 'theme-update' ) ) $this->log( $message );

                // Check if setting is enabled.
                if( ! $this->is_enabled( 'theme-update' ) ) return;

                // Send.
                $this->slack->message( $message );

            }

        } else {

            // Set message.
            $message = "🔄 A theme was just updated: `" . $data['theme'] . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Check if summary.
            if( $this->is_summary( 'theme-update' ) ) $this->log( $message );

            // Check if setting is enabled.
            if( ! $this->is_enabled( 'theme-update' ) ) return;

            // Send.
            $this->slack->message( $message );

        }

    }

    /**
     * Theme install.
     * 
     * @since   2.2.0
     * 
     * @param   array   $data
     * @param   object  $user
     * @return  void
     */
    public function theme_install( $post, $user ) {

        // Check.
        if( ! isset( $post['action'] ) || $post['action'] !== 'install-theme' ) return;

        // Check for theme.
        if( ! isset( $post['slug'] ) ) return;

        // Set message.
        $message = "📦 A theme was just installed: `" . $post['slug'] . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if summary.
        if( $this->is_summary( 'theme-install' ) ) $this->log( $message );

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'theme-install' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Plugin Activate.
     * 
     * @since   2.2.0
     * 
     * @param   string  $plugin
     * @return  void
     */
    public function plugin_activate( $plugin ) {

        // Get current user.
        $user = wp_get_current_user();

        // Set message.
        $message = "🔌 A plugin was just ✅ activated: `" . $plugin . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'plugin-activate' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Plugin Deactivate.
     * 
     * @since   2.2.0
     * 
     * @param   string  $plugin
     * @return  void
     */
    public function plugin_deactivate( $plugin ) {

        // Get current user.
        $user = wp_get_current_user();

        // Set message.
        $message = "🔌 A plugin was just 🔻 deactivated: `" . $plugin . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'plugin-deactivate' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Theme.
     * 
     * @since   2.2.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function theme( $stylesheet ) {

        // Get current user.
        $user = wp_get_current_user();

        // Set message.
        $message = "🎨 The theme was just changed to `" . $stylesheet . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'theme-change' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Admin Create.
     * 
     * @since   2.2.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_create( $user_id, $data ) {

        // Check role.
        if( $data['role'] !== 'administrator' ) return;

        // Set message.
        $message = "👨‍💻 An admin user was just created.\n\n>*User*: `" . $data['user_login'] . "`\n>*Email*: `" . $data['user_email'] . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'admin-create' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Admin Delete.
     * 
     * @since   2.2.0
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
        $message = "👨‍💻 An admin user was just deleted.\n\n>*User*: `" . $user->user_login . "`\n>*Email*: `" . $user->user_email . "`\n\nUser was deleted by...\n>*User*: `" . $current->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'admin-delete' ) ) return;

        // Send.
        $this->slack->message( $message );

    }

    /**
     * Admin Update.
     * 
     * @since   2.2.0
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
            $message = "👨‍💻 An admin user was just demoted.\n\n>*User*: `" . $data['user_login'] . "`\n>*Email*: `" . $data['user_email'] . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-role' ) ) $this->slack->message( $message );

        }

        // Check if user went from non-admin to admin.
        if( ! in_array( 'administrator', (array)$old_data->roles ) && $data['role'] == 'administrator' ) {

            // Set message.
            $message = "👨‍💻 A user was just promoted to admin.\n\n>*User*: `" . $data['user_login'] . "`\n>*Email*: `" . $data['user_email'] . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-role' ) ) $this->slack->message( $message );

        }

        // Check if user email was changed.
        if( $old_data->user_email !== $data['user_email'] ) {

            // Set message.
            $message = "👨‍💻 An admin user just changed their email.\n\n>*User*: `" . $data['user_login'] . "`\n>*Old Email*: `" . $old_data->user_email . "`\n>*New Email*: `" . $data['user_email'] . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-email' ) ) $this->slack->message( $message );

        }

        // Check if the password was changed.
        if( $old_data->data->user_pass !== $data['user_pass'] ) {

            // Set message.
            $message = "👨‍💻 An admin user just changed their password.\n\n>*User*: `" . $data['user_login'] . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-password' ) ) $this->slack->message( $message );

        }

    }

    /**
     * Admin Login.
     * 
     * @since   2.2.0
     * 
     * @param   array   $options
     * @return  void
     */
    public function admin_login( $user_login, $user ) {

        // Check if action has run.
        if( did_action( 'wp_login' ) > 1 ) return;

        // Set static variable.
        static $has_run = false;

        // Check if we have run.
        if( $has_run ) return;
        
        // Set has run.
        $has_run = true;

        // Check if user is admin.
        if( ! in_array( 'administrator', $user->roles ) ) return;

        // Set message.
        $message = "👨‍💻 An admin user just logged into the site at " . site_url() . ".\n\n>*User*: `" . $user_login . "`\n>*IP*: `" . $this->get_ip() . "`\n>*User Agent*: `" . $_SERVER['HTTP_USER_AGENT'] . "`";

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
     * @since   2.2.0
     */
    public function file_editor() {

        // Check for post.
        if( ! isset( $_POST ) || empty( $_POST ) ) return;

        // Check action.
        if( ! isset( $_POST['action'] ) || $_POST['action'] !== 'edit-theme-plugin-file' ) return;

        // Check for file.
        if( ! isset( $_POST['file'] ) ) return;

        // Get current user.
        $user = wp_get_current_user();

        // Check type.
        if( isset( $_POST['theme'] ) ) {

            // Set message.
            $message = "📝 A theme file was edited.\n\n>*File*: `" . $_POST['file'] . "`\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'theme-editor' ) ) $this->slack->message( $message );

        } elseif( isset( $_POST['plugin'] ) ) {

            // Set message.
            $message = "📝 A plugin file was edited.\n\n>*File*: `" . $_POST['file'] . "`\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'plugin-editor' ) ) $this->slack->message( $message );

        }

    }

    /** 
     * Check if enabled.
     * 
     * @since   2.2.0
     */
    public function is_enabled( $setting ) {

        // Get notification settings.
        $notifications = get_option( 'slack-notifications' );

        // Check if setting is enabled.
        return ( ! in_array( $setting, (array)$notifications ) ) ? false : true;

    }

    /**
     * Check if summary.
     * 
     * @since   2.2.0
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
     * @since   2.2.0
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
     * Schedule.
     * 
     * @since   2.2.0
     */
    public function schedule() {

        // Check for scheduled.
        if( ! wp_next_scheduled( 'builtmighty_slack_summary' ) ) {

            // Schedule.
            wp_schedule_event( time(), 'hourly', 'builtmighty_slack_summary' );

        }

    }

    /**
     * Sync.
     * 
     * @since   2.2.0
     */
    public function sync() {

        // Check if we should sync.
        if( get_option( 'builtmighty_slack_summary' ) && get_option( 'builtmighty_slack_summary' ) == date( 'Y-m-d' ) ) return;

        // Get set time.
        $time = get_option( 'slack-summary-time' );

        // Check if time is set.
        if( empty( $time ) ) return;

        // Check if time is current hour or past hour.
        if( date( 'H', current_time( 'timestamp' ) ) <= $time ) return;

        // Get the log file.
        $log = file_get_contents( WP_CONTENT_DIR . '/uploads/builtmighty-slack-summary.log' );

        // Check if log is empty.
        if( empty( $log ) ) return;

        // Send.
        $this->slack->message( "📅 *Daily Summary for `" . site_url() . "`*\n" . $log );

        // Empty log.
        file_put_contents( WP_CONTENT_DIR . '/uploads/builtmighty-slack-summary.log', "" );

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
        $message = "`[" . date( 'Y-m-d g:i:s A' ) . "]`\n" . $message;

        // Check if file exists.
        if( ! file_exists( $file ) ) {

            // Create file.
            file_put_contents( $file, $message );

        } else {

            // Append to file.
            file_put_contents( $file, "\n\n" . $message, FILE_APPEND );

        }

    }

    /**
     * Get notifications.
     * 
     * @since   1.0.0
     */
    public function get_notifications() {

        // Return.
        return apply_filters( 'builtmighty_notifications', [
            'core-update'       => 'WordPress Update',
            'woocommerce'       => 'WooCommerce Settings',
            'plugin-install'    => 'Plugin Installation',
            'plugin-update'     => 'Plugin Update',
            'plugin-activate'   => 'Plugin Activation',
            'plugin-deactivate' => 'Plugin Deactivation',
            'plugin-editor'     => 'Plugin Editor',
            'theme-install'     => 'Theme Installation',
            'theme-update'      => 'Theme Update',
            'theme-change'      => 'Theme Change',
            'theme-editor'      => 'Theme Editor',
            'admin-create'      => 'Admin User Creation',
            'admin-delete'      => 'Admin User Deletion',
            'admin-role'        => 'Admin User Role Change',
            'admin-password'    => 'Admin User Password Change',
            'admin-email'       => 'Admin User Email Change',
            'admin-login'       => 'Admin User Login'
        ] );

    }

}