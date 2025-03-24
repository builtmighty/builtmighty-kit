<?php
/**
 * Speed.
 *
 * Increase the speed of the site.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Private;
class speed {

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
        foreach( $meta_boxes as $id => $meta_box ) {

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

}