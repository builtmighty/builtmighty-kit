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
        <title>🔒 Locked &mdash; IP Not Approved</title><?php

        // Admin head.
        do_action( 'admin_head' );
        
        // CSS. 
        echo '<link rel="stylesheet" id="lockdown-css" href="' . BUILT_URI . 'assets/2fa/lockdown.css?ver=' . BUILT_VERSION . '"  media="all" />'; ?>

    </head>
    <body><?php

        echo '<pre>';
        print_r( $data );
        echo '</pre>'; ?>
        <div class="builtmighty-lockdown">
            <div class="builtmighty-lockdown-inner">
                <div class="builtmighty-lockdown-head">
                    <span>🔒</span>
                    <h2>Locked &mdash; IP Not Approved</h2>
                </div>
                <div class="builtmighty-lockdown-body"><?php

                    // Check if user has 2FA setup.
                    if( get_user_meta( get_current_user_id(), 'google_authenticator_confirmed', true ) ) {

                        // 2FA is required. ?>
                        <p>Your IP address is not approved to access this site.</p>
                        <p class="builtmighty-lockdown-ip"><?php echo $ip; ?></p>
                        <div class="builtmighty-lockdown-form">
                            <form method="post">
                                <div class="built-panel-code">
                                    <input type="text" name="google_authenticator_code" id="google_authenticator_code" class="regular-text" placeholder="Enter your code" />
                                </div>
                                <div class="built-panel-actions">
                                    <button type="submit" class="button button-primary">Approve IP</button>
                                </div>
                            </form>
                        </div><?php

                    } else {

                        // Request from another admin.
                        echo '<p>Your IP address is not approved to access this site. Request access.</p>';

                    }

                    // Admin footer.
                    do_action( 'admin_footer' ); ?>
                </div>
            </div>
        </div>
    </body>
</html>