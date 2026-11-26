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
     * Two-factor for a user.
     *
     * wp kit tfa --user_id=1 --type=email          Email the user a setup link.
     * wp kit tfa --user_id=1 --type=cli            Show a setup key to add to an app here.
     * wp kit tfa --user_id=1 --type=cli --code=123456   Confirm with the app's current code.
     * wp kit tfa --user_id=1 --reset               Remove the app, backup codes and trusted browsers.
     *
     * @since   1.0.0
     * @version 6.0.0
     */
    public function tfa( $args, $assoc_args ) {

        $user = isset( $assoc_args['user_id'] ) ? get_user_by( 'id', (int) $assoc_args['user_id'] ) : false;
        if( ! $user ) \WP_CLI::error( 'Pass a valid --user_id=.' );

        $auth = new \BuiltMightyKit\Utility\authentication();

        if( isset( $assoc_args['reset'] ) ) {
            \WP_CLI::confirm( 'Remove two-factor for ' . $user->display_name . '?' );
            $auth->disable( $user );
            \WP_CLI::success( 'Two-factor reset for ' . $user->display_name . '. They will get emailed codes until they set up an app.' );
            return;
        }

        $type = $assoc_args['type'] ?? '';
        if( ! in_array( $type, [ 'email', 'cli' ], true ) ) \WP_CLI::error( 'Use --type=email or --type=cli, or --reset.' );

        if( $type === 'email' ) {
            $auth->send_setup( $user ) ? \WP_CLI::success( 'Setup link emailed to ' . $user->user_email . '.' ) : \WP_CLI::error( 'The email couldn\'t be sent.' );
            return;
        }

        if( ! isset( $assoc_args['code'] ) ) {
            $secret = $auth->pending_secret( $user );
            \WP_CLI::log( 'Add this key to an authenticator app (time-based, 6 digits):' );
            \WP_CLI::log( trim( chunk_split( $secret, 4, ' ' ) ) );
            \WP_CLI::log( 'Then run: wp kit tfa --user_id=' . $user->ID . ' --type=cli --code=<code>' );
            return;
        }

        $codes = $auth->confirm_app( $user, (string) $assoc_args['code'] );
        if( ! $codes ) \WP_CLI::error( 'That code didn\'t match. Use the code your app shows right now.' );
        \WP_CLI::success( 'Authenticator app set up for ' . $user->display_name . '. Backup codes (shown once):' );
        foreach( $codes as $code ) \WP_CLI::log( '  ' . $code );

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

        // wp_delete_user() lives in an admin include WP-CLI doesn't always load.
        require_once ABSPATH . 'wp-admin/includes/user.php';

        // Set total.
        $total = 1;

        // If single or multi-user.
        if( isset( $assoc_args['user_id'] ) ) {

            // Get user.
            $user = get_user_by( 'id', absint( $assoc_args['user_id'] ) );
            if( ! $user ) \WP_CLI::error( 'User #' . absint( $assoc_args['user_id'] ) . ' not found.' );

            // Confirm.
            \WP_CLI::confirm( 'Are you sure you want to remove ' . $user->display_name . '?' );

            // Remove user.
            wp_delete_user( $user->ID );

            // Success. Stop here so only this user is removed.
            \WP_CLI::success( 'User ' . $user->display_name . ' has been removed.' );
            return;

        } else {

            // Get total users.
            $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN ( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND ( meta_value LIKE '%\"subscriber\"%' OR meta_value LIKE '%\"customer\"%' ) )" );

            // Confirm.
            \WP_CLI::confirm( 'Are you sure you want to remove ' . $total . ' customers?' );

        }

        // Set progress.
        $progress = \WP_CLI\Utils\make_progress_bar( 'Removing Users', $total );

        // Loop.
        while( true ) {

            // Get customers.
            $users = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE ID IN ( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND ( meta_value LIKE '%\"subscriber\"%' OR meta_value LIKE '%\"customer\"%' ) ) LIMIT %d",
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

        if( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce is not active.' );
        }

        // Single order.
        if( isset( $assoc_args['order_id'] ) ) {
            $order = wc_get_order( absint( $assoc_args['order_id'] ) );
            if( ! $order ) \WP_CLI::error( 'Order #' . absint( $assoc_args['order_id'] ) . ' not found.' );
            \WP_CLI::confirm( 'Are you sure you want to remove order #' . $order->get_id() . '?' );
            $order->delete( true );
            \WP_CLI::success( 'Order #' . $order->get_id() . ' has been removed.' );
            return;
        }

        $query = $this->order_query( $assoc_args );
        $total = (int) wc_get_orders( $query + [ 'limit' => 1, 'paginate' => true, 'return' => 'ids' ] )->total;
        if( ! $total ) {
            \WP_CLI::success( 'No orders match.' );
            return;
        }
        \WP_CLI::confirm( 'Are you sure you want to remove ' . $total . ' orders?' );

        // Collect IDs first, so an order that can't be deleted is never selected again (no endless loop).
        $all      = wc_get_orders( $query + [ 'limit' => -1, 'return' => 'ids' ] );
        $progress = \WP_CLI\Utils\make_progress_bar( 'Removing Orders', count( $all ) );
        foreach( $all as $id ) {
            $order = wc_get_order( $id );
            if( $order ) $order->delete( true );
            $progress->tick();
        }
        $progress->finish();

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

        // Single order.
        if( isset( $assoc_args['order_id'] ) ) {
            \WP_CLI::confirm( 'Are you sure you want to process renewal for order #' . absint( $assoc_args['order_id'] ) . '?' );
            $payment = $this->payment( absint( $assoc_args['order_id'] ) );
            $payment['success'] ? \WP_CLI::success( $payment['message'] ) : \WP_CLI::error( $payment['message'] );
            return;
        }

        $query = [ 'status' => [ 'pending' ], 'type' => 'shop_order', 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ];
        $total = (int) wc_get_orders( $query + [ 'limit' => 1, 'paginate' => true ] )->total;
        \WP_CLI::confirm( 'Are you sure you want to check ' . $total . ' pending orders and charge the renewals among them?' );

        // Collect IDs first: charged orders leave the pending set, so paging the live query would skip orders.
        $all      = wc_get_orders( $query + [ 'limit' => -1 ] );
        $progress = \WP_CLI\Utils\make_progress_bar( 'Processing Renewals', count( $all ) );
        foreach( $all as $order_id ) {
            $payment = $this->payment( $order_id );
            \WP_CLI::log( '#' . $order_id . ': ' . $payment['message'] );
            $progress->tick();
        }

        // Finish progress.
        $progress->finish();

        // Success.
        \WP_CLI::success( 'Finished processing renewals for ' . $total . ' orders.' );

    }

    /**
     * Get login.
     * 
     * Get login URL for users.
     * 
     * wp kit get_login
     * 
     * @since   1.0.0
     */
    public function get_login( $args, $assoc_args ) {

        $slug = trim( (string) \BuiltMightyKit\Utility\settings::get( 'kit_login_url' ), '/' );
        if( ! \BuiltMightyKit\Utility\settings::is_on( 'kit_enable_login' ) || $slug === '' ) {
            \WP_CLI::success( 'Login: ' . site_url( '/wp-login.php' ) );
            return;
        }
        \WP_CLI::success( 'Login: ' . home_url( '/' . $slug . '/' ) );

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
        $order->delete_meta_data( '_requires_manual_renewal' );
        $order->save();

        // Get subscription.
        $subscription = wcs_get_subscription( $order->get_meta( '_subscription_renewal', true ) );

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
     * Order query arguments from --status, --start and --end.
     *
     * @since   6.0.0
     */
    private function order_query( $args ) {
        $query = [ 'type' => 'shop_order' ];
        if( ! empty( $args['status'] ) ) {
            $query['status'] = array_map( function( $s ) { return preg_replace( '/^wc-/', '', sanitize_key( trim( $s ) ) ); }, explode( ',', (string) $args['status'] ) );
        } else {
            $query['status'] = array_keys( wc_get_order_statuses() );
        }
        $start = ! empty( $args['start'] ) ? strtotime( (string) $args['start'] ) : false;
        $end   = ! empty( $args['end'] ) ? strtotime( (string) $args['end'] . ' 23:59:59' ) : false;
        if( $start && $end ) $query['date_created'] = $start . '...' . $end;
        elseif( $start ) $query['date_created'] = '>=' . $start;
        elseif( $end ) $query['date_created'] = '<=' . $end;
        return $query;
    }

}
