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

    }

    /**
     * Enqueue.
     * 
     * @since   1.0.0
     */
    public function enqueue() {

        // CSS.
        wp_enqueue_style( 'builtmighty-kit-admin', KIT_URI . 'public/css/admin.css', [], KIT_VERSION );

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

            // Run setup.

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
            'Built Mighty Kit',  // Title.
            function() {
                echo '<p>Settings for the Built Mighty Kit.</p>'; // Description.
            }
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
                'Enabled by default on non-production environments. Blocks the Action Scheduler from running.' // Description.
            );

        }

        // Block external.
        $settings->radio_field(
            'kit_block_external', // Field ID.
            'External Requests', // Field label.
            'builtmighty_kit', // The section ID this field will be placed into.
            [ 'enable' => 'Block', 'disable' => 'Enable' ], // Options.
            'Enabled by default on non-production environments. Blocks external API requests. Allowed by default: ' . $_SERVER['SERVER_NAME'] . ', api.wordpress.org, downloads.wordpress.org, github.com, github.dev, github.io, githubusercontent.com, slack.com, builtmighty.com.' // Description.
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
            'Blocks access to the site for non-logged in users. Can be bypassed without logging in by appending ?bypass=true to a URL.' // Description.
        );

    }

}
