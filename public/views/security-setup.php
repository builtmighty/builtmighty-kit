<?php
/**
 * Security Setup.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div class="built-security-inner built-security-setup">
    <p>Scan the QR code below with Google Authenticator or manually enter the code below.</p>
    <div class="built-security-code">
        <div class="built-security-qr">
            <img src="<?php echo $dataUri; ?>" alt="QR Code">
        </div>
        <div class="built-security-secret">
            <input type="text" name="authentication_secret" id="authentication_secret" value="<?php echo esc_attr( $secret ); ?>" class="regular-text" readonly />
        </div>
        <div class="built-security-actions">
            <a href="<?php echo site_url( '/security?key=' . $_GET['key'] . '&confirm=true' ); ?>" class="button button-primary">Confirm</a>
        </div>
    </div>
    <div class="built-security-download">
    <p>Don't have <span><img src="<?php echo KIT_URI . 'assets/images/authenticator_logo.png'; ?>" /></span>Google Authenticator? Download it now.</p>
        <div class="built-security-buttons">
            <div class="built-security-button built-security-button-google">
                <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" class="button button-primary">
                    <img src="<?php echo KIT_URI . 'assets/images/google_logo.png'; ?>" alt="Google Play">
                </a>
            </div>
            <div class="built-security-button built-security-button-apple">
                <a href="https://apps.apple.com/us/app/google-authenticator/id388497605" target="_blank" class="button button-primary">
                    <img src="<?php echo KIT_URI . 'assets/images/apple_logo.png'; ?>" alt="App Store">
                </a>
            </div>
        </div>
    </div>
</>