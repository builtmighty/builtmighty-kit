<?php
/*
Plugin Name: 🔨 Built Mighty Kit
Plugin URI: https://builtmighty.com
Description: A custom kit for Built Mighty developers.
Version: 1.5.0
Author: Built Mighty
Author URI: https://builtmighty.com
Copyright: Built Mighty
Text Domain: builtmighty-kit
Copyright © 2023 Built Mighty. All Rights Reserved.
*/

/**
 * Disallow direct access.
 */
if( ! defined( 'WPINC' ) ) { die; }

/**
 * Constants.
 * 
 * @since   1.0.0
 */
define( 'BUILT_VERSION', '1.5.0' );
define( 'BUILT_NAME', 'builtmighty-kit' );
define( 'BUILT_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'BUILT_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'BUILT_DOMAIN', 'builtmighty-kit' );

/** 
 * On activation.
 * 
 * @since   1.0.0
 */
register_activation_hook( __FILE__, 'built_activation' );
function built_activation() {

    // Load setup class.
    require_once BUILT_PATH . 'classes/class-setup.php';

    // Initiate and run setup class.
    $setup = new builtSetup();
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
register_deactivation_hook( __FILE__, 'built_deactivation' );
function built_deactivation() {

    // Flush rewrite rules.
    flush_rewrite_rules();

}

/**
 * Load classes.
 * 
 * @since   1.0.0
 */
require_once BUILT_PATH . 'classes/class-login.php';
require_once BUILT_PATH . 'classes/class-access.php';
require_once BUILT_PATH . 'classes/class-woo.php';
require_once BUILT_PATH . 'classes/class-mail.php';
require_once BUILT_PATH . 'classes/class-security.php';
require_once BUILT_PATH . 'classes/class-speed.php';
require_once BUILT_PATH . 'classes/class-setup.php';
require_once BUILT_PATH . 'classes/class-dev.php';
require_once BUILT_PATH . 'classes/class-admin.php';
require_once BUILT_PATH . 'classes/class-ajax.php';
require_once BUILT_PATH . 'classes/class-keys.php';
require_once BUILT_PATH . 'inc/class-jira.php';
require_once BUILT_PATH . 'inc/class-jira-helper.php';

/**
 * Initiate classes.
 * 
 * @since   1.0.0
 */
new builtLogin();
new builtAccess();
new builtWoo();
new builtMail();
new builtSecurity();
new builtSpeed();
new builtDev();
new builtAdmin();
new builtAJAX();

/**
 * Check environment.
 * 
 * @since   1.0.0
 */
function is_kit_mode() {

    // Check if production environment.
    if( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'production' ) return false;

    // Check if site is mightyrhino.net.
    if( strpos( $_SERVER['HTTP_HOST'], 'mightyrhino.net' ) !== false ) return true;

    // Check if site is builtmighty.com.
    if( strpos( $_SERVER['HTTP_HOST'], 'builtmighty.com' ) !== false ) return true;

    // Check environment type.
    if( defined( 'WP_ENVIRONMENT_TYPE' ) && in_array( WP_ENVIRONMENT_TYPE, [ 'development', 'local', 'staging' ] ) ) return true;

    // Return false.
    return false;

}

/**
 * Check site.
 * 
 * @since   1.5.0
 */
add_action( 'admin_init', 'built_check_site' );
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
        if( get_option( 'built_siteurl') === site_url() ) return;

        // Update site URL.
        update_option( 'built_siteurl', site_url() );

        // Update wp-config.php.
        $setup = new builtSetup();
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