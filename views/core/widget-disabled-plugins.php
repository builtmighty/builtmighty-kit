<?php
/**
 * Disabled plugins.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
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
</div>