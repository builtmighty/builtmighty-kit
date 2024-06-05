<?php
/**
 * Lockdown.
 * 
 * Lockdown the admin to allowed IPs.
 * 
 * @since   2.0.0
 */ 
?>
<!DOCTYPE html>
<html class="wp-toolbar" lang="en-US">
    <head>
        <title>🔒 Admin Lockdown</title><?php

        // Admin head.
        do_action( 'admin_head' );
        
        // CSS. 
        echo '<link rel="stylesheet" id="lockdown-css" href="' . BUILT_URI . 'assets/security/lockdown.css?ver=' . BUILT_VERSION . '"  media="all" />'; ?>

    </head>
    <body>
        <div class="builtmighty-lockdown">
            <div class="builtmighty-lockdown-inner"><?php 
            
                // Check if user has requested.
                if( ! empty( get_user_meta( get_current_user_id(), 'google_authenticator_request', true ) ) ) {

                    // Load waiting.
                    include BUILT_PATH . 'views/security/lockdown-waiting.php';

                } else {

                    // Load form.
                    include BUILT_PATH . 'views/security/lockdown-blocked.php';

                } ?>
            </div>
        </div><?php

        // Admin footer.
        do_action( 'admin_footer' ); ?>

    </body>
</html>