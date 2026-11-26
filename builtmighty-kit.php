<?php
/*
Plugin Name: Built Mighty Kit
Plugin URI: https://builtmighty.com
Description: Keeps staging copies from emailing customers, charging cards or calling outside services, and hardens the live store with two-factor sign-in, security headers and speed settings.
Version: 6.0.0
Author: Built Mighty
Author URI: https://builtmighty.com
Copyright: Built Mighty
Text Domain: builtmighty-kit
Requires at least: 6.2
Requires PHP: 8.0
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
define( 'KIT_VERSION', '6.0.0' );
define( 'KIT_NAME', 'builtmighty-kit' );
define( 'KIT_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'KIT_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );
defined( 'KIT_FILE' ) || define( 'KIT_FILE', __FILE__ );

/**
 * Autoloader.
 *
 * Maps BuiltMightyKit\{Area}\{class_name} to {area}/class-{class-name}.php.
 *
 * @since   6.0.0
 */
spl_autoload_register( function( $class ) {

    if( strpos( $class, 'BuiltMightyKit\\' ) !== 0 ) return;

    $map = [
        'BuiltMightyKit\\Plugin'          => 'init.php',
        'BuiltMightyKit\\Private\\core'   => 'private/class-private.php',
        'BuiltMightyKit\\Private\\CLI'    => 'private/class-cli.php',
        'BuiltMightyKit\\Utility\\API'    => 'utilities/class-api.php',
        'BuiltMightyKit\\Utility\\authentication' => 'utilities/class-auth.php',
    ];
    if( isset( $map[ $class ] ) ) {
        require_once KIT_PATH . $map[ $class ];
        return;
    }

    $parts = explode( '\\', $class );
    if( count( $parts ) !== 3 ) return;

    $dirs = [
        'Public'  => 'public',
        'Private' => 'private',
        'Utility' => 'utilities',
        'CRM'     => 'crm',
        'Admin'   => 'admin',
    ];
    if( ! isset( $dirs[ $parts[1] ] ) ) return;

    $file = KIT_PATH . $dirs[ $parts[1] ] . '/class-' . str_replace( '_', '-', strtolower( $parts[2] ) ) . '.php';
    if( is_readable( $file ) ) require_once $file;

} );

/**
 * On activation.
 *
 * @since   1.0.0
 */
register_activation_hook( __FILE__, '\BuiltMightyKit\activation' );
function activation() {

    // Settings migration and the activity table.
    \BuiltMightyKit\Utility\settings::maybe_migrate();
    \BuiltMightyKit\Utility\activity::install();

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

    // Scheduled events.
    \BuiltMightyKit\Private\active_site_logger::deactivate();
    \BuiltMightyKit\CRM\crm_analytics::deactivate();
    wp_clear_scheduled_hook( \BuiltMightyKit\Utility\activity::CRON );
    wp_clear_scheduled_hook( 'kit_plugins_refresh' );
    wp_clear_scheduled_hook( 'kit_bundle_rebuild' );
    wp_clear_scheduled_hook( 'builtmighty_slack_summary' );

}

/**
 * Load.
 *
 * @since   1.0.0
 */
add_action( 'plugins_loaded', '\BuiltMightyKit\load' );
function load() {

    // Shared settings framework (other Built Mighty plugins use it too).
    require_once KIT_PATH . 'includes/class-global-settings.php';

    // Settings, activity log.
    \BuiltMightyKit\Utility\settings::boot();
    \BuiltMightyKit\Utility\activity::boot();
    if( get_option( \BuiltMightyKit\Utility\activity::DB_VERSION_OPTION ) !== \BuiltMightyKit\Utility\activity::DB_VERSION ) {
        \BuiltMightyKit\Utility\activity::install();
    }

    // Initiate.
    \BuiltMightyKit\Plugin::get_instance();

}

/**
 * CLI.
 *
 * @since   1.0.0
 */
if( defined( 'WP_CLI' ) && WP_CLI ) {
    add_action( 'plugins_loaded', '\BuiltMightyKit\register_cli', 20 );
}

/**
 * Register CLI.
 *
 * @since   1.0.0
 */
function register_cli() {
    \WP_CLI::add_command( 'kit', '\BuiltMightyKit\Private\CLI' );
}

/**
 * Kit mode: is this a non-production copy?
 *
 * Kept for backward compatibility. See Utility\environment.
 *
 * @since   1.0.0
 * @version 6.0.0
 */
function is_kit_mode(): bool {
    return \BuiltMightyKit\Utility\environment::is_kit();
}

/**
 * Read a Kit setting.
 *
 * @since   6.0.0
 */
function setting( string $key ) {
    return \BuiltMightyKit\Utility\settings::get( $key );
}

/**
 * The visitor's IP address.
 *
 * @since   6.0.0
 */
function client_ip(): string {
    return \BuiltMightyKit\Utility\request::client_ip();
}

/**
 * Is the current user on the Built Mighty team?
 *
 * Only used to pick the developer or client dashboard widget and to show
 * developer-only detail. Never used for permission checks.
 *
 * @since   6.0.0
 */
function is_builtmighty_user( $user = null ): bool {
    $user = $user ?: wp_get_current_user();
    if( ! $user || empty( $user->user_email ) ) return false;
    $domain  = strtolower( (string) substr( strrchr( $user->user_email, '@' ), 1 ) );
    $domains = (array) apply_filters( 'builtmighty_settings_valid_domains', [ 'builtmighty.com', 'littlerhino.io' ] );
    return in_array( $domain, array_map( 'strtolower', $domains ), true );
}

/**
 * Plugin updates.
 *
 * Only needed where updates are checked or installed.
 *
 * @since   1.0.0
 * @version 6.0.0
 */
if( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
    require KIT_PATH . 'updates/plugin-update-checker.php';
    $kit_updates = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/builtmighty/builtmighty-kit',
        __FILE__,
        'builtmighty-kit'
    );
    $kit_updates->setBranch( 'main' );
}
