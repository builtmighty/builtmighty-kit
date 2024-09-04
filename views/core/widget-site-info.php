<?php
/**
 * Site Info.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
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
        <li>
            <label class="label-php">PHP</label>
            <code><?php echo $php; ?></code>
        </li>
        <li>
            <label class="label-mysql">MySQL</label>
            <code><?php echo $mysql; ?></code>
        </li>
        <li>
            <label class="label-wordpress">WordPress</label>
            <code><?php echo $wp; ?></code>
        </li>
    </ul>
</div>
<div class="built-panel">
    <p style="margin-top:0;"><strong>⚙ Services</strong></p>
    <p><span style="color:#00d26a;">Active</span> / <span style="color: #f8312f;">Inactive</span></p>
    <ul style="margin:0;" class="kit-services"><?php

        // Set services.
        $services = [];

        // Check external API connections.
        $services['External API Connections'] = ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) ? 'inactive' : 'active';

        // Check for allowed external connections.
        if( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && defined( 'WP_ACCESSIBLE_HOSTS' ) ) {

            // Add.
            $services['Allowed API Connections'] = 'active';

        }
        
        // Check Action Scheduler.
        $services['Action Scheduler'] = ( $mode ) ? 'inactive' : 'active';

        // Check Search Indexing.
        $services['Search Indexing'] = ( get_option( 'blog_public' ) ) ? 'active' : 'inactive';

        // Check theme/plugin editor.
        $services['Theme/Plugin Editor'] = ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) ? 'inactive' : 'active';

        // Check Email Delivery.
        $services['Email Delivery'] = ( $mode ) ? 'inactive' : 'active';

        // Check Custom Login.
        $services['Custom Login'] = ( defined( 'BUILT_ENDPOINT' ) && BUILT_ENDPOINT ) ? 'active' : 'inactive';

        // Check Access Control.
        $services['Access Control'] = ( defined( 'BUILT_ACCESS' ) && BUILT_ACCESS ) ? 'active' : 'inactive';

        // Check Admin Lockdown.
        $services['Admin IP Lockdown'] = ( defined( 'BUILT_LOCKDOWN' ) && BUILT_LOCKDOWN ) ? 'active' : 'inactive';

        // Check Two-Factor Authentication.
        $services['Two-Factor Authentication'] = ( defined( 'BUILT_2FA' ) && BUILT_2FA ) ? 'active' : 'inactive';

        // Check if Slack is enabled.
        $services['Slack Notifications'] = ( ! empty( get_option( 'slack-channel' ) ) ) ? 'active' : 'inactive';

        // Loop through services.
        foreach( $services as $service => $status ) {

            // Output. ?>
            <li>
                <span class="service-status service-<?php echo $status; ?>"><?php echo ( $status == 'active' ) ? '🟢' : '🔴'; ?></span>
                <span class="label-services label-service-<?php echo $status; ?>"><?php echo $service; ?></span><?php

                // Check Allowed API Connections.
                if( $service == 'Allowed API Connections' ) { ?>

                    <div class="allowed"><?php

                        // Explode.
                        $hosts = explode( ',', WP_ACCESSIBLE_HOSTS );

                        // Loop.
                        foreach( $hosts as $host ) {

                            // Output.
                            echo '<code class="services-code">' . $host . '</code>';

                        } ?>

                    </div><?php

                } elseif( $service == 'Two-Factor Authentication' ) {

                    // Set roles.
                    $roles = [
                        'administrator',
                    ];

                    // Check if 2FA roles is set.
                    if( ! empty( get_option( '2fa-roles' ) ) ) {

                        // Get roles and merge with existing.
                        $roles = array_merge( $roles, unserialize( get_option( '2fa-roles' ) ) );

                    }
                
                    // Output. ?>
                    <div class="allowed"><?php

                        // Loop.
                        foreach( $roles as $role ) {

                            // Output.
                            echo '<code class="services-code">' . ucwords( $role ) . '</code>';

                        } ?>

                    </div><?php

                } ?>

            </li><?php

        } ?>

    </ul>
</div>