<?php
/**
 * WooCommerce.
 * 
 * Disables the action scheduler from running, if the constant is set within wp-config.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Frontend;
use function BuiltMightyKit\is_kit_mode;
class builtWoo {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Actions.
        add_action( 'init', [ $this, 'disable_actionscheduler' ], 10 );

    }

    /**
     * Disable Action Scheduler.
     * 
     * Disable the action scheduler from running, if the constant is set within wp-config.
     * 
     * @since   1.0.0
     */
    public function disable_actionscheduler() {

        // If the ActionScheduler doesn't exist, hasta la vista.
        if( ! class_exists( 'ActionScheduler' ) ) return;

        // Check if site is mightyrhino.net/builtmighty.com or if constant is set.
        if( is_kit_mode() && ! defined( 'BUILT_ENABLE_AS' ) || defined( 'BUILT_DISABLE_AS' ) ) {

            // Disable the ActionScheduler.
            remove_action( 'action_scheduler_run_queue', [ ActionScheduler::runner(), 'run' ] );

        }

    }

}
