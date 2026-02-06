<?php
/**
 * Performance.
 *
 * CSS/JS asset detection, bundling, and minification.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\Private;

class performance {

    /**
     * Upload directory for bundles.
     *
     * @var string
     */
    private $upload_dir;

    /**
     * Upload URL for bundles.
     *
     * @var string
     */
    private $upload_url;

    /**
     * Handles that should never be bundled.
     *
     * @var array
     */
    private $excluded_prefixes = [
        // WordPress core.
        'jquery',
        'jquery-core',
        'jquery-migrate',
        'wp-',
        'backbone',
        'underscore',
        'wp-util',
        'wp-api',
        'hoverIntent',
        // WooCommerce.
        'wc-',
        'woocommerce',
        'selectWoo',
        'select2',
        // Payment gateways.
        'stripe',
        'paypal',
        'square',
        'braintree',
        // Admin bar / admin-only plugins (enqueue on frontend for logged-in admins).
        'admin-bar',
        'instawp',
        'query-monitor',
        'debug-bar',
        'updraft',
        'wordfence',
        'sucuri',
        'jetpack',
        // This plugin.
        'builtmighty',
        'kit-bundle-js',
        'kit-bundle-css',
    ];

    /**
     * Construct.
     *
     * @since 5.0.0
     */
    public function __construct() {

        // Set upload paths.
        $upload = wp_upload_dir();
        $this->upload_dir = trailingslashit( $upload['basedir'] ) . 'builtmighty-kit/';
        $this->upload_url = trailingslashit( $upload['baseurl'] ) . 'builtmighty-kit/';

        // Always register AJAX handlers and admin bar (even when bundling is off).
        add_action( 'wp_ajax_kit_rebuild_bundle', [ $this, 'ajax_rebuild' ] );
        add_action( 'init', [ $this, 'maybe_rebuild_from_querystring' ] );

        // Register checkbox options so WordPress saves them on form submit.
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Detection always runs on frontend to keep the asset list fresh.
        if ( ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'detect_assets' ], 99999 );
        }

        // Everything below requires bundling to be enabled.
        if ( get_option( 'kit_bundle_enabled' ) !== 'enable' ) {
            return;
        }

        // Admin bar rebuild button.
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_button' ], 999 );

        // Frontend: serve bundles.
        if ( ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'serve_bundles' ], 100001 );
            add_filter( 'script_loader_tag', [ $this, 'add_defer_attribute' ], 10, 3 );
        }

    }

    // =========================================================================
    // ASSET DETECTION
    // =========================================================================

    /**
     * Detect enqueued CSS/JS on the frontend and store in a transient.
     * Only captures assets that are actually in the queue (not just registered).
     * Merges with existing detections so different pages accumulate the full list.
     *
     * @since 5.0.0
     */
    public function detect_assets() {

        // Only detect for logged-out visitors to avoid admin bar / admin-only plugin scripts.
        if ( is_user_logged_in() ) return;

        global $wp_scripts, $wp_styles;

        // Start with existing detected assets to merge across page visits.
        $existing = get_transient( 'kit_detected_assets' );
        $assets = is_array( $existing ) ? $existing : [
            'js'  => [],
            'css' => [],
        ];
        $assets['updated'] = time();

        // Detect JS — only scripts actually enqueued on this page.
        if ( isset( $wp_scripts ) && is_object( $wp_scripts ) ) {
            foreach ( $wp_scripts->queue as $handle ) {

                if ( $this->is_excluded_handle( $handle ) ) continue;
                if ( ! isset( $wp_scripts->registered[ $handle ] ) ) continue;

                $script = $wp_scripts->registered[ $handle ];
                $src = $script->src ? strtok( $script->src, '?' ) : '';
                if ( empty( $src ) ) continue;
                if ( $this->is_external_src( $src ) ) continue;

                $path = $this->url_to_path( $src );
                if ( ! $path || ! file_exists( $path ) ) continue;

                $assets['js'][ $handle ] = [
                    'src'  => $src,
                    'path' => $path,
                    'deps' => $script->deps,
                ];
            }
        }

        // Detect CSS — only styles actually enqueued on this page.
        if ( isset( $wp_styles ) && is_object( $wp_styles ) ) {
            foreach ( $wp_styles->queue as $handle ) {

                if ( $this->is_excluded_handle( $handle ) ) continue;
                if ( ! isset( $wp_styles->registered[ $handle ] ) ) continue;

                $style = $wp_styles->registered[ $handle ];
                $src = $style->src ? strtok( $style->src, '?' ) : '';
                if ( empty( $src ) ) continue;
                if ( $this->is_external_src( $src ) ) continue;

                $path = $this->url_to_path( $src );
                if ( ! $path || ! file_exists( $path ) ) continue;

                $assets['css'][ $handle ] = [
                    'src'  => $src,
                    'path' => $path,
                    'deps' => $style->deps,
                ];
            }
        }

        // Only update if we have anything.
        if ( ! empty( $assets['js'] ) || ! empty( $assets['css'] ) ) {
            set_transient( 'kit_detected_assets', $assets, 7 * DAY_IN_SECONDS );
        }

    }

    /**
     * Register bundler options so WordPress saves checkbox arrays on form submit.
     *
     * @since 5.0.0
     */
    public function register_settings() {

        register_setting( 'built_mighty_global_settings_group', 'kit_bundled_js_handles', [
            'type'              => 'array',
            'sanitize_callback' => function( $value ) {
                return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
            },
            'default'           => [],
        ] );

        register_setting( 'built_mighty_global_settings_group', 'kit_bundled_css_handles', [
            'type'              => 'array',
            'sanitize_callback' => function( $value ) {
                return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
            },
            'default'           => [],
        ] );

    }

    // =========================================================================
    // BUNDLE SERVING
    // =========================================================================

    /**
     * Dequeue selected assets and enqueue bundles.
     *
     * @since 5.0.0
     */
    public function serve_bundles() {

        // Skip on WooCommerce cart/checkout.
        if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
            return;
        }

        // Auto-rebuild if the 24-hour freshness transient expired.
        if ( false === get_transient( 'kit_bundle_fresh' ) ) {
            $this->rebuild_bundles();
        }

        // Serve JS bundle.
        $js_file = get_option( 'kit_bundle_js_file', '' );
        $js_handles = get_option( 'kit_bundled_js_handles', [] );

        if ( $js_file && file_exists( $this->upload_dir . $js_file ) && ! empty( $js_handles ) ) {
            foreach ( (array) $js_handles as $handle ) {
                wp_dequeue_script( $handle );
            }
            $version = get_option( 'kit_bundle_version', time() );
            wp_enqueue_script( 'kit-bundle-js', $this->upload_url . $js_file, [ 'jquery' ], $version, true );
        }

        // Serve CSS bundle.
        $css_file = get_option( 'kit_bundle_css_file', '' );
        $css_handles = get_option( 'kit_bundled_css_handles', [] );

        if ( $css_file && file_exists( $this->upload_dir . $css_file ) && ! empty( $css_handles ) ) {
            foreach ( (array) $css_handles as $handle ) {
                wp_dequeue_style( $handle );
            }
            $version = get_option( 'kit_bundle_version', time() );
            wp_enqueue_style( 'kit-bundle-css', $this->upload_url . $css_file, [], $version );
        }

    }

    /**
     * Add defer attribute to bundle JS.
     *
     * @since 5.0.0
     */
    public function add_defer_attribute( $tag, $handle, $src ) {

        if ( $handle !== 'kit-bundle-js' ) return $tag;
        if ( strpos( $tag, 'defer' ) !== false ) return $tag;

        return str_replace( ' src', ' defer src', $tag );

    }

    // =========================================================================
    // BUNDLE GENERATION
    // =========================================================================

    /**
     * Rebuild JS and CSS bundles.
     *
     * @since 5.0.0
     * @return bool
     */
    public function rebuild_bundles() {

        // Ensure upload directory exists.
        wp_mkdir_p( $this->upload_dir );

        // Clean old bundles.
        $this->clean_old_bundles();

        // Get detected assets.
        $detected = get_transient( 'kit_detected_assets' );
        if ( ! $detected ) return false;

        $js_handles  = get_option( 'kit_bundled_js_handles', [] );
        $css_handles = get_option( 'kit_bundled_css_handles', [] );

        $built = false;

        // Build JS bundle.
        if ( ! empty( $js_handles ) && ! empty( $detected['js'] ) ) {
            $combined = '';
            foreach ( (array) $js_handles as $handle ) {
                if ( ! isset( $detected['js'][ $handle ] ) ) continue;
                $path = $detected['js'][ $handle ]['path'];
                if ( ! file_exists( $path ) ) continue;
                $content = file_get_contents( $path );
                $combined .= "/* {$handle} */\n" . $content . ";\n\n";
            }
            if ( $combined ) {
                $minified = $this->minify_js( $combined );
                $hash     = substr( md5( $minified ), 0, 12 );
                $filename = "bundle-{$hash}.min.js";
                if ( file_put_contents( $this->upload_dir . $filename, $minified ) !== false ) {
                    update_option( 'kit_bundle_js_file', $filename );
                    $built = true;
                }
            }
        }

        // Build CSS bundle.
        if ( ! empty( $css_handles ) && ! empty( $detected['css'] ) ) {
            $combined = '';
            foreach ( (array) $css_handles as $handle ) {
                if ( ! isset( $detected['css'][ $handle ] ) ) continue;
                $path = $detected['css'][ $handle ]['path'];
                if ( ! file_exists( $path ) ) continue;
                $content = file_get_contents( $path );

                // Rewrite relative URLs in CSS to absolute.
                $css_dir = trailingslashit( dirname( $detected['css'][ $handle ]['src'] ) );
                $content = preg_replace_callback(
                    '/url\(\s*[\'"]?(?!data:|https?:|\/\/)([^\'")]+)[\'"]?\s*\)/',
                    function( $matches ) use ( $css_dir ) {
                        return 'url(' . $css_dir . $matches[1] . ')';
                    },
                    $content
                );

                $combined .= "/* {$handle} */\n" . $content . "\n\n";
            }
            if ( $combined ) {
                $minified = $this->minify_css( $combined );
                $hash     = substr( md5( $minified ), 0, 12 );
                $filename = "bundle-{$hash}.min.css";
                if ( file_put_contents( $this->upload_dir . $filename, $minified ) !== false ) {
                    update_option( 'kit_bundle_css_file', $filename );
                    $built = true;
                }
            }
        }

        if ( $built ) {
            update_option( 'kit_bundle_version', time() );
            set_transient( 'kit_bundle_fresh', time(), 24 * HOUR_IN_SECONDS );
        }

        return $built;

    }

    /**
     * Remove old bundle files from the upload directory.
     *
     * @since 5.0.0
     */
    private function clean_old_bundles() {

        if ( ! is_dir( $this->upload_dir ) ) return;

        $files = glob( $this->upload_dir . 'bundle-*' );
        if ( ! $files ) return;

        foreach ( $files as $file ) {
            @unlink( $file );
        }

    }

    // =========================================================================
    // MINIFICATION
    // =========================================================================

    /**
     * Minify JavaScript.
     *
     * @since 5.0.0
     */
    private function minify_js( $js ) {

        // Remove multi-line comments.
        $js = preg_replace( '~/\*.*?\*/~s', '', $js );

        // Remove single-line comments (but not URLs).
        $js = preg_replace( '~//(?!["\':])([^\n]*)~', '', $js );

        // Collapse whitespace.
        $js = preg_replace( '/\s+/', ' ', $js );

        // Remove spaces around operators/punctuation.
        $js = preg_replace( '/\s*([{}();,:])\s*/', '$1', $js );

        return trim( $js );

    }

    /**
     * Minify CSS.
     *
     * @since 5.0.0
     */
    private function minify_css( $css ) {

        // Remove comments.
        $css = preg_replace( '!/\*.*?\*/!s', '', $css );

        // Collapse whitespace.
        $css = preg_replace( '/\s+/', ' ', $css );

        // Remove spaces around punctuation.
        $css = preg_replace( '/\s*([{}:;,>+~])\s*/', '$1', $css );

        // Remove trailing semicolons before closing braces.
        $css = str_replace( ';}', '}', $css );

        return trim( $css );

    }

    // =========================================================================
    // ADMIN BAR
    // =========================================================================

    /**
     * Add rebuild button to the admin bar.
     *
     * @since 5.0.0
     */
    public function add_admin_bar_button( $wp_admin_bar ) {

        if ( ! current_user_can( 'manage_options' ) ) return;

        $wp_admin_bar->add_node( [
            'id'    => 'kit-rebuild-bundle',
            'title' => '⚡ Rebuild Bundles',
            'href'  => wp_nonce_url( admin_url( 'admin.php?page=builtmighty&kit_rebuild_bundle=1' ), 'kit_rebuild_bundle' ),
            'meta'  => [
                'title' => 'Rebuild CSS/JS bundles',
            ],
        ] );

    }

    // =========================================================================
    // REBUILD TRIGGERS
    // =========================================================================

    /**
     * Handle rebuild via query string.
     *
     * @since 5.0.0
     */
    public function maybe_rebuild_from_querystring() {

        if ( ! isset( $_GET['kit_rebuild_bundle'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'kit_rebuild_bundle' ) ) return;

        $this->rebuild_bundles();

        // Redirect back without the query params.
        wp_safe_redirect( remove_query_arg( [ 'kit_rebuild_bundle', '_wpnonce' ] ) );
        exit;

    }

    /**
     * Handle AJAX rebuild request.
     *
     * @since 5.0.0
     */
    public function ajax_rebuild() {

        check_ajax_referer( 'kit_rebuild_bundle', '_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $result = $this->rebuild_bundles();

        if ( $result ) {
            wp_send_json_success( [
                'message' => 'Bundles rebuilt successfully.',
                'time'    => current_time( 'M j, Y g:i A' ),
            ] );
        }

        // Provide a specific error message.
        $detected    = get_transient( 'kit_detected_assets' );
        $js_handles  = get_option( 'kit_bundled_js_handles', [] );
        $css_handles = get_option( 'kit_bundled_css_handles', [] );

        if ( ! $detected ) {
            wp_send_json_error( 'No assets detected yet. Visit your site\'s frontend first, then try again.' );
        } elseif ( empty( $js_handles ) && empty( $css_handles ) ) {
            wp_send_json_error( 'No assets selected. Check some assets in the list above, save, then rebuild.' );
        } else {
            wp_send_json_error( 'Rebuild failed. Selected assets may no longer exist on disk.' );
        }

    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Convert an enqueued asset URL to a local file path.
     *
     * @since 5.0.0
     */
    private function url_to_path( $src ) {

        // Strip query strings (e.g., ?ver=1.0) before resolving path.
        $src = strtok( $src, '?' );

        // Strip fragment identifiers.
        $src = strtok( $src, '#' );

        // Handle protocol-relative URLs.
        if ( strpos( $src, '//' ) === 0 ) {
            $src = 'https:' . $src;
        }

        // Must be a local URL.
        $site_url    = site_url();
        $content_url = content_url();
        $abspath     = ABSPATH;

        // Try content URL first (most plugin/theme assets).
        if ( strpos( $src, $content_url ) === 0 ) {
            return WP_CONTENT_DIR . substr( $src, strlen( $content_url ) );
        }

        // Try site URL.
        if ( strpos( $src, $site_url ) === 0 ) {
            return $abspath . substr( $src, strlen( $site_url ) + 1 );
        }

        // Try root-relative URLs.
        if ( strpos( $src, '/' ) === 0 && strpos( $src, '//' ) !== 0 ) {
            $path = $abspath . ltrim( $src, '/' );
            if ( file_exists( $path ) ) return $path;
        }

        return false;

    }

    /**
     * Check if a handle should be excluded from bundling.
     *
     * @since 5.0.0
     */
    private function is_excluded_handle( $handle ) {

        foreach ( $this->excluded_prefixes as $prefix ) {
            if ( $handle === $prefix || strpos( $handle, $prefix ) === 0 ) {
                return true;
            }
        }

        return false;

    }

    /**
     * Check if a source URL is external.
     *
     * @since 5.0.0
     */
    private function is_external_src( $src ) {

        // Empty or data URIs.
        if ( empty( $src ) || strpos( $src, 'data:' ) === 0 ) return true;

        // Admin assets.
        if ( strpos( $src, '/wp-admin/' ) !== false ) return true;
        if ( strpos( $src, '/wp-includes/' ) !== false ) return true;

        // CDN / external domains.
        $external_domains = [
            'googleapis.com',
            'cloudflare.com',
            'jsdelivr.net',
            'cdnjs.com',
            'unpkg.com',
            'js.stripe.com',
            'google.com',
            'gstatic.com',
            'facebook.net',
        ];

        foreach ( $external_domains as $domain ) {
            if ( strpos( $src, $domain ) !== false ) return true;
        }

        // Not a local URL.
        $site_host = wp_parse_url( site_url(), PHP_URL_HOST );
        if ( strpos( $src, '//' ) !== false ) {
            $src_host = wp_parse_url( $src, PHP_URL_HOST );
            if ( $src_host && $src_host !== $site_host ) return true;
        }

        return false;

    }

    // =========================================================================
    // SETTINGS UI (rendered by class-private.php)
    // =========================================================================

    /**
     * Render the asset list with checkboxes for the admin settings page.
     *
     * @since 5.0.0
     */
    public static function render_asset_settings() {

        $detected    = get_transient( 'kit_detected_assets' );
        $js_selected  = get_option( 'kit_bundled_js_handles', [] );
        $css_selected = get_option( 'kit_bundled_css_handles', [] );

        if ( ! is_array( $js_selected ) )  $js_selected  = [];
        if ( ! is_array( $css_selected ) ) $css_selected = [];

        // Bundle status.
        $bundle_time = get_transient( 'kit_bundle_fresh' );
        $js_file     = get_option( 'kit_bundle_js_file', '' );
        $css_file    = get_option( 'kit_bundle_css_file', '' );

        ?>
        <div class="builtmighty-field builtmighty-performance-field">
            <span class="builtmighty-field-label">Asset Bundler</span>

            <?php if ( $bundle_time ) : ?>
                <div style="background:#e7f5e7;border:1px solid #8bc38b;border-radius:4px;padding:8px 12px;margin-bottom:12px;">
                    <strong>Bundles active.</strong>
                    Last built: <?php echo esc_html( date( 'M j, Y g:i A', (int) $bundle_time ) ); ?>
                    <?php
                    $upload_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'builtmighty-kit/';
                    if ( $js_file && file_exists( $upload_dir . $js_file ) ) {
                        echo ' &mdash; JS: ' . esc_html( size_format( filesize( $upload_dir . $js_file ) ) );
                    }
                    if ( $css_file && file_exists( $upload_dir . $css_file ) ) {
                        echo ' &mdash; CSS: ' . esc_html( size_format( filesize( $upload_dir . $css_file ) ) );
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div style="margin-bottom:12px;">
                <button type="button" id="kit-rebuild-bundle" class="button button-secondary">Rebuild Bundles</button>
                <span id="kit-rebuild-status" style="margin-left:8px;"></span>
            </div>

            <?php if ( ! $detected || ( empty( $detected['js'] ) && empty( $detected['css'] ) ) ) : ?>
                <p class="description" style="color:#b32d2e;">
                    No assets detected yet. Visit your site's frontend in an incognito/private window (logged out), then reload this page.
                </p>
            <?php else : ?>

                <?php if ( ! empty( $detected['js'] ) ) : ?>
                    <h4 style="margin:16px 0 8px;">JavaScript</h4>
                    <div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;">
                        <?php foreach ( $detected['js'] as $handle => $info ) :
                            $checked = in_array( $handle, $js_selected, true ) ? ' checked' : '';
                            $short_src = str_replace( ABSPATH, '/', $info['path'] );
                        ?>
                            <label style="display:block;padding:4px 0;border-bottom:1px solid #f0f0f0;">
                                <input type="checkbox" name="kit_bundled_js_handles[]" value="<?php echo esc_attr( $handle ); ?>"<?php echo $checked; ?> />
                                <strong><?php echo esc_html( $handle ); ?></strong>
                                <span style="color:#888;font-size:12px;margin-left:6px;"><?php echo esc_html( $short_src ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $detected['css'] ) ) : ?>
                    <h4 style="margin:16px 0 8px;">CSS</h4>
                    <div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;">
                        <?php foreach ( $detected['css'] as $handle => $info ) :
                            $checked = in_array( $handle, $css_selected, true ) ? ' checked' : '';
                            $short_src = str_replace( ABSPATH, '/', $info['path'] );
                        ?>
                            <label style="display:block;padding:4px 0;border-bottom:1px solid #f0f0f0;">
                                <input type="checkbox" name="kit_bundled_css_handles[]" value="<?php echo esc_attr( $handle ); ?>"<?php echo $checked; ?> />
                                <strong><?php echo esc_html( $handle ); ?></strong>
                                <span style="color:#888;font-size:12px;margin-left:6px;"><?php echo esc_html( $short_src ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="description" style="margin-top:8px;">
                    Last scan: <?php echo esc_html( date( 'M j, Y g:i A', $detected['updated'] ) ); ?>
                    &mdash; <?php echo count( $detected['js'] ); ?> JS, <?php echo count( $detected['css'] ); ?> CSS detected.
                    Select assets to bundle, save, then click <strong>Rebuild Bundles</strong>.
                </p>

            <?php endif; ?>

        </div>

        <script>
        jQuery(function($) {
            $('#kit-rebuild-bundle').on('click', function() {
                var $btn = $(this), $status = $('#kit-rebuild-status');
                $btn.prop('disabled', true);
                $status.text('Rebuilding...');
                $.post(ajaxurl, {
                    action: 'kit_rebuild_bundle',
                    _nonce: '<?php echo wp_create_nonce( 'kit_rebuild_bundle' ); ?>'
                }, function(res) {
                    $btn.prop('disabled', false);
                    if (res.success) {
                        $status.css('color', 'green').text(res.data.message + ' (' + res.data.time + ')');
                    } else {
                        $status.css('color', 'red').text(res.data || 'Rebuild failed.');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $status.css('color', 'red').text('Request failed.');
                });
            });
        });
        </script>
        <?php

    }

}
