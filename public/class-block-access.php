<?php
/**
 * Access.
 *
 * Prevent non-logged in users from accessing the site.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Public;
use function BuiltMightyKit\is_kit_mode;
class block_access {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check.
        if( get_option( 'kit_block_access' ) !== 'enable' ) return;

        // Action.
        add_action( 'template_redirect', [ $this, 'block' ] );

    }

    /**
     * Block.
     * 
     * @since   1.0.0
     */
    public function block() {

        // If user is logged in, take off.
        if( is_user_logged_in() ) return;

        // If user is trying to login, sayonara.
        if( isset( $_POST['builtmighty_login'] ) ) return;

        // Check if cookie set.
        if( isset( $_COOKIE['builtmighty_bypass'] ) ) return;

        // Check if get parameter is set to bypass.
        if( isset( $_GET['bypass'] ) && $_GET['bypass'] == 'true' ) {

            // Set cookie.
            setcookie( 'builtmighty_bypass', 'true', time() + 3600, '/' );
            return;

        }

        // If custom login page is set and user is trying to access it, off I go.
        if( get_option( 'kit_enable_login' ) && ! empty( get_option( 'kit_login_url' ) ) && strpos( $_SERVER['REQUEST_URI'], '/' . ltrim( (string)get_option( 'kit_login_url' ), '/' ) . '/' ) !== false ) return;

        // Redirect to Built Mighty.
        wp_redirect( 'https://builtmighty.com' );
        exit;

    }

}