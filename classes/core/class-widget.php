<?php
/**
 * Widget.
 * 
 * Adds cosmetic WordPress updates and dashboard widgets.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Core;
use function BuiltMightyKit\is_kit_mode;
class builtWidget {

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

        // Process form.
        add_action( 'wp_ajax_built_process_form', [ $this, 'process_form' ] );
        
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

        // Add dashboard widget, if dev.
        if( is_kit_mode() ) {

            // Add dashboard widget.
            wp_add_dashboard_widget( 'builtmighty_checklist_widget', 'Dev Site Checklist', [ $this, 'checklist_content' ] );

        }

    }

    /**
     * Dashboard content.
     * 
     * @since   1.0.0
     */
    public function dashboard_content() {

        // Check if we're on a dev site.
        if( is_kit_mode() ) {

            // Display developer content.
            echo $this->developer_content();

        } else {

            // Get current user.
            $user = wp_get_current_user();

            // Check if user email is @builtmighty.
            if( strpos( $user->user_email, '@builtmighty.com' ) !== false ) {

                // Display developer content.
                echo $this->developer_content();

            } else {

                // Display client content.
                echo $this->client_content();

            }

        }

    }

    /**
     * Checklist content.
     * 
     * @since   1.0.0
     */
    public function checklist_content() {

        // Generate checklist.
        $this->set_checklist();

        // Checklist.
        echo $this->get_checklist();

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

        // Get Jira issues.
        echo $this->get_jira_issues();

        // Get basic Git.
        echo $this->get_git();

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

        // Client content.
        include BUILT_PATH . 'views/core/widget-client-content.php';

        // Get Git.
        echo $this->get_git();
        
    }

    /**
     * Get site info.
     * 
     * @since   1.0.0
     */
    public function get_site_info() {

        // Start.
        ob_start();

        // Site info.
        include BUILT_PATH . 'views/core/widget-site-info.php';

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

            // Disabled plugins.
            include BUILT_PATH . 'views/core/widget-disabled-plugins.php';

        }

        // Return.
        return ob_get_clean();

    }

    /**
     * Get Jira issues.
     * 
     * @since   1.0.0
     */
    public function get_jira_issues() {

        // Start.
        ob_start();

        // Jira issues.
        include BUILT_PATH . 'views/core/widget-jira-issues.php';

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

        // Readme.
        include BUILT_PATH . 'views/core/widget-readme.php';

        // Return.
        return ob_get_clean();

    }

    /**
     * Get Git.
     * 
     * @since   1.0.0
     */
    public function get_git() {

        // Start.
        ob_start();

        // Git.
        BUILT_PATH . 'views/core/widget-git.php';

        // Return.
        return ob_get_clean();

    }

    /**
     * Issue form.
     * 
     * @since   1.0.0
     */
    public function issue_form() {

        // Start output buffering.
        ob_start();

        // Get current user.
        $user = wp_get_current_user();

        // Issue Form.
        include BUILT_PATH . 'views/core/widget-issue-form.php';

        // Return.
        return ob_get_clean();

    }

    /**
     * Contact form.
     * 
     * @since   1.0.0
     */
    public function contact_form() {

        // Start output buffering.
        ob_start();

        // Get current user.
        $user = wp_get_current_user();

        // Contact Form.
        include BUILT_PATH . 'views/core/widget-contact-form.php';

        // Return.
        return ob_get_clean();

    }

    /**
     * Process form.
     * 
     * @since   1.0.0
     */
    public function process_form() {

        // Set status.
        $status = [
            'status'    => 'error',
            'message'   => 'There was an error processing your request. Please make sure all required fields are filled.',
        ];

        // Check for missing data.
        if( empty( $_POST['project'] ) || empty( $_POST['pm'] ) || empty( $_POST['title'] ) || empty( $_POST['desc'] ) ) {

            // Respond.
            echo json_encode( $status );

            // Execute Order 66.
            wp_die();

        }

        // Jira.
        $jira = new \BuiltMightyKit\Plugins\builtJira();

        // Check type.
        if( $_POST['type'] === 'built-issue-save' ) {

            // Create issue.
            $jira->create_issue( $_POST );

            // Set status.
            $status = [
                'status'    => 'success',
                'message'   => 'Your issue has been created.',
            ];

        } else if( $_POST['type'] === 'built-project-save' ) {

            // Get project manager ID.
            $pm = explode( '|', base64_decode( $_POST['pm'] ) );

            // Get user.
            $user = $jira->get_user( $pm[0] );

            // Check for email.
            if( isset( $user['emailAddress'] ) ) {

                // Append site URL to message.
                $_POST['desc'] .= "\n\n — Submitted on: " . site_url( '/' );

                // Append user.
                $_POST['desc'] .= "\n — Submitted by: " . $_POST['user'];

                // Send email.
                wp_mail( $user['emailAddress'], stripslashes( sanitize_text_field( $_POST['title'] ) ), stripslashes( sanitize_text_field( $_POST['desc'] ) ) );

                // Set status.
                $status = [
                    'status'    => 'success',
                    'message'   => 'Your message has been sent.',
                ];

            }

        }

        // Respond.
        echo json_encode( $status );

        // Execute Order 66.
        wp_die();

    }

    /**
     * Generate checklist.
     * 
     * @since   1.0.0
     */
    public function set_checklist() {

        // Check if already generated.
        if( get_option( 'built_checklist' ) ) return get_option( 'built_checklist' );

        // Set data.
        $data = [
            'todo'  => [
                'email'         => [
                    'title'     => 'Emails',
                    'desc'      => 'Ensure that emails, especially subscription renewal notices, welcome emails, or password resets, do not get sent to actual customers. This plugin does its best to stop emails from being sent, but it is not foolproof. It is best to test and perhaps even install an email logger to keep watch.',
                    'status'    => false,
                ],
                'gateways'      => [
                    'title'     => 'Payment Gateways',
                    'desc'      => 'Set payment gateways to test/sandbox mode.',
                    'status'    => false,
                ],
                'indexing'      => [
                    'title'     => 'Indexing',
                    'desc'      => 'Double-check that the site is not being indexed by search engines.',
                    'status'    => false,
                ],
                'access'        => [
                    'title'     => 'Access',
                    'desc'      => 'Set the site so that it is inaccessible to the public. You can do this for non-logged in users by defining BUILT_ACCESS as true within wp-config.php. Example: define( \'BUILT_ACCESS\', true );.',
                    'status'    => false,
                ],
                'subscriptions' => [
                    'title'     => 'Subscriptions',
                    'desc'      => 'Confirm that subscriptions are not being processed by the site and that WooCommerce Subscriptions is in staging mode, if applicable.',
                    'status'    => false,
                ],
                'webhooks'      => [
                    'title'     => 'Webhooks',
                    'desc'      => 'For WooCommerce subscriptions, ensure that webhook endpoints for subscription events are disabled. Also, review any automated tasks or CRON jobs related to subscriptions and adjust them accordingly.',
                    'status'    => false,
                ],
                'apis'          => [
                    'title'     => 'APIs',
                    'desc'      => 'Check that any APIs, especially those that send data to third-party services (e.g., shipping, tax calculation), are in test/sandbox mode.',
                    'status'    => false,
                ],
            ],
            'complete'  => false,
        ];
        
        // Set option.
        update_option( 'built_checklist', $data );

        // Return.
        return $data;

    }

    /**
     * Get checklist.
     * 
     * @since   1.0.0
     */
    public function get_checklist() {

        // Start.
        ob_start();

        // Get checklist.
        $list = $this->set_checklist();

        // Process.
        if( isset( $_POST ) ) $this->process_checklist( $_POST );

        // Checklist.
        include BUILT_PATH . 'views/core/widget-checklist.php';

        // Return.
        return ob_get_clean();

    }

    /** 
     * Process checklist.
     * 
     * @since   1.0.0
     */
    public function process_checklist( $data ) {

        // Get checklist.
        $list = $this->set_checklist();

        // Loop through data.
        foreach( $data as $task_id => $task ) {

            // Update task ID.
            $task_id = str_replace( 'built-task-', '', $task_id );

            // Check if task exists.
            if( ! isset( $list['todo'][$task_id] ) ) continue;

            // Update task.
            $list['todo'][$task_id]['status'] = true;

        }

        // Check if all tasks are complete.
        $list['complete'] = ( in_array( false, array_column( $list['todo'], 'status' ) ) ) ? false : true;

        // Update option.
        update_option( 'built_checklist', $list );

    }

    /**
     * Add admin notice.
     * 
     * @since   1.0.0
     */
    public function admin_notice() {

        // Set.
        $set = false;

        // Check if WP_ENVIRONMENT_TYPE is set.
        if( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {

            // Check if set.
            if( isset( $_POST['set_environment'] ) ) {

                // Get setup.
                $setup = new \BuiltMightyKit\Core\builtSetup();

                // Set environment.
                $set = $setup->set_environment( sanitize_text_field( $_POST['set_environment'] ) );

            }

            // Check if set.
            if( ! $set ) {

                // Display notice. ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>WP_ENVIRONMENT_TYPE</strong> is not defined. What type of environment is this?</p>
                    <form method="post">
                        <div style="margin:0 0 7.5px 0">
                            <button type="submit" name="set_environment" value="local" class="button button-primary">Local</button>
                            <button type="submit" name="set_environment" value="development" class="button button-primary">Development</button>
                            <button type="submit" name="set_environment" value="staging" class="button button-primary">Staging</button>
                            <button type="submit" name="set_environment" value="production" class="button button-primary">Production</button>
                        </div>
                    </form>
                </div><?php

            }

        }

        // Check if we're on a dev site.
        if( is_kit_mode() ) {

            // Display dev content.
            echo '<div class="notice notice-warning is-dismissible"><p>NOTICE &mdash; This is a Built Mighty development site.</p></div>';

        }

    }

}