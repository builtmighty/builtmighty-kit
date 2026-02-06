<?php
/**
 * Initialize.
 * 
 * Load all of our classes using a Singleton pattern.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit;
class Plugin {

    /**
     * Set instance(s).
     * 
     * @since   1.0.0
     */
    private static $instance = null;
    private $instances = [];

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    private function __construct() {

        // Initiate classes.
        $this->init_classes();

    }

    /**
     * Get instance.
     * 
     * @since   1.0.0
     */
    public static function get_instance() {

        // Set instance.
        if( self::$instance === null ) {

            // Set.
            self::$instance = new self();
            
        }

        // Return.
        return self::$instance;

    }

    /**
     * Initiate classes.
     * 
     * @since   1.0.0
     */
    private function init_classes() {

        // Load classes.
        $this->load_class( \BuiltMightyKit\Utility\slack::class );
        $this->load_class( \BuiltMightyKit\Public\core::class );
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
        $this->load_class( \BuiltMightyKit\Private\core::class );
        $this->load_class( \BuiltMightyKit\Private\widgets::class );
        $this->load_class( \BuiltMightyKit\Private\updates::class );
        $this->load_class( \BuiltMightyKit\Private\plugins::class );
        $this->load_class( \BuiltMightyKit\Private\disable_editor::class );
        $this->load_class( \BuiltMightyKit\Private\actionscheduler::class );
        $this->load_class( \BuiltMightyKit\Private\notifications::class );
        $this->load_class( \BuiltMightyKit\Private\speed::class );
        $this->load_class( \BuiltMightyKit\Private\performance::class );
        $this->load_class( \BuiltMightyKit\Private\active_site_logger::class );

        // CRM Analytics.
        $this->load_class( \BuiltMightyKit\CRM\crm_analytics::class );
        $this->load_class( \BuiltMightyKit\CRM\crm_rum::class );

    }

    /**
     * Load class.
     * 
     * @since   1.0.0
     * 
     * @param   string  $class
     */
    private function load_class( $class ) {

        // Check if class exists.
        if( ! isset( $this->instances[$class] ) ) {

            // Set instance.
            $this->instances[$class] = new $class();

        }
        
    }
    
}