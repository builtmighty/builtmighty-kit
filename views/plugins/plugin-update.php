<?php
/**
 * Plugin Update.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div id="builtmighty-kit-updates">
    <div class="builtmighty-kit-modal">
        <div class="builtmighty-kit-modal-content">
            <span class="builtmighty-kit-modal-close">&times;</span>
            <h2>WARNING: Updating Plugins/Themes</h2>
            <p>Updating plugins/themes on a production site involves risks that may cause your site to crash or become inaccessible. Please proceed with caution. This action will add new uncommitted code to the server and interrupt the automated deployment system. A manual re-sync will be necessary before the system can run smoothly. Please only update if absolutely necessary, otherwise let us know and we can take care of it for you. If you have any questions, please reach out to <a href="mailto:<?php echo antispambot( 'developers@builtmighty.com', true ); ?>">Built Mighty</a>.</p>
            <div class="builtmighty-kit-modal-buttons">
                <button class="button button-primary" id="builtmighty-kit-update">Update</button>
                <button class="button button-secondary" id="builtmighty-kit-close">Close</button>
            </div>
        </div>
    </div>
</div>