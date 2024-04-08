<?php
/**
 * WooCommerce.
 * 
 * Disables the action scheduler from running, if the constant is set within wp-config.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtWoo {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Actions.
        add_action( 'init', [ $this, 'disable_actionscheduler' ], 1 );

    }

    /**
     * Disable Action Scheduler.
     * 
     * Disable the action scheduler from running, if the constant is set within wp-config.
     * 
     * @since   1.0.0
     */
    public function disable_actionscheduler() {

        // If WooCommerce isn't active, hasta la vista.
        if( ! class_exists( 'WooCommerce' ) ) return;

        // Check if site is mightyrhino.net/builtmighty.com or if constant is set.
        if( is_kit_mode() && ! defined( 'BUILT_ENABLE_AS' ) || defined( 'BUILT_DISABLE_AS' ) ) {

            // Remove the action scheduler.
            remove_action( 'init', 'action_scheduler_run_queue', 10 );

        }

    }

}