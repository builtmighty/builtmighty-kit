<?php
/**
 * Safeguards and hardening.
 *
 * One list of what Kit protects, read by the Overview, the admin bar chip and
 * the dashboard widget, so every surface reports the same, true status.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Utility;

if( ! defined( 'ABSPATH' ) ) { exit; }

class safeguards {

    /**
     * Safeguards for copies of the store.
     *
     * Each: key (setting), icon, on (label when on), off (label when off),
     * detail (callable returning a short status line), new (bool).
     *
     * @return array
     */
    public static function copy(): array {

        $items = [
            'kit_block_email' => [
                'icon'   => 'mail',
                'on'     => 'Email goes to developers',
                'off'    => 'Email reaches real customers',
                'detail' => function() {
                    $n = activity::counts( 'mail', 7 )['redirected'] ?? 0;
                    return $n ? sprintf( _n( '%s email redirected this week', '%s emails redirected this week', $n, 'builtmighty-kit' ), number_format_i18n( $n ) ) : 'Sent to ' . settings::get( 'kit_email_redirect_to' );
                },
            ],
            'kit_block_external' => [
                'icon'   => 'globe',
                'on'     => 'Outside services blocked',
                'off'    => 'Outside services can be called',
                'detail' => function() {
                    $extra = count( (array) settings::get( 'kit_allowed_external' ) );
                    return $extra ? sprintf( '%d extra hosts allowed', $extra ) : 'WordPress.org, GitHub, Slack and Built Mighty allowed';
                },
            ],
            'kit_actionscheduler' => [
                'icon'   => 'clock',
                'on'     => 'Scheduled actions paused',
                'off'    => 'Scheduled actions are running',
                'detail' => function() {
                    return class_exists( '\ActionScheduler' ) ? 'Action Scheduler isn\'t processing its queue' : 'Action Scheduler isn\'t installed';
                },
            ],
            'kit_block_access' => [
                'icon'   => 'lock',
                'on'     => 'Visitors kept out',
                'off'    => 'Visitors can see this copy',
                'detail' => function() {
                    return settings::is_on( 'kit_block_access' ) ? 'Signed-out visitors go to builtmighty.com' : 'Logged-out visitors can browse and place orders';
                },
                'optional' => true,
            ],
        ];

        /**
         * Filter the list of copy safeguards.
         *
         * @since 6.0.0
         */
        return (array) apply_filters( 'kit_safeguards', $items );

    }

    /**
     * Status of each copy safeguard.
     *
     * @return array [ key => [ on, label, detail, icon, optional, new ] ]
     */
    public static function status(): array {
        $out = [];
        foreach( self::copy() as $key => $item ) {
            $on = settings::is_on( $key );
            $out[ $key ] = [
                'on'       => $on,
                'label'    => $on ? $item['on'] : $item['off'],
                'detail'   => is_callable( $item['detail'] ?? null ) ? (string) call_user_func( $item['detail'] ) : '',
                'icon'     => $item['icon'],
                'optional' => ! empty( $item['optional'] ),
                'new'      => ! empty( $item['new'] ),
            ];
        }
        return $out;
    }

    /**
     * Hardening steps for the live store.
     *
     * @return array [ key => [ done, label, page, action ] ]
     */
    public static function hardening(): array {

        $items = [
            'kit_enable_2fa'         => [ 'done' => settings::is_on( 'kit_enable_2fa' ), 'label' => 'Require two-factor sign-in', 'page' => 'login' ],
            'kit_enable_login'       => [ 'done' => settings::is_on( 'kit_enable_login' ) && settings::get( 'kit_login_url' ) !== '', 'label' => 'Move the login page to a custom address', 'page' => 'login' ],
            'kit_login_logging'      => [ 'done' => settings::is_on( 'kit_login_logging' ), 'label' => 'Keep a sign-in log', 'page' => 'login' ],
            'kit_session_management' => [ 'done' => settings::is_on( 'kit_session_management' ), 'label' => 'Sign out idle sessions', 'page' => 'login' ],
            'kit_security_headers'   => [ 'done' => settings::is_on( 'kit_security_headers' ), 'label' => 'Send security headers', 'page' => 'headers' ],
            'kit_rest_api_security'  => [ 'done' => settings::is_on( 'kit_rest_api_security' ), 'label' => 'Protect the REST API', 'page' => 'headers' ],
            'kit_disable_editor'     => [ 'done' => settings::is_on( 'kit_disable_editor' ) || ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ), 'label' => 'Turn off the theme and plugin file editors', 'page' => 'settings' ],
            'kit_spam_protection'    => [ 'done' => settings::is_on( 'kit_spam_protection' ), 'label' => 'Protect comments from spam', 'page' => 'spam' ],
            'xmlrpc'                 => [ 'done' => true, 'label' => 'XML-RPC is off', 'page' => '' ],
        ];

        /**
         * Filter the hardening checklist. Health checks add to it.
         *
         * @since 6.0.0
         */
        return (array) apply_filters( 'kit_hardening', $items );

    }

    /**
     * Git branch and commit of the site, if it's a repository.
     *
     * @return array [ branch, commit, repo ]
     */
    public static function git(): array {
        $dir = ABSPATH . '.git/';
        if( ! is_readable( $dir . 'HEAD' ) ) return [];
        $head   = trim( (string) @file_get_contents( $dir . 'HEAD' ) );
        $branch = strpos( $head, 'ref:' ) === 0 ? basename( trim( substr( $head, 4 ) ) ) : '';
        $commit = '';
        if( $branch !== '' ) {
            $ref = $dir . trim( substr( $head, 4 ) );
            if( is_readable( $ref ) ) $commit = substr( trim( (string) @file_get_contents( $ref ) ), 0, 7 );
        } else {
            $commit = substr( $head, 0, 7 );
        }
        $repo = '';
        if( is_readable( $dir . 'config' ) && preg_match( '#url\s*=\s*.*[/:]([^/\s]+?)(?:\.git)?\s*$#m', (string) @file_get_contents( $dir . 'config' ), $m ) ) {
            $repo = $m[1]; // Repository name only, never the remote URL (it can hold credentials).
        }
        return [ 'branch' => $branch, 'commit' => $commit, 'repo' => $repo ];
    }

}
