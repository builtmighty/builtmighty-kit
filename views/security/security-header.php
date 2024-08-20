<?php
/**
 * 2FA Header.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
// Set icon.
if( ! empty( get_user_meta( $user->ID, 'google_authenticator_confirmed', true ) ) ) {
    $icon = '🔒';
    $color = '#266d29';
} else {
    $icon = '🔓';
    $color = '#d63638';
}

// Display header. ?>
<div class="built-panel-header">
    <div class="built-panel-icon">
        <span style="background:<?php echo $color;?>"><?php echo $icon; ?></span>
    </div>
    <div class="built-panel-title">
        <h2>Two Factor Authentication</h2>
    </div>
</div>