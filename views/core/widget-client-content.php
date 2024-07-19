<?php
/**
 * Client Content.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div class="built-dash-head">
    <div class="built-dash-logo">
        <a href="https://builtmighty.com" target="_blank">
            <img src="<?php echo BUILT_URI; ?>assets/images/block-builtmighty.png" alt="Built Mighty">
        </a>
    </div>
    <div class="built-dash-message">
        <p>Welcome! Thanks for being a Built Mighty client. We're here to help with any of your WordPress or WooCommerce needs.</p>
    </div>
</div><?php

// Check for Slack channel.
if( ! empty( get_option( 'slack-channel' ) ) ) {

    // Create menu. ?>
    <div class="built-dash-body built-panel">
        <div class="built-dash-forms">
            <div class="built-form-status" style="display:none"><p></p></div><?php

            // Issue form.
            echo $this->issue_form(); ?>

        </div>
    </div><?php

}