<?php
/**
 * Log.
 *
 * An extendable class for creating logs.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Utility;

/**
 * Disallow direct access.
 * 
 * @since   1.0.0
 */
if( ! defined( 'ABSPATH' ) ) { exit; }

class log {

    /**
     * Log directory.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     string
     */
    protected string $log_dir;

    /**
     * Log file.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     string
     */
    protected string $log_file;

    /**
     * Construct.
     * 
     * @param  string  $log_filename
     * 
     * @since   1.0.0
     */
    public function __construct( string $log_filename = 'plugin.log' ) {

        // Get upload directory.
        $upload_dir = wp_upload_dir();

        // Set log directory.
        $this->log_dir = trailingslashit( $upload_dir['basedir'] ) . 'builtmighty-kit/';

        // Ensure the log directory exists.
        if( ! file_exists( $this->log_dir ) ) {

            // Create the log directory.
            wp_mkdir_p( $this->log_dir );

        }

        // Add log date.
        $log_filename = $log_filename . '-' . date( 'Y-m-d' );

        // Set log file.
        $this->log_file = $this->log_dir . sanitize_file_name( $log_filename );

    }

    /**
     * Logs a message with a given level.
     * 
     * @param  string  $message
     * @param  string  $level
     * 
     * @since   1.0.0
     */
    public function log( string $message, string $level = 'INFO' ) {

        // Get timestamp.
        $timestamp = date( 'Y-m-d H:i:s' );

        // Format message.
        $formatted_message = "[$timestamp] [$level] $message" . PHP_EOL;

        // Write log.
        return $this->write( $formatted_message );

    }

    /**
     * Logs an error message.
     * 
     * @param  string  $message
     * 
     * @since   1.0.0
     */
    public function error( string $message ) {
        return $this->log( $message, 'ERROR' );
    }

    /**
     * Logs a warning message.
     * 
     * @param  string  $message
     * 
     * @since   1.0.0
     */
    public function warning( string $message ) {
        return $this->log( $message, 'WARNING' );
    }

    /**
     * Logs an info message.
     * 
     * @param  string  $message
     * 
     * @since   1.0.0
     */
    public function info( string $message ) {
        return $this->log( $message, 'INFO' );
    }

    /**
     * Logs a debug message.
     * 
     * @param  string  $message
     * 
     * @since   1.0.0
     */
    public function debug( string $message ) {
        return $this->log( $message, 'DEBUG' );
    }

    /**
     * Writes the log message to the file.
     * 
     * @param  string  $message
     * 
     * @since   1.0.0
     */
    protected function write( string $message ): bool {

        // Check if the log directory is writable.
        if( is_writable( $this->log_dir ) ) {

            // Write to the log file.
            return file_put_contents( $this->log_file, $message, FILE_APPEND | LOCK_EX ) !== false;

        } else {

            // Fallback to error_log if file writing fails.
            error_log( "BuiltMightyKit Error: Unable to write to log file. Message: $message" );
            return false;

        }

    }

}