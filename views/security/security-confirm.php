<?php
/**
 * Security Confirm.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ 
echo '<pre>';
print_r( $_POST );
echo '</pre>';
?>
<form method="post" action="<?php echo site_url( '/security?key=' . $_GET['key'] . '&confirm=true' ); ?>">
    <div class="built-panel-code">
        <input type="text" inputmode="numeric" maxlength="6" name="google_authenticator_code" id="google_authenticator_code" class="regular-text" placeholder="Enter your code" />
    </div>
    <div class="built-security-actions">
        <button type="submit" class="button button-primary">Submit</button>
    </div>
    <a href="<?php echo site_url( '/security?key=' . $_GET['key'] ); ?>" class="built-security-back">⟵ Back</a>
</form>