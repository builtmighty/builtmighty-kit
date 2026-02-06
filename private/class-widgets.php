<?php
/**
 * Widgets.
 *
 * WordPress backend informational widgets for developers and clients.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Private;
use function BuiltMightyKit\is_kit_mode;
class widgets {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Change admin footer.
        add_filter( 'admin_footer_text', [ $this, 'footer_text' ], 9999 );

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

    /**
     * Developer Content.
     *
     * @since   1.0.0
     */
    public function developer_content() {

        // Start output buffering.
        ob_start();

        // Site Information.
        echo $this->site_information();

        // Git Information.
        echo $this->git_information();

        // Login Activity (developers only).
        echo $this->login_activity();

        // Systems.
        echo $this->systems();

        // Readme.
        echo $this->readme();

        // Return.
        return ob_get_clean();

    }

    /**
     * Client Content.
     * 
     * @since   1.0.0
     */
    public function client_content() {

        // Start output buffering.
        ob_start();

        // Welcome.
        echo $this->welcome();

        // Check Slack.
        if( ! empty( get_option( 'built_slack_token' ) ) && ! empty( get_option( 'slack-channel' ) ) ) {

            // Slack.
            echo $this->slack();

        }

        // Git Information.
        echo $this->git_information();

        // Return.
        return ob_get_clean();

    }

    /**
     * Site Information.
     * 
     * @since   1.0.0
     */
    public function site_information() {

        // Start output buffering.
        ob_start();

        // Global.
        global $wpdb;
        $data = [
            'PHP'       => phpversion(),
            'MySQL'     => $wpdb->db_version(),
            'WordPress' => get_bloginfo( 'version' )
        ];

        // Check if WooCommerce is active.
        if( class_exists( 'WooCommerce' ) ) {

            // Add to data.
            $data['WooCommerce'] = WC()->version;

        }

        // Panel.
        echo $this->get_panel( '📔 Developer', 'array', $data );

        // Return.
        return ob_get_clean();

    }

    /**
     * Git Information.
     * 
     * @since   1.0.0
     */
    public function git_information() {

        // Start output buffering.
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

                // Set branch.
                $branch = ( in_array( $branch, [ 'master', 'main', 'prod', 'production' ] ) ) ? 'main' : $branch;

                // Output.
                echo $this->get_panel( '💻 GitHub', 'array', [
                    'Repo'   => $repo,
                    'Branch' => $branch
                ] );

            } else {

                // Output.
                echo $this->get_panel( '💻 GitHub', 'array', [
                    'Repo'   => 'A Git repo is not setup.',
                ] );

            }

        } else {

            // Output.
            echo $this->get_panel( '💻 GitHub', 'array', [
                'Repo'   => 'A Git repo is not setup.',
            ] );
            
        }

        // Return.
        return ob_get_clean();

    }

    /**
     * Systems.
     * 
     * TODO: Add features, once all are added.
     * 
     * @since   1.0.0
     */
    public function systems() {

        // Start output buffering.
        ob_start();

        // Output. ?> 
        <div class="built-panel">
            <div class="built-panel-heading">
                <p>🔧 Systems</p>
            </div>
            <div class="built-panel-content">
                <div class="built-panel-header">
                    <div class="built-panel-header-label">
                        <p>Service</p>
                    </div>
                    <div class="built-panel-header-label">
                        <p>Status</p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>👨‍💻 Custom Login URL</p>
                    </div>
                    <div class="built-panel-feature-value status-<?php echo ( get_option( 'kit_enable_login' ) == 'enable' ) ? 'active' : 'inactive'; ?>">
                        <p><?php echo ( get_option( 'kit_enable_login' ) == 'enable' ) ? 'Active' : 'Disabled'; ?></p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>📱 Two-Factor Authentication</p>
                    </div>
                    <div class="built-panel-feature-value status-<?php echo ( get_option( 'kit_enable_2fa' ) == 'enable' ) ? 'active' : 'inactive'; ?>">
                        <p><?php echo ( get_option( 'kit_enable_2fa' ) == 'enable' ) ? 'Active' : 'Disabled'; ?></p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>💬 Slack</p>
                    </div>
                    <div class="built-panel-feature-value status-<?php echo ( ! empty( get_option( 'built_slack_token' ) ) && ! empty( get_option( 'slack-channel' ) ) ) ? 'active' : 'inactive'; ?>">
                        <p><?php echo ( ! empty( get_option( 'built_slack_token' ) ) && ! empty( get_option( 'slack-channel' ) ) ) ? 'Connected' : 'Disconnected'; ?></p>
                    </div>
                </div><?php

                // Check for Slack connection.
                if( ! empty( get_option( 'built_slack_token' ) ) && ! empty( get_option( 'slack-channel' ) ) ) { ?>
                
                    <div class="built-panel-feature">
                        <div class="built-panel-feature-label">
                            <p>📣 Slack Notifications</p>
                        </div>
                        <div class="built-panel-feature-value status-<?php echo ( ! empty( get_option( 'slack-notifications' ) ) ) ? 'active' : 'inactive'; ?>">
                            <p><?php echo ( ! empty( get_option( 'slack-notifications' ) ) ) ? 'Active' : 'Disabled'; ?></p>
                        </div>
                    </div><?php

                }

                // Check for Action Scheduler.
                if( class_exists( '\ActionScheduler' ) ) { ?>
                
                    <div class="built-panel-feature">
                        <div class="built-panel-feature-label">
                            <p>🕒 Action Scheduler</p>
                        </div>
                        <div class="built-panel-feature-value status-<?php echo ( is_kit_mode() && get_option( 'kit_actionscheduler' ) !== 'disable' ) ? 'inactive' : 'active'; ?>">
                            <p><?php echo ( is_kit_mode() && get_option( 'kit_actionscheduler' ) !== 'disable' ) ? 'Blocked' : 'Running'; ?></p>
                        </div>
                    </div><?php

                } ?>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>💽 Theme/Plugin Editor</p>
                    </div>
                    <div class="built-panel-feature-value status-<?php echo ( get_option( 'kit_disable_editor' ) !== 'enable' ) ? 'active' : 'inactive'; ?>">
                        <p><?php echo ( get_option( 'kit_disable_editor' ) !== 'enable' ) ? 'Active' : 'Blocked'; ?></p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>📫 WP Mail</p>
                    </div>
                    <div class="built-panel-feature-value status-<?php echo ( is_kit_mode() && get_option( 'kit_block_email' ) !== 'disable' ) ? 'inactive' : 'active'; ?>">
                        <p><?php echo ( is_kit_mode() && get_option( 'kit_block_email' ) !== 'disable' ) ? 'Blocked' : 'Running'; ?></p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>🚪 Site Access</p>
                    </div>
                    <div class="built-panel-feature-value status-<?php echo ( get_option( 'kit_block_access' ) !== 'disable' ) ? 'inactive' : 'active'; ?>">
                        <p><?php echo ( get_option( 'kit_block_access' ) !== 'disable' ) ? 'Blocked' : 'Accessible'; ?></p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>☕ WP CLI Commands</p>
                    </div>
                    <div class="built-panel-feature-value status-active">
                        <p>Available</p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>💞 WP Heartbeat</p>
                    </div>
                    <div class="built-panel-feature-value status-active">
                        <p>Modified</p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>💾 WP Revisions</p>
                    </div>
                    <div class="built-panel-feature-value status-active">
                        <p>Modified</p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>🕸 XMLRPC</p>
                    </div>
                    <div class="built-panel-feature-value status-inactive">
                        <p>Blocked</p>
                    </div>
                </div>
                <div class="built-panel-feature">
                    <div class="built-panel-feature-label">
                        <p>👥 User Enumeration</p>
                    </div>
                    <div class="built-panel-feature-value status-inactive">
                        <p>Blocked</p>
                    </div>
                </div>
            </div>
        </div><?php

        // Return.
        return ob_get_clean();

    }

    /**
     * Login Activity.
     *
     * Display recent login activity for developers.
     *
     * @since   5.0.0
     */
    public function login_activity() {

        // Check if login logging is enabled.
        if ( get_option( 'kit_login_logging' ) !== 'enable' ) {
            return '';
        }

        // Start output buffering.
        ob_start();

        // Get recent logs.
        $logs = \BuiltMightyKit\Public\login_logging::get_logs( 10 );
        $stats = \BuiltMightyKit\Public\login_logging::get_stats( 7 );

        ?>
        <div class="built-panel">
            <div class="built-panel-heading">
                <p>🔐 Login Activity</p>
            </div>
            <div class="built-panel-content">
                <div class="built-panel-stats" style="display:flex;gap:15px;margin-bottom:15px;">
                    <div style="flex:1;text-align:center;padding:10px;background:#f0f0f1;border-radius:4px;">
                        <strong style="font-size:20px;color:#00a32a;"><?php echo esc_html( $stats['success'] ); ?></strong>
                        <div style="font-size:11px;color:#666;">Successful (7d)</div>
                    </div>
                    <div style="flex:1;text-align:center;padding:10px;background:#f0f0f1;border-radius:4px;">
                        <strong style="font-size:20px;color:#d63638;"><?php echo esc_html( $stats['failed'] ); ?></strong>
                        <div style="font-size:11px;color:#666;">Failed (7d)</div>
                    </div>
                    <div style="flex:1;text-align:center;padding:10px;background:#f0f0f1;border-radius:4px;">
                        <strong style="font-size:20px;color:#2271b1;"><?php echo esc_html( $stats['unique_ips'] ); ?></strong>
                        <div style="font-size:11px;color:#666;">Unique IPs</div>
                    </div>
                </div>
                <?php if ( ! empty( $logs ) ) : ?>
                    <table style="width:100%;font-size:12px;border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid #ddd;">
                                <th style="text-align:left;padding:5px 0;">User</th>
                                <th style="text-align:left;padding:5px 0;">IP</th>
                                <th style="text-align:left;padding:5px 0;">Status</th>
                                <th style="text-align:right;padding:5px 0;">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $logs as $log ) : ?>
                                <tr style="border-bottom:1px solid #f0f0f1;">
                                    <td style="padding:5px 0;"><?php echo esc_html( $log['user_login'] ?? 'Unknown' ); ?></td>
                                    <td style="padding:5px 0;font-family:monospace;font-size:11px;"><?php echo esc_html( $log['ip'] ?? '' ); ?></td>
                                    <td style="padding:5px 0;">
                                        <?php if ( ( $log['type'] ?? '' ) === 'success' ) : ?>
                                            <span style="color:#00a32a;">✓</span>
                                        <?php else : ?>
                                            <span style="color:#d63638;">✗</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:5px 0;text-align:right;color:#666;">
                                        <?php
                                        if ( isset( $log['timestamp'] ) ) {
                                            echo esc_html( human_time_diff( $log['timestamp'], current_time( 'timestamp' ) ) . ' ago' );
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p style="color:#666;font-style:italic;">No login activity recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php

        // Return.
        return ob_get_clean();

    }

    /**
     * Readme.
     *
     * @since   1.0.0
     */
    public function readme() {

        // Start output buffering.
        ob_start();

        // Output.
        echo $this->get_panel( '📓 Information', 'text', '<p>New to the <i>Built Mighty Kit</i>? Check out the <a href="https://github.com/builtmighty/builtmighty-kit/blob/master/README.md" target="_blank">plugin readme</a> for more information.</p><p><a href="https://github.com/builtmighty/builtmighty-kit/blob/master/README.md" target="_blank" class="built-button">View Readme</a></p>' );

        // Return.
        return ob_get_clean();

    }

    /**
     * Welcome.
     * 
     * @since   1.0.0
     */
    public function welcome() {

        // Start output buffering.
        ob_start();

        // Set message.
        $message = 'Welcome! Thanks for being a Built Mighty client. We\'re here to help with any of your WordPress';
        $message .= ( class_exists( 'WooCommerce' ) ) ? ' or WooCommerce needs.' : ' needs.';

        // Output. ?>
        <div class="built-panel built-panel-welcome">
            <div class="built-panel-heading built-panel-logo">
                <a href="https://builtmighty.com" target="_blank">
                    <img src="<?php echo KIT_URI; ?>assets/images/block-builtmighty.png" alt="Built Mighty">
                </a>
            </div>
            <div class="built-panel-content">
                <div class="built-panel-single built-panel-text">
                    <p><?php echo $message; ?></p>
                </div>
            </div>
        </div><?php

        // Return.
        return ob_get_clean();

    }

    /**
     * Slack.
     * 
     * @since   1.0.0
     */
    public function slack() {

        // Check for message/screenshot.
        if( isset( $_POST['slack_message'] ) || isset( $_POST['slack_screenshot'] ) ) {

            // Slack.
            $slack = new \BuiltMightyKit\Utility\slack();

            // Get user.
            $user = get_user_by( 'ID', $_POST['slack_user_id'] );

            // Send message.
            if( isset( $_POST['slack_message'] ) ) {

                // Send.
                $slack->message( 'From: ' . $user->user_email . "\n\n" . sanitize_textarea_field( $_POST['slack_message'] ) );

            }

            // Upload screenshot.
            if( ! empty( $_POST['slack_screenshot'] ) ) {

                // Upload and post image.
                $slack->image( $_POST['slack_screenshot'] );

            }

        }

        // Start output buffering.
        ob_start(); 
        
        // Form. ?>
        <div class="built-panel">
            <div class="built-panel-heading">
                <p>📨 Contact Us</p>
            </div>
            <div class="built-panel-content">
                <p>Have a question or need help? Send us a message.</p>
                <form method="POST">
                    <textarea name="slack_message"></textarea>
                    <div id="slack_screenshot">
                        <span>Have a screenshot? Paste it here.</span>
                    </div>
                    <input type="hidden" name="slack_screenshot" value="" />
                    <input type="hidden" name="slack_user_id" value="<?php echo get_current_user_id(); ?>" />
                    <input type="submit" value="Send" />
                </form>
            </div>
        </div><?php

        // Return.
        return ob_get_clean();

    }

    /**
     * Get panel.
     *
     * @param   string       $title  Panel title.
     * @param   string       $type   Type of panel content ('array' or 'text').
     * @param   array|string $data   Data to display (array for key-value pairs, string for text).
     *
     * @since   1.0.0
     */
    public function get_panel( $title, $type, $data ) {

        // Start output buffering.
        ob_start();

        // Output. ?>
        <div class="built-panel">
            <div class="built-panel-heading">
                <p><?php echo esc_html( $title ); ?></p>
            </div>
            <div class="built-panel-content"><?php

                // Check type.
                if( $type === 'array' && is_array( $data ) ) {

                    // Data.
                    foreach( $data as $key => $value ) {

                        // Output. ?>
                        <div class="built-panel-single built-panel-<?php echo esc_attr( strtolower( $key ) ); ?>">
                            <p class="built-panel-label built-panel-label-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $key ); ?></p>
                            <p class="built-panel-value built-panel-value-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></p>
                        </div><?php

                    }

                } else {

                    // Content. ?>
                    <div class="built-panel-single built-panel-text">
                        <?php echo wp_kses_post( $data ); ?>
                    </div><?php

                } ?>

            </div>
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
        if( is_kit_mode() ) {

            // Display dev content.
            echo '<div class="notice notice-warning"><p>NOTICE &mdash; This is a Built Mighty development site.</p></div>';

        }

    }

}
