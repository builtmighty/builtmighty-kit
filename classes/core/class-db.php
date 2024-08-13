<?php
/**
 * Database.
 * 
 * Creates database tables for plugin.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
namespace BuiltMightyKit\Core;
class builtDB {

    /**
     * Database.
     * 
     * @since   2.0.0
     */
    private $db;

    /**
     * Prefix.
     * 
     * @since   2.0.0
     */
    private $prefix;

    /**
     * Construct.
     * 
     * Initialize the class.
     * 
     * @since   2.0.0
     */
    public function __construct() {

        // Globals.
        global $wpdb, $table_prefix;

        // Set database.
        $this->db = $wpdb;

        // Set prefix.
        $this->prefix = $table_prefix;

        // On initialize.
        add_action( 'init', [ $this, 'check_version' ] );
        
    }

    /**
     * Check version.
     * 
     * @since   2.0.0
     */
    public function check_version() {

        // Check for a database version.
        if( empty( get_option( 'built_db_version' ) ) ) {

            // Create the tables.
            $this->create_tables();

            // Set option.
            update_option( 'built_db_version', BUILT_VERSION );

        } elseif( get_option( 'built_db_version' ) !== BUILT_VERSION ) {
            
            // Update the tables.
            $this->update_tables();

            // Set option.
            update_option( 'built_db_version', BUILT_VERSION );

        }

    }

    /**
     * Define the tables.
     * 
     * @since   2.0.0
     */
    public function define_tables() {

        // Set the tables.
        $tables = [
            'built_lockdown'        => [
                'id'            => 'int(11) NOT NULL AUTO_INCREMENT',
                'ip'            => 'VARCHAR(45) NOT NULL',
                'user_id'       => 'int(11) NOT NULL',
                'PRIMARY KEY'   => '(id)'
            ],
            'built_lockdown_log'    => [
                'id'            => 'int(11) NOT NULL AUTO_INCREMENT',
                'ip'            => 'VARCHAR(45) NOT NULL',
                'user_id'       => 'int(11) NOT NULL',
                'user_agent'    => 'text NOT NULL',
                'type'          => 'VARCHAR(45) NOT NULL',
                'status'        => 'VARCHAR(45) NOT NULL',
                'date'          => 'datetime NOT NULL',
                'PRIMARY KEY'   => '(id)'
            ],
            'built_site_data'       => [
                'id'            => 'int(11) NOT NULL AUTO_INCREMENT',
                'name'          => 'VARCHAR(45) NOT NULL',
                'value'         => 'text NOT NULL',
                'date'          => 'date NOT NULL',
                'PRIMARY KEY'   => '(id)'
            ],
        ];

        // Return the tables.
        return $tables;

    }

    /**
     * Create the tables.
     * 
     * @since   2.0.0
     */
    public function create_tables() {

        // Get the tables.
        $tables = $this->define_tables();

        // Loop through the tables.
        foreach( $tables as $table => $fields ) {

            // Set the table name.
            $table_name = $this->prefix . $table;

            // Create the table.
            if( $this->db->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {

                // Set the SQL.
                $sql = "CREATE TABLE $table_name (";

                // Loop through the fields.
                foreach( $fields as $field => $type ) {
                    $sql .= "$field $type, ";
                }

                // Remove the last comma.
                $sql = rtrim( $sql, ', ' );

                // Set the primary key.
                $sql .= ");";

                // Require the upgrade file.
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

                // Create the table.
                dbDelta( $sql );

            }

        }

    }

    /**
     * Update the tables.
     * 
     * @since   2.0.0
     */
    public function update_tables() {

        // Get the tables.
        $tables = $this->define_tables();

        // Loop through the tables.
        foreach( $tables as $table => $fields ) {

            // Set the table name.
            $table_name = $this->prefix . $table;

            // Check if table exists.
            if( $this->db->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
                
                // Create the tables.
                $this->create_tables();

            }

            // Get the columns.
            $columns = $this->db->get_results( "SHOW COLUMNS FROM $table_name" );

            // Loop through the fields.
            foreach( $fields as $field => $type ) {

                // Set the column name.
                $column_name = $field;

                // Check if column exists.
                if( ! in_array( $column_name, $columns ) ) {

                    // Set the SQL.
                    $sql = "ALTER TABLE $table_name ADD $column_name $type;";

                    // Require the upgrade file.
                    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

                    // Create the table.
                    dbDelta( $sql );

                } else {

                    // Set the SQL.
                    $sql = "ALTER TABLE $table_name MODIFY $column_name $type;";

                    // Require the upgrade file.
                    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

                    // Create the table.
                    dbDelta( $sql );

                }

            }

        }

    }

}