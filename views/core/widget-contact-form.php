<?php
/**
 * Contact Form.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
// Output. ?>
<div id="built-contact-form" class="built-form">
    <p>Contact your project manager.</p>
    <input type="hidden" name="built-project-project" value="<?php echo get_option( 'jira-project' ); ?>">
    <input type="hidden" name="built-project-pm" value="<?php echo get_option( 'jira-pm' ); ?>">
    <input type="hidden" name="built-project-user" value="<?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)">
    <div class="built-issue-field">
        <input type="text" name="built-project-subject" placeholder="Subject *">
    </div>
    <div class="built-issue-field">
        <textarea name="built-project-message" placeholder="Message *"></textarea>
    </div>
    <div class="built-issue-save">
        <input type="submit" class="button button-primary button-built" name="built-project-save" value="Send">
    </div>
</div>