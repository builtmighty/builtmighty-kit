<?php
/**
 * Security Confirm.
 *
 * @package Built Mighty Kit
 * @since   2.0.0
 * @since   5.0.0  Added CSRF nonce, updated to 8-digit codes
 */
// Check for error.
if( isset( $_GET['status'] ) && sanitize_text_field( wp_unslash( $_GET['status'] ) ) === 'error' ) {

    // Output error. ?>
    <p class="built-security-error">The code you entered is incorrect. Please try again.</p><?php

} ?>
<form method="post" action="<?php echo esc_url( site_url( '/security?key=' . rawurlencode( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) . '&confirm=true' ) ); ?>">
    <?php wp_nonce_field( 'kit_2fa_confirm', '_kit_2fa_nonce' ); ?>
    <div class="built-panel-code">
        <input type="hidden" name="key" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ); ?>" />
        <input type="text" inputmode="numeric" maxlength="8" name="authentication_code" id="authentication_code" class="regular-text" placeholder="Enter your code or backup code" />
    </div>
    <div class="built-security-actions">
        <button type="submit" class="button button-primary">Submit</button>
    </div>
    <a href="<?php echo esc_url( site_url( '/security?key=' . rawurlencode( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) ) ); ?>" class="built-security-back">⟵ Back</a>
</form>
