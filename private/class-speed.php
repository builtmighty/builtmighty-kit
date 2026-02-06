<?php
/**
 * Speed.
 *
 * Increase the speed of the site.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 5.0.0
 */
namespace BuiltMightyKit\Private;

class speed {

    /**
     * Construct.
     *
     * @since   1.0.0
     */
    public function __construct() {

        // Core optimizations (always on).
        add_action( 'init', [ $this, 'emojis' ] );
        add_action( 'heartbeat_settings', [ $this, 'heartbeat' ], 1 );
        add_filter( 'wp_revisions_to_keep', [ $this, 'post_revisions' ], 10, 2 );
        add_action( 'wp_dashboard_setup', [ $this, 'dashboard_widgets' ], 999 );
        add_action( 'wp_user_dashboard_setup', [ $this, 'dashboard_widgets' ], 999 );
        add_filter( 'action_scheduler_retention_period', [ $this, 'action_scheduler' ] );

        // Optional optimizations.
        $this->init_optional_optimizations();

    }

    /**
     * Initialize optional optimizations based on settings.
     *
     * @since   5.0.0
     */
    private function init_optional_optimizations() {

        // Disable Cart Fragments AJAX.
        if ( get_option( 'kit_disable_cart_fragments' ) === 'enable' ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'disable_cart_fragments' ], 99 );
        }

        // Disable WC Scripts on Non-WC Pages.
        if ( get_option( 'kit_disable_wc_scripts' ) === 'enable' ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'disable_wc_scripts' ], 99 );
        }

        // Disable jQuery Migrate.
        if ( get_option( 'kit_disable_jquery_migrate' ) === 'enable' ) {
            add_action( 'wp_default_scripts', [ $this, 'disable_jquery_migrate' ] );
        }

        // Remove Query Strings.
        if ( get_option( 'kit_remove_query_strings' ) === 'enable' ) {
            add_filter( 'script_loader_src', [ $this, 'remove_query_strings' ], 15 );
            add_filter( 'style_loader_src', [ $this, 'remove_query_strings' ], 15 );
        }

        // Disable WC Admin Features.
        if ( get_option( 'kit_disable_wc_admin' ) === 'enable' ) {
            add_filter( 'woocommerce_admin_disabled', '__return_true' );
            add_filter( 'woocommerce_admin_features', [ $this, 'disable_wc_admin_features' ] );
        }

        // Disable Marketing Hub.
        if ( get_option( 'kit_disable_marketing_hub' ) === 'enable' ) {
            add_filter( 'woocommerce_admin_features', [ $this, 'disable_marketing_hub' ] );
            add_filter( 'woocommerce_marketplace_suggestions_enabled', '__return_false' );
        }

        // Cleanup Head Tags.
        if ( get_option( 'kit_cleanup_head' ) === 'enable' ) {
            $this->cleanup_head();
        }

        // DNS Prefetch.
        if ( get_option( 'kit_dns_prefetch' ) === 'enable' ) {
            add_action( 'wp_head', [ $this, 'dns_prefetch' ], 1 );
        }

        // Disable Password Strength Meter.
        if ( get_option( 'kit_disable_password_meter' ) === 'enable' ) {
            add_action( 'wp_print_scripts', [ $this, 'disable_password_strength_meter' ], 100 );
        }

    }

    /**
     * Dequeue emojis.
     *
     * @since   1.0.0
     */
    public function emojis() {

        // Remove emojis.
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );

    }

    /**
     * Adjust heartbeat.
     *
     * @since   1.0.0
     */
    public function heartbeat( $settings ) {

        // Adjust heartbeat.
        $settings['interval'] = apply_filters( 'builtmighty_heartbeat', 60 );

        // Return settings.
        return $settings;

    }

    /**
     * Adjust post revisions.
     *
     * @since   1.0.0
     */
    public function post_revisions( $num, $post ) {

        // Return.
        return apply_filters( 'builtmighty_revisions', 3 );

    }

    /**
     * Remove dashboard widgets.
     *
     * @since   1.0.0
     */
    public function dashboard_widgets() {

        // Set meta boxes.
        $meta_boxes = [
            'dashboard_primary' => [
                'screen'    => 'dashboard',
                'context'   => 'side',
            ],
            'dashboard_secondary' => [
                'screen'    => 'dashboard',
                'context'   => 'side',
            ],
            'dashboard_quick_press' => [
                'screen'    => 'dashboard',
                'context'   => 'side',
            ],
            'dashboard_recent_drafts' => [
                'screen'    => 'dashboard',
                'context'   => 'side',
            ],
            'dashboard_php_nag' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'dashboard_site_health' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'dashboard_browser_nag' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'health_check_status' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'dashboard_activity' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'dashboard_right_now' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'network_dashboard_right_now' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'dashboard_recent_comments' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'dashboard_incoming_links' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'dashboard_plugins' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'e-dashboard-overview' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'monsterinsights_reports_widget' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'bbp-dashboard-right-now' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'fue-dashboard' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'yoast_db_widget' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'wpseo-dashboard-overview' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'yith_dashboard_products_news' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'yith_dashboard_blog_news' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'rg_forms_dashboard' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'appscreo_news' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'wpematico_widget' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'wpe_dify_news_feed' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'wc_admin_dashboard_setup' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
            'woocommerce_dashboard_status' => [
                'screen'    => 'dashboard',
                'context'   => 'normal',
            ],
        ];

        // Filters.
        $meta_boxes = apply_filters( 'builtmighty_kit_dashboard_widgets', $meta_boxes );

        // Loop through meta boxes.
        foreach ( $meta_boxes as $id => $meta_box ) {

            // Remove meta box.
            remove_meta_box( $id, $meta_box['screen'], $meta_box['context'] );

        }

    }

    /**
     * Set action scheduler retention to 5 days.
     *
     * @since   1.0.0
     */
    public function action_scheduler() {

        // Return.
        return apply_filters( 'builtmighty_actionscheduler_retention', 5 * DAY_IN_SECONDS );

    }

    /**
     * Disable WooCommerce Cart Fragments AJAX.
     *
     * This is one of the biggest performance killers in WooCommerce.
     * It makes an AJAX call on every single page load to update cart totals.
     *
     * @since   5.0.0
     */
    public function disable_cart_fragments() {

        // Only run on frontend.
        if ( is_admin() ) {
            return;
        }

        // Check if WooCommerce is active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Check for excluded pages.
        if ( $this->is_excluded_page( 'kit_cart_fragments_exclude' ) ) {
            return;
        }

        // Dequeue cart fragments.
        wp_dequeue_script( 'wc-cart-fragments' );

    }

    /**
     * Disable WooCommerce scripts and styles on non-WooCommerce pages.
     *
     * @since   5.0.0
     */
    public function disable_wc_scripts() {

        // Only run on frontend.
        if ( is_admin() ) {
            return;
        }

        // Check if WooCommerce is active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Check if this is a WooCommerce page.
        if ( $this->is_woocommerce_page() ) {
            return;
        }

        // Check for excluded pages.
        if ( $this->is_excluded_page( 'kit_wc_scripts_exclude' ) ) {
            return;
        }

        // Dequeue WooCommerce styles.
        wp_dequeue_style( 'woocommerce-general' );
        wp_dequeue_style( 'woocommerce-layout' );
        wp_dequeue_style( 'woocommerce-smallscreen' );
        wp_dequeue_style( 'woocommerce_frontend_styles' );
        wp_dequeue_style( 'woocommerce_fancybox_styles' );
        wp_dequeue_style( 'woocommerce_chosen_styles' );
        wp_dequeue_style( 'woocommerce_prettyPhoto_css' );
        wp_dequeue_style( 'wc-blocks-style' );
        wp_dequeue_style( 'wc-blocks-vendors-style' );

        // Dequeue WooCommerce scripts.
        wp_dequeue_script( 'wc-add-to-cart' );
        wp_dequeue_script( 'wc-cart-fragments' );
        wp_dequeue_script( 'woocommerce' );
        wp_dequeue_script( 'jquery-blockui' );
        wp_dequeue_script( 'jquery-placeholder' );
        wp_dequeue_script( 'jquery-payment' );
        wp_dequeue_script( 'jqueryui' );
        wp_dequeue_script( 'wc-add-to-cart-variation' );
        wp_dequeue_script( 'wc-single-product' );
        wp_dequeue_script( 'wc-checkout' );

    }

    /**
     * Check if current page is a WooCommerce page.
     *
     * @return  bool  True if WooCommerce page.
     *
     * @since   5.0.0
     */
    private function is_woocommerce_page() {

        if ( ! function_exists( 'is_woocommerce' ) ) {
            return false;
        }

        return (
            is_woocommerce() ||
            is_cart() ||
            is_checkout() ||
            is_account_page() ||
            is_product() ||
            is_product_category() ||
            is_product_tag() ||
            is_shop()
        );

    }

    /**
     * Check if current page is in the exclusion list.
     *
     * @param   string $option_name  Option name for exclusions.
     *
     * @return  bool  True if excluded.
     *
     * @since   5.0.0
     */
    private function is_excluded_page( $option_name ) {

        $excluded = get_option( $option_name, '' );

        if ( empty( $excluded ) ) {
            return false;
        }

        // Get current page ID.
        $current_page_id = get_queried_object_id();

        // Parse excluded pages.
        $excluded_pages = array_map( 'trim', explode( ',', $excluded ) );

        foreach ( $excluded_pages as $page ) {
            // Check by page ID.
            if ( is_numeric( $page ) && (int) $page === $current_page_id ) {
                return true;
            }

            // Check by slug.
            if ( ! is_numeric( $page ) ) {
                $page_obj = get_page_by_path( $page );
                if ( $page_obj && $page_obj->ID === $current_page_id ) {
                    return true;
                }

                // Check if current URL contains the slug.
                $current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
                if ( strpos( $current_url, '/' . $page ) !== false ) {
                    return true;
                }
            }
        }

        return false;

    }

    /**
     * Disable jQuery Migrate.
     *
     * @param   \WP_Scripts $scripts  Scripts object.
     *
     * @since   5.0.0
     */
    public function disable_jquery_migrate( $scripts ) {

        // Only on frontend, not admin.
        if ( is_admin() ) {
            return;
        }

        if ( ! empty( $scripts->registered['jquery'] ) ) {
            $scripts->registered['jquery']->deps = array_diff(
                $scripts->registered['jquery']->deps,
                [ 'jquery-migrate' ]
            );
        }

    }

    /**
     * Remove query strings from static resources.
     *
     * @param   string $src  Source URL.
     *
     * @return  string  Modified URL.
     *
     * @since   5.0.0
     */
    public function remove_query_strings( $src ) {

        if ( strpos( $src, '?ver=' ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }

        return $src;

    }

    /**
     * Disable WooCommerce Admin features.
     *
     * @param   array $features  Features array.
     *
     * @return  array  Modified features.
     *
     * @since   5.0.0
     */
    public function disable_wc_admin_features( $features ) {

        // Remove all WC Admin features.
        return [];

    }

    /**
     * Disable Marketing Hub.
     *
     * @param   array $features  Features array.
     *
     * @return  array  Modified features.
     *
     * @since   5.0.0
     */
    public function disable_marketing_hub( $features ) {

        // Remove marketing hub from features.
        $marketing_features = [
            'marketing',
            'coupons',
            'marketing-coupons',
        ];

        return array_diff( $features, $marketing_features );

    }

    /**
     * Cleanup unnecessary head tags.
     *
     * @since   5.0.0
     */
    private function cleanup_head() {

        // Remove RSD link.
        remove_action( 'wp_head', 'rsd_link' );

        // Remove wlwmanifest link (Windows Live Writer).
        remove_action( 'wp_head', 'wlwmanifest_link' );

        // Remove shortlink.
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
        remove_action( 'template_redirect', 'wp_shortlink_header', 11 );

        // Remove WordPress generator tag.
        remove_action( 'wp_head', 'wp_generator' );

        // Remove oEmbed discovery links.
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );

        // Remove REST API link.
        remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );

        // Remove adjacent posts links (prev/next).
        remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );

        // Remove WordPress version from RSS feeds.
        add_filter( 'the_generator', '__return_empty_string' );

        // Disable XML-RPC RSD link.
        add_filter( 'xmlrpc_enabled', '__return_false' );

        // Remove global styles (for non-block themes).
        if ( get_option( 'kit_remove_global_styles' ) === 'enable' ) {
            remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
            remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
        }

    }

    /**
     * Add DNS prefetch hints for external resources.
     *
     * @since   5.0.0
     */
    public function dns_prefetch() {

        // Get custom DNS prefetch domains.
        $custom_domains = get_option( 'kit_dns_prefetch_domains', '' );

        // Default domains to prefetch.
        $domains = [
            '//fonts.googleapis.com',
            '//fonts.gstatic.com',
            '//ajax.googleapis.com',
            '//www.google-analytics.com',
            '//www.googletagmanager.com',
            '//cdnjs.cloudflare.com',
        ];

        // Add custom domains.
        if ( ! empty( $custom_domains ) ) {
            $custom = array_map( 'trim', explode( "\n", $custom_domains ) );
            foreach ( $custom as $domain ) {
                if ( ! empty( $domain ) ) {
                    // Ensure it starts with //.
                    if ( strpos( $domain, '//' ) !== 0 && strpos( $domain, 'http' ) !== 0 ) {
                        $domain = '//' . $domain;
                    }
                    // Remove http: or https: prefix.
                    $domain = preg_replace( '/^https?:/', '', $domain );
                    $domains[] = $domain;
                }
            }
        }

        // Allow filtering.
        $domains = apply_filters( 'kit_dns_prefetch_domains', $domains );

        // Output DNS prefetch links.
        foreach ( array_unique( $domains ) as $domain ) {
            printf(
                '<link rel="dns-prefetch" href="%s" />' . "\n",
                esc_attr( $domain )
            );
        }

        // Add preconnect for critical resources.
        $preconnect = [
            '//fonts.googleapis.com',
            '//fonts.gstatic.com',
        ];

        $preconnect = apply_filters( 'kit_preconnect_domains', $preconnect );

        foreach ( array_unique( $preconnect ) as $domain ) {
            printf(
                '<link rel="preconnect" href="%s" crossorigin />' . "\n",
                esc_attr( $domain )
            );
        }

    }

    /**
     * Disable password strength meter script.
     *
     * The zxcvbn.js script is ~800KB and loaded on checkout/account pages.
     *
     * @since   5.0.0
     */
    public function disable_password_strength_meter() {

        // Only on frontend.
        if ( is_admin() ) {
            return;
        }

        // Check if we're on a page that needs it.
        if ( ! class_exists( 'WooCommerce' ) ) {
            // For non-WooCommerce, only dequeue on non-password pages.
            if ( ! is_page( 'password-reset' ) && ! is_page( 'lost-password' ) ) {
                wp_dequeue_script( 'zxcvbn-async' );
                wp_dequeue_script( 'password-strength-meter' );
            }
            return;
        }

        // For WooCommerce, check if we're on account/checkout pages with registration.
        $is_account = function_exists( 'is_account_page' ) && is_account_page();
        $is_checkout = function_exists( 'is_checkout' ) && is_checkout();

        // Dequeue if not on account page or checkout with registration.
        if ( ! $is_account && ! $is_checkout ) {
            wp_dequeue_script( 'zxcvbn-async' );
            wp_dequeue_script( 'password-strength-meter' );
            wp_dequeue_script( 'wc-password-strength-meter' );
        }

        // Even on checkout, only keep if registration is enabled.
        if ( $is_checkout && 'yes' !== get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) ) {
            wp_dequeue_script( 'zxcvbn-async' );
            wp_dequeue_script( 'password-strength-meter' );
            wp_dequeue_script( 'wc-password-strength-meter' );
        }

    }

}
