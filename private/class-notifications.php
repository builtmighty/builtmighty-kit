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

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\request;

if( ! defined( 'ABSPATH' ) ) { exit; }
class notifications {

    /**
     * Slack.
     * 
     * @since   1.0.0
     */
    private $slack;

    /**
     * Messages waiting to be sent at the end of the request.
     *
     * @var array
     */
    private $queue = [];

    /**
     * Only one instance hooks (the old settings screen created a second one).
     *
     * @var bool
     */
    private static $hooked = false;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check if Slack is connected.
        if( empty( get_option( 'slack-channel' ) ) || empty( get_option( 'built_slack_token' ) ) ) return;

        // Hook once.
        if( self::$hooked ) return;
        self::$hooked = true;

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
        if( settings::is_on( 'slack-summary' ) ) {

            // Schedule.
            add_action( 'wp_loaded', [ $this, 'schedule' ] );
            add_action( 'builtmighty_slack_summary', [ $this, 'sync' ] );

        } elseif( is_admin() || wp_doing_cron() ) {

            // Summary turned off: stop its hourly event.
            add_action( 'admin_init', function() { if( wp_next_scheduled( 'builtmighty_slack_summary' ) ) wp_clear_scheduled_hook( 'builtmighty_slack_summary' ); } );
            add_action( 'builtmighty_slack_summary', function() { wp_clear_scheduled_hook( 'builtmighty_slack_summary' ); } );

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

        // Only settings an administrator saves in wp-admin, not background updates.
        if( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
        if( ! current_user_can( 'manage_woocommerce' ) ) return;
        if( strpos( $name, 'woocommerce_' ) !== 0 ) return;
        foreach( [ 'transient', 'queue', 'version', '_meta', 'session', 'cache', 'admin_notice', 'onboarding', 'task_list', 'tracker', 'schema' ] as $skip ) {
            if( strpos( $name, $skip ) !== false ) return;
        }

        // Name the changed keys. Never send values: they include gateway secrets.
        $changed = [];
        if( is_array( $old ) && is_array( $new ) ) {
            foreach( array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) ) as $key ) {
                if( ( $old[ $key ] ?? null ) !== ( $new[ $key ] ?? null ) ) $changed[] = $key;
            }
        }
        $user = wp_get_current_user();
        $message = "🛒 WooCommerce settings were just updated.\n\n>*Setting*: `" . $name . "`"
            . ( $changed ? "\n>*Changed*: `" . implode( '`, `', array_slice( $changed, 0, 15 ) ) . "`" : '' )
            . "\n>*User*: `" . $user->user_login . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'woocommerce' ) ) return;

        // Send.
        $this->send( $message );

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

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'core-update' ) ) return;

        // Send.
        $this->send( $message );

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

                // Check if setting is enabled.
                if( ! $this->is_enabled( 'plugin-update' ) ) return;

                // Send.
                $this->send( $message );

            }

        } else {

            // Set message.
            $message = "🔄 A plugin was just updated: `" . $data['plugin'] . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Check if summary.

            // Check if setting is enabled.
            if( ! $this->is_enabled( 'plugin-update' ) ) return;

            // Send.
            $this->send( $message );

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

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'plugin-install' ) ) return;

        // Send.
        $this->send( $message );

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

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'plugin-install' ) ) return;

        // Send.
        $this->send( $message );

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

                // Check if setting is enabled.
                if( ! $this->is_enabled( 'theme-update' ) ) return;

                // Send.
                $this->send( $message );

            }

        } else {

            // Set message.
            $message = "🔄 A theme was just updated: `" . $data['theme'] . "`.\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Check if summary.

            // Check if setting is enabled.
            if( ! $this->is_enabled( 'theme-update' ) ) return;

            // Send.
            $this->send( $message );

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

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'theme-install' ) ) return;

        // Send.
        $this->send( $message );

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
        $this->send( $message );

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
        $this->send( $message );

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
        $this->send( $message );

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
        if( ( $data['role'] ?? '' ) !== 'administrator' ) return;

        // Set message.
        $message = "👨‍💻 An admin user was just created.\n\n>*User*: `" . $data['user_login'] . "`\n>*Email*: `" . $data['user_email'] . "`\n>*IP*: `" . $this->get_ip() . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'admin-create' ) ) return;

        // Send.
        $this->send( $message );

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
        $this->send( $message );

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

        // Current role after the update ($data['role'] is only set when the role field was submitted).
        $updated = get_userdata( $user_id );
        $data['role'] = ( $updated && in_array( 'administrator', (array) $updated->roles, true ) ) ? 'administrator' : ( $data['role'] ?? '' );
        $data['user_login'] = $data['user_login'] ?? ( $updated ? $updated->user_login : '' );
        $data['user_email'] = $data['user_email'] ?? ( $updated ? $updated->user_email : '' );

        // Check role.
        if( ! in_array( 'administrator', (array)$old_data->roles ) && $data['role'] !== 'administrator' ) return;

        // Check if user went from admin to non-admin.
        if( in_array( 'administrator', (array)$old_data->roles ) && $data['role'] !== 'administrator' ) {

            // Set message.
            $message = "👨‍💻 An admin user was just demoted.\n\n>*User*: `" . $data['user_login'] . "`\n>*Email*: `" . $data['user_email'] . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-role' ) ) $this->send( $message );

        }

        // Check if user went from non-admin to admin.
        if( ! in_array( 'administrator', (array)$old_data->roles ) && $data['role'] == 'administrator' ) {

            // Set message.
            $message = "👨‍💻 A user was just promoted to admin.\n\n>*User*: `" . $data['user_login'] . "`\n>*Email*: `" . $data['user_email'] . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-role' ) ) $this->send( $message );

        }

        // Check if user email was changed.
        if( $old_data->user_email !== $data['user_email'] ) {

            // Set message.
            $message = "👨‍💻 An admin user just changed their email.\n\n>*User*: `" . $data['user_login'] . "`\n>*Old Email*: `" . $old_data->user_email . "`\n>*New Email*: `" . $data['user_email'] . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-email' ) ) $this->send( $message );

        }

        // Check if the password was changed.
        if( isset( $data['user_pass'] ) && $old_data->data->user_pass !== $data['user_pass'] ) {

            // Set message.
            $message = "👨‍💻 An admin user just changed their password.\n\n>*User*: `" . $data['user_login'] . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'admin-password' ) ) $this->send( $message );

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
        $message = "👨‍💻 An admin user just logged into the site at " . site_url() . ".\n\n>*User*: `" . $user_login . "`\n>*IP*: `" . $this->get_ip() . "`\n>*User Agent*: `" . sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? 'unknown' ) ) . "`";

        // Check if setting is enabled.
        if( ! $this->is_enabled( 'admin-login' ) ) return;

        // Send.
        $this->send( $message );

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
            if( $this->is_enabled( 'theme-editor' ) ) $this->send( $message );

        } elseif( isset( $_POST['plugin'] ) ) {

            // Set message.
            $message = "📝 A plugin file was edited.\n\n>*File*: `" . $_POST['file'] . "`\n>*User*: `" . $user->user_login . "`\n>*IP*: `" . $this->get_ip() . "`";

            // Send.
            if( $this->is_enabled( 'plugin-editor' ) ) $this->send( $message );

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

        // The daily summary collects the same notifications instead of sending each one.
        return settings::is_on( 'slack-summary' ) && $this->is_enabled( $setting );

    }

    /** 
     * Get IP.
     * 
     * @since   2.2.0
     */
    public function get_ip() {
        return request::client_ip();
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

        // Once a day.
        $today = wp_date( 'Y-m-d' );
        if( get_option( 'builtmighty_slack_summary' ) === $today ) return;

        // At or after the chosen time, in the site's time zone.
        $time = (string) settings::get( 'slack-summary-time' );
        if( $time === '' || wp_date( 'H:i' ) < $time ) return;

        $log = (array) get_option( 'kit_slack_summary_log', [] );
        update_option( 'builtmighty_slack_summary', $today, false );
        if( ! $log ) return;

        $this->slack->message( "📅 *Daily summary for `" . site_url() . "`*\n\n" . implode( "\n\n", $log ) );
        delete_option( 'kit_slack_summary_log' );

    }

    /**
     * Add a message to the daily summary.
     *
     * Stored in a private option, not a public file.
     *
     * @since 6.0.0
     */
    public function log( $message ) {
        $log   = (array) get_option( 'kit_slack_summary_log', [] );
        $log[] = "`[" . wp_date( 'g:i A' ) . "]` " . $message;
        update_option( 'kit_slack_summary_log', array_slice( $log, -100 ), false );
    }

    /**
     * Queue a Slack message. Sent after the response is flushed, so saving a
     * setting or updating a plugin never waits on Slack.
     *
     * @since 6.0.0
     */
    public function send( $message ) {
        // When the daily summary is on, collect the message for it instead of sending now.
        if( settings::is_on( 'slack-summary' ) ) {
            $this->log( $message );
            return;
        }
        if( ! $this->queue ) add_action( 'shutdown', [ $this, 'flush' ], 20 );
        $this->queue[] = (string) $message;
    }

    /**
     * Send queued messages.
     *
     * @since 6.0.0
     */
    public function flush() {
        if( ! $this->queue ) return;
        if( function_exists( 'fastcgi_finish_request' ) ) fastcgi_finish_request();
        foreach( array_unique( $this->queue ) as $message ) {
            $this->slack->message( $message );
        }
        $this->queue = [];
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