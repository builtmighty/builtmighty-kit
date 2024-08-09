<?php
/**
 * Initialize.
 * 
 * Load all of our classes using a Singleton pattern.
 * 
 * @package Built Mighty Kit
 * @since   2.2.0
 */
namespace BuiltMightyKit;
class Plugin {

    /**
     * Set instance(s).
     * 
     * @since   2.2.0
     */
    private static $instance = null;
    private $instances = [];

    /**
     * Construct.
     * 
     * @since   2.2.0
     */
    private function __construct() {

        // Initiate classes.
        $this->init_classes();

    }

    /**
     * Get instance.
     * 
     * @since   2.2.0
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
     * @since   2.2.0
     */
    private function init_classes() {

        // Load classes.
        $this->load_class( \BuiltMightyKit\Security\builtLogin::class );
        $this->load_class( \BuiltMightyKit\Security\builtAccess::class );
        $this->load_class( \BuiltMightyKit\Frontend\builtWoo::class );
        $this->load_class( \BuiltMightyKit\Frontend\builtMail::class );
        $this->load_class( \BuiltMightyKit\Security\builtSecurity::class );
        $this->load_class( \BuiltMightyKit\Core\builtDB::class );
        $this->load_class( \BuiltMightyKit\Data\builtData::class );
        $this->load_class( \BuiltMightyKit\Security\built2FA::class );
        $this->load_class( \BuiltMightyKit\Security\built2FASettings::class );
        $this->load_class( \BuiltMightyKit\Security\builtLockdown::class );
        $this->load_class( \BuiltMightyKit\Security\builtLockdownLog::class );
        $this->load_class( \BuiltMightyKit\Security\builtNotifications::class );
        $this->load_class( \BuiltMightyKit\Frontend\builtSpeed::class );
        $this->load_class( \BuiltMightyKit\Core\builtWidget::class );
        $this->load_class( \BuiltMightyKit\Core\builtAdmin::class );
        $this->load_class( \BuiltMightyKit\Core\builtAJAX::class );
        $this->load_class( \BuiltMightyKit\Plugins\builtSlack::class );
        $this->load_class( \BuiltMightyKit\Plugins\builtUpdates::class );

    }

    /**
     * Load class.
     * 
     * @since   2.2.0
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