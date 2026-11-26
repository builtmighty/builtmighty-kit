<?php
/**
 * Sign-in log.
 *
 * Records sign-ins and failed attempts in the activity table, and emails
 * administrators when they sign in from an address they haven't used before.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;
use BuiltMightyKit\Utility\request;

if( ! defined( 'ABSPATH' ) ) { exit; }

class login_logging {

    /**
     * Construct.
     */
    public function __construct() {

        if( ! settings::is_on( 'kit_login_logging' ) ) return;

        add_action( 'wp_login', [ $this, 'log_successful_login' ], 10, 2 );
        add_action( 'wp_login_failed', [ $this, 'log_failed_login' ], 10, 2 );

        if( settings::is_on( 'kit_login_notify_new_ip' ) ) {
            add_action( 'wp_login', [ $this, 'notify_new_ip_login' ], 20, 2 );
        }

        self::import_legacy();

    }

    /**
     * Short browser label from a user agent.
     */
    public static function browser( string $ua ): string {
        $browser = 'Browser';
        foreach( [ 'Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari' ] as $needle => $label ) {
            if( stripos( $ua, $needle ) !== false ) { $browser = $label; break; }
        }
        $os = '';
        foreach( [ 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows', 'Mac OS' => 'Mac', 'Linux' => 'Linux' ] as $needle => $label ) {
            if( stripos( $ua, $needle ) !== false ) { $os = $label; break; }
        }
        return $os ? $browser . ' on ' . $os : $browser;
    }

    /**
     * Successful sign-in.
     */
    public function log_successful_login( $user_login, $user ) {
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        activity::log( 'login', 'login_success', $user_login . ' signed in', [
            'level'    => 'success',
            'user_id'  => $user->ID,
            'username' => $user_login,
            'context'  => [ 'browser' => self::browser( $ua ), 'method' => (string) apply_filters( 'kit_login_method', 'Password' ) ],
        ] );
    }

    /**
     * Failed sign-in.
     */
    public function log_failed_login( $username, $error = null ) {
        $user = get_user_by( 'login', $username ) ?: get_user_by( 'email', $username );
        $code = $error instanceof \WP_Error ? $error->get_error_code() : '';
        $labels = [
            'invalid_username'    => 'unknown user',
            'invalid_email'       => 'unknown email',
            'incorrect_password'  => 'wrong password',
            'authentication_code' => 'wrong code',
            'kit_2fa_locked'      => 'locked out',
        ];
        $reason = $labels[ $code ] ?? 'failed';
        activity::log( 'login', 'login_failed', ( $user ? $user->user_login : sanitize_user( (string) $username ) ) . ': ' . $reason, [
            'level'    => 'warning',
            'user_id'  => $user ? $user->ID : 0,
            'username' => $user ? $user->user_login : sanitize_user( (string) $username ),
            'context'  => [ 'reason' => $code ],
        ] );
    }

    /**
     * Email an administrator who signs in from a new address.
     */
    public function notify_new_ip_login( $user_login, $user ) {

        if( ! in_array( 'administrator', (array) $user->roles, true ) ) return;

        $ip    = request::client_ip();
        $known = get_user_meta( $user->ID, 'kit_known_login_ips', true );
        if( ! is_array( $known ) ) $known = [];
        if( in_array( $ip, $known, true ) ) return;

        $first   = empty( $known );
        $known[] = $ip;
        update_user_meta( $user->ID, 'kit_known_login_ips', array_slice( $known, -20 ) );

        // No alert the first time we see this person.
        if( $first ) return;

        $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $message = sprintf(
            "Hi %s,\n\nSomeone signed in to your account on %s from an address we haven't seen before.\n\nAddress: %s\nBrowser: %s\nTime: %s\n\nIf this was you, there's nothing to do.\nIf it wasn't, change your password now and reset two-factor from your profile.\n",
            $user->display_name,
            $site,
            $ip,
            self::browser( $ua ),
            wp_date( 'F j, Y g:i a' )
        );

        wp_mail( $user->user_email, sprintf( '[%s] New sign-in to your account', $site ), $message );

        activity::log( 'login', 'new_ip_alert', 'New-address alert sent to ' . $user_login, [ 'user_id' => $user->ID, 'level' => 'info' ] );

    }

    /**
     * Recent entries, in the shape the dashboard widget expects.
     */
    public static function get_logs( $limit = 20 ) {
        $result = activity::query( [ 'channel' => 'login', 'per_page' => $limit ] );
        return array_map( function( $row ) {
            return [
                'type'       => $row->event === 'login_success' ? 'success' : ( $row->event === 'login_failed' ? 'failed' : 'info' ),
                'user_id'    => (int) $row->user_id,
                'user_login' => $row->username,
                'ip'         => $row->ip,
                'timestamp'  => strtotime( $row->created_at . ' UTC' ),
                'message'    => $row->message,
            ];
        }, $result['items'] );
    }

    /**
     * Totals for the last N days.
     */
    public static function get_stats( $days = 7 ) {
        $counts = activity::counts( 'login', (int) $days );
        return [
            'success'    => (int) ( $counts['login_success'] ?? 0 ),
            'failed'     => (int) ( $counts['login_failed'] ?? 0 ),
            'unique_ips' => activity::unique_ips( 'login', (int) $days ),
            'days'       => (int) $days,
        ];
    }

    /**
     * Clear the sign-in log.
     */
    public static function clear_logs() {
        activity::clear( 'login' );
    }

    /**
     * One-time import of the old option-based log.
     */
    public static function import_legacy(): void {
        $legacy = get_option( 'kit_login_logs', null );
        if( ! is_array( $legacy ) ) return;
        global $wpdb;
        foreach( array_reverse( array_slice( $legacy, 0, 100 ) ) as $log ) {
            $wpdb->insert( activity::table(), [
                'channel'    => 'login',
                'level'      => ( $log['type'] ?? '' ) === 'success' ? 'success' : 'warning',
                'event'      => ( $log['type'] ?? '' ) === 'success' ? 'login_success' : 'login_failed',
                'user_id'    => (int) ( $log['user_id'] ?? 0 ),
                'username'   => (string) ( $log['user_login'] ?? '' ),
                'ip'         => (string) ( $log['ip'] ?? '' ),
                'message'    => ( $log['user_login'] ?? '' ) . ( ( $log['type'] ?? '' ) === 'success' ? ' signed in' : ': failed' ),
                'context'    => null,
                'created_at' => gmdate( 'Y-m-d H:i:s', (int) ( $log['timestamp'] ?? time() ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ),
            ] );
        }
        delete_option( 'kit_login_logs' );
        delete_option( 'kit_api_logs' );
    }

}
