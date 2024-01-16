<?php
/**
 * Admin.
 * 
 * Adds a settings panel for admins.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtAdmin {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Add admin menu.
        add_action( 'admin_menu', [ $this, 'menu' ] );

        // Enqueue.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

        // Ajax.
        add_action( 'wp_ajax_built_email_replace', [ $this, 'replace_emails' ] );

    }

    /**
     * Add admin menu.
     * 
     * @since   1.0.0
     */
    public function menu() {

        // Get current user.
        $user = wp_get_current_user();

        // If user isn't an admin, later gator.
        if( ! in_array( 'administrator', $user->roles ) ) return;

        // Check if user email is @builtmighty.com.
        if( strpos( $user->user_email, '@builtmighty.com' ) === false ) return;

        // Add admin menu.
        add_menu_page( 
            'Built Mighty', 
            'Built Mighty', 
            'manage_options', 
            'builtmighty', 
            [ $this, 'page' ], 
            'dashicons-admin-tools', 
            99 
        );

    }

    /**
     * Admin page.
     * 
     * @since   1.0.0
     */
    public function page() {

        // Save.
        if( isset( $_POST['built-save'] ) ) {

            // Save.
            $this->save();

        }

        // New Jira API.
        $jira = new builtJira();

        // Get Jira objects.
        $projects   = $jira->get_projects();
        $users      = $jira->get_users();

        // Panel. ?>
        <div class="built-admin">
            <div class="built-logo">
                <img src="<?php echo BUILT_URI . 'assets/logo-builtmighty.png'; ?>" alt="Built Mighty">
            </div>
            <div class="built-panel built-admin-panel">
                <p>Welcome to the client configuration panel for this client. Here, you can connect both the client's project on Jira, as well as their project manager.</p>
                <form method="POST" class="built-fields"><?php

                    // Project select field.
                    echo $this->field( 'jira-project', 'Project', [
                        'type'      => 'select',
                        'options'   => $projects
                    ] );
                    
                    // User select field.
                    echo $this->field( 'jira-pm', 'Project Manager', [
                        'type'      => 'select',
                        'options'   => $users
                    ] ); 
                    
                    // Jira User field.
                    echo $this->field( 'jira-user', 'Jira User', [
                        'type'      => 'text'
                    ] );
                    
                    // Jira API Token.
                    echo $this->field( 'jira-token', 'Jira Token', [
                        'type'      => 'password'
                    ] ); ?>

                    <div class="built-save">
                        <input type="submit" class="button button-primary button-built" name="built-save" value="Save">
                    </div>
                </form>
            </div>
            <div class="built-panel built-admin-panel">
                <p>Customer email implmentation tools. Run this tool to re-implement real user emails, instead of the replacements.</p>
                <div class="built-email-tool">
                    <div class="built-email-progress">
                        <div class="built-email-bar-outer">
                            <div class="built-email-bar-inner"></div>
                        </div>
                        <div class="built-email-bar-status">
                            25%
                        </div>
                    </div>
                    <input type="submit" id="built-email" class="button button-primary button-built" data-count="0" data-offset="0" data-total="0" data-action="built_email_replace" name="built-tool" value="Run">
                </div>
            </div>
        </div><?php

    }

    /**
     * Field.
     * 
     * @since   1.0.0
     */
    public function field( $id, $label, $field ) {

        // Set value.
        $value = ( ! empty( get_option( $id ) ) ) ? get_option( $id ) : '';
        $value = ( ! empty( $_POST[ $id ] ) ) ? $_POST[ $id ] : $value;
        
        // Output. ?>
        <div class="built-field">
            <div class="built-label">
                <label for="<?php echo $id; ?>"><?php echo $label; ?></label>
            </div>
            <div class="built-input"><?php

                // Check type.
                if( $field['type'] == 'select' && ! empty( $field['options'] ) ) {

                    // Output select. ?>
                    <select name="<?php echo $id; ?>">
                        <option value="">Select...</option><?php

                        // Loop through options.
                        foreach( $field['options'] as $option_key => $option ) {

                            // Set selected.
                            $selected = ( $option_key == $value ) ? ' selected' : '';

                            // Output. ?>
                            <option value="<?php echo $option_key; ?>"<?php echo $selected; ?>><?php echo $option; ?></option><?php

                        } ?>

                    </select><?php

                } elseif( $field['type'] == 'password' ) {

                    // Check value.
                    if( ! empty( $value ) ) {

                        // Obfuscate value.
                        $value = '***********************';

                    }

                    // Output password. ?>
                    <input type="password" name="<?php echo $id; ?>" value="<?php echo $value; ?>"><?php

                } elseif( $field['type'] == 'text' ) {

                    // Output text. ?>
                    <input type="text" name="<?php echo $id; ?>" value="<?php echo $value; ?>"><?php

                } else {

                    // Don't output a field.

                } ?>

            </div>
        </div><?php


    }

    /**
     * Save.
     * 
     * @since   1.0.0
     */
    public function save() {

        // Loop.
        foreach( $_POST as $key => $value ) {

            // Sanitize.
            $value = sanitize_text_field( $value );

            // Check key.
            if( $key == 'jira-token' ) {

                // Check value.
                if( $value === '***********************' ) continue;

                // Get keys.
                $keys = new builtKeys();

                // Encrypt.
                $value = $keys->encrypt( $value );

                // Update option.
                update_option( $key, serialize( $value ) );

            } else {

                // Update option.
                update_option( $key, $value );

            }

        }

    }
    
    /**
     * Enqueue.
     * 
     * @since   1.0.0
     */
    public function enqueue() {

        // CSS.
        wp_enqueue_style( 'built-admin-settings', BUILT_URI . 'assets/admin-settings.css', [], BUILT_VERSION );

        // JS.
        wp_enqueue_script( 'built-admin-settings', BUILT_URI . 'assets/admin-settings.js', [ 'jquery' ], BUILT_VERSION, true );

        // Localize.
        wp_localize_script( 'built-admin-settings', 'built', [
            'ajax'  => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'built' )
        ] );

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
        $setup = new builtSetup();

        // Reset.
        $data = $setup->reset_emails( $_POST['count'], $_POST['offset'], $_POST['total'] );

        // Send JSON.
        echo json_encode( $data );

        // Execute Order 66.
        wp_die();

    }

}