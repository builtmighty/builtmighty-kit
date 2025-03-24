<?php
/**
 * Disable Editor.
 *
 * Disables the theme/plugin file editor.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Private;
class disable_editor {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check.
        if( get_option( 'kit_disable_editor' ) !== 'enable' ) return;

        // Actions.
        add_action( 'admin_menu', [ $this, 'menus' ], 110 );

        // Filters.
        add_filter( 'user_has_cap', [ $this, 'editing' ], 10, 3 );

    }

    /**
     * Menus.
     * 
     * @since   1.0.0
     */
    public function menus() {

        // Remove.
        remove_submenu_page( 'themes.php', 'theme-editor.php' );
        remove_submenu_page( 'plugins.php', 'plugin-editor.php' );

    }

    /**
     * Capabilities.
     * 
     * @since   1.0.0
     */
    public function editing( $allcaps, $caps, $args ) {

        // Check.
        if( isset( $args[0] ) && in_array( $args[0], [ 'edit_themes', 'edit_plugins' ] ) ) {

            // Set.
            $allcaps[$args[0]] = false;

        }

        // Return.
        return $allcaps;

    }

}