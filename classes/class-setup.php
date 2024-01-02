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
     * Update wp-config.php.
     * 
     * Updates wp-config.php with custom values.
     * 
     * @since   1.0.0
     */
    public function update_config() {

        // Config.
        $config = $this->get_config();

        // Set updates.
        $updates = "\n// Built Mighty Kit - Disable external connections.\ndefine( 'WP_HTTP_BLOCK_EXTERNAL', true );\n\n// Built Mighty Kit - Whitelist external connections.\n// define( 'WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,*.github.com' );\n\n";

        // If the updates aren't in the config, add them after the opening PHP tag.
        if( strpos( $config, $updates ) === false ) {

            // Add updates.
            $config = str_replace( '<?php', '<?php' . $updates, $config );

            // Write the updates to the wp-config.php file.
            file_put_contents( ABSPATH . 'wp-config.php', $config );

        }

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
