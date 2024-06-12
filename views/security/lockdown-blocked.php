<?php
/**
 * Blocked.
 * 
 * Template for blocked IP approval requests.
 * 
 * @since   2.0.0
 */ 
?>
<div class="builtmighty-lockdown-head">
    <span>🔒</span>
    <h2>Locked &mdash; IP Not Approved</h2>
</div>
<div class="builtmighty-lockdown-body"><?php

    // Check if user has 2FA setup.
    if( defined( 'BUILT_2FA' ) && get_user_meta( get_current_user_id(), 'google_authenticator_confirmed', true ) ) {

        // 2FA is required. ?>
        <p>Your IP address is not approved to access this site.</p>
        <p class="builtmighty-lockdown-ip"><?php echo $ip; ?></p>
        <div class="builtmighty-lockdown-form">
            <form method="post">
                <div class="built-panel-code">
                    <input type="hidden" name="user_ip" id="user_ip" value="<?php echo $ip; ?>" />
                    <input type="hidden" name="user_id" id="user_id" value="<?php echo get_current_user_id(); ?>" />
                    <input type="text" name="google_authenticator_code" id="google_authenticator_code" class="regular-text" placeholder="Enter your code" />
                </div>
                <div class="built-panel-actions">
                    <button type="submit" class="button button-primary">Approve IP</button>
                </div>
            </form>
        </div><?php

    } else {

        // Request from another admin. ?>
        <p>Your IP address is not approved to access this site. Request approval from another admin.</p>
        <p class="builtmighty-lockdown-ip"><?php echo $ip; ?></p>
        <div class="builtmighty-lockdown-form">
            <form method="post">
                <input type="hidden" name="google_authenticator_request" id="google_authenticator_request" value="true" />
                <input type="hidden" name="user_ip" id="user_ip" value="<?php echo $ip; ?>" />
                <input type="hidden" name="user_id" id="user_id" value="<?php echo get_current_user_id(); ?>" />
                <div class="built-panel-actions">
                    <button type="submit" class="button button-primary">Request Approval</button>
                </div>
            </form>
        </div><?php

    } ?>
</div>