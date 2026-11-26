<?php
/**
 * Activity log.
 *
 * One custom table for sign-ins, two-factor events, API changes, spam,
 * admin changes, redirected mail and safeguard events. Replaces the
 * serialized options that were rewritten on every request.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Utility;

if( ! defined( 'ABSPATH' ) ) { exit; }

class activity {

    const DB_VERSION_OPTION = 'kit_activity_db';
    const DB_VERSION        = '1';
    const CRON              = 'kit_activity_prune';

    /**
     * Channels and their labels.
     */
    public static function channels(): array {
        return [
            'login'     => 'Sign-ins',
            '2fa'       => 'Two-factor',
            'api'       => 'REST API',
            'spam'      => 'Spam',
            'admin'     => 'Admin changes',
            'mail'      => 'Redirected email',
            'safeguard' => 'Safeguards',
        ];
    }

    /**
     * Table name.
     */
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'kit_log';
    }

    /**
     * Create or upgrade the table.
     */
    public static function install(): void {

        if( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) return;

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::table();
        $collate = $wpdb->get_charset_collate();

        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            channel varchar(20) NOT NULL,
            level varchar(10) NOT NULL DEFAULT 'info',
            event varchar(40) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            username varchar(60) NOT NULL DEFAULT '',
            ip varchar(45) NOT NULL DEFAULT '',
            message varchar(255) NOT NULL DEFAULT '',
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY channel_created (channel, created_at),
            KEY user_id (user_id),
            KEY ip (ip),
            KEY created_at (created_at)
        ) {$collate};" );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );

    }

    /**
     * Schedule pruning.
     */
    public static function boot(): void {
        add_action( self::CRON, [ __CLASS__, 'prune' ] );
        if( ! wp_next_scheduled( self::CRON ) && ! wp_installing() ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
        }
    }

    /**
     * Write an entry.
     *
     * @param string $channel One of channels().
     * @param string $event   Short machine key, like 'login_failed'.
     * @param string $message Plain sentence for people.
     * @param array  $args    level, user_id, username, ip, context (array)
     * @return int Inserted ID, or 0.
     */
    public static function log( string $channel, string $event, string $message, array $args = [] ): int {

        global $wpdb;

        if( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) self::install();

        $user_id  = isset( $args['user_id'] ) ? (int) $args['user_id'] : get_current_user_id();
        $username = (string) ( $args['username'] ?? '' );
        if( $username === '' && $user_id ) {
            $user = get_userdata( $user_id );
            $username = $user ? $user->user_login : '';
        }

        $context = $args['context'] ?? null;
        if( $context !== null ) {
            $context = wp_json_encode( self::redact( (array) $context ) );
            if( strlen( (string) $context ) > 65536 ) $context = substr( (string) $context, 0, 65536 );
        }

        $ok = $wpdb->insert( self::table(), [
            'channel'    => substr( sanitize_key( $channel ), 0, 20 ),
            'level'      => in_array( $args['level'] ?? 'info', [ 'info', 'warning', 'error', 'success' ], true ) ? ( $args['level'] ?? 'info' ) : 'info',
            'event'      => substr( sanitize_key( $event ), 0, 40 ),
            'user_id'    => $user_id,
            'username'   => substr( $username, 0, 60 ),
            'ip'         => substr( (string) ( $args['ip'] ?? request::client_ip() ), 0, 45 ),
            'message'    => mb_substr( wp_strip_all_tags( $message ), 0, 255 ),
            'context'    => $context,
            'created_at' => current_time( 'mysql', true ),
        ] );

        if( $ok ) {
            wp_cache_delete( 'kit_activity_counts', 'kit' );
            /**
             * Fires after an activity entry is written.
             */
            do_action( 'kit_activity_logged', $channel, $event, $message, $args );
        }

        return $ok ? (int) $wpdb->insert_id : 0;

    }

    /**
     * Remove sensitive values from context, recursively.
     */
    public static function redact( array $data, int $depth = 0 ): array {
        $sensitive = '/pass|pwd|secret|token|key|auth|card|cvv|cvc|iban|ssn|nonce|cookie|session|billing_|shipping_|email|phone|address/i';
        foreach( $data as $k => $v ) {
            if( is_string( $k ) && preg_match( $sensitive, $k ) && ! in_array( $k, [ 'route', 'method', 'status' ], true ) ) {
                $data[ $k ] = '[removed]';
            } elseif( is_array( $v ) ) {
                $data[ $k ] = $depth > 4 ? '[nested]' : self::redact( $v, $depth + 1 );
            }
        }
        return $data;
    }

    /**
     * Query entries.
     *
     * @param array $args channel, level, user, ip, search, since (Y-m-d H:i:s UTC), per_page, page
     * @return array [ 'items' => [], 'total' => int ]
     */
    public static function query( array $args = [] ): array {

        global $wpdb;

        if( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) self::install();

        $where  = [ '1=1' ];
        $params = [];

        if( ! empty( $args['channel'] ) ) { $where[] = 'channel = %s'; $params[] = $args['channel']; }
        if( ! empty( $args['level'] ) )   { $where[] = 'level = %s';   $params[] = $args['level']; }
        if( ! empty( $args['event'] ) )   { $where[] = 'event = %s';   $params[] = $args['event']; }
        if( ! empty( $args['ip'] ) )      { $where[] = 'ip = %s';      $params[] = $args['ip']; }
        if( ! empty( $args['user_id'] ) ) { $where[] = 'user_id = %d'; $params[] = (int) $args['user_id']; }
        if( ! empty( $args['since'] ) )   { $where[] = 'created_at >= %s'; $params[] = $args['since']; }
        if( ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(message LIKE %s OR username LIKE %s OR ip LIKE %s)';
            array_push( $params, $like, $like, $like );
        }

        $per_page = max( 1, min( 500, (int) ( $args['per_page'] ?? 50 ) ) );
        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;
        $table    = self::table();
        $sql_where = implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
        $items_sql = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d";

        $total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore
        $items = $wpdb->get_results( $wpdb->prepare( $items_sql, array_merge( $params, [ $per_page, $offset ] ) ) ); // phpcs:ignore

        foreach( (array) $items as $item ) {
            $item->context = $item->context ? json_decode( $item->context, true ) : [];
        }

        return [ 'items' => (array) $items, 'total' => $total ];

    }

    /**
     * Counts for a channel over the last N days, grouped by event.
     */
    public static function counts( string $channel, int $days = 7 ): array {
        global $wpdb;
        if( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) self::install();
        $since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
        $table = self::table();
        $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT event, COUNT(*) AS n FROM {$table} WHERE channel = %s AND created_at >= %s GROUP BY event", $channel, $since ) ); // phpcs:ignore
        $out = [];
        foreach( (array) $rows as $row ) $out[ $row->event ] = (int) $row->n;
        return $out;
    }

    /**
     * Distinct IPs for a channel over the last N days.
     */
    public static function unique_ips( string $channel, int $days = 7 ): int {
        global $wpdb;
        if( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) self::install();
        $since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
        $table = self::table();
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT ip) FROM {$table} WHERE channel = %s AND created_at >= %s", $channel, $since ) ); // phpcs:ignore
    }

    /**
     * Delete entries (optionally one channel).
     */
    public static function clear( string $channel = '' ): void {
        global $wpdb;
        $table = self::table();
        if( $channel ) {
            $wpdb->delete( $table, [ 'channel' => $channel ] );
        } else {
            $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
        }
    }

    /**
     * Daily pruning: age limit plus a per-channel row cap.
     */
    public static function prune(): void {

        global $wpdb;
        $table = self::table();

        $days = (int) apply_filters( 'kit_activity_retention_days', 90 );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore

        $caps = (array) apply_filters( 'kit_activity_channel_caps', [ 'mail' => 200, 'api' => 5000, 'login' => 10000 ] );
        foreach( $caps as $channel => $cap ) {
            $cutoff = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE channel = %s ORDER BY id DESC LIMIT 1 OFFSET %d", $channel, (int) $cap ) ); // phpcs:ignore
            if( $cutoff ) {
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE channel = %s AND id <= %d", $channel, (int) $cutoff ) ); // phpcs:ignore
            }
        }

    }

    /**
     * Drop the table (uninstall).
     */
    public static function uninstall(): void {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore
        delete_option( self::DB_VERSION_OPTION );
        wp_clear_scheduled_hook( self::CRON );
    }

}
