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
            $title      = 'You\'re about to update plugins on the live store';
            $content    = 'An update can take checkout offline. It also puts code on the server that isn\'t in the site\'s repository, so the next deployment needs a manual re-sync. Built Mighty tests updates on staging first and ships them with your next release.';

        } elseif( in_array( $screen->id, $this->get_screens()['install'] ) ) {

            // Install content.
            $title      = 'You\'re about to add plugins to the live store';
            $content    = 'A new plugin or theme can conflict with the store and take checkout offline. It also puts code on the server that isn\'t in the site\'s repository. Ask Built Mighty and we\'ll add it safely.';

        } else {

            // Return.
            return;

        }

        // Output. ?>
        <div id="builtmighty-kit-modal" class="bmk-wp bmk-umodal" hidden>
            <div class="bmk-umodal-box" role="alertdialog" aria-modal="true" aria-labelledby="bmk-umodal-t" aria-describedby="bmk-umodal-d">
                <div class="bmk-umodal-head">
                    <svg class="bmk-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.3 4.2 2.8 17.5A2 2 0 0 0 4.5 20.5h15a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/><path d="M12 9.5v4M12 17h.01"/></svg>
                    <h2 id="bmk-umodal-t"><?php echo esc_html( $title ); ?></h2>
                </div>
                <p id="bmk-umodal-d"><?php echo esc_html( $content ); ?></p>
                <p class="bmk-umodal-contact">Questions? Email <a href="mailto:<?php echo esc_attr( antispambot( 'developers@builtmighty.com' ) ); ?>"><?php echo esc_html( antispambot( 'developers@builtmighty.com' ) ); ?></a>.</p>
                <div class="bmk-umodal-foot">
                    <button type="button" id="builtmighty-kit-modal-leave" class="bmk-btn bmk-btn-primary">Go back to the dashboard</button>
                    <button type="button" id="builtmighty-kit-modal-continue" class="bmk-btn bmk-btn-ghost">Continue anyway</button>
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
        wp_enqueue_script( 'kit-updates', KIT_URI . 'public/js/updates.js', [], KIT_VERSION, true );

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
                'plugins-network',
                'update-core-network',
                'themes-network',
            ],
            'install'   => [
                'plugin-install',
                'theme-install',
                'plugin-install-network',
                'theme-install-network',
            ]
        ];

    }

}