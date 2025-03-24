<?php
/**
 * Mail.
 *
 * An extendable class for creating and sending emails.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Utility;
use WC_Email;

/**
 * Disallow direct access.
 * 
 * @since   1.0.0
 */
if( ! defined( 'ABSPATH' ) ) { exit; }

class mail {

    /** 
     * Headers.
     * 
     * @since   1.0.0
     */
    protected array $headers;

    /** 
     * WC Email.
     * 
     * @since   1.0.0
     */
    protected WC_Email $wc_email;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Set headers.
        $this->headers  = ['Content-Type: text/html; charset=UTF-8'];

        // Set WC Email.
        $this->wc_email = new WC_Email();

    }

    /**
     * Sends an email.
     * 
     * @param  string  $email
     * @param  string  $subject
     * @param  string  $heading
     * @param  string  $message
     * @param  string  $attachment
     * 
     * @since   1.0.0
     */
    public function send( string $email, string $subject, string $heading, string $message, $attachment = null ) {

        // Allow child classes to modify the message before sending
        $wrapped_message    = $this->prepare_message( $heading, $message );
        $html_message       = $this->wc_email->style_inline( $wrapped_message );

        // Allow extending classes to modify headers
        $headers = $this->get_headers();

        // Send the email
        wp_mail( $email, $subject, $html_message, $headers, $attachment );

    }

    /**
     * Get headers.
     * 
     * @since   1.0.0
     */
    protected function get_headers(): array {
        return $this->headers;
    }

    /**
     * Prepare message.
     * 
     * @since   1.0.0
     */
    protected function prepare_message( string $heading, string $message ): string {
        return $this->wc_email->wrap_message( $heading, $message );
    }

}