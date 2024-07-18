<?php
/** 
 * Admin Settings.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
 <div class="built-panel built-admin-panel">
    <p>Welcome to the client configuration panel for this client. Here, you can connect the site to the client's Slack channel.</p>
    <form method="POST" class="built-fields"><?php

        // Include.
        include BUILT_PATH . 'views/core/admin-slack.php';

        // Add settings.
        do_action( '\BuiltMightyKit\Core\add_settings' ); ?>

        <div class="built-save">
            <input type="submit" class="button button-primary button-built" name="built-save" value="Save"><?php

            // Check for data.
            if( $projects && $users ) { ?>

                <a href="<?php echo admin_url( 'admin.php?page=builtmighty&refresh=true' ); ?>" class="button button-built" style="color:#fff;">Refresh</a><?php 

            } ?>

        </div>
    </form>
</div>
<div class="built-panel built-admin-panel">
    <p>Customer email implmentation tools. Run this tool to re-implement real user emails, instead of the replacements.</p>
    <div id="built-email-tool" class="built-tool">
        <div class="built-progress">
            <div class="built-bar-outer">
                <div class="built-bar-inner"></div>
            </div>
            <div class="built-bar-status"></div>
        </div>
        <div class="built-submit">
            <input type="submit" class="button built-action button-primary button-built" data-set='<?php echo json_encode( [ 'id' => 'built-email-tool', 'action' => 'built_email_replace', 'count' => 0, 'offset' => 0, 'total' => 0 ] ); ?>' name="built-tool" value="Run">
            <div class="built-loading"><?php include BUILT_PATH . 'assets/images/loading-icon.svg'; ?></div>
        </div>
    </div>
</div>