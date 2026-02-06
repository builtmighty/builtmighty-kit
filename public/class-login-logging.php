<?php
/**
 * Login Logging.
 *
 * Logs all login attempts and provides activity tracking.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\Public;

class login_logging {

    /**
     * Option name for storing login logs.
     *
     * @var string
     */
    private const LOG_OPTION = 'kit_login_logs';

    /**
     * Maximum number of logs to keep.
     *
     * @var int
     */
    private const MAX_LOGS = 100;

    /**
     * Construct.
     *
     * @since   5.0.0
     */
    public function __construct() {

        // Check if enabled.
        if ( get_option( 'kit_login_logging' ) !== 'enable' ) {
            return;
        }

        // Log successful logins.
        add_action( 'wp_login', [ $this, 'log_successful_login' ], 10, 2 );

        // Log failed logins.
        add_action( 'wp_login_failed', [ $this, 'log_failed_login' ], 10, 2 );

        // Send email notification for admin logins from new IPs.
        add_action( 'wp_login', [ $this, 'notify_new_ip_login' ], 20, 2 );

    }

    /**
     * Log successful login.
     *
     * @param   string   $user_login  Username.
     * @param   \WP_User $user        User object.
     *
     * @since   5.0.0
     */
    public function log_successful_login( $user_login, $user ) {

        $this->add_log( [
            'type'       => 'success',
            'user_id'    => $user->ID,
            'user_login' => $user_login,
            'user_email' => $user->user_email,
            'ip'         => $this->get_client_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'timestamp'  => current_time( 'timestamp' ),
        ] );

    }

    /**
     * Log failed login.
     *
     * @param   string    $username  Username attempted.
     * @param   \WP_Error $error     Error object.
     *
     * @since   5.0.0
     */
    public function log_failed_login( $username, $error = null ) {

        // Try to get user if exists.
        $user = get_user_by( 'login', $username );
        if ( ! $user ) {
            $user = get_user_by( 'email', $username );
        }

        $this->add_log( [
            'type'       => 'failed',
            'user_id'    => $user ? $user->ID : 0,
            'user_login' => $username,
            'user_email' => $user ? $user->user_email : '',
            'ip'         => $this->get_client_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'timestamp'  => current_time( 'timestamp' ),
            'error'      => $error instanceof \WP_Error ? $error->get_error_code() : '',
        ] );

    }

    /**
     * Notify admin of login from new IP.
     *
     * @param   string   $user_login  Username.
     * @param   \WP_User $user        User object.
     *
     * @since   5.0.0
     */
    public function notify_new_ip_login( $user_login, $user ) {

        // Only notify for administrators.
        if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
            return;
        }

        // Check if notifications are enabled.
        if ( get_option( 'kit_login_notify_new_ip' ) !== 'enable' ) {
            return;
        }

        $current_ip = $this->get_client_ip();
        $known_ips  = get_user_meta( $user->ID, 'kit_known_login_ips', true );

        if ( ! is_array( $known_ips ) ) {
            $known_ips = [];
        }

        // Check if this is a new IP.
        if ( in_array( $current_ip, $known_ips, true ) ) {
            return;
        }

        // Add IP to known IPs.
        $known_ips[] = $current_ip;
        $known_ips   = array_slice( $known_ips, -20 ); // Keep last 20 IPs.
        update_user_meta( $user->ID, 'kit_known_login_ips', $known_ips );

        // Don't notify on first login (no known IPs yet).
        if ( count( $known_ips ) === 1 ) {
            return;
        }

        // Send notification email.
        $this->send_new_ip_notification( $user, $current_ip );

    }

    /**
     * Send new IP login notification email.
     *
     * @param   \WP_User $user  User object.
     * @param   string   $ip    IP address.
     *
     * @since   5.0.0
     */
    private function send_new_ip_notification( $user, $ip ) {

        $site_name = get_bloginfo( 'name' );
        $subject   = sprintf( '[%s] New Login Location Detected', $site_name );

        $message = sprintf(
            "Hello %s,\n\n" .
            "A new login to your account was detected from a new IP address.\n\n" .
            "Details:\n" .
            "- Site: %s\n" .
            "- Username: %s\n" .
            "- IP Address: %s\n" .
            "- Time: %s\n" .
            "- Browser: %s\n\n" .
            "If this was you, you can ignore this email.\n\n" .
            "If this wasn't you, please change your password immediately and review your account security.\n\n" .
            "Thank you,\n%s",
            $user->display_name,
            $site_name,
            $user->user_login,
            $ip,
            current_time( 'F j, Y g:i a' ),
            isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'Unknown',
            $site_name
        );

        wp_mail( $user->user_email, $subject, $message );

    }

    /**
     * Add a log entry.
     *
     * @param   array $log  Log data.
     *
     * @since   5.0.0
     */
    private function add_log( $log ) {

        $logs = get_option( self::LOG_OPTION, [] );

        if ( ! is_array( $logs ) ) {
            $logs = [];
        }

        // Add new log at the beginning.
        array_unshift( $logs, $log );

        // Keep only the most recent logs.
        $logs = array_slice( $logs, 0, self::MAX_LOGS );

        update_option( self::LOG_OPTION, $logs, false );

    }

    /**
     * Get login logs.
     *
     * @param   int    $limit  Number of logs to return.
     * @param   string $type   Filter by type (success, failed, or all).
     *
     * @return  array  Login logs.
     *
     * @since   5.0.0
     */
    public static function get_logs( $limit = 20, $type = 'all' ) {

        $logs = get_option( self::LOG_OPTION, [] );

        if ( ! is_array( $logs ) ) {
            return [];
        }

        // Filter by type if specified.
        if ( $type !== 'all' ) {
            $logs = array_filter( $logs, function( $log ) use ( $type ) {
                return isset( $log['type'] ) && $log['type'] === $type;
            } );
        }

        return array_slice( $logs, 0, $limit );

    }

    /**
     * Get login stats.
     *
     * @param   int $days  Number of days to look back.
     *
     * @return  array  Stats array.
     *
     * @since   5.0.0
     */
    public static function get_stats( $days = 7 ) {

        $logs      = get_option( self::LOG_OPTION, [] );
        $cutoff    = current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS );
        $success   = 0;
        $failed    = 0;
        $unique_ips = [];

        foreach ( $logs as $log ) {
            if ( ! isset( $log['timestamp'] ) || $log['timestamp'] < $cutoff ) {
                continue;
            }

            if ( $log['type'] === 'success' ) {
                $success++;
            } else {
                $failed++;
            }

            if ( ! empty( $log['ip'] ) ) {
                $unique_ips[ $log['ip'] ] = true;
            }
        }

        return [
            'success'    => $success,
            'failed'     => $failed,
            'unique_ips' => count( $unique_ips ),
            'days'       => $days,
        ];

    }

    /**
     * Clear all logs.
     *
     * @since   5.0.0
     */
    public static function clear_logs() {
        delete_option( self::LOG_OPTION );
    }

    /**
     * Get client IP address.
     *
     * @return  string  Client IP address.
     *
     * @since   5.0.0
     */
    private function get_client_ip() {

        $ip = '';

        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip  = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }

        return '0.0.0.0';

    }

}
