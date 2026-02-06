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

        // Setting.
        $setting = defined( 'KIT_ACTION_SCHEDULER' ) ? KIT_ACTION_SCHEDULER : get_option( 'kit_actionscheduler' );

        // Confirm setting.
        $setting = is_string( $setting ) ? strtolower( trim( $setting ) ) : 'auto';
        if( ! in_array( $setting, [ 'auto', 'enable', 'disable' ], true ) ) {
            $setting = 'auto';
        }

        // Check if blocking.
        $block = match ( $setting ) {
            'enable'  => true,               // enable the block
            'disable' => false,              // disable the block
            default   => is_kit_mode(),      // auto
        };

        // Skip if not blocking.
        if( ! $block ) return;

        // Disable the ActionScheduler.
        remove_action( 'action_scheduler_run_queue', [ \ActionScheduler::runner(), 'run' ] );

        // Block before executing, just in case an outside source triggers.
        add_action( 'action_scheduler_before_execute', function() {

            // If something still tries to kick off, immediately short-circuit.
            wp_die( 'Action Scheduler is disabled in this environment.', '', 503 );
            
        }, 0 );

    }

}
