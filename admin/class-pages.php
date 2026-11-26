<?php
/**
 * Kit admin pages.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Admin;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\environment;
use BuiltMightyKit\Utility\activity;
use BuiltMightyKit\Utility\safeguards;

if( ! defined( 'ABSPATH' ) ) { exit; }

class pages {

    /**
     * Render a page.
     */
    public static function render( string $view ): void {
        $method = 'page_' . $view;
        if( method_exists( __CLASS__, $method ) ) {
            self::$method();
            return;
        }
        /**
         * Render a custom Kit page added with the kit_admin_pages filter.
         *
         * @since 6.0.0
         */
        do_action( 'kit_admin_page_' . $view );
    }

    // ------------------------------------------------------------------
    // Overview
    // ------------------------------------------------------------------

    private static function page_overview(): void {

        $env    = environment::type();
        $source = environment::source();
        $live   = environment::live_url();
        $git    = safeguards::git();

        if( environment::is_kit() ) {

            $status = safeguards::status();
            $on     = count( array_filter( $status, function( $s ) { return $s['on']; } ) );
            $total  = count( $status );
            $required_off = array_filter( $status, function( $s ) { return ! $s['on'] && ! $s['optional']; } );

            echo '<div class="bmk-frame bmk-envhero"><div class="bmk-in">';
            echo '<div><h1>' . esc_html( $required_off ? 'Some of this copy can reach real customers.' : 'Nothing here reaches real customers.' ) . '</h1>';
            echo '<p>' . esc_html( sprintf( '%1$d of %2$d safeguards are on for this %3$s copy.', $on, $total, $env ) ) . '</p></div>';
            echo self::env_details( $source, $live, $git ); // phpcs:ignore
            echo '</div></div>';

            echo '<div class="bmk-cols">';

            // Safeguards, exceptions first.
            uasort( $status, function( $a, $b ) {
                $rank = function( $s ) { return $s['on'] ? 2 : ( $s['optional'] ? 1 : 0 ); };
                return $rank( $a ) <=> $rank( $b );
            } );
            echo '<section class="bmk-panel"><div class="bmk-panel-h"><h2>Safeguards</h2><a href="' . esc_url( app::url( 'safeguards' ) ) . '">Manage</a></div><ul class="bmk-plist">';
            foreach( $status as $key => $s ) {
                $class = $s['on'] ? '' : ( $s['optional'] ? ' class="bmk-optional"' : ' class="bmk-off"' );
                echo '<li' . $class . '><span class="bmk-pic">' . ui::icon( $s['icon'] ) . '</span><div><b>' . esc_html( $s['label'] ) . ( $s['new'] ? ' ' . ui::pill( 'New', 'info', false ) : '' ) . '</b><small>' . esc_html( $s['detail'] ) . '</small></div>'; // phpcs:ignore
                if( $s['on'] ) {
                    echo '<span class="bmk-meta">' . ui::icon( 'check', 'bmk-sm' ) . 'On</span>'; // phpcs:ignore
                } elseif( settings::locked_by( $key ) ) {
                    echo '<span class="bmk-meta">' . ui::icon( 'lock', 'bmk-sm' ) . 'wp-config.php</span>'; // phpcs:ignore
                } else {
                    echo '<button type="button" class="bmk-btn bmk-btn-secondary bmk-btn-sm" data-bmk-quick="' . esc_attr( $key ) . '" data-value="true">Turn on</button>';
                }
                echo '</li>';
            }
            echo '</ul></section>';

            self::recent_activity();
            echo '</div>';

        } else {

            $steps = safeguards::hardening();
            $done  = count( array_filter( $steps, function( $s ) { return $s['done']; } ) );
            $total = count( $steps );
            $left  = $total - $done;

            echo '<div class="bmk-frame bmk-envhero"><div class="bmk-in">';
            echo '<div><h1>' . esc_html( $left ? sprintf( _n( '%d step left to finish hardening this store.', '%d steps left to finish hardening this store.', $left, 'builtmighty-kit' ), $left ) : 'This store is fully hardened.' ) . '</h1>';
            echo '<p>This is the live store. Changes here are real, so Kit focuses on keeping it secure and fast.</p>';
            echo '<div class="bmk-meter" role="img" aria-label="' . esc_attr( sprintf( '%d of %d hardening steps on', $done, $total ) ) . '">';
            foreach( $steps as $s ) echo '<i' . ( $s['done'] ? '' : ' class="bmk-todo"' ) . '></i>';
            echo '</div><p class="bmk-meter-label">' . esc_html( sprintf( '%d of %d hardening steps on', $done, $total ) ) . '</p></div>';
            echo self::env_details( $source, $live, $git ); // phpcs:ignore
            echo '</div></div>';

            echo '<div class="bmk-cols">';
            echo '<section class="bmk-panel"><div class="bmk-panel-h"><h2>Hardening</h2></div><ul class="bmk-todo-list">';
            uasort( $steps, function( $a, $b ) { return (int) $a['done'] <=> (int) $b['done']; } );
            foreach( $steps as $key => $s ) {
                echo '<li class="' . ( $s['done'] ? 'bmk-done' : '' ) . '"><span>' . ui::icon( $s['done'] ? 'checkc' : 'alert', 'bmk-sm' ) . esc_html( $s['label'] ) . '</span>'; // phpcs:ignore
                if( ! $s['done'] && ! empty( $s['page'] ) ) {
                    $field = settings::field( $key );
                    if( $field && $field['type'] === 'bool' && empty( $field['requires'] ) && $key !== 'kit_enable_login' ) {
                        echo '<button type="button" class="bmk-btn bmk-btn-secondary bmk-btn-sm" data-bmk-quick="' . esc_attr( $key ) . '" data-value="true">Turn on</button>';
                    } else {
                        echo '<a class="bmk-btn bmk-btn-secondary bmk-btn-sm" href="' . esc_url( app::url( $s['page'] ) . ( $field ? '#bmk-row-' . $key : '' ) ) . '">Review</a>';
                    }
                }
                echo '</li>';
            }
            echo '</ul></section>';
            self::recent_activity();
            echo '</div>';

        }

        self::stats();

    }

    /**
     * Detection details list.
     */
    private static function env_details( array $source, string $live, array $git ): string {
        $html  = '<dl class="bmk-envdl">';
        $html .= '<dt>Detected</dt><dd>' . esc_html( $source['label'] ) . ( $source['detail'] !== '' ? ' <code>' . esc_html( $source['detail'] ) . '</code>' : '' ) . '</dd>';
        $html .= '<dt>Live store</dt><dd>' . ( $live ? '<code>' . esc_html( preg_replace( '#^https?://#', '', $live ) ) . '</code>' : '<a href="' . esc_url( app::url( 'settings' ) ) . '">Not set</a>' ) . '</dd>';
        if( ! empty( $git['branch'] ) || ! empty( $git['commit'] ) ) {
            $html .= '<dt>Branch</dt><dd><code>' . esc_html( trim( $git['branch'] . ( $git['commit'] ? ' @ ' . $git['commit'] : '' ) ) ) . '</code></dd>';
        }
        return $html . '</dl>';
    }

    /**
     * Recent activity panel.
     */
    private static function recent_activity(): void {
        $recent = activity::query( [ 'per_page' => 6 ] );
        echo '<section class="bmk-panel"><div class="bmk-panel-h"><h2>Recent activity</h2><a href="' . esc_url( app::url( 'activity' ) ) . '">View all</a></div>';
        if( ! $recent['items'] ) {
            echo '<div class="bmk-empty-inline"><p>Nothing logged yet. Sign-ins appear here once the sign-in log is on, and blocked requests and redirected email appear on copies.</p><a class="bmk-btn bmk-btn-secondary bmk-btn-sm" href="' . esc_url( app::url( 'login' ) . '#bmk-row-kit_login_logging' ) . '">Set up the sign-in log</a></div>';
        } else {
            echo '<ul class="bmk-feed">';
            foreach( $recent['items'] as $row ) echo self::feed_item( $row ); // phpcs:ignore
            echo '</ul>';
        }
        echo '</section>';
    }

    /**
     * One activity feed row.
     */
    public static function feed_item( $row ): string {
        $tone = [ 'success' => [ 'ok', 'check' ], 'warning' => [ 'bad', 'x' ], 'error' => [ 'bad', 'x' ] ][ $row->level ] ?? [ 'neu', 'info' ];
        $icon = [ 'mail' => 'mail', 'safeguard' => 'globe', 'api' => 'server', 'spam' => 'ban' ][ $row->channel ] ?? $tone[1];
        if( $row->channel === 'login' ) $icon = $tone[1];
        return '<li><span class="bmk-fi bmk-' . esc_attr( $tone[0] ) . '">' . ui::icon( $icon ) . '</span><div>' . esc_html( $row->message ) . ( $row->ip ? ' <span class="bmk-m">' . esc_html( $row->ip ) . '</span>' : '' ) . '</div><time datetime="' . esc_attr( $row->created_at ) . 'Z">' . esc_html( ui::ago( $row->created_at ) ) . '</time></li>';
    }

    /**
     * Seven-day stats.
     */
    private static function stats(): void {
        $login = activity::counts( 'login', 7 );
        $ips   = activity::unique_ips( 'login', 7 );
        $safe  = activity::counts( 'safeguard', 7 );
        $mail  = activity::counts( 'mail', 7 );
        $tiles = [
            [ 'Sign-ins, 7 days', number_format_i18n( $login['login_success'] ?? 0 ), '' ],
            [ 'Failed sign-ins', number_format_i18n( $login['login_failed'] ?? 0 ), ( $login['login_failed'] ?? 0 ) ? 'See Activity for details' : 'None this week' ],
            [ 'Sign-in IP addresses', number_format_i18n( $ips ), '' ],
            environment::is_kit()
                ? [ 'Emails redirected', number_format_i18n( $mail['redirected'] ?? 0 ), number_format_i18n( $safe['request_blocked'] ?? 0 ) . ' outside hosts blocked' ]
                : [ 'Spam blocked', number_format_i18n( array_sum( activity::counts( 'spam', 7 ) ) ), '' ],
        ];
        echo '<section class="bmk-stats" aria-label="Last 7 days">';
        foreach( $tiles as $t ) {
            echo '<div class="bmk-stat"><span class="bmk-k">' . esc_html( $t[0] ) . '</span><span class="bmk-v">' . esc_html( $t[1] ) . '</span>' . ( $t[2] ? '<span class="bmk-d">' . esc_html( $t[2] ) . '</span>' : '' ) . '</div>';
        }
        echo '</section>';
    }

    // ------------------------------------------------------------------
    // Safeguards
    // ------------------------------------------------------------------

    private static function page_safeguards(): void {

        if( ! environment::is_kit() ) {
            echo ui::alert( 'info', 'This is the live store, so safeguards are off by default', 'Safeguards stop copies of the store from emailing customers, charging cards or calling outside services. They turn on automatically on staging, development and local copies. You can still turn one on here if you need to.' ); // phpcs:ignore
        }

        $email_pill = settings::is_on( 'kit_block_email' ) ? ui::pill( 'Protected', 'ok' ) : ( environment::is_kit() ? ui::pill( 'Reaching customers', 'warn' ) : '' );
        $mail_link  = '<div class="bmk-group-foot"><a href="' . esc_url( app::url( 'activity', [ 'channel' => 'mail' ] ) ) . '">' . ui::icon( 'eye', 'bmk-sm' ) . 'See redirected email</a></div>';
        echo ui::group( 'safeguards', 'email', 'Outgoing email', 'What happens to mail WordPress and WooCommerce send from this copy.', $email_pill, $mail_link ); // phpcs:ignore

        $always = '<div class="bmk-group-note"><span class="bmk-label">Always allowed</span><div class="bmk-tags bmk-tags-static">';
        foreach( \BuiltMightyKit\Public\block_external::always_allowed() as $host ) {
            $always .= '<span class="bmk-tag bmk-fixed">' . esc_html( $host ) . '</span>';
        }
        $always .= '</div></div>';
        echo ui::group( 'safeguards', 'external', 'Outside services', 'Calls this copy makes to payment, shipping, marketing and other APIs.', settings::is_on( 'kit_block_external' ) ? ui::pill( 'Blocking', 'ok' ) : '', $always ); // phpcs:ignore

        echo ui::group( 'safeguards', 'schedules', 'Scheduled actions', 'Background jobs WooCommerce and plugins queue up, like renewal charges and follow-up emails.', settings::is_on( 'kit_actionscheduler' ) ? ui::pill( 'Paused', 'ok' ) : '' ); // phpcs:ignore

        /**
         * Extra safeguard groups (payments, renewals, webhooks, indexing).
         *
         * @since 6.0.0
         */
        do_action( 'kit_admin_safeguards_groups' );

        $preview = '';
        if( settings::is_on( 'kit_block_access' ) ) {
            $preview = '<div class="bmk-group-note"><span class="bmk-label">Preview link</span><div class="bmk-secret"><code data-bmk-copy-src>' . esc_html( \BuiltMightyKit\Public\block_access::preview_url() ) . '</code><button type="button" class="bmk-btn bmk-btn-ghost bmk-btn-icon bmk-btn-sm" data-bmk-copy aria-label="Copy preview link">' . ui::icon( 'copy', 'bmk-sm' ) . '</button></div><span class="bmk-help">Anyone with this link can browse this copy for 12 hours without signing in. <button type="button" class="bmk-linkbtn" data-bmk-action="preview/rotate" data-bmk-confirm-title="Make a new preview link?" data-bmk-confirm-text="The current link stops working right away. Anyone using it will need the new one.">Make a new link</button></span></div>';
        }
        echo ui::group( 'safeguards', 'visitors', 'Visitors', 'Who can see this copy without signing in.', '', $preview ); // phpcs:ignore

    }

    // ------------------------------------------------------------------
    // Login and two-factor
    // ------------------------------------------------------------------

    private static function page_login(): void {
        if( empty( get_option( 'permalink_structure' ) ) && settings::is_on( 'kit_enable_login' ) ) {
            echo ui::alert( 'warn', 'The custom login address needs pretty permalinks', 'Choose any setting other than Plain under Settings, Permalinks, or the login page stays at wp-login.php.', '<a class="bmk-btn bmk-btn-secondary bmk-btn-sm" href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">Open permalink settings</a>' ); // phpcs:ignore
        }
        $login_url = settings::is_on( 'kit_enable_login' ) && settings::get( 'kit_login_url' ) !== '' ? '<div class="bmk-group-foot"><span class="bmk-help">Your login page: <code>' . esc_html( home_url( '/' . trim( (string) settings::get( 'kit_login_url' ), '/' ) . '/' ) ) . '</code>. Bookmark it.</span></div>' : '';
        echo ui::group( 'login', 'address', 'Login address', 'Move the login page so bots hitting wp-login.php find nothing.', '', $login_url ); // phpcs:ignore

        $roles = count( (array) settings::get( 'kit_2fa_users' ) ) + 1;
        $pill  = settings::is_on( 'kit_enable_2fa' ) ? ui::pill( sprintf( _n( 'Required for %d role', 'Required for %d roles', $roles, 'builtmighty-kit' ), $roles ), 'ok' ) : ui::pill( 'Off', 'warn' );
        echo ui::group( 'login', 'twofactor', 'Two-factor sign-in', 'A second step after the password: an emailed 8-digit code, an authenticator app, or a backup code.', $pill ); // phpcs:ignore

        echo ui::group( 'login', 'alerts', 'Sign-in log and alerts', 'Know who signed in, from where, and when something looks wrong.', '', '<div class="bmk-group-foot"><a href="' . esc_url( app::url( 'activity', [ 'channel' => 'login' ] ) ) . '">' . ui::icon( 'activity', 'bmk-sm' ) . 'See sign-in activity</a></div>' ); // phpcs:ignore
        echo ui::group( 'login', 'sessions', 'Sessions', 'How long people stay signed in, and in how many places.' ); // phpcs:ignore
    }

    // ------------------------------------------------------------------
    // Headers and API
    // ------------------------------------------------------------------

    private static function page_headers(): void {
        echo ui::group( 'headers', 'headers', 'Security headers', 'Response headers that tell browsers how to treat this site.' ); // phpcs:ignore
        if( settings::is_active( 'kit_header_hsts' ) || settings::is_on( 'kit_security_headers' ) ) {
            echo ui::group( 'headers', 'hsts', 'HTTPS only', is_ssl() ? 'This site is served over HTTPS.' : 'This page isn\'t loaded over HTTPS, so browsers will ignore HSTS.' ); // phpcs:ignore
            echo ui::group( 'headers', 'csp', 'Content-Security-Policy', 'Limits where scripts, styles and images can load from.' ); // phpcs:ignore
        }
        echo ui::group( 'headers', 'rest', 'REST API', 'Controls for /wp-json/, which the block editor, WooCommerce and apps use.' ); // phpcs:ignore
    }

    // ------------------------------------------------------------------
    // Spam
    // ------------------------------------------------------------------

    private static function page_spam(): void {
        echo ui::group( 'spam', 'comments', 'Comments', 'Checks only apply to visitors who aren\'t signed in.' ); // phpcs:ignore
        $blocked = \BuiltMightyKit\Public\spam_protection::get_blocked_ips();
        echo '<section class="bmk-group"><div class="bmk-group-head"><div><h2>Blocked addresses</h2><p>Addresses blocked after repeated spam. Unblock one if a real person got caught.</p></div></div>';
        if( ! $blocked ) {
            echo '<div class="bmk-empty"><h3>' . ui::icon( 'ban' ) . 'No blocked addresses</h3><p>When "Block addresses that keep sending spam" is on, an address is blocked after ' . (int) settings::get( 'kit_spam_block_threshold' ) . ' rejected comments and listed here.</p></div>'; // phpcs:ignore
        } else {
            echo '<div class="bmk-table-wrap"><table class="bmk-table"><thead><tr><th>IP address</th><th>Spam comments</th><th>Last seen</th><th><span class="screen-reader-text">Actions</span></th></tr></thead><tbody class="bmk-tnum">';
            foreach( $blocked as $ip => $data ) {
                echo '<tr><td class="bmk-mono">' . esc_html( $ip ) . '</td><td>' . (int) ( $data['count'] ?? 0 ) . '</td><td>' . esc_html( isset( $data['last_seen'] ) ? wp_date( 'M j, g:i A', (int) $data['last_seen'] ) : '' ) . '</td><td class="bmk-right"><button type="button" class="bmk-btn bmk-btn-secondary bmk-btn-sm" data-bmk-action="spam/unblock" data-ip="' . esc_attr( $ip ) . '">Unblock</button></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
    }

    // ------------------------------------------------------------------
    // Activity
    // ------------------------------------------------------------------

    private static function page_activity(): void {

        $channels = activity::channels();
        $channel  = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '';
        if( ! isset( $channels[ $channel ] ) ) $channel = '';
        $search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
        $result   = activity::query( [ 'channel' => $channel, 'search' => $search, 'page' => $paged, 'per_page' => 50 ] );
        $pages    = max( 1, (int) ceil( $result['total'] / 50 ) );

        echo '<form class="bmk-filters" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( app::SLUG ) . '"><input type="hidden" name="view" value="activity">';
        echo '<div class="bmk-tabs" role="tablist" aria-label="Filter by type">';
        echo '<a role="tab" href="' . esc_url( app::url( 'activity', $search ? [ 's' => $search ] : [] ) ) . '" aria-selected="' . ( $channel === '' ? 'true' : 'false' ) . '">All</a>';
        foreach( $channels as $key => $label ) {
            echo '<a role="tab" href="' . esc_url( app::url( 'activity', array_filter( [ 'channel' => $key, 's' => $search ] ) ) ) . '" aria-selected="' . ( $channel === $key ? 'true' : 'false' ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</div>';
        if( $channel ) echo '<input type="hidden" name="channel" value="' . esc_attr( $channel ) . '">';
        echo '<div class="bmk-filter-row"><label class="screen-reader-text" for="bmk-act-s">Search the log</label><input class="bmk-input" type="search" id="bmk-act-s" name="s" value="' . esc_attr( $search ) . '" placeholder="Search by user, IP address or message"><button class="bmk-btn bmk-btn-secondary" type="submit">Search</button><span class="bmk-sp"></span>';
        echo '<a class="bmk-btn bmk-btn-ghost" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kit_export_activity' . ( $channel ? '&channel=' . $channel : '' ) ), 'kit_export_activity' ) ) . '">' . ui::icon( 'download', 'bmk-sm' ) . 'Export CSV</a>'; // phpcs:ignore
        echo '<button type="button" class="bmk-btn bmk-btn-danger-soft" data-bmk-action="activity/clear" data-channel="' . esc_attr( $channel ) . '" data-bmk-confirm-title="' . esc_attr( $channel ? 'Clear the ' . strtolower( $channels[ $channel ] ) . ' log?' : 'Clear the whole activity log?' ) . '" data-bmk-confirm-text="Entries are deleted for good. Export a CSV first if you might need them.">Clear log</button>';
        echo '</div></form>';

        if( ! $result['items'] ) {
            echo '<div class="bmk-empty"><h3>' . ui::icon( 'activity' ) . esc_html( $search ? 'Nothing matches that search' : 'Nothing logged yet' ) . '</h3><p>' . esc_html( $search ? 'Try a username, an IP address or part of a message.' : 'Sign-ins appear here when the sign-in log is on. Copies of the store also log redirected email and blocked requests.' ) . '</p></div>'; // phpcs:ignore
            return;
        }

        echo '<div class="bmk-table-wrap"><table class="bmk-table"><thead><tr><th>When</th><th>Type</th><th>What happened</th><th>User</th><th>IP address</th><th><span class="screen-reader-text">Details</span></th></tr></thead><tbody class="bmk-tnum">';
        $tones = [ 'success' => 'ok', 'warning' => 'warn', 'error' => 'danger', 'info' => 'off' ];
        foreach( $result['items'] as $row ) {
            $details = '';
            if( $row->channel === 'mail' && ! empty( $row->context['body'] ) ) {
                $details = '<button type="button" class="bmk-btn bmk-btn-ghost bmk-btn-sm" data-bmk-mail="' . esc_attr( wp_json_encode( [
                    'to'      => $row->context['to'] ?? '',
                    'subject' => $row->context['subject'] ?? '',
                    'html'    => ! empty( $row->context['html'] ),
                    'body'    => $row->context['body'] ?? '',
                    'when'    => wp_date( 'M j, Y g:i A', strtotime( $row->created_at . ' UTC' ) ),
                ] ) ) . '">View</button>';
            }
            echo '<tr>';
            echo '<td title="' . esc_attr( wp_date( 'M j, Y g:i:s A', strtotime( $row->created_at . ' UTC' ) ) ) . '">' . esc_html( ui::ago( $row->created_at ) ) . '</td>';
            echo '<td>' . ui::pill( $channels[ $row->channel ] ?? $row->channel, $tones[ $row->level ] ?? 'off' ) . '</td>'; // phpcs:ignore
            echo '<td>' . esc_html( $row->message ) . '</td>';
            echo '<td>' . ( $row->username !== '' ? '<b>' . esc_html( $row->username ) . '</b>' : '<span class="bmk-muted">-</span>' ) . '</td>';
            echo '<td class="bmk-mono">' . esc_html( $row->ip ) . '</td>';
            echo '<td class="bmk-right">' . $details . '</td>'; // phpcs:ignore
            echo '</tr>';
        }
        echo '</tbody></table></div>';

        if( $pages > 1 ) {
            echo '<nav class="bmk-pager" aria-label="Activity pages"><span>' . esc_html( sprintf( 'Page %1$d of %2$d, %3$s entries', $paged, $pages, number_format_i18n( $result['total'] ) ) ) . '</span>';
            $args = array_filter( [ 'channel' => $channel, 's' => $search ] );
            if( $paged > 1 ) echo '<a class="bmk-btn bmk-btn-secondary bmk-btn-sm" href="' . esc_url( app::url( 'activity', $args + [ 'paged' => $paged - 1 ] ) ) . '">Newer</a>';
            if( $paged < $pages ) echo '<a class="bmk-btn bmk-btn-secondary bmk-btn-sm" href="' . esc_url( app::url( 'activity', $args + [ 'paged' => $paged + 1 ] ) ) . '">Older</a>';
            echo '</nav>';
        }

    }

    // ------------------------------------------------------------------
    // Performance
    // ------------------------------------------------------------------

    private static function page_performance(): void {

        if( class_exists( 'WooCommerce' ) ) {
            echo ui::group( 'performance', 'woocommerce', 'WooCommerce', 'Cut the extra requests and scripts WooCommerce adds to every page.' ); // phpcs:ignore
        }
        echo ui::group( 'performance', 'frontend', 'Front end', 'Lighter pages for visitors.' ); // phpcs:ignore

        $after = '';
        if( settings::is_on( 'kit_bundle_enabled' ) ) {
            $detected = get_transient( 'kit_detected_assets' );
            $after .= '<div class="bmk-bundler" data-bmk-bundler>';
            if( ! is_array( $detected ) || ( empty( $detected['js'] ) && empty( $detected['css'] ) ) ) {
                $after .= '<div class="bmk-empty"><h3>' . ui::icon( 'layers' ) . 'No files found yet</h3><p>Kit finds scripts and stylesheets while signed-out visitors browse. Open a few pages in a private window, then come back.</p></div>';
            } else {
                $js_sel  = (array) get_option( 'kit_bundled_js_handles', [] );
                $css_sel = (array) get_option( 'kit_bundled_css_handles', [] );
                $after  .= '<div class="bmk-bundle-cols">';
                foreach( [ 'js' => [ 'Scripts', $js_sel ], 'css' => [ 'Stylesheets', $css_sel ] ] as $type => [ $label, $selected ] ) {
                    $after .= '<fieldset class="bmk-bundle-list" data-type="' . esc_attr( $type ) . '"><legend>' . esc_html( $label ) . '</legend>';
                    foreach( (array) ( $detected[ $type ] ?? [] ) as $handle => $info ) {
                        $after .= '<label class="bmk-check"><input type="checkbox" value="' . esc_attr( $handle ) . '"' . checked( in_array( $handle, $selected, true ), true, false ) . '> <span class="bmk-mono">' . esc_html( $handle ) . '</span></label>';
                    }
                    $after .= '</fieldset>';
                }
                $after .= '</div>';
                $built  = get_option( 'kit_bundle_version' );
                $after .= '<div class="bmk-bundle-foot"><span class="bmk-help">' . esc_html( $built ? 'Last built ' . human_time_diff( (int) $built ) . ' ago. Bundles rebuild every day.' : 'Not built yet.' ) . ' Cart and checkout pages always use the original files.</span><span class="bmk-sp"></span><button type="button" class="bmk-btn bmk-btn-secondary" data-bmk-bundle-save>Save selection</button><button type="button" class="bmk-btn bmk-btn-primary" data-bmk-bundle-rebuild><span class="bmk-spin" hidden></span><span>Rebuild bundles</span></button></div>';
            }
            $after .= '</div>';
        }
        echo ui::group( 'performance', 'bundler', 'Combine files', 'Fewer requests for pages that load many small scripts and stylesheets.', '', $after ); // phpcs:ignore

    }

    // ------------------------------------------------------------------
    // Slack
    // ------------------------------------------------------------------

    private static function page_slack(): void {

        $token = get_option( 'built_slack_token' );

        if( empty( $token ) ) {
            $key = (string) get_option( 'built_api_key' );
            if( $key === '' ) {
                $key = bin2hex( random_bytes( 16 ) );
                update_option( 'built_api_key', $key, false );
            }
            $state = base64_encode( http_build_query( [ 'site' => site_url(), 'key' => $key ] ) );
            $url   = 'https://slack.com/oauth/v2/authorize?scope=chat%3Awrite%2Cchannels%3Aread%2Cgroups%3Aread%2Cim%3Aread%2Cmpim%3Aread%2Cchannels%3Ajoin%2Cfiles%3Awrite&user_scope=&redirect_uri=https%3A%2F%2Fbuiltmighty.com%2Fwp-json%2Fbuiltmighty-kit%2Fv1%2Fslack&state=' . rawurlencode( $state ) . '&client_id=3387858095.7426170344038';
            echo '<section class="bmk-group"><div class="bmk-group-head"><div><h2>Connect Slack</h2><p>Kit posts alerts to your team\'s channel, and clients can message Built Mighty from the dashboard.</p></div>' . ui::pill( 'Not connected', 'off' ) . '</div>'; // phpcs:ignore
            echo '<div class="bmk-setting"><div class="bmk-setting-text"><span class="bmk-setting-title">Add the Built Mighty app to Slack</span><p class="bmk-setting-desc">You\'ll approve access in Slack, then come back here to pick a channel.</p></div><a class="bmk-btn bmk-btn-primary" href="' . esc_url( $url ) . '">Add to Slack</a></div>';
            echo '</section>';
            return;
        }

        $slack    = new \BuiltMightyKit\Utility\slack();
        $channels = $slack->get_channels();
        unset( $channels[''] );
        $notes    = ( new \BuiltMightyKit\Private\notifications() )->get_notifications();

        $channel_field = settings::field( 'slack-channel' );
        $channel_field['type'] = 'enum';
        $channel_field['select'] = true;
        $channel_field['options'] = [ '' => 'Choose a channel' ] + (array) $channels;
        $notes_field = settings::field( 'slack-notifications' );
        $notes_field['options'] = $notes;

        $connected = '<div class="bmk-group-foot"><button type="button" class="bmk-linkbtn" data-bmk-action="slack/channels">Refresh channel list</button><span class="bmk-sp"></span><button type="button" class="bmk-btn bmk-btn-danger-soft bmk-btn-sm" data-bmk-action="slack/disconnect" data-bmk-confirm-title="Disconnect Slack?" data-bmk-confirm-text="Alerts and client messages stop until someone connects Slack again.">Disconnect Slack</button></div>';

        echo '<section class="bmk-group"><div class="bmk-group-head"><div><h2>Slack</h2><p>Where alerts go and which ones to send.</p></div>' . ui::pill( settings::get( 'slack-channel' ) ? 'Connected' : 'Pick a channel', settings::get( 'slack-channel' ) ? 'ok' : 'warn' ) . '</div>'; // phpcs:ignore
        echo ui::row( 'slack-channel', $channel_field ); // phpcs:ignore
        echo '<div class="bmk-setting"><div class="bmk-setting-text bmk-span">' . ui::field( 'slack-notifications', $notes_field ) . '</div></div>'; // phpcs:ignore
        echo $connected; // phpcs:ignore
        echo '</section>';

        echo ui::group( 'slack', 'summary', 'Daily summary', 'Group the day\'s alerts into one message.' ); // phpcs:ignore

    }

    // ------------------------------------------------------------------
    // Monitoring
    // ------------------------------------------------------------------

    private static function page_monitoring(): void {

        $key  = (string) get_option( 'kit_crm_api_key', '' );
        $last = get_option( 'kit_crm_last_report', '' );

        if( $key === '' ) {
            $status = '<div class="bmk-group-note"><span class="bmk-label">Connection</span><p class="bmk-help">This site isn\'t connected to Built Mighty yet. Connecting creates an API key for the daily report.</p><div class="bmk-actions"><button type="button" class="bmk-btn bmk-btn-primary" data-bmk-crm="kit_crm_register">Connect this site</button></div></div>';
        } else {
            $masked = substr( $key, 0, 4 ) . str_repeat( '•', 8 ) . substr( $key, -4 );
            $status = '<div class="bmk-group-note"><span class="bmk-label">Connection</span><dl class="bmk-envdl"><dt>API key</dt><dd><code>' . esc_html( $masked ) . '</code></dd><dt>Last report</dt><dd>' . esc_html( $last ? ( is_numeric( $last ) ? wp_date( 'M j, Y g:i A', (int) $last ) : (string) $last ) : 'Not sent yet' ) . '</dd></dl>'
                . '<div class="bmk-actions"><button type="button" class="bmk-btn bmk-btn-secondary bmk-btn-sm" data-bmk-crm="kit_crm_test_connection">Test connection</button><button type="button" class="bmk-btn bmk-btn-secondary bmk-btn-sm" data-bmk-crm="kit_crm_send_report_now">Send report now</button>'
                . ( class_exists( 'WooCommerce' ) ? '<button type="button" class="bmk-btn bmk-btn-ghost bmk-btn-sm" data-bmk-crm="kit_crm_run_backfill">Send past 2 years of sales</button>' : '' ) . '</div></div>';
        }

        $key_field = '<div class="bmk-group-note"><label class="bmk-label" for="bmk-crm-key">Replace API key</label><div class="bmk-inline"><input type="password" class="bmk-input bmk-mono" id="bmk-crm-key" autocomplete="off" placeholder="Paste a new key"><button type="button" class="bmk-btn bmk-btn-secondary" data-bmk-crm-key>Save key</button></div><span class="bmk-help">Only needed if Built Mighty gives you a new key.</span></div>';

        echo ui::group( 'monitoring', 'crm', 'Daily report', 'What Kit sends to Built Mighty each night.', settings::is_on( 'kit_crm_enabled' ) && $key ? ( \BuiltMightyKit\is_kit_mode() ? ui::pill( 'Paused on this copy', 'warn' ) : ui::pill( 'Sending', 'ok' ) ) : ui::pill( 'Off', 'off' ), $status . $key_field ); // phpcs:ignore

    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    private static function page_settings(): void {

        $source = environment::source();
        $note = '<div class="bmk-group-note"><span class="bmk-label">Right now</span><p class="bmk-detect">' . ui::env_chip() . ' ' . esc_html( $source['label'] ) . ( $source['detail'] !== '' ? ' <code>' . esc_html( $source['detail'] ) . '</code>' : '' ) . '</p></div>'; // phpcs:ignore
        if( get_option( 'kit_confirm_live_url' ) ) {
            $note .= '<div class="bmk-group-note">' . ui::alert( 'info', 'Kit saved this address as the live store when it updated', 'Older versions treated any site without WP_ENVIRONMENT_TYPE as live. Check the address below is the real store.', '<button type="button" class="bmk-btn bmk-btn-primary bmk-btn-sm" data-bmk-action="environment/confirm">That\'s right</button>' ) . '</div>';
        }
        echo ui::group( 'settings', 'environment', 'Environment', 'How Kit decides whether this is the live store or a copy.', '', $note ); // phpcs:ignore
        echo ui::group( 'settings', 'admin', 'wp-admin', 'Small changes for the people who run the store.' ); // phpcs:ignore

    }

    // ------------------------------------------------------------------
    // Other Built Mighty plugins (shared settings framework)
    // ------------------------------------------------------------------

    private static function page_plugins(): void {
        global $wp_settings_sections, $wp_settings_fields;
        $page = 'built-mighty-global-settings';
        echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" class="bmk-legacy">';
        settings_fields( 'built_mighty_global_settings_group' );
        foreach( (array) ( $wp_settings_sections[ $page ] ?? [] ) as $id => $section ) {
            if( empty( $wp_settings_fields[ $page ][ $id ] ) ) continue;
            echo '<section class="bmk-group"><div class="bmk-group-head"><div><h2>' . esc_html( wp_strip_all_tags( (string) $section['title'] ) ) . '</h2>';
            if( ! empty( $section['callback'] ) && is_callable( $section['callback'] ) ) {
                echo '<div class="bmk-legacy-desc">';
                call_user_func( $section['callback'], $section );
                echo '</div>';
            }
            echo '</div></div><div class="bmk-legacy-fields">';
            do_settings_fields( $page, $id );
            echo '</div></section>';
        }
        echo '<p><button type="submit" class="bmk-btn bmk-btn-primary">Save changes</button></p></form>';
    }

}
