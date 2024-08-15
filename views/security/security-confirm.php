<?php
/**
 * Security Confirm.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ 
// Check for error.
if( isset( $_GET['status'] ) && $_GET['status'] == 'error' ) {

    // Output error. ?>
    <p class="built-security-error">The code you entered is incorrect. Please try again.</p><?php
    
} ?>
<form method="post" action="<?php echo site_url( '/security?key=' . $_GET['key'] . '&confirm=true' ); ?>">
    <div class="built-panel-code">
        <input type="hidden" name="key" value="<?php echo $_GET['key']; ?>" />
        <input type="text" inputmode="numeric" maxlength="6" name="google_authenticator_code" id="google_authenticator_code" class="regular-text" placeholder="Enter your code" />
    </div>
    <div class="built-security-actions">
        <button type="submit" class="button button-primary">Submit</button>
    </div>
    <a href="<?php echo site_url( '/security?key=' . $_GET['key'] ); ?>" class="built-security-back">⟵ Back</a>
</form>