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
            <p>Adding plugins/themes on production can cause the site to crash and be inaccessible. Even if it does not crash the site, it brings uncommitted code onto the server and will cause the automated deployment system to fail, which will make deployments take much longer. Please only install if absolutely necessary. If you have any questions, please reach out to <a href="mailto:<?php echo antispambot( 'developers@builtmighty.com', true ); ?>">Built Mighty</a>.</p>
            <div class="builtmighty-kit-modal-buttons">
                <button class="button button-primary" id="builtmighty-kit-install">Continue</button>
                <button class="button button-secondary" id="builtmighty-kit-install-close">Leave</button>
            </div>
        </div>
    </div>
</div>