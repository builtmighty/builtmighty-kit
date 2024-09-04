<?php
/**
 * Updates.
 * 
 * List for plugin updates.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Plugins;
use function BuiltMightyKit\is_kit_mode;
class builtUpdates {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check if site is in kit mode.
        if( is_kit_mode() ) return;

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

            // Modal.
            include BUILT_PATH . 'views/plugins/plugin-update.php';

        } elseif( $screen->id === 'plugin-install' ) {

            // Modal.
            include BUILT_PATH . 'views/plugins/plugin-install.php';

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
        if( in_array( $screen->id, [ 'plugins', 'plugin-install', 'update-core', 'themes' ] ) ) {

            // CSS.
            wp_enqueue_style( 'built-updates', BUILT_URI . 'assets/updates/updates.css', [], BUILT_VERSION );

            // JS.
            wp_enqueue_script( 'built-updates', BUILT_URI . 'assets/updates/updates.js', [ 'jquery' ], BUILT_VERSION, true );

        }

    }

}