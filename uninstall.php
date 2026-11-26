<?php
/**
 * Uninstall: remove Kit's table, options and scheduled events.
 *
 * User data that belongs to people (two-factor secrets, passkeys, trusted
 * browsers) is removed too, so nothing sensitive is left behind.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'kit_log' ); // phpcs:ignore

$options = [
    'kit_settings', 'kit_db_version', 'kit_activity_db', 'kit_2fa_key', 'kit_preview_key', 'kit_confirm_live_url',
    'kit_blocked_spam_ips', 'kit_slack_summary_log', 'kit_crm_backfill', 'kit_bundled_js_handles', 'kit_bundled_css_handles',
    'kit_bundle_js_file', 'kit_bundle_css_file', 'kit_bundle_version', 'built_siteurl',
    // Secrets and connection state.
    'built_slack_token', 'built_api_key', 'kit_crm_api_key', 'joined-slack-channel',
    'kit_crm_last_report', 'kit_crm_last_inventory', 'kit_crm_backfill_completed', 'builtmighty_slack_summary',
    'kit_login_logs', 'kit_api_logs',
];

// Pre-6.0 settings rows (one option per setting) that the migration left for rollback.
if( is_readable( __DIR__ . '/utilities/class-settings.php' ) ) {
    require_once __DIR__ . '/utilities/class-environment.php';
    require_once __DIR__ . '/utilities/class-settings.php';
    if( class_exists( '\BuiltMightyKit\Utility\settings' ) ) {
        foreach( \BuiltMightyKit\Utility\settings::schema() as $key => $field ) {
            if( ! empty( $field['legacy'] ) ) $options[] = $key;
        }
    }
}

foreach( array_unique( $options ) as $option ) delete_option( $option );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'kit\_crm\_rum\_%' OR option_name LIKE '\_transient\_kit\_%' OR option_name LIKE '\_transient\_timeout\_kit\_%'" ); // phpcs:ignore

$meta = [ 'authentication_secret', 'authentication_confirmed', 'authentication_backup_codes', 'authentication_setup', 'kit_2fa_pending_secret', 'kit_totp_last_step', 'kit_email_code', 'email_code', 'kit_trusted_devices', 'kit_passkeys', 'kit_known_login_ips', 'kit_last_activity' ];
foreach( $meta as $key ) delete_metadata( 'user', 0, $key, '', true );

foreach( [ 'kit_activity_prune', 'kit_plugins_refresh', 'kit_bundle_rebuild', 'builtmightykit_daily_ping', 'builtmightykit_crm_daily_report', 'builtmighty_slack_summary' ] as $hook ) {
    wp_clear_scheduled_hook( $hook );
}
