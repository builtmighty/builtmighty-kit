<?php
/**
 * Security health checks.
 *
 * A registry of checks shown on Kit's Health page, in WordPress's Site
 * Health screen, and (on the live store) in the Overview hardening meter.
 * Results are cached for 12 hours.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Private;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\environment;
use BuiltMightyKit\Admin\ui;
use BuiltMightyKit\Admin\app;

if( ! defined( 'ABSPATH' ) ) { exit; }

class health {

    const CACHE = 'kit_health_results';

    /**
     * Construct.
     */
    public function __construct() {
        add_filter( 'kit_admin_pages', [ $this, 'page' ] );
        add_action( 'kit_admin_page_health', [ $this, 'render' ] );
        add_filter( 'kit_hardening', [ $this, 'hardening' ] );
        add_filter( 'site_status_tests', [ $this, 'site_health' ] );
        add_action( 'rest_api_init', [ $this, 'route' ] );
        add_action( 'kit_settings_saved', [ __CLASS__, 'flush' ] );
        add_action( 'activated_plugin', [ __CLASS__, 'flush' ] );
        add_action( 'deactivated_plugin', [ __CLASS__, 'flush' ] );
    }

    /**
     * Forget cached results.
     */
    public static function flush(): void {
        delete_transient( self::CACHE );
    }

    /**
     * Check definitions.
     *
     * Each returns [ status (good|recommended|critical), label, description, page (Kit page or URL), action label ].
     *
     * @return array
     */
    public static function checks(): array {

        $checks = [

            'live_url' => function() {
                $set = environment::live_url() !== '';
                return [ $set ? 'good' : 'recommended', $set ? 'The live store address is saved' : 'Save the live store address',
                    $set ? 'Copies at any other address are recognised automatically.' : 'Without it, a copy without WP_ENVIRONMENT_TYPE could be mistaken for the live store.',
                    'settings', 'Open environment settings' ];
            },

            'debug_display' => function() {
                $showing = defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ) && ! environment::is_kit();
                return [ $showing ? 'critical' : 'good', $showing ? 'PHP errors are shown to visitors' : 'PHP errors are hidden from visitors',
                    $showing ? 'WP_DEBUG is on with errors displayed. Error messages can reveal file paths and database details. Set WP_DEBUG_DISPLAY to false in wp-config.php.' : 'Errors aren\'t printed on live pages.',
                    '', '' ];
            },

            'https' => function() {
                $https = strpos( home_url(), 'https://' ) === 0;
                return [ $https ? 'good' : 'critical', $https ? 'The site uses HTTPS' : 'The site address isn\'t HTTPS',
                    $https ? 'Sign-ins and checkout are encrypted.' : 'Passwords and payment details can be read in transit. Switch the site and WordPress addresses to https://.',
                    $https ? '' : admin_url( 'options-general.php' ), $https ? '' : 'Open general settings' ];
            },

            'hsts' => function() {
                $on = settings::is_on( 'kit_security_headers' ) && settings::is_on( 'kit_header_hsts' );
                return [ $on ? 'good' : 'recommended', $on ? 'Browsers always use HTTPS (HSTS)' : 'Tell browsers to always use HTTPS',
                    $on ? 'HSTS is sent with every response.' : 'HSTS stops anyone from downgrading a visitor to plain HTTP.',
                    'headers', 'Review headers' ];
            },

            'two_factor' => function() {
                if( ! settings::is_on( 'kit_enable_2fa' ) ) {
                    return [ environment::is_kit() ? 'recommended' : 'critical', 'Two-factor sign-in is off', 'A stolen administrator password is enough to take over the store.', 'login', 'Turn on two-factor' ];
                }
                $auth = new \BuiltMightyKit\Utility\authentication();
                $no_app = 0;
                foreach( get_users( [ 'role' => 'administrator', 'fields' => [ 'ID' ], 'number' => 200 ] ) as $u ) {
                    if( ! $auth->is_enabled( get_userdata( $u->ID ) ) ) $no_app++;
                }
                return [ 'good', 'Two-factor is required for administrators',
                    $no_app ? sprintf( _n( '%d administrator still uses emailed codes instead of an app.', '%d administrators still use emailed codes instead of an app.', $no_app, 'builtmighty-kit' ), $no_app ) : 'Every administrator uses an authenticator app.',
                    'login', 'Review sign-in settings' ];
            },

            'admin_username' => function() {
                $exists = (bool) username_exists( 'admin' );
                return [ $exists ? 'recommended' : 'good', $exists ? 'An account is named "admin"' : 'No account is named "admin"',
                    $exists ? 'Bots try "admin" first. Create a new administrator with a less obvious name and delete this one.' : 'Bots have to guess usernames.',
                    $exists ? admin_url( 'users.php?s=admin' ) : '', $exists ? 'Find the account' : '' ];
            },

            'registration' => function() {
                $open = (bool) get_option( 'users_can_register' );
                $role = (string) get_option( 'default_role' );
                $risky = $open && ! in_array( $role, [ 'subscriber', 'customer' ], true );
                return [ $risky ? 'critical' : 'good', $risky ? 'Anyone can register as ' . translate_user_role( ucfirst( $role ) ) : ( $open ? 'Registration gives new accounts the lowest role' : 'Registration is closed' ),
                    $risky ? 'Open registration with a role above Subscriber lets strangers edit the site. Change the default role or close registration.' : 'New accounts can\'t change the site.',
                    $risky ? admin_url( 'options-general.php' ) : '', $risky ? 'Open general settings' : '' ];
            },

            'file_editing' => function() {
                $off = ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) || settings::is_on( 'kit_disable_editor' );
                return [ $off ? 'good' : 'recommended', $off ? 'The theme and plugin file editors are off' : 'The theme and plugin file editors are on',
                    $off ? 'Code can only change through deployments.' : 'Anyone with an administrator password can change code from wp-admin, which bypasses the repository.',
                    'settings', 'Turn off the editors' ];
            },

            'salts' => function() {
                $weak = defined( 'AUTH_KEY' ) && ( AUTH_KEY === '' || stripos( AUTH_KEY, 'put your unique phrase here' ) !== false );
                return [ $weak ? 'critical' : 'good', $weak ? 'wp-config.php still has the default security keys' : 'Security keys are set',
                    $weak ? 'Anyone can forge sign-in cookies. Generate new keys at api.wordpress.org/secret-key/1.1/salt/ and paste them into wp-config.php.' : 'Sign-in cookies can\'t be forged.',
                    '', '' ];
            },

            'php' => function() {
                $v = PHP_VERSION;
                $status = version_compare( $v, '8.2', '<' ) ? ( version_compare( $v, '8.1', '<' ) ? 'critical' : 'recommended' ) : 'good';
                return [ $status, $status === 'good' ? 'PHP ' . $v . ' is supported' : 'PHP ' . $v . ' is out of security support',
                    $status === 'good' ? 'Your host is running a PHP version that still gets security fixes.' : 'Ask your host to move the site to PHP 8.2 or newer.',
                    '', '' ];
            },

            'outdated_plugins' => function() {
                $updates = get_site_transient( 'update_plugins' );
                $n = isset( $updates->response ) ? count( (array) $updates->response ) : 0;
                return [ $n ? 'recommended' : 'good', $n ? sprintf( _n( '%d plugin has an update', '%d plugins have updates', $n, 'builtmighty-kit' ), $n ) : 'Plugins are up to date',
                    $n ? 'Updates often fix security problems. Built Mighty tests them on staging before they reach the live store.' : 'Nothing is waiting to update.',
                    $n ? admin_url( 'plugins.php?plugin_status=upgrade' ) : '', $n ? 'See updates' : '' ];
            },

            'inactive_plugins' => function() {
                if( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
                $active = (array) get_option( 'active_plugins', [] );
                if( is_multisite() ) $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
                $n = count( array_diff( array_keys( get_plugins() ), $active ) );
                return [ $n ? 'recommended' : 'good', $n ? sprintf( _n( '%d plugin is installed but inactive', '%d plugins are installed but inactive', $n, 'builtmighty-kit' ), $n ) : 'No inactive plugins',
                    $n ? 'Inactive plugins can still be attacked through their files. Delete the ones you don\'t use.' : 'Nothing unused is lying around.',
                    $n ? admin_url( 'plugins.php?plugin_status=inactive' ) : '', $n ? 'See inactive plugins' : '' ];
            },

            'table_prefix' => function() {
                global $wpdb;
                $default = $wpdb->prefix === 'wp_';
                return [ $default ? 'recommended' : 'good', $default ? 'Database tables use the default wp_ prefix' : 'Database tables use a custom prefix',
                    $default ? 'Low risk, but a custom prefix makes automated SQL injection attacks slightly harder. Change it only during a planned migration.' : 'Automated attacks can\'t assume table names.',
                    '', '' ];
            },

            'config_writable' => function() {
                $file = file_exists( ABSPATH . 'wp-config.php' ) ? ABSPATH . 'wp-config.php' : dirname( ABSPATH ) . '/wp-config.php';
                $writable = file_exists( $file ) && is_writable( $file );
                return [ $writable ? 'recommended' : 'good', $writable ? 'wp-config.php can be changed by WordPress' : 'wp-config.php is read-only',
                    $writable ? 'If an attacker can run code, they can change your configuration. Ask your host to make it read-only (0440).' : 'Configuration can only change through your host.',
                    '', '' ];
            },

            'app_passwords' => function() {
                $count = 0;
                if( class_exists( '\WP_Application_Passwords' ) ) {
                    foreach( get_users( [ 'role' => 'administrator', 'fields' => [ 'ID' ], 'number' => 200 ] ) as $u ) {
                        $count += count( \WP_Application_Passwords::get_user_application_passwords( $u->ID ) );
                    }
                }
                return [ $count ? 'recommended' : 'good', $count ? sprintf( _n( 'Administrators have %d application password', 'Administrators have %d application passwords', $count, 'builtmighty-kit' ), $count ) : 'No administrator application passwords',
                    $count ? 'Application passwords skip two-factor. Revoke any that aren\'t in use from each administrator\'s profile.' : 'Every administrator sign-in goes through two-factor.',
                    $count ? admin_url( 'users.php?role=administrator' ) : '', $count ? 'Review administrators' : '' ];
            },

        ];

        /**
         * Filter health checks.
         *
         * @since 6.0.0
         */
        return (array) apply_filters( 'kit_health_checks', $checks );

    }

    /**
     * Run (or read cached) results.
     */
    public static function results( bool $fresh = false ): array {
        if( ! $fresh ) {
            $cached = get_transient( self::CACHE );
            if( is_array( $cached ) ) return $cached;
        }
        $out = [];
        foreach( self::checks() as $key => $check ) {
            try {
                [ $status, $label, $desc, $page, $action ] = array_pad( (array) call_user_func( $check ), 5, '' );
            } catch( \Throwable $e ) {
                continue;
            }
            $out[ $key ] = compact( 'status', 'label', 'desc', 'page', 'action' );
        }
        $out['_checked'] = time();
        set_transient( self::CACHE, $out, 12 * HOUR_IN_SECONDS );
        return $out;
    }

    /**
     * Add the Health page.
     */
    public function page( $pages ) {
        $out = [];
        foreach( $pages as $slug => $page ) {
            $out[ $slug ] = $page;
            if( $slug === 'activity' ) $out['health'] = [ 'Health', 'heart', 'Security', 'Checks that keep the store hard to break into.' ];
        }
        return $out;
    }

    /**
     * Health page.
     */
    public function render() {

        $results = self::results( isset( $_GET['refresh'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'kit_health' ) );
        $checked = (int) ( $results['_checked'] ?? time() );
        unset( $results['_checked'] );

        $order = [ 'critical' => 0, 'recommended' => 1, 'good' => 2 ];
        uasort( $results, function( $a, $b ) use ( $order ) { return ( $order[ $a['status'] ] ?? 3 ) <=> ( $order[ $b['status'] ] ?? 3 ); } );
        $counts = array_count_values( array_column( $results, 'status' ) );

        $tone = ! empty( $counts['critical'] ) ? 'danger' : ( ! empty( $counts['recommended'] ) ? 'warn' : 'ok' );
        $head = ! empty( $counts['critical'] )
            ? sprintf( _n( '%d problem needs fixing', '%d problems need fixing', $counts['critical'], 'builtmighty-kit' ), $counts['critical'] )
            : ( ! empty( $counts['recommended'] ) ? sprintf( _n( '%d improvement recommended', '%d improvements recommended', $counts['recommended'], 'builtmighty-kit' ), $counts['recommended'] ) : 'Everything checks out' );

        echo '<section class="bmk-group"><div class="bmk-group-head"><div><h2>' . esc_html( $head ) . '</h2><p>' . esc_html( sprintf( 'Checked %s ago.', human_time_diff( $checked ) ) ) . '</p></div><a class="bmk-btn bmk-btn-secondary bmk-btn-sm" href="' . esc_url( wp_nonce_url( app::url( 'health', [ 'refresh' => 1 ] ), 'kit_health' ) ) . '">' . ui::icon( 'refresh', 'bmk-sm' ) . 'Check again</a></div>'; // phpcs:ignore
        echo '<ul class="bmk-plist">';
        $icons = [ 'critical' => 'alert', 'recommended' => 'info', 'good' => 'checkc' ];
        $pills = [ 'critical' => [ 'Fix now', 'danger' ], 'recommended' => [ 'Recommended', 'warn' ], 'good' => [ 'Good', 'ok' ] ];
        foreach( $results as $key => $r ) {
            $class = $r['status'] === 'critical' ? ' class="bmk-off"' : '';
            echo '<li' . $class . '><span class="bmk-pic">' . ui::icon( $icons[ $r['status'] ] ?? 'info' ) . '</span><div><b>' . esc_html( $r['label'] ) . '</b><small>' . esc_html( $r['desc'] ) . '</small></div><span class="bmk-meta">'; // phpcs:ignore
            if( $r['status'] !== 'good' && $r['action'] ) {
                $url = strpos( (string) $r['page'], 'http' ) === 0 ? $r['page'] : app::url( $r['page'] );
                echo '<a class="bmk-btn bmk-btn-secondary bmk-btn-sm" href="' . esc_url( $url ) . '">' . esc_html( $r['action'] ) . '</a>';
            } else {
                echo ui::pill( $pills[ $r['status'] ][0] ?? '', $pills[ $r['status'] ][1] ?? 'off' ); // phpcs:ignore
            }
            echo '</span></li>';
        }
        echo '</ul></section>';
        echo '<p class="bmk-help">These checks also appear under Tools, Site Health.</p>';

    }

    /**
     * Add failing checks to the live store's hardening list.
     */
    public function hardening( $items ) {
        if( environment::is_kit() ) return $items;
        $results = self::results();
        foreach( [ 'https', 'salts', 'debug_display', 'registration', 'admin_username', 'app_passwords' ] as $key ) {
            if( empty( $results[ $key ] ) ) continue;
            $items[ 'health_' . $key ] = [ 'done' => $results[ $key ]['status'] === 'good', 'label' => $results[ $key ]['status'] === 'good' ? $results[ $key ]['label'] : $results[ $key ]['label'], 'page' => 'health' ];
        }
        return $items;
    }

    /**
     * WordPress Site Health.
     */
    public function site_health( $tests ) {
        foreach( array_keys( self::checks() ) as $key ) {
            $tests['direct'][ 'kit_' . $key ] = [
                'label' => 'Built Mighty Kit',
                'test'  => function() use ( $key ) {
                    $r = self::results()[ $key ] ?? ( self::results( true )[ $key ] ?? null );
                    if( ! $r ) {
                        return [ 'label' => 'Built Mighty Kit check unavailable', 'status' => 'recommended', 'badge' => [ 'label' => 'Security', 'color' => 'blue' ],
                            'description' => '<p>This check couldn\'t run. Refresh it from Built Mighty, Health.</p>', 'actions' => '', 'test' => 'kit_' . $key ];
                    }
                    $url = strpos( (string) $r['page'], 'http' ) === 0 ? $r['page'] : ( $r['page'] ? app::url( $r['page'] ) : '' );
                    return [
                        'label'       => $r['label'],
                        'status'      => $r['status'],
                        'badge'       => [ 'label' => 'Security', 'color' => $r['status'] === 'critical' ? 'red' : 'blue' ],
                        'description' => '<p>' . esc_html( $r['desc'] ) . '</p>',
                        'actions'     => $url && $r['action'] ? '<p><a href="' . esc_url( $url ) . '">' . esc_html( $r['action'] ) . '</a></p>' : '',
                        'test'        => 'kit_' . $key,
                    ];
                },
            ];
        }
        return $tests;
    }

    /**
     * REST: refresh.
     */
    public function route() {
        register_rest_route( 'builtmighty-kit/v1', '/health', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'callback'            => function() { return [ 'results' => self::results( true ), 'message' => 'Checked.' ]; },
        ] );
    }

}
