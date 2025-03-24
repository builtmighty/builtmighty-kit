<?php
/**
 * Public.
 *
 * The core.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Public;
class core {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Enqueue.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

    }

    /**
     * Enqueue.
     * 
     * @since   1.0.0
     */
    public function enqueue() {

        // Get asset version.
        $version = file_exists( KIT_PATH . 'public/mix-manifest.json' ) ? json_decode( file_get_contents( KIT_PATH . 'public/mix-manifest.json' ), true ) : [];

        // Set version.
        $css    = $version['/css/style.css'] ?? time();
        $js     = $version['/js/main.js'] ?? time();

        // CSS.
        wp_enqueue_style( 'builtmighty-kit-css', KIT_URI . 'public/css/style.css', [], $css );

        // JS.
        wp_enqueue_script( 'builtmighty-kit-js', KIT_URI . 'public/js/main.js', [ 'jquery' ], $js, true );

    }
    
}
