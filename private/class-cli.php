<?php
/**
 * CLI.
 *
 * Command Line Interface utilities.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Private;

/**
 * CLI.
 *
 * Commands for processing renewals.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
class CLI {

    /**
     * Two-Factor Authentication.
     * 
     * @since   1.0.0
     * 
     * wp kit tfa --user_id=1 --type=email,cli --code=123456 // Authentication code for CLI setup.
     */
    public function tfa( $args, $assoc_args ) {

        // Get user.
        if( ! isset( $assoc_args['user_id'] ) ) {
            \WP_CLI::error( 'User ID is required. Use --user_id=' );
        }

        // Get type.
        if( ! isset( $assoc_args['type'] ) ) {
            \WP_CLI::error( 'Type is required. Use --type=email,cli. Email sends the user an email for setup. CLI allows you to setup here.' );
        }

        // Check type.
        if( ! in_array( $assoc_args['type'], [ 'email', 'cli' ] ) ) {
            \WP_CLI::error( 'Type must be email or cli.' );
        }

        // Get user.
        $user = get_user_by( 'id', $assoc_args['user_id'] );

        // Check user.
        if( ! $user ) {
            \WP_CLI::error( 'User not found.' );
        }

        // Get auth.
        $auth = new \BuiltMightyKit\Utility\authentication();

        // Check if user has Two-Factor Authentication enabled.
        if( $auth->is_enabled( $user ) ) {
            \WP_CLI::log( 'Two-Factor Authentication is already enabled for ' . ucwords( $user->display_name ) . '.' );
            \WP_CLI::confirm( 'Would you like to disable Two-Factor Authentication for ' . ucwords( $user->display_name ) . '?' );
            $auth->disable( $user );
            \WP_CLI::success( 'Two-Factor Authentication disabled for ' . ucwords( $user->display_name ) . '.' );
        }

        // Check type and confirm.
        if( $assoc_args['type'] == 'email' ) {

            // Confirm.
            \WP_CLI::confirm( 'Are you sure you want to send a Two-Factor Authentication setup email to ' . $user->user_email . '?' );

            // Send setup.
            $auth->send_setup( $user );

            // Success.
            \WP_CLI::success( 'Two-Factor Authentication setup email sent to ' . $user->user_email . '.' );

        } else {

            // Check if code is set.
            if( ! isset( $assoc_args['code'] ) ) {

                // Confirm.
                \WP_CLI::confirm( 'Are you sure you want to setup Two-Factor Authentication for ' . ucwords( $user->display_name ) . ' now?' );

                // Generate key.
                $auth->generate_key( $user, true );

                // Generate secret.
                $secret = $auth->generate_secret( $user );

                // Success.
                \WP_CLI::success( 'The setup code for ' . ucwords( $user->display_name ) . ' is: ' . $secret );

            } else {

                // Get the user's key.
                $key = get_user_meta( $user->ID, 'authentication_setup', true );
                $key = base64_encode( $user->ID . ':' . $key );

                // Confirm.
                $confirm = $auth->confirm( $key, $assoc_args['code'] );

                // Check confirm.
                if( ! $confirm ) {
                    $auth->disable( $user );
                    \WP_CLI::error( 'Invalid code. Please restart and try again.' );
                } else {
                    // Success.
                    \WP_CLI::success( 'Two-Factor Authentication setup completed for ' . ucwords( $user->display_name ) . '.' );
                }

            }

        }

    }

    /**
     * Remove customers.
     * 
     * Removes customers and their data from the database.
     * 
     * wp kit remove_customers --user_id=1
     */
    public function remove_customers( $args, $assoc_args ) {

        // Check if WooCommerce is active.
        if( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce is not active.' );
        }

        // Global.
        global $wpdb;

        // Set total.
        $total = 1;

        // If single or multi-user.
        if( isset( $assoc_args['user_id'] ) ) {

            // Get user.
            $user = get_user_by( 'id', $assoc_args['user_id'] );

            // Confirm.
            \WP_CLI::confirm( 'Are you sure you want to remove ' . $user->display_name . '?' );

            // Remove user.
            wp_delete_user( $user->ID );

            // Success.
            \WP_CLI::success( 'User ' . $user->display_name . ' has been removed.' );

        } else {

            // Get total users.
            $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN ( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wp_capabilities' AND ( meta_value LIKE '%subscriber%' OR meta_value LIKE '%customer%' ) )" );

            // Confirm.
            \WP_CLI::confirm( 'Are you sure you want to remove ' . $total . ' customers?' );

        }

        // Set progress.
        $progress = \WP_CLI\Utils\make_progress_bar( 'Removing Users', $total );

        // Loop.
        while( true ) {

            // Get customers.
            $users = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE ID IN ( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wp_capabilities' AND ( meta_value LIKE '%subscriber%' OR meta_value LIKE '%customer%' ) ) LIMIT %s",
                100
            ) );

            // Stop if we don't have any more customers.
            if( empty( $users ) ) break;

            // Loop through customers.
            foreach( $users as $user_id ) {

                // Remove user.
                wp_delete_user( $user_id );

                // Tick.
                $progress->tick();

            }

        }

        // Success.
        \WP_CLI::success( 'All ' . $total . ' customers have been removed.' );

    }

    /**
     * Remove orders.
     * 
     * Removes orders from the database.
     * 
     * wp kit remove_orders --order_id=1 --status=wc-pending,wc-cancelled --start=2020-01-01 --end=2020-01-01
     */
    public function remove_orders( $args, $assoc_args ) {

        // Check if WooCommerce is active.
        if( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce is not active.' );
        }

        // Global.
        global $wpdb;

        // Get total orders.
        $total = 1;
        
        // If single or multi-user.
        if( isset( $assoc_args['order_id'] ) ) {

            // Confirm.
            \WP_CLI::confirm( 'Are you sure you want to remove order #' . $assoc_args['order_id'] . '?' );

            // Delete order.
            wp_delete_post( $assoc_args['order_id'], true );

            // Success.
            \WP_CLI::success( 'Order #' . $assoc_args['order_id'] . ' has been removed.' );

        } else {

            // Set total query.
            $query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'" . $this->order_settings( $assoc_args );

            // Get total.
            $total = $wpdb->get_var( $query );

            // Confirm.
            \WP_CLI::confirm( 'Are you sure you want to remove ' . $total . ' orders?' );

        }

        // Set progress.
        $progress = \WP_CLI\Utils\make_progress_bar( 'Removing Orders', $total );

        // Loop.
        while( true ) {

            // Get orders.
            $orders = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order'" . $this->order_settings( $assoc_args ) . " LIMIT %s",
                100
            ) );

            // Stop if we don't have any more orders.
            if( empty( $orders ) ) break;

            // Loop through orders.
            foreach( $orders as $order_id ) {

                // Delete order.
                wp_delete_post( $order_id, true );

                // Tick.
                $progress->tick();

            }

        }

        // Success.
        \WP_CLI::success( 'All ' . $total . ' orders have been removed.' );

    }

    /**
     * Process renewals.
     * 
     * Processes WooCommerce Subscription renewals if pending payment.
     * 
     * wp kit process_renewals --order_id=123
     * 
     * @since   1.0.0
     */
    public function process_renewals( $args, $assoc_args ) {

        // Check if WooCommerce is active.
        if( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce is not active.' );
        }

        // Check if WooCommerce Subscriptions is active.
        if( ! class_exists( 'WC_Subscriptions' ) ) {
            \WP_CLI::error( 'WooCommerce Subscriptions is not active.' );
        }

        // Global.
        global $wpdb;

        // Get total of pending renewal orders.
        $total = ( ! isset( $assoc_args['order_id'] ) ) ? $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'wc-pending'" ) : 1;

        // Confirm.
        if( ! isset( $assoc_args['order_id'] ) ) {
            \WP_CLI::confirm( 'Are you sure you want to process ' . $total . ' renewals?' );
        } else {
            \WP_CLI::confirm( 'Are you sure you want to process renewal for order #' . $assoc_args['order_id'] . '?' );
        }

        // Set progress.
        $progress = \WP_CLI\Utils\make_progress_bar( 'Processing Renewals', $total );

        // Loop.
        while( true ) {

            // Check if single order.
            if( ! isset( $assoc_args['order_id'] ) ) {

                // Query for pending renewal orders.
                $query = $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'wc-pending' LIMIT %s",
                    50
                );

                // Get orders.
                $orders = $wpdb->get_col( $query );

            } else {

                // Get single order.
                $orders = [ $assoc_args['order_id'] ];

            }

            // Stop if we don't have orders.
            if( empty( $orders ) ) break;

            // Loop through orders.
            foreach( $orders as $order_id ) {

                // Payment.
                $payment = $this->payment( $order_id );

                // Log.
                \WP_CLI::log( $payment['message'] );
                $progress->tick();

            }

            // Stop.
            if( isset( $assoc_args['order_id'] ) ) break;

        }

        // Finish progress.
        $progress->finish();

        // Success.
        \WP_CLI::success( 'Finished processing renewals for ' . $total . ' orders.' );

    }

    /**
     * Payment.
     * 
     * @since   1.0.0
     */
    private function payment( $order_id ) {

        // Get order.
        $order = wc_get_order( $order_id );

        // Check order.
        if( ! $order ) {
            return [ 'message' => 'Order not found.', 'success' => false ];
        }

        // Check order status.
        if( ! $order->has_status( 'pending' ) ) {
            return [ 'message' => 'Order is not pending.', 'success' => false ];
        }

        // Check if this is a renewal order.
        if( ! wcs_order_contains_renewal( $order ) ) {
            return [ 'message' => 'Order is not a renewal order.', 'success' => false ];
        }

        // Remove manual payment flag.
        delete_post_meta( $order_id, '_requires_manual_renewal' );

        // Get subscription.
        $subscription = wcs_get_subscription( get_post_meta( $order_id, '_subscription_renewal', true ) );

        // Check subscription.
        if( ! $subscription ) {
            return [ 'message' => 'Subscription not found.', 'success' => false ];
        }

        // Check if subscription is on-hold.
        if( $subscription->get_status() != 'on-hold' ) {
            return [ 'message' => 'Subscription is not on-hold.', 'success' => false ];
        }

        // Get payment method from subscription.
        $method = $subscription->get_payment_method();

        // Check payment method.
        if( ! $method ) {
            return [ 'message' => 'Payment method not found.', 'success' => false ];
        }

        // Set payment method.
        $order->set_payment_method( $method );
        $order->save();

        // Get gateways.
        WC()->payment_gateways();

        // Trigger payment.
        do_action( 'woocommerce_scheduled_subscription_payment_' . $method, $order->get_total(), $order );

        // Return. 
        return [ 'message' => 'Payment processed for #' . $order->get_id() . '.', 'success' => true ];

    }

    /**
     * Order settings.
     * 
     * @since   1.0.0
     */
    public function order_settings( $args ) {

        // Query.
        $query = "";

        // Check if status is set.
        if( isset( $args['status'] ) ) {

            // Check if status is an array.
            if( strpos( $args['status'], ',' ) !== false ) {
                $args['status'] = explode( ',', $args['status'] );
                $args['status'] = array_map( 'trim', $args['status'] );
                $args['status'] = implode( "','", $args['status'] );
            }

            // Add status to query.
            $query .= " AND post_status IN ('" . $args['status'] . "')";

        }

        // Check if start date is set.
        if( isset( $args['start'] ) ) {
            $query .= " AND post_date >= '" . $args['start'] . "'";
        }

        // Check if end date is set.
        if( isset( $args['end'] ) ) {
            $query .= " AND post_date <= '" . $args['end'] . "'";
        }

        // Return.
        return $query;

    }

}