<?php
/**
 * Email redirect.
 *
 * On copies of the store, every email goes to the developer inbox instead of
 * the customer. Cc and Bcc are removed so nobody else receives it either, and
 * each redirected email is logged so developers can see what would have been
 * sent.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;

if( ! defined( 'ABSPATH' ) ) { exit; }

class block_email {

    /**
     * Construct.
     */
    public function __construct() {

        if( ! settings::is_on( 'kit_block_email' ) ) return;

        add_filter( 'wp_mail', [ $this, 'redirect' ], PHP_INT_MAX );
        add_action( 'phpmailer_init', [ $this, 'enforce' ], PHP_INT_MAX );

    }

    /**
     * Where redirected email goes.
     */
    public static function inbox(): string {
        $to = (string) settings::get( 'kit_email_redirect_to' );
        return is_email( $to ) ? $to : 'developers@builtmighty.com';
    }

    /**
     * Rewrite recipients and log.
     *
     * @param  array $args wp_mail arguments.
     * @return array
     */
    public function redirect( $args ) {

        $original = is_array( $args['to'] ) ? implode( ', ', $args['to'] ) : (string) $args['to'];

        // Remove Cc and Bcc, keep everything else.
        $headers = $args['headers'] ?? [];
        if( ! is_array( $headers ) ) $headers = preg_split( "/\r\n|\n|\r/", (string) $headers );
        $kept = [];
        foreach( (array) $headers as $header ) {
            if( preg_match( '/^\s*(cc|bcc)\s*:/i', (string) $header ) ) continue;
            if( trim( (string) $header ) === '' ) continue;
            $kept[] = $header;
        }
        $kept[] = 'X-Original-To: ' . str_replace( [ "\r", "\n" ], '', $original );
        $kept[] = 'X-Kit-Redirected: 1';

        $args['headers'] = $kept;
        $args['to']      = self::inbox();

        // Log for the redirected-email viewer.
        $body = (string) ( $args['message'] ?? '' );
        activity::log( 'mail', 'redirected', 'Email to ' . $original . ' redirected', [
            'context' => [
                'to'       => $original,
                'subject'  => (string) ( $args['subject'] ?? '' ),
                'html'     => (bool) preg_match( '/<(html|body|table|div|p)[\s>]/i', $body ),
                'body'     => substr( $body, 0, 65000 ),
                'headers'  => array_values( array_filter( $kept, function( $h ) { return stripos( (string) $h, 'X-' ) !== 0; } ) ),
                'attached' => count( (array) ( $args['attachments'] ?? [] ) ),
            ],
        ] );

        return $args;

    }

    /**
     * Last line of defense: whatever happened after the wp_mail filter, only
     * the developer inbox receives this message.
     *
     * @param \PHPMailer\PHPMailer\PHPMailer $mailer
     */
    public function enforce( $mailer ) {
        try {
            $mailer->clearAllRecipients();
            $mailer->addAddress( self::inbox() );
        } catch( \Throwable $e ) {
            // PHPMailer throws on invalid addresses; wp_mail reports the failure.
        }
    }

}
