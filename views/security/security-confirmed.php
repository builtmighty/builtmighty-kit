<?php
/**
 * Confirmed.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ 

// Set default button link.
$button = site_url( '/' );

// Set button to WooCommerce my account page if not admin.
if( is_plugin_active( 'woocommerce/woocommerce.php' ) && ! in_array( 'administrator', (array)$user->roles ) ) $button = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );

// Set button to custom endpoint if enabled and user is admin.
if( defined( 'BUILT_ENDPOINT' ) && in_array( 'administrator', (array)$user->roles ) ) $button = site_url( '/' . BUILT_ENDPOINT );

// Set button to admin URL if custom endpoint is not enabled and user is admin.
if( ! defined( 'BUILT_ENDPOINT' ) && in_array( 'administrator', (array)$user->roles ) ) $button = admin_url( '/' ); ?>

<p>Two Factor Authentication has been setup and confirmed.<br>You're good to go!</p>
<div class="built-panel-footer">
    <div class="built-security-actions">
        <a href="<?php echo $button; ?>" class="button button-secondary">Finish</a>
    </div>
</div>