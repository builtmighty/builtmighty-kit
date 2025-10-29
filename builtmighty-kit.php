<?php
/*
Plugin Name: 🔨 Built Mighty Kit
Plugin URI: https://builtmighty.com
Description: A kit for Built Mighty clients and developers.
Version: 4.3.0
Author: Built Mighty
Author URI: https://builtmighty.com
Copyright: Built Mighty
Text Domain: builtmighty-kit
Requires Plugins: 
Copyright © 2025 Built Mighty. All Rights Reserved.
*/

/**
 * Namespace.
 *
 * @since   1.0.0
 */
namespace BuiltMightyKit;

/**
 * Disallow direct access.
 * 
 * @since   1.0.0
 */
if( ! defined( 'WPINC' ) ) { die; }

/**
 * Constants.
 *
 * @since   1.0.0
 */
define( 'KIT_VERSION', '4.3.0' );
define( 'KIT_NAME', 'builtmighty-kit' );
define( 'KIT_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'KIT_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );
defined( 'KIT_FILE' ) || define( 'KIT_FILE', __FILE__ );

/**
 * On activation.
 *
 * @since   1.0.0
 */
register_activation_hook( __FILE__, '\BuiltMightyKit\activation' );
function activation() {

    // Flush rewrite rules.
    flush_rewrite_rules();

}

/**
 * On deactivation.
 *
 * @since   1.0.0
 */
register_deactivation_hook( __FILE__, '\BuiltMightyKit\deactivation' );
function deactivation() {

    // Flush rewrite rules.
    flush_rewrite_rules();

    // Call logger deactivation.
    \BuiltMightyKit\Private\active_site_logger::deactivate();

}

/**
 * Load.
 *
 * @since   1.0.0
 */
add_action( 'plugins_loaded', '\BuiltMightyKit\load' );
function load() {

    /**
     * Settings.
     * 
     * @since   1.0.0
     */
    require_once KIT_PATH . 'includes/class-global-settings.php';

    /**
     * Utilities.
     * 
     * @since   1.0.0
     */
    require_once KIT_PATH . 'utilities/class-api.php';
    require_once KIT_PATH . 'utilities/class-controller.php';
    require_once KIT_PATH . 'utilities/class-cpt.php';
    require_once KIT_PATH . 'utilities/class-auth.php';
    require_once KIT_PATH . 'utilities/class-slack.php';
    if( class_exists( 'WC_Email' ) ) require_once KIT_PATH . 'utilities/class-email.php';

    /**
     * Require classes.
     *
     * @since   1.0.0
     */
    require_once KIT_PATH . 'init.php';
    require_once KIT_PATH . 'vendor/autoload.php';
    require_once KIT_PATH . 'public/class-public.php';
    require_once KIT_PATH . 'public/class-security.php';
    require_once KIT_PATH . 'public/class-login.php';
    require_once KIT_PATH . 'public/class-login-security.php';
    require_once KIT_PATH . 'public/class-block-external.php';
    require_once KIT_PATH . 'public/class-block-email.php';
    require_once KIT_PATH . 'public/class-block-access.php';
    require_once KIT_PATH . 'private/class-private.php';
    require_once KIT_PATH . 'private/class-widgets.php';
    require_once KIT_PATH . 'private/class-updates.php';
    require_once KIT_PATH . 'private/class-plugins.php';
    require_once KIT_PATH . 'private/class-disable-editor.php';
    require_once KIT_PATH . 'private/class-actionscheduler.php';
    require_once KIT_PATH . 'private/class-notifications.php';
    require_once KIT_PATH . 'private/class-speed.php';
    require_once KIT_PATH . 'private/class-active-site-logger.php';

    /**
     * Initiate.
     *
     * @since   1.0.0
     */
    \BuiltMightyKit\Plugin::get_instance();

}




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

    // Require CLI class.
    require_once KIT_PATH . 'private/class-cli.php';

    // Register CLI classes.
    \WP_CLI::add_command( 'kit', '\BuiltMightyKit\Private\CLI' );

}

/**
 * Check mode.
 * 
 * @since   1.0.0
 */
function is_kit_mode() {

    // Save production URL.
    if( empty( get_option( 'kit_production_url' ) ) ) {

        // Site URL.
        $site_url = base64_encode( trailingslashit( site_url() ) );

        // Save.
        update_option( 'kit_production_url', $site_url );

    }

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

    // Check if site is kinsta.cloud.
    if( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], 'kinsta.cloud' ) !== false ) return true;

    // Check if site is wpengine.com.
    if( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], 'wpengine.com' ) !== false ) return true;

    // Check if site is cloudwaysapps.com.
    if( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], 'cloudwaysapps.com' ) !== false ) return true;

    // Return false.
    return false;

}

/**
 * Plugin Updates. 
 * 
 * @since   1.0.0
 */
require KIT_PATH . 'updates/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$updates = PucFactory::buildUpdateChecker(
	'https://github.com/builtmighty/builtmighty-kit',
	__FILE__,
	'builtmighty-kit'
);
$updates->setBranch( 'main' );
