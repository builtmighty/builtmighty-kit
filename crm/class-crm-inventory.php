<?php
/**
 * CRM Inventory.
 *
 * Collects the site's installed software inventory — WordPress core, plugins,
 * and themes, with versions and available updates — for the daily CRM push.
 * Update data comes from WordPress's own update transients, so premium
 * plugins with their own update servers are included.
 *
 * @package Built Mighty Kit
 * @since   5.1.0
 */
namespace BuiltMightyKit\CRM;

if ( ! defined( 'WPINC' ) ) { die; }

class crm_inventory {

    /**
     * Instance.
     *
     * @since 5.1.0
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get instance.
     *
     * @since 5.1.0
     * @return self
     */
    public static function get_instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Collect the full inventory.
     *
     * @since 5.1.0
     * @param bool $refresh Refresh the update transients first so the data
     *                      matches wp-admin's Updates screen.
     * @return array
     */
    public function collect( bool $refresh = true ): array {

        // get_plugins()/is_plugin_active() live in admin includes; load them
        // for cron and AJAX contexts.
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( $refresh ) {
            wp_version_check();
            wp_update_plugins();
            wp_update_themes();
        }

        return [
            'checked_at' => current_time( 'c' ),
            'core'       => $this->collect_core(),
            'plugins'    => $this->collect_plugins(),
            'themes'     => $this->collect_themes(),
        ];
    }

    /**
     * Core version + available update.
     *
     * @since 5.1.0
     * @return array
     */
    private function collect_core(): array {

        // Local scope on purpose — avoids trusting a mutated global.
        require ABSPATH . WPINC . '/version.php';

        $update    = null;
        $transient = get_site_transient( 'update_core' );

        if ( $transient && ! empty( $transient->updates ) ) {
            foreach ( $transient->updates as $offer ) {
                if ( isset( $offer->response, $offer->version ) && 'upgrade' === $offer->response ) {
                    $update = $offer->version;
                    break;
                }
            }
        }

        return [
            'version'        => $wp_version,
            'update_version' => $update,
        ];
    }

    /**
     * All installed plugins with versions, active state, and available updates.
     *
     * @since 5.1.0
     * @return array
     */
    private function collect_plugins(): array {

        $installed = get_plugins();
        $updates   = get_site_transient( 'update_plugins' );
        $items     = [];

        foreach ( $installed as $file => $data ) {

            // "woocommerce/woocommerce.php" → "woocommerce"; single-file
            // plugins like "hello.php" → "hello".
            $slug = ( false !== strpos( $file, '/' ) ) ? dirname( $file ) : basename( $file, '.php' );

            $items[] = [
                'slug'           => $slug,
                'name'           => ! empty( $data['Name'] ) ? $data['Name'] : $slug,
                'version'        => $data['Version'] ?? '',
                'active'         => is_plugin_active( $file ) || ( is_multisite() && is_plugin_active_for_network( $file ) ),
                'update_version' => isset( $updates->response[ $file ]->new_version ) ? $updates->response[ $file ]->new_version : null,
                'meta'           => [
                    'file'   => $file,
                    'author' => wp_strip_all_tags( $data['Author'] ?? '' ),
                    'uri'    => $data['PluginURI'] ?? '',
                ],
            ];
        }

        return $items;
    }

    /**
     * All installed themes with versions, active state, and available updates.
     *
     * @since 5.1.0
     * @return array
     */
    private function collect_themes(): array {

        $themes  = wp_get_themes();
        $updates = get_site_transient( 'update_themes' );
        $current = get_stylesheet();
        $parent  = get_template();
        $items   = [];

        foreach ( $themes as $stylesheet => $theme ) {

            // Theme update responses are arrays, not objects.
            $update = isset( $updates->response[ $stylesheet ]['new_version'] ) ? $updates->response[ $stylesheet ]['new_version'] : null;

            $items[] = [
                'slug'           => $stylesheet,
                'name'           => $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $stylesheet,
                'version'        => $theme->get( 'Version' ) ? $theme->get( 'Version' ) : '',
                'active'         => ( $stylesheet === $current || $stylesheet === $parent ),
                'update_version' => $update,
                'meta'           => [
                    'author' => wp_strip_all_tags( $theme->get( 'Author' ) ? $theme->get( 'Author' ) : '' ),
                ],
            ];
        }

        return $items;
    }

}
