<?php
/**
 * Store safeguards for copies of the store.
 *
 * Payments in test mode, subscription renewals held, WooCommerce webhooks
 * paused and search engines kept out. Everything works through read-time
 * filters: no gateway, subscription or Reading setting is ever changed, so
 * a copy that's promoted to live behaves exactly like the original.
 *
 * Filter names checked against the plugins' sources:
 *  - Stripe: woocommerce_stripe_settings['testmode'] (WC_Stripe_Mode)
 *  - WooPayments: wcpay_test_mode, wcpay_dev_mode (includes/core/class-mode.php)
 *  - PayPal Payments: woocommerce-ppcp-data-common['use_sandbox']
 *  - PayPal Standard: woocommerce_paypal_settings['testmode'] (WooCommerce core)
 *  - Subscriptions: woocommerce_subscriptions_is_duplicate_site (WCS_Staging)
 *  - Webhooks: woocommerce_webhook_should_deliver (WC_Webhook)
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Private;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;
use BuiltMightyKit\Admin\ui;

if( ! defined( 'ABSPATH' ) ) { exit; }

class store_safeguards {

    /**
     * Gateways that never charge a card.
     */
    const OFFLINE = [ 'bacs', 'cheque', 'cod' ];

    /**
     * Gateways Kit switches to test mode (so they stay available).
     */
    const TESTABLE = [ 'stripe', 'woocommerce_payments', 'ppcp-gateway', 'ppcp-credit-card-gateway', 'paypal' ];

    /**
     * Construct.
     */
    public function __construct() {

        add_filter( 'kit_safeguards', [ $this, 'list' ] );
        add_filter( 'kit_banner_held', [ $this, 'banner' ] );
        add_action( 'kit_admin_safeguards_groups', [ $this, 'admin_groups' ] );

        if( settings::is_on( 'kit_test_payments' ) ) {
            add_filter( 'option_woocommerce_stripe_settings', [ $this, 'stripe' ] );
            add_filter( 'option_woocommerce_paypal_settings', [ $this, 'testmode_yes' ] );
            add_filter( 'option_woocommerce-ppcp-data-common', [ $this, 'ppcp' ] );
            add_filter( 'wcpay_test_mode', '__return_true', PHP_INT_MAX );
            add_filter( 'wcpay_dev_mode', '__return_true', PHP_INT_MAX );
            add_filter( 'woocommerce_available_payment_gateways', [ $this, 'gateways' ], PHP_INT_MAX );
        }

        if( settings::is_on( 'kit_hold_renewals' ) ) {
            add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_true', PHP_INT_MAX );
        }

        if( settings::is_on( 'kit_pause_webhooks' ) ) {
            add_filter( 'woocommerce_webhook_should_deliver', [ $this, 'webhook' ], PHP_INT_MAX, 3 );
        }

        if( settings::is_on( 'kit_noindex' ) ) {
            add_filter( 'wp_robots', 'wp_robots_no_robots', PHP_INT_MAX );
            add_filter( 'robots_txt', [ $this, 'robots_txt' ], PHP_INT_MAX );
            add_action( 'send_headers', [ $this, 'robots_header' ] );
        }

    }

    // ------------------------------------------------------------------
    // Payments
    // ------------------------------------------------------------------

    /**
     * Stripe: test mode.
     */
    public function stripe( $value ) {
        if( is_array( $value ) ) $value['testmode'] = 'yes';
        return $value;
    }

    /**
     * PayPal Standard: sandbox.
     */
    public function testmode_yes( $value ) {
        if( is_array( $value ) ) $value['testmode'] = 'yes';
        return $value;
    }

    /**
     * PayPal Payments: sandbox.
     */
    public function ppcp( $value ) {
        if( is_array( $value ) ) $value['use_sandbox'] = true;
        return $value;
    }

    /**
     * Only gateways that can't charge a real card reach checkout.
     */
    public function gateways( $gateways ) {
        if( ! is_array( $gateways ) ) return $gateways;
        $allowed = array_merge( self::OFFLINE, self::TESTABLE, array_map( 'sanitize_key', (array) settings::get( 'kit_allowed_gateways' ) ) );
        foreach( array_keys( $gateways ) as $id ) {
            if( in_array( $id, $allowed, true ) || strpos( $id, 'ppcp-' ) === 0 ) continue;
            unset( $gateways[ $id ] );
        }
        return $gateways;
    }

    // ------------------------------------------------------------------
    // Webhooks and indexing
    // ------------------------------------------------------------------

    /**
     * Hold every webhook delivery; log the first per webhook per hour.
     */
    public function webhook( $should, $webhook, $arg ) {
        if( ! $should ) return $should;
        $id  = is_object( $webhook ) && method_exists( $webhook, 'get_id' ) ? (int) $webhook->get_id() : 0;
        $key = 'kit_wh_' . $id;
        if( ! get_transient( $key ) ) {
            set_transient( $key, 1, HOUR_IN_SECONDS );
            $name = is_object( $webhook ) && method_exists( $webhook, 'get_name' ) ? $webhook->get_name() : 'a webhook';
            $url  = is_object( $webhook ) && method_exists( $webhook, 'get_delivery_url' ) ? (string) wp_parse_url( $webhook->get_delivery_url(), PHP_URL_HOST ) : '';
            activity::log( 'safeguard', 'webhook_held', sprintf( 'Held "%s"%s', $name, $url ? ' to ' . $url : '' ), [ 'user_id' => 0, 'context' => [ 'webhook' => $id ] ] );
        }
        return false;
    }

    /**
     * robots.txt: disallow everything.
     */
    public function robots_txt( $output ) {
        return "User-agent: *\nDisallow: /\n";
    }

    /**
     * X-Robots-Tag on every response.
     */
    public function robots_header() {
        if( ! headers_sent() ) header( 'X-Robots-Tag: noindex, nofollow', true );
    }

    // ------------------------------------------------------------------
    // Reporting
    // ------------------------------------------------------------------

    /**
     * Add to the safeguards list (Overview, admin bar, widget).
     */
    public function list( $items ) {

        $woo = class_exists( 'WooCommerce' );

        $items['kit_test_payments'] = [
            'icon'   => 'card',
            'on'     => 'Payments in test mode',
            'off'    => 'Payments can charge real cards',
            'detail' => function() { return $this->payments_detail(); },
            'new'    => true,
            'optional' => ! $woo,
        ];

        if( class_exists( 'WC_Subscriptions' ) || class_exists( 'WCS_Staging' ) ) {
            $items['kit_hold_renewals'] = [
                'icon'   => 'repeat',
                'on'     => 'Renewals held',
                'off'    => 'Renewals charge saved cards',
                'detail' => function() { return $this->renewals_detail(); },
                'new'    => true,
            ];
        }

        if( $woo ) {
            $items['kit_pause_webhooks'] = [
                'icon'   => 'webhook',
                'on'     => 'Webhooks paused',
                'off'    => 'Webhooks are delivered',
                'detail' => function() {
                    $n = $this->webhook_count();
                    return $n ? sprintf( _n( '%d active webhook held', '%d active webhooks held', $n, 'builtmighty-kit' ), $n ) : 'No active webhooks';
                },
                'new'    => true,
            ];
        }

        $items['kit_noindex'] = [
            'icon'   => 'robot',
            'on'     => 'Hidden from search engines',
            'off'    => 'Search engines can index this copy',
            'detail' => function() { return 'noindex on every page, robots.txt disallows all'; },
            'new'    => true,
        ];

        return $items;

    }

    /**
     * Words for the banner.
     */
    public function banner( $held ) {
        if( settings::is_on( 'kit_test_payments' ) && class_exists( 'WooCommerce' ) ) $held[] = 'live payments';
        if( settings::is_on( 'kit_hold_renewals' ) && class_exists( 'WC_Subscriptions' ) ) $held[] = 'renewals';
        if( settings::is_on( 'kit_pause_webhooks' ) && class_exists( 'WooCommerce' ) ) $held[] = 'webhooks';
        return $held;
    }

    /**
     * Payment gateway detail line.
     */
    private function payments_detail(): string {
        if( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) return 'WooCommerce isn\'t active';
        $enabled = [];
        foreach( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
            if( isset( $gateway->enabled ) && $gateway->enabled === 'yes' ) $enabled[ $id ] = $gateway;
        }
        $test = array_intersect( array_keys( $enabled ), self::TESTABLE );
        $hidden = array_diff( array_keys( $enabled ), self::OFFLINE, self::TESTABLE, (array) settings::get( 'kit_allowed_gateways' ) );
        $hidden = array_filter( $hidden, function( $id ) { return strpos( $id, 'ppcp-' ) !== 0; } );
        if( ! $test && ! $hidden ) return 'No card gateways are turned on';
        $parts = [];
        if( $test ) $parts[] = count( $test ) . ' in test mode';
        if( $hidden ) $parts[] = count( $hidden ) . ' hidden at checkout';
        return implode( ', ', $parts );
    }

    /**
     * Renewals due in the next 7 days.
     */
    private function renewals_detail(): string {
        if( ! function_exists( 'as_get_scheduled_actions' ) ) return 'Renewals switch to manual on this copy';
        $ids = as_get_scheduled_actions( [
            'hook'         => 'woocommerce_scheduled_subscription_payment',
            'status'       => \ActionScheduler_Store::STATUS_PENDING,
            'date'         => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ),
            'date_compare' => '<=',
            'per_page'     => 500,
        ], 'ids' );
        $n = count( (array) $ids );
        return $n ? sprintf( _n( '%d renewal due this week won\'t charge', '%d renewals due this week won\'t charge', $n, 'builtmighty-kit' ), $n ) : 'No renewals due this week';
    }

    /**
     * Active webhooks.
     */
    private function webhook_count(): int {
        if( ! class_exists( '\WC_Data_Store' ) ) return 0;
        try {
            $ids = \WC_Data_Store::load( 'webhook' )->search_webhooks( [ 'status' => 'active', 'limit' => 100 ] );
            return count( (array) $ids );
        } catch( \Throwable $e ) {
            return 0;
        }
    }

    /**
     * Groups on the Safeguards page.
     */
    public function admin_groups() {
        $woo = class_exists( 'WooCommerce' );
        if( $woo ) {
            echo ui::group( 'safeguards', 'payments', 'Payments', 'Card gateways on this copy.', settings::is_on( 'kit_test_payments' ) ? ui::pill( 'Test mode', 'ok' ) : '', '<div class="bmk-group-foot"><span class="bmk-help">' . esc_html( $this->payments_detail() ) . '. Offline methods: ' . esc_html( implode( ', ', self::OFFLINE ) ) . '.</span></div>' ); // phpcs:ignore
        }
        if( class_exists( 'WC_Subscriptions' ) || class_exists( 'WCS_Staging' ) ) {
            echo ui::group( 'safeguards', 'subscriptions', 'Subscriptions', 'Renewal payments for WooCommerce Subscriptions.', settings::is_on( 'kit_hold_renewals' ) ? ui::pill( 'Held', 'ok' ) : '', '<div class="bmk-group-foot"><span class="bmk-help">' . esc_html( $this->renewals_detail() ) . '.</span></div>' ); // phpcs:ignore
        }
        if( $woo ) {
            echo ui::group( 'safeguards', 'webhooks', 'Webhooks', 'Messages WooCommerce sends to other systems when orders and customers change.', settings::is_on( 'kit_pause_webhooks' ) ? ui::pill( 'Paused', 'ok' ) : '' ); // phpcs:ignore
        }
        echo ui::group( 'safeguards', 'indexing', 'Search engines', 'Keep copies out of Google so customers never land on them.', settings::is_on( 'kit_noindex' ) ? ui::pill( 'Hidden', 'ok' ) : '' ); // phpcs:ignore
    }

}
