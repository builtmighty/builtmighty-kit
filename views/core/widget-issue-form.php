<?php
/** 
 * Jira Issue Form.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
// Output. ?>
<div id="built-issue-form" class="built-form active">
    <p>Have a feature request or an issue? Create a new task here and your project manager will review it shortly.</p>
    <input type="hidden" name="built-issue-project" value="<?php echo get_option( 'jira-project' ); ?>">
    <input type="hidden" name="built-issue-pm" value="<?php echo get_option( 'jira-pm' ); ?>">
    <div class="built-issue-field">
        <input type="text" name="built-issue-subject" placeholder="Subject *">
    </div>
    <div class="built-issue-field">
        <textarea name="built-issue-description" placeholder="Description *"></textarea>
    </div>
    <div class="built-issue-field">
        <label>Reported by</label>
        <input type="text" name="built-issue-user" value="<?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)">
    </div>
    <div class="built-issue-field">
        <input type="url" name="built-issue-url" placeholder="Relevant Link">
    </div>
    <div class="built-issue-field built-issue-screenshot" contenteditable="true">
        Paste Screenshot Here
    </div>
    <input type="hidden" name="built-issue-screenshot" value="">
    <div class="built-issue-save">
        <input type="submit" class="button button-primary button-built" name="built-issue-save" value="Send">
    </div>
</div>