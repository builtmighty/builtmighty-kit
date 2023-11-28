<?php
/*
Plugin Name: Built Mighty Login
Plugin URI: https://builtmighty.com
Description: A custom URL for the WordPress login screen.
Version: 1.0.0
Author: Built Mighty
Author URI: https://builtmighty.com
Copyright: Built Mighty
Text Domain: builtmighty-login
Copyright © 2023 Built Mighty. All Rights Reserved.
*/

/**
 * Disallow direct access.
 */
if( ! defined( 'WPINC' ) ) { die; }

/**
 * Constants.
 */
define( 'BML_VERSION', '1.0.0' );
define( 'BML_NAME', 'builtmighty-login' );
define( 'BML_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'BML_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'BML_DOMAIN', 'builtmighty-login' );

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
 * Redirect login.
 * 
 * @since   1.0.0
 */
add_action( 'init', 'builtmighty_login' );
function builtmighty_login() {

    // Check if constant is set.
    if( defined( 'BML_ENDPOINT' ) && ! is_user_logged_in() && ! isset( $_POST['builtmighty_login'] ) ) {

        // Check if wp-login.php.
        if( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) {

            // Redirect to home page.
            wp_redirect( home_url( '/' ) );
            exit;

        }

    }

}

/**
 * Load wp-login at endpoint.
 * 
 * @since   1.0.0
 */
add_action( 'template_redirect', 'builtmighty_login_endpoint' );
function builtmighty_login_endpoint() {

    // Check if user IP is allowed.
    if( defined( 'BML_ALLOWED' ) ) {

        // Check for get parameter.
        if( isset( $_GET['bml'] ) ) {

            // Set cookie.
            setcookie( 'bml', $_GET['bml'], time() + ( 60 * 60 * 24 * 30 ), '/' );

        }

        // Check if allowed.
        if( ( is_array( BML_ALLOWED ) && ! in_array( $_SERVER['REMOTE_ADDR'], BML_ALLOWED ) ) || ! isset( $_COOKIE['bml'] ) ) {

            // Redirect to home page.
            wp_redirect( 'https://builtmighty.com' );
            exit;

        }

    }

    // Check if constant is set.
    if( defined( 'BML_ENDPOINT' ) && ! is_user_logged_in() ) {

        // Parse the URL.
        $request = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

        // Check request.
        if( $request === BML_ENDPOINT ) {

            // Include the necessary WordPress files.
            require_once ABSPATH . WPINC . '/load.php';
            require_once ABSPATH . WPINC . '/default-constants.php';
            require_once ABSPATH . WPINC . '/version.php';
            require_once ABSPATH . WPINC . '/functions.php';
            require_once ABSPATH . WPINC . '/plugin.php';
            require_once ABSPATH . WPINC . '/user.php';
            require_once ABSPATH . WPINC . '/class-wpdb.php';
            require_once ABSPATH . WPINC . '/meta.php';
            require_once ABSPATH . WPINC . '/capabilities.php';
            require_once ABSPATH . WPINC . '/formatting.php';
            require_once ABSPATH . WPINC . '/capabilities.php';
            require_once ABSPATH . WPINC . '/pluggable.php';

            // Set the necessary global variables.
            global $pagenow, $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
            $wp_did_header = true;

            // Load wp-login.php to handle the login functionality.
            include ABSPATH . 'wp-login.php';
            exit;

        }

    }


}

/**
 * Add custom login form field for validation.
 * 
 * @since   1.0.0
 */
add_action( 'login_form', 'builtmighty_login_form' );
function builtmighty_login_form() { ?>

    <input type="hidden" name="builtmighty_login" value="true" /><?php

}
