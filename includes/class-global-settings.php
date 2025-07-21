<?php
/**
 * Global Settings.
 *
 * Handles creating admin pages, as well as a global Built Mighty admin page.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMighty\GlobalSettings;;

// Check for class.
if( ! class_exists( 'BuiltMighty\GlobalSettings\settings' ) ) {

    class settings {

        /**
         * Instance.
         * 
         * @since   1.0.0
         * @access  private
         * @var     object
         */
        private static $instance = null;

        /**
         * Settings sections.
         * 
         * @since   1.0.0
         * @access  private
         * @var     array
         */
        private $settings_sections = [];

        /**
         * Settings fields.
         * 
         * @since   1.0.0
         * @access  private
         * @var     array
         */
        private $settings_fields = [];

        /**
         * Construct.
         * 
         * @since   1.0.0
         */
        private function __construct() {

            // Actions.
            add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
            add_action( 'admin_init', [ $this, 'register_settings' ], 999 );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

            add_action( 'show_user_profile', [ $this, 'builtmighty_admin_color_mode_field' ] );
            add_action( 'edit_user_profile', [ $this, 'builtmighty_admin_color_mode_field' ] );

            add_action( 'personal_options_update', [ $this, 'builtmighty_save_admin_color_mode' ] );
            add_action( 'edit_user_profile_update', [ $this, 'builtmighty_save_admin_color_mode' ] );


        }

        /**
         * Get the instance.
         * 
         * @since   1.0.0
         */
        public static function get_instance() {

            // Get.
            if( self::$instance === null ) self::$instance = new self();

            // Return.
            return self::$instance; 

        }

        /** 
         * Add settings page.
         * 
         * @since   1.0.0
         */
        public function add_settings_page() {

            // Check.
            if( defined( 'BUILT_MIGHTY_GLOBAL_SETTINGS_LOADED' ) ) return;

            // Define.
            define( 'BUILT_MIGHTY_GLOBAL_SETTINGS_LOADED', true );

            // Add.
            add_menu_page(
                'Built Mighty Plugin Settings',
                'Built Mighty',
                'manage_options',
                'builtmighty',
                [ $this, 'render_settings_page' ],
                'dashicons-admin-generic'
            );

        }

        /**
         * Register settings.
         * 
         * @since   1.0.0
         */
        public function register_settings() {

            // Loop.
            foreach( $this->settings_sections as $section_id => $section ) {

                // Add.
                add_settings_section( $section_id, $section['title'], $section['callback'], 'built-mighty-global-settings' );

            }

            // Loop.
            foreach( $this->settings_fields as $field_id => $field ) {

                // Add.
                add_settings_field( $field_id, $field['label'], $field['callback'], 'built-mighty-global-settings', $field['section'] );

                // Register.
                register_setting( 'built_mighty_global_settings_group', $field_id );

            }

        }

        /** 
         * Enqueue.
         * 
         * @since   1.0.0
         */
        public function enqueue() {

            // Determine color mode
            $color_mode = 'system';
            if ( is_user_logged_in() ) {
                $user_mode = get_user_meta( get_current_user_id(), 'builtmighty_admin_color_mode', true );
                if ( in_array( $user_mode, [ 'dark', 'light', 'system' ] ) ) {
                    $color_mode = $user_mode;
                }
            }

            // CSS.
            wp_enqueue_style( 'builtmighty-admin', KIT_URI . 'includes/builtmighty-settings.css', [], date( 'YmdHis' ) );
            wp_enqueue_media();
            wp_enqueue_style( 'wp-color-picker' );

            // Add a body class for user override
            if ( $color_mode !== 'system' ) {
                add_filter( 'admin_body_class', function( $classes ) use ( $color_mode ) {
                    return "$classes builtmighty-admin-$color_mode-mode";
                });
            }

            // JS.
            wp_enqueue_script( 'wp-color-picker' );
            wp_enqueue_script( 'builtmighty-admin', KIT_URI . 'includes/builtmighty-settings.js', [ 'jquery', 'wp-color-picker' ], date( 'YmdHis' ), true );

        }

        /**
         * Allows plugins to add a settings section.
         * 
         * @since   1.0.0
         */
        public function add_settings_section( $id, $title, $callback, $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->settings_sections[$id] = [
                'title'     => $title,
                'callback'  => $callback
            ];
            
        }

        /**
         * Allows plugins to add a settings field.
         * 
         * @since   1.0.0
         */
        public function add_settings_field( $id, $label, $callback, $section ) {
            $this->settings_fields[$id] = [
                'label'     => $label,
                'callback'  => $callback,
                'section'   => $section
            ];
        }

        /**
         * Renders the settings page.
         * 
         * @since   1.0.0
         */
        public function render_settings_page() { ?>

            <div class="wrap builtmighty-wrap"><?php

                // Action.
                do_action( 'before_builtmighty_settings_logo' ); 
                
                // Content. ?>
                <a href="https://builtmighty.com" target="_blank">
                    <img src="<?php echo KIT_URI; ?>includes/logo-builtmighty.png" alt="Built Mighty" class="builtmighty-logo" />
                </a><?php

                // Action.
                do_action( 'before_builtmighty_settings_form' ); ?>

                <form method="post" action="options.php">
                    <?php settings_fields( 'built_mighty_global_settings_group' ); ?>
                    <ul id="builtmighty-tabs"><?php

                        // Set count.
                        $count = 0;

                        // Tabs.
                        foreach( $this->settings_sections as $section_id => $section ) {

                            // Add to count.
                            $count++;

                            // Class.
                            $class = ( $count == 1 ) ? ' active' : '';

                            // Filters.
                            $section_data = [
                                'id'    => apply_filters( 'builtmighty_settings_section_id', $section_id ),
                                'title' => apply_filters( 'builtmighty_settings_section_title', $section['title'] ),
                                'class' => apply_filters( 'builtmighty_settings_section_class', $class )
                            ];

                            // Output. ?>
                            <li class="builtmighty-tab<?php echo $section_data['class']; ?>" data-id="<?php echo $section_data['id']; ?>"><?php echo $section_data['title']; ?></li><?php

                        } ?>

                    </ul><?php

                    // Set count.
                    $count = 0;
                    
                    // Content.
                    foreach( $this->settings_sections as $section_id => $section ) {

                        // Add to count.
                        $count++;

                        // Class.
                        $class = ( $count == 1 ) ? ' active' : '';

                        // Data.
                        $section_data = [
                            'id'    => apply_filters( 'builtmighty_settings_section_id', $section_id ),
                            'title' => apply_filters( 'builtmighty_settings_section_title', $section['title'] ),
                            'class' => apply_filters( 'builtmighty_settings_section_class', $class )
                        ];

                        // Output. ?>
                        <div id="<?php echo $section_data['id']; ?>" class="builtmighty-tab-content<?php echo $section_data['class']; ?>"><?php

                            // Fields.
                            do_settings_fields('built-mighty-global-settings', $section_data['id'] ); ?>

                        </div><?php

                    } 
                    
                    // Action.
                    do_action( 'before_builtmighty_settings_submit' );

                    // Submit button.
                    submit_button();
                    
                    // Action.
                    do_action( 'after_builtmighty_settings_submit' ); ?>
                </form><?php

                // Action.
                do_action( 'after_builtmighty_settings_form' ); ?>

            </div><?php

        }

        /**
         * Create message field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function message( $id, $label = '', $content = '', $section, $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $content ) { ?>
                <div class="builtmighty-message"><?php

                    // Check if label is set.
                    if( ! empty( $label ) ) { ?>

                        <h3><?php echo $label; ?></h3><?php

                    }

                    // Check if content is set.
                    if( ! empty( $content ) ) { ?>

                        <p><?php echo $content; ?></p><?php

                    } ?>

                </div><?php
            }, $section );

        }

        /**
         * Create text field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function text_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-text-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="text" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create textarea field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function textarea_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-textarea-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <textarea name="<?php echo esc_attr( $id ); ?>" class="large-text
                        "><?php echo esc_textarea( $value ); ?></textarea>
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create password field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function password_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-password-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="password" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create select field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * @param   array   $options
         * 
         * @since   1.0.0
         */
        public function select_field( $id, $label, $section, $options, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $options, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-select-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <select name="<?php echo esc_attr( $id ); ?>"><?php
                            foreach( $options as $option_value => $option_label ) {
                                echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
                            } ?>
                        </select>
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create checkbox field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function checkbox_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-checkbox-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <label for="<?php echo esc_attr( $id ); ?>">
                        <input type="checkbox" name="<?php echo esc_attr( $id ); ?>" value="1" <?php checked( $value, 1 ); ?> />
                        Enable
                    </label><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create a checkboxes field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * @param   array   $options
         * @param   boolean $access
         */
        public function checkboxes_field( $id, $label, $section, $options, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $options, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-checkboxes-field">
                    <span class="builtmighty-field-label"><?php echo $label; ?></span><?php
                    foreach( $options as $option_value => $option_label ) {
                        $checked = ( is_array( $value ) && in_array( $option_value, $value ) ) ? ' checked' : '';
                        echo '<label><input type="checkbox" name="' . esc_attr( $id ) . '[]" value="' . esc_attr( $option_value ) . '"' . $checked . ' />' . esc_html( $option_label ) . '</label>';
                    }

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create radio field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * @param   array   $options
         * 
         * @since   1.0.0
         */
        public function radio_field( $id, $label, $section, $options, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $options, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-radio-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span><?php
                    foreach( $options as $option_value => $option_label ) {
                        echo '<label><input type="radio" name="' . esc_attr( $id ) . '" value="' . esc_attr( $option_value ) . '" ' . checked( $value, $option_value, false ) . ' />' . esc_html( $option_label ) . '</label>';
                    }

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create image field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function image_field( $id, $label, $section, $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-image-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input id="<?php echo esc_attr( $id ); ?>" type="text" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text builtmighty-upload-image-field" />
                        <button id="<?php echo esc_attr( $id ); ?>-button" class="button builtmighty-upload-image-button">Upload Image</button>
                    </div>
                    <div class="builtmighty-field_image">
                        <img id="<?php echo esc_attr( $id ); ?>-preview" class="builtmighty-upload-image-preview" style="max-width:500px;display:block;margin-top:15px;" src="<?php echo esc_url( $value ); ?>" />
                    </div>
                </div><?php
            }, $section );
            
        }

        /**
         * Create color field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function color_field( $id, $label, $section, $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, $label, function() use ( $id ) {
                $value = get_option( $id, '' ); ?>
                <input type="text" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="builtmighty-color-field" /><?php
            }, $section );
            
        }

        /**
         * Create date field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function date_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-date-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="date" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create time field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function time_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-time-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="time" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );
            
        }

        /**
         * Create datetime field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function datetime_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-datetime-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></s>
                    <div class="builtmighty-field_inner">
                        <input type="datetime-local" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create email field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function email_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-email-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="email" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create number field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function number_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-number-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="number" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create range field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * @param   int     $min
         * @param   int     $max
         * 
         * @since   1.0.0
         */
        public function range_field( $id, $label, $section, $min, $max, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $min, $max, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-range-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="range" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create url field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function url_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-url-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="url" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create tel field.
         * 
         * @param   string  $id
         * @param   string  $label
         * @param   string  $section
         * 
         * @since   1.0.0
         */
        public function tel_field( $id, $label, $section, $desc = '', $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $label, $desc ) {
                $value = get_option( $id, '' ); ?>
                <div class="builtmighty-field builtmighty-tel-field">
                    <span class="builtmighty-field-label"><?php echo esc_html( $label ); ?></span>
                    <div class="builtmighty-field_inner">
                        <input type="tel" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                    </div><?php

                    // Check if description is set.
                    if( ! empty( $desc ) ) { ?>

                        <p class="description"><?php echo $desc; ?></p><?php

                    } ?>
                </div><?php
            }, $section );

        }

        /**
         * Create hidden field.
         * 
         * @param   string  $id
         * @param   string  $value
         * 
         * @since   1.0.0
         */
        public function hidden_field( $id, $value, $section, $access = false ) {

            // Check user.
            if( $access == false && ! $this->is_builtmighty() ) return;

            $this->add_settings_field( $id, '', function() use ( $id, $value ) {
                echo '<input type="hidden" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" />';
            }, $section );
            
        }

        /**
         * Is Built Mighty?
         * 
         * @since   1.0.0
         */
        public function is_builtmighty() {

            // Check user.
            if( ! is_user_logged_in() ) return false;

            // Get current user email.
            $user_email = wp_get_current_user()->user_email;

            // Explode.
            $user_email = explode( '@', $user_email );

            // Valid.
            $valid = apply_filters( 'builtmighty_settings_valid_domains', [ 'builtmighty.com', 'littlerhino.io' ] );

            // Return.
            return ( in_array( $user_email[1], (array)$valid ) ) ? true : false;

        }

        /**
         * Add Built Mighty Admin Color Mode field to user profile.
         * 
         * @param   \WP_User $user - The user object.
         *
         * @return  void
         *
         * @hook    edit_user_profile
         * @hook    show_user_profile
         *
         * @since   4.2.0
         */
        public function builtmighty_admin_color_mode_field( $user ) {
            $value = get_user_meta( $user->ID, 'builtmighty_admin_color_mode', true ) ?: 'system';
            ?>
            <h3>Built Mighty Admin Color Mode</h3>
            <table class="form-table">
                <tr>
                    <th><label for="builtmighty_admin_color_mode">Color Mode</label></th>
                    <td>
                        <select name="builtmighty_admin_color_mode" id="builtmighty_admin_color_mode">
                            <option value="system" <?php selected( $value, 'system' ); ?>>System Default</option>
                            <option value="light" <?php selected( $value, 'light' ); ?>>Light</option>
                            <option value="dark" <?php selected( $value, 'dark' ); ?>>Dark</option>
                        </select>
                        <p class="description">Choose your preferred color mode for Built Mighty Kit admin screens.</p>
                    </td>
                </tr>
            </table>
            <?php
        }

        /**
         * Save Built Mighty Admin Color Mode.
         * 
         * @param   int $user_id - The user ID.
         *
         * @return  void
         *
         * @hook    builtmighty_user_profile_update
         * @hook    builtmighty_user_register
         *
         * @since   4.2.0
         */
        public function builtmighty_save_admin_color_mode( $user_id ) {
            if ( ! current_user_can( 'edit_user', $user_id ) ) return;
            $mode = $_POST['builtmighty_admin_color_mode'] ?? 'system';
            if ( in_array( $mode, [ 'dark', 'light', 'system' ] ) ) {
                update_user_meta( $user_id, 'builtmighty_admin_color_mode', $mode );
            }
        }

    }

    // Get instance.
    \BuiltMighty\GlobalSettings\settings::get_instance();

}