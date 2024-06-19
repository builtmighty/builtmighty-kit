<?php
/**
 * Plugin Install.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>
<div id="builtmighty-kit-install">
    <div class="builtmighty-kit-modal">
        <div class="builtmighty-kit-modal-content">
            <span class="builtmighty-kit-modal-close">&times;</span>
            <h2>WARNING: Adding Plugins/Themes</h2>
            <p>Adding new plugins/themes on a production site involves risks that may cause your site to crash or become inaccessible. Please proceed with caution. This action will add new uncommitted code to the server and interrupt the automated deployment system. A manual re-sync will be necessary before the system can run smoothly. Please only take this action if absolutely necessary, otherwise let us know and we can take care of it for you. If you have any questions, please reach out to <a href="mailto:<?php echo antispambot( 'developers@builtmighty.com', true ); ?>">Built Mighty</a>.</p>
            <div class="builtmighty-kit-modal-buttons">
                <button class="button button-primary" id="builtmighty-kit-install">Continue</button>
                <button class="button button-secondary" id="builtmighty-kit-install-close">Leave</button>
            </div>
        </div>
    </div>
</div>