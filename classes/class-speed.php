<?php
/**
 * Speed.
 * 
 * Adjusts certain pieces of WordPress for speed.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtSpeed {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Dequeue emojis.
        add_action( 'init', [ $this, 'emojis' ] );

        // Adjust heartbeat time.
        add_action( 'heartbeat_settings', [ $this, 'heartbeat' ], 1 );

        // Adjust post revisions.
        add_filter( 'wp_revisions_to_keep', [ $this, 'post_revisions' ], 10, 2 );

        // Remove dashboard widgets.
        add_action( 'wp_dashboard_setup', [ $this, 'dashboard_widgets' ], 999 );
        add_action( 'wp_user_dashboard_setup', [ $this, 'dashboard_widgets' ], 999 );

        // Set action scheduler retention to 5 days.
        add_filter( 'action_scheduler_retention_period', [ $this, 'action_scheduler' ] );

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
        $settings['interval'] = 60;

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
        return 3;

    }

    /**
     * Remove dashboard widgets.
     * 
     * @since   1.0.0
     */
    public function dashboard_widgets() {

        // WordPress.
        remove_action( 'welcome_panel', 'wp_welcome_panel' );
        remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_php_nag', 'dashboard', 'normal' );
        remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_browser_nag', 'dashboard', 'normal' );
		remove_meta_box( 'health_check_status', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		remove_meta_box( 'network_dashboard_right_now', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );

        // Elementor.
        remove_meta_box( 'e-dashboard-overview', 'dashboard', 'normal' );

        // MonsterInsights.
        remove_meta_box( 'monsterinsights_reports_widget', 'dashboard', 'normal' );

        // BBPress.
        remove_meta_box( 'bbp-dashboard-right-now', 'dashboard', 'normal' );

        // Follow-up Emails.
        remove_meta_box( 'fue-dashboard', 'dashboard', 'normal' );

        // Yoast SEO.
        remove_meta_box( 'yoast_db_widget', 'dashboard', 'normal' );
        remove_meta_box( 'wpseo-dashboard-overview', 'dashboard', 'normal' );

        // YITH.
        remove_meta_box( 'yith_dashboard_products_news', 'dashboard', 'normal' );
        remove_meta_box( 'yith_dashboard_blog_news', 'dashboard', 'normal' );

        // Gravity Forms.
        remove_meta_box( 'rg_forms_dashboard', 'dashboard', 'normal' );

        // Apps Creo.
        remove_meta_box( 'appscreo_news', 'dashboard', 'normal' );

        // WP Ematico.
        remove_meta_box( 'wpematico_widget', 'dashboard', 'normal' );

        // WP Engine.
        remove_meta_box( 'wpe_dify_news_feed', 'dashboard', 'normal' );

        // WooCommerce.
        remove_meta_box( 'wc_admin_dashboard_setup', 'dashboard', 'normal' );
        remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' );

    }

    /**
     * Set action scheduler retention to 5 days.
     * 
     * @since   1.0.0
     */
    public function action_scheduler() {

        // Return.
        return 5 * DAY_IN_SECONDS;

    }

}