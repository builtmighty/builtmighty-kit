<?php
/**
 * Authenticator setup.
 *
 * @var \WP_User    $user
 * @var string      $error
 * @var array|false $codes   Backup codes after confirming, or null/false.
 * @var string      $secret  Base32 secret being set up.
 * @var string      $qr      QR code data URI.
 * @var string      $finish  Where to go when done.
 * @var string      $via     'key' (emailed link) or 'session'.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
if( ! defined( 'ABSPATH' ) ) { exit; }

$action = add_query_arg( array_filter( [ 'action' => 'kit_2fa_setup', 'key' => isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : null, '_wpnonce' => isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null ] ), wp_login_url() );
?>
<div id="loginform" class="bmk-2fa bmk-setup">
<?php if ( is_array( $codes ) ) : ?>

	<ol class="bmk-steps" aria-label="Setup progress"><li class="bmk-done">Scan</li><li class="bmk-done">Confirm</li><li class="bmk-on" aria-current="step">Save codes</li></ol>
	<div class="bmk-2fa-head">
		<h1>Save your backup codes</h1>
		<p>Your authenticator app is set up. If you lose your phone, each of these codes signs you in once. Keep them in your password manager.</p>
	</div>
	<ol class="bmk-codes" id="kit-codes">
		<?php foreach ( $codes as $code ) : ?>
			<li><?php echo esc_html( $code ); ?></li>
		<?php endforeach; ?>
	</ol>
	<p class="bmk-2fa-row">
		<button type="button" class="button" data-bmk-copy="#kit-codes">Copy codes</button>
		<a class="button button-primary" href="<?php echo esc_url( $finish ); ?>"><?php echo esc_html( $via === 'key' ? 'Done, go to sign in' : ( ! empty( $forced ) ? 'Done, continue' : 'Done' ) ); ?></a>
	</p>
	<p class="bmk-2fa-note">These codes won't be shown again. You can make new ones from your profile.</p>

<?php else : ?>

	<ol class="bmk-steps" aria-label="Setup progress"><li class="bmk-on" aria-current="step">Scan</li><li>Confirm</li><li>Save codes</li></ol>
	<?php if ( ! empty( $forced ) ) : ?>
		<p class="bmk-2fa-note" role="status">Your account needs two-factor sign-in. Set it up now to keep going. It takes about a minute.</p>
	<?php endif; ?>
	<div class="bmk-2fa-head">
		<h1>Set up your authenticator app</h1>
		<p>Scan the code with 1Password, Google Authenticator, Microsoft Authenticator, Authy or any app that shows 6-digit codes.</p>
	</div>
	<div class="bmk-qr"><img src="<?php echo esc_attr( $qr ); ?>" alt="QR code for <?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="196" height="196"></div>
	<details class="bmk-manual">
		<summary>Can't scan? Enter this key</summary>
		<div class="bmk-secret">
			<code id="kit-secret"><?php echo esc_html( trim( chunk_split( $secret, 4, ' ' ) ) ); ?></code>
			<button type="button" class="bmk-copybtn" data-bmk-copy="#kit-secret" aria-label="Copy setup key">Copy</button>
		</div>
		<p class="bmk-2fa-note">Type it into your app and choose "time-based" if it asks.</p>
	</details>

	<form method="post" action="<?php echo esc_url( $action ); ?>" class="bmk-confirm">
		<?php wp_nonce_field( 'kit_2fa_setup_confirm_' . $user->ID, '_kit_setup' ); ?>
		<label for="kit-setup-code" class="bmk-label">Enter the 6-digit code your app shows</label>
		<div class="bmk-otp" data-len="6"><input type="text" id="kit-setup-code" name="kit_setup_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" required<?php echo $error ? ' aria-invalid="true" aria-describedby="kit-setup-err"' : ''; ?>></div>
		<?php if ( $error ) : ?><p class="bmk-2fa-err" id="kit-setup-err" role="alert"><?php echo esc_html( $error ); ?></p><?php endif; ?>
		<p class="submit"><button type="submit" class="button button-primary button-large bmk-2fa-submit">Confirm code</button></p>
	</form>

<?php endif; ?>
	<?php if ( ! empty( $forced ) && ! is_array( $codes ) ) : ?>
		<p class="bmk-2fa-secured"><a href="<?php echo esc_url( $logout ); ?>">Sign out</a></p>
	<?php endif; ?>
	<p class="bmk-2fa-secured">Protected by Built Mighty Kit</p>
</div>
