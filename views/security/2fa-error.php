<?php
/**
 * 2FA Error.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div class="notice notice-error is-dismissible built-2fa-error-message">
    <form method="post">
        <input type="hidden" name="google_authenticator_reset" value="true" />
        <p>Sorry, but the code entered was incorrect. Please try again. Still having issues? <button type="submit">Reset</button></p>
    </form>
</div>