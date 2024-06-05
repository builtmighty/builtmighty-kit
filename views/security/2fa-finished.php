<?php
/**
 * 2FA Finished.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div class="built-panel built-2fa">
    <?php echo $this->header(); ?>
    <div class="built-panel-inner">
        <p>Two Factor Authentication has been setup and confirmed.<br>
        You're good to go, unless you need to reset and restart the process.</p>
        <form method="post">
            <div class="built-panel-actions">
                <input type="hidden" name="google_authenticator_reset" value="true" />
                <button type="submit" class="button button-primary">Reset</button>
            </div>
        </form>
    </div>
</div>