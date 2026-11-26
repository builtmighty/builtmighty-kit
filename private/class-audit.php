<?php
/**
 * Admin audit trail.
 *
 * Writes the changes that matter to the activity log (Admin changes), with
 * or without Slack: plugins and themes, WordPress updates, administrators
 * added, removed or changed, file editor saves and WooCommerce settings.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Private;

use BuiltMightyKit\Utility\activity;

if( ! defined( 'ABSPATH' ) ) { exit; }

class audit {

    /**
     * Construct.
     */
    public function __construct() {
        add_action( 'activated_plugin', [ $this, 'plugin_activated' ], 10, 2 );
        add_action( 'deactivated_plugin', [ $this, 'plugin_deactivated' ], 10, 2 );
        add_action( 'deleted_plugin', [ $this, 'plugin_deleted' ], 10, 2 );
        add_action( 'upgrader_process_complete', [ $this, 'upgraded' ], 10, 2 );
        add_action( 'switch_theme', [ $this, 'theme_switched' ], 10, 3 );
        add_action( 'user_register', [ $this, 'user_registered' ] );
        add_action( 'set_user_role', [ $this, 'role_changed' ], 10, 3 );
        add_action( 'delete_user', [ $this, 'user_deleted' ] );
        add_action( 'profile_update', [ $this, 'profile_updated' ], 10, 2 );
        add_action( 'woocommerce_settings_saved', [ $this, 'woocommerce_saved' ] );
        add_action( 'admin_init', [ $this, 'file_edit' ] );
    }

    /**
     * Plugin name from its file.
     */
    private function plugin_name( string $file ): string {
        if( ! function_exists( 'get_plugin_data' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $path = WP_PLUGIN_DIR . '/' . $file;
        if( is_readable( $path ) ) {
            $data = get_plugin_data( $path, false, false );
            if( ! empty( $data['Name'] ) ) return $data['Name'];
        }
        return $file;
    }

    private function log( string $event, string $message, string $level = 'info', array $context = [] ): void {
        activity::log( 'admin', $event, $message, [ 'level' => $level, 'context' => $context ] );
    }

    public function plugin_activated( $plugin, $network ) {
        $this->log( 'plugin_activated', 'Activated ' . $this->plugin_name( $plugin ), 'info', [ 'plugin' => $plugin ] );
    }

    public function plugin_deactivated( $plugin, $network ) {
        $this->log( 'plugin_deactivated', 'Deactivated ' . $this->plugin_name( $plugin ), 'warning', [ 'plugin' => $plugin ] );
    }

    public function plugin_deleted( $plugin, $deleted ) {
        if( $deleted ) $this->log( 'plugin_deleted', 'Deleted ' . $plugin, 'warning', [ 'plugin' => $plugin ] );
    }

    public function upgraded( $upgrader, $data ) {
        $type   = $data['type'] ?? '';
        $action = $data['action'] ?? '';
        if( $type === 'core' ) {
            $this->log( 'core_updated', 'Updated WordPress to ' . get_bloginfo( 'version' ) );
            return;
        }
        if( $type === 'plugin' ) {
            $items = (array) ( $data['plugins'] ?? ( isset( $data['plugin'] ) ? [ $data['plugin'] ] : [] ) );
            if( $action === 'install' && ! $items && ! empty( $upgrader->new_plugin_data['Name'] ) ) {
                $this->log( 'plugin_installed', 'Installed ' . $upgrader->new_plugin_data['Name'] . ' ' . ( $upgrader->new_plugin_data['Version'] ?? '' ), 'warning' );
                return;
            }
            foreach( $items as $plugin ) {
                $this->log( $action === 'install' ? 'plugin_installed' : 'plugin_updated', ( $action === 'install' ? 'Installed ' : 'Updated ' ) . $this->plugin_name( $plugin ), $action === 'install' ? 'warning' : 'info', [ 'plugin' => $plugin ] );
            }
            return;
        }
        if( $type === 'theme' ) {
            $items = (array) ( $data['themes'] ?? ( isset( $data['theme'] ) ? [ $data['theme'] ] : [] ) );
            if( $action === 'install' && ! $items && ! empty( $upgrader->new_theme_data['Name'] ) ) $items = [ $upgrader->new_theme_data['Name'] ];
            foreach( $items as $theme ) {
                $this->log( $action === 'install' ? 'theme_installed' : 'theme_updated', ( $action === 'install' ? 'Installed theme ' : 'Updated theme ' ) . $theme, $action === 'install' ? 'warning' : 'info' );
            }
        }
    }

    public function theme_switched( $name, $new, $old ) {
        $this->log( 'theme_switched', 'Switched theme to ' . $name, 'warning' );
    }

    public function user_registered( $user_id ) {
        $user = get_userdata( $user_id );
        if( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
            $this->log( 'admin_created', 'Created administrator ' . $user->user_login, 'warning', [ 'user' => $user_id ] );
        }
    }

    public function role_changed( $user_id, $role, $old_roles ) {
        // A brand-new user gets its first role here too; user_registered() logs that one.
        if( empty( $old_roles ) ) return;
        $was = in_array( 'administrator', (array) $old_roles, true );
        $is  = $role === 'administrator';
        if( $was === $is ) return;
        $user = get_userdata( $user_id );
        $this->log( $is ? 'admin_promoted' : 'admin_demoted', ( $user ? $user->user_login : '#' . $user_id ) . ( $is ? ' became an administrator' : ' is no longer an administrator' ), 'warning', [ 'user' => $user_id ] );
    }

    public function user_deleted( $user_id ) {
        $user = get_userdata( $user_id );
        if( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
            $this->log( 'admin_deleted', 'Deleted administrator ' . $user->user_login, 'warning', [ 'user' => $user_id ] );
        }
    }

    public function profile_updated( $user_id, $old ) {
        $user = get_userdata( $user_id );
        if( ! $user || ! in_array( 'administrator', (array) $user->roles, true ) ) return;
        if( $old && $old->user_email !== $user->user_email ) {
            $this->log( 'admin_email', $user->user_login . ' changed their email address', 'warning', [ 'user' => $user_id ] );
        }
        if( $old && $old->user_pass !== $user->user_pass ) {
            $this->log( 'admin_password', $user->user_login . ' changed their password', 'info', [ 'user' => $user_id ] );
        }
    }

    public function woocommerce_saved() {
        $tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : '';
        $this->log( 'woocommerce_settings', 'Changed WooCommerce settings: ' . $tab . ( $section ? ' / ' . $section : '' ), 'info', [ 'tab' => $tab, 'section' => $section ] );
    }

    public function file_edit() {
        if( ! wp_doing_ajax() || ( $_POST['action'] ?? '' ) !== 'edit-theme-plugin-file' || empty( $_POST['file'] ) ) return;
        $where = isset( $_POST['theme'] ) ? 'theme ' . sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : 'plugin ' . sanitize_text_field( wp_unslash( $_POST['plugin'] ?? '' ) );
        $this->log( 'file_edited', 'Edited ' . sanitize_text_field( wp_unslash( $_POST['file'] ) ) . ' in the ' . $where . ' file editor', 'warning' );
    }

}
