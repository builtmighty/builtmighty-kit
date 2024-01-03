<?php
/**
 * Setup.
 * 
 * Sets up necessary items for dev environments.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtSetup {

    /**
     * Variables.
     * 
     * @since   1.0.0
     */
    private $updates;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Set updates.
        $this->updates = [];

    }

    /**
     * Update wp-config.php.
     * 
     * Updates wp-config.php with custom values.
     * 
     * @since   1.0.0
     */
    public function update_config() {

        // Config.
        $config = $this->get_config();

        // Disable external connections.
        $this->disable_external();

        // Disable indexing.
        $this->disable_indexing();

        // Disable plugins.
        $this->disable_plugins();

        // If the updates aren't in the config, add them after the opening PHP tag.
        if( strpos( $config, $updates ) === false ) {

            // Add updates.
            $config = str_replace( '<?php', '<?php' . $updates, $config );

            // Write the updates to the wp-config.php file.
            file_put_contents( ABSPATH . 'wp-config.php', $config );

        }

    }

    /**
     * Disable external connections.
     * 
     * @since   1.0.0
     */
    public function disable_external() {

        // Check if this is a dev site.
        if( ! is_built_mighty() ) return;

        // Add to updates.
        $this->updates['external'] = "\n// Built Mighty Kit - Disable external connections.\ndefine( 'WP_HTTP_BLOCK_EXTERNAL', true );\n\n// Built Mighty Kit - Whitelist external connections.\n// define( 'WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,*.github.com' );\n\n";

    }

    /**
     * Disable robots/indexing.
     * 
     * @since   1.0.0
     */
    public function disable_indexing() {

        // Check if this is a dev site.
        if( ! is_built_mighty() ) return;

        // Add to updates.
        $this->updates['indexing'] = "\n// Built Mighty Kit - Disable indexing.\nif( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) define( 'WP_ENVIRONMENT_TYPE', 'local' );\n\n";

        // Set site to noindex.
        update_option( 'blog_public', '0' );

    }

    /**
     * Disable plugins.
     * 
     * @since   1.0.0
     */
    public function disable_plugins() {

        // Check if this is a dev site.
        if( ! is_built_mighty() ) return;

        // Get plugins.
        $plugins = $this->get_plugins();

        // Set array of disabled.
        $disabled = [];

        // Loop through plugins.
        foreach( $plugins as $plugin => $file ) {

            // If the plugin is active, deactivate it.
            if( is_plugin_active( $file ) ) {

                // Add to disabled.
                $disabled[] = $plugin;

                // Deactivate plugin.
                deactivate_plugins( $file );

            }

        }

        // Save disabled plugins.
        update_option( 'built_disabled_plugins', $disabled );

    }

    /**
     * Get plugins.
     * 
     * @since   1.0.0
     */
    public function get_plugins() {

        // Set array of plugins to disable. 
        $plugins = [
            'Akismet'           => 'akismet/akismet.php',
            'Hello Dolly'       => 'hello.php',
            'WP Mail SMTP'      => 'wp-mail-smtp/wp_mail_smtp.php',
            'Easy WP SMTP'      => 'easy-wp-smtp/easy-wp-smtp.php',
            'WP Super Cache'    => 'wp-super-cache/wp-cache.php',
            'W3 Total Cache'    => 'w3-total-cache/w3-total-cache.php',
            'WP Fastest Cache'  => 'wp-fastest-cache/wpFastestCache.php',
            'WP Rocket'         => 'wp-rocket/wp-rocket.php',
            'WP-Optimize'       => 'wp-optimize/wp-optimize.php',
            'Contact Form 7'    => 'contact-form-7/wp-contact-form-7.php',
            'Gravity Forms'     => 'gravityforms/gravityforms.php',
            'Ninja Forms'       => 'ninja-forms/ninja-forms.php',
            'Mailchimp for WP'  => 'mailchimp-for-wp/mailchimp-for-wp.php',
            'MailPoet'          => 'wysija-newsletters/index.php',
            'Yoast SEO'         => 'wordpress-seo/wp-seo.php',
            'All in One SEO'    => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'SEO by Rank Math'  => 'seo-by-rank-math/rank-math.php',
            'Wordfence'         => 'wordfence/wordfence.php',
            'iThemes Security'  => 'better-wp-security/better-wp-security.php',
            'UpdraftPlus'       => 'updraftplus/updraftplus.php',
            'BackWPup'          => 'backwpup/backwpup.php',
            'Duplicator'        => 'duplicator/duplicator.php',
            'WP Migrate DB'     => 'wp-migrate-db/wp-migrate-db.php',
            'WP Migrate DB Pro' => 'wp-migrate-db-pro/wp-migrate-db-pro.php',
            'Social Networks Auto Poster' => 'social-networks-auto-poster-facebook-twitter-g/NextScripts_SNAP.php',
            'Google Analytics for WordPress' => 'google-analytics-for-wordpress/googleanalytics.php',
            'PixelYourSite'     => 'pixelyoursite/pixelyoursite.php',
            'OneSignal'         => 'onesignal-free-web-push-notifications/onesignal.php',
            'Push Engage'       => 'pushengage-web-push-notifications/pushengage.php',
            'WP Pusher'         => 'wppusher/wppusher.php',
            'Webpushr'          => 'webpushr-web-push-notifications/webpushr.php',
            'Twilio SMS Notifications' => 'twilio-sms-notifications/twilio-sms-notifications.php',
            'WP SMS'            => 'wp-sms/wp-sms.php',
            'YITH WooCommerce SMS Notifications' => 'yith-woocommerce-sms-notifications/init.php',
            'Zendesk Chat'      => 'zopim-live-chat/zopim.php',
            'Tawk.to Live Chat' => 'tawkto-live-chat/tawkto.php',
            'WP Live Chat Support' => 'wp-live-chat-support/wp-live-chat-support.php',
        ];

        // Return plugins.
        return $plugins;

    }

    /**
     * Get config.
     * 
     * Gets the wp-config.php file.
     * 
     * @since   1.0.0
     */
    public function get_config() {

        // Get the wp-config.php file.
        return file_get_contents( ABSPATH . 'wp-config.php' );

    }

}
