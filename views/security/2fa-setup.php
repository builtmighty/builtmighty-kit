<?php
/**
 * 2FA Setup.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div class="built-panel built-2fa">
    <?php include BUILT_PATH . 'views/security/2fa-header.php'; ?>
    <div class="built-panel-inner">
        <div class="built-panel-qr">
            <img src="<?php echo $dataUri; ?>" alt="QR Code">
        </div>
        <div class="built-panel-code">
            <input type="text" name="google_authenticator_secret" id="google_authenticator_secret" value="<?php echo esc_attr( $secret ); ?>" class="regular-text" readonly />
        </div>
        <div class="built-panel-actions">
            <a href="<?php echo admin_url( '/admin.php?page=builtmighty-2fa&confirm=true' ); ?>" class="button button-primary">Confirm</a>
        </div>
    </div>
</div>