<?php
/**
 * Mail.
 * 
 * Disables mail, if the constant is set, by universally changing the "to" address to developers@builtmighty.com.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtMail {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Filters.
        add_filter( 'wp_mail', [ $this, 'to_mail' ], 999999 );

    }

    /**
     * To Address.
     * 
     * Set to for all emails to developers@builtmighty.com
     * 
     * @since   1.0.0
     */
    public function to_mail( $args ) {

        // Check if site is mightyrhino.net/builtmighty.com or if constant is set.
        if( is_built_mighty() && ! defined( 'BUILT_ENABLE_EMAIL' ) || defined( 'BUILT_DISABLE_EMAIL' ) ) {

            // Update args. 
            $args['to'] = 'developers@builtmighty.com';

        }

        // Return the args.
        return $args;

    }

}