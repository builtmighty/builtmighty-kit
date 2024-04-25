<?php
/**
 * Updates.
 * 
 * List for plugin updates.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtUpdates {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Confirmation modal.
        add_action( 'admin_footer', [ $this, 'modal' ] );

        // Enqueue.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

    }

    /**
     * Confirmation Modal.
     * 
     * @since   1.0.0
     */
    public function modal() {

        // Get current screen.
        $screen = get_current_screen();

        // Check if screen is plugins.
        if( in_array( $screen->id, [ 'plugins', 'update-core', 'themes' ] ) ) {

            // Modal. ?>
            <div id="builtmighty-kit-updates">
                <div class="builtmighty-kit-modal">
                    <div class="builtmighty-kit-modal-content">
                        <span class="builtmighty-kit-modal-close">&times;</span>
                        <h2>WARNING: Updating Plugins/Themes</h2>
                        <p>Updating plugins/themes on production can cause the site to crash and be inaccessible. Even if it does not crash the site, it brings uncommitted code onto the server and will cause the automated deployment system to fail, which will make deployments take much longer. Please only update if absolutely necessary. If you have any questions, please reach out to <a href="mailto:<?php echo antispambot( 'developers@builtmighty.com', true ); ?>">Built Mighty</a>.</p>
                        <div class="builtmighty-kit-modal-buttons">
                            <button class="button button-primary" id="builtmighty-kit-update">Update</button>
                            <button class="button button-secondary" id="builtmighty-kit-close">Close</button>
                        </div>
                    </div>
                </div>
            </div><?php

        }

    }

    /**
     * Enqueue.
     * 
     * @since   1.0.0
     */
    public function enqueue() {

        // Get current screen.
        $screen = get_current_screen();

        // Check if screen is plugins.
        if( in_array( $screen->id, [ 'plugins', 'update-core', 'themes' ] ) ) {

            // CSS.
            wp_enqueue_style( 'built-updates', BUILT_URI . 'assets/updates/updates.css', [], BUILT_VERSION );

            // JS.
            wp_enqueue_script( 'built-updates', BUILT_URI . 'assets/updates/updates.js', [ 'jquery' ], BUILT_VERSION, true );

        }

    }

}