<?php
/**
 * Admin Email Protection.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div class="built-panel built-admin-panel">
    <h3 style="color:#fff;">Customer Email Protection</h3>
    <p>Run the following tool to replace email addresses in the database with custom generated, protected email addresses. Original addresses are stored and a tool is available to re-implement, if needed.</p>
    <div id="built-email-protect" class="built-tool">
        <div class="built-progress">
            <div class="built-bar-outer">
                <div class="built-bar-inner"></div>
            </div>
            <div class="built-bar-status"></div>
        </div>
        <div class="built-submit">
            <input type="submit" class="button built-action button-primary button-built" data-set='<?php echo json_encode( [ 'id' => 'built-email-protect', 'action' => 'built_email_protect', 'count' => 0, 'offset' => 0, 'total' => 0 ] ); ?>' name="built-tool" value="Run">
            <div class="built-loading"><?php include BUILT_PATH . 'assets/images/loading-icon.svg'; ?></div>
        </div>
    </div>
</div>