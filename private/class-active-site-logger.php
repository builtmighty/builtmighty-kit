<?php
/**
 * Active Site Logger.
 *
 * Logs active sites to Built Mighty daily.
 *
 * @package Built Mighty Kit
 * @since   4.1.0
 */

namespace BuiltMightyKit\Private;

// Exit if accessed directly.
if( ! defined( 'WPINC' ) ) { die; }

class active_site_logger {

    /**
     * Cron hook name.
     *
     * @since 4.1.0
     * 
     * @var string
     */
    const CRON_HOOK = 'builtmightykit_daily_ping';

    /**
     * Instance.
     *
     * @since 4.1.0
     * 
     * @var self
     */
    public $instance;

    /**
     * Get the instance of the class.
     *
     * @since 4.1.0
     * 
     * @return self
     */
    public static function get_instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Constructor.
     *
     * @since 4.1.0
     * 
     * @return void
     */
    public function __construct() {

        // Register cron event.
        add_action( 'init', [ $this, 'schedule_cron' ] );

        // Clear cron event on deactivation.
        register_deactivation_hook( KIT_PATH . KIT_FILE, [ $this, 'deactivate' ] );

        // Add cron callback.
        add_action( self::CRON_HOOK, [ $this, 'send_ping' ] );

    }

    /**
     * Activate the plugin.
     *
     * @since 4.1.0
     * 
     * @return void
     * 
     * @hooked - action - init
     */
    public function schedule_cron() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Deactivate the plugin.
     *
     * @since 4.1.0
     * 
     * @return void
     * 
     * @hook - action - deactivate
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Send a ping to Built Mighty.
     *
     * @since 4.1.0
     * 
     * @return void
     * 
     * @hooked - action - builtmightykit_daily_ping
     */
    public function send_ping() {
        $body = [
            'site'    => get_site_url(),
            'context' => 'builtmightykit',
        ];
        $args = [
            'body'        => wp_json_encode( $body ),
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'timeout'     => 10,
            'data_format' => 'body',
        ];
        $endpoint = 'https://builtmighty.com/wp-json/builtmighty-kit/v1/active';

        // Send the request.
        $response = wp_remote_post( $endpoint, $args );

        // Check for errors.
        if ( is_wp_error( $response ) ) {
            error_log( 'Error sending ping: ' . $response->get_error_message() );
            return;
        }

        // Check for unsuccessful HTTP status codes.
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code < 200 || $status_code >= 300 ) {
            error_log( 'Ping failed with status code: ' . $status_code );
        }
    }
}