<?php
/**
 * Confirmed.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ 

// Set default button link.
$button = site_url( '/' );

// Auth.
$auth = new \BuiltMightyKit\Utility\authentication();

// Set button to WooCommerce my account page if not admin.
if( in_array( 'woocommerce/woocommerce.php', (array)apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && ! in_array( 'administrator', (array)$user->roles ) ) $button = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );

// Set button to custom endpoint if enabled, if WooCommerce is not active, and user is not admin.
if( defined( 'BUILT_ENDPOINT' ) && ! in_array( 'woocommerce/woocommerce.php', (array)apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) $button = site_url( '/' . BUILT_ENDPOINT );

// Set button to custom endpoint if enabled and user is admin.
if( defined( 'BUILT_ENDPOINT' ) && in_array( 'administrator', (array)$user->roles ) ) $button = site_url( '/' . BUILT_ENDPOINT );

// Set button to admin URL if custom endpoint is not enabled, WooCommerce is not active and user is not admin.
if( ! defined( 'BUILT_ENDPOINT' ) && ! in_array( 'woocommerce/woocommerce.php', (array)apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) $button = admin_url( '/' );

// Set button to admin URL if custom endpoint is not enabled and user is admin.
if( ! defined( 'BUILT_ENDPOINT' ) && in_array( 'administrator', (array)$user->roles ) ) $button = admin_url( '/' ); ?>

<p>Two Factor Authentication has been setup and confirmed.<br>You're good to go!</p>
<div class="built-panel-footer">
    <div class="built-security-actions">
        <a href="<?php echo $button; ?>" class="button button-secondary">Finish</a>
    </div>
</div>