<?php
/**
 * AJAX.
 * 
 * Processes AJAX requests.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Core;
class builtAJAX {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Ajax.
        add_action( 'wp_ajax_built_email_protect', [ $this, 'protect_emails' ] );
        add_action( 'wp_ajax_built_email_replace', [ $this, 'replace_emails' ] );

    }

    /**
     * Protect emails.
     * 
     * @since   1.0.0
     */
    public function protect_emails() {

        // Check nonce.
        if( ! wp_verify_nonce( $_POST['nonce'], 'built' ) ) wp_die( 'Nonce failed.' );

        // Get setup.
        $setup = new \BuiltMightyKit\Core\builtSetup();

        // Reset.
        $data = $setup->update_emails( $_POST['data_set'] );

        // Calculate percentage done, based on count * offset / total.
        $data['percentage'] = round( $data['offset'] / $data['total'] * 100 );

        // Check percentage.
        if( $data['percentage'] >= 100 ) $data['percentage'] = 100;

        // Send JSON.
        echo json_encode( $data );

        // Execute Order 66.
        wp_die();

    }

    /**
     * Replace emails.
     * 
     * @since   1.0.0
     */
    public function replace_emails() {

        // Check nonce.
        if( ! wp_verify_nonce( $_POST['nonce'], 'built' ) ) wp_die( 'Nonce failed.' );

        // Get setup.
        $setup = new \BuiltMightyKit\Core\builtSetup();

        // Reset.
        $data = $setup->reset_emails( $_POST['data_set'] );

        // Calculate percentage done, based on count * offset / total.
        $data['percentage'] = round( $data['offset'] / $data['total'] * 100 );

        // Check percentage.
        if( $data['percentage'] >= 100 ) $data['percentage'] = 100;

        // Send JSON.
        echo json_encode( $data );

        // Execute Order 66.
        wp_die();

    }

}