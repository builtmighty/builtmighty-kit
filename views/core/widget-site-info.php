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