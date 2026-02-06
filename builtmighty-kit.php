<?php
/*
Plugin Name: 🔨 Built Mighty Kit
Plugin URI: https://builtmighty.com
Description: A kit for Built Mighty clients and developers.
Version: 5.0.0
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
define( 'KIT_VERSION', '5.0.0' );
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

    // Call CRM analytics deactivation.
    if ( class_exists( '\BuiltMightyKit\CRM\crm_analytics' ) ) {
        \BuiltMightyKit\CRM\crm_analytics::deactivate();
    }

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
    require_once KIT_PATH . 'public/class-security-headers.php';
    require_once KIT_PATH . 'public/class-login-logging.php';
    require_once KIT_PATH . 'public/class-session-management.php';
    require_once KIT_PATH . 'public/class-rest-api-security.php';
    require_once KIT_PATH . 'public/class-spam-protection.php';
    require_once KIT_PATH . 'private/class-private.php';
    require_once KIT_PATH . 'private/class-widgets.php';
    require_once KIT_PATH . 'private/class-updates.php';
    require_once KIT_PATH . 'private/class-plugins.php';
    require_once KIT_PATH . 'private/class-disable-editor.php';
    require_once KIT_PATH . 'private/class-actionscheduler.php';
    require_once KIT_PATH . 'private/class-notifications.php';
    require_once KIT_PATH . 'private/class-speed.php';
    require_once KIT_PATH . 'private/class-performance.php';
    require_once KIT_PATH . 'private/class-active-site-logger.php';

    /**
     * CRM Analytics.
     *
     * @since   5.0.0
     */
    require_once KIT_PATH . 'crm/class-crm-api.php';
    require_once KIT_PATH . 'crm/class-crm-woocommerce.php';
    require_once KIT_PATH . 'crm/class-crm-rum.php';
    require_once KIT_PATH . 'crm/class-crm-analytics.php';

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
 * Kit Mode.
 * 
 * @since   1.0.0
 * @version 5.0.0
 */
function is_kit_mode(): bool {

    // Cached.
    static $cached = null;
    if( $cached !== null ) return $cached;

    // Key.
    $key = 'kit_production_url';

    // Get scheme.
    $scheme = ( is_ssl() ) ? 'https' : 'http';

    // Get option.
    $production = ( is_multisite() ) ? get_site_option( $key ) : get_option( $key );
    if( ! empty( $production ) ) {

        // Get current site scheme.
        $production = trailingslashit( $scheme . '://' . wp_parse_url( base64_decode( $production ), PHP_URL_HOST ) );
        
    }

    // Get host.
    $host = trailingslashit( $scheme . '://' . wp_parse_url( home_url(), PHP_URL_HOST ) );

    // Extract base domain (e.g., "mightyrhino.net" from "staging.mightyrhino.net").
    $host_only = wp_parse_url( home_url(), PHP_URL_HOST );
    $parts = explode( '.', $host_only );
    $base = ( count( $parts ) >= 2 )
        ? implode( '.', array_slice( $parts, -2 ) )
        : $host_only;

    // Host-based kit mode check.
    $host_kit = function( $domain ): bool {

        // Force suffixes.
        $force = apply_filters( 'kit_mode_suffixes', [
            'mightyrhino.net',
            'builtmighty.com',
            'github.dev',
            'kinsta.cloud',
            'wpengine.com',
            'cloudwaysapps.com',
        ] );

        // Check.
        return in_array( $domain, $force, true );

    };

    // Filter override.
    $filter = apply_filters( 'is_kit_mode', null );
    if( $filter !== null ) return $cached = (bool) $filter;

    // Constant override.
    if( defined( 'KIT_MODE' ) ) return $cached = (bool) KIT_MODE;

    // Get environment.
    $environment = get_option( 'kit_environment', '' );
    if( $environment === 'production' ) return $cached = false;
    if( in_array( $environment, [ 'staging', 'development', 'local' ], true ) ) return $cached = true;

    // Get WordPress environment.
    $wp_environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : null;
    if( $wp_environment === 'production' ) return $cached = false;
    if( in_array( $wp_environment, [ 'staging', 'development', 'local' ], true ) ) return $cached = true;

    // Compare host against production.
    if( $production ) {

        // If current host matches production, we're on production (not kit mode).
        if( $host === $production ) {
            return $cached = false;
        }

        // Different from production, so we're in kit mode.
        return $cached = true;

    }

    // No production URL set yet.
    // If on a kit suffix domain, we're in kit mode.
    if( $host_kit( $base ) ) {
        return $cached = true;
    }

    // Not on a kit suffix and no production set - assume this is production.
    $production = base64_encode( trailingslashit( home_url() ) );

    // Save as production URL.
    if( is_multisite() ) {
        update_site_option( $key, $production );
    } else {
        update_option( $key, $production );
    }

    // This is production, not kit mode.
    return $cached = false;

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
