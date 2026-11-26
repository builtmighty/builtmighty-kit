<?php
/**
 * Kit admin REST API.
 *
 * Every route requires manage_options and the standard wp_rest nonce.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Admin;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;

if( ! defined( 'ABSPATH' ) ) { exit; }

class rest {

    const NS = 'builtmighty-kit/v1';

    /**
     * Construct.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'routes' ] );
    }

    /**
     * Permission check.
     */
    public function can(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Routes.
     */
    public function routes() {

        $post = function( string $route, callable $cb, array $args = [] ) {
            register_rest_route( self::NS, $route, [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => $cb,
                'permission_callback' => [ $this, 'can' ],
                'args'                => $args,
            ] );
        };

        register_rest_route( self::NS, '/settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_settings' ],
                'permission_callback' => [ $this, 'can' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'save_settings' ],
                'permission_callback' => [ $this, 'can' ],
                'args'                => [ 'changes' => [ 'type' => 'object', 'required' => true ] ],
            ],
        ] );

        $post( '/activity/clear', [ $this, 'clear_activity' ] );
        $post( '/spam/unblock', [ $this, 'unblock_ip' ] );
        $post( '/preview/rotate', [ $this, 'rotate_preview' ] );
        $post( '/bundle', [ $this, 'save_bundle' ] );
        $post( '/slack/disconnect', [ $this, 'slack_disconnect' ] );
        $post( '/slack/channels', [ $this, 'slack_channels' ] );
        $post( '/crm/key', [ $this, 'crm_key' ] );
        $post( '/environment/confirm', [ $this, 'confirm_environment' ] );

    }

    /**
     * Current values.
     */
    public function get_settings() {
        $out = [];
        foreach( array_keys( settings::schema() ) as $key ) $out[ $key ] = settings::get( $key );
        return rest_ensure_response( [ 'values' => $out ] );
    }

    /**
     * Save changes.
     */
    public function save_settings( \WP_REST_Request $request ) {

        $changes = (array) $request->get_param( 'changes' );
        if( ! $changes ) return new \WP_Error( 'kit_nothing', 'Nothing to save.', [ 'status' => 400 ] );

        // Constants win; refuse changes to locked settings.
        foreach( array_keys( $changes ) as $key ) {
            if( settings::locked_by( (string) $key ) ) {
                return new \WP_Error( 'kit_locked', sprintf( '%s is set in wp-config.php.', settings::field( (string) $key )['label'] ?? $key ), [ 'status' => 400 ] );
            }
        }

        $before = [];
        foreach( array_keys( $changes ) as $key ) $before[ $key ] = settings::get( (string) $key );

        $result = settings::update( $changes );

        if( $result['errors'] ) {
            return new \WP_REST_Response( [ 'saved' => $result['saved'], 'errors' => $result['errors'] ], 422 );
        }

        // Flush caches once per save.
        if( class_exists( '\Kinsta\Cache_Purge' ) ) ( new \Kinsta\Cache_Purge() )->purge_complete_caches();

        // Custom login address changes need fresh rewrite rules.
        if( array_intersect( array_keys( $result['saved'] ), [ 'kit_enable_login', 'kit_login_url' ] ) ) flush_rewrite_rules( false );

        activity::log( 'admin', 'settings_saved', 'Kit settings changed: ' . implode( ', ', array_map( function( $k ) {
            return settings::field( $k )['label'] ?? $k;
        }, array_keys( $result['saved'] ) ) ), [ 'context' => [ 'keys' => array_keys( $result['saved'] ) ] ] );

        return rest_ensure_response( [ 'saved' => $result['saved'], 'before' => $before, 'errors' => [] ] );

    }

    /**
     * Clear the activity log.
     */
    public function clear_activity( \WP_REST_Request $request ) {
        $channel = sanitize_key( (string) $request->get_param( 'channel' ) );
        if( $channel !== '' && ! isset( activity::channels()[ $channel ] ) ) {
            return new \WP_Error( 'kit_channel', 'Unknown log type.', [ 'status' => 400 ] );
        }
        activity::clear( $channel );
        return [ 'message' => 'Log cleared.', 'reload' => true ];
    }

    /**
     * Unblock a spam IP.
     */
    public function unblock_ip( \WP_REST_Request $request ) {
        $ip = (string) $request->get_param( 'ip' );
        if( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return new \WP_Error( 'kit_ip', 'That isn\'t an IP address.', [ 'status' => 400 ] );
        \BuiltMightyKit\Public\spam_protection::unblock_ip( $ip );
        activity::log( 'spam', 'ip_unblocked', 'Unblocked ' . $ip, [ 'ip' => $ip ] );
        return [ 'message' => $ip . ' unblocked.', 'reload' => true ];
    }

    /**
     * New preview link.
     */
    public function rotate_preview() {
        \BuiltMightyKit\Public\block_access::rotate();
        return [ 'message' => 'New preview link made. The old one no longer works.', 'reload' => true ];
    }

    /**
     * Bundler file selection.
     */
    public function save_bundle( \WP_REST_Request $request ) {
        $detected = get_transient( 'kit_detected_assets' );
        $clean = function( $list, $type ) use ( $detected ) {
            $known = array_keys( (array) ( $detected[ $type ] ?? [] ) );
            return array_values( array_intersect( array_map( 'sanitize_text_field', (array) $list ), $known ) );
        };
        update_option( 'kit_bundled_js_handles', $clean( $request->get_param( 'js' ), 'js' ), false );
        update_option( 'kit_bundled_css_handles', $clean( $request->get_param( 'css' ), 'css' ), false );
        delete_transient( 'kit_bundle_fresh' );
        delete_transient( 'kit_bundle_failed' );
        return [ 'message' => 'Selection saved. Rebuild bundles to use it.' ];
    }

    /**
     * Disconnect Slack.
     */
    public function slack_disconnect() {
        delete_option( 'built_slack_token' );
        delete_option( 'joined-slack-channel' );
        delete_transient( 'kit_slack_channels' );
        settings::reset_keys( [ 'slack-channel', 'slack-notifications' ] );
        activity::log( 'admin', 'slack_disconnected', 'Slack disconnected' );
        return [ 'message' => 'Slack disconnected.', 'reload' => true ];
    }

    /**
     * Refresh the Slack channel list.
     */
    public function slack_channels() {
        ( new \BuiltMightyKit\Utility\slack() )->get_channels( true );
        return [ 'message' => 'Channel list refreshed.', 'reload' => true ];
    }

    /**
     * Replace the monitoring API key.
     */
    public function crm_key( \WP_REST_Request $request ) {
        $key = sanitize_text_field( (string) $request->get_param( 'key' ) );
        if( strlen( $key ) < 16 ) return new \WP_Error( 'kit_key', 'That key looks too short. Paste the whole key.', [ 'status' => 400 ] );
        update_option( 'kit_crm_api_key', $key, false );
        return [ 'message' => 'API key saved.', 'reload' => true ];
    }

    /**
     * Confirm the live store URL saved during the upgrade.
     */
    public function confirm_environment() {
        delete_option( 'kit_confirm_live_url' );
        return [ 'message' => 'Thanks. Kit will treat that address as the live store.', 'reload' => true ];
    }

}
