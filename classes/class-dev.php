<?php
/**
 * Dev.
 * 
 * Adds cosmetic WordPress updates and dashboard widgets.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtDev {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Change admin footer.
        add_filter( 'admin_footer_text', [ $this, 'footer_text' ] );

        // Add a dashboard widget.
        add_action( 'wp_dashboard_setup', [ $this, 'dashboard_widget' ] );

        // Add admin notification for dev sites.
        add_action( 'admin_notices', [ $this, 'admin_notice' ] );
        
    }

    /**
     * Update footer text.
     * 
     * @since   1.0.0
     */
    public function footer_text() {

        // Return footer text.
        return '🔨 Proudly developed by <a href="https://builtmighty.com" target="_blank">Built Mighty</a>.';

    }

    /**
     * Add a dashboard widget for Built Mighty.
     * 
     * @since   1.0.0
     */
    public function dashboard_widget() {

        // Add dashboard widget.
        wp_add_dashboard_widget( 'builtmighty_dashboard_widget', 'Built Mighty', [ $this, 'dashboard_content' ] );

    }

    /**
     * Dashboard content.
     * 
     * @since   1.0.0
     */
    public function dashboard_content() {

        // Check if we're on a dev site.
        if( ! is_built_mighty() ) {

            // Check for disabled plugins.
            if( get_option( 'built_disabled_plugins' ) ) {

                // Get disabled plugins.
                $disabled_plugins = get_option( 'built_disabled_plugins' );

                // Display disabled plugins.
                echo '<p><strong>Disabled Plugins:</strong></p>';
                echo '<ul>';
                foreach( $disabled_plugins as $plugin ) {
                    echo '<li>' . $plugin . '</li>';
                }
                echo '</ul>';

            } else {

                // Display dev content.
                echo 'This is a Built Mighty development site. No plugins have been disabled, at this time.';

            }

        } else {

            // Display production content.
            echo 'Welcome! Thanks for being a Built Mighty client. If you need us, please reach out to your project manager or open a ticket here.';

        }

    }

    /**
     * Add admin notice.
     * 
     * @since   1.0.0
     */
    public function admin_notice() {

        // Check if we're on a dev site.
        if( is_built_mighty() ) {

            // Display dev content.
            echo '<div class="notice notice-warning is-dismissible"><p>NOTICE &mdash; This is a Built Mighty development site.</p></div>';

        }

    }

}