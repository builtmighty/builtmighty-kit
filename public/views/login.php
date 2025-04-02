<?php
/**
 * 2FA Login.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ 

// Set class.
$button_class = ( function_exists( 'is_account_page' ) && is_account_page() ) ? 'woocommerce-button button woocommerce-form-login__submit wp-element-button' : 'button button-primary button-large';
$input_class = ( function_exists( 'is_account_page' ) && is_account_page() ) ? 'woocommerce-Input woocommerce-Input--text input-text' : 'input'; ?>
<p>
    <span id="check-2fa" class="<?php echo $button_class; ?>">Login</span>
</p>
<p id="authentication-code" style="display:none;overflow:hidden;height:0">
    <label for="authentication_code"><span id="authentication_code_text"></span><br />
    <input type="text" name="authentication_code" id="authentication_code" class="<?php echo $input_class; ?>" value="" size="20" /></label>
</p>