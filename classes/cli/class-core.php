<?php
/**
 * CLI Core.
 * 
 * Create CLI commands for the Built Mighty Kit.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\CLI;
use function BuiltMightyKit\is_kit_mode;
class builtCore {

    /**
     * Disable External.
     * 
     * Run the command to disable external API connections via wp-config. NOTE: Will not apply on a production site.
     * 
     * wp kit core disable_external
     * 
     * @since   2.0.0
     */
    public function disable_external( $args, $assoc_args ) {

        // Get setup.
        $setup = new \BuiltMightyKit\Core\builtSetup();

        // Check for kit mode.
        if( ! is_kit_mode() ) {

            // Error.
            \WP_CLI::error( 'This command is only available in development mode.' );

        } else {

            // Run disable external.
            $setup->disable_external();
            
            // Output.
            \WP_CLI::success( 'Successfully disabled external API connections.' );

        }

    }

    /**
     * Disable Indexing.
     * 
     * Run the command to disable indexing on a non-production site.
     * 
     * wp kit core disable_indexing
     * 
     * @since   2.0.0
     */
    public function disable_indexing( $args, $assoc_args ) {

        // Get setup.
        $setup = new \BuiltMightyKit\Core\builtSetup();

        // Check for kit mode.
        if( ! is_kit_mode() ) {

            // Error.
            \WP_CLI::error( 'This command is only available in development mode.' );

        } else {

            // Run disable external.
            $setup->disable_indexing();
            
            // Output.
            \WP_CLI::success( 'Successfully disabled search engine indexing.' );

        }

    }

    /**
     * Disable Plugins.
     * 
     * Run the command to disable problematic plugins on a development site.
     * 
     * wp kit core disable_plugins
     * 
     * @since   2.0.0
     */
    public function disable_plugins( $args, $assoc_args ) {

        // Get setup.
        $setup = new \BuiltMightyKit\Core\builtSetup();

        // Check for kit mode.
        if( ! is_kit_mode() ) {

            // Error.
            \WP_CLI::error( 'This command is only available in development mode.' );

        } else {

            // Run disable external.
            $setup->disable_plugins();
            
            // Output.
            \WP_CLI::success( 'Successfully disabled possible problematic plugins. A list of disabled plugins is available in the admin dashboard.' );

        }

    }

    /**
     * Set environment type.
     * 
     * Run the command to set the environment type for the site.
     * 
     * wp kit core environment --type=value
     * 
     * @since   2.0.0
     */
    public function environment( $args, $assoc_args ) {

        // Get setup.
        $setup = new \BuiltMightyKit\Core\builtSetup();

        // Check for type.
        if( ! isset( $assoc_args['type'] ) ) {

            // Error.
            \WP_CLI::error( 'Please provide a type for the environment.' );

        } else {

            // Set the environment.
            $set = $setup->set_environment( $assoc_args['type'] );

            // Check if set.
            if( ! $set ) {

                // Error.
                \WP_CLI::error( 'There was an error setting the environment type. Available types are: local, development, staging, production' );

            } else {

                // Output.
                \WP_CLI::success( 'Successfully set the environment type to ' . $assoc_args['type'] . '.' );

            }

        }

    }

    /**
     * Update emails.
     * 
     * Run the command to update emails to user-RANDOMSTRING@builtmighty.com for the site.
     * 
     * wp kit core update_emails
     * 
     * @since   2.0.0
     */
    public function update_emails( $args, $assoc_args ) {

        // Get setup.
        $setup = new \BuiltMightyKit\Core\builtSetup();

        // Global.
        global $wpdb;

        // Set size.
        $size   = 100;
        $offset = 0;
        $total  = 0;

        // Get total users, if not set.
        if( $total == NULL || $total == 0 ) {

            // Get total users.
            $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
            
        }

        // Loop 
        while( true ) {

            // Set the query.
            $query = $wpdb->prepare( 
                "SELECT ID, user_email FROM {$wpdb->prefix}users LIMIT %d OFFSET %d", 
                $size, 
                $offset 
            );

            // Execute.
            $results = $wpdb->get_results( $query );

            // If we're at the end...
            if( empty( $results ) ) break;

            // Loop through results.
            foreach( $results as $user ) {

                // Check if user email is already a builtmighty.com email.
                if( strpos( $user->user_email, '@builtmighty.com' ) !== false ) continue;

                // Set original.
                $original = $user->user_email;

                // Generate a random string.
                $string = $setup->get_string();

                // Create new email.
                $new_email = explode( '@', $user->user_email )[0] . '.' . $string . '@builtmighty.com';

                // Search for post meta with user email.
                $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = '{$new_email}' WHERE meta_value = '{$user->user_email}'" );

                // Search for and update user meta with user email.
                $wpdb->query( "UPDATE {$wpdb->usermeta} SET meta_value = '{$new_email}' WHERE meta_value = '{$user->user_email}'" );

                // Save original email.
                update_user_meta( $user->ID, 'built_original_email', $original );

                // Update user email.
                $wpdb->update(
                    $wpdb->users,
                    [ 'user_email' => $new_email ],
                    [ 'ID' => $user->ID ]
                );

            }

            // Calculate percentage with no decimals.
            $percent = intval( ( $offset / $total ) * 100 );

            // Check percent. 
            if( $percent == 0 ) {

                // Output progress to WP-CLI
                \WP_CLI::success( 'Starting to update emails...' );

            } else {

                // Output progress to WP-CLI
                \WP_CLI::success( 'Updating emails...' . $percent . '% complete.' );

            }

            // Increase the offset for the next round...
            $offset += $size;

        }

        // Finished.
        \WP_CLI::success( 'Finished updating emails.' );

    }

    /**
     * Reset emails.
     * 
     * Run the command to reset emails to their original state for the site.
     * 
     * wp kit core reset_emails
     * 
     * @since   2.0.0
     */
    public function reset_emails( $args, $assoc_args ) {

        // Global.
        global $wpdb;

        // Set size.
        $size   = 100;
        $offset = 0;

        // Get total users.
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

        // Loop 
        while( true ) {

            // Set the query.
            $query = $wpdb->prepare( 
                "SELECT u.ID, u.user_email, m.meta_value FROM {$wpdb->users} u LEFT JOIN {$wpdb->usermeta} m ON (u.ID = m.user_id AND m.meta_key = 'built_original_email') WHERE m.meta_key = 'built_original_email' LIMIT %d OFFSET %d", 
                $size, 
                $offset 
            );

            // Execute.
            $results = $wpdb->get_results( $query );

            // If we're at the end...
            if( empty( $results ) ) break;

            // Loop through results.
            foreach( $results as $user ) {

                // Set original email.
                $original_email = $user->meta_value;

                // Search for post meta with user email.
                $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = '{$original_email}' WHERE meta_value = '{$user->user_email}'" );

                // Search for and update user meta with user email.
                $wpdb->query( "UPDATE {$wpdb->usermeta} SET meta_value = '{$original_email}' WHERE meta_value = '{$user->user_email}'" );

                // Update user email.
                $wpdb->update(
                    $wpdb->users,
                    [ 'user_email' => $original_email ],
                    [ 'ID' => $user->ID ]
                );

                // Delete original email meta.
                delete_user_meta( $user->ID, 'built_original_email' );

            }

            // Calculate percentage with no decimals.
            $percent = intval( ( $offset / $total ) * 100 );

            // Check percent. 
            if( $percent == 0 ) {

                // Output progress to WP-CLI
                \WP_CLI::success( 'Starting to reset emails...' );

            } else {

                // Output progress to WP-CLI
                \WP_CLI::success( 'Resetting emails...' . $percent . '% complete.' );

            }

            // Increase the offset for the next round...
            $offset += $size;

        }

        // Finished.
        \WP_CLI::success( 'Finished resetting emails.' );

    }

    /**
     * Clean customers.
     * 
     * Run the command to clean up customer data for the site.
     * 
     * wp kit core clean_customers
     * 
     * @since   2.0.0
     */
    public function clean_customers( $args, $assoc_args ) {

        // Check if WooCommerce is active.
        if( ! class_exists( 'WooCommerce' ) ) {

            // Error.
            \WP_CLI::error( 'WooCommerce is not active on this site.' );

        }

        // Global.
        global $wpdb;

        // Set size.
        $size   = 100;
        $offset = 0;

        // Get total.
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN ( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wp_capabilities' AND meta_value LIKE '%customer%' )" );

        // Loop.
        while( true ) {

            // Query.
            $query = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE ID IN (
                    SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wp_capabilities' AND meta_value LIKE %s
                ) LIMIT %d OFFSET %d",
                '%customer%',
                $size,
                $offset
            );

            // Get customer IDs.
            $customer_ids = $wpdb->get_col( $query );

            // Break the loop if no customers are found.
            if( empty( $customer_ids ) ) break;

            // Loop through customer IDs and delete them.
            foreach( $customer_ids as $customer_id ) {

                // Delete the user/customer.
                wp_delete_user( $customer_id );

            }

            // Percentage.
            $percent = intval( ( $offset / $total ) * 100 );

            // Check percent. 
            if( $percent == 0 ) {

                // Output progress to WP-CLI
                \WP_CLI::success( 'Starting to remove customer data...' );

            } else {

                // Output progress to WP-CLI
                \WP_CLI::success( 'Removing customer data...' . $percent . '% complete.' );

            }

            // Increase the offset.
            $offset += $size;

        }

        // Success.
        \WP_CLI::success( 'All customer data removed.' );

    }

    /**
     * Clean orders.
     * 
     * Run the command to clean orders off of the site.
     * 
     * wp kit core clean_orders
     * 
     * @since   2.0.0
     */
    public function clean_orders( $args, $assoc_args ) {

        // Check if WooCommerce is active.
        if( ! class_exists( 'WooCommerce' ) ) {

            // Error.
            \WP_CLI::error( 'WooCommerce is not active on this site.' );

        }

        // Global.
        global $wpdb;

        // Set size.
        $size   = 100;
        $offset = 0;

        // Get total.
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'" );

        // Loop.
        while( true ) {

            // Query.
            $query = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' LIMIT %d OFFSET %d",
                $size,
                $offset
            );

            // Get order IDs.
            $order_ids = $wpdb->get_col( $query );

            // Stop if we don't have orders.
            if( empty( $order_ids ) ) break;

            // Loop through order IDs and delete them.
            foreach( $order_ids as $order_id ) {

                // Get the order.
                $order = wc_get_order( $order_id );

                // Delete the order.
                $order->delete( true );

            }

            // Percentage.
            $percent = intval( ( $offset / $total ) * 100 );

            // Check percent. 
            if( $percent == 0 ) {

                // Output progress to WP-CLI
                \WP_CLI::success( 'Starting to remove orders...' );

            } else {

                // Output progress to WP-CLI
                \WP_CLI::success( 'Removing orders...' . $percent . '% complete.' );

            }
            
            // Increase the offset.
            $offset += $size;

        }

        // Success.
        \WP_CLI::success( 'All orders removed.' );

    }

}