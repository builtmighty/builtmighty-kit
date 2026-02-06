<?php
/**
 * Confirmed.
 *
 * @package Built Mighty Kit
 * @since   2.0.0
 * @since   5.0.0  Added backup codes display
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
if( ! defined( 'BUILT_ENDPOINT' ) && in_array( 'administrator', (array)$user->roles ) ) $button = admin_url( '/' );

// Get backup codes (only available for 5 minutes after confirmation).
$backup_codes = get_transient( 'kit_backup_codes_' . $user->ID );

// Clear transient after retrieval so codes are only shown once.
if ( $backup_codes ) {
    delete_transient( 'kit_backup_codes_' . $user->ID );
} ?>

<p>Two Factor Authentication has been setup and confirmed.<br>You're good to go!</p>

<?php if ( $backup_codes && is_array( $backup_codes ) ) : ?>
<div class="built-security-backup-codes" style="margin:20px 0;padding:20px;background:#f8f8f8;border:1px solid #ddd;border-radius:8px;">
    <h3 style="margin-top:0;color:#d42027;">Save Your Backup Codes</h3>
    <p style="color:#666;">If you lose access to your authenticator app, you can use these one-time codes to log in. Each code can only be used once. <strong>Save these now — they won't be shown again.</strong></p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;max-width:300px;margin:15px 0;">
        <?php foreach ( $backup_codes as $code ) : ?>
            <code style="font-size:14px;padding:6px 10px;background:#fff;border:1px solid #ccc;border-radius:4px;font-family:monospace;text-align:center;"><?php echo esc_html( $code ); ?></code>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="built-panel-footer">
    <div class="built-security-actions">
        <a href="<?php echo esc_url( $button ); ?>" class="button button-secondary">Finish</a>
    </div>
</div>
