<?php
/**
 * Setup.
 * 
 * Sets up necessary items for dev environments.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Core;
use function BuiltMightyKit\is_kit_mode;
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
        $this->updates = '';

    }

    /**
     * Run setup..
     * 
     * Runs setup process for plugin.
     * 
     * @since   1.0.0
     */
    public function run() {

        // Disable external connections.
        $this->disable_external();

        // Disable indexing.
        $this->disable_indexing();

        // Disable plugins.
        $this->disable_plugins();

    }

    /**
     * Disable external connections.
     * 
     * @since   1.0.0
     */
    public function disable_external() {

        // Check if this is a dev site.
        if( ! is_kit_mode() ) return;

        // Add to updates.
        $this->updates .= "\n// Built Mighty Kit - Disable external connections.\ndefine( 'WP_HTTP_BLOCK_EXTERNAL', true );\n\n// Built Mighty Kit - Whitelist external connections.\ndefine( 'WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,*.github.com' );\n\n";

    }

    /**
     * Disable robots/indexing.
     * 
     * @since   1.0.0
     */
    public function disable_indexing() {

        // Check if this is a dev site.
        if( ! is_kit_mode() ) return;

        // Add to updates.
        $this->updates .= "\n// Built Mighty Kit - Disable indexing.\nif( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) define( 'WP_ENVIRONMENT_TYPE', 'development' );\n\n";

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
        if( ! is_kit_mode() ) return;

        // Check if is_plugin_active function exists.
        if( ! function_exists( 'is_plugin_active' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';

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
     * Update wp-config.php.
     * 
     * @since   1.0.0
     */
    public function update_config() {

        // Config.
        $config = $this->get_config();

        // If the updates aren't in the config, add them after the opening PHP tag.
        if( strpos( $config, $this->updates['config'] ) === false ) {

            // Add updates.
            $config = str_replace( '<?php', '<?php' . $this->updates['config'], $config );

            // Write the updates to the wp-config.php file.
            file_put_contents( ABSPATH . 'wp-config.php', $config );

        }

    }

    /**
     * Update email addresses.
     * 
     * @since   1.0.0
     */
    public function update_emails( $data ) {

        // Get WPDB.
        global $wpdb;

        // Set limit.
        $limit = 100;

        // Get users.
        $users = $wpdb->get_results( "SELECT ID, user_email FROM {$wpdb->users} LIMIT {$limit} OFFSET " . $data['offset'] );

        // Get total users, if not set.
        if( $data['total'] == NULL || $data['total'] == 0 ) {

            // Get total users.
            $data['total'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
            
        }

        // Loop through users.
        foreach( $users as $user ) {

            // Check if user email is already a builtmighty.com email.
            if( strpos( $user->user_email, '@builtmighty.com' ) !== false ) continue;

            // Set original.
            $original = $user->user_email;

            // Generate a random string.
            $string = $this->get_string();

            // Create new email.
            $new_email = explode( '@', $user->user_email )[0] . '.' . $string . '@builtmighty.com';

            // Search for post meta with user email.
            $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = '{$new_email}' WHERE meta_value = '{$user->user_email}'" );

            // Search for and update user meta with user email.
            $wpdb->query( "UPDATE {$wpdb->usermeta} SET meta_value = '{$new_email}' WHERE meta_value = '{$user->user_email}'" );

            // Save original email.
            update_user_meta( $user->ID, 'built_original_email', $original );

            // Update user email.
            $wpdb->update(
                $wpdb->users,
                [ 'user_email' => $new_email ],
                [ 'ID' => $user->ID ]
            );

        }

        // Update data.
        $data['count']++;
        $data['offset'] = ( $data['count'] == 0 ) ? 0 : $data['offset'] + $limit;

        // Return.
        return $data;

    }

    /**
     * Reset email addresses.
     * 
     * @since   1.0.0
     */
    public function reset_emails( $data ) {

        // Set limit.
        $limit = 100;

        // Get WPDB.
        global $wpdb;

        // Set SQL. 
        $SQL = "SELECT u.ID, u.user_email, m.meta_value 
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->usermeta} m ON (u.ID = m.user_id AND m.meta_key = 'built_original_email')
        WHERE m.meta_key = 'built_original_email'
        LIMIT {$limit} OFFSET " . $data['offset'];

        // Get users.
        $users = $wpdb->get_results( $SQL );

        // Get total users, if not set.
        if( $data['total'] == NULL || $data['total'] == 0 ) {

            // Get total users.
            $data['total'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} INNER JOIN {$wpdb->usermeta} ON {$wpdb->users}.ID = {$wpdb->usermeta}.user_id WHERE {$wpdb->usermeta}.meta_key = 'built_original_email'" );

        }

        // Loop through users.
        foreach( $users as $user ) {

            // Set original email.
            $original_email = $user->meta_value;

            // Search for post meta with user email.
            $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = '{$original_email}' WHERE meta_value = '{$user->user_email}'" );

            // Search for and update user meta with user email.
            $wpdb->query( "UPDATE {$wpdb->usermeta} SET meta_value = '{$original_email}' WHERE meta_value = '{$user->user_email}'" );

            // Update user email.
            $wpdb->update(
                $wpdb->users,
                [ 'user_email' => $original_email ],
                [ 'ID' => $user->ID ]
            );

            // Delete original email meta.
            delete_user_meta( $user->ID, 'built_original_email' );

        }

        // Update data.
        $data['count']++;
        $data['offset'] = ( $data['count'] == 0 ) ? 0 : $data['offset'] + $limit;

        // Return.
        return $data;

    }

    /**
     * Get random string.
     * 
     * @since   1.0.0
     */
    public function get_string( $length = 10 ) {

        // Set characters.
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Set length.
        $c_len = strlen( $characters );

        // Set string.
        $string = '';

        // Loop through length.
        for( $i = 0; $i < $length; $i++ ) {

            // Add to string.
            $string .= $characters[ rand( 0, $c_len - 1 ) ];

        }

        // Return string.
        return $string;

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
