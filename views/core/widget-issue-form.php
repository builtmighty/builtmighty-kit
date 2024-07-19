<?php
/** 
 * Message Form.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
// Output. ?>
<div id="built-issue-form" class="built-form active">
    <p>Have a feature request or an issue? Send us a quick message and we'll review it shortly.</p>
    <input type="hidden" name="built-slack-channel" value="<?php echo get_option( 'slack-channel' ); ?>">
    <div class="built-issue-field">
        <textarea name="built-issue-message" placeholder="Message *"></textarea>
    </div>
    <div class="built-issue-field built-issue-screenshot" contenteditable="true">
        Have a screenshot? Paste it here.
    </div>
    <input type="hidden" name="built-issue-screenshot" value="">
    <div class="built-issue-field">
        <label>From *</label>
        <input type="text" name="built-issue-user" value="<?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)">
    </div>
    <div class="built-issue-save">
        <input type="submit" class="button button-primary button-built" name="built-issue-save" value="Send">
    </div>
</div>