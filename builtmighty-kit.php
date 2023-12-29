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
 */
define( 'BUILT_VERSION', '1.0.0' );
define( 'BUILT_NAME', 'builtmighty-kit' );
define( 'BUILT_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'BUILT_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'BUILT_DOMAIN', 'builtmighty-kit' );

/** 
 * On activation.
 */
register_activation_hook( __FILE__, 'bml_activation' );
function bml_activation() {

    // Flush rewrite rules.
    flush_rewrite_rules();

}

/**
 * On deactivation.
 */
register_deactivation_hook( __FILE__, 'bml_deactivation' );
function bml_deactivation() {

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

/**
 * Initiate classes.
 * 
 * @since   1.0.0
 */
new builtLogin();
new builtAccess();
new builtWoo();
new builtMail();

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
$updates->setBranch('main');