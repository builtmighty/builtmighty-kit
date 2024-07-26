<?php
/**
 * Admin.
 * 
 * Adds a settings panel for admins.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Core;
class builtAdmin {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Add admin menu.
        add_action( 'admin_menu', [ $this, 'menu' ] );

        // Root.
        add_action( 'admin_head', [ $this, 'root' ] );

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
     * Set root.
     * 
     * @since   1.0.0
     */
    public function root() {

        // Set colors.
        $colors = [
            'red'           => '#D4121F',
            'light-red'     => '#e42029',
            'light-grey'    => '#2c3338',
            'dark-grey'     => '#1d2327',
            'white'         => '#FFFFFF',
            'black'         => '#000000'
        ];

        // Root CSS. ?>
        <style>
        :root {<?php

            // Loop.
            foreach( $colors as $key => $color ) {

                // Output. ?>
                --<?php echo $key; ?>: <?php echo $color; ?>;<?php

            } ?>

        }
        </style><?php

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
        
        // Panel. ?>
        <div class="built-admin">
            <div class="built-logo">
                <img src="<?php echo BUILT_URI . 'assets/images/logo-builtmighty.png'; ?>" alt="Built Mighty">
            </div><?php

            // Check for activation.
            if( isset( $_GET['activation'] ) && $_GET['activation'] == 'true' ) {

                // Email protection.
                include BUILT_PATH . 'views/core/admin-email-protection.php';

            } else {

                // Admin settings.
                include BUILT_PATH . 'views/core/admin-settings.php';

            } ?>

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

        // Check if value is serialized.
        if( is_serialized( $value ) ) {

            // Unserialize.
            $value = unserialize( $value );

        }

        // Set ID.
        $field_id = ( ! empty( $field['id'] ) ) ? ' id="' . $field['id'] . '"' : '';
        
        // Output. ?>
        <div class="built-field">
            <div class="built-label">
                <label for="<?php echo $id; ?>"><?php echo $label; ?></label>
            </div>
            <div class="built-input"><?php

                // Check type.
                if( $field['type'] == 'select' && ! empty( $field['options'] ) ) {

                    // Output select. ?>
                    <select <?php echo $field_id; ?>name="<?php echo $id; ?>">
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
                    <input <?php echo $field_id; ?>type="password" name="<?php echo $id; ?>" value="<?php echo $value; ?>"><?php

                } elseif( $field['type'] == 'text' ) {

                    // Output text. ?>
                    <input <?php echo $field_id; ?>type="text" name="<?php echo $id; ?>" value="<?php echo $value; ?>"><?php

                } elseif( $field['type'] == 'checkbox' ) {

                    // Loop through options.
                    foreach( $field['options'] as $option_key => $option ) {

                        // Set checked.
                        $checked = ( in_array( $option_key, (array)$value ) ) ? ' checked' : '';

                        // Output. ?>
                        <div class="builtmighty-checkbox">
                            <input <?php echo $field_id; ?>type="checkbox" name="<?php echo $id; ?>[]" value="<?php echo $option_key; ?>"<?php echo $checked; ?>> <?php echo $option; ?>
                        </div><?php

                    }

                } else {

                    // Don't output a field.

                } ?>

            </div>
        </div><?php


    }

    /**
     * Get time.
     * 
     * @since   1.0.0
     */
    public function get_time() {

        // Set time.
        $time = [];

        // Set range.
        $range = range( 0, 23 );

        // Loop.
        foreach( $range as $hour ) {

            // Get legible time with hour, minute, and am/pm.
            $time[$hour . ':00'] = date( 'g:i A', strtotime( $hour . ':00' ) );

        }

        // Return.
        return $time;

    }

    /**
     * Get notifications.
     * 
     * @since   1.0.0
     * 
     * @return  array
     */
    public function get_notifications() {

        // Return.
        return [
            'core-update'       => 'WordPress Update',
            'woocommerce'       => 'WooCommerce Settings',
            'plugin-install'    => 'Plugin Installation',
            'plugin-update'     => 'Plugin Update',
            'plugin-activate'   => 'Plugin Activation',
            'plugin-deactivate' => 'Plugin Deactivation',
            'plugin-editor'     => 'Plugin Editor',
            'theme-install'     => 'Theme Installation',
            'theme-update'      => 'Theme Update',
            'theme-change'      => 'Theme Change',
            'theme-editor'      => 'Theme Editor',
            'admin-create'      => 'Admin User Creation',
            'admin-delete'      => 'Admin User Deletion',
            'admin-role'        => 'Admin User Role Change',
            'admin-password'    => 'Admin User Password Change',
            'admin-email'       => 'Admin User Email Change',
            'admin-login'       => 'Admin User Login'
        ];

    }

    /**
     * Save.
     * 
     * @since   1.0.0
     */
    public function save() {

        // Loop.
        foreach( $_POST as $key => $value ) {

            // Check if array.
            if( is_array( $value ) ) {

                // Serialize.
                $value = serialize( $value );

            } else {

                // Sanitize.
                $value = sanitize_text_field( $value );

            }

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
        wp_enqueue_style( 'built-admin-settings', BUILT_URI . 'assets/core/admin.css', [], BUILT_VERSION );

        // JS.
        wp_enqueue_script( 'built-admin-settings', BUILT_URI . 'assets/core/admin.js', [ 'jquery' ], BUILT_VERSION, true );

        // Localize.
        wp_localize_script( 'built-admin-settings', 'built', [
            'ajax'  => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'built' )
        ] );

    }

}