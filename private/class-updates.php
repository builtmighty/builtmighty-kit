<?php
/**
 * Action Scheduler.
 *
 * System notifications for admins.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Private;
use function BuiltMightyKit\is_kit_mode;
class updates {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check if site is in kit mode.
        if( is_kit_mode() ) return;

        // Actions.
        add_action( 'admin_footer', [ $this, 'modal' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

    }

    /**
     * Modal.
     * 
     * @since   1.0.0
     */
    public function modal() {

        // Get screen.
        $screen = get_current_screen();

        // Check if updates.
        if( in_array( $screen->id, $this->get_screens()['updates'] ) ) {

            // Update content.
            $title      = 'WARNING: Adding Plugins/Themes';
            $content    = 'Adding new plugins/themes on a production site involves risks that may cause your site to crash or become inaccessible. Please proceed with caution. This action will add new uncommitted code to the server and interrupt the automated deployment system. A manual re-sync will be necessary before the system can run smoothly. Please only take this action if absolutely necessary, otherwise let us know and we can take care of it for you. If you have any questions, please reach out to <a href="mailto:' . antispambot( 'developers@builtmighty.com', true ) . '">Built Mighty</a>.';

        } elseif( in_array( $screen->id, $this->get_screens()['install'] ) ) {

            // Install content.
            $title      = 'WARNING: Updating Plugins/Themes';
            $content    = 'Updating plugins/themes on a production site involves risks that may cause your site to crash or become inaccessible. Please proceed with caution. This action will add new uncommitted code to the server and interrupt the automated deployment system. A manual re-sync will be necessary before the system can run smoothly. Please only update if absolutely necessary, otherwise let us know and we can take care of it for you. If you have any questions, please reach out to <a href="mailto:' . antispambot( 'developers@builtmighty.com', true ) . '">Built Mighty</a>.';

        } else {

            // Return.
            return;

        }

        // Output. ?>
        <div id="builtmighty-kit-modal" style="display:none">
            <div class="builtmighty-kit-modal">
                <div class="builtmighty-kit-modal-header">
                    <span class="builtmighty-kit-modal-close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg></span>
                </div>
                <div class="builtmighty-kit-modal-content">
                    <h2><?php echo $title; ?></h2>
                    <p><?php echo $content; ?></p>
                </div>
                <div class="builtmighty-kit-modal-footer">
                    <button id="builtmighty-kit-modal-continue" class="button button-primary">Continue</button>
                    <button id="builtmighty-kit-modal-leave" class="button button-secondary">Leave</button>
                </div>
            </div>
        </div><?php

    }

    /**
     * Enqueue.
     * 
     * @since   1.0.0
     */
    public function enqueue() {

        // Get screen.
        $screen = get_current_screen();

        // Merge updates/installs.
        $screens = array_merge( $this->get_screens()['updates'], $this->get_screens()['install'] );

        // Skip all non-update/install screens.
        if( ! in_array( $screen->id, (array)$screens ) ) return;

        // CSS.
        wp_enqueue_style( 'kit-updates', KIT_URI . 'public/css/updates.css', [], KIT_VERSION );

        // JS.
        wp_enqueue_script( 'kit-updates', KIT_URI . 'public/js/updates.js', [ 'jquery' ], KIT_VERSION, true );

        // Localize.
        wp_localize_script( 'kit-updates', 'kit_updates', [
            'admin_url' => admin_url(),
        ]);

    }

    /**
     * Screens.
     * 
     * @since   1.0.0
     */
    public function get_screens() {

        // Return.
        return [
            'updates'   => [
                'plugins',
                'update-core',
                'themes',
            ],
            'install'   => [
                'plugin-install',
                'theme-install',
            ]
        ];

    }

}