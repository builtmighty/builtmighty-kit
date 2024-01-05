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

        // Load admin styles.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        
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

            // Display developer content.
            echo $this->developer_content();

        } else {

            // Get current user.
            $user = wp_get_current_user();

            // Check if user email is @builtmighty.
            if( ! strpos( $user->user_email, '@builtmighty.com' ) !== false ) {

                // Display developer content.
                echo $this->developer_content();

            } else {

                // Display client content.
                echo $this->client_content();

            }

        }

    }

    /**
     * Developer content.
     * 
     * @since   1.0.0
     */
    public function developer_content() {

        // Start output buffering.
        ob_start();

        // Get site info.
        echo $this->get_site_info();

        // Get disabled plugins.
        echo $this->get_disabled();

        // Get plugin readme.
        echo $this->get_readme();

        // Return.
        return ob_get_clean();

    }

    /**
     * Client content.
     * 
     * @since   1.0.0
     */
    public function client_content() { 

        // Output. ?>
        <div class="built-dash-head">
            <div class="built-dash-logo">
                <a href="https://builtmighty.com" target="_blank">
                    <img src="<?php echo BUILT_URI; ?>assets/block-builtmighty.png" alt="Built Mighty">
                </a>
            </div>
            <div class="built-dash-message">
                <p>Welcome! Thanks for being a Built Mighty client. We're here to help with any of your WordPress or WooCommerce needs.</p>
            </div>
        </div><?php

        // Check for Jira project or project manager.
        if( ! empty( get_option( 'jira-project' ) ) && ! empty( get_option( 'jira-pm' ) ) ) {

            // Create menu. ?>
            <div class="built-dash-body built-panel">
                <div class="built-dash-nav">
                    <span class="built-nav-button active" id="built-issue">Create Issue</span>
                    <span class="built-nav-button" id="built-pm">Contact Us</span>
                </div>
                <div class="built-dash-forms">
                    <div id="built-issue-form" class="active">
                        Create an issue.
                    </div>
                    <div id="built-pm-form">
                        Contact PM.
                    </div>
                </div>
            </div><?php

        }
        
    }

    /**
     * Get site info.
     * 
     * @since   1.0.0
     */
    public function get_site_info() {

        // Start.
        ob_start();

        // Global.
        global $wpdb;

        // Get information for developers.
        $php    = phpversion();
        $mysql  = $wpdb->db_version();
        $wp     = get_bloginfo( 'version' );

        // Output. ?>
        <div class="built-panel">
            <p style="margin-top:0;"><strong>Developer Info</strong></p>
            <ul style="margin:0;">
                <li>PHP <code><?php echo $php; ?></code></li>
                <li>MySQL <code><?php echo $mysql; ?></code></li>
                <li>WordPress <code><?php echo $wp; ?></code></li>
            </ul>
        </div><?php

        // Return.
        return ob_get_clean();

    }

    /**
     * Get disabled plugins.
     * 
     * @since   1.0.0
     */
    public function get_disabled() {

        // Start.
        ob_start();

        // Check for disabled plugins.
        if( get_option( 'built_disabled_plugins' ) ) {

            // Get disabled plugins.
            $disabled_plugins = get_option( 'built_disabled_plugins' );

            // Display disabled plugins. ?>
            <div class="built-panel">
                <p style="margin-top:0;"><strong>Disabled Plugins</strong></p>
                <ul style="margin:0;"><?php

                    // Loop.
                    foreach( $disabled_plugins as $plugin ) {

                        // Output item. ?>
                        <li><?php echo $plugin; ?> &mdash; <code class="built-flag">Inactive</code></li><?php

                    } ?>

                </ul>
            </div><?php

        }

        // Return.
        return ob_get_clean();

    }

    /**
     * Get plugin readme.
     * 
     * @since   1.0.0
     */
    public function get_readme() {

        // Start.
        ob_start();

        // Display information for plugin readme. ?>
        <div class="built-message">
            <strong><p>Information</p></strong>
            <p>New to the <i>Built Mighty Kit</i>? Check out the <a href="https://github.com/builtmighty/builtmighty-kit/blob/master/README.md" target="_blank">plugin readme</a> for more information.</p>
            <p><a href="https://github.com/builtmighty/builtmighty-kit/blob/master/README.md" target="_blank" class="built-button">View Readme</a></p>
        </div><?php

        // Return.
        return ob_get_clean();

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

    /**
     * Enqueue admin styles.
     * 
     * @since   1.0.0
     */
    public function enqueue() {

        // Check if we're on a dev site.
        if( is_built_mighty() ) {

            // Enqueue admin styles.
            wp_enqueue_style( 'builtmighty-admin', BUILT_URI . 'assets/dev-admin.css', [], BUILT_VERSION );

        } else {

            // Enqueue admin styles.
            wp_enqueue_style( 'builtmighty-admin', BUILT_URI . 'assets/admin.css', [], BUILT_VERSION );

        }

    }

}