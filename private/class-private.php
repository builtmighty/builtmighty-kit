<?php
/**
 * Private.
 *
 * The core.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Private;
use function BuiltMightyKit\is_kit_mode;
class core {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Enqueue.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

        // Load settings.
        add_action( 'admin_init', [ $this, 'site_check' ] );
        add_action( 'admin_init', [ $this, 'settings' ] );

        // On save.
        add_action( 'updated_option', [ $this, 'save' ], 10, 3 );

        // User profile.
        add_action( 'show_user_profile', [ $this, 'builtmighty_admin_color_mode_field' ] );
        add_action( 'edit_user_profile', [ $this, 'builtmighty_admin_color_mode_field' ] );

        // Save user profile.
        add_action( 'personal_options_update', [ $this, 'builtmighty_save_admin_color_mode' ] );
        add_action( 'edit_user_profile_update', [ $this, 'builtmighty_save_admin_color_mode' ] );

    }

    /**
     * Enqueue.
     * 
     * @since   1.0.0
     */
    public function enqueue() {

        // Determine color mode
        $color_mode = 'system';
        if ( is_user_logged_in() ) {
            $user_mode = get_user_meta( get_current_user_id(), 'builtmighty_admin_color_mode', true );
            if ( in_array( $user_mode, [ 'dark', 'light', 'system' ] ) ) {
                $color_mode = $user_mode;
            }
        }

        // CSS.
        wp_enqueue_style( 'builtmighty-kit-admin', KIT_URI . 'public/css/admin.css', [], KIT_VERSION );

        // Add a body class for user override
        if ( $color_mode !== 'system' ) {
            add_filter( 'admin_body_class', function( $classes ) use ( $color_mode ) {
                return "$classes builtmighty-admin-$color_mode-mode";
            });
        }


        // JS.
        wp_enqueue_script( 'builtmighty-kit-admin', KIT_URI . 'public/js/admin.js', [ 'jquery' ], KIT_VERSION, true );

    }

    /**
     * Site check.
     * 
     * @since   1.0.0
     */
    public function site_check() {

        // Check if site URL is stored.
        if( empty( get_option( 'built_siteurl' ) ) ) {

            // Update.
            update_option( 'built_siteurl', site_url() );

        } else {

            // Check if site URL has changed.
            if( get_option( 'built_siteurl' ) === site_url() ) return;

            // Update site URL.
            update_option( 'built_siteurl', site_url() );

            // Check if class Kinsta\Cache_Purge exists.
            if( ! class_exists( '\Kinsta\Cache_Purge' ) ) return;

            // Purge cache.
            $cache = new \Kinsta\Cache_Purge();
            $cache->purge_complete_caches();

        }

    }

    /**
     * Settings.
     * 
     * @since   1.0.0
     */
    public function settings() {

        // Check if class exists.
        if( ! class_exists( '\BuiltMighty\GlobalSettings\settings' ) ) return;

        // Remove Slack.
        if( isset( $_POST['remove_slack'] ) && $_POST['remove_slack'] == true ) {

            // Delete.
            delete_option( 'built_slack_token' );
            delete_option( 'slack-channel' );
            delete_option( 'slack-notifications' );

        }

        // Get settings.
        $settings = \BuiltMighty\GlobalSettings\settings::get_instance();

        // Register a section.
        $settings->add_settings_section(
            'builtmighty_kit',   // ID.
            '🔨 Built Mighty Kit',  // Title.
            function() {
                echo '<p>Settings for the Built Mighty Kit.</p>'; // Description.
            }
        );

        // Add admin color mode setting field to Built Mighty Kit admin screens.
        $settings->select_field(
            'builtmighty_admin_color_mode',
            'Admin Color Mode',
            'builtmighty_kit',
            [
                'system' => 'System Default',
                'light'  => 'Light',
                'dark'   => 'Dark'
            ],
            'Choose your preferred color mode for Built Mighty Kit admin screens. <strong>This setting is unique to your admin account and does not affect other users.</strong>'
        );

        // Save the value as user meta when the settings form is submitted.
        if ( isset( $_POST['builtmighty_admin_color_mode'] ) && is_user_logged_in() ) {

            // Sanitize the text field.
            $mode = sanitize_text_field( $_POST['builtmighty_admin_color_mode'] );

            // Update the user meta.
            if( in_array( $mode, [ 'dark', 'light', 'system' ] ) ) {

                // Update the user meta.
                update_user_meta(
                    get_current_user_id(),
                    'builtmighty_admin_color_mode',
                    $mode
                );

            }

        }

        // Production URL.
        $settings->add_settings_field( 'kit_production_url', '', function() {
            $value = get_option( 'kit_production_url', '' );
            if( $this->is_base64( $value ) ) $value = base64_decode( $value ); ?>
            <div class="builtmighty-field builtmighty-text-field">
                <span class="builtmighty-field-label"><?php echo esc_html( 'Production URL' ); ?></span>
                <div class="builtmighty-field_inner">
                    <input type="text" name="<?php echo esc_attr( 'kit_production_url' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                </div>
                <p class="description">Enter the production URL. No slash required.</p>
            </div><?php
        }, 'builtmighty_kit' );

        // Environment type.
        $settings->select_field(
            'kit_environment',
            'Environment',
            'builtmighty_kit',
            [
                'default'       => 'Default',
                'production'    => 'Production',
                'staging'       => 'Staging',
                'development'   => 'Development',
                'local'         => 'Local'
            ],
            '<strong>Only set if you need to explicitly disable/enable kit mode.</strong> Allow the system to determine mode based on above production URL, if possible.'
        );

        // Stale plugin display.
        $settings->radio_field(
            'kit_stale_plugins', // Field ID.
            'Stale Plugins', // Field label.
            'builtmighty_kit', // The section ID this field will be placed into.
            [ 'developers' => 'Developers Only', 'all' => 'All', 'disable' => 'Disable' ], // Options.
            'Enable a custom WordPress login URL, instead of the default /wp-login.php.' // Description.
        );

        // Enable custom login.
        $settings->radio_field(
            'kit_enable_login', // Field ID.
            'Custom Login URL', // Field label.
            'builtmighty_kit', // The section ID this field will be placed into.
            [ 'enable' => 'Enable', 'disable' => 'Disable' ], // Options.
            'Enable a custom WordPress login URL, instead of the default /wp-login.php.' // Description.
        );

        // Add custom login URL.
        if( get_option( 'kit_enable_login' ) == 'enable' ) { 
            $settings->text_field(
                'kit_login_url', // Field ID.
                'Custom Login Endpoint', // Field label.
                'builtmighty_kit', // The section ID this field will be placed into.
                'Enter the custom login endpoint. No slash required.' // Description.
            );
        }

        // Enable 2FA.
        $settings->radio_field(
            'kit_enable_2fa', // Field ID.
            'Enable Two-Factor Authentication', // Field label.
            'builtmighty_kit', // The section ID this field will be placed into.
            [ 'enable' => 'Enable', 'disable' => 'Disable' ], // Options.
            'Enable two-factor authentication for users.' // Description.
        );

        // Add users.
        if( get_option( 'kit_enable_2fa' ) == 'enable' ) {

            // Get roles.
            global $wp_roles;
            $roles = $wp_roles->get_names();
            unset( $roles['administrator'] );

            $settings->checkboxes_field(
                'kit_2fa_users', // Field ID.
                'Two-Factor Authentication Users', // Field label.
                'builtmighty_kit', // The section ID this field will be placed into.
                $roles, // Options.
                'Select the roles that require two-factor authentication.' // Description.
            );
        }

        // Slack.
        if( empty( get_option( 'built_slack_token' ) ) ) {

            // Set authorization key.
            $key = ( empty( get_option( 'built_api_key' ) ) ) ? bin2hex( random_bytes( 16 ) ) : get_option( 'built_api_key' );
        
            // Save.
            if( empty( get_option( 'built_api_key' ) ) ) update_option( 'built_api_key', $key );

            // Slack.
            $state = http_build_query( [
                'site'  => site_url(),
                'key'   => $key
            ] );
            
            // Start output buffering. 
            ob_start();
            
            // Button. ?>
            <a href="https://slack.com/oauth/v2/authorize?scope=chat%3Awrite%2Cchannels%3Aread%2Cgroups%3Aread%2Cim%3Aread%2Cmpim%3Aread%2Cchannels%3Ajoin%2Cfiles%3Awrite&amp;user_scope=&amp;redirect_uri=https%3A%2F%2Fbuiltmighty.com%2Fwp-json%2Fbuiltmighty-kit%2Fv1%2Fslack&amp;state=<?php echo base64_encode( $state ); ?>&amp;client_id=3387858095.7426170344038" style="align-items:center;color:#000;background-color:#fff;border:1px solid #ddd;border-radius:4px;display:inline-flex;font-family:Lato, sans-serif;font-size:16px;font-weight:600;height:48px;justify-content:center;text-decoration:none;width:236px;margin-bottom:15px;"><svg xmlns="http://www.w3.org/2000/svg" style="height:20px;width:20px;margin-right:12px" viewBox="0 0 122.8 122.8"><path d="M25.8 77.6c0 7.1-5.8 12.9-12.9 12.9S0 84.7 0 77.6s5.8-12.9 12.9-12.9h12.9v12.9zm6.5 0c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9v32.3c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V77.6z" fill="#e01e5a"></path><path d="M45.2 25.8c-7.1 0-12.9-5.8-12.9-12.9S38.1 0 45.2 0s12.9 5.8 12.9 12.9v12.9H45.2zm0 6.5c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H12.9C5.8 58.1 0 52.3 0 45.2s5.8-12.9 12.9-12.9h32.3z" fill="#36c5f0"></path><path d="M97 45.2c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9-5.8 12.9-12.9 12.9H97V45.2zm-6.5 0c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V12.9C64.7 5.8 70.5 0 77.6 0s12.9 5.8 12.9 12.9v32.3z" fill="#2eb67d"></path><path d="M77.6 97c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9-12.9-5.8-12.9-12.9V97h12.9zm0-6.5c-7.1 0-12.9-5.8-12.9-12.9s5.8-12.9 12.9-12.9h32.3c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H77.6z" fill="#ecb22e"></path></svg>Add to Slack</a><?php

            // Message.
            $settings->message(
                'kit_slack_button',
                '',
                ob_get_clean(),
                'builtmighty_kit'
            );

        } else {

            // Get Slack.
            $slack = new \BuiltMightyKit\Utility\slack();
            $notifications = new \BuiltMightyKit\Private\notifications();

            // Start.
            ob_start();

            // Disconnect Slack. ?>
            <button name="remove_slack" class="button button-secondary" value="true"style="align-items:center;color:#000;background:none;border:1px solid #ddd;border-radius:4px;display:inline-flex;font-family:Lato, sans-serif;font-size:16px;font-weight:600;height:48px;justify-content:center;text-decoration:none;width:236px;margin-bottom:15px;"><svg xmlns="http://www.w3.org/2000/svg" style="height:20px;width:20px;margin-right:12px" viewBox="0 0 122.8 122.8"><path d="M25.8 77.6c0 7.1-5.8 12.9-12.9 12.9S0 84.7 0 77.6s5.8-12.9 12.9-12.9h12.9v12.9zm6.5 0c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9v32.3c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V77.6z" fill="#e01e5a"></path><path d="M45.2 25.8c-7.1 0-12.9-5.8-12.9-12.9S38.1 0 45.2 0s12.9 5.8 12.9 12.9v12.9H45.2zm0 6.5c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H12.9C5.8 58.1 0 52.3 0 45.2s5.8-12.9 12.9-12.9h32.3z" fill="#36c5f0"></path><path d="M97 45.2c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9-5.8 12.9-12.9 12.9H97V45.2zm-6.5 0c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V12.9C64.7 5.8 70.5 0 77.6 0s12.9 5.8 12.9 12.9v32.3z" fill="#2eb67d"></path><path d="M77.6 97c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9-12.9-5.8-12.9-12.9V97h12.9zm0-6.5c-7.1 0-12.9-5.8-12.9-12.9s5.8-12.9 12.9-12.9h32.3c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H77.6z" fill="#ecb22e"></path></svg>Disconnect Slack</button><?php

            // Message.
            $settings->message(
                'kit_slack_button',
                '',
                ob_get_clean(),
                'builtmighty_kit'
            );

            // Channel select.
            $settings->select_field(
                'slack-channel', // Field ID.
                'Slack Channel', // Field label.
                'builtmighty_kit', // The section ID this field will be placed into.
                $slack->get_channels(), // Options.
                'Select the channel to send notifications to. These are not only notifications from the site, but can also be messages from the client dashboard.' // Description.
            );

            // Notification options.
            $settings->checkboxes_field(
                'slack-notifications', // Field ID.
                'Slack Notifications', // Field label.
                'builtmighty_kit', // The section ID this field will be placed into.
                $notifications->get_notifications(), // Options.
                'Select the WordPress action notifications to send to Slack.' // Description.
            );

        }

        // Check for Action Scheduler.
        if( class_exists( '\ActionScheduler' ) ) {

            // Action Scheduler.
            $settings->radio_field(
                'kit_actionscheduler', // Field ID.
                'Action Scheduler', // Field label.
                'builtmighty_kit', // The section ID this field will be placed into.
                [ 'enable' => 'Block', 'disable' => 'Enable' ], // Options.
                'Blocked by default on non-production environments. Blocks the Action Scheduler from running.' // Description.
            );

        }

        // Block external.
        $settings->radio_field(
            'kit_block_external', // Field ID.
            'External Requests', // Field label.
            'builtmighty_kit', // The section ID this field will be placed into.
            [ 'enable' => 'Block', 'disable' => 'Enable' ], // Options.
            'Blocked by default on non-production environments. Blocks external API requests. Allowed by default: ' . $_SERVER['SERVER_NAME'] . ', api.wordpress.org, downloads.wordpress.org, github.com, github.dev, github.io, githubusercontent.com, slack.com, builtmighty.com.' // Description.
        );

        // Allowed.
        if( get_option( 'kit_block_external' ) == 'enable' || empty( get_option( 'kit_block_external' ) ) && is_kit_mode() ) {

            // Get allowed.
            $settings->text_field(
                'kit_allowed_external', // Field ID.
                'Allowed External Requests', // Field label.
                'builtmighty_kit', // The section ID this field will be placed into.
                'Enter the allowed external requests separated by a comma.'
            );

        }

        // Disable editor.
        $settings->radio_field(
            'kit_disable_editor', // Field ID.
            'Theme/Plugin Editor', // Field label.
            'builtmighty_kit', // The section ID this field will be placed into.
            [ 'enable' => 'Block', 'disable' => 'Enable' ], // Options.
            'Disables the theme/plugin file editor.' // Description.
        );

        // Disable email.
        $settings->radio_field(
            'kit_block_email', // Field ID.
            'WP Mail', // Field label.
            'builtmighty_kit', // The section ID this field will be placed into.
            [ 'enable' => 'Block', 'disable' => 'Enable' ], // Options.
            'Blocks all emails sent via wp_mail from sending.' // Description.
        );

        // Disable access.
        $settings->radio_field(
            'kit_block_access', // Field ID.
            'Site Access', // Field label.
            'builtmighty_kit', // The section ID this field will be placed into.
            [ 'enable' => 'Block', 'disable' => 'Enable' ], // Options.
            'Redirects non-logged in users to https://builtmighty.com. Can be bypassed without logging in by appending <code style="border:1px solid;border-radius:6px;">?bypass=true</code> to a URL.' // Description.
        );

        // ============================================
        // SECURITY HEADERS
        // ============================================

        // Enable security headers.
        $settings->radio_field(
            'kit_security_headers',
            'Security Headers',
            'builtmighty_kit',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Enable HTTP security headers for enhanced protection.'
        );

        if ( get_option( 'kit_security_headers' ) === 'enable' ) {

            // X-Frame-Options.
            $settings->select_field(
                'kit_header_x_frame',
                'X-Frame-Options',
                'builtmighty_kit',
                [
                    'SAMEORIGIN' => 'SAMEORIGIN (Recommended)',
                    'DENY'       => 'DENY',
                    'disable'    => 'Disable',
                ],
                'Prevents clickjacking attacks by controlling if your site can be embedded in frames.'
            );

            // X-Content-Type-Options.
            $settings->radio_field(
                'kit_header_x_content_type',
                'X-Content-Type-Options',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Prevents MIME type sniffing. Recommended to enable.'
            );

            // Referrer-Policy.
            $settings->select_field(
                'kit_header_referrer_policy',
                'Referrer-Policy',
                'builtmighty_kit',
                [
                    'strict-origin-when-cross-origin' => 'strict-origin-when-cross-origin (Recommended)',
                    'no-referrer'                     => 'no-referrer',
                    'no-referrer-when-downgrade'      => 'no-referrer-when-downgrade',
                    'same-origin'                     => 'same-origin',
                    'strict-origin'                   => 'strict-origin',
                    'disable'                         => 'Disable',
                ],
                'Controls how much referrer information is sent with requests.'
            );

            // Permissions-Policy.
            $settings->radio_field(
                'kit_header_permissions_policy',
                'Permissions-Policy',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Restricts browser features like camera, microphone, geolocation. Recommended to enable.'
            );

            // X-XSS-Protection (legacy).
            $settings->radio_field(
                'kit_header_x_xss',
                'X-XSS-Protection',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Legacy XSS protection for older browsers.'
            );

            // HSTS.
            $settings->radio_field(
                'kit_header_hsts',
                'Strict-Transport-Security (HSTS)',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Forces HTTPS connections. Only enable if your site fully supports HTTPS.'
            );

            // Content-Security-Policy.
            $settings->radio_field(
                'kit_header_csp',
                'Content-Security-Policy',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Advanced XSS protection. May require customization for your site. Start with Report-Only mode.'
            );

            if ( get_option( 'kit_header_csp' ) === 'enable' ) {
                $settings->radio_field(
                    'kit_header_csp_report_only',
                    'CSP Report-Only Mode',
                    'builtmighty_kit',
                    [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                    'Report violations without blocking. Recommended when first enabling CSP.'
                );
            }
        }

        // ============================================
        // LOGIN ACTIVITY
        // ============================================

        // Enable login logging.
        $settings->radio_field(
            'kit_login_logging',
            'Login Logging',
            'builtmighty_kit',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Log all login attempts (successful and failed) with IP addresses and timestamps.'
        );

        if ( get_option( 'kit_login_logging' ) === 'enable' ) {
            // Email notification for new IP.
            $settings->radio_field(
                'kit_login_notify_new_ip',
                'New IP Login Alerts',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Email administrators when they log in from a new IP address.'
            );
        }

        // ============================================
        // SESSION MANAGEMENT
        // ============================================

        // Enable session management.
        $settings->radio_field(
            'kit_session_management',
            'Session Management',
            'builtmighty_kit',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Enable advanced session management features.'
        );

        if ( get_option( 'kit_session_management' ) === 'enable' ) {

            // Logout on password change.
            $settings->radio_field(
                'kit_session_logout_on_password_change',
                'Logout on Password Change',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Force logout from all other devices when a user changes their password.'
            );

            // Concurrent session limit.
            $settings->select_field(
                'kit_session_limit',
                'Concurrent Session Limit',
                'builtmighty_kit',
                [
                    '0' => 'Unlimited',
                    '1' => '1 Session',
                    '2' => '2 Sessions',
                    '3' => '3 Sessions',
                    '5' => '5 Sessions',
                ],
                'Limit how many devices a user can be logged in on simultaneously.'
            );

            // Session timeout.
            $settings->select_field(
                'kit_session_timeout',
                'Idle Session Timeout',
                'builtmighty_kit',
                [
                    '0'    => 'Disabled',
                    '15'   => '15 minutes',
                    '30'   => '30 minutes',
                    '60'   => '1 hour',
                    '120'  => '2 hours',
                    '480'  => '8 hours',
                    '1440' => '24 hours',
                ],
                'Automatically log out users after a period of inactivity.'
            );
        }

        // ============================================
        // REST API SECURITY
        // ============================================

        // Enable REST API security.
        $settings->radio_field(
            'kit_rest_api_security',
            'REST API Security',
            'builtmighty_kit',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Enable REST API security features.'
        );

        if ( get_option( 'kit_rest_api_security' ) === 'enable' ) {

            // Require authentication.
            $settings->radio_field(
                'kit_rest_require_auth',
                'Require Authentication',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Require authentication for most REST API endpoints. Public content endpoints are whitelisted.'
            );

            // Remove REST API link.
            $settings->radio_field(
                'kit_rest_remove_link',
                'Remove REST API Link',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Remove the REST API discovery link from the HTML head.'
            );

            // API logging.
            $settings->radio_field(
                'kit_rest_logging',
                'API Request Logging',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Log REST API requests for security monitoring.'
            );

            // Rate limiting.
            $settings->radio_field(
                'kit_rest_rate_limit',
                'Rate Limiting',
                'builtmighty_kit',
                [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                'Limit REST API requests from unauthenticated users (60 requests per minute).'
            );
        }

        // ============================================
        // SPAM PROTECTION
        // ============================================

        // Enable spam protection.
        $settings->radio_field(
            'kit_spam_protection',
            'Spam Protection',
            'builtmighty_kit',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Enable comment spam protection features.'
        );

        if ( get_option( 'kit_spam_protection' ) === 'enable' ) {

            // Disable comments globally.
            $settings->radio_field(
                'kit_disable_comments',
                'Disable Comments',
                'builtmighty_kit',
                [ 'enable' => 'Disable Comments', 'disable' => 'Allow Comments' ],
                'Completely disable comments site-wide.'
            );

            if ( get_option( 'kit_disable_comments' ) !== 'enable' ) {

                // Honeypot.
                $settings->radio_field(
                    'kit_comment_honeypot',
                    'Honeypot Field',
                    'builtmighty_kit',
                    [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                    'Add a hidden honeypot field to catch bots.'
                );

                // Time-based check.
                $settings->radio_field(
                    'kit_comment_time_check',
                    'Time-Based Check',
                    'builtmighty_kit',
                    [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                    'Block comments submitted too quickly (indicates bot behavior).'
                );

                // Block spam IPs.
                $settings->radio_field(
                    'kit_block_spam_ips',
                    'Block Spam IPs',
                    'builtmighty_kit',
                    [ 'enable' => 'Enable', 'disable' => 'Disable' ],
                    'Automatically block IPs that submit multiple spam comments.'
                );

                // Max links.
                $settings->select_field(
                    'kit_comment_max_links',
                    'Maximum Links in Comments',
                    'builtmighty_kit',
                    [
                        '0' => 'Disabled',
                        '1' => '1 Link',
                        '2' => '2 Links',
                        '3' => '3 Links',
                        '5' => '5 Links',
                    ],
                    'Block comments with too many links.'
                );
            }

            // Disable pingbacks.
            $settings->radio_field(
                'kit_disable_pingbacks',
                'Disable Pingbacks',
                'builtmighty_kit',
                [ 'enable' => 'Disable Pingbacks', 'disable' => 'Allow Pingbacks' ],
                'Disable pingbacks and trackbacks (often used for spam).'
            );
        }

        // ============================================
        // SPEED/PERFORMANCE OPTIMIZATION
        // ============================================

        // Register Speed tab.
        $settings->add_settings_section(
            'builtmighty_speed',
            '⚡️ Speed',
            function() {
                echo '<p>Performance and speed optimization settings.</p>';
            }
        );

        // Disable Cart Fragments.
        $settings->radio_field(
            'kit_disable_cart_fragments',
            'Disable Cart Fragments AJAX',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Disables WooCommerce cart fragments AJAX which polls every 5 seconds. Can significantly improve performance.'
        );

        if ( get_option( 'kit_disable_cart_fragments' ) === 'enable' ) {
            $settings->text_field(
                'kit_cart_fragments_exclude',
                'Cart Fragments - Exclude Pages',
                'builtmighty_speed',
                'Enter page slugs to exclude (comma-separated). Example: cart,checkout,shop. Cart fragments will still work on these pages.'
            );
        }

        // Disable WC Scripts on Non-WC Pages.
        $settings->radio_field(
            'kit_disable_wc_scripts',
            'Disable WC Scripts on Non-WC Pages',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Dequeues WooCommerce scripts and styles on non-WooCommerce pages to reduce page weight.'
        );

        if ( get_option( 'kit_disable_wc_scripts' ) === 'enable' ) {
            $settings->text_field(
                'kit_wc_scripts_exclude',
                'WC Scripts - Exclude Pages',
                'builtmighty_speed',
                'Enter page slugs to exclude (comma-separated). WooCommerce scripts will still load on these pages.'
            );
        }

        // Disable jQuery Migrate.
        $settings->radio_field(
            'kit_disable_jquery_migrate',
            'Disable jQuery Migrate',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Removes the jQuery Migrate script. Only enable if you are sure your theme/plugins do not require it.'
        );

        // Remove Query Strings.
        $settings->radio_field(
            'kit_remove_query_strings',
            'Remove Query Strings',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Removes version query strings from CSS/JS files for better caching. Example: style.css?ver=1.0 becomes style.css'
        );

        // Disable WC Admin Features.
        $settings->radio_field(
            'kit_disable_wc_admin',
            'Disable WC Admin Features',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Disables WooCommerce Admin features (analytics, inbox, activity panels) to reduce admin overhead.'
        );

        // Disable Marketing Hub.
        $settings->radio_field(
            'kit_disable_marketing_hub',
            'Disable Marketing Hub',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Disables the WooCommerce Marketing Hub to reduce admin overhead.'
        );

        // Cleanup Head Tags.
        $settings->radio_field(
            'kit_cleanup_head',
            'Cleanup Head Tags',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Removes unnecessary meta tags from the head section (WP generator, RSD, wlwmanifest, shortlinks, etc.).'
        );

        // DNS Prefetch.
        $settings->radio_field(
            'kit_dns_prefetch',
            'DNS Prefetch',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Adds DNS prefetch hints for common third-party domains to speed up external resource loading.'
        );

        if ( get_option( 'kit_dns_prefetch' ) === 'enable' ) {
            $settings->text_field(
                'kit_dns_prefetch_domains',
                'Custom Prefetch Domains',
                'builtmighty_speed',
                'Enter additional domains to prefetch (comma-separated). Example: cdn.example.com,fonts.googleapis.com'
            );
        }

        // Disable Password Strength Meter.
        $settings->radio_field(
            'kit_disable_password_meter',
            'Disable Password Strength Meter',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Removes the password strength meter script on account pages. Reduces page weight by ~400KB.'
        );

        // ============================================
        // CSS/JS BUNDLER
        // ============================================

        // Enable bundler.
        $settings->radio_field(
            'kit_bundle_enabled',
            'CSS/JS Bundler',
            'builtmighty_speed',
            [ 'enable' => 'Enable', 'disable' => 'Disable' ],
            'Automatically detects enqueued CSS/JS, lets you select which to bundle, and serves minified combined files. Rebuilds every 24 hours.'
        );

        // Asset list and rebuild button (only when enabled).
        if ( get_option( 'kit_bundle_enabled' ) === 'enable' ) {
            $settings->add_settings_field( 'kit_bundled_assets', '', function() {
                \BuiltMightyKit\Private\performance::render_asset_settings();
            }, 'builtmighty_speed' );
        }

    }

    /**
     * Save.
     * 
     * @since   1.0.0
     */
    public function save( $option, $old, $new ) {

        // Check.
        if( ! in_array( $option, (array)$this->option_keys() ) ) return;

        // Check production URL encoding.
        if( $option === 'kit_production_url' && ! $this->is_base64( $new ) ) {

            // Encode and save.
            $new = base64_encode( trailingslashit( $new ) );
            update_option( $option, $new );

        }

        // Check if class Kinsta\Cache_Purge exists.
        if( ! class_exists( '\Kinsta\Cache_Purge' ) ) return;

        // Purge cache.
        $cache = new \Kinsta\Cache_Purge();
        $cache->purge_complete_caches();

    }

    /**
     * Is Base64?
     * 
     * @since   1.0.0
     */
    public function is_base64( string $string ){

        // Check if the string is a valid base64 string.
        if( ! preg_match( '/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string ) ) return false;

        // Decode the string in strict mode and check the results.
        $decoded = base64_decode( $string, true );
        if( false === $decoded ) return false;

        // Encode the string again and check if it's the same.
        if( base64_encode( $decoded ) != $string ) return false;

        // It's a valid base64 string.
        return true;
        
    }

    /**
     * Set option keys.
     * 
     * @since   1.0.0
     */
    public function option_keys() {

        // Return.
        return [
            'kit_production_url',
            'kit_enable_login',
            'kit_login_url',
            'kit_enable_2fa',
            'kit_2fa_users',
            'kit_block_email',
            'kit_block_access',
            'kit_block_external',
            'kit_allowed_external',
            'kit_disable_editor',
            'kit_actionscheduler',
            'slack-channel',
            'slack-notifications',
            // Security Headers.
            'kit_security_headers',
            'kit_header_x_frame',
            'kit_header_x_content_type',
            'kit_header_referrer_policy',
            'kit_header_permissions_policy',
            'kit_header_x_xss',
            'kit_header_hsts',
            'kit_header_csp',
            'kit_header_csp_report_only',
            // Login Activity.
            'kit_login_logging',
            'kit_login_notify_new_ip',
            // Session Management.
            'kit_session_management',
            'kit_session_logout_on_password_change',
            'kit_session_limit',
            'kit_session_timeout',
            // REST API Security.
            'kit_rest_api_security',
            'kit_rest_require_auth',
            'kit_rest_remove_link',
            'kit_rest_logging',
            'kit_rest_rate_limit',
            // Spam Protection.
            'kit_spam_protection',
            'kit_disable_comments',
            'kit_comment_honeypot',
            'kit_comment_time_check',
            'kit_block_spam_ips',
            'kit_comment_max_links',
            'kit_disable_pingbacks',
            // Speed Optimization.
            'kit_disable_cart_fragments',
            'kit_cart_fragments_exclude',
            'kit_disable_wc_scripts',
            'kit_wc_scripts_exclude',
            'kit_disable_jquery_migrate',
            'kit_remove_query_strings',
            'kit_disable_wc_admin',
            'kit_disable_marketing_hub',
            'kit_cleanup_head',
            'kit_dns_prefetch',
            'kit_dns_prefetch_domains',
            'kit_disable_password_meter',
            // CSS/JS Bundler.
            'kit_bundle_enabled',
            'kit_bundled_js_handles',
            'kit_bundled_css_handles',
            'kit_bundle_js_file',
            'kit_bundle_css_file',
            'kit_bundle_version',
        ];

    }

    /**
     * Add Built Mighty Admin Color Mode field to user profile.
     * 
     * @param   \WP_User $user - The user object.
     *
     * @return  void
     *
     * @hook    edit_user_profile
     * @hook    show_user_profile
     *
     * @since   4.2.0
     */
    public function builtmighty_admin_color_mode_field( $user ) {
        $value = get_user_meta( $user->ID, 'builtmighty_admin_color_mode', true ) ?: 'system';
        ?>
        <h3>Built Mighty Admin Color Mode</h3>
        <table class="form-table">
            <tr>
                <th><label for="builtmighty_admin_color_mode">Color Mode</label></th>
                <td>
                    <select name="builtmighty_admin_color_mode" id="builtmighty_admin_color_mode">
                        <option value="system" <?php selected( $value, 'system' ); ?>>System Default</option>
                        <option value="light" <?php selected( $value, 'light' ); ?>>Light</option>
                        <option value="dark" <?php selected( $value, 'dark' ); ?>>Dark</option>
                    </select>
                    <p class="description">Choose your preferred color mode for Built Mighty Kit admin screens.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Built Mighty Admin Color Mode.
     * 
     * @param   int $user_id - The user ID.
     *
     * @return  void
     *
     * @hook    personal_options_update
     * @hook    edit_user_profile_update
     *
     * @since   4.2.0
     */
    public function builtmighty_save_admin_color_mode( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        $mode = sanitize_text_field( $_POST['builtmighty_admin_color_mode'] ?? 'system' );
        if ( in_array( $mode, [ 'dark', 'light', 'system' ] ) ) {
            update_user_meta( $user_id, 'builtmighty_admin_color_mode', $mode );
        }
    }

}
