<?php
/**
 * Initialize.
 *
 * Loads each feature only where it's needed: admin screens get admin
 * classes, the front end gets front-end classes, and everything that hooks
 * sign-in, email, HTTP or REST loads everywhere.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit;

if( ! defined( 'ABSPATH' ) ) { exit; }

class Plugin {

    /**
     * Instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Loaded feature instances.
     *
     * @var array
     */
    private $instances = [];

    /**
     * Construct.
     */
    private function __construct() {
        $this->init_classes();
    }

    /**
     * Get instance.
     */
    public static function get_instance() {
        if( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    /**
     * Get a loaded feature instance.
     */
    public function get( string $class ) {
        return $this->instances[ $class ] ?? null;
    }

    /**
     * Initiate classes by context.
     */
    private function init_classes() {

        $is_admin    = is_admin();
        $is_frontend = ! $is_admin && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );

        // Everywhere: these hook sign-in, mail, HTTP, REST, cron and option changes.
        $this->load_class( \BuiltMightyKit\Utility\slack::class );
        $this->load_class( \BuiltMightyKit\Public\security::class );
        $this->load_class( \BuiltMightyKit\Public\login::class );
        $this->load_class( \BuiltMightyKit\Public\login_security::class );
        $this->load_class( \BuiltMightyKit\Public\block_external::class );
        $this->load_class( \BuiltMightyKit\Public\block_email::class );
        $this->load_class( \BuiltMightyKit\Public\block_access::class );
        $this->load_class( \BuiltMightyKit\Public\security_headers::class );
        $this->load_class( \BuiltMightyKit\Public\login_logging::class );
        $this->load_class( \BuiltMightyKit\Public\session_management::class );
        $this->load_class( \BuiltMightyKit\Public\rest_api_security::class );
        $this->load_class( \BuiltMightyKit\Public\spam_protection::class );
        $this->load_class( \BuiltMightyKit\Public\admin_bar::class );
        $this->load_class( \BuiltMightyKit\Private\disable_editor::class );
        $this->load_class( \BuiltMightyKit\Private\actionscheduler::class );
        $this->load_class( \BuiltMightyKit\Private\store_safeguards::class );
        $this->load_class( \BuiltMightyKit\Private\audit::class );
        $this->load_class( \BuiltMightyKit\Private\health::class );
        $this->load_class( \BuiltMightyKit\Public\passkeys::class );
        $this->load_class( \BuiltMightyKit\Private\notifications::class );
        $this->load_class( \BuiltMightyKit\Private\speed::class );
        $this->load_class( \BuiltMightyKit\Private\performance::class );
        $this->load_class( \BuiltMightyKit\Private\active_site_logger::class );
        $this->load_class( \BuiltMightyKit\CRM\crm_analytics::class );
        $this->load_class( \BuiltMightyKit\CRM\crm_rum::class );
        $this->load_class( \BuiltMightyKit\Admin\rest::class );

        // Admin only (includes admin-ajax).
        if( $is_admin ) {
            $this->load_class( \BuiltMightyKit\Admin\app::class );
            $this->load_class( \BuiltMightyKit\Private\widgets::class );
            $this->load_class( \BuiltMightyKit\Private\updates::class );
            $this->load_class( \BuiltMightyKit\Private\plugins::class );
            $this->load_class( \BuiltMightyKit\Private\user_delete::class );
        }

        /**
         * Fires after Kit has loaded its features.
         *
         * @since 6.0.0
         */
        do_action( 'kit_loaded', $this, $is_frontend );

    }

    /**
     * Load class once.
     */
    private function load_class( $class ) {
        if( ! isset( $this->instances[ $class ] ) && class_exists( $class ) ) {
            $this->instances[ $class ] = new $class();
        }
    }

}
