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
        <li>PHP <code><?php echo $php; ?></code></li>
        <li>MySQL <code><?php echo $mysql; ?></code></li>
        <li>WordPress <code><?php echo $wp; ?></code></li>
    </ul>
</div>