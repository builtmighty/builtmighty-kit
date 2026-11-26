<?php
/**
 * Kit admin app.
 *
 * The Built Mighty menu, the Kit shell (navigation, top bar, environment
 * strip, save bar) and page routing. Pages render from the settings schema;
 * changes are saved through the REST API (see Admin\rest).
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Admin;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\environment;

if( ! defined( 'ABSPATH' ) ) { exit; }

class app {

    const SLUG = 'builtmighty';

    /**
     * Construct.
     */
    public function __construct() {

        // Kit owns the Built Mighty menu; the shared settings framework must not add a second one.
        if( ! defined( 'BUILT_MIGHTY_GLOBAL_SETTINGS_LOADED' ) ) define( 'BUILT_MIGHTY_GLOBAL_SETTINGS_LOADED', true );

        add_action( 'admin_menu', [ $this, 'menu' ], 9 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_filter( 'admin_body_class', [ $this, 'body_class' ] );
        add_action( 'admin_init', [ $this, 'site_check' ] );
        add_action( 'admin_notices', [ $this, 'confirm_live_url_notice' ] );
        add_action( 'admin_post_kit_export_activity', [ $this, 'export_activity' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( KIT_PATH . 'builtmighty-kit.php' ), [ $this, 'action_links' ] );

    }

    /**
     * Pages: slug => [ title, icon, nav group, description ].
     */
    public static function pages(): array {
        $pages = [
            'overview'    => [ 'Overview', 'home', '', '' ],
            'safeguards'  => [ 'Safeguards', 'shield', 'This environment', 'What this copy is kept from doing.' ],
            'login'       => [ 'Login/2FA', 'key', 'Security', 'Who can sign in, how, and from where.' ],
            'headers'     => [ 'Headers/API', 'server', 'Security', 'How browsers and API clients are allowed to use this site.' ],
            'spam'        => [ 'Spam', 'ban', 'Security', 'Keep bots out of comments.' ],
            'activity'    => [ 'Activity', 'activity', 'Security', 'Sign-ins, blocked requests, redirected email and other events.' ],
            'performance' => [ 'Performance', 'bolt', 'Site', 'Make pages lighter and faster.' ],
            'slack'       => [ 'Slack alerts', 'bell', 'Site', 'Tell the team when something important changes.' ],
            'monitoring'  => [ 'Monitoring', 'gauge', 'Site', 'A daily health report for Built Mighty.' ],
            'settings'    => [ 'Settings', 'gear', 'Site', 'Environment detection and wp-admin preferences.' ],
        ];
        if( self::has_external_sections() ) {
            $pages['plugins'] = [ 'Other Built Mighty plugins', 'layers', 'Site', 'Settings from other Built Mighty plugins.' ];
        }
        /**
         * Filter Kit admin pages.
         *
         * @since 6.0.0
         */
        return (array) apply_filters( 'kit_admin_pages', $pages );
    }

    /**
     * Do other plugins register sections through the shared settings framework?
     */
    public static function has_external_sections(): bool {
        global $wp_settings_fields;
        return ! empty( $wp_settings_fields['built-mighty-global-settings'] );
    }

    /**
     * Current page slug.
     */
    public static function current(): string {
        $view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'overview';
        return array_key_exists( $view, self::pages() ) ? $view : 'overview';
    }

    /**
     * URL to a Kit page.
     */
    public static function url( string $view = 'overview', array $args = [] ): string {
        $base = admin_url( 'admin.php?page=' . self::SLUG );
        if( $view !== 'overview' ) $args = [ 'view' => $view ] + $args;
        return $args ? add_query_arg( $args, $base ) : $base;
    }

    /**
     * Is this a Kit screen?
     */
    public static function is_screen( $hook = '' ): bool {
        if( $hook === '' ) {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            $hook   = $screen ? $screen->id : '';
        }
        return $hook === 'toplevel_page_' . self::SLUG;
    }

    /**
     * Menu.
     */
    public function menu() {
        // The Built Mighty B mark (same icon as the shared settings framework).
        $icon = 'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIKCSB3aWR0aD0iMjBweCIgaGVpZ2h0PSIyMHB4IiB2aWV3Qm94PSIwIDAgMjAgMjAiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDIwIDIwIiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPGc+Cgk8cGF0aCBmaWxsPSIjRDQyMDI3IiBkPSJNMTguMDk0LDEzLjgzNmwtMy43NzgtMy43ODFsMy43NzgtMy43NzZWMEgxLjkwNXY0LjM1N2gxMS45NDNsLTIuNjEzLDIuNjE3bC0wLjg0NywwLjg0NWwtMi4yMzEsMi4yMzUKCQlsMi4xMiwyLjEyNmwwLjk1OCwwLjk1OGwyLjUwMiwyLjUwNEgxLjkwNVYyMGgxNi4xODhWMTMuODM2eiIvPgoJPHBhdGggZmlsbD0iI0Q0MjAyNyIgZD0iTTQuNDIsMTAuMDU4bDIuMjM2LTIuMjM4SDEuOTA1djQuMzYxaDQuNjM0bC0xLjExNy0xLjEyTDQuNDIsMTAuMDU4eiIvPgo8L2c+Cjwvc3ZnPg==';
        add_menu_page( 'Built Mighty Kit', 'Built Mighty', 'manage_options', self::SLUG, [ $this, 'render' ], $icon, 81 );
    }

    /**
     * Plugins screen link.
     */
    public function action_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Open Kit</a>' );
        return $links;
    }

    /**
     * Body class for Kit screens.
     */
    public function body_class( $classes ) {
        if( self::is_screen() ) $classes .= ' bmk-screen';
        return $classes;
    }

    /**
     * Assets, only on Kit screens.
     */
    public function enqueue( $hook ) {

        if( ! self::is_screen( $hook ) ) return;

        wp_enqueue_style( 'bmk-fonts', 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Hanken+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap', [], null );
        wp_enqueue_style( 'bmk-admin', KIT_URI . 'public/css/kit-admin.css', [ 'bmk-fonts' ], KIT_VERSION );
        wp_enqueue_script( 'bmk-admin', KIT_URI . 'public/js/kit-admin.js', [], KIT_VERSION, true );

        // Index of every setting for the Ctrl K search.
        $index = [];
        $pages = self::pages();
        foreach( settings::schema() as $key => $field ) {
            $page = $field['page'] ?? '';
            if( ! isset( $pages[ $page ] ) ) continue;
            $index[] = [
                'key'   => $key,
                'label' => $field['label'],
                'desc'  => wp_strip_all_tags( (string) ( $field['desc'] ?? '' ) ),
                'page'  => $pages[ $page ][0],
                'url'   => self::url( $page ) . '#bmk-row-' . $key,
            ];
        }
        foreach( $pages as $slug => $page ) {
            $index[] = [ 'key' => 'page-' . $slug, 'label' => $page[0], 'desc' => $page[3], 'page' => 'Page', 'url' => self::url( $slug ) ];
        }

        wp_localize_script( 'bmk-admin', 'bmkKit', [
            'rest'    => esc_url_raw( rest_url( 'builtmighty-kit/v1/' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonces'  => [
                'bundle'      => wp_create_nonce( 'kit_rebuild_bundle' ),
                'crm'         => wp_create_nonce( 'kit_crm_actions' ),
                'crmRegister' => wp_create_nonce( 'kit_crm_register' ),
            ],
            'search'  => $index,
            'view'    => self::current(),
        ] );

        // The shared framework's scripts are only needed for other plugins' fields.
        if( self::current() === 'plugins' ) {
            wp_enqueue_media();
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
        }

    }

    /**
     * Purge the Kinsta cache when the site URL changes (a copy was made).
     */
    public function site_check() {
        $stored = get_option( 'built_siteurl' );
        if( $stored === site_url() ) return;
        update_option( 'built_siteurl', site_url() );
        if( $stored && class_exists( '\Kinsta\Cache_Purge' ) ) {
            ( new \Kinsta\Cache_Purge() )->purge_complete_caches();
        }
    }

    /**
     * After upgrading, ask an administrator to confirm the live store URL.
     */
    public function confirm_live_url_notice() {
        if( ! current_user_can( 'manage_options' ) ) return;
        if( ! get_option( 'kit_confirm_live_url' ) ) return;
        $url = environment::live_url();
        echo '<div class="notice notice-info bmk-wp-notice"><p><strong>Built Mighty Kit saved ' . esc_html( $url ) . ' as the live store.</strong> Copies of the store at any other address will keep email, payments and outside services safe. <a href="' . esc_url( self::url( 'settings' ) ) . '">Check environment settings</a></p></div>';
    }

    /**
     * CSV export of the activity log.
     */
    public function export_activity() {
        if( ! current_user_can( 'manage_options' ) ) wp_die( 'You need to be an administrator to export the log.', 403 );
        check_admin_referer( 'kit_export_activity' );
        $channel = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '';
        $result  = \BuiltMightyKit\Utility\activity::query( [ 'channel' => $channel, 'per_page' => 500 ] );
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=kit-activity-' . gmdate( 'Y-m-d' ) . '.csv' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Time (UTC)', 'Type', 'Event', 'Level', 'User', 'IP address', 'Message' ] );
        // Usernames from failed sign-ins are attacker-chosen: stop spreadsheets reading them as formulas.
        $cell = function( $v ) { $v = (string) $v; return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v; };
        foreach( $result['items'] as $row ) {
            fputcsv( $out, array_map( $cell, [ $row->created_at, $row->channel, $row->event, $row->level, $row->username, $row->ip, $row->message ] ) );
        }
        fclose( $out );
        exit;
    }

    /**
     * Render the current page inside the Kit shell.
     */
    public function render() {

        if( ! current_user_can( 'manage_options' ) ) wp_die( 'You need to be an administrator to open Kit.', 403 );

        $view  = self::current();
        $pages = self::pages();
        $env   = environment::type();
        $user  = wp_get_current_user();
        $initials = strtoupper( substr( $user->first_name ?: $user->display_name, 0, 1 ) . substr( $user->last_name, 0, 1 ) );

        echo ui::sprite();
        echo '<div class="bmk-app" data-env="' . esc_attr( $env ) . '" data-view="' . esc_attr( $view ) . '">';
        echo '<div class="bmk-shell">';

        // Navigation.
        echo '<aside class="bmk-side" aria-label="Kit navigation">';
        echo '<a class="bmk-lock" href="' . esc_url( self::url() ) . '"><span class="bmk-mark" aria-hidden="true"></span><span class="bmk-kitword">Kit</span></a>';
        echo '<nav>';
        $group = null;
        foreach( $pages as $slug => $page ) {
            if( $page[2] !== $group ) {
                $group = $page[2];
                if( $group !== '' ) echo '<div class="bmk-navgrp">' . esc_html( $group ) . '</div>';
            }
            $count = $slug === 'activity' ? self::warnings_today() : 0;
            printf(
                '<a href="%s"%s>%s<span>%s</span>%s</a>',
                esc_url( self::url( $slug ) ),
                $slug === $view ? ' class="bmk-on" aria-current="page"' : '',
                ui::icon( $page[1] ), // phpcs:ignore
                esc_html( $page[0] ),
                $count ? '<span class="bmk-count" title="' . esc_attr( $count . ' warnings today' ) . '">' . (int) $count . '</span>' : ''
            );
        }
        echo '</nav>';
        echo '<div class="bmk-side-foot"><span>Kit ' . esc_html( KIT_VERSION ) . '</span><a href="https://builtmighty.com" target="_blank" rel="noopener">builtmighty.com</a></div>';
        echo '</aside>';

        // Content.
        echo '<div class="bmk-content">';
        echo '<div class="bmk-envstrip"></div>';
        echo '<div class="bmk-topbar">';
        echo ui::env_chip( $env ); // phpcs:ignore
        echo '<button type="button" class="bmk-search" data-bmk-search>' . ui::icon( 'search', 'bmk-sm' ) . '<span>Search settings</span><kbd>Ctrl K</kbd></button>'; // phpcs:ignore
        echo '<div class="bmk-who"><span>' . esc_html( $user->display_name ) . '</span><span class="bmk-avatar" aria-hidden="true">' . esc_html( $initials ?: '?' ) . '</span></div>';
        echo '</div>';

        echo '<div class="bmk-pagebody">';
        if( $view !== 'overview' ) {
            echo '<header class="bmk-pagehead"><div><h1>' . esc_html( $pages[ $view ][0] ) . '</h1>';
            if( $pages[ $view ][3] !== '' ) echo '<p>' . esc_html( $pages[ $view ][3] ) . '</p>';
            echo '</div></header>';
        }
        pages::render( $view );
        echo '</div>';

        echo '<div class="bmk-savebar" role="region" aria-label="Unsaved changes"><span><b data-bmk-dirty>1 change</b> not saved yet</span><span class="bmk-sp"></span><button type="button" class="bmk-btn bmk-btn-ghost bmk-btn-sm" data-bmk-discard>Discard</button><button type="button" class="bmk-btn bmk-btn-light bmk-btn-sm" data-bmk-save>Save changes</button></div>';
        echo '</div>'; // content
        echo '</div>'; // shell

        // Toasts, dialog, search.
        echo '<div class="bmk-toasts" aria-live="polite"></div>';
        echo '<div class="bmk-scrim" data-bmk-dialog hidden><div class="bmk-dialog" role="alertdialog" aria-modal="true" aria-labelledby="bmk-dlg-t" aria-describedby="bmk-dlg-d"><div class="bmk-dh">' . ui::icon( 'alert', 'bmk-lg' ) . '<h2 id="bmk-dlg-t"></h2></div><p id="bmk-dlg-d"></p><label class="bmk-check" data-bmk-ack hidden><input type="checkbox"> <span></span></label><div class="bmk-dialog-body" data-bmk-dialog-body></div><div class="bmk-foot"><button type="button" class="bmk-btn bmk-btn-secondary" data-bmk-cancel>Cancel</button><button type="button" class="bmk-btn bmk-btn-danger" data-bmk-confirm>Confirm</button></div></div></div>'; // phpcs:ignore
        echo '<div class="bmk-scrim bmk-palette-scrim" data-bmk-palette hidden><div class="bmk-palette" role="dialog" aria-modal="true" aria-label="Search settings"><div class="bmk-palette-in">' . ui::icon( 'search' ) . '<input type="search" placeholder="Search settings and pages" aria-label="Search settings and pages" autocomplete="off"></div><ul role="listbox"></ul><p class="bmk-palette-empty" hidden>No settings match. Try a different word.</p></div></div>'; // phpcs:ignore

        echo '</div>';

    }

    /**
     * Warnings logged today (for the Activity badge).
     */
    public static function warnings_today(): int {
        $cached = wp_cache_get( 'kit_warn_today', 'kit' );
        if( $cached !== false ) return (int) $cached;
        global $wpdb;
        $table = \BuiltMightyKit\Utility\activity::table();
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE level IN ('warning','error') AND created_at >= %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
        wp_cache_set( 'kit_warn_today', $count, 'kit', 5 * MINUTE_IN_SECONDS );
        return $count;
    }

}
