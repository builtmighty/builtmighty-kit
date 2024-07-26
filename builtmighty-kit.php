<?php
/*
Plugin Name: 🔨 Built Mighty Kit
Plugin URI: https://builtmighty.com
Description: A kit for Built Mighty clients.
Version: 2.1.0
Author: Built Mighty
Author URI: https://builtmighty.com
Copyright: Built Mighty
Text Domain: builtmighty-kit
Copyright © 2024 Built Mighty. All Rights Reserved.
*/

/**
 * Namespace.
 * 
 * @since   1.0.0
 */
namespace BuiltMightyKit;

/**
 * Disallow direct access.
 */
if( ! defined( 'WPINC' ) ) { die; }

/**
 * Constants.
 * 
 * @since   1.0.0
 */
define( 'BUILT_VERSION', '2.1.0' );
define( 'BUILT_NAME', 'builtmighty-kit' );
define( 'BUILT_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'BUILT_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );

/** 
 * On activation.
 * 
 * @since   1.0.0
 */
register_activation_hook( __FILE__, '\BuiltMightyKit\built_activation' );
function built_activation() {

    // Load setup class.
    require_once BUILT_PATH . 'classes/core/class-setup.php';

    // Initiate and run setup class.
    $setup = new \BuiltMightyKit\Core\builtSetup();
    $setup->run();

    // Store site URL.
    update_option( 'built_siteurl', site_url() );

    // Only redirect on Built Mighty sites.
    if( is_kit_mode() ) {

        // Set transient.
        set_transient( 'built_activation', true, 60 );

    }

    // Flush rewrite rules.
    flush_rewrite_rules();

}

/**
 * On deactivation.
 * 
 * @since   1.0.0
 */
register_deactivation_hook( __FILE__, '\BuiltMightyKit\built_deactivation' );
function built_deactivation() {

    // Flush rewrite rules.
    flush_rewrite_rules();

}

/**
 * Load classes.
 * 
 * @since   1.0.0
 */
require_once BUILT_PATH . 'vendor/autoload.php';
require_once BUILT_PATH . 'classes/core/class-setup.php';
require_once BUILT_PATH . 'classes/core/class-widget.php';
require_once BUILT_PATH . 'classes/core/class-admin.php';
require_once BUILT_PATH . 'classes/core/class-ajax.php';
require_once BUILT_PATH . 'classes/core/class-db.php';
require_once BUILT_PATH . 'classes/security/class-login.php';
require_once BUILT_PATH . 'classes/security/class-access.php';
require_once BUILT_PATH . 'classes/security/class-security.php';
require_once BUILT_PATH . 'classes/security/class-auth.php';
require_once BUILT_PATH . 'classes/security/class-2fa.php';
require_once BUILT_PATH . 'classes/security/class-2fa-settings.php';
require_once BUILT_PATH . 'classes/security/class-lockdown.php';
require_once BUILT_PATH . 'classes/security/class-lockdown-log.php';
require_once BUILT_PATH . 'classes/security/class-notifications.php';
require_once BUILT_PATH . 'classes/frontend/class-woo.php';
require_once BUILT_PATH . 'classes/frontend/class-mail.php';
require_once BUILT_PATH . 'classes/frontend/class-speed.php';
require_once BUILT_PATH . 'classes/plugins/class-slack.php';
require_once BUILT_PATH . 'classes/plugins/class-updates.php';

/**
 * Initiate classes.
 * 
 * @since   1.0.0
 */
new \BuiltMightyKit\Security\builtLogin();
new \BuiltMightyKit\Security\builtAccess();
new \BuiltMightyKit\Frontend\builtWoo();
new \BuiltMightyKit\Frontend\builtMail();
new \BuiltMightyKit\Security\builtSecurity();
new \BuiltMightyKit\Core\builtDB();
new \BuiltMightyKit\Security\built2FA();
new \BuiltMightyKit\Security\built2FASettings();
new \BuiltMightyKit\Security\builtLockdown();
new \BuiltMightyKit\Security\builtLockdownLog();
new \BuiltMightyKit\Security\builtNotifications();
new \BuiltMightyKit\Frontend\builtSpeed();
new \BuiltMightyKit\Core\builtWidget();
new \BuiltMightyKit\Core\builtAdmin();
new \BuiltMightyKit\Core\builtAJAX();
new \BuiltMightyKit\Plugins\builtSlack();
new \BuiltMightyKit\Plugins\builtUpdates();

/** 
 * CLI.
 * 
 * @since   1.0.0
 */
if( defined( '\WP_CLI' ) && \WP_CLI ) {

    // Register.
    add_action( 'plugins_loaded', '\BuiltMightyKit\register_cli' );

}

/**
 * Register CLI.
 * 
 * @since   1.0.0
 */
function register_cli() {

    // Require CLI classes.
    require_once BUILT_PATH . 'classes/cli/class-security.php';
    require_once BUILT_PATH . 'classes/cli/class-core.php';

    // Register CLI classes.
    \WP_CLI::add_command( 'kit security', '\BuiltMightyKit\CLI\builtSecurity' );
    \WP_CLI::add_command( 'kit core', '\BuiltMightyKit\CLI\builtCore' );

}

/**
 * Check environment.
 * 
 * @since   1.0.0
 */
function is_kit_mode() {

    // Check if production environment.
    if( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'production' ) return false;

    // Check environment type.
    if( defined( 'WP_ENVIRONMENT_TYPE' ) && in_array( WP_ENVIRONMENT_TYPE, [ 'development', 'local', 'staging' ] ) ) return true;

    // Check if site is mightyrhino.net.
    if( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], 'mightyrhino.net' ) !== false ) return true;

    // Check if site is builtmighty.com.
    if( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], 'builtmighty.com' ) !== false ) return true;

    // Check if site is github.dev.
    if( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], 'github.dev' ) !== false ) return true;

    // Return false.
    return false;

}

/**
 * Check site.
 * 
 * @since   1.5.0
 */
add_action( 'admin_init', '\BuiltMightyKit\built_check_site' );
function built_check_site() {

    // Check for transient.
    if( get_transient( 'built_activation' ) ) {

        // Delete transient.
        delete_transient( 'built_activation' );

        // Redirect to settings page.
        wp_safe_redirect( admin_url( 'admin.php?page=builtmighty&activation=true' ) );

    }

    // Check if site URL is stored.
    if( empty( get_option( 'built_siteurl' ) ) ) {

        // Store site URL.
        update_option( 'built_siteurl', site_url() );

    } else {

        // Check if site URL has changed.
        if( get_option( 'built_siteurl' ) === site_url() ) return;

        // Update site URL.
        update_option( 'built_siteurl', site_url() );

        // Update wp-config.php.
        $setup = new \BuiltMightyKit\Core\builtSetup();
        $setup->run();

    }

}

/**
 * Plugin Updates. 
 * 
 * @since   1.0.0
 */
require BUILT_PATH . 'updates/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$updates = PucFactory::buildUpdateChecker(
	'https://github.com/builtmighty/builtmighty-kit',
	__FILE__,
	'builtmighty-kit'
);
$updates->setBranch( 'main' );
