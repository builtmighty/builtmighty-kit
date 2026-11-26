<?php
/**
 * Scheduled actions.
 *
 * On copies of the store, Action Scheduler stops processing its queue, so
 * renewal charges, queued emails and webhooks wait. Jobs stay in the queue
 * and can be inspected (or run by hand) under Tools, Scheduled Actions.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Private;

use BuiltMightyKit\Utility\settings;

if( ! defined( 'ABSPATH' ) ) { exit; }

class actionscheduler {

    /**
     * Construct.
     */
    public function __construct() {

        if( ! settings::is_on( 'kit_actionscheduler' ) ) return;

        // No async runner from admin page loads, and no queue batches from WP-Cron.
        add_filter( 'action_scheduler_allow_async_request_runner', '__return_false', PHP_INT_MAX );
        add_filter( 'action_scheduler_queue_runner_concurrent_batches', '__return_zero', PHP_INT_MAX );

        // Remove the WP-Cron queue runner once Action Scheduler has hooked it.
        add_action( 'init', [ $this, 'unhook_runner' ], PHP_INT_MAX );

    }

    /**
     * Unhook the WP-Cron runner.
     */
    public function unhook_runner() {
        if( ! class_exists( '\ActionScheduler' ) ) return;
        $runner = \ActionScheduler::runner();
        if( $runner ) remove_action( 'action_scheduler_run_queue', [ $runner, 'run' ] );
    }

    /**
     * Is the queue paused right now?
     */
    public static function paused(): bool {
        return settings::is_on( 'kit_actionscheduler' );
    }

}
