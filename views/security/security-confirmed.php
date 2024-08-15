<?php
/**
 * Confirmed.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<p>Two Factor Authentication has been setup and confirmed.<br>
You're good to go, unless you need to reset and restart the process.</p>
<div class="built-panel-footer">
    <div class="built-footer-action">
        <a href="<?php echo site_url( '/' ); ?>" class="button button-secondary">Confirm</a>
    </div>
    <form method="post">
        <div class="built-panel-actions">
            <input type="hidden" name="google_authenticator_reset" value="true" />
            <button type="submit" class="google-authenticator-reset">Reset</button>
        </div>
    </form>
</div>