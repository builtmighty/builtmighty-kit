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

// Check for Jira project or project manager.
if( ! empty( get_option( 'jira-project' ) ) && ! empty( get_option( 'jira-pm' ) ) ) {

    // Get project and project manager.
    $project = get_option( 'jira-project' );
    $pm = explode( '|', base64_decode( get_option( 'jira-pm' ) ) );

    // Set.
    $pm_name = $pm[1];
    $pm_id   = $pm[0];

    // Create menu. ?>
    <div class="built-dash-body built-panel">
        <div class="built-dash-nav">
            <span class="built-nav-button active" id="built-issue" data-id="built-issue-form">Create Task</span>
            <span class="built-nav-button" id="built-pm" data-id="built-contact-form">Contact Us</span>
        </div>
        <div class="built-dash-forms">
            <div class="built-form-status" style="display:none"><p></p></div><?php

            // Issue form.
            echo $this->issue_form();
            
            // Contact form.
            echo $this->contact_form(); ?>

        </div>
    </div><?php

}