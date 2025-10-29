<?php
/**
 * Plugins.
 *
 * Interactions with plugins.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Private;
class plugins {

    /**
     * Option key.
     * 
     * @since   4.3.0
     */
    const OPTION_KEY       = 'kit_lu_';

    /**
     * Cache TTL.
     * 
     * @since   4.3.0
     */
    const CACHE_TTL        = DAY_IN_SECONDS;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Get user.
        $user = wp_get_current_user();
        
        // Check settings.
        if( empty( get_option( 'kit_stale_plugins' ) ) || get_option( 'kit_stale_plugins' ) == 'developers' ) {

            // Get email.
            $host = explode( '@', $user->user_email )[1];

            // Check array.
            if( ! in_array( $host, [ 'builtmighty.com', 'littlerhino.io'] ) ) return;

        } elseif( get_option( 'kit_stale_plugins' ) == 'disable' ) {

            // Stop.
            return;

        }

        // Actions.
        add_action( 'after_plugin_row', [ $this, 'view' ], 10, 3 );
        add_action( 'load-plugins.php', [ $this, 'snapshot' ], 1 );
        add_action( 'upgrader_process_complete', [ $this, 'record' ], 10, 2 );
        add_action( 'admin_head', [ $this, 'styles' ] );

        // Filters.
        add_filter( 'http_request_timeout', [ $this, 'timeout' ] );

    }

    /**
     * View.
     * 
     * @since   4.3.0
     */
    public function view( $plugin_file, $plugin_data, $status ) {

        // Get slug.
        $slug = $this->get_slug( $plugin_file, $plugin_data );
        if( ! $slug ) return;

        // Get WP.org info.
        $org  = $this->get_info( $slug );
        if( ! $org && ! $this->get_store() ) return;

        // Get store.
        $store = $this->get_store();

        // Check for WP.org data.
        if( $org && ! empty( $org->last_updated ) ) {

            // Set.
            $store[ $plugin_file ]['ts'] = strtotime( $org->last_updated );

        }

        // [version] => 6.5.1
        // [ts] => 1757502060
        // [wp] => 6.0
        // [php] => 7.4
        // [wc] =>

        // Output buffering.
        ob_start(); ?>

        <tr>
            <td colspan="4" style="background:#f1f1f1;color:#000;"><?php

                // Loop.
                foreach( $store[$plugin_file] as $key => $data ) {

                    // Check.
                    if( empty( $data ) ) continue;

                    // Skip version.
                    if( $key == 'version' ) continue;

                    // Comparison.
                    $compare = $this->compare( $key, $data );

                    // Output.
                    ?><span class="kit-update kit-update-<?php echo $key; ?> kit-update-<?php echo $compare['status']; ?>"><?php

                        // Icon.
                        if( ! empty( $compare['icon'] ) ) {
                            echo '<span class="kit-update-icon">' . $compare['icon'] . '</span> ';
                        }
                        
                        // Value.
                        echo esc_html( $compare['value'] ); ?>

                    </span><?php

                } ?>

            </td>
        </tr><?php

        // Get output.
        echo ob_get_clean();

    }

    /**
     * Timeout.
     * 
     * @since   4.3.0
     */
    public function timeout( $seconds ) {

        // Get screen.
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        // Shorter timeout on plugins screen.
        if( is_admin() && $screen && 'plugins' === $screen->id ) {
            return min( 3, (int) $seconds );
        }

        // Return default.
        return $seconds;

    }

    /**
     * Snapshot.
     * 
     * @since   4.3.0
     */
    public function snapshot() {

        // Compare versions to last seen.
        if( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // Get plugins.
        $all = get_plugins();

        // Get store.
        $store = $this->get_store();

        // Set time.
        $now = time();

        // Loop through plugins.
        foreach( $all as $plugin_file => $data ) {

            // Get current version.
            $current    = isset( $data['Version'] ) ? (string)$data['Version'] : '';
            $previous   = $store[ $plugin_file ] ?? null;

            // Check.
            if( ! $previous || ( isset( $previous['version'] ) && $previous['version'] !== $current ) ) {

                // Add to store.
                $store[ $plugin_file ] = [
                    'version'   => $current,
                    'ts'        => $now,
                    'wp'        => $data['RequiresWP'] ?? '',
                    'php'       => $data['RequiresPHP'] ?? '',
                    'wc'        => $data['WC tested up to'] ?? '',
                ];

            }

        }

        // Set store.
        $this->set_store( $store );

    }

    /**
     * Record.
     * 
     * @since   4.3.0
     */
    public function record( $upgrader, $hook_extra ) {

        // When WordPress finishes updating plugins, stamp their local updated time.
        if( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) return;

        // Set items.
        $items = [];
        if( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {

            // Multiple plugins.
            $items = $hook_extra['plugins'];

        } elseif( ! empty( $hook_extra['plugin'] ) ) {

            // Single plugin.
            $items = [ $hook_extra['plugin'] ];

        }

        // Check for items.
        if( ! $items ) return;

        // Load plugins API if needed.
        if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // Get plugins, store, and time.
        $all    = get_plugins();
        $store  = $this->get_store();
        $now    = time();

        // Loop through items.
        foreach( $items as $plugin_file ) {

            // Check if in all plugins.
            if( isset( $all[ $plugin_file ] ) ) {

                // Get current version.
                $cur_ver = (string)( $all[ $plugin_file ]['Version'] ?? '' );

                // Add to store.
                $store[ $plugin_file ] = [
                    'version'   => $cur_ver,
                    'ts'        => $now,
                    'wp'        => $all[ $plugin_file ]['RequiresWP'] ?? '',
                    'php'       => $all[ $plugin_file ]['RequiresPHP'] ?? '',
                    'wc'        => $all[ $plugin_file ]['WC tested up to'] ?? '',
                ];

            } else {

                // In case of rename, at least stamp something.
                $store[ $plugin_file ] = [
                    'version'   => '',
                    'ts'        => $now,
                    'wp'        => '',
                    'php'       => '',
                    'wc'        => '',
                ];

            }

        }

        // Set.
        $this->set_store( $store );

    }

    /**
     * Styles.
     * 
     * @since   4.3.0
     */
    public function styles() {

        // Check if current page is plugins.php.
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        // Check screen.
        if( ! is_admin() || ! $screen || 'plugins' !== $screen->id ) return;

        // Output. ?>
        <style>
            span.kit-update {
                background: #ffffff;
                border-radius: 6px;
                padding: 2.5px 5px;
                margin-right: 5px;
                border: 2px solid rgb(0 0 0 / 10%);
                box-shadow: 0px 2px 8px -4px rgb(0 0 0 / 20%);
            }

            span.kit-update.kit-update-wp {
                background: #3858e9;
                color: #fff;
            }

            span.kit-update.kit-update-php {
                background: #4f5b93;
                color: #fff;
            }

            span.kit-update.kit-update-wc {
                background: #720eec;
                color: #fff;
            }

            span.kit-update-icon svg {
                max-height: 15px;
                width: auto;
                display: inline-block;
                vertical-align: text-bottom;
            }

            span.kit-update.kit-update-stale {
                border-color: rgb(255 129 0);
            }

            span.kit-update.kit-update-outdated {
                border-color: #ff0000;
            }
        </style><?php

    }

    /**
     * Get store.
     * 
     * @since   4.3.0
     */
    private function get_store() {

        // Set.
        $val = [];
        $val = ( is_multisite() ) ? get_site_option( self::OPTION_KEY, [] ) : get_option( self::OPTION_KEY, [] );

        // Return.
        return is_array( $val ) ? $val : [];

    }

    /**
     * Set store.
     * 
     * @since   4.3.0
     * 
     * @param   array   $store
     */
    private function set_store( array $store ) {

        // Update.
        if( is_multisite() ) {

            // Update multisite option.
            update_site_option( self::OPTION_KEY, $store );

        } else {

            // Update option.
            update_option( self::OPTION_KEY, $store );

        }

    }

    /**
     * Delete store.
     * 
     * @since   4.3.0
     */
    private function delete_store() {

        // Delete.
        if( is_multisite() ) {

            // Delete multisite option.
            delete_site_option( self::OPTION_KEY );

        } else {

            // Delete option.
            delete_option( self::OPTION_KEY );

        }

    }

    /**
     * Get slug.
     * 
     * @since   4.3.0
     * @param   string  $plugin_file
     * @param   array   $plugin_data
     * @return  string|null
     */
    private function get_slug( $plugin_file, $plugin_data ) {

        // Check.
        if( ! empty( $plugin_data['slug'] ) ) return sanitize_key( $plugin_data['slug'] );

        // Get directory.
        $dir = dirname( $plugin_file );
        if( $dir && '.' !== $dir ) return sanitize_key( $dir );

        // Get base.
        $base = basename( $plugin_file, '.php' );
        if( $base ) return sanitize_key( $base );

        // Check text domain.
        if( ! empty( $plugin_data['TextDomain'] ) ) return sanitize_key( $plugin_data['TextDomain'] );

        // Return null.
        return null;

    }

    /**
     * Get info.
     * 
     * @since   4.3.0
     * 
     * @param   string  $slug
     * @return  \stdClass|false
     */
    private function get_info( $slug ) {

        // Set key.
        $key    = self::OPTION_KEY . $slug;
        
        // Get cache.
        $cached = get_transient( $key );
        if( false !== $cached ) return $cached;

        // Load plugins API if needed.
        if( ! function_exists( 'plugins_api' ) ) require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        // Set args.
        $args = [
            'slug'   => $slug,
            'fields' => [
                'sections'          => false,
                'banners'           => false,
                'icons'             => false,
                'short_description' => false,
                'last_updated'      => true,
                'version'           => true,
            ],
        ];

        // Get info.
        $response = plugins_api( 'plugin_information', $args );

        // Check if error.
        if( is_wp_error( $response ) ) {

            // Set transient and stop.
            set_transient( $key, false, self::CACHE_TTL );
            return false;

        }

        // Set transient and return.
        set_transient( $key, $response, self::CACHE_TTL );
        return $response;

    }

    /**
     * Compare.
     * 
     * @since   4.3.0
     */
    private function compare( $key, $value ) {

        // Check key.
        switch( $key ) {
            case 'ts':

                // Get now.
                $now = time();

                // Set response.
                $response = [
                    'value'    => date( 'F j, Y', (int) $value ),
                ];

                // If timestamp is within 90 days.
                if( ( $now - (int) $value ) <= ( 90 * DAY_IN_SECONDS ) ) {
                    // Add.
                    $response['status'] = 'recent';
                    return $response;
                } elseif( ( $now - (int) $value ) <= ( 180 * DAY_IN_SECONDS ) ) {
                    // Add.
                    $response['status'] = 'stale';
                    return $response;
                } else {
                    // Add.
                    $response['status'] = 'outdated';
                    return $response;
                }

            case 'wp':

                // Get stable.
                $stable = explode( '.', $this->get_wp() );

                // Version.
                $version = explode( '.', $value );

                // Set response.
                $response = [
                    'value'    => $value,
                    'icon'     => '<svg xmlns="http://www.w3.org/2000/svg" role="img" width="28" height="28" viewBox="0 0 28 28"><path fill="#ffffff" d="M13.6052 0.923525C16.1432 0.923525 18.6137 1.67953 20.7062 3.09703C22.7447 4.47403 24.3512 6.41803 25.3097 8.68603C26.9837 12.6415 26.5382 17.164 24.1352 20.7145C22.7582 22.753 20.8142 24.3595 18.5462 25.318C14.5907 26.992 10.0682 26.5465 6.51772 24.1435C4.47922 22.7665 2.87272 20.8225 1.91422 18.5545C0.240225 14.599 0.685725 10.0765 3.08872 6.52603C4.46572 4.48753 6.40973 2.88103 8.67772 1.92253C10.2302 1.26103 11.9177 0.923525 13.6052 0.923525ZM13.6052 0.113525C6.15322 0.113525 0.105225 6.16153 0.105225 13.6135C0.105225 21.0655 6.15322 27.1135 13.6052 27.1135C21.0572 27.1135 27.1052 21.0655 27.1052 13.6135C27.1052 6.16153 21.0572 0.113525 13.6052 0.113525Z"></path><path fill="#ffffff" d="M2.36011 13.6133C2.36011 17.9198 4.81711 21.8618 8.70511 23.7383L3.33211 9.03684C2.68411 10.4813 2.36011 12.0338 2.36011 13.6133ZM21.2061 13.0463C21.2061 11.6558 20.7066 10.6973 20.2746 9.94134C19.8426 9.18534 19.1676 8.22684 19.1676 7.30884C19.1676 6.39084 19.9506 5.31084 21.0576 5.31084H21.2061C16.6296 1.11234 9.51511 1.42284 5.31661 6.01284C4.91161 6.45834 4.53361 6.93084 4.20961 7.43034H4.93861C6.11311 7.43034 7.93561 7.28184 7.93561 7.28184C8.54311 7.24134 8.61061 8.13234 8.00311 8.21334C8.00311 8.21334 7.39561 8.28084 6.72061 8.32134L10.8111 20.5118L13.2681 13.1273L11.5131 8.32134C10.9056 8.28084 10.3386 8.21334 10.3386 8.21334C9.73111 8.17284 9.79861 7.25484 10.4061 7.28184C10.4061 7.28184 12.2691 7.43034 13.3626 7.43034C14.4561 7.43034 16.3596 7.28184 16.3596 7.28184C16.9671 7.24134 17.0346 8.13234 16.4271 8.21334C16.4271 8.21334 15.8196 8.28084 15.1446 8.32134L19.2081 20.4173L20.3691 16.7453C20.8821 15.1388 21.1926 14.0048 21.1926 13.0328L21.2061 13.0463ZM13.7946 14.5853L10.4196 24.3998C12.6876 25.0613 15.1041 25.0073 17.3316 24.2243L17.2506 24.0758L13.7946 14.5853ZM23.4741 8.21334C23.5281 8.59134 23.5551 8.98284 23.5551 9.37434C23.5551 10.5218 23.3391 11.8043 22.7046 13.3973L19.2621 23.3333C24.5271 20.2688 26.4036 13.5593 23.4741 8.21334Z"></path></svg>'
                ];

                // If current WP version is within 4 minor versions.
                if( ( (int)$stable[0] === (int)$version[0] ) && ( (int)$stable[1] - (int)$version[1] ) <= 4 ) {
                    // Add.
                    $response['status'] = 'recent';
                    return $response;
                } elseif( ( (int)$stable[0] === (int)$version[0] ) && ( (int)$stable[1] - (int)$version[1] ) <= 8 ) {
                    // Add.
                    $response['status'] = 'stale';
                    return $response;
                } else {
                    // Add.
                    $response['status'] = 'outdated';
                    return $response;
                }

            case 'php':
                
                // Get stable.
                $stable = explode( '.', $this->get_php() );

                // Version.
                $version = explode( '.', $value );

                // Set response.
                $response = [
                    'value'    => $value,
                    'icon'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -1 100 50" fill="#ffffff"><path d="m7.579 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053"/><path d="m41.093 0 7.314 0-2.067 10.123 6.572 0c3.604 0.071 6.289 0.813 8.056 2.226 1.802 1.413 2.332 4.099 1.59 8.056l-3.551 17.649-7.42 0 3.392-16.854c0.353-1.767 0.247-3.021-0.318-3.763-0.565-0.742-1.784-1.113-3.657-1.113l-5.883-0.053-4.346 21.783-7.314 0 7.632-38.054 0 0"/><path d="m70.412 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053"/></svg>'
                ];

                // If current WP version is within 4 minor versions.
                if( ( (int)$stable[0] === (int)$version[0] ) && ( (int)$stable[1] - (int)$version[1] ) <= 4 ) {
                    // Add.
                    $response['status'] = 'recent';
                    return $response;
                } elseif( ( (int)$stable[0] === (int)$version[0] ) && ( (int)$stable[1] - (int)$version[1] ) <= 8 ) {
                    // Add.
                    $response['status'] = 'stale';
                    return $response;
                } else {
                    // Add.
                    $response['status'] = 'outdated';
                    return $response;
                }

            case 'wc':

                // Get stable.
                $stable = explode( '.', $this->get_wc() );

                // Version.
                $version = explode( '.', $value );

                // Set response.
                $response = [
                    'value'    => $value,
                    'icon'     => '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" class="wccom-icon wccom-icon__woo-logo" role="img" aria-labelledby="logoTitle" viewBox="0 0 95 26" width="95" height="26" preserveAspectRatio="xMidYMid" style="max-width: 100%; height: auto;"><title id="logoTitle">WooCommerce</title><path d="M12.0825 25.2704C14.8471 25.2704 17.0657 23.9052 18.7381 20.7651L22.4584 13.8023V19.707C22.4584 23.1884 24.7111 25.2704 28.1925 25.2704C30.923 25.2704 32.9368 24.0758 34.8822 20.7651L43.4492 6.29339C45.3264 3.11918 43.9953 0.72998 39.8654 0.72998C37.6469 0.72998 36.2134 1.44674 34.9164 3.87006L29.0117 14.9628V5.09879C29.0117 2.1635 27.6123 0.72998 25.0183 0.72998C22.9704 0.72998 21.3321 1.6174 20.0692 4.07485L14.5058 14.9628V5.20119C14.5058 2.0611 13.2088 0.72998 10.0687 0.72998H3.65206C1.22873 0.72998 0 1.85632 0 3.93833C0 6.02034 1.29699 7.21494 3.65206 7.21494H6.28017V19.6729C6.28017 23.1884 8.63523 25.2704 12.0825 25.2704Z" fill="#FFFFFF"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M55.9772 0.72998C48.9803 0.72998 43.6217 5.95208 43.6217 13.0173C43.6217 20.0825 49.0144 25.2704 55.9772 25.2704C62.94 25.2704 68.2645 20.0483 68.2986 13.0173C68.2986 5.95208 62.94 0.72998 55.9772 0.72998ZM55.9772 17.7274C53.3491 17.7274 51.5401 15.7478 51.5401 13.0173C51.5401 10.2868 53.3491 8.27301 55.9772 8.27301C58.6053 8.27301 60.4143 10.2868 60.4143 13.0173C60.4143 15.7478 58.6395 17.7274 55.9772 17.7274Z" fill="#FFFFFF"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M70.0369 13.0173C70.0369 5.95208 75.3955 0.72998 82.3583 0.72998C89.3211 0.72998 94.6797 5.98621 94.6797 13.0173C94.6797 20.0483 89.3211 25.2704 82.3583 25.2704C75.3955 25.2704 70.0369 20.0825 70.0369 13.0173ZM77.9554 13.0173C77.9554 15.7478 79.6961 17.7274 82.3583 17.7274C84.9864 17.7274 86.7954 15.7478 86.7954 13.0173C86.7954 10.2868 84.9864 8.27301 82.3583 8.27301C79.7302 8.27301 77.9554 10.2868 77.9554 13.0173Z" fill="#ffffff"></path></svg>',
                ];

                // If current WP version is within 4 minor versions.
                if( ( (int)$stable[0] === (int)$version[0] ) && ( (int)$stable[1] - (int)$version[1] ) <= 4 ) {
                    // Add.
                    $response['status'] = 'recent';
                    return $response;
                } elseif( ( (int)$stable[0] === (int)$version[0] ) && ( (int)$stable[1] - (int)$version[1] ) <= 8 ) {
                    // Add.
                    $response['status'] = 'stale';
                    return $response;
                } else {
                    // Add.
                    $response['status'] = 'outdated';
                    return $response;
                }

            default:
                return false;
        }

    }
    
    /**
     * Get WordPress.
     * 
     * @since   4.3.0
     */
    private function get_wp() {

        // Get cached version.
        $wp_version = get_transient( self::OPTION_KEY . 'wp_version' );
        if( $wp_version ) return $wp_version;

        // Get sites current WP version.
        $version = get_bloginfo( 'version' );

        // Get most recent WordPress version from API.
        $response = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/' );

        // Check for response.
        if( ! is_wp_error( $response ) && is_array( $response ) ) {

            // Decode.
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            // Check for current version.
            if( isset( $data['offers'][0]['current'] ) ) {

                // Set version.
                $version = $data['offers'][0]['current'];

                // Cache version.
                set_transient( self::OPTION_KEY . 'wp_version', $version, self::CACHE_TTL );

            }

        }

        // Return.
        return $version;

    }

    /**
     * Get PHP.
     * 
     * @since   4.3.0
     */    
    private function get_php() {

        // Get cached version.
        $php_version = get_transient( self::OPTION_KEY . 'php_version' );
        if( $php_version ) return $php_version;

        // Get current PHP version.
        $version = phpversion();

        // Get stable PHP version from API.
        $response = wp_remote_get( 'https://www.php.net/releases/index.php?json&version=8' );

        // Check for response.
        if( ! is_wp_error( $response ) && is_array( $response ) ) {

            // Decode.
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            // Check for current version.
            if( is_array( $data ) && ! empty( $data['supported_versions'] ) ) {

                // Get first from supported versions.
                $version = reset( $data['supported_versions'] );

            }

        }

        // Cache version.
        set_transient( self::OPTION_KEY . 'php_version', $version, self::CACHE_TTL );

        // Return.
        return $version;

    }

    /**
     * Get WooCommerce.
     * 
     * @since   4.3.0
     */    
    private function get_wc() {

        // Get cached version.
        $wc_version = get_transient( self::OPTION_KEY . 'wc_version' );
        if( $wc_version ) return $wc_version;

        // Default version.
        $version = '';

        // Get WooCommerce version if active.
        if( class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) ) {
            $version = WC_VERSION;
        }

        // Get stable WooCommerce version from API.
        $response = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/?action=plugin_information&request[slug]=woocommerce&request[fields][versions]=true' );
        if( ! is_wp_error( $response ) && is_array( $response ) ) {

            // Decode.
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            // Check for current version.
            if( isset( $data['version'] ) ) {

                // Set version.
                $version = $data['version'];

                // Cache version.
                set_transient( self::OPTION_KEY . 'wc_version', $version, self::CACHE_TTL );

            }

        }

        // Return.
        return $version;

    }

}
