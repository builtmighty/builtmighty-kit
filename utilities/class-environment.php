<?php
/**
 * Environment.
 *
 * Decides which copy of the site this is (production, staging, development
 * or local) and whether Kit's safeguards ("kit mode") apply.
 *
 * Detection never writes to the database and, when nothing identifies the
 * site, assumes it is a copy. Treating an unknown site as production is the
 * dangerous mistake: emails, payments and renewals would run for real.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Utility;

if( ! defined( 'ABSPATH' ) ) { exit; }

class environment {

    /**
     * Resolved result, cached per request.
     *
     * @var array|null [ 'type' => string, 'kit' => bool, 'source' => string, 'detail' => string ]
     */
    private static $resolved = null;

    /**
     * Hosting domains that are always copies.
     *
     * @return array
     */
    public static function copy_suffixes(): array {
        return (array) apply_filters( 'kit_mode_suffixes', [
            'mightyrhino.net',
            'builtmighty.com',
            'github.dev',
            'kinsta.cloud',
            'wpengine.com',
            'wpenginepowered.com',
            'cloudwaysapps.com',
            'flywheelsites.com',
            'pantheonsite.io',
            'local',
            'test',
            'localhost',
        ] );
    }

    /**
     * Is kit mode (any non-production copy)?
     *
     * @return bool
     */
    public static function is_kit(): bool {
        return self::resolve()['kit'];
    }

    /**
     * Environment type: production, staging, development or local.
     *
     * @return string
     */
    public static function type(): string {
        return self::resolve()['type'];
    }

    /**
     * Human-readable explanation of how the environment was detected.
     *
     * @return array [ 'source' => machine key, 'label' => sentence, 'detail' => code-ish value ]
     */
    public static function source(): array {
        $r = self::resolve();
        $labels = [
            'filter'       => 'Set by the is_kit_mode filter',
            'constant'     => 'Set by the KIT_MODE constant',
            'setting'      => 'Set in Kit settings',
            'wp_env'       => 'Detected from WP_ENVIRONMENT_TYPE',
            'live_url'     => 'Matched the saved live store URL',
            'not_live_url' => 'Doesn\'t match the saved live store URL',
            'suffix'       => 'Known development or staging host',
            'unknown'      => 'Couldn\'t identify this site, so Kit treats it as a copy',
        ];
        return [
            'source' => $r['source'],
            'label'  => $labels[ $r['source'] ] ?? '',
            'detail' => $r['detail'],
        ];
    }

    /**
     * Reset the cache (used after settings change and in tests).
     */
    public static function reset(): void {
        self::$resolved = null;
    }

    /**
     * Normalize a host for comparison: lowercase, no www., no port.
     *
     * @param  string $url_or_host
     * @return string
     */
    public static function normalize_host( string $url_or_host ): string {
        $url_or_host = trim( $url_or_host );
        if( $url_or_host === '' ) return '';
        if( strpos( $url_or_host, '//' ) === false ) $url_or_host = 'https://' . $url_or_host;
        $host = strtolower( (string) wp_parse_url( $url_or_host, PHP_URL_HOST ) );
        return preg_replace( '/^www\./', '', $host );
    }

    /**
     * The current site's host.
     *
     * @return string
     */
    public static function current_host(): string {
        return self::normalize_host( home_url() );
    }

    /**
     * The saved live store URL (plain, not encoded).
     *
     * @return string
     */
    public static function live_url(): string {
        return (string) settings::get( 'kit_production_url' );
    }

    /**
     * Resolve.
     *
     * Order: filter, constant, setting, explicit WP_ENVIRONMENT_TYPE,
     * saved live URL, known copy hosts, then "unknown" (treated as a copy).
     *
     * @return array
     */
    private static function resolve(): array {

        if( self::$resolved !== null ) return self::$resolved;

        // Filter override.
        $filter = apply_filters( 'is_kit_mode', null );
        if( $filter !== null ) {
            return self::set( (bool) $filter ? self::guess_copy_type() : 'production', 'filter', '' );
        }

        // Constant override.
        if( defined( 'KIT_MODE' ) ) {
            return self::set( (bool) KIT_MODE ? self::guess_copy_type() : 'production', 'constant', 'KIT_MODE' );
        }

        // Kit setting.
        $setting = (string) settings::get( 'kit_environment' );
        if( in_array( $setting, [ 'production', 'staging', 'development', 'local' ], true ) ) {
            return self::set( $setting, 'setting', $setting );
        }

        // Explicit WordPress environment type. wp_get_environment_type() returns
        // "production" when nothing is set, so only trust a value that was set.
        $explicit = self::explicit_wp_environment();
        if( $explicit !== '' ) {
            return self::set( $explicit, 'wp_env', $explicit );
        }

        // Saved live store URL.
        $live = self::normalize_host( self::live_url() );
        $host = self::current_host();
        if( $live !== '' ) {
            if( $live === $host ) return self::set( 'production', 'live_url', $live );
            return self::set( self::guess_copy_type(), 'not_live_url', $live );
        }

        // Known development and staging hosts.
        if( self::is_copy_host( $host ) ) {
            return self::set( self::guess_copy_type(), 'suffix', $host );
        }

        // Unknown: safe default.
        return self::set( 'staging', 'unknown', $host );

    }

    /**
     * WP_ENVIRONMENT_TYPE only when explicitly defined.
     *
     * @return string
     */
    public static function explicit_wp_environment(): string {
        $allowed = [ 'production', 'staging', 'development', 'local' ];
        $value   = '';
        if( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
            $value = (string) WP_ENVIRONMENT_TYPE;
        } else {
            $env = getenv( 'WP_ENVIRONMENT_TYPE' );
            if( $env !== false ) $value = (string) $env;
        }
        return in_array( $value, $allowed, true ) ? $value : '';
    }

    /**
     * Is this host a known development/staging host?
     *
     * @param  string $host
     * @return bool
     */
    public static function is_copy_host( string $host ): bool {
        foreach( self::copy_suffixes() as $suffix ) {
            $suffix = strtolower( ltrim( (string) $suffix, '.' ) );
            if( $suffix === '' ) continue;
            if( $host === $suffix || substr( $host, -strlen( '.' . $suffix ) ) === '.' . $suffix ) return true;
        }
        return false;
    }

    /**
     * Best guess at a copy's type when only "not production" is known.
     *
     * @return string
     */
    private static function guess_copy_type(): string {
        $host = self::current_host();
        if( preg_match( '/(^|\.)(localhost|local|test)$/', $host ) ) return 'local';
        if( preg_match( '/(^|[.-])(dev|develop|development)([.-]|$)|github\.dev$/', $host ) ) return 'development';
        return 'staging';
    }

    /**
     * Store the result.
     */
    private static function set( string $type, string $source, string $detail ): array {
        return self::$resolved = [
            'type'   => $type,
            'kit'    => $type !== 'production',
            'source' => $source,
            'detail' => $detail,
        ];
    }

}
