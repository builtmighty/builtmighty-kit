<?php
/*
Plugin Name: Built Mighty Kit
Plugin URI: https://builtmighty.com
Description: A custom kit for Built Mighty developers.
Version: 1.0.0
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
define( 'BUILT_VERSION', '0.0.147' );
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
require_once BUILT_PATH . 'classes/class-setup.php';
require_once BUILT_PATH . 'classes/class-dev.php';
require_once BUILT_PATH . 'classes/class-admin.php';
require_once BUILT_PATH . 'classes/class-keys.php';
require_once BUILT_PATH . 'inc/class-jira.php';

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
new builtDev();
new builtAdmin();

/**
 * Check if site is mightyrhino.net or builtmighty.com.
 * 
 * @since   1.0.0
 */
function is_built_mighty() {

    // Check if site is mightyrhino.net or builtmighty.com.
    if( strpos( $_SERVER['HTTP_HOST'], 'mightyrhino.net' ) !== false || strpos( $_SERVER['HTTP_HOST'], 'builtmighty.com' ) !== false ) return true;

    // Return false.
    return false;

}

/**
 * Check site.
 * 
 * @since   1.0.0
 */
add_action( 'admin_head', 'built_check_site' );
function built_check_site() {

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