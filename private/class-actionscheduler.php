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
class actionscheduler {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // If the ActionScheduler doesn't exist, hasta la vista.
        if( ! class_exists( '\ActionScheduler' ) ) return;

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

        // Block.
        $block = false;

        // Check.
        if( is_kit_mode() && empty( get_option( 'kit_actionscheduler' ) ) ) $block = true;

        // Check for settings.
        if( ! $block && get_option( 'kit_actionscheduler' ) == 'enable' ) $block = true;

        // Check if site is mightyrhino.net/builtmighty.com or if constant is set.
        if( $block ) {

            // Disable the ActionScheduler.
            remove_action( 'action_scheduler_run_queue', [ \ActionScheduler::runner(), 'run' ] );

        }

    }

}
