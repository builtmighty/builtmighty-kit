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

            // Get project and project manager.
            $project = get_option( 'jira-project' );
            $pm = explode( '|', base64_decode( get_option( 'jira-pm' ) ) );

            // Set.
            $pm_name = $pm[1];
            $pm_id   = $pm[0];

            // Create menu. ?>
            <div class="built-dash-body built-panel">
                <div class="built-dash-nav">
                    <span class="built-nav-button active" id="built-issue" data-id="built-issue-form">Create Task</span>
                    <span class="built-nav-button" id="built-pm" data-id="built-contact-form">Contact Us</span>
                </div>
                <div class="built-dash-forms">
                    <div class="built-form-status" style="display:none"><p></p></div><?php

                    // Issue form.
                    echo $this->issue_form();
                    
                    // Contact form.
                    echo $this->contact_form(); ?>

                </div>
            </div><?php

        }

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

        // Global.
        global $wpdb;

        // Get information for developers.
        $php    = phpversion();
        $mysql  = $wpdb->db_version();
        $wp     = get_bloginfo( 'version' );

        // Output. ?>
        <div class="built-panel">
            <p style="margin-top:0;"><strong>❔Developer Info</strong></p>
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
                <p style="margin-top:0;"><strong>❗Disabled Plugins</strong></p>
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
     * Get Jira issues.
     * 
     * @since   1.0.0
     */
    public function get_jira_issues() {

        // Start.
        ob_start();

        // Jira.
        $jira = new builtJira();
        $help = new builtJiraHelper();

        // Get issues.
        $issues = $help->sort_issues( $jira->get_issues() );

        // Check for issues.
        if( ! $issues ) return;

        // Set output of issues. ?>
        <div class="built-panel">
            <p style="margin-top:0;"><strong>✅ Issues</strong></p>
            <div class="built-jira-issues">
                <ul style="margin:0;"><?php

                    // Loop through issues.
                    foreach( $issues as $issue_key => $issue ) {

                        // Output. ?>
                        <li class="built-jira-issue">
                            <a href="https://builtmighty.atlassian.net/browse/<?php echo $issue_key; ?>" class="jira-issue-summary" target="_blank"><?php echo $issue['summary']; ?></a>
                            <span class="jira-issue-status <?php echo $issue['class']; ?>"><?php echo strtoupper( $issue['status']['name'] ); ?></span>
                            <span class="jira-issue-assignee"><img src="<?php echo $issue['assignee']['avatarUrls']['24x24']; ?>" /> <?php echo $issue['assignee']['displayName']; ?></span>
                        </li><?php

                    } ?>

                </ul>
                <p style="margin:0;">
                    <a href="https://builtmighty.atlassian.net/projects/<?php echo get_option( 'jira-project' ); ?>" target="_blank" class="built-button" style="margin-top:10px;">View Project</a>
                </p>
            </div>
        </div><?php

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
     * Get Git.
     * 
     * @since   1.0.0
     */
    public function get_git() {

        // Start.
        ob_start();

        // Set path.
        $git = ABSPATH . '/.git';

        // Check if Git is installed.
        if( is_dir( $git ) ) {

            // Get remote origin from .git/config.
            $config = file_get_contents( $git . '/config' );

            // Get repo URL.
            preg_match( '/url = (.*)/', $config, $matches );

            // Get branch.
            $branch = trim( str_replace( 'ref: refs/heads/', '', file_get_contents( $git . '/HEAD' ) ) );

            // Check for matches.
            if( $matches[1] ) {

                // Set repo.
                $repo = str_replace( '.git', '', $matches[1] );

                // Set colors.
                $colors = ( in_array( $branch, [ 'master', 'main', 'prod', 'production' ] ) ) ? ' style="background:green;"' : '';

                // Output. ?>
                <div class="built-panel">
                    <p style="margin-top:0;">
                        <strong>💻 GitHub</strong>
                    </p>
                    <ul style="margin:0;">
                        <li>Branch: <code<?php echo $colors; ?>><?php echo $branch; ?></code></li>
                    </ul>
                    <p style="margin:0;">
                        <a href="<?php echo $matches[1]; ?>" target="_blank" class="built-button" style="margin-top:10px;">View Repo</a>
                    </p>
                </div><?php

            } else {

                // Display message. ?>
                <div class="built-panel">
                    <p style="margin:0;"><strong>A Git repo is not setup.</strong> Create a Git repo to use this feature.</p>
                </div><?php

            }

        } else {

            // Display message. ?>
            <div class="built-panel">
                <p style="margin:0;"><strong>A Git repo is not setup.</strong> Create a Git repo to use this feature.</p>
            </div><?php
            
        }

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

        // Output. ?>
        <div id="built-issue-form" class="built-form active">
            <p>Have a feature request or an issue? Create a new task here and your project manager will review it shortly.</p>
            <input type="hidden" name="built-issue-project" value="<?php echo get_option( 'jira-project' ); ?>">
            <input type="hidden" name="built-issue-pm" value="<?php echo get_option( 'jira-pm' ); ?>">
            <div class="built-issue-field">
                <input type="text" name="built-issue-subject" placeholder="Subject *">
            </div>
            <div class="built-issue-field">
                <textarea name="built-issue-description" placeholder="Description *"></textarea>
            </div>
            <div class="built-issue-field">
                <label>Reported by</label>
                <input type="text" name="built-issue-user" value="<?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)">
            </div>
            <div class="built-issue-field">
                <input type="url" name="built-issue-url" placeholder="Relevant Link">
            </div>
            <div class="built-issue-field built-issue-screenshot" contenteditable="true">
                Paste Screenshot Here
            </div>
            <input type="hidden" name="built-issue-screenshot" value="">
            <div class="built-issue-save">
                <input type="submit" class="button button-primary button-built" name="built-issue-save" value="Send">
            </div>
        </div><?php

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

        // Output. ?>
        <div id="built-contact-form" class="built-form">
            <p>Contact your project manager.</p>
            <input type="hidden" name="built-project-project" value="<?php echo get_option( 'jira-project' ); ?>">
            <input type="hidden" name="built-project-pm" value="<?php echo get_option( 'jira-pm' ); ?>">
            <input type="hidden" name="built-project-user" value="<?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)">
            <div class="built-issue-field">
                <input type="text" name="built-project-subject" placeholder="Subject *">
            </div>
            <div class="built-issue-field">
                <textarea name="built-project-message" placeholder="Message *"></textarea>
            </div>
            <div class="built-issue-save">
                <input type="submit" class="button button-primary button-built" name="built-project-save" value="Send">
            </div>
        </div><?php

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
        $jira = new builtJira();

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

        // Output. ?>
        <div class="built-panel">
            <p style="margin-top:0;"><strong>✔️ Checklist</strong></p>
            <ul style="margin:0;"><?php

                // Check if complete.
                if( $list['complete'] ) {

                    // Output all done. ?>
                    <li>All done! Check list is complete.</li><?php

                } else {

                    // Loop throgh todo.
                    foreach( $list['todo'] as $task_id => $task ) {

                        // If task is complete, set class.
                        $class = ( isset( $_POST['built-task-' . $task_id] ) || $task['status'] ) ? ' built-task-complete' : '';

                        // Output. ?>
                        <li class="built-tasklist">
                            <form method="POST">
                                <div class="built-task-list-actions<?php echo $class; ?>">
                                    <button type="submit" class="built-task-button" name="built-task-<?php echo $task_id; ?>" value="1">✔</button>
                                    <input type="hidden" id="built-task-<?php echo $task_id; ?>" name="built-task-<?php echo $task_id; ?>" value="1" <?php checked( $task['status'], true ); ?>>
                                    <label for="built-task-<?php echo $task_id; ?>"><?php echo $task['title']; ?></label>
                                </div>
                                <p><?php echo $task['desc']; ?></p>
                            </form>
                        </li><?php

                    }

                } ?>
            </ul>
        </div><?php

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

        // Check if we're on a dev site.
        if( is_kit_mode() ) {

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

        // CSS.
        wp_enqueue_style( 'builtmighty-admin', BUILT_URI . 'assets/dev-admin.css', [], BUILT_VERSION );
        wp_enqueue_style( 'builtmighty-admin', BUILT_URI . 'assets/admin.css', [], BUILT_VERSION );

        // JS.
        wp_enqueue_script( 'builtmighty-admin', BUILT_URI . 'assets/dash.js', [ 'jquery' ], BUILT_VERSION, true );

        // Localize.
        wp_localize_script( 'builtmighty-admin', 'built', [
            'ajax' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'builtmighty' ),
        ] );

    }

}