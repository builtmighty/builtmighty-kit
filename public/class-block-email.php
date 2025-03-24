<?php
/**
 * Block Email.
 *
 * Block emails from sending.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Public;
use function BuiltMightyKit\is_kit_mode;
class block_email {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check.
        if( ( empty( get_option( 'kit_block_email' ) ) && is_kit_mode() ) || get_option( 'kit_block_external' ) == 'disable' ) return;

        // Filter.
        add_filter( 'wp_mail', [ $this, 'to_mail' ], 99999 );

    }

    /**
     * To Mail.
     * 
     * @since   1.0.0
     */
    public function to_mail( $args ) {

        // Update.
        $args['to'] = 'developers@builtmighty.com';

        // Return.
        return $args;

    }

}