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
                    ] );?>

                    <div class="built-save">
                        <input type="submit" class="button button-primary button-built" name="built-save" value="Save">
                    </div>
                </form>
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
                if( $field['type'] == 'select' ) {

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

                } else {

                    // Output text. ?>
                    <input type="text" name="<?php echo $id; ?>" value="<?php echo $value; ?>"><?php

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

            // Update option.
            update_option( $key, $value );

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

    }

}