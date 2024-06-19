<?php
/**
 * 2FA Confirm.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div class="built-panel built-2fa">
    <?php include BUILT_PATH . 'views/security/2fa-header.php'; ?>
    <div class="built-panel-inner">
        <form method="post">
            <div class="built-panel-code">
                <input type="text" name="google_authenticator_code" id="google_authenticator_code" class="regular-text" placeholder="Enter your code" />
            </div>
            <div class="built-panel-actions">
                <button type="submit" class="button button-primary">Submit</button>
            </div>
        </form>
    </div>
</div>